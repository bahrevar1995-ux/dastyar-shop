<?php
/**
 * HTTP Client — ارتباط سایت فروشنده با REST API مرکزی (dastyar/v1)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Client {

	public static function configured() {
		return (bool) ( get_option( 'dastyarc_central_url' ) && get_option( 'dastyarc_api_key' ) );
	}

	/**
	 * @param string $method GET|POST
	 * @param string $path   مسیر نسبت به /wp-json/dastyar/v1/ مثل products/12
	 * @return array|WP_Error
	 */
	public static function request( $method, $path, $body = null, $query = array() ) {
		if ( ! self::configured() ) {
			return new WP_Error( 'dastyarc_not_configured', 'تنظیمات اتصال به دستیار کامل نشده است.' );
		}
		$base = trailingslashit( get_option( 'dastyarc_central_url' ) ) . 'wp-json/dastyar/v1/';
		$url  = $base . ltrim( $path, '/' );
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'X-Dastyar-Key'  => get_option( 'dastyarc_api_key' ),
				'X-Dastyar-Site' => home_url(),
				'Content-Type'   => 'application/json',
				'Accept'         => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			DastyarC::log( 'API connection error: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$message = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : 'خطای API مرکزی (HTTP ' . $code . ')';
			// v1.8.0 — کد خطای مرکز حفظ می‌شود (مثلاً dastyar_locked ← رد لغو سفارش) تا مصرف‌کننده بتواند بر اساس آن تصمیم بگیرد
			$remote_code = is_array( $data ) && ! empty( $data['code'] ) ? sanitize_key( (string) $data['code'] ) : 'dastyarc_api_error';
			DastyarC::log( sprintf( 'API error %s %s: %s', $method, $path, $message ), 'error' );
			return new WP_Error( $remote_code, $message, array( 'status' => $code ) );
		}

		return is_array( $data ) ? $data : array();
	}

	/* شورتکات‌ها */

	public static function connector_update() {
		return self::request( 'GET', '/connector-update' ); // (v1.9.3) مانیفست به‌روزرسانی (نسخه + برچسب + لینک)
	}

	public static function ping() {
		// (v1.7.11) نسخه کانکتور به‌صورت پارامتر GET می‌رود تا «رادار نسخه‌ها» (ماژول ۴۵ کنسول مرکز) پر شود
		return self::request( 'GET', 'ping', null, array( 'version' => DASTYARC_VERSION ) );
	}

	/** (v1.7.11) اعلان فعال مرکز (ماژول‌های ۸/۴۰ کنسول) — GET /notice */
	public static function notice() {
		return self::request( 'GET', 'notice' );
	}

	public static function products( $query = array() ) {
		return self::request( 'GET', 'products', null, $query );
	}

	/** دسته‌بندی‌های مرکز (v1.6.0) — برای فیلتر دسته در صفحه «محصولات دستیار» (با کش ۱ ساعته) */
	public static function categories() {
		$cache = get_transient( 'dastyarc_central_cats' );
		if ( is_array( $cache ) && count( $cache ) > 0 ) {
			return $cache;
		}
		$res = self::request( 'GET', 'categories' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = (array) ( $res['data'] ?? array() );
		set_transient( 'dastyarc_central_cats', $data, HOUR_IN_SECONDS );
		return $data;
	}

	public static function product( $remote_id ) {
		return self::request( 'GET', 'products/' . (int) $remote_id );
	}

	public static function create_order( array $payload ) {
		return self::request( 'POST', 'orders', $payload );
	}

	public static function order( $remote_order_id ) {
		return self::request( 'GET', 'orders/' . (int) $remote_order_id );
	}

	/** v1.7.12 — همگام‌سازی وضعیت (لغو) از فروشگاه به مرکز */
	public static function update_order_status( $remote_order_id, $status, $reason = '' ) {
		return self::request( 'POST', 'orders/' . (int) $remote_order_id . '/status', array(
			'status' => (string) $status,
			'reason' => (string) $reason,
		) );
	}

	public static function refund_request( $remote_order_id, $amount, $reason ) {
		return self::request( 'POST', 'orders/' . (int) $remote_order_id . '/refund-request', array(
			'amount' => (float) $amount,
			'reason' => (string) $reason,
		) );
	}

	/* عودت و مرجوعی (RMA) */

	/** ارسال گزارش عودت به مرکز */
	public static function rma_create( array $payload ) {
		return self::request( 'POST', 'rma', $payload );
	}

	/** دریافت وضعیت یک درخواست عودت از مرکز */
	public static function rma_get( $rma_id ) {
		return self::request( 'GET', 'rma/' . (int) $rma_id );
	}

	/** لیست درخواست‌های عودت همین فروشنده در مرکز */
	public static function rma_list( $query = array() ) {
		return self::request( 'GET', 'rma', null, $query );
	}

	/** دریافت قوانین عودت + آدرس انبار مرجوعی از مرکز (قوانین فقط در مرکز نوشته می‌شود) */
	public static function rules() {
		return self::request( 'GET', 'rules' );
	}

	/** موجودی کیف پول + آخرین تراکنش‌ها (v1.7.0 — صفحه «کیف پول» در پیشخوان خود فروشنده) */
	public static function wallet() {
		return self::request( 'GET', 'wallet' );
	}

	/** ساخت سفارش شارژ کیف پول در مرکز و دریافت لینک پرداخت (v1.7.0) */
	public static function wallet_charge( $amount ) {
		return self::request( 'POST', 'wallet/charge', array( 'amount' => (float) $amount ) );
	}

	/** موجودی سبک دسته‌ای محصولات مرکزی — برای سینک سریع موجودی */
	public static function stock( array $remote_ids ) {
		$remote_ids = array_filter( array_map( 'absint', $remote_ids ) );
		if ( ! $remote_ids ) {
			return array( 'data' => array() );
		}
		return self::request( 'GET', 'stock', null, array( 'ids' => implode( ',', $remote_ids ) ) );
	}

	/** (v1.7.5 — مورد ۲) آخرین هشدارهای افزایش قیمت تامین از مرکز */
	public static function pricelog() {
		return self::request( 'GET', 'pricelog' );
	}

	/** (v1.7.5 — مورد ۹) لیست تیکت‌های خود فروشنده از مرکز */
	public static function tickets() {
		return self::request( 'GET', 'tickets' );
	}

	/** (v1.7.5 — مورد ۹) ثبت تیکت جدید در مرکز از پیشخوان سایت فروشنده */
	public static function ticket_create( $subject, $message ) {
		return self::request( 'POST', 'tickets', array(
			'subject' => (string) $subject,
			'message' => (string) $message,
		) );
	}
}
