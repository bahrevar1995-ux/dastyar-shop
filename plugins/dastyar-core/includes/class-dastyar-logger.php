<?php
/**
 * لاگ درخواست‌های API در جدول اختصاصی + رویدادهای وب‌هوک در لاگ ووکامرس
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Logger {

	/** لاگ درخواست‌های ورودی REST (جدول dastyar_api_logs) */
	public function log_request( $vendor_id, $route, $method, $status, $message = '' ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'dastyar_api_logs',
			array(
				'vendor_id'  => (int) $vendor_id,
				'route'      => substr( (string) $route, 0, 190 ),
				'method'     => substr( (string) $method, 0, 10 ),
				'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'status'     => (int) $status,
				'message'    => substr( wp_strip_all_tags( (string) $message ), 0, 500 ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/** لاگ رویدادهای داخلی (وب‌هوک، سینک…) — استفاده از WC Logger استاندارد */
	public function log_event( $message, $level = 'info' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array( 'source' => 'dastyar-core' ) );
		}
	}

	/** بازیابی لاگ‌ها برای نمایش در پیشخوان */
	public function get_logs( $limit = 100, $vendor_id = 0 ) {
		global $wpdb;
		$where  = $vendor_id ? $wpdb->prepare( 'WHERE vendor_id = %d', $vendor_id ) : '';
		$limit  = max( 1, min( 500, (int) $limit ) );
		$table  = $wpdb->prefix . 'dastyar_api_logs';
		return $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT {$limit}" ); // phpcs:ignore
	}
}
