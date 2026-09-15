<?php
/**
 * v1.8.0 — ادمین «کاتالوگ دستیار» در حالت ادغام (پنل واحد).
 *
 * زیرکلاس Dastyar_Cat_Admin در هسته: همه منطق ذخیره/رندر صفحه تنظیمات
 * (آپشن dct_settings، نگاشت آیکون دسته‌ها، متاباکس محصول) دقیقاً همان نسخه
 * مستقل است؛ فقط محل منو از «منوی مستقل» به زیرمجموعه واحد «دستیار شاپ»
 * منتقل شده و یک سربرگ یکدست برند بالای صفحه اضافه می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Cat_Admin_Ext extends Dastyar_Cat_Admin {

	public function __construct() {
		parent::__construct();
		// v1.8.1 — اولویت ۹۹: زیرمنو حتماً «بعد از» ثبت منوی مادر «dastyar» بیاید (ر.ک. فیکس dvp_panel).
		remove_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 99 );
	}

	/** منو: زیرمجموعه «دستیار شاپ» به‌جای منوی مستقل (اسلاگ حفظ شده: dct_catalog) */
	public function menu() {
		add_submenu_page(
			'dastyar',
			'تنظیمات کاتالوگ',
			'کاتالوگ',
			'manage_woocommerce',
			'dct_catalog',
			array( $this, 'page_unified' )
		);
	}

	/** رندر صفحه با سربرگ پنل واحد + بدنه استاندارد والد */
	public function page_unified() {
		if ( class_exists( 'Dastyar_Unified' ) ) {
			Dastyar_Unified::banner(
				'کاتالوگ دستیار شاپ',
				'آرشیو و صفحه محصول برنددار ([dastyar_catalog]) — بخشی از پنل واحد دستیار شاپ',
				'layers'
			);
		}
		parent::page_settings();
	}
}
