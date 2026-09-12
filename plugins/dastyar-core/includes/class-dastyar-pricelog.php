<?php
/**
 * لاگ تغییرات قیمت محصولات مرکز (v1.6.0 — مورد ۸)
 *
 * با هر ذخیره محصول در مرکز، «قیمت تأمین مؤثر» (supplier_price یا قیمت عادی در نبود آن)
 * با مقدار قبلی مقایسه می‌شود؛ افزایش‌ها در آپشن dastyar_price_log (سقف ۱۰۰ مورد) ثبت می‌گردد
 * تا پنل فروشنده در داشبوردش افزایش قیمت‌ها را ببیند.
 *
 * نکته‌ها:
 *  - اولین مشاهده هر محصول فقط مبنا می‌سازد و لاگ نمی‌زند (بدون false-positive).
 *  - فقط محصولات اصلی (نه وارییشن‌ها) ردیابی می‌شوند؛ قیمت وارییشن مؤثر در تامین، همان supplier است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Pricelog {

	const OPTION   = 'dastyar_price_log';
	const PREV_KEY = '_dastyar_prev_price';
	const MAX      = 100;

	public function __construct() {
		add_action( 'woocommerce_update_product', array( $this, 'track' ), 30, 2 );
		add_action( 'woocommerce_new_product', array( $this, 'track' ), 30, 2 );
	}

	/**
	 * مقایسه قیمت مؤثر جدید با مبنای قبلی؛ افزایش‌ها ثبت می‌شوند.
	 * @param int        $product_id
	 * @param WC_Product $product    (در برخی نسخه‌ها ممکن است null باشد)
	 */
	public function track( $product_id, $product = null ) {
		$product_id = (int) $product_id;
		if ( ! $product_id || wp_is_post_revision( $product_id ) || wp_is_post_autosave( $product_id ) ) {
			return;
		}
		// فقط محصول اصلی؛ وارییشن‌ها از طریق محصول والد پوشش داده می‌شوند
		if ( (int) wp_get_post_parent_id( $product_id ) ) {
			return;
		}
		if ( $product && $product instanceof WC_Product && $product->is_type( 'variation' ) ) {
			return;
		}

		if ( ! $product instanceof WC_Product ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
			if ( ! $product ) {
				return;
			}
		}
		if ( ! apply_filters( 'dastyar_pricelog_track_product', true, $product_id, $product ) ) {
			return;
		}

		// قیمت مؤثر تامین — همان چیزی که فروشنده می‌پردازد/در API می‌بیند
		$new = class_exists( 'Dastyar_Product_Export' )
			? (float) Dastyar_Product_Export::supplier_price( $product )
			: (float) $product->get_regular_price();

		$prev = get_post_meta( $product_id, self::PREV_KEY, true );
		if ( '' === $prev ) {
			// اولین مشاهده ← فقط مبنا ساز (بدون لاگ)
			update_post_meta( $product_id, self::PREV_KEY, $new );
			return;
		}
		$prev = (float) $prev;
		if ( $new === $prev ) {
			return;
		}
		update_post_meta( $product_id, self::PREV_KEY, $new );

		if ( $new <= $prev ) {
			return; // فعلاً فقط «افزایش» قیمت اعلان می‌شود (کاهش شما خواهش مدیر)
		}

		$percent = $prev > 0 ? round( ( ( $new - $prev ) / $prev ) * 100, 1 ) : 0;
		self::append( array(
			'product_id'   => $product_id,
			'product_name' => (string) $product->get_name(),
			'old'          => $prev,
			'new'          => $new,
			'percent'      => $percent,
			'time'         => current_time( 'mysql' ),
		) );
	}

	/** افزودن رکورد + کوتاه‌سازی آرایه به سقف MAX */
	public static function append( array $entry ) {
		$log = self::all();
		array_unshift( $log, $entry );
		if ( count( $log ) > self::MAX ) {
			$log = array_slice( $log, 0, self::MAX );
		}
		update_option( self::OPTION, $log, false );
		do_action( 'dastyar_pricelog_appended', $entry );
	}

	/** همه رکوردها (جدیدترین اول) */
	public static function all() {
		$log = get_option( self::OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/** آخرین N رکورد */
	public static function latest( $limit = 10 ) {
		return array_slice( self::all(), 0, max( 1, (int) $limit ) );
	}

	/** خالی‌کردن لاگ (ابزار مدیر) */
	public static function flush() {
		delete_option( self::OPTION );
	}
}
