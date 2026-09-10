<?php
/**
 * Template Name: دستیار — ورود و ثبت‌نام
 *
 * فرم‌های ورود/ثبت‌نام با کدکوتاه افزونه «دانیار پنل» ([dastyar_vendor_auth])
 * رندر می‌شوند؛ کاربر لاگین‌شده (functions.php) خودکار به پنل هدایت می‌شود.
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$dth_s = Dastyar_Theme_Settings::all();
if ( Dastyar_Theme_Settings::page_hero_on( 'auth' ) ) {
	echo '<section class="dth-page-hero dth-hero-slim"><div class="dhm-in"><h1>' . esc_html( $dth_s['auth_title'] ) . '</h1>';
	if ( '' !== trim( (string) $dth_s['auth_sub'] ) ) {
		echo '<p>' . esc_html( $dth_s['auth_sub'] ) . '</p>';
	}
	echo '</div></section>';
}
echo '<section class="dhm-sec"><div class="dhm-in dth-auth">';
if ( function_exists( 'shortcode_exists' ) && shortcode_exists( 'dastyar_vendor_auth' ) ) {
	echo do_shortcode( '[dastyar_vendor_auth]' ); // phpcs:ignore WordPress.Security.EscapedOutput
} else {
	echo '<div class="dth-empty dhm-rv">' . Dastyar_Theme_Landing::icon( 'users' ) . '<h3>افزونه «دانیار پنل» فعال نیست</h3><p>فرم ورود و ثبت‌نام فروشنده با افزونه دستیار پنل ارائه می‌شود؛ آن را نصب و فعال کنید.</p></div>';
}
echo '</div></section>';
get_footer();
