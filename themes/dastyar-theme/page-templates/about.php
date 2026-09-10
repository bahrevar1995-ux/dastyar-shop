<?php
/**
 * Template Name: دستیار — درباره ما
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$dth_s = Dastyar_Theme_Settings::all();

if ( Dastyar_Theme_Settings::page_hero_on( 'about' ) ) {
	echo '<section class="dth-page-hero"><div class="dhm-in">';
	echo '<h1>' . esc_html( $dth_s['about_title'] ) . '</h1>';
	if ( '' !== trim( (string) $dth_s['about_sub'] ) ) {
		echo '<p>' . esc_html( $dth_s['about_sub'] ) . '</p>';
	}
	echo '</div></section>';
}

echo '<section class="dhm-sec"><div class="dhm-in"><div class="dth-about dhm-rv">';
echo '<div class="dth-about-text">';
foreach ( Dastyar_Theme_Landing::parse_lines( $dth_s['about_text'] ) as $dth_p ) {
	echo '<p>' . esc_html( $dth_p ) . '</p>';
}
echo '</div>';
$dth_points = Dastyar_Theme_Landing::parse_lines( $dth_s['about_points'] );
if ( $dth_points ) {
	echo '<ul class="dth-about-points">';
	foreach ( $dth_points as $dth_pt ) {
		echo '<li>' . Dastyar_Theme_Landing::icon( 'check' ) . esc_html( $dth_pt ) . '</li>';
	}
	echo '</ul>';
}
echo '</div>';

// محتوای ویرایشگر برگه (در صورت وجود) زیر محتوای تنظیمات
while ( have_posts() ) {
	the_post();
	$dth_c = get_the_content();
	if ( '' !== trim( (string) $dth_c ) ) {
		echo '<div class="dth-content">' . apply_filters( 'the_content', $dth_c ) . '</div>';
	}
}
echo '</div></section>';

// نوار آمار (همان داده‌های صفحه اصلی) — v2.0.0: سوییچ اختصاصی برگه درباره هم به sec_stats اضافه شد
if ( Dastyar_Theme_Settings::yes( 'sec_stats' ) && Dastyar_Theme_Settings::yes( 'about_stats_on' ) ) {
	echo Dastyar_Theme::landing()->stats_html( $dth_s ); // phpcs:ignore WordPress.Security.EscapedOutput
}
get_footer();
