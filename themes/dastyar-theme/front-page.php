<?php
/**
 * صفحه اصلی — لندینگ کامل دستیار (بخش‌ها از نمایش ← تنظیمات دستیار مدیریت می‌شوند)
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
echo Dastyar_Theme::landing()->render(); // phpcs:ignore WordPress.Security.EscapedOutput -- خروجی ساخته‌شده و امن
get_footer();
