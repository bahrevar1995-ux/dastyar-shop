<?php
/**
 * Template Name: دستیار — تماس با ما
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$dth_s = Dastyar_Theme_Settings::all();

if ( Dastyar_Theme_Settings::page_hero_on( 'contact' ) ) {
	echo '<section class="dth-page-hero"><div class="dhm-in">';
	echo '<h1>' . esc_html( $dth_s['contact_title'] ) . '</h1>';
	if ( '' !== trim( (string) $dth_s['contact_sub'] ) ) {
		echo '<p>' . esc_html( $dth_s['contact_sub'] ) . '</p>';
	}
	echo '</div></section>';
}

$dth_cards_on = Dastyar_Theme_Settings::yes( 'contact_cards_on' );
echo '<section class="dhm-sec"><div class="dhm-in"><div class="dth-contact' . ( $dth_cards_on ? '' : ' dth-contact-formonly' ) . '">';

// کارت‌های اطلاعات تماس (v2.0.0: قابل خاموشی از تنظیمات)
if ( $dth_cards_on ) {
echo '<div class="dth-cinfo">';
$dth_items = array(
	array( 'phone', 'تلفن', $dth_s['contact_phone'], 'ltr' ),
	array( 'mail', 'ایمیل', $dth_s['contact_email'], 'ltr' ),
	array( 'pin', 'نشانی', $dth_s['contact_address'], '' ),
	array( 'clock', 'ساعات پاسخ‌گویی', $dth_s['contact_hours'], '' ),
);
foreach ( $dth_items as $dth_it ) {
	if ( '' === trim( (string) $dth_it[2] ) ) {
		continue;
	}
	echo '<div class="dth-ci"><span class="dth-ci-ic">' . Dastyar_Theme_Landing::icon( $dth_it[0] ) . '</span><div><strong>' . esc_html( $dth_it[1] ) . '</strong><span' . ( 'ltr' === $dth_it[3] ? ' dir="ltr"' : '' ) . '>' . esc_html( $dth_it[2] ) . '</span></div></div>';
}
echo '</div>';
}

// فرم تماس
if ( Dastyar_Theme_Settings::yes( 'contact_form_enabled' ) ) {
	$dth_sent = isset( $_GET['dth_sent'] ) ? sanitize_key( wp_unslash( $_GET['dth_sent'] ) ) : '';
	echo '<form class="dth-form dhm-rv" method="post" action="">';
	echo '<h3>پیام شما</h3>';
	if ( '1' === $dth_sent ) {
		echo '<div class="dth-notice dth-notice-ok">پیام‌تان ارسال شد؛ کارشناسان دستیار به‌زودی پاسخ می‌دهند.</div>';
	} elseif ( 'err' === $dth_sent ) {
		echo '<div class="dth-notice dth-notice-err">ارسال ناموفق بود؛ نام و متن پیام را کامل کنید یا بعداً تلاش کنید.</div>';
	}
	wp_nonce_field( 'dth_contact' );
	echo '<input type="hidden" name="dth_contact" value="1">';
	echo '<div class="dth-frow"><label>نام و نام خانوادگی<input type="text" name="c_name" required></label>';
	echo '<label>ایمیل<input type="email" name="c_email" dir="ltr"></label></div>';
	echo '<div class="dth-frow"><label>تلفن همراه<input type="tel" name="c_phone" dir="ltr"></label></div>';
	echo '<label>متن پیام<textarea name="c_msg" rows="5" required></textarea></label>';
	echo '<button class="dhm-btn dhm-btn-primary" type="submit">ارسال پیام ' . Dastyar_Theme_Landing::icon( 'arrow-l' ) . '</button>';
	echo '</form>';
}
echo '</div></div></section>';
get_footer();
