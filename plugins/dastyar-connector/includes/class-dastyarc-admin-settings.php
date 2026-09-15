<?php
/**
 * DastyarC_Admin_Settings — trait بخش «تنظیمات» پنل کانکتور (v1.12.0 — شکافتن کلاس ادمین).
 * فقط توسط DastyarC_Admin استفاده می‌شود؛ منطق نسبت به قبل هیچ تغییری نکرده است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait DastyarC_Admin_Settings {

	/** (v1.10.0) تب «تنظیمات قدیمی» حذف شد — پیکربندی در ۴ آکوردئون داخل tab_settings است */
	public function page_settings() {
		$this->tab_settings();
	}

	/* ------------------------------------------------------------------
	 * صفحه تنظیمات
	 * ---------------------------------------------------------------- */

	/**
	 * (v1.10.0 — Command Center) تب «تنظیمات» — گروه‌بندی ۴ آکوردئونی:
	 * اتصال / قیمت‌گذاری و سینک / نمایش و صفحات / مرجوعی — نام فیلدها و قرارداد ذخیره عیناً حفظ شد.
	 */
	protected function tab_settings() {
		$mode       = get_option( 'dastyarc_price_mode', 'percent' );
		$round      = get_option( 'dastyarc_price_round', 'none' );
		$apply_mode = get_option( 'dastyarc_price_apply_mode', 'auto' );
		$order_mode = get_option( 'dastyarc_order_mode', 'auto' );
		$statuses   = (array) get_option( 'dastyarc_send_statuses', array( 'processing' ) );
		$rules_now  = trim( (string) get_option( 'dastyarc_rma_rules', '' ) );
		$rules_sync = (string) get_option( 'dastyarc_rules_synced_at', '' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'dastyarc_save_settings' ); ?>
			<input type="hidden" name="action" value="dastyarc_save_settings">
			<input type="hidden" name="dastyarc_full_settings" value="1">

			<!-- ══ گروه ۱: اتصال ══ -->
			<details class="dcx2-acc" open>
				<summary><?php echo DastyarC_Ui::icon( 'link', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <b>اتصال به سایت مرکزی</b><span class="ch"><?php echo DastyarC_Ui::icon( 'chev', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></summary>
				<div class="dcx2-acc-body">
					<table class="form-table" role="presentation">
						<tr>
							<th><label>آدرس سایت مرکزی</label></th>
							<td><input type="url" name="dastyarc_central_url" class="regular-text" value="<?php echo esc_attr( get_option( 'dastyarc_central_url' ) ); ?>" placeholder="https://dastyar.shop" style="direction:ltr"></td>
						</tr>
						<tr>
							<th><label>API Key فروشنده</label></th>
							<td><input type="text" name="dastyarc_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'dastyarc_api_key' ) ); ?>" style="direction:ltr">
								</td>
						</tr>
					</table>
				</div>
			</details>

			<!-- ══ گروه ۲: قیمت‌گذاری و سینک ══ -->
			<details class="dcx2-acc" open>
				<summary><?php echo DastyarC_Ui::icon( 'price', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <b>قیمت‌گذاری و همگام‌سازی</b><span class="ch"><?php echo DastyarC_Ui::icon( 'chev', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></summary>
				<div class="dcx2-acc-body">
					<h3 class="dcx2-sec">فرمول قیمت‌گذاری</h3>
					<table class="form-table" role="presentation">
						<tr>
							<th>حالت قیمت‌گذاری</th>
							<td>
								<label><input type="radio" name="dastyarc_price_mode" value="percent" <?php checked( $mode, 'percent' ); ?>> درصد افزایش نسبت به قیمت تامین</label><br>
								<label><input type="radio" name="dastyarc_price_mode" value="fixed" <?php checked( $mode, 'fixed' ); ?>> افزایش مبلغ ثابت روی قیمت تامین</label>
							</td>
						</tr>
						<tr>
							<th><label>مقدار</label></th>
							<td><input type="number" step="any" name="dastyarc_price_value" value="<?php echo esc_attr( get_option( 'dastyarc_price_value', 30 ) ); ?>">
								</td>
						</tr>
						<tr>
							<th><label>گرد کردن قیمت</label></th>
							<td><select name="dastyarc_price_round">
								<?php foreach ( array( 'none' => 'بدون گرد کردن', '10' => 'نزدیک‌ترین ۱۰', '100' => 'نزدیک‌ترین ۱۰۰', '1000' => 'نزدیک‌ترین ۱۰۰۰' ) as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $round, $val ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select></td>
						</tr>
						<tr>
							<th><label>اعمال به‌روزرسانی قیمت مرکز</label></th>
							<td>
								<label style="display:block;margin-bottom:6px"><input type="radio" name="dastyarc_price_apply_mode" value="auto" <?php checked( $apply_mode, 'auto' ); ?>> <strong>خودکار</strong></label>
								<label style="display:block;margin-bottom:6px"><input type="radio" name="dastyarc_price_apply_mode" value="manual" <?php checked( $apply_mode, 'manual' ); ?>> <strong>دستی</strong></label>
							</td>
						</tr>
					</table>

					<h3 class="dcx2-sec">همگام‌سازی و سفارش‌ها</h3>
					<table class="form-table" role="presentation">
						<tr>
							<th>موجودی</th>
							<td><label><input type="checkbox" name="dastyarc_stock_sync" value="yes" <?php checked( get_option( 'dastyarc_stock_sync', 'yes' ), 'yes' ); ?>> همگام‌سازی خودکار موجودی با مرکز</label></td>
						</tr>
						<tr>
							<th>اعلان‌ها</th>
							<td><label><input type="checkbox" name="dastyarc_notify_email" value="yes" <?php checked( get_option( 'dastyarc_notify_email', 'no' ), 'yes' ); ?>> ایمیل هنگام ثبت خطا (حداکثر هر ۶ ساعت یک‌بار)</label></td>
						</tr>
						<tr>
							<th>وب‌هوک</th>
							<td><label><input type="checkbox" name="dastyarc_webhook_v1_fallback" value="yes" <?php checked( get_option( 'dastyarc_webhook_v1_fallback', 'yes' ), 'yes' ); ?>> پذیرش وب‌هوک نسخه قدیمی (سازگاری با مرکز قدیمی)</label></td>
						</tr>
						<tr>
							<th>دسته‌بندی محصولات</th>
							<td><label><input type="checkbox" name="dastyarc_import_cats" value="yes" <?php checked( get_option( 'dastyarc_import_cats', 'yes' ), 'yes' ); ?>> انتقال دسته‌بندی‌های دستیار شاپ هنگام افزودن محصول</label>
								</td>
						</tr>
						<tr>
							<th>حالت انتقال سفارش‌ها به مرکز</th>
							<td>
								<label style="display:block;margin-bottom:8px"><input type="radio" name="dastyarc_order_mode" value="auto" <?php checked( $order_mode, 'auto' ); ?>> <strong>خودکار</strong></label>
								<label style="display:block"><input type="radio" name="dastyarc_order_mode" value="manual" <?php checked( $order_mode, 'manual' ); ?>> <strong>دستی</strong></label>
							</td>
						</tr>
						<tr>
							<th>ارسال خودکار در وضعیت</th>
							<td class="dastyarc-statuses-cell">
								<?php foreach ( wc_get_order_statuses() as $slug => $label ) :
									$slug_clean = str_replace( 'wc-', '', $slug );
									?>
									<label style="margin-left:14px"><input type="checkbox" name="dastyarc_send_statuses[]" value="<?php echo esc_attr( $slug_clean ); ?>" <?php checked( in_array( $slug_clean, $statuses, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
							</td>
						</tr>
						<tr>
							<th>کد رهگیری</th>
							<td><label><input type="checkbox" name="dastyarc_tracking_completes" value="yes" <?php checked( get_option( 'dastyarc_tracking_completes', 'yes' ), 'yes' ); ?>> بعد از دریافت کد رهگیری از مرکز، سفارش «تکمیل شده» شود</label></td>
						</tr>
					</table>
				</div>
			</details>

			<!-- ══ گروه ۳: نمایش و صفحات ══ -->
			<details class="dcx2-acc">
				<summary><?php echo DastyarC_Ui::icon( 'eye', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <b>نمایش فروشگاه و برگه‌ها</b><span class="ch"><?php echo DastyarC_Ui::icon( 'chev', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></summary>
				<div class="dcx2-acc-body">
					<table class="form-table" role="presentation">
						<tr>
							<th>نشان انبار</th>
							<td><label><input type="checkbox" name="dastyarc_warehouse_badge" value="yes" <?php checked( DastyarC_Badge::enabled() ); ?>> نمایش «ارسال از انبار بندرگناوه» بالای عنوان محصول</label>
								</td>
						</tr>
						<tr>
							<th>برگه «پیگیری سفارش»</th>
							<td>
								<?php $track_page_id = (int) get_option( DastyarC_Track::PAGE_OPTION ); ?>
								<?php if ( $track_page_id && 'page' === get_post_type( $track_page_id ) ) : ?>
									<a href="<?php echo esc_url( get_permalink( $track_page_id ) ); ?>" target="_blank" class="dcx2-btn sm">مشاهده برگه</a>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $track_page_id . '&action=edit' ) ); ?>" class="dcx2-btn sm">ویرایش برگه</a>
									<p class="description">شورتکد: <code>[dastyarc_track]</code></p>
								<?php else : ?>
									<p class="description">برگه یافت نشد.</p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th>برگه «عودت و مرجوعی»</th>
							<td>
								<?php $rma_page_id = (int) get_option( DastyarC_Rma::PAGE_OPTION ); ?>
								<?php if ( $rma_page_id && 'page' === get_post_type( $rma_page_id ) ) : ?>
									<a href="<?php echo esc_url( get_permalink( $rma_page_id ) ); ?>" target="_blank" class="dcx2-btn sm">مشاهده برگه</a>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $rma_page_id . '&action=edit' ) ); ?>" class="dcx2-btn sm">ویرایش برگه</a>
									<a href="<?php echo esc_url( self::hub_url( 'rma' ) ); ?>" class="dcx2-btn prime sm">مدیریت گزارش‌های عودت</a>
									<p class="description">شورتکد: <code>[dastyarc_rma]</code></p>
								<?php else : ?>
									<p class="description">برگه یافت نشد.</p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</div>
			</details>

			<!-- ══ گروه ۴: مرجوعی ══ -->
			<details class="dcx2-acc">
				<summary><?php echo DastyarC_Ui::icon( 'rma', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <b>قوانین عودت و مرجوعی</b><span class="ch"><?php echo DastyarC_Ui::icon( 'chev', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></summary>
				<div class="dcx2-acc-body">
					<div style="background:#f2faf6;border:1px solid #cdeee0;border-right:4px solid var(--g1);border-radius:12px;padding:14px 18px;line-height:2;max-width:720px">
						<?php if ( '' !== $rules_now ) : ?>
							<?php echo nl2br( esc_html( $rules_now ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<p class="description" style="margin:8px 0 0">آخرین همگام‌سازی از مرکز: <?php echo esc_html( $rules_sync ?: '—' ); ?></p>
						<?php else : ?>
							<p style="margin:0" class="description">هنوز متنی از مرکز دریافت نشده است.</p>
						<?php endif; ?>
					</div>
				</div>
			</details>

			<p class="submit" style="padding-top:2px">
				<button type="submit" class="button button-primary">ذخیره تنظیمات</button>
			</p>
		</form>

		<script>
		jQuery(function($){
			function dastyarcToggleStatuses(){
				var manual = $('input[name="dastyarc_order_mode"]:checked').val() === 'manual';
				$('.dastyarc-statuses-cell').css('opacity', manual ? 0.45 : 1)
					.find('input[name="dastyarc_send_statuses[]"]').prop('disabled', manual);
			}
			$('input[name="dastyarc_order_mode"]').on('change', dastyarcToggleStatuses);
			dastyarcToggleStatuses();
		});
		</script>

		<!-- اقدام‌های سریع اتصال/سینک -->
		<section class="dcx2-card">
			<header><?php echo DastyarC_Ui::icon( 'sync', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> اقدام‌های سریع<span class="more"><?php
				$last_stock = (int) get_option( 'dastyarc_last_stock_sync', 0 );
				echo 'آخرین سینک موجودی: ' . esc_html( $last_stock ? date_i18n( 'Y/m/d H:i', $last_stock ) : '—' );
			?></span></header>
			<div class="in" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0">
					<?php wp_nonce_field( 'dastyarc_test' ); ?>
					<input type="hidden" name="action" value="dastyarc_test">
					<button class="dcx2-btn" type="submit"><?php echo DastyarC_Ui::icon( 'link', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> تست اتصال به مرکز</button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0">
					<?php wp_nonce_field( 'dastyarc_stock_sync_now' ); ?>
					<input type="hidden" name="action" value="dastyarc_stock_sync_now">
					<button class="dcx2-btn prime" type="submit"><?php echo DastyarC_Ui::icon( 'sync', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> همگام‌سازی فوری موجودی الان</button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0">
					<?php wp_nonce_field( 'dastyarc_rules_refresh' ); ?>
					<input type="hidden" name="action" value="dastyarc_rules_refresh">
					<button class="dcx2-btn" type="submit"><?php echo DastyarC_Ui::icon( 'rma', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> دریافت مجدد قوانین عودت از مرکز</button>
				</form>
			</div>
		</section>
		<?php
	}

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_save_settings' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		// همیشه ذخیره می‌شود (در هر دو فرم تنظیمات پیشخوان و فرم قیمت‌گذاری پنل مشترک‌اند)
		update_option( 'dastyarc_central_url', untrailingslashit( esc_url_raw( wp_unslash( $_POST['dastyarc_central_url'] ?? '' ) ) ) );
		update_option( 'dastyarc_api_key', sanitize_text_field( wp_unslash( $_POST['dastyarc_api_key'] ?? '' ) ) );
		update_option( 'dastyarc_price_mode', in_array( $_POST['dastyarc_price_mode'] ?? '', array( 'percent', 'fixed' ), true ) ? $_POST['dastyarc_price_mode'] : 'percent' );
		update_option( 'dastyarc_price_value', (float) ( $_POST['dastyarc_price_value'] ?? 30 ) );
		update_option( 'dastyarc_price_round', in_array( $_POST['dastyarc_price_round'] ?? '', array( 'none', '10', '100', '1000' ), true ) ? $_POST['dastyarc_price_round'] : 'none' );
		update_option( 'dastyarc_price_apply_mode', 'manual' === ( $_POST['dastyarc_price_apply_mode'] ?? '' ) ? 'manual' : 'auto' ); // v1.6.0
		update_option( 'dastyarc_stock_sync', ! empty( $_POST['dastyarc_stock_sync'] ) ? 'yes' : 'no' );
		update_option( 'dastyarc_notify_email', ! empty( $_POST['dastyarc_notify_email'] ) ? 'yes' : 'no' ); // v1.12.0
		update_option( 'dastyarc_webhook_v1_fallback', ! empty( $_POST['dastyarc_webhook_v1_fallback'] ) ? 'yes' : 'no' ); // v1.12.0
		update_option( 'dastyarc_tracking_completes', ! empty( $_POST['dastyarc_tracking_completes'] ) ? 'yes' : 'no' );
		if ( isset( $_POST['dastyarc_rma_rules'] ) ) {
			update_option( 'dastyarc_rma_rules', wp_kses_post( wp_unslash( $_POST['dastyarc_rma_rules'] ) ) );
		}

		// فقط از «فرم کامل تنظیمات» (پیشخوان) — فرم‌های کوتاه دیگر این بخش‌ها را دست نمی‌زنند (v1.5.0)
		if ( ! empty( $_POST['dastyarc_full_settings'] ) ) {
			update_option( 'dastyarc_order_mode', in_array( $_POST['dastyarc_order_mode'] ?? '', array( 'auto', 'manual' ), true ) ? sanitize_key( $_POST['dastyarc_order_mode'] ) : 'auto' );
			update_option( 'dastyarc_import_cats', ! empty( $_POST['dastyarc_import_cats'] ) ? 'yes' : 'no' ); // v1.7.8 — انتقال دسته‌بندی مرکز (فقط از فرم کامل تنظیمات)
			update_option( 'dastyarc_send_statuses', array_map( 'sanitize_key', (array) ( $_POST['dastyarc_send_statuses'] ?? array( 'processing' ) ) ) );
			// نشان انبار (v1.5.0)
			update_option( DastyarC_Badge::OPTION, ! empty( $_POST['dastyarc_warehouse_badge'] ) ? 'yes' : 'no' );
			// (v1.9.1) سوییچ‌های ابزارک آمار و حالت تاریک سازمانی از فرم حذف شد ← مقدارشان دیگر تغییر نمی‌کند
		}

		delete_transient( 'dastyarc_central_cats' ); // کش فیلتر دسته‌بندی‌ها تازه شود (v1.6.0)

		$this->notice( 'تنظیمات ذخیره شد.' );
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=settings' ) );
		exit;
	}

	public function handle_test() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_test' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$ping = DastyarC_Client::ping();
		if ( is_wp_error( $ping ) ) {
			$this->notice( 'اتصال ناموفق: ' . $ping->get_error_message(), 'error' );
		} else {
			$this->notice( sprintf( 'اتصال موفق ✔ — فروشنده: %s', $ping['vendor_name'] ?? '' ) );
			// ضمناً قوانین عودت را هم تازه می‌کنیم (تعریف‌شده فقط در مرکز)
			DastyarC_Rma::sync_rules_from_central();
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=settings' ) );
		exit;
	}

	/** همگام‌سازی فوری موجودی (دکمه تنظیمات) */
	public function handle_stock_sync_now() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_stock_sync_now' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$this->run_full_stock_sync();
		DastyarC::log( 'سینک دستی موجودی از هاب اجرا شد.', 'info' );
		// v1.10.0: به همان تبِ مراجعت بازگردد (اکشن سریع «سینک دستی» روی هر تب کار می‌کند)
		$back = wp_get_referer();
		if ( ! $back || false === strpos( (string) $back, 'dastyarc-hub' ) ) {
			$back = self::hub_url( 'stats' );
		}
		wp_safe_redirect( add_query_arg( 'stock_sync', 'done', $back ) );
		exit;
	}

	/** سینک کامل موجودی از صفحه آمار/قدیماً ابزارک → بازگشت به همان صفحه (v1.5.0 / v1.7.0: پیش‌فرض صفحه آمار) */
	public function handle_full_sync() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_full_sync' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$this->run_full_stock_sync();
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=dastyarc-hub&tab=stats' ) );
		exit;
	}

	/** اجرای سینک کامل + ثبت اعلان نتیجه (مشترک دو دکمه تنظیمات و ابزارک) */
	protected function run_full_stock_sync() {
		if ( 'yes' !== get_option( 'dastyarc_stock_sync', 'yes' ) ) {
			$this->notice( 'همگام‌سازی خودکار موجودی خاموش است؛ ابتدا در تنظیمات دستیار فعالش کنید.', 'error' );
			return;
		}
		$n = DastyarC::instance()->sync->sync_stock( true );
		if ( false === $n || is_wp_error( $n ) || -1 === $n ) {
			$this->notice( 'خطا در همگام‌سازی موجودی؛ لاگ WooCommerce را بررسی کنید.', 'error' );
		} elseif ( $n > 0 ) {
			$this->notice( sprintf( 'سینک کامل انجام شد — %d محصول به‌روزرسانی/تازه شد ✔', (int) $n ) );
		} else {
			$this->notice( 'سینک کامل انجام شد — همه موجودی‌ها از قبل به‌روز بودند ✔' );
		}
	}

	/**
	 * ترجمه پیام‌های خام خطای API به فارسیِ قابل‌اقدام (v1.7.1):
	 * - ۴۰۴/rest_no_route ← افزونه مرکز به‌روز نیست (مسیر /wallet/charge ندارد) — راهنمای دقیق به‌روزرسانی
	 * - پیام کاملاً لاتین (cURL/DNS/Timeout) ← بسته‌بندی فارسی + جزئیات فنی
	 */
	public static function friendly_api_error( WP_Error $err ) {
		$msg  = (string) $err->get_error_message();
		$data = $err->get_error_data();
		$http = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;

		if ( 404 === $http
			|| false !== stripos( $msg, 'no route was found' )
			|| false !== stripos( $msg, 'rest_no_route' ) ) {
			return 'مسیر شارژ روی سایت مرکزی پیدا نشد؛ به‌احتمال زیاد افزونه مرکز (دستیار شاپ) هنوز به نسخه جدید به‌روزرسانی نشده است. ابتدا روی سایت مرکزی، افزونه «دستیار کر» را به‌روز کنید و سپس دوباره تلاش کنید.';
		}
		if ( '' !== $msg && ! preg_match( '/[\x{0600}-\x{06FF}]/u', $msg ) ) {
			return 'ارتباط با سایت مرکزی برقرار نشد یا پاسخ نامعتبر بود؛ آدرس مرکز و API Key را در «دستیار شاپ ← تنظیمات دستیار» بررسی کنید. (جزئیات فنی: ' . $msg . ')';
		}
		return $msg;
	}
}
