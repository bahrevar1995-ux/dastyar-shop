<?php
/**
 * Template Name: دستیار — روند پرداخت
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$dth_s = Dastyar_Theme_Settings::all();

if ( Dastyar_Theme_Settings::page_hero_on( 'pay' ) ) {
	echo '<section class="dth-page-hero"><div class="dhm-in">';
	echo '<h1>' . esc_html( $dth_s['pay_title'] ) . '</h1>';
	if ( '' !== trim( (string) $dth_s['pay_sub'] ) ) {
		echo '<p>' . esc_html( $dth_s['pay_sub'] ) . '</p>';
	}
	echo '</div></section>';
}

echo '<section class="dhm-sec"><div class="dhm-in"><div class="dth-pay">'; 
for ( $dth_n = 1; $dth_n <= 4; $dth_n++ ) {
	$dth_t = trim( (string) $dth_s[ 'pay_' . $dth_n . '_t' ] );
	if ( '' === $dth_t ) {
		continue;
	}
	echo '<div class="dth-pay-step dhm-rv"><span class="dth-pay-n">' . esc_html( Dastyar_Theme_Settings::fa_num( $dth_n ) ) . '</span><div class="dth-pay-body"><h3>' . esc_html( $dth_t ) . '</h3><p>' . esc_html( $dth_s[ 'pay_' . $dth_n . '_d' ] ) . '</p></div></div>';
}

if ( Dastyar_Theme_Settings::yes( 'pay_cta_on' ) ) {
echo '<div class="dth-pay-cta dhm-rv"><div class="dhm-cta-band"><div class="dhm-cta-r"><span class="dhm-cta-ic">' . Dastyar_Theme_Landing::icon( 'wallet' ) . '</span><div><h3>کیف پول‌تان را شارژ کنید</h3><p>با کیف پول فعال، سفارش‌های مشتریان‌تان بی‌وقفه پرداخت و ارسال می‌شوند.</p></div></div>';
printf(
	'<a class="dhm-btn dhm-btn-primary" href="%s">ورود به پنل فروشنده %s</a>',
	esc_url( is_user_logged_in() ? Dastyar_Theme::page_url( 'panel_page', 'panel' ) : Dastyar_Theme_Landing::default_cta_url() ),
	Dastyar_Theme_Landing::icon( 'arrow-l' )
);
echo '</div></div>';
}
echo '</div></div></section>';
get_footer();
