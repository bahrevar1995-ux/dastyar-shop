<?php
/**
 * ثبت‌نام و ورود فروشنده — کاملاً بر پایه سیستم کاربران استاندارد وردپرس (User System جدید ساخته نشده).
 *
 * - شورتکد [dastyar_vendor_auth] : کارت زیبای ورود/ثبت‌نام با رنگ سازمانی (#17a16d)
 * - ثبت‌نام: کاربر با نقش «مشتری» ساخته می‌شود و پرچم _dastyar_pending_vendor می‌گیرد
 * - تا قبل از تأیید مدیر، به‌جای پنل فروشنده صفحه «در انتظار تأیید» نمایش داده می‌شود
 * - تأیید/رد: پیشخوان ← دستیار شاپ ← فروشندگان (Dastyar_Admin) — نقش کاربر به «فروشنده دستیار» تغییر می‌کند
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Registration {

	const PENDING = '_dastyar_pending_vendor';

	/**
	 * (v1.7.4 — بازخورد کاربر، مورد ۱۰) انواع فروشگاه آنلاین داوطلب — در فرم ثبت‌نام پرسیده می‌شود
	 * و در متای کاربر (_dastyar_shop_type) ذخیره می‌گردد؛ از طریق REST /ping هم در دسترس است.
	 */
	public static function shop_types() {
		return array(
			'woocommerce' => 'سایت ووکامرسی (وردپرس)',
			'builder'     => 'سایت‌ساز (شاپفا، ژاکت و…)',
			'instagram'   => 'پیج اینستاگرام',
			'telegram'    => 'کانال یا ربات تلگرام',
			'physical'    => 'فروش حضوری / کسب‌وکار سنتی',
			'none'        => 'هنوز فروشگاهی ندارم',
		);
	}

	/** پیام‌های خطا/موفقیت همین درخواست (بدون ریدایرکت) */
	protected $errors  = array();
	protected $success = '';

	public function __construct() {
		add_shortcode( 'dastyar_vendor_auth', array( $this, 'render' ) );
		add_action( 'template_redirect', array( $this, 'handle_posts' ), 5 );
		add_action( 'template_redirect', array( $this, 'pending_gate' ), 20 );
	}

	/** آیا این کاربر در انتظار تأیید فروشندگی است؟ */
	public static function is_pending( $uid ) {
		return (bool) $uid && (bool) get_user_meta( $uid, self::PENDING, true );
	}

	/* ------------------------------------------------------------------
	 * پردازش فرم‌ها
	 * ---------------------------------------------------------------- */

	public function handle_posts() {
		if ( empty( $_POST['dastyar_auth_action'] ) ) {
			return;
		}
		$action = sanitize_key( $_POST['dastyar_auth_action'] );

		if ( 'login' === $action ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dastyar_login' ) ) {
				$this->errors[] = 'نشست نامعتبر است. صفحه را تازه‌سازی کنید.';
				return;
			}
			$this->do_login();
		} elseif ( 'register' === $action ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dastyar_register' ) ) {
				$this->errors[] = 'نشست نامعتبر است. صفحه را تازه‌سازی کنید.';
				return;
			}
			$this->do_register();
		} elseif ( in_array( $action, array( 'otp_send', 'otp_verify' ), true ) ) {
			// ورود یک‌بارمصرف با پیامک (v1.6.0)
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dastyar_otp' ) ) {
				$this->errors[] = 'نشست نامعتبر است. صفحه را تازه‌سازی کنید.';
				return;
			}
			if ( 'otp_send' === $action ) {
				$this->do_otp_send();
			} else {
				$this->do_otp_verify();
			}
		} elseif ( 'forgot' === $action ) {
			// v1.10.15 — بازیابی رمز عبور (جایگزین لینک خام wp_lostpassword_url که کاربر را
			// از صفحه ورود/ثبت‌نام برند‌شده به wp-login.php خام می‌فرستاد و باعث سردرگمی می‌شد)
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dastyar_forgot' ) ) {
				$this->errors[] = 'نشست نامعتبر است. صفحه را تازه‌سازی کنید.';
				return;
			}
			$this->do_forgot();
		} elseif ( 'resetpass' === $action ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dastyar_resetpass' ) ) {
				$this->errors[] = 'نشست نامعتبر است. صفحه را تازه‌سازی کنید.';
				return;
			}
			$this->do_resetpass();
		}
	}

	/* ------------------------------------------------------------------
	 * ورود با پیامک (OTP) — v1.6.0
	 * ---------------------------------------------------------------- */

	/** سرویس OTP در دسترس است؟ (پیامک فعال + الگوی کد تعریف‌شده) */
	public static function otp_available() {
		return class_exists( 'Dastyar_Sms' ) && Dastyar_Sms::ready() && '' !== trim( (string) Dastyar_Sms::get( 'pattern_otp', '' ) );
	}

	/**
	 * v1.10.7 — نمایش تب «ورود با پیامک» در کارت ورود؟
	 * به درخواست کارفرما «فعلاً» حذف شد: پیش‌فرض false است و هندلرهای otp_send/otp_verify
	 * در پس‌زمینه دست‌نخورده فعال‌اند؛ بازگردانی آینده فقط با این فیلتر:
	 *   add_filter( 'dastyar_auth_otp_ui', '__return_true' );
	 */
	public static function otp_ui_enabled() {
		$on = false;
		if ( function_exists( 'apply_filters' ) ) {
			$on = (bool) apply_filters( 'dastyar_auth_otp_ui', $on );
		}
		return $on && self::otp_available();
	}

	/**
	 * آیکن‌های SVG خطی برند (v1.10.7) — جایگزین اموجی‌ها در کارت ورود/ثبت‌نام؛
	 * همان زبان طراحی قالب (stroke یک‌دست ۱.۸، currentColor).
	 */
	protected static function icon( $name, $size = 18 ) {
		$i = array(
			'store'    => '<path d="M3.5 9L5 4h14l1.5 5"/><path d="M3.5 9a2.65 2.65 0 0 0 5.3 0 2.65 2.65 0 0 0 5.4 0 2.6 2.6 0 0 0 5.3 0"/><path d="M5 11.5V19a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-7.5"/><path d="M9.5 20v-5h5v5"/>',
			'key'      => '<circle cx="8" cy="15.5" r="3.5"/><path d="M10.6 13.1L20 3.7"/><path d="M15.5 8l3 3M18 5.5l2 2"/>',
			'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21c1.4-3.6 4.3-5.2 7.5-5.2s6.1 1.6 7.5 5.2"/>',
			'lock'     => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
			'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
			'tel'      => '<path d="M15.5 3.5h-7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-13a2 2 0 0 0-2-2z"/><path d="M11 18h2"/>',
			'globe'    => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.5 2.3 3.7 5.3 3.7 8.5s-1.2 6.2-3.7 8.5c-2.5-2.3-3.7-5.3-3.7-8.5S9.5 5.8 12 3.5z"/>',
			'check'    => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
			'ok-c'     => '<circle cx="12" cy="12" r="8.5"/><path d="M8.3 12.4l2.6 2.6 5-5.6"/>',
			'alert'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.8v5"/><circle cx="12" cy="16.1" r="1" fill="currentColor" stroke="none"/>',
			'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
			'block'    => '<circle cx="12" cy="12" r="8.5"/><path d="M6.3 6.3l11.4 11.4"/>',
			'arrow-l'  => '<path d="M15 6l-6 6 6 6"/>',
			'sms'      => '<path d="M21 11.5a8.5 8.5 0 0 1-12.3 7.5L3 21l2-5.5A8.5 8.5 0 1 1 21 11.5z"/><path d="M8.5 10.5h7M8.5 13.5h4"/>',
			'shop-t'   => '<path d="M4 7l1.5-4h13L20 7v3"/><path d="M4 7v10a1 1 0 0 0 1 1h6"/><path d="M14 18.5l1.8 1.8 3.7-4"/>',
		);
		if ( ! isset( $i[ $name ] ) ) {
			return '';
		}
		return '<svg viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $i[ $name ] . '</svg>';
	}

	/** یافتن کاربر بر اساس موبایل (متای billing_phone — با تطبیق نرمال‌شده) */
	protected static function find_user_by_mobile( $mobile ) {
		$mobile = Dastyar_Sms::normalize_local( $mobile );
		if ( strlen( $mobile ) < 11 ) {
			return 0;
		}
		$tail    = substr( $mobile, -10 ); // «9123456789» — مشترک همه فرمت‌های نگارشی
		$users   = get_users( array(
			'number'      => 20,
			'meta_key'    => 'billing_phone',
			'meta_value'  => $tail,
			'meta_compare'=> 'LIKE',
			'fields'      => array( 'ID' ),
		) );
		foreach ( (array) $users as $u ) {
			if ( Dastyar_Sms::normalize_local( get_user_meta( (int) $u->ID, 'billing_phone', true ) ) === $mobile ) {
				return (int) $u->ID;
			}
		}
		return 0;
	}

	protected function otp_key( $mobile ) {
		return 'dastyar_otp_' . md5( $mobile );
	}

	/** مرحله ۱: دریافت موبایل و ارسال کد */
	protected function do_otp_send() {
		if ( ! empty( $_POST['dastyar_hp'] ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}
		if ( ! self::otp_available() ) {
			$this->errors[] = 'ورود با پیامک فعلاً فعال نیست.';
			return;
		}
		$mobile = Dastyar_Sms::normalize_local( sanitize_text_field( wp_unslash( $_POST['otp_mobile'] ?? '' ) ) );
		if ( strlen( $mobile ) < 11 || '09' !== substr( $mobile, 0, 2 ) ) {
			$this->errors[] = 'شماره موبایل معتبر نیست (مثلاً 09123456789).';
			return;
		}

		$uid = self::find_user_by_mobile( $mobile );
		if ( ! $uid ) {
			$this->errors[] = 'حسابی با این شماره پیدا نشد؛ لطفاً ابتدا از تب «ثبت‌نام فروشنده» ثبت‌نام کنید.';
			return;
		}

		// محدودیت ارسال مجدد: هر ۶۰ ثانیه یک‌بار
		$rate_key = $this->otp_key( $mobile ) . '_rate';
		if ( get_transient( $rate_key ) && ! get_transient( $this->otp_key( $mobile ) ) ) {
			$this->errors[] = 'کمی صبر کنید و سپس دوباره «دریافت کد» را بزنید.';
			return;
		}
		if ( get_transient( $rate_key ) ) {
			$this->success = 'کد قبلاً برایتان ارسال شده است؛ همان کد را وارد کنید یا تا انقضای آن چند لحظه صبر کنید.';
			return;
		}

		$code = (string) wp_rand( 10000, 99999 );
		set_transient( $this->otp_key( $mobile ), array(
			'hash'     => wp_hash( $code . '|' . $uid ),
			'uid'      => (int) $uid,
			'attempts' => 0,
		), 120 );
		set_transient( $rate_key, 1, 60 );

		$sent = Dastyar_Sms::send_pattern(
			$mobile,
			(string) Dastyar_Sms::get( 'pattern_otp', '' ),
			array( 'code' => $code ),
			(string) Dastyar_Sms::get( 'vars_otp', 'code=%code%' )
		);
		if ( true !== $sent ) {
			delete_transient( $this->otp_key( $mobile ) );
			delete_transient( $rate_key );
			$this->errors[] = 'ارسال پیامک ناموفق بود؛ لطفاً چند لحظه دیگر دوباره تلاش کنید.';
			return;
		}
		$this->success = 'کد ورود برای ' . $mobile . ' پیامک شد؛ ظرف ۲ دقیقه واردش کنید.';
	}

	/** مرحله ۲: بررسی کد و ورود */
	protected function do_otp_verify() {
		$mobile = Dastyar_Sms::normalize_local( sanitize_text_field( wp_unslash( $_POST['otp_mobile'] ?? '' ) ) );
		$code   = preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['otp_code'] ?? '' ) );
		$data   = get_transient( $this->otp_key( $mobile ) );

		if ( ! is_array( $data ) || empty( $data['hash'] ) ) {
			$this->errors[] = 'کد منقضی شده است؛ دوباره «دریافت کد» را بزنید.';
			return;
		}
		$attempts = (int) ( $data['attempts'] ?? 0 );
		if ( $attempts >= 5 ) {
			delete_transient( $this->otp_key( $mobile ) );
			$this->errors[] = 'تلاش‌های ناموفق زیاد بود؛ دوباره کد بگیرید.';
			return;
		}
		$data['attempts'] = $attempts + 1;
		set_transient( $this->otp_key( $mobile ), $data, 120 );

		if ( '' === $code || ! hash_equals( (string) $data['hash'], wp_hash( $code . '|' . (int) $data['uid'] ) ) ) {
			$this->errors[] = 'کد واردشده درست نیست.';
			return;
		}

		$uid = (int) $data['uid'];
		delete_transient( $this->otp_key( $mobile ) );
		wp_set_current_user( $uid );
		wp_set_auth_cookie( $uid, true );
		$user = get_userdata( $uid );
		do_action( 'wp_login', $user ? (string) ( $user->user_login ?? '' ) : '', $user );
		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * بازیابی رمز عبور — کاملاً داخل همین صفحه برند‌شده (بدون خروج به wp-login.php)
	 * ---------------------------------------------------------------- */

	/** مرحله ۱: درخواست لینک بازیابی با ایمیل/نام‌کاربری */
	protected function do_forgot() {
		if ( ! empty( $_POST['dastyar_hp'] ) ) { // هانی‌پات ضدربات
			wp_safe_redirect( home_url() );
			exit;
		}
		$login = sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) );
		if ( '' === $login ) {
			$this->errors[] = 'ایمیل یا نام کاربری را وارد کنید.';
			return;
		}
		$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
		// به دلایل امنیتی، چه کاربر پیدا شود چه نه، همیشه همین پیام یکسان نمایش داده می‌شود
		// (وگرنه می‌شد با امتحان ایمیل‌های مختلف فهمید کدام‌یک در سایت ثبت‌نام کرده است)
		if ( $user instanceof WP_User ) {
			$key = get_password_reset_key( $user );
			if ( ! is_wp_error( $key ) ) {
				$reset_url = add_query_arg(
					array( 'dastyar_auth' => 'resetpass', 'key' => $key, 'login' => rawurlencode( $user->user_login ) ),
					get_permalink()
				);
				wp_mail(
					$user->user_email,
					'بازیابی رمز عبور — دستیار شاپ',
					"برای تنظیم رمز عبور جدید حساب دستیار شاپ خود، روی لینک زیر کلیک کنید (تا ۱ ساعت معتبر است):\n\n{$reset_url}\n\nاگر این درخواست را شما نداده‌اید، همین ایمیل را نادیده بگیرید؛ رمز عبورتان تغییر نخواهد کرد."
				);
			}
		}
		$this->success = 'اگر این ایمیل/نام‌کاربری در دستیار شاپ ثبت‌نام شده باشد، لینک بازیابی رمز عبور همین الان برایش ایمیل شد.';
	}

	/** مرحله ۲: تنظیم رمز جدید از روی لینک ایمیل‌شده + ورود خودکار */
	protected function do_resetpass() {
		$login = sanitize_text_field( wp_unslash( $_POST['rp_login'] ?? '' ) );
		$key   = sanitize_text_field( wp_unslash( $_POST['rp_key'] ?? '' ) );
		$pass1 = (string) ( $_POST['password'] ?? '' );
		$pass2 = (string) ( $_POST['password2'] ?? '' );

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			$this->errors[] = 'لینک بازیابی نامعتبر یا منقضی شده است؛ از فرم پایین دوباره درخواست بدهید.';
			return;
		}
		if ( strlen( $pass1 ) < 8 ) {
			$this->errors[] = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
			return;
		}
		if ( $pass1 !== $pass2 ) {
			$this->errors[] = 'تکرار رمز عبور با رمز یکسان نیست.';
			return;
		}

		// نکته فنی: تابع reset_password() هسته وردپرس داخل wp-login.php تعریف شده و در
		// بارگذاری معمول صفحات لود نمی‌شود؛ به‌جایش مستقیم از wp_set_password (همیشه در
		// دسترس) استفاده می‌کنیم و اکشن استاندارد password_reset را هم برای سازگاری با
		// افزونه‌های دیگر شلیک می‌کنیم.
		do_action( 'password_reset', $user, $pass1 );
		wp_set_password( $pass1, $user->ID );

		// ورود خودکار بلافاصله بعد از تنظیم رمز — کاربر مجبور نیست دوباره فرم ورود را پر کند
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	protected function do_login() {
		// هانی‌پات ضدربات
		if ( ! empty( $_POST['dastyar_hp'] ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}
		$creds = array(
			'user_login'    => sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ),
			'user_password' => (string) ( $_POST['pwd'] ?? '' ),
			'remember'      => ! empty( $_POST['rememberme'] ),
		);
		if ( '' === $creds['user_login'] || '' === $creds['user_password'] ) {
			$this->errors[] = 'ایمیل و رمز عبور را وارد کنید.';
			return;
		}
		$user = wp_signon( $creds, is_ssl() );
		if ( is_wp_error( $user ) ) {
			$this->errors[] = 'ورود ناموفق بود: ایمیل یا رمز عبور اشتباه است.';
			return;
		}
		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	protected function do_register() {
		// هانی‌پات ضدربات
		if ( ! empty( $_POST['dastyar_hp'] ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}

		$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last_name  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		$shop_name  = sanitize_text_field( wp_unslash( $_POST['shop_name'] ?? '' ) );
		$shop_type  = sanitize_key( wp_unslash( $_POST['shop_type'] ?? '' ) ); // v1.7.4 — مورد ۱۰
		$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone      = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$site_url   = esc_url_raw( wp_unslash( $_POST['site_url'] ?? '' ) );
		$password   = (string) ( $_POST['password'] ?? '' );
		$password2  = (string) ( $_POST['password2'] ?? '' );

		if ( '' === $first_name || '' === $last_name ) {
			$this->errors[] = 'نام و نام خانوادگی الزامی است.';
		}
		if ( '' === $shop_name ) {
			$this->errors[] = 'نام فروشگاه الزامی است.';
		}
		if ( '' === $shop_type || ! isset( self::shop_types()[ $shop_type ] ) ) {
			$this->errors[] = 'نوع فروشگاه آنلاین‌تان را انتخاب کنید (ووکامرس، اینستاگرام و…).';
		}
		if ( ! is_email( $email ) ) {
			$this->errors[] = 'ایمیل معتبر نیست.';
		} elseif ( email_exists( $email ) ) {
			$this->errors[] = 'این ایمیل قبلاً ثبت شده است. از فرم ورود استفاده کنید.';
		}
		if ( '' === $phone ) {
			$this->errors[] = 'شماره موبایل الزامی است.';
		}
		if ( strlen( $password ) < 8 ) {
			$this->errors[] = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
		} elseif ( $password !== $password2 ) {
			$this->errors[] = 'تکرار رمز عبور با رمز یکسان نیست.';
		}

		if ( $this->errors ) {
			return;
		}

		// نام کاربری یکتا از روی ایمیل
		$base     = sanitize_user( strstr( $email, '@', true ), true );
		$base     = $base ?: 'vendor';
		$username = $base;
		$i        = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $i++;
		}

		$uid = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $uid ) ) {
			$this->errors[] = 'خطا در ساخت حساب: ' . $uid->get_error_message();
			return;
		}

		wp_update_user( array(
			'ID'           => $uid,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
			'role'         => 'customer', // تا زمان تأیید مدیر، فقط مشتری است
		) );

		update_user_meta( $uid, '_dastyar_shop_name', $shop_name );
		update_user_meta( $uid, '_dastyar_shop_type', $shop_type ); // v1.7.4 — مورد ۱۰
		update_user_meta( $uid, 'billing_phone', $phone );
		update_user_meta( $uid, 'billing_first_name', $first_name );
		update_user_meta( $uid, 'billing_last_name', $last_name );
		update_user_meta( $uid, 'billing_email', $email );
		if ( $site_url ) {
			Dastyar::instance()->vendors->set_site_url( $uid, $site_url );
		}
		update_user_meta( $uid, self::PENDING, 1 );

		// اطلاع به مدیر سایت
		$approve_url = admin_url( 'admin.php?page=dastyar-vendors' );
		wp_mail(
			get_option( 'admin_email' ),
			'درخواست فروشندگی جدید در دستیار شاپ: ' . $shop_name,
			sprintf(
				"درخواست فروشندگی جدید ثبت شد:\n\nنام: %s %s\nفروشگاه: %s\nنوع فروشگاه: %s\nایمیل: %s\nموبایل: %s\nسایت: %s\n\nبرای تأیید یا رد: %s",
				$first_name, $last_name, $shop_name, ( self::shop_types()[ $shop_type ] ?? $shop_type ), $email, $phone, $site_url ?: '—', $approve_url
			)
		);

		// ورود خودکار — صفحه «در انتظار تأیید» را خواهد دید
		wp_signon( array( 'user_login' => $username, 'user_password' => $password, 'remember' => true ), is_ssl() );
		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * گیت «در انتظار تأیید» — تا قبل از تأیید، پنل فروشنده نمایش داده نمی‌شود
	 * ---------------------------------------------------------------- */

	public function pending_gate() {
		if ( ! is_account_page() || ! is_user_logged_in() || current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$uid = get_current_user_id();
		if ( ! self::is_pending( $uid ) ) {
			return;
		}
		if ( get_user_meta( $uid, '_dastyar_vendor_rejected', true ) ) {
			$this->render_waiting_page( 'rejected' );
		} else {
			$this->render_waiting_page( 'pending' );
		}
		exit;
	}

	protected function render_waiting_page( $state ) {
		get_header();
		echo '<div class="dastyar-auth-wrap"><div class="dastyar-auth-card dastyar-waiting">';
		// v1.10.7 — نشان SVG وضعیت (به‌جای اموجی ساعت/منع قبلی)
		echo '<div class="dastyar-waiting-icon ' . ( 'rejected' === $state ? 'dastyar-wic-rej' : 'dastyar-wic-pend' ) . '">' . self::icon( 'rejected' === $state ? 'block' : 'clock', 40 ) . '</div>';
		if ( 'rejected' === $state ) {
			echo '<h2>درخواست فروشندگی شما بررسی شد</h2>';
			echo '<p>متأسفانه درخواست شما در این مرحله تأیید نشد. برای کسب اطلاعات بیشتر با پشتیبانی در تماس باشید.</p>';
		} else {
			echo '<h2>حساب شما در انتظار تأیید است</h2>';
			echo '<p>ثبت‌نام شما با موفقیت انجام شد.<br>درخواست فروشندگی شما در صف بررسی ماست؛ پس از تأیید مدیر، نقش کاربری‌تان به «فروشنده دستیار شاپ» تغییر می‌کند و پنل فروشنده برایتان فعال می‌شود. نتیجه از طریق ایمیل اطلاع‌رسانی می‌شود.</p>';
		}
		printf( '<p><a class="dastyar-btn" href="%s">بازگشت به صفحه اصلی</a> <a class="dastyar-btn dastyar-btn-ghost" href="%s">خروج از حساب</a></p>', esc_url( home_url() ), esc_url( wp_logout_url( home_url() ) ) );
		echo '</div></div>';
		$this->css();
		get_footer();
	}

	/* ------------------------------------------------------------------
	 * رندر شورتکد
	 * ---------------------------------------------------------------- */

	public function render() {
		// کاربر واردشده: نیازی به فرم نیست
		if ( is_user_logged_in() ) {
			ob_start();
			echo '<div class="dastyar-auth-wrap"><div class="dastyar-auth-card dastyar-waiting">';
			echo '<div class="dastyar-waiting-icon dastyar-wic-ok">' . self::icon( 'ok-c', 40 ) . '</div>'; // v1.10.7 — آیکن SVG (به‌جای اموجی تیک قبلی)
			printf( '<h2>سلام %s عزیز</h2>', esc_html( wp_get_current_user()->display_name ) );
			echo '<p>شما وارد حساب کاربری خود هستید.</p>';
			printf( '<p><a class="dastyar-btn" href="%s">رفتن به پنل فروشنده</a> <a class="dastyar-btn dastyar-btn-ghost" href="%s">خروج</a></p>', esc_url( wc_get_page_permalink( 'myaccount' ) ), esc_url( wp_logout_url( home_url() ) ) );
			echo '</div></div>';
			$this->css();
			return ob_get_clean();
		}

		// v1.10.15 — بازیابی رمز عبور: دو حالت اختصاصی که به‌جای تب‌های معمولی نمایش داده می‌شوند
		$dastyar_auth_mode   = sanitize_key( wp_unslash( $_GET['dastyar_auth'] ?? '' ) );
		$dastyar_post_action = sanitize_key( (string) ( $_POST['dastyar_auth_action'] ?? '' ) );

		// حالت ۲: کاربر از لینک ایمیل‌شده رسیده (یا فرم رمز جدید را ارسال کرد و خطا داشت)
		if ( 'resetpass' === $dastyar_auth_mode ) {
			$rp_login = sanitize_text_field( wp_unslash( $_POST['rp_login'] ?? ( $_GET['login'] ?? '' ) ) );
			$rp_key   = sanitize_text_field( wp_unslash( $_POST['rp_key'] ?? ( $_GET['key'] ?? '' ) ) );
			$rp_user  = check_password_reset_key( $rp_key, $rp_login );
			$rp_valid = ! is_wp_error( $rp_user );

			ob_start();
			echo '<div class="dastyar-auth-wrap"><div class="dastyar-auth-card">';
			echo '<div class="dastyar-auth-head"><div class="dastyar-auth-logo"><span class="dastyar-auth-logo-badge">' . self::icon( 'lock', 30 ) . '</span></div><h2>تنظیم رمز عبور جدید</h2></div>';
			foreach ( $this->errors as $err ) {
				printf( '<div class="dastyar-alert dastyar-alert-error">%s<span>%s</span></div>', self::icon( 'alert', 16 ), esc_html( $err ) );
			}
			if ( $rp_valid ) {
				echo '<form method="post">';
				wp_nonce_field( 'dastyar_resetpass' );
				echo '<input type="hidden" name="dastyar_auth_action" value="resetpass">';
				printf( '<input type="hidden" name="rp_login" value="%s">', esc_attr( $rp_login ) );
				printf( '<input type="hidden" name="rp_key" value="%s">', esc_attr( $rp_key ) );
				echo '<p class="dastyar-field"><label>رمز عبور جدید <b>*</b> <small>(حداقل ۸ کاراکتر)</small></label><span class="dastyar-fwrap"><input type="password" name="password" required autocomplete="new-password"><span class="dastyar-ficon">' . self::icon( 'lock', 16 ) . '</span></span></p>';
				echo '<p class="dastyar-field"><label>تکرار رمز عبور <b>*</b></label><span class="dastyar-fwrap"><input type="password" name="password2" required autocomplete="new-password"><span class="dastyar-ficon">' . self::icon( 'lock', 16 ) . '</span></span></p>';
				echo '<p><button type="submit" class="dastyar-btn dastyar-btn-block">تنظیم رمز جدید و ورود ' . self::icon( 'arrow-l', 15 ) . '</button></p>';
				echo '</form>';
			} else {
				echo '<p class="dastyar-note">این لینک منقضی شده یا قبلاً استفاده شده است.</p>';
				printf( '<p><a class="dastyar-btn dastyar-btn-block" href="%s">درخواست لینک بازیابی جدید</a></p>', esc_url( add_query_arg( 'dastyar_auth', 'forgot', get_permalink() ) ) );
			}
			echo '</div></div>';
			$this->css();
			return ob_get_clean();
		}

		// حالت ۱: درخواست لینک بازیابی (از لینک «رمز را فراموش کرده‌اید؟» یا ارسال فرم آن)
		if ( 'forgot' === $dastyar_auth_mode || 'forgot' === $dastyar_post_action ) {
			ob_start();
			echo '<div class="dastyar-auth-wrap"><div class="dastyar-auth-card">';
			echo '<div class="dastyar-auth-head"><div class="dastyar-auth-logo"><span class="dastyar-auth-logo-badge">' . self::icon( 'lock', 30 ) . '</span></div><h2>بازیابی رمز عبور</h2><p>ایمیل یا نام کاربری‌ات را وارد کن؛ لینک بازیابی برایت ایمیل می‌شود.</p></div>';
			foreach ( $this->errors as $err ) {
				printf( '<div class="dastyar-alert dastyar-alert-error">%s<span>%s</span></div>', self::icon( 'alert', 16 ), esc_html( $err ) );
			}
			if ( $this->success ) {
				printf( '<div class="dastyar-alert dastyar-alert-ok">%s<span>%s</span></div>', self::icon( 'ok-c', 16 ), esc_html( $this->success ) );
			} else {
				echo '<form method="post">';
				wp_nonce_field( 'dastyar_forgot' );
				echo '<input type="hidden" name="dastyar_auth_action" value="forgot">';
				echo '<input type="text" name="dastyar_hp" class="dastyar-hp" tabindex="-1" autocomplete="off">';
				printf( '<p class="dastyar-field"><label>ایمیل یا نام کاربری</label><span class="dastyar-fwrap"><input type="text" name="user_login" value="%s" required autocomplete="username"><span class="dastyar-ficon">' . self::icon( 'user', 16 ) . '</span></span></p>', esc_attr( wp_unslash( $_POST['user_login'] ?? '' ) ) );
				echo '<p><button type="submit" class="dastyar-btn dastyar-btn-block">ارسال لینک بازیابی ' . self::icon( 'arrow-l', 15 ) . '</button></p>';
				echo '</form>';
			}
			printf( '<p class="dastyar-lostpass"><a href="%s">بازگشت به ورود</a></p>', esc_url( remove_query_arg( 'dastyar_auth' ) ) );
			echo '</div></div>';
			$this->css();
			return ob_get_clean();
		}

		// v1.10.7 — پیش‌پرکردن موبایل از پارامتر لینک (باکس موبایل هیرو قالب ← ?phone=):
		// تب «ثبت‌نام فروشنده» فعال و فیلد موبایل پر می‌شود تا قیف ثبت‌نام گسسته نشود
		$pre_phone = sanitize_text_field( wp_unslash( $_GET['phone'] ?? '' ) );
		$pre_phone = preg_replace( '/\D+/', '', strtr( $pre_phone, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) ) );
		$pre_phone = substr( (string) $pre_phone, 0, 14 );

		$auth_action = sanitize_key( (string) ( $_POST['dastyar_auth_action'] ?? '' ) );
		if ( 'register' === $auth_action ) {
			$active_tab = 'register';
		} elseif ( in_array( $auth_action, array( 'otp_send', 'otp_verify' ), true ) ) {
			$active_tab = 'otp'; // (v1.6.0) تب ورود با پیامک — خطاهای OTP کاربر را به تب اشتباه نمی‌برند
		} else {
			$active_tab = $this->errors ? 'register' : ( '' !== $pre_phone ? 'register' : 'login' );
		}
		$otp_on     = self::otp_ui_enabled(); // v1.10.7 — تب «ورود با پیامک» فعلاً حذف است (سرویس OTP در پس‌زمینه فعال می‌ماند)
		$otp_mobile = sanitize_text_field( wp_unslash( $_POST['otp_mobile'] ?? '' ) );
		$otp_sent   = $otp_on && ( 'otp_send' === $auth_action && $this->success || get_transient( 'dastyar_otp_' . md5( Dastyar_Sms::normalize_local( $otp_mobile ) ) ) );

		ob_start();
		echo '<div class="dastyar-auth-wrap"><div class="dastyar-auth-card">';

		// هدر برند — v1.10.7: آیکن SVG فروشگاه در نشان شیشه‌ای (به‌جای اموجی)
		echo '<div class="dastyar-auth-head">';
		echo '<div class="dastyar-auth-logo"><span class="dastyar-auth-logo-badge">' . self::icon( 'store', 30 ) . '</span></div>';
		echo '<h2>فروشنده دستیار شاپ شوید</h2>';
		echo '<p>بدون سرمایه‌گذاری، محصولات ما را در فروشگاه خود بفروشید</p>';
		echo '</div>';

		// پیام‌ها — v1.10.7: با آیکن هشدار/تأیید
		foreach ( $this->errors as $err ) {
			printf( '<div class="dastyar-alert dastyar-alert-error">%s<span>%s</span></div>', self::icon( 'alert', 16 ), esc_html( $err ) );
		}
		if ( $this->success ) {
			printf( '<div class="dastyar-alert dastyar-alert-ok">%s<span>%s</span></div>', self::icon( 'ok-c', 16 ), esc_html( $this->success ) );
		}

		// تب‌ها — v1.10.7: با آیکن‌های خطی
		echo '<div class="dastyar-tabs">';
		printf( '<button type="button" class="dastyar-tab %s" data-tab="login">%s<span>ورود</span></button>', 'login' === $active_tab ? 'active' : '', self::icon( 'key', 16 ) );
		if ( $otp_on ) {
			printf( '<button type="button" class="dastyar-tab %s" data-tab="otp">%s<span>ورود با پیامک</span></button>', 'otp' === $active_tab ? 'active' : '', self::icon( 'sms', 16 ) );
		}
		printf( '<button type="button" class="dastyar-tab %s" data-tab="register">%s<span>ثبت‌نام فروشنده</span></button>', 'register' === $active_tab ? 'active' : '', self::icon( 'store', 16 ) );
		echo '</div>';

		// --- فرم ورود --- (v1.10.7: آیکن درون فیلدها)
		printf( '<div class="dastyar-pane %s" data-pane="login">', 'login' === $active_tab ? 'active' : '' );
		echo '<form method="post">';
		wp_nonce_field( 'dastyar_login' );
		echo '<input type="hidden" name="dastyar_auth_action" value="login">';
		echo '<input type="text" name="dastyar_hp" class="dastyar-hp" tabindex="-1" autocomplete="off">';
		echo '<p class="dastyar-field"><label>ایمیل یا نام کاربری</label><span class="dastyar-fwrap"><input type="text" name="log" required autocomplete="username"><span class="dastyar-ficon">' . self::icon( 'user', 16 ) . '</span></span></p>';
		echo '<p class="dastyar-field"><label>رمز عبور</label><span class="dastyar-fwrap"><input type="password" name="pwd" required autocomplete="current-password"><span class="dastyar-ficon">' . self::icon( 'lock', 16 ) . '</span></span></p>';
		echo '<p class="dastyar-remember"><label><input type="checkbox" name="rememberme" value="1" checked> مرا به خاطر بسپار</label></p>';
		echo '<p><button type="submit" class="dastyar-btn dastyar-btn-block">ورود به پنل فروشنده ' . self::icon( 'arrow-l', 15 ) . '</button></p>';
		printf( '<p class="dastyar-lostpass"><a href="%s">رمز عبور را فراموش کرده‌اید؟</a></p>', esc_url( add_query_arg( 'dastyar_auth', 'forgot', get_permalink() ) ) );
		echo '</form></div>';

		// --- ورود با پیامک (OTP) — v1.6.0 ---
		if ( $otp_on ) {
			printf( '<div class="dastyar-pane %s" data-pane="otp">', 'otp' === $active_tab ? 'active' : '' );

			// مرحله ۱: موبایل ← دریافت کد
			echo '<form method="post">';
			wp_nonce_field( 'dastyar_otp' );
			echo '<input type="hidden" name="dastyar_auth_action" value="otp_send">';
			echo '<input type="text" name="dastyar_hp" class="dastyar-hp" tabindex="-1" autocomplete="off">';
			printf( '<p class="dastyar-field"><label>موبایل ثبت‌نام‌شده</label><input type="tel" name="otp_mobile" value="%s" style="direction:ltr" placeholder="09xxxxxxxxx" required autocomplete="tel"></p>', esc_attr( $otp_mobile ) );
			echo '<p><button type="submit" class="dastyar-btn dastyar-btn-block dastyar-btn-otp">دریافت کد ورود ←</button></p>';
			if ( ! $otp_sent ) {
				echo '<p class="dastyar-note">کد ورود فقط برای شماره‌ای پیامک می‌شود که قبلاً در دستیار شاپ ثبت‌نام کرده است.</p>';
			}
			echo '</form>';

			// مرحله ۲: واردکردن کد (بعد از ارسال، یا اگر کد فعالی برای همان شماره هست)
			if ( $otp_sent ) {
				echo '<form method="post" class="dastyar-otp-step2">';
				wp_nonce_field( 'dastyar_otp' );
				echo '<input type="hidden" name="dastyar_auth_action" value="otp_verify">';
				printf( '<input type="hidden" name="otp_mobile" value="%s">', esc_attr( $otp_mobile ) );
				echo '<p class="dastyar-field"><label>کد پیامک‌شده (۵ رقم)</label><input type="text" name="otp_code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" style="direction:ltr;text-align:center;letter-spacing:8px;font-size:22px;font-weight:800" placeholder="۰•۰•۰•۰•۰" required autocomplete="one-time-code"></p>';
				echo '<p><button type="submit" class="dastyar-btn dastyar-btn-block">تأیید و ورود به پنل ←</button></p>';
				echo '<p class="dastyar-note">کد تا ۲ دقیقه معتبر است؛ اگر نرسید، دوباره «دریافت کد» را بزنید.</p>';
				echo '</form>';
			}
			echo '</div>';
		}

		// --- فرم ثبت‌نام --- (v1.10.7: آیکن درون فیلدها + پیش‌پرکردن موبایل)
		printf( '<div class="dastyar-pane %s" data-pane="register">', 'register' === $active_tab ? 'active' : '' );
		echo '<form method="post">';
		wp_nonce_field( 'dastyar_register' );
		echo '<input type="hidden" name="dastyar_auth_action" value="register">';
		echo '<input type="text" name="dastyar_hp" class="dastyar-hp" tabindex="-1" autocomplete="off">';
		echo '<div class="dastyar-grid2">';
		printf( '<p class="dastyar-field"><label>نام <b>*</b></label><span class="dastyar-fwrap"><input type="text" name="first_name" value="%s" required><span class="dastyar-ficon">' . self::icon( 'user', 16 ) . '</span></span></p>', esc_attr( wp_unslash( $_POST['first_name'] ?? '' ) ) );
		printf( '<p class="dastyar-field"><label>نام خانوادگی <b>*</b></label><span class="dastyar-fwrap"><input type="text" name="last_name" value="%s" required><span class="dastyar-ficon">' . self::icon( 'user', 16 ) . '</span></span></p>', esc_attr( wp_unslash( $_POST['last_name'] ?? '' ) ) );
		echo '</div>';
		printf( '<p class="dastyar-field"><label>نام فروشگاه <b>*</b></label><span class="dastyar-fwrap"><input type="text" name="shop_name" value="%s" placeholder="مثلاً: فروشگاه آنلاین پارس" required><span class="dastyar-ficon">' . self::icon( 'store', 16 ) . '</span></span></p>', esc_attr( wp_unslash( $_POST['shop_name'] ?? '' ) ) );
		// v1.7.4 — مورد ۱۰: نوع فروشگاه آنلاین داوطلب
		$st_sel = sanitize_key( wp_unslash( $_POST['shop_type'] ?? '' ) );
		echo '<p class="dastyar-field"><label>کجا آنلاین می‌فروشید؟ <b>*</b></label><span class="dastyar-fwrap dastyar-fwrap-sel"><select name="shop_type" required>';
		echo '<option value="">— انتخاب کنید —</option>';
		foreach ( self::shop_types() as $k => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $st_sel, $k, false ), esc_html( $label ) );
		}
		echo '</select><span class="dastyar-ficon">' . self::icon( 'shop-t', 16 ) . '</span></span></p>';
		echo '<div class="dastyar-grid2">';
		printf( '<p class="dastyar-field"><label>ایمیل <b>*</b></label><span class="dastyar-fwrap"><input type="email" name="email" value="%s" style="direction:ltr" required><span class="dastyar-ficon">' . self::icon( 'mail', 16 ) . '</span></span></p>', esc_attr( wp_unslash( $_POST['email'] ?? '' ) ) );
		$reg_phone_val = '' !== (string) ( $_POST['phone'] ?? '' ) ? wp_unslash( $_POST['phone'] ?? '' ) : $pre_phone;
		printf( '<p class="dastyar-field"><label>موبایل <b>*</b></label><span class="dastyar-fwrap"><input type="tel" name="phone" value="%s" style="direction:ltr" placeholder="09xxxxxxxxx" required><span class="dastyar-ficon">' . self::icon( 'tel', 16 ) . '</span></span></p>', esc_attr( $reg_phone_val ) );
		echo '</div>';
		printf( '<p class="dastyar-field"><label>آدرس سایت فروشگاه (اختیاری)</label><span class="dastyar-fwrap"><input type="url" name="site_url" value="%s" style="direction:ltr" placeholder="https://example.com"><span class="dastyar-ficon">' . self::icon( 'globe', 16 ) . '</span></span></p>', esc_attr( wp_unslash( $_POST['site_url'] ?? '' ) ) );
		echo '<div class="dastyar-grid2">';
		echo '<p class="dastyar-field"><label>رمز عبور <b>*</b> <small>(حداقل ۸ کاراکتر)</small></label><span class="dastyar-fwrap"><input type="password" name="password" required autocomplete="new-password"><span class="dastyar-ficon">' . self::icon( 'lock', 16 ) . '</span></span></p>';
		echo '<p class="dastyar-field"><label>تکرار رمز عبور <b>*</b></label><span class="dastyar-fwrap"><input type="password" name="password2" required autocomplete="new-password"><span class="dastyar-ficon">' . self::icon( 'lock', 16 ) . '</span></span></p>';
		echo '</div>';
		echo '<p><button type="submit" class="dastyar-btn dastyar-btn-block">ثبت درخواست فروشندگی ' . self::icon( 'arrow-l', 15 ) . '</button></p>';
		echo '<p class="dastyar-note">' . self::icon( 'ok-c', 15 ) . ' پس از ثبت‌نام، درخواست شما توسط مدیر بررسی می‌شود و پس از <strong>تأیید</strong>، نقش «فروشنده دستیار شاپ» به شما داده می‌شود و پنل فروشنده فعال خواهد شد.</p>';
		echo '</form></div>';

		echo '</div></div>';
		$this->css();
		$this->js();
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------
	 * استایل و اسکریپت برند (رنگ سازمانی #17a16d)
	 * ---------------------------------------------------------------- */

	protected function css() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<style>
		.dastyar-auth-wrap{max-width:520px;margin:32px auto;padding:0 12px}
		/* فونت از قالب سایت به ارث می‌رسد تا با سایت شما هماهنگ باشد */
		.dastyar-auth-wrap,.dastyar-auth-wrap *{font-family:inherit}
		.dastyar-auth-wrap button,.dastyar-auth-wrap input,.dastyar-auth-wrap select,.dastyar-auth-wrap textarea{font-family:inherit!important}
		.dastyar-auth-card{background:#fff;border-radius:18px;box-shadow:0 10px 40px rgba(23,161,109,.13),0 2px 8px rgba(0,0,0,.05);overflow:hidden;border:1px solid #e8f5ef}
		.dastyar-auth-head{background:linear-gradient(135deg,#17a16d,#0f8a5c);color:#fff;text-align:center;padding:30px 24px 26px}
		.dastyar-auth-logo{font-size:40px;margin-bottom:6px}
		.dastyar-auth-head h2{margin:0 0 6px;font-size:22px;font-weight:800;color:#fff}
		.dastyar-auth-head p{margin:0;opacity:.92;font-size:13.5px}
		.dastyar-tabs{display:flex;border-bottom:1px solid #e5e5e5}
		.dastyar-tab{flex:1;background:#fafbfa;border:0;padding:14px;font-size:15px;font-weight:700;cursor:pointer;color:#666;transition:.2s}
		.dastyar-tab.active{color:#17a16d;background:#fff;box-shadow:inset 0 -3px 0 #17a16d}
		.dastyar-pane{display:none;padding:24px 26px 28px}
		.dastyar-pane.active{display:block}
		.dastyar-tab{white-space:nowrap;font-size:14px}
		.dastyar-otp-step2{margin-top:16px;padding-top:16px;border-top:2px dashed #e0efe8}
		.dastyar-btn-otp{background:#242536!important}
		.dastyar-btn-otp:hover{background:#1a1b28!important}
		.dastyar-field{margin:0 0 14px}
		.dastyar-field label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;color:#333}
		.dastyar-field label b{color:#d63638}
		.dastyar-field label small{font-weight:400;color:#888}
		.dastyar-field input,.dastyar-field select{width:100%;padding:11px 13px;border:1.5px solid #dde3df;border-radius:10px;font-size:14px;transition:.2s;box-sizing:border-box;background:#fff;font-family:inherit}
		.dastyar-field input:focus,.dastyar-field select:focus{border-color:#17a16d;outline:none;box-shadow:0 0 0 3px rgba(23,161,109,.14)}
		.dastyar-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
		@media (max-width:480px){.dastyar-grid2{grid-template-columns:1fr}}
		.dastyar-btn{display:inline-block;background:#17a16d;color:#fff;border:0;border-radius:10px;padding:12px 26px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;transition:.2s}
		.dastyar-btn:hover{background:#12875c;color:#fff;transform:translateY(-1px)}
		.dastyar-btn-block{width:100%;text-align:center;margin-top:4px}
		.dastyar-btn-ghost{background:#eef4f1!important;color:#17a16d!important}
		.dastyar-remember{font-size:13px;color:#555;margin:0 0 12px}
		.dastyar-lostpass{text-align:center;font-size:12.5px;margin:14px 0 0}
		.dastyar-lostpass a{color:#17a16d}
		.dastyar-note{background:#f0faf5;border:1px solid #cdeee0;color:#146c4b;font-size:12.5px;border-radius:10px;padding:12px 14px;margin:16px 0 0;line-height:1.9}
		.dastyar-alert{border-radius:10px;padding:12px 14px;margin:16px 24px 0;font-size:13.5px;line-height:1.8}
		.dastyar-alert-error{background:#fdf0f0;border:1px solid #f3caca;color:#a52828}
		.dastyar-alert-ok{background:#f0faf5;border:1px solid #cdeee0;color:#146c4b}
		.dastyar-waiting{padding:44px 30px;text-align:center}
		.dastyar-waiting-icon{font-size:46px;margin-bottom:10px}
		.dastyar-waiting h2{margin:0 0 10px;font-size:20px;color:#123}
		.dastyar-waiting p{color:#555;line-height:2;font-size:14px}
		.dastyar-hp{position:absolute;right:-5000px;opacity:0;height:0;width:0;overflow:hidden}
		/* v1.10.7 — بازطراحی کارت: نشان SVG هدر، آیکن تب‌ها/فیلدها/پیام‌ها، نشان وضعیت انتظار */
		.dastyar-auth-head{background:linear-gradient(135deg,#14805a,#17a16d 55%,#1fae74);position:relative;overflow:hidden}
		.dastyar-auth-head::after{content:"";position:absolute;inset-inline-end:-60px;top:-80px;width:210px;height:210px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.16),transparent 65%);pointer-events:none}
		.dastyar-auth-head>*{position:relative;z-index:1}
		.dastyar-auth-logo{margin-bottom:10px}
		.dastyar-auth-logo-badge{display:inline-flex;align-items:center;justify-content:center;width:62px;height:62px;border-radius:18px;background:rgba(255,255,255,.14);border:1.5px solid rgba(255,255,255,.32);color:#fff;box-shadow:0 8px 20px rgba(0,0,0,.12)}
		.dastyar-tab{display:inline-flex;align-items:center;justify-content:center;gap:6px}
		.dastyar-tab svg{flex:none}
		.dastyar-alert{display:flex;align-items:center;gap:8px}
		.dastyar-alert svg{flex:none}
		.dastyar-fwrap{position:relative;display:block}
		.dastyar-ficon{position:absolute;inset-inline-start:12px;top:50%;transform:translateY(-50%);display:inline-flex;line-height:0;color:#9aa5a0;pointer-events:none;transition:color .2s}
		.dastyar-fwrap input,.dastyar-fwrap select{padding-inline-start:38px!important}
		.dastyar-fwrap input:focus+.dastyar-ficon,.dastyar-fwrap select:focus+.dastyar-ficon{color:#17a16d}
		.dastyar-btn svg{vertical-align:-2px;margin-inline-start:6px}
		.dastyar-note svg{vertical-align:-3px;margin-inline-end:4px;color:#17a16d}
		.dastyar-waiting-icon{display:inline-flex;align-items:center;justify-content:center;width:88px;height:88px;border-radius:50%;margin:0 auto 16px}
		.dastyar-wic-ok{background:#e9f6ee;color:#17a16d}
		.dastyar-wic-pend{background:#fdf4d8;color:#d97706}
		.dastyar-wic-rej{background:#fdf0f0;color:#dc2626}
		</style>
		<?php
	}

	protected function js() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<script>
		document.addEventListener('click', function(e){
			var t = e.target.closest('.dastyar-tab');
			if (!t) return;
			var card = t.closest('.dastyar-auth-card');
			card.querySelectorAll('.dastyar-tab').forEach(function(x){ x.classList.remove('active'); });
			card.querySelectorAll('.dastyar-pane').forEach(function(x){ x.classList.remove('active'); });
			t.classList.add('active');
			card.querySelector('.dastyar-pane[data-pane="' + t.dataset.tab + '"]').classList.add('active');
		});
		</script>
		<?php
	}
}
