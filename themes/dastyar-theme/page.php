<?php
/**
 * برگه عمومی — هیرو صفحه + محتوا
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
while ( have_posts() ) {
	the_post();
	if ( Dastyar_Theme_Settings::page_hero_on( 'page' ) ) {
		echo '<section class="dth-page-hero"><div class="dhm-in"><h1>' . esc_html( get_the_title() ) . '</h1></div></section>';
	}
	echo '<section class="dhm-sec"><div class="dhm-in dth-content">';
	the_content();
	echo '</div></section>';
}
get_footer();
