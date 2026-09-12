<?php
/**
 * موتور Push مرکز → سایت فروشنده
 *
 * رویدادها:
 *  - products.updated : تغییر محصولات (موجودی، تصویر، توضیحات، ویژگی…) — صف ۵ دقیقه‌ای
 *  - product.deleted  : حذف محصول
 *  - order.status     : تغییر وضعیت سفارش مرکزی
 *  - order.tracking   : ثبت کد رهگیری
 *  - order.refund     : انجام مرجوعی (WooCommerce Refund)
 *
 * امضا: X-Dastyar-Signature = HMAC-SHA256(body, API Key فروشنده)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Webhooks {

	const QUEUE = 'dastyar_push_queue';

	public function __construct() {
		add_action( 'woocommerce_new_product', array( $this, 'queue_product' ) );
		add_action( 'woocommerce_update_product', array( $this, 'queue_product' ) );
		add_action( 'woocommerce_product_set_stock', array( $this, 'queue_product_from_obj' ) );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'queue_variation_parent' ) );
		// تغییر «وضعیت موجودی» (موجود/ناموجود) — پوشش تغییراتی که set_stock رخ نمی‌دهد
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'queue_product_from_stock_status' ), 10, 3 );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'queue_product_from_stock_status' ), 10, 3 );
		add_action( 'wp_trash_post', array( $this, 'queue_product_delete' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'push_order_status' ), 20, 4 );
		add_action( 'woocommerce_order_refunded', array( $this, 'push_refund' ), 20, 2 );
	}

	/* ------------------------------------------------------------------
	 * صف تغییرات محصول
	 * ---------------------------------------------------------------- */

	public function queue_product( $product_id ) {
		$this->enqueue( (int) $product_id );
	}

	public function queue_product_from_obj( $product ) {
		if ( $product instanceof WC_Product ) {
			$this->enqueue( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() );
		}
	}

	public function queue_variation_parent( $variation ) {
		$this->queue_product_from_obj( $variation );
	}

	/** تغییر وضعیت موجودی (instock/outofstock) — محصول والد در صف قرار می‌گیرد */
	public function queue_product_from_stock_status( $product_id, $stock_status = '', $product = null ) {
		if ( $product instanceof WC_Product ) {
			$this->queue_product_from_obj( $product );
			if ( ! $product->get_parent_id() ) {
				$this->maybe_notify_outofstock( $product->get_id(), (string) $stock_status );
			}
			return;
		}
		$parent_id = (int) wp_get_post_parent_id( (int) $product_id );
		$this->enqueue( $parent_id ? $parent_id : (int) $product_id );
		if ( ! $parent_id ) {
			$this->maybe_notify_outofstock( (int) $product_id, (string) $stock_status );
		}
	}

	/**
	 * هشدار ایمیلی ناموجودی ناگهانی به مدیر مرکز (v1.5.0) —
	 * برای هر محصول حداکثر یک‌بار در ۲۴ ساعت (جلوگیری از اسپم).
	 */
	public function maybe_notify_outofstock( $product_id, $stock_status ) {
		if ( 'outofstock' !== (string) $stock_status ) {
			return;
		}
		if ( 'yes' !== get_option( 'dastyar_oos_notify', 'yes' ) ) {
			return;
		}
		$tkey = 'dastyar_oos_mail_' . (int) $product_id;
		if ( get_transient( $tkey ) ) {
			return;
		}
		set_transient( $tkey, 1, DAY_IN_SECONDS );
		$name = get_the_title( $product_id ) ?: ( '#' . (int) $product_id );
		wp_mail(
			get_option( 'admin_email' ),
			'⚠ ناموجود شدن کالا در انبار دستیار شاپ',
			sprintf(
				"کالای «%s» (شناسه %d) هم‌اکنون در سایت مرکزی ناموجود شد.\nفروشگاه‌های متصل با سینک خودکار جلو می‌روند و این کالا در آن‌ها هم ناموجود می‌شود.\nمشاهده محصول: %s",
				$name,
				(int) $product_id,
				admin_url( 'post.php?post=' . (int) $product_id . '&action=edit' )
			)
		);
	}

	public function queue_product_delete( $post_id ) {
		if ( 'product' === get_post_type( $post_id ) ) {
			$queue = get_option( self::QUEUE, array() );
			$queue['deleted'][ (int) $post_id ] = (int) $post_id;
			update_option( self::QUEUE, $queue, false );
		}
	}

	protected function enqueue( $product_id ) {
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}
		$queue = get_option( self::QUEUE, array() );
		$queue['updated'][ $product_id ] = $product_id;
		update_option( self::QUEUE, $queue, false );
	}

	/** پردازش صف توسط WP-Cron و ارسال دسته‌ای به تمام فروشنده‌های متصل */
	public function process_queue() {
		$queue = get_option( self::QUEUE, array() );
		if ( empty( $queue['updated'] ) && empty( $queue['deleted'] ) ) {
			return;
		}
		update_option( self::QUEUE, array(), false );

		$vendors = Dastyar::instance()->vendors->connected_vendors();
		foreach ( $vendors as $uid ) {
			if ( ! empty( $queue['updated'] ) ) {
				// رده‌بندی فروشندگان: پوش فقط محصولات مجاز رده (v1.5.0)
				$allowed_updates = Dastyar_Tiers::filter_ids( $uid, (array) $queue['updated'] );
				if ( $allowed_updates ) {
					$this->send( $uid, 'products.updated', array( 'product_ids' => array_values( $allowed_updates ) ), false );
				}
			}
			foreach ( (array) ( $queue['deleted'] ?? array() ) as $pid ) {
				$this->send( $uid, 'product.deleted', array( 'product_id' => $pid ), false );
			}
		}
	}

	/* ------------------------------------------------------------------
	 * رویدادهای سفارش
	 * ---------------------------------------------------------------- */

	public function push_order_status( $order_id, $from, $to, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order || ! $order->get_meta( '_dastyar_remote_ref' ) ) {
			return;
		}
		// فقط وضعیت‌های معنادار برای فروشنده ارسال می‌شود (مثلاً pending دوباره ارسال نمی‌شود)
		// (v1.11.1) «تحویل پست شده» و «تعلیق شده» به فهرست وب‌هوک‌ها افزوده شد تا به محض تغییر، سایت فروشنده
		// مطلع شود (سفارش‌های ترکیبی به سینک دوره‌ای تکیه داشتند — سریع‌تر).
		$allowed = (array) apply_filters( 'dastyar_push_statuses', array( 'processing', 'posted', 'completed', 'on-hold', 'cancelled', 'refunded', Dastyar_Statuses::SUSPENDED ) );
		if ( ! in_array( $to, $allowed, true ) ) {
			return;
		}
		$vendor_id = (int) $order->get_meta( '_dastyar_vendor_id' );
		$this->send( $vendor_id, 'order.status', array(
			'remote_order_id' => (int) $order->get_meta( '_dastyar_remote_order_id' ),
			'status'          => $to,
		), true, $order );
	}

	/** ثبت کد رهگیری و ارسال آن به سایت فروشنده (از متاباکس سفارش سایت مرکزی صدا زده می‌شود) */
	public function push_tracking( WC_Order $order ) {
		$vendor_id = (int) $order->get_meta( '_dastyar_vendor_id' );
		if ( ! $vendor_id ) {
			return;
		}
		$this->send( $vendor_id, 'order.tracking', array(
			'remote_order_id' => (int) $order->get_meta( '_dastyar_remote_order_id' ),
			'tracking_code'   => (string) $order->get_meta( '_dastyar_tracking_code' ),
			'carrier'         => (string) $order->get_meta( '_dastyar_tracking_carrier' ),
			'status'          => $order->get_status(),
		), true, $order );
	}

	/** ارسال قوانین عودت + آدرس انبار مرجوعی به همه فروشندگان متصل (بعد از ذخیره تنظیمات مرکز) */
	public function push_rma_rules() {
		$data = array(
			'rules'          => (string) get_option( 'dastyar_rma_rules', '' ),
			'return_address' => (string) get_option( 'dastyar_return_address', '' ),
		);
		foreach ( Dastyar::instance()->vendors->connected_vendors() as $uid ) {
			$this->send( $uid, 'rma.rules', $data, false );
		}
	}

	/** (v1.11.2) انتشار فوری مانیفست به‌روزرسانی کانکتور به همه فروشندگان متصل (بعد از ذخیره تنظیمات مرکز)
	 * تا سایت فروشنده بدون انتظار برای سینک دوره‌ای، همان لحظه اعلان را در نوار ابزار پیشخوانش نمایش دهد */
	public function push_connector_update() {
		if ( ! class_exists( 'Dastyar_Rest' ) ) {
			return;
		}
		$m = Dastyar_Rest::update_manifest();
		foreach ( Dastyar::instance()->vendors->connected_vendors() as $uid ) {
			$this->send( $uid, 'connector.update', (array) $m, false );
		}
	}

	public function push_refund( $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( '_dastyar_remote_ref' ) ) {
			return;
		}
		$refund = wc_get_order( $refund_id );
		$this->send( (int) $order->get_meta( '_dastyar_vendor_id' ), 'order.refund', array(
			'remote_order_id' => (int) $order->get_meta( '_dastyar_remote_order_id' ),
			'amount'          => $refund ? abs( (float) $refund->get_total() ) : 0,
			'reason'          => $refund ? (string) $refund->get_reason() : '',
		), true, $order );
	}

	/* ------------------------------------------------------------------
	 * ارسال وب‌هوک
	 * ---------------------------------------------------------------- */

	/**
	 * @return bool|WP_Error
	 */
	public function send( $vendor_id, $event, array $data, $blocking = true, $order = null ) {
		$vendors = Dastyar::instance()->vendors;
		$site    = $vendors->site_url( $vendor_id );
		$key     = $vendors->raw_key( $vendor_id );
		if ( ! $site || ! $key ) {
			return false;
		}

		$url  = trailingslashit( $site ) . 'wp-json/dastyar-connect/v1/webhook';
		$body = wp_json_encode( array(
			'event' => $event,
			'data'  => $data,
			'at'    => current_time( 'c' ),
		) );

		$response = wp_remote_post( $url, array(
			'timeout'  => $blocking ? 15 : 2,
			'blocking' => $blocking,
			'headers'  => array(
				'Content-Type'         => 'application/json',
				'X-Dastyar-Signature'  => hash_hmac( 'sha256', $body, $key ),
				'X-Dastyar-Event'      => $event,
			),
			'body'     => $body,
		) );

		if ( ! $blocking ) {
			return true; // ارسال غیرمسدودکننده — جبران با سینک دوره‌ای (Pull) سمت فروشنده انجام می‌شود
		}

		$error = null;
		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
		} elseif ( (int) wp_remote_retrieve_response_code( $response ) >= 400 ) {
			$error = 'HTTP ' . wp_remote_retrieve_response_code( $response );
		}

		if ( $error ) {
			Dastyar::instance()->logger->log_event( sprintf( 'Webhook failed [%s] vendor %d: %s', $event, $vendor_id, $error ), 'error' );
			if ( $order instanceof WC_Order ) {
				$order->add_order_note( sprintf( 'ارسال وب‌هوک «%s» به فروشگاه فروشنده ناموفق بود: %s', $event, $error ) );
			}
			return new WP_Error( 'dastyar_webhook_failed', $error );
		}

		Dastyar::instance()->logger->log_event( sprintf( 'Webhook sent [%s] vendor %d', $event, $vendor_id ) );
		return true;
	}
}
