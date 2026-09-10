<?php
/**
 * نصب، جداول اختصاصی، نقش فروشنده، محصول شارژ کیف پول
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Install {

	/**
	 * بر اساس قانون پروژه: قبل از ساخت جدول بررسی شد که WP/WC معادل ندارند.
	 * جدول ۱: لاگ درخواست‌های API (هسته وردپرس جدول لاگ ندارد)
	 * جدول ۲: تراکنش‌های کیف پول (ووکامرس کیف پول داخلی ندارد)
	 */
	public static function tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->prefix}dastyar_api_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vendor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			route varchar(190) NOT NULL DEFAULT '',
			method varchar(10) NOT NULL DEFAULT '',
			ip varchar(45) NOT NULL DEFAULT '',
			status smallint(5) NOT NULL DEFAULT 0,
			message text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY vendor_id (vendor_id),
			KEY created_at (created_at)
		) $charset;

		CREATE TABLE {$wpdb->prefix}dastyar_wallet_txns (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(10) NOT NULL DEFAULT 'credit',
			amount decimal(19,4) NOT NULL DEFAULT 0,
			balance_after decimal(19,4) NOT NULL DEFAULT 0,
			ref_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			description varchar(500) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) $charset;";

		dbDelta( $sql );
	}

	/** نقش کاربری فروشنده — User جدید نمی‌سازیم، فقط Role اضافه می‌کنیم */
	public static function role() {
		add_role(
			'dastyar_vendor',
			'فروشنده دستیار',
			array( 'read' => true )
		);
	}

	/**
	 * محصول مخفی «شارژ کیف پول» — برای استفاده از درگاه‌های استاندارد WooCommerce جهت شارژ کیف پول.
	 */
	public static function charge_product() {
		if ( (int) get_option( 'dastyar_charge_product_id' ) ) {
			return;
		}
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return;
		}
		$p = new WC_Product_Simple();
		$p->set_name( 'شارژ کیف پول دستیار' );
		$p->set_status( 'private' );
		$p->set_catalog_visibility( 'hidden' );
		$p->set_virtual( true );
		$p->set_regular_price( 1000 );
		$p->set_price( 1000 );
		$p->save();
		update_option( 'dastyar_charge_product_id', $p->get_id() );
	}

	/** زمان‌بندی کرون اختصاصی ۵ دقیقه‌ای */
	public static function schedules( $schedules ) {
		if ( ! isset( $schedules['dastyar_five_minutes'] ) ) {
			$schedules['dastyar_five_minutes'] = array(
				'interval' => 300,
				'display'  => 'هر ۵ دقیقه (دستیار)',
			);
		}
		return $schedules;
	}

	public static function install() {
		self::tables();
		self::role();
		self::charge_product();
		// پیش‌فرض متن قوانین عودت (فقط اگر هنوز چیزی ذخیره نشده) — قابل ویرایش در وضعیت و تنظیمات
		if ( false === get_option( 'dastyar_rma_rules', false ) ) {
			add_option( 'dastyar_rma_rules', 'کالا تا ۴۸ ساعت پس از تحویل، در صورت سالم بودن و نداشتن خسارت، قابل عودت است. پس از ثبت گزارش توسط فروشنده، تیم دستیار شاپ درخواست را بررسی می‌کند. در صورت تایید عودت، کالا باید به آدرس انبار اعلام‌شده ارسال شود. هزینه ارسال عودتی طبق قوانین و بر اساس مقصر تعیین می‌شود.' );
		}
		Dastyar_MyAccount::add_endpoints();
		Dastyar_Suggestions::register_cpt(); // ثبت CPT قبل از flush
		Dastyar_Rma::register_cpt(); // ثبت CPT مرجوعی‌ها قبل از flush
		if ( ! wp_next_scheduled( 'dastyar_push_process' ) ) {
			wp_schedule_event( time() + 300, 'dastyar_five_minutes', 'dastyar_push_process' );
		}
		flush_rewrite_rules();
		update_option( 'dastyar_core_version', DASTYAR_CORE_VERSION );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'dastyar_push_process' );
		flush_rewrite_rules();
	}
}
