<?php
/**
 * هدر قالب «دستیار» — اعلان + هدر چسبان (لوگو/منو/دکمه‌ها/همبرگر) + کشوی موبایل
 * سپس <main id="dth-main"> که هدف تعویض محتوای لود آنی (PJAX) است.
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dth_s = Dastyar_Theme_Settings::all();
?>
<!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}
?>
<?php
// v2.4.9 — پریلود اولیه (فقط بار اول لود کامل صفحه؛ در ناوبری‌های لود آنی دوباره نمایش داده نمی‌شود)
$dth_pl_logo = '' !== trim( (string) $dth_s['logo_text'] ) ? $dth_s['logo_text'] : get_bloginfo( 'name' );
?>
<div id="dth-preloader" aria-hidden="true">
	<span class="dth-preloader-logo"><?php echo esc_html( $dth_pl_logo ); ?></span>
</div>
<script>
(function () {
	function dthHidePreloader() {
		var el = document.getElementById( 'dth-preloader' );
		if ( ! el || el.classList.contains( 'dth-pl-hide' ) ) { return; }
		el.classList.add( 'dth-pl-hide' );
		setTimeout( function () { el.remove(); }, 500 );
	}
	window.addEventListener( 'load', dthHidePreloader );
	setTimeout( dthHidePreloader, 2500 ); // فالبک ایمنی؛ صفحه هرگز پشت پریلود گیر نمی‌افتد
})();
</script>
<div id="dth-progress" aria-hidden="true"></div>
<?php
// نوار اعلانات (اختیاری)
if ( Dastyar_Theme_Settings::yes( 'announce_enabled' ) && '' !== trim( (string) $dth_s['announce_text'] ) ) {
	$dth_announce = esc_html( $dth_s['announce_text'] );
	$dth_ann_url  = trim( (string) $dth_s['announce_url'] );
	echo '<div class="dhm-announce" dir="rtl">';
	echo $dth_ann_url ? '<a href="' . esc_url( $dth_ann_url ) . '">' . $dth_announce . '</a>' : '<span>' . $dth_announce . '</span>';
	echo '</div>';
}
?>
<?php
// v2.1.0 — سبک هدر: default | dark | center | minimal
$dth_hstyle = (string) Dastyar_Theme_Settings::get( 'header_style', 'default' );
if ( ! in_array( $dth_hstyle, array( 'default', 'dark', 'center', 'minimal' ), true ) ) {
	$dth_hstyle = 'default';
}
?>
<header class="dhm-site-head dhm-hs-<?php echo esc_attr( $dth_hstyle ); ?>" dir="rtl">
	<div class="dhm-head-in">
		<?php
		// v2.3.0 — لوگوی دوحالته روشن/تاریک (از «نمایش ← تنظیمات دستیار ← هدر سایت»):
		// هر دو پر باشند ← در حالت تاریک لوگوی تاریک جایگزین می‌شود؛ فقط یکی پر باشد ← همان در هر دو حالت؛
		// هیچ‌کدام ← رفتار قبلی (لوگوی سفارشی وردپرس، وگرنه متن لوگو) دست‌نخورده باقی می‌ماند.
		$dth_ll_id = (int) Dastyar_Theme_Settings::get( 'logo_light_id', 0 );
		$dth_ld_id = (int) Dastyar_Theme_Settings::get( 'logo_dark_id', 0 );
		$dth_ll    = ( $dth_ll_id && function_exists( 'wp_get_attachment_image_url' ) ) ? (string) wp_get_attachment_image_url( $dth_ll_id, 'full' ) : '';
		$dth_ld    = ( $dth_ld_id && function_exists( 'wp_get_attachment_image_url' ) ) ? (string) wp_get_attachment_image_url( $dth_ld_id, 'full' ) : '';
		if ( '' !== $dth_ll || '' !== $dth_ld ) {
			$dth_alt = get_bloginfo( 'name' );
			if ( '' !== $dth_ll && '' !== $dth_ld && $dth_ll !== $dth_ld ) {
				printf(
					'<a class="dhm-logo dhm-logo-img dhm-logo-dual dhm-logo-hasdark" href="%1$s"><img class="dhm-logo-l" src="%2$s" alt="%4$s"><img class="dhm-logo-d" src="%3$s" alt="%4$s"></a>',
					esc_url( home_url( '/' ) ),
					esc_url( $dth_ll ),
					esc_url( $dth_ld ),
					esc_attr( $dth_alt )
				);
			} else {
				printf(
					'<a class="dhm-logo dhm-logo-img" href="%1$s"><img src="%2$s" alt="%3$s"></a>',
					esc_url( home_url( '/' ) ),
					esc_url( '' !== $dth_ll ? $dth_ll : $dth_ld ),
					esc_attr( $dth_alt )
				);
			}
		} elseif ( function_exists( 'get_custom_logo' ) && get_custom_logo() ) {
			echo '<div class="dhm-logo dhm-logo-img">' . wp_kses_post( get_custom_logo() ) . '</div>';
		} else {
			$dth_lt = '' !== trim( (string) $dth_s['logo_text'] ) ? $dth_s['logo_text'] : get_bloginfo( 'name' );
			echo '<a class="dhm-logo" href="' . esc_url( home_url( '/' ) ) . '"><span class="dhm-logo-t">' . esc_html( $dth_lt ) . '</span><span class="dhm-ldot"></span></a>';
		}
		?>
		<nav class="dhm-nav" aria-label="منوی اصلی">
			<?php
			if ( function_exists( 'has_nav_menu' ) && has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'dhm-menu',
					'fallback_cb'    => false,
					'depth'          => 2,
				) );
			} else {
				echo '<ul class="dhm-menu">';
				foreach ( Dastyar_Theme_Landing::parse_pairs( $dth_s['nav_links'] ) as $dth_ln ) {
					printf( '<li><a href="%s">%s</a></li>', esc_url( '' !== $dth_ln[1] ? $dth_ln[1] : '#' ), esc_html( $dth_ln[0] ) );
				}
				echo '</ul>';
			}
			?>
		</nav>
		<div class="dhm-cta">
			<?php // v1.0.5 — دکمه برجسته «آموزش استفاده» | v2.1.0 — سوییچ hbtn_tut_on از تنظیمات ?>
			<?php if ( Dastyar_Theme_Settings::yes( 'hbtn_tut_on' ) ) : ?>
				<a class="dhm-btn dhm-btn-tut" href="<?php echo esc_url( Dastyar_Theme::page_url( 'tutorial_page', 'tutorial' ) ); ?>"><?php echo Dastyar_Theme_Landing::icon( 'book' ); ?> آموزش استفاده</a>
			<?php endif; ?>
			<?php if ( Dastyar_Theme_Settings::yes( 'hbtn_cta2_on' ) && '' !== trim( (string) $dth_s['cta2_text'] ) ) : ?>
				<a class="dhm-btn dhm-btn-outline" href="<?php echo esc_url( Dastyar_Theme_Landing::default_cta2_url() ); ?>"><?php echo esc_html( $dth_s['cta2_text'] ); ?></a>
			<?php endif; ?>
			<?php if ( is_user_logged_in() ) : ?>
				<?php
				// (v1.0.4 — بازخورد کاربر، مورد ۱) کاربر واردشده ← به‌جای دکمه ورود، آواتار/لوگوی فروشگاه + نام
				$dth_user    = wp_get_current_user();
				$dth_logo_id = function_exists( 'get_user_meta' ) ? (int) get_user_meta( (int) $dth_user->ID, '_dastyar_logo_id', true ) : 0;
				$dth_logo    = $dth_logo_id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $dth_logo_id, 'thumbnail' ) : '';
				$dth_acct    = function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'myaccount' ) > 0 ? get_permalink( wc_get_page_id( 'myaccount' ) ) : home_url( '/my-account/' );
				?>
				<a class="dhm-userchip" href="<?php echo esc_url( $dth_acct ); ?>" title="رفتن به پنل فروشنده">
					<?php if ( $dth_logo ) : ?>
						<img class="dhm-userchip-img" src="<?php echo esc_url( $dth_logo ); ?>" alt="" width="30" height="30">
					<?php else : ?>
						<?php echo function_exists( 'get_avatar' ) ? get_avatar( (int) $dth_user->ID, 30, '', '', array( 'class' => 'dhm-userchip-img' ) ) : ''; ?>
					<?php endif; ?>
					<span class="dhm-userchip-name"><?php echo esc_html( $dth_user->display_name ); ?></span>
					<?php echo Dastyar_Theme_Landing::icon( 'arrow-l' ); ?>
				</a>
			<?php elseif ( Dastyar_Theme_Settings::yes( 'hbtn_cta1_on' ) && '' !== trim( (string) $dth_s['cta1_text'] ) ) : ?>
				<a class="dhm-btn dhm-btn-primary" href="<?php echo esc_url( Dastyar_Theme_Landing::default_cta_url() ); ?>"><?php echo esc_html( $dth_s['cta1_text'] ) . ' ' . Dastyar_Theme_Landing::icon( 'arrow-l' ); ?></a>
			<?php endif; ?>
		</div>
		<button type="button" class="dhm-burger" aria-label="منو"><?php echo Dastyar_Theme_Landing::icon( 'menu' ); ?></button>
		<?php // v2.2.0 — سوییچ حالت نمایش (تاریک/روشن): چپ‌ترین آیتم هدر، فقط آیکون، بدون اموجی ?>
		<button type="button" class="dhm-theme-toggle" aria-label="تغییر حالت تاریک و روشن" title="حالت تاریک/روشن" aria-pressed="false">
			<svg class="dhm-tt-ic dhm-tt-moon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 14.2A8.6 8.6 0 0 1 9.8 3.4a.6.6 0 0 0-.8-.8 10.4 10.4 0 1 0 12.4 12.4.6.6 0 0 0-.8-.8Z"/></svg>
			<svg class="dhm-tt-ic dhm-tt-sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.2v2.2M12 19.6v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.2 12h2.2M19.6 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/></svg>
		</button>
	</div>
</header>
<div class="dhm-drawer">
	<div class="dhm-drawer-bg"></div>
	<nav class="dhm-drawer-nav" dir="rtl" aria-label="منوی موبایل">
		<button type="button" class="dhm-drawer-close" aria-label="بستن"><?php echo Dastyar_Theme_Landing::icon( 'close' ); ?></button>
		<?php
		if ( function_exists( 'has_nav_menu' ) && has_nav_menu( 'primary' ) ) {
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'dhm-menu dhm-menu-drawer',
				'fallback_cb'    => false,
				'depth'          => 2,
			) );
		} else {
			foreach ( Dastyar_Theme_Landing::parse_pairs( $dth_s['nav_links'] ) as $dth_ln ) {
				printf( '<a href="%s">%s</a>', esc_url( '' !== $dth_ln[1] ? $dth_ln[1] : '#' ), esc_html( $dth_ln[0] ) );
			}
		}
		// v1.0.5 — دکمه «آموزش استفاده» در کشوی موبایل هم پابرجاست | v2.1.0 — سوییچ دار
		if ( Dastyar_Theme_Settings::yes( 'hbtn_tut_on' ) ) {
			echo '<a class="dhm-btn dhm-btn-tut dhm-drawer-cta" href="' . esc_url( Dastyar_Theme::page_url( 'tutorial_page', 'tutorial' ) ) . '">' . Dastyar_Theme_Landing::icon( 'book' ) . ' آموزش استفاده</a>';
		}
		if ( is_user_logged_in() ) {
			// (v1.0.4 — مورد ۱) در منوی موبایل هم به‌جای «ورود/ثبت‌نام» نام و آواتار کاربر
			$dth_user    = wp_get_current_user();
			$dth_logo_id = function_exists( 'get_user_meta' ) ? (int) get_user_meta( (int) $dth_user->ID, '_dastyar_logo_id', true ) : 0;
			$dth_logo    = $dth_logo_id && function_exists( 'wp_get_attachment_image_url' ) ? wp_get_attachment_image_url( $dth_logo_id, 'thumbnail' ) : '';
			$dth_acct    = function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'myaccount' ) > 0 ? get_permalink( wc_get_page_id( 'myaccount' ) ) : home_url( '/my-account/' );
			echo '<a class="dhm-userchip dhm-userchip-drawer" href="' . esc_url( $dth_acct ) . '">';
			echo $dth_logo
				? '<img class="dhm-userchip-img" src="' . esc_url( $dth_logo ) . '" alt="" width="30" height="30">'
				: ( function_exists( 'get_avatar' ) ? get_avatar( (int) $dth_user->ID, 30, '', '', array( 'class' => 'dhm-userchip-img' ) ) : '' );
			echo '<span class="dhm-userchip-name">' . esc_html( $dth_user->display_name ) . '</span>';
			echo '<small>مشاهده پنل فروشنده ←</small></a>';
		} elseif ( Dastyar_Theme_Settings::yes( 'hbtn_cta1_on' ) && '' !== trim( (string) $dth_s['cta1_text'] ) ) {
			printf(
				'<a class="dhm-btn dhm-btn-primary dhm-drawer-cta" href="%s">%s %s</a>',
				esc_url( Dastyar_Theme_Landing::default_cta_url() ),
				esc_html( $dth_s['cta1_text'] ),
				Dastyar_Theme_Landing::icon( 'arrow-l' )
			);
		}
		if ( Dastyar_Theme_Settings::yes( 'hbtn_cta2_on' ) && '' !== trim( (string) $dth_s['cta2_text'] ) ) {
			printf(
				'<a class="dhm-btn dhm-btn-outline dhm-drawer-cta" href="%s">%s</a>',
				esc_url( Dastyar_Theme_Landing::default_cta2_url() ),
				esc_html( $dth_s['cta2_text'] )
			);
		}
		?>
	</nav>
</div>
<main id="dth-main" class="dhm-home dth-main">
