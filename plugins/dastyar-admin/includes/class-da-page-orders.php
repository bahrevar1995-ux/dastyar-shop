<?php
/**
 * صفحه «عملیات سفارش» — ماژول‌های ۳۲ (چاپ گروهی فاکتور) و ۳۳ (ثبت گروهی کد رهگیری).
 * کد رهگیری با همان کلید متای استاندارد ذخیره می‌شود که سایت فروشنده هم می‌خواند/نمایش می‌دهد.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Page_Orders {

	/* ── ۳۲ ─ چاپ گروهی فاکتور */
	public static function render_m32() {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" target="_blank">'
			. wp_nonce_field( DA_Admin::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="da_print">';
		$rows = array();
		foreach ( (array) wc_get_orders( array( 'limit' => 60, 'orderby' => 'date', 'order' => 'DESC' ) ) as $o ) {
			$rows[] = array(
				'<input type="checkbox" name="ids[]" value="' . (int) $o->get_id() . '">',
				'#' . esc_html( $o->get_order_number() ),
				esc_html( DA_Page_Dash::vendor_name( (int) $o->get_meta( DA_Data::VENDOR_KEY ) ) ),
				esc_html( wc_get_order_status_name( $o->get_status() ) ),
				DA_Data::money( DA_Data::order_goods( $o ) ?: (float) $o->get_total() ),
				esc_html( $o->get_date_created() ? gmdate( 'Y-m-d H:i', $o->get_date_created()->getTimestamp() ) : '' ),
			);
		}
		echo DA_Render::table( array( '', 'سفارش', 'مشتری', 'وضعیت', 'مبلغ', 'تاریخ' ), $rows );
		echo '<p><button class="da-btn">🖨 چاپ فاکتور موارد انتخابی</button> <span class="da-hint">در زبانه جدید با دکمه چاپ باز می‌شود.</span></p></form>';
	}

	/** خروجی مستقل چاپ (admin_post — قبل از هر خروجی ادمین) */
	public static function print_invoices() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$ids = array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) );
		if ( ! $ids ) {
			DA_Render::redirect_back( 'da-orders', 'noop' );
		}
		echo '<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8"><title>فاکتورهای دستیار شاپ</title>'
			. '<style>body{font-family:Tahoma,sans-serif;color:#242536;margin:24px}h2{border-bottom:3px solid #17a16d;padding-bottom:6px}'
			. '.inv{border:2px solid #242536;border-radius:12px;padding:16px;margin:0 0 20px;page-break-inside:avoid}'
			. 'table{width:100%;border-collapse:collapse;margin:8px 0}td,th{border:1px solid #cdd8d2;padding:5px 8px;font-size:13px}th{background:#eef6f1}'
			. '.meta{color:#5b6f65;font-size:12.5px}.trk{font-size:15px;font-weight:800;color:#12875c;direction:ltr;display:inline-block}'
			. '@media print{.nop{display:none}}</style></head><body>'
			. '<button class="nop" onclick="window.print()" style="background:#17a16d;color:#fff;border:0;border-radius:10px;padding:10px 22px;font-weight:800;cursor:pointer">🖨 چاپ</button>';
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( ! $o ) { continue; }
			$addr  = method_exists( $o, 'get_address' ) ? (array) $o->get_address( 'billing' ) : array();
			$name  = trim( (string) ( ( $addr['first_name'] ?? '' ) . ' ' . ( $addr['last_name'] ?? '' ) ) );
			$track = (string) $o->get_meta( DA_Data::TRACK_KEY );
			echo '<div class="inv"><h2>دستیار شاپ — فاکتور سفارش #' . esc_html( $o->get_order_number() ) . '</h2>'
				. '<p class="meta">مشتری (فروشنده): <strong>' . esc_html( DA_Page_Dash::vendor_name( (int) $o->get_meta( DA_Data::VENDOR_KEY ) ) ) . '</strong>'
				. ' — شماره در سایت فروشنده: <strong>' . esc_html( (string) $o->get_meta( '_dastyar_remote_order_number' ) ?: '—' ) . '</strong></p>'
				. '<p class="meta">گیرنده: ' . esc_html( $name ?: '—' ) . ' — ' . esc_html( (string) ( $addr['phone'] ?? '' ) ) . '<br>'
				. esc_html( trim( (string) ( ( $addr['state'] ?? '' ) . '، ' . ( $addr['city'] ?? '' ) . '، ' . ( $addr['address_1'] ?? '' ) ) ) )
				. ( ! empty( $addr['postcode'] ) ? ' — کدپستی: ' . esc_html( (string) $addr['postcode'] ) : '' ) . '</p>'
				. '<table><thead><tr><th>قلم</th><th>تعداد</th></tr></thead><tbody>';
			foreach ( (array) $o->get_items() as $it ) {
				echo '<tr><td>' . esc_html( method_exists( $it, 'get_name' ) ? (string) $it->get_name() : '' ) . '</td><td>' . ( method_exists( $it, 'get_quantity' ) ? (int) $it->get_quantity() : 1 ) . '</td></tr>';
			}
			echo '</tbody></table>'
				. '<p><strong>مبلغ تامین: ' . esc_html( DA_Data::money( DA_Data::order_goods( $o ) ) ) . '</strong>'
				. ( $track ? ' — کد رهگیری: <span class="trk">' . esc_html( $track ) . '</span>' : '' ) . '</p></div>';
		}
		echo '</body></html>';
		exit;
	}

	/* ── ۳۳ ─ ثبت گروهی کد رهگیری */
	public static function render_m33() {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. wp_nonce_field( DA_Admin::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="da_bulk_tracking">'
			. '<p class="da-hint">در هر خط: <code dir="ltr">شماره سفارش,کد رهگیری</code> — مثال: <code dir="ltr">1052,240198765432108974521365</code></p>'
			. '<textarea class="da-input" name="lines" rows="6" style="width:100%;direction:ltr" placeholder="1052,2401…' . "\n" . '1053,2401…"></textarea>'
			. '<p><button class="da-btn">ثبت گروهی</button></p></form>';
	}

	public static function save_bulk_tracking() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$ok = 0;
		$bad = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $_POST['lines'] ?? '' ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) { continue; }
			$parts = array_map( 'trim', explode( ',', str_replace( '،', ',', $line ) ) );
			if ( count( $parts ) < 2 || ! is_numeric( $parts[0] ) ) {
				$bad[] = $line;
				continue;
			}
			$o = wc_get_order( (int) $parts[0] );
			if ( ! $o ) { $bad[] = $parts[0]; continue; }
			$o->update_meta_data( DA_Data::TRACK_KEY, sanitize_text_field( $parts[1] ) );
			$o->add_order_note( 'کد رهگیری محموله از کنسول مرکز ثبت شد: ' . sanitize_text_field( $parts[1] ) );
			$o->save();
			$ok++;
		}
		DA_Render::redirect_back( 'da-orders', $ok ? 'done' : 'noop', array( 'da_count' => $ok, 'da_err' => $bad ? 'ناموفق: ' . implode( ' ، ', array_slice( $bad, 0, 5 ) ) : '' ) );
	}
}
