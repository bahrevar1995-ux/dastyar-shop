<?php
/**
 * صفحه «تنظیمات کنسول» — کلید فعال/غیرفعال هر ۵۰ ماژول (سوویچ سازمانی) + آستانه‌های سراسری.
 * ذخیره فقط از همین فرم (گیت da_form=modules) تا فرم‌های دیگر هرگز کلیدها را دست نزنند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Page_Settings {

	/** آستانه‌های سراسری کنسول (گزینه‌ها با پیش‌فرض) */
	public static function option( $key, $default = '' ) {
		$v = get_option( 'dastyar_admin_' . $key, null );
		if ( null === $v || '' === $v ) {
			$defaults = self::defaults();
			return $defaults[ $key ] ?? $default;
		}
		return $v;
	}

	public static function defaults() {
		return array(
			'inactive_days'  => 14,   // ماژول ۴: بیش از این روز بدون سفارش = کم‌تحرک
			'low_balance'    => 100000, // ماژول ۱۵: کف مانده خطرناک
			'sla_hours'      => 48,   // ماژول ۳۱: سقف آماده‌سازی سفارش (ساعت)
			'stock_alert'    => 3,    // ماژول ۳۴: آستانه اتمام موجودی
			'vat_rate'       => 10,   // ماژول ۳۸: نرخ مالیات/ارزش افزوده ٪
			'rank_silver'    => 5000000,  // ماژول ۳: آستانه نقره‌ای (فروش کل)
			'rank_gold'      => 20000000, // ماژول ۳: آستانه طلایی
			'points_per'     => 100000,   // ماژول ۵۰: به ازای هر این‌مقدار فروش = ۱ امتیاز
			'point_value'    => 1000,     // ماژول ۵۰: ارزش هر امتیاز هنگام پاداش (تومان)
			'latest_conn'    => '1.7.10', // ماژول ۴۵: آخرین نسخه رسمی کانکتور برای مقایسه
			// v1.1.0 — اتصال تلگرام
			'telegram_bot_token' => '',
			'telegram_chat_id'   => '',
			'offline_hours'      => 6,    // بعد از چند ساعت بی‌پینگ، فروشگاه «آفلاین» محسوب و اعلان داده شود
			'tg_new_order'       => 1,
			'tg_new_receipt'     => 1,
			'tg_offline'         => 1,
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$mods  = DA_Modules::all();
		$groups= DA_Modules::groups();
		$st    = DA_Modules::stats();
		echo '<div class="wrap da-wrap" dir="rtl">';
		echo '<h1 class="da-page-title"><span class="da-logo-dot"></span>تنظیمات کنسول مرکز <span class="da-ver">' . (int) $st['on'] . ' ماژول از ' . (int) $st['total'] . ' فعال</span></h1>';
		DA_Render::notices();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( DA_Admin::NONCE );
		echo '<input type="hidden" name="action" value="da_modules_save">';
		echo '<input type="hidden" name="da_form" value="modules">';

		echo '<div class="da-card"><div class="da-card-b da-form-row">'
			. '<p style="margin:0;flex:1">هر ماژول را می‌توانید جداگانه روشن/خاموش کنید؛ ماژول خاموش فقط از نمایش حذف می‌شود و هیچ داده‌ای پاک نمی‌کند.</p>'
			. '<button type="button" class="da-btn gray" onclick="document.querySelectorAll(\'.da-mod-cb\').forEach(function(c){c.checked=true});return false;">✅ همه فعال</button> '
			. '<button type="button" class="da-btn gray" onclick="document.querySelectorAll(\'.da-mod-cb\').forEach(function(c){c.checked=false});return false;">⬜ همه غیرفعال</button></div></div>';

		// ماژول‌ها به تفکیک گروه
		$by_group = array();
		foreach ( $mods as $id => $m ) {
			$by_group[ $m['group'] ][] = array( $id, $m );
		}
		foreach ( $groups as $g => $label ) {
			if ( empty( $by_group[ $g ] ) ) {
				continue;
			}
			echo '<h2 class="da-grouptitle"><span class="da-logo-dot" style="animation:none"></span>' . esc_html( $label ) . '</h2>';
			echo '<div class="da-card"><div class="da-card-b">';
			foreach ( $by_group[ $g ] as $it ) {
				list( $id, $m ) = $it;
				$on = DA_Modules::enabled( $id );
				echo '<div class="da-modrow"><span class="num">' . (int) $m['n'] . '</span>'
					. '<label class="da-switch"><input type="checkbox" class="da-mod-cb" name="mods[' . esc_attr( $id ) . ']" value="1" ' . checked( $on, true, false ) . '><span class="da-slider"></span></label>'
					. '<span><span class="t">' . esc_html( $m['title'] ) . '</span><br><span class="d">' . esc_html( $m['desc'] ) . '</span></span></div>';
			}
			echo '</div></div>';
		}

		// آستانه‌های سراسری
		echo '<h2 class="da-grouptitle"><span class="da-logo-dot" style="animation:none"></span>آستانه‌ها و نرخ‌ها</h2>';
		echo '<div class="da-card"><div class="da-card-b"><table class="form-table" role="presentation"><tbody>';
		$fields = array(
			'inactive_days'  => array( 'روزهای بی‌سفارش برای «مشتری کم‌تحرک» (۴)', 'روز' ),
			'low_balance'    => array( 'کف مانده خطرناک کیف پول (۱۵)', 'تومان' ),
			'sla_hours'      => array( 'حداکثر ساعت آماده‌سازی سفارش — SLA (۳۱)', 'ساعت' ),
			'stock_alert'    => array( 'آستانه هشدار موجودی انبار (۳۴)', 'عدد' ),
			'vat_rate'       => array( 'نرخ مالیات/ارزش افزوده (۳۸)', '٪' ),
			'rank_silver'    => array( 'آستانه رتبه نقره‌ای — فروش کل (۳)', 'تومان' ),
			'rank_gold'      => array( 'آستانه رتبه طلایی — فروش کل (۳)', 'تومان' ),
			'points_per'     => array( 'هر چند تومان فروش = ۱ امتیاز (۵۰)', 'تومان' ),
			'point_value'    => array( 'ارزش هر امتیاز هنگام پاداش (۵۰)', 'تومان' ),
			'latest_conn'    => array( 'آخرین نسخه رسمی کانکتور برای رادار نسخه (۴۵)', 'مثل 1.7.10' ),
			'offline_hours'  => array( 'بعد از چند ساعت بی‌پینگ، فروشگاه «آفلاین» محسوب شود', 'ساعت' ),
		);
		foreach ( $fields as $key => $f ) {
			echo '<tr><th scope="row"><label>' . esc_html( $f[0] ) . '</label></th><td>'
				. '<input type="text" class="da-input" name="opt[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) self::option( $key ) ) . '" style="width:140px"> '
				. '<span class="da-hint">' . esc_html( $f[1] ) . '</span></td></tr>';
		}
		echo '</tbody></table></div></div>';

		// v1.1.0 — اتصال تلگرام
		$tg_ok = '' !== trim( (string) self::option( 'telegram_bot_token' ) ) && '' !== trim( (string) self::option( 'telegram_chat_id' ) );
		echo '<h2 class="da-grouptitle"><span class="da-logo-dot" style="animation:none"></span>اتصال تلگرام (اعلان‌های مهم)</h2>';
		echo '<div class="da-card"><div class="da-card-b">';
		echo '<p class="da-hint" style="margin:0 0 10px">با ساخت یک ربات از <code>@BotFather</code> توکن بگیرید و شناسه چت/کانال مقصد را وارد کنید. ' . ( $tg_ok ? DA_Render::pill( 'متصل', 'green' ) : DA_Render::pill( 'تنظیم نشده', 'gray' ) ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label>توکن ربات</label></th><td><input type="text" class="da-input" dir="ltr" name="opt[telegram_bot_token]" value="' . esc_attr( (string) self::option( 'telegram_bot_token' ) ) . '" style="width:320px" placeholder="123456:ABC-..."></td></tr>';
		echo '<tr><th scope="row"><label>شناسه چت/کانال</label></th><td><input type="text" class="da-input" dir="ltr" name="opt[telegram_chat_id]" value="' . esc_attr( (string) self::option( 'telegram_chat_id' ) ) . '" style="width:200px" placeholder="-1001234567890"></td></tr>';
		echo '<tr><th scope="row">اعلان‌های فعال</th><td>';
		$tg_toggles = array(
			'tg_new_order'   => 'سفارش جدید فروشنده',
			'tg_new_receipt' => 'رسید کارت‌به‌کارت جدید',
			'tg_offline'     => 'قطع اتصال فروشگاه',
		);
		foreach ( $tg_toggles as $k => $label ) {
			printf(
				'<label style="display:inline-flex;align-items:center;gap:6px;margin-inline-end:16px;font-size:12.5px;font-weight:700"><input type="hidden" name="opt[%1$s]" value="0"><input type="checkbox" name="opt[%1$s]" value="1"%2$s> %3$s</label>',
				esc_attr( $k ),
				checked( '0' !== (string) self::option( $k, '1' ), true, false ),
				esc_html( $label )
			);
		}
		echo '</td></tr>';
		echo '</tbody></table>';
		echo '<p class="da-hint">۱) این تنظیمات را ذخیره کنید، ۲) بعد از رفرش صفحه، دکمه تست را بزنید.</p>';
		echo '<a class="da-btn gray" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=da_tg_test' ), DA_Admin::NONCE ) ) . '">📨 ارسال پیام تست</a>';
		echo '</div></div>';

		echo '<p><button class="da-btn" style="font-size:14px;padding:10px 28px">💾 ذخیره تنظیمات کنسول</button> '
			. '<a class="da-btn gray" href="' . esc_url( admin_url( 'admin.php?page=da-settings&da_msg=saved' ) ) . '">بازگشت</a></p>';
		echo '</form></div>';
	}

	/** ذخیره: فقط وقتی گیت da_form=modules باشد (الگوی فرم-کامل کانکتور v1.5.0) */
	public static function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		if ( 'modules' === sanitize_key( $_POST['da_form'] ?? '' ) ) {
			$posted = (array) ( $_POST['mods'] ?? array() );
			$out    = array();
			foreach ( DA_Modules::all() as $id => $m ) {
				$out[ $id ] = isset( $posted[ $id ] ) ? 1 : 0;
			}
			update_option( DASTYAR_ADMIN_MODULES_OPTION, $out );
			foreach ( (array) ( $_POST['opt'] ?? array() ) as $k => $v ) {
				update_option( 'dastyar_admin_' . sanitize_key( $k ), sanitize_text_field( wp_unslash( (string) $v ) ) );
			}
		}
		DA_Render::redirect_back( 'da-settings', 'saved' );
	}
}
