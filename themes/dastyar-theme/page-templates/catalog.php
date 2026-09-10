<?php
/**
 * Template Name: دستیار — کاتالوگ محصولات
 *
 * محصولات با کدکوتاه افزونه «دانیار کاتالوگ» ([dastyar_catalog]) رندر می‌شوند؛
 * فروشگاه پیش‌فرض ووکامرس (به درخواست کارفرما) برای کاربران نمایش داده نمی‌شود
 * و گارد قالب بازدیدکنندگان را به همین برگه هدایت می‌کند.
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$dth_s = Dastyar_Theme_Settings::all();
if ( Dastyar_Theme_Settings::page_hero_on( 'catalog' ) ) {
	echo '<section class="dth-page-hero"><div class="dhm-in">';
	if ( '' !== trim( (string) $dth_s['catalog_chip'] ) ) {
		echo '<span class="dth-ph-chip">' . Dastyar_Theme_Landing::icon( 'package' ) . esc_html( $dth_s['catalog_chip'] ) . '</span>';
	}
	echo '<h1>' . esc_html( $dth_s['catalog_title'] ) . '</h1>';
	if ( '' !== trim( (string) $dth_s['catalog_sub'] ) ) {
		echo '<p>' . esc_html( $dth_s['catalog_sub'] ) . '</p>';
	}
	echo '</div></section>';
}
echo '<section class="dhm-sec"><div class="dhm-in">';
if ( function_exists( 'shortcode_exists' ) && shortcode_exists( 'dastyar_catalog' ) ) {
	echo do_shortcode( '[dastyar_catalog]' ); // phpcs:ignore WordPress.Security.EscapedOutput
} else {
	echo '<div class="dth-empty dhm-rv">' . Dastyar_Theme_Landing::icon( 'package' ) . '<h3>افزونه «دانیار کاتالوگ» فعال نیست</h3><p>برای نمایش کاتالوگ محصولات، افزونه دستیار کاتالوگ را نصب و فعال کنید.</p></div>';
}
echo '</div></section>';
get_footer();
