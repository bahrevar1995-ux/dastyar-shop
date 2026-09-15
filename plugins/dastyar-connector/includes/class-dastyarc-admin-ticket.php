<?php
/**
 * DastyarC_Admin_Ticket — trait بخش «تیکت» پنل کانکتور (v1.12.0 — شکافتن کلاس ادمین).
 * فقط توسط DastyarC_Admin استفاده می‌شود؛ منطق نسبت به قبل هیچ تغییری نکرده است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait DastyarC_Admin_Ticket {

	/* ------------------------------------------------------------------
	 * اعلان‌ها
	 * ---------------------------------------------------------------- */

	/** (v1.10.0 — Command Center) تب «تیکت و پشتیبانی» — فرم + فید گفتگوی دوسویه با پیل وضعیت */
	protected function tab_tickets() {
		if ( ! empty( $_GET['tk_refresh'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_tk_refresh' ) ) {
			delete_transient( 'dastyarc_tickets' );
		}
		$items = get_transient( 'dastyarc_tickets' );
		$err   = '';
		if ( false === $items ) {
			$res = DastyarC_Client::configured()
				? DastyarC_Client::tickets()
				: new WP_Error( 'dastyarc_not_configured', 'ابتدا اتصال را در «تنظیمات دستیار» کامل کنید.' );
			if ( is_wp_error( $res ) ) {
				$err   = self::friendly_api_error( $res );
				$items = array();
			} else {
				$items = (array) ( $res['data'] ?? array() );
				set_transient( 'dastyarc_tickets', $items, 60 );
			}
		}
		?>

		<div class="dcx2-grid2" style="grid-template-columns:1fr 1.5fr">
			<!-- فرم ثبت تیکت -->
			<section class="dcx2-card">
				<header><?php echo DastyarC_Ui::icon( 'ticket', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> ثبت تیکت جدید</header>
				<div class="in">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'dastyarc_ticket_submit' ); ?>
						<input type="hidden" name="action" value="dastyarc_ticket_submit">
						<p style="margin:0 0 12px"><label for="dastyarc-tk-subject" style="display:block;font-weight:700;font-size:12px;margin-bottom:6px">موضوع تیکت</label>
							<input type="text" id="dastyarc-tk-subject" name="subject" required maxlength="190" placeholder="مثلاً: سؤال درباره هزینه ارسال" style="width:100%"></p>
						<p style="margin:0 0 14px"><label for="dastyarc-tk-body" style="display:block;font-weight:700;font-size:12px;margin-bottom:6px">شرح سوال یا مشکل</label>
							<textarea id="dastyarc-tk-body" name="message" rows="6" required placeholder="جزئیات را بنویسید…" style="width:100%"></textarea></p>
						<button class="dcx2-btn prime" type="submit"><?php echo DastyarC_Ui::icon( 'send', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> ارسال تیکت به مرکز ←</button>
					</form>
				</div>
			</section>

			<!-- فید تیکت‌ها -->
			<section class="dcx2-card">
				<header><?php echo DastyarC_Ui::icon( 'sync', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> تیکت‌های اخیر شما
					<a class="more" href="<?php echo esc_url( wp_nonce_url( self::hub_url( 'ticket', array( 'tk_refresh' => 1 ) ), 'dastyarc_tk_refresh' ) ); ?>"><?php echo DastyarC_Ui::icon( 'sync', 11 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> به‌روزرسانی</a>
				</header>
				<div class="in" style="padding-top:10px">
				<?php if ( $err ) : ?>
					<div class="dcx2-notice x-bad">خطا در دریافت تیکت‌ها: <?php echo esc_html( $err ); ?></div>
				<?php elseif ( ! $items ) : ?>
					<?php DastyarC_Ui::empty_state( 'تیکتی ثبت نشده است.' ); ?>
				<?php else : ?>
					<?php
					foreach ( $items as $t ) :
						$st = (string) ( $t['status'] ?? 'open' );
						$tone = 'open' === $st ? 'y' : ( 'answered' === $st ? 'g' : 'x' );
						?>
						<article style="border:1px solid var(--line);border-radius:13px;padding:13px 15px;margin-bottom:12px">
							<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
								<b style="font-size:13px;flex:1"><?php echo esc_html( (string) ( $t['subject'] ?? '' ) ); ?></b>
								<?php echo DastyarC_Ui::pill( (string) ( $t['status_label'] ?? $st ), $tone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<div style="color:var(--mut);font-size:10.5px;margin:3px 0 9px;direction:ltr;text-align:right">#<?php echo (int) ( $t['id'] ?? 0 ); ?> — <?php echo esc_html( ! empty( $t['date_fa'] ) ? (string) $t['date_fa'] : DastyarC_Jalali::dt( (string) ( $t['date'] ?? '' ) ) ); ?></div>
							<div style="font-size:12.5px;line-height:2;color:var(--ink);margin-bottom:8px"><?php echo nl2br( esc_html( (string) ( $t['body'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<?php foreach ( (array) ( $t['replies'] ?? array() ) as $r ) :
								$mine = 'شما' === (string) ( $r['author'] ?? '' );
								?>
								<div style="border-right:3px solid <?php echo $mine ? '#dcdce4' : '#cdeedd'; ?>;background:<?php echo $mine ? '#f9fafb' : '#f6fbf8'; ?>;border-radius:8px;padding:8px 12px;margin:6px 0;font-size:12px;line-height:1.9;color:<?php echo $mine ? '#4b5563' : '#166b47'; ?>">
									<strong style="display:block;font-size:10.5px;color:<?php echo $mine ? '#5b6472' : '#0f7a52'; ?>;margin-bottom:2px"><?php echo esc_html( (string) ( $r['author'] ?? '' ) ); ?> — <?php echo esc_html( ! empty( $r['date_fa'] ) ? (string) $r['date_fa'] : DastyarC_Jalali::dt( (string) ( $r['date'] ?? '' ) ) ); ?></strong>
									<?php echo nl2br( esc_html( (string) ( $r['body'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							<?php endforeach; ?>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
				</div>
			</section>
		</div>
		<?php
	}

	/** ثبت تیکت از فرم پیشخوان فروشنده → مرکز */
	public function handle_ticket_submit() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_ticket_submit' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$subject = sanitize_text_field( wp_unslash( (string) ( $_POST['subject'] ?? '' ) ) );
		$message = sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) );
		if ( '' === $subject || '' === $message ) {
			$this->notice( 'عنوان و متن تیکت الزامی است.', 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=ticket' ) );
			exit;
		}
		$res = DastyarC_Client::ticket_create( $subject, $message );
		if ( is_wp_error( $res ) ) {
			$this->notice( 'ثبت تیکت ناموفق بود: ' . self::friendly_api_error( $res ), 'error' );
		} else {
			delete_transient( 'dastyarc_tickets' );
			$this->notice( 'تیکت شما در مرکز ثبت شد؛ پاسخ در همین صفحه اعلام می‌شود.' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=ticket' ) );
		exit;
	}
}
