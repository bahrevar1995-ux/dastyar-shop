<?php
/**
 * ۴۰۴ — جعبه برند + لینک‌های سریع
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
echo '<section class="dhm-sec"><div class="dhm-in"><div class="dth-404 dhm-rv">';
echo '<div class="dth-404-num">' . esc_html( Dastyar_Theme_Settings::fa_num( '404' ) ) . '</div>';
echo '<h1>صفحه پیدا نشد</h1><p>نشانی‌ای که باز کردید وجود ندارد یا جابه‌جا شده است.</p>';
echo '<div class="dth-404-links">';
printf( '<a class="dhm-btn dhm-btn-primary" href="%s">بازگشت به خانه</a>', esc_url( home_url( '/' ) ) );
printf( '<a class="dhm-btn dhm-btn-outline" href="%s">محصولات</a>', esc_url( Dastyar_Theme::page_url( 'catalog_page', 'catalog' ) ) );
printf( '<a class="dhm-btn dhm-btn-outline" href="%s">تماس با ما</a>', esc_url( Dastyar_Theme::page_url( 'contact_page', 'contact' ) ) );
echo '</div></div></div></section>';
get_footer();
