<?php
/**
 * نتایج جستجو — محصولات ووکامرس پیش از این در کوئری حذف شده‌اند (فقط نوشته/برگه)
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$dth_q = function_exists( 'get_search_query' ) ? get_search_query() : '';
if ( Dastyar_Theme_Settings::page_hero_on( 'search' ) ) {
	echo '<section class="dth-page-hero"><div class="dhm-in">';
	echo '<h1>نتایج جستجو: «' . esc_html( $dth_q ) . '»</h1>';
	echo '</div></section>';
}
echo '<section class="dhm-sec"><div class="dhm-in">';
echo '<form class="dth-searchbar" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '">';
echo '<input type="search" name="s" placeholder="جستجو…" value="' . esc_attr( $dth_q ) . '">';
echo '<button class="dhm-btn dhm-btn-primary" type="submit">جستجو</button></form>';
echo '<div class="dth-posts">';
if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/card' );
	}
} else {
	echo '<div class="dth-empty">' . Dastyar_Theme_Landing::icon( 'book' ) . '<p>نتیجه‌ای یافت نشد؛ عبارت دیگری را امتحان کنید.</p></div>';
}
echo '</div>';
the_posts_pagination();
echo '</div></section>';
get_footer();
