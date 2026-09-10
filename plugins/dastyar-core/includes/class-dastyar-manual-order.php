<?php
/**
 * ثبت سفارش دستی از پنل فروشنده (My Account) — ویزارد مرحله‌ای با لود آجاکسی.
 * برای فروشنده‌هایی که افزونه Connector را روی سایت خود نصب نکرده‌اند.
 *
 * مراحل: ۱) گیرنده ۲) انتخاب محصول (قیمت تامین) ۳) بازبینی سبد ۴) حمل‌ونقل + ثبت نهایی و پرداخت
 * - تمام مراحل با AJAX (admin-ajax) و بدون رفرش صفحه انجام می‌شود
 * - پرداخت: انتخاب درگاه (رفتن مستقیم به PSP) یا پرداخت از کیف پول — همان سفارش استاندارد WooCommerce
 * - سفارش نهایی با _dastyar_vendor_id + _dastyar_manual_order علامت می‌خورد → چرخه دستیار
 * - بدون JavaScript: فلوی قدیمی (فرم‌های معمولی + Checkout ووکامرس) همچنان کار می‌کند
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Manual_Order {

	const SESS_REC  = 'dastyar_manual_recipient'; // اطلاعات گیرنده (مشتری فروشنده)
	const SESS_FLAG = 'dastyar_manual_mode';      // fallback فلوی قدیمی (Checkout)
	const ITEM_FLAG = 'dastyar_manual';           // پرچم آیتم سبد (برای اعمال قیمت تامین)

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'handle_posts' ), 5 );
		add_action( 'wp_ajax_dastyar_moa', array( $this, 'ajax' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_supplier_prices' ), 25 );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'prefill_checkout' ), 10, 2 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ) );
		add_filter( 'woocommerce_ship_to_different_address_checked', array( $this, 'force_ship_to_different' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'order_meta' ), 20, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'after_order_created' ), 10, 3 );
	}

	/** دسترسی امن به سبد/Session — در AJAX هم سشن دستی راه می‌افتد */
	protected function ensure_cart() {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		if ( null === WC()->session && class_exists( 'WC_Session_Handler' ) ) {
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
		}
		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		return WC()->cart && WC()->session;
	}

	protected function recipient() {
		$this->ensure_cart();
		$rec = WC()->session ? WC()->session->get( self::SESS_REC ) : null;
		return is_array( $rec ) ? $rec : array();
	}

	/* ==================================================================
	 *  موتور AJAX ویزارد (همه مراحل بدون رفرش)
	 * ================================================================== */

	public function ajax() {
		check_ajax_referer( 'dastyar_moa', 'nonce' );

		$uid = get_current_user_id();
		if ( ! $uid || ! Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز است.' ), 403 );
		}
		if ( ! $this->ensure_cart() ) {
			wp_send_json_error( array( 'message' => 'سبد خرید در دسترس نیست.' ), 500 );
		}

		$act = sanitize_key( (string) ( $_POST['act'] ?? '' ) );

		switch ( $act ) {

			case 'save_recipient':
				$rec = $this->read_recipient_post();
				if ( is_wp_error( $rec ) ) {
					wp_send_json_error( array( 'message' => $rec->get_error_message() ), 400 );
				}
				WC()->session->set( self::SESS_REC, $rec );
				wp_send_json_success( array( 'summary' => $this->recipient_summary_html( $rec ) ) );

			case 'products':
				wp_send_json_success( array(
					'html' => $this->products_html(
						sanitize_text_field( (string) ( $_POST['s'] ?? '' ) ),
						max( 1, (int) ( $_POST['paged'] ?? 1 ) )
					),
				) );

			case 'add':
				$pid = (int) ( $_POST['product_id'] ?? 0 );
				$vid = (int) ( $_POST['variation_id'] ?? 0 );
				$qty = max( 1, (int) ( $_POST['quantity'] ?? 1 ) );
				$this->add_manual_item( $pid, $vid, $qty );
				wp_send_json_success( array( 'cart_html' => $this->cart_html(), 'count' => $this->manual_count() ) );

			case 'remove':
				$key = sanitize_text_field( (string) ( $_POST['cart_item_key'] ?? '' ) );
				if ( $key && isset( WC()->cart->get_cart()[ $key ] ) ) {
					WC()->cart->remove_cart_item( $key );
				}
				wp_send_json_success( array( 'cart_html' => $this->cart_html(), 'count' => $this->manual_count() ) );

			case 'cart':
				wp_send_json_success( array( 'cart_html' => $this->cart_html(), 'count' => $this->manual_count() ) );

			case 'shipping':
				$rates_html = $this->shipping_html();
				if ( false === $rates_html ) {
					wp_send_json_error( array( 'message' => 'برای آدرس گیرنده روش حمل‌ونقلی پیدا نشد. ابتدا گیرنده را کامل کنید یا با مدیر سایت تماس بگیرید.' ), 400 );
				}
				wp_send_json_success( array(
					'html'        => $rates_html,
					'goods_html'  => wp_kses_post( wc_price( $this->manual_goods() ) ),
					'balance'     => (float) Dastyar::instance()->wallet->get_balance( $uid ),
					'balance_html'=> wp_kses_post( wc_price( Dastyar::instance()->wallet->get_balance( $uid ) ) ),
				) );

			case 'place_order':
				$result = $this->place_order( $uid );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
				}
				/** @var WC_Order $order */
				$order = $result;
				wp_send_json_success( array(
					'order_number' => $order->get_order_number(),
					'total_html'   => wp_kses_post( wc_price( (float) $order->get_total() ) ),
					'goods_html'   => wp_kses_post( wc_price( (float) $order->get_meta( '_dastyar_goods_total' ) ) ),
					'ship_html'    => wp_kses_post( wc_price( (float) $order->get_shipping_total() ) ),
					'ship_title'   => implode( '، ', array_map( function ( $s ) { return $s->get_method_title(); }, $order->get_shipping_methods() ) ),
					'gateways'     => Dastyar_Payments::gateway_picker_html( $order ),
					'balance'      => (float) Dastyar::instance()->wallet->get_balance( $uid ),
					'balance_html' => wp_kses_post( wc_price( Dastyar::instance()->wallet->get_balance( $uid ) ) ),
					'wallet_nonce' => wp_create_nonce( 'dastyar_wallet_pay' ),
					'order_id'     => (int) $order->get_id(),
				) );
		}

		wp_send_json_error( array( 'message' => 'درخواست نامعتبر است.' ), 400 );
	}

	protected function manual_count() {
		$n = 0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item[ self::ITEM_FLAG ] ) ) {
				$n += (int) $item['quantity'];
			}
		}
		return $n;
	}

	protected function manual_goods() {
		$goods = 0.0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item[ self::ITEM_FLAG ] ) ) {
				$goods += Dastyar_Product_Export::supplier_price( $item['data'] ) * (int) $item['quantity'];
			}
		}
		return $goods;
	}

	protected function add_manual_item( $pid, $vid, $qty ) {
		$product = $vid ? wc_get_product( $vid ) : wc_get_product( $pid );
		if ( ! $product || ! $product->is_purchasable() ) {
			return false;
		}
		// تمیز نگه داشتن سبد «سفارش دستی»
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( empty( $item[ self::ITEM_FLAG ] ) ) {
				WC()->cart->remove_cart_item( $key );
			}
		}
		$attrs = array();
		if ( $vid && $product instanceof WC_Product_Variation ) {
			$attrs = $product->get_attributes();
		}
		return (bool) WC()->cart->add_to_cart( $pid, $qty, $vid, $attrs, array( self::ITEM_FLAG => 1 ) );
	}

	/* ----- حمل‌ونقل: همه روش‌های قابل دسترس برای آدرس گیرنده (Shipping Zones) ----- */

	protected function shipping_rates() {
		$rec  = $this->recipient();
		$cart = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item[ self::ITEM_FLAG ] ) ) {
				$cart[] = array( 'data' => $item['data'], 'quantity' => $item['quantity'] );
			}
		}
		if ( ! $rec || ! $cart ) {
			return array();
		}

		$goods   = $this->manual_goods();
		$package = array(
			'destination' => array(
				'country'   => 'IR',
				'state'     => (string) ( $rec['state'] ?? '' ),
				'postcode'  => (string) ( $rec['postcode'] ?? '' ),
				'city'      => (string) ( $rec['city'] ?? '' ),
				'address'   => (string) ( $rec['address_1'] ?? '' ),
				'address_1' => (string) ( $rec['address_1'] ?? '' ),
				'address_2' => (string) ( $rec['address_2'] ?? '' ),
			),
			'contents'        => $cart,
			'contents_cost'   => $goods,
			'applied_coupons' => array(),
			'user'            => array( 'ID' => get_current_user_id() ),
			'cart_subtotal'   => $goods,
		);

		$zone = WC_Shipping_Zones::get_zone_matching_package( $package );
		if ( ! $zone ) {
			return array();
		}
		$rates = array();
		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			foreach ( (array) $method->get_rates_for_package( $package ) as $rate_id => $rate ) {
				$rates[ $rate_id ] = $rate;
			}
		}
		$rates = apply_filters( 'dastyar_shipping_rates', $rates, $package );
		return is_array( $rates ) ? $rates : array();
	}

	protected function shipping_html() {
		$rates = $this->shipping_rates();
		if ( ! $rates ) {
			return false;
		}
		// ارزان‌ترین پیش‌فرض تیک بخورد
		uasort( $rates, function ( $a, $b ) {
			return (float) $a->get_cost() <=> (float) $b->get_cost();
		} );

		$html = '<div class="dastyar-ship-list">';
		$i    = 0;
		foreach ( $rates as $rate_id => $rate ) {
			$i++;
			$html .= sprintf(
				'<label class="dastyar-ship-option"><input type="radio" name="dastyar_ship" value="%s"%s> <span class="dastyar-ship-title">%s</span> <span class="dastyar-ship-cost">%s</span></label>',
				esc_attr( $rate_id ),
				1 === $i ? ' checked' : '',
				esc_html( $rate->get_label() ?: 'حمل و نقل' ),
				wp_kses_post( wc_price( (float) $rate->get_cost() ) )
			);
		}
		$html .= '</div>';
		return $html;
	}

	/* ----- ثبت نهایی سفارش از سبد «سفارش دستی» ----- */

	/** @return WC_Order|WP_Error */
	protected function place_order( $uid ) {
		$this->ensure_cart();
		$rec = $this->recipient();

		$cart_items = array();
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( ! empty( $item[ self::ITEM_FLAG ] ) ) {
				$cart_items[ $key ] = $item;
			}
		}
		if ( ! $rec ) {
			return new WP_Error( 'dastyar_mo_no_recipient', 'ابتدا مشخصات گیرنده را در مرحله ۱ ثبت کنید.' );
		}
		if ( ! $cart_items ) {
			return new WP_Error( 'dastyar_mo_empty', 'سبد سفارش خالی است؛ در مرحله ۲ محصول اضافه کنید.' );
		}

		// روش حمل انتخابی — فقط از میان نرخ‌های محاسبه‌شده سمت سرور (قیمت از کاربر پذیرفته نمی‌شود)
		$rate_id = sanitize_text_field( (string) ( $_POST['ship_rate'] ?? '' ) );
		$rates   = $this->shipping_rates();
		if ( ! isset( $rates[ $rate_id ] ) ) {
			return new WP_Error( 'dastyar_mo_bad_ship', 'روش حمل‌ونقل انتخاب‌شده معتبر نیست؛ مرحله ۴ را تازه‌سازی کنید.' );
		}
		$rate = $rates[ $rate_id ];

		$order = wc_create_order( array( 'customer_id' => $uid, 'created_via' => 'dastyar-manual' ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$goods = 0.0;
		foreach ( $cart_items as $key => $item ) {
			$unit = Dastyar_Product_Export::supplier_price( $item['data'] );
			$qty  = (int) $item['quantity'];
			$order_item = new WC_Order_Item_Product();
			$order_item->set_product( $item['data'] );
			$order_item->set_quantity( $qty );
			$order_item->set_subtotal( $unit * $qty );
			$order_item->set_total( $unit * $qty );
			$order->add_item( $order_item );
			$goods += $unit * $qty;
			unset( $cart_items[ $key ] );
			WC()->cart->remove_cart_item( $key );
		}

		// آدرس گیرنده = مشتری نهایی فروشنده
		$order->set_shipping_first_name( $rec['first_name'] );
		$order->set_shipping_last_name( $rec['last_name'] );
		$order->set_shipping_phone( $rec['phone'] );
		$order->set_shipping_country( 'IR' );
		$order->set_shipping_state( $rec['state'] ?? '' );
		$order->set_shipping_city( $rec['city'] );
		$order->set_shipping_address_1( $rec['address_1'] );
		$order->set_shipping_address_2( $rec['address_2'] ?? '' );
		$order->set_shipping_postcode( $rec['postcode'] ?? '' );

		// صورتحساب = خود فروشنده
		Dastyar_Payments::fill_billing_from_user( $order, $uid );

		$ship = new WC_Order_Item_Shipping();
		$ship->set_method_title( $rate->get_label() ?: 'حمل و نقل' );
		$ship->set_method_id( $rate->get_method_id() );
		$ship->set_total( (float) $rate->get_cost() );
		$order->add_item( $ship );

		$order->update_meta_data( '_dastyar_vendor_id', $uid );
		$order->update_meta_data( '_dastyar_manual_order', 1 );
		$order->update_meta_data( '_dastyar_goods_total', $goods );
		$order->update_meta_data( '_dastyar_shipping_total', (float) $rate->get_cost() );

		$order->calculate_totals();
		$order->set_status( 'pending', 'سفارش دستی فروشنده (ویزارد My Account) ثبت شد.' );
		$order->add_order_note( 'سفارش دستی از پنل فروشنده ثبت شد — در انتظار پرداخت فروشنده (درگاه یا کیف پول).' );
		$order->save();

		if ( WC()->session ) {
			WC()->session->set( self::SESS_FLAG, 0 );
		}
		do_action( 'dastyar_manual_order_created', $order->get_id(), $uid );

		return $order;
	}

	/* ==================================================================
	 *  رندر ویزارد در My Account
	 * ================================================================== */

	public function render_account( $uid ) {
		if ( ! $this->ensure_cart() ) {
			echo '<p>سبد خرید در دسترس نیست.</p>';
			return;
		}

		$rec = $this->recipient();

		echo '<h3>ثبت سفارش دستی</h3>';
		echo '<p class="description">در ۴ مرحله سفارش مشتری‌تان را ثبت کنید: کالاها با <strong>قیمت تامین</strong>، سپس حمل‌ونقل و در انتها پرداخت — همه بدون رفرش صفحه.</p>';

		// نوار پیشرفت مراحل
		echo '<div class="dastyar-steps" id="dastyar-steps">';
		foreach ( array( 1 => 'مشخصات گیرنده', 2 => 'انتخاب محصولات', 3 => 'بازبینی سبد', 4 => 'حمل‌ونقل و پرداخت' ) as $n => $label ) {
			printf(
				'<button type="button" class="dastyar-step%s" data-step="%d"><span class="dastyar-step-num">%d</span><span class="dastyar-step-label">%s</span></button>',
				1 === $n ? ' active done-when' : '',
				(int) $n,
				(int) $n,
				esc_html( $label )
			);
		}
		echo '</div>';

		echo '<div id="dastyar-wizard">';

		// ---------- مرحله ۱ ----------
		echo '<section class="dastyar-pane active" data-pane="1">';
		echo '<div class="dastyar-card"><h4>مرحله ۱) مشخصات گیرنده (مشتری نهایی شما)</h4>';
		echo '<div id="dastyar-rec-summary-slot">' . ( $rec ? $this->recipient_summary_html( $rec ) : '' ) . '</div>';
		$this->recipient_form_html( $rec, true );
		echo '<p class="dastyar-step-nav"><button type="button" class="button dastyar-btn-green dastyar-next" data-to="2" data-needs="recipient">مرحله بعد: انتخاب محصولات ←</button></p>';
		echo '</div></section>';

		// ---------- مرحله ۲ ----------
		echo '<section class="dastyar-pane" data-pane="2">';
		echo '<div class="dastyar-card"><h4>مرحله ۲) افزودن محصول به سفارش (قیمت تامین)</h4>';
		echo '<div class="dastyar-search-form"><input type="search" id="dastyar-mo-search" placeholder="جستجوی محصول…" class="input-text"><button type="button" class="button" id="dastyar-mo-search-btn">جستجو</button></div>';
		echo '<div id="dastyar-mo-products">' . $this->products_html( '', 1 ) . '</div>';
		echo '<p class="dastyar-step-nav"><button type="button" class="button dastyar-prev" data-to="1">→ مرحله قبل</button> <button type="button" class="button dastyar-btn-green dastyar-next" data-to="3" data-needs="cart">مرحله بعد: بازبینی سبد ←</button></p>';
		echo '</div></section>';

		// ---------- مرحله ۳ ----------
		echo '<section class="dastyar-pane" data-pane="3">';
		echo '<div class="dastyar-card"><h4>مرحله ۳) بازبینی سبد سفارش</h4>';
		echo '<div id="dastyar-mo-cart">' . $this->cart_html() . '</div>';
		echo '<p class="dastyar-step-nav"><button type="button" class="button dastyar-prev" data-to="2">→ مرحله قبل</button> <button type="button" class="button dastyar-btn-green dastyar-next" data-to="4" data-needs="cart">مرحله بعد: حمل‌ونقل و پرداخت ←</button></p>';
		echo '</div></section>';

		// ---------- مرحله ۴ ----------
		echo '<section class="dastyar-pane" data-pane="4">';
		echo '<div class="dastyar-card"><h4>مرحله ۴) حمل‌ونقل، ثبت نهایی و پرداخت</h4>';
		echo '<div id="dastyar-mo-shipping"><p class="description">در حال دریافت روش‌های ارسال برای آدرس گیرنده…</p></div>';
		echo '<div id="dastyar-mo-final"></div>';
		echo '<p class="dastyar-step-nav"><button type="button" class="button dastyar-prev" data-to="3">→ مرحله قبل</button></p>';
		echo '</div></section>';

		echo '</div>'; // #dastyar-wizard

		$this->wizard_js( $rec );
	}

	protected function recipient_summary_html( array $rec ) {
		return sprintf(
			'<div class="dastyar-rec-summary">✔ گیرنده ثبت شده: <strong>%s %s</strong> — %s، %s — <span dir="ltr">%s</span></div>',
			esc_html( $rec['first_name'] ?? '' ),
			esc_html( $rec['last_name'] ?? '' ),
			esc_html( $rec['state'] ?? '' ),
			esc_html( $rec['city'] ?? '' ),
			esc_html( $rec['phone'] ?? '' )
		);
	}

	protected function read_recipient_post() {
		$rec = array(
			'first_name' => sanitize_text_field( wp_unslash( $_POST['rec_first_name'] ?? '' ) ),
			'last_name'  => sanitize_text_field( wp_unslash( $_POST['rec_last_name'] ?? '' ) ),
			'phone'      => sanitize_text_field( wp_unslash( $_POST['rec_phone'] ?? '' ) ),
			'state'      => sanitize_text_field( wp_unslash( $_POST['rec_state'] ?? '' ) ),
			'city'       => sanitize_text_field( wp_unslash( $_POST['rec_city'] ?? '' ) ),
			'address_1'  => sanitize_text_field( wp_unslash( $_POST['rec_address_1'] ?? '' ) ),
			'address_2'  => sanitize_text_field( wp_unslash( $_POST['rec_address_2'] ?? '' ) ),
			'postcode'   => sanitize_text_field( wp_unslash( $_POST['rec_postcode'] ?? '' ) ),
		);
		if ( '' === $rec['first_name'] || '' === $rec['last_name'] || '' === $rec['phone'] || '' === $rec['city'] || '' === $rec['address_1'] ) {
			return new WP_Error( 'dastyar_mo_recipient', 'نام، نام خانوادگی، موبایل، شهر و آدرس گیرنده الزامی است.' );
		}
		return $rec;
	}

	protected function recipient_form_html( array $rec, $ajax_hint = false ) {
		echo '<form method="post" class="dastyar-manual-form" id="dastyar-rec-form">';
		wp_nonce_field( 'dastyar_manual' );
		echo '<input type="hidden" name="dastyar_manual_action" value="save_recipient">';
		echo '<input type="hidden" name="dastyar_back" value="' . esc_url( $this->current_url() ) . '">';

		echo '<div class="dastyar-grid2">';
		printf( '<p class="woocommerce-form-row form-row form-row-first"><label>نام گیرنده <b class="required">*</b></label><input type="text" name="rec_first_name" class="input-text" value="%s" required></p>', esc_attr( $rec['first_name'] ?? '' ) );
		printf( '<p class="woocommerce-form-row form-row form-row-last"><label>نام خانوادگی <b class="required">*</b></label><input type="text" name="rec_last_name" class="input-text" value="%s" required></p>', esc_attr( $rec['last_name'] ?? '' ) );
		echo '</div><div class="dastyar-grid2">';
		printf( '<p class="woocommerce-form-row form-row form-row-first"><label>موبایل گیرنده <b class="required">*</b></label><input type="tel" name="rec_phone" class="input-text" style="direction:ltr" value="%s" required></p>', esc_attr( $rec['phone'] ?? '' ) );

		$states = WC()->countries ? WC()->countries->get_states( 'IR' ) : array();
		echo '<p class="woocommerce-form-row form-row form-row-last"><label>استان</label>';
		if ( is_array( $states ) && $states ) {
			echo '<select name="rec_state" class="input-text">';
			echo '<option value="">— انتخاب استان —</option>';
			foreach ( $states as $code => $label ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $code ), selected( (string) ( $rec['state'] ?? '' ), (string) $code, false ), esc_html( $label ) );
			}
			echo '</select>';
		} else {
			printf( '<input type="text" name="rec_state" class="input-text" value="%s">', esc_attr( $rec['state'] ?? '' ) );
		}
		echo '</p></div><div class="dastyar-grid2">';
		printf( '<p class="woocommerce-form-row form-row form-row-first"><label>شهر <b class="required">*</b></label><input type="text" name="rec_city" class="input-text" value="%s" required></p>', esc_attr( $rec['city'] ?? '' ) );
		printf( '<p class="woocommerce-form-row form-row form-row-last"><label>کدپستی</label><input type="text" name="rec_postcode" class="input-text" style="direction:ltr" value="%s"></p>', esc_attr( $rec['postcode'] ?? '' ) );
		echo '</div>';
		printf( '<p class="woocommerce-form-row form-row form-row-wide"><label>آدرس کامل <b class="required">*</b></label><input type="text" name="rec_address_1" class="input-text" value="%s" required></p>', esc_attr( $rec['address_1'] ?? '' ) );
		printf( '<p class="woocommerce-form-row form-row form-row-wide"><label>آدرس (ادامه)</label><input type="text" name="rec_address_2" class="input-text" value="%s"></p>', esc_attr( $rec['address_2'] ?? '' ) );
		echo '<p style="margin-bottom:0"><button type="submit" class="button dastyar-btn-green" id="dastyar-rec-save">ذخیره گیرنده</button> <span id="dastyar-rec-msg"></span></p>';
		echo '</form>';
	}

	protected function products_html( $search, $paged ) {
		$per  = 8;
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per,
			'paged'          => $paged,
			'fields'         => 'ids',
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$query = new WP_Query( $args );

		ob_start();
		if ( ! $query->posts ) {
			echo '<p>محصولی یافت نشد.</p>';
			return ob_get_clean();
		}

		echo '<div class="dastyar-products-grid">';
		foreach ( $query->posts as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product || ! $product->is_purchasable() ) {
				continue;
			}
			$img = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );

			echo '<div class="dastyar-product-card">';
			if ( $img ) {
				printf( '<img src="%s" alt="">', esc_url( $img ) );
			}
			printf( '<div class="dastyar-product-name">%s</div>', esc_html( $product->get_name() ) );

			// فرم واقعی (fallback بدون JS) — با JS با ajax کار می‌کند
			echo '<form method="post" class="dastyar-mo-add-form">';
			wp_nonce_field( 'dastyar_manual' );
			echo '<input type="hidden" name="dastyar_manual_action" value="add_item">';
			echo '<input type="hidden" name="dastyar_back" value="' . esc_url( $this->current_url() ) . '">';
			printf( '<input type="hidden" name="product_id" value="%d">', (int) $pid );

			if ( $product->is_type( 'variable' ) ) {
				echo '<select name="variation_id" class="dastyar-var-select" required>';
				echo '<option value="">— انتخاب نوع —</option>';
				foreach ( $product->get_children() as $vid ) {
					$v = wc_get_product( $vid );
					if ( ! $v || ! $v->is_purchasable() ) {
						continue;
					}
					$attrs = wc_get_formatted_variation( $v, true, false, false );
					printf(
						'<option value="%d">%s — %s</option>',
						(int) $vid,
						esc_html( trim( wp_strip_all_tags( $attrs ) ) ?: '#' . $vid ),
						wp_kses_post( wc_price( Dastyar_Product_Export::supplier_price( $v ) ) )
					);
				}
				echo '</select>';
			} else {
				printf( '<div class="dastyar-product-price">%s</div>', wp_kses_post( wc_price( Dastyar_Product_Export::supplier_price( $product ) ) ) );
			}

			echo '<div class="dastyar-add-row">';
			echo '<input type="number" name="quantity" value="1" min="1" class="dastyar-qty">';
			echo '<button type="submit" class="button dastyar-btn-green">＋ افزودن</button>';
			echo '</div></form></div>';
		}
		echo '</div>';

		// (v1.6.0) صفحه‌بندی عددی زیر باکس محصولات حذف شد — کاربر فقط با «جستجو» به محصول می‌رسد.
		// نکته UX: اگر تعداد نتایج بیش از یک صفحه بود، راهنمای جستجوی دقیق‌تر نمایش داده می‌شود.
		if ( (int) $query->max_num_pages > 1 ) {
			echo '<p class="dastyar-search-form dastyar-more-hint" style="margin:10px 0 0"><span class="dastyar-more-hint-txt">نتایج بیشتری هم وجود دارد؛ برای دیدن آن‌ها جستجوی دقیق‌تری انجام دهید.</span></p>';
		}
		return ob_get_clean();
	}

	protected function cart_html() {
		$items = array();
		$goods = 0.0;
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( empty( $item[ self::ITEM_FLAG ] ) ) {
				continue;
			}
			$unit    = Dastyar_Product_Export::supplier_price( $item['data'] );
			$items[] = array( 'key' => $key ) + $item + array( '_unit' => $unit );
			$goods  += $unit * (int) $item['quantity'];
		}

		ob_start();
		if ( ! $items ) {
			echo '<p>سبد سفارش دستی خالی است — از مرحله ۲ محصول اضافه کنید.</p>';
			return ob_get_clean();
		}

		echo '<table class="shop_table"><thead><tr><th>محصول</th><th>تعداد</th><th>فی تامین</th><th>جمع</th><th></th></tr></thead><tbody>';
		foreach ( $items as $item ) {
			echo '<tr>';
			printf( '<td>%s</td>', esc_html( $item['data']->get_name() ) );
			printf( '<td>%d</td>', (int) $item['quantity'] );
			printf( '<td>%s</td>', wp_kses_post( wc_price( $item['_unit'] ) ) );
			printf( '<td>%s</td>', wp_kses_post( wc_price( $item['_unit'] * (int) $item['quantity'] ) ) );
			echo '<td><form method="post" class="dastyar-mo-remove-form" style="display:inline">';
			wp_nonce_field( 'dastyar_manual' );
			echo '<input type="hidden" name="dastyar_manual_action" value="remove_item">';
			echo '<input type="hidden" name="dastyar_back" value="' . esc_url( $this->current_url() ) . '">';
			printf( '<input type="hidden" name="cart_item_key" value="%s">', esc_attr( $item['key'] ) );
			echo '<button class="button dastyar-btn-remove" title="حذف">✕</button></form></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		printf( '<p class="dastyar-goods-total">جمع کالاها (قیمت تامین): <strong>%s</strong></p>', wp_kses_post( wc_price( $goods ) ) );
		echo '<p class="description">هزینه حمل‌ونقل در مرحله ۴ بر اساس آدرس گیرنده محاسبه می‌شود.</p>';

		// fallback کامل بدون JS: ادامه با Checkout استاندارد ووکامرس (فلوی قدیمی)
		echo '<noscript><form method="post">';
		wp_nonce_field( 'dastyar_manual' );
		echo '<input type="hidden" name="dastyar_manual_action" value="checkout">';
		echo '<button type="submit" class="button dastyar-btn-green dastyar-btn-big">ادامه و پرداخت (تسویه‌حساب) ←</button></form></noscript>';

		return ob_get_clean();
	}

	/* ----- جاوااسکریپت ویزارد (Vanilla JS + fetch) ----- */

	protected function wizard_js( $rec ) {
		?>
		<script>
		(function(){
			var AJAX_URL = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			var NONCE    = '<?php echo esc_js( wp_create_nonce( 'dastyar_moa' ) ); ?>';
			var wizard   = document.getElementById('dastyar-wizard');
			if (!wizard) return;

			var state = {
				recipientSaved: <?php echo $rec ? 'true' : 'false'; ?>,
				search: '',
				placed: false
			};

			function post(act, extra){
				var fd = new FormData();
				fd.append('action','dastyar_moa');
				fd.append('nonce', NONCE);
				fd.append('act', act);
				extra = extra || {};
				Object.keys(extra).forEach(function(k){ fd.append(k, extra[k]); });
				return fetch(AJAX_URL, {method:'POST', credentials:'same-origin', body:fd}).then(function(r){return r.json();});
			}
			function goStep(n){
				wizard.querySelectorAll('.dastyar-pane').forEach(function(p){ p.classList.toggle('active', p.dataset.pane == n); });
				document.querySelectorAll('#dastyar-steps .dastyar-step').forEach(function(s){
					s.classList.toggle('active', s.dataset.step == n);
					if (parseInt(s.dataset.step) < parseInt(n)) s.classList.add('done'); else if (parseInt(s.dataset.step) > parseInt(n)) s.classList.remove('done');
				});
				if (n == 3) { post('cart').then(function(res){ if(res.success) setHtml('dastyar-mo-cart', res.data.cart_html); }); }
				if (n == 4 && !state.placed) { loadShipping(); }
				window.scrollTo({top: wizard.offsetTop - 40, behavior:'smooth'});
			}
			function setHtml(id, html){ var el = document.getElementById(id); if (el) el.innerHTML = html; }
			function toast(msg, ok){
				var t = document.createElement('div');
				t.className = 'dastyar-toast' + (ok === false ? ' error' : '');
				t.textContent = msg;
				document.body.appendChild(t);
				setTimeout(function(){ t.classList.add('show'); }, 10);
				setTimeout(function(){ t.classList.remove('show'); setTimeout(function(){ t.remove(); }, 400); }, 3000);
			}
			function cartCount(){ post('cart').then(function(res){ if(res.success){ setHtml('dastyar-mo-cart', res.data.cart_html); } }); }
			function loadProducts(s, p){ post('products', {s:s||'', paged:p||1}).then(function(res){ if(res.success) setHtml('dastyar-mo-products', res.data.html); }); }
			function loadShipping(){
				post('shipping').then(function(res){
					if (!res.success){ setHtml('dastyar-mo-shipping', '<p class="dastyar-alert-inline">'+(res.data && res.data.message ? res.data.message : 'خطا')+'</p>'); return; }
					setHtml('dastyar-mo-shipping',
						'<p class="dastyar-goods-total">جمع کالاها: <strong>'+res.data.goods_html+'</strong> — روش ارسال را انتخاب کنید:</p>' +
						res.data.html +
						'<p style="margin-top:14px"><button type="button" class="button dastyar-btn-green dastyar-btn-big" id="dastyar-mo-place">ثبت نهایی سفارش و رفتن به پرداخت ←</button></p>');
				});
			}

			// ناوبری مراحل
			document.addEventListener('click', function(e){
				var next = e.target.closest('.dastyar-next');
				if (next){
					var need = next.dataset.needs;
					if (need === 'recipient' && !state.recipientSaved){ toast('ابتدا «ذخیره گیرنده» را بزنید.', false); return; }
					if (need === 'cart' && !document.querySelector('#dastyar-mo-cart table')){ toast('سبد خالی است؛ ابتدا محصول اضافه کنید.', false); return; }
					goStep(next.dataset.to); return;
				}
				var prev = e.target.closest('.dastyar-prev');
				if (prev){ goStep(prev.dataset.to); return; }
				var step = e.target.closest('#dastyar-steps .dastyar-step');
				if (step){ goStep(step.dataset.step); return; }

				// جستجو
				if (e.target.closest('#dastyar-mo-search-btn')){
					var s = document.getElementById('dastyar-mo-search');
					state.search = s ? s.value : '';
					loadProducts(state.search, 1); return;
				}
				// صفحه‌بندی محصولات (آجاکس)


				// ثبت نهایی سفارش
				if (e.target.closest('#dastyar-mo-place')){
					var btn = e.target.closest('#dastyar-mo-place');
					var ship = wizard.querySelector('input[name="dastyar_ship"]:checked');
					if (!ship){ toast('روش حمل‌ونقل را انتخاب کنید.', false); return; }
					btn.disabled = true; btn.textContent = '⏳ در حال ثبت سفارش…';
					post('place_order', {ship_rate: ship.value}).then(function(res){
						btn.disabled = false; btn.textContent = 'ثبت نهایی سفارش و رفتن به پرداخت ←';
						if (!res || !res.success){ toast(res && res.data && res.data.message ? res.data.message : 'خطا در ثبت سفارش.', false); return; }
						state.placed = true;
						var d = res.data;
						var walletBtn = '';
						if (d.balance >= 0) {
							walletBtn =
								'<form method="post" class="dastyar-wallet-inline">'+
								'<input type="hidden" name="_wpnonce" value="'+d.wallet_nonce+'">'+
								'<input type="hidden" name="dastyar_action" value="wallet_pay">'+
								'<input type="hidden" name="order_id" value="'+d.order_id+'">'+
								'<button type="submit" class="button dastyar-btn-ghost-g">💳 پرداخت از کیف پول (موجودی: '+d.balance_html+')</button></form>';
						}
						setHtml('dastyar-mo-final',
							'<div class="dastyar-done-box">'+
							'<h4>✔ سفارش #'+d.order_number+' ثبت شد</h4>'+
							'<table class="shop_table"><tbody>'+
							'<tr><th>جمع کالاها (تامین)</th><td>'+d.goods_html+'</td></tr>'+
							'<tr><th>حمل‌ونقل ('+d.ship_title+')</th><td>'+d.ship_html+'</td></tr>'+
							'<tr><th>مبلغ قابل پرداخت</th><td><strong>'+d.total_html+'</strong></td></tr>'+
							'</tbody></table>'+
							'<h4 style="margin-top:14px">پرداخت سفارش</h4>'+
							walletBtn + d.gateways +
							'</div>');
						setHtml('dastyar-mo-shipping', '');
						toast('سفارش با موفقیت ثبت شد؛ حالا پرداخت کنید.');
						goStep(4);
					});
					return;
				}
			});

			// ذخیره گیرنده آجاکسی
			document.addEventListener('submit', function(e){
				if (e.target && e.target.id === 'dastyar-rec-form'){
					e.preventDefault();
					var f = e.target;
					var extra = {};
					['rec_first_name','rec_last_name','rec_phone','rec_state','rec_city','rec_address_1','rec_address_2','rec_postcode'].forEach(function(n){
						var el = f.querySelector('[name="'+n+'"]'); extra[n] = el ? el.value : '';
					});
					post('save_recipient', extra).then(function(res){
						if (res.success){
							state.recipientSaved = true;
							setHtml('dastyar-rec-summary-slot', res.data.summary);
							toast('مشخصات گیرنده ذخیره شد.');
						} else {
							toast(res.data && res.data.message ? res.data.message : 'خطا', false);
						}
					});
					return;
				}
				// افزودن محصول آجاکسی
				var add = e.target.closest('.dastyar-mo-add-form');
				if (add){
					e.preventDefault();
					post('add', {
						product_id:  add.querySelector('[name="product_id"]').value,
						variation_id:(add.querySelector('[name="variation_id"]')||{}).value || 0,
						quantity:    add.querySelector('[name="quantity"]').value || 1
					}).then(function(res){
						if (res.success){ setHtml('dastyar-mo-cart', res.data.cart_html); toast('به سبد سفارش اضافه شد ('+res.data.count+' قلم).'); }
						else { toast('افزودن ناموفق بود.', false); }
					});
					return;
				}
				// حذف قلم آجاکسی
				var rem = e.target.closest('.dastyar-mo-remove-form');
				if (rem){
					e.preventDefault();
					post('remove', {cart_item_key: rem.querySelector('[name="cart_item_key"]').value}).then(function(res){
						if (res.success){ setHtml('dastyar-mo-cart', res.data.cart_html); }
					});
					return;
				}
			});

			// جستجو با Enter
			var si = document.getElementById('dastyar-mo-search');
			if (si) si.addEventListener('keydown', function(e){ if (e.key === 'Enter'){ e.preventDefault(); state.search = si.value; loadProducts(state.search, 1); } });
		})();
		</script>
		<?php
	}

	/* ==================================================================
	 *  فلوی قدیمی (Fallback بدون JS) — بدون تغییر رفتار قبلی
	 * ================================================================== */

	public function handle_posts() {
		if ( ! is_account_page() || ! is_user_logged_in() || empty( $_POST['dastyar_manual_action'] ) ) {
			return;
		}
		$uid = get_current_user_id();
		if ( ! Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dastyar_manual' ) ) {
			wc_add_notice( 'نشست نامعتبر است.', 'error' );
			return;
		}
		if ( ! $this->ensure_cart() ) {
			wc_add_notice( 'سبد خرید در دسترس نیست.', 'error' );
			return;
		}

		$action      = sanitize_key( $_POST['dastyar_manual_action'] );
		$endpoint    = wc_get_account_endpoint_url( 'dastyar-manual-order' );
		$redirect    = esc_url_raw( (string) ( $_POST['dastyar_back'] ?? '' ) );
		$redirect_to = $redirect && strpos( $redirect, home_url() ) === 0 ? $redirect : $endpoint;

		switch ( $action ) {

			case 'save_recipient':
				$rec = $this->read_recipient_post();
				if ( is_wp_error( $rec ) ) {
					wc_add_notice( $rec->get_error_message(), 'error' );
					break;
				}
				WC()->session->set( self::SESS_REC, $rec );
				wc_add_notice( 'مشخصات گیرنده ذخیره شد. حالا محصولات را اضافه کنید.' );
				break;

			case 'add_item':
				$added = $this->add_manual_item(
					(int) ( $_POST['product_id'] ?? 0 ),
					(int) ( $_POST['variation_id'] ?? 0 ),
					max( 1, (int) ( $_POST['quantity'] ?? 1 ) )
				);
				wc_add_notice( $added ? 'محصول با قیمت تامین به سبد سفارش اضافه شد.' : 'افزودن محصول ناموفق بود.', $added ? 'success' : 'error' );
				break;

			case 'remove_item':
				$key = sanitize_text_field( (string) ( $_POST['cart_item_key'] ?? '' ) );
				if ( $key && isset( WC()->cart->get_cart()[ $key ] ) ) {
					WC()->cart->remove_cart_item( $key );
					wc_add_notice( 'قلم از سبد حذف شد.' );
				}
				break;

			case 'checkout':
				$manual_items = 0;
				foreach ( WC()->cart->get_cart() as $item ) {
					if ( ! empty( $item[ self::ITEM_FLAG ] ) ) {
						$manual_items += (int) $item['quantity'];
					}
				}
				if ( ! $manual_items ) {
					wc_add_notice( 'ابتدا حداقل یک محصول به سبد سفارش اضافه کنید.', 'error' );
					break;
				}
				if ( ! $this->recipient() ) {
					wc_add_notice( 'ابتدا مشخصات گیرنده (مشتری نهایی) را ثبت کنید.', 'error' );
					break;
				}
				WC()->session->set( self::SESS_FLAG, 1 );
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
		}

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/* ------------------------------------------------------------------
	 * قیمت اقلام سبد = قیمت تامین‌کننده (فقط اقلام پرچم‌دار این بخش) — فلوی قدیمی
	 * ---------------------------------------------------------------- */

	public function set_supplier_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item[ self::ITEM_FLAG ] ) && $item['data'] instanceof WC_Product ) {
				$item['data']->set_price( Dastyar_Product_Export::supplier_price( $item['data'] ) );
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Checkout (فلوی قدیمی fallback): پیش‌پرکردن آدرس + فیلد تلفن گیرنده
	 * ---------------------------------------------------------------- */

	public function prefill_checkout( $value, $input ) {
		if ( '' !== (string) $value || ! $this->manual_checkout_active() ) {
			return $value;
		}
		$rec = $this->recipient();
		$map = array(
			'shipping_first_name' => $rec['first_name'] ?? '',
			'shipping_last_name'  => $rec['last_name'] ?? '',
			'shipping_phone'      => $rec['phone'] ?? '',
			'shipping_state'      => $rec['state'] ?? '',
			'shipping_city'       => $rec['city'] ?? '',
			'shipping_address_1'  => $rec['address_1'] ?? '',
			'shipping_address_2'  => $rec['address_2'] ?? '',
			'shipping_postcode'   => $rec['postcode'] ?? '',
		);
		if ( isset( $map[ $input ] ) && '' !== $map[ $input ] ) {
			return $map[ $input ];
		}
		return $value;
	}

	public function checkout_fields( $fields ) {
		if ( ! $this->manual_checkout_active() ) {
			return $fields;
		}
		if ( ! isset( $fields['shipping']['shipping_phone'] ) ) {
			$fields['shipping']['shipping_phone'] = array(
				'label'    => 'تلفن گیرنده',
				'required' => true,
				'class'    => array( 'form-row-first' ),
				'priority' => 25,
				'clear'    => true,
				'type'     => 'tel',
			);
		}
		return $fields;
	}

	public function force_ship_to_different( $checked ) {
		if ( $this->manual_checkout_active() && $this->recipient() ) {
			return true;
		}
		return $checked;
	}

	protected function manual_checkout_active() {
		$this->ensure_cart();
		return WC()->session && (bool) WC()->session->get( self::SESS_FLAG );
	}

	/* ------------------------------------------------------------------
	 * علامت‌گذاری سفارش نهایی (فلوی قدیمی fallback از طریق Checkout)
	 * ---------------------------------------------------------------- */

	public function order_meta( $order, $data ) {
		if ( ! $this->manual_checkout_active() ) {
			return;
		}
		$uid = get_current_user_id();
		if ( ! $uid ) {
			return;
		}

		$goods = 0.0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item[ self::ITEM_FLAG ] ) ) {
				$goods += Dastyar_Product_Export::supplier_price( $item['data'] ) * (int) $item['quantity'];
			}
		}

		$order->update_meta_data( '_dastyar_vendor_id', $uid );
		$order->update_meta_data( '_dastyar_manual_order', 1 );
		$order->update_meta_data( '_dastyar_goods_total', $goods );
	}

	public function after_order_created( $order_id, $posted_data, $order ) {
		if ( ! $order instanceof WC_Order || ! $order->get_meta( '_dastyar_manual_order' ) ) {
			return;
		}
		$order->update_meta_data( '_dastyar_shipping_total', (float) $order->get_shipping_total() );
		$order->add_order_note( 'سفارش دستی فروشنده — از پنل کاربری (My Account) ثبت و با درگاه/کیف‌پول ووکامرس پرداخت شد.' );
		if ( WC()->session ) {
			WC()->session->set( self::SESS_FLAG, 0 );
		}
	}

	protected function current_url() {
		$url = wc_get_account_endpoint_url( 'dastyar-manual-order' );
		if ( ! empty( $_GET['ds'] ) || ! empty( $_GET['dpage'] ) ) {
			$url = add_query_arg( array(
				'ds'    => sanitize_text_field( (string) ( $_GET['ds'] ?? '' ) ),
				'dpage' => max( 1, (int) ( $_GET['dpage'] ?? 1 ) ),
			), $url );
		}
		return $url;
	}
}
