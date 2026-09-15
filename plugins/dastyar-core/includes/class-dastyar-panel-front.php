<?php
/**
 * پنل فروشنده در فرانت — کدکوتاه [dastyar_vendor_panel]
 *
 * مستقل از «حساب کاربری من» ووکامرس اما کاملاً روی زیرساخت Dastyar Core:
 *  - بخش‌های حیاتی (ثبت سفارش دستی، پیشنهاد محصول) از رندرهای آماده Core استفاده می‌کنند
 *    (چرخ دوباره اختراع نشده) و فقط کروم/ناوبری/داشبورد و بهبودهای UX این‌جاست.
 *  - فرم‌های Core که dastyar_action پست می‌کنند این‌جا هم پشتیبانی می‌شوند؛
 *    هندلر My Account فقط روی is_account_page فعال است پس تداخلی پیش نمی‌آید.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Panel_Front {

	public function __construct() {
		add_shortcode( 'dastyar_vendor_panel', array( $this, 'render' ) );
		// اولویت ۴: قبل از هندلرهای Core اجرا می‌شود (آن‌ها خودشان روی My Account گیت دارند)
		add_action( 'template_redirect', array( $this, 'handle_posts' ), 4 );
	}

	/* ==================================================================
	 *  بخش‌ها
	 * ============================================================== */

	/** بخش‌های قابل‌نمایش (با احترام به سوییچ‌های تنظیمات) */
	public function sections() {
		$all = array(
			'dashboard' => array( 'dash', 'پیشخوان' ),
			'orders'    => array( 'orders', 'سفارش‌ها' ),
			'manual'    => array( 'manual', 'ثبت سفارش دستی' ),
			'invoices'  => array( 'invoices', 'صورتحساب‌ها' ),
			'wallet'    => array( 'wallet', 'کیف پول' ),
			'favorites' => array( 'heart', 'علاقه‌مندی‌ها' ),
			'rma'       => array( 'rma', 'مرجوعی‌ها' ),
			'suggest'   => array( 'suggest', 'پیشنهاد محصول' ),
			'api'       => array( 'api', 'اتصال فروشگاه' ),
			'tickets'   => array( 'tickets', 'پشتیبانی' ),
			'contract'  => array( 'contract', 'قرارداد همکاری' ),
			'account'   => array( 'account', 'حساب کاربری' ),
		);

		// داشبورد و حساب کاربری همیشه فعال‌اند
		foreach ( array( 'orders', 'manual', 'invoices', 'wallet', 'rma', 'suggest', 'api', 'tickets', 'contract' ) as $slug ) {
			if ( ! Dastyar_Panel_Settings::yes( 'sec_' . $slug ) ) {
				unset( $all[ $slug ] );
			}
		}
		// قرارداد فقط وقتی متنِ تنظیم‌شده دارد نمایش داده می‌شود (v1.4.0)
		if ( isset( $all['contract'] ) && '' === trim( (string) Dastyar_Panel_Settings::get( 'contract_text', '' ) ) ) {
			unset( $all['contract'] );
		}
		// مرجوعی‌ها وابسته به ماژول RMA در Core
		if ( isset( $all['rma'] ) && ! class_exists( 'Dastyar_Rma' ) ) {
			unset( $all['rma'] );
		}
		// v1.10.18 — علاقه‌مندی‌ها وابسته به ماژول Growth در Core
		if ( isset( $all['favorites'] ) && ! class_exists( 'Dastyar_Growth' ) ) {
			unset( $all['favorites'] );
		}
		return $all;
	}

	/**
	 * آیکن‌های SVG لاین‌دیزاین (stroke=currentColor) — بدون اموجی.
	 * @param string $name نام آیکن
	 */
	public static function icon( $name ) {
		$o = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
		$c = '</svg>';
		$i = array(
			'dash'     => '<rect x="3" y="3" width="7" height="7" rx="1.6"/><rect x="14" y="3" width="7" height="7" rx="1.6"/><rect x="3" y="14" width="7" height="7" rx="1.6"/><rect x="14" y="14" width="7" height="7" rx="1.6"/>',
			'orders'   => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0"/><path d="M9.5 11h5M9.5 15h3.5"/>',
			'manual'   => '<path d="M4 20l1-4L16.6 4.4a2.1 2.1 0 0 1 3 3L8 19l-4 1z"/><path d="M14 6l3 3"/>',
			'invoices' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/>',
			'wallet'   => '<path d="M4 7h13a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7z"/><path d="M4 7V5.5A2.5 2.5 0 0 1 6.5 3H16"/><circle cx="16.2" cy="13.8" r="1.1" fill="currentColor" stroke="none"/>',
			'rma'      => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4.5V9h4.5"/>',
			'suggest'  => '<path d="M9.5 18.5h5"/><path d="M10.5 21.5h3"/><path d="M12 3a6 6 0 0 0-3.4 10.9c.7.6 1 1.3 1.2 2.1h4.4c.2-.8.5-1.5 1.2-2.1A6 6 0 0 0 12 3z"/>',
			'api'      => '<path d="M9 7V3M15 7V3"/><path d="M7 7h10v4a5 5 0 0 1-10 0V7z"/><path d="M12 16v5"/>',
			'tickets'  => '<path d="M21 11.5a8.5 8.5 0 0 1-12.3 7.5L3 21l2-5.5A8.5 8.5 0 1 1 21 11.5z"/>',
			'account'  => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21c1.4-3.6 4.3-5.2 7.5-5.2s6.1 1.6 7.5 5.2"/>',
			'home'     => '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
			'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M15.5 16.5L21 11l-5.5-5.5"/><path d="M21 11H9.5"/>',
			'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21c1.4-3.6 4.3-5.2 7.5-5.2s6.1 1.6 7.5 5.2"/>',
			'package'  => '<path d="M21 16V8a2 2 0 0 0-1-1.7l-6-3.5a2 2 0 0 0-2 0L6 6.3A2 2 0 0 0 5 8v8a2 2 0 0 0 1 1.7l6 3.5a2 2 0 0 0 2 0l6-3.5A2 2 0 0 0 21 16z"/><path d="M3.3 7.3L12 12.2l8.7-4.9"/><path d="M12 22V12"/>',
			'card'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/>',
			'layers'   => '<path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>',
			'gift'     => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
			'edit'     => '<path d="M4 20l1-4L16.6 4.4a2.1 2.1 0 0 1 3 3L8 19l-4 1z"/><path d="M14 6l3 3"/>',
			'contract' => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/><path d="M9.5 12h5M9.5 15.5h5"/><path d="M9 19c.8-1.2 1.6-1.2 2.4 0s1.6 1.2 2.4 0"/>',
			'doc'      => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/><path d="M9.5 12h5M9.5 15.5h5"/>',
			'trend'    => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
			'check'    => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
			'fire'     => '<path d="M12 3c1 3-2 4.5-2 7a4 4 0 0 0 8 .5C18 7 15 5.5 12 3z"/><path d="M9.5 13.5a2.6 2.6 0 1 0 5 0c0-1.5-1.5-2-2.5-3.5-1 1.5-2.5 2-2.5 3.5z"/>',
			'zap'      => '<path d="M13 2L4 14h6l-1 8 9-12h-6z"/>',
			'heart'    => '<path d="M12 20.5s-7.5-4.6-9.8-9.4C.6 7.2 2.8 3.5 6.5 3.5c2 0 3.5 1 5.5 3 2-2 3.5-3 5.5-3 3.7 0 5.9 3.7 4.3 7.6C19.5 15.9 12 20.5 12 20.5z"/>',
			'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
			'truck'       => '<rect x="2" y="7" width="12" height="10" rx="1.4"/><path d="M14 10.5h3.5L20 13.5V17h-6"/><circle cx="6.5" cy="18.2" r="1.6"/><circle cx="16.5" cy="18.2" r="1.6"/>',
			'check-circle'=> '<circle cx="12" cy="12" r="9"/><path d="M8 12.3l2.6 2.6L16.2 9"/>',
		);
		return isset( $i[ $name ] ) ? $o . $i[ $name ] . $c : '';
	}

	/** آدرس بخش در برگه پنل */
	public static function url( $sec, array $args = array() ) {
		$base = function_exists( 'get_permalink' ) ? get_permalink( 0 ) : '';
		if ( ! $base ) {
			$base = home_url( '/' );
		}
		return add_query_arg( array_merge( array( 'dvp_sec' => $sec ), $args ), $base );
	}

	/** برگه ورود/ثبت‌نام فروشنده */
	public static function auth_url() {
		$pid = (int) Dastyar_Panel_Settings::get( 'auth_page', 0 );
		if ( $pid && function_exists( 'get_permalink' ) ) {
			$u = get_permalink( $pid );
			if ( $u ) {
				return $u;
			}
		}
		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
	}

	/* ==================================================================
	 *  رندر اصلی کدکوتاه
	 * ============================================================== */

	/** کلاس ریشه پنل + طرح ظاهری انتخاب‌شده (کلاسیک = بدون کلاس اضافه، برای سازگاری کامل با قبل) */
	public static function wrap_class( $extra = '' ) {
		$skin  = (string) Dastyar_Panel_Settings::get( 'panel_skin', 'classic' );
		$skin  = array_key_exists( $skin, Dastyar_Panel_Settings::skins() ) ? $skin : 'classic';
		$class = 'dvp-wrap';
		if ( 'classic' !== $skin ) {
			$class .= ' dvp-skin-' . $skin;
		}
		return trim( $class . ( '' !== $extra ? ' ' . $extra : '' ) );
	}

	public function render( $atts = array() ) {
		if ( ! class_exists( 'Dastyar' ) ) {
			return '<div class="' . esc_attr( self::wrap_class() ) . '" dir="rtl"><div class="dvp-card"><p>برای فعال شدن پنل فروشنده، افزونه «Dastyar Core — هسته مرکزی دستیار شاپ» باید نصب و فعال باشد.</p></div></div>' . $this->css_html();
		}

		$uid = (int) get_current_user_id();

		// مهمان ← فرم ورود/ثبت‌نام Core
		if ( ! $uid ) {
			return $this->guest_box();
		}

		// در انتظار تأیید مدیر
		if ( class_exists( 'Dastyar_Registration' ) && Dastyar_Registration::is_pending( $uid ) ) {
			return $this->pending_box( $uid );
		}

		// فقط فروشنده (مدیر سایت هم مجاز است — is_vendor آن را پوشش می‌دهد)
		if ( ! Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			return $this->not_vendor_box();
		}

		$atts  = is_array( $atts ) ? $atts : array();
		$secs  = $this->sections();
		$sec   = sanitize_key( (string) ( isset( $_GET['dvp_sec'] ) ? wp_unslash( $_GET['dvp_sec'] ) : ( $atts['section'] ?? 'dashboard' ) ) );
		if ( ! isset( $secs[ $sec ] ) ) {
			$sec = 'dashboard';
		}

		ob_start();
		echo '<div class="' . esc_attr( self::wrap_class() ) . '" dir="rtl">';

		// سربرگ
		echo '<div class="dvp-head"><div class="dvp-head-t">';
		printf( '<h1>%s</h1>', esc_html( (string) Dastyar_Panel_Settings::get( 'title' ) ) );
		printf( '<p>%s</p>', esc_html( (string) Dastyar_Panel_Settings::get( 'subtitle' ) ) );
		echo '</div>' . $this->userbox_html( $uid ) . '</div>';

		echo '<div class="dvp-shell">';

		// ناوبری
		echo '<nav class="dvp-nav">';
		foreach ( $secs as $slug => $conf ) {
			printf(
				'<a class="dvp-nav-link%s" href="%s"><span class="dvp-nic">%s</span>%s</a>',
				$slug === $sec ? ' dvp-on' : '',
				esc_url( self::url( $slug ) ),
				self::icon( $conf[0] ),
				esc_html( $conf[1] )
			);
		}
		printf( '<a class="dvp-nav-link dvp-home" href="%s"><span class="dvp-nic">%s</span>بازگشت به سایت</a>', esc_url( home_url( '/' ) ), self::icon( 'home' ) );
		printf( '<a class="dvp-nav-link dvp-logout" href="%s"><span class="dvp-nic">%s</span>خروج از حساب</a>', esc_url( wp_logout_url( home_url() ) ), self::icon( 'logout' ) );
		echo '</nav>';

		// محتوا
		echo '<main class="dvp-content">';
		$this->render_notices( $uid );
		$method = 'sec_' . $sec;
		if ( method_exists( $this, $method ) ) {
			$this->$method( $uid );
		}
		echo '</main>';

		echo '</div></div>';

		// پاپ‌آپ قرارداد همکاری — تا امضا، پنل مسدود می‌ماند (v1.4.0)
		$this->contract_modal( $uid );

		$this->js_once();
		return ob_get_clean() . $this->css_html();
	}

	/* ==================================================================
	 *  حالت‌های ورود/انتظار/غیرفروشنده
	 * ============================================================== */

	protected function guest_box() {
		$out  = '<div class="' . esc_attr( self::wrap_class( 'dvp-guest' ) ) . '" dir="rtl">';
		$out .= '<div class="dvp-card dvp-center"><div class="dvp-bigico">🛍</div>';
		$out .= '<h2>ورود / ثبت‌نام فروشنده</h2>';
		$out .= '<p>برای دسترسی به پنل فروشنده ابتدا وارد حساب خود شوید یا درخواست فروشندگی ثبت کنید.</p></div>';
		// فرم ورود/ثبت‌نام Core (هندلر آن سراسری است و در هر برگه‌ای کار می‌کند)
		if ( shortcode_exists( 'dastyar_vendor_auth' ) ) {
			$out .= do_shortcode( '[dastyar_vendor_auth]' );
		} else {
			$out .= '<p style="text-align:center"><a class="dvp-btn" href="' . esc_url( self::auth_url() ) . '">رفتن به صفحه ورود ←</a></p>';
		}
		$out .= '</div>';
		return $out . $this->css_html();
	}

	protected function pending_box( $uid ) {
		$rejected = (bool) get_user_meta( $uid, '_dastyar_vendor_rejected', true );
		$out  = '<div class="' . esc_attr( self::wrap_class( 'dvp-guest' ) ) . '" dir="rtl"><div class="dvp-card dvp-center">';
		$out .= '<div class="dvp-bigico">' . ( $rejected ? '⛔' : '⏳' ) . '</div>';
		if ( $rejected ) {
			$out .= '<h2>درخواست فروشندگی شما بررسی شد</h2><p>متأسفانه درخواست شما در این مرحله تأیید نشد. برای کسب اطلاعات بیشتر با پشتیبانی در تماس باشید.</p>';
		} else {
			$out .= '<h2>حساب شما در انتظار تأیید است</h2><p>ثبت‌نام شما با موفقیت انجام شد ✅<br>پس از تأیید مدیر، نقش «فروشنده دستیار شاپ» به شما داده می‌شود و پنل فروشنده فعال خواهد شد.</p>';
		}
		$out .= '<p><a class="dvp-btn" href="' . esc_url( home_url() ) . '">بازگشت به صفحه اصلی</a> ';
		$out .= '<a class="dvp-btn dvp-btn-ghost" href="' . esc_url( wp_logout_url( home_url() ) ) . '">خروج از حساب</a></p>';
		$out .= '</div></div>';
		return $out . $this->css_html();
	}

	protected function not_vendor_box() {
		$out  = '<div class="' . esc_attr( self::wrap_class( 'dvp-guest' ) ) . '" dir="rtl"><div class="dvp-card dvp-center">';
		$out .= '<div class="dvp-bigico">🔒</div><h2>این بخش مخصوص فروشنده‌هاست</h2>';
		$out .= '<p>حساب شما نقش «فروشنده دستیار شاپ» ندارد. اگر مایل به همکاری هستید، درخواست فروشندگی ثبت کنید.</p>';
		$out .= '<p><a class="dvp-btn" href="' . esc_url( self::auth_url() ) . '">ثبت درخواست فروشندگی ←</a></p>';
		$out .= '</div></div>';
		return $out . $this->css_html();
	}

	/** مینی‌کارت کاربر (لوگو/نام/موجودی) در سربرگ */
	protected function userbox_html( $uid ) {
		$user     = get_userdata( $uid );
		$shop     = (string) get_user_meta( $uid, '_dastyar_shop_name', true );
		$name     = $shop ?: ( $user ? $user->display_name : '' );
		$logo_id  = (int) get_user_meta( $uid, '_dastyar_logo_id', true );
		$logo_url = $logo_id ? ( function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '' ) : '';
		$balance  = class_exists( 'Dastyar' ) ? Dastyar::instance()->wallet->get_balance( $uid ) : 0;

		$html  = '<div class="dvp-userbox">';
		$html .= $logo_url
			? '<img class="dvp-avatar" src="' . esc_url( $logo_url ) . '" alt="">'
			: '<span class="dvp-avatar dvp-avatar-ph">' . self::icon( 'user' ) . '</span>';
		$html .= '<span class="dvp-uname">سلام <strong>' . esc_html( $name ) . '</strong>';
		$html .= '<small>موجودی کیف پول: ' . wp_kses_post( wc_price( $balance ) ) . '</small></span></div>';
		return $html;
	}

	/* ==================================================================
	 *  اعلان‌ها (ترنزینت یک‌بارمصرف)
	 * ============================================================== */

	public static function notify( $uid, $type, $msg ) {
		$k   = 'dvp_nt_' . (int) $uid;
		$all = get_transient( $k );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$all[] = array( $type, (string) $msg );
		set_transient( $k, $all, 120 );
	}

	protected function render_notices( $uid ) {
		$k   = 'dvp_nt_' . (int) $uid;
		$all = get_transient( $k );
		if ( is_array( $all ) && $all ) {
			foreach ( $all as $n ) {
				printf( '<div class="dvp-notice dvp-%s">%s</div>', esc_attr( (string) ( $n[0] ?? 'ok' ) ), esc_html( (string) ( $n[1] ?? '' ) ) );
			}
			delete_transient( $k );
		}
		if ( function_exists( 'wc_print_notices' ) ) {
			wc_print_notices();
		}
	}

	/* ==================================================================
	 *  هندلر فرم‌ها
	 * ============================================================== */

	public function handle_posts() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		// v1.8.2 — باگ‌فیکس: کدکوتاه [dastyar_vendor_panel] روی صفحه My Account قرار بگیرد،
		// فرم‌های پنل (dvp_action مثل wallet_charge/wallet_pay) بی‌صاحب می‌مانند: هسته فقط
		// dastyar_action را هندل می‌کند و این گارد کورکورانه همه‌چیز را رد می‌کرد ← صفحه فقط رفرش می‌شد.
		// حالا: روی My Account فقط اکشن‌های dastyar_action به هسته سپرده می‌شود؛ dvp_action همیشه مال پنل.
		if ( function_exists( 'is_account_page' ) && is_account_page() && empty( $_POST['dvp_action'] ) ) {
			return;
		}

		$action = '';
		$ns     = 'dvp';
		if ( ! empty( $_POST['dvp_action'] ) ) {
			$action = sanitize_key( wp_unslash( $_POST['dvp_action'] ) );
		} elseif ( ! empty( $_POST['dastyar_action'] ) ) {
			// فرم‌های جاسازی‌شده Core (مثل پیشنهاد محصول)
			$action = sanitize_key( wp_unslash( $_POST['dastyar_action'] ) );
			$ns     = 'dastyar';
		}
		if ( '' === $action ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), $ns . '_' . $action ) ) {
			self::notify( get_current_user_id(), 'err', 'نشست نامعتبر است. صفحه را تازه‌سازی و دوباره تلاش کنید.' );
			$this->redirect_back();
		}

		$uid = (int) get_current_user_id();
		if ( ! Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			return;
		}

		switch ( $action ) {

			case 'wallet_charge':
				if ( ! Dastyar_Panel_Settings::yes( 'sec_wallet' ) ) {
					break;
				}
				$order = Dastyar_Payments::create_charge_order( $uid, (float) ( $_POST['amount'] ?? 0 ) );
				if ( is_wp_error( $order ) ) {
					self::notify( $uid, 'err', $order->get_error_message() );
					break;
				}
				wp_safe_redirect( $order->get_checkout_payment_url() );
				exit;

			case 'wallet_pay':
				$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) ( $_POST['order_id'] ?? 0 ) ) : null;
				if ( ! $order || (int) $order->get_customer_id() !== $uid ) {
					self::notify( $uid, 'err', 'صورتحساب یافت نشد.' );
					break;
				}
				$result = Dastyar::instance()->wallet->pay_order_now( $order, $uid );
				self::notify( $uid, is_wp_error( $result ) ? 'err' : 'ok', is_wp_error( $result ) ? $result->get_error_message() : 'فاکتور از کیف پول پرداخت شد و سفارش در حال پردازش است.' );
				break;

			case 'api_key':
				$key = Dastyar::instance()->vendors->generate_key( $uid );
				set_transient( 'dastyar_new_key_' . $uid, $key, 300 );
				self::notify( $uid, 'ok', 'کلید API جدید ساخته شد. آن را همین حالا کپی کنید؛ دوباره نمایش داده نمی‌شود.' );
				break;

			case 'suggest':
				$result = Dastyar_Suggestions::submit(
					$uid,
					sanitize_text_field( wp_unslash( $_POST['sugg_name'] ?? '' ) ),
					sanitize_textarea_field( wp_unslash( $_POST['sugg_desc'] ?? '' ) ),
					esc_url_raw( wp_unslash( $_POST['sugg_link'] ?? '' ) )
				);
				self::notify( $uid, is_wp_error( $result ) ? 'err' : 'ok', is_wp_error( $result ) ? $result->get_error_message() : 'پیشنهاد محصول شما ثبت شد و در صف بررسی قرار گرفت.' );
				break;

			case 'ticket_new':
				$ticket_id = Dastyar::instance()->tickets->create(
					$uid,
					sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
					sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) )
				);
				self::notify( $uid, $ticket_id ? 'ok' : 'err', $ticket_id ? 'تیکت شما ثبت شد؛ پاسخ پشتیبانی در همین بخش نمایش داده می‌شود.' : 'عنوان و متن تیکت الزامی است.' );
				break;

			case 'ticket_reply':
				Dastyar::instance()->tickets->reply(
					(int) ( $_POST['ticket_id'] ?? 0 ),
					$uid,
					sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) )
				);
				self::notify( $uid, 'ok', 'پاسخ شما ثبت شد.' );
				break;

			case 'contract_accept':
				// امضای الکترونیکی قرارداد همکاری (v1.4.0)
				if ( ! self::contract_accepted_at( $uid ) ) {
					update_user_meta( $uid, self::CONTRACT_META, time() );
					do_action( 'dastyar_contract_accepted', $uid );
				}
				self::notify( $uid, 'ok', 'قرارداد همکاری با موفقیت امضا شد؛ از این پس به همه بخش‌های پنل دسترسی دارید.' );
				break;

			case 'account_save':
				$this->save_account( $uid );
				break;
		}

		$this->redirect_back();
	}

	/** ریدایرکت به همان بخش پنل (اولویت با dvp_back سپس referer) */
	protected function redirect_back() {
		$back = esc_url_raw( wp_unslash( $_POST['dvp_back'] ?? '' ) );
		if ( ! $back && function_exists( 'wp_get_referer' ) ) {
			$back = wp_get_referer();
		}
		if ( ! $back || 0 !== strpos( $back, home_url() ) ) {
			$back = home_url( '/' );
		}
		wp_safe_redirect( $back );
		exit;
	}

	/** هیدن‌های استاندارد فرم‌های پنل */
	protected function form_head( $action, $sec ) {
		wp_nonce_field( 'dvp_' . $action );
		printf( '<input type="hidden" name="dvp_action" value="%s">', esc_attr( $action ) );
		printf( '<input type="hidden" name="dvp_back" value="%s">', esc_url( self::url( $sec ) ) );
	}

	/* ------------------------------------------------------------------
	 *  ذخیره حساب کاربری
	 * ---------------------------------------------------------------- */

	protected function save_account( $uid ) {
		$first = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		$disp  = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
		if ( '' === $disp ) {
			$disp = trim( $first . ' ' . $last );
		}
		if ( '' !== $disp ) {
			wp_update_user( array(
				'ID'           => $uid,
				'display_name' => $disp,
				'first_name'   => $first,
				'last_name'    => $last,
			) );
		}

		update_user_meta( $uid, '_dastyar_shop_name', sanitize_text_field( wp_unslash( $_POST['shop_name'] ?? '' ) ) );
		update_user_meta( $uid, 'billing_phone', sanitize_text_field( wp_unslash( $_POST['billing_phone'] ?? '' ) ) );

		$site = esc_url_raw( wp_unslash( $_POST['site_url'] ?? '' ) );
		if ( $site ) {
			Dastyar::instance()->vendors->set_site_url( $uid, $site );
		} else {
			delete_user_meta( $uid, Dastyar_Vendors::SITE );
		}

		// لوگو (به‌همان موتور Core)
		if ( class_exists( 'Dastyar_MyAccount' ) && ( ! empty( $_POST['remove_logo'] ) || ! empty( $_FILES['dastyar_logo']['name'] ) ) ) {
			Dastyar_MyAccount::save_logo( $uid );
		}

		// تعویض رمز (اختیاری)
		$p1 = (string) ( $_POST['password'] ?? '' );
		$p2 = (string) ( $_POST['password2'] ?? '' );
		if ( '' !== $p1 || '' !== $p2 ) {
			if ( $p1 !== $p2 ) {
				self::notify( $uid, 'err', 'تکرار رمز عبور جدید یکسان نیست.' );
				return;
			}
			if ( strlen( $p1 ) < 8 ) {
				self::notify( $uid, 'err', 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.' );
				return;
			}
			wp_set_password( $p1, $uid );
			wp_set_auth_cookie( $uid );
		}

		self::notify( $uid, 'ok', 'تغییرات حساب کاربری با موفقیت ذخیره شد.' );
	}

	/* ==================================================================
	 *  پرس‌وجوی سفارش‌ها (اشتراکی)
	 * ============================================================== */

	protected static function orders_of( $uid, array $extra = array() ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		return wc_get_orders( array_merge( array(
			'customer_id' => $uid,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'limit'       => 50,
		), $extra ) );
	}

	/** رنگ/لیبل بج وضعیت سفارش */
	protected function status_badge( $status ) {
		$colors = array(
			'pending'    => '#8a919e',
			'processing' => '#12875c',
			'on-hold'    => '#e0a800',
			'completed'  => '#0f5132',
			'cancelled'  => '#c0392b',
			'refunded'   => '#6f42c1',
			'failed'     => '#c0392b',
			'posted'     => '#ffe9a8', // v1.10.5 — «تحویل پست شده»: زرد (پیش‌فرض‌ها با متن سفید، این یکی تیره)
		);
		// v1.10.5 — رنگ متن هر وضعیت (پیش‌فرض سفید؛ روی زرد، تیره خواناتر است)
		$texts = array(
			'posted' => '#6b4e00',
		);
		$label = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $status ) : $status;
		$color = $colors[ $status ] ?? '#666';
		$text  = $texts[ $status ] ?? '#fff';
		return '<span class="dvp-badge" style="background:' . esc_attr( $color ) . ';color:' . esc_attr( $text ) . '">' . esc_html( $label ) . '</span>';
	}

	/** جدول سفارش‌ها (اشتراک داشبورد/سفارش‌ها) */
	protected function orders_table( $orders ) {
		echo '<div class="dvp-table-wrap"><table class="dvp-table"><thead><tr>';
		echo '<th>سفارش</th><th>منشأ</th><th>تاریخ</th><th>وضعیت</th><th>مبلغ</th><th>کد رهگیری</th><th></th>';
		echo '</tr></thead><tbody>';
		foreach ( $orders as $order ) {
			$remote = $order->get_meta( '_dastyar_remote_order_number' );
			$manual = (bool) $order->get_meta( '_dastyar_manual_order' );
			$track  = $order->get_meta( '_dastyar_tracking_code' );
			$origin = $remote
				? '<span class="dvp-origin">🛒 فروشگاه شما #' . esc_html( $remote ) . '</span>'
				: ( $manual ? '<span class="dvp-manual-badge">ثبت دستی</span>' : '—' );
			echo '<tr>';
			printf( '<td><strong>#%s</strong></td>', esc_html( $order->get_order_number() ) );
			echo '<td>' . wp_kses_post( $origin ) . '</td>';
			printf( '<td>%s</td>', esc_html( Dastyar_Jalali::dt( $order->get_date_created(), 'Y/m/d' ) ) );
			echo '<td>' . $this->status_badge( $order->get_status() ) . '</td>';
			printf( '<td>%s</td>', wp_kses_post( $order->get_formatted_order_total() ) );
			if ( $track ) {
				printf(
					'<td><span class="dvp-track" dir="ltr">%s</span> <button type="button" class="dvp-copy" data-dvp-copy="%s" title="کپی کد رهگیری">📋</button></td>',
					esc_html( $track ),
					esc_attr( $track )
				);
			} else {
				echo '<td>—</td>';
			}
			printf(
				'<td><a class="dvp-btn dvp-btn-sm" href="%s">جزئیات ←</a></td>',
				esc_url( self::url( 'orders', array( 'dvp_order' => (int) $order->get_id() ) ) )
			);
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/* ==================================================================
	 *  بخش: پیشخوان
	 * ============================================================== */

	protected function sec_dashboard( $uid ) {
		$orders = self::orders_of( $uid, array( 'limit' => 100 ) );

		$active  = 0; // pending/processing/on-hold
		$pending = array();
		foreach ( $orders as $o ) {
			$st = $o->get_status();
			if ( in_array( $st, array( 'pending', 'processing', 'on-hold' ), true ) ) {
				$active++;
			}
			if ( 'pending' === $st ) {
				$pending[] = $o;
			}
		}
		$pending_sum = 0;
		foreach ( $pending as $o ) {
			$pending_sum += (float) $o->get_total();
		}
		$balance = Dastyar::instance()->wallet->get_balance( $uid );

		// v1.9.9 — «از اینجا شروع کن — ۳ قدم تا اولین فروش» تا اولین سفارش بالای داشبورد می‌ماند
		// v1.10.19 — قابل نمایش/عدم‌نمایش از تنظیمات؛ بنر به زیر ۴ باکس خلاصه وضعیت منتقل شد
		$dvp_show_onboard = ! $orders && class_exists( 'Dastyar_Growth' ) && Dastyar_Panel_Settings::yes( 'onboard_enabled' );
		if ( $dvp_show_onboard ) {
			echo '<details class="dvp-onboard" open>';
			echo '<summary>' . self::icon( 'zap' ) . ' از اینجا شروع کن — ۳ قدم تا اولین فروش</summary>';
			echo '<div class="dvp-onboard-body">' . Dastyar_Growth::start_box_html( $uid ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی کنترل‌شده
			echo '</details>';
		}

		// کارت‌های آمار (با آیکن SVG در قاب رنگی ملایم — شبیه داشبورد مرجع)
		echo '<div class="dvp-stats">';
		$this->stat_card( 'wallet', 'موجودی کیف پول', wc_price( $balance ), self::url( 'wallet' ), '#17a16d' );
		$this->stat_card( 'package', 'سفارش‌های در جریان', Dastyar_Panel_Settings::fa_num( count( $orders ) ? $active : 0 ) . ' سفارش', self::url( 'orders' ), '#242536' );
		$this->stat_card( 'card', 'صورتحساب باز', Dastyar_Panel_Settings::fa_num( count( $pending ) ) . ' فاکتور' . ( $pending_sum ? ' — ' . wp_strip_all_tags( wc_price( $pending_sum ) ) : '' ), self::url( 'invoices' ), '#b45309' );
		$this->stat_card( 'layers', 'کل سفارش‌ها', Dastyar_Panel_Settings::fa_num( count( $orders ) ) . ' سفارش', self::url( 'orders' ), '#475569' );
		echo '</div>';

		// v1.10.19 — بنر داشبورد، زیر ۴ باکس خلاصه وضعیت (فقط تا قبل از اولین سفارش)
		if ( ! $orders ) {
			$banner_img = trim( (string) Dastyar_Panel_Settings::get( 'dash_banner_img', '' ) );
			if ( '' !== $banner_img ) {
				$banner_url = trim( (string) Dastyar_Panel_Settings::get( 'dash_banner_url', '' ) );
				$banner_alt = (string) Dastyar_Panel_Settings::get( 'dash_banner_alt', '' );
				$img_tag    = '<img src="' . esc_url( $banner_img ) . '" alt="' . esc_attr( $banner_alt ) . '">';
				echo '<div class="dvp-dash-banner">' . ( '' !== $banner_url
					? '<a href="' . esc_url( $banner_url ) . '">' . $img_tag . '</a>'
					: $img_tag ) . '</div>';
			}
		}

		// بدنه داشبورد دوستونه: آخرین سفارش‌ها + (کارت پیشنهاد + اطلاعات حساب)
		echo '<div class="dvp-dash-grid">';

		// —— ستون اصلی: آخرین سفارش‌ها (لیستی با بندانگشتی و نقطه وضعیت) ——
		echo '<div class="dvp-card dvp-recent"><h3>آخرین سفارش‌ها</h3>';
		if ( ! $orders ) {
			// v1.10.19 — طرح گرافیکی به‌جای متن ساده
			echo '<div class="dvp-empty-orders">';
			echo '<div class="dvp-empty-orders-ic">' . self::icon( 'package' ) . '</div>';
			echo '<h4>هنوز سفارشی ثبت نشده</h4>';
			echo '<p>به‌محض اتصال فروشگاهتان یا ثبت اولین سفارش، همین‌جا نمایش داده می‌شود.</p>';
			echo '<div class="dvp-empty-orders-actions">';
			if ( Dastyar_Panel_Settings::yes( 'sec_manual' ) ) {
				printf( '<a class="dvp-btn dvp-btn-sm" href="%s">%s ثبت سفارش دستی</a>', esc_url( self::url( 'manual' ) ), self::icon( 'manual' ) );
			}
			if ( Dastyar_Panel_Settings::yes( 'sec_api' ) ) {
				printf( '<a class="dvp-btn dvp-btn-sm dvp-btn-ghost" href="%s">%s اتصال فروشگاه</a>', esc_url( self::url( 'api' ) ), self::icon( 'api' ) );
			}
			echo '</div></div>';
		} else {
			foreach ( array_slice( $orders, 0, 5 ) as $order ) {
				$this->recent_order_row( $order );
			}
			printf( '<p class="dvp-viewall"><a href="%s">مشاهده همه ←</a></p>', esc_url( self::url( 'orders' ) ) );
		}
		echo '</div>';

		// —— ستون کناری: پیشنهاد ویژه + تغییرات قیمت + اطلاعات حساب ——
		echo '<div class="dvp-dash-side">';
		$this->promo_card_html();
		$this->account_card_html( $uid );
		echo '</div>';

		echo '</div>';
	}


	protected function stat_card( $icon, $label, $value_html, $link, $color ) {
		echo '<a class="dvp-stat" href="' . esc_url( $link ) . '">';
		echo '<span class="dvp-stat-ic" style="background:' . esc_attr( $color ) . '1a;color:' . esc_attr( $color ) . '">' . self::icon( $icon ) . '</span>';
		echo '<span class="dvp-stat-lb">' . esc_html( $label ) . '</span>';
		echo '<span class="dvp-stat-vl">' . wp_kses_post( $value_html ) . '</span>';
		echo '</a>';
	}

	/** ردیف لیستی آخرین سفارش‌ها داشبورد (بندانگشتی + شماره + تاریخ + نقطه وضعیت) */
	protected function recent_order_row( $order ) {
		$status_colors = array(
			'completed' => '#17a16d',
			'processing'=> '#d97706',
			'on-hold'   => '#d97706',
			'pending'   => '#8a8d9d',
			'cancelled' => '#dc2626',
			'failed'    => '#dc2626',
			'refunded'  => '#dc2626',
		);
		$st    = $order->get_status();
		$color = $status_colors[ $st ] ?? '#8a8d9d';
		$label = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $st ) : $st;

		// بندانگشتی = تصویر اولین قلم (در صورت وجود)
		$thumb = '';
		foreach ( $order->get_items() as $item ) {
			$p = method_exists( $item, 'get_product' ) ? $item->get_product() : null;
			if ( $p && function_exists( 'wp_get_attachment_image_url' ) ) {
				$thumb = (string) wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' );
			}
			break;
		}

		$remote    = $order->get_meta( '_dastyar_remote_order_number' );
		$manual    = (bool) $order->get_meta( '_dastyar_manual_order' );
		$is_charge = (bool) $order->get_meta( '_dastyar_wallet_charge' ); // v1.10.32

		echo '<div class="dvp-orow-wrap">';
		echo '<a class="dvp-orow" href="' . esc_url( self::url( 'orders', array( 'dvp_order' => (int) $order->get_id() ) ) ) . '">';
		echo '<span class="dvp-othumb">' . ( $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : self::icon( $is_charge ? 'wallet' : 'package' ) ) . '</span>';
		echo '<span class="dvp-oinfo">';
		printf( '<span class="dvp-oino">#%s</span>', esc_html( $order->get_order_number() ) );
		if ( $is_charge ) {
			echo ' <span class="dvp-manual-badge">شارژ کیف پول</span>';
		} elseif ( $remote ) {
			printf( ' <span class="dvp-omini">فروشگاه شما #%s</span>', esc_html( $remote ) );
		} elseif ( $manual ) {
			echo ' <span class="dvp-manual-badge">ثبت دستی</span>';
		}
		printf( '<small class="dvp-odate">%s</small>', esc_html( Dastyar_Jalali::dt( $order->get_date_created(), 'Y/m/d' ) ) );
		echo '</span>';

		// v1.10.32 — برای شارژ کیف پول، به‌جای پیل وضعیت عمومی: تیک پرداخت‌شده سبز (اگر پولش نشسته) یا هشدار در انتظار
		if ( $is_charge ) {
			if ( $order->is_paid() ) {
				echo '<span class="dvp-ost" style="color:#17a16d">' . self::icon( 'check-circle' ) . 'پرداخت‌شده</span>';
			} else {
				echo '<span class="dvp-ost" style="color:#d97706">' . self::icon( 'clock' ) . 'در انتظار پرداخت</span>';
			}
		} else {
			printf(
				'<span class="dvp-ost" style="color:%s"><i class="dvp-odot" style="background:%s"></i>%s</span>',
				esc_attr( $color ),
				esc_attr( $color ),
				esc_html( $label )
			);
		}
		echo '</a>';
		if ( $is_charge && ! $order->is_paid() ) {
			printf( '<a class="dvp-btn dvp-btn-sm" href="%s">پرداخت ←</a>', esc_url( $order->get_checkout_payment_url() ) );
		}
		echo '</div>';
	}

	/** کارت پیشنهاد ویژه داشبورد (قابل تنظیم از پنل مدیریت) */
	protected function promo_card_html() {
		if ( ! Dastyar_Panel_Settings::yes( 'promo_enabled' ) ) {
			return;
		}
		$title = (string) Dastyar_Panel_Settings::get( 'promo_title' );
		$text  = (string) Dastyar_Panel_Settings::get( 'promo_text' );
		$code  = trim( (string) Dastyar_Panel_Settings::get( 'promo_code', '' ) );
		if ( '' === $title && '' === $text ) {
			return;
		}
		echo '<div class="dvp-promo"><span class="dvp-promo-ic">' . self::icon( 'gift' ) . '</span>';
		if ( '' !== $title ) {
			printf( '<h4>%s</h4>', esc_html( $title ) );
		}
		if ( '' !== $text ) {
			printf( '<p>%s</p>', esc_html( $text ) );
		}
		if ( '' !== $code ) {
			printf(
				'<span class="dvp-promo-code" dir="ltr">%s</span> <button type="button" class="dvp-copy dvp-promo-copy" data-dvp-copy="%s">کپی کد</button>',
				esc_html( $code ),
				esc_attr( $code )
			);
		}
		echo '</div>';
	}

	/** مینی‌کارت اطلاعات حساب در داشبورد */
	protected function account_card_html( $uid ) {
		$user  = get_userdata( $uid );
		$shop  = (string) get_user_meta( $uid, '_dastyar_shop_name', true );
		$name  = $shop ?: ( $user ? $user->display_name : '' );
		$email = $user ? $user->user_email : '';
		$phone = (string) get_user_meta( $uid, 'billing_phone', true );

		echo '<div class="dvp-card dvp-acct"><h3>اطلاعات حساب</h3>';
		$this->acct_row( 'نام', $name );
		$this->acct_row( 'ایمیل', self::mask_email( $email ), 'ltr' );
		$this->acct_row( 'تلفن', $phone, 'ltr' );
		printf(
			'<a class="dvp-editlink" href="%s"><span class="dvp-nic">%s</span>ویرایش اطلاعات</a>',
			esc_url( self::url( 'account' ) ),
			self::icon( 'edit' )
		);
		echo '</div>';
	}

	protected function acct_row( $label, $value, $dir = '' ) {
		echo '<div class="dvp-arow">';
		echo '<span>' . esc_html( $label ) . '</span>';
		echo '<strong' . ( $dir ? ' dir="' . esc_attr( $dir ) . '"' : '' ) . '>' . esc_html( (string) $value ) . '</strong>';
		echo '</div>';
	}

	/** ماسک ایمیل: ali***@gmail.com */
	protected static function mask_email( $email ) {
		$email = (string) $email;
		if ( false === strpos( $email, '@' ) ) {
			return $email;
		}
		list( $local, $domain ) = explode( '@', $email, 2 );
		$keep = max( 2, min( 4, (int) floor( strlen( $local ) / 3 ) ) );
		return substr( $local, 0, $keep ) . '***@' . $domain;
	}

	/* ==================================================================
	 *  بخش: سفارش‌ها
	 * ============================================================== */

	protected function sec_orders( $uid ) {
		// نمای جزئیات یک سفارش
		$order_id = (int) ( $_GET['dvp_order'] ?? 0 );
		if ( $order_id ) {
			$this->sec_order_detail( $uid, $order_id );
			return;
		}

		$per   = max( 5, min( 50, (int) Dastyar_Panel_Settings::get( 'orders_per_page', 15 ) ) );
		$page  = max( 1, (int) ( $_GET['dvp_page'] ?? 1 ) );
		$raw    = self::orders_of( $uid, array(
			'limit'  => $per,
			'offset' => ( $page - 1 ) * $per,
		) );
		$has_next = count( $raw ) >= $per; // صفحه پر = احتمالاً صفحه بعد هم هست
		$orders   = array_slice( array_values( (array) $raw ), 0, $per );

		echo '<div class="dvp-card"><h3>سفارش‌های شما</h3>';
		if ( ! $orders ) {
			echo '<p class="dvp-empty">' . ( $page > 1 ? 'صفحه دیگری وجود ندارد.' : 'سفارشی ثبت نشده است.' ) . '</p>';
		} else {
			$this->orders_table( $orders );

			// صفحه‌بندی
			echo '<div class="dvp-pager">';
			if ( $page > 1 ) {
				printf( '<a class="dvp-btn dvp-btn-ghost" href="%s">→ صفحه قبل</a>', esc_url( self::url( 'orders', array( 'dvp_page' => $page - 1 ) ) ) );
			}
			echo '<span class="dvp-pager-cur">صفحه ' . esc_html( Dastyar_Panel_Settings::fa_num( $page ) ) . '</span>';
			if ( $has_next ) {
				printf( '<a class="dvp-btn dvp-btn-ghost" href="%s">صفحه بعد ←</a>', esc_url( self::url( 'orders', array( 'dvp_page' => $page + 1 ) ) ) );
			}
			echo '</div>';
		}
		echo '</div>';
	}

	protected function sec_order_detail( $uid, $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order || (int) $order->get_customer_id() !== $uid ) {
			echo '<div class="dvp-card"><p class="dvp-empty">سفارش یافت نشد.</p>';
			printf( '<p><a class="dvp-btn dvp-btn-ghost" href="%s">→ بازگشت به سفارش‌ها</a></p></div>', esc_url( self::url( 'orders' ) ) );
			return;
		}

		$remote  = $order->get_meta( '_dastyar_remote_order_number' );
		$track   = $order->get_meta( '_dastyar_tracking_code' );
		$carrier = $order->get_meta( '_dastyar_tracking_carrier' );
		$is_charge = (bool) $order->get_meta( '_dastyar_wallet_charge' ); // v1.10.32 — شارژ کیف پول، نه یک سفارش کالای واقعی

		echo '<div class="dvp-card">';
		printf( '<p style="margin:0 0 10px"><a class="dvp-btn dvp-btn-ghost" href="%s">→ بازگشت به سفارش‌ها</a></p>', esc_url( self::url( 'orders' ) ) );
		echo '<div class="dvp-odhead">';
		printf( '<h3 style="margin:0">سفارش #%s %s</h3>', esc_html( $order->get_order_number() ), $this->status_badge( $order->get_status() ) );
		printf( '<div class="dvp-odmeta">تاریخ: %s', esc_html( Dastyar_Jalali::dt( $order->get_date_created(), 'Y/m/d' ) ) );
		if ( $remote ) {
			printf( ' · شماره در فروشگاه شما: <strong>#%s</strong>', esc_html( $remote ) );
		}
		echo '</div></div>';

		if ( $is_charge ) {
			// v1.10.32 — شارژ کیف پول یک محموله فیزیکی نیست: نه مراحل بسته‌بندی/ارسال معنی دارد،
			// نه کد رهگیری. به‌جایش فقط وضعیت پرداخت (دکمه پرداخت یا تیک موفق) نشان داده می‌شود.
			echo '<div class="dvp-chargebox">';
			if ( $order->is_paid() ) {
				echo '<div class="dvp-charge-ok">' . self::icon( 'check-circle' ) . '<div><strong>پرداخت با موفقیت انجام شد</strong><span>مبلغ به کیف پول شما اضافه شده است.</span></div></div>';
			} else {
				echo '<div class="dvp-charge-pending">';
				echo '<div>' . self::icon( 'clock' ) . '<div><strong>در انتظار پرداخت</strong><span>این شارژ هنوز پرداخت نشده است.</span></div></div>';
				printf( '<a class="dvp-btn" href="%s">پرداخت از درگاه ←</a>', esc_url( $order->get_checkout_payment_url() ) );
				echo '</div>';
			}
			echo '</div>';
		} else {
			// v1.9.0 — تایم‌لاین وضعیت سفارش (ثبت ← پردازش ← ارسال ← تحویل) — فقط سفارش‌های واقعی کالا
			if ( class_exists( 'Dastyar_Growth' ) ) {
				echo Dastyar_Growth::timeline_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی کنترل‌شده
			}
		}

		// اقلام
		echo '<h4>اقلام سفارش</h4><div class="dvp-table-wrap"><table class="dvp-table"><thead><tr><th>محصول</th><th>تعداد</th><th>مبلغ</th></tr></thead><tbody>';
		foreach ( $order->get_items() as $item ) {
			echo '<tr>';
			printf( '<td>%s</td>', esc_html( $item->get_name() ) );
			printf( '<td>%s</td>', esc_html( Dastyar_Panel_Settings::fa_num( $item->get_quantity() ) ) );
			printf( '<td>%s</td>', wp_kses_post( wc_price( (float) $item->get_total() ) ) );
			echo '</tr>';
		}
		echo '</tbody></table></div>';

		// مبالغ
		$goods = class_exists( 'Dastyar_MyAccount' ) ? Dastyar_MyAccount::goods_total( $order ) : (float) $order->get_subtotal();
		echo '<div class="dvp-totals">';
		printf( '<div><span>جمع کالاها</span><strong>%s</strong></div>', wp_kses_post( wc_price( $goods ) ) );
		printf( '<div><span>حمل‌ونقل</span><strong>%s</strong></div>', wp_kses_post( wc_price( (float) $order->get_shipping_total() ) ) );
		printf( '<div class="dvp-grand"><span>مبلغ قابل پرداخت</span><strong>%s</strong></div>', wp_kses_post( $order->get_formatted_order_total() ) );
		echo '</div>';

		if ( ! $is_charge ) {
			// رهگیری مرسوله — فقط برای سفارش‌های واقعی کالا؛ شارژ کیف پول محموله‌ای برای ارسال ندارد
			echo '<div class="dvp-trackbox">';
			if ( $track ) {
				echo '<span class="dvp-tb-title">' . self::icon( 'truck' ) . 'کد رهگیری مرسوله' . ( $carrier ? ' (' . esc_html( $carrier ) . ')' : '' ) . ':</span> ';
				printf( '<code dir="ltr">%s</code> <button type="button" class="dvp-copy" data-dvp-copy="%s">کپی</button>', esc_html( $track ), esc_attr( $track ) );
				echo '<p class="dvp-tb-sub">این کد به‌صورت خودکار به فروشگاه شما هم ارسال شده است تا مشتری‌تان را مطلع کنید.</p>';
			} else {
				echo '<span class="dvp-tb-title">' . self::icon( 'truck' ) . 'کد رهگیری مرسوله:</span> هنوز ثبت نشده است؛ پس از خروج از انبار این‌جا و در فروشگاه شما نمایش داده می‌شود.';
			}
			echo '</div>';

			// گیرنده (مشتری نهایی) — فقط سفارش‌های واقعی کالا آدرس گیرنده دارند
			$addr = $order->get_address( 'shipping' );
			if ( is_array( $addr ) && array_filter( $addr ) ) {
				echo '<div class="dvp-recipient"><h4>گیرنده (مشتری نهایی)</h4><p>';
				echo esc_html( trim( ( $addr['first_name'] ?? '' ) . ' ' . ( $addr['last_name'] ?? '' ) ) );
				if ( ! empty( $addr['phone'] ) ) {
					echo ' — <span dir="ltr">' . esc_html( $addr['phone'] ) . '</span>';
				}
				echo '<br>' . esc_html( trim( ( $addr['state'] ?? '' ) . '، ' . ( $addr['city'] ?? '' ) . '، ' . ( $addr['address_1'] ?? '' ) . ' ' . ( $addr['address_2'] ?? '' ) ) );
				if ( ! empty( $addr['postcode'] ) ) {
					echo '<br>کدپستی: <span dir="ltr">' . esc_html( $addr['postcode'] ) . '</span>';
				}
				echo '</p></div>';
			}
		}
		echo '</div>';
	}

	/* ==================================================================
	 *  بخش: ثبت سفارش دستی — رندر آماده Core (ویزارد AJAX)
	 * ============================================================== */

	protected function sec_manual( $uid ) {
		Dastyar::instance()->manual_order->render_account( $uid );
	}

	/* ==================================================================
	 *  بخش: صورتحساب‌ها
	 * ============================================================== */

	protected function sec_invoices( $uid ) {
		$orders = self::orders_of( $uid, array( 'status' => array( 'pending' ), 'limit' => 50 ) );

		echo '<div class="dvp-card"><h3>صورتحساب‌های پرداخت‌نشده</h3>';
		if ( ! $orders ) {
			echo '<p class="dvp-empty">صورتحساب پرداخت‌نشده‌ای ندارید. 🎉</p></div>';
			return;
		}
		echo '<div class="dvp-table-wrap"><table class="dvp-table"><thead><tr>';
		echo '<th>سفارش</th><th>تاریخ</th><th>جمع کالاها</th><th>حمل‌ونقل</th><th>قابل پرداخت</th><th>پرداخت</th>';
		echo '</tr></thead><tbody>';
		foreach ( $orders as $order ) {
			$goods = class_exists( 'Dastyar_MyAccount' ) ? Dastyar_MyAccount::goods_total( $order ) : (float) $order->get_subtotal();
			echo '<tr>';
			printf( '<td><strong>#%s</strong></td>', esc_html( $order->get_order_number() ) );
			printf( '<td>%s</td>', esc_html( Dastyar_Jalali::dt( $order->get_date_created(), 'Y/m/d' ) ) );
			printf( '<td>%s</td>', wp_kses_post( wc_price( $goods ) ) );
			printf( '<td>%s</td>', wp_kses_post( wc_price( (float) $order->get_shipping_total() ) ) );
			printf( '<td><strong>%s</strong></td>', wp_kses_post( $order->get_formatted_order_total() ) );
			echo '<td>';
			printf( '<a class="dvp-btn dvp-btn-sm" href="%s">پرداخت از درگاه</a> ', esc_url( $order->get_checkout_payment_url() ) );
			echo '<form method="post" style="display:inline">';
			$this->form_head( 'wallet_pay', 'invoices' );
			printf( '<input type="hidden" name="order_id" value="%d">', (int) $order->get_id() );
			echo '<button type="submit" class="dvp-btn dvp-btn-sm dvp-btn-ghost">از کیف پول</button></form>';
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="dvp-hint">مبلغ هر صورتحساب = جمع کالاها به قیمت تامین + هزینه حمل‌ونقل طبق تعرفه دستیار شاپ. با شارژ کیف پول، پرداخت صورتحساب‌ها خودکار انجام می‌شود.</p>';
		echo '</div>';
	}

	/* ==================================================================
	 *  بخش: کیف پول
	 * ============================================================== */

	protected function sec_wallet( $uid ) {
		$balance = Dastyar::instance()->wallet->get_balance( $uid );

		// کارت موجودی (v1.4.0)
		echo '<div class="dvp-wallet-hero"><div class="dvp-wh-ic">' . self::icon( 'wallet' ) . '</div><div class="dvp-wh-info">';
		echo '<span class="dvp-wh-lb">موجودی کیف پول شما</span>';
		printf( '<strong class="dvp-wh-am">%s</strong>', wp_kses_post( wc_price( $balance ) ) );
		echo '<span class="dvp-wh-ds">با شارژ کیف پول، سفارش‌های مشتریانتان به‌صورت خودکار و بدون معطلی پرداخت می‌شوند.</span>';
		echo '</div></div>';

		// طرح‌های شارژ (همان فیلتر Core — یک چرخه واحد)
		if ( Dastyar_Panel_Settings::yes( 'plans_enabled' ) ) {
			$plans = apply_filters( 'dastyar_wallet_charge_plans', array(
				array( 'amount' => 10000000, 'title' => 'طرح پایه',    'desc' => 'مناسب شروع و تست فروشگاه' ),
				array( 'amount' => 25000000, 'title' => 'طرح رشد',     'desc' => 'پوشش خیال‌راحت سفارش‌های ماه', 'popular' => true ),
				array( 'amount' => 50000000, 'title' => 'طرح حرفه‌ای', 'desc' => 'برای فروشگاه‌های پرفروش و بدون توقف' ),
			) );
			echo '<div class="dvp-card"><h3>شارژ سریع با طرح‌های پیشنهادی</h3><div class="dvp-plan-grid">';
			foreach ( (array) $plans as $plan ) {
				$amount  = (float) ( is_array( $plan ) ? ( $plan['amount'] ?? 0 ) : 0 );
				$popular = is_array( $plan ) && ! empty( $plan['popular'] );
				if ( $amount <= 0 ) {
					continue;
				}
				echo '<form method="post" class="dvp-plan-form">';
				$this->form_head( 'wallet_charge', 'wallet' );
				printf(
					'<button type="submit" name="amount" value="%s" class="dvp-plan%s">',
					esc_attr( $amount ),
					$popular ? ' dvp-plan-pop' : ''
				);
				if ( $popular ) {
					echo '<span class="dvp-plan-badge">★ پرطرفدارترین</span>';
				}
				printf( '<span class="dvp-plan-t">%s</span>', esc_html( (string) ( $plan['title'] ?? '' ) ) );
				printf( '<span class="dvp-plan-a">%s</span>', wp_kses_post( wc_price( $amount ) ) );
				if ( ! empty( $plan['desc'] ) ) {
					printf( '<span class="dvp-plan-d">%s</span>', esc_html( (string) $plan['desc'] ) );
				}
				echo '<span class="dvp-plan-c">شارژ و پرداخت ←</span></button></form>';
			}
			echo '</div></div>';
		}

		// مبلغ دلخواه + تراکنش‌ها
		echo '<div class="dvp-card"><h3>شارژ با مبلغ دلخواه</h3>';
		echo '<form method="post" class="dvp-inline-form">';
		$this->form_head( 'wallet_charge', 'wallet' );
		printf( '<input type="number" min="0" step="any" name="amount" placeholder="مبلغ دلخواه (%s)">', esc_html( function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '' ) );
		echo '<button type="submit" class="dvp-btn">شارژ کیف پول ←</button></form>';
		echo '<p class="dvp-hint">مستقیم به درگاه پرداخت می‌روید؛ پس از پرداخت موفق، موجودی به‌صورت خودکار شارژ می‌شود.</p>';
		echo '</div>';

		// تراکنش‌ها
		$txns = Dastyar::instance()->wallet->get_transactions( $uid, 50 );
		echo '<div class="dvp-card"><h3>تراکنش‌های کیف پول</h3>';
		if ( ! $txns ) {
			echo '<p class="dvp-empty">تراکنشی ثبت نشده است.</p>';
		} else {
			echo '<div class="dvp-table-wrap"><table class="dvp-table"><thead><tr><th>نوع</th><th>مبلغ</th><th>موجودی پس از تراکنش</th><th>شرح</th><th>تاریخ</th></tr></thead><tbody>';
			foreach ( $txns as $t ) {
				$credit = 'credit' === ( $t->type ?? '' );
				printf(
					'<tr><td><span class="dvp-tx %s">%s</span></td><td>%s%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					$credit ? 'plus' : 'minus',
					$credit ? 'واریز' : 'برداشت',
					$credit ? '+' : '−',
					wp_kses_post( wc_price( (float) ( $t->amount ?? 0 ) ) ),
					wp_kses_post( wc_price( (float) ( $t->balance_after ?? 0 ) ) ),
					esc_html( (string) ( $t->description ?? '' ) ),
					esc_html( Dastyar_Jalali::dt( (string) ( $t->created_at ?? '' ) ) )
				);
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}

	/* ==================================================================
	 *  بخش: علاقه‌مندی‌ها (v1.10.18 — قبلاً کارت کناری داشبورد بود، حالا منوی مستقل)
	 * ============================================================== */

	protected function sec_favorites( $uid ) {
		if ( ! class_exists( 'Dastyar_Growth' ) ) {
			echo '<div class="dvp-card"><p>این بخش در دسترس نیست.</p></div>';
			return;
		}
		$html = Dastyar_Growth::favs_card_html( $uid );
		if ( '' === trim( (string) $html ) ) {
			echo '<div class="dvp-card dvp-center">'
				. '<div class="dvp-bigico">' . self::icon( 'heart' ) . '</div>'
				. '<h3>هنوز چیزی ذخیره نکرده‌اید</h3>'
				. '<p class="dvp-hint">وقتی تو کاتالوگ محصولات روی آیکن قلب بزنید، همان‌جا اینجا نمایش داده می‌شود تا بعداً راحت پیداش کنید.</p>'
				. '<p><a class="dvp-btn" href="' . esc_url( home_url( '/' ) ) . '">مشاهده کاتالوگ محصولات ←</a></p>'
				. '</div>';
			return;
		}
		echo '<div class="dvp-card">';
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی کنترل‌شده Dastyar_Growth
		echo '</div>';
	}

	/* ==================================================================
	 *  بخش: مرجوعی‌ها (ماژول RMA مرکز)
	 * ============================================================== */

	protected function sec_rma( $uid ) {
		if ( ! class_exists( 'Dastyar_Rma' ) ) {
			echo '<div class="dvp-card"><p class="dvp-empty">ماژول مرجوعی در این نسخه مرکز فعال نیست.</p></div>';
			return;
		}
		$posts = get_posts( array(
			'post_type'      => Dastyar_Rma::POST_TYPE,
			'meta_key'       => Dastyar_Rma::M_VENDOR,
			'meta_value'     => $uid,
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		$finals = Dastyar_Rma::finals();

		echo '<div class="dvp-card"><h3>درخواست‌های مرجوعی</h3>';
		if ( ! $posts ) {
			echo '<p class="dvp-empty">درخواست مرجوعی‌ای ثبت نشده است. درخواست‌های مرجوعی از افزونه اتصال‌دهنده روی فروشگاه‌تان ثبت می‌شوند.</p></div>';
			return;
		}
		echo '<div class="dvp-table-wrap"><table class="dvp-table"><thead><tr>';
		echo '<th>درخواست</th><th>سفارش</th><th>دلیل</th><th>وضعیت</th><th>نتیجه نهایی</th><th>اعتبار برگشتی</th><th>یادداشت مدیر</th>';
		echo '</tr></thead><tbody>';
		foreach ( $posts as $p ) {
			$status   = (string) get_post_meta( $p->ID, Dastyar_Rma::M_STATUS, true ) ?: 'submitted';
			$reason   = (string) get_post_meta( $p->ID, Dastyar_Rma::M_REASON, true );
			$remote   = (string) get_post_meta( $p->ID, Dastyar_Rma::M_REMOTE, true );
			$order    = (int) get_post_meta( $p->ID, Dastyar_Rma::M_ORDER, true );
			$final    = (string) get_post_meta( $p->ID, Dastyar_Rma::M_FINAL, true );
			$credit   = (float) get_post_meta( $p->ID, Dastyar_Rma::M_CREDIT, true );
			$admin_n  = (string) get_post_meta( $p->ID, Dastyar_Rma::M_ADMIN_N, true );
			echo '<tr>';
			printf( '<td><strong>#%s</strong><br><small>%s</small></td>', esc_html( $p->ID ), esc_html( Dastyar_Jalali::day( $p->post_date ) ) );
			echo '<td>' . ( $remote ? '🛒 #' . esc_html( $remote ) : ( $order ? '#' . esc_html( $order ) : '—' ) ) . '</td>';
			printf( '<td>%s</td>', $reason ? esc_html( wp_trim_words( $reason, 12 ) ) : '—' );
			printf(
				'<td><span class="dvp-badge" style="background:%s">%s</span></td>',
				esc_attr( Dastyar_Rma::status_color( $status ) ),
				esc_html( Dastyar_Rma::status_label( $status ) )
			);
			echo '<td>' . ( isset( $finals[ $final ] ) ? esc_html( $finals[ $final ] ) : '—' ) . '</td>';
			echo '<td>' . ( $credit ? wp_kses_post( wc_price( $credit ) ) : '—' ) . '</td>';
			echo '<td>' . ( $admin_n ? esc_html( wp_trim_words( $admin_n, 12 ) ) : '—' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div></div>';

		// آدرس مرجع مرجوعی‌ها
		$ret = Dastyar_Rma::return_address();
		if ( is_string( $ret ) && '' !== $ret ) {
			echo '<div class="dvp-card"><h4>آدرس مرجع مرجوعی‌ها</h4><p>' . esc_html( $ret ) . '</p></div>';
		}
	}

	/* ==================================================================
	 *  بخش: پیشنهاد محصول — رندر آماده Core
	 * ============================================================== */

	protected function sec_suggest( $uid ) {
		echo '<div class="dvp-card">';
		Dastyar::instance()->suggestions->render_account( $uid );
		echo '</div>';
	}

	/* ==================================================================
	 *  بخش: اتصال فروشگاه (API)
	 * ============================================================== */

	protected function sec_api( $uid ) {
		$vendors = Dastyar::instance()->vendors;
		$hint    = $vendors->hint( $uid );
		$site    = $vendors->site_url( $uid );
		$linked  = $hint && $site;
		$new_key = get_transient( 'dastyar_new_key_' . $uid );
		if ( $new_key ) {
			delete_transient( 'dastyar_new_key_' . $uid );
		}

		if ( $new_key ) {
			echo '<div class="dvp-card dvp-keybox"><h3>کلید API جدید شما</h3>';
			echo '<p><strong>فقط همین یک‌بار نمایش داده می‌شود</strong> — آن را کپی و در تنظیمات افزونه Connector وارد کنید:</p>';
			printf( '<p><code class="dvp-key" dir="ltr">%s</code> <button type="button" class="dvp-copy" data-dvp-copy="%s">📋 کپی</button></p></div>', esc_html( $new_key ), esc_attr( $new_key ) );
		}

		echo '<div class="dvp-card"><h3>وضعیت اتصال فروشگاه</h3>';
		printf(
			'<div class="dvp-conn %s"><span class="dvp-conn-dot"></span>%s</div>',
			$linked ? 'on' : 'off',
			$linked ? 'فروشگاه شما به مرکز متصل است ✅' : 'فروشگاه هنوز متصل نیست'
		);
		echo '<table class="dvp-kv">';
		printf( '<tr><th>آدرس سایت مرکزی</th><td><code dir="ltr">%s</code></td></tr>', esc_html( untrailingslashit( home_url() ) ) );
		printf( '<tr><th>کلید API فعلی</th><td>%s</td></tr>', $hint ? '<code dir="ltr">' . esc_html( $hint ) . '</code>' : '<span class="dvp-hint">هنوز ساخته نشده است — با دکمه زیر بسازید.</span>' );
		printf( '<tr><th>آدرس سایت فروشگاه</th><td>%s</td></tr>', $site ? '<code dir="ltr">' . esc_html( $site ) . '</code>' : '<span class="dvp-hint">پس از اولین اتصال افزونه Connector به‌صورت خودکار ثبت می‌شود.</span>' );
		echo '</table>';

		echo '<form method="post" class="dvp-inline-form" onsubmit="return confirm(\'کلید فعلی باطل می‌شود و اتصال فعلی قطع خواهد شد. مطمئنید؟\')">';
		$this->form_head( 'api_key', 'api' );
		echo '<button type="submit" class="dvp-btn dvp-btn-ghost">🔄 تولید کلید جدید (ابطال کلید قبلی)</button></form>';
		echo '<details class="dvp-details"><summary>جزئیات فنی برای توسعه‌دهندگان</summary>';
		printf( '<p>آدرس پایه REST API: <code dir="ltr">%s</code><br>احراز هویت با هدر <code>X-Dastyar-Key</code> انجام می‌شود.</p>', esc_html( home_url( '/wp-json/dastyar/v1' ) ) );
		echo '</details></div>';

		// راهنمای نصب افزونه اتصال‌دهنده
		echo '<div class="dvp-card dvp-guide"><h3>دانلود و نصب افزونه اتصال‌دهنده (Connector)</h3>';
		echo '<ol class="dvp-steps-ol">';
		echo '<li>افزونه «Dastyar Connector» را از دکمه زیر دانلود کنید.</li>';
		echo '<li>روی سایت فروشگاه خود: پیشخوان ← افزونه‌ها ← افزودن ← بارگذاری افزونه ← نصب و فعال‌سازی (ووکامرس باید فعال باشد).</li>';
		echo '<li>در تنظیمات افزونه، <strong>آدرس سایت مرکزی</strong> و <strong>کلید API</strong> بالا را وارد و ذخیره کنید.</li>';
		echo '<li>محصولات را به‌صورت تکی یا دسته‌جمعی به فروشگاه خود اضافه کنید — سفارش‌ها خودکار به مرکز می‌رسند.</li>';
		echo '</ol>';
		printf( '<p style="margin:10px 0 0"><a class="dvp-btn dvp-btn-big" href="%s" target="_blank" rel="noopener">⬇ دانلود افزونه اتصال‌دهنده</a></p></div>', esc_url( (string) Dastyar_Panel_Settings::get( 'connector_url' ) ) );
	}

	/* ==================================================================
	 *  بخش: تیکت‌ها — نسخه پنل (لینک‌ها داخل پنل می‌مانند)
	 * ============================================================== */

	protected function sec_tickets( $uid ) {
		$tickets = Dastyar::instance()->tickets;

		// نمای یک تیکت
		$ticket_id = (int) ( $_GET['dvp_ticket'] ?? 0 );
		if ( $ticket_id ) {
			$ticket = get_post( $ticket_id );
			if ( $ticket && Dastyar_Tickets::POST_TYPE === $ticket->post_type && (int) $ticket->post_author === $uid ) {
				$status = wp_get_object_terms( $ticket->ID, Dastyar_Tickets::TAX, array( 'fields' => 'names' ) );
				echo '<div class="dvp-card">';
				printf( '<p style="margin:0 0 10px"><a class="dvp-btn dvp-btn-ghost" href="%s">→ بازگشت به تیکت‌ها</a></p>', esc_url( self::url( 'tickets' ) ) );
				printf( '<h3 style="margin-top:0">%s <span class="dvp-badge" style="background:#8a919e">%s</span></h3>', esc_html( $ticket->post_title ), esc_html( $status ? $status[0] : '' ) );
				echo '<div class="dvp-ticket-body"><strong>شما:</strong> ' . wp_kses_post( wpautop( $ticket->post_content ) ) . '</div>';

				$comments = get_comments( array( 'post_id' => $ticket->ID, 'status' => 'approve', 'order' => 'ASC' ) );
				foreach ( (array) $comments as $c ) {
					$is_admin = user_can( $c->user_id, 'manage_woocommerce' );
					printf(
						'<div class="dvp-ticket-reply%s"><strong>%s</strong> <small>%s</small><br>%s</div>',
						$is_admin ? ' is-admin' : '',
						esc_html( $is_admin ? 'پشتیبانی دستیار' : 'شما' ),
						esc_html( isset( $c->comment_date ) ? Dastyar_Jalali::dt( (string) $c->comment_date ) : '' ),
						wp_kses_post( wpautop( $c->comment_content ) )
					);
				}
				echo '<form method="post" class="dvp-ticket-form">';
				$this->form_head( 'ticket_reply', 'tickets' );
				printf( '<input type="hidden" name="ticket_id" value="%d">', (int) $ticket->ID );
				printf( '<input type="hidden" name="dvp_back" value="%s">', esc_url( self::url( 'tickets', array( 'dvp_ticket' => (int) $ticket->ID ) ) ) );
				echo '<p><textarea name="message" rows="4" required placeholder="پاسخ شما…"></textarea></p>';
				echo '<button type="submit" class="dvp-btn">ارسال پاسخ ←</button></form></div>';
				return;
			}
			// تیکت نامعتبر
			echo '<div class="dvp-card"><p class="dvp-empty">تیکت یافت نشد.</p>';
			printf( '<p><a class="dvp-btn dvp-btn-ghost" href="%s">→ بازگشت</a></p></div>', esc_url( self::url( 'tickets' ) ) );
			return;
		}

		// فرم تیکت جدید
		echo '<div class="dvp-card"><h3>ثبت تیکت جدید</h3>';
		echo '<form method="post" class="dvp-ticket-form">';
		$this->form_head( 'ticket_new', 'tickets' );
		echo '<p><input type="text" name="subject" placeholder="عنوان تیکت" required></p>';
		echo '<p><textarea name="message" rows="4" placeholder="شرح مشکل یا سوال…" required></textarea></p>';
		echo '<button type="submit" class="dvp-btn">ارسال تیکت ←</button></form></div>';

		// لیست تیکت‌ها
		$list = get_posts( array(
			'post_type'      => Dastyar_Tickets::POST_TYPE,
			'author'         => $uid,
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		echo '<div class="dvp-card"><h3>تیکت‌های شما</h3>';
		if ( ! $list ) {
			echo '<p class="dvp-empty">تیکتی ثبت نشده است.</p></div>';
			return;
		}
		echo '<div class="dvp-table-wrap"><table class="dvp-table"><thead><tr><th>عنوان</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead><tbody>';
		foreach ( $list as $t ) {
			$status = wp_get_object_terms( $t->ID, Dastyar_Tickets::TAX, array( 'fields' => 'names' ) );
			echo '<tr>';
			printf( '<td><strong>%s</strong></td>', esc_html( $t->post_title ) );
			echo '<td>' . esc_html( $status ? $status[0] : '—' ) . '</td>';
			echo '<td>' . esc_html( Dastyar_Jalali::day( $t->post_date ) ) . '</td>';
			printf( '<td><a class="dvp-btn dvp-btn-sm" href="%s">مشاهده ←</a></td>', esc_url( self::url( 'tickets', array( 'dvp_ticket' => (int) $t->ID ) ) ) );
			echo '</tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/* ==================================================================
	 *  بخش: حساب کاربری
	 * ============================================================== */

	protected function sec_account( $uid ) {
		$user    = get_userdata( $uid );
		$logo_id = (int) get_user_meta( $uid, '_dastyar_logo_id', true );
		$logo    = $logo_id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

		echo '<div class="dvp-card"><h3>حساب کاربری</h3>';
		echo '<form method="post" enctype="multipart/form-data">';
		$this->form_head( 'account_save', 'account' );

		echo '<div class="dvp-grid2">';
		printf( '<p><label>نام</label><input type="text" name="first_name" value="%s"></p>', esc_attr( $user ? $user->first_name : '' ) );
		printf( '<p><label>نام خانوادگی</label><input type="text" name="last_name" value="%s"></p>', esc_attr( $user ? $user->last_name : '' ) );
		echo '</div><div class="dvp-grid2">';
		printf( '<p><label>نام نمایشی</label><input type="text" name="display_name" value="%s"></p>', esc_attr( $user ? $user->display_name : '' ) );
		printf( '<p><label>موبایل</label><input type="tel" name="billing_phone" dir="ltr" value="%s"></p>', esc_attr( (string) get_user_meta( $uid, 'billing_phone', true ) ) );
		echo '</div><div class="dvp-grid2">';
		printf( '<p><label>نام فروشگاه</label><input type="text" name="shop_name" value="%s"></p>', esc_attr( (string) get_user_meta( $uid, '_dastyar_shop_name', true ) ) );
		printf( '<p><label>آدرس سایت فروشگاه</label><input type="url" name="site_url" dir="ltr" value="%s"></p>', esc_attr( (string) get_user_meta( $uid, Dastyar_Vendors::SITE, true ) ) );
		echo '</div>';

		// لوگو
		echo '<div class="dvp-logobox"><label><strong>لوگوی فروشنده</strong> <small class="dvp-hint">(روی لیبل آدرس مرسوله‌ها چاپ می‌شود)</small></label><br>';
		if ( $logo ) {
			printf( '<img src="%s" alt="لوگو" class="dvp-logo-cur">', esc_url( $logo ) );
			echo ' <label><input type="checkbox" name="remove_logo" value="1"> حذف لوگوی فعلی</label><br>';
		}
		echo '<input type="file" name="dastyar_logo" accept=".jpg,.jpeg,.png,.webp"></div>';

		// رمز
		echo '<div class="dvp-grid2">';
		echo '<p><label>رمز عبور جدید <small class="dvp-hint">(اختیاری — حداقل ۸ کاراکتر)</small></label><input type="password" name="password" autocomplete="new-password"></p>';
		echo '<p><label>تکرار رمز عبور جدید</label><input type="password" name="password2" autocomplete="new-password"></p>';
		echo '</div>';

		echo '<p style="margin:14px 0 0"><button type="submit" class="dvp-btn dvp-btn-big">ذخیره تغییرات ←</button></p>';
		echo '</form></div>';
	}

	/* ==================================================================
	 *  بخش: قرارداد همکاری (v1.4.0 — مورد ۱۳)
	 *  متن + فایل PDF از تنظیمات ادمین می‌آید؛ امضا در متا کاربر ثبت می‌شود.
	 * ============================================================== */

	const CONTRACT_META = '_dvp_contract_accepted';

	/** زمان امضای قرارداد (timestamp) یا 0 */
	public static function contract_accepted_at( $uid ) {
		return (int) get_user_meta( $uid, self::CONTRACT_META, true );
	}

	protected function sec_contract( $uid ) {
		$title = (string) Dastyar_Panel_Settings::get( 'contract_title', 'قرارداد همکاری فروشندگی دستیار شاپ' );
		$text  = (string) Dastyar_Panel_Settings::get( 'contract_text', '' );
		$pdf   = (string) Dastyar_Panel_Settings::get( 'contract_pdf_url', '' );
		$at    = self::contract_accepted_at( $uid );

		echo '<div class="dvp-card dvp-contract">';
		printf( '<h3><span class="dvp-pl-ic">%s</span> %s</h3>', self::icon( 'contract' ), esc_html( $title ) );

		if ( $at ) {
			// وضعیت تأیید — بنر سبز با SVG تیک
			echo '<div class="dvp-contract-signed">';
			echo '<span class="dvp-cs-badge">' . self::icon( 'check' ) . '</span>';
			echo '<div><strong>شما قرارداد همکاری را تأیید و امضا نموده‌اید.</strong>';
			printf( '<span>تاریخ امضا: %s</span></div>', esc_html( Dastyar_Jalali::format( 'Y/m/d — H:i', $at ) ) );
			echo '</div>';
		}

		if ( $pdf ) {
			printf( '<p class="dvp-contract-dl"><a class="dvp-btn dvp-btn-ghost" href="%s" target="_blank" rel="noopener">%s دانلود فایل PDF قرارداد</a></p>', esc_url( $pdf ), self::icon( 'doc' ) );
		}

		echo '<div class="dvp-contract-body">' . wp_kses_post( nl2br( $text ) ) . '</div>';

		if ( $at ) {
			printf( '<p class="dvp-hint" style="margin-top:14px">این متن همان نسخه‌ای است که در تاریخ %s توسط شما امضا شد؛ برای تغییر شرایط با پشتیبانی در تماس باشید.</p>', esc_html( Dastyar_Jalali::format( 'Y/m/d', $at ) ) );
		} else {
			echo '<form method="post" class="dvp-contract-form">';
			$this->form_head( 'contract_accept', 'contract' );
			echo '<p class="dvp-contract-warn">با تأیید این قرارداد، شرایط همکاری با دستیار شاپ را می‌پذیرید؛ این اقدام به‌عنوان امضای الکترونیکی شما ثبت می‌شود.</p>';
			echo '<button type="submit" class="dvp-btn dvp-btn-big">' . self::icon( 'check' ) . ' قرارداد را خواندم و تأیید و امضا می‌نمایم ←</button>';
			echo '</form>';
		}
		echo '</div>';
	}

	/** پاپ‌آپ مسدودکننده قرارداد در اولین ورود (تا وقتی امضا اعمال نشود) */
	protected function contract_modal( $uid ) {
		if ( ! Dastyar_Panel_Settings::yes( 'sec_contract' ) ) {
			return;
		}
		$title = (string) Dastyar_Panel_Settings::get( 'contract_title', 'قرارداد همکاری فروشندگی دستیار شاپ' );
		$text  = (string) Dastyar_Panel_Settings::get( 'contract_text', '' );
		if ( '' === trim( $text ) || self::contract_accepted_at( $uid ) ) {
			return;
		}
		?>
		<div class="dvp-contract-modal" id="dvp-contract-modal" role="dialog" aria-modal="true">
			<div class="dvp-cm-card">
				<div class="dvp-cm-head">
					<span class="dvp-cm-ic"><?php echo self::icon( 'contract' ); ?></span>
					<div><h2><?php echo esc_html( $title ); ?></h2>
					<p>برای شروع استفاده از پنل فروشنده، مطالعه و تأیید قرارداد همکاری الزامی است.</p></div>
				</div>
				<div class="dvp-cm-body"><?php echo wp_kses_post( nl2br( $text ) ); ?></div>
				<?php $pdf = (string) Dastyar_Panel_Settings::get( 'contract_pdf_url', '' ); if ( $pdf ) : ?>
					<p class="dvp-cm-pdf"><a href="<?php echo esc_url( $pdf ); ?>" target="_blank" rel="noopener"><?php echo self::icon( 'doc' ); ?> دانلود نسخه PDF قرارداد</a></p>
				<?php endif; ?>
				<form method="post" class="dvp-cm-actions">
					<?php $this->form_head( 'contract_accept', 'dashboard' ); ?>
					<button type="submit" class="dvp-btn dvp-btn-big"><?php echo self::icon( 'check' ); ?> قرارداد را خواندم و تأیید و امضا می‌نمایم ←</button>
				</form>
			</div>
		</div>
		<?php
	}

	/* ==================================================================
	 *  استایل و اسکریپت
	 * ============================================================== */

	public function css_html() {
		static $done = false;
		if ( $done ) {
			return '';
		}
		$done = true;
		ob_start();
		?>
<style>
@import url("https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css");
.dvp-wrap{--dvp-green:#17a16d;--dvp-green-dark:#12875c;--dvp-navy:#242536;--dvp-line:#e6e8ef;--dvp-bg:#f6f7fa;--dvp-text:#2b2c3a;--dvp-muted:#8a8d9d;font-family:"Vazirmatn","IRANSans",Tahoma,sans-serif;max-width:1100px;margin:0 auto;padding:6px 2px;color:var(--dvp-text)}
		.dvp-wrap *{box-sizing:border-box}
		.dvp-head{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:16px}
		.dvp-head-t h1{font-size:21px;font-weight:800;color:var(--dvp-navy);margin:0 0 3px}
		.dvp-head-t p{margin:0;color:var(--dvp-muted);font-size:13px}
		.dvp-userbox{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--dvp-line);border-radius:10px;padding:8px 14px}
		.dvp-avatar{width:44px;height:44px;border-radius:50%;object-fit:cover;border:1px solid var(--dvp-line);background:#fff}
		.dvp-avatar-ph{display:inline-flex;align-items:center;justify-content:center;font-size:22px;background:var(--dvp-bg);color:var(--dvp-navy)}
		.dvp-uname{font-size:13.5px;font-weight:700;color:var(--dvp-navy);line-height:1.7}
		.dvp-uname small{display:block;font-size:11.5px;font-weight:700;color:var(--dvp-green)}
		.dvp-shell{display:grid;grid-template-columns:225px minmax(0,1fr);gap:16px;align-items:start}
		.dvp-nav{position:sticky;top:16px;background:#fff;border:1px solid var(--dvp-line);border-radius:12px;padding:10px;display:flex;flex-direction:column;gap:2px}
		.dvp-nav-link{display:flex;align-items:center;gap:9px;padding:9px 11px;border-radius:8px;text-decoration:none;color:#3a3c4e;font-size:13px;font-weight:700;transition:background .12s,color .12s}
		.dvp-nav-link:hover{background:var(--dvp-bg);color:var(--dvp-navy)}
		.dvp-nav-link.dvp-on{background:var(--dvp-green);color:#fff}
		.dvp-nic{display:inline-flex;line-height:0;flex-shrink:0}
		.dvp-home{margin-top:8px;border-top:1px solid var(--dvp-line);padding-top:13px;color:var(--dvp-green)!important}
		.dvp-home:hover{background:#ecf9f2;color:var(--dvp-green-dark)!important}
		.dvp-logout{margin-top:2px;color:#dc2626!important}
		.dvp-logout:hover{background:#fdf0f0;color:#dc2626!important}
		.dvp-content{min-width:0}
		.dvp-card{background:#fff;border:1px solid var(--dvp-line);border-radius:12px;padding:18px 20px;margin:0 0 16px}
		.dvp-card h3{margin:0 0 14px;color:var(--dvp-navy);font-size:15.5px;font-weight:800}
		.dvp-card h4{color:var(--dvp-navy);font-size:14px}
		.dvp-center{text-align:center;padding:30px 20px}
		.dvp-bigico{font-size:40px;margin-bottom:6px}
		.dvp-empty{color:var(--dvp-muted);background:var(--dvp-bg);border:1px dashed var(--dvp-line);border-radius:10px;padding:13px 16px;font-size:13px}
		/* v1.10.19 — طرح گرافیکی حالت خالی «آخرین سفارش‌ها» */
		.dvp-empty-orders{text-align:center;padding:34px 20px}
		.dvp-empty-orders-ic{width:64px;height:64px;border-radius:50%;background:var(--dvp-bg);color:var(--dvp-green);display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
		.dvp-empty-orders-ic svg{width:30px;height:30px}
		.dvp-empty-orders h4{margin:0 0 6px;color:var(--dvp-navy);font-size:15px}
		.dvp-empty-orders p{margin:0 0 18px;color:var(--dvp-muted);font-size:13px}
		.dvp-empty-orders-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
		/* v1.10.18 — بنر داشبورد + جعبه جمع‌شونده «از اینجا شروع کن» */
		.dvp-dash-banner{display:block;margin:0 0 14px;border-radius:14px;overflow:hidden;line-height:0}
		.dvp-dash-banner img{width:100%;height:auto;display:block}
		.dvp-onboard{background:#fff;border:1.5px solid var(--dvp-line);border-radius:16px;margin:0 0 16px;overflow:hidden}
		.dvp-onboard summary{cursor:pointer;list-style:none;display:flex;align-items:center;gap:8px;padding:16px 20px;font-weight:800;color:var(--dvp-navy);font-size:14.5px;user-select:none}
		.dvp-onboard summary::-webkit-details-marker{display:none}
		.dvp-onboard summary::after{content:'';margin-inline-start:auto;width:9px;height:9px;border-inline-end:2px solid var(--dvp-muted);border-bottom:2px solid var(--dvp-muted);transform:rotate(45deg);transition:transform .15s}
		.dvp-onboard[open] summary::after{transform:rotate(-135deg)}
		.dvp-onboard-body{padding:0 20px 18px}
		.dvp-onboard-body .dgr-card.dgr-start{margin:0;border:0;box-shadow:none;padding:0}
		.dvp-onboard-body .dgr-start-t{display:none}
		.dvp-notice{border-radius:8px;padding:11px 15px;margin:0 0 14px;font-size:13px;font-weight:700;border:1px solid}
		.dvp-ok{background:#ecf9f2;border-color:#c6e9d8;color:#0f5132}
		.dvp-err{background:#fdf0f0;border-color:#f0c8c8;color:#a52828}
		.dvp-btn{display:inline-block;background:var(--dvp-green);color:#fff!important;border:0;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer;font-family:inherit;transition:background .12s}
		.dvp-btn:hover{background:var(--dvp-green-dark)}
		.dvp-btn-sm{padding:4px 11px;font-size:12px}
		.dvp-btn-big{padding:11px 24px;font-size:14px}
		.dvp-btn-ghost{background:var(--dvp-bg)!important;color:var(--dvp-navy)!important;border:1px solid var(--dvp-line)}
		.dvp-btn-ghost:hover{background:#eceef3!important;color:var(--dvp-navy)!important}
		.dvp-inline-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:6px}
		.dvp-inline-form input{border:1px solid var(--dvp-line);border-radius:8px;padding:8px 13px;font-family:inherit;font-size:13px;min-width:200px;background:#fff}
		.dvp-inline-form input:focus{border-color:var(--dvp-green);outline:none}
		.dvp-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
		.dvp-stat{background:#fff;border:1px solid var(--dvp-line);border-radius:10px;padding:13px 15px;text-decoration:none;display:flex;flex-direction:column;gap:3px;transition:border-color .12s}
		.dvp-stat:hover{border-color:var(--dvp-green)}
		.dvp-stat-ic{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:9px;margin-bottom:2px}
		.dvp-stat-lb{font-size:11.5px;color:var(--dvp-muted);font-weight:700}
		.dvp-stat-vl{font-size:15.5px;font-weight:800;color:var(--dvp-navy)}
		.dvp-quick{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
		.dvp-quick-link{background:#fff;border:1px solid var(--dvp-line);border-radius:10px;padding:12px;text-align:center;text-decoration:none;color:var(--dvp-navy);font-size:13px;font-weight:700;transition:border-color .12s,color .12s}
		.dvp-quick-link:hover{border-color:var(--dvp-green);color:var(--dvp-green-dark)}
		.dvp-dash-grid{display:grid;grid-template-columns:1.55fr 1fr;gap:14px;align-items:start}
		.dvp-recent{padding-bottom:12px}
		.dvp-orow{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f0f1f5;text-decoration:none;flex:1;min-width:0}
		.dvp-orow-wrap{display:flex;align-items:center;gap:10px;border-bottom:1px solid #f0f1f5}
		.dvp-orow-wrap .dvp-orow{border-bottom:0}
		.dvp-orow-wrap:last-of-type{border-bottom:0}
		.dvp-orow:last-of-type{border-bottom:0}
		.dvp-othumb{width:38px;height:38px;border-radius:9px;background:var(--dvp-bg);overflow:hidden;display:inline-flex;align-items:center;justify-content:center;color:var(--dvp-muted);flex-shrink:0}
		.dvp-othumb img{width:100%;height:100%;object-fit:cover}
		.dvp-oinfo{flex:1;min-width:0}
		.dvp-oino{font-size:13px;font-weight:800;color:var(--dvp-navy)}
		.dvp-omini{font-size:11px;color:var(--dvp-muted);font-weight:700}
		.dvp-odate{display:block;font-size:11px;color:var(--dvp-muted);margin-top:1px}
		.dvp-ost{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;white-space:nowrap}
		.dvp-ost svg{width:13px;height:13px}
		.dvp-odot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-inline-end:6px}
		.dvp-viewall{margin:10px 0 0;text-align:left}
		.dvp-viewall a{color:var(--dvp-green-dark);font-size:12.5px;font-weight:800;text-decoration:none}
		.dvp-dash-side{display:flex;flex-direction:column;gap:14px;min-width:0}
		.dvp-promo{background:linear-gradient(135deg,#242536,#2e2f47);border-radius:12px;padding:18px 20px;color:#fff}
		.dvp-promo-ic{display:inline-flex;line-height:0;color:var(--dvp-green);margin-bottom:6px}
		.dvp-promo h4{margin:0 0 6px;font-size:15px;font-weight:800;color:#fff}
		.dvp-promo p{margin:0 0 10px;font-size:12px;color:#b9bccd;line-height:1.9}
		.dvp-promo-code{display:inline-block;background:rgba(23,161,109,.16);border:1.5px dashed var(--dvp-green);color:#7ce8b0;border-radius:8px;padding:5px 14px;font-size:13px;font-weight:800;letter-spacing:1px}
		.dvp-promo-copy{background:transparent;border-color:rgba(255,255,255,.25);color:#c9cbdc}
		.dvp-acct h3{margin-bottom:10px}
		.dvp-arow{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid #f0f1f5;font-size:13px}
		.dvp-arow span{color:var(--dvp-muted);font-weight:700}
		.dvp-arow strong{color:var(--dvp-navy);font-weight:800;text-align:left;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
		.dvp-arow:last-of-type{border-bottom:0}
		.dvp-editlink{display:inline-flex;align-items:center;gap:7px;margin-top:10px;background:var(--dvp-bg);border:1px solid var(--dvp-line);color:var(--dvp-navy);border-radius:8px;padding:7px 14px;font-size:12.5px;font-weight:800;text-decoration:none;transition:border-color .12s}
		.dvp-editlink:hover{border-color:var(--dvp-green);color:var(--dvp-green-dark)}
		.dvp-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
		.dvp-table{width:100%;min-width:620px;border-collapse:collapse;font-size:13px}
		.dvp-table th{background:var(--dvp-bg);border-bottom:1px solid var(--dvp-line);padding:9px 12px;text-align:right;color:#5a5d6e;font-weight:700;white-space:nowrap}
		.dvp-table td{border-bottom:1px solid #f0f1f5;padding:9px 12px;vertical-align:middle;color:var(--dvp-text)}
		.dvp-table tr:last-child td{border-bottom:0}
		.dvp-badge{display:inline-block;color:#fff;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;white-space:nowrap}
		.dvp-manual-badge{background:var(--dvp-navy);color:#fff;border-radius:12px;padding:1px 9px;font-size:10.5px;font-weight:700}
		.dvp-origin{font-size:12px;color:#5a5d6e}
		.dvp-track{background:var(--dvp-bg);border:1px solid var(--dvp-line);border-radius:6px;padding:2px 8px;font-size:12px}
		.dvp-copy{background:#fff;border:1px solid var(--dvp-line);border-radius:6px;padding:3px 8px;font-size:11.5px;cursor:pointer;color:var(--dvp-navy);font-family:inherit}
		.dvp-copy:hover{border-color:var(--dvp-green);color:var(--dvp-green-dark)}
		.dvp-pager{display:flex;align-items:center;justify-content:center;gap:12px;margin-top:14px}
		.dvp-pager-cur{font-size:12.5px;font-weight:700;color:#5a5d6e}
		.dvp-odhead{padding-bottom:12px;border-bottom:1px solid var(--dvp-line);margin-bottom:14px}
		.dvp-odmeta{color:var(--dvp-muted);font-size:12.5px;margin-top:5px}
		.dvp-totals{margin:14px 0;border:1px solid var(--dvp-line);border-radius:10px;overflow:hidden}
		.dvp-totals div{display:flex;justify-content:space-between;padding:9px 16px;font-size:13px;border-bottom:1px solid var(--dvp-line)}
		.dvp-totals div:last-child{border-bottom:0}
		.dvp-totals .dvp-grand{background:var(--dvp-bg);color:var(--dvp-navy);font-weight:800}
		.dvp-trackbox{background:var(--dvp-bg);border:1px solid var(--dvp-line);border-radius:10px;padding:12px 16px;margin:6px 0 14px;font-size:13px}
		.dvp-trackbox svg{vertical-align:-3px;margin-inline-end:5px}
		.dvp-chargebox{margin:0 0 16px}
		.dvp-charge-ok{display:flex;align-items:center;gap:12px;background:#eafaf2;border:1px solid #bfe6d2;border-radius:12px;padding:14px 16px;color:#0f5132}
		.dvp-charge-ok svg{width:26px;height:26px;flex-shrink:0}
		.dvp-charge-ok strong{display:block;font-size:14px}
		.dvp-charge-ok span{display:block;font-size:12px;color:#3d7a5c;margin-top:2px}
		.dvp-charge-pending{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#fdf3e2;border:1px solid #eccb6a;border-radius:12px;padding:14px 16px}
		.dvp-charge-pending>div{display:flex;align-items:center;gap:12px}
		.dvp-charge-pending svg{width:26px;height:26px;flex-shrink:0;color:#7a5b00}
		.dvp-charge-pending strong{display:block;font-size:14px;color:#7a5b00}
		.dvp-charge-pending span{display:block;font-size:12px;color:#8a6c1f;margin-top:2px}
		.dvp-tb-title{font-weight:800;color:var(--dvp-navy)}
		.dvp-tb-sub{margin:6px 0 0;color:var(--dvp-green-dark);font-size:12px}
		.dvp-recipient{border-top:1px dashed var(--dvp-line);padding-top:10px}
		.dvp-wallet-hero{display:flex;align-items:flex-start;gap:14px;background:var(--dvp-navy);border-right:4px solid var(--dvp-green);border-radius:12px;padding:18px 20px;color:#fff;margin:0 0 16px}
		.dvp-wh-ic{display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:12px;background:rgba(23,161,109,.22);color:#4fe0a8;flex-shrink:0;margin-top:3px}
		.dvp-wh-ic svg{width:24px;height:24px}
		.dvp-wh-lb{font-size:12px;color:#b9bccd;font-weight:600}
		.dvp-wh-am{display:block;font-size:25px;font-weight:800;margin:2px 0;color:#fff}
		.dvp-wh-ds{font-size:12px;color:#9a9db0;line-height:1.8}
		.dvp-wh-quick{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px dashed rgba(255,255,255,.16)}
		.dvp-wh-qk-lb{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;color:#4fe0a8}
		.dvp-wh-qk-f{margin:0;display:inline}
		.dvp-wh-qk-btn{background:rgba(23,161,109,.18);border:1.5px solid rgba(79,224,168,.5);color:#8af0c6;border-radius:22px;padding:6px 14px;font-size:13px;font-weight:800;cursor:pointer;font-family:inherit;transition:.15s}
		.dvp-wh-qk-btn:hover{background:var(--dvp-green);border-color:var(--dvp-green);color:#fff}
		/* ابزارک تغییرات قیمت (v1.4.0) */
		.dvp-pl-ic{display:inline-flex;line-height:0;vertical-align:-3px;color:var(--dvp-green)}
		.dvp-pl-list{list-style:none;margin:0;padding:0}
		.dvp-pl-item{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:9px 0;border-bottom:1px dashed var(--dvp-line);font-size:12.5px}
		.dvp-pl-item:last-child{border-bottom:0}
		.dvp-pl-name{color:var(--dvp-navy);font-weight:700;line-height:1.7}
		.dvp-pl-name a{color:var(--dvp-navy);text-decoration:none}
		.dvp-pl-name a:hover{color:var(--dvp-green)}
		.dvp-pl-name time{display:block;color:var(--dvp-muted);font-size:11px;font-weight:600}
		.dvp-pl-nums{display:flex;align-items:center;gap:6px;white-space:nowrap;font-size:11.5px}
		.dvp-pl-old{color:var(--dvp-muted);text-decoration:line-through}
		.dvp-pl-nums svg{width:14px;height:14px;color:#dc2626}
		.dvp-pl-new{color:#dc2626;font-size:12.5px}
		.dvp-pl-pct{background:#fdf0f0;color:#dc2626;border:1px solid #f0c8c8;border-radius:20px;padding:1px 8px;font-size:10.5px;font-weight:800}
		.dvp-pl-hint{font-size:11px;color:var(--dvp-muted);background:var(--dvp-bg);border-radius:8px;padding:8px 11px;margin:10px 0 0;line-height:1.9}
		/* قرارداد همکاری (v1.4.0) */
		.dvp-contract-signed{display:flex;align-items:center;gap:12px;background:#ecf9f2;border:1.5px solid #bfe6d2;border-radius:12px;padding:13px 16px;margin:0 0 14px}
		.dvp-cs-badge{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:50%;background:var(--dvp-green);color:#fff;flex-shrink:0}
		.dvp-cs-badge svg{width:19px;height:19px}
		.dvp-contract-signed strong{display:block;color:#0f5132;font-size:14px}
		.dvp-contract-signed span{display:block;color:#166b47;font-size:12px;margin-top:2px;font-weight:700}
		.dvp-contract-dl{margin:0 0 14px}
		.dvp-contract-dl svg{vertical-align:-3px}
		.dvp-contract-body{background:var(--dvp-bg);border:1px solid var(--dvp-line);border-radius:12px;padding:16px 18px;font-size:13px;line-height:2.1;color:#3a3c4e;max-height:420px;overflow-y:auto;white-space:normal}
		.dvp-contract-warn{background:#fff7ed;border:1.5px solid #fed7aa;color:#9a3412;border-radius:10px;padding:11px 14px;font-size:12.5px;font-weight:700;margin:14px 0 10px}
		.dvp-contract-form button svg{vertical-align:-3px}
		/* پاپ‌آپ قرارداد (مسدودکننده اولین ورود) */
		.dvp-contract-modal{position:fixed;inset:0;z-index:999999;background:rgba(20,21,34,.72);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:22px}
		.dvp-cm-card{background:#fff;border-radius:18px;max-width:640px;width:100%;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 24px 70px rgba(0,0,0,.35);overflow:hidden;border-top:5px solid var(--dvp-green)}
		.dvp-cm-head{display:flex;gap:13px;align-items:flex-start;padding:20px 22px 14px}
		.dvp-cm-ic{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:12px;background:#ecf9f2;color:var(--dvp-green);flex-shrink:0}
		.dvp-cm-ic svg{width:22px;height:22px}
		.dvp-cm-head h2{margin:0 0 4px;font-size:17px;font-weight:800;color:var(--dvp-navy)}
		.dvp-cm-head p{margin:0;font-size:12.5px;color:var(--dvp-muted);line-height:1.9}
		.dvp-cm-body{padding:6px 22px 12px;overflow-y:auto;font-size:13px;line-height:2.1;color:#3a3c4e;border-top:1px dashed var(--dvp-line)}
		.dvp-cm-pdf{margin:0;padding:0 22px 6px}
		.dvp-cm-pdf a{color:var(--dvp-green);text-decoration:none;font-weight:700;font-size:12.5px}
		.dvp-cm-pdf svg{vertical-align:-3px}
		.dvp-cm-actions{margin:0;padding:14px 22px 20px;border-top:1px solid var(--dvp-line);background:var(--dvp-bg)}
		.dvp-cm-actions button{width:100%}
		/* بهبود گرافیک ویزارد ثبت سفارش دستی داخل پنل (v1.4.0 — مورد ۲) */
		.dvp-content .dastyar-manual-form .woocommerce-form-row{display:flex;flex-direction:column;margin-bottom:16px}
		.dvp-content .dastyar-manual-form label{font-weight:700;font-size:13px;color:var(--dvp-navy);margin-bottom:6px}
		.dvp-content .dastyar-manual-form label .required{color:#dc2626}
		.dvp-content .dastyar-manual-form .input-text,
		.dvp-content .dastyar-manual-form select{border:1.5px solid #d3e7dc!important;border-radius:12px!important;padding:11px 14px!important;font-family:inherit;font-size:14px;background:#fbfefd;transition:.2s;width:100%;box-shadow:inset 0 1px 3px rgba(23,161,109,.04)}
		.dvp-content .dastyar-manual-form .input-text:focus,
		.dvp-content .dastyar-manual-form select:focus{border-color:var(--dvp-green)!important;outline:none;box-shadow:0 0 0 3px rgba(23,161,109,.14);background:#fff}
		.dvp-content #dastyar-wizard .button{border-radius:12px!important;font-family:inherit;font-weight:700;transition:.15s}
		.dvp-content #dastyar-rec-save{background:var(--dvp-green)!important;border-color:var(--dvp-green)!important;color:#fff!important;padding:12px 28px!important;font-size:14px!important;font-weight:800!important}
		.dvp-content #dastyar-rec-save:hover{background:var(--dvp-green-dark)!important}
		.dvp-content #dastyar-wizard .dastyar-step-nav .dastyar-next{background:var(--dvp-green)!important;border-color:var(--dvp-green)!important;color:#fff!important;padding:12px 30px!important;font-size:15px!important;font-weight:800!important}
		.dvp-content #dastyar-wizard .dastyar-step-nav .dastyar-next:hover{background:var(--dvp-green-dark)!important;color:#fff!important}
		.dvp-content #dastyar-wizard .dastyar-step-nav .dastyar-prev{background:#fff!important;border:1.5px solid #d3e7dc!important;color:#43524b!important;padding:11px 22px!important}
		.dvp-content #dastyar-wizard .dastyar-step-nav .dastyar-prev:hover{border-color:var(--dvp-green)!important;color:var(--dvp-green-dark)!important}
		.dvp-content .dastyar-search-form .input-text{border:1.5px solid #d3e7dc;border-radius:12px;padding:11px 14px;font-size:14px;font-family:inherit}
		.dvp-content #dastyar-mo-search-btn{background:var(--dvp-green)!important;border-color:var(--dvp-green)!important;color:#fff!important;border-radius:12px;padding:11px 22px;font-weight:800}
		.dvp-content .dastyar-more-hint-txt{font-size:12.5px;color:var(--dvp-muted)}
		.dvp-plan-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
		.dvp-plan-form{margin:0}
		.dvp-plan{position:relative;width:100%;display:flex;flex-direction:column;align-items:center;gap:6px;background:#fff;border:1.5px solid var(--dvp-line);border-radius:12px;padding:20px 14px 16px;cursor:pointer;font-family:inherit;text-align:center;transition:border-color .12s}
		.dvp-plan:hover{border-color:var(--dvp-green)}
		.dvp-plan-pop{border-color:var(--dvp-green)}
		.dvp-plan-badge{position:absolute;top:-11px;right:50%;transform:translateX(50%);background:var(--dvp-green);color:#fff;font-size:10.5px;font-weight:700;border-radius:10px;padding:2px 10px;white-space:nowrap}
		.dvp-plan-t{font-size:13.5px;font-weight:800;color:var(--dvp-navy)}
		.dvp-plan-a{font-size:19px;font-weight:800;color:var(--dvp-green-dark)}
		.dvp-plan-d{font-size:11.5px;color:var(--dvp-muted);line-height:1.8;min-height:32px}
		.dvp-plan-c{margin-top:2px;background:var(--dvp-bg);color:var(--dvp-navy);border:1px solid var(--dvp-line);border-radius:8px;padding:6px 16px;font-size:12px;font-weight:700}
		.dvp-plan:hover .dvp-plan-c{background:var(--dvp-green);color:#fff;border-color:var(--dvp-green)}
		.dvp-tx{display:inline-block;border-radius:8px;padding:1px 9px;font-size:11px;font-weight:700}
		.dvp-tx.plus{background:#ecf9f2;color:#0f5132}
		.dvp-tx.minus{background:#fdf0f0;color:#a52828}
		.dvp-keybox{border:1.5px solid var(--dvp-green);background:#f4fcf8}
		.dvp-key{display:inline-block;background:#fff;border:1px dashed var(--dvp-green);border-radius:8px;padding:9px 15px;font-size:13.5px;font-weight:800;letter-spacing:.5px;color:var(--dvp-navy)}
		.dvp-conn{display:flex;align-items:center;gap:9px;border-radius:8px;padding:10px 15px;font-weight:700;font-size:13px;margin-bottom:12px;border:1px solid}
		.dvp-conn.on{background:#ecf9f2;border-color:#c6e9d8;color:#0f5132}
		.dvp-conn.off{background:#fdf6ec;border-color:#efe3c8;color:#8a6116}
		.dvp-conn-dot{width:10px;height:10px;border-radius:50%;background:currentColor}
		.dvp-kv{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:8px}
		.dvp-kv th{text-align:right;color:#5a5d6e;font-weight:700;padding:8px 0 8px 12px;border-bottom:1px solid #f0f1f5;width:200px;vertical-align:top}
		.dvp-kv td{padding:8px 0;border-bottom:1px solid #f0f1f5}
		.dvp-kv tr:last-child th,.dvp-kv tr:last-child td{border-bottom:0}
		.dvp-hint{color:var(--dvp-muted);font-size:12px}
		.dvp-details{margin-top:8px}
		.dvp-details summary{cursor:pointer;color:var(--dvp-green-dark);font-size:12.5px;font-weight:700}
		.dvp-guide{background:var(--dvp-bg)}
		.dvp-steps-ol{line-height:2.1;margin:0;font-size:13px;color:var(--dvp-text);padding-right:18px}
		.dvp-ticket-body{background:var(--dvp-bg);border-radius:8px;padding:11px 15px;margin-bottom:10px;line-height:1.9;font-size:13px}
		.dvp-ticket-reply{background:#fff;border:1px solid var(--dvp-line);border-radius:8px;padding:9px 13px;margin:8px 0;line-height:1.9;font-size:13px}
		.dvp-ticket-reply.is-admin{background:#f0f9f4;border-color:#c6e9d8}
		.dvp-ticket-form input[type=text],.dvp-ticket-form textarea{width:100%;border:1px solid var(--dvp-line);border-radius:8px;padding:9px 13px;font-family:inherit;font-size:13px;box-sizing:border-box}
		.dvp-ticket-form input:focus,.dvp-ticket-form textarea:focus{border-color:var(--dvp-green);outline:none}
		.dvp-suggest-form label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;color:var(--dvp-navy)}
		.dvp-suggest-form input[type=text],.dvp-suggest-form input[type=url],.dvp-suggest-form textarea{width:100%;border:1px solid var(--dvp-line);border-radius:8px;padding:9px 13px;font-family:inherit;font-size:13px;box-sizing:border-box;background:#fbfdfc}
		.dvp-suggest-form input:focus,.dvp-suggest-form textarea:focus{border-color:var(--dvp-green);outline:none}
		.dvp-suggest-form input[type=file]{margin-top:2px;font-size:12.5px}
		.dvp-suggest-form p{margin:0 0 14px}
		.dvp-grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
		.dvp-grid2 label{display:block;font-size:12px;font-weight:700;color:#5a5d6e;margin-bottom:4px}
		.dvp-grid2 input{width:100%;border:1px solid var(--dvp-line);border-radius:8px;padding:8px 12px;font-family:inherit;font-size:13px;margin-bottom:11px;box-sizing:border-box}
		.dvp-grid2 input:focus{border-color:var(--dvp-green);outline:none}
		.dvp-logobox{background:var(--dvp-bg);border:1px solid var(--dvp-line);border-radius:10px;padding:11px 15px;margin:6px 0 13px;font-size:13px}
		.dvp-logo-cur{max-height:64px;border:1px solid var(--dvp-line);padding:4px;background:#fff;border-radius:8px;margin:8px 0}
		.dvp-guest .dvp-card{max-width:680px;margin:22px auto 16px}

		/* ── کروم بخش‌های جاسازی‌شده Core (ثبت سفارش دستی/پیشنهاد محصول) — تخت و هم‌رنگ ── */
		.dvp-content h3{color:var(--dvp-navy)}
		.dvp-content .dastyar-card{background:#fff;border:1px solid var(--dvp-line);border-radius:12px;padding:16px 18px;margin:0 0 16px}
		.dvp-content .dastyar-card h4{margin:0 0 12px;color:var(--dvp-navy);font-size:14px}
		.dvp-content .dastyar-grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
		.dvp-content .dastyar-rec-summary{background:#f0f9f4;border:1px solid #c6e9d8;border-radius:8px;padding:9px 13px;margin-bottom:12px;color:#146c4b;font-size:13px}
		.dvp-content .dastyar-btn-green{background:#17a16d!important;color:#fff!important;border-color:#17a16d!important;border-radius:8px!important}
		.dvp-content .dastyar-btn-green:hover{background:#12875c!important;color:#fff!important}
		.dvp-content .dastyar-btn-big{padding:11px 26px!important;font-size:15px!important}
		.dvp-content .dastyar-btn-remove{color:#c0392b!important}
		.dvp-content .dastyar-btn-ghost-g{background:var(--dvp-bg)!important;color:var(--dvp-navy)!important;border:1px solid var(--dvp-line)!important;font-weight:700}
		.dvp-content .dastyar-search-form{display:flex;gap:8px;margin-bottom:14px}
		.dvp-content .dastyar-search-form .input-text{flex:1;border:1px solid var(--dvp-line);border-radius:8px;padding:8px 12px}
		.dvp-content .dastyar-products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px}
		.dvp-content .dastyar-product-card{border:1px solid var(--dvp-line);border-radius:10px;padding:12px;text-align:center;background:#fff;transition:border-color .12s}
		.dvp-content .dastyar-product-card:hover{border-color:var(--dvp-green)}
		.dvp-content .dastyar-product-card img{width:100%;height:110px;object-fit:cover;border-radius:8px;margin-bottom:8px}
		.dvp-content .dastyar-product-name{font-size:12.5px;font-weight:700;min-height:36px;margin-bottom:6px;color:var(--dvp-navy)}
		.dvp-content .dastyar-product-price{color:var(--dvp-green-dark);font-weight:700;margin-bottom:8px}
		.dvp-content .dastyar-var-select{width:100%;margin-bottom:8px;font-size:12px;border:1px solid var(--dvp-line);border-radius:8px}
		.dvp-content .dastyar-add-row{display:flex;gap:6px;justify-content:center}
		.dvp-content .dastyar-qty{width:58px!important;border:1px solid var(--dvp-line);border-radius:8px}
		.dvp-content .dastyar-pagination{margin-top:12px}
		.dvp-content .dastyar-goods-total{background:var(--dvp-bg);border-radius:8px;padding:9px 13px;color:var(--dvp-navy)}
		.dvp-content .dastyar-steps{display:flex;gap:8px;margin:14px 0 16px;flex-wrap:wrap}
		.dvp-content .dastyar-step{flex:1;min-width:120px;background:#fff;border:1px solid var(--dvp-line);border-radius:10px;padding:9px 6px;cursor:pointer;text-align:center;font-family:inherit;transition:border-color .12s;color:#5a5d6e}
		.dvp-content .dastyar-step-num{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:var(--dvp-bg);color:var(--dvp-navy);font-weight:800;margin-bottom:4px}
		.dvp-content .dastyar-step-label{display:block;font-size:11.5px;font-weight:700}
		.dvp-content .dastyar-step.active{border-color:var(--dvp-green);color:var(--dvp-green-dark)}
		.dvp-content .dastyar-step.active .dastyar-step-num{background:var(--dvp-green);color:#fff}
		.dvp-content .dastyar-step.done .dastyar-step-num{background:#d6efe2;color:var(--dvp-green-dark)}
		.dvp-content #dastyar-wizard .dastyar-pane{display:none}
		.dvp-content #dastyar-wizard .dastyar-pane.active{display:block;animation:dvpFade .25s ease}
		@keyframes dvpFade{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}
		.dvp-content .dastyar-step-nav{margin:14px 0 0;display:flex;gap:10px}
		.dvp-content .dastyar-ship-list{display:grid;gap:8px;margin-top:10px}
		.dvp-content .dastyar-ship-option{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--dvp-line);border-radius:8px;padding:9px 13px;cursor:pointer}
		.dvp-content .dastyar-ship-option:hover{border-color:var(--dvp-green)}
		.dvp-content .dastyar-ship-title{flex:1;font-weight:700}
		.dvp-content .dastyar-ship-cost{color:var(--dvp-green-dark);font-weight:700}
		.dvp-content .dastyar-done-box{background:#f0f9f4;border:1px solid #c6e9d8;border-radius:10px;padding:15px;margin-top:12px}
		.dvp-content .dastyar-done-box h4{margin-top:0;color:var(--dvp-green-dark)}
		/* لیست درگاه‌های پرداخت (Dastyar_Payments::gateway_picker_html) — استفاده «ثبت سفارش دستی» */
		.dvp-content .dastyar-gw-list{display:grid;gap:10px}
		.dvp-content .dastyar-gw-form{margin:0!important}
		.dvp-content .dastyar-gw-btn{display:flex;align-items:center;gap:10px;width:100%;background:#fff;border:1px solid var(--dvp-line);border-radius:10px;padding:11px 13px;cursor:pointer;font-size:13.5px;font-weight:700;color:var(--dvp-navy);transition:border-color .12s;font-family:inherit}
		.dvp-content .dastyar-gw-btn:hover{border-color:var(--dvp-green);background:#f4fcf8}
		.dvp-content .dastyar-gw-title{flex:1;text-align:right}
		.dvp-content .dastyar-gw-go{color:var(--dvp-green);font-size:12px;white-space:nowrap}
		.dvp-content .dastyar-gw-empty{text-align:center;color:#a52828}
		.dvp-content .dastyar-alert-inline{background:#fdf0f0;border:1px solid #f0c8c8;color:#a52828;border-radius:8px;padding:9px 12px}
		.dvp-content .dastyar-wallet-inline{display:inline-block;margin:0 0 10px}
		.dvp-content .dastyar-toast{position:fixed;bottom:22px;right:50%;transform:translateX(50%) translateY(16px);background:var(--dvp-navy);color:#fff;padding:11px 20px;border-radius:10px;font-size:13.5px;z-index:100001;opacity:0;transition:.3s;box-shadow:0 8px 22px rgba(36,37,54,.3)}
		.dvp-content .dastyar-toast.show{opacity:1;transform:translateX(50%) translateY(0)}
		.dvp-content .dastyar-toast.error{background:#a52828}
		.dvp-content input[type=text],.dvp-content input[type=tel],.dvp-content input[type=url],.dvp-content input[type=number],.dvp-content input[type=email],.dvp-content textarea,.dvp-content input[type=search]{font-family:inherit}
		.dvp-content .shop_table{width:100%;border-collapse:collapse;font-size:13px}
		.dvp-content .shop_table th{background:var(--dvp-bg);padding:8px 12px;text-align:right;border-bottom:1px solid var(--dvp-line);color:#5a5d6e}
		.dvp-content .shop_table td{padding:8px 12px;border-bottom:1px solid #f0f1f5}

		/* سازگاری با نوار ابزار وردپرس (هدر چسبان) */
		body.admin-bar .dvp-head{top:32px}
		/* ── ریسپانسیو ۹۶۰: حالت اپلیکیشن موبایل ── */
		@media (max-width:960px){
			.dvp-wrap{background:var(--dvp-bg);padding:10px 14px 92px}
			.dvp-shell{grid-template-columns:1fr;gap:12px}
			/* کارت کاربر بالای صفحه (مثل رفرنس) */
			.dvp-head{position:static;margin:0 0 12px;padding:0;border:0;background:transparent}
			.dvp-head-t{display:none}
			.dvp-userbox{width:100%;background:#fff;border:1px solid var(--dvp-line);border-radius:12px;padding:9px 14px}
			.dvp-uname{display:block}
			.dvp-avatar{width:42px;height:42px}
			/* ناوبری پایین اپلیکیشنی (Tab Bar روشن) */
			.dvp-nav{position:fixed;right:0;left:0;bottom:0;top:auto;z-index:90;flex-direction:row;overflow-x:auto;gap:4px;background:#fff;border:0;border-top:1px solid var(--dvp-line);border-radius:0;padding:8px 10px calc(8px + env(safe-area-inset-bottom,0px));box-shadow:0 -6px 18px rgba(36,37,54,.08);scrollbar-width:none}
			.dvp-nav::-webkit-scrollbar{display:none}
			.dvp-nav-link{flex-direction:column;align-items:center;justify-content:center;gap:3px;min-width:62px;min-height:46px;padding:6px 10px;font-size:10px;flex-shrink:0;border-radius:10px;text-align:center;color:#5a5d6e;-webkit-tap-highlight-color:transparent}
			.dvp-nav-link:hover{background:var(--dvp-bg);color:var(--dvp-navy)}
			.dvp-nav-link.dvp-on{background:#ecf9f2;color:var(--dvp-green)}
			.dvp-home,.dvp-logout{margin-top:0;border-top:0;padding-top:6px}
			.dvp-home{border-inline-start:1px solid var(--dvp-line)}
			/* گریدها */
			.dvp-dash-grid{grid-template-columns:1fr;gap:12px}
			.dvp-stats{grid-template-columns:repeat(2,1fr);gap:8px}
			.dvp-quick{grid-template-columns:repeat(2,1fr);gap:8px}
			.dvp-plan-grid{grid-template-columns:1fr}
			.dvp-grid2{grid-template-columns:1fr}
			.dvp-kv tr{display:block;border-bottom:1px solid #f0f1f5;padding:6px 0}
			.dvp-kv th,.dvp-kv td{display:block;width:auto;border-bottom:0;padding:2px 0}
		}
		@media (max-width:782px){
			body.admin-bar .dvp-head{top:46px}
		}
		@media (max-width:560px){
			.dvp-head-t h1{font-size:15px}
			.dvp-card{padding:14px;border-radius:10px}
			.dvp-stat{padding:11px 12px}
			.dvp-stat-vl{font-size:14px}
			.dvp-table{min-width:540px;font-size:12.5px}
			.dvp-wallet-hero{padding:14px 16px}
			.dvp-wh-am{font-size:20px}
			.dvp-notice{font-size:12.5px}
			.dvp-inline-form{flex-direction:column;align-items:stretch}
			.dvp-inline-form input{width:100%;min-width:0}
			.dvp-btn{padding:10px 18px}
			.dvp-btn-sm{padding:7px 13px}
			.dvp-content .dastyar-steps{gap:6px}
			.dvp-content .dastyar-step{flex:1 1 calc(50% - 6px);min-width:calc(50% - 6px);padding:8px 4px}
			.dvp-content .dastyar-products-grid{grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
			.dvp-content .dastyar-grid2{grid-template-columns:1fr}
		}

		/* ============================================================
		 *  v1.10.13 — طرح‌های ظاهری جایگزین پنل (انتخابی از تنظیمات)
		 *  همه فقط با override روی همین کلاس‌های بالا کار می‌کنند؛
		 *  هیچ‌کدام ساختار HTML یا رفتار پنل را تغییر نمی‌دهند.
		 * ============================================================ */

		/* ۱) مینیمال و سبک — بدون کارت/حاشیه، فقط تایپوگرافی و فاصله */
		.dvp-skin-minimal .dvp-card,.dvp-skin-minimal .dvp-stat,.dvp-skin-minimal .dvp-nav,.dvp-skin-minimal .dvp-userbox,.dvp-skin-minimal .dvp-quick-link{background:transparent;border:0;border-radius:0;box-shadow:none}
		.dvp-skin-minimal .dvp-card{border-bottom:1px solid var(--dvp-line);padding:16px 2px;margin-bottom:10px}
		.dvp-skin-minimal .dvp-stats{border-bottom:1px solid var(--dvp-line);padding-bottom:14px}
		.dvp-skin-minimal .dvp-stat{padding:2px}
		.dvp-skin-minimal .dvp-stat-ic{background:transparent!important;color:var(--dvp-muted)!important;width:auto;height:auto}
		.dvp-skin-minimal .dvp-stat-vl{font-size:19px}
		.dvp-skin-minimal .dvp-nav{padding:4px 0}
		.dvp-skin-minimal .dvp-nav-link.dvp-on{background:transparent;color:var(--dvp-green)!important;font-weight:800}
		.dvp-skin-minimal .dvp-promo{background:#fff;border:1px solid var(--dvp-line);color:var(--dvp-text)}
		.dvp-skin-minimal .dvp-promo h4{color:var(--dvp-navy)}
		.dvp-skin-minimal .dvp-promo p{color:var(--dvp-muted)}
		.dvp-skin-minimal .dvp-badge{border-radius:4px;padding:2px 0;background:transparent!important;font-weight:800}

		/* ۲) پررنگ و مدرن — کارت‌های رنگی، آیکون‌های بج‌دار، کارت کیف‌پول به‌عنوان نقطه توجه */
		.dvp-skin-bold .dvp-card,.dvp-skin-bold .dvp-stat,.dvp-skin-bold .dvp-nav,.dvp-skin-bold .dvp-quick-link{border:0;border-radius:16px;background:var(--dvp-bg)}
		.dvp-skin-bold .dvp-stat-ic{border-radius:10px;width:34px;height:34px}
		.dvp-skin-bold .dvp-stats .dvp-stat:nth-child(2){background:var(--dvp-green);border-color:var(--dvp-green)}
		.dvp-skin-bold .dvp-stats .dvp-stat:nth-child(2) .dvp-stat-lb{color:rgba(255,255,255,.8)}
		.dvp-skin-bold .dvp-stats .dvp-stat:nth-child(2) .dvp-stat-vl{color:#fff}
		.dvp-skin-bold .dvp-stats .dvp-stat:nth-child(2) .dvp-stat-ic{background:rgba(255,255,255,.18)!important;color:#fff!important}
		.dvp-skin-bold .dvp-badge{border-radius:20px;padding:5px 13px;font-weight:800}
		.dvp-skin-bold .dvp-nav-link.dvp-on{border-radius:12px}
		.dvp-skin-bold .dvp-btn{border-radius:12px}

		/* ۳) اپلیکیشنی — سایدبار تیره ثابت، محتوا روشن */
		.dvp-skin-dark .dvp-nav{background:var(--dvp-navy);border:0}
		.dvp-skin-dark .dvp-nav-link{color:rgba(255,255,255,.65)}
		.dvp-skin-dark .dvp-nav-link:hover{background:rgba(255,255,255,.08);color:#fff}
		.dvp-skin-dark .dvp-nav-link.dvp-on{background:var(--dvp-green);color:#fff}
		.dvp-skin-dark .dvp-home{border-top-color:rgba(255,255,255,.14);color:#7ce8b0!important}
		.dvp-skin-dark .dvp-home:hover{background:rgba(255,255,255,.08);color:#7ce8b0!important}
		.dvp-skin-dark .dvp-logout{color:#f5a3a3!important}
		.dvp-skin-dark .dvp-logout:hover{background:rgba(255,255,255,.08);color:#f5a3a3!important}

		/* ۴) گرد و دوستانه — گوشه‌های خیلی گرد، پس‌زمینه نعنایی روشن */
		.dvp-skin-soft .dvp-card,.dvp-skin-soft .dvp-nav,.dvp-skin-soft .dvp-userbox,.dvp-skin-soft .dvp-quick-link{border-radius:20px}
		.dvp-skin-soft .dvp-stat{border:0;border-radius:20px;background:#f0faf5;text-align:center}
		.dvp-skin-soft .dvp-stat-ic{border-radius:50%;background:#fff!important;margin:0 auto 6px}
		.dvp-skin-soft .dvp-badge{border-radius:20px;padding:5px 13px}
		.dvp-skin-soft .dvp-btn{border-radius:14px}
		.dvp-skin-soft .dvp-nav-link,.dvp-skin-soft .dvp-nav-link.dvp-on{border-radius:14px}
		.dvp-skin-soft .dvp-promo{border-radius:20px}
		.dvp-skin-soft .dvp-othumb{border-radius:14px}

		/* ۵) فشرده و حرفه‌ای — کم‌فاصله، شبیه پنل‌های اداری/ERP */
		.dvp-skin-dense .dvp-card{padding:13px 15px;border-radius:8px;margin-bottom:10px}
		.dvp-skin-dense .dvp-card h3{font-size:13.5px;margin-bottom:8px}
		.dvp-skin-dense .dvp-stats{gap:8px;margin-bottom:10px}
		.dvp-skin-dense .dvp-stat{padding:9px 11px;border-radius:8px;gap:1px}
		.dvp-skin-dense .dvp-stat-ic{width:24px;height:24px;border-radius:6px}
		.dvp-skin-dense .dvp-stat-lb{font-size:10.5px}
		.dvp-skin-dense .dvp-stat-vl{font-size:13.5px}
		.dvp-skin-dense .dvp-orow{padding:6px 0}
		.dvp-skin-dense .dvp-nav{padding:6px}
		.dvp-skin-dense .dvp-nav-link{padding:6px 9px;font-size:12px}
		.dvp-skin-dense .dvp-badge{border-radius:4px;padding:2px 7px;font-size:10.5px}
		.dvp-skin-dense .dvp-btn{padding:6px 13px;font-size:12px}
		</style>
		<?php
		return ob_get_clean();
	}

	/** اسکریپت کپی با یک کلیک (کد رهگیری / کلید API) — فقط یک‌بار */
	public function js_once() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
<script>
(function(){
	function toast(msg){
		var t=document.createElement('div');
		t.className='dastyar-toast show';
		t.textContent=msg;
		document.body.appendChild(t);
		setTimeout(function(){ t.classList.remove('show'); setTimeout(function(){ t.remove(); },400); },1800);
	}
	document.addEventListener('click',function(e){
		var b=e.target.closest('[data-dvp-copy]');
		if(!b)return;
		var txt=b.getAttribute('data-dvp-copy')||'';
		var done=function(){ toast('✔ کپی شد'); };
		if(navigator.clipboard&&window.isSecureContext){ navigator.clipboard.writeText(txt).then(done); }
		else{ var ta=document.createElement('textarea'); ta.value=txt; document.body.appendChild(ta); ta.select(); try{document.execCommand('copy');done();}catch(x){} ta.remove(); }
	});
})();
</script>
		<?php
	}
}
