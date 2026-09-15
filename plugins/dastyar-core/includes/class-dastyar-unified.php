<?php
/**
 * v1.8.0 — «پنل واحد»: ادغام افزونه‌های «پنل فروشنده» و «کاتالوگ دستیار» داخل هسته.
 *
 * سیاست سازگاری (مهم: رفتار قبلی هیچ‌وقت نشکند):
 *  - اگر افزونه مستقل «پنل فروشنده» (ثابت DASTYAR_PANEL_FILE) یا «کاتالوگ»
 *    (ثابت DCT_URL) فعال باشد، هسته هیچ دخالتی نمی‌کند و همان افزونه مستقل
 *    مثل قبل رهبر است (اتولودر هسته هم با همان گیت، فایل‌های خودش را سرو نمی‌کند).
 *  - در غیر این صورت (حالت ادغام)، نسخه یکپارچه هسته بالا می‌آید: همان کلاس‌ها،
 *    همان کدکوتاه‌ها ([dastyar_vendor_panel]، [dastyar_catalog] و …)، همان
 *    آپشن‌ها (dvp_settings / dct_settings) — فقط صفحه‌های تنظیمات به‌جای منوی
 *    مستقل، زیرمجموعه منوی واحد «دستیار شاپ» می‌شوند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dastyar_Unified {

	/** پنل فروشنده در حالت ادغام است؟ (افزونه مستقل غایب است) */
	public static function merged_panel() {
		return ! defined( 'DASTYAR_PANEL_FILE' );
	}

	/** کاتالوگ در حالت ادغام است؟ (افزونه مستقل غایب است) */
	public static function merged_catalog() {
		return ! defined( 'DCT_URL' );
	}

	/**
	 * بوت حالت ادغام — مستقیم از سازنده Dastyar صدا زده می‌شود
	 * (همه فایل‌های اصلی افزونه‌ها پیش از هر هوک لود شده‌اند، پس گیت قطعی است).
	 */
	public function boot() {
		if ( self::merged_panel() ) {
			if ( ! defined( 'DASTYAR_PANEL_VERSION' ) ) {
				define( 'DASTYAR_PANEL_VERSION', DASTYAR_CORE_VERSION );
			}
			if ( false === get_option( Dastyar_Panel_Settings::OPTION, false ) ) {
				add_option( Dastyar_Panel_Settings::OPTION, Dastyar_Panel_Settings::defaults() );
			}
			Dastyar_Panel::instance();
		}

		if ( self::merged_catalog() ) {
			if ( ! defined( 'DCT_VERSION' ) ) {
				define( 'DCT_VERSION', DASTYAR_CORE_VERSION );
			}
			if ( ! defined( 'DCT_DIR' ) ) {
				define( 'DCT_DIR', DASTYAR_CORE_DIR );
			}
			if ( false === get_option( Dastyar_Cat_Settings::OPTION, false ) ) {
				add_option( Dastyar_Cat_Settings::OPTION, Dastyar_Cat_Settings::defaults() );
			}
			Dastyar_Cat::instance();
		}
	}

	/**
	 * سربرگ یکدست پنل واحد برای صفحه‌های تنظیمات ادغام‌شده (wp-admin).
	 * نوار باریک برند + پیوند برگشت به منوی مرکز.
	 */
	public static function banner( $title, $subtitle, $icon = 'dash' ) {
		$svg = '';
		if ( class_exists( 'Dastyar_Panel_Front' ) ) {
			$svg = Dastyar_Panel_Front::icon( $icon );
		}
		echo '<style>' .
			'.dsu-banner{display:flex;align-items:center;gap:14px;background:#242536;border-radius:14px;padding:14px 18px;margin:16px 0 4px;max-width:960px;color:#fff}' .
			'.dsu-banner .dsu-ic{display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:11px;background:#17a16d;color:#fff;flex:none}' .
			'.dsu-banner h2{margin:0;font-size:16px;font-weight:800;color:#fff}' .
			'.dsu-banner p{margin:2px 0 0;font-size:12px;color:#b9bdd0}' .
			'.dsu-banner .dsu-back{margin-right:auto;color:#8fe3c2;text-decoration:none;font-size:12px;font-weight:700;flex:none}' .
			'.dsu-banner .dsu-back:hover{color:#fff}' .
			'</style>';
		echo '<div class="dsu-banner" dir="rtl">';
		echo '<span class="dsu-ic">' . $svg . '</span>';
		echo '<div><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $subtitle ) . '</p></div>';
		echo '<a class="dsu-back" href="' . esc_url( admin_url( 'admin.php?page=dastyar' ) ) . '">بازگشت به مرکز دستیار ←</a>';
		echo '</div>';
	}
}
