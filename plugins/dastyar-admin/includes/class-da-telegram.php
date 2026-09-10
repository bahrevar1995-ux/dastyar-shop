<?php
/**
 * اتصال تلگرام برای اعلان‌های مهم کنسول — بدون کتابخانه خارجی، فقط Bot API استاندارد
 * (https://api.telegram.org/bot{token}/sendMessage). سه رویداد:
 *   ۱) سفارش جدید از فروشنده (وقتی سفارش راه‌دور ساخته می‌شود)
 *   ۲) رسید کارت‌به‌کارت جدید (وقتی سفارش شارژ کیف‌پول به «در انتظار بررسی» می‌رود و رسید دارد)
 *   ۳) قطع اتصال فروشگاه (کرون ساعتی؛ بر اساس همان _dastyar_last_ping ماژول ۴۳)
 * هرکدام جدا از تنظیمات کنسول روشن/خاموش می‌شود؛ نبود توکن/چت‌آیدی یعنی هیچ پیامی ارسال نمی‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Telegram {

	const CRON_HOOK = 'da_cron_offline_check';

	public static function init() {
		add_action( 'woocommerce_new_order', array( __CLASS__, 'on_new_order' ), 20, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'on_receipt' ), 20, 1 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'check_offline_vendors' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/* ------------------------------------------------------------------
	 * ارسال پیام
	 * ---------------------------------------------------------------- */

	protected static function configured() {
		return '' !== trim( (string) DA_Page_Settings::option( 'telegram_bot_token' ) )
			&& '' !== trim( (string) DA_Page_Settings::option( 'telegram_chat_id' ) );
	}

	/** ارسال متن ساده (HTML مجاز)؛ اگر تنظیم نشده باشد بی‌صدا نادیده می‌گیرد */
	public static function send( $text ) {
		if ( ! self::configured() ) {
			return false;
		}
		$token   = trim( (string) DA_Page_Settings::option( 'telegram_bot_token' ) );
		$chat_id = trim( (string) DA_Page_Settings::option( 'telegram_chat_id' ) );
		$res     = wp_remote_post( 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage', array(
			'timeout' => 8,
			'body'    => array(
				'chat_id'                  => $chat_id,
				'text'                     => $text,
				'parse_mode'               => 'HTML',
				'disable_web_page_preview' => true,
			),
		) );
		return ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res );
	}

	/** دکمه «تست اتصال» در تنظیمات (لینک GET با nonce، هم‌الگو با save_lowbal_mark) */
	public static function save_test() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		if ( ! self::configured() ) {
			DA_Render::redirect_back( 'da-settings', 'error', array( 'da_err' => 'ابتدا توکن ربات و شناسه چت را وارد و ذخیره کنید.' ) );
		}
		$ok = self::send( '✅ اتصال کنسول مرکز دستیار شاپ به تلگرام با موفقیت برقرار شد.' );
		DA_Render::redirect_back( 'da-settings', $ok ? 'done' : 'error', $ok ? array() : array( 'da_err' => 'ارسال ناموفق بود؛ توکن/چت‌آیدی را بررسی کنید.' ) );
	}

	/* ------------------------------------------------------------------
	 * رویداد ۱ — سفارش جدید فروشنده
	 * ---------------------------------------------------------------- */

	public static function on_new_order( $order_id ) {
		if ( '0' === (string) DA_Page_Settings::option( 'tg_new_order', '1' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$vid = (int) $order->get_meta( DA_Data::VENDOR_KEY );
		if ( $vid <= 0 ) {
			return; // فقط سفارش‌های راه‌دور فروشندگان؛ سفارش‌های مستقیم مرکز نویز اضافه می‌کنند
		}
		self::send( sprintf(
			"🛒 <b>سفارش جدید</b>\nفروشنده: %s\nسفارش: #%s\nمبلغ تامین: %s",
			esc_html( DA_Page_Dash::vendor_name( $vid ) ),
			esc_html( $order->get_order_number() ),
			esc_html( DA_Data::money( DA_Data::order_goods( $order ) ) )
		) );
	}

	/* ------------------------------------------------------------------
	 * رویداد ۲ — رسید کارت‌به‌کارت جدید
	 * ---------------------------------------------------------------- */

	public static function on_receipt( $order_id ) {
		if ( '0' === (string) DA_Page_Settings::option( 'tg_new_receipt', '1' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( '_dastyar_wallet_charge' ) ) {
			return;
		}
		$receipt_meta = class_exists( 'Dastyar_C2C_Gateway' ) ? Dastyar_C2C_Gateway::RECEIPT_META : '_dastyar_c2c_receipt_id';
		if ( ! $order->get_meta( $receipt_meta ) ) {
			return; // فقط وقتی واقعاً رسید بارگذاری شده باشد
		}
		$uid = (int) ( $order->get_meta( '_dastyar_charge_for_user' ) ?: $order->get_customer_id() );
		self::send( sprintf(
			"🧾 <b>رسید کارت‌به‌کارت جدید</b>\nمشتری: %s\nسفارش: #%s\nمبلغ: %s\n\nبررسی در کنسول: %s",
			esc_html( DA_Page_Dash::vendor_name( $uid ) ),
			esc_html( $order->get_order_number() ),
			esc_html( DA_Data::money( (float) $order->get_total() ) ),
			esc_url( admin_url( 'admin.php?page=da-wallet' ) )
		) );
	}

	/* ------------------------------------------------------------------
	 * رویداد ۳ — قطع اتصال فروشگاه (کرون ساعتی)
	 * یک‌بار به‌ازای هر «قطعی»؛ با اتصال دوباره (ping تازه)، پرچم اعلان پاک می‌شود
	 * تا قطعی بعدی دوباره اعلان بدهد.
	 * ---------------------------------------------------------------- */

	public static function check_offline_vendors() {
		if ( '0' === (string) DA_Page_Settings::option( 'tg_offline', '1' ) ) {
			return;
		}
		$hours = max( 1, (float) DA_Page_Settings::option( 'offline_hours', 6 ) );
		foreach ( DA_Data::vendors() as $v ) {
			$last     = (int) get_user_meta( $v['id'], '_dastyar_last_ping', true );
			$notified = (int) get_user_meta( $v['id'], '_da_offline_notified', true );
			if ( ! $last ) {
				continue; // هرگز پینگ نزده؛ چیزی برای «قطع شدن» وجود ندارد
			}
			$idle_h = ( time() - $last ) / 3600;
			if ( $idle_h >= $hours ) {
				if ( ! $notified ) {
					self::send( sprintf(
						"🔴 <b>فروشگاه آفلاین شد</b>\nفروشنده: %s\nآخرین اتصال: %s پیش",
						esc_html( $v['shop'] ?: $v['name'] ),
						esc_html( DA_Data::ago( $last ) )
					) );
					update_user_meta( $v['id'], '_da_offline_notified', 1 );
				}
			} elseif ( $notified ) {
				delete_user_meta( $v['id'], '_da_offline_notified' );
			}
		}
	}
}
