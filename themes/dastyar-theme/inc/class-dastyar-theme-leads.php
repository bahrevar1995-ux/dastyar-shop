<?php
/**
 * درخواست‌های مشاوره تلفنی (لید) — v2.4.0
 *
 * جریان: فرم شماره موبایل هیرو (سبک «فرم جذب») ← پاپ‌آپ «نیاز به مشاوره داری؟» ←
 * با تأیید کاربر، شماره این‌جا ذخیره می‌شود و مدیر از سه راه مطلع می‌گردد:
 *  ۱) زیرمنوی «نمایش ← مشاوره‌ها» با حباب شمارش خوانده‌نشده
 *  ۲) اطلاع‌رسانی سبز در پیشخوان
 *  ۳) ایمیل به مدیر سایت
 * ذخیره‌سازی: دو آپشن ساده (dhm_leads + dhm_leads_unread) — بدون جدول تازه.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Theme_Leads {

	const OPT        = 'dhm_leads';
	const OPT_UNREAD = 'dhm_leads_unread';
	const CAP        = 'manage_options';
	const MAX        = 200; // سقف نگهداری — قدیمی‌ترها حذف می‌شوند

	public static function boot() {
		add_action( 'wp_ajax_dth_lead', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_nopriv_dth_lead', array( __CLASS__, 'ajax_save' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/** تعداد درخواست‌های خوانده‌نشده (برای حباب منو و اطلاع‌رسانی) */
	public static function unread() {
		return max( 0, (int) get_option( self::OPT_UNREAD, 0 ) );
	}

	/** نرمال‌سازی موبایل: ارقام فارسی/عربی ← لاتین، فقط رقم، پیش‌شماره ۹۸/۰۹۸ ← ۰۹ */
	public static function normalize_phone( $raw ) {
		$p = strtr( (string) $raw, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		) );
		$p = preg_replace( '/\D+/', '', $p );
		if ( 0 === strpos( $p, '0098' ) ) {
			$p = '0' . substr( $p, 4 );
		} elseif ( 0 === strpos( $p, '98' ) && strlen( $p ) >= 12 ) {
			$p = '0' . substr( $p, 2 );
		}
		return $p;
	}

	/* ------------------------------------------------------------------
	 *  AJAX — ثبت درخواست (مهمان و عضو)
	 * ---------------------------------------------------------------- */

	public static function ajax_save() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'dth_lead' ) ) {
			wp_send_json_error( array( 'msg' => 'نشست نامعتبر است.' ), 403 );
		}
		$phone = self::normalize_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
		if ( strlen( $phone ) < 10 || '09' !== substr( $phone, 0, 2 ) ) {
			wp_send_json_error( array( 'msg' => 'شماره موبایل معتبر نیست.' ), 422 );
		}
		$page  = esc_url_raw( wp_unslash( $_POST['page'] ?? '' ) );
		$leads = get_option( self::OPT, array() );
		if ( ! is_array( $leads ) ) {
			$leads = array();
		}
		// ضدتکرار: همین شماره در ۵ دقیقه اخیر ثبت شده ← موفقیت بی‌صدا (سطر تازه نساز)
		$now = time();
		foreach ( array_slice( $leads, -20 ) as $row ) {
			if ( (string) ( $row['phone'] ?? '' ) === $phone && $now - (int) ( $row['time'] ?? 0 ) < 300 ) {
				wp_send_json_success( array( 'dup' => 1 ) );
			}
		}
		$leads[] = array(
			'id'    => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'ld', true ),
			'phone' => $phone,
			'time'  => $now,
			'page'  => $page,
			'done'  => 0,
		);
		if ( count( $leads ) > self::MAX ) {
			$leads = array_slice( $leads, -1 * self::MAX );
		}
		update_option( self::OPT, array_values( $leads ) );
		update_option( self::OPT_UNREAD, self::unread() + 1 );

		// اطلاع ایمیلی به مدیر (بهترین تلاش — شکست ایمیل، ثبت را خراب نمی‌کند)
		$to = (string) get_option( 'admin_email', '' );
		if ( $to ) {
			wp_mail(
				$to,
				'درخواست مشاوره جدید (دستیار شاپ): ' . $phone,
				"یک بازدیدکننده درخواست مشاوره تلفنی ثبت کرد:\n\nموبایل: {$phone}\nزمان: " . date_i18n( 'Y/m/d H:i' ) . "\nصفحه: " . ( $page ?: '—' ) . "\n\nمدیریت درخواست‌ها: " . admin_url( 'themes.php?page=dastyar-theme-leads' )
			);
		}
		do_action( 'dth_lead_saved', $phone, $page );
		wp_send_json_success( array( 'ok' => 1 ) );
	}

	/* ------------------------------------------------------------------
	 *  پیشخوان — منو + اطلاع‌رسانی + صفحه مدیریت
	 * ---------------------------------------------------------------- */

	public static function menu() {
		if ( ! function_exists( 'add_theme_page' ) ) {
			return;
		}
		$n     = self::unread();
		$title = 'مشاوره‌ها';
		if ( $n ) {
			$title .= ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>';
		}
		add_theme_page( 'درخواست‌های مشاوره تلفنی', $title, self::CAP, 'dastyar-theme-leads', array( __CLASS__, 'render' ) );
	}

	/** اطلاع‌رسانی سبز در پیشخوان وقتی درخواست خوانده‌نشده هست */
	public static function notice() {
		$n = self::unread();
		if ( ! $n || ! current_user_can( self::CAP ) ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p><strong>%s درخواست مشاوره تلفنی جدید</strong> ثبت شده است — <a href="%s">مشاهده و تماس</a></p></div>',
			esc_html( Dastyar_Theme_Settings::fa_num( $n ) ),
			esc_url( admin_url( 'themes.php?page=dastyar-theme-leads' ) )
		);
	}

	/** اکشن‌های صفحه (تماس‌گرفتم / حذف) — فقط روی صفحه خودمان، با نانس */
	public static function maybe_handle() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'dastyar-theme-leads' !== $page ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) || empty( $_POST['dth_lead_act'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dth_lead_mod' ) ) {
			return;
		}
		$act   = sanitize_key( wp_unslash( $_POST['dth_lead_act'] ) );
		$id    = sanitize_text_field( wp_unslash( $_POST['lead_id'] ?? '' ) );
		$leads = get_option( self::OPT, array() );
		if ( is_array( $leads ) ) {
			foreach ( $leads as $i => $row ) {
				if ( (string) ( $row['id'] ?? '' ) !== $id ) {
					continue;
				}
				if ( 'done' === $act ) {
					$leads[ $i ]['done'] = empty( $row['done'] ) ? 1 : 0;
				} elseif ( 'del' === $act ) {
					unset( $leads[ $i ] );
				}
				break;
			}
			update_option( self::OPT, array_values( $leads ) );
		}
		wp_safe_redirect( admin_url( 'themes.php?page=dastyar-theme-leads&dth_ledone=1' ) );
		exit;
	}

	/** صفحه مدیریت درخواست‌ها — با بازدید، حباب خوانده‌نشده صفر می‌شود */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		update_option( self::OPT_UNREAD, 0 );
		$leads = get_option( self::OPT, array() );
		if ( ! is_array( $leads ) ) {
			$leads = array();
		}
		$leads = array_reverse( $leads ); // جدیدترین بالا

		echo '<div class="wrap dhm-admin dhm-leads-admin" dir="rtl">';
		echo '<style>
			.dhm-leads-admin .dhm-card{background:#fff;border:1.5px solid #e3ebe7;border-radius:14px;padding:20px 22px;margin:18px 0;max-width:980px}
			.dhm-leads-admin h1{font-size:21px;font-weight:800;color:#242536}
			.dhm-leads-admin .dhm-note{background:#e9f6ee;border:1.5px solid #bfe6d2;border-radius:12px;padding:12px 16px;color:#166b47;font-size:13px;margin:14px 0}
			.dhm-leads-admin table.widefat{max-width:980px;border-radius:12px;overflow:hidden}
			.dhm-leads-admin .dhm-lphone{font-weight:800;letter-spacing:.5px}
			.dhm-leads-admin .dhm-st{display:inline-block;border-radius:999px;padding:2px 12px;font-size:11.5px;font-weight:800}
			.dhm-leads-admin .dhm-st-new{background:#fdf4d8;color:#8a6600}
			.dhm-leads-admin .dhm-st-done{background:#e9f6ee;color:#166b47}
			.dhm-leads-admin .button{border-radius:8px!important}
			.dhm-leads-admin form{display:inline}
			.dhm-leads-admin .dhm-empty{background:#fff;border:1.5px dashed #d5dae6;border-radius:14px;padding:36px;text-align:center;color:#6b6e84;max-width:940px}
		</style>';

		echo '<h1>درخواست‌های مشاوره تلفنی</h1>';
		if ( ! empty( $_GET['dth_ledone'] ) ) {
			echo '<div class="notice notice-success" style="max-width:980px"><p>انجام شد.</p></div>';
		}
		echo '<div class="dhm-note" style="max-width:948px">وقتی بازدیدکننده در باکس موبایل صفحه اصلی شماره‌اش را وارد کند، پاپ‌آپ «نیاز به مشاوره داری؟» باز می‌شود؛ با تأیید او، شماره این‌جا ثبت می‌شود تا باهاش تماس بگیری. متن‌های پاپ‌آپ از «<a href="' . esc_url( admin_url( 'themes.php?page=dastyar-theme#dth-leads' ) ) . '">تنظیمات دستیار ← مشاوره تلفنی</a>» قابل تغییر است.</div>';

		if ( ! $leads ) {
			echo '<div class="dhm-empty"><strong>هنوز درخواستی ثبت نشده است.</strong><br>به‌محض این‌که بازدیدکننده‌ای درخواست مشاوره بدهد، همین‌جا (و با ایمیل به مدیر) اطلاع‌رسانی می‌شود.</div></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr><th style="width:44px">#</th><th>شماره موبایل</th><th>زمان ثبت</th><th>صفحه</th><th>وضعیت</th><th style="width:220px">اقدام</th></tr></thead><tbody>';
		$i = 0;
		foreach ( $leads as $row ) {
			$i++;
			$id   = (string) ( $row['id'] ?? '' );
			$done = ! empty( $row['done'] );
			echo '<tr>';
			echo '<td>' . esc_html( Dastyar_Theme_Settings::fa_num( $i ) ) . '</td>';
			echo '<td class="dhm-lphone" dir="ltr" style="text-align:right">' . esc_html( (string) ( $row['phone'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( Dastyar_Theme_Settings::fa_num( date_i18n( 'Y/m/d H:i', (int) ( $row['time'] ?? 0 ) ) ) ) . '</td>';
			echo '<td style="font-size:11.5px;color:#6b6e84;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" dir="ltr">' . esc_html( (string) ( $row['page'] ?? '—' ) ) . '</td>';
			echo '<td><span class="dhm-st ' . ( $done ? 'dhm-st-done' : 'dhm-st-new' ) . '">' . ( $done ? 'تماس گرفته شد' : 'در انتظار تماس' ) . '</span></td>';
			echo '<td><form method="post">';
			wp_nonce_field( 'dth_lead_mod' );
			echo '<input type="hidden" name="lead_id" value="' . esc_attr( $id ) . '">';
			echo '<button class="button button-primary" name="dth_lead_act" value="done">' . ( $done ? 'برگردان به انتظار' : 'تماس گرفتم' ) . '</button> ';
			echo '<button class="button" name="dth_lead_act" value="del" onclick="return confirm(\'این درخواست حذف شود؟\')">حذف</button>';
			echo '</form></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p style="color:#6b6e84;font-size:12px">حداکثر ' . esc_html( Dastyar_Theme_Settings::fa_num( self::MAX ) ) . ' درخواست آخر نگهداری می‌شود.</p>';
		echo '</div>';
	}
}
