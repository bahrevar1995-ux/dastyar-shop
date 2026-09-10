<?php
/**
 * کیف پول فروشنده — WooCommerce کیف پول داخلی ندارد، بنابراین ماژول سبک اختصاصی:
 * - موجودی در usermeta
 * - تراکنش‌ها در جدول dastyar_wallet_txns
 * - شارژ از طریق درگاه‌های استاندارد WooCommerce (محصول مخفی «شارژ کیف پول»)
 *
 * با فیلتر dastyar_wallet_balance می‌توانید کیف پول خارجی (مثل TeraWallet) را جایگزین کنید.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Wallet {

	public function __construct() {
		// اعمال مبلغ دلخواه روی محصول شارژ کیف پول
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_charge_price' ), 20 );
		// پنهان ماندن محصول شارژ از فروشگاه
		add_filter( 'woocommerce_product_is_visible', array( $this, 'hide_charge_product' ), 10, 2 );
		// شارژ کیف پول پس از پرداخت موفق سفارش شارژ
		add_action( 'woocommerce_order_status_changed', array( $this, 'credit_paid_charge' ), 10, 4 );
		// v1.10.8 — بازگشت خودکار وجه به کیف پول هنگام «لغو» سفارش فروشنده (اعم از لغو توسط خود فروشنده یا مرکز)
		add_action( 'woocommerce_order_status_changed', array( $this, 'refund_on_cancel' ), 30, 4 );
	}

	/* ------------------------------------------------------------------
	 * موجودی و تراکنش‌ها
	 * ---------------------------------------------------------------- */

	public function get_balance( $uid ) {
		$balance = (float) get_user_meta( $uid, Dastyar_Vendors::WALLET, true );
		// نقطه اتصال کیف پول خارجی
		return (float) apply_filters( 'dastyar_wallet_balance', $balance, $uid );
	}

	public function credit( $uid, $amount, $order_id = 0, $desc = '' ) {
		return $this->adjust( $uid, (float) $amount, 'credit', $order_id, $desc );
	}

	public function debit( $uid, $amount, $order_id = 0, $desc = '' ) {
		return $this->adjust( $uid, (float) $amount, 'debit', $order_id, $desc );
	}

	/** @return float|false موجودی جدید یا false در صورت ناموفق */
	protected function adjust( $uid, $amount, $type, $order_id = 0, $desc = '' ) {
		global $wpdb;
		$amount  = abs( $amount );
		$balance = (float) get_user_meta( $uid, Dastyar_Vendors::WALLET, true );
		$new     = 'credit' === $type ? $balance + $amount : $balance - $amount;
		if ( $new < 0 ) {
			return false;
		}
		update_user_meta( $uid, Dastyar_Vendors::WALLET, $new );
		$wpdb->insert(
			$wpdb->prefix . 'dastyar_wallet_txns',
			array(
				'user_id'       => (int) $uid,
				'type'          => $type,
				'amount'        => $amount,
				'balance_after' => $new,
				'ref_order_id'  => (int) $order_id,
				'description'   => substr( (string) $desc, 0, 500 ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%f', '%f', '%d', '%s', '%s' )
		);
		do_action( 'dastyar_wallet_adjusted', $uid, $type, $amount, $new, $order_id, $desc );
		return $new;
	}

	public function get_transactions( $uid, $limit = 50 ) {
		global $wpdb;
		$limit = max( 1, min( 200, (int) $limit ) );
		$table = $wpdb->prefix . 'dastyar_wallet_txns';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d", $uid, $limit ) // phpcs:ignore
		);
	}

	/* ------------------------------------------------------------------
	 * پرداخت سفارش از کیف پول
	 * ---------------------------------------------------------------- */

	/**
	 * اگر موجودی کافی است سفارش را پرداخت و Processing می‌کند،
	 * در غیر این صورت سفارش به‌صورت «در انتظار پرداخت» برای صورتحساب باقی می‌ماند.
	 */
	public function maybe_pay_order( WC_Order $order, $vendor_id ) {
		$total = (float) $order->get_total();
		if ( $total <= 0 ) {
			$order->payment_complete();
			$order->add_order_note( 'سفارش بدون مبلغ — بدون نیاز به پرداخت.' );
			return true;
		}
		if ( $this->get_balance( $vendor_id ) >= $total ) {
			$this->debit( $vendor_id, $total, $order->get_id(), 'پرداخت سفارش #' . $order->get_order_number() . ' از کیف پول' );
			$order->payment_complete( 'dastyar-wallet' );
			$order->add_order_note( 'سفارش به صورت خودکار از کیف پول فروشنده پرداخت شد.' );
			return true;
		}
		$order->add_order_note( 'موجودی کیف پول کافی نیست — سفارش به‌صورت «در انتظار پرداخت» در صورتحساب‌های فروشنده قرار گرفت.' );
		return false;
	}

	/** پرداخت دستی یک فاکتور از کیف پول (دکمه «پرداخت از کیف پول» در پنل فروشنده) */
	public function pay_order_now( WC_Order $order, $vendor_id ) {
		if ( ! $order->has_status( array( 'pending', 'on-hold' ) ) ) {
			return new WP_Error( 'dastyar_paid', 'این سفارش قابل پرداخت نیست.' );
		}
		$total = (float) $order->get_total();
		if ( $this->get_balance( $vendor_id ) < $total ) {
			return new WP_Error( 'dastyar_low_balance', 'موجودی کیف پول کافی نیست.' );
		}
		$this->debit( $vendor_id, $total, $order->get_id(), 'پرداخت دستی فاکتور #' . $order->get_order_number() );
		$order->payment_complete( 'dastyar-wallet' );
		$order->add_order_note( 'فاکتور توسط فروشنده از کیف پول پرداخت شد.' );
		return true;
	}

	/* ------------------------------------------------------------------
	 * بازگشت خودکار وجه هنگام لغو سفارش فروشنده (v1.10.8 — درخواست کاربر)
	 * ---------------------------------------------------------------- */

	/**
	 * «وقتی سفارش فروشنده (از طرف خودش یا مرکز) لغو می‌شود باید مبلغ کل سفارش
	 * خودکار به کیف پولش برگرده» — تا امروز دستی انجام می‌شد.
	 *
	 * روش دقیق و ضددوبار: مانده واقعی سفارش در دفتر کیف پول = جمع بدهی‌ها (پرداخت سفارش)
	 * منهای جمع بستانکاری‌های مرتبط با همان سفارش (مثل بازپرداخت مرجوعی RMA که قبلاً
	 * با ref_order_id همین سفارش واریز شده). فقط آنچه واقعاً بستانکار نشده برمی‌گردد و
	 * متای گارد _dastyar_cancel_refunded اجرای مجدد را غیرممکن می‌کند.
	 *
	 * @param int      $order_id شناسه سفارش مرکز
	 * @param string   $from     وضعیت قبلی
	 * @param string   $to       وضعیت جدید
	 * @param WC_Order $order    شی سفارش
	 */
	public function refund_on_cancel( $order_id, $from, $to, $order ) {
		if ( 'cancelled' !== (string) $to || ! $order instanceof WC_Order ) {
			return;
		}
		$vendor_id = (int) $order->get_meta( '_dastyar_vendor_id' );
		if ( ! $vendor_id ) {
			return; // سفارش عادی فروشگاه مرکز — ربطی به کیف پول فروشنده ندارد
		}
		if ( '' !== (string) $order->get_meta( '_dastyar_cancel_refunded' ) ) {
			return; // قبلاً انجام شده — هرگز دوباره واریز نکن
		}
		// وضعیت‌هایی که یعنی «وجه گرفته شده»؛ لغو از pending/failed یعنی پولی جابه‌جا نشده
		$paid_from = (array) apply_filters( 'dastyar_cancel_refund_from', array( 'processing', 'on-hold', 'posted', 'completed' ) );
		if ( ! in_array( (string) $from, $paid_from, true ) ) {
			return;
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'dastyar_wallet_txns';
		$debits  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE user_id = %d AND ref_order_id = %d AND type = 'debit'", $vendor_id, $order_id ) ); // phpcs:ignore
		$credits = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE user_id = %d AND ref_order_id = %d AND type = 'credit'", $vendor_id, $order_id ) ); // phpcs:ignore
		$total   = (float) $order->get_total();
		$due     = $debits - $credits;

		// سازگاری با رکوردهای قدیمی: پرداخت با کیف پول ثبت شده ولی تراکنشش در جدول نیست
		if ( $due <= 0 && 'dastyar-wallet' === (string) $order->get_payment_method() && $total > 0 ) {
			$due = $total - $credits;
		}
		if ( $due > $total && $total > 0 ) {
			$due = $total; // هرگز بیشتر از مبلغ کل سفارش برنگردان
		}
		$due = max( 0.0, $due );

		// گارد یک‌بارمصرف: حتی اگر مبلغی برنگشت، دیگر این مسیر اجرا نمی‌شود
		$order->update_meta_data( '_dastyar_cancel_refunded', $due > 0 ? $due : 'none' );
		$order->save();

		if ( $due <= 0 ) {
			$order->add_order_note( 'سفارش لغو شد؛ وجهی در کیف پول برای برگشت نداشت (خارج از کیف پول پرداخت شده بود).' );
			return;
		}

		$desc = sprintf( 'بازگشت خودکار وجه لغو سفارش #%s', $order->get_order_number() );
		$this->credit( $vendor_id, $due, $order_id, $desc );
		$order->add_order_note( sprintf( 'مبلغ %s به‌صورت خودکار به کیف پول فروشنده برگشت (لغو سفارش).', function_exists( 'wc_price' ) ? wc_price( $due ) : $due ) );

		// اطلاع‌رسانی به فروشنده در پنل مرکز (همان سازوکار اعلان‌های پنل)
		if ( class_exists( 'Dastyar_Panel_Front' ) ) {
			Dastyar_Panel_Front::notify( $vendor_id, 'ok', sprintf( 'سفارش #%s لغو شد و مبلغ %s به کیف پول شما برگشت.', $order->get_order_number(), function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $due ) ) : $due ) );
		}
		do_action( 'dastyar_cancel_wallet_refunded', $order_id, $vendor_id, $due );
	}

	/* ------------------------------------------------------------------
	 * شارژ کیف پول از طریق درگاه‌های WooCommerce
	 * ---------------------------------------------------------------- */

	/** افزودن محصول شارژ با مبلغ دلخواه به سبد و انتقال به تسویه */
	public function charge_to_cart( $amount ) {
		$pid = (int) get_option( 'dastyar_charge_product_id' );
		if ( ! $pid || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		WC()->cart->empty_cart();
		return (bool) WC()->cart->add_to_cart( $pid, 1, 0, array(), array( 'dastyar_wallet_amount' => (float) $amount ) );
	}

	public function set_charge_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item['dastyar_wallet_amount'] ) && $item['data'] instanceof WC_Product ) {
				$item['data']->set_price( (float) $item['dastyar_wallet_amount'] );
			}
		}
	}

	public function hide_charge_product( $visible, $product_id ) {
		if ( (int) $product_id === (int) get_option( 'dastyar_charge_product_id' ) ) {
			return false;
		}
		return $visible;
	}

	/** بعد از پرداخت سفارش شارژ، موجودی کیف پول افزایش می‌یابد */
	public function credit_paid_charge( $order_id, $from, $to, $order ) {
		if ( ! in_array( $to, array( 'processing', 'completed' ), true ) ) {
			return;
		}
		if ( ! $order instanceof WC_Order || $order->get_meta( '_dastyar_wallet_credited' ) ) {
			return;
		}
		$uid    = (int) $order->get_customer_id();
		$amount = 0;

		if ( $order->get_meta( '_dastyar_wallet_charge' ) ) {
			// روش جدید (v1.4.0): شناسایی سفارش شارژ با متا — بدون وابستگی به محصول
			$uid = (int) $order->get_meta( '_dastyar_charge_for_user' ) ?: $uid;
			foreach ( $order->get_items() as $item ) {
				$amount += (float) $item->get_total();
			}
			if ( $amount <= 0 ) {
				$amount = (float) $order->get_total();
			}
		} else {
			// روش قدیمی: شناسایی با «محصول شارژ کیف پول» (سازگاری با نسخه‌های قبل)
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( $product && (int) $product->get_id() === (int) get_option( 'dastyar_charge_product_id' ) ) {
					$amount += (float) $item->get_total();
				}
			}
		}

		if ( $uid && $amount > 0 ) {
			$this->credit( $uid, $amount, $order_id, 'شارژ کیف پول از طریق سفارش #' . $order->get_order_number() );
			$order->update_meta_data( '_dastyar_wallet_credited', 1 );
			$order->add_order_note( sprintf( 'مبلغ %s به کیف پول فروشنده اضافه شد.', wc_price( $amount ) ) );
			$order->save();
		}
	}
}
