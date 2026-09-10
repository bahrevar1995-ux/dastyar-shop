<?php
/**
 * سامانه پیامکی مرکز (v1.6.0) — دو درگاه قابل انتخاب (v1.9.8):
 *
 * ۱) ملی پیامک / melipayamak.com (پیش‌فرض v1.9.8) — خط خدماتی اشتراکی (الگو/متن پیش‌فرض):
 *   POST https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber
 *   (متد مستندات «SendByBaseNumber» در REST با نام مسیر «BaseServiceNumber» سوار است — v1.10.6 رفع HTTP 404)
 *   Form: username, password, text=«مقدار۱;مقدار۲» (به ترتیب سطرهای نگاشت)، to=«0912…»، bodyId=کد الگوی عددی
 *
 * ۲) آی‌پی‌پنل / ippanel.com — پیامک الگو (Pattern):
 *   POST https://api.ippanel.com/v1/messages/patterns/send
 *   Header: Authorization: AccessKey {api_key}
 *   Body:   { "code": "…", "sender": "98…", "recipient": "98912…", "variable": { "key": "value" } }
 *
 * رویدادهای دارای اعلان: تأیید/رد عضویت فروشنده، نتیجه درخواست مرجوعی (RMA)،
 * هشدار اتمام شارژ کیف پول، کد ورود یک‌بارمصرف (OTP)،
 * و (v1.9.6) «کد رهگیری به مشتری» — با فروش ثبت کد در سفارش، پیامک شامل کد رهگیری
 * + نام فروشگاه فروشنده (متغیر shop) برای موبایل بیلینگ سفارش می‌رود.
 * موبایل گیرنده از همان متای بیلینگ استاندارد ووکامرس (billing_phone) خوانده می‌شود
 * — چرخ دوباره اختراع نمی‌شود؛ ثبت‌نام Core خودش آن را ذخیره می‌کند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Sms {

	const OPTION        = 'dastyar_sms_settings';
	const EVENTS_OPTION = 'dastyar_sms_events'; // v1.9.7 — حلقه آخرین رویدادها/خطاها برای نمایش در تنظیمات
	const EVENTS_CAP    = 10;
	const API           = 'https://api.ippanel.com/v1/messages/patterns/send';
	const MELLI_API     = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber'; // v1.10.6 — مسیر درست REST ملی پیامک (قبلاً اشتباه SendByBaseNumber بود ← HTTP 404)

	/** پیش‌فرض‌ها */
	public static function defaults() {
		return array(
			'enabled'              => 'no',   // سوئیچ اصلی
			'provider'             => 'melipayamak', // v1.9.8 — درگاه: melipayamak (پیش‌فرض) | ippanel
			'mp_username'          => '',     // v1.9.8 — نام کاربری ورود به melipayamak.com
			'mp_password'          => '',     // v1.9.8 — رمز عبور ورود به melipayamak.com
			'api_key'              => '',     // AccessKey از پنل ippanel
			'sender'               => '',     // خط اختصاصی (مثل 3000505) — برای الگو «+983000505»
			// کدهای الگو (از پنل ippanel ← ارسال الگو) برای هر رویداد
			'pattern_approved'     => '',
			'pattern_rejected'     => '',
			'pattern_rma'          => '',
			'pattern_wallet_low'   => '',
			'pattern_otp'          => '',
			'pattern_tracking'     => '',     // v1.9.6 — کد رهگیری به مشتری
			// متغیرهای هر الگو — نام متغیرهای تعریف‌شده در پنل آمریکا (هر سطر: نام=منبع)
			'vars_approved'        => 'name=%name%',
			'vars_rejected'        => 'name=%name%',
			'vars_rma'             => "name=%name%\nrma=%rma_id%\nstatus=%status%",
			'vars_wallet_low'      => "name=%name%\nbalance=%balance%",
			'vars_otp'             => 'code=%code%',
			'vars_tracking'        => "name=%name%\ntrack=%track%\nshop=%shop%", // v1.9.6
			// هشدار اتمام شارژ
			'low_balance_enabled'  => 'yes',
			'low_balance_threshold'=> 2000000,
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * v1.9.8 — درگاه فعّال. فالبک خودکار: اگر اعتبارِ درگاهِ انتخاب‌شده خالی است ولی اعتبارِ
	 * درگاه دیگر پر است، همان درگاه دیگر استفاده می‌شود (سازگاری با تنظیمات قدیمی نسخه‌ها).
	 */
	public static function provider() {
		$s = self::all();
		$p = (string) ( $s['provider'] ?? 'melipayamak' );
		if ( ! in_array( $p, array( 'melipayamak', 'ippanel' ), true ) ) {
			$p = 'melipayamak';
		}
		$mp_ok = '' !== trim( (string) ( $s['mp_username'] ?? '' ) ) && '' !== trim( (string) ( $s['mp_password'] ?? '' ) );
		$ip_ok = '' !== trim( (string) ( $s['api_key'] ?? '' ) );
		if ( 'melipayamak' === $p && ! $mp_ok && $ip_ok ) {
			return 'ippanel';
		}
		if ( 'ippanel' === $p && ! $ip_ok && $mp_ok ) {
			return 'melipayamak';
		}
		return $p;
	}

	/** سرویس آماده است؟ (فعال + اعتبار درگاهِ فعّال) */
	public static function ready() {
		$s = self::all();
		if ( 'yes' !== (string) $s['enabled'] ) {
			return false;
		}
		if ( 'melipayamak' === self::provider() ) {
			return '' !== trim( (string) $s['mp_username'] ) && '' !== trim( (string) $s['mp_password'] );
		}
		return '' !== trim( (string) $s['api_key'] );
	}

	/** اتصال به رویدادها */
	public function __construct() {
		add_action( 'dastyar_vendor_approved', array( $this, 'on_vendor_approved' ), 20 );
		add_action( 'dastyar_vendor_rejected', array( $this, 'on_vendor_rejected' ), 20 );
		add_action( 'dastyar_rma_status_changed', array( $this, 'on_rma_status' ), 20, 3 );
		add_action( 'dastyar_wallet_adjusted', array( $this, 'on_wallet_adjusted' ), 20, 4 );
		add_action( 'dastyar_tracking_updated', array( $this, 'on_tracking_updated' ), 20, 3 ); // v1.9.6 — کد رهگیری به مشتری
	}

	/* ------------------------------------------------------------------
	 * نرمال‌سازی شماره موبایل
	 * ---------------------------------------------------------------- */

	/** «0912…» / «0098912…» / «+98912…» → «0912…» (فرمت محلی) */
	public static function normalize_local( $phone ) {
		$d = preg_replace( '/\D+/', '', (string) $phone );
		if ( 0 === strpos( $d, '0098' ) ) {
			$d = '0' . substr( $d, 4 );
		} elseif ( 0 === strpos( $d, '98' ) && strlen( $d ) >= 12 ) {
			$d = '0' . substr( $d, 2 );
		} elseif ( 10 === strlen( $d ) && '9' === substr( $d, 0, 1 ) ) {
			$d = '0' . $d;
		}
		return $d;
	}

	/** «0912…» → «98912…» (فرمت API ippanel) */
	public static function normalize_msisdn( $phone ) {
		$local = self::normalize_local( $phone );
		if ( 11 === strlen( $local ) && '09' === substr( $local, 0, 2 ) ) {
			return '98' . substr( $local, 1 );
		}
		return $local;
	}

	/** موبایل کاربر — متا استاندارد بیلینگ ووکامرس/ثبت‌نام دستیار */
	public static function user_mobile( $uid ) {
		$p = get_user_meta( (int) $uid, 'billing_phone', true );
		if ( ! $p ) {
			$u = get_userdata( (int) $uid );
			$p = $u && class_exists( 'WooCommerce' ) && method_exists( $u, 'get_billing_phone' ) ? $u->get_billing_phone() : '';
		}
		return self::normalize_local( $p );
	}

	/* ------------------------------------------------------------------
	 * ارسال الگو
	 * ---------------------------------------------------------------- */

	/**
	 * ارسال پیامک الگویی.
	 * @param string $mobile   شماره موبایل (هر فرمتی — نرمال می‌شود)
	 * @param string $pattern  کد الگو از پنل ippanel
	 * @param array  $context  متادیتا برای نگاشت نام متغیرها (name/rma_id/status/balance/code …)
	 * @param string $vars_map نگاشت «نام‌متغیر=%منبع%» (هر سطر یک مورد)
	 * @return true|WP_Error
	 */
	public static function send_pattern( $mobile, $pattern, array $context = array(), $vars_map = '' ) {
		if ( ! self::ready() ) {
			return new WP_Error( 'dastyar_sms_disabled', 'سرویس پیامک فعال نیست.' );
		}
		$pattern = trim( (string) $pattern );
		$msisdn  = self::normalize_msisdn( $mobile );
		if ( '' === $pattern || '' === $msisdn ) {
			return new WP_Error( 'dastyar_sms_bad_target', 'الگو یا گیرنده مشخص نیست.' );
		}

		// ساخت آرایه variable از نگاشت «نام=%منبع%» (هر سطر یک نام متغیر)
		$vars = array();
		foreach ( preg_split( '/\r?\n/', (string) $vars_map ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			// هم «name=%name%» و هم فقط «name» معتبر است
			$src_key = $line;
			if ( false !== strpos( $line, '=' ) ) {
				list( $name, $src_key ) = array_map( 'trim', explode( '=', $line, 2 ) );
				$src_key = trim( $src_key, '%' );
			} else {
				$name = $line;
			}
			$vars[ $name ] = (string) ( $context[ $src_key ] ?? '' );
		}

		// v1.9.8 — درگاه ملی پیامک (خط خدماتی اشتراکی): مقادیر به ترتیب سطرهای نگاشت می‌روند
		if ( 'melipayamak' === self::provider() ) {
			return self::send_melipayamak( $pattern, self::normalize_local( $mobile ), array_values( $vars ) );
		}

		$body = array(
			'code'      => $pattern,
			'sender'    => (string) self::get( 'sender', '+983000505' ),
			'recipient' => $msisdn,
			'variable'  => $vars,
		);
		$body = apply_filters( 'dastyar_sms_body', $body, $context );

		$res = wp_remote_post( self::API, array(
			'timeout' => 12,
			'headers' => array(
				'Authorization' => 'AccessKey ' . (string) self::get( 'api_key' ),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $res ) ) {
			self::log_error( sprintf( 'SMS send failed [%s→%s]: %s', $pattern, $msisdn, $res->get_error_message() ) );
			self::record( 'fail', $pattern, $msisdn, $res->get_error_message() );
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		if ( $code >= 400 ) {
			self::log_error( sprintf( 'SMS API error [%s→%s] HTTP %d: %s', $pattern, $msisdn, $code, mb_substr( $raw, 0, 200 ) ) );
			$msg = self::api_fail_text( $raw, $code ); // v1.10.6 — پاسخ واقعی سرور هم در «آخرین پیامک‌ها» دیده می‌شود
			self::record( 'fail', $pattern, $msisdn, $msg );
			return new WP_Error( 'dastyar_sms_api', $msg );
		}
		self::record( 'ok', $pattern, $msisdn );
		return true;
	}

	/**
	 * v1.9.8 (بازخورد کاربر) — ارسال از درگاه ملی پیامک (melipayamak.com) — متد SendByBaseNumber
	 * متن: مقدارهای متغیرها به ترتیب سطرهای «نگاشت متغیرها»، جداشده با «;» — مقدار سطر اول
	 * به‌جای {0} الگو، سطر دوم به‌جای {1} و… می‌نشیند.
	 *
	 * @param string $pattern      کد متن (bodyId) عددی تأییدشده در پنل
	 * @param string $mobile_local گیرنده با فرمت محلی «0912…»
	 * @param array  $args         مقدارهای متغیرها به ترتیب
	 * @return true|WP_Error
	 */
	protected static function send_melipayamak( $pattern, $mobile_local, array $args ) {
		$body_id = (int) preg_replace( '/\D+/', '', (string) $pattern );
		if ( $body_id <= 0 ) {
			return new WP_Error( 'dastyar_sms_bad_body', 'کد متن (bodyId) ملی پیامک باید عددی باشد — «کد متن» تأییدشده در پنل را وارد کنید.' );
		}
		$res = wp_remote_post( self::MELLI_API, array(
			'timeout' => 12,
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array(
				'username' => (string) self::get( 'mp_username' ),
				'password' => (string) self::get( 'mp_password' ),
				'text'     => implode( ';', array_map( 'strval', $args ) ),
				'to'       => $mobile_local,
				'bodyId'   => (string) $body_id,
			),
		) );
		if ( is_wp_error( $res ) ) {
			self::log_error( sprintf( 'SMS send failed (melipayamak) [%d→%s]: %s', $body_id, $mobile_local, $res->get_error_message() ) );
			self::record( 'fail', (string) $body_id, $mobile_local, $res->get_error_message() );
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		if ( $code >= 400 ) {
			$msg = self::api_fail_text( $raw, $code ); // v1.10.6 — گزیده پاسخ واقعی در گزارش خطا
			self::log_error( sprintf( 'SMS API error (melipayamak) [%d→%s] HTTP %d: %s', $body_id, $mobile_local, $code, mb_substr( $raw, 0, 200 ) ) );
			self::record( 'fail', (string) $body_id, $mobile_local, $msg );
			return new WP_Error( 'dastyar_sms_api', $msg );
		}
		$json = json_decode( $raw, true );
		$val  = is_array( $json ) ? trim( (string) ( $json['Value'] ?? '' ) ) : '';
		// RecId بلند (عدد ۶ رقمی و بیشتر) = ارسال موفق
		if ( '' !== $val && (bool) preg_match( '/^\d{6,}$/', $val ) ) {
			self::record( 'ok', (string) $body_id, $mobile_local );
			return true;
		}
		$msg = self::melli_error_message( $val );
		self::log_error( sprintf( 'SMS melipayamak error [%d→%s] Value=%s: %s', $body_id, $mobile_local, $val, mb_substr( $raw, 0, 200 ) ) );
		self::record( 'fail', (string) $body_id, $mobile_local, $msg );
		return new WP_Error( 'dastyar_sms_melli', $msg );
	}

	/** پیام فارسیِ دوستانه برای کدهای بازگشتی وب‌سرویس ملی پیامک (مستندات رسمی melipayamak.com) */
	public static function melli_error_message( $code ) {
		$code = trim( (string) $code );
		$map  = array(
			'0'  => 'نام کاربری یا رمز عبور ملی پیامک اشتباه است.',
			'2'  => 'اعتبار (شارژ) پنل ملی پیامک کافی نیست.',
			'6'  => 'سامانه ملی پیامک در حال به‌روزرسانی است — کمی بعد دوباره تلاش کنید.',
			'7'  => 'متن حاوی کلمه فیلترشده است — متن الگو را بازبینی کنید.',
			'10' => 'کاربر ملی پیامک فعال نیست.',
			'11' => 'ارسال نشده — با پشتیبانی ملی پیامک تماس بگیرید.',
			'12' => 'مدارک کاربر در پنل ملی پیامک کامل نیست.',
			'-1' => 'دسترسی وب‌سرویس در پنل ملی پیامک غیرفعال است — با پشتیبانی تماس بگیرید.',
			'-2' => 'کد متن (bodyId) در درخواست درج نشده است.',
			'-4' => 'خط ارسالی تعریف نشده است — با پشتیبانی ملی پیامک تماس بگیرید.',
			'-5' => 'کد متن (bodyId) صحیح نیست یا هنوز توسط ملی پیامک تأیید نشده است.',
			'-6' => 'تعداد/ترتیب مقدارها با متغیرهای الگو ({0}،{1}،…) هم‌خوانی ندارد — ترتیب سطرهای «نگاشت متغیرها» را چک کنید.',
			'-9' => 'خط ارسالی تعریف نشده است — با پشتیبانی ملی پیامک تماس بگیرید.',
		);
		if ( isset( $map[ $code ] ) ) {
			return $map[ $code ];
		}
		return '' !== $code
			? sprintf( 'ارسال ناموفق بود (پاسخ ملی پیامک: %s)', $code )
			: 'ارسال ناموفق بود (پاسخ نامعتبر از ملی پیامک).';
	}

	/** خطاها در لاگ همان رجیستری لاگ ووکامرس خود کانکتور/هسته (در صورت وجود فایل dastyar debug log) */
	protected static function log_error( $msg ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( 'error', $msg, array( 'source' => 'dastyar-sms' ) );
		}
	}

	/**
	 * v1.10.6 — متن خطای قابل‌تشخیص از پاسخ HTTP ناموفق درگاه پیامک:
	 * علاوه بر کد وضعیت، گزیده پاسخ واقعی سرور هم می‌آید (تا در «آخرین پیامک‌ها»
	 * علت دقیق دیده شود نه فقط عدد HTTP) + راهنمای مخصوص ۴۰۴.
	 */
	protected static function api_fail_text( $raw, $code ) {
		$msg = sprintf( 'خطای API پیامک (HTTP %d)', (int) $code );
		if ( 404 === (int) $code ) {
			$msg .= ' — (کد الگو/متن یا آدرس سرویس در حساب شما پیدا نشد؛ تنظیمات درگاه و کد هر رویداد را با پنل پیامک‌تان یکی چک کنید)';
		}
		$snip = trim( preg_replace( '/\s+/u', ' ', (string) $raw ) );
		if ( function_exists( 'mb_substr' ) ) {
			$snip = mb_substr( $snip, 0, 140 );
		} else {
			$snip = substr( $snip, 0, 140 );
		}
		if ( '' !== $snip ) {
			$msg .= ' | پاسخ سرور: ' . $snip;
		}
		return $msg;
	}

	/* ------------------------------------------------------------------
	 *  v1.9.7 — حلقه رویدادهای پیامکی (برای «آخرین پیامک‌ها» در صفحه تنظیمات)
	 * ---------------------------------------------------------------- */

	/** لیست آخرین رویدادها (جدیدترین اول) */
	public static function events_log() {
		$log = get_option( self::EVENTS_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/** موبایل برای نمایش — ماسک جزئی (0912***4455) */
	public static function mask_mobile( $mobile ) {
		$d = preg_replace( '/\D+/', '', (string) $mobile );
		if ( strlen( $d ) < 8 ) {
			return $d;
		}
		return substr( $d, 0, 4 ) . '***' . substr( $d, -4 );
	}

	/**
	 * ثبت یک رویداد (حداکثر EVENTS_CAP مورد آخر).
	 * @param string $kind   ok | fail | skip
	 * @param string $event  کد رویداد/الگو
	 * @param string $target موبایل/کاربر هدف
	 * @param string $msg    توضیح (خطا یا دلیل رد)
	 */
	protected static function record( $kind, $event, $target = '', $msg = '' ) {
		$log   = self::events_log();
		$log[] = array(
			't'      => function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ),
			'kind'   => (string) $kind,
			'event'  => (string) $event,
			'target' => self::mask_mobile( $target ),
			'msg'    => (string) $msg,
		);
		if ( count( $log ) > self::EVENTS_CAP ) {
			$log = array_slice( $log, - self::EVENTS_CAP );
		}
		update_option( self::EVENTS_OPTION, $log, false );
	}

	/* ------------------------------------------------------------------
	 * رویدادها
	 * ---------------------------------------------------------------- */

	/** تأیید عضویت فروشنده */
	public function on_vendor_approved( $uid, $user = null ) {
		$this->notify_user(
			$uid,
			'approved',
			array( 'name' => $user ? $user->display_name : self::display_name( $uid ) )
		);
	}

	/** رد درخواست فروشندگی */
	public function on_vendor_rejected( $uid, $user = null ) {
		$this->notify_user(
			$uid,
			'rejected',
			array( 'name' => $user ? $user->display_name : self::display_name( $uid ) )
		);
	}

	/**
	 * تغییر وضعیت RMA در مرکز (v1.6.0 — اکشن در Dastyar_Rma::set_status/finalize صدا زده می‌شود)
	 * فقط برای وضعیت‌های «نتیجه‌دار» پیامک می‌رود: تأییدها و نتایج نهایی + رد.
	 */
	public function on_rma_status( $rma_id, $old, $new ) {
		if ( ! class_exists( 'Dastyar_Rma' ) ) {
			return;
		}
		$notify_map = array( 'approved', 'await_item', 'item_received', 'cancelled_refunded', 'exchanged', 'rejected' );
		if ( ! in_array( $new, $notify_map, true ) ) {
			return;
		}
		$vendor_id = (int) get_post_meta( $rma_id, defined( 'Dastyar_Rma::M_VENDOR' ) ? Dastyar_Rma::M_VENDOR : '_dastyar_vendor', true );
		if ( ! $vendor_id ) {
			return;
		}
		$is_rejected = 'rejected' === $new;
		$label       = method_exists( 'Dastyar_Rma', 'status_label' ) ? Dastyar_Rma::status_label( $new ) : $new;
		$credit      = (float) get_post_meta( $rma_id, defined( 'Dastyar_Rma::M_CREDIT' ) ? Dastyar_Rma::M_CREDIT : '_dastyar_credit', true );

		$this->notify_user( $vendor_id, 'rma', array(
			'name'    => self::display_name( $vendor_id ),
			'rma_id'  => (string) $rma_id,
			'status'  => (string) $label,
			'result'  => $is_rejected ? 'رد شده' : 'تأیید شده',
			'credit'  => $credit > 0 && function_exists( 'wc_price' ) ? wp_strip_all_tags( html_entity_decode( wc_price( $credit ) ) ) : '',
		) );
	}

	/** کسر از کیف پول → اگر موجودی به سقف رسید، هشدار «شارژ رو به اتمام» (حداکثر روزی یک‌بار) */
	public function on_wallet_adjusted( $uid, $type, $amount, $new ) {
		if ( 'debit' !== $type || 'yes' !== (string) self::get( 'low_balance_enabled', 'yes' ) ) {
			return;
		}
		$threshold = (float) self::get( 'low_balance_threshold', 2000000 );
		if ( $threshold <= 0 || $new >= $threshold ) {
			return;
		}
		// ضداسپم: روزی حداکثر یک هشدار
		if ( get_transient( 'dastyar_sms_lowbal_' . (int) $uid ) ) {
			return;
		}
		$ok = $this->notify_user( $uid, 'wallet_low', array(
			'name'    => self::display_name( $uid ),
			'balance' => function_exists( 'wc_price' ) ? wp_strip_all_tags( html_entity_decode( wc_price( $new ) ) ) : (string) $new,
		) );
		if ( true === $ok ) {
			set_transient( 'dastyar_sms_lowbal_' . (int) $uid, 1, DAY_IN_SECONDS );
		}
	}

	/**
	 * v1.9.6 (بازخورد کاربر) — ثبت/تغییر کد رهگیری ← پیامک به مشتری
	 * متن شامل کد رهگیری است و نام فروشگاه فروشنده (متغیر shop) برای انتهای متن الگو پاس داده می‌شود.
	 * گیرنده: موبایل بیلینگ «سفارش» (مشتری نهایی فروشنده) — خود اکشن فقط هنگام «تغییر» کد صدا زده می‌شود.
	 */
	public function on_tracking_updated( $order_id, $code, $carrier = '' ) {
		$code = trim( (string) $code );
		if ( '' === $code || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( (int) $order_id );
		if ( ! $order || ! is_object( $order ) ) {
			return;
		}

		// نام فروشگاه فروشنده — متای ثبت‌نام؛ فالبک: نام نمایشی فروشنده، سپس نام سایت مرکز
		$vid  = (int) $order->get_meta( '_dastyar_vendor_id' );
		$shop = '';
		if ( $vid ) {
			$shop = trim( (string) get_user_meta( $vid, '_dastyar_shop_name', true ) );
			if ( '' === $shop ) {
				$shop = (string) self::display_name( $vid );
			}
		}
		if ( '' === $shop ) {
			$shop = (string) get_option( 'blogname', 'دستیار شاپ' );
		}

		// نام مشتری (بیلینگ سفارش)
		$name = '';
		if ( method_exists( $order, 'get_billing_first_name' ) ) {
			$name = trim( (string) $order->get_billing_first_name() );
		}
		if ( '' === $name && method_exists( $order, 'get_address' ) ) {
			$addr = (array) $order->get_address( 'billing' );
			$name = trim( (string) ( $addr['first_name'] ?? '' ) );
		}

		$ctx = array(
			'name'    => '' !== $name ? $name : 'مشتری',
			'track'   => $code,
			'carrier' => (string) $carrier,
			'shop'    => $shop,
			'order'   => method_exists( $order, 'get_order_number' ) ? (string) $order->get_order_number() : (string) $order_id,
		);

		$sent = $this->notify_order( $order, 'tracking', $ctx );
		if ( true === $sent && method_exists( $order, 'add_order_note' ) ) {
			$mobile = method_exists( $order, 'get_billing_phone' ) ? self::normalize_local( (string) $order->get_billing_phone() ) : '';
			$order->add_order_note( 'پیامک کد رهگیری برای مشتری ارسال شد (' . $mobile . '): ' . $code );
		} elseif ( is_wp_error( $sent ) && method_exists( $order, 'add_order_note' ) ) {
			$order->add_order_note( 'ارسال پیامک کد رهگیری ناموفق بود: ' . $sent->get_error_message() );
		}
	}

	/* ------------------------------------------------------------------
	 * کمکی‌ها
	 * ---------------------------------------------------------------- */

	/** ارسال پیامک یک رویداد به یک کاربر (با احترام به سوئیچ و الگوی آن رویداد) */
	protected function notify_user( $uid, $event, array $context ) {
		if ( ! self::ready() ) {
			return false;
		}
		$pattern = (string) self::get( 'pattern_' . $event, '' );
		if ( '' === trim( $pattern ) ) {
			self::record( 'skip', $event, (string) $uid, 'برای این رویداد کد الگو وارد نشده است — ردیف مربوطه در سامانه پیامکی خالی است.' );
			return false;
		}
		$mobile = self::user_mobile( $uid );
		if ( '' === $mobile ) {
			self::record( 'skip', $event, (string) $uid, 'موبایل گیرنده (متای billing_phone) پیدا نشد — راه‌حل: پیشخوان ← کاربران ← ویرایش این کاربر ← موبایلِ بخش «صورتحساب» را ثبت کنید.' );
			return false;
		}
		$context = apply_filters( 'dastyar_sms_event_context', $context, $event, $uid );
		return self::send_pattern( $mobile, $pattern, $context, (string) self::get( 'vars_' . $event, '' ) );
	}

	/** v1.9.6 — ارسال پیامک یک رویداد به «مشتری سفارش» (گیرنده: موبایل بیلینگ سفارش، نه کاربر وردپرس) */
	protected function notify_order( $order, $event, array $context ) {
		if ( ! self::ready() ) {
			return false;
		}
		$event_label = $event . ' (سفارش)';
		$pattern     = (string) self::get( 'pattern_' . $event, '' );
		if ( '' === trim( $pattern ) ) {
			self::record( 'skip', $event_label, '', 'برای این رویداد کد الگو وارد نشده است — ردیف مربوطه در سامانه پیامکی خالی است.' );
			return false;
		}
		$mobile = method_exists( $order, 'get_billing_phone' ) ? self::normalize_local( (string) $order->get_billing_phone() ) : '';
		if ( '' === $mobile ) {
			self::record( 'skip', $event_label, '', 'موبایل بیلینگ سفارش خالی است.' );
			return false;
		}
		$context = apply_filters( 'dastyar_sms_event_context', $context, $event, 0 );
		return self::send_pattern( $mobile, $pattern, $context, (string) self::get( 'vars_' . $event, '' ) );
	}

	protected static function display_name( $uid ) {
		$u = get_userdata( (int) $uid );
		return $u ? $u->display_name : 'کاربر';
	}
}
