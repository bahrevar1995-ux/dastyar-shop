<?php
/**
 * Template Name: دستیار — پنل کاربری
 *
 * پنل فروشنده با کدکوتاه افزونه «دانیار پنل» ([dastyar_vendor_panel]) رندر می‌شود؛
 * اگر افزونه نصب نباشد، راهنمای نصب نمایش داده می‌شود (بدون خطا).
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$dth_s = Dastyar_Theme_Settings::all();
// v2.1.0 — پنج سبک نمایش پنل: classic | full | bare | gray | card
$dth_pstyle = (string) Dastyar_Theme_Settings::get( 'panel_style', 'classic' );
if ( ! in_array( $dth_pstyle, array( 'classic', 'full', 'bare', 'gray', 'card' ), true ) ) {
	$dth_pstyle = 'classic';
}
// در سبک bare هیرو اصلاً نیست (حتی اگر سوییچ روشن باشد)
if ( 'bare' !== $dth_pstyle && Dastyar_Theme_Settings::page_hero_on( 'panel' ) ) {
	$dth_full = 'full' === $dth_pstyle;
	echo '<section class="dth-page-hero' . ( $dth_full ? '' : ' dth-hero-slim' ) . '"><div class="dhm-in"'
		. ( $dth_full ? '>' : '>' )
		. '<h1>' . esc_html( $dth_s['panel_title'] ) . '</h1>' . ( $dth_full ? '<p>مدیریت فروشگاه، سفارش‌ها، کیف پول و اتصالات — همه در یک‌جا</p>' : '' ) . '</div></section>';
}
echo '<section class="dhm-sec dth-panelsec dth-panel-' . esc_attr( $dth_pstyle ) . '"><div class="dhm-in' . ( 'card' === $dth_pstyle ? ' dth-panelcard' : '' ) . '">';
if ( function_exists( 'shortcode_exists' ) && shortcode_exists( 'dastyar_vendor_panel' ) ) {
	echo do_shortcode( '[dastyar_vendor_panel]' ); // phpcs:ignore WordPress.Security.EscapedOutput
} else {
	echo '<div class="dth-empty dhm-rv">' . Dastyar_Theme_Landing::icon( 'package' ) . '<h3>افزونه «دانیار پنل» فعال نیست</h3><p>برای نمایش پنل فروشنده، افزونه دستیار پنل را نصب و فعال کنید.</p></div>';
}
echo '</div></section>';
get_footer();
