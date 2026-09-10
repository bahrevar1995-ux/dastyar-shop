<?php
/**
 * v1.8.0 — ادمین «پنل فروشنده» در حالت ادغام (پنل واحد).
 *
 * زیرکلاس Dastyar_Panel_Admin در هسته: همه منطق ذخیره/رندر صفحه تنظیمات
 * (آپشن dvp_settings، ساخت خودکار برگه، راهنمای کدکوتاه) دقیقاً همان نسخه
 * مستقل است؛ فقط محل منو از «منوی مستقل» به زیرمجموعه واحد «دستیار شاپ»
 * منتقل شده و یک سربرگ یکدست برند بالای صفحه اضافه می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Panel_Admin_Ext extends Dastyar_Panel_Admin {

	public function __construct() {
		parent::__construct();
		// v1.8.1 — اولویت ۹۹: زیرمنو حتماً «بعد از» ثبت منوی مادر «dastyar» بیاید.
		// نام هوک صفحه زیرمنو در وردپرس به parentِ ثبت‌شده وابسته است؛ ثبتِ زودتر
		// ← ناسازگاری hookname ← لینک خام /wp-admin/dvp_panel ← ۴۰۴ فرانت.
		remove_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 99 );
	}

	/** منو: زیرمجموعه «دستیار شاپ» به‌جای منوی مستقل (اسلاگ حفظ شده: dvp_panel) */
	public function menu() {
		add_submenu_page(
			'dastyar',
			'تنظیمات پنل فروشنده',
			'پنل فروشنده',
			'manage_woocommerce',
			'dvp_panel',
			array( $this, 'page_unified' )
		);
	}

	/** رندر صفحه با سربرگ پنل واحد + بدنه استاندارد والد */
	public function page_unified() {
		if ( class_exists( 'Dastyar_Unified' ) ) {
			Dastyar_Unified::banner(
				'پنل فروشنده دستیار شاپ',
				'پنل اختصاصی فروشنده‌ها ([dastyar_vendor_panel]) — بخشی از پنل واحد دستیار شاپ',
				'user'
			);
		}
		parent::page_settings();
	}
}
