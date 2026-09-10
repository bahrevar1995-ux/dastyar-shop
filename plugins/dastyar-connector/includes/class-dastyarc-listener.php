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
		$allowed = wc_get_order_statuses();
		if ( ! isset( $allowed[ 'wc-' . $status ] ) ) {
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
		$order->update_meta_data( '_dastyar_tracking_code', $tracking_code );
		$order->update_meta_data( '_dastyar_tracking_carrier', $carrier );
		$order->add_order_note( sprintf( 'کد رهگیری از مرکز دریافت شد: %s%s', $tracking_code, $carrier ? ' (' . $carrier . ')' : '' ) );
		$order->save();

		if ( 'yes' === get_option( 'dastyarc_tracking_completes', 'yes' ) && 'processing' === $order->get_status() ) {
			$order->update_status( 'completed', 'سفارش توسط مرکز ارسال و کد رهگیری ثبت شد.' );
		}
		do_action( 'dastyarc_tracking_received', $order->get_id(), $tracking_code, $carrier );
	}

	/** مرجوعی — بر پایه WooCommerce Refund در سایت فروشنده */
	public static function apply_refund( $local_order_id, $amount, $reason ) {
		$order = self::find_local_order( $local_order_id );
		if ( ! $order || $amount <= 0 ) {
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
