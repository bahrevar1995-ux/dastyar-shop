<?php
/**
 * شنونده وب‌هوک‌های مرکز — namespace: dastyar-connect/v1
 * اعتبارسنجی: X-Dastyar-Signature = HMAC-SHA256 بدنه خام با API Key فروشنده
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Listener {

	public function register_routes() {
		register_rest_route( 'dastyar-connect/v1', '/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => '__return_true', // احراز با امضای HMAC در بدنه‌ی متد انجام می‌شود
		) );
	}

	public function handle( WP_REST_Request $request ) {
		$body = $request->get_body();
		$sig  = (string) $request->get_header( 'x-dastyar-signature' );
		$key  = (string) get_option( 'dastyarc_api_key' );

		if ( ! $key || ! hash_equals( hash_hmac( 'sha256', $body, $key ), $sig ) ) {
			DastyarC::log( 'Webhook rejected: bad signature', 'warning' );
			return new WP_Error( 'dastyarc_bad_sig', 'امضا نامعتبر است.', array( 'status' => 401 ) );
		}

		$payload = json_decode( $body, true );
		$event   = (string) ( $payload['event'] ?? '' );
		$data    = (array) ( $payload['data'] ?? array() );

		switch ( $event ) {

			case 'products.updated':
				foreach ( array_map( 'intval', (array) ( $data['product_ids'] ?? array() ) ) as $remote_id ) {
					if ( DastyarC_Importer::local_id( $remote_id ) ) {
						DastyarC_Importer::import_remote( $remote_id );
					}
				}
				break;

			case 'product.updated':
				$remote_id = (int) ( $data['product_id'] ?? 0 );
				if ( $remote_id && DastyarC_Importer::local_id( $remote_id ) ) {
					DastyarC_Importer::import_remote( $remote_id );
				}
				break;

			case 'product.deleted':
				DastyarC_Importer::apply_deleted( (int) ( $data['product_id'] ?? 0 ) );
				break;

			case 'product.import':
				// درخواست «افزودن به فروشگاه من» از صفحه محصول مرکز (v1.5.1) — همان جریان استاندارد ایمپورت، منتشرشده
				$remote_id = (int) ( $data['product_id'] ?? 0 );
				if ( ! $remote_id ) {
					return new WP_Error( 'dastyarc_bad_import', 'شناسه محصول نامعتبر است.', array( 'status' => 400 ) );
				}
				$result = DastyarC_Importer::import_remote( $remote_id, 'publish' );
				if ( is_wp_error( $result ) ) {
					return new WP_Error( 'dastyarc_import_failed', $result->get_error_message(), array( 'status' => 502 ) );
				}
				break;

			case 'order.status':
				self::apply_order_status( (int) ( $data['remote_order_id'] ?? 0 ), (string) ( $data['status'] ?? '' ) );
				break;

			case 'order.tracking':
				self::apply_tracking(
					(int) ( $data['remote_order_id'] ?? 0 ),
					(string) ( $data['tracking_code'] ?? '' ),
					(string) ( $data['carrier'] ?? '' )
				);
				break;

			case 'order.refund':
				self::apply_refund(
					(int) ( $data['remote_order_id'] ?? 0 ),
					(float) ( $data['amount'] ?? 0 ),
					(string) ( $data['reason'] ?? '' )
				);
				break;

			case 'rma.status':
				// تغییر وضعیت مرجوعی در مرکز → اعمال روی درخواست محلی (شناسایی با local_rma_id)
				DastyarC_Rma::apply_remote( (int) ( $data['local_rma_id'] ?? 0 ), $data );
				break;

			case 'rma.rules':
				// قوانین عودت + آدرس انبار مرجوعی از مرکز رسید — فقط مرکز قوانین را تعیین می‌کند
				DastyarC_Rma::apply_rules( $data );
				break;

			case 'connector.update':
				// (v1.9.3) مانیفست به‌روزرسانی کانکتور از مرکز بلافاصله پس از تنظیم مدیر مرکز رسید
				DastyarC_Updater::handle_push( $data );
				break;

			default:
				return new WP_Error( 'dastyarc_unknown_event', 'رویداد ناشناخته: ' . $event, array( 'status' => 400 ) );
		}

		DastyarC::log( 'Webhook handled: ' . $event );
		return rest_ensure_response( array( 'ok' => true, 'event' => $event ) );
	}

	/* ------------------------------------------------------------------
	 * هندلرها (برای استفاده مجدد در سینک دوره‌ای public هستند)
	 * ---------------------------------------------------------------- */

	public static function find_local_order( $local_order_id ) {
		$order = wc_get_order( (int) $local_order_id );
		if ( ! $order || ! $order->get_meta( '_dastyarc_remote_order_id' ) ) {
			return null;
		}
		return $order;
	}

	/** برگشت وضعیت سفارش از مرکز به سفارش فروشنده */
	public static function apply_order_status( $local_order_id, $status ) {
		$order = self::find_local_order( $local_order_id );
		if ( ! $order || '' === $status ) {
			return;
		}

		// (v1.9.1) «تعلیق شده» از مرکز: اتصال سفارش با مرکز قطع می‌شود و مدیریت کاملش به فروشنده واگذار می‌گردد.
		// از این لحظه هیچ به‌روزرسانی خودکاری (وضعیت/کد رهگیری/مرجوعی) از مرکز برای این سفارش اعمال نمی‌شود.
		if ( 'dastyar-suspended' === $status ) {
			if ( DastyarC_Orders::is_link_broken( $order ) ) {
				return;
			}
			$order->update_meta_data( '_dastyarc_link_broken', 1 );
			$order->update_meta_data( '_dastyarc_part_center', 'cancelled' );
			$order->add_order_note( 'مرکز این سفارش را «تعلیق شده» کرد؛ اتصال با مرکز قطع شد و مدیریت کامل سفارش (وضعیت و کد رهگیری) به فروشنده واگذار شد. این سفارش جزو سفارش‌های موفق مرکز شمرده نمی‌شود ولی دستیار نیز آن را لغو نکرده است.' );
			$order->update_status( 'dastyar-suspended', 'وضعیت ابلاغ‌شده از مرکز: تعلیق شده' );
			return;
		}

		// سفارش «تعلیق‌شده و لینک‌شکسته»: هر به‌روزرسانی بعدی مرکز نادیده گرفته می‌شود
		if ( DastyarC_Orders::is_link_broken( $order ) ) {
			return;
		}

		$allowed = wc_get_order_statuses();
		if ( ! isset( $allowed[ 'wc-' . $status ] ) ) {
			return;
		}

		// (v1.9.2) «سفارش ترکیبی»: وضعیت مرکز فقط «بخش دستیار» را جلو می‌برد؛ وضعیت کلی سفارش دست‌نخورده می‌ماند
		// و فروشنده همچنان می‌بیند که بخشی از سفارش با خودش است.
		if ( $order->get_meta( '_dastyarc_mixed' ) ) {
			$map = array(
				'processing'   => 'processing',
				'dastyar-sent' => 'processing',
				'posted'       => 'posted',
				'completed'    => 'completed',
				'cancelled'    => 'cancelled',
			);
			if ( ! isset( $map[ $status ] ) ) {
				return; // وضعیت‌های نامرتبط (pending/failed/on-hold از مرکز) روی سفارش ترکیبی اعمال نمی‌شود
			}
			$part = $map[ $status ];
			if ( (string) $order->get_meta( '_dastyarc_part_center' ) === $part ) {
				return;
			}
			$order->update_meta_data( '_dastyarc_part_center', $part );
			$order->add_order_note( sprintf( 'بخش دستیار «سفارش ترکیبی» به‌روزرسانی شد: %s', DastyarC_Orders::part_label( $part ) ) );
			$order->save();
			DastyarC_Orders::maybe_complete_mixed( $order );
			return;
		}

		// اگر فروشنده سفارش را «ارسال شده به دستیارشاپ» کرده، وضعیت processing مرکزی نباید آن را به عقب برگرداند
		if ( 'dastyar-sent' === $order->get_status() && 'processing' === $status ) {
			return;
		}
		if ( $order->get_status() !== $status ) {
			$order->update_status( $status, sprintf( 'وضعیت سفارش از سایت مرکزی به‌روزرسانی شد: %s', wc_get_order_status_name( $status ) ) );
		}
	}

	/** برگشت کد رهگیری از مرکز → ثبت در سفارش فروشنده + یادداشت */
	public static function apply_tracking( $local_order_id, $tracking_code, $carrier = '' ) {
		$order = self::find_local_order( $local_order_id );
		if ( ! $order || '' === $tracking_code ) {
			return;
		}
		if ( DastyarC_Orders::is_link_broken( $order ) ) {
			return; // (v1.9.2) تعلیق‌شده‌ها دیگر خودکار به‌روز نمی‌شوند
		}

		// (v1.9.2) سفارش ترکیبی: کد رهگیری مرکز فقط «کد رهگیری بخش دستیار» است؛ کل سفارش کامل نمی‌شود.
		$is_mixed = (bool) $order->get_meta( '_dastyarc_mixed' );
		$order->update_meta_data( '_dastyar_tracking_code', $tracking_code );
		$order->update_meta_data( '_dastyar_tracking_carrier', $carrier );
		$order->add_order_note( sprintf(
			'%s کد رهگیری: %s%s',
			$is_mixed ? 'بخش دستیار (مرکز) —' : 'کد رهگیری از مرکز دریافت شد:',
			$tracking_code,
			$carrier ? ' (' . $carrier . ')' : ''
		) );

		if ( $is_mixed ) {
			// بخش دستیار پیشرفت می‌کند؛ کل سفارش تا تکمیل بخش فروشنده در حالت «ترکیبی» می‌ماند
			$part = ( 'yes' === get_option( 'dastyarc_tracking_completes', 'yes' ) ) ? 'completed' : 'posted';
			$order->update_meta_data( '_dastyarc_part_center', $part );
			$order->add_order_note( 'بخش دستیار سفارش ترکیبی: ' . DastyarC_Orders::part_label( $part ) );
			$order->save();
			DastyarC_Orders::maybe_complete_mixed( $order );
			do_action( 'dastyarc_tracking_received', $order->get_id(), $tracking_code, $carrier );
			return;
		}

		$order->save();

		if ( 'yes' === get_option( 'dastyarc_tracking_completes', 'yes' ) && 'processing' === $order->get_status() ) {
			$order->update_status( 'completed', 'سفارش توسط مرکز ارسال و کد رهگیری ثبت شد.' );
		}
		do_action( 'dastyarc_tracking_received', $order->get_id(), $tracking_code, $carrier );
	}

	/** مرجوعی — بر پایه WooCommerce Refund در سایت فروشنده */
	public static function apply_refund( $local_order_id, $amount, $reason ) {
		$order = self::find_local_order( $local_order_id );
		if ( ! $order || $amount <= 0 || DastyarC_Orders::is_link_broken( $order ) ) {
			return;
		}
		$refund = wc_create_refund( array(
			'order_id'       => $order->get_id(),
			'amount'         => $amount,
			'reason'         => 'مرجوعی تأییدشده توسط مرکز: ' . $reason,
			'refund_payment' => false, // برگشت وجه به درگاه به‌صورت دستی/طبق فرایند فروشگاه
			'restock_items'  => false,
		) );
		if ( is_wp_error( $refund ) ) {
			$order->add_order_note( 'خطا در ایجاد WooCommerce Refund: ' . $refund->get_error_message() );
			return;
		}
		$order->add_order_note( sprintf( 'مرجوعی مرکز اعمال شد (Refund #%d — مبلغ: %s).', $refund->get_id(), wc_price( $amount ) ) );
	}
}
