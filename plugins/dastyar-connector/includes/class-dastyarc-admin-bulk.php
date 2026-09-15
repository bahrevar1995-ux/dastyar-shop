<?php
/**
 * DastyarC_Admin_Bulk — trait بخش «ویرایش دسته‌جمعی» پنل کانکتور (v1.12.0 — شکافتن کلاس ادمین).
 * فقط توسط DastyarC_Admin استفاده می‌شود؛ منطق نسبت به قبل هیچ تغییری نکرده است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait DastyarC_Admin_Bulk {

	/** (v1.10.0 — Command Center) تب «ویرایش دسته‌جمعی» — بازنویسی مارک‌اپ با dcx2 (متغیرها/فرم/نوشته مطابق قبلی) */
	protected function tab_bulk() {
		if ( isset( $_GET['bkbd_done'] ) ) {
			echo '<div class="dcx2-notice">✔ ویرایش دسته‌جمعی روی ' . esc_html( (string) DastyarC_Jalali::num( (string) (int) $_GET['bkbd_done'] ) ) . ' محصول اعمال شد.</div>';
		}

		// وضعیت فرمول دستی: قیمت‌های تامین جدید در صف اعمال
		$pending = (int) count( (array) get_posts( array(
			'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1,
			'meta_key' => '_dastyar_price_pending', 'meta_value' => 1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		) ) );
		if ( $pending > 0 ) {
			echo '<div class="dcx2-warn"><b>' . esc_html( (string) DastyarC_Jalali::num( (string) $pending ) ) . ' محصول</b>'
				. ' قیمت تامین جدیدی از مرکز گرفته‌اند و در حالت «دستی» در صف اعمال‌اند.'
				. '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_apply_prices' ), 'dastyarc_apply_prices' ) ) . '" class="dcx2-btn prime sm">✔ اعمال به‌روزرسانی قیمت‌ها</a></div>';
		}

		$bkbd_cat = (int) ( $_GET['bkbd_cat'] ?? 0 );
		$bkbd_s   = sanitize_text_field( (string) ( $_GET['bkbd_s'] ?? '' ) );
		$page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$per      = 20;

		$args = array( 'limit' => $per, 'page' => $page );
		if ( $bkbd_cat ) {
			$args['category'] = array( (string) get_term_field( 'slug', $bkbd_cat, 'product_cat' ) );
		}
		if ( '' !== $bkbd_s ) {
			$args['s'] = $bkbd_s;
		}
		$items      = $this->synced_products_query( $args );
		$count_args = $args;
		unset( $count_args['limit'], $count_args['page'] );
		$all_count = count( (array) $this->synced_products_query( array_merge( $count_args, array( 'limit' => -1, 'return' => 'ids' ) ) ) );
		$pages     = max( 1, (int) ceil( $all_count / $per ) );
		?>

		<!-- فیلترها -->
		<section class="dcx2-card">
			<div class="in" style="padding:12px 16px">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0">
					<input type="hidden" name="page" value="dastyarc-hub">
					<input type="hidden" name="tab" value="bulk">
					<?php
					wp_dropdown_categories( array(
						'taxonomy'        => 'product_cat',
						'name'            => 'bkbd_cat',
						'show_option_all' => 'همه دسته‌بندی‌ها',
						'hierarchical'    => true,
						'orderby'         => 'name',
						'order'           => 'ASC',
						'selected'        => $bkbd_cat,
						'value_field'     => 'term_id',
						'class'           => 'postform',
					) );
					?>
					<input type="search" name="bkbd_s" value="<?php echo esc_attr( $bkbd_s ); ?>" placeholder="جستجو در نام محصول…" style="min-width:190px;flex:1">
					<button class="dcx2-btn prime" type="submit"><?php echo DastyarC_Ui::icon( 'chart', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> فیلتر</button>
					<?php if ( $bkbd_cat || '' !== $bkbd_s ) : ?>
						<a class="dcx2-btn" href="<?php echo esc_url( self::hub_url( 'bulk' ) ); ?>">حذف فیلترها</a>
					<?php endif; ?>
					<?php echo DastyarC_Ui::pill( DastyarC_Jalali::num( (string) $all_count ) . ' محصول دستیار در فروشگاه شما', 'b' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</form>
			</div>
		</section>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="dastyarc-bulk-form">
			<?php wp_nonce_field( 'dastyarc_bulk_edit' ); ?>
			<input type="hidden" name="action" value="dastyarc_bulk_edit">

			<!-- نوار اعمال تغییر -->
			<section class="dcx2-card">
				<div class="in" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;border-right:4px solid var(--g1);border-radius:16px">
					<b style="font-size:12.5px"><?php echo DastyarC_Ui::icon( 'edit', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> تغییر قیمت فروش:</b>
					<select name="price_dir">
						<option value="">بدون تغییر قیمت</option>
						<option value="inc_pct">افزایش درصدی ٪+</option>
						<option value="dec_pct">کاهش درصدی ٪−</option>
						<option value="inc_fix">افزایش مبلغ ثابت +</option>
						<option value="dec_fix">کاهش مبلغ ثابت −</option>
					</select>
					<input type="number" step="any" min="0" name="price_adj" placeholder="مقدار (درصد یا مبلغ)" style="width:160px;direction:ltr">
					<b style="font-size:12.5px">وضعیت موجودی:</b>
					<select name="stock_set">
						<option value="">بدون تغییر</option>
						<option value="instock">موجود</option>
						<option value="outofstock">ناموجود</option>
					</select>
					<button class="dcx2-btn prime" type="submit">اعمال روی محصولات انتخاب‌شده</button>
				</div>
			</section>

			<!-- جدول -->
			<section class="dcx2-card" style="overflow-x:auto">
				<table class="dcx2-table">
					<thead><tr>
						<td style="width:36px"><input type="checkbox" id="dastyarc-bulk-all"></td>
						<th style="width:52px">تصویر</th><th>عنوان</th><th>دسته‌بندی</th><th>قیمت تامین فعلی</th><th>قیمت فروش فعلی</th><th>موجودی</th>
					</tr></thead>
					<tbody>
					<?php if ( ! $items ) : ?>
						<tr><td colspan="7" style="text-align:center;color:var(--mut);padding:26px!important">محصول دستیاری مطابق فیلترها یافت نشد.</td></tr>
					<?php endif; ?>
					<?php
					foreach ( $items as $product ) :
						if ( ! $product instanceof WC_Product ) {
							continue;
						}
						$pid      = $product->get_id();
						$supplier = (float) get_post_meta( $pid, '_dastyar_supplier_price', true );
						$cats     = get_the_terms( $pid, 'product_cat' );
						$cat_html = $cats && ! is_wp_error( $cats ) ? esc_html( implode( '، ', wp_list_pluck( $cats, 'name' ) ) ) : '—';
						$img      = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
						?>
						<tr>
							<td><input type="checkbox" class="dastyarc-bulk-cb" name="ids[]" value="<?php echo (int) $pid; ?>"></td>
							<td><?php echo $img ? '<img src="' . esc_url( $img ) . '" alt="" style="width:38px;height:38px;object-fit:cover;border-radius:8px">' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><strong style="font-size:12.5px"><?php echo esc_html( $product->get_name() ); ?></strong><?php echo 'publish' === $product->get_status() ? '' : ' ' . DastyarC_Ui::pill( 'پیش‌نویس', 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br><small style="color:var(--mut)">مرکز: #<?php echo esc_html( (string) (int) get_post_meta( $pid, '_dastyar_remote_id', true ) ); ?></small></td>
							<td style="font-size:11.5px"><?php echo $cat_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo $supplier > 0 ? wp_kses_post( wc_price( $supplier ) ) : '—'; ?></td>
							<td><strong><?php echo wp_kses_post( wc_price( (float) $product->get_price() ) ); ?></strong></td>
							<td><?php echo $product->is_in_stock() ? DastyarC_Ui::pill( 'موجود', 'g' ) : DastyarC_Ui::pill( 'ناموجود', 'r' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</section>

			<?php if ( $pages > 1 ) : ?>
				<div style="display:flex;gap:6px;align-items:center;justify-content:center;margin:12px 0 2px;flex-wrap:wrap">
					<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
						<a class="dcx2-btn sm<?php echo $i === $page ? ' prime' : ''; ?>" href="<?php echo esc_url( self::hub_url( 'bulk', array( 'paged' => $i, 'bkbd_cat' => $bkbd_cat, 'bkbd_s' => $bkbd_s ) ) ); ?>"><?php echo esc_html( (string) DastyarC_Jalali::num( (string) $i ) ); ?></a>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
		</form>

		<script>
		jQuery(function($){
			$('#dastyarc-bulk-all').on('change', function(){ $('.dastyarc-bulk-cb').prop('checked', this.checked); });
		});
		</script>
		<?php
	}

	/** اعمال تغییرات دسته‌جمعی قیمت/موجودی (v1.6.0 — مورد ۱۰) */
	public function handle_bulk_edit() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_bulk_edit' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$ids  = array_values( array_filter( array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) ) ) );
		$dir  = sanitize_key( (string) ( $_POST['price_dir'] ?? '' ) );
		$adj  = (float) ( $_POST['price_adj'] ?? 0 );
		$stk  = in_array( $_POST['stock_set'] ?? '', array( 'instock', 'outofstock' ), true ) ? sanitize_key( $_POST['stock_set'] ) : '';

		if ( ! $ids ) {
			$this->notice( 'هیچ محصولی انتخاب نشده است.', 'error' );
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-hub&tab=bulk' ) );
			exit;
		}
		if ( '' === $dir && '' === $stk ) {
			$this->notice( 'نوع تغییر قیمت یا وضعیت موجودی را مشخص کنید.', 'error' );
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-hub&tab=bulk' ) );
			exit;
		}

		$done = 0;
		foreach ( $ids as $pid ) {
			if ( ! get_post_meta( $pid, '_dastyar_remote_id', true ) ) {
				continue; // فقط محصولات دستیار
			}
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			$touched = false;

			if ( '' !== $dir && $adj > 0 ) {
				$targets = $product->is_type( 'variable' ) ? array_merge( array( $pid ), (array) $product->get_children() ) : array( $pid );
				foreach ( $targets as $tid ) {
					$t = wc_get_product( $tid );
					if ( ! $t ) {
						continue;
					}
					$price = (float) $t->get_regular_price();
					if ( $price <= 0 ) {
						continue;
					}
					switch ( $dir ) {
						case 'inc_pct': $price = $price * ( 1 + $adj / 100 ); break;
						case 'dec_pct': $price = $price * ( 1 - $adj / 100 ); break;
						case 'inc_fix': $price = $price + $adj; break;
						case 'dec_fix': $price = $price - $adj; break;
					}
					$price = max( 0, round( $price ) );
					$t->set_regular_price( (string) $price );
					$t->set_price( (string) $price );
					$t->save();
				}
				$touched = true;
			}
			if ( '' !== $stk ) {
				$targets = $product->is_type( 'variable' ) ? array_merge( array( $pid ), (array) $product->get_children() ) : array( $pid );
				foreach ( $targets as $tid ) {
					$t = wc_get_product( $tid );
					if ( ! $t ) {
						continue;
					}
					$t->set_stock_status( $stk );
					$t->save();
				}
				$touched = true;
			}
			if ( $touched ) {
				$done++;
				DastyarC::log( sprintf( 'Bulk edit on #%d (dir=%s adj=%s stock=%s)', $pid, $dir, $adj, $stk ) );
			}
		}

		$this->notice( sprintf( 'ویرایش دسته‌جمعی روی %d محصول اعمال شد.', $done ) );
		wp_safe_redirect( add_query_arg( 'bkbd_done', $done, wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-hub&tab=bulk' ) ) );
		exit;
	}

	/** دکمه «اعمال به‌روزرسانی قیمت‌ها» (v1.6.0 — مورد ۱۱، حالت دستی فرمول قیمت) */
	public function handle_apply_prices() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_apply_prices' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$done = DastyarC_Importer::apply_pending_prices();
		$this->notice( sprintf( 'فرمول قیمت‌گذاری روی %d محصول اعمال شد.', $done ) );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-hub&tab=bulk' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Override قیمت‌گذاری در صفحه ویرایش محصول دستیاری
	 * ---------------------------------------------------------------- */

	public function product_price_override_fields() {
		global $post;
		if ( ! $post || ! get_post_meta( $post->ID, '_dastyar_remote_id', true ) ) {
			return;
		}
		$mode = get_post_meta( $post->ID, '_dastyar_price_mode', true );
		echo '</div><div class="options_group dastyar-override" style="border-top:1px dashed #ccc">';
		echo '<p class="form-field" style="padding:10px 12px 0"><strong>قیمت‌گذاری دستیار (Override این محصول)</strong></p>';
		woocommerce_wp_select( array(
			'id'      => '_dastyar_price_mode',
			'label'   => 'حالت محاسبه قیمت',
			'value'   => $mode,
			'options' => array(
				''        => 'پیش‌فرض فروشگاه',
				'percent' => 'درصد افزایش روی قیمت تامین',
				'fixed'   => 'افزایش مبلغ ثابت',
			),
		) );
		woocommerce_wp_text_input( array(
			'id'          => '_dastyar_price_value',
			'label'       => 'مقدار (درصد یا مبلغ)',
			'value'       => get_post_meta( $post->ID, '_dastyar_price_value', true ),
			'description' => 'شناسه مرکزی: ' . get_post_meta( $post->ID, '_dastyar_remote_id', true ) . ' — آخرین سینک: ' . ( get_post_meta( $post->ID, '_dastyar_synced_at', true ) ?: '—' ),
		) );
	}

	public function save_product_price_override( $product ) {
		if ( ! $product->get_meta( '_dastyar_remote_id' ) ) {
			return;
		}
		$mode = sanitize_key( $_POST['_dastyar_price_mode'] ?? '' );
		if ( in_array( $mode, array( 'percent', 'fixed' ), true ) ) {
			$product->update_meta_data( '_dastyar_price_mode', $mode );
			$product->update_meta_data( '_dastyar_price_value', (float) ( $_POST['_dastyar_price_value'] ?? 0 ) );
		} else {
			$product->delete_meta_data( '_dastyar_price_mode' );
		}
	}
}
