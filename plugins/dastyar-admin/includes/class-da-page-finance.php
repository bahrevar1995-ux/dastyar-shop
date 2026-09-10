<?php
/**
 * صفحه «مالی و تسویه» — ماژول‌های ۳۷ (تسویه پایان ماه)، ۳۸ (مالیات)، ۳۹ (دفتر سند دستی).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Page_Finance {

	/* ── ۳۷ ─ تسویه پایان ماه */
	public static function render_m37() {
		$month = sanitize_text_field( (string) ( $_GET['da_month'] ?? gmdate( 'Y-m' ) ) );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) { $month = gmdate( 'Y-m' ); }
		$start = strtotime( $month . '-01 00:00:00' );
		$end   = strtotime( gmdate( 'Y-m-t 23:59:59', $start ) );
		$map   = DA_Data::sales_map( DA_Data::orders_range( $start, $end, 900 ) );
		$rows  = array();
		foreach ( DA_Data::vendors() as $v ) {
			$m = $map[ $v['id'] ] ?? array( 'orders' => 0, 'sum' => 0.0 );
			$cr = 0.0; $db = 0.0;
			foreach ( DA_Data::wallet_txns( $v['id'], 200 ) as $t ) {
				$t = (array) $t;
				$ts = strtotime( (string) ( $t['created_at'] ?? '' ) );
				if ( ! $ts || $ts < $start || $ts > $end ) { continue; }
				if ( 'credit' === ( $t['type'] ?? '' ) ) { $cr += (float) ( $t['amount'] ?? 0 ); }
				if ( 'debit' === ( $t['type'] ?? '' ) )  { $db += (float) ( $t['amount'] ?? 0 ); }
			}
			$key  = '_da_settled_' . str_replace( '-', '', $month );
			$done = (string) get_user_meta( $v['id'], $key, true );
			$rows[] = array(
				esc_html( $v['shop'] ?: $v['name'] ),
				number_format( $m['orders'] ),
				DA_Data::money( $m['sum'] ),
				DA_Data::money( $cr ),
				DA_Data::money( $db ),
				DA_Data::money( $v['balance'] ),
				$done ? DA_Render::pill( 'تسویه شد: ' . $done, 'green' )
					: '<a class="da-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=da_settle&uid=' . $v['id'] . '&month=' . $month ), DA_Admin::NONCE ) ) . '">✔ تسویه شد</a>',
			);
		}
		echo '<form method="get" class="da-form-row"><input type="hidden" name="page" value="da-finance">'
			. '<input class="da-input" type="month" name="da_month" value="' . esc_attr( $month ) . '"> <button class="da-btn gray">ماه دیگر</button></form>';
		echo DA_Render::table( array( 'مشتری', 'سفارش‌ها', 'فروش تامین', 'شارژ ماه', 'برداشت ماه', 'مانده فعلی', 'وضعیت تسویه' ), $rows );
	}

	/* ── ۳۸ ─ گزارش مالیات */
	public static function render_m38() {
		$rate = (float) DA_Page_Settings::option( 'vat_rate' );
		$tot  = DA_Data::orders_totals( DA_Data::orders_range( DA_Data::t_month(), time(), 500 ) );
		$base = $tot['goods'];
		$vat  = $base * $rate / 100;
		echo '<div class="da-grid">'
			. DA_Render::kpi( 'مبنای محاسبه (فروش تامین ماه)', DA_Data::money( $base ) )
			. DA_Render::kpi( 'مالیات/ارزش افزوده با نرخ ' . $rate . '٪', DA_Data::money( $vat ), 'برآورد — مشاور مالی نهایت تصمیم است', 'dark' )
			. '</div><p class="da-hint">نرخ از «تنظیمات کنسول» قابل تغییر است.</p>';
	}

	/* ── ۳۹ ─ دفتر سند دستی */
	public static function render_m39() {
		$rows = (array) get_option( DA_Admin::LEDGER, array() );
		$sum  = 0.0;
		$body = array();
		foreach ( array_reverse( $rows, true ) as $i => $r ) {
			$amt = (float) ( $r['amount'] ?? 0 );
			$sum += ( 'in' === ( $r['type'] ?? '' ) ? $amt : -$amt );
			$body[] = array(
				esc_html( (string) ( $r['date'] ?? '' ) ),
				'in' === ( $r['type'] ?? '' ) ? '<span class="da-ledger-inc">+ ' . DA_Data::money( $amt ) . '</span>' : '<span class="da-ledger-dec">− ' . DA_Data::money( $amt ) . '</span>',
				esc_html( (string) ( $r['desc'] ?? '' ) ),
				'<a class="da-btn gray" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=da_ledger_del&i=' . (int) $i ), DA_Admin::NONCE ) ) . '" onclick="return confirm(\'حذف شود؟\')">حذف</a>',
			);
		}
		echo '<div class="da-grid">' . DA_Render::kpi( 'تراز دفتر سند', DA_Data::money( $sum ), 'هزینه/درآمدهای خارج از پلتفرم', $sum < 0 ? 'red' : 'dark' ) . '</div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="da-form-row">'
			. wp_nonce_field( DA_Admin::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="da_ledger_add">'
			. '<input class="da-input" type="date" name="date" value="' . esc_attr( gmdate( 'Y-m-d' ) ) . '">'
			. '<select class="da-input" name="type"><option value="out">هزینه (خروج)</option><option value="in">درآمد (ورود)</option></select>'
			. '<input class="da-input" type="number" name="amount" min="1" placeholder="مبلغ" required style="width:140px">'
			. '<input class="da-input" type="text" name="desc" placeholder="شرح سند" style="min-width:220px">'
			. '<button class="da-btn">ثبت سند</button></form>';
		echo DA_Render::table( array( 'تاریخ', 'مبلغ', 'شرح', '' ), $body );
	}

	/* ==================================================================
	 * اکشن‌ها
	 * ================================================================== */

	public static function save_settle() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$uid   = (int) ( $_GET['uid'] ?? 0 );
		$month = sanitize_text_field( (string) ( $_GET['month'] ?? gmdate( 'Y-m' ) ) );
		if ( $uid && preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			update_user_meta( $uid, '_da_settled_' . str_replace( '-', '', $month ), current_time( 'mysql' ) );
		}
		DA_Render::redirect_back( 'da-finance', 'done', array( 'da_month' => $month ) );
	}

	public static function ledger_add() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$amount = (float) ( $_POST['amount'] ?? 0 );
		if ( $amount > 0 ) {
			$rows   = (array) get_option( DA_Admin::LEDGER, array() );
			$rows[] = array(
				'date'   => sanitize_text_field( (string) ( $_POST['date'] ?? gmdate( 'Y-m-d' ) ) ),
				'type'   => 'in' === sanitize_key( $_POST['type'] ?? '' ) ? 'in' : 'out',
				'amount' => $amount,
				'desc'   => sanitize_text_field( wp_unslash( (string) ( $_POST['desc'] ?? '' ) ) ),
			);
			$rows = array_slice( $rows, -500 );
			update_option( DA_Admin::LEDGER, $rows );
		}
		DA_Render::redirect_back( 'da-finance', 'saved' );
	}

	public static function ledger_del() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$rows = (array) get_option( DA_Admin::LEDGER, array() );
		$i    = (int) ( $_GET['i'] ?? -1 );
		if ( isset( $rows[ $i ] ) ) {
			unset( $rows[ $i ] );
			$rows = array_values( $rows );
			update_option( DA_Admin::LEDGER, $rows );
		}
		DA_Render::redirect_back( 'da-finance', 'done' );
	}
}
