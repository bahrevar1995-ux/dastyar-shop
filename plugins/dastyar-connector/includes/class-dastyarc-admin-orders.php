<?php
/**
 * DastyarC_Admin_Orders — trait بخش «سفارش‌ها» پنل کانکتور (v1.12.0 — شکافتن کلاس ادمین).
 * فقط توسط DastyarC_Admin استفاده می‌شود؛ منطق نسبت به قبل هیچ تغییری نکرده است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait DastyarC_Admin_Orders {

	/* ------------------------------------------------------------------
	 * ستون «دستیار» در لیست سفارش‌ها — دو آیکون ترازشده (بدون ستاره — v1.5.0)
	 * ---------------------------------------------------------------- */

	public function order_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new['dastyarc'] = 'دستیار';
			}
		}
		if ( ! isset( $new['dastyarc'] ) ) {
			$new['dastyarc'] = 'دستیار';
		}
		return $new;
	}

	public function order_column_legacy( $column, $post_id ) {
		if ( 'dastyarc' === $column ) {
			$this->order_column_content( wc_get_order( $post_id ) );
		}
	}

	public function order_column_hpos( $column, $order ) {
		if ( 'dastyarc' === $column ) {
			$this->order_column_content( $order instanceof WC_Order ? $order : wc_get_order( $order ) );
		}
	}

	protected function order_column_content( $order ) {
		if ( ! $order instanceof WC_Order || ! DastyarC_Orders::has_remote_items( $order ) ) {
			echo '—';
			return;
		}

		// بک‌فیل تنبل پرچم «دارای قلم دستیار» برای سفارش‌های قدیمی (برای فیلتر/شمارش)
		if ( ! $order->get_meta( '_dastyarc_has_items' ) ) {
			$order->update_meta_data( '_dastyarc_has_items', 1 );
			$order->save();
		}

		// (v1.9.2) سفارش «تعلیق‌شده»: اتصال با مرکز قطع شده — نمایش قرص تعلیق به‌جای آیکون‌های ارسال/رهگیری
		if ( DastyarC_Orders::is_link_broken( $order ) ) {
			echo '<span title="تعلیق شده توسط مرکز — مدیریت کامل این سفارش با فروشنده است" style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:rgba(224,38,63,.08);border:1.5px solid rgba(224,38,63,.35);color:#c81e38;font-size:11px;font-weight:800">تعلیق</span>';
			return;
		}

		// (v1.9.2) سفارش «ترکیبی»: وضعیت و کد رهگیری هر بخش قابل تفکیک است
		if ( $order->get_meta( '_dastyarc_mixed' ) ) {
			$pc   = (string) ( $order->get_meta( '_dastyarc_part_center' ) ?: 'processing' );
			$pv   = (string) ( $order->get_meta( '_dastyarc_part_vendor' ) ?: 'pending' );
			$dota = array( 'completed' => '#17a16d', 'posted' => '#f2a500', 'processing' => '#5a8dee', 'pending' => '#aab2c0', 'cancelled' => '#e0263f' );
			$ct   = $order->get_meta( '_dastyar_tracking_code' );
			$vt   = $order->get_meta( '_dastyarc_vendor_tracking_code' );
			echo '<span style="display:inline-flex;align-items:center;gap:7px;background:rgba(23,161,109,.07);border:1.5px solid rgba(23,161,109,.3);border-radius:9px;padding:4px 9px">';
			printf(
				'<span title="بخش دستیار: %s%s" style="display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:800;color:#242536"><i style="width:9px;height:9px;border-radius:50%%;background:%s;display:inline-block"></i>دستیار</span>',
				esc_attr( DastyarC_Orders::part_label( $pc ) ),
				$ct ? ' — کد رهگیری: ' . esc_attr( (string) $ct ) : '',
				esc_attr( $dota[ $pc ] ?? '#aab2c0' )
			);
			printf(
				'<span title="بخش فروشنده: %s%s" style="display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:800;color:#242536"><i style="width:9px;height:9px;border-radius:50%%;background:%s;display:inline-block"></i>فروشنده</span>',
				esc_attr( DastyarC_Orders::part_label( $pv ) ),
				$vt ? ' — کد رهگیری: ' . esc_attr( (string) $vt ) : '',
				esc_attr( $dota[ $pv ] ?? '#aab2c0' )
			);
			echo '</span>';
			return;
		}

		// دو آیکون کنار هم و تراز: (وضعیت ارسال ✈) (وضعیت کد رهگیری 📮)
		$sent  = (bool) $order->get_meta( '_dastyarc_sent' );
		$track = (string) $order->get_meta( '_dastyar_tracking_code' );

		echo '<span class="dc-icons" style="display:inline-flex;align-items:center;gap:6px;vertical-align:middle">';

		// ─ آیکون ارسال ─
		if ( ! $sent ) {
			// روشن و قابل کلیک → ارسال سفارش به مرکز
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_send_order&order_id=' . $order->get_id() ), 'dastyarc_send_order_' . $order->get_id() );
			printf(
				'<a class="dc-icn dc-icn-on" href="%s" title="ارسال سفارش به دستیار شاپ (کلیک کنید)" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:9px;background:#e5f5ee;border:1.5px solid #17a16d;text-decoration:none;font-size:15px;box-shadow:0 2px 6px rgba(23,161,109,.18)">✈</a>',
				esc_url( $url )
			);
		} else {
			// خاموش و غیرقابل‌ارسال (ارسال انجام شده)
			echo '<span class="dc-icn dc-icn-off" title="به مرکز ارسال شده — دیگر قابل ارسال نیست" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:9px;background:#f2f4f3;border:1.5px solid #dcdedd;font-size:15px;opacity:.5;filter:grayscale(1)">✈</span>';
		}

		// ─ آیکون کد رهگیری ─
		if ( $track ) {
			// روشن — کد رهگیری ثبت شده
			printf(
				'<span class="dc-icn dc-icn-on" title="کد رهگیری ثبت شد: %s" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:9px;background:#e5f5ee;border:1.5px solid #17a16d;font-size:15px;box-shadow:0 2px 6px rgba(23,161,109,.18)">📮</span>',
				esc_attr( $track )
			);
		} else {
			// خاموش — تا ثبت کد رهگیری
			echo '<span class="dc-icn dc-icn-off" title="کد رهگیری هنوز ثبت نشده است" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:9px;background:#f2f4f3;border:1.5px solid #dcdedd;font-size:15px;opacity:.5;filter:grayscale(1)">📮</span>';
		}

		echo '</span>';
	}

	/* ------------------------------------------------------------------
	 * تب/فیلتر «سفارش‌های دستیار ★» در بالای لیست سفارش‌ها
	 * ---------------------------------------------------------------- */

	/** افزودن لینک تب «دستیار ★ (N)» به نوار نماها — کلاسیک و HPOS */
	public function orders_view( $views ) {
		$count = self::dastyar_orders_count();
		if ( ! $count ) {
			return $views;
		}
		$hpos = class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$url  = $hpos
			? admin_url( 'admin.php?page=wc-orders&dastyarc_orders=1' )
			: admin_url( 'edit.php?post_type=shop_order&dastyarc_orders=1' );

		$current = ! empty( $_GET['dastyarc_orders'] ) ? ' class="current"' : '';
		$views['dastyarc_orders'] = sprintf(
			'<a href="%s"%s><span style="color:#17a16d">★</span> دستیار شاپ <span class="count">(%d)</span></a>',
			esc_url( $url ),
			$current,
			(int) $count
		);
		return $views;
	}

	/** تعداد سفارش‌های دارای قلم دستیار (کش ۲ دقیقه‌ای برای سبک ماندن لیست) */
	protected static function dastyar_orders_count() {
		$cached = get_transient( 'dastyarc_orders_count' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$count = count( (array) wc_get_orders( array(
			'limit'        => -1,
			'return'       => 'ids',
			'meta_key'     => '_dastyarc_has_items',
			'meta_compare' => 'EXISTS',
		) ) );
		set_transient( 'dastyarc_orders_count', $count, 2 * MINUTE_IN_SECONDS );
		return $count;
	}

	/** اعمال فیلتر — لیست کلاسیک (پست‌های shop_order) */
	public function filter_orders_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || empty( $_GET['dastyarc_orders'] ) ) {
			return;
		}
		if ( 'shop_order' !== $query->get( 'post_type' ) ) {
			return;
		}
		$meta_query   = (array) $query->get( 'meta_query' );
		$meta_query[] = array( 'key' => '_dastyarc_has_items', 'compare' => 'EXISTS' );
		$query->set( 'meta_query', $meta_query );
	}

	/** اعمال فیلتر — لیست HPOS */
	public function filter_orders_args( $args ) {
		if ( empty( $_GET['dastyarc_orders'] ) || ! is_array( $args ) ) {
			return $args;
		}
		$args['meta_query']   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
		$args['meta_query'][] = array( 'key' => '_dastyarc_has_items', 'compare' => 'EXISTS' );
		return $args;
	}

	/* ------------------------------------------------------------------
	 * اقدام دسته‌جمعی «ارسال به دستیار شاپ» (سفارش‌ها)
	 * ---------------------------------------------------------------- */

	public function bulk_actions( $actions ) {
		$actions['dastyarc_send'] = 'ارسال به دستیار شاپ';
		return $actions;
	}

	public function bulk_send( $redirect_to, $action, $order_ids ) {
		if ( 'dastyarc_send' !== $action || ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect_to;
		}
		$sent = 0;
		$skip = 0;
		foreach ( (array) $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order && ! $order->get_meta( '_dastyarc_sent' ) && DastyarC_Orders::has_remote_items( $order ) ) {
				$result = DastyarC_Orders::send( $order );
				! is_wp_error( $result ) ? $sent++ : $skip++;
			} else {
				$skip++;
			}
		}
		return add_query_arg( array( 'dastyarc_bulk_sent' => $sent, 'dastyarc_bulk_skip' => $skip ), $redirect_to );
	}

	/* ------------------------------------------------------------------
	 * متاباکس سفارش فروشنده
	 * ---------------------------------------------------------------- */

	public function order_metabox() {
		$screen = 'shop_order';
		try {
			if ( class_exists( '\\Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\CustomOrdersTableController' )
				&& function_exists( 'wc_get_container' ) && function_exists( 'wc_get_page_screen_id' ) ) {
				$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
				if ( is_object( $controller ) && method_exists( $controller, 'custom_orders_table_usage_is_enabled' )
					&& $controller->custom_orders_table_usage_is_enabled() ) {
					$screen = wc_get_page_screen_id( 'shop-order' );
				}
			}
		} catch ( \Throwable $e ) { // در هر شرایط Woo، ثبت روی صفحه کلاسیک هم کفایت می‌کند
			$screen = 'shop_order';
		}
		add_meta_box( 'dastyarc_order', 'دستیار شاپ', array( $this, 'order_metabox_content' ), $screen, 'side', 'high' );
	}

	public function order_metabox_content( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}

		echo '<style>' .
			'#dastyarc_order{border:1.5px solid #cdeee0;border-radius:16px;overflow:hidden;box-shadow:0 5px 18px rgba(23,161,109,.10)}' .
			'#dastyarc_order .postbox-header{background:linear-gradient(135deg,#17a16d,#0f7a52);border-color:#0f7a52}' .
			'#dastyarc_order .postbox-header h2{color:#fff;font-weight:800}' .
			'#dastyarc_order .inside{margin:0;padding:0}' .
			'.dc-box{padding:14px 16px;background:linear-gradient(180deg,#f6fcf9,#eef8f3)}' .
			'.dc-badge{display:inline-block;border-radius:12px;padding:2px 12px;font-size:11.5px;font-weight:700}' .
			'.dc-badge-ok{background:#e5f5ee;border:1px solid #a9dcc4;color:#0f5132}' .
			'.dc-badge-no{background:#fff5d6;border:1px solid #eccb6a;color:#7a5b00}' .
			'.dc-trackcard{background:#fff;border:1px solid #d9ece2;border-radius:12px;padding:10px 14px;margin:10px 0;font-size:13px}' .
			'.dc-trackcard-dim{opacity:.55;background:#f8faf9}' .
			'.dc-tc-title{margin:0 0 6px;font-weight:800;color:#242536;font-size:12.5px}' .
			'.dc-trackcode{font-weight:800;color:#12875c;font-size:15px;letter-spacing:.5px;direction:ltr;display:inline-block}' .
			'.dc-copy-btn{border:1.5px solid #17a16d;background:#fff;color:#12875c;border-radius:8px;padding:2px 10px;font-size:11.5px;font-weight:700;cursor:pointer;margin-right:6px}' .
			'.dc-copy-btn:hover{background:#e5f5ee}' .
			'</style>';

		// حالت تاریک سازمانی (v1.5.0)
		if ( DastyarC_Dashboard::dark() ) {
			echo '<style>' .
				'#dastyarc_order{background:#131f1a;border-color:#243c31}' .
				'#dastyarc_order .dc-box{background:#131f1a;color:#cfe2d8}' .
				'#dastyarc_order .dc-trackcard{background:#1a2a22;border-color:#2c4639;color:#cfe2d8}' .
				'#dastyarc_order .dc-trackcard-dim{background:#16221c;opacity:.55}' .
				'#dastyarc_order .dc-tc-title{color:#e8f5ee}' .
				'#dastyarc_order .dc-trackcode{color:#5fd6a8}' .
				'#dastyarc_order input,#dastyarc_order textarea{background:#131f1a!important;border-color:#2c4639!important;color:#e8f5ee!important}' .
				'#dastyarc_order .dc-copy-btn{background:#1a2a22;color:#5fd6a8}' .
				'</style>';
		}

		echo '<div class="dc-box">';

		$has_items = DastyarC_Orders::has_remote_items( $order );

		$track   = $order->get_meta( '_dastyar_tracking_code' );
		$carrier = $order->get_meta( '_dastyar_tracking_carrier' );

		/* ── جعبه رهگیری سفارش (v1.6.0 — مورد ۷): دو بخش مجزا ──
		 * بالا: «کد رهگیری دستیارشاپ» — فقط برای سفارش‌های دارای اقلام دستیار
		 * پایین: «کد رهگیری سفارش» — برای سفارش‌هایی که دستیار نیستند (ثبت دستی توسط شما)
		 * بخش «درخواست مرجوعی» از این جعبه حذف شد (مدیریت مرجوعی: منوی بازگشت/مرجوعی).
		 */
		echo '<div class="dc-trackcard' . ( $has_items ? '' : ' dc-trackcard-dim' ) . '">';
		echo '<p class="dc-tc-title">🛍 کد رهگیری دستیارشاپ:</p>';
		if ( ! $has_items ) {
			echo '<p class="description" style="margin:0">این سفارش اقلام دستیار ندارد.</p>';
		} elseif ( $track ) {
			printf(
				'<span class="dc-trackcode">%s</span>%s <button type="button" class="dc-copy-btn" data-copy="%s">📋 کپی کد رهگیری</button><span class="dc-copy-note" style="font-size:11px;color:#12875c;margin-right:6px"></span>',
				esc_html( (string) $track ),
				$carrier ? ' <small style="color:#687a72">(' . esc_html( (string) $carrier ) . ')</small>' : '',
				esc_attr( (string) $track )
			);
		} else {
			echo '<p class="description" style="margin:0">کد رهگیری ثبت نشده است.</p>';
		}
		echo '</div>';

		/* ── (v1.9.2) پنل‌های «سفارش ترکیبی» و «تعلیق‌شده» ── */
		$broken   = DastyarC_Orders::is_link_broken( $order );
		$is_mixed = (bool) $order->get_meta( '_dastyarc_mixed' );

		if ( $broken ) {
			echo '<div class="dc-trackcard" style="border-color:#f0b7c0;background:rgba(224,38,63,.05)">';
			echo '<p class="dc-tc-title" style="color:#c81e38">⏸ سفارش «تعلیق‌شده» (طبق اعلام مرکز):</p>';
			echo '<p class="description" style="margin:0">اتصال این سفارش با مرکز قطع شده است؛ مدیریت آن با شماست.</p>';
			echo '</div>';
		}

		if ( $is_mixed && ! $broken ) :
			$pc       = (string) ( $order->get_meta( '_dastyarc_part_center' ) ?: 'processing' );
			$pv       = (string) ( $order->get_meta( '_dastyarc_part_vendor' ) ?: 'pending' );
			$vtrack   = (string) $order->get_meta( '_dastyarc_vendor_tracking_code' );
			$vcarrier = (string) $order->get_meta( '_dastyarc_vendor_tracking_carrier' );
			?>
			<div class="dc-trackcard" style="border-color:#f0d27c;background:#fffdf5">
				<p class="dc-tc-title" style="color:#8a6d00">🧩 سفارش ترکیبی — وضعیت و کد رهگیری هر بخش جداگانه است:</p>
				<p style="margin:0 0 8px">
					<span class="dc-badge dc-badge-ok">بخش دستیار: <?php echo esc_html( DastyarC_Orders::part_label( $pc ) ); ?></span>
					<?php if ( $track ) : ?>
						<span class="dc-trackcode" style="margin-right:8px"><?php echo esc_html( (string) $track ); ?></span>
					<?php endif; ?>
				</p>
				<p style="margin:8px 0">
					<span class="dc-badge <?php echo 'completed' === $pv ? 'dc-badge-ok' : 'dc-badge-no'; ?>">بخش فروشنده: <?php echo esc_html( DastyarC_Orders::part_label( $pv ) ); ?></span>
				</p>
				<?php wp_nonce_field( 'dastyarc_mixed_vendor', 'dastyarc_mixed_vendor_nonce' ); ?>
				<p style="margin:0 0 6px"><input type="text" name="vendor_tracking_code" value="<?php echo esc_attr( $vtrack ); ?>" placeholder="کد رهگیری بخش فروشنده (مثلاً ۲۴ رقمی پست)" style="width:100%;direction:ltr;border:1.5px solid #f0d27c;border-radius:8px;padding:7px 10px" dir="ltr"></p>
				<p style="margin:0 0 8px"><input type="text" name="vendor_tracking_carrier" value="<?php echo esc_attr( $vcarrier ); ?>" placeholder="روش ارسال بخش فروشنده (پست، تیپاکس…)" style="width:100%;border:1.5px solid #f0d27c;border-radius:8px;padding:7px 10px"></p>
				<?php if ( 'completed' !== $pv ) : ?>
					<p style="margin:0"><a class="button button-primary" style="background:#17a16d;border-color:#17a16d;width:100%;text-align:center" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_vendor_done&order_id=' . $order->get_id() ), 'dastyarc_vendor_done_' . $order->get_id() ) ); ?>"><?php echo 'completed' === $pc ? 'تکمیل بخش فروشنده ← کل سفارش کامل می‌شود' : 'تأیید ارسال و تکمیل بخش فروشنده'; ?></a>
					</p>
				<?php else : ?>
					<p class="description" style="margin:0">✔ بخش فروشنده تکمیل شد.</p>
				<?php endif; ?>
			</div>
			<?php
		endif;

		// بخش پایین: کد رهگیری دستی — برای سفارش‌های غیردستیار و سفارش‌های «تعلیق‌شده» (v1.9.2) قابل ویرایش است
		$vendor_track_editable = ( ! $has_items || $broken );
		$self_track   = $order->get_meta( '_dastyarc_self_tracking_code' );
		$self_carrier = $order->get_meta( '_dastyarc_self_tracking_carrier' );
		// (v1.11.0) برای سفارش‌های دستیار، جعبه رهگیری دستی نمایش داده نمی‌شود (رهگیری فقط با کد دستیارشاپ)
		if ( $vendor_track_editable || $self_track ) {
			echo '<div class="dc-trackcard' . ( $vendor_track_editable ? '' : ' dc-trackcard-dim' ) . '" style="margin-top:10px">';
			echo '<p class="dc-tc-title">📮 کد رهگیری سفارش (ارسال توسط شما):</p>';
			if ( ! $vendor_track_editable ) {
				printf( '<p style="margin:6px 0 0"><span class="dc-trackcode">%s</span>%s</p>', esc_html( (string) $self_track ), $self_carrier ? ' <small style="color:#687a72">(' . esc_html( (string) $self_carrier ) . ')</small>' : '' );
			} else {
			/* (v1.7.9 — رفع باگ گزارش‌شده کاربر) نسخه قبلی این بخش یک <form> جدا با فیلد مخفی
			 * name="action" داخل متاباکس می‌چاپ کرد. متاباکس داخل فرم اصلی صفحه سفارش رندر می‌شود
			 * و چون HTML فرمِ تو در تو را دور می‌ریزد، همان فیلد «action» مقدار action=editpost فرم
			 * اصلی را بازنویسی می‌کرد؛ نتیجه: با کلیک روی «به‌روزرسانی» (مثلاً برای تغییر وضعیت سفارش)،
			 * wp-admin/post.php به مسیر پیش‌فرض می‌رفت و صفحه «نوشته‌ها» باز می‌شد و ذخیره انجام نمی‌شد.
			 * راه‌حل: هیچ فرم/فیلد action جدایی نیست؛ فیلدها سوار همان فرم اصلی سفارش می‌شوند و
			 * با هوک woocommerce_update_order (متد save_self_track) ذخیره خواهند شد. */
				wp_nonce_field( 'dastyarc_self_track', 'dastyarc_self_track_nonce' );
				printf( '<p style="margin:0 0 6px"><input type="text" name="self_tracking_code" value="%s" placeholder="کد رهگیری (مثلاً ۲۴ رقمی پست)" style="width:100%%;direction:ltr;border:1.5px solid #cdeee0;border-radius:8px;padding:7px 10px" dir="ltr"></p>', esc_attr( (string) $self_track ) );
				printf( '<p style="margin:0 0 8px"><input type="text" name="self_tracking_carrier" value="%s" placeholder="روش ارسال (مثلاً: پست، تیپاکس، چاپار…)" style="width:100%%;border:1.5px solid #cdeee0;border-radius:8px;padding:7px 10px"></p>', esc_attr( (string) $self_carrier ) );
			}
			echo '</div>';
		}

		$this->copy_admin_js();

		// ── ادامه: جزئیات اتصال فقط برای سفارش‌های دارای اقلام دستیار ──
		if ( ! $has_items ) {
			echo '</div>';
			return;
		}

		$sent    = $order->get_meta( '_dastyarc_sent' );
		$remote  = $order->get_meta( '_dastyarc_remote_order_id' );

		if ( $sent ) {
			printf(
				'<p style="margin:0 0 6px"><span class="dc-badge dc-badge-ok">✔ ارسال شده به دستیار شاپ — مرکز: #%s</span></p>',
				esc_html( (string) ( $order->get_meta( '_dastyarc_remote_order_number' ) ?: $remote ) )
			);
			$this->cancel_box( $order, (int) $remote ); // v1.8.0 — دکمه لغو یک‌مرحله‌ای
		} else {
			echo '<p style="margin:0 0 10px"><span class="dc-badge dc-badge-no">در انتظار ارسال به مرکز</span></p>';
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_send_order&order_id=' . $order->get_id() ), 'dastyarc_send_order_' . $order->get_id() );
			printf(
				'<p style="margin:0 0 12px"><a class="button" href="%s" style="background:#17a16d;border-color:#17a16d;color:#fff;border-radius:10px;font-weight:700">✈ ارسال سفارش به مرکز</a></p>',
				esc_url( $url )
			);
		}

		// (v1.6.0) نمایش کد رهگیری و فیلدهای رهگیری دو‌بخشی به بالای جعبه منتقل شد؛
		// فرم «درخواست مرجوعی از مرکز» در این جعبه حذف شد (ثبت مرجوعی: منوی «عودت و مرجوعی» + برگه عمومی).
		// هندلر admin_post دایمی dastyarc_refund حفظ است تا لینک‌های قدیمی نشکنند.

		echo '</div>';
	}

	/**
	 * v1.8.0 — جعبه «لغو سفارش» در متاباکس دستیار (درخواست کاربر).
	 * دکمه فقط برای سفارش‌های متصلِ نانهایی دیده می‌شود؛ تصمیم نهایی دست مرکز است:
	 * هندلر ابتدا وضعیت مرکز را می‌خواند و اگر «تحویل پست شده/تکمیل‌شده/…» بود، لغو انجام
	 * نمی‌شود و پیام شفاف متناسب با همان وضعیت به فروشنده نشان داده می‌شود.
	 */
	protected function cancel_box( WC_Order $order, $remote ) {
		if ( ! $remote ) {
			return;
		}
		if ( $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
			return; // سفارش همین حالا نهایی/لغو است
		}
		$track = $order->get_meta( '_dastyar_tracking_code' );
		echo '<div class="dc-trackcard dc-cancelcard" style="margin-top:10px;border-color:#f3d2d2;background:#fff9f9">';
		echo '<p class="dc-tc-title" style="color:#a52828">لغو سفارش از دستیار شاپ:</p>';
		if ( $track ) {
			echo '<p class="description" style="margin:0">کد رهگیری برای این سفارش ثبت شده است.</p>';
		}
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=dastyarc_cancel_order&order_id=' . $order->get_id() ),
			'dastyarc_cancel_order_' . $order->get_id()
		);
		printf(
			'<p style="margin:8px 0 0"><a class="button" href="%s" onclick="return confirm(\'سفارش در فروشگاه شما و سایت مرکزی لغو می‌شود و مبلغ آن به‌صورت خودکار به کیف پول شما برمی‌گردد. ادامه می‌دهید؟\')" style="background:#a52828;border-color:#a52828;color:#fff;border-radius:10px;font-weight:700">لغو سفارش در فروشگاه و مرکز</a></p>',
			esc_url( $url )
		);
		echo '</div>';
	}

	/** هندلر لغو یک‌مرحله‌ای: پیش‌بررسی وضعیت مرکز ← لغو محلی ← سینک خودکار (v1.8.0) */
	public function handle_cancel_order() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$order_id = (int) ( $_GET['order_id'] ?? $_POST['order_id'] ?? 0 );
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_cancel_order_' . $order_id ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			wp_die( 'سفارش یافت نشد.' );
		}
		$remote = (int) $order->get_meta( '_dastyarc_remote_order_id' );
		if ( ! $remote ) {
			$this->notice( 'این سفارش به مرکز متصل نیست؛ لغو معمولی ووکامرس کافی است.', 'error' );
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}
		if ( $order->has_status( array( 'cancelled', 'refunded' ) ) ) {
			$this->notice( 'این سفارش قبلاً لغو شده است.', 'error' );
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		// ۱) وضعیت مرکز را بخوان — تصمیم نهایی با مرکز است
		$info = DastyarC_Client::order( $remote );
		if ( is_wp_error( $info ) ) {
			$this->notice( 'اتصال به مرکز برقرار نشد؛ لغو انجام نشد. چند لحظه بعد دوباره تلاش کنید. (' . $info->get_error_message() . ')', 'error' );
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}
		$remote_status = sanitize_key( (string) ( $info['status'] ?? '' ) );

		// ۲) فقط وضعیت‌های قابل‌مدیریت مرکز ← همان قانون کاربر: «در حال انجام» قابل لغو است
		$cancellable = (array) apply_filters( 'dastyarc_cancel_statuses', array( 'pending', 'processing', 'on-hold' ) );
		if ( ! in_array( $remote_status, $cancellable, true ) ) {
			$label = DastyarC_Orders::status_label( $remote_status );
			$this->notice( sprintf( 'لغو انجام نشد؛ این سفارش در مرکز در وضعیت «%s» است و امکان لغو آن وجود ندارد. برای بررسی، از بخش تیکت‌ها با پشتیبانی در تماس باشید.', $label ), 'error' );
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		// ۳) لغو محلی ← هوک push_cancel_wc خودش با مرکز سینک می‌کند (و اگر مرکز رد کند، وضعیت برمی‌گردد)
		$order->update_status( 'cancelled', 'لغو سفارش به درخواست فروشنده از باکس دستیار.' );
		$this->notice( sprintf( 'سفارش #%s لغو و با مرکز همگام شد؛ وجه آن به‌صورت خودکار به کیف پول شما در مرکز برمی‌گردد.', $order->get_order_number() ) );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	/** ذخیره «کد رهگیری سفارش» دستی توسط فروشنده (v1.6.0 — مورد ۷) */
	public function handle_self_track() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$order_id = (int) ( $_POST['order_id'] ?? 0 );
		if ( ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_self_track_' . $order_id ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			wp_die( 'سفارش یافت نشد.' );
		}
		// این بخش فقط برای سفارش‌های «غیر دستیار» فعال است
		if ( DastyarC_Orders::has_remote_items( $order ) ) {
			$this->notice( 'این سفارش اقلام دستیار دارد؛ رهگیری آن فقط با کد رهگیری دستیارشاپ انجام می‌شود.', 'error' );
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}
		$code    = sanitize_text_field( (string) ( $_POST['self_tracking_code'] ?? '' ) );
		$carrier = sanitize_text_field( wp_unslash( (string) ( $_POST['self_tracking_carrier'] ?? '' ) ) );
		if ( '' === $code ) {
			$order->delete_meta_data( '_dastyarc_self_tracking_code' );
			$order->delete_meta_data( '_dastyarc_self_tracking_carrier' );
		} else {
			$order->update_meta_data( '_dastyarc_self_tracking_code', $code );
			$order->update_meta_data( '_dastyarc_self_tracking_carrier', $carrier );
		}
		$order->save();
		$this->notice( '' === $code ? 'کد رهگیری سفارش حذف شد.' : 'کد رهگیری سفارش ذخیره شد و در برگه پیگیری سفارش نمایش داده می‌شود.' );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	/**
	 * (v1.7.10 — رفع بازگشت 1.7.9) ذخیره «کد رهگیری سفارش» دستی به‌همراه به‌روزرسانی خود سفارش.
	 * روی woocommerce_before_order_object_save سوار است: وقتی صفحه ویرایش سفارش سابمیت می‌شود، WC یک‌بار
	 * $order->save() می‌کند و این متد فقط Mتاها را روی همان شی درجریان تنظیم می‌کند تا با همان ذخیره
	 * نوشته شوند — هیچ save() اضافه‌ای صدا زده نمی‌شود ← بازگشت (recursion) و خطای 503 غیرممکن است.
	 * توجه: این هوک برای «هر» ذخیره سفارش اجرا می‌شود (سینک مرکز/کَرون/AJAX)؛ گارد نانس فقط اجازه‌ی
	 * عمل واقعی را به سابمیت صفحه ویرایش سفارش می‌دهد.
	 *
	 * @param WC_Order|int $order شی سفارش (آنچه WP پاس می‌دهد) یا شناسه‌ی سفارش
	 */
	public function save_self_track( $order ) {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		// فقط سابمیت صفحه ویرایش سفارش (که فیلدهای متاباکس ما را دارد)، نه سینک مرکز/کَرون/حرکت‌های AJAX
		if ( ! isset( $_POST['dastyarc_self_track_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( (string) $_POST['dastyarc_self_track_nonce'] ) ), 'dastyarc_self_track' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! $order instanceof WC_Order ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order ) : null;
		}
		if ( ! $order ) {
			return;
		}
		// این بخش فقط برای سفارش‌های «غیر دستیار» فعال است (دفاع دوم پس از UI)
		// استثنا (v1.9.2): سفارش‌های «تعلیق‌شده» اتصال‌شان را با مرکز از دست داده‌اند و رهگیریشان را خود فروشنده ثبت می‌کند
		if ( DastyarC_Orders::has_remote_items( $order ) && ! DastyarC_Orders::is_link_broken( $order ) ) {
			return;
		}
		$code    = sanitize_text_field( wp_unslash( (string) ( $_POST['self_tracking_code'] ?? '' ) ) );
		$carrier = sanitize_text_field( wp_unslash( (string) ( $_POST['self_tracking_carrier'] ?? '' ) ) );
		if ( '' === $code ) {
			$order->delete_meta_data( '_dastyarc_self_tracking_code' );
			$order->delete_meta_data( '_dastyarc_self_tracking_carrier' );
		} else {
			$order->update_meta_data( '_dastyarc_self_tracking_code', $code );
			$order->update_meta_data( '_dastyarc_self_tracking_carrier', $carrier );
		}
		// عمداً save() صدا زده نمی‌شود: ذخیره‌ی درجریانِ خودِ WC، متاها را با هم می‌نویسد (v1.7.10)
	}

	/**
	 * (v1.9.2) ذخیره «کد رهگیری بخش فروشنده» در سفارش ترکیبی — با همان الگوی امن save_self_track
	 * (فیلدها سوار فرم اصلی ویرایش سفارشند؛ nonce جداگانه تا دو بخش تضاد نداشته باشند)
	 */
	public function save_mixed_vendor( $order ) {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( ! isset( $_POST['dastyarc_mixed_vendor_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( (string) $_POST['dastyarc_mixed_vendor_nonce'] ) ), 'dastyarc_mixed_vendor' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! $order instanceof WC_Order ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order ) : null;
		}
		if ( ! $order || ! $order->get_meta( '_dastyarc_mixed' ) || DastyarC_Orders::is_link_broken( $order ) ) {
			return;
		}
		$code    = sanitize_text_field( wp_unslash( (string) ( $_POST['vendor_tracking_code'] ?? '' ) ) );
		$carrier = sanitize_text_field( wp_unslash( (string) ( $_POST['vendor_tracking_carrier'] ?? '' ) ) );
		if ( '' === $code ) {
			$order->delete_meta_data( '_dastyarc_vendor_tracking_code' );
			$order->delete_meta_data( '_dastyarc_vendor_tracking_carrier' );
		} else {
			$order->update_meta_data( '_dastyarc_vendor_tracking_code', $code );
			$order->update_meta_data( '_dastyarc_vendor_tracking_carrier', $carrier );
			if ( 'pending' === (string) $order->get_meta( '_dastyarc_part_vendor' ) ) {
				$order->update_meta_data( '_dastyarc_part_vendor', 'posted' ); // ثبت کد رهگیری = ارسال بخش فروشنده انجام شد
			}
		}
		// بدون save() — معادل v1.7.10: متاها با ذخیره‌ی درجریان خودِ ووکامرس نوشته می‌شوند (بدون حلقه)
	}

	/** (v1.9.2) دکمه «تکمیل بخش فروشنده» در متاباکس سفارش ترکیبی — پایان کار بخش فروشنده + دروازه تکمیل کل سفارش */
	public function handle_vendor_done() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$order_id = (int) ( $_GET['order_id'] ?? 0 );
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_vendor_done_' . $order_id ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_meta( '_dastyarc_mixed' ) && ! DastyarC_Orders::is_link_broken( $order ) ) {
			$order->update_meta_data( '_dastyarc_part_vendor', 'completed' );
			$order->add_order_note( 'بخش فروشنده «سفارش ترکیبی» توسط فروشنده تکمیل شد.' );
			$order->save();
			DastyarC_Orders::maybe_complete_mixed( $order );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	public function handle_refund() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$order_id = (int) ( $_POST['order_id'] ?? 0 );
		if ( ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_refund_' . $order_id ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$result = DastyarC_Orders::request_refund(
			$order_id,
			(float) ( $_POST['amount'] ?? 0 ),
			sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) )
		);
		$this->notice(
			is_wp_error( $result ) ? 'خطا در ثبت درخواست مرجوعی: ' . $result->get_error_message() : 'درخواست مرجوعی به مرکز ارسال شد.',
			is_wp_error( $result ) ? 'error' : 'success'
		);
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}
}
