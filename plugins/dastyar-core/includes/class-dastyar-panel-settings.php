<?php
/**
 * تنظیمات افزونه «پنل فروشنده دستیار» — آپشن dvp_settings
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Panel_Settings {

	const OPTION = 'dvp_settings';

	/** مقادیر پیش‌فرض */
	public static function defaults() {
		return array(
			'title'           => 'پنل فروشنده دستیار شاپ',
			'subtitle'        => 'مدیریت سفارش‌ها، کیف پول و اتصال فروشگاه شما — همه‌چیز در یک‌جا',
			'panel_page'      => 0,        // برگه پنل (برای راهنمای ادمین)
			'auth_page'       => 0,        // برگه ورود/ثبت‌نام فروشنده (۰ = برگه «حساب کاربری» ووکامرس)
			'orders_per_page' => 15,
			'plans_enabled'   => 'yes',    // طرح‌های شارژ سریع کیف پول
			'connector_url'   => home_url( '/plugin/' ),
			// v1.10.13 — طرح ظاهری پنل: کلاسیک (پیش‌فرض/فعلی) یا یکی از ۵ طرح جدید
			'panel_skin'      => 'classic',
			'promo_enabled'   => 'yes',
			'promo_title'     => 'پیشنهاد ویژه برای شما',
			'promo_text'      => 'با افزایش موجودی کیف پول، سفارش‌های مشتریان‌تان خودکار و بدون تأخیر پرداخت می‌شوند.',
			'promo_code'      => '',
			// v1.10.18 — بنر بالای جعبه «از اینجا شروع کن» در داشبورد
			'dash_banner_img' => '',
			'dash_banner_url' => '',
			'dash_banner_alt' => '',
			// v1.10.19 — نمایش/عدم‌نمایش جعبه «از اینجا شروع کن»
			'onboard_enabled' => 'yes',
			// روشن/خاموش‌کردن بخش‌ها (داشبورد و حساب کاربری همیشه فعال‌اند)
			'sec_orders'      => 'yes',
			'sec_manual'      => 'yes',
			'sec_invoices'    => 'yes',
			'sec_wallet'      => 'yes',
			'sec_rma'         => 'yes',
			'sec_suggest'     => 'yes',
			'sec_api'         => 'yes',
			'sec_tickets'     => 'yes',
			'sec_contract'    => 'yes',
			// قرارداد همکاری (v1.4.0) — متن خالی = تب قرارداد و پاپ‌آپ نمایش داده نمی‌شود
			'contract_title'  => 'قرارداد همکاری فروشندگی دستیار شاپ',
			'contract_text'   => '',
			'contract_pdf_url'=> '',
		);
	}

	/** همه تنظیمات (مرج‌شده با پیش‌فرض‌ها) */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/** گرفتن یک کلید */
	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/** بله/خیر شدن یک کلید */
	public static function yes( $key ) {
		return 'no' !== (string) self::get( $key, 'yes' );
	}

	/** فهرست طرح‌های ظاهری پنل (شناسه ← لیبل فارسی) */
	public static function skins() {
		return array(
			'classic' => 'کلاسیک (طرح فعلی)',
			'minimal' => 'مینیمال و سبک',
			'bold'    => 'پررنگ و مدرن',
			'dark'    => 'اپلیکیشنی (سایدبار تیره)',
			'soft'    => 'گرد و دوستانه',
			'dense'   => 'فشرده و حرفه‌ای',
		);
	}

	/** تبدیل ارقام لاتین به فارسی (۱۲۳) */
	public static function fa_num( $n ) {
		return strtr( (string) $n, array(
			'0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
			'5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
		) );
	}
}
