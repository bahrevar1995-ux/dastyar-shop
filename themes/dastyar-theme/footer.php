<?php
/**
 * فوتر قالب «دستیار» — ۴ ستون (معرفی / لینک‌های سریع / تماس / منوی فوتر) + نوار کپی‌رایت
 * v2.1.1 — پنج سبک نمایش فوتر (footer_style): columns | center | deep | split | slim
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$dth_s     = Dastyar_Theme_Settings::all();
$dth_links = Dastyar_Theme_Landing::parse_pairs( $dth_s['footer_links'] );
$dth_year  = Dastyar_Theme_Settings::fa_num( function_exists( 'date_i18n' ) ? date_i18n( 'Y' ) : gmdate( 'Y' ) );
// v2.1.1 — سبک فوتر: columns | center | deep | split | slim
$dth_fstyle = (string) Dastyar_Theme_Settings::get( 'footer_style', 'columns' );
if ( ! in_array( $dth_fstyle, array( 'columns', 'center', 'deep', 'split', 'slim' ), true ) ) {
	$dth_fstyle = 'columns';
}
?>
</main>
<footer class="dth-footer dth-fs-<?php echo esc_attr( $dth_fstyle ); ?>" dir="rtl">
	<div class="dhm-in dth-footer-in">
		<div class="dth-fcol dth-fcol-brand">
			<a class="dhm-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="dhm-logo-t"><?php echo esc_html( '' !== trim( (string) $dth_s['logo_text'] ) ? $dth_s['logo_text'] : get_bloginfo( 'name' ) ); ?></span><span class="dhm-ldot"></span></a>
			<?php if ( '' !== trim( (string) $dth_s['footer_about'] ) ) : ?>
				<p class="dth-fabout"><?php echo esc_html( $dth_s['footer_about'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $dth_links ) : ?>
			<div class="dth-fcol">
				<h4>لینک‌های سریع</h4>
				<ul class="dth-flist">
					<?php foreach ( $dth_links as $dth_fl ) : ?>
						<li><a href="<?php echo esc_url( '' !== $dth_fl[1] ? $dth_fl[1] : '#' ); ?>"><?php echo esc_html( $dth_fl[0] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<div class="dth-fcol">
			<h4>دسترسی</h4>
			<?php
			if ( function_exists( 'has_nav_menu' ) && has_nav_menu( 'footer' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'dth-flist dth-fmenu',
					'fallback_cb'    => false,
					'depth'          => 1,
				) );
			} else {
				echo '<ul class="dth-flist">';
				printf( '<li><a href="%s">%s</a></li>', esc_url( Dastyar_Theme::page_url( 'about_page', 'about' ) ), 'درباره ما' );
				printf( '<li><a href="%s">%s</a></li>', esc_url( Dastyar_Theme::page_url( 'faq_page', 'faq' ) ), 'سوالات متداول' );
				printf( '<li><a href="%s">%s</a></li>', esc_url( Dastyar_Theme::page_url( 'pay_page', 'payment' ) ), 'روند پرداخت' );
				printf( '<li><a href="%s">%s</a></li>', esc_url( Dastyar_Theme::page_url( 'download_page', 'download' ) ), 'دریافت پلاگین' );
				printf( '<li><a href="%s">%s</a></li>', esc_url( Dastyar_Theme::page_url( 'contact_page', 'contact' ) ), 'تماس با ما' );
				echo '</ul>';
			}
			?>
		</div>
		<div class="dth-fcol">
			<h4>تماس با ما</h4>
			<ul class="dth-flist dth-fcontact">
				<?php if ( '' !== trim( (string) $dth_s['contact_phone'] ) ) : ?>
					<li><?php echo Dastyar_Theme_Landing::icon( 'phone' ) . esc_html( $dth_s['contact_phone'] ); ?></li>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $dth_s['contact_email'] ) ) : ?>
					<li><?php echo Dastyar_Theme_Landing::icon( 'mail' ) . '<span dir="ltr">' . esc_html( $dth_s['contact_email'] ) . '</span>'; ?></li>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $dth_s['contact_hours'] ) ) : ?>
					<li><?php echo Dastyar_Theme_Landing::icon( 'clock' ) . esc_html( $dth_s['contact_hours'] ); ?></li>
				<?php endif; ?>
			</ul>
		</div>
	</div>
	<?php
	// v2.4.1 — باکس‌های مجوز فوتر (اینماد/درگاه پرداخت/…): هر باکس دارای کد بود، نمایش داده می‌شود.
	$dth_badges = array();
	if ( Dastyar_Theme_Settings::yes( 'trust_badges_on' ) ) {
		for ( $dth_bn = 1; $dth_bn <= 3; $dth_bn++ ) {
			$dth_bcode = trim( (string) $dth_s[ 'trust_badge_' . $dth_bn . '_code' ] );
			if ( '' !== $dth_bcode ) {
				$dth_badges[] = array(
					'title' => (string) $dth_s[ 'trust_badge_' . $dth_bn . '_title' ],
					'code'  => $dth_bcode,
				);
			}
		}
	}
	if ( $dth_badges ) :
		?>
		<div class="dth-trust-row">
			<div class="dhm-in dth-trust-in">
				<?php foreach ( $dth_badges as $dth_b ) : ?>
					<div class="dth-trust-box">
						<?php if ( '' !== trim( $dth_b['title'] ) ) : ?>
							<span class="dth-trust-box-t"><?php echo esc_html( $dth_b['title'] ); ?></span>
						<?php endif; ?>
						<div class="dth-trust-box-c">
							<?php
							// کد این باکس هنگام ذخیره در پیشخوان پالایش شده (کاربران بدون دسترسی unfiltered_html با wp_kses_post)؛
							// این‌جا عیناً همان مقدار ذخیره‌شده چاپ می‌شود تا اسکریپت اینماد/درگاه سالم اجرا شود.
							echo $dth_b['code']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( Dastyar_Theme_Settings::yes( 'footer_credit' ) ) : ?>
		<div class="dth-copy">
			<div class="dhm-in">© <?php echo esc_html( $dth_year ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?> — همه حقوق محفوظ است. <span class="dth-credit">قدرت‌گرفته از قالب «دستیار»</span></div>
		</div>
	<?php endif; ?>
</footer>
<?php wp_footer(); ?>
</body>
</html>
