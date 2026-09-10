<?php
/**
 * موتور قیمت‌گذاری فروشنده
 *
 * حالت ۱ — درصد افزایش:  قیمت فروش = قیمت تامین × (۱ + درصد/۱۰۰)
 * حالت ۲ — مبلغ ثابت:   قیمت فروش = قیمت تامین + مبلغ ثابت
 *
 * اولویت: تنظیم اختصاصی هر محصول (متا) → تنظیم سراسری فروشنده → فیلترها
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Price {

	/**
	 * @param float $supplier_price قیمت تامین‌کننده
	 * @param int   $product_id     شناسه محصول محلی (برای Override اختصاصی)
	 */
	public static function calculate( $supplier_price, $product_id = 0 ) {
		$mode  = get_option( 'dastyarc_price_mode', 'percent' );
		$value = (float) get_option( 'dastyarc_price_value', 30 );

		// Override اختصاصی هر محصول
		if ( $product_id ) {
			$pm = get_post_meta( $product_id, '_dastyar_price_mode', true );
			if ( in_array( $pm, array( 'percent', 'fixed' ), true ) ) {
				$mode  = $pm;
				$value = (float) get_post_meta( $product_id, '_dastyar_price_value', true );
			}
		}

		$supplier = (float) $supplier_price;
		$price    = 'fixed' === $mode ? $supplier + $value : $supplier * ( 1 + $value / 100 );

		$price = (float) apply_filters( 'dastyarc_price_before_round', $price, $supplier, $mode, $value, $product_id );

		// گرد کردن
		$round = get_option( 'dastyarc_price_round', 'none' );
		if ( in_array( $round, array( '10', '100', '1000' ), true ) ) {
			$base  = (int) $round;
			$price = $base > 0 ? round( $price / $base ) * $base : $price;
		}

		return wc_format_decimal(
			(float) apply_filters( 'dastyarc_calculated_price', $price, $supplier, $product_id ),
			wc_get_price_decimals()
		);
	}
}
