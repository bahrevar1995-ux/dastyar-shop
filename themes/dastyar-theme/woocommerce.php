<?php
/**
 * پوشش ووکامرس — فقط برای مسیرهای غیرقابل‌جایگزینی (سبد/تسویه مدیران).
 * فروشگاه/محصول برای کاربران عادی با گارد قالب به کاتالوگ دستیار هدایت می‌شود.
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
echo '<section class="dhm-sec"><div class="dhm-in dth-content dth-woo">';
if ( function_exists( 'woocommerce_content' ) ) {
	woocommerce_content();
}
echo '</div></section>';
get_footer();
