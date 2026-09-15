<?php
/**
 * ساخت سفارش «شارژ کیف پول» (بدون عبور از سبد خرید) — پرداخت آن کاملاً با فلوی
 * پیش‌فرض/استاندارد ووکامرس انجام می‌شود (صفحه «پرداخت سفارش» با لیست معمولی درگاه‌ها).
 *
 * توجه (بازگشت به حالت پیش‌فرض — v1.10.17): پاپ‌آپ سفارشی انتخاب درگاه و منطق اولویت
 * زرین‌پال که قبلاً اینجا بود، به درخواست کارفرما کاملاً حذف شد. سایت الان فقط با یک
 * درگاه (زرین‌پال) کار می‌کند و تسویه/تایید هم کاملاً خودکار توسط خودِ درگاه (با
 * برگشت به woocommerce_order_status_changed ← Dastyar_Wallet::credit_paid_charge)
 * انجام می‌شود؛ هیچ تایید دستی لازم نیست.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Payments {

	public function __construct() {
		// عمداً خالی — دیگر هیچ اکشن/فیلتر سفارشی روی درگاه‌ها ثبت نمی‌شود.
	}

	/* ------------------------------------------------------------------
	 * ساخت سفارش «شارژ کیف پول» (در انتظار پرداخت) — بدون دخالت سبد
	 * ---------------------------------------------------------------- */

	/** @return WC_Order|WP_Error */
	public static function create_charge_order( $uid, $amount ) {
		$amount = (float) $amount;
		if ( $amount <= 0 ) {
			return new WP_Error( 'dastyar_bad_amount', 'مبلغ معتبر نیست.' );
		}
		// محصول شارژ «اختیاری» است — اگر حذف شده باشد، آیتم بدون محصول با نام ثابت ساخته می‌شود
		// تا شارژ کیف پول هرگز با خطای «محصول یافت نشد» متوقف نشود.
		$pid     = (int) get_option( 'dastyar_charge_product_id' );
		$product = $pid ? wc_get_product( $pid ) : null;

		$order = wc_create_order( array( 'customer_id' => (int) $uid, 'created_via' => 'dastyar-wallet' ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$item = new WC_Order_Item_Product();
		if ( $product ) {
			$item->set_product( $product );
		}
		$item->set_name( 'شارژ کیف پول دستیار شاپ' );
		$item->set_quantity( 1 );
		$item->set_subtotal( $amount );
		$item->set_total( $amount );
		$order->add_item( $item );

		// شناسایی سفارش شارژ با متا (نه با شناسه محصول) — پایدار حتی بدون محصول شارژ
		$order->update_meta_data( '_dastyar_wallet_charge', 1 );
		$order->update_meta_data( '_dastyar_charge_for_user', (int) $uid );

		self::fill_billing_from_user( $order, $uid );
		$order->calculate_totals();
		$order->set_status( 'pending', 'سفارش شارژ کیف پول ساخته شد.' );
		$order->save();

		return $order;
	}

	/** اطلاعات صورتحساب از پروفایل فروشنده (برخی درگاه‌ها به ایمیل/موبایل نیاز دارند) */
	public static function fill_billing_from_user( WC_Order $order, $uid ) {
		$user = get_userdata( $uid );
		$order->set_billing_email( $user && $user->user_email ? $user->user_email : get_option( 'admin_email' ) );
		$order->set_billing_first_name( get_user_meta( $uid, 'billing_first_name', true ) ?: ( $user ? $user->display_name : '' ) );
		$order->set_billing_last_name( get_user_meta( $uid, 'billing_last_name', true ) );
		$order->set_billing_phone( get_user_meta( $uid, 'billing_phone', true ) );
		$base = wc_get_base_location();
		$order->set_billing_country( $base['country'] ?? 'IR' );
	}

	/* ------------------------------------------------------------------
	 * لیست ساده درگاه‌های فعال به‌صورت فرم مستقیم (کلیک = رفتن به PSP) — بدون هیچ
	 * اولویت‌دهی/پاپ‌آپ سفارشی؛ فقط توسط ویزارد «ثبت سفارش دستی» استفاده می‌شود
	 * (Dastyar_Manual_Order) تا مرحله پرداخت پایانی‌اش بدون سبد/تسویه کار کند.
	 * ---------------------------------------------------------------- */

	public static function gateway_picker_html( WC_Order $order ) {
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( ! $gateways ) {
			return '<p class="dastyar-gw-empty">هیچ درگاه پرداخت فعالی روی فروشگاه تنظیم نشده است.</p>';
		}

		$pay_url = $order->get_checkout_payment_url();
		$nonce   = wp_create_nonce( 'woocommerce-pay' );

		$html = '<div class="dastyar-gw-list">';
		foreach ( $gateways as $gw_id => $gw ) {
			$icon  = method_exists( $gw, 'get_icon' ) ? $gw->get_icon() : '';
			$html .= '<form method="post" action="' . esc_url( $pay_url ) . '" class="dastyar-gw-form">';
			$html .= '<input type="hidden" name="woocommerce-pay-nonce" value="' . esc_attr( $nonce ) . '">';
			$html .= '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( wp_unslash( $pay_url ) ) . '">';
			$html .= '<input type="hidden" name="payment_method" value="' . esc_attr( $gw_id ) . '">';
			if ( function_exists( 'wc_terms_and_conditions_page_id' ) && wc_terms_and_conditions_page_id() ) {
				$html .= '<input type="hidden" name="terms" value="1">';
			}
			$html .= '<button type="submit" class="dastyar-gw-btn">';
			if ( $icon ) {
				$html .= '<span class="dastyar-gw-icon">' . $icon . '</span>';
			}
			$html .= '<span class="dastyar-gw-title">' . esc_html( $gw->get_title() ) . '</span>';
			$html .= '<span class="dastyar-gw-go">پرداخت ←</span>';
			$html .= '</button></form>';
		}
		$html .= '</div>';
		return $html;
	}
}
