<?php
/**
 * DastyarC_Admin_Products — trait بخش «محصولات» پنل کانکتور (v1.12.0 — شکافتن کلاس ادمین).
 * فقط توسط DastyarC_Admin استفاده می‌شود؛ منطق نسبت به قبل هیچ تغییری نکرده است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait DastyarC_Admin_Products {

	/* ------------------------------------------------------------------
	 * صفحه «محصولات دستیار» — لیست از WooCommerce سایت مرکزی + افزودن تکی/دسته‌جمعی
	 * ---------------------------------------------------------------- */

	/** (v1.10.0 — Command Center) تب «محصولات دستیار» — بازنویسی کامل مارک‌اپ با سیستم dcx2 (قرارداد داده/فرم‌ها/نوشته‌ها عیناً حفظ شد) */
	protected function tab_products() {
		if ( ! DastyarC_Client::configured() ) {
			echo '<div class="dcx2-warn">ابتدا اتصال به مرکز را کامل کنید.<a href="' . esc_url( self::hub_url( 'settings' ) ) . '">برو به تنظیمات ←</a></div>';
			DastyarC_Ui::empty_state( 'محصولی برای نمایش وجود ندارد.' );
			return;
		}

		$page   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$search = sanitize_text_field( (string) ( $_GET['s'] ?? '' ) );
		$dcat   = sanitize_title( (string) ( $_GET['dcat'] ?? '' ) );
		$dstock = in_array( $_GET['dstock'] ?? '', array( 'instock', 'outofstock' ), true ) ? sanitize_key( $_GET['dstock'] ) : '';
		$dimp   = in_array( $_GET['dimp'] ?? '', array( 'added', 'notadded' ), true ) ? sanitize_key( $_GET['dimp'] ) : '';
		$per    = (int) ( $_GET['dpp'] ?? 20 );
		$per    = in_array( $per, array( 10, 20, 25, 50 ), true ) ? $per : 20;

		$query = array( 'page' => $page, 'per_page' => $per );
		if ( '' !== $search ) {
			$query['search'] = $search;
		}
		if ( '' !== $dcat ) {
			$query['category'] = $dcat;
		}
		if ( '' !== $dstock ) {
			$query['stock_status'] = $dstock;
		}

		// (v1.12.0) کش ۵ دقیقه‌ای لیست مرکز — رفرش صفحه دیگر یعنی یک درخواست HTTP تازه نیست
		$ckey   = 'dastyarc_products_' . md5( (string) wp_json_encode( $query ) );
		$result = ( 1 === (int) ( $_GET['refresh'] ?? 0 ) ) ? false : get_transient( $ckey );
		if ( ! is_array( $result ) ) {
			$result = DastyarC_Client::products( $query );
			if ( ! is_wp_error( $result ) ) {
				set_transient( $ckey, $result, 5 * MINUTE_IN_SECONDS );
			}
		}
		if ( is_wp_error( $result ) ) {
			echo '<div class="dcx2-notice x-bad">خطا در دریافت محصولات از مرکز: ' . esc_html( $result->get_error_message() ) . '</div>';
			$cached = get_option( self::SNAPSHOT );
			$result = $cached ?: array( 'data' => array(), 'total' => 0 );
			if ( $cached ) {
				echo '<div class="dcx2-notice">نمایش آخرین نسخه کش‌شده محصولات (اتصال موقتاً برقرار نیست).</div>';
			}
		} elseif ( 1 === $page && '' === $search && '' === $dcat && '' === $dstock ) {
			update_option( self::SNAPSHOT, $result, false );
		}

		$items = (array) ( $result['data'] ?? array() );
		$total = (int) ( $result['total'] ?? 0 );

		// نقشه محصولاتی که قبلاً به فروشگاه اضافه شده‌اند
		$imported = array();
		foreach ( (array) get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_dastyar_remote_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		) ) as $pid ) {
			$imported[ (int) get_post_meta( $pid, '_dastyar_remote_id', true ) ] = $pid;
		}
		$detached = array();
		foreach ( $imported as $rid => $lid ) {
			if ( get_post_meta( $lid, '_dastyarc_detached', true ) ) {
				$detached[ $rid ] = $lid;
			}
		}

		$dimp_total = count( $items );
		$dimp_added = 0;
		foreach ( $items as $it0 ) {
			if ( isset( $imported[ (int) ( $it0['id'] ?? 0 ) ] ) ) {
				$dimp_added++;
			}
		}
		if ( '' !== $dimp ) {
			$items = array_values( array_filter( $items, static function ( $it0 ) use ( $imported, $dimp ) {
				$is_in = isset( $imported[ (int) ( $it0['id'] ?? 0 ) ] );
				return 'added' === $dimp ? $is_in : ! $is_in;
			} ) );
		}

		// ناموجودهای مرکز ← انتهای همان صفحه (ترتیب نسبی مرکز حفظ می‌شود)
		$in_stock  = array();
		$out_stock = array();
		foreach ( $items as $it_s ) {
			if ( 'outofstock' === (string) ( $it_s['stock_status'] ?? '' ) ) {
				$out_stock[] = $it_s;
			} else {
				$in_stock[] = $it_s;
			}
		}
		$items = array_merge( $in_stock, $out_stock );

		$pref_status = (string) get_user_meta( get_current_user_id(), 'dastyarc_import_status', true );
		if ( ! in_array( $pref_status, array( 'draft', 'publish' ), true ) ) {
			$pref_status = 'draft';
		}

		// دسته‌بندی‌های مرکز برای فیلتر (+فالبک کش)
		$cats_for_filter = array();
		$cats            = DastyarC_Client::categories();
		if ( ! is_wp_error( $cats ) && $cats ) {
			foreach ( $cats as $c ) {
				$cats_for_filter[ (string) $c['slug'] ] = (string) $c['name'];
			}
		}
		if ( ! $cats_for_filter ) {
			foreach ( (array) ( get_option( self::SNAPSHOT )['data'] ?? array() ) as $it ) {
				foreach ( (array) ( $it['categories'] ?? array() ) as $cn ) {
					$cn = (string) $cn;
					if ( '' !== $cn ) {
						$cats_for_filter[ sanitize_title( $cn ) ] = $cn;
					}
				}
			}
			asort( $cats_for_filter );
		}
		?>

		<!-- نوار فیلترها -->
		<section class="dcx2-card">
			<div class="in" style="padding:12px 16px">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0">
					<input type="hidden" name="page" value="dastyarc-hub">
					<input type="hidden" name="tab" value="products">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="جستجو در محصولات مرکز…" style="min-width:200px;flex:1">
					<select name="dcat" style="min-width:150px">
						<option value="">همه دسته‌بندی‌ها</option>
						<?php foreach ( $cats_for_filter as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $dcat, $slug ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="dstock" style="min-width:130px">
						<option value="">موجودی: همه</option>
						<option value="instock" <?php selected( $dstock, 'instock' ); ?>>فقط موجود</option>
						<option value="outofstock" <?php selected( $dstock, 'outofstock' ); ?>>فقط ناموجود</option>
					</select>
					<select name="dimp" style="min-width:150px">
						<option value="">وضعیت افزودن: همه</option>
						<option value="added" <?php selected( $dimp, 'added' ); ?>>افزوده شده به فروشگاهم</option>
						<option value="notadded" <?php selected( $dimp, 'notadded' ); ?>>هنوز اضافه نشده</option>
					</select>
					<select name="dpp" style="min-width:115px">
						<?php foreach ( array( 10, 20, 25, 50 ) as $pp ) : ?>
							<option value="<?php echo (int) $pp; ?>" <?php selected( $per, $pp ); ?>><?php echo (int) $pp; ?> مورد در صفحه</option>
						<?php endforeach; ?>
					</select>
					<button class="dcx2-btn prime" type="submit"><?php echo DastyarC_Ui::icon( 'chart', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> فیلتر / جستجو</button>
					<?php if ( '' !== $search || '' !== $dcat || '' !== $dstock || '' !== $dimp || 20 !== $per ) : ?>
						<a class="dcx2-btn" href="<?php echo esc_url( self::hub_url( 'products' ) ); ?>">حذف فیلترها</a>
					<?php endif; ?>
					<a class="dcx2-btn" href="<?php echo esc_url( self::hub_url( 'products', array( 'refresh' => 1 ) ) ); ?>"><?php echo DastyarC_Ui::icon( 'sync', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> به‌روزرسانی از مرکز</a>
					<?php echo DastyarC_Ui::pill( DastyarC_Jalali::num( (string) $dimp_added ) . ' افزوده‌شده از ' . DastyarC_Jalali::num( (string) $dimp_total ) . ' مورد این صفحه', 'g' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</form>
			</div>
		</section>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'dastyarc_bulk_import' ); ?>
			<input type="hidden" name="action" value="dastyarc_bulk_import">

			<!-- نوار افزودن دسته‌جمعی -->
			<section class="dcx2-card">
				<div class="in" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;border-right:4px solid var(--g1);border-radius:16px">
					<b style="font-size:12.5px"><?php echo DastyarC_Ui::icon( 'plus', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> اقدام دسته‌جمعی:</b>
					<label style="display:flex;align-items:center;gap:6px;font-size:11.5px;color:var(--mut);font-weight:700">وضعیت پس از افزودن:
						<select name="import_status" id="dastyarc-import-status">
							<option value="draft" <?php selected( $pref_status, 'draft' ); ?>>پیش‌نویس</option>
							<option value="publish" <?php selected( $pref_status, 'publish' ); ?>>منتشر شده</option>
						</select>
					</label>
					<button class="dcx2-btn prime" type="submit" name="bulk_op" value="import">افزودن به فروشگاه</button>
					<button class="dcx2-btn" type="submit" name="bulk_op" value="publish">انتشار انتخاب‌شده‌ها</button>
				</div>
			</section>

			<!-- جدول محصولات -->
			<section class="dcx2-card" style="overflow-x:auto">
				<table class="dcx2-table">
					<thead><tr>
						<td style="width:36px"><input type="checkbox" id="dastyarc-select-all" title="انتخاب همه"></td>
						<th style="width:52px">تصویر</th><th>عنوان</th><th>موجودی مرکز</th><th>قیمت تامین</th><th>قیمت شما</th><th>وضعیت</th><th style="width:60px">اقدام</th>
					</tr></thead>
					<tbody>
					<?php if ( ! $items ) : ?>
						<tr><td colspan="8" style="text-align:center;color:var(--mut);padding:30px!important">محصولی مطابق فیلترها یافت نشد.</td></tr>
					<?php endif; ?>
					<?php
					foreach ( $items as $item ) :
						$remote_id = (int) $item['id'];
						$local_id  = $imported[ $remote_id ] ?? 0;
						$image     = ! empty( $item['images'][0]['src'] ) ? $item['images'][0]['src'] : wc_placeholder_img_src();
						$supplier  = (float) $item['supplier_price'];
						$your      = $local_id ? DastyarC_Price::calculate( $supplier, $local_id ) : DastyarC_Price::calculate( $supplier );

						$supplier_html = wc_price( $supplier );
						$your_html     = wc_price( (float) $your );
						if ( 'variable' === (string) ( $item['type'] ?? '' ) && ! empty( $item['variations'] ) ) {
							$var_prices = array();
							foreach ( (array) $item['variations'] as $vv ) {
								$vv = (array) $vv;
								$vp = (float) ( $vv['supplier_price'] ?? 0 );
								if ( $vp > 0 ) {
									$var_prices[] = $vp;
								}
							}
							if ( $var_prices ) {
								$v_min = min( $var_prices );
								$v_max = max( $var_prices );
								$y_min = (float) ( $local_id ? DastyarC_Price::calculate( $v_min, $local_id ) : DastyarC_Price::calculate( $v_min ) );
								$y_max = (float) ( $local_id ? DastyarC_Price::calculate( $v_max, $local_id ) : DastyarC_Price::calculate( $v_max ) );
								$supplier_html = $v_min === $v_max ? wc_price( $v_min ) : wc_price( $v_min ) . ' <small>تا</small> ' . wc_price( $v_max );
								$your_html     = $y_min === $y_max ? wc_price( $y_min ) : wc_price( $y_min ) . ' <small>تا</small> ' . wc_price( $y_max );
							}
						}
						?>
						<tr>
							<td><input type="checkbox" class="dastyarc-cb" name="remote_ids[]" value="<?php echo (int) $remote_id; ?>"></td>
							<td><img src="<?php echo esc_url( $image ); ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:10px;box-shadow:0 1px 4px rgba(16,24,40,.12)"></td>
							<td><strong style="font-size:12.5px"><?php echo esc_html( (string) $item['name'] ); ?></strong></td>
							<td><?php echo 'outofstock' === (string) ( $item['stock_status'] ?? 'instock' ) ? DastyarC_Ui::pill( 'ناموجود', 'r' ) : DastyarC_Ui::pill( 'موجود', 'g' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><span style="font-weight:700;font-size:12.5px"><?php echo wp_kses_post( $supplier_html ); ?></span></td>
							<td><b style="color:var(--g1);font-size:12.5px"><?php echo wp_kses_post( $your_html ); ?></b></td>
							<td><?php
								if ( $local_id && isset( $detached[ $remote_id ] ) ) {
									echo DastyarC_Ui::pill( 'انبار شما', 'y' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								} elseif ( $local_id ) {
									$st_obj = get_post_status_object( get_post_status( $local_id ) );
									echo DastyarC_Ui::pill( $st_obj ? (string) $st_obj->label : 'افزوده شد', 'g' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									// (v1.12.0) نشان قفل سینک
									$lk_marks = array();
									if ( get_post_meta( $local_id, '_dastyarc_lock_price', true ) ) { $lk_marks[] = 'قیمت'; }
									if ( get_post_meta( $local_id, '_dastyarc_lock_stock', true ) ) { $lk_marks[] = 'موجودی'; }
									if ( get_post_meta( $local_id, '_dastyarc_lock_content', true ) ) { $lk_marks[] = 'محتوا'; }
									if ( $lk_marks ) { echo ' <span title="قفل: ' . esc_attr( implode( '، ', $lk_marks ) ) . '">🔒</span>'; }
								} else {
									echo '<span style="color:var(--mut)">—</span>';
								}
							?></td>
							<td><div class="dcx2-menu">
								<button type="button" class="dcx2-dots" aria-label="اقدام‌ها"><?php echo DastyarC_Ui::icon( 'dots', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								<div class="dcx2-menu-src" style="display:none"><?php
									if ( $local_id && isset( $detached[ $remote_id ] ) ) {
										echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $local_id . '&action=edit' ) ) . '">ویرایش محصول</a>'
											. '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_reattach&remote_id=' . $remote_id ), 'dastyarc_reattach_' . $remote_id ) ) . '">اتصال مجدد به دستیار</a>';
									} elseif ( $local_id ) {
										$lk = array(
											'price'   => (bool) get_post_meta( $local_id, '_dastyarc_lock_price', true ),
											'stock'   => (bool) get_post_meta( $local_id, '_dastyarc_lock_stock', true ),
											'content' => (bool) get_post_meta( $local_id, '_dastyarc_lock_content', true ),
										);
										$lk_url = array();
										foreach ( array( 'price', 'stock', 'content' ) as $lk_part ) {
											$lk_url[ $lk_part ] = wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_lock&remote_id=' . $remote_id . '&part=' . $lk_part ), 'dastyarc_lock_' . $remote_id );
										}
										echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $local_id . '&action=edit' ) ) . '">ویرایش محصول</a>'
											. '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_resync&remote_id=' . $remote_id ), 'dastyarc_resync_' . $remote_id ) ) . '">سینک مجدد</a>'
											. '<a href="' . esc_url( $lk_url['price'] ) . '">' . ( $lk['price'] ? '🔓 باز کردن قیمت' : '🔒 قفل قیمت' ) . '</a>'
											. '<a href="' . esc_url( $lk_url['stock'] ) . '">' . ( $lk['stock'] ? '🔓 باز کردن موجودی' : '🔒 قفل موجودی' ) . '</a>'
											. '<a href="' . esc_url( $lk_url['content'] ) . '">' . ( $lk['content'] ? '🔓 باز کردن محتوا' : '🔒 قفل محتوا' ) . '</a>'
											. '<a class="danger" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_detach&remote_id=' . $remote_id ), 'dastyarc_detach_' . $remote_id ) ) . '">قطع اتصال</a>';
									} else {
										echo '<a class="dastyarc-add-single" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_import&remote_id=' . $remote_id . '&status=' . $pref_status ), 'dastyarc_import_' . $remote_id ) ) . '">افزودن به فروشگاه</a>';
									}
								?></div>
							</div></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</section>

			<?php
			// صفحه‌بندی فشرده: قبلی | N از M | بعدی
			$pages = max( 1, (int) ceil( $total / $per ) );
			if ( $pages > 1 ) :
				$pg_url = static function ( $pp ) use ( $search, $dcat, $dstock, $dimp, $per ) {
					return esc_url( self::hub_url( 'products', array(
						'paged'  => max( 1, (int) $pp ),
						's'      => $search,
						'dcat'   => $dcat,
						'dstock' => $dstock,
						'dimp'   => $dimp,
						'dpp'    => $per,
					) ) );
				};
				?>
				<div style="display:flex;gap:8px;align-items:center;justify-content:center;margin:14px 0 4px">
					<?php if ( $page > 1 ) : ?>
						<a class="dcx2-btn" href="<?php echo $pg_url( $page - 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo DastyarC_Ui::icon( 'chev', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> قبلی</a>
					<?php else : ?>
						<span class="dcx2-btn" style="opacity:.4"><?php echo DastyarC_Ui::icon( 'chev', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> قبلی</span>
					<?php endif; ?>
					<span style="font-size:12px">صفحه <b style="color:var(--g1)"><?php echo esc_html( (string) DastyarC_Jalali::num( (string) $page ) ); ?></b> از <b><?php echo esc_html( (string) DastyarC_Jalali::num( (string) $pages ) ); ?></b><span style="color:var(--mut);font-size:11px"> — <?php echo esc_html( (string) DastyarC_Jalali::num( (string) $total ) ); ?> محصول</span></span>
					<?php if ( $page < $pages ) : ?>
						<a class="dcx2-btn" href="<?php echo $pg_url( $page + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">بعدی <?php echo DastyarC_Ui::icon( 'chev', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					<?php else : ?>
						<span class="dcx2-btn" style="opacity:.4">بعدی <?php echo DastyarC_Ui::icon( 'chev', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</form>

		<div id="dcx2-pop" role="menu"></div>
		<script>
		jQuery(function($){
			$('#dastyarc-select-all').on('change', function(){
				$('.dastyarc-cb').prop('checked', this.checked);
			});
			$('#dastyarc-import-status').on('change', function(){
				var st = $(this).val();
				$('a.dastyarc-add-single').each(function(){
					var href = $(this).attr('href');
					if (href.indexOf('status=') > -1) {
						href = href.replace(/([?&])status=[^&]*/, '$1status=' + st);
					} else {
						href += '&status=' + st;
					}
					$(this).attr('href', href);
				});
			});
			// (v1.11.0) منوی ⋮ — پاپ‌اپ شناور مشترک (بیرون از جدول تا در اسکرول بریده نشود)
			var $pop = $('#dcx2-pop');
			function dcx2ClosePop(){ $pop.removeClass('open').empty(); $pop.data('for', null); }
			$(document).on('click', '.dcx2-dots', function(e){
				e.stopPropagation();
				if ( $pop.data('for') === this && $pop.hasClass('open') ) { dcx2ClosePop(); return; }
				var html = $(this).siblings('.dcx2-menu-src').html() || '';
				$pop.data('for', this).html(html);
				$pop.css({visibility:'hidden'}).addClass('open');
				var r = this.getBoundingClientRect();
				var pw = $pop.outerWidth() || 190, ph = $pop.outerHeight() || 100;
				var top = r.bottom + 6;
				if ( top + ph > window.innerHeight - 8 ) { top = Math.max(8, r.top - ph - 6); }
				var left = r.right - pw;
				left = Math.max(8, Math.min(left, window.innerWidth - pw - 8));
				$pop.css({top: top + 'px', left: left + 'px', visibility:'visible'});
			});
			$(document).on('click', function(e){
				if ( ! $(e.target).closest('#dcx2-pop,.dcx2-dots').length ) { dcx2ClosePop(); }
			});
			$(document).on('keydown', function(e){ if ( 'Escape' === e.key ) { dcx2ClosePop(); } });
			$(window).on('resize scroll', function(){ dcx2ClosePop(); });
		});
		</script>
		<?php
	}

	/* ------------------------------------------------------------------
	 * افزودن محصول به فروشگاه (تکی)
	 * ---------------------------------------------------------------- */

	public function handle_import() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$remote_id = (int) ( $_GET['remote_id'] ?? 0 );
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_import_' . $remote_id ) ) {
			wp_die( 'نشست نامعتبر' );
		}

		// وضعیت انتخابی فروشنده در همان صفحه (draft = رفتار قبلی)
		$status = in_array( $_GET['status'] ?? '', array( 'draft', 'publish' ), true ) ? sanitize_key( $_GET['status'] ) : 'draft';
		update_user_meta( get_current_user_id(), 'dastyarc_import_status', $status );

		// (v1.11.0) ایمپورت تکی هم سقف زمانی/حافظه می‌خواهد (محصول پرتصویر/پرتنوع در هاست ضعیف) + هر خطای
		// غیرمنتظره به اعلان تمیز تبدیل می‌شود تا هرگز صفحه «خطای مهم» دیده نشود
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		try {
			$result = DastyarC_Importer::import_remote( $remote_id, $status );
		} catch ( \Throwable $e ) {
			DastyarC::log( sprintf( 'Import crashed (remote #%d): %s in %s:%d', $remote_id, $e->getMessage(), basename( (string) $e->getFile() ), (int) $e->getLine() ), 'error' );
			$result = new WP_Error( 'dastyarc_import_failed', 'افزودن محصول ناموفق بود؛ دوباره تلاش کنید.' );
		}

		if ( is_wp_error( $result ) ) {
			$this->notice( 'خطا در افزودن محصول: ' . $result->get_error_message(), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
			exit;
		}

		if ( 'publish' === $status ) {
			$this->notice( 'محصول به فروشگاه اضافه و منتشر شد.' );
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
		} else {
			$this->notice( 'محصول به فروشگاه اضافه شد (پیش‌نویس).' );
			wp_safe_redirect( admin_url( 'post.php?post=' . (int) $result . '&action=edit' ) );
		}
		exit;
	}

	/* ------------------------------------------------------------------
	 * افزودن دسته‌جمعی محصولات (با وضعیت انتخابی فروشنده)
	 * ---------------------------------------------------------------- */

	public function handle_bulk_import() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_bulk_import' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}

		$status = in_array( $_POST['import_status'] ?? '', array( 'draft', 'publish' ), true ) ? sanitize_key( $_POST['import_status'] ) : 'draft';
		update_user_meta( get_current_user_id(), 'dastyarc_import_status', $status );

		$remote_ids = array_values( array_filter( array_map( 'absint', (array) ( $_POST['remote_ids'] ?? array() ) ) ) );
		if ( ! $remote_ids ) {
			$this->notice( 'هیچ محصولی انتخاب نشده است.', 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
			exit;
		}

		// در افزودن دسته‌جمعی، دانلود تصاویر ممکن است کمی طول بکشد
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		// (v1.11.0) عملیات «انتشار»: محصولاتِ از قبل افزوده‌شدهِ انتخاب‌شده، منتشر می‌شوند
		$op = ( (string) ( $_POST['bulk_op'] ?? '' ) === 'publish' ) ? 'publish' : 'import';
		if ( 'publish' === $op ) {
			$ok   = 0;
			$skip = 0;
			foreach ( $remote_ids as $remote_id ) {
				$local_id = DastyarC_Importer::local_id( $remote_id );
				if ( ! $local_id ) {
					$skip++;
					continue;
				}
				try {
					$product = function_exists( 'wc_get_product' ) ? wc_get_product( $local_id ) : null;
					if ( ! $product ) {
						$skip++;
						continue;
					}
					if ( 'publish' !== $product->get_status() ) {
						$product->set_status( 'publish' );
						$product->save();
					}
					$ok++;
				} catch ( \Throwable $e ) {
					$skip++;
					DastyarC::log( sprintf( 'Bulk publish failed for remote #%d: %s', $remote_id, $e->getMessage() ), 'error' );
				}
			}
			$msg = sprintf( 'انتشار دسته‌جمعی انجام شد: %d محصول منتشر شد.', $ok );
			if ( $skip ) {
				$msg .= sprintf( ' %d مورد رد شد (به فروشگاه اضافه نشده یا ناموفق).', $skip );
			}
			$this->notice( $msg, ( $skip && ! $ok ) ? 'error' : 'success' );
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
			exit;
		}

		$ok   = 0;
		$fail = 0;
		$dup  = 0;
		foreach ( $remote_ids as $remote_id ) {
			// (v1.11.0) چک‌باکس حالا روی همه ردیف‌هاست؛ مواردِ قبلاً افزوده‌شده ایمپورت مجدد نمی‌شوند
			if ( DastyarC_Importer::local_id( $remote_id ) ) {
				$dup++;
				continue;
			}
			try {
				$result = DastyarC_Importer::import_remote( $remote_id, $status );
			} catch ( \Throwable $e ) {
				$result = new WP_Error( 'dastyarc_import_failed', $e->getMessage() );
			}
			if ( is_wp_error( $result ) ) {
				$fail++;
				DastyarC::log( sprintf( 'Bulk import failed for remote #%d: %s', $remote_id, $result->get_error_message() ), 'error' );
			} else {
				$ok++;
			}
		}

		$label = 'publish' === $status ? 'منتشر شده' : 'پیش‌نویس';
		$msg   = sprintf( 'افزودن دسته‌جمعی انجام شد: %d محصول با وضعیت «%s» به فروشگاه اضافه شد.', $ok, $label );
		if ( $dup ) {
			$msg .= sprintf( ' %d مورد قبلاً افزوده شده بود.', $dup );
		}
		if ( $fail ) {
			$msg .= sprintf( ' %d مورد ناموفق بود.', $fail );
		}

		$this->notice( $msg, ( $fail && ! $ok ) ? 'error' : 'success' );
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
		exit;
	}

	public function handle_resync() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$remote_id = (int) ( $_GET['remote_id'] ?? 0 );
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_resync_' . $remote_id ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$rs = DastyarC_Importer::import_remote( $remote_id );
		if ( is_wp_error( $rs ) && 'dastyarc_detached' === $rs->get_error_code() ) {
			$this->notice( $rs->get_error_message(), 'warning' );
		} else {
			$this->notice( 'محصول با مرکز همگام‌سازی شد.' );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * (v1.7.8 — بازخورد کاربر) خروج موقت یک محصول از دراپ‌شیپینگ + اتصال مجدد
	 * ---------------------------------------------------------------- */

	/** خروج از دراپ‌شیپینگ: محصول دیگر از مرکز سینک نمی‌شود و فروشنده از انبار خودش ارسال می‌کند */
	public function handle_detach() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$remote_id = (int) ( $_GET['remote_id'] ?? 0 );
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_detach_' . $remote_id ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$local_id = DastyarC_Importer::local_id( $remote_id );
		if ( $local_id ) {
			DastyarC_Importer::detach( $local_id );
			$this->notice( 'محصول از دراپ‌شیپینگ خارج شد.' );
		} else {
			$this->notice( 'این محصول هنوز به فروشگاه اضافه نشده است.', 'warning' );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
		exit;
	}

	/** اتصال مجدد: پرچم خروج برداشته می‌شود و بلافاصله با مرکز همگام می‌شود */
	public function handle_reattach() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$remote_id = (int) ( $_GET['remote_id'] ?? 0 );
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_reattach_' . $remote_id ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$local_id = DastyarC_Importer::local_id( $remote_id );
		if ( $local_id ) {
			DastyarC_Importer::reattach( $local_id );
			$rs = DastyarC_Importer::import_remote( $remote_id );
			if ( is_wp_error( $rs ) ) {
				$this->notice( 'اتصال مجدد برقرار شد، اما همگام‌سازی فوری ممکن نشد: ' . $rs->get_error_message(), 'warning' );
			} else {
				$this->notice( 'اتصال مجدد برقرار شد.' );
			}
		} else {
			$this->notice( 'این محصول هنوز به فروشگاه اضافه نشده است.', 'warning' );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
		exit;
	}

	/** قفل/بازکردن سینک قیمت/موجودی/محتوای یک محصول (v1.12.0) */
	public function handle_lock() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$remote_id = (int) ( $_GET['remote_id'] ?? 0 );
		$part      = sanitize_key( $_GET['part'] ?? '' );
		if ( ! in_array( $part, array( 'price', 'stock', 'content' ), true ) ) {
			wp_die( 'درخواست نامعتبر' );
		}
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_lock_' . $remote_id ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$local_id = DastyarC_Importer::local_id( $remote_id );
		if ( ! $local_id ) {
			$this->notice( 'این محصول هنوز به فروشگاه اضافه نشده است.', 'warning' );
		} else {
			$key = '_dastyarc_lock_' . $part;
			if ( get_post_meta( $local_id, $key, true ) ) {
				delete_post_meta( $local_id, $key );
				$this->notice( 'قفل باز شد.' );
			} else {
				update_post_meta( $local_id, $key, 1 );
				$this->notice( 'قفل شد.' );
			}
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
		exit;
	}

	/* ==================================================================
	 * ویرایش دسته‌جمعی دستیار (v1.6.0 — مورد ۱۰)
	 * محصولاتِ افزوده‌شده از مرکز (+فیلتر دسته/جستجو) — تغییر گروهی قیمت و موجودی
	 * ================================================================= */

	/** لیست محصولات محلیِ دارای متای _dastyar_remote_id با فیلترها */
	protected function synced_products_query( array $opts ) {
		return wc_get_products( array_merge( array(
			'status'  => array( 'publish', 'draft' ),
			'limit'   => 20,
			'return'  => 'objects',
			'orderby' => 'date',
			'order'   => 'DESC',
			'meta_query' => array(
				array( 'key' => '_dastyar_remote_id', 'compare' => 'EXISTS' ),
			),
		), $opts ) );
	}

	/* ------------------------------------------------------------------
	 * فیلتر منبع کالا در لیست محصولات ووکامرس (v1.6.0 — مورد ۹)
	 * ---------------------------------------------------------------- */

	/** دراپ‌داون «همه محصولات / فقط محصولات دستیارشاپ / به‌جز محصولات دستیارشاپ» */
	public function products_source_filter() {
		if ( 'product' !== ( get_current_screen() ? get_current_screen()->post_type : '' ) ) {
			return;
		}
		$cur = sanitize_key( (string) ( $_GET['dastyar_src'] ?? '' ) );
		echo '<select name="dastyar_src" id="dastyar-src-filter">';
		printf( '<option value="">همه محصولات (دستیار + محلی)</option>' );
		printf( '<option value="dastyar"%s>فقط محصولات دستیارشاپ ★</option>', selected( $cur, 'dastyar', false ) );
		printf( '<option value="local"%s>به‌جز محصولات دستیارشاپ</option>', selected( $cur, 'local', false ) );
		echo '</select>';
	}

	/** اعمال فیلتر: محصول دارای متای _dastyar_remote_id = محصول دستیار */
	public function products_source_query( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return;
		}
		$post_type = $q->get( 'post_type' );
		if ( 'product' !== $post_type ) {
			return;
		}
		$src = sanitize_key( (string) ( $_GET['dastyar_src'] ?? '' ) );
		if ( ! in_array( $src, array( 'dastyar', 'local' ), true ) ) {
			return;
		}
		$meta_query   = (array) $q->get( 'meta_query' );
		$meta_query[] = array(
			'key'     => '_dastyar_remote_id',
			'compare' => 'dastyar' === $src ? 'EXISTS' : 'NOT EXISTS',
		);
		$q->set( 'meta_query', $meta_query );
	}

	/* ------------------------------------------------------------------
	 * اقدام دسته‌جمعی «سینک موجودی از دستیار» (محصولات — v1.5.0)
	 * ---------------------------------------------------------------- */

	public function product_bulk_actions( $actions ) {
		$actions['dastyarc_stock_sync'] = 'سینک موجودی از دستیار';
		return $actions;
	}

	public function product_bulk_sync( $redirect_to, $action, $post_ids ) {
		if ( 'dastyarc_stock_sync' !== $action || ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect_to;
		}

		// نگاشت شناسه مرکزی ← محصول محلی انتخاب‌شده
		$map = array();
		foreach ( (array) $post_ids as $pid ) {
			$rid = (int) get_post_meta( (int) $pid, '_dastyar_remote_id', true );
			if ( $rid ) {
				$map[ $rid ] = (int) $pid;
			}
		}
		$skipped = max( 0, count( (array) $post_ids ) - count( $map ) );
		$changed = 0;

		foreach ( array_chunk( array_keys( $map ), 100 ) as $chunk ) {
			$result = DastyarC_Client::stock( $chunk );
			if ( is_wp_error( $result ) ) {
				$skipped += count( $chunk );
				DastyarC::log( 'Bulk stock sync failed: ' . $result->get_error_message(), 'error' );
				continue;
			}
			foreach ( (array) ( $result['data'] ?? array() ) as $item ) {
				$rid = (int) ( $item['id'] ?? 0 );
				if ( $rid && isset( $map[ $rid ] ) && DastyarC_Importer::apply_stock_only( $map[ $rid ], $item ) ) {
					$changed++;
				}
			}
		}

		return add_query_arg( array( 'dastyarc_stocked' => $changed, 'dastyarc_stocked_skip' => $skipped ), $redirect_to );
	}
}
