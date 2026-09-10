<?php
/**
 * دیدگاه‌ها — لیست + فرم به سبک قالب
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( post_password_required() ) {
	return;
}
?>
<div class="dth-comments">
	<?php if ( have_comments() ) : ?>
		<h3><?php echo esc_html( Dastyar_Theme_Settings::fa_num( get_comments_number() ) ); ?> دیدگاه</h3>
		<ul class="dth-comment-list">
			<?php
			wp_list_comments( array(
				'style'      => 'ul',
				'avatar_size' => 36,
				'short_ping' => true,
			) );
			?>
		</ul>
	<?php endif; ?>
	<?php if ( comments_open() ) : ?>
		<?php comment_form( array( 'title_reply' => 'دیدگاه شما', 'label_submit' => 'ثبت دیدگاه' ) ); ?>
	<?php endif; ?>
</div>
