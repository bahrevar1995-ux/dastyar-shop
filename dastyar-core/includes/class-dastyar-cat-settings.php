<?php
/**
 * تنظیمات کاتالوگ دستیار — پیش‌فرض‌ها مطابق طرح نمونه، همه از پنل افزونه قابل تغییرند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Cat_Settings {

	const OPTION     = 'dct_settings';
	const ICONS_OPT  = 'dct_cat_icons';

	/** همه پیش‌فرض‌ها */
	public static function defaults() {
		return array(
			// آرشیو
			'title_a'      => 'کاتالوگ',
			'title_b'      => 'محصولات دستیار شاپ',
			'subtitle'     => 'بیش از {count} محصول آماده عرضه برای فروشگاه شما',
			'columns'      => 4,
			'per_page'     => 12,
			'orderby'      => 'date', // date | popularity | price | price-desc
			'show_search'  => 'yes',
			'show_chips'   => 'yes',
			'chips_limit'  => 8,
			'show_popular' => 'yes', // v1.1.1 — کنترل مستقل نوار «دسته‌های پرطرفدار» (جدای از چیپ‌ها)
			'popular_limit'=> 6, // v1.1.0 — تعداد دسته‌های پرطرفدار نوار بالای کاتالوگ
			'price_roles'  => 'yes', // v1.2.0 — قیمت کارت‌ها بر اساس نقش: مدیر←قیمت فروشگاه، فروشنده←قیمت همکاری، مهمان←نمایش ندارد
			// بخش همکاری (CTA)
			'cta_enabled'  => 'yes',
			'cta_title'    => 'همکاری مطمئن، سود بیشتر',
			'cta_text'     => 'با دستیار شاپ، تأمین کالا را ساده، سریع و مطمئن تجربه کنید.',
			'cta_btn'      => 'درباره ما بیشتر بدانید',
			'cta_url'      => '',
			// نشان‌های اعتماد (۴ لایه)
			'trust_items'  => array(
				array( 'icon' => '🚚', 'title' => 'ارسال سریع', 'sub' => 'به سراسر کشور' ),
				array( 'icon' => '🧩', 'title' => 'تامین پایدار', 'sub' => 'موجودی روزانه' ),
				array( 'icon' => '🏷️', 'title' => 'قیمت همکاری', 'sub' => 'ویژه فروشندگان' ),
				array( 'icon' => '🎧', 'title' => 'پشتیبانی اختصاصی', 'sub' => 'همراه شما هستیم' ),
			),
			// صفحه تکی محصول
			'single_override' => 'yes',
			'archive_page'    => 0,
			'single_icons'    => array(
				array( 'icon' => '🚚', 'title' => 'ارسال سریع', 'sub' => 'به سراسر کشور' ),
				array( 'icon' => '🛡️', 'title' => 'تضمین اصالت', 'sub' => 'کالای اورجینال' ),
				array( 'icon' => '🎧', 'title' => 'پشتیبانی', 'sub' => 'پاسخگوی شما هستیم' ),
				array( 'icon' => '📦', 'title' => 'تامین پایدار', 'sub' => 'موجودی روزانه' ),
			),
			'tab_intro'       => 'yes',
			'tab_specs'       => 'yes',
			'tab_package'     => 'yes',
			'tab_faq'         => 'yes',
			'res_title'       => 'منابع و اطلاعات تکمیلی',
			'res_sub'         => 'فایل‌های مورد نیاز برای معرفی و فروش این محصول',
			'res_items'       => array(
				'specs'  => array( 'icon' => '📄', 'label' => 'مشخصات فنی', 'action' => 'دانلود فایل', 'enabled' => 'yes' ),
				'desc'   => array( 'icon' => '📝', 'label' => 'توضیحات محصول', 'action' => 'دانلود فایل', 'enabled' => 'yes' ),
				'video'  => array( 'icon' => '▶️', 'label' => 'ویدیو معرفی', 'action' => 'مشاهده ویدیو', 'enabled' => 'yes' ),
				'images' => array( 'icon' => '🖼️', 'label' => 'تصاویر محصول', 'action' => 'دانلود تصاویر', 'enabled' => 'yes' ),
			),
		);
	}

	/** یک مقدار تنظیم */
	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/** همه تنظیمات (ادغام با پیش‌فرض‌ها) */
	public static function all() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/** آیکون یک دسته‌بندی (از نگاشت پنل) */
	public static function cat_icon( $term_id ) {
		$icons = (array) get_option( self::ICONS_OPT, array() );
		return isset( $icons[ (int) $term_id ] ) && '' !== $icons[ (int) $term_id ] ? $icons[ (int) $term_id ] : '🔹';
	}

	/** تبدیل اعداد به نمایش فارسی/محلی */
	public static function fa_num( $n ) {
		$s = function_exists( 'number_format_i18n' ) ? number_format_i18n( (float) $n ) : number_format( (float) $n );
		return strtr( $s, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	/** خطوط غیرخالی یک textarea */
	public static function lines( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/** تجزیه خط «مقدار۱|مقدار۲» */
	public static function pair( $line, $sep = '|' ) {
		$parts = array_map( 'trim', explode( $sep, (string) $line, 2 ) );
		return array( $parts[0] ?? '', $parts[1] ?? '' );
	}

	/** جایگزینی جای‌نگهدار {count} با شمارش محصولات منتشرشده (با کش کوتاه) */
	public static function products_count() {
		$cached = get_transient( 'dct_products_count' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		if ( function_exists( 'wp_count_posts' ) ) {
			$count = (int) ( wp_count_posts( 'product' )->publish ?? 0 );
		} else {
			$count = function_exists( 'wc_get_products' )
				? count( (array) wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'return' => 'ids' ) ) )
				: 0;
		}
		set_transient( 'dct_products_count', $count, 10 * MINUTE_IN_SECONDS );
		return $count;
	}
}
