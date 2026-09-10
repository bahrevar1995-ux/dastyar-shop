<?php
/**
 * حذف تمیز کنسول مدیریت مرکز.
 * گزینه‌های خود افزونه پاک می‌شوند؛ متاهای کاربران (_da_*) دست نمی‌خورند تا
 * در صورت نصب مجدد، یادداشت‌ها/برچسب‌ها/تسویه‌ها حفظ بمانند. هیچ داده‌ی افزونه مرکز حذف نمی‌شود.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'da_cron_offline_check' );

delete_option( 'dastyar_admin_modules' );
delete_option( 'dastyar_admin_broadcasts' );
delete_option( 'dastyar_admin_ledger' );
foreach ( array( 'inactive_days', 'low_balance', 'sla_hours', 'stock_alert', 'vat_rate', 'rank_silver', 'rank_gold', 'points_per', 'point_value', 'latest_conn', 'telegram_bot_token', 'telegram_chat_id', 'offline_hours', 'tg_new_order', 'tg_new_receipt', 'tg_offline' ) as $k ) {
	delete_option( 'dastyar_admin_' . $k );
}
// v1.4.0 — پنل مستقل کارمندان: فقط کش شناسه صفحه پاک می‌شود؛ نقش «کارمند دستیار» و
// حساب‌های کاربری کارمندان دست‌نخورده می‌مانند (داده کاربر است، نه تنظیمات افزونه).
delete_option( '_dastyar_staff_panel_page_id' );
