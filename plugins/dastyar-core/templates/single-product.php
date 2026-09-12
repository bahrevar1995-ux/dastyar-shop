<?php
/**
 * قالب صفحه تکی محصول — کاتالوگ دستیار (جایگزین قالب پیش‌فرض قالب سایت)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// v1.9.2 — کلاس قدیمی dct-page با پیجینیشن آرشیو تداخل داشت؛ تغییر نام به dct-shell
echo '<div class="dct-shell" style="padding:24px 12px">';

global $product;
if ( ! $product instanceof WC_Product && function_exists( 'wc_get_product' ) ) {
	$product = wc_get_product( get_the_ID() );
}

if ( $product instanceof WC_Product ) {
	// phpcs:ignore — خروجی در کلاس امن‌سازی شده است
	echo Dastyar_Cat::instance()->single->html( $product );
} else {
	echo '<div class="dct-empty" dir="rtl">محصول یافت نشد.</div>';
}

echo '</div>';

get_footer();
