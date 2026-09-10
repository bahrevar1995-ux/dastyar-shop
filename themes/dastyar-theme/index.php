<?php
/**
 * فالبک عمومی قالب — کارت محتوا
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
echo '<section class="dhm-sec"><div class="dhm-in"><div class="dth-posts">';
if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/card' );
	}
} else {
	echo '<div class="dth-empty">' . Dastyar_Theme_Landing::icon( 'book' ) . '<p>محتوایی یافت نشد.</p></div>';
}
echo '</div>';
the_posts_pagination();
echo '</div></section>';
get_footer();
