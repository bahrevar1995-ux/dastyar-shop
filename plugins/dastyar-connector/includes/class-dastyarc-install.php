<?php
/**
 * نصب Connector — بدون هیچ جدول جدیدی (فقط Option + Post Meta + Page)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Install {

	public static function defaults() {
		return array(
			'dastyarc_central_url'            => '',
			'dastyarc_api_key'                => '',
			'dastyarc_price_mode'             => 'percent',   // percent | fixed
			'dastyarc_price_value'            => 30,
			'dastyarc_price_round'            => 'none',      // none | 10 | 100 | 1000
			'dastyarc_stock_sync'             => 'yes',       // yes | no ← همگام‌سازی خودکار موجودی
			'dastyarc_send_statuses'          => array( 'processing' ),
			'dastyarc_tracking_completes'     => 'yes',       // بعد از ثبت کد رهگیری سفارش کامل شود؟
			'dastyarc_unpublish_when_hidden'  => 'yes',
			'dastyarc_last_sync'              => 0,
			'dastyarc_rma_rules'              => '',          // متن قوانین عودت (خالی = پیش‌فرض)
			'dastyarc_warehouse_badge'        => 'yes',       // نشان «ارسال از انبار بندرگناوه» (v1.5.0)
			'dastyarc_dark_mode'              => 'no',        // حالت تاریک سازمانی (v1.5.0)
		);
	}

	/** بازه کرون اختصاصی ۱۵ دقیقه‌ای — سینک سریع موجودی (v1.4.0) */
	public static function schedules( $schedules ) {
		if ( ! isset( $schedules['dastyarc_15min'] ) ) {
			$schedules['dastyarc_15min'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => 'هر ۱۵ دقیقه (دستیار کانکتور)',
			);
		}
		return $schedules;
	}

	public static function install() {
		foreach ( self::defaults() as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $value );
			}
		}
		DastyarC_MyAccount::add_endpoints();
		DastyarC_Rma::register_cpt(); // ثبت CPT عودت قبل از flush

		// برگه عمومی «پیگیری سفارش» برای مشتریان فروشگاه (Fail-Safe)
		try {
			DastyarC_Track::ensure_page();
		} catch ( \Throwable $e ) { /* در ادامه روی admin_init دوباره تلاش می‌شود */ }

		// برگه عمومی «عودت و مرجوعی» برای مشتریان فروشگاه (Fail-Safe)
		try {
			DastyarC_Rma::ensure_page();
		} catch ( \Throwable $e ) { /* در ادامه روی admin_init دوباره تلاش می‌شود */ }

		if ( ! wp_next_scheduled( 'dastyarc_sync_tick' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'dastyarc_sync_tick' );
		}
		if ( ! wp_next_scheduled( 'dastyarc_reconcile_tick' ) ) {
			wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'twicedaily', 'dastyarc_reconcile_tick' );
		}
		if ( ! wp_next_scheduled( 'dastyarc_stock_tick' ) ) {
			wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, 'dastyarc_15min', 'dastyarc_stock_tick' );
		}
		flush_rewrite_rules();
		update_option( 'dastyarc_version', DASTYARC_VERSION );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'dastyarc_sync_tick' );
		wp_clear_scheduled_hook( 'dastyarc_reconcile_tick' );
		wp_clear_scheduled_hook( 'dastyarc_stock_tick' );
		flush_rewrite_rules();
	}
}
