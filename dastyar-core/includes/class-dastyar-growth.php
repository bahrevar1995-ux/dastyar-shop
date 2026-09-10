<?php
/**
 * v1.9.0 — ابزارهای رشد فروشنده (Growth Tools)
 *
 *  ۱) کپی متن آماده تبلیغ محصول (عنوان + ویژگی‌ها + قیمت پیشنهادی) برای اینستاگرام/سایت‌ساز
 *  ۲) تایم‌لاین وضعیت سفارش (ثبت ← پردازش ← تحویل به پست ← تکمیل شده) در جزئیات سفارش پنل (v1.10.4)
 *  ۳) چک‌لیست شروع هوشمند بالای داشبورد فروشنده (۵ تیک سبزشونده)
 *  ۴) جعبه «از اینجا شروع کن» برای فروشنده بدون سفارش (۳ قدم + لینک آموزش)
 *  ۵) علاقه‌مندی‌ها (قلب) روی محصولات کاتالوگ + کارت پنل + تاگل AJAX و فالبک GET
 *
 * استفاده مجدد («چرخ دوباره اختراع نکن»): Vendors (نقش/متاها)، Panel (آدرس بخش‌ها)،
 * Theme (آدرس صفحه آموزش/کاتالوگ)، الگوی کلیپ‌بورد و SVGهای خطی — بدون اموجی در فرانت.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Growth {

	const FAV_META = '_dastyar_fav_products';
	const FAV_CAP  = 20;

	public function __construct() {
		add_action( 'wp_ajax_dastyar_fav_toggle', array( $this, 'ajax_fav_toggle' ) );
		add_action( 'template_redirect', array( $this, 'maybe_fav_get' ), 3 ); // فالبک بدون‌JS
		// v1.9.5 (بازخورد کاربر) — بخش «کپی متن آماده» کامل از صفحات محصول حذف شد؛ متد copy_ad_block برای سازگاری باقی است
	}

	/* ==================================================================
	 *  آیکن‌های SVG خطی (بدون اموجی)
	 * ============================================================== */

	/** @return string svg */
	public static function icon( $name ) {
		$inner = array(
			'heart'         => '<path d="M12 20.7s-7.4-4.6-9.6-8.9C.8 8.4 2.5 5 5.9 5c2 0 3.4 1.1 4.2 2.7h1c.9-1.6 2.3-2.7 4.2-2.7 3.4 0 5.1 3.4 3.5 6.8-2.2 4.3-6.8 8.9-6.8 8.9z"/>',
			'heart-filled'  => '<path fill="currentColor" stroke="none" d="M12 20.7s-7.4-4.6-9.6-8.9C.8 8.4 2.5 5 5.9 5c2 0 3.4 1.1 4.2 2.7h1c.9-1.6 2.3-2.7 4.2-2.7 3.4 0 5.1 3.4 3.5 6.8-2.2 4.3-6.8 8.9-6.8 8.9z"/>',
			'copy'          => '<rect x="9" y="9" width="11" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
			'clipboard'     => '<rect x="7" y="4" width="11" height="17" rx="2"/><path d="M9 4V3h7v2M10 10h5M10 14h5"/>',
			'check'         => '<path d="M4.5 12.5l5 5 10-11"/>',
			'circle'        => '<circle cx="12" cy="12" r="8"/>',
			'box'           => '<path d="M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4m0 0l9-4m-9 4v10"/>',
			'truck'         => '<path d="M1 7h13v9H1zM14 10h4l3 3v3h-7z"/><circle cx="6" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/>',
			'home'          => '<path d="M3 11l9-8 9 8M5 10v10h14V10"/>',
			'x'             => '<path d="M6 6l12 12M18 6L6 18"/>',
			'spark'         => '<path d="M12 2l2 6 6 2-6 2-2 6-2-6-6-2 6-2z"/>',
			'cart'          => '<path d="M2 4h3l2.6 12h11l2.4-9H7"/><circle cx="9.5" cy="20" r="1.6"/><circle cx="16.5" cy="20" r="1.6"/>',
			'wallet'        => '<rect x="2.5" y="6" width="19" height="13" rx="2.5"/><path d="M2.5 10h19M16 14.5h2.5"/>',
			'key'           => '<circle cx="8" cy="12" r="4"/><path d="M12 12h9M18 12v3M21 12v2"/>',
			'link'          => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/>',
			'book'          => '<path d="M4 4h7a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4zM20 4h-7a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h7z"/>',
			'star'          => '<path d="M12 3l2.7 5.6 6.3.9-4.5 4.4 1 6.1-5.5-2.9L6.5 20l1-6.1L3 9.5l6.3-.9z"/>',
		);
		if ( ! isset( $inner[ $name ] ) ) {
			$inner[ $name ] = $inner['circle'];
		}
		return '<svg class="dgr-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true" focusable="false">' . $inner[ $name ] . '</svg>';
	}

	/* ==================================================================
	 *  ۵) علاقه‌مندی‌ها (قلب)
	 * ============================================================== */

	/** @return array<int> شناسه محصولات */
	public static function favs_of( $uid ) {
		$f = get_user_meta( (int) $uid, self::FAV_META, true );
		return is_array( $f ) ? array_values( array_unique( array_map( 'absint', $f ) ) ) : array();
	}

	public static function is_fav( $uid, $pid ) {
		return in_array( absint( $pid ), self::favs_of( $uid ), true );
	}

	/** تاگل — @return array{fav:bool,count:int}|WP_Error */
	public static function toggle_fav( $uid, $pid ) {
		$uid = (int) $uid;
		$pid = absint( $pid );
		if ( ! $uid || ! $pid ) {
			return new WP_Error( 'dgr_fav_bad', 'محصول نامعتبر است.' );
		}
		if ( ! function_exists( 'wc_get_product' ) || ! wc_get_product( $pid ) ) {
			return new WP_Error( 'dgr_fav_bad', 'محصول یافت نشد.' );
		}
		$favs = self::favs_of( $uid );
		if ( in_array( $pid, $favs, true ) ) {
			$favs = array_values( array_diff( $favs, array( $pid ) ) );
			update_user_meta( $uid, self::FAV_META, $favs );
			return array( 'fav' => false, 'count' => count( $favs ) );
		}
		if ( count( $favs ) >= self::FAV_CAP ) {
			return new WP_Error( 'dgr_fav_cap', 'حداکثر ' . self::FAV_CAP . ' محصول می‌تواند در علاقه‌مندی‌ها باشد.' );
		}
		$favs[] = $pid;
		update_user_meta( $uid, self::FAV_META, $favs );
		return array( 'fav' => true, 'count' => count( $favs ) );
	}

	/** AJAX تاگل */
	public function ajax_fav_toggle() {
		check_ajax_referer( 'dastyar_fav', 'nonce' );
		$uid = get_current_user_id();
		if ( ! $uid ) {
			wp_send_json_error( array( 'message' => 'برای ستاره‌زدن محصول، وارد حساب کاربری شوید.' ), 401 );
		}
		// v1.9.5 (بازخورد کاربر) — علاقه‌مندی ابزار فروشنده است؛ برای کاربر معمولی سمت سرور هم کاملاً خاموش
		if ( ! class_exists( 'Dastyar' ) || ! Dastyar::instance()->vendors || ! Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			wp_send_json_error( array( 'message' => 'این بخش ویژه فروشندگان دستیار است.' ), 403 );
		}
		$r = self::toggle_fav( $uid, (int) ( $_POST['product_id'] ?? 0 ) );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ), 400 );
		}
		wp_send_json_success( $r );
	}

	/** فالبک GET (بدون JS): ?dgr_fav=ID&_wpnonce= */
	public function maybe_fav_get() {
		if ( empty( $_GET['dgr_fav'] ) || ! is_user_logged_in() ) {
			return;
		}
		// v1.9.5 (بازخورد کاربر) — فقط فروشنده؛ برای کاربر معمولی خاموش (بی‌اثر)
		if ( ! class_exists( 'Dastyar' ) || ! Dastyar::instance()->vendors || ! Dastyar::instance()->vendors->is_vendor( get_current_user_id() ) ) {
			return;
		}
		$pid = absint( $_GET['dgr_fav'] );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'dastyar_fav_' . $pid ) ) {
			return;
		}
		$r    = self::toggle_fav( get_current_user_id(), $pid );
		$back = function_exists( 'wp_get_referer' ) && wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$mark = is_wp_error( $r ) ? rawurlencode( $r->get_error_message() ) : ( $r['fav'] ? 'added' : 'removed' );
		wp_safe_redirect( add_query_arg( 'dgr_fav_msg', $mark, $back ) );
		exit;
	}

	/** دکمه قلب — فروشنده: فعال؛ کاربر معمولی/مهمان: «تار و غیرقابل‌کلیک» (v1.9.5 — به‌جای حذف کامل، خاموش دیده می‌شود)
	 * (v1.9.1: خودکفا — استایل و اسکریپت همیشه همراه دکمه چاپ می‌شود) */
	public static function heart_html( $pid, $floating = false ) {
		$uid = get_current_user_id();
		self::css();    // v1.9.1 — بدون این، قلبِ کارت کاتالوگ بی‌استایل دیده می‌شد
		self::fav_js(); // v1.9.1 — تاگل AJAX همیشه فعال باشد (برای قلب خاموش المان data-dgr-heart نیست؛ بی‌اثر)
		$pid = absint( $pid );
		if ( ! $uid || ! class_exists( 'Dastyar' ) || ! Dastyar::instance()->vendors || ! Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			// v1.9.5 (بازخورد کاربر) — ابزار فروشنده برای کاربر معمولی «نمایش داده ولی خاموش» می‌شود: بدون لینک/بدون اکشن
			return '<span class="dgr-heart dgr-heart-off' . ( $floating ? ' dgr-heart-float' : '' ) . '" aria-disabled="true" title="ویژه فروشندگان دستیار است — با ورود فعال می‌شود">' . self::icon( 'heart' ) . '</span>';
		}
		$fav  = self::is_fav( $uid, $pid );
		$href = wp_nonce_url( add_query_arg( 'dgr_fav', $pid, self::current_url() ), 'dastyar_fav_' . $pid );
		return '<button type="button" class="dgr-heart' . ( $fav ? ' is-fav' : '' ) . ( $floating ? ' dgr-heart-float' : '' )
			. '" data-dgr-heart="' . $pid . '" data-dgr-href="' . esc_url( $href ) . '" aria-pressed="' . ( $fav ? 'true' : 'false' )
			. '" title="' . ( $fav ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها' ) . '">' . self::icon( $fav ? 'heart-filled' : 'heart' ) . '</button>';
	}

	/** کارت «علاقه‌مندی‌های من» در پنل */
	public static function favs_card_html( $uid ) {
		$favs = self::favs_of( $uid );
		if ( ! $favs ) {
			return '';
		}
		self::css();
		$out = '<div class="dgr-card dgr-favs"><strong class="dgr-favs-t">' . self::icon( 'heart-filled' ) . ' علاقه‌مندی‌های من</strong><ul>';
		foreach ( array_slice( $favs, 0, 10 ) as $pid ) {
			$p = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
			if ( ! $p || ! is_object( $p ) ) {
				continue;
			}
			$out .= '<li><a href="' . esc_url( get_permalink( $pid ) ) . '">' . esc_html( (string) $p->get_name() ) . '</a>'
				. ' <button type="button" class="dgr-heart is-fav dgr-heart-mini" data-dgr-heart="' . (int) $pid . '" data-dgr-href="'
				. esc_url( wp_nonce_url( add_query_arg( 'dgr_fav', $pid, self::current_url() ), 'dastyar_fav_' . $pid ) ) . '" title="حذف">'
				. self::icon( 'heart-filled' ) . '</button></li>';
		}
		$out .= '</ul></div>';
		self::fav_js();
		return $out;
	}

	/** JS تاگل قلب (یک‌بار چاپ) */
	public static function fav_js() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		printf(
			'<script>(function(){var N=%s,A=%s;document.addEventListener("click",function(e){var b=e.target.closest("[data-dgr-heart]");if(!b){return}e.preventDefault();var fb=function(){location.href=b.getAttribute("data-dgr-href")};try{var f=new FormData();f.append("action","dastyar_fav_toggle");f.append("nonce",N);f.append("product_id",b.getAttribute("data-dgr-heart"));fetch(A,{method:"POST",credentials:"same-origin",body:f}).then(function(r){return r.json()}).then(function(j){if(j&&j.success){b.classList.toggle("is-fav",!!j.data.fav);b.setAttribute("aria-pressed",j.data.fav?"true":"false")}else{fb()}}).catch(fb)}catch(x){fb()}});})();</script>',
			wp_json_encode( wp_create_nonce( 'dastyar_fav' ) ),
			wp_json_encode( admin_url( 'admin-ajax.php' ) )
		);
	}

	/* ==================================================================
	 *  ۱) کپی متن آماده تبلیغ
	 * ============================================================== */

	/** خطوط «برچسب: مقدار» ویژگی‌های محصول */
	public static function attr_lines( $product, $max = 6 ) {
		$out = array();
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_attributes' ) ) {
			return $out;
		}
		foreach ( (array) $product->get_attributes() as $k => $attr ) {
			$label = '';
			$value = '';
			if ( is_object( $attr ) && method_exists( $attr, 'get_name' ) ) {
				$label = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $attr->get_name(), $product ) : $attr->get_name();
				if ( method_exists( $attr, 'get_options' ) ) {
					$value = implode( '، ', array_map( 'trim', array_map( 'strval', (array) $attr->get_options() ) ) );
				}
			} elseif ( is_array( $attr ) ) {
				$label = (string) ( $attr['label'] ?? ( $attr['name'] ?? ( is_string( $k ) ? $k : '' ) ) );
				$value = (string) ( $attr['value'] ?? implode( '، ', array_map( 'trim', (array) ( $attr['options'] ?? array() ) ) ) );
			} elseif ( is_scalar( $attr ) && is_string( $k ) ) {
				$label = (string) $k;
				$value = (string) $attr;
			}
			$label = trim( $label );
			$value = trim( (string) $value );
			if ( '' !== $label && '' !== $value ) {
				$out[] = $label . ': ' . $value;
			}
			if ( count( $out ) >= (int) $max ) {
				break;
			}
		}
		return $out;
	}

	/** قیمت پیشنهادی فروش = قیمت همکاری + درصد سود (پیش‌فرض ۳۰٪ — آپشن/فیلتر) */
	public static function suggest_price( $coop ) {
		$coop = (float) $coop;
		if ( $coop <= 0 ) {
			return 0;
		}
		$pct = (float) apply_filters( 'dastyar_ad_profit_pct', (float) get_option( 'dastyar_ad_profit_pct', 30 ) );
		if ( $pct < 0 ) {
			$pct = 0;
		}
		return (float) round( $coop * ( 100 + $pct ) / 100 );
	}

	/** متن آماده تبلیغ (عنوان + ویژگی‌ها + قیمت‌ها + CTA) */
	public static function ad_text( $product ) {
		if ( ! is_object( $product ) ) {
			return '';
		}
		$lines = array();
		$name  = method_exists( $product, 'get_name' ) ? trim( (string) $product->get_name() ) : '';
		if ( '' !== $name ) {
			$lines[] = $name;
			$lines[] = '';
		}
		$attrs = self::attr_lines( $product );
		if ( $attrs ) {
			$lines[] = 'ویژگی‌ها:';
			foreach ( $attrs as $a ) {
				$lines[] = '• ' . $a;
			}
			$lines[] = '';
		}
		$price = method_exists( $product, 'get_price' ) ? (float) $product->get_price() : 0;
		if ( $price > 0 ) {
			$lines[] = 'قیمت همکاری: ' . number_format( $price ) . ' تومان';
			$suggest   = self::suggest_price( $price );
			if ( $suggest > 0 ) {
				$lines[] = 'قیمت پیشنهادی فروش: ' . number_format( $suggest ) . ' تومان';
			}
			$lines[] = '';
		}
		$lines[] = 'برای سفارش، به دایرکت پیام بدهید.';
		$lines[] = '#دستیار_شاپ';
		return trim( implode( "\n", $lines ) );
	}

	/** بلاک ابزار فروش: قلب + دکمه کپی متن + پیش‌نمایش (فقط فروشنده لاگین)
	 * v1.9.3 — پارامتر $with_heart: صفحه تکی قلب را جداگانه در ردیف بالای اطلاعات می‌گذارد
	 * و از این بلاک فقط دکمه کپی (+پیش‌نمایش) را کنار تب‌ها می‌خواهد → false */
	public static function copy_ad_block( $product = null, $with_heart = true ) {
		if ( ! $product && function_exists( 'wc_get_product' ) && function_exists( 'get_the_ID' ) ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return '';
		}
		$uid = get_current_user_id();
		if ( ! $uid || ! Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			return '';
		}
		$text = self::ad_text( $product );
		if ( '' === $text ) {
			return '';
		}
		self::css();
		$out  = '<div class="dgr-tools">';
		if ( $with_heart ) {
			$out .= self::heart_html( (int) $product->get_id() );
		}
		$out .= '<button type="button" class="dgr-btn dgr-copyad" data-dgr-copy="' . esc_attr( $text ) . '">' . self::icon( 'copy' ) . ' کپی متن آماده برای اینستاگرام/سایت</button>';
		$out .= '<details class="dgr-adprev"><summary>پیش‌نمایش متن آماده</summary><div class="dgr-adtext">' . nl2br( esc_html( $text ) ) . '</div></details>';
		$out .= '</div>';
		self::copy_js();
		self::fav_js();
		return $out;
	}

	/** هوک سینگل استاندارد ووکامرس */
	public function copy_ad_block_hook() {
		echo self::copy_ad_block(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی کنترل‌شده
	}

	/** JS کلیپ‌بورد (یک‌بار چاپ — الگوی درگاه) */
	public static function copy_js() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		echo '<script>document.addEventListener("click",function(e){var b=e.target.closest("[data-dgr-copy]");if(!b){return}var t=b.getAttribute("data-dgr-copy")||"";var ok=function(){var old=b.innerHTML;b.classList.add("dgr-done");b.textContent="کپی شد";setTimeout(function(){b.classList.remove("dgr-done");b.innerHTML=old},1400)};var leg=function(){var ta=document.createElement("textarea");ta.value=t;document.body.appendChild(ta);ta.select();try{document.execCommand("copy");ok()}catch(x){}ta.remove()};if(navigator.clipboard&&window.isSecureContext===true){navigator.clipboard.writeText(t).then(ok,leg)}else{leg()}});</script>';
	}

	/* ==================================================================
	 *  ۲) تایم‌لاین وضعیت سفارش
	 * ============================================================== */

	/** مرحله فعلی: ۱ ثبت، ۲ پردازش، ۳ تحویل به پست، ۴ تکمیل شده، ۰ نامعلوم، ۱- متوقف/لغو */
	public static function timeline_stage( $order ) {
		if ( ! $order || ! is_object( $order ) ) {
			return 0;
		}
		$status = (string) $order->get_status();
		if ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
			return -1;
		}
		if ( 'completed' === $status ) {
			return 4;
		}
		// v1.10.4 — وضعیت «تحویل پست شده» (تحویل بسته به پست) یا داشتن کد رهگیری ← مرحله ۳
		if ( ( class_exists( 'Dastyar_Statuses' ) && Dastyar_Statuses::POSTED === $status ) || '' !== (string) $order->get_meta( '_dastyar_tracking_code' ) ) {
			return 3;
		}
		if ( in_array( $status, array( 'processing', 'on-hold' ), true ) ) {
			return 2;
		}
		return 1;
	}

	/** کارت تایم‌لاین مرحله‌ای */
	public static function timeline_html( $order ) {
		$stage = self::timeline_stage( $order );
		self::css();
		$out = '<div class="dgr-tl" dir="rtl">';
		if ( -1 === $stage ) {
			return $out . '<div class="dgr-tl-stop"><span class="dgr-tl-stopic">' . self::icon( 'x' ) . '</span> این سفارش متوقف یا لغو شده است.</div></div>';
		}
		$steps = array(
			1 => array( 'ثبت سفارش', 'clipboard' ),
			2 => array( 'پردازش و آماده‌سازی', 'box' ),
			3 => array( 'تحویل به پست', 'truck' ),
			4 => array( 'تکمیل شده', 'home' ),
		);
		$out .= '<ul class="dgr-tl-steps">';
		$i    = 0;
		$num  = 0;
		foreach ( $steps as $n => $cfg ) {
			$num++;
			$i++;
			$classes = 'dgr-tl-step' . ( $n <= $stage ? ' is-done' : '' ) . ( $n === $stage && $stage < 4 ? ' is-now' : '' );
			$out    .= '<li class="' . $classes . '"><span class="dgr-tl-dot">' . ( $n < $stage ? self::icon( 'check' ) : self::icon( $cfg[1] ) ) . '</span><span class="dgr-tl-lab">' . esc_html( $cfg[0] ) . '</span></li>';
			if ( $i < count( $steps ) ) {
				$out .= '<li class="dgr-tl-bar' . ( $n < $stage ? ' is-done' : '' ) . '" aria-hidden="true"></li>';
			}
		}
		$out .= '</ul></div>';
		return $out;
	}

	/* ==================================================================
	 *  ۳) چک‌لیست شروع هوشمند
	 * ============================================================== */

	/** @return array<int,array{0:string,1:string,2:bool,3:string}> key,label,done,url */
	public static function checklist_items( $uid ) {
		$uid = (int) $uid;
		return array(
			array( 'signup', 'ثبت‌نام فروشنده انجام شد', true, '' ),
			array( 'connect', 'فروشگاه‌ات را وصل کن', '' !== (string) get_user_meta( $uid, '_dastyar_site_url', true ), class_exists( 'Dastyar_Panel_Front' ) ? Dastyar_Panel_Front::url( 'api' ) : '' ),
			array( 'wallet', 'کیف پول را شارژ کن', (float) get_user_meta( $uid, Dastyar_Vendors::WALLET, true ) > 0, class_exists( 'Dastyar_Panel_Front' ) ? Dastyar_Panel_Front::url( 'wallet' ) : '' ),
			array( 'apikey', 'کلید API بساز', '' !== (string) get_user_meta( $uid, '_dastyar_api_key_hash', true ), class_exists( 'Dastyar_Panel_Front' ) ? Dastyar_Panel_Front::url( 'api' ) : '' ),
			array( 'order', 'اولین سفارش‌ات را ثبت کن', self::has_orders( $uid ), class_exists( 'Dastyar_Panel_Front' ) ? Dastyar_Panel_Front::url( 'manual' ) : '' ),
		);
	}

	public static function has_orders( $uid ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}
		$rows = wc_get_orders( array( 'customer_id' => (int) $uid, 'limit' => 1 ) );
		return is_array( $rows ) && count( $rows ) > 0;
	}

	/** کارت چک‌لیست — بالای داشبورد */
	public static function checklist_html( $uid ) {
		$items = self::checklist_items( $uid );
		$done  = 0;
		foreach ( $items as $it ) {
			if ( $it[2] ) {
				$done++;
			}
		}
		$all = count( $items ) === $done;
		self::css();
		$out  = '<div class="dgr-card dgr-check' . ( $all ? ' dgr-check-all' : '' ) . '">';
		$out .= '<strong class="dgr-check-t">' . self::icon( 'spark' ) . ( $all ? ' آفرین! همهٔ مراحل شروع کامل شده — وقت رشد است' : ' چک‌لیست شروع فروشندگی' ) . '</strong>';
		$out .= '<ul class="dgr-check-list">';
		foreach ( $items as $it ) {
			$out .= '<li class="' . ( $it[2] ? 'is-done' : '' ) . '"><span class="dgr-ckic">' . self::icon( $it[2] ? 'check' : 'circle' ) . '</span>'
				. '<span class="dgr-cklab">' . esc_html( $it[1] ) . '</span>'
				. ( ! $it[2] && '' !== $it[3] ? '<a class="dgr-ckgo" href="' . esc_url( $it[3] ) . '">انجامش بده ←</a>' : '' )
				. '</li>';
		}
		$out .= '</ul></div>';
		return $out;
	}

	/* ==================================================================
	 *  ۴) جعبه «از اینجا شروع کن» (فروشنده بدون سفارش)
	 * ============================================================== */

	/** آدرس صفحه آموزش (قالب دستیار) با فیلتر */
	public static function tutorial_url() {
		$url = '';
		if ( class_exists( 'Dastyar_Theme' ) && method_exists( 'Dastyar_Theme', 'page_url' ) ) {
			$url = (string) Dastyar_Theme::page_url( 'tutorial_page', 'tutorial' );
		}
		if ( ! $url ) {
			$url = home_url( '/' );
		}
		return (string) apply_filters( 'dastyar_tutorial_page_url', $url );
	}

	/** آدرس کاتالوگ */
	public static function catalog_url() {
		if ( class_exists( 'Dastyar_Theme' ) && method_exists( 'Dastyar_Theme', 'page_url' ) ) {
			$u = (string) Dastyar_Theme::page_url( 'catalog_page', 'catalog' );
			if ( $u ) {
				return $u;
			}
		}
		return home_url( '/catalog/' );
	}

	/** جعبه راهنمای ۳ قدم — جایگزین «هنوز سفارشی نداری» */
	public static function start_box_html( $uid ) {
		self::css();
		$steps = array(
			array( 'link', 'فروشگاه‌ات را وصل کن', 'کلید API بساز و افزونه اتصال‌دهنده را روی سایتت نصب کن؛ سفارش‌ها خودشان می‌آیند.', class_exists( 'Dastyar_Panel_Front' ) ? Dastyar_Panel_Front::url( 'api' ) : '' ),
			array( 'star', 'از کاتالوگ، محصول‌های پرفروش را پیدا کن', 'چند محصول اصلی را ستاره بزن تا با یک کلیک برگردی سراغشان.', self::catalog_url() ),
			array( 'cart', 'اولین سفارش‌ات را ثبت کن', 'با «ثبت سفارش دستی» یا از فروشگاه خودت — بسته‌بندی و ارسال با دستیار.', class_exists( 'Dastyar_Panel_Front' ) ? Dastyar_Panel_Front::url( 'manual' ) : '' ),
		);
		$out  = '<div class="dgr-card dgr-start">';
		$out .= '<strong class="dgr-start-t">' . self::icon( 'spark' ) . ' از اینجا شروع کن — ۳ قدم تا اولین فروش</strong>';
		$out .= '<div class="dgr-steps">';
		$n    = 0;
		foreach ( $steps as $s ) {
			$n++;
			$out .= '<div class="dgr-step"><span class="dgr-step-n">' . esc_html( class_exists( 'Dastyar_Panel_Settings' ) ? Dastyar_Panel_Settings::fa_num( $n ) : (string) $n ) . '</span>'
				. '<span class="dgr-step-ic">' . self::icon( $s[0] ) . '</span><strong>' . esc_html( $s[1] ) . '</strong><p>' . esc_html( $s[2] ) . '</p>'
				. ( '' !== $s[3] ? '<a class="dgr-btn dgr-btn-sm" href="' . esc_url( $s[3] ) . '">شروع ←</a>' : '' )
				. '</div>';
		}
		$out .= '</div>';
		$out .= '<p class="dgr-start-tut">' . self::icon( 'book' ) . ' <a href="' . esc_url( self::tutorial_url() ) . '">صفحه آموزش استفاده</a> — از راه‌اندازی تا اولین فروش، مرحله به مرحله.</p>';
		$out .= '</div>';
		return $out;
	}

	/* ==================================================================
	 *  مشترک: CSS + URL جاری
	 * ============================================================== */

	/** CSS ابزارها (یک‌بار چاپ) */
	public static function css() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		echo '<style>'
			. '.dgr-card{background:#fff;border:1.5px solid #e3ebe7;border-radius:16px;padding:18px 20px;margin:16px 0;box-shadow:0 2px 10px rgba(23,161,109,.06)}'
			. '.dgr-ic{vertical-align:-3px}'
			/* دکمه‌ها */
			. '.dgr-btn{display:inline-flex;align-items:center;gap:8px;background:#17a16d;color:#fff;border:none;border-radius:12px;padding:11px 18px;font-size:14px;font-weight:800;cursor:pointer;transition:.2s;text-decoration:none;font-family:inherit}'
			. '.dgr-btn:hover{background:#12875c}.dgr-btn.dgr-done{background:#0f8a5f}'
			. '.dgr-btn-sm{padding:8px 14px;font-size:13px}'
			/* قلب — v1.9.1: بازطراحی گرافیکی (دایره‌ای مرتب، سایه لطیف، هاور، پاپ هنگام روشن‌شدن) */
			. '.dgr-heart{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;min-width:40px;border-radius:50%;background:#fff;border:1.5px solid #e3ebe7;color:#c2436a;cursor:pointer;transition:all .18s ease;padding:0;line-height:1;text-decoration:none;box-sizing:border-box;font-family:inherit;box-shadow:0 1px 4px rgba(36,37,54,.08)}'
			. '.dgr-heart:hover{border-color:#f0b9cb;background:#fff6f8}'
			. '.dgr-heart:active{transform:scale(.9)}'
			. '.dgr-heart.is-fav{background:#c2436a;border-color:#c2436a;color:#fff;box-shadow:0 5px 14px rgba(194,67,106,.30);animation:dgrPop .28s ease}'
			. '.dgr-heart.is-fav .dgr-ic path{fill:currentColor}'
			. '@keyframes dgrPop{0%{transform:scale(.5)}55%{transform:scale(1.18)}100%{transform:scale(1)}}'
			/* v1.9.5 — قلب خاموش برای کاربر معمولی/مهمان: تار و غیرقابل‌کلیک (رنگ/هاور بی‌اثر) */
			. '.dgr-heart-off,.dgr-heart-off:hover,.dgr-heart-off:active{background:#f8f9fb;border-color:#e6e8ef;color:#c3c7d6;cursor:not-allowed;box-shadow:none;opacity:.55;transform:none;animation:none}'
			/* نسخه کارت کاتالوگ (کنار دکمه «جزئیات» در پایین کارت — v1.9.1 دیگر شناور نیست) */
			. '.dgr-heart-float{width:42px;height:42px;min-width:42px;flex:0 0 auto;border-radius:13px}'
			. '.dgr-heart-float .dgr-ic{width:19px;height:19px}'
			. '.dgr-heart-float:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(194,67,106,.22);border-color:#f0b9cb}'
			. '.dgr-heart-mini{width:26px;height:26px;min-width:26px;border-radius:8px;box-shadow:none}.dgr-heart-mini .dgr-ic{width:13px;height:13px}'
			/* ابزار محصول */
			. '.dgr-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:14px 0 6px}'
			. '.dgr-adprev{flex-basis:100%;border:1.5px solid #e3ebe7;border-radius:12px;background:#fbfdfc}'
			. '.dgr-adprev summary{cursor:pointer;padding:10px 14px;font-weight:700;font-size:13px;color:#42564d;list-style:none}'
			. '.dgr-adprev summary::-webkit-details-marker{display:none}'
			. '.dgr-adtext{padding:2px 16px 14px;font-size:13.5px;line-height:2;color:#22322b;white-space:normal;direction:rtl}'
			/* تایم‌لاین */
			. '.dgr-tl{margin:14px 0 20px;padding:16px 18px;background:linear-gradient(135deg,#f4fbf8,#eef8f2);border:1.5px solid #cdeee0;border-radius:16px}'
			. '.dgr-tl-steps{list-style:none;margin:0;padding:0;display:flex;align-items:flex-start}'
			. '.dgr-tl-step{display:flex;flex-direction:column;align-items:center;gap:7px;flex:0 0 auto;min-width:86px;text-align:center;color:#8fa39a}'
			. '.dgr-tl-dot{display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:50%;background:#fff;border:2px solid #dbe7e0;color:#8fa39a;transition:.2s}'
			. '.dgr-tl-step .dgr-ic{width:20px;height:20px}'
			. '.dgr-tl-lab{font-size:12.5px;font-weight:700}'
			. '.dgr-tl-step.is-done{color:#12875c}'
			. '.dgr-tl-step.is-done .dgr-tl-dot{background:#17a16d;border-color:#17a16d;color:#fff}'
			. '.dgr-tl-step.is-now .dgr-tl-dot{box-shadow:0 0 0 5px rgba(23,161,109,.18)}'
			. '.dgr-tl-bar{flex:1;height:3px;background:#dbe7e0;border-radius:99px;margin:20px 4px 0}'
			. '.dgr-tl-bar.is-done{background:#17a16d}'
			. '.dgr-tl-stop{display:flex;align-items:center;gap:9px;color:#a93226;font-weight:800;font-size:14px}'
			. '.dgr-tl-stopic{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:#fde3e0;color:#c0392b}'
			/* چک‌لیست */
			. '.dgr-check-t{display:flex;align-items:center;gap:8px;font-size:15.5px;color:#12875c;margin-bottom:10px}'
			. '.dgr-check-all{border-color:#9fdcc0;background:#f2fbf6}'
			. '.dgr-check-list{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:8px 16px}'
			. '.dgr-check-list li{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#42564d;padding:6px 0}'
			. '.dgr-check-list li.is-done{color:#12875c}'
			. '.dgr-ckic{display:flex;color:#b7c8c0}.is-done .dgr-ckic{color:#17a16d}'
			. '.dgr-ckgo{margin-inline-start:auto;font-size:12px;color:#17a16d;font-weight:800;text-decoration:none;white-space:nowrap}'
			/* قدم‌های شروع */
			. '.dgr-start{border-color:#cdeee0;background:linear-gradient(135deg,#f6fcfa,#f2faf6)}'
			. '.dgr-start-t{display:flex;align-items:center;gap:8px;font-size:16px;color:#12875c;margin-bottom:14px}'
			. '.dgr-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}'
			. '.dgr-step{position:relative;background:#fff;border:1.5px solid #e3ebe7;border-radius:14px;padding:16px 14px 14px}'
			. '.dgr-step strong{display:block;font-size:13.5px;color:#242536;margin:8px 0 4px}'
			. '.dgr-step p{font-size:12.5px;color:#687a72;line-height:1.9;margin:0 0 10px}'
			. '.dgr-step-n{position:absolute;top:-11px;right:12px;background:#17a16d;color:#fff;border-radius:999px;padding:1px 11px;font-size:12px;font-weight:900}'
			. '.dgr-step-ic{display:inline-flex;color:#17a16d;background:#e9f7f0;border-radius:10px;padding:7px}'
			. '.dgr-start-tut{display:flex;align-items:center;gap:7px;margin:16px 0 0;font-size:13px;color:#42564d}'
			. '.dgr-start-tut a{color:#12875c;font-weight:800}'
			/* کارت علاقه‌مندی‌ها */
			. '.dgr-favs-t{display:flex;align-items:center;gap:8px;font-size:14.5px;color:#c2436a;margin-bottom:8px}'
			. '.dgr-favs ul{list-style:none;margin:0;padding:0}'
			. '.dgr-favs li{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:7px 0;border-bottom:1px dashed #eef2f0;font-size:13.5px}'
			. '.dgr-favs li:last-child{border-bottom:none}'
			. '.dgr-favs a{color:#242536;text-decoration:none;font-weight:700}'
			. '.dgr-favs a:hover{color:#17a16d}'
			. '@media (max-width:640px){.dgr-tl-steps{flex-wrap:wrap;justify-content:center}.dgr-tl-bar{display:none}}'
			. '</style>';
	}

	/** آدرس جاری (برای href فالبک قلب) */
	protected static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		return $uri ? home_url( $uri ) : home_url( '/' );
	}
}
