<?php
/**
 * ساخت Order استاندارد WooCommerce از سفارش ارسالی سایت فروشنده.
 *
 * - سفارش با وضعیت «در انتظار پرداخت» (pending) ساخته می‌شود.
 * - قیمت اقلام = قیمت تامین‌کننده (مبلغی که فروشنده بدهکار است).
 * - هزینه حمل = طبق تعرفه WooCommerce سایت مرکزی (مناطق حمل‌ونقل) محاسبه می‌شود.
 * - مبلغ نهایی صورتحساب/کسر کیف پول = جمع کالاها + هزینه حمل مرکزی.
 * - تفکیک کالا/حمل در متا ثبت و در پنل نمایش داده می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Order_Import {

	/**
	 * @param array $data      payload ارسالی Connector
	 * @param int   $vendor_id شناسه کاربر فروشنده در سایت مرکزی
	 * @return array|WP_Error
	 */
	public static function create( array $data, $vendor_id ) {
		$remote_id = (int) ( $data['remote_order_id'] ?? 0 );
		if ( ! $remote_id ) {
			return new WP_Error( 'dastyar_bad_payload', 'remote_order_id الزامی است.', array( 'status' => 400 ) );
		}
		if ( empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return new WP_Error( 'dastyar_bad_payload', 'اقلام سفارش ارسال نشده است.', array( 'status' => 400 ) );
		}

		$remote_site = esc_url_raw( (string) ( $data['remote_site'] ?? Dastyar::instance()->vendors->site_url( $vendor_id ) ) );
		$ref         = $vendor_id . ':' . $remote_site . ':' . $remote_id;

		// Idempotency: جلوگیری از ثبت تکراری سفارش
		$existing = wc_get_orders( array(
			'meta_key'   => '_dastyar_remote_ref',
			'meta_value' => $ref,
			'limit'      => 1,
			'return'     => 'ids',
		) );
		if ( $existing ) {
			$order = wc_get_order( $existing[0] );
			return self::response( $order, true );
		}

		$order = wc_create_order( array(
			'customer_id' => $vendor_id,
			'created_via' => 'dastyar-api',
		) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		// آدرس مشتری نهایی (مشتری سایت فروشنده)
		foreach ( array( 'billing', 'shipping' ) as $type ) {
			$address = isset( $data[ $type ] ) && is_array( $data[ $type ] ) ? $data[ $type ] : array();
			$order->set_address( self::clean_address( $address ), $type );
		}

		// اقلام — بر اساس Product/Variation مرکزی با قیمت تامین‌کننده
		$missing    = array();
		$goods_cost = 0.0;
		$contents   = array(); // برای محاسبه حمل زون‌محور
		foreach ( $data['items'] as $item ) {
			$pid = (int) ( $item['product_id'] ?? 0 );
			$vid = (int) ( $item['variation_id'] ?? 0 );
			$qty = max( 1, (int) ( $item['quantity'] ?? 1 ) );

			$product = $vid ? wc_get_product( $vid ) : wc_get_product( $pid );
			if ( ! $product ) {
				$missing[] = $vid ? $vid : $pid;
				continue;
			}

			$unit        = Dastyar_Product_Export::supplier_price( $product );
			$goods_cost += $unit * $qty;
			$contents[]  = array( 'data' => $product, 'quantity' => $qty );

			$order_item = new WC_Order_Item_Product();
			$order_item->set_product( $product );
			$order_item->set_quantity( $qty );
			$order_item->set_subtotal( $unit * $qty );
			$order_item->set_total( $unit * $qty );
			if ( isset( $item['price'] ) ) {
				$order_item->add_meta_data( '_dastyar_vendor_sale_price', (float) $item['price'], true );
			}
			$order->add_item( $order_item );
		}

		if ( $missing ) {
			$order->add_order_note( 'اقلامی در سایت مرکزی یافت نشدند: ' . implode( ', ', $missing ) );
		}

		// --- هزینه حمل طبق تنظیمات WooCommerce مرکز (Shipping Zones) ---
		$destination_address = $order->get_address( 'shipping' );
		if ( '' === trim( (string) $destination_address['address_1'] ) ) {
			$destination_address = $order->get_address( 'billing' );
		}
		$ship_calc = self::central_shipping( $destination_address, $contents, $goods_cost );

		if ( null !== $ship_calc ) {
			$ship = new WC_Order_Item_Shipping();
			$ship->set_method_title( $ship_calc['title'] );
			$ship->set_total( $ship_calc['cost'] );
			$ship->save();
			$order->add_item( $ship );
		} elseif ( ! empty( $data['shipping_lines'] ) && is_array( $data['shipping_lines'] ) ) {
			// Fallback: اگر هیچ روش حمل مرکزی با مقصد مچ نشد، از اطلاعات ارسالی فروشنده استفاده می‌شود (رفتار قبلی)
			foreach ( $data['shipping_lines'] as $line ) {
				$ship = new WC_Order_Item_Shipping();
				$ship->set_method_title( sanitize_text_field( (string) ( $line['method_title'] ?? 'ارسال' ) ) );
				$ship->set_total( (float) ( $line['total'] ?? 0 ) );
				$ship->save();
				$order->add_item( $ship );
			}
			$order->add_order_note( 'هیچ روش حمل‌ونقل مرکزی با مقصد مچ نشد؛ هزینه حمل از سفارش فروشنده استفاده شد.' );
		}

		if ( ! empty( $data['customer_note'] ) ) {
			$order->set_customer_note( sanitize_textarea_field( (string) $data['customer_note'] ) );
		}
		if ( ! empty( $data['currency'] ) ) {
			$order->set_currency( sanitize_text_field( (string) $data['currency'] ) );
		}

		$order->update_meta_data( '_dastyar_vendor_id', $vendor_id );
		$order->update_meta_data( '_dastyar_remote_ref', $ref );
		$order->update_meta_data( '_dastyar_remote_site', $remote_site );
		$order->update_meta_data( '_dastyar_remote_order_id', $remote_id );
		$order->update_meta_data( '_dastyar_remote_order_number', sanitize_text_field( (string) ( $data['remote_order_number'] ?? $remote_id ) ) );

		$order->calculate_totals();

		// تفکیک مبالغ برای نمایش در صورتحساب/کیف پول/لیبل
		$order->update_meta_data( '_dastyar_goods_total', $goods_cost );
		$order->update_meta_data( '_dastyar_shipping_total', (float) $order->get_shipping_total() );

		$order->set_status( 'pending', 'سفارش از طریق دستیار ثبت شد.' );
		$order->save();

		// پرداخت از کیف پول در صورت موجودی کافی (مبلغ = کالا + حمل مرکزی)
		Dastyar::instance()->wallet->maybe_pay_order( $order, $vendor_id );

		do_action( 'dastyar_central_order_created', $order->get_id(), $vendor_id, $data );

		return self::response( $order, false );
	}

	/**
	 * محاسبه هزینه حمل از روی مناطق حمل‌ونقل WooCommerce مرکز.
	 *
	 * @return array{title:string,cost:float}|null  null یعنی هیچ روشی مچ نشد
	 */
	public static function central_shipping( array $address, array $contents, $goods_cost ) {
		if ( empty( $contents ) ) {
			return null;
		}

		$package = array(
			'destination' => array(
				'country'   => (string) ( $address['country'] ?? '' ),
				'state'     => (string) ( $address['state'] ?? '' ),
				'postcode'  => (string) ( $address['postcode'] ?? '' ),
				'city'      => (string) ( $address['city'] ?? '' ),
				'address'   => (string) ( $address['address_1'] ?? '' ),
				'address_1' => (string) ( $address['address_1'] ?? '' ),
				'address_2' => (string) ( $address['address_2'] ?? '' ),
			),
			'contents'        => $contents,
			'contents_cost'   => (float) $goods_cost,
			'applied_coupons' => array(),
			'user'            => array( 'ID' => 0 ),
			'cart_subtotal'   => (float) $goods_cost,
		);

		$zone = WC_Shipping_Zones::get_zone_matching_package( $package );
		if ( ! $zone ) {
			return null;
		}

		$rates = array();
		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			foreach ( (array) $method->get_rates_for_package( $package ) as $rate_id => $rate ) {
				$rates[ $rate_id ] = $rate;
			}
		}
		$rates = apply_filters( 'dastyar_shipping_rates', $rates, $package );
		if ( ! $rates ) {
			return null;
		}

		// پیش‌فرض: ارزان‌ترین روش (قابل تغییر با فیلتر dastyar_pick_shipping_rate)
		uasort( $rates, function ( $a, $b ) {
			return (float) $a->get_cost() <=> (float) $b->get_cost();
		} );
		$rate = apply_filters( 'dastyar_pick_shipping_rate', reset( $rates ), $rates, $package );
		if ( ! $rate || ! $rate instanceof WC_Shipping_Rate ) {
			return null;
		}

		return array(
			'title' => $rate->get_label() ?: 'حمل و نقل',
			'cost'  => (float) $rate->get_cost(),
		);
	}

	protected static function clean_address( array $a ) {
		$fields = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone' );
		$out    = array();
		foreach ( $fields as $f ) {
			$out[ $f ] = isset( $a[ $f ] ) ? sanitize_text_field( (string) $a[ $f ] ) : '';
		}
		if ( '' === $out['country'] ) {
			$base = wc_get_base_location();
			$out['country'] = $base['country'];
		}
		return $out;
	}

	protected static function response( WC_Order $order, $duplicate ) {
		return array(
			'ok'             => true,
			'duplicate'      => $duplicate,
			'order_id'       => $order->get_id(),
			'number'         => $order->get_order_number(),
			'status'         => $order->get_status(),
			'goods_total'    => (float) $order->get_meta( '_dastyar_goods_total' ),
			'shipping_total' => (float) $order->get_shipping_total(),
			'total'          => (float) $order->get_total(),
			'payment_url'    => $order->get_checkout_payment_url(),
		);
	}
}
