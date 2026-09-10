<?php
/**
 * برگه «مقالات» — لیست نوشته‌ها با کارت‌های برند دستیار
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$dth_s = Dastyar_Theme_Settings::all();

if ( Dastyar_Theme_Settings::page_hero_on( 'blog' ) ) {
	echo '<section class="dth-page-hero"><div class="dhm-in">';
	echo '<span class="dth-ph-chip">' . Dastyar_Theme_Landing::icon( 'book' ) . 'مقالات و آموزش</span>';
	echo '<h1>' . esc_html( $dth_s['blog_title'] ) . '</h1>';
	if ( '' !== trim( (string) $dth_s['blog_sub'] ) ) {
		echo '<p>' . esc_html( $dth_s['blog_sub'] ) . '</p>';
	}
	echo '</div></section>';
}

echo '<section class="dhm-sec"><div class="dhm-in">';
echo '<form class="dth-searchbar" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '">';
echo '<input type="search" name="s" placeholder="جستجو در مقالات…" value="' . esc_attr( function_exists( 'get_search_query' ) ? get_search_query() : '' ) . '">';
echo '<button class="dhm-btn dhm-btn-primary" type="submit">جستجو</button></form>';

echo '<div class="dth-posts">';
if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/card' );
	}
} else {
	echo '<div class="dth-empty">' . Dastyar_Theme_Landing::icon( 'book' ) . '<p>هنوز مقاله‌ای منتشر نشده است؛ به‌زودی!</p></div>';
}
echo '</div>';

the_posts_pagination( array(
	'mid_size'           => 1,
	'prev_text'          => 'قبلی',
	'next_text'          => 'بعدی',
	'screen_reader_text' => 'صفحه‌بندی مقالات',
) );
echo '</div></section>';

get_footer();
