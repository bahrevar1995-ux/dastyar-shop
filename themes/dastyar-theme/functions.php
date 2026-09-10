<?php
/**
 * قالب «دستیار» — بوت‌استرپ، اتولودر، ستاپ، دارایی‌ها، ساخت خودکار برگه‌ها.
 * اصل قطعی v1.0.1: قالب در «پیشخوان» کاملاً بی‌اثر است؛ تنها نقطه تماس با ادمین،
 * صفحه تنظیمات خود قالب است — صفحات ووکامرس (ویرایش محصول و…) مطلقاً دست‌نخورده می‌مانند.
 *
 * جانشین افزونه «دستیار هوم» (همان موتور لندینگ + هدر، حالا به‌صورت قالب واقعی).
 * هنگام فعال‌بودن قالب، کروم افزونه دستیار هوم (اگر نصب باشد) خودکار بی‌اثر می‌شود
 * تا هدر دوباره رندر نشود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// v2.4.9 — رفع باگ مهم: نسخه دارایی‌ها (CSS/JS) قبلاً روی «۲.۴.۱» فریز شده بود و هیچ‌وقت
// با نسخه واقعی قالب هم‌گام نمی‌شد — یعنی مرورگر/کش سرور می‌توانست main.css قدیمی را
// نگه دارد و تغییرات جدید (مثل همین منوی اپلیکیشنی) اصلاً بارگذاری نشوند. از این به بعد
// نسخه مستقیم از هدر style.css خوانده می‌شود، پس هر آپدیت خودکار یک کش‌باستینگ تازه دارد.
define( 'DTH_VERSION', function_exists( 'wp_get_theme' ) ? (string) wp_get_theme()->get( 'Version' ) : '2.4.9' );
define( 'DTH_DIR', trailingslashit( dirname( __FILE__ ) ) );
define( 'DTH_URI', function_exists( 'get_template_directory_uri' ) ? trailingslashit( get_template_directory_uri() ) : '' );

/**
 * Autoloader کلاس‌های Dastyar_Theme* — نام کلاس: Dastyar_Theme_Settings → inc/class-dastyar-theme-settings.php
 */
spl_autoload_register( function ( $class ) {
	if ( 'Dastyar_Theme' !== $class && strpos( $class, 'Dastyar_Theme_' ) !== 0 ) {
		return;
	}
	$file = DTH_DIR . 'inc/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

final class Dastyar_Theme {

	/** @var Dastyar_Theme_Landing|null */
	private static $landing = null;

	/** موتور لندینگ قالب (singleton) */
	public static function landing() {
		if ( null === self::$landing ) {
			self::$landing = new Dastyar_Theme_Landing();
		}
		return self::$landing;
	}

	/** لینک برگه از تنظیمات با فالبک */
	public static function page_url( $setting_key, $fallback = '/' ) {
		$pid = (int) Dastyar_Theme_Settings::get( $setting_key, 0 );
		if ( $pid && function_exists( 'get_permalink' ) ) {
			$u = get_permalink( $pid );
			if ( $u ) {
				return $u;
			}
		}
		return home_url( $fallback );
	}

	/**
	 * ساخت خودکار همه برگه‌های قالب + فرانت‌پیج + منو.
	 *
	 * @param bool $only_if_none اگر true باشد برگه‌هایی که شناسه‌دارند رد می‌شوند
	 * @return array شناسه‌های برگه (کلید تنظیمات → شناسه)
	 */
	public static function auto_pages( $only_if_none = true ) {
		$defs = array(
			'home_page'     => array( 'صفحه اصلی', '' ),
			'about_page'    => array( 'درباره ما', 'page-templates/about.php' ),
			'contact_page'  => array( 'تماس با ما', 'page-templates/contact.php' ),
			'faq_page'      => array( 'سوالات متداول', 'page-templates/faq.php' ),
			'panel_page'    => array( 'پنل کاربری', 'page-templates/panel.php' ),
			'download_page' => array( 'دریافت پلاگین', 'page-templates/download.php' ),
			'auth_page'     => array( 'ورود / ثبت‌نام', 'page-templates/auth.php' ),
			'pay_page'      => array( 'روند پرداخت', 'page-templates/payment.php' ),
		'catalog_page'  => array( 'محصولات', 'page-templates/catalog.php' ),
		'tutorial_page' => array( 'آموزش استفاده', 'page-templates/tutorial.php' ), // v1.0.5 — راهنمای قدم‌به‌قدم فروشنده‌ها
		'blog_page'     => array( 'مقالات', '' ),
	);
		$made = array();
		$s    = Dastyar_Theme_Settings::all();
		foreach ( $defs as $key => $d ) {
			if ( $only_if_none && ! empty( $s[ $key ] ) ) {
				$made[ $key ] = (int) $s[ $key ];
				continue;
			}
			$pid = wp_insert_post( array(
				'post_type'    => 'page',
				'post_title'   => $d[0],
				'post_content' => '',
				'post_status'  => 'publish',
			), true );
			if ( ! is_wp_error( $pid ) && $pid ) {
				$made[ $key ] = (int) $pid;
				update_option( Dastyar_Theme_Settings::OPTION, array_merge( Dastyar_Theme_Settings::all(), array( $key => (int) $pid ) ) );
				if ( $d[1] ) {
					update_post_meta( (int) $pid, '_wp_page_template', $d[1] );
				}
				$s[ $key ] = (int) $pid;
			}
		}

		// فرانت‌پیج + برگه وبلاگ (فقط وقتی سایت تنظیم نکرده)
		if ( ! empty( $made['home_page'] ) && 'page' !== (string) get_option( 'show_on_front', 'posts' ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $made['home_page'] );
			if ( ! empty( $made['blog_page'] ) ) {
				update_option( 'page_for_posts', (int) $made['blog_page'] );
			}
		}

		// منوی خودکار اگر هیچ منویی به جای primary تخصیص نداده شده
		if ( function_exists( 'has_nav_menu' ) && ! has_nav_menu( 'primary' ) && function_exists( 'wp_create_nav_menu' ) ) {
			$menu_id = wp_create_nav_menu( 'منوی دستیار' );
			if ( $menu_id && ! is_wp_error( $menu_id ) ) {
				$i = 0;
				wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'خانه', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-position' => ++$i ) );
				foreach ( array( 'catalog_page' => 'محصولات', 'tutorial_page' => 'آموزش استفاده', 'about_page' => 'درباره ما', 'faq_page' => 'سوالات متداول', 'pay_page' => 'روند پرداخت', 'blog_page' => 'مقالات', 'contact_page' => 'تماس با ما' ) as $key => $title ) {
					if ( ! empty( $made[ $key ] ) ) {
						wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $title, 'menu-item-url' => get_permalink( (int) $made[ $key ] ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-position' => ++$i ) );
					}
				}
				$locs            = (array) get_theme_mod( 'nav_menu_locations', array() );
				$locs['primary'] = $menu_id;
				$locs['footer']  = $menu_id;
				set_theme_mod( 'nav_menu_locations', $locs );
			}
		}
		return $made;
	}
}

/* ==================================================================
 *  ستاپ قالب
 * ============================================================== */

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'custom-logo', array( 'height' => 96, 'width' => 320, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	// سازگاری با ووکامرس (بدون اورراید قالب‌ها — فقط اعلام سازگاری)
	add_theme_support( 'woocommerce' );
	register_nav_menus( array(
		'primary' => 'منوی اصلی (هدر)',
		'footer'  => 'منوی فوتر',
	) );
} );

/* دارایی‌ها: فونت وزیرمتن + استایل اصلی + اسکریپ트 تعاملات + لود آنی */
/* v2.2.0 — دارک‌مود: اعمال تم ذخیره‌شده «قبل از» رندر استایل‌ها (بدون فلش سفید) */
add_action( 'wp_head', function () {
	echo "<script>(function(){try{var t=localStorage.getItem('dastyar-theme');if(t!=='dark'&&t!=='light'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}if(t==='dark'){document.documentElement.classList.add('dhm-dark');}document.documentElement.style.colorScheme=t;}catch(e){}})();</script>\n";
}, 0 );

// v2.4.9 — منوی اپلیکیشنی موبایل: کلاس روی body (برای فاصله پایین صفحه) + چاپ خودِ نوار
add_filter( 'body_class', function ( $classes ) {
	if ( Dastyar_Theme_Landing::mobnav_should_show() ) {
		$classes[] = 'dth-has-mobnav';
	}
	return $classes;
} );
add_action( 'wp_footer', array( 'Dastyar_Theme_Landing', 'render_mobile_nav' ) );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'dth-font', 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css', array(), '33.003' );
	wp_enqueue_style( 'dth-main', DTH_URI . 'assets/main.css', array( 'dth-font' ), DTH_VERSION );
	wp_enqueue_script( 'dth-app', DTH_URI . 'assets/main.js', array(), DTH_VERSION, true );
	if ( Dastyar_Theme_Settings::yes( 'instant_load' ) ) {
		wp_enqueue_script( 'dth-instant', DTH_URI . 'assets/instant.js', array( 'dth-app' ), DTH_VERSION, true );
		$exclude = Dastyar_Theme_Landing::parse_lines( Dastyar_Theme_Settings::get( 'pj_exclude', "/wp-admin/\n/wp-login.php\n/checkout\n/cart\n/my-account" ) );
		wp_add_inline_script(
			'dth-instant',
			'window.DTH_CFG=' . wp_json_encode( array(
				'main'    => 'dth-main',
				'sel'     => '#dth-main',
				'exclude' => $exclude,
			) ) . ';',
			'before'
		);
	}
} );

/* ساخت خودکار برگه‌ها هنگام فعال‌شدن قالب */
add_action( 'after_switch_theme', function () {
	Dastyar_Theme::auto_pages( true );
} );

/**
 * بی‌اثرکردن کروم افزونه «دستیار هوم» وقتی قالب دستیار فعال است
 * (کاربر را به مهاجرت نرم تنبیه نمی‌کنیم — هدر دوتایی رندر نمی‌شود).
 */
add_action( 'init', function () {
	if ( is_admin() || ! class_exists( 'Dastyar_Home' ) ) {
		return; // قالب در پیشخوان هیچ اثری ندارد (حتی این گارد)
	}
	$inst  = method_exists( 'Dastyar_Home', 'instance' ) ? Dastyar_Home::instance() : null;
	$front = ( $inst && isset( $inst->front ) ) ? $inst->front : null;
	if ( ! $front ) {
		return;
	}
	remove_action( 'wp_body_open', array( $front, 'render_header' ) );
	remove_action( 'wp_head', array( $front, 'head_css' ) );
	remove_action( 'template_redirect', array( $front, 'start_ob' ), 1 );
	remove_filter( 'template_include', array( $front, 'template_fullpage' ), 99 );
}, 30 );

/* اخطار مدیریت: مهاجرت از افزونه دستیار هوم */
add_action( 'admin_notices', function () {
	global $pagenow;
	$dth_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'themes.php' !== (string) $pagenow && 'dastyar-theme' !== $dth_page ) {
		return; // خارج از «پوسته‌ها» و صفحه تنظیمات خودمان، هیچ خروجی در پیشخوان چاپ نمی‌شود
	}
	if ( class_exists( 'Dastyar_Home' ) && current_user_can( 'manage_options' ) ) {
		echo '<div class="notice notice-info"><p>قالب «دستیار» جایگزین افزونه «دستیار هوم» شده است؛ هدر آن افزونه برای جلوگیری از تداخل خودکار خاموش است. توصیه می‌شود افزونه «دستیار هوم» را <strong>غیرفعال و حذف</strong> کنید (تنظیمات‌تان حفظ شده و به قالب منتقل می‌شود).</p></div>';
	}
} );

/* ---------- گارد فروشگاه ووکامرس: نمایش نده، به کاتالوگ بفرست ---------- */
add_action( 'template_redirect', function () {
	if ( is_admin() || ! function_exists( 'is_shop' ) || ! Dastyar_Theme_Settings::yes( 'shop_guard' ) ) {
		return;
	}
	if ( current_user_can( 'manage_woocommerce' ) ) {
		return; // مدیر همه‌جا را می‌بیند
	}
	// (v1.0.3 — بازخورد کاربر، مورد ۱) اگر قالب تکیِ کاتالوگ فعال است، صفحه محصول تکی به آرشیو
	// برنمی‌گردد: فروشنده با کلیک روی محصول داخل کاتالوگ به «صفحه محصول» (قیمت + دکمه‌ها) می‌رسد.
	$catalog_single = class_exists( 'Dastyar_Cat_Settings' ) && 'yes' === Dastyar_Cat_Settings::get( 'single_override', 'yes' );
	$blocked = is_shop()
		|| ( function_exists( 'is_product' ) && is_product() && ! $catalog_single )
		|| ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() );
	if ( $blocked ) {
		wp_safe_redirect( Dastyar_Theme::page_url( 'catalog_page', 'catalog' ) );
		exit;
	}
}, 3 );

/* محصولات ووکامرس از نتایج جستجوی عمومی خارج شوند (فقط پست و برگه) */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ! function_exists( 'is_search' ) || ! is_search() ) {
		return; // در پیشخوان و AJAX هیچ تغییری در کوئری‌ها نمی‌دهیم
	}
	if ( is_object( $q ) && method_exists( $q, 'is_main_query' ) && $q->is_main_query() && method_exists( $q, 'set' ) ) {
		$q->set( 'post_type', array( 'post', 'page' ) );
	}
} );

/* ---------- هدایت کاربر لاگین‌شده از صفحه ورود به پنل ---------- */
add_action( 'template_redirect', function () {
	if ( is_admin() ) {
		return;
	}
	$aid = (int) Dastyar_Theme_Settings::get( 'auth_page', 0 );
	if ( ! $aid || ! function_exists( 'is_page' ) || ! is_page( $aid ) || ! is_user_logged_in() ) {
		return;
	}
	wp_safe_redirect( Dastyar_Theme::page_url( 'panel_page', 'panel' ) );
	exit;
}, 5 );

/* ---------- هندلر فرم تماس (ارسال ایمیل به مدیر) ---------- */
add_action( 'template_redirect', function () {
	if ( is_admin() || empty( $_POST['dth_contact'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dth_contact' ) ) {
		return;
	}
	$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	if ( ! Dastyar_Theme_Settings::yes( 'contact_form_enabled' ) ) {
		wp_safe_redirect( add_query_arg( 'dth_sent', 'off', $back ) );
		exit;
	}
	$name  = sanitize_text_field( wp_unslash( $_POST['c_name'] ?? '' ) );
	$email = sanitize_email( wp_unslash( $_POST['c_email'] ?? '' ) );
	$phone = sanitize_text_field( wp_unslash( $_POST['c_phone'] ?? '' ) );
	$msg   = sanitize_textarea_field( wp_unslash( $_POST['c_msg'] ?? '' ) );
	if ( '' === $name || '' === $msg ) {
		wp_safe_redirect( add_query_arg( 'dth_sent', 'err', $back ) );
		exit;
	}
	$to  = (string) get_option( 'admin_email', '' );
	$ok  = $to ? wp_mail( $to, 'پیام از فرم تماس: ' . $name, "نام: {$name}\nایمیل: {$email}\nتلفن: {$phone}\n\n{$msg}" ) : false;
	wp_safe_redirect( add_query_arg( 'dth_sent', $ok ? '1' : 'err', $back ) );
	exit;
}, 4 );

/* v2.4.0 — درخواست‌های مشاوره تلفنی (AJAX فرانت + صفحه مدیریت پیشخوان) */
Dastyar_Theme_Leads::boot();

if ( is_admin() ) {
	new Dastyar_Theme_Admin();
}
