<?php
/**
 * نقش «کارمند دستیار» — یک نقش وردپرسی سفارشی که:
 *  - فقط دو قابلیت دارد: read (برای اینکه یک کاربر واردشده معتبر باشد) و
 *    manage_woocommerce (همان قابلیتی که تمام ماژول‌های کنسول با آن چک می‌شوند —
 *    یعنی همه ۵۰ ماژول کنسول بدون هیچ تغییری، همان‌طور که هستند، برای این نقش هم کار می‌کنند)
 *  - هیچ قابلیت دیگری ندارد: نه ساخت/ویرایش نوشته، نه دسترسی به افزونه‌ها/قالب‌ها/کاربران.
 *  - وقتی چنین کاربری بخواهد وارد پیشخوان وردپرس (wp-admin) شود، به‌جایش به پنل مستقل
 *    کارمندان (صفحه‌ای با کدکوتاه [dastyar_staff_panel]) هدایت می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Staff {

	const ROLE = 'dastyar_staff';

	public function boot() {
		add_action( 'init', array( __CLASS__, 'ensure_role' ) );
		add_action( 'admin_init', array( $this, 'block_wp_admin' ) );
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 20, 3 );
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
	}

	/** ساخت نقش (اگر قبلاً نباشد) — idempotent، هر بار init صدا زده می‌شود ولی فقط یک‌بار واقعاً کاری می‌کند */
	public static function ensure_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, 'کارمند دستیار', array(
				'read'               => true,
				'manage_woocommerce' => true,
			) );
		}
	}

	/** آیا کاربر جاری دسترسی کنسول (کارمند یا مدیر واقعی) دارد؟ */
	public static function current_user_has_access() {
		return is_user_logged_in() && current_user_can( 'manage_woocommerce' );
	}

	/** فقط مدیر واقعی سایت (نه کارمند) — برای بخش «مدیریت کارمندان» */
	public static function current_user_is_owner() {
		return current_user_can( 'create_users' ) && current_user_can( 'manage_options' );
	}

	/** اگر کارمند بخواهد وارد wp-admin شود (به‌جز admin-post.php/admin-ajax.php)، به پنل مستقل هدایتش کن */
	public function block_wp_admin() {
		if ( wp_doing_ajax() || defined( 'DOING_CRON' ) ) {
			return;
		}
		$user = wp_get_current_user();
		if ( ! $user || ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return; // کاربر مدیر واقعی — دست نمی‌زنیم
		}
		global $pagenow;
		if ( 'admin-post.php' === $pagenow ) {
			return; // ارسال فرم‌های خودِ کنسول از طریق admin-post.php — این باید کار کند
		}
		$panel = self::panel_url();
		if ( $panel ) {
			wp_safe_redirect( $panel );
			exit;
		}
	}

	/** بعد از لاگین، کارمند مستقیم برود به پنل مستقل، نه پیشخوان وردپرس */
	public function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( $user instanceof WP_User && in_array( self::ROLE, (array) $user->roles, true ) ) {
			$panel = self::panel_url();
			if ( $panel ) {
				return $panel;
			}
		}
		return $redirect_to;
	}

	/** نوار بالای سایت (Admin Bar) برای کارمندها نمایش داده نشود — لینک «پیشخوان» توش هست */
	public function hide_admin_bar( $show ) {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( in_array( self::ROLE, (array) $user->roles, true ) ) {
				return false;
			}
		}
		return $show;
	}

	/** آدرس صفحه‌ای که کدکوتاه [dastyar_staff_panel] رویش قرار دارد (اولین یافته) */
	public static function panel_url() {
		$cached = get_option( '_dastyar_staff_panel_page_id' );
		if ( $cached && get_post_status( $cached ) ) {
			return get_permalink( $cached );
		}
		$q = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			's'              => '',
			'posts_per_page' => 1,
			'meta_query'     => array(), // نگه‌داشته برای گسترش آینده
		) );
		// جستجوی ساده در محتوای صفحات برای کدکوتاه — فقط یک‌بار در هر درخواست
		global $wpdb;
		$id = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='page' AND post_content LIKE '%[dastyar_staff_panel%' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $id ) {
			update_option( '_dastyar_staff_panel_page_id', (int) $id );
			return get_permalink( (int) $id );
		}
		return home_url( '/' ); // فالبک امن؛ بهتر است هرچه زودتر صفحه ساخته شود
	}
}
