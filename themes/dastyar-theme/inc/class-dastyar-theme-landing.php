<?php
/**
 * موتور لندینگ قالب «دستیار» — بخش‌های صفحه اصلی + ابزارهای مشترک.
 *
 * پورت مستقیم موتور تأییدشده افزونه «دستیار هوم» داخل قالب (چرخ دوباره اختراع نشده);
 * استایل و JS این‌بار در فایل‌های استاتیک قالب میزبانند (نه چاپ درون‌خطی) تا با لود آنی سازگار بمانند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Theme_Landing {

	/* ==================================================================
	 *  منوی اپلیکیشنی موبایل — نوار ثابت پایین صفحه (v2.4.9)
	 * ============================================================== */

	/** ساخت آدرس/آیکون/برچسب هر خانه بر اساس نوع انتخاب‌شده در تنظیمات */
	protected static function mobnav_resolve( $n ) {
		$s     = Dastyar_Theme_Settings::all();
		$type  = $s[ 'mobnav_' . $n . '_type' ] ?? 'off';
		if ( 'off' === $type || '' === $type ) {
			return null;
		}
		$label = trim( (string) ( $s[ 'mobnav_' . $n . '_label' ] ?? '' ) );

		switch ( $type ) {
			case 'home':
				return array( 'url' => home_url( '/' ), 'icon' => 'home', 'label' => $label ?: 'خانه' );
			case 'catalog':
				return array( 'url' => Dastyar_Theme::page_url( 'catalog_page', '/' ), 'icon' => 'store', 'label' => $label ?: 'محصولات' );
			case 'cart':
				$url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
				return array( 'url' => $url, 'icon' => 'cart', 'label' => $label ?: 'سبد خرید' );
			case 'account':
				if ( is_user_logged_in() ) {
					$url = function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'myaccount' ) > 0 ? get_permalink( wc_get_page_id( 'myaccount' ) ) : home_url( '/my-account/' );
				} else {
					$url = Dastyar_Theme::page_url( 'auth_page', '/my-account/' );
				}
				return array( 'url' => $url, 'icon' => 'user', 'label' => $label ?: 'حساب من' );
			case 'custom':
				$url = trim( (string) ( $s[ 'mobnav_' . $n . '_url' ] ?? '' ) );
				if ( '' === $url || '' === $label ) {
					return null; // بدون لینک/برچسب، خانه سفارشی نمایش داده نمی‌شود
				}
				return array( 'url' => $url, 'icon' => $s[ 'mobnav_' . $n . '_icon' ] ?? 'star', 'label' => $label );
		}
		return null;
	}

	/** آیا این آیتم منو، مطابق آدرس فعلی است؟ (برای نمایش حالت فعال) */
	protected static function mobnav_is_active( $item_url ) {
		$cur  = untrailingslashit( (string) wp_parse_url( esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
		$item = untrailingslashit( (string) wp_parse_url( $item_url, PHP_URL_PATH ) );
		if ( '' === $item ) {
			return '' === $cur; // آیتم «خانه» با ریشه سایت
		}
		return $cur === $item;
	}

	/** آیا نوار پایین باید در این صفحه نمایش داده شود؟ (تنظیمات + استثناهای پنل فروشنده/عملیات) */
	public static function mobnav_should_show() {
		if ( ! Dastyar_Theme_Settings::yes( 'mobnav_enabled' ) ) {
			return false;
		}
		if ( is_singular( 'page' ) ) {
			$post = get_post();
			if ( $post && has_shortcode( (string) $post->post_content, 'dastyar_vendor_panel' ) ) {
				return false;
			}
			if ( function_exists( 'get_page_template_slug' ) && 'dastyar-staff-ops' === get_page_template_slug( $post ) ) {
				return false;
			}
		}
		return true;
	}

	/** رندر نوار پایین — فقط اگر حداقل یک خانه فعال باشد چیزی چاپ می‌کند */
	public static function render_mobile_nav() {
		if ( ! self::mobnav_should_show() ) {
			return;
		}
		$items = array();
		for ( $n = 1; $n <= 5; $n++ ) {
			$it = self::mobnav_resolve( $n );
			if ( $it ) {
				$items[] = $it;
			}
		}
		if ( ! $items ) {
			return;
		}
		echo '<nav class="dth-mobnav" aria-label="منوی اپلیکیشنی موبایل">';
		foreach ( $items as $it ) {
			$on = self::mobnav_is_active( $it['url'] );
			printf(
				'<a class="dth-mn-item%s" href="%s">%s<span>%s</span></a>',
				$on ? ' dth-mn-on' : '',
				esc_url( $it['url'] ),
				self::icon( $it['icon'] ),
				esc_html( $it['label'] )
			);
		}
		echo '</nav>';
		// همگام‌سازی حالت فعال بعد از ناوبری «لود آنی» (PJAX) — چون خودِ نوار در wp_footer فقط
		// یک‌بار در بارگذاری کامل صفحه چاپ می‌شود و در جابه‌جایی‌های بعدی دوباره اجرا نمی‌شود.
		echo '<script>(function(){
			function dthMobnavSync(){
				var path = location.pathname.replace(/\/$/, "") || "/";
				document.querySelectorAll(".dth-mn-item").forEach(function(a){
					var href = a.getAttribute("href") || "";
					var p; try { p = new URL(href, location.href).pathname.replace(/\/$/, "") || "/"; } catch(e){ p = ""; }
					a.classList.toggle("dth-mn-on", p === path);
				});
			}
			window.addEventListener("dth:pjax", dthMobnavSync);
			window.addEventListener("pageshow", dthMobnavSync);
		})();</script>';
	}

	/* ==================================================================
	 *  ابزارهای عمومی
	 * ============================================================== */

	/**
	 * آیکن‌های SVG لاین‌دیزاین (stroke=currentColor) — بدون اموجی.
	 * @param string $name نام آیکن
	 */
	public static function icon( $name ) {
		$o = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
		$c = '</svg>';
		$i = array(
			'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
			'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
			'check'   => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
			'arrow-l' => '<path d="M15 6l-6 6 6 6"/>',
			'rocket'  => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
			'sync'    => '<path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/>',
			'users'   => '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20c1.2-3 3.6-4.5 6.5-4.5s5.3 1.5 6.5 4.5"/><path d="M16 5.2a3.4 3.4 0 0 1 0 5.6"/><path d="M17.8 15.7c2 .7 3.3 2 3.9 4.3"/>',
			'star'    => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.7l5.9-.9L12 3.5z"/>',
			'shield'  => '<path d="M12 3l7 3v5c0 4.4-3 7.6-7 9-4-1.4-7-4.6-7-9V6l7-3z"/><path d="M9.5 11.5l2 2 3.5-3.5"/>',
			'truck'   => '<path d="M3 7h11v8H3z"/><path d="M14 10h4l3 3v2h-7v-5z"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
			'chart'   => '<path d="M4 20h16"/><rect x="6" y="11" width="3" height="7" rx="1"/><rect x="11" y="6" width="3" height="12" rx="1"/><rect x="16" y="13.5" width="3" height="4.5" rx="1"/>',
			'quote'   => '<path d="M7 7h3.5v3.5c0 3-1.8 4.8-4 5.5v-1.6c1-.3 1.7-1 1.9-2H7a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z"/><path d="M15.5 7H19v3.5c0 3-1.8 4.8-4 5.5v-1.6c1-.3 1.7-1 1.9-2h-1.4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z"/>',
			'api'     => '<path d="M9 7V3M15 7V3"/><path d="M7 7h10v4a5 5 0 0 1-10 0V7z"/><path d="M12 16v5"/>',
			'orders'  => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0"/><path d="M9.5 11h5M9.5 15h3.5"/>',
			'wallet'  => '<path d="M4 7h13a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7z"/><path d="M4 7V5.5A2.5 2.5 0 0 1 6.5 3H16"/><circle cx="16.2" cy="13.8" r="1.1" fill="currentColor" stroke="none"/>',
			'tickets' => '<path d="M21 11.5a8.5 8.5 0 0 1-12.3 7.5L3 21l2-5.5A8.5 8.5 0 1 1 21 11.5z"/>',
			'rma'     => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4.5V9h4.5"/>',
			'package' => '<path d="M21 16V8a2 2 0 0 0-1-1.7l-6-3.5a2 2 0 0 0-2 0L6 6.3A2 2 0 0 0 5 8v8a2 2 0 0 0 1 1.7l6 3.5a2 2 0 0 0 2 0l6-3.5A2 2 0 0 0 21 16z"/><path d="M3.3 7.3L12 12.2l8.7-4.9"/><path d="M12 22V12"/>',
			// ---------- v2.4.5: آیکن‌های صحنه سه‌بعدی هیرو (فروشگاه/انبار تامین‌کننده) ----------
			'store'   => '<path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M9 20v-6h6v6"/><path d="M3 9h18"/>',
			'boxes'   => '<rect x="3" y="10" width="8" height="8" rx="1"/><rect x="13" y="10" width="8" height="8" rx="1"/><rect x="8" y="3" width="8" height="8" rx="1"/>',
			'gift'    => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
			'card'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/>',
			'home'    => '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
			'suggest' => '<path d="M9.5 18.5h5"/><path d="M10.5 21.5h3"/><path d="M12 3a6 6 0 0 0-3.4 10.9c.7.6 1 1.3 1.2 2.1h4.4c.2-.8.5-1.5 1.2-2.1A6 6 0 0 0 12 3z"/>',
			'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21c1.4-3.6 4.3-5.2 7.5-5.2s6.1 1.6 7.5 5.2"/>',
			'phone'   => '<path d="M15.5 3.5h-7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-13a2 2 0 0 0-2-2z"/><path d="M11 18h2"/>',
			'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
			'pin'     => '<path d="M12 21s-6.5-5.2-6.5-10.5a6.5 6.5 0 0 1 13 0C18.5 15.8 12 21 12 21z"/><circle cx="12" cy="10.5" r="2.3"/>',
			'clock'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
			'download'=> '<path d="M12 4v11"/><path d="M7.5 11l4.5 4.5L16.5 11"/><path d="M4 19.5h16"/>',
			'book'    => '<path d="M5 4.5A2.5 2.5 0 0 1 7.5 2H19v17.5H7.5A2.5 2.5 0 0 0 5 22V4.5z"/><path d="M19 15.5H7.5A2.5 2.5 0 0 0 5 18"/>',
			'plogo'   => '<path d="M9 7V3M15 7V3"/><path d="M7 7h10v4a5 5 0 0 1-10 0V7z"/><path d="M12 16v5"/>',
			// ---------- v2.0.0: آیکن‌های محصولی کارت‌های شناور ----------
			'headphone' => '<path d="M4 8a8 8 0 0 1 16 0"/><path d="M3 14a2 2 0 0 1 2-2h1v6H5a2 2 0 0 1-2-2v-2z"/><path d="M21 14a2 2 0 0 0-2-2h-1v6h1a2 2 0 0 0 2-2v-2z"/>',
			'watch'     => '<circle cx="12" cy="12" r="6"/><path d="M12 9v3l2 2M9 3.5 8.8 6M15 3.5l.2 2.5M9 20.5 8.8 18M15 20.5l.2-2.5"/>',
			'bag'       => '<path d="M6 3h12l2 4H4l2-4zM4 7v13a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V7"/><path d="M9 11a3 3 0 0 0 6 0"/>',
			'mug'       => '<path d="M5 9h11v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V9z"/><path d="M16 10h2a2.5 2.5 0 0 1 0 5h-2M8 5.5c0-1 .8-1.2.8-2.2M12 5.5c0-1 .8-1.2.8-2.2"/>',
			'cart'      => '<path d="M6 6h15l-1.7 8.5a2 2 0 0 1-2 1.5H8.6a2 2 0 0 1-2-1.6L5 3H2.5"/><circle cx="9.5" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/>',
			'zap'       => '<path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/>',
			'plus'      => '<path d="M12 5v14M5 12h14"/>',
			'headset'   => '<path d="M4 14v-2.5a8 8 0 0 1 16 0V14"/><rect x="2.5" y="13.5" width="4" height="6" rx="1.7"/><rect x="17.5" y="13.5" width="4" height="6" rx="1.7"/><path d="M19.5 19.5c0 1.8-1.6 2.8-3.5 2.8h-2"/>',
		);
		return isset( $i[ $name ] ) ? $o . $i[ $name ] . $c : '';
	}

	/** فهرست آیکن‌ها برای دراپ‌داون مدیریت: کلید → لیبل فارسی */
	public static function icon_choices() {
		return array(
			'api' => 'اتصال/API', 'sync' => 'همگام‌سازی', 'orders' => 'سفارش', 'wallet' => 'کیف پول',
			'tickets' => 'پشتیبانی/گفتگو', 'rma' => 'مرجوعی', 'package' => 'بسته/محصول', 'rocket' => 'موشک/رشد',
			'users' => 'کاربران', 'star' => 'ستاره', 'shield' => 'امنیت', 'truck' => 'حمل و ارسال',
			'chart' => 'نمودار/آمار', 'suggest' => 'ایده/لامپ', 'gift' => 'هدیه', 'card' => 'کارت/پرداخت',
			'check' => 'تأیید', 'home' => 'خانه', 'phone' => 'تلفن', 'mail' => 'ایمیل', 'pin' => 'نشانی',
			'clock' => 'ساعت', 'download' => 'دانلود', 'book' => 'کتاب/مقاله',
			// v2.0.0 — آیکن‌های محصولی کارت‌های شناور
			'headphone' => 'هدفون', 'watch' => 'ساعت', 'bag' => 'کیف', 'mug' => 'ماگ',
			'cart' => 'سبد خرید', 'zap' => 'انرژی/برق', 'plus' => 'افزودن',
		);
	}

	/** پارس خطوط «برچسب|مقدار» */
	public static function parse_pairs( $raw ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$p     = array_map( 'trim', explode( '|', $line, 2 ) );
			$out[] = array( $p[0], isset( $p[1] ) ? $p[1] : '' );
		}
		return $out;
	}

	/** پارس خطوط ساده */
	public static function parse_lines( $raw ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * تجزیه مقدار آمار («2500+» / «۹۸٪») ← نمایش فارسی، پسوند، عدد شمارنده JS.
	 * @return array{fa:string,suffix:string,to:string}
	 */
	public static function stat_parts( $raw ) {
		$raw   = trim( (string) $raw );
		$fa2en = array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' );
		$en    = strtr( $raw, $fa2en );
		if ( preg_match( '/[0-9][0-9,٬.]*/', $en, $m ) ) {
			$digits = str_replace( array( ',', '٬' ), '', $m[0] );
			$num    = (float) $digits;
			$to     = ( $num == (int) $num ) ? (string) (int) $num : (string) $num;
			$suffix = trim( (string) substr( $en, strpos( $en, $m[0] ) + strlen( $m[0] ) ) );
			if ( false !== strpos( $digits, '.' ) ) {
				$fa = Dastyar_Theme_Settings::fa_num( $digits );
			} else {
				$fa = str_replace( ',', '٬', Dastyar_Theme_Settings::fa_num( number_format( (float) $digits, 0, '.', ',' ) ) );
			}
			return array( 'fa' => $fa, 'suffix' => $suffix, 'to' => $to );
		}
		return array( 'fa' => $raw, 'suffix' => '', 'to' => '' );
	}

	/** لینک دکمه اصلی: تنظیمات ← برگه ورود/ثبت‌نام قالب ← پنل ← خانه */
	public static function default_cta_url() {
		$u = trim( (string) Dastyar_Theme_Settings::get( 'cta1_url', '' ) );
		if ( $u ) {
			return $u;
		}
		$aid = (int) Dastyar_Theme_Settings::get( 'auth_page', 0 );
		if ( $aid && function_exists( 'get_permalink' ) ) {
			$p = get_permalink( $aid );
			if ( $p ) {
				return $p;
			}
		}
		if ( class_exists( 'Dastyar_Panel_Settings' ) && function_exists( 'get_permalink' ) ) {
			foreach ( array( 'auth_page', 'panel_page' ) as $k ) {
				$pid = (int) Dastyar_Panel_Settings::get( $k, 0 );
				if ( $pid ) {
					$p = get_permalink( $pid );
					if ( $p ) {
						return $p;
					}
				}
			}
		}
		return home_url( '/' );
	}

	/** لینک دکمه دوم: تنظیمات ← برگه کاتالوگ ← فروشگاه ووکامرس ← خانه */
	public static function default_cta2_url() {
		$u = trim( (string) Dastyar_Theme_Settings::get( 'cta2_url', '' ) );
		if ( $u ) {
			return $u;
		}
		$cid = (int) Dastyar_Theme_Settings::get( 'catalog_page', 0 );
		if ( $cid && function_exists( 'get_permalink' ) ) {
			$p = get_permalink( $cid );
			if ( $p ) {
				return $p;
			}
		}
		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	}

	/* ==================================================================
	 *  رندر لندینگ (فرانت‌پیج)
	 * ============================================================== */

		public function render() {
		$s = Dastyar_Theme_Settings::all();

		// v2.0.0 — اسکوپ استایل نسخه ۲ (گرافیک کارت‌های شناور) روی کل صفحه اصلی
		$out  = '<div class="dhm-home dth-v2' . ( Dastyar_Theme_Settings::yes( 'sec_hero' ) ? ' dhm-hero-on' : '' ) . '" dir="rtl">';
		$out .= $this->sec_hero( $s );
		$out .= $this->sec_stats( $s );
		$out .= $this->sec_features( $s );
		$out .= $this->sec_how( $s );
		// v2.1.0 — بخش‌های جدید صفحه اصلی (نمونه کاتالوگ، ویدیو، مزایا)
		$out .= $this->sec_catalog( $s );
		$out .= $this->sec_video( $s );
		$out .= $this->sec_benefits( $s );
		$out .= $this->sec_plans( $s );
		$out .= $this->sec_testi( $s );
		$out .= $this->sec_faq( $s );
		$out .= $this->sec_cta( $s );
		return $out . '</div>';
	}

	/**
	 * پارس خط‌های سه‌بخشی «عنوان|قیمت|رنگ|آیکن» / «متن|آیکن» (کارت‌های شناور و اعلان‌ها)
	 * @return array<int, array<int, string>>
	 */
	public static function parse_quads( $raw ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$p     = array_map( 'trim', explode( '|', $line ) );
			$out[] = array(
				isset( $p[0] ) ? $p[0] : '',
				isset( $p[1] ) ? $p[1] : '',
				isset( $p[2] ) ? $p[2] : '',
				isset( $p[3] ) ? $p[3] : '',
			);
		}
		return $out;
	}

	/** سربرگ بخش‌ها */
	public static function sec_head( $title, $sub ) {
		$h = '<div class="dhm-sec-head"><h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== trim( (string) $sub ) ) {
			$h .= '<p>' . esc_html( $sub ) . '</p>';
		}
		return $h . '</div>';
	}

	/** هیرو — پنج سبک (v2.1.0): cards | split | constellation | leadform | banner */
	protected function sec_hero( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_hero' ) ) {
			return '';
		}
		$style = (string) Dastyar_Theme_Settings::get( 'hero_style', 'cards' );
		switch ( $style ) {
			case 'banner':
				return $this->sec_hero_banner( $s );
			case 'split':
				return $this->sec_hero_split( $s );
			case 'constellation':
				return $this->sec_hero_constellation( $s );
			case 'leadform':
				return $this->sec_hero_leadform( $s );
			case 'flow3d':
				return $this->sec_hero_flow3d( $s );
			case 'cards':
			default:
				return $this->sec_hero_cards( $s );
		}
	}

	/** متن هیرو (ستون راست در سبک کارت‌ها / تنها در سبک باکس) */
	protected function hero_copy( $s, $dark = false ) {
		$b1u = '' !== trim( (string) $s['hero_b1_url'] ) ? $s['hero_b1_url'] : self::default_cta_url();
		$b2u = '' !== trim( (string) $s['hero_b2_url'] ) ? $s['hero_b2_url'] : '#features';

		$h = '';
		if ( '' !== trim( (string) $s['hero_badge'] ) ) {
			$h .= '<span class="dhm-badge' . ( $dark ? '' : ' dhm-badge-l' ) . '">' . self::icon( 'star' ) . esc_html( $s['hero_badge'] ) . '</span>';
		}
		$h .= '<h1>' . esc_html( $s['hero_title'] );
		if ( '' !== trim( (string) $s['hero_hl'] ) ) {
			$h .= ' <span class="dhm-hl">' . esc_html( $s['hero_hl'] ) . '</span>';
		}
		$h .= '</h1>';
		if ( '' !== trim( (string) $s['hero_sub'] ) ) {
			$h .= '<p class="dhm-hero-sub">' . esc_html( $s['hero_sub'] ) . '</p>';
		}
		$h .= '<div class="dhm-hero-btns">';
		if ( '' !== trim( (string) $s['hero_b1_text'] ) ) {
			$h .= '<a class="dhm-btn dhm-btn-primary" href="' . esc_url( $b1u ) . '">' . self::icon( 'zap' ) . esc_html( $s['hero_b1_text'] ) . ' ' . self::icon( 'arrow-l' ) . '</a>';
		}
		if ( '' !== trim( (string) $s['hero_b2_text'] ) ) {
			$h .= '<a class="dhm-btn ' . ( $dark ? 'dhm-btn-ghost' : 'dhm-btn-outline' ) . '" href="' . esc_url( $b2u ) . '">' . esc_html( $s['hero_b2_text'] ) . '</a>';
		}
		$h .= '</div>';
		return $h;
	}

	/** هیروی باکس کلاسیک (سبک نسخه ۱ — سرمه‌ای تمام‌عرض) */
	protected function sec_hero_banner( $s ) {
		$h  = '<section class="dhm-hero dhm-rv">';
		$h .= '<div class="dhm-in dhm-hero-in">';
		$h .= $this->hero_copy( $s, true );
		$points = self::parse_lines( $s['hero_points'] );
		if ( $points ) {
			$h .= '<ul class="dhm-hero-points">';
			foreach ( $points as $p ) {
				$h .= '<li>' . self::icon( 'check' ) . esc_html( $p ) . '</li>';
			}
			$h .= '</ul>';
		}
		return $h . '</div></section>';
	}

	/** هیروی v2 — متن + کارت‌های شناور از محصولات کاتالوگ */
	protected function sec_hero_cards( $s ) {
		$h  = '<section class="dhm-hero2 dhm-rv">';
		$h .= '<div class="dhm-in dhm-hero2-in">';

		// ستون متن
		$h .= '<div class="dhm-hero2-copy">' . $this->hero_copy( $s, false );
		$minis = self::parse_pairs( $s['hero_ministats'] );
		if ( $minis ) {
			$h .= '<div class="dhm-hero2-stats">';
			foreach ( array_slice( $minis, 0, 4 ) as $m ) {
				$h .= '<div class="dhm-hero2-stat"><b>' . esc_html( $m[0] ) . '</b><span>' . esc_html( isset( $m[1] ) && '' !== $m[1] ? $m[1] : '' ) . '</span></div>';
			}
			$h .= '</div>';
		}
		$h .= '</div>';

		// صحنه کارت‌های شناور
		$h .= '<div class="dhm-hero2-stage"><div class="dhm-h2-blob"></div><div class="dhm-h2-ring dhm-h2-r1"></div><div class="dhm-h2-ring dhm-h2-r2"></div>';

		$choices = Dastyar_Theme_Landing::icon_choices();
		$heroc   = trim( (string) $s['heroc_title'] );
		if ( '' !== $heroc ) {
			$ic  = isset( $choices[ $s['heroc_icon'] ] ) ? $s['heroc_icon'] : 'package';
			$h  .= '<div class="dhm-pcard dhm-pcard-main">';
			$h  .= '<div class="dhm-pimg dhm-ptint-1">' . self::icon( $ic ) . '</div>';
			$h  .= '<h4>' . esc_html( $heroc ) . '</h4>';
			$h  .= '<div class="dhm-ppr"><b>' . esc_html( $s['heroc_price'] ) . '</b>';
			if ( '' !== trim( (string) $s['heroc_old'] ) ) {
				$h .= '<s>' . esc_html( $s['heroc_old'] ) . '</s>';
			}
			$h .= '</div>';
			if ( '' !== trim( (string) $s['heroc_profit'] ) ) {
				$h .= '<div class="dhm-pmargin"><span>' . esc_html( $s['heroc_profit_label'] ) . '</span><b>' . esc_html( $s['heroc_profit'] ) . '</b></div>';
			}
			if ( '' !== trim( (string) $s['heroc_btn'] ) ) {
				$cu = '' !== trim( (string) $s['heroc_url'] ) ? $s['heroc_url'] : self::default_cta_url();
				$h .= '<a class="dhm-pbtn" href="' . esc_url( $cu ) . '">' . self::icon( 'plus' ) . esc_html( $s['heroc_btn'] ) . '</a>';
			}
			if ( '' !== trim( (string) $s['heroc_off'] ) ) {
				$h .= '<span class="dhm-poff">' . esc_html( $s['heroc_off'] ) . '</span>';
			}
			$h .= '</div>';
		}

		// کارت‌های کوچک پیرامونی
		$mini = self::parse_quads( $s['hero_minicards'] );
		$pos  = 1;
		foreach ( array_slice( $mini, 0, 3 ) as $c ) {
			if ( '' === $c[0] ) {
				continue;
			}
			$tint = in_array( $c[2], array( '1', '2', '3', '4' ), true ) ? $c[2] : ( ( $pos % 4 ) + 1 );
			$ic   = isset( $choices[ $c[3] ] ) ? $c[3] : 'package';
			$h   .= '<div class="dhm-pcard dhm-pcard-mini dhm-ppos' . (int) $pos . '">';
			$h   .= '<div class="dhm-pimg dhm-ptint-' . (int) $tint . '">' . self::icon( $ic ) . '</div>';
			$h   .= '<h4>' . esc_html( $c[0] ) . '</h4>';
			if ( '' !== $c[1] ) {
				$h .= '<div class="dhm-ppr"><b>' . esc_html( $c[1] ) . '</b></div>';
			}
			$h .= '</div>';
			$pos++;
		}

		// اعلان‌های شناور
		$notes = self::parse_quads( $s['hero_notes'] );
		$npos  = 1;
		foreach ( array_slice( $notes, 0, 2 ) as $nn ) {
			if ( '' === $nn[0] ) {
				continue;
			}
			$ic = isset( $choices[ $nn[1] ] ) ? $nn[1] : 'check';
			$h .= '<div class="dhm-pchip dhm-pnote' . (int) $npos . '">' . self::icon( $ic ) . '<span>' . esc_html( $nn[0] ) . '</span></div>';
			$npos++;
		}

		return $h . '</div></div></section>';
	}

	/** هیروی v2.1 «اسپلیت» — متن راست + فضای خلوت چپ (پترن نقطه‌ای نرم) */
	protected function sec_hero_split( $s ) {
		$h  = '<section class="dhm-hero3 dhm-hero3-split dhm-rv">';
		$h .= '<div class="dhm-in dhm-hero3-in">';
		$h .= '<div class="dhm-hero3-copy">' . $this->hero_copy( $s, false ) . '</div>';
		$h .= '<div class="dhm-hero3-art" aria-hidden="true"><span class="dhm-h3-dot d1"></span><span class="dhm-h3-dot d2"></span><span class="dhm-h3-dot d3"></span><span class="dhm-h3-ring"></span></div>';
		return $h . '</div></section>';
	}

	/** هیروی v2.1 «مداری» — متن بزرگ وسط + کارت‌های شناور در دو سوی تصویر */
	protected function sec_hero_constellation( $s ) {
		$h  = '<section class="dhm-hero3 dhm-hero3-orbit dhm-rv">';
		$h .= '<div class="dhm-in dhm-hero3-orbit-in">';
		// کارت‌های شناور ستون راست
		$notes = self::parse_quads( $s['hero_notes'] );
		$mini  = self::parse_quads( $s['hero_minicards'] );
		if ( isset( $notes[0] ) && '' !== $notes[0][0] ) {
			$h .= '<div class="dhm-o-card dhm-o-r1"><span class="dhm-o-ic t-gr">' . self::icon( 'cart' ) . '</span><div><b>' . esc_html( $notes[0][0] ) . '</b><span>همین الان</span></div></div>';
		}
		if ( isset( $mini[0] ) && '' !== $mini[0][0] ) {
			$h .= '<div class="dhm-o-card dhm-o-r2"><span class="dhm-o-ic t-nv">' . self::icon( 'package' ) . '</span><div><b>' . esc_html( $mini[0][0] ) . '</b><span>' . esc_html( $mini[0][1] ) . '</span></div></div>';
		}
		// متن مرکزی
		$h .= '<div class="dhm-hero3-copy dhm-hero3-center">';
		if ( '' !== trim( (string) $s['hero_badge'] ) ) {
			$h .= '<span class="dhm-badge dhm-badge-l">' . self::icon( 'zap' ) . esc_html( $s['hero_badge'] ) . '</span>';
		}
		$h .= '<h1 class="dhm-o-title">' . esc_html( $s['hero_title'] );
		if ( '' !== trim( (string) $s['hero_hl'] ) ) {
			$h .= ' <span class="dhm-hl">' . esc_html( $s['hero_hl'] ) . '</span>';
		}
		$h .= '</h1>';
		if ( '' !== trim( (string) $s['hero_sub'] ) ) {
			$h .= '<p class="dhm-hero-sub">' . esc_html( $s['hero_sub'] ) . '</p>';
		}
		$b1u = '' !== trim( (string) $s['hero_b1_url'] ) ? $s['hero_b1_url'] : self::default_cta_url();
		$b2u = '' !== trim( (string) $s['hero_b2_url'] ) ? $s['hero_b2_url'] : '#features';
		$h .= '<div class="dhm-hero-btns dhm-hero-btns-center">';
		if ( '' !== trim( (string) $s['hero_b1_text'] ) ) {
			$h .= '<a class="dhm-btn dhm-btn-primary" href="' . esc_url( $b1u ) . '">' . self::icon( 'zap' ) . esc_html( $s['hero_b1_text'] ) . '</a>';
		}
		if ( '' !== trim( (string) $s['hero_b2_text'] ) ) {
			$h .= '<a class="dhm-btn dhm-btn-outline" href="' . esc_url( $b2u ) . '">' . esc_html( $s['hero_b2_text'] ) . '</a>';
		}
		$h .= '</div></div>';
		// کارت‌های شناور ستون چپ
		if ( isset( $mini[1] ) && '' !== $mini[1][0] ) {
			$h .= '<div class="dhm-o-card dhm-o-l1"><span class="dhm-o-ic t-gr">' . self::icon( 'wallet' ) . '</span><div><b>' . esc_html( $mini[1][0] ) . '</b><span>' . esc_html( $mini[1][1] ) . '</span></div></div>';
		}
		if ( isset( $notes[1] ) && '' !== $notes[1][0] ) {
			$h .= '<div class="dhm-o-card dhm-o-l2"><span class="dhm-o-ic t-mt">' . self::icon( 'chart' ) . '</span><div><b>' . esc_html( $notes[1][0] ) . '</b><span>نمودار رشد</span></div></div>';
		}
		return $h . '</div></section>';
	}

	/** هیروی v2.1 «فرم جذب» — متن مرکزی + فیلد موبایل/شروع (همان لینک اصلی) */
	protected function sec_hero_leadform( $s ) {
		$h  = '<section class="dhm-hero3 dhm-hero3-lead dhm-rv">';
		$h .= '<div class="dhm-in dhm-hero3-copy dhm-hero3-center">';
		if ( '' !== trim( (string) $s['hero_badge'] ) ) {
			$h .= '<span class="dhm-badge dhm-badge-l">' . self::icon( 'star' ) . esc_html( $s['hero_badge'] ) . '</span>';
		}
		$h .= '<h1 class="dhm-o-title">' . esc_html( $s['hero_title'] );
		if ( '' !== trim( (string) $s['hero_hl'] ) ) {
			$h .= ' <span class="dhm-hl">' . esc_html( $s['hero_hl'] ) . '</span>';
		}
		$h .= '</h1>';
		if ( '' !== trim( (string) $s['hero_sub'] ) ) {
			$h .= '<p class="dhm-hero-sub">' . esc_html( $s['hero_sub'] ) . '</p>';
		}
		$b1u = '' !== trim( (string) $s['hero_b1_url'] ) ? $s['hero_b1_url'] : self::default_cta_url();
		// v2.4.0 — پاپ‌آپ مشاوره تلفنی: فرم با دیتا-اتریبیوت‌ها تجهیز می‌شود؛ بدون JS همان GET قبلی انجام می‌شود (فالبک حفظ شده)
		$dhm_leads_on = Dastyar_Theme_Settings::yes( 'leads_on' );
		$dhm_fattrs   = $dhm_leads_on
			? ' data-dth-lead="1" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'dth_lead' ) ) . '"'
			: '';
		$h .= '<form class="dhm-lead" method="get" action="' . esc_url( $b1u ) . '"' . $dhm_fattrs . '><input type="tel" name="phone" inputmode="tel" dir="ltr" placeholder="شماره موبایلت…"><button class="dhm-btn dhm-btn-primary" type="submit">' . esc_html( '' !== trim( (string) $s['hero_b1_text'] ) ? $s['hero_b1_text'] : 'شروع کن' ) . ' ' . self::icon( 'arrow-l' ) . '</button></form>';
		if ( $dhm_leads_on ) {
			static $dhm_lp_printed = false;
			if ( ! $dhm_lp_printed ) {
				$dhm_lp_printed = true;
				$h .= '<div class="dth-leadm" aria-hidden="true">'
					. '<div class="dth-leadm-bg" data-lead-close></div>'
					. '<div class="dth-leadm-card" role="dialog" aria-modal="true" aria-label="' . esc_attr( $s['lead_pop_title'] ) . '">'
					. '<button type="button" class="dth-leadm-x" data-lead-close aria-label="بستن">' . self::icon( 'close' ) . '</button>'
					. '<div class="dth-leadm-step dth-leadm-ask">'
					. '<span class="dth-leadm-ic">' . self::icon( 'headset' ) . '</span>'
					. '<h3>' . esc_html( $s['lead_pop_title'] ) . '</h3>'
					. '<p>' . esc_html( $s['lead_pop_text'] ) . '</p>'
					. '<p class="dth-leadm-phone" dir="ltr"></p>'
					. '<div class="dth-leadm-btns"><button type="button" class="dhm-btn dhm-btn-primary dth-leadm-yes">' . esc_html( $s['lead_pop_yes'] ) . '</button>'
					. '<button type="button" class="dhm-btn dhm-btn-outline dth-leadm-no">' . esc_html( $s['lead_pop_no'] ) . '</button></div>'
					. '<p class="dth-leadm-err" hidden>ثبت نشد؛ لطفاً دوباره تلاش کنید.</p>'
					. '</div>'
					. '<div class="dth-leadm-step dth-leadm-done" hidden>'
					. '<span class="dth-leadm-ic dth-leadm-okic">' . self::icon( 'check' ) . '</span>'
					. '<h3>' . esc_html( $s['lead_done_t'] ) . '</h3>'
					. '<p>' . esc_html( $s['lead_done_s'] ) . '</p>'
					. '<div class="dth-leadm-btns"><a class="dhm-btn dhm-btn-primary dth-leadm-go" href="' . esc_url( $b1u ) . '">ثبت‌نام فروشنده</a>'
					. '<button type="button" class="dhm-btn dhm-btn-outline" data-lead-close>بستن</button></div>'
					. '</div>'
					. '</div></div>';
			}
		}
		$points = self::parse_lines( $s['hero_points'] );
		if ( $points ) {
			$h .= '<div class="dhm-lead-ticks">';
			foreach ( array_slice( $points, 0, 3 ) as $p ) {
				$h .= '<span>' . self::icon( 'check' ) . esc_html( $p ) . '</span>';
			}
			$h .= '</div>';
		}
		return $h . '</div></section>';
	}

	/**
	 * هیروی v2.4.5 «کارتن سه‌بعدی معلق» — متن هیرو (راست) + یک کارتن پستی سه‌بعدی واقعی
	 * (با transform های CSS، بدون کتابخانه/فایل خارجی) که شناور است و به‌آرامی می‌چرخد؛
	 * دور آن پیام‌های وضعیت سفارش (ثبت/ارسال/تحویل/تسویه) به‌صورت یک حلقه دایره‌ای می‌چرخند.
	 */
	protected function sec_hero_flow3d( $s ) {
		$h  = '<section class="dhm-hero3d dhm-rv">';
		$h .= '<div class="dhm-in dhm-hero3d-in">';
		$h .= '<div class="dhm-hero3d-copy">' . $this->hero_copy( $s ) . '</div>';

		$msgs = self::parse_lines( (string) ( $s['hero_orbit_msgs'] ?? '' ) );
		if ( ! $msgs ) {
			$msgs = array(
				'✅ سفارش شما ثبت شد',
				'📦 سفارش با کد رهگیری ۱۲۳۴۵۶۷۸ ارسال شد',
				'🚚 سفارش به مشتری تحویل داده شد',
				'💰 سود فروش به کیف پولت اضافه شد',
			);
		}
		$msgs  = array_slice( $msgs, 0, 6 );
		$count = count( $msgs );

		$h .= '<div class="dth-3dbox-wrap">';
		$h .= '<div class="dth-orbit" style="--n:' . (int) $count . '">';
		foreach ( $msgs as $i => $msg ) {
			$angle = $count ? round( 360 / $count * $i ) : 0;
			$h    .= '<div class="dth-orbit-item" style="--a:' . $angle . 'deg"><span class="dth-toast">' . esc_html( $msg ) . '</span></div>';
		}
		$h .= '</div>';
		$h .= '<div class="dth-3dbox-stage">' . self::parcel_svg() . '</div>';
		$h .= '</div>';

		return $h . '</div></section>';
	}

	/**
	 * وکتور ایزومتریک کارتن پستی (نزدیک به واقعیت: رنگ کرافت، سایه‌روشن ۳ وجه، نوار
	 * چسب سبز روی درز جلو، برچسب پستی) — یک SVG ساده، بدون فایل/تصویر خارجی.
	 * چون تصویر ایزومتریک تخت است (نه سه‌بعدی واقعی)، فقط شناور می‌شود و کمی تاب می‌خورد؛
	 * چرخش کامل روی آن اعمال نمی‌شود (باعث می‌شد وجه‌ها به‌طور غیرواقعی جابه‌جا به‌نظر برسند).
	 */
	protected static function parcel_svg() {
		return '<svg class="dth-parcel" viewBox="0 0 220 230" role="img" aria-label="کارتن پستی">'
			. '<defs><filter id="dthPshadow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="6"/></filter></defs>'
			. '<ellipse cx="110" cy="208" rx="58" ry="11" fill="rgba(36,37,54,.16)" filter="url(#dthPshadow)"/>'
			. '<polygon points="40,74 110,112 110,202 40,164" fill="#d9ad78" stroke="#8a6339" stroke-width="1.5" stroke-linejoin="round"/>'
			. '<polygon points="110,112 180,74 180,164 110,202" fill="#c0915a" stroke="#8a6339" stroke-width="1.5" stroke-linejoin="round"/>'
			. '<polygon points="110,35 180,74 110,112 40,74" fill="#f0d3ac" stroke="#8a6339" stroke-width="1.5" stroke-linejoin="round"/>'
			. '<polygon points="90,101.1 108,110.9 108,200.9 90,191.1" fill="#17a16d"/>'
			. '<polygon points="112,110.9 130,101.1 130,191.1 112,200.9" fill="#17a16d"/>'
			. '<polygon points="97,71 110,78 123,71 110,64" fill="#2fce96" opacity=".85"/>'
			. '<rect x="136" y="116" width="36" height="30" rx="3" fill="#fbfdfc" stroke="#e6e8ef"/>'
			. '<rect x="142" y="123" width="24" height="2.4" rx="1.2" fill="#c7ccd8"/>'
			. '<rect x="142" y="129" width="20" height="2.4" rx="1.2" fill="#c7ccd8"/>'
			. '<rect x="142" y="135" width="14" height="2.4" rx="1.2" fill="#17a16d"/>'
			. '</svg>';
	}


	protected function sec_catalog( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_catalog' ) ) {
			return '';
		}
		$count = max( 1, min( 12, (int) $s['cat_count'] ) );
		$cols  = max( 2, min( 6, (int) $s['cat_cols'] ) );
		$h  = '<section class="dhm-sec dhm-sec-cats dhm-rv" id="catalog-peek"><div class="dhm-in">';
		$h .= self::sec_head( $s['cat_title'], $s['cat_sub'] );
		if ( function_exists( 'shortcode_exists' ) && shortcode_exists( 'dastyar_catalog' ) ) {
			$h .= '<div class="dhm-cats-wrap">' . do_shortcode( '[dastyar_catalog per_page="' . $count . '" columns="' . $cols . '" show_search="no" show_chips="no" show_popular="no"]' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapedOutput
		} else {
			$h .= '<div class="dhm-cats-empty"><p>برای نمایش این بخش، افزونه «دستیار کاتالوگ» باید فعال باشد.</p></div>';
		}
		if ( '' !== trim( (string) $s['cat_btn'] ) ) {
			$h .= '<div class="dhm-sec-more"><a class="dhm-btn dhm-btn-outline" href="' . esc_url( Dastyar_Theme::page_url( 'catalog_page', 'catalog' ) ) . '">' . esc_html( $s['cat_btn'] ) . ' ' . self::icon( 'arrow-l' ) . '</a></div>';
		}
		return $h . '</div></section>';
	}

	/** بخش «ویدیو آموزش» در صفحه اصلی (v2.1.0) — با iframe (آپارات/یوتیوب) یا <video> */
	protected function sec_video( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_video' ) ) {
			return '';
		}
		$vu = trim( (string) $s['video_url'] );
		if ( '' === $vu ) {
			return ''; // بدون لینک ویدیو، کل بخش مخفی می‌ماند
		}
		$h  = '<section class="dhm-sec dhm-sec-video dhm-rv" id="how-video"><div class="dhm-in">';
		$h .= self::sec_head( $s['video_title'], $s['video_sub'] );
		$h .= '<div class="dhm-video-wrap">';
		if ( preg_match( '/\.(mp4|webm|ogg)(\?|#|$)/i', $vu ) ) {
			$h .= '<video class="dhm-video" controls preload="none" playsinline src="' . esc_url( $vu ) . '"></video>';
		} else {
			$h .= '<div class="dhm-video-frame"><iframe src="' . esc_url( $vu ) . '" title="' . esc_attr( $s['video_title'] ) . '" loading="lazy" allowfullscreen frameborder="0"></iframe></div>';
		}
		$h .= '</div>';
		$vb = '' !== trim( (string) $s['video_btn_url'] ) ? $s['video_btn_url'] : Dastyar_Theme::page_url( 'tutorial_page', 'tutorial' );
		if ( '' !== trim( (string) $s['video_btn'] ) ) {
			$h .= '<div class="dhm-sec-more"><a class="dhm-btn dhm-btn-primary" href="' . esc_url( $vb ) . '">' . esc_html( $s['video_btn'] ) . ' ' . self::icon( 'arrow-l' ) . '</a></div>';
		}
		if ( '' !== trim( (string) $s['video_note'] ) ) {
			$h .= '<p class="dhm-video-note">' . self::icon( 'check' ) . esc_html( $s['video_note'] ) . '</p>';
		}
		return $h . '</div></section>';
	}

	/** بخش «مزایا» (v2.1.0) — نشان + تیتر + چک‌لیست ۲ ستونه + CTA */
	protected function sec_benefits( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_benefits' ) ) {
			return '';
		}
		$items = self::parse_lines( $s['ben_items'] );
		if ( ! $items ) {
			return '';
		}
		$h  = '<section class="dhm-sec dhm-sec-ben dhm-rv" id="benefits"><div class="dhm-in"><div class="dhm-ben-band">';
		$h .= '<div class="dhm-ben-r">';
		if ( '' !== trim( (string) $s['ben_badge'] ) ) {
			$h .= '<span class="dhm-badge dhm-badge-l">' . self::icon( 'star' ) . esc_html( $s['ben_badge'] ) . '</span>';
		}
		$h .= '<h2>' . esc_html( $s['ben_title'] ) . '</h2>';
		if ( '' !== trim( (string) $s['ben_sub'] ) ) {
			$h .= '<p>' . esc_html( $s['ben_sub'] ) . '</p>';
		}
		$bu = '' !== trim( (string) $s['ben_cta_url'] ) ? $s['ben_cta_url'] : self::default_cta_url();
		if ( '' !== trim( (string) $s['ben_cta_text'] ) ) {
			$h .= '<a class="dhm-btn dhm-btn-primary" href="' . esc_url( $bu ) . '">' . esc_html( $s['ben_cta_text'] ) . ' ' . self::icon( 'arrow-l' ) . '</a>';
		}
		$h .= '</div><div class="dhm-ben-list">';
		foreach ( $items as $bi ) {
			$h .= '<div class="dhm-ben-item"><span class="dhm-ben-ic">' . self::icon( 'check' ) . '</span><span>' . esc_html( $bi ) . '</span></div>';
		}
		return $h . '</div></div></div></section>';
	}

	/** رندر عمومی نوار آمار (برای برگه‌هایی مثل درباره ما) */
	public function stats_html( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_stats' ) ) {
			return '';
		}
		return $this->sec_stats( $s );
	}

	/** نوار آمار */
	protected function sec_stats( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_stats' ) ) {
			return '';
		}
		$h = '<section class="dhm-sec dhm-sec-stats dhm-rv"><div class="dhm-in"><div class="dhm-stats">';
		for ( $n = 1; $n <= 4; $n++ ) {
			$parts = self::stat_parts( $s[ 'stats_' . $n . '_num' ] );
			$h    .= '<div class="dhm-stat"><div class="dhm-stat-n">';
			if ( '' !== $parts['to'] ) {
				$h .= '<span class="dhm-count" data-dhm-to="' . esc_attr( $parts['to'] ) . '">' . esc_html( $parts['fa'] ) . '</span>';
			} else {
				$h .= '<span>' . esc_html( $parts['fa'] ) . '</span>';
			}
			if ( '' !== $parts['suffix'] ) {
				$h .= '<span class="dhm-stat-sfx">' . esc_html( $parts['suffix'] ) . '</span>';
			}
			$h .= '</div><div class="dhm-stat-l">' . esc_html( $s[ 'stats_' . $n . '_label' ] ) . '</div></div>';
		}
		return $h . '</div></div></section>';
	}

	/** امکانات */
	protected function sec_features( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_features' ) ) {
			return '';
		}
		$h = '<section class="dhm-sec dhm-rv" id="features"><div class="dhm-in">';
		$h .= self::sec_head( $s['feat_title'], $s['feat_sub'] );
		$h .= '<div class="dhm-feats">';
		for ( $n = 1; $n <= 6; $n++ ) {
			$title = trim( (string) $s[ 'feat_' . $n . '_title' ] );
			if ( '' === $title ) {
				continue;
			}
			$h .= '<div class="dhm-feat dhm-tint-' . ( ( ( $n - 1 ) % 4 ) + 1 ) . '"><span class="dhm-feat-ic">'
				. self::icon( $s[ 'feat_' . $n . '_icon' ] ) . '</span><h3>' . esc_html( $title ) . '</h3><p>'
				. esc_html( $s[ 'feat_' . $n . '_desc' ] ) . '</p></div>';
		}
		return $h . '</div></div></section>';
	}

	/** مراحل */
	protected function sec_how( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_how' ) ) {
			return '';
		}
		$h = '<section class="dhm-sec dhm-rv" id="how"><div class="dhm-in">';
		$h .= self::sec_head( $s['how_title'], $s['how_sub'] );
		$h .= '<div class="dhm-steps">';
		for ( $n = 1; $n <= 3; $n++ ) {
			$title = trim( (string) $s[ 'how_' . $n . '_title' ] );
			if ( '' === $title ) {
				continue;
			}
			$h .= '<div class="dhm-step"><span class="dhm-step-n">' . esc_html( Dastyar_Theme_Settings::fa_num( $n ) ) . '</span><h3>' . esc_html( $title ) . '</h3><p>' . esc_html( $s[ 'how_' . $n . '_desc' ] ) . '</p></div>';
		}
		return $h . '</div></div></section>';
	}

	/** تعرفه‌ها */
	protected function sec_plans( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_plans' ) ) {
			return '';
		}
		$h = '<section class="dhm-sec dhm-rv" id="plans"><div class="dhm-in">';
		$h .= self::sec_head( $s['plans_title'], $s['plans_sub'] );
		$h .= '<div class="dhm-plans">';
		for ( $n = 1; $n <= 3; $n++ ) {
			$name = trim( (string) $s[ 'plan_' . $n . '_name' ] );
			if ( '' === $name ) {
				continue;
			}
			$hot  = Dastyar_Theme_Settings::yes( 'plan_' . $n . '_hot' );
			$url  = '' !== trim( (string) $s[ 'plan_' . $n . '_url' ] ) ? $s[ 'plan_' . $n . '_url' ] : self::default_cta_url();
			$h   .= '<div class="dhm-plan' . ( $hot ? ' dhm-plan-hot' : '' ) . '">';
			if ( $hot ) {
				$h .= '<span class="dhm-plan-badge">محبوب‌ترین</span>';
			}
			$h .= '<h3>' . esc_html( $name ) . '</h3><div class="dhm-plan-price"><strong>' . esc_html( $s[ 'plan_' . $n . '_price' ] ) . '</strong>';
			if ( '' !== trim( (string) $s[ 'plan_' . $n . '_per' ] ) ) {
				$h .= '<span>' . esc_html( $s[ 'plan_' . $n . '_per' ] ) . '</span>';
			}
			$h .= '</div><ul>';
			foreach ( self::parse_lines( $s[ 'plan_' . $n . '_feats' ] ) as $f ) {
				$h .= '<li>' . self::icon( 'check' ) . esc_html( $f ) . '</li>';
			}
			$h .= '</ul>';
			if ( '' !== trim( (string) $s[ 'plan_' . $n . '_btn' ] ) ) {
				$h .= '<a class="dhm-btn ' . ( $hot ? 'dhm-btn-primary' : 'dhm-btn-outline' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $s[ 'plan_' . $n . '_btn' ] ) . '</a>';
			}
			$h .= '</div>';
		}
		return $h . '</div></div></section>';
	}

	/** نظرات */
	protected function sec_testi( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_testi' ) ) {
			return '';
		}
		$h = '<section class="dhm-sec dhm-rv" id="testi"><div class="dhm-in">';
		$h .= self::sec_head( $s['testi_title'], $s['testi_sub'] );
		$h .= '<div class="dhm-testis">';
		for ( $n = 1; $n <= 3; $n++ ) {
			$quote = trim( (string) $s[ 'testi_' . $n . '_quote' ] );
			if ( '' === $quote ) {
				continue;
			}
			$h .= '<div class="dhm-testi"><span class="dhm-testi-ic">' . self::icon( 'quote' ) . '</span><p>' . esc_html( $quote ) . '</p><footer><span class="dhm-testi-av">' . self::icon( 'user' ) . '</span><span class="dhm-testi-w"><strong>' . esc_html( $s[ 'testi_' . $n . '_name' ] ) . '</strong>';
			if ( '' !== trim( (string) $s[ 'testi_' . $n . '_shop' ] ) ) {
				$h .= '<em>' . esc_html( $s[ 'testi_' . $n . '_shop' ] ) . '</em>';
			}
			$h .= '</span></footer></div>';
		}
		return $h . '</div></div></section>';
	}

	/** سوالات متداول (آکوردئون بومی) */
	public static function faq_html( $items, $title = '' ) {
		$pairs = self::parse_pairs( $items );
		if ( ! $pairs ) {
			return '';
		}
		$h = '';
		if ( '' !== $title ) {
			$h .= self::sec_head( $title, '' );
		}
		$h .= '<div class="dhm-faqs">';
		foreach ( $pairs as $it ) {
			$h .= '<details class="dhm-faq"><summary>' . esc_html( $it[0] ) . '<span class="dhm-faq-ic"></span></summary><div class="dhm-faq-a">' . esc_html( $it[1] ) . '</div></details>';
		}
		return $h . '</div>';
	}

	/** سوالات متداول لندینگ */
	protected function sec_faq( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_faq' ) ) {
			return '';
		}
		$inner = self::faq_html( $s['faq_items'] );
		if ( '' === $inner ) {
			return '';
		}
		return '<section class="dhm-sec dhm-rv" id="faq"><div class="dhm-in">' . self::sec_head( $s['faq_title'], '' ) . $inner . '</div></section>';
	}

	/** CTA پایانی */
	protected function sec_cta( $s ) {
		if ( ! Dastyar_Theme_Settings::yes( 'sec_cta' ) ) {
			return '';
		}
		$url = '' !== trim( (string) $s['cta_url'] ) ? $s['cta_url'] : self::default_cta_url();
		$h   = '<section class="dhm-sec dhm-rv"><div class="dhm-in"><div class="dhm-cta-band">';
		$h  .= '<div class="dhm-cta-r"><span class="dhm-cta-ic">' . self::icon( 'rocket' ) . '</span><div><h3>' . esc_html( $s['cta_title'] ) . '</h3><p>' . esc_html( $s['cta_text'] ) . '</p></div></div>';
		if ( '' !== trim( (string) $s['cta_btn'] ) ) {
			$h .= '<a class="dhm-btn dhm-btn-primary" href="' . esc_url( $url ) . '">' . esc_html( $s['cta_btn'] ) . ' ' . self::icon( 'arrow-l' ) . '</a>';
		}
		return $h . '</div></div></section>';
	}
}
