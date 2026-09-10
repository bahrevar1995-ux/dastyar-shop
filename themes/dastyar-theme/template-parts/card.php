<?php
/**
 * کارت نوشته (وبلاگ/جستجو/بایگانی)
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article <?php post_class( 'dth-card' ); ?>>
	<a class="dth-card-thumb" href="<?php echo esc_url( get_permalink() ); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'medium' ); ?>
		<?php else : ?>
			<span class="dth-card-noimg"><?php echo Dastyar_Theme_Landing::icon( 'book' ); ?></span>
		<?php endif; ?>
	</a>
	<div class="dth-card-body">
		<h3><a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a></h3>
		<p class="dth-card-ex"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22, '…' ) ); ?></p>
		<div class="dth-card-meta">
			<span><?php echo Dastyar_Theme_Landing::icon( 'clock' ) . esc_html( Dastyar_Theme_Settings::fa_num( get_the_date() ) ); ?></span>
			<a class="dth-card-more" href="<?php echo esc_url( get_permalink() ); ?>">ادامه <?php echo Dastyar_Theme_Landing::icon( 'arrow-l' ); ?></a>
		</div>
	</div>
</article>
