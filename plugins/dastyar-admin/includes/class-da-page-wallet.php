<?php
/**
 * صفحه «کیف پول» — ماژول‌های ۹ تا ۱۵ + اکشن‌ها.
 * همه اعمال مالی واقعی از API خود مرکز (Dastyar_Wallet::credit/debit/get_transactions) انجام می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Page_Wallet {

	/* ── ۹ ─ برد کیف پول (مانده همه مشتری‌ها) */
	public static function render_m09() {
		$rows = array();
		$sum  = 0.0;
		foreach ( DA_Data::vendors() as $v ) {
			$sum += $v['balance'];
			$rows[] = array(
				esc_html( $v['shop'] ?: $v['name'] ),
				DA_Data::money( $v['balance'] ),
				$v['balance'] < 0 ? DA_Render::pill( 'منفی', 'red' ) : ( $v['balance'] < (float) DA_Page_Settings::option( 'low_balance' ) ? DA_Render::pill( 'کم', 'amber' ) : DA_Render::pill( 'سالم', 'green' ) ),
			);
		}
		usort( $rows, function ( $a, $b ) { return strcmp( strip_tags( $a[1] ), strip_tags( $b[1] ) ); } );
		echo '<div class="da-grid">' . DA_Render::kpi( 'جمع مانده همه کیف پول‌ها', DA_Data::money( $sum ), 'بدهی پلتفرم به مشتری‌ها', 'dark' ) . '</div>';
		echo DA_Render::table( array( 'مشتری', 'مانده', 'وضعیت' ), $rows );
	}

	/* ── ۱۰ ─ صف شارژهای ناتمام (اطلاع‌رسانی صرف — بدون اقدام دستی) */
	public static function render_m10() {
		$rows = array();
		foreach ( (array) wc_get_orders( array( 'limit' => 150, 'status' => array( 'wc-on-hold', 'wc-pending' ), 'orderby' => 'date', 'order' => 'DESC' ) ) as $o ) {
			if ( ! $o->get_meta( '_dastyar_wallet_charge' ) || $o->get_meta( '_dastyar_wallet_credited' ) ) { continue; }
			$uid = (int) ( $o->get_meta( '_dastyar_charge_for_user' ) ?: $o->get_customer_id() );

			$rows[] = array(
				'#' . esc_html( $o->get_order_number() ),
				esc_html( DA_Page_Dash::vendor_name( $uid ) ),
				DA_Data::money( (float) $o->get_total() ),
				esc_html( $o->get_payment_method_title() ?: '—' ),
				esc_html( $o->get_date_created() ? DA_Data::ago( (int) $o->get_date_created()->getTimestamp() ) : '—' ),
				'<a class="da-btn gray" href="' . esc_url( admin_url( 'post.php?post=' . (int) $o->get_id() . '&action=edit' ) ) . '" target="_blank">مشاهده سفارش</a>',
			);
		}
		echo '<p class="da-hint">سفارش‌های «شارژ کیف پول» که پرداختشان هنوز کامل نشده — یعنی یا مشتری در درگاه پرداخت را رها کرده، یا هنوز درگاه را باز نکرده. از وقتی پرداخت فقط از طریق زرین‌پال انجام می‌شود، نیازی به تایید دستی نیست: به‌محض پرداخت موفق در زرین‌پال، سفارش و شارژ کیف پول کاملاً خودکار انجام می‌شود. این لیست فقط برای اطلاع از تلاش‌های ناتمام است؛ خودش خودبه‌خود از این صف خارج می‌شود (یا با پرداخت موفق بعدی، یا با پاکسازی دوره‌ای سفارش‌های معلق ووکامرس).</p>';
		echo DA_Render::table( array( 'سفارش', 'مشتری', 'مبلغ', 'روش پرداخت', 'چقدر پیش', '' ), $rows );
	}

	/* ── ۱۱ ─ شارژ/کسر دستی */
	public static function render_m11() {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="da-form-row">'
			. wp_nonce_field( DA_Admin::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="da_wallet_adjust">'
			. '<select class="da-input" name="uid" required><option value="">انتخاب مشتری…</option>';
		foreach ( DA_Data::vendors() as $v ) {
			echo '<option value="' . (int) $v['id'] . '">' . esc_html( ( $v['shop'] ?: $v['name'] ) . ' (مانده: ' . number_format( $v['balance'] ) . ')' ) . '</option>';
		}
		echo '</select>'
			. '<select class="da-input" name="type"><option value="credit">افزایش (شارژ)</option><option value="debit">کاهش (برداشت)</option></select>'
			. '<input class="da-input" type="number" name="amount" min="1" step="1" placeholder="مبلغ (تومان)" required style="width:150px">'
			. '<input class="da-input" type="text" name="desc" placeholder="شرح سند (اختیاری)" style="min-width:220px">'
			. '<button class="da-btn">ثبت در دفتر کیف پول</button></form>'
			. '<p class="da-hint">تراکنش‌ها در همان جدول رسمی «dastyar_wallet_txns» مرکز ثبت می‌شوند و در حساب مشتری هم دیده می‌شود.</p>';
	}

	/* ── ۱۲ ─ هشدار بدهکاران / بیش از سقف اعتبار */
	public static function render_m12() {
		$rows = array();
		foreach ( DA_Data::vendors() as $v ) {
			$limit = (float) get_user_meta( $v['id'], '_da_credit_limit', true );
			$debt  = $v['balance'] < 0 ? abs( $v['balance'] ) : 0.0;
			$over  = $limit > 0 && $debt > $limit;
			if ( $debt <= 0 && ! $over ) { continue; }
			$rows[] = array(
				esc_html( $v['shop'] ?: $v['name'] ),
				DA_Data::money( $v['balance'] ),
				$limit ? DA_Data::money( $limit ) : '—',
				DA_Render::pill( $over ? 'بیش از سقف اعتبار!' : 'مانده منفی', 'red' ),
			);
		}
		echo DA_Render::table( array( 'مشتری', 'مانده', 'سقف اعتبار', 'هشدار' ), $rows );
	}

	/* ── ۱۳ ─ دفتر گردش حساب مشتری */
	public static function render_m13() {
		$uid = (int) ( $_GET['da_uid'] ?? 0 );
		echo '<form method="get" class="da-form-row da-no-print"><input type="hidden" name="page" value="da-wallet">'
			. '<select class="da-input" name="da_uid"><option value="">انتخاب مشتری…</option>';
		foreach ( DA_Data::vendors() as $v ) {
			echo '<option value="' . (int) $v['id'] . '" ' . selected( $uid, $v['id'], false ) . '>' . esc_html( $v['shop'] ?: $v['name'] ) . '</option>';
		}
		echo '</select><button class="da-btn">نمایش دفتر</button>';
		if ( $uid ) {
			echo ' <button class="da-btn gray" onclick="window.print();return false;">🖨 چاپ</button>';
		}
		echo '</form>';
		if ( ! $uid ) {
			echo '<p class="da-empty">یک مشتری انتخاب کنید تا صورت‌حساب کاملش نمایش داده شود.</p>';
			return;
		}
		$rows = array();
		foreach ( DA_Data::wallet_txns( $uid, 50 ) as $t ) {
			$t    = (array) $t;
			$type = (string) ( $t['type'] ?? '' );
			$amt  = DA_Data::money( (float) ( $t['amount'] ?? 0 ) );
			$rows[] = array(
				esc_html( (string) ( $t['created_at'] ?? '' ) ),
				'credit' === $type ? '<span class="da-ledger-inc">+ ' . $amt . '</span>' : '<span class="da-ledger-dec">− ' . $amt . '</span>',
				esc_html( (string) ( $t['description'] ?? ( $t['desc'] ?? '' ) ) ),
				esc_html( isset( $t['balance'] ) ? DA_Data::money( (float) $t['balance'] ) : '—' ),
			);
		}
		echo '<h4 style="margin:8px 0">دفتر گردش حساب: ' . esc_html( DA_Page_Dash::vendor_name( $uid ) )
			. ' — مانده فعلی: <strong>' . esc_html( DA_Data::money( DA_Data::balance( $uid ) ) ) . '</strong></h4>';
		echo DA_Render::table( array( 'تاریخ', 'مبلغ', 'شرح', 'مانده بعد از سند' ), $rows );
	}

	/* ── ۱۴ ─ سقف اعتبار هر مشتری */
	public static function render_m14() {
		$rows = array();
		foreach ( DA_Data::vendors() as $v ) {
			$limit = (string) get_user_meta( $v['id'], '_da_credit_limit', true );
			$rows[] = array(
				esc_html( $v['shop'] ?: $v['name'] ),
				DA_Data::money( $v['balance'] ),
				'<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="da-form-row" style="margin:0">'
					. wp_nonce_field( DA_Admin::NONCE, '_wpnonce', true, false )
					. '<input type="hidden" name="action" value="da_credit_limit"><input type="hidden" name="uid" value="' . (int) $v['id'] . '">'
					. '<input class="da-input" type="number" name="limit" min="0" step="1000" value="' . esc_attr( $limit ) . '" placeholder="۰ = نامحدود" style="width:130px">'
					. '<button class="da-btn gray">ثبت</button></form>',
			);
		}
		echo '<p class="da-hint">اگر مانده منفی مشتری از این سقف بگذرد، در «هشدار بدهکاران» (ماژول ۱۲) قرمز می‌شود.</p>';
		echo DA_Render::table( array( 'مشتری', 'مانده فعلی', 'سقف اعتبار (تومان)' ), $rows );
	}

	/* ── ۱۵ ─ یادآور کیف پول کم */
	public static function render_m15() {
		$min  = (float) DA_Page_Settings::option( 'low_balance' );
		$rows = array();
		foreach ( DA_Data::vendors() as $v ) {
			if ( $v['balance'] >= $min ) { continue; }
			$marked = (string) get_user_meta( $v['id'], '_da_lowbal_notice', true );
			$rows[] = array(
				esc_html( $v['shop'] ?: $v['name'] ),
				DA_Data::money( $v['balance'] ),
				$marked ? DA_Render::pill( 'یادآوری شد: ' . $marked, 'gray' ) : DA_Render::pill( 'نیازمند یادآوری', 'amber' ),
				'<a class="da-btn gray" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=da_lowbal_mark&uid=' . $v['id'] ), DA_Admin::NONCE ) ) . '">ثبت یادآوری</a>',
			);
		}
		echo '<p class="da-hint">مشتری‌های زیر ' . esc_html( DA_Data::money( $min ) ) . ' مانده — بعد از تماس/پیام، «ثبت یادآوری» بزنید تا ردیابی شود.</p>';
		echo DA_Render::table( array( 'مشتری', 'مانده', 'وضعیت یادآور', '' ), $rows );
	}

	/* ==================================================================
	 * اکشن‌ها
	 * ================================================================== */

	public static function save_adjust() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$uid    = (int) ( $_POST['uid'] ?? 0 );
		$type   = sanitize_key( (string) ( $_POST['type'] ?? 'credit' ) );
		$amount = (float) ( $_POST['amount'] ?? 0 );
		$desc   = sanitize_text_field( wp_unslash( (string) ( $_POST['desc'] ?? '' ) ) );
		$w      = DA_Data::wallet();
		if ( ! $uid || $amount <= 0 || ! $w ) {
			DA_Render::redirect_back( 'da-wallet', 'error' );
		}
		$desc = $desc ?: ( 'credit' === $type ? 'شارژ دستی مدیر (کنسول مرکز)' : 'کسر دستی مدیر (کنسول مرکز)' );
		if ( 'debit' === $type ) {
			$w->debit( $uid, $amount, 0, $desc );
		} else {
			$w->credit( $uid, $amount, 0, $desc );
		}
		DA_Render::redirect_back( 'da-wallet', 'saved', array( 'da_uid' => $uid ) );
	}

	public static function save_credit_limit() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$uid = (int) ( $_POST['uid'] ?? 0 );
		if ( $uid ) {
			$limit = max( 0, (float) ( $_POST['limit'] ?? 0 ) );
			if ( $limit > 0 ) {
				update_user_meta( $uid, '_da_credit_limit', $limit );
			} else {
				delete_user_meta( $uid, '_da_credit_limit' );
			}
		}
		DA_Render::redirect_back( 'da-wallet', 'saved' );
	}

	public static function save_lowbal_mark() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$uid = (int) ( $_GET['uid'] ?? 0 );
		if ( $uid ) {
			update_user_meta( $uid, '_da_lowbal_notice', current_time( 'mysql' ) );
		}
		DA_Render::redirect_back( 'da-wallet', 'done' );
	}
}
