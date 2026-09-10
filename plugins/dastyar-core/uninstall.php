<?php
/**
 * حذف افزونه — داده‌های سفارش/محصول/کاربر دست‌نخورده می‌مانند.
 * فقط زمان‌بندی‌ها و Endpoint ها پاک می‌شوند؛ جداول و متاها برای حفظ سوابق باقی می‌مانند.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'dastyar_push_process' );

$endpoint_options = array(
	'dastyar_push_queue',
);
foreach ( $endpoint_options as $opt ) {
	delete_option( $opt );
}
flush_rewrite_rules();
