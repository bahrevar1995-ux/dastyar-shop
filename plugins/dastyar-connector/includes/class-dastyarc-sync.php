<?php
/**
 * همگام‌سازی دوره‌ای (Pull) از مرکز — مکمل وب‌هوک‌ها:
 *  - هر ساعت: محصولاتی که بعد از آخرین سینک تغییر کرده‌اند
 *  - روزی دو بار: وضعیت/کد رهگیری سفارش‌های در جریان
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Sync {

	/** سینک محصولات تغییرکرده (فقط محصولاتی که قبلاً به فروشگاه اضافه شده‌اند) */
	public function run() {
		if ( ! DastyarC_Client::configured() ) {
			return;
		}
		$after    = (int) get_option( 'dastyarc_last_sync', 0 );
		$max_page = $after ? 10 : 3; // اجرای اول محدود باشد
		$synced   = 0;

		for ( $page = 1; $page <= $max_page; $page++ ) {
			$result = DastyarC_Client::products( array(
				'modified_after' => $after,
				'page'           => $page,
				'per_page'       => 50,
				'order'          => 'asc',
			) );
			if ( is_wp_error( $result ) ) {
				DastyarC::log( 'Sync failed p' . $page . ': ' . $result->get_error_message(), 'error' );
				return;
			}
			$items = (array) ( $result['data'] ?? array() );
			foreach ( $items as $item ) {
				if ( DastyarC_Importer::local_id( (int) $item['id'] ) ) {
					$res = DastyarC_Importer::save( $item );
					if ( ! is_wp_error( $res ) ) {
						$synced++;
					}
				}
			}
			if ( count( $items ) < 50 ) {
				break;
			}
		}

		update_option( 'dastyarc_last_sync', time() );
		if ( $synced ) {
			DastyarC::log( sprintf( 'Sync completed: %d product(s) updated', $synced ) );
		}
	}

	/**
	 * سینک سریع موجودی (v1.4.0) — اگر محصولی در مرکز ناموجود/موجود شود، حداکثر ظرف چند دقیقه
	 * روی فروشگاه اعمال می‌شود؛ مستقل از post_modified و مکمل وب‌هوک‌هاست.
	 *
	 * @param bool $manual اجرای دستی از تنظیمات (گذر از قفل خاموش‌بودن سینک خودکار؟ خیر — فقط گزارش)
	 * @return int تعداد محصولاتی که موجودی‌اشان تغییر کرد
	 */
	public function sync_stock( $manual = false ) {
		if ( ! DastyarC_Client::configured() ) {
			return 0;
		}
		if ( 'yes' !== get_option( 'dastyarc_stock_sync', 'yes' ) ) {
			// سینک خودکار موجودی توسط فروشنده خاموش شده — حتی اجرای دستی هم اعمال نمی‌شود
			return -1;
		}

		// نگاشت شناسه مرکزی → محصول محلی
		$local_ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'meta_key'       => '_dastyar_remote_id',
			'meta_compare'   => 'EXISTS',
		) );
		if ( ! $local_ids ) {
			update_option( 'dastyarc_last_stock_sync', time() );
			return 0;
		}

		$map = array(); // remote_id => local_id
		foreach ( $local_ids as $lid ) {
			$rid = (int) get_post_meta( $lid, '_dastyar_remote_id', true );
			if ( $rid ) {
				$map[ $rid ] = (int) $lid;
			}
		}

		$changed = 0;
		foreach ( array_chunk( array_keys( $map ), 100 ) as $chunk ) {
			$result = DastyarC_Client::stock( $chunk );
			if ( is_wp_error( $result ) ) {
				DastyarC::log( 'Stock sync failed: ' . $result->get_error_message(), 'error' );
				break;
			}
			$data     = (array) ( $result['data'] ?? array() );
			$received = array();
			foreach ( $data as $item ) {
				$rid = (int) ( $item['id'] ?? 0 );
				if ( $rid && isset( $map[ $rid ] ) ) {
					$received[ $rid ] = true;
					if ( DastyarC_Importer::apply_stock_only( $map[ $rid ], $item ) ) {
						$changed++;
					}
				}
			}

			// اجرای رده‌بندی مرکز (v1.5.0): محصولی که مرکز در پاسخ برنگردانده (خارج از رده
			// یا حذف/غیرفعال شده) دیگر نباید در فروشگاه دیده شود → پیش‌نویس می‌شود.
			foreach ( $chunk as $rid ) {
				if ( isset( $received[ $rid ] ) || ! isset( $map[ $rid ] ) ) {
					continue;
				}
				if ( 'yes' !== get_option( 'dastyarc_unpublish_when_hidden', 'yes' ) ) {
					continue;
				}
				$local = wc_get_product( $map[ $rid ] );
				if ( $local && 'draft' !== $local->get_status() ) {
					$local->set_status( 'draft' );
					$local->save();
					update_post_meta( $map[ $rid ], '_dastyar_hidden_by_central', current_time( 'mysql' ) );
					DastyarC::log( sprintf( 'Product #%d (remote #%d) hidden by central → drafted', $map[ $rid ], $rid ) );
					$changed++;
				}
			}
		}

		update_option( 'dastyarc_last_stock_sync', time() );
		if ( $changed ) {
			DastyarC::log( sprintf( 'Stock sync: %d product(s) stock updated', $changed ) );
		}
		return $changed;
	}

	/** بازیابی وضعیت و کد رهگیری سفارش‌های در جریان (جبران وب‌هوک‌های از دست رفته) */
	public function reconcile_orders() {
		if ( ! DastyarC_Client::configured() ) {
			return;
		}
		// (v1.9.2) «سفارش ترکیبی» و «ارسال شده به دستیارشاپ» هم در فهرست سینک دوره‌ای آورده شدند تا
		// به‌روزرسانی‌های مرکز (تکمیل / تعلیق) برایشان هم از دست نرود.
		$orders = wc_get_orders( array(
			'limit'        => 8,
			'status'       => array( 'processing', 'on-hold', 'dastyar-sent', 'dastyar-mixed' ),
			'meta_key'     => '_dastyarc_remote_order_id',
			'meta_compare' => 'EXISTS',
			'orderby'      => 'date',
			'order'        => 'ASC',
		) );
		foreach ( $orders as $order ) {
			$remote = (int) $order->get_meta( '_dastyarc_remote_order_id' );
			$info   = DastyarC_Client::order( $remote );
			if ( is_wp_error( $info ) || empty( $info['status'] ) ) {
				continue;
			}
			DastyarC_Listener::apply_order_status( $order->get_id(), (string) $info['status'] );
			if ( ! empty( $info['tracking_code'] ) ) {
				DastyarC_Listener::apply_tracking( $order->get_id(), (string) $info['tracking_code'], (string) ( $info['carrier'] ?? '' ) );
			}
		}
	}
}
