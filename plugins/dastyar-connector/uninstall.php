<?php
/**
 * حذف افزونه Connector — محصولات واردشده و سفارش‌ها دست‌نخورده می‌مانند.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'dastyarc_sync_tick' );
wp_clear_scheduled_hook( 'dastyarc_reconcile_tick' );
wp_clear_scheduled_hook( 'dastyarc_stock_tick' );

// (v1.12.0) پاک‌سازی کامل تنظیمات/کش افزونه — محصولات، سفارش‌ها و مرجوعی‌ها دست‌نخورده می‌مانند
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dastyarc_%'" ); // phpcs:ignore
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dastyarc_%' OR option_name LIKE '_transient_timeout_dastyarc_%'" ); // phpcs:ignore
flush_rewrite_rules();
