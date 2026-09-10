<?php
/**
 * Template Name: دستیار — سوالات متداول
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$dth_s = Dastyar_Theme_Settings::all();

if ( Dastyar_Theme_Settings::page_hero_on( 'faq' ) ) {
	echo '<section class="dth-page-hero"><div class="dhm-in">';
	echo '<h1>' . esc_html( $dth_s['faq_title'] ) . '</h1>';
	if ( '' !== trim( (string) $dth_s['faq_sub'] ) ) {
		echo '<p>' . esc_html( $dth_s['faq_sub'] ) . '</p>';
	}
	echo '</div></section>';
}

echo '<section class="dhm-sec"><div class="dhm-in">';
echo Dastyar_Theme_Landing::faq_html( $dth_s['faq_items'] ); // phpcs:ignore WordPress.Security.EscapedOutput
echo '<div class="dth-faq-cta dhm-rv"><p>پاسخ سؤال‌تان را پیدا نکردید؟</p>';
printf( '<a class="dhm-btn dhm-btn-primary" href="%s">تماس با پشتیبانی %s</a>', esc_url( Dastyar_Theme::page_url( 'contact_page', 'contact' ) ), Dastyar_Theme_Landing::icon( 'arrow-l' ) );
echo '</div></div></section>';
get_footer();
