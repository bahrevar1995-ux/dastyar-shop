<?php
/**
 * تک‌نوشته — هیرو + محتوا + ناوبری قبلی/بعدی
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
while ( have_posts() ) {
	the_post();
	if ( Dastyar_Theme_Settings::page_hero_on( 'single' ) ) {
		echo '<section class="dth-page-hero"><div class="dhm-in">';
		echo '<span class="dth-ph-chip">' . Dastyar_Theme_Landing::icon( 'book' ) . '<a href="' . esc_url( Dastyar_Theme::page_url( 'blog_page', 'blog' ) ) . '">مقالات</a></span>';
		echo '<h1>' . esc_html( get_the_title() ) . '</h1>';
		echo '<p class="dth-single-meta">' . Dastyar_Theme_Landing::icon( 'clock' ) . esc_html( Dastyar_Theme_Settings::fa_num( get_the_date() ) ) . ' · ' . Dastyar_Theme_Landing::icon( 'user' ) . esc_html( get_the_author() ) . '</p>';
		echo '</div></section>';
	}

	echo '<section class="dhm-sec"><div class="dhm-in dth-article">';
	if ( has_post_thumbnail() ) {
		echo '<figure class="dth-single-thumb">' . get_the_post_thumbnail( null, 'large' ) . '</figure>';
	}
	echo '<div class="dth-content">';
	the_content();
	echo '</div>';
	echo '<nav class="dth-postnav" aria-label="ناوبری نوشته">';
	previous_post_link( '<span class="dth-pn dth-pn-prev">%link</span>', 'نوشته قبلی' );
	next_post_link( '<span class="dth-pn dth-pn-next">%link</span>', 'نوشته بعدی' );
	echo '</nav>';
	if ( ( comments_open() || get_comments_number() ) && 'post' === get_post_type() ) {
		comments_template();
	}
	echo '</div></section>';
}
get_footer();
