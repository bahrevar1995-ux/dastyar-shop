<?php
/**
 * حذف افزونه Connector — محصولات واردشده و سفارش‌ها دست‌نخورده می‌مانند.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'dastyarc_sync_tick' );
wp_clear_scheduled_hook( 'dastyarc_reconcile_tick' );
flush_rewrite_rules();
