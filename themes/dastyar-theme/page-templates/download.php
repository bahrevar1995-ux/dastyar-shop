<?php
/**
 * Template Name: دستیار — دریافت پلاگین
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$dth_s = Dastyar_Theme_Settings::all();

if ( Dastyar_Theme_Settings::page_hero_on( 'download' ) ) {
	echo '<section class="dth-page-hero"><div class="dhm-in">';
	echo '<h1>' . esc_html( $dth_s['dl_title'] ) . '</h1>';
	if ( '' !== trim( (string) $dth_s['dl_sub'] ) ) {
		echo '<p>' . esc_html( $dth_s['dl_sub'] ) . '</p>';
	}
	echo '</div></section>';
}

echo '<section class="dhm-sec"><div class="dhm-in"><div class="dth-download dhm-rv">';
echo '<div class="dth-dl-card">';
echo '<span class="dth-dl-ic">' . Dastyar_Theme_Landing::icon( 'download' ) . '</span>';
if ( '' !== trim( (string) $dth_s['dl_version'] ) ) {
	echo '<span class="dth-dl-ver">نسخه ' . esc_html( $dth_s['dl_version'] ) . '</span>';
}
if ( '' !== trim( (string) $dth_s['dl_url'] ) ) {
	echo '<a class="dhm-btn dhm-btn-primary dth-dl-btn" href="' . esc_url( $dth_s['dl_url'] ) . '" data-dth-nopjax>' . Dastyar_Theme_Landing::icon( 'download' ) . ' دانلود افزونه اتصال‌دهنده</a>';
}
$dth_changes = Dastyar_Theme_Landing::parse_lines( $dth_s['dl_changes'] );
if ( $dth_changes ) {
	echo '<ul class="dth-dl-feats">';
	foreach ( $dth_changes as $dth_c ) {
		echo '<li>' . Dastyar_Theme_Landing::icon( 'check' ) . esc_html( $dth_c ) . '</li>';
	}
	echo '</ul>';
}
echo '</div>';

echo '<div class="dth-dl-side">';
if ( '' !== trim( (string) $dth_s['dl_note'] ) ) {
	echo '<div class="dth-note">' . Dastyar_Theme_Landing::icon( 'suggest' ) . '<p>' . esc_html( $dth_s['dl_note'] ) . '</p></div>';
}
if ( Dastyar_Theme_Settings::yes( 'dl_steps_on' ) ) {
echo '<div class="dth-dl-steps"><h3>مسیر نصب</h3><ol>';
printf( '<li>فایل افزونه را از همین صفحه دانلود کنید.</li>' );
printf( '<li>در پیشخوان وردپرس فروشگاه‌تان: افزونه‌ها ← افزودن ← بارگذاری افزونه ← نصب و فعال‌سازی.</li>' );
printf( '<li>از پیشخوان ← «اتصال دستیار»، کلید API را از بخش اتصال فروشگاهِ پنل خود وارد کنید.</li>' );
echo '</ol>';
printf(
	'<a class="dhm-btn dhm-btn-outline" href="%s">دریافت کلید API از پنل %s</a>',
	esc_url( Dastyar_Theme::page_url( 'auth_page', 'auth' ) ),
	Dastyar_Theme_Landing::icon( 'arrow-l' )
);
echo '</div>';
}
echo '</div>';

echo '</div></div></section>';
get_footer();
