<?php
/**
 * پیشخوان وردپرس سایت مرکزی:
 * - فیلد «قیمت تامین‌کننده» در محصول ساده و متغیر
 * - سایدبار «اطلاعات ارسال دستیار» در سفارش‌ها (کد رهگیری + کپی + ذخیره خودکار AJAX) — سازگار با HPOS
 * - ستون «دستیار» در لیست سفارش‌ها
 * - منوی «دستیار شاپ»: مرجوعی، لاگ API، فروشندگان + رده‌بندی محرمانه + خروجی اکسل تراز
 * - فیلدهای پروفایل کاربر (کیف پول / کلید API / سایت فروشنده / لوگو / رده)
 * - چاپ «لیبل آدرس» A5: اقدام دسته‌جمعی + اکشن سفارش + لینک در متاباکس + QR رهگیری
 * - حالت تاریک سازمانی (v1.5.0)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Admin {

	public function __construct() {
		// قیمت تامین‌کننده — محصول ساده
		add_action( 'woocommerce_product_options_pricing', array( $this, 'supplier_price_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_supplier_price' ) );
		// قیمت تامین‌کننده — وارییشن‌ها
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'variation_supplier_price_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_supplier_price' ), 10, 2 );

		// سفارش: سایدبار «اطلاعات ارسال دستیار» (بالاترین قسمت، تم برند، گوشه گرد) — سازگار با HPOS
		add_action( 'add_meta_boxes', array( $this, 'tracking_metabox' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_tracking' ) );
		add_action( 'woocommerce_update_order', array( $this, 'save_tracking_from_order' ) );
		// ذخیره خودکار AJAX کد رهگیری (v1.5.0)
		add_action( 'wp_ajax_dastyar_autosave_tracking', array( $this, 'ajax_autosave_tracking' ) );

		// ستون لیست سفارش‌ها (هر دو حالت کلاسیک و HPOS)
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'order_columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'order_column_legacy' ), 10, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'order_columns' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'order_column_hpos' ), 10, 2 );

		// لیبل آدرس — اکشن تکی سفارش
		add_filter( 'woocommerce_order_actions', array( $this, 'order_actions' ) );
		add_action( 'woocommerce_order_action_dastyar_print_label', array( $this, 'print_label_from_action' ) );
		// لیبل آدرس — اقدام دسته‌جمعی (کلاسیک + HPOS)
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'bulk_actions' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'bulk_labels' ), 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'bulk_labels' ), 10, 3 );
		// لیبل آدرس — رندر نهایی صفحه چاپ
		add_action( 'admin_post_dastyar_print_labels', array( $this, 'print_labels' ) );

		// منوی دستیار
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_dastyar_refund_done', array( $this, 'refund_done' ) );
		add_action( 'admin_post_dastyar_flush', array( $this, 'flush' ) );
		add_action( 'admin_post_dastyar_save_sender', array( $this, 'save_sender' ) );
		add_action( 'admin_post_dastyar_save_sms', array( $this, 'save_sms' ) ); // v1.9.8 — ذخیره صفحه مستقل پیامک
		add_action( 'admin_post_dastyar_approve_vendor', array( $this, 'approve_vendor' ) );
		add_action( 'admin_post_dastyar_reject_vendor', array( $this, 'reject_vendor' ) );
		add_action( 'admin_post_dastyar_wallet_adjust', array( $this, 'wallet_adjust' ) ); // v1.6.0
		add_action( 'admin_post_dastyar_suggestion_save', array( $this, 'suggestion_save' ) );
		add_action( 'admin_post_dastyar_rma_save', array( $this, 'rma_save' ) );
		add_action( 'admin_post_dastyar_rma_final', array( $this, 'rma_final' ) );
		// رده‌بندی فروشندگان + خروجی اکسل تراز (v1.5.0)
		add_action( 'admin_post_dastyar_tiers_save', array( $this, 'tiers_save' ) );
		add_action( 'admin_post_dastyar_vendor_csv', array( $this, 'vendor_csv_download' ) );
		// v1.9.7 — ابزارهای تشخیص پیامک: ارسال تست + ارسال مجدد پیامک تأیید عضویت
		add_action( 'admin_post_dastyar_sms_test', array( $this, 'sms_test' ) );
		add_action( 'admin_post_dastyar_vendor_sms_resend', array( $this, 'vendor_sms_resend' ) );

		// پروفایل کاربر (فروشنده)
		add_action( 'show_user_profile', array( $this, 'user_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'user_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_fields' ) );

		// استایل برند منوی «دستیار شاپ» در پیشخوان
		add_action( 'admin_head', array( $this, 'menu_brand_css' ) );
	}

	/* ------------------------------------------------------------------
	 * قیمت تامین‌کننده
	 * ---------------------------------------------------------------- */

	public function supplier_price_field() {
		woocommerce_wp_text_input( array(
			'id'          => '_dastyar_supplier_price',
			'label'       => 'قیمت تامین‌کننده (دستیار)',
			'description' => 'قیمتی که به فروشندگان دراپ‌شیپینگ اعلام می‌شود. اگر خالی باشد، قیمت اصلی محصول استفاده می‌شود.',
			'data_type'   => 'price',
		) );
	}

	public function save_supplier_price( $product ) {
		if ( isset( $_POST['_dastyar_supplier_price'] ) ) {
			$product->update_meta_data( '_dastyar_supplier_price', wc_format_decimal( wp_unslash( $_POST['_dastyar_supplier_price'] ) ) );
		}
	}

	public function variation_supplier_price_field( $loop, $variation_data, $variation ) {
		woocommerce_wp_text_input( array(
			'id'            => "_dastyar_supplier_price[{$loop}]",
			'name'          => "_dastyar_supplier_price[{$loop}]",
			'label'         => 'قیمت تامین‌کننده (دستیار)',
			'value'         => get_post_meta( $variation->ID, '_dastyar_supplier_price', true ),
			'wrapper_class' => 'form-row form-row-full',
			'data_type'     => 'price',
		) );
	}

	public function save_variation_supplier_price( $variation_id, $i ) {
		if ( isset( $_POST['_dastyar_supplier_price'][ $i ] ) ) {
			update_post_meta( $variation_id, '_dastyar_supplier_price', wc_format_decimal( wp_unslash( $_POST['_dastyar_supplier_price'][ $i ] ) ) );
		}
	}

	/* ------------------------------------------------------------------
	 * کد رهگیری سفارش
	 * ---------------------------------------------------------------- */

	/**
	 * ثبت سایدبار «اطلاعات ارسال دستیار» — بالاترین بخش (side/high)، سازگار با HPOS و کلاسیک
	 */
	public function tracking_metabox() {
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
		} catch ( \Throwable $e ) { // روی صفحه کلاسیک ثبت می‌ماند
			$screen = 'shop_order';
		}
		add_meta_box( 'dastyar_tracking', '📦 اطلاعات ارسال دستیار', array( $this, 'tracking_metabox_content' ), $screen, 'side', 'high' );
	}

	public function tracking_metabox_content( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( is_object( $post_or_order ) ? $post_or_order->ID : $post_or_order );
		if ( ! $order ) {
			return;
		}
		$this->tracking_fields( $order );
		echo '<style>' .
			'#dastyar_tracking{border:1.5px solid #cdeee0;border-radius:16px;overflow:hidden;box-shadow:0 5px 18px rgba(23,161,109,.10)}' .
			'#dastyar_tracking .postbox-header{background:linear-gradient(135deg,#17a16d,#0f7a52);border-color:#0f7a52}' .
			'#dastyar_tracking .postbox-header h2{color:#fff;font-weight:800}' .
			'#dastyar_tracking .inside{margin:0;padding:0}' .
			'</style>';

		// حالت تاریک سازمانی (v1.5.0)
		if ( 'yes' === get_option( 'dastyar_dark_mode', 'no' ) ) {
			echo '<style>' .
				'#dastyar_tracking{background:#131f1a;border-color:#243c31}' .
				'#dastyar_tracking .dastyar-tracking{background:#131f1a!important;color:#cfe2d8}' .
				'#dastyar_tracking input[type=text]{background:#1a2a22!important;border-color:#2c4639!important;color:#e8f5ee!important}' .
				'#dastyar_tracking label{color:#93aca0!important}' .
				'#dastyar_tracking .dastyar-breakdown{background:#1a2a22!important;border-color:#2c4639!important}' .
				'#dastyar_tracking .dastyar-breakdown th{background:#1f332a!important;border-color:#2c4639!important;color:#5fd6a8!important}' .
				'#dastyar_tracking .dastyar-breakdown td{color:#cfe2d8!important;border-color:#2c4639!important}' .
				'#dastyar_tracking .description{color:#93aca0}' .
				'</style>';
		}
	}

	public function tracking_fields( $order ) {
		if ( ! $order instanceof WC_Order || ( ! $order->get_meta( '_dastyar_remote_ref' ) && ! $order->get_meta( '_dastyar_manual_order' ) ) ) {
			return;
		}
		$manual = ! $order->get_meta( '_dastyar_remote_ref' ) && $order->get_meta( '_dastyar_manual_order' );
		echo '<div class="dastyar-tracking" style="padding:14px 16px;background:linear-gradient(180deg,#f6fcf9,#eef8f3)">';

		// نشان سفارش دستیار (گوشه‌های گرد + برند)
		if ( $manual ) {
			echo '<p style="margin:0 0 10px"><span style="display:inline-block;background:#fff5d6;border:1px solid #eccb6a;color:#7a5b00;border-radius:12px;padding:2px 12px;font-size:11.5px;font-weight:700">ثبت دستی توسط فروشنده</span></p>';
			echo '<p class="description" style="margin:0 0 10px">کد رهگیری در پنل فروشنده (My Account) نمایش داده می‌شود و وب‌هوکی ارسال نمی‌شود.</p>';
		} else {
			printf(
				'<p style="margin:0 0 10px"><span style="display:inline-block;background:#e5f5ee;border:1px solid #a9dcc4;color:#0f5132;border-radius:12px;padding:2px 12px;font-size:11.5px;font-weight:700">سفارش فروشگاه فروشنده #%s</span></p>',
				esc_html( (string) $order->get_meta( '_dastyar_remote_order_number' ) )
			);
			echo '<p class="description" style="margin:0 0 10px">پس از ذخیره، کد رهگیری خودکار به سایت فروشنده ارسال می‌شود. <strong>ذخیره خودکار فعال است</strong> — با خروج از فیلد، مقدار بلافاصله ذخیره می‌شود.</p>';
		}

		echo '<p class="form-field" style="margin:0 0 6px"><label style="display:block;font-weight:700;font-size:12.5px;color:#284436;margin-bottom:5px">کد رهگیری</label>';
		printf(
			'<input type="text" name="dastyar_tracking_code" value="%s" style="width:100%%;direction:ltr;border:1.5px solid #cdeee0;border-radius:10px;padding:8px 12px">',
			esc_attr( (string) $order->get_meta( '_dastyar_tracking_code' ) )
		);
		echo '</p>';

		// کپی با یک کلیک (v1.5.0)
		echo '<p style="margin:0 0 10px;display:flex;gap:6px;align-items:center">';
		echo '<button type="button" class="button dastyar-copy-btn" data-dastyar-copy="dastyar_tracking_code" style="border:1.5px solid #17a16d;color:#12875c;border-radius:8px;font-weight:700">📋 کپی کد رهگیری</button>';
		echo '<span class="dastyar-autosave-note" style="font-size:11.5px;color:#12875c"></span>';
		echo '</p>';

		echo '<p class="form-field" style="margin:0 0 12px"><label style="display:block;font-weight:700;font-size:12.5px;color:#284436;margin-bottom:5px">شرکت حمل</label>';
		printf(
			'<input type="text" name="dastyar_tracking_carrier" value="%s" style="width:100%%;border:1.5px solid #cdeee0;border-radius:10px;padding:8px 12px">',
			esc_attr( (string) $order->get_meta( '_dastyar_tracking_carrier' ) )
		);
		echo '</p>';

		// تفکیک مبالغ (کالا / حمل / جمع) — کارت گوشه‌گرد
		$goods    = (float) $order->get_meta( '_dastyar_goods_total' );
		$shipping = (float) $order->get_shipping_total();
		echo '<table class="dastyar-breakdown" style="width:100%;border-collapse:separate;border-spacing:0;margin:2px 0 12px;font-size:12.5px;background:#fff;border:1px solid #d9ece2;border-radius:12px;overflow:hidden">';
		echo '<tr style="background:#f0f8f4"><th style="text-align:right;padding:8px 10px;border-bottom:1px solid #d9ece2;color:#0f5132">جمع کالاها (تامین)</th><th style="text-align:right;padding:8px 10px;border-bottom:1px solid #d9ece2;color:#0f5132;border-right:1px solid #d9ece2">حمل‌ونقل</th><th style="text-align:right;padding:8px 10px;border-bottom:1px solid #d9ece2;color:#0f5132;border-right:1px solid #d9ece2">جمع صورتحساب</th></tr>';
		printf(
			'<tr><td style="padding:8px 10px">%s</td><td style="padding:8px 10px;border-right:1px solid #d9ece2">%s</td><td style="padding:8px 10px;border-right:1px solid #d9ece2;font-weight:800;color:#12875c">%s</td></tr>',
			wp_kses_post( wc_price( $goods ) ),
			wp_kses_post( wc_price( $shipping ) ),
			wp_kses_post( wc_price( (float) $order->get_total() ) )
		);
		echo '</table>';

		$label_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=dastyar_print_labels&orders=' . $order->get_id() ),
			'dastyar_print_labels'
		);
		printf(
			'<p style="margin:0"><a class="button" href="%s" target="_blank" style="background:#17a16d;border-color:#17a16d;color:#fff;border-radius:10px;font-weight:700">🏷 چاپ لیبل آدرس (A5)</a></p>',
			esc_url( $label_url )
		);
		echo '</div>';

		$this->tracking_admin_js( $order );
	}

	/** جاوااسکریپت کپی + ذخیره خودکار کد رهگیری (فقط یک‌بار در صفحه چاپ می‌شود) */
	protected function tracking_admin_js( WC_Order $order ) {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		$cfg = array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'dastyar_autosave_tracking_' . $order->get_id() ),
			'order' => (int) $order->get_id(),
		);
		printf( '<script>var dastyarTrackingCfg = %s;</script>', wp_json_encode( $cfg ) );
		?>
		<script>
		(function(){
			var cfg = window.dastyarTrackingCfg || {};

			// ─── کپی با یک کلیک ───
			document.querySelectorAll('.dastyar-copy-btn').forEach(function(btn){
				btn.addEventListener('click', function(){
					var input = document.querySelector('#dastyar_tracking input[name="' + btn.getAttribute('data-dastyar-copy') + '"]');
					if (!input || !input.value) { btn.textContent = '— خالی است'; setTimeout(function(){ btn.textContent = '📋 کپی کد رهگیری'; }, 1200); return; }
					var done = function(){ btn.textContent = '✓ کپی شد'; setTimeout(function(){ btn.textContent = '📋 کپی کد رهگیری'; }, 1500); };
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(input.value).then(done, function(){ legacyCopy(input); done(); });
					} else { legacyCopy(input); done(); }
				});
			});
			function legacyCopy(input){ input.focus(); input.select(); try { document.execCommand('copy'); } catch(e){} input.blur(); }

			// ─── ذخیره خودکار با خروج از فیلد ───
			var box     = document.getElementById('dastyar_tracking');
			if (!box) { return; }
			var code    = box.querySelector('input[name="dastyar_tracking_code"]'),
				carrier = box.querySelector('input[name="dastyar_tracking_carrier"]'),
				note    = box.querySelector('.dastyar-autosave-note');
			if (!code || !carrier) { return; }
			var lastCode = code.value, lastCarrier = carrier.value, busy = false;
			function autosave(){
				if (busy) { return; }
				if (code.value === lastCode && carrier.value === lastCarrier) { return; }
				lastCode = code.value; lastCarrier = carrier.value;
				busy = true;
				if (note) { note.textContent = '⏳ در حال ذخیره…'; }
				var body = new URLSearchParams({
					action: 'dastyar_autosave_tracking',
					_ajax_nonce: cfg.nonce,
					order_id: cfg.order,
					dastyar_tracking_code: code.value,
					dastyar_tracking_carrier: carrier.value
				});
				fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
					.then(function(r){ return r.json(); })
					.then(function(){ if (note) { note.textContent = '✓ ذخیره شد'; setTimeout(function(){ note.textContent=''; }, 2500); } busy = false; })
					.catch(function(){ if (note) { note.textContent = '⚠ ذخیره نشد — با دکمه بروزرسانی سفارش ذخیره کنید'; } busy = false; });
			}
			code.addEventListener('blur', autosave);
			carrier.addEventListener('blur', autosave);
		})();
		</script>
		<?php
	}

	public function save_tracking( $post_id ) {
		$order = wc_get_order( $post_id );
		if ( $order ) {
			$this->persist_tracking( $order );
		}
	}

	public function save_tracking_from_order( $order ) {
		if ( $order instanceof WC_Order ) {
			$this->persist_tracking( $order );
		}
	}

	protected function persist_tracking( WC_Order $order ) {
		if ( ! isset( $_POST['dastyar_tracking_code'] ) && ! isset( $_POST['dastyar_tracking_carrier'] ) ) {
			return;
		}
		$code    = sanitize_text_field( wp_unslash( $_POST['dastyar_tracking_code'] ?? '' ) );
		$carrier = sanitize_text_field( wp_unslash( $_POST['dastyar_tracking_carrier'] ?? '' ) );
		$this->store_tracking( $order, $code, $carrier );
	}

	/**
	 * هسته ذخیره کد رهگیری + اطلاع‌رسانی به فروشنده (اشتراک ذخیره فرم و AJAX).
	 * @return bool آیا کد تغییر کرد و به فروشنده ارسال شد؟
	 */
	protected function store_tracking( WC_Order $order, $code, $carrier ) {
		$old = (string) $order->get_meta( '_dastyar_tracking_code' );

		$order->update_meta_data( '_dastyar_tracking_code', $code );
		$order->update_meta_data( '_dastyar_tracking_carrier', $carrier );
		$order->save();

		// فقط هنگام تغییر کد به فروشنده اطلاع بده
		if ( '' !== $code && $code !== $old ) {
			if ( $order->get_meta( '_dastyar_remote_ref' ) ) {
				$order->add_order_note( 'کد رهگیری ثبت و به فروشگاه فروشنده ارسال شد: ' . $code );
				Dastyar::instance()->webhooks->push_tracking( $order );
			} else {
				$order->add_order_note( 'کد رهگیری ثبت شد: ' . $code . ' (سفارش ثبت دستی — کد در پنل فروشنده نمایش داده می‌شود)' );
			}
			do_action( 'dastyar_tracking_updated', $order->get_id(), $code, $carrier );
			return true;
		}
		return false;
	}

	/** ذخیره خودکار AJAX کد رهگیری از سایدبار سفارش (v1.5.0) */
	public function ajax_autosave_tracking() {
		$order_id = (int) ( $_POST['order_id'] ?? 0 );
		check_ajax_referer( 'dastyar_autosave_tracking_' . $order_id, '_ajax_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز' ), 403 );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ( ! $order->get_meta( '_dastyar_remote_ref' ) && ! $order->get_meta( '_dastyar_manual_order' ) ) ) {
			wp_send_json_error( array( 'message' => 'سفارش دستیار یافت نشد.' ), 404 );
		}
		$code    = sanitize_text_field( wp_unslash( $_POST['dastyar_tracking_code'] ?? '' ) );
		$carrier = sanitize_text_field( wp_unslash( $_POST['dastyar_tracking_carrier'] ?? '' ) );
		$pushed  = $this->store_tracking( $order, $code, $carrier );
		wp_send_json_success( array( 'saved' => true, 'pushed' => $pushed ) );
	}

	/* ------------------------------------------------------------------
	 * ستون سفارش‌ها
	 * ---------------------------------------------------------------- */

	public function order_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_number' === $key ) {
				$new['dastyar'] = 'دستیار';
			}
		}
		if ( ! isset( $new['dastyar'] ) ) {
			$new['dastyar'] = 'دستیار';
		}
		return $new;
	}

	public function order_column_legacy( $column, $post_id ) {
		if ( 'dastyar' === $column ) {
			$this->order_column_content( wc_get_order( $post_id ) );
		}
	}

	public function order_column_hpos( $column, $order ) {
		if ( 'dastyar' === $column ) {
			$this->order_column_content( $order instanceof WC_Order ? $order : wc_get_order( $order ) );
		}
	}

	protected function order_column_content( $order ) {
		if ( ! $order || ( ! $order->get_meta( '_dastyar_remote_ref' ) && ! $order->get_meta( '_dastyar_manual_order' ) ) ) {
			echo '—';
			return;
		}
		// سفارش ثبت دستی از پنل فروشنده
		if ( ! $order->get_meta( '_dastyar_remote_ref' ) ) {
			$vendor = get_userdata( (int) $order->get_meta( '_dastyar_vendor_id' ) );
			printf(
				'<span class="badge" style="background:#17a16d;color:#fff;border-radius:3px;padding:1px 6px">دستیار (دستی)</span><br><small>%s</small>',
				$vendor ? esc_html( $vendor->display_name ) : '—'
			);
			return;
		}
		$vendor = get_userdata( (int) $order->get_meta( '_dastyar_vendor_id' ) );
		printf(
			'<span class="badge" style="background:#2271b1;color:#fff;border-radius:3px;padding:1px 6px">دستیار</span><br><small>%s%s</small>',
			$vendor ? esc_html( $vendor->display_name ) . '<br>' : '',
			esc_html( 'فروشگاه: #' . $order->get_meta( '_dastyar_remote_order_number' ) )
		);
	}

	/* ------------------------------------------------------------------
	 * لیبل آدرس A5
	 * ---------------------------------------------------------------- */

	public function order_actions( $actions ) {
		$actions['dastyar_print_label'] = 'چاپ لیبل آدرس (A5)';
		return $actions;
	}

	public function print_label_from_action( $order ) {
		// در فلو ذخیره ادمین اجرا می‌شود (Nonce وردپرس قبلاً چک شده) — مستقیم صفحه چاپ رندر می‌شود
		$this->render_labels( array( $order->get_id() ) );
		exit;
	}

	public function bulk_actions( $actions ) {
		$actions['dastyar_print_labels'] = 'لیبل آدرس (A5)';
		return $actions;
	}

	public function bulk_labels( $redirect_to, $action, $order_ids ) {
		if ( 'dastyar_print_labels' !== $action || ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect_to;
		}
		$ids = array_slice( array_map( 'intval', (array) $order_ids ), 0, 50 );
		if ( ! $ids ) {
			return $redirect_to;
		}
		// v1.10.2 — رفع قطعی خطای «دسترسی غیرمجاز» در چاپ دسته‌جمعی (تکرار گزارش کاربر):
		// ریدایرکت به admin-post.php (حتی با آدرس خام v1.10.1) به کل میان‌مرحله حذف شد؛
		// اقدامات دسته‌جمعی لیست سفارش‌ها پیش از شروع خروجی صفحه پردازش می‌شوند — نانس خودِ فرم
		// (‏bulk-orders) را وردپرس/ووکامرس از قبل بررسی کرده و هدرها هنوز ارسال نشده‌اند.
		// پس لیبل‌ها در همین درخواست مستقیم رندر می‌شوند ← هیچ ریدایرکتی نیست که خراب شود.
		$this->render_labels( $ids );
		exit;
	}

	public function print_labels() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyar_print_labels' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$ids = array_filter( array_slice( array_map( 'intval', explode( ',', (string) ( $_GET['orders'] ?? '' ) ) ), 0, 50 ) );
		if ( ! $ids ) {
			wp_die( 'سفارشی انتخاب نشده است.' );
		}
		$this->render_labels( $ids );
		exit;
	}

	/** رندر HTML چاپ لیبل‌ها (هر سفارش = یک صفحه A5 — مطابق طرح تصویری تأییدشده) */
	protected function render_labels( array $order_ids ) {
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>لیبل آدرس سفارش</title>'; // v1.10.3 — عنوان بی‌نام؛ هیچ اشاره‌ای به مرکز/دستیارشاپ در لیبل نیست
		// فونت سایت (وزیرمتن) از CDN — در نبود اینترنت به Tahoma برمی‌گردد (v1.5.1)
		echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">';
		echo '<style>
			* { box-sizing: border-box; }
			body { margin:0; font-family:"Vazirmatn","IRANSans","IRANSansX",Tahoma,Arial,sans-serif; background:#fff; color:#000; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
			@page { size: A5; margin: 0; }
			/* v1.10.3 — ساده و بدون کادرهای مشکی/حاشیه دور برگه (بازخورد کاربر، موارد ۲ و ۴) */
			.dastyar-label { width:148mm; min-height:208mm; padding:7mm 8mm; page-break-after: always; display:flex; }
			.dastyar-label:last-child { page-break-after: auto; }
			.l-frame { flex:1; display:flex; flex-direction:column; }
			table { border-collapse: collapse; width:100%; }
			td, th { border:0.9pt solid #555; padding:2.2mm 2.6mm; font-size:9.6pt; vertical-align:middle; }
			.no-border td { border:0; }
			/* هدر: لوگوی فروشنده + نام برند و تاریخ چاپ */
			.l-top { display:flex; justify-content:space-between; align-items:center; border-bottom:1.2pt solid #000; padding-bottom:3mm; margin-bottom:5mm; }
			.l-brand { display:flex; align-items:center; gap:3mm; }
			.l-brand img { max-height:15mm; max-width:48mm; object-fit:contain; }
			.l-brand .nm { font-size:12.5pt; font-weight:800; }
			.l-brand .url { font-size:8pt; color:#444; direction:ltr; }
			.l-print-date { font-size:9pt; font-weight:700; white-space:nowrap; }
			/* برچسب فرستنده/گیرنده — حالت عادی (بدون نوار مشکی، v1.10.3 مورد ۲) */
			.l-side { text-align:center; font-size:9.6pt; font-weight:800; width:14mm; white-space:nowrap; color:#000; }
			.l-info .nm { font-weight:800; }
			.l-info .line { margin:1.4mm 0; }
			.l-info .lbl { color:#666; font-size:8.2pt; font-weight:700; display:inline-block; min-width:17mm; }
			.l-phone { font-size:11pt; font-weight:800; letter-spacing:.5pt; }
			.l-small { font-size:8.6pt; }
			.l-num { direction:ltr; display:inline-block; }
			.l-order-cell { text-align:center; font-size:9pt; line-height:1.9; }
			/* QR + کد رهگیری متنی */
			.l-qr { margin-top:1.5mm; text-align:center; }
			.l-qr svg { width:22mm; height:22mm; }
			.l-qr .cap { font-size:7.4pt; color:#333; margin-top:.8mm; }
			.l-tracknum { direction:ltr; display:inline-block; font-weight:800; font-size:10.5pt; letter-spacing:.7pt; margin-top:1mm; }
			/* جدول اقلام — سربرگ ساده (بدون مشکی توپر، v1.10.3 مورد ۲) و فاصله بیشتر بخش‌ها برای قیچی‌کردن آسان (مورد ۳) */
			.l-items th { background:none; color:#000; border-color:#555; font-size:9.2pt; font-weight:800; }
			.l-items tbody tr:nth-child(even) td { background:#f5f5f5; }
			/* جمع نهایی (v1.5.2: بارکد و امضاها حذف شدند) */
			.l-totals { width:64mm; margin-right:auto; margin-top:6mm; }
			.l-totals td { font-size:9.4pt; }
			.l-totals .tt { background:#f5f5f5; font-weight:800; }
			.l-totals .grand td { border-top:1.2pt solid #000; font-weight:800; }
			/* پابرگ بایگانی (v1.10.3 مورد ۵): خط‌چین برای قیچی‌کردن آسان */
			.l-archive { margin-top:auto; padding-top:3.5mm; border-top:1pt dashed #777; font-size:8.6pt; color:#222; line-height:2; }
			.print-toolbar { position:fixed; top:0; left:0; right:0; background:#222; color:#fff; padding:10px; text-align:center; z-index:9; }
			.print-toolbar button { background:#17a16d; color:#fff; border:0; padding:8px 24px; border-radius:4px; cursor:pointer; font-size:14px; }
			body.has-toolbar .dastyar-label:first-child { margin-top:60px; }
			@media print { .print-toolbar { display:none; } body.has-toolbar .dastyar-label:first-child { margin-top:0; } }
		</style></head><body class="has-toolbar">';
		echo '<div class="print-toolbar"><button onclick="window.print()">🖨 چاپ لیبل‌ها (کاغذ A5)</button></div>';

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			// گیرنده — اولویت با آدرس حمل، در نبود آن صورتحساب
			$to = $order->get_address( 'shipping' );
			if ( '' === trim( (string) $to['address_1'] ) ) {
				$to = $order->get_address( 'billing' );
			}
			$country = WC()->countries->countries[ $to['country'] ] ?? $to['country'];
			$state   = WC()->countries->get_states( $to['country'] );
			$state   = is_array( $state ) && isset( $state[ $to['state'] ] ) ? $state[ $to['state'] ] : $to['state'];

			// لوگو و برند فروشنده + اطلاعات فرستنده (مرکز — تنظیمات)
			$vendor_id   = (int) $order->get_meta( '_dastyar_vendor_id' );
			$logo_id     = $vendor_id ? (int) get_user_meta( $vendor_id, '_dastyar_logo_id', true ) : 0;
			$logo_url    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
			$vendor      = $vendor_id ? get_userdata( $vendor_id ) : null;
			$vendor_shop = $vendor_id ? get_user_meta( $vendor_id, '_dastyar_shop_name', true ) : '';
			// v1.10.3 (مورد ۱): هویت چاپی لیبل = فروشگاه فروشنده؛ نام/نشانی مرکز (مثل «دستیارشاپ») هرگز چاپ نمی‌شود
			$brand_name  = trim( (string) ( $vendor_shop ?: ( $vendor ? $vendor->display_name : '' ) ) );
			// نشانی سایت روی لیبل = سایت فروشنده (متا سفارش یا پروفایل فروشنده) — در نبودش چیزی چاپ نمی‌شود
			$vendor_site = trim( (string) $order->get_meta( '_dastyar_remote_site' ) );
			if ( '' === $vendor_site && $vendor_id && class_exists( 'Dastyar' ) ) {
				$vendor_site = trim( (string) Dastyar::instance()->vendors->site_url( $vendor_id ) );
			}

			$sender_name = trim( (string) get_option( 'dastyar_sender_name', '' ) );
			if ( '' === $sender_name || get_bloginfo( 'name' ) === $sender_name ) {
				$sender_name = $brand_name; // فالبک امن: نام فروشگاه فروشنده — هرگز نام پیش‌فرض مرکز
			}
			$sender = array(
				'name'    => $sender_name,
				'address' => get_option( 'dastyar_sender_address', $this->default_sender_address() ),
				'phone'   => get_option( 'dastyar_sender_phone', '' ),
				'postcode'=> get_option( 'dastyar_sender_postcode', '' ),
			);

			// QR رهگیری مرسوله (v1.5.0): برای بارکد پستی ← لینک مستقیم صفحه رهگیری پست
			$qr_svg = '';
			$track_code = (string) $order->get_meta( '_dastyar_tracking_code' );
			if ( '' !== $track_code ) {
				$digits  = preg_replace( '/\\D/', '', $track_code );
				$qr_text = strlen( $digits ) >= 10
					? 'https://tracking.post.ir/?id=' . rawurlencode( $track_code )
					: $track_code;
				$qr_svg  = Dastyar_QrCode::svg( $qr_text, 96 );
			}

			echo '<div class="dastyar-label"><div class="l-frame">';

			// ─── هدر: لوگوی فروشنده + برند | تاریخ چاپ ───
			echo '<table class="no-border l-top"><tr>';
			echo '<td style="border:0">';
			echo '<div class="l-brand">';
			if ( $logo_url ) {
				printf( '<img src="%s" alt="logo">', esc_url( $logo_url ) );
			}
			// v1.10.3 (مورد ۱): کنار نام فروشگاه، نشانی «سایت فروشنده» چاپ می‌شود — نه نشانی مرکز؛ در نبودش چاپ نمی‌شود
			$brand_html = '<div>';
			if ( '' !== $brand_name ) {
				$brand_html .= '<div class="nm">' . esc_html( $brand_name ) . '</div>';
			}
			if ( '' !== $vendor_site ) {
				$brand_html .= '<div class="url">' . esc_html( $vendor_site ) . '</div>';
			}
			$brand_html .= '</div>';
			echo $brand_html;
			echo '</div></td>';
			printf( '<td style="border:0;text-align:left"><span class="l-print-date">تاریخ چاپ: %s</span></td>', esc_html( Dastyar_Jalali::format( 'Y/m/d H:i' ) ) );
			echo '</tr></table>';

			// ─── فرستنده ───
			echo '<table><tr>';
			echo '<td class="l-side" rowspan="1">فرستنده</td>';
			echo '<td class="l-info">';
			printf( '<div class="line nm"><span class="lbl">نام:</span> %s</div>', esc_html( $sender['name'] ) );
			printf( '<div class="line"><span class="lbl">آدرس:</span> %s%s</div>', esc_html( $sender['address'] ), $sender['postcode'] ? ' &nbsp;&nbsp; <strong>کد پستی:</strong> <span class="l-num">' . esc_html( $sender['postcode'] ) . '</span>' : '' );
			echo '</td>';
			echo '<td class="l-small" style="width:34mm;text-align:center">';
			if ( '' !== $vendor_site ) {
				printf( '<div class="url" style="direction:ltr">%s</div>', esc_html( $vendor_site ) ); // v1.10.3 — سایت فروشنده، نه مرکز
			}
			if ( $sender['phone'] ) {
				printf( '<div style="margin-top:2mm;font-size:8.2pt;color:#666;font-weight:700">تلفن:</div><div class="l-num" style="font-weight:800">%s</div>', esc_html( $sender['phone'] ) );
			}
			echo '</td></tr></table>';

			// ─── گیرنده ───
			// تلفن گیرنده: از آدرس حمل، و اگر خالی بود از صورتحساب (شماره همراه مشتری همیشه در صورتحساب است) — v1.5.1
			$to_phone = trim( (string) ( $to['phone'] ?? '' ) );
			if ( '' === $to_phone ) {
				$to_phone = (string) $order->get_billing_phone();
			}

			echo '<table style="margin-top:4mm"><tr>'; // v1.10.3 (مورد ۳): فاصله بخش‌ها — قیچی‌کردن آسان‌تر
			echo '<td class="l-side" rowspan="1">گیرنده</td>';
			echo '<td class="l-info">';
			printf( '<div class="line nm"><span class="lbl">نام:</span> %s %s</div>', esc_html( $to['first_name'] ), esc_html( $to['last_name'] ) );
			printf(
				'<div class="line"><span class="lbl">آدرس:</span> %s، %s، %s، %s %s</div>',
				esc_html( $country ),
				esc_html( $state ),
				esc_html( $to['city'] ),
				esc_html( $to['address_1'] ),
				esc_html( $to['address_2'] )
			);
			if ( $to['postcode'] ) {
				printf( '<div class="line"><span class="lbl">کد پستی:</span> <strong><span class="l-num">%s</span></strong></div>', esc_html( $to['postcode'] ) );
			}
			if ( $to_phone ) {
				printf( '<div class="line"><span class="lbl">تلفن گیرنده:</span> <span class="l-num l-phone">%s</span></div>', esc_html( $to_phone ) );
			}
			echo '</td>';
			echo '<td class="l-order-cell" style="width:34mm">';
			printf( '<div><strong>#%s</strong></div>', esc_html( $order->get_order_number() ) );
			printf( '<div>%s</div>', esc_html( wc_get_order_status_name( $order->get_status() ) ) );
			printf( '<div>%s</div>', esc_html( Dastyar_Jalali::dt( $order->get_date_created(), 'Y/m/d H:i' ) ) );
			if ( $qr_svg || '' !== $track_code ) {
				echo '<div class="l-qr">' . $qr_svg;
				if ( '' !== $track_code ) {
					// کد رهگیری به‌صورت متنی خوانا هم چاپ شود (بدون نیاز به اسکن)
					printf( '<div class="l-tracknum">%s</div>', esc_html( $track_code ) );
				}
				echo '<div class="cap">اسکن برای رهگیری مرسوله</div></div>';
			}
			echo '</td></tr></table>';

			// ─── اقلام سفارش (v1.5.2: ستون بارکد حذف شد) ───
			echo '<table class="l-items" style="margin-top:4mm"><thead><tr>'; // v1.10.3 (مورد ۳)
			echo '<th style="width:9mm">ردیف</th><th>نام کالا</th><th style="width:22mm">قیمت</th><th style="width:13mm">تعداد</th><th style="width:24mm">مجموع</th>';
			echo '</tr></thead><tbody>';
			$i   = 0;
			$qty = 0;
			foreach ( $order->get_items() as $item ) {
				$i++;
				$qty += (int) $item->get_quantity();
				echo '<tr>';
				printf( '<td style="text-align:center">%d</td>', $i );
				printf( '<td>%s</td>', esc_html( $item->get_name() ) );
				printf( '<td>%s</td>', wp_kses_post( wc_price( (float) ( $item->get_quantity() > 0 ? $item->get_total() / $item->get_quantity() : 0 ) ) ) );
				printf( '<td style="text-align:center">%d</td>', (int) $item->get_quantity() );
				printf( '<td>%s</td>', wp_kses_post( wc_price( (float) $item->get_total() ) ) );
				echo '</tr>';
			}
			printf(
				'<tr><td colspan="3" style="text-align:left;font-weight:700;border-left:0">مجموع</td><td style="text-align:center;font-weight:700">%d</td><td style="font-weight:700">%s</td></tr>',
				$qty,
				wp_kses_post( wc_price( (float) $order->get_subtotal() ) )
			);
			echo '</tbody></table>';

			// ─── جمع نهایی ───
			$ship_methods = array();
			foreach ( $order->get_shipping_methods() as $sm ) {
				$ship_methods[] = $sm->get_method_title();
			}
			echo '<table class="l-totals">'; // v1.10.3 (مورد ۳): فاصله از کلاس CSS (۶ م‌م) — بدون استایل این‌لاین چسبیده
			printf( '<tr><td class="tt">مجموع محصولات</td><td>%s</td></tr>', wp_kses_post( wc_price( (float) $order->get_subtotal() ) ) );
			printf( '<tr><td class="tt">هزینه ارسال%s</td><td>%s</td></tr>', $ship_methods ? ' [' . esc_html( implode( '، ', $ship_methods ) ) . ']' : '', wp_kses_post( wc_price( (float) $order->get_shipping_total() ) ) );
			printf( '<tr class="grand"><td class="tt">مجموع سفارش</td><td>%s</td></tr>', wp_kses_post( wc_price( (float) $order->get_total() ) ) );
			echo '</table>';

			// v1.10.3 (مورد ۵) — پابرگ بایگانی: فروشگاه + شماره سفارش فروشنده + شماره سند مرکزی (بدون هیچ اشاره به هویت مرکز)
			$vendor_order_no = trim( (string) ( $order->get_meta( '_dastyar_remote_order_number' ) ?: $order->get_meta( '_dastyar_remote_order_id' ) ) );
			printf(
				'<div class="l-archive">(برای بایگانی): این سفارش مربوط به فروشگاه <strong>%s</strong> به شماره سفارش فروشنده <strong class="l-num">%s</strong> با شماره سند <strong class="l-num">%s</strong> است.</div>',
				esc_html( '' !== $brand_name ? $brand_name : '—' ),
				esc_html( '' !== $vendor_order_no ? $vendor_order_no : '—' ),
				esc_html( (string) $order->get_order_number() )
			);

			// v1.5.2: بخش امضاها (مهر و امضای فروشگاه / امضا مشتری) به درخواست مدیر حذف شد
				echo '</div></div>'; // .l-frame / .dastyar-label
		}

		echo '</body></html>';
	}


	protected function default_sender_address() {
		$parts  = array();
		$parts[] = WC()->countries->get_base_city();
		$parts[] = WC()->countries->get_base_address();
		$parts[] = WC()->countries->get_base_address_2();
		return trim( implode( ' — ', array_filter( $parts ) ) );
	}

	/* ------------------------------------------------------------------
	 * منوی دستیار شاپ
	 * ---------------------------------------------------------------- */

	public function menu() {
		// شمارنده‌های انتظار (برای حباب اعلان در منو)
		$pending_vendors     = count( get_users( array( 'meta_key' => Dastyar_Registration::PENDING, 'meta_value' => 1, 'fields' => 'ids' ) ) );
		$pending_suggestions = count( get_posts( array(
			'post_type'      => Dastyar_Suggestions::POST_TYPE,
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_key'       => Dastyar_Suggestions::STATUS,
			'meta_value'     => 'pending',
		) ) );
		// مرجوعی‌های باز (نیازمند اقدام مدیر)
		$pending_rmas = count( get_posts( array(
			'post_type'      => Dastyar_Rma::POST_TYPE,
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => Dastyar_Rma::M_STATUS,
					'value'   => array( 'submitted', 'reviewing', 'need_info', 'approved', 'await_item', 'item_received', 'final_review' ),
					'compare' => 'IN',
				),
			),
		) ) );
		$bubble = function ( $n ) {
			return $n > 0 ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>' : '';
		};

		// موقعیت ۳ = بلافاصله زیر «پیشخوان» در منوی وردپرس (v1.5.0)
		add_menu_page( 'دستیار شاپ', 'دستیار شاپ' . $bubble( $pending_rmas ) . ' <span class="dastyar-live-dot" aria-hidden="true"></span>', 'manage_woocommerce', 'dastyar', array( $this, 'page_rma' ), 'dashicons-networking', 3 );
		add_submenu_page( 'dastyar', 'عودت و مرجوعی', 'عودت و مرجوعی' . $bubble( $pending_rmas ), 'manage_woocommerce', 'dastyar', array( $this, 'page_rma' ) );
		add_submenu_page( 'dastyar', 'مرجوعی‌های قدیمی (legacy)', 'مرجوعی‌های قدیمی', 'manage_woocommerce', 'dastyar-refunds-legacy', array( $this, 'page_refunds' ) );
		add_submenu_page( 'dastyar', 'فروشندگان', 'فروشندگان' . $bubble( $pending_vendors ), 'manage_woocommerce', 'dastyar-vendors', array( $this, 'page_vendors' ) );
		add_submenu_page( 'dastyar', 'پیشنهادهای محصول', 'پیشنهادهای محصول' . $bubble( $pending_suggestions ), 'manage_woocommerce', 'dastyar-suggestions', array( $this, 'page_suggestions' ) );
		add_submenu_page( 'dastyar', 'لاگ API', 'لاگ API', 'manage_woocommerce', 'dastyar-logs', array( $this, 'page_logs' ) );
		add_submenu_page( 'dastyar', 'وضعیت و تنظیمات', 'وضعیت و تنظیمات', 'manage_woocommerce', 'dastyar-status', array( $this, 'page_status' ) );
		add_submenu_page( 'dastyar', 'پیامک', 'پیامک', 'manage_woocommerce', 'dastyar-sms', array( $this, 'page_sms' ) ); // v1.9.8 — صفحه مستقل پیامک
	}

	/**
	 * (v1.7.4 — بازخورد کاربر، مورد ۳) تب «دستیار شاپ» دیگر رنگی نیست؛ مثل بقیه منوها بی‌رنگ است
	 * و فقط یک «نقطه سبز موج‌دار» (نماد وضعیت زنده) کنار نامش می‌درخشد.
	 */
	public function menu_brand_css() {
		// (v1.7.6 — بازخورد کاربر) صرف‌نظر از کاشی لوگو؛ برگشت به حالت ساده: فقط نقطه سبز موج‌دار کنار نام
		echo '<style>' .
			'.dastyar-live-dot{display:inline-block;width:8px;height:8px;margin-inline-start:7px;vertical-align:middle;border-radius:50%;background:#17a16d;box-shadow:0 0 0 0 rgba(23,161,109,.55);animation:dastyarLivePulse 1.8s ease-out infinite}' .
			'@keyframes dastyarLivePulse{0%{box-shadow:0 0 0 0 rgba(23,161,109,.55)}70%{box-shadow:0 0 0 9px rgba(23,161,109,0)}100%{box-shadow:0 0 0 0 rgba(23,161,109,0)}}' .
			'</style>';
	}

	/** درخواست‌های مرجوعی قدیمی (legacy) — انجام Refund واقعی با WooCommerce Refund از صفحه خود سفارش */
	public function page_refunds() {
		echo '<div class="wrap"><h1>درخواست‌های مرجوعی فروشندگان</h1>';
		$orders = wc_get_orders( array(
			'meta_key'     => '_dastyar_refund_requests',
			'meta_compare' => 'EXISTS',
			'limit'        => 50,
			'orderby'      => 'date',
			'order'        => 'DESC',
		) );
		echo '<table class="widefat striped"><thead><tr><th>سفارش</th><th>فروشنده</th><th>مبلغ</th><th>دلیل</th><th>وضعیت</th><th>اقدام</th></tr></thead><tbody>';
		$found = false;
		foreach ( $orders as $order ) {
			foreach ( (array) $order->get_meta( '_dastyar_refund_requests' ) as $i => $req ) {
				if ( 'pending' !== ( $req['status'] ?? '' ) ) {
					continue;
				}
				$found  = true;
				$vendor = get_userdata( (int) $order->get_meta( '_dastyar_vendor_id' ) );
				echo '<tr>';
				printf( '<td><a href="%s">#%s</a></td>', esc_url( $order->get_edit_order_url() ), esc_html( $order->get_order_number() ) );
				printf( '<td>%s</td>', $vendor ? esc_html( $vendor->display_name ) : '—' );
				printf( '<td>%s</td>', wp_kses_post( wc_price( (float) $req['amount'] ) ) );
				printf( '<td>%s</td>', esc_html( $req['reason'] ) );
				echo '<td>در انتظار بررسی</td><td>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
				wp_nonce_field( 'dastyar_refund_done' );
				echo '<input type="hidden" name="action" value="dastyar_refund_done">';
				printf( '<input type="hidden" name="order_id" value="%d"><input type="hidden" name="idx" value="%d">', (int) $order->get_id(), (int) $i );
				echo '<button class="button button-primary">علامت‌گذاری انجام شد</button></form> ';
				printf( '<a class="button" href="%s">اجرای Refund در سفارش</a>', esc_url( $order->get_edit_order_url() ) );
				echo '</td></tr>';
			}
		}
		if ( ! $found ) {
			echo '<tr><td colspan="6">درخواست باز وجود ندارد.</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public function refund_done() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyar_refund_done' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$order = wc_get_order( (int) ( $_POST['order_id'] ?? 0 ) );
		$idx   = (int) ( $_POST['idx'] ?? 0 );
		if ( $order ) {
			$requests = (array) $order->get_meta( '_dastyar_refund_requests' );
			if ( isset( $requests[ $idx ] ) ) {
				$requests[ $idx ]['status']       = 'processed';
				$requests[ $idx ]['processed_at'] = current_time( 'mysql' );
				$order->update_meta_data( '_dastyar_refund_requests', $requests );
				$order->add_order_note( 'درخواست مرجوعی فروشنده بررسی و بسته شد.' );
				$order->save();
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyar-refunds-legacy' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * عودت و مرجوعی (RMA) — درخواست‌های ارسال‌شده از سایت فروشنده‌ها
	 * ---------------------------------------------------------------- */

	/** لیست مرجوعی‌ها + نمای تکی (?view=ID) */
	public function page_rma() {
		$view_id = (int) ( $_GET['view'] ?? 0 );
		if ( $view_id ) {
			$this->page_rma_detail( $view_id );
			return;
		}

		echo '<div class="wrap"><h1>عودت و مرجوعی (RMA)</h1>';
		echo '<p class="description">رابط اصلی با مشتری نهایی، فروشنده است؛ درخواست‌ها از سایت فروشنده‌ها به‌صورت خودکار اینجا می‌رسند و نتیجه نهایی (به‌همراه بازپرداخت کیف پول در صورت «لغو سفارش») به همان سایت بازگردانده می‌شود.</p>';

		$this->rma_notices();

		// فیلترها
		$f_status = sanitize_key( (string) ( $_GET['rma_status'] ?? '' ) );
		$f_vendor = (int) ( $_GET['rma_vendor'] ?? 0 );

		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="margin:12px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
		echo '<input type="hidden" name="page" value="dastyar">';
		echo '<select name="rma_status"><option value="">همه وضعیت‌ها</option>';
		foreach ( Dastyar_Rma::statuses() as $slug => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $slug ), selected( $f_status, $slug, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '<select name="rma_vendor"><option value="0">همه فروشنده‌ها</option>';
		foreach ( get_users( array( 'role' => 'dastyar_vendor' ) ) as $v ) {
			printf( '<option value="%d" %s>%s</option>', (int) $v->ID, selected( $f_vendor, (int) $v->ID, false ), esc_html( $v->display_name ) );
		}
		echo '</select>';
		submit_button( 'فیلتر', 'secondary', '', false );
		echo '</form>';

		$args = array(
			'post_type'      => Dastyar_Rma::POST_TYPE,
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$meta_query = array();
		if ( $f_status && array_key_exists( $f_status, Dastyar_Rma::statuses() ) ) {
			$meta_query[] = array( 'key' => Dastyar_Rma::M_STATUS, 'value' => $f_status );
		}
		if ( $f_vendor ) {
			$meta_query[] = array( 'key' => Dastyar_Rma::M_VENDOR, 'value' => $f_vendor, 'compare' => '=', 'type' => 'NUMERIC' );
		}
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query;
		}
		$posts = get_posts( $args );

		if ( ! $posts ) {
			echo '<p>درخواست مرجوعی‌ای یافت نشد.</p></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr><th>شناسه</th><th>سفارش مرکز</th><th>سفارش فروشگاه</th><th>فروشنده</th><th>اقلام</th><th>دلیل</th><th>تاریخ</th><th>وضعیت</th><th>نتیجه نهایی</th><th></th></tr></thead><tbody>';
		foreach ( $posts as $p ) {
			$status    = (string) get_post_meta( $p->ID, Dastyar_Rma::M_STATUS, true ) ?: 'submitted';
			$order     = wc_get_order( (int) get_post_meta( $p->ID, Dastyar_Rma::M_ORDER, true ) );
			$vendor    = get_userdata( (int) get_post_meta( $p->ID, Dastyar_Rma::M_VENDOR, true ) );
			$items     = Dastyar_Rma::meta_arr( $p->ID, Dastyar_Rma::M_ITEMS );
			$qty       = 0;
			foreach ( $items as $it ) {
				$qty += (int) ( $it['qty'] ?? 1 );
			}
			$final     = (string) get_post_meta( $p->ID, Dastyar_Rma::M_FINAL, true );
			$credit    = (float) get_post_meta( $p->ID, Dastyar_Rma::M_CREDIT, true );
			$finals    = Dastyar_Rma::finals();

			echo '<tr>';
			printf( '<td><strong>#%d</strong></td>', (int) $p->ID );
			printf( '<td>%s</td>', $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>' : '—' );
			printf( '<td>#%s</td>', esc_html( get_post_meta( $p->ID, Dastyar_Rma::M_REMOTE, true ) ?: '—' ) );
			printf( '<td>%s</td>', $vendor ? esc_html( $vendor->display_name ) : '—' );
			printf( '<td>%d</td>', $qty );
			printf( '<td>%s</td>', esc_html( wp_trim_words( (string) get_post_meta( $p->ID, Dastyar_Rma::M_REASON, true ), 12 ) ) );
			printf( '<td>%s</td>', esc_html( Dastyar_Jalali::format( 'Y/m/d H:i', $p->post_date ) ) );
			printf( '<td><span style="display:inline-block;background:%s;color:#fff;border-radius:12px;padding:2px 10px;font-size:11px;white-space:nowrap">%s</span></td>', esc_attr( Dastyar_Rma::status_color( $status ) ), esc_html( Dastyar_Rma::status_label( $status ) ) );
			printf( '<td>%s</td>', $final ? esc_html( $finals[ $final ] ?? $final ) . ( $credit > 0 ? ' — ' . wp_kses_post( wc_price( $credit ) ) : '' ) : '—' );
			printf( '<td><a class="button button-small" href="%s">جزئیات و بررسی</a></td>', esc_url( admin_url( 'admin.php?page=dastyar&view=' . (int) $p->ID ) ) );
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/** نمای تکی مرجوعی: جزئیات کامل + تغییر وضعیت + نتیجه نهایی مالی */
	protected function page_rma_detail( $rma_id ) {
		$post = get_post( $rma_id );
		if ( ! $post || Dastyar_Rma::POST_TYPE !== $post->post_type ) {
			wp_die( 'درخواست مرجوعی یافت نشد.' );
		}

		$status    = (string) get_post_meta( $rma_id, Dastyar_Rma::M_STATUS, true ) ?: 'submitted';
		$order     = wc_get_order( (int) get_post_meta( $rma_id, Dastyar_Rma::M_ORDER, true ) );
		$vendor_id = (int) get_post_meta( $rma_id, Dastyar_Rma::M_VENDOR, true );
		$vendor    = get_userdata( $vendor_id );
		$items     = Dastyar_Rma::meta_arr( $rma_id, Dastyar_Rma::M_ITEMS );
		$reason    = (string) get_post_meta( $rma_id, Dastyar_Rma::M_REASON, true );
		$cust_note = (string) get_post_meta( $rma_id, Dastyar_Rma::M_CUSTOMER, true );
		$vend_note = (string) get_post_meta( $rma_id, Dastyar_Rma::M_VENDOR_N, true );
		$admin_n   = (string) get_post_meta( $rma_id, Dastyar_Rma::M_ADMIN_N, true );
		$attach    = array_filter( array_map( 'intval', Dastyar_Rma::meta_arr( $rma_id, Dastyar_Rma::M_ATTACH ) ) );
		$final     = (string) get_post_meta( $rma_id, Dastyar_Rma::M_FINAL, true );
		$fault     = (string) get_post_meta( $rma_id, Dastyar_Rma::M_FAULT, true );
		$credit    = (float) get_post_meta( $rma_id, Dastyar_Rma::M_CREDIT, true );

		echo '<div class="wrap">';
		printf( '<h1>مرجوعی #%d <a class="page-title-action" href="%s">← بازگشت به لیست</a></h1>', (int) $rma_id, esc_url( admin_url( 'admin.php?page=dastyar' ) ) );
		$this->rma_notices();

		// ── اطلاعات پایه ──
		echo '<div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start">';
		echo '<div style="flex:1;min-width:300px;background:#fff;border:1px solid #c3c4c7;padding:12px 16px">';
		echo '<h2 style="margin-top:0">اطلاعات درخواست</h2><table class="widefat" style="border:0">';
		printf( '<tr><th style="width:130px">وضعیت فعلی</th><td><span style="display:inline-block;background:%s;color:#fff;border-radius:12px;padding:2px 12px">%s</span></td></tr>', esc_attr( Dastyar_Rma::status_color( $status ) ), esc_html( Dastyar_Rma::status_label( $status ) ) );
		printf( '<tr><th>تاریخ ثبت</th><td>%s</td></tr>', esc_html( Dastyar_Jalali::format( 'Y/m/d H:i', $post->post_date ) ) );
		printf(
			'<tr><th>فروشنده</th><td>%s<br><small>%s%s</small></td></tr>',
			$vendor ? esc_html( $vendor->display_name ) : '—',
			$vendor ? esc_html( $vendor->user_email ) : '',
			Dastyar::instance()->vendors->site_url( $vendor_id ) ? ' — ' . esc_html( Dastyar::instance()->vendors->site_url( $vendor_id ) ) : ''
		);
		printf(
			'<tr><th>سفارش</th><td>%s (فروشگاه: #%s)</td></tr>',
			$order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a> — ' . wp_kses_post( wc_price( (float) $order->get_total() ) ) : '—',
			esc_html( get_post_meta( $rma_id, Dastyar_Rma::M_REMOTE, true ) ?: '—' )
		);
		if ( Dastyar_Rma::return_address() ) {
			printf( '<tr><th>آدرس مرجوعی</th><td>%s</td></tr>', nl2br( esc_html( Dastyar_Rma::return_address() ) ) );
		}
		echo '</table></div>';

		// ── اقلام + دلایل ──
		echo '<div style="flex:1;min-width:300px;background:#fff;border:1px solid #c3c4c7;padding:12px 16px">';
		echo '<h2 style="margin-top:0">اقلام درخواستی</h2>';
		echo '<table class="widefat striped"><thead><tr><th>کالا</th><th style="width:70px">تعداد</th><th style="width:90px">شناسه</th></tr></thead><tbody>';
		foreach ( $items as $it ) {
			printf( '<tr><td>%s</td><td>%d</td><td style="direction:ltr">#%d%s</td></tr>', esc_html( $it['name'] ?? '—' ), (int) ( $it['qty'] ?? 1 ), (int) ( $it['product_id'] ?? 0 ), ! empty( $it['variation_id'] ) ? ' / #' . (int) $it['variation_id'] : '' );
		}
		echo '</tbody></table>';
		printf( '<p><strong>دلیل مرجوعی:</strong> %s</p>', esc_html( $reason ) );
		if ( '' !== $cust_note ) {
			printf( '<p><strong>توضیحات مشتری:</strong><br>%s</p>', nl2br( esc_html( $cust_note ) ) );
		}
		if ( '' !== $vend_note ) {
			printf( '<p><strong>توضیحات فروشنده:</strong><br>%s</p>', nl2br( esc_html( $vend_note ) ) );
		}
		if ( $attach ) {
			echo '<p><strong>تصاویر ضمیمه:</strong><br>';
			foreach ( $attach as $aid ) {
				$full = wp_get_attachment_image_url( $aid, 'full' );
				$th   = wp_get_attachment_image_url( $aid, 'thumbnail' );
				if ( $full && $th ) {
					printf( '<a href="%s" target="_blank" style="display:inline-block;margin:0 0 6px 6px"><img src="%s" style="width:72px;height:72px;object-fit:cover;border:1px solid #ddd;border-radius:6px"></a>', esc_url( $full ), esc_url( $th ) );
				}
			}
			echo '</p>';
		}
		echo '</div></div>'; // پایان ردیف اطلاعات

		// ── نتیجه نهایی ثبت‌شده (اگر قبلاً ثبت شده) ──
		if ( $final ) {
			$finals = Dastyar_Rma::finals();
			echo '<div class="notice notice-info" style="padding:10px 14px;margin:14px 0">';
			printf(
				'<p style="margin:0"><strong>نتیجه نهایی ثبت‌شده:</strong> %s%s%s</p>',
				esc_html( $finals[ $final ] ?? $final ),
				$credit > 0 ? ' — مبلغ بازگشتی به کیف پول فروشنده: <strong>' . wp_kses_post( wc_price( $credit ) ) . '</strong>' : '',
				$fault ? ' (مقصر: ' . esc_html( 'dastyar' === $fault ? 'دستیار شاپ' : 'مشتری' ) . ')' : ''
			);
			echo '</div>';
		}

		echo '<div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start;margin-top:14px">';

		// ── فرم تغییر وضعیت ──
		echo '<div style="flex:1;min-width:300px;background:#fff;border:1px solid #c3c4c7;padding:12px 16px">';
		echo '<h2 style="margin-top:0">تغییر وضعیت</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dastyar_rma_save_' . $rma_id );
		echo '<input type="hidden" name="action" value="dastyar_rma_save">';
		printf( '<input type="hidden" name="rma_id" value="%d">', (int) $rma_id );
		echo '<p><select name="rma_status" style="width:100%">';
		foreach ( Dastyar_Rma::statuses() as $slug => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $slug ), selected( $status, $slug, false ), esc_html( $label ) );
		}
		echo '</select></p>';
		echo '<p class="description">با انتخاب «منتظر ارسال کالا»، آدرس مقصد مرجوعی (تنظیمات) به فروشنده ارسال می‌شود.</p>';
		printf( '<p><label><strong>توضیحات اپراتور (برای فروشنده نمایش داده می‌شود)</strong></label><br><textarea name="rma_admin_note" rows="4" style="width:100%%">%s</textarea></p>', esc_textarea( $admin_n ) );
		submit_button( 'ذخیره و اطلاع‌رسانی به فروشنده' );
		echo '</form></div>';

		// ── فرم نتیجه نهایی (مالی) ──
		if ( ! $final ) {
			$p_dastyar  = Dastyar_Rma::proposed_credit( $rma_id, 'dastyar' );
			$p_customer = Dastyar_Rma::proposed_credit( $rma_id, 'customer' );

			echo '<div style="flex:1;min-width:300px;background:#fff;border:1px solid #c3c4c7;padding:12px 16px">';
			echo '<h2 style="margin-top:0">نتیجه نهایی</h2>';
			echo '<p class="description">قواعد مالی لغو سفارش: مقصر «دستیار شاپ» → مبلغ کالا + هزینه ارسال به کیف پول فروشنده شارژ می‌شود؛ مقصر «مشتری» → فقط مبلغ کالا.</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'نتیجه نهایی ثبت شود؟ این عمل قابل ویرایش ساده نیست.\')">';
			wp_nonce_field( 'dastyar_rma_final_' . $rma_id );
			echo '<input type="hidden" name="action" value="dastyar_rma_final">';
			printf( '<input type="hidden" name="rma_id" value="%d">', (int) $rma_id );
			echo '<p><strong>نتیجه:</strong><br><select name="rma_final" id="dastyar-rma-final" style="width:100%">';
			foreach ( Dastyar_Rma::finals() as $slug => $label ) {
				printf( '<option value="%s">%s</option>', esc_attr( $slug ), esc_html( $label ) );
			}
			echo '</select></p>';
			echo '<p><strong>مقصر (فقط برای لغو سفارش):</strong><br>';
			echo '<label style="display:block;margin:4px 0"><input type="radio" name="rma_fault" value="dastyar" checked> دستیار شاپ (مبلغ کالا + هزینه ارسال)</label>';
			echo '<label style="display:block;margin:4px 0"><input type="radio" name="rma_fault" value="customer"> مشتری (فقط مبلغ کالا)</label></p>';
			echo '<div id="dastyar-rma-credit-row">';
			echo '<p><label><strong>مبلغ بازگشتی به کیف پول (' . esc_html( get_woocommerce_currency_symbol() ) . ')</strong></label><br>';
			printf( '<input type="number" step="1" min="0" name="rma_credit" id="dastyar-rma-credit" value="%s" style="width:100%%;direction:ltr"></p>', esc_attr( $p_dastyar ) );
			printf(
				'<p><button type="button" class="button dastyar-rma-fill" data-amount="%s" data-fault="dastyar">مبلغ پیشنهادی: کالا + ارسال (%s)</button> <button type="button" class="button dastyar-rma-fill" data-amount="%s" data-fault="customer">فقط مبلغ کالا (%s)</button></p>',
				esc_attr( $p_dastyar ),
				wp_kses_post( wc_price( $p_dastyar ) ),
				esc_attr( $p_customer ),
				wp_kses_post( wc_price( $p_customer ) )
			);
			echo '</div>';
			submit_button( 'ثبت نتیجه نهایی', 'primary' );
			echo '</form></div>';
			?>
			<script>
			(function(){
				var finalSel = document.getElementById('dastyar-rma-final');
				var creditRow = document.getElementById('dastyar-rma-credit-row');
				function sync(){
					creditRow.style.display = ('cancel' === finalSel.value) ? '' : 'none';
				}
				finalSel.addEventListener('change', sync);
				sync();
				document.querySelectorAll('.dastyar-rma-fill').forEach(function(btn){
					btn.addEventListener('click', function(){
						document.getElementById('dastyar-rma-credit').value = btn.getAttribute('data-amount');
						document.querySelectorAll('input[name="rma_fault"]').forEach(function(r){
							r.checked = (r.value === btn.getAttribute('data-fault'));
						});
					});
				});
			})();
			</script>
			<?php
		}

		echo '</div>'; // پایان ردیف فرم‌ها
		echo '</div>'; // wrap
	}

	/** اعلان‌های نتیجه عملیات RMA */
	protected function rma_notices() {
		if ( isset( $_GET['rma_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>وضعیت مرجوعی ذخیره شد و به سایت فروشنده اطلاع‌رسانی شد.</p></div>';
		}
		if ( isset( $_GET['rma_finalized'] ) ) {
			$c = (float) ( $_GET['credit'] ?? 0 );
			printf(
				'<div class="notice notice-success is-dismissible"><p>نتیجه نهایی ثبت شد.%s</p></div>',
				$c > 0 ? ' مبلغ ' . wp_kses_post( wc_price( $c ) ) . ' به کیف پول فروشنده شارژ شد.' : ''
			);
		}
		if ( ! empty( $_GET['rma_error'] ) ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( sanitize_text_field( wp_unslash( $_GET['rma_error'] ) ) ) );
		}
	}

	/** ذخیره وضعیت + توضیحات اپراتور (Push خودکار به فروشنده در صورت تغییر وضعیت) */
	public function rma_save() {
		$rma_id = (int) ( $_POST['rma_id'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyar_rma_save_' . $rma_id ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$post = get_post( $rma_id );
		if ( ! $post || Dastyar_Rma::POST_TYPE !== $post->post_type ) {
			wp_die( 'درخواست مرجوعی یافت نشد.' );
		}
		$new  = sanitize_key( (string) ( $_POST['rma_status'] ?? '' ) );
		$note = sanitize_textarea_field( wp_unslash( $_POST['rma_admin_note'] ?? '' ) );
		if ( array_key_exists( $new, Dastyar_Rma::statuses() ) ) {
			Dastyar_Rma::set_status( $rma_id, $new, $note );
		} else {
			update_post_meta( $rma_id, Dastyar_Rma::M_ADMIN_N, $note );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyar&view=' . $rma_id . '&rma_saved=1' ) );
		exit;
	}

	/** ثبت نتیجه نهایی (تعویض / لغو با بازپرداخت کیف پول / رد) */
	public function rma_final() {
		$rma_id = (int) ( $_POST['rma_id'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyar_rma_final_' . $rma_id ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$post = get_post( $rma_id );
		if ( ! $post || Dastyar_Rma::POST_TYPE !== $post->post_type ) {
			wp_die( 'درخواست مرجوعی یافت نشد.' );
		}
		$res = Dastyar_Rma::finalize(
			$rma_id,
			sanitize_key( (string) ( $_POST['rma_final'] ?? '' ) ),
			sanitize_key( (string) ( $_POST['rma_fault'] ?? '' ) ),
			(float) ( $_POST['rma_credit'] ?? 0 )
		);
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'dastyar', 'view' => $rma_id, 'rma_error' => rawurlencode( $res->get_error_message() ) ), admin_url( 'admin.php' ) ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyar&view=' . $rma_id . '&rma_finalized=1&credit=' . rawurlencode( (string) $res ) ) );
		exit;
	}

	/** فهرست فروشندگان + درخواست‌های در انتظار تأیید ثبت‌نام + رده‌بندی + خروجی اکسل */
	public function page_vendors() {
		echo '<div class="wrap"><h1>فروشندگان دستیار</h1>';

		if ( isset( $_GET['tiers_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>رده‌بندی فروشندگان ذخیره شد ✔ از همین لحظه روی دسترسی API هر سایت اثر می‌گذارد.</p></div>';
		}

		// --- درخواست‌های فروشندگی در انتظار تأیید مدیر (ثبت‌نام از شورتکد [dastyar_vendor_auth]) ---
		$pending = get_users( array( 'meta_key' => Dastyar_Registration::PENDING, 'meta_value' => 1 ) );
		if ( $pending ) {
			printf( '<div class="notice notice-warning" style="padding:10px 14px;margin:16px 0"><h2 style="margin:0 0 4px;font-size:15px">%d درخواست فروشندگی در انتظار تأیید شماست</h2><p style="margin:0">پس از «تأیید»، نقش کاربر به «فروشنده دستیار» تغییر می‌کند و پنل فروشنده برایش فعال می‌شود؛ نتیجه به ایمیلش اطلاع‌رسانی می‌شود.</p></div>', count( $pending ) );
			echo '<table class="widefat striped" style="max-width:1100px;margin-bottom:26px"><thead><tr><th>نام</th><th>فروشگاه</th><th>ایمیل</th><th>موبایل</th><th>سایت</th><th>تاریخ ثبت‌نام</th><th>اقدام</th></tr></thead><tbody>';
			foreach ( $pending as $u ) {
				$approve = wp_nonce_url( admin_url( 'admin-post.php?action=dastyar_approve_vendor&uid=' . $u->ID ), 'dastyar_approve_vendor_' . $u->ID );
				$reject  = wp_nonce_url( admin_url( 'admin-post.php?action=dastyar_reject_vendor&uid=' . $u->ID ), 'dastyar_reject_vendor_' . $u->ID );
				echo '<tr>';
				printf( '<td><strong>%s</strong></td>', esc_html( $u->display_name ) );
				printf( '<td>%s</td>', esc_html( get_user_meta( $u->ID, '_dastyar_shop_name', true ) ?: '—' ) );
				printf( '<td>%s</td>', esc_html( $u->user_email ) );
				printf( '<td style="direction:ltr;text-align:right">%s</td>', esc_html( get_user_meta( $u->ID, 'billing_phone', true ) ?: '—' ) );
				printf( '<td style="direction:ltr;text-align:right">%s</td>', esc_html( Dastyar::instance()->vendors->site_url( $u->ID ) ?: '—' ) );
				printf( '<td>%s</td>', esc_html( $u->user_registered ) );
				printf(
					'<td><a class="button button-primary" href="%s">✔ تأیید فروشنده</a> <a class="button" href="%s" onclick="return confirm(\'درخواست این کاربر رد شود؟\')">✖ رد</a></td>',
					esc_url( $approve ),
					esc_url( $reject )
				);
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		// --- لیست فروشنده‌های فعال ---
		$vendors = get_users( array( 'role' => 'dastyar_vendor' ) );
		if ( $vendors ) {
			echo '<h2>فروشندگان فعال ';
			printf(
				'<a class="page-title-action" href="%s" style="background:#17a16d;border-color:#17a16d;color:#fff">📗 خروجی اکسل تراز (همه فروشندگان)</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyar_vendor_csv&uid=0' ), 'dastyar_vendor_csv' ) )
			);
			echo '</h2>';
			echo '<table class="widefat striped"><thead><tr><th>لوگو</th><th>فروشنده</th><th>فروشگاه</th><th>ایمیل</th><th>سایت</th><th>رده</th><th>کیف پول</th><th>کلید API</th><th></th></tr></thead><tbody>';
			foreach ( $vendors as $v ) {
				$logo_id  = (int) get_user_meta( $v->ID, '_dastyar_logo_id', true );
				$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
				$tier     = Dastyar_Tiers::tier_of( $v->ID );
				echo '<tr>';
				printf( '<td>%s</td>', $logo_url ? '<img src="' . esc_url( $logo_url ) . '" style="width:36px;height:36px;object-fit:contain">' : '—' );
				printf( '<td>%s</td>', esc_html( $v->display_name ) );
				printf( '<td>%s</td>', esc_html( get_user_meta( $v->ID, '_dastyar_shop_name', true ) ?: '—' ) );
				printf( '<td>%s</td>', esc_html( $v->user_email ) );
				printf( '<td>%s</td>', esc_html( Dastyar::instance()->vendors->site_url( $v->ID ) ?: '—' ) );
				printf( '<td>%s</td>', $tier ? '<span style="display:inline-block;background:#e5f5ee;border:1px solid #a9dcc4;color:#0f5132;border-radius:10px;padding:0 9px;font-size:11px">' . esc_html( $tier['name'] ) . '</span>' : '<span class="description">همه محصولات</span>' );
				printf( '<td>%s</td>', wp_kses_post( wc_price( Dastyar::instance()->wallet->get_balance( $v->ID ) ) ) );
				printf( '<td><code>%s</code></td>', esc_html( Dastyar::instance()->vendors->hint( $v->ID ) ?: '—' ) );
				printf(
					'<td><a class="button" href="%s">مدیریت</a> <a class="button" href="%s" title="دانلود اکسل تراز کیف پول و سفارشات">📗 اکسل</a> <a class="button" href="%s" title="v1.9.7 — ارسال مجدد پیامک «تأیید عضویت» به موبایل این فروشنده">📨 پیامک تأیید</a></td>',
					esc_url( admin_url( 'user-edit.php?user_id=' . $v->ID ) ),
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyar_vendor_csv&uid=' . $v->ID ), 'dastyar_vendor_csv' ) ),
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyar_vendor_sms_resend&uid=' . $v->ID ), 'dastyar_vendor_sms_resend_' . $v->ID ) )
				);
				echo '</tr>';
			}
			echo '</tbody></table>';
		} elseif ( ! $pending ) {
			printf( '<p>فروشنده‌ای ثبت نشده است. از <a href="%s">کاربران → افزودن</a> کاربر جدید با نقش «فروشنده دستیار» بسازید یا فرم ثبت‌نام فروشنده (شورتکد <code>[dastyar_vendor_auth]</code>) را در اختیار متقاضیان قرار دهید.</p>', esc_url( admin_url( 'user-new.php' ) ) );
		}

		// تنظیم دستی کیف پول فروشنده (v1.6.0) — واریز/برداشت اعتبار با شرح
		$this->render_wallet_adjust_card();

		// رده‌بندی محرمانه فروشندگان (v1.5.0) — فقط مدیر می‌بیند
		$this->render_tiers_card();

		echo '</div>';
	}

	/**
	 * کارت «تنظیم دستی کیف پول» (v1.6.0) — مدیر می‌تواند به هر فروشنده اعتبار بدهد یا کسر کند.
	 * از همان متدهای Dastyar_Wallet استفاده می‌شود تا تراکنش با شرح در جدول ثبت و قابل ردیابی بماند.
	 */
	protected function render_wallet_adjust_card() {
		$vendors = get_users( array( 'role' => 'dastyar_vendor' ) );
		if ( ! $vendors ) {
			return;
		}
		if ( isset( $_GET['wallet_adjusted'] ) ) {
			$bal = isset( $_GET['new_bal'] ) ? wc_price( (float) wp_unslash( $_GET['new_bal'] ) ) : '';
			printf( '<div class="notice notice-success is-dismissible" style="max-width:980px"><p>✔ کیف پول با موفقیت به‌روزرسانی شد. موجودی جدید: <strong>%s</strong></p></div>', wp_kses_post( $bal ) );
		}
		if ( isset( $_GET['wallet_error'] ) ) {
			printf( '<div class="notice notice-error is-dismissible" style="max-width:980px"><p>%s</p></div>', esc_html( sanitize_text_field( wp_unslash( $_GET['wallet_error'] ) ) ) );
		}
		// v1.9.7 — نتیجه ارسال مجدد پیامک تأیید عضویت
		if ( isset( $_GET['sms_resend'] ) ) {
			if ( 'resent' === $_GET['sms_resend'] ) {
				echo '<div class="notice notice-success is-dismissible" style="max-width:980px"><p>✔ پیامک «تأیید عضویت» دوباره برای فروشنده ارسال شد. نتیجه دقیق را در «پیامک ← آخرین پیامک‌ها» ببینید.</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible" style="max-width:980px"><p>کاربر انتخاب‌شده فروشنده نیست؛ پیامکی ارسال نشد.</p></div>';
			}
		}

		echo '<hr style="margin:28px 0 18px"><h2>💳 تنظیم دستی کیف پول فروشنده <small style="font-weight:400;color:#8a97a3;font-size:12px">(واریز اعتبار، کسر یا اصلاح دستی موجودی — همه با شرح در تراکنش‌ها ثبت می‌شود)</small></h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:980px;background:#fff;border:1.5px solid #e3ebe7;border-radius:14px;padding:18px 22px">';
		wp_nonce_field( 'dastyar_wallet_adjust' );
		echo '<input type="hidden" name="action" value="dastyar_wallet_adjust">';
		echo '<table class="form-table" style="margin:0"><tbody>';

		echo '<tr><th scope="row" style="width:180px"><label for="dwa-uid">فروشنده</label></th><td>';
		echo '<select name="uid" id="dwa-uid" required>';
		foreach ( $vendors as $v ) {
			$shop = get_user_meta( $v->ID, '_dastyar_shop_name', true );
			printf(
				'<option value="%d">%s%s — موجودی: %s</option>',
				(int) $v->ID,
				esc_html( $v->display_name ),
				$shop ? ' (' . esc_html( $shop ) . ')' : '',
				wp_kses_post( wc_price( Dastyar::instance()->wallet->get_balance( $v->ID ) ) )
			);
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="dwa-type">نوع تراکنش</label></th><td>';
		echo '<select name="txn_type" id="dwa-type"><option value="credit">➕ واریز اعتبار (افزایش موجودی)</option><option value="debit">➖ کسر از کیف پول (کاهش موجودی)</option></select></td></tr>';

		echo '<tr><th scope="row"><label for="dwa-amount">مبلغ (' . esc_html( get_woocommerce_currency_symbol() ) . ')</label></th><td>';
		echo '<input type="number" step="any" min="1" name="amount" id="dwa-amount" required style="direction:ltr;min-width:200px"></td></tr>';

		echo '<tr><th scope="row"><label for="dwa-desc">شرح تراکنش</label></th><td>';
		echo '<input type="text" name="description" id="dwa-desc" class="regular-text" placeholder="مثلاً: اعتبار خوش‌آمدگویی / جبران خسارت سفارش #…" style="width:100%;max-width:520px"></td></tr>';

		echo '</tbody></table>';
		echo '<p class="submit" style="margin:10px 0 0"><button type="submit" class="button button-primary" style="background:#17a16d;border-color:#17a16d;border-radius:10px;font-weight:700">اعمال تراکنش</button> <span class="description">پس از اعمال، نتیجه در جدول تراکنش‌های کیف پول فروشنده قابل مشاهده است.</span></p>';
		echo '</form>';
	}

	/** هندلر تنظیم دستی کیف پول (v1.6.0) */
	public function wallet_adjust() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyar_wallet_adjust' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$uid    = (int) ( $_POST['uid'] ?? 0 );
		$amount = (float) ( $_POST['amount'] ?? 0 );
		$type   = 'debit' === ( $_POST['txn_type'] ?? '' ) ? 'debit' : 'credit';
		$desc   = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) );

		$user = $uid ? get_userdata( $uid ) : null;
		if ( ! $user || ! in_array( 'dastyar_vendor', (array) $user->roles, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=dastyar-vendors&wallet_error=' . rawurlencode( 'فروشنده انتخاب‌شده معتبر نیست.' ) ) );
			exit;
		}
		if ( $amount <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=dastyar-vendors&wallet_error=' . rawurlencode( 'مبلغ باید بزرگ‌تر از صفر باشد.' ) ) );
			exit;
		}
		if ( '' === $desc ) {
			$desc = 'تنظیم دستی توسط مدیر سایت';
		} else {
			$desc .= ' (دستی توسط مدیر)';
		}

		$wallet = Dastyar::instance()->wallet;
		$new    = 'credit' === $type ? $wallet->credit( $uid, $amount, 0, $desc ) : $wallet->debit( $uid, $amount, 0, $desc );
		if ( false === $new ) {
			wp_safe_redirect( admin_url( 'admin.php?page=dastyar-vendors&wallet_error=' . rawurlencode( 'کسر ناموفق بود — موجودی کیف پول کافی نیست.' ) ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyar-vendors&wallet_adjusted=1&new_bal=' . rawurlencode( (string) $new ) ) );
		exit;
	}

	/**
	 * کارت «رده‌بندی فروشندگان» — ساخت/ویرایش رده‌ها + محصولات مجاز هر رده (v1.5.0).
	 * این بخش محرمانه است و هیچ‌جای پنل فروشنده نمایش داده نمی‌شود.
	 */
	protected function render_tiers_card() {
		$tiers = Dastyar_Tiers::all();

		echo '<hr style="margin:28px 0 18px"><h2>🎚 رده‌بندی فروشندگان <small style="font-weight:400;color:#8a97a3;font-size:12px">(محرمانه — فقط شما این بخش را می‌بینید؛ فروشنده هرگز متوجه رده خودش نمی‌شود)</small></h2>';
		echo '<p class="description" style="max-width:880px">برای هر رده مشخص می‌کنید <strong>کدام محصولات</strong> در API آن فروشنده‌ها دیده شود (لیست محصولات، دریافت تکی، سینک موجودی و اعلان‌های تغییر). فروشنده «بدون رده» مثل قبل <strong>همه محصولات</strong> را می‌بیند. محصولاتی که بعداً از رده حذف شوند، در سینک بعدی به‌صورت خودکار در فروشگاه فروشنده «پیش‌نویس» می‌شوند تا دیگر به نمایش درنیایند. تخصیص فروشنده به رده: دکمه «مدیریت» در جدول بالا ← بخش «دستیار شاپ» ← «رده فروشنده».</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dastyar_tiers_save' );
		echo '<input type="hidden" name="action" value="dastyar_tiers_save">';

		echo '<table class="widefat striped" style="max-width:980px"><thead><tr>';
		echo '<th style="width:200px">عنوان رده</th><th>شناسه‌های محصولات مجاز (با ویرگول)</th><th style="width:110px">فروشنده‌ها</th><th style="width:60px">حذف</th>';
		echo '</tr></thead><tbody>';

		foreach ( $tiers as $i => $t ) {
			echo '<tr>';
			printf( '<td><input type="hidden" name="dastyar_tiers[%1$d][id]" value="%2$s"><input type="text" name="dastyar_tiers[%1$d][name]" value="%3$s" class="regular-text" required></td>', (int) $i, esc_attr( $t['id'] ), esc_attr( $t['name'] ) );
			printf( '<td><input type="text" name="dastyar_tiers[%d][products]" value="%s" class="large-text" style="direction:ltr" placeholder="مثلاً: 101,205,390"></td>', (int) $i, esc_attr( implode( ',', $t['products'] ) ) );
			printf( '<td>%d فروشنده</td>', (int) Dastyar_Tiers::count_vendors( $t['id'] ) );
			printf( '<td><input type="checkbox" name="dastyar_tiers[%d][delete]" value="1" title="حذف این رده (فروشنده‌هایش بدون رده می‌شوند)"></td>', (int) $i );
			echo '</tr>';
		}

		// ردیف ساخت رده جدید
		$row = count( $tiers );
		echo '<tr style="background:#f6fcf9">';
		printf( '<td><input type="text" name="dastyar_tiers[%d][name]" class="regular-text" placeholder="رده جدید — مثلاً: طلایی"></td>', (int) $row );
		printf( '<td><input type="text" name="dastyar_tiers[%d][products]" class="large-text" style="direction:ltr" placeholder="شناسه محصولات مجاز — خالی یعنی هیچ محصولی"></td>', (int) $row );
		echo '<td>—</td><td>—</td>';
		echo '</tr>';

		echo '</tbody></table>';
		echo '<p class="description">💡 شناسه هر محصول را می‌توانید در لیست «محصولات» ووکامرس (ستون ID یا روی لینک ویرایش) ببینید. چند شناسه را با ویرگول انگلیسی <code>,</code> جدا کنید.</p>';
		submit_button( '💾 ذخیره رده‌بندی' );
		echo '</form>';
	}

	/** ذخیره رده‌ها (admin-post) */
	public function tiers_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyar_tiers_save' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		Dastyar_Tiers::save_from_post();
		wp_safe_redirect( admin_url( 'admin.php?page=dastyar-vendors&tiers_saved=1' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * خروجی اکسل (CSV) تراز فروشندگان — قابل باز شدن مستقیم در Excel (v1.5.0)
	 * ---------------------------------------------------------------- */

	public function vendor_csv_download() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyar_vendor_csv' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$uid  = (int) ( $_GET['uid'] ?? 0 );
		$csv  = $this->vendor_csv( $uid );
		$name = 'dastyar-' . ( $uid ? 'vendor-' . $uid : 'vendors' ) . '-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/** ساخت CSV (با BOM برای فارسی در Excel) از ردیف‌های vendor_csv_rows */
	public function vendor_csv( $uid = 0 ) {
		$rows = $this->vendor_csv_rows( (int) $uid );
		$fh   = fopen( 'php://temp', 'r+' );
		foreach ( $rows as $r ) {
			fputcsv( $fh, $r );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return "\xEF\xBB\xBF" . $csv;
	}

	/**
	 * ردیف‌های گزارش تراز (قابل تست جداگانه).
	 * uid > 0 → گزارش کامل یک فروشنده + تراکنش‌های کیف پول؛ uid = 0 → جدول خلاصه همه فروشنده‌ها
	 */
	public function vendor_csv_rows( $uid ) {
		$wallet = Dastyar::instance()->wallet;
		$rows   = array();

		if ( $uid ) {
			$v = get_userdata( $uid );
			if ( ! $v ) {
				return array( array( 'فروشنده یافت نشد' ) );
			}
			$tier  = Dastyar_Tiers::tier_of( $uid );
			$stats = $this->vendor_orders_stats( $uid );

			$rows[] = array( 'گزارش تراز فروشنده دستیار شاپ' );
			$rows[] = array( 'فروشنده', $v->display_name );
			$rows[] = array( 'ایمیل', $v->user_email );
			$rows[] = array( 'سایت', Dastyar::instance()->vendors->site_url( $uid ) ?: '—' );
			$rows[] = array( 'رده', $tier ? $tier['name'] : 'بدون رده' );
			$rows[] = array( 'موجودی کیف پول (تومان)', number_format( $wallet->get_balance( $uid ) ) );
			$rows[] = array( 'تعداد سفارشات دستیار', (string) (int) $stats['count'] );
			$rows[] = array( 'مجموع سفارشات (تومان)', number_format( $stats['sum'] ) );
			$rows[] = array();
			$rows[] = array( 'تراکنش‌های کیف پول' );
			$rows[] = array( 'تاریخ', 'نوع', 'مبلغ (تومان)', 'موجودی بعد', 'سفارش', 'توضیح' );
			foreach ( $wallet->get_transactions( $uid, 200 ) as $t ) {
				$rows[] = array(
					Dastyar_Jalali::dt( (string) ( $t->created_at ?? '' ), 'Y/m/d H:i' ),
					( isset( $t->type ) && 'credit' === $t->type ) ? 'واریز' : 'برداشت',
					number_format( (float) ( $t->amount ?? 0 ) ),
					number_format( (float) ( $t->balance_after ?? 0 ) ),
					(string) (int) ( $t->ref_order_id ?? 0 ),
					(string) ( $t->description ?? '' ),
				);
			}
			$rows[] = array( 'تاریخ تولید گزارش: ' . Dastyar_Jalali::format( 'Y/m/d H:i' ) );
			return $rows;
		}

		// خلاصه همه فروشنده‌ها
		$rows[] = array( 'گزارش تراز فروشندگان دستیار شاپ', 'تاریخ تولید: ' . Dastyar_Jalali::format( 'Y/m/d H:i' ) );
		$rows[] = array( 'فروشنده', 'ایمیل', 'سایت', 'رده', 'موجودی کیف پول (تومان)', 'تعداد سفارشات', 'مجموع سفارشات (تومان)' );
		foreach ( get_users( array( 'role' => 'dastyar_vendor' ) ) as $v ) {
			$tier  = Dastyar_Tiers::tier_of( $v->ID );
			$stats = $this->vendor_orders_stats( $v->ID );
			$rows[] = array(
				$v->display_name,
				$v->user_email,
				Dastyar::instance()->vendors->site_url( $v->ID ) ?: '—',
				$tier ? $tier['name'] : 'بدون رده',
				number_format( $wallet->get_balance( $v->ID ) ),
				(string) (int) $stats['count'],
				number_format( $stats['sum'] ),
			);
		}
		return $rows;
	}

	/** آمار سفارش‌های یک فروشنده (تعداد + مجموع مبالغ) */
	protected function vendor_orders_stats( $uid ) {
		$orders = wc_get_orders( array(
			'limit'      => 500,
			'return'     => 'objects',
			'meta_key'   => '_dastyar_vendor_id',
			'meta_value' => (int) $uid,
		) );
		$count = 0;
		$sum   = 0.0;
		foreach ( (array) $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$count++;
			$sum += (float) $order->get_total();
		}
		return array( 'count' => $count, 'sum' => $sum );
	}

	/** تأیید درخواست فروشندگی → تغییر نقش به «فروشنده دستیار» + ایمیل اطلاع‌رسانی */
	public function approve_vendor() {
		$uid = (int) ( $_GET['uid'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyar_approve_vendor_' . $uid ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$user = get_userdata( $uid );
		if ( $user && Dastyar_Registration::is_pending( $uid ) ) {
			$user->set_role( 'dastyar_vendor' );
			delete_user_meta( $uid, Dastyar_Registration::PENDING );
			delete_user_meta( $uid, '_dastyar_vendor_rejected' );
			do_action( 'dastyar_vendor_approved', $uid, $user ); // v1.6.0 — اعلان پیامک تأیید عضویت

			wp_mail(
				$user->user_email,
				'✔ حساب فروشندگی شما در دستیار شاپ تأیید شد',
				sprintf(
					"سلام %s عزیز،\n\nحساب فروشندگی شما در دستیار شاپ تأیید شد و نقش کاربری‌تان به «فروشنده دستیار شاپ» تغییر کرد.\nاز همین حالا می‌توانید وارد پنل فروشنده خود شوید:\n%s\n\nموفق باشید!",
					$user->display_name,
					wc_get_page_permalink( 'myaccount' )
				)
			);
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyar-vendors&approved=1' ) );
		exit;
	}

	/** رد درخواست فروشندگی + ایمیل اطلاع‌رسانی */
	public function reject_vendor() {
		$uid = (int) ( $_GET['uid'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyar_reject_vendor_' . $uid ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$user = get_userdata( $uid );
		if ( $user && Dastyar_Registration::is_pending( $uid ) ) {
			delete_user_meta( $uid, Dastyar_Registration::PENDING );
			update_user_meta( $uid, '_dastyar_vendor_rejected', 1 );
			do_action( 'dastyar_vendor_rejected', $uid, $user ); // v1.6.0

			wp_mail(
				$user->user_email,
				'نتیجه بررسی درخواست فروشندگی دستیار شاپ',
				sprintf(
					"سلام %s عزیز،\n\nمتأسفانه درخواست فروشندگی شما در این مرحله تأیید نشد. برای کسب اطلاعات بیشتر با پشتیبانی در تماس باشید.\n%s",
					$user->display_name,
					home_url()
				)
			);
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyar-vendors&rejected=1' ) );
		exit;
	}

	/** صفحه مدیریت پیشنهادهای محصول فروشندگان */
	public function page_suggestions() {
		echo '<div class="wrap"><h1>پیشنهادهای محصول فروشندگان</h1>';

		$filter = sanitize_key( (string) ( $_GET['sugg_status'] ?? '' ) );
		$args   = array(
			'post_type'      => Dastyar_Suggestions::POST_TYPE,
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $filter && array_key_exists( $filter, Dastyar_Suggestions::statuses() ) ) {
			$args['meta_key']   = Dastyar_Suggestions::STATUS;
			$args['meta_value'] = $filter;
		}
		$posts = get_posts( $args );

		// فیلتر وضعیت
		echo '<p>';
		foreach ( array_merge( array( '' => 'همه' ), Dastyar_Suggestions::statuses() ) as $slug => $label ) {
			$url = $slug ? add_query_arg( 'sugg_status', $slug, admin_url( 'admin.php?page=dastyar-suggestions' ) ) : admin_url( 'admin.php?page=dastyar-suggestions' );
			printf( '<a class="button%s" href="%s">%s</a> ', $slug === $filter ? ' button-primary' : '', esc_url( $url ), esc_html( $label ) );
		}
		echo '</p>';

		if ( ! $posts ) {
			echo '<p>پیشنهادی یافت نشد.</p></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr><th style="width:64px">تصویر</th><th>محصول</th><th>فروشنده</th><th>توضیح / لینک</th><th>تاریخ</th><th>وضعیت</th><th>یادداشت مدیر (به فروشنده نمایش داده می‌شود)</th><th></th></tr></thead><tbody>';
		foreach ( $posts as $p ) {
			$status   = (string) get_post_meta( $p->ID, Dastyar_Suggestions::STATUS, true ) ?: 'pending';
			$note     = (string) get_post_meta( $p->ID, Dastyar_Suggestions::NOTE, true );
			$link     = (string) get_post_meta( $p->ID, Dastyar_Suggestions::LINK, true );
			$image_id = (int) get_post_meta( $p->ID, Dastyar_Suggestions::IMAGE, true );
			$img      = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
			$vendor   = get_userdata( (int) $p->post_author );

			echo '<tr>';
			printf( '<td>%s</td>', $img ? '<a href="' . esc_url( wp_get_attachment_image_url( $image_id, 'full' ) ) . '" target="_blank"><img src="' . esc_url( $img ) . '" style="width:48px;height:48px;object-fit:cover;border-radius:6px"></a>' : '—' );
			printf( '<td><strong>%s</strong></td>', esc_html( $p->post_title ) );
			printf( '<td>%s<br><small>%s</small></td>', $vendor ? esc_html( $vendor->display_name ) : '—', $vendor ? esc_html( $vendor->user_email ) : '' );
			printf(
				'<td>%s%s</td>',
				$p->post_content ? esc_html( wp_trim_words( $p->post_content, 30 ) ) : '—',
				$link ? '<br><a href="' . esc_url( $link ) . '" target="_blank" style="direction:ltr;display:inline-block">🔗 لینک نمونه</a>' : ''
			);
			printf( '<td>%s</td>', esc_html( Dastyar_Jalali::day( $p->post_date ) ) );
			echo '<td colspan="3" style="min-width:330px">';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">';
			wp_nonce_field( 'dastyar_suggestion_save_' . $p->ID );
			echo '<input type="hidden" name="action" value="dastyar_suggestion_save">';
			printf( '<input type="hidden" name="post_id" value="%d">', (int) $p->ID );
			echo '<select name="sugg_status">';
			foreach ( Dastyar_Suggestions::statuses() as $slug => $label ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $slug ), selected( $status, $slug, false ), esc_html( $label ) );
			}
			echo '</select>';
			printf( '<input type="text" name="sugg_note" value="%s" placeholder="یادداشت برای فروشنده…" style="min-width:190px">', esc_attr( $note ) );
			echo '<button class="button button-primary">ذخیره</button></form>';
			printf( '<span style="display:inline-block;margin-top:4px;background:%s;color:#fff;border-radius:12px;padding:1px 10px;font-size:11px">وضعیت فعلی: %s</span>', esc_attr( Dastyar_Suggestions::status_color( $status ) ), esc_html( Dastyar_Suggestions::status_label( $status ) ) );
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/** ذخیره وضعیت/یادداشت پیشنهاد محصول + ایمیل اطلاع‌رسانی به فروشنده در صورت تغییر وضعیت */
	public function suggestion_save() {
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyar_suggestion_save_' . $post_id ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$post = get_post( $post_id );
		if ( $post && Dastyar_Suggestions::POST_TYPE === $post->post_type ) {
			$old_status = (string) get_post_meta( $post_id, Dastyar_Suggestions::STATUS, true ) ?: 'pending';
			$new_status = array_key_exists( (string) ( $_POST['sugg_status'] ?? '' ), Dastyar_Suggestions::statuses() ) ? sanitize_key( $_POST['sugg_status'] ) : 'pending';
			$note       = sanitize_text_field( wp_unslash( $_POST['sugg_note'] ?? '' ) );

			update_post_meta( $post_id, Dastyar_Suggestions::STATUS, $new_status );
			update_post_meta( $post_id, Dastyar_Suggestions::NOTE, $note );

			if ( $new_status !== $old_status ) {
				$vendor = get_userdata( (int) $post->post_author );
				if ( $vendor ) {
					wp_mail(
						$vendor->user_email,
						'به‌روزرسانی پیشنهاد محصول «' . $post->post_title . '»',
						sprintf(
							"سلام %s عزیز،\n\nوضعیت پیشنهاد محصول «%s» به «%s» تغییر کرد.%s\n\nپنل فروشنده: %s",
							$vendor->display_name,
							$post->post_title,
							Dastyar_Suggestions::status_label( $new_status ),
							$note ? "\nیادداشت مدیر: " . $note : '',
							wc_get_account_endpoint_url( 'dastyar-suggest' )
						)
					);
				}
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyar-suggestions&saved=1' ) );
		exit;
	}

	public function page_logs() {
		echo '<div class="wrap"><h1>لاگ درخواست‌های API</h1>';
		$logs = Dastyar::instance()->logger->get_logs( 200 );
		echo '<table class="widefat striped"><thead><tr><th>زمان</th><th>فروشنده</th><th>مسیر</th><th>متد</th><th>وضعیت</th><th>پیام</th><th>IP</th></tr></thead><tbody>';
		foreach ( $logs as $log ) {
			$user = $log->vendor_id ? get_userdata( $log->vendor_id ) : null;
			printf(
				'<tr><td>%s</td><td>%s</td><td style="direction:ltr;text-align:right">%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( Dastyar_Jalali::dt( (string) $log->created_at ) ),
				$user ? esc_html( $user->display_name ) : '—',
				esc_html( $log->route ),
				esc_html( $log->method ),
				esc_html( $log->status ),
				esc_html( $log->message ),
				esc_html( $log->ip )
			);
		}
		echo '</tbody></table></div>';
	}

	/** وضعیت سامانه + تنظیمات فرستنده (برای لیبل آدرس) */
	public function page_status() {
		echo '<div class="wrap"><h1>وضعیت و تنظیمات دستیار</h1><table class="widefat" style="max-width:800px">';
		printf( '<tr><th>نسخه افزونه</th><td>%s</td></tr>', esc_html( DASTYAR_CORE_VERSION ) );
		printf( '<tr><th>آدرس API</th><td><code>%s</code></td></tr>', esc_html( home_url( '/wp-json/dastyar/v1' ) ) );
		printf( '<tr><th>تعداد فروشندگان متصل</th><td>%d</td></tr>', count( Dastyar::instance()->vendors->connected_vendors() ) );
		printf( '<tr><th>Cron پردازش صف Push</th><td>%s</td></tr>', wp_next_scheduled( 'dastyar_push_process' ) ? esc_html( date( 'Y-m-d H:i', wp_next_scheduled( 'dastyar_push_process' ) ) ) : 'زمان‌بندی نشده!' );
		printf( '<tr><th>محصول شارژ کیف پول</th><td>#%d <span class="description">(اختیاری — شارژ کیف پول حتی بدون این محصول هم کار می‌کند)</span></td></tr>', (int) get_option( 'dastyar_charge_product_id' ) );
		echo '</table>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dastyar_flush' );
		echo '<input type="hidden" name="action" value="dastyar_flush">';
		echo '<p><button class="button">بازسازی پیوندهای یکتا (Rewrite Rules)</button></p></form>';

		// تنظیمات فرستنده برای لیبل آدرس
		echo '<hr><h2>اطلاعات فرستنده (روی لیبل آدرس چاپ می‌شود)</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dastyar_save_sender' );
		echo '<input type="hidden" name="action" value="dastyar_save_sender">';
		echo '<table class="form-table">';
		printf( '<tr><th><label>نام فرستنده</label></th><td><input type="text" name="dastyar_sender_name" class="regular-text" value="%s"></td></tr>', esc_attr( get_option( 'dastyar_sender_name', get_bloginfo( 'name' ) ) ) );
		printf( '<tr><th><label>آدرس فرستنده</label></th><td><textarea name="dastyar_sender_address" rows="2" class="large-text">%s</textarea></td></tr>', esc_textarea( get_option( 'dastyar_sender_address', $this->default_sender_address() ) ) );
		printf( '<tr><th><label>تلفن فرستنده</label></th><td><input type="text" name="dastyar_sender_phone" class="regular-text" value="%s" style="direction:ltr"></td></tr>', esc_attr( get_option( 'dastyar_sender_phone', '' ) ) );
		printf( '<tr><th><label>کدپستی فرستنده</label></th><td><input type="text" name="dastyar_sender_postcode" class="regular-text" value="%s" style="direction:ltr"></td></tr>', esc_attr( get_option( 'dastyar_sender_postcode', '' ) ) );
		echo '</table>';

		// تنظیمات عودت و مرجوعی (متن قوانین فقط همین‌جا توسط مدیر مرکز نوشته می‌شود و برای همه فروشنده‌ها ارسال می‌گردد)
		echo '<h2>قوانین عودت و مرجوعی</h2>';
		echo '<table class="form-table">';
		printf(
			'<tr><th><label>متن قوانین عودت</label></th><td><textarea name="dastyar_rma_rules" rows="5" class="large-text">%s</textarea><p class="description">این متن در ابتدای برگه عمومی «عودت و مرجوعی» <strong>همه فروشگاه‌های متصل</strong> به مشتریان نمایش داده می‌شود. با هر ذخیره، متن جدید به‌صورت خودکار برای فروشنده‌ها ارسال می‌شود.</p></td></tr>',
			esc_textarea( get_option( 'dastyar_rma_rules', '' ) )
		);
		printf(
			'<tr><th><label>آدرس مقصد مرجوعی (انبار)</label></th><td><textarea name="dastyar_return_address" rows="3" class="large-text">%s</textarea><p class="description">مثلاً آدرس انبار دستیار شاپ یا هر آدرس دیگری که کالای مرجوعی باید به آن برگردد. هر زمان وضعیت مرجوعی به «منتظر ارسال کالا» برسد، این آدرس به‌صورت خودکار به سایت فروشنده ارسال می‌شود تا کالا از مشتری به این نشانی عودت داده شود.</p></td></tr>',
			esc_textarea( get_option( 'dastyar_return_address', '' ) )
		);
		echo '</table>';

		// v1.9.8 (بازخورد کاربر) — بخش پیامک از این صفحه جدا و به صفحه مستقل «پیامک» منتقل شد
		echo '<h2>📱 پیامک</h2>';
		printf(
			'<div class="notice notice-info inline" style="max-width:840px;padding:8px 14px;margin:6px 0 14px"><p style="margin:0">تنظیمات سامانه پیامکی (انتخاب درگاه، اعتبار و کد الگوها) + ابزار تشخیص به صفحه مستقل <a href="%s"><strong>«پیامک»</strong></a> در منوی «دستیار شاپ» منتقل شد.</p></div>',
			esc_url( admin_url( 'admin.php?page=dastyar-sms' ) )
		);

		// ابزارک‌های پیشخوان دستیار (قابل فعال/غیرفعال)
		echo '<h2>ابزارک‌های صفحه پیشخوان</h2>';
		echo '<table class="form-table"><tr><th>ابزارک‌های دستیار شاپ</th><td>';
		foreach ( Dastyar_Dashboard::widgets() as $key => $label ) {
			printf(
				'<label style="display:block;margin-bottom:6px"><input type="checkbox" name="dastyar_widgets_enabled[%s]" value="1" %s> %s</label>',
				esc_attr( $key ),
				checked( Dastyar_Dashboard::enabled( $key ), true, false ),
				esc_html( $label )
			);
		}
		echo '<p class="description">این ابزارک‌ها با تم رنگی سازمانی در صفحه «پیشخوان وردپرس» نمایش داده می‌شوند.</p></td></tr>';

		// ظاهر و اطلاع‌رسانی (v1.5.0)
		echo '<tr><th>حالت تاریک سازمانی</th><td>';
		printf(
			'<label><input type="checkbox" name="dastyar_dark_mode" value="yes" %s> فعال — ابزارک‌های پیشخوان، منوی دستیار و سایدبار ارسال با پوسته تیره سازمانی نمایش داده شوند</label>',
			checked( get_option( 'dastyar_dark_mode', 'no' ), 'yes', false )
		);
		echo '</td></tr>';
		echo '<tr><th>هشدار ناموجودی</th><td>';
		printf(
			'<label><input type="checkbox" name="dastyar_oos_notify" value="yes" %s> با ناموجود شدن ناگهانی هر کالا در مرکز، ایمیل هشدار به مدیر سایت ارسال شود (برای هر کالا حداکثر یک‌بار در روز)</label>',
			checked( get_option( 'dastyar_oos_notify', 'yes' ), 'yes', false )
		);
		echo '</td></tr></table>';

		submit_button( 'ذخیره تنظیمات فرستنده و مرجوعی' );
		echo '</form>';

		echo '</div>';
	}

	/**
	 * v1.9.8 (بازخورد کاربر) — صفحه مستقل «پیامک» (زیرمنوی جدا، نه بخشی از وضعیت و تنظیمات):
	 * انتخاب درگاه (ملی پیامک پیش‌فرض / آی‌پی‌پنل) + اعتبار هر درگاه + کد الگوی ۶ رویداد + ابزار تشخیص
	 */
	public function page_sms() {
		if ( isset( $_GET['sms_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible" style="max-width:860px"><p>✔ تنظیمات پیامک ذخیره شد.</p></div>';
		}
		$sms = Dastyar_Sms::all();
		echo '<div class="wrap"><h1>📱 پیامک <small style="font-weight:400;color:#8a97a3;font-size:12px">(اطلاع‌رسانی پیامکی تأیید عضویت، نتیجه مرجوعی، کد رهگیری به مشتری، اتمام شارژ کیف پول و کد ورود)</small></h1>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dastyar_save_sms' );
		echo '<input type="hidden" name="action" value="dastyar_save_sms">';

		// ——— درگاه و اعتبار ———
		echo '<h2>درگاه پیامک</h2>';
		echo '<table class="form-table">';
		printf(
			'<tr><th>وضعیت سامانه</th><td><label><input type="checkbox" name="dastyar_sms[enabled]" value="yes" %s> فعال — ارسال پیامک کار کند</label>%s</td></tr>',
			checked( $sms['enabled'], 'yes', false ),
			'yes' === $sms['enabled'] ? ' <span style="color:#008a20;font-weight:700">✔ سرویس فعال است</span>' : ''
		);
		echo '<tr><th><label>درگاه پیامکی</label></th><td><select name="dastyar_sms[provider]">';
		printf( '<option value="melipayamak" %s>ملی پیامک (melipayamak.com) — خط خدماتی اشتراکی</option>', selected( (string) ( $sms['provider'] ?? 'melipayamak' ), 'melipayamak', false ) );
		printf( '<option value="ippanel" %s>آی‌پی‌پنل (ippanel.com)</option>', selected( (string) ( $sms['provider'] ?? 'melipayamak' ), 'ippanel', false ) );
		echo '</select><p class="description">فقط بخش مربوط به درگاه انتخاب‌شده را پر کنید؛ بخش درگاه دیگر می‌تواند خالی بماند.</p></td></tr>';

		echo '<tr><th colspan="2" style="padding-top:20px;font-size:13px"><strong>۱) تنظیمات ملی پیامک</strong> <span class="description">(melipayamak.com)</span></th></tr>';
		printf(
			'<tr><th><label>نام کاربری ملی پیامک</label></th><td><input type="text" name="dastyar_sms[mp_username]" value="%s" class="regular-text" style="direction:ltr" autocomplete="off"><p class="description">همان نام کاربری (شماره موبایل) ورود به سایت melipayamak.com</p></td></tr>',
			esc_attr( (string) ( $sms['mp_username'] ?? '' ) )
		);
		printf(
			'<tr><th><label>رمز عبور ملی پیامک</label></th><td><input type="password" name="dastyar_sms[mp_password]" value="%s" class="regular-text" style="direction:ltr" autocomplete="new-password"><p class="description">رمز ورود به پنل melipayamak.com</p></td></tr>',
			esc_attr( (string) ( $sms['mp_password'] ?? '' ) )
		);

		echo '<tr><th colspan="2" style="padding-top:20px;font-size:13px"><strong>۲) تنظیمات آی‌پی‌پنل</strong> <span class="description">(ippanel.com — فقط اگر این درگاه را انتخاب کرده‌اید)</span></th></tr>';
		printf(
			'<tr><th><label>کلید دسترسی (AccessKey)</label></th><td><input type="text" name="dastyar_sms[api_key]" value="%s" class="regular-text" style="direction:ltr" autocomplete="off"><p class="description">از پنل ippanel ← کلیدهای API کپی کنید.</p></td></tr>',
			esc_attr( (string) $sms['api_key'] )
		);
		printf(
			'<tr><th><label>شماره خط ارسال (sender)</label></th><td><input type="text" name="dastyar_sms[sender]" value="%s" class="regular-text" style="direction:ltr" placeholder="+983000505"><p class="description">خط اختصاصی یا خط اشتراکی انتخاب‌شده هنگام ساخت الگو (با +98).</p></td></tr>',
			esc_attr( (string) $sms['sender'] )
		);
		echo '</table>';

		// ——— الگوهای رویدادها ———
		echo '<h2>الگوهای رویدادها</h2>';
		echo '<div class="notice notice-info inline" style="max-width:860px;padding:8px 14px;margin:6px 0 14px"><p style="margin:0">برای هر رویداد یک «الگو» در پنل پیامک‌تان بسازید و کدش را این‌جا وارد کنید. در <strong>ملی پیامک</strong>: بخش «پیامک‌های متغیردار» ← ثبت متن با متغیرهای <code dir="ltr">{0} {1}</code> و… ← «کد متن» عددی (bodyId) را این‌جا بگذارید؛ مقدار <strong>سطر اول</strong> نگاشت به‌جای <code dir="ltr">{0}</code>، سطر دوم به‌جای <code dir="ltr">{1}</code> و… می‌نشیند. اگر برای رویدادی الگویی نگذارید، پیامک آن رویداد ارسال نمی‌شود.</p></div>';
		echo '<table class="form-table">';
		$events = array(
			'approved'   => array( 'تأیید عضویت فروشنده', 'متغیرهای معمول: name' ),
			'rejected'   => array( 'رد درخواست فروشندگی', 'متغیرهای معمول: name' ),
			'rma'        => array( 'نتیجه درخواست مرجوعی (تأیید/رد)', 'متغیرهای معمول: name, rma, status, result, credit' ),
			'tracking'   => array( 'کد رهگیری به مشتری سفارش', 'گیرنده: موبایل بیلینگ «مشتری» — متغیرها: name (نام مشتری)، track (کد رهگیری)، shop (نام فروشگاه فروشنده؛ در «انتهای» متن الگو بگذارید)، carrier (شرکت حمل)، order (شماره سفارش)' ),
			'wallet_low' => array( 'هشدار اتمام شارژ کیف پول', 'متغیرهای معمول: name, balance' ),
			'otp'        => array( 'کد ورود یک‌بارمصرف (OTP)', 'متغیرهای معمول: code' ),
		);
		foreach ( $events as $ev => $conf ) {
			printf(
				'<tr><th><label>کد الگوی «%s»</label></th><td><input type="text" name="dastyar_sms[pattern_%s]" value="%s" class="regular-text" style="direction:ltr" placeholder="%s"><p class="description">%s — نگاشت متغیرها (هر سطر: نام‌متغیر=منبع):</p><textarea name="dastyar_sms[vars_%s]" rows="2" class="large-text code" style="direction:ltr">%s</textarea></td></tr>',
				esc_html( $conf[0] ),
				esc_attr( $ev ),
				esc_attr( (string) $sms[ 'pattern_' . $ev ] ),
				'کد متن (bodyId) یا کد پترن',
				esc_html( $conf[1] ),
				esc_attr( $ev ),
				esc_textarea( (string) $sms[ 'vars_' . $ev ] )
			);
		}

		printf(
			'<tr><th>هشدار اتمام شارژ</th><td><label><input type="checkbox" name="dastyar_sms[low_balance_enabled]" value="yes" %s> فعال</label> — وقتی موجودی کیف پول از
			<input type="number" step="any" min="0" name="dastyar_sms[low_balance_threshold]" value="%s" style="width:130px;direction:ltr"> %s کمتر شود، پیامک هشدار ارسال شود (برای هر فروشنده حداکثر یک‌بار در روز)</td></tr>',
			checked( $sms['low_balance_enabled'], 'yes', false ),
			esc_attr( (string) $sms['low_balance_threshold'] ),
			esc_html( get_woocommerce_currency_symbol() )
		);
		echo '</table>';

		submit_button( 'ذخیره تنظیمات پیامک' );
		echo '</form>';

		// ابزارهای تشخیص (ارسال تست + آخرین رویدادها + راهنما)
		$this->render_sms_tools();

		echo '</div>';
	}

	/**
	 * v1.9.7 — ابزارهای تشخیص سامانه پیامکی: ارسال تست + آخرین رویدادها + راهنمای عیب‌یابی
	 * (تا مدیر بدون خواندن لاگ بفهمد «چرا پیامک نرفت»)
	 */
	protected function render_sms_tools() {
		echo '<hr><h2 id="dastyar-sms-tools">🧪 ابزار تشخیص پیامک <small style="font-weight:400;color:#8a97a3;font-size:12px">(تست ارسال واقعی + گزارش آخرین ارسال‌ها و دلیلِ ارسال‌نشدن‌ها)</small></h2>';

		if ( isset( $_GET['sms_test'] ) ) {
			$st = sanitize_key( $_GET['sms_test'] );
			if ( 'ok' === $st ) {
				echo '<div class="notice notice-success is-dismissible" style="max-width:860px"><p>✔ پیامک تست ارسال شد — به گیرنده نگاه کن؛ اگر نرسید، گزارش‌های پنل پیامک را بررسی کن.</p></div>';
			} else {
				$msg = isset( $_GET['sms_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['sms_msg'] ) ) : 'ارسال ناموفق بود.';
				printf( '<div class="notice notice-error is-dismissible" style="max-width:860px"><p>✖ ارسال تست ناموفق: %s</p></div>', esc_html( $msg ) );
			}
		}

		echo '<div style="max-width:860px;background:#fff;border:1.5px solid #e3ebe7;border-radius:14px;padding:16px 20px;margin-bottom:16px">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
		wp_nonce_field( 'dastyar_sms_test' );
		echo '<input type="hidden" name="action" value="dastyar_sms_test">';
		echo '<strong>ارسال پیامک تستی:</strong> رویداد ';
		echo '<select name="event">';
		$test_events = array(
			'approved'   => 'تأیید عضویت فروشنده',
			'rejected'   => 'رد درخواست فروشندگی',
			'rma'        => 'نتیجه مرجوعی',
			'tracking'   => 'کد رهگیری به مشتری',
			'wallet_low' => 'هشدار اتمام شارژ',
			'otp'        => 'کد ورود (OTP)',
		);
		foreach ( $test_events as $ev => $label ) {
			printf( '<option value="%s">%s</option>', esc_attr( $ev ), esc_html( $label ) );
		}
		echo '</select>';
		echo ' به موبایل <input type="text" name="mobile" placeholder="0912…" style="direction:ltr;width:150px" required>';
		echo '<button class="button button-primary" style="background:#17a16d;border-color:#17a16d;border-radius:9px">ارسال تست</button>';
		echo '<span class="description">متغیرها با داده نمونه پر می‌شوند؛ نتیجه دقیق (خطای API) همین‌جا نمایش داده می‌شود.</span>';
		echo '</form></div>';

		// آخرین رویدادها
		$log = Dastyar_Sms::events_log();
		echo '<div style="max-width:860px;background:#fff;border:1.5px solid #e3ebe7;border-radius:14px;padding:16px 20px;margin-bottom:16px">';
		echo '<h3 style="margin:0 0 10px;font-size:14px">آخرین پیامک‌ها (۱۰ مورد اخیر)</h3>';
		if ( ! $log ) {
			echo '<p class="description" style="margin:0">هنوز هیچ رویدادی ثبت نشده است. یک «ارسال تست» بزنید یا رویدادی (مثلاً تأیید فروشنده) را اجرا کنید.</p>';
		} else {
			echo '<table class="widefat striped" style="margin:0"><thead><tr><th>زمان</th><th>نتیجه</th><th>رویداد/الگو</th><th>گیرنده</th><th>توضیح</th></tr></thead><tbody>';
			foreach ( array_reverse( $log ) as $row ) {
				$kind  = (string) ( $row['kind'] ?? '' );
				$badge = 'ok' === $kind
					? '<span style="color:#0f7a3d;font-weight:800">✔ ارسال شد</span>'
					: ( 'skip' === $kind
						? '<span style="color:#a15c07;font-weight:800">⊘ ارسال نشد</span>'
						: '<span style="color:#b02a37;font-weight:800">✖ خطا</span>' );
				printf(
					'<tr><td style="white-space:nowrap">%s</td><td>%s</td><td><code>%s</code></td><td style="direction:ltr;text-align:right">%s</td><td>%s</td></tr>',
					esc_html( (string) ( $row['t'] ?? '' ) ),
					wp_kses_post( $badge ),
					esc_html( (string) ( $row['event'] ?? '—' ) ),
					esc_html( (string) ( $row['target'] ?? '—' ) ),
					esc_html( (string) ( $row['msg'] ?? '—' ) )
				);
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		// راهنمای عیب‌یابی سریع
		echo '<div class="notice notice-info inline" style="max-width:860px;padding:10px 14px"><p style="margin:0 0 6px"><strong>اگر پیامک نمی‌رسد، این ۵ مورد را به ترتیب چک کنید:</strong></p><ol style="margin:0;padding-right:20px;line-height:2">';
		echo '<li>در پنل پیامک‌تان وضعیت هر الگو باید «<strong>تأیید شده/فعال</strong>» باشد — ملی پیامک: «پیامک‌های متغیردار»، آی‌پی‌پنل: «مدیریت الگوها» (الگوی تازه‌ساخته‌شده معمولاً چند دقیقه تا چند ساعت در انتظار تأیید است).</li>';
		echo '<li>«درگاه» درست انتخاب شده + اعتبار همان درگاه صحیح باشد (ملی پیامک: نام کاربری/رمز ورود پنل — آی‌پی‌پنل: AccessKey + خط <code dir="ltr">+98…</code>) + شارژ پنل کافی.</li>';
		echo '<li>ردیف رویدادِ موردنظر «کد الگو» داشته باشد (خالی = هیچ ارسالی انجام نمی‌شود)؛ در ملی پیامک کد متن یک «عدد» (bodyId) است و ترتیب سطرهای نگاشت با {0}،{1}،… الگو یکی باشد.</li>';
		echo '<li>پیامک «تأیید/رد فروشنده» فقط <strong>لحظهٔ کلیک روی تأیید/رد</strong> می‌رود — برای فروشنده‌ای که قبلاً تأیید کرده‌اید، در صفحه «فروشندگان» دکمه «📨 پیامک تأیید» را بزنید.</li>';
		echo '<li>جزئیات دقیق هر ارسال/خطا در جدول «آخرین پیامک‌ها» بالای همین بخش ثبت می‌شود.</li>';
		echo '</ol></div>';
	}

	/** v1.9.7 — هندلر ارسال پیامک تستی */
	public function sms_test() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyar_sms_test' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$ev     = sanitize_key( $_POST['event'] ?? '' );
		$mobile = sanitize_text_field( wp_unslash( $_POST['mobile'] ?? '' ) );
		$valid  = array( 'approved', 'rejected', 'rma', 'wallet_low', 'otp', 'tracking' );
		$back   = 'admin.php?page=dastyar-sms'; // v1.9.8 — صفحه مستقل پیامک
		if ( ! in_array( $ev, $valid, true ) || '' === trim( $mobile ) ) {
			wp_safe_redirect( admin_url( $back . '&sms_test=fail&sms_msg=' . rawurlencode( 'رویداد یا شماره موبایل نامعتبر است.' ) ) );
			exit;
		}
		$pattern = (string) Dastyar_Sms::get( 'pattern_' . $ev, '' );
		if ( '' === trim( $pattern ) ) {
			wp_safe_redirect( admin_url( $back . '&sms_test=fail&sms_msg=' . rawurlencode( 'برای این رویداد «کد الگو» وارد نشده است — ابتدا در همان ردیف، کد الگو را ذخیره کنید.' ) ) );
			exit;
		}
		// داده نمونه برای همه متغیرهای احتمالی الگو
		$ctx = array(
			'name'    => 'کاربر تست',
			'rma_id'  => '12',
			'status'  => 'تأیید شده',
			'result'  => 'تأیید شده',
			'credit'  => '۱۰۰٬۰۰۰ تومان',
			'balance' => '۵۰٬۰۰۰ تومان',
			'code'    => '54321',
			'track'   => 'TEST-12345',
			'shop'    => (string) get_option( 'blogname' ),
			'order'   => '1001',
			'carrier' => 'پست',
		);
		$r = Dastyar_Sms::send_pattern( $mobile, $pattern, $ctx, (string) Dastyar_Sms::get( 'vars_' . $ev, '' ) );
		if ( true === $r ) {
			wp_safe_redirect( admin_url( $back . '&sms_test=ok&ev=' . rawurlencode( $ev ) . '#dastyar-sms-tools' ) );
			exit;
		}
		$msg = is_wp_error( $r ) ? $r->get_error_message() : 'ارسال ناموفق بود.';
		wp_safe_redirect( admin_url( $back . '&sms_test=fail&sms_msg=' . rawurlencode( $msg ) . '#dastyar-sms-tools' ) );
		exit;
	}

	/** v1.9.7 — ارسال مجدد پیامک «تأیید عضویت» برای فروشنده‌ای که پیش از پیکربندی پیامک تأیید شده بود */
	public function vendor_sms_resend() {
		$uid = (int) ( $_GET['uid'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyar_vendor_sms_resend_' . $uid ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$user = get_userdata( $uid );
		$flag = 'bad';
		if ( $user && in_array( 'dastyar_vendor', (array) $user->roles, true ) ) {
			do_action( 'dastyar_vendor_approved', $uid, $user ); // همان رویداد اصلی ← پیامک تأیید دوباره‌کار می‌شود
			$flag = 'resent';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyar-vendors&sms_resend=' . $flag ) );
		exit;
	}

	public function save_sender() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyar_save_sender' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		update_option( 'dastyar_sender_name', sanitize_text_field( wp_unslash( $_POST['dastyar_sender_name'] ?? '' ) ) );
		update_option( 'dastyar_sender_address', sanitize_textarea_field( wp_unslash( $_POST['dastyar_sender_address'] ?? '' ) ) );
		update_option( 'dastyar_sender_phone', sanitize_text_field( wp_unslash( $_POST['dastyar_sender_phone'] ?? '' ) ) );
		update_option( 'dastyar_sender_postcode', sanitize_text_field( wp_unslash( $_POST['dastyar_sender_postcode'] ?? '' ) ) );
		update_option( 'dastyar_return_address', sanitize_textarea_field( wp_unslash( $_POST['dastyar_return_address'] ?? '' ) ) );

		// قوانین عودت + ابزارک‌ها
		update_option( 'dastyar_rma_rules', wp_kses_post( wp_unslash( $_POST['dastyar_rma_rules'] ?? '' ) ) );
		$widgets = array();
		foreach ( array_keys( Dastyar_Dashboard::widgets() ) as $key ) {
			$widgets[ $key ] = ! empty( $_POST['dastyar_widgets_enabled'][ $key ] ) ? 1 : 0;
		}
		update_option( Dastyar_Dashboard::OPTION, $widgets );

		// ظاهر و اطلاع‌رسانی (v1.5.0)
		update_option( 'dastyar_dark_mode', ! empty( $_POST['dastyar_dark_mode'] ) ? 'yes' : 'no' );
		update_option( 'dastyar_oos_notify', ! empty( $_POST['dastyar_oos_notify'] ) ? 'yes' : 'no' );

		// v1.9.8 — سامانه پیامکی از این فرم جدا شد و در صفحه مستقل «پیامک» ذخیره می‌شود (save_sms)

		// بازنشر غیرمسدودکننده قوانین + آدرس انبار برای همه فروشندگان متصل (از همگام‌بودن مطمئن می‌شود)
		Dastyar::instance()->webhooks->push_rma_rules();

		wp_safe_redirect( admin_url( 'admin.php?page=dastyar-status&sender_saved=1' ) );
		exit;
	}

	/** v1.9.8 — ذخیره تنظیمات صفحه مستقل «پیامک» (درگاه + اعتبار + الگوهای ۶ رویداد) */
	public function save_sms() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyar_save_sms' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$in       = (array) wp_unslash( $_POST['dastyar_sms'] ?? array() ); // phpcs:ignore
		$provider = in_array( (string) ( $in['provider'] ?? '' ), array( 'melipayamak', 'ippanel' ), true ) ? (string) $in['provider'] : 'melipayamak';
		$new      = array(
			'provider'              => $provider,
			'enabled'               => ! empty( $in['enabled'] ) ? 'yes' : 'no',
			'mp_username'           => sanitize_text_field( (string) ( $in['mp_username'] ?? '' ) ),
			'mp_password'           => sanitize_text_field( (string) ( $in['mp_password'] ?? '' ) ),
			'api_key'               => sanitize_text_field( (string) ( $in['api_key'] ?? '' ) ),
			'sender'                => sanitize_text_field( (string) ( $in['sender'] ?? '' ) ),
			'low_balance_enabled'   => ! empty( $in['low_balance_enabled'] ) ? 'yes' : 'no',
			'low_balance_threshold' => max( 0, (float) ( $in['low_balance_threshold'] ?? 2000000 ) ),
		);
		foreach ( array( 'approved', 'rejected', 'rma', 'wallet_low', 'otp', 'tracking' ) as $ev ) {
			$new[ 'pattern_' . $ev ] = sanitize_text_field( (string) ( $in[ 'pattern_' . $ev ] ?? '' ) );
			// نگاشت متغیرها فقط «نام=منبع» امن؛ اجازه کد نمی‌دهیم
			$lines = array();
			foreach ( preg_split( '/\\r?\\n/', (string) ( $in[ 'vars_' . $ev ] ?? '' ) ) as $line ) {
				$line = sanitize_text_field( $line );
				if ( '' !== $line && preg_match( '/^[\\p{L}\\p{N}_-]+\\s*(=\\s*[%\\p{L}\\p{N}_-]+)?$/u', $line ) ) {
					$lines[] = $line;
				}
			}
			$new[ 'vars_' . $ev ] = implode( "\n", $lines );
		}
		update_option( Dastyar_Sms::OPTION, array_merge( Dastyar_Sms::all(), $new ) );
		wp_safe_redirect( admin_url( 'admin.php?page=dastyar-sms&sms_saved=1' ) );
		exit;
	}

	public function flush() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyar_flush' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		Dastyar_MyAccount::add_endpoints();
		flush_rewrite_rules();
		wp_safe_redirect( admin_url( 'admin.php?page=dastyar-status&flushed=1' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * پروفایل کاربر — کیف پول، کلید API، سایت، لوگو، رده
	 * ---------------------------------------------------------------- */

	public function user_fields( $user ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$vendors = Dastyar::instance()->vendors;
		echo '<h2>دستیار شاپ</h2><table class="form-table">';
		printf(
			'<tr><th><label>موجودی کیف پول</label></th><td><input type="number" step="any" name="dastyar_wallet" value="%s"> <span class="description">مقدار جدید را وارد کنید؛ تفاضل به‌صورت تراکنش ثبت می‌شود.</span></td></tr>',
			esc_attr( (float) get_user_meta( $user->ID, Dastyar_Vendors::WALLET, true ) )
		);
		printf( '<tr><th>کلید API</th><td><code>%s</code> <label><input type="checkbox" name="dastyar_regen_key" value="1"> تولید کلید جدید (کلید قبلی باطل می‌شود؛ کلید جدید در اعلان بالا نمایش داده می‌شود)</label></td></tr>', esc_html( $vendors->hint( $user->ID ) ?: '—' ) );
		printf( '<tr><th>آدرس سایت فروشنده</th><td><code>%s</code></td></tr>', esc_html( $vendors->site_url( $user->ID ) ?: '—' ) );

		// رده فروشنده (محرمانه — v1.5.0)
		$tiers = Dastyar_Tiers::all();
		echo '<tr><th><label>رده فروشنده</label></th><td>';
		if ( $tiers ) {
			echo '<select name="dastyar_tier">';
			echo '<option value="">بدون رده — همه محصولات</option>';
			foreach ( $tiers as $t ) {
				printf(
					'<option value="%s" %s>%s (%d محصول)</option>',
					esc_attr( $t['id'] ),
					selected( Dastyar_Tiers::tier_id_of( $user->ID ), $t['id'], false ),
					esc_html( $t['name'] ),
					count( $t['products'] )
				);
			}
			echo '</select>';
		} else {
			echo '<span class="description">هنوز رده‌ای ساخته نشده — از «دستیار شاپ ← فروشندگان» بسازید.</span>';
		}
		echo '<p class="description">محرمانه است؛ فروشنده رده خودش را نمی‌بیند. فقط محصولات رده در API آن سایت دیده می‌شود.</p></td></tr>';

		// لوگوی فروشنده (برای لیبل آدرس) — آپلود از My Account فروشنده انجام می‌شود
		$logo_id  = (int) get_user_meta( $user->ID, '_dastyar_logo_id', true );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		echo '<tr><th>لوگوی فروشنده</th><td>';
		if ( $logo_url ) {
			printf( '<img src="%s" style="max-height:70px;display:block;margin-bottom:6px;border:1px solid #ddd;padding:4px"> ', esc_url( $logo_url ) );
			echo '<label><input type="checkbox" name="dastyar_remove_logo" value="1"> حذف لوگو</label>';
		} else {
			echo '<span class="description">فروشنده از پنل My Account خود (بخش «حساب کاربری») می‌تواند لوگو بارگذاری کند.</span>';
		}
		echo '</td></tr>';
		echo '</table>';
	}

	public function save_user_fields( $uid ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( isset( $_POST['dastyar_wallet'] ) ) {
			$new  = (float) $_POST['dastyar_wallet'];
			$old  = (float) get_user_meta( $uid, Dastyar_Vendors::WALLET, true );
			$diff = $new - $old;
			if ( abs( $diff ) > 0.0001 ) {
				if ( $diff > 0 ) {
					Dastyar::instance()->wallet->credit( $uid, $diff, 0, 'تنظیم دستی توسط مدیر' );
				} else {
					Dastyar::instance()->wallet->debit( $uid, abs( $diff ), 0, 'تنظیم دستی توسط مدیر' );
				}
			}
		}
		if ( ! empty( $_POST['dastyar_regen_key'] ) ) {
			$key = Dastyar::instance()->vendors->generate_key( $uid );
			add_action( 'admin_notices', function () use ( $key ) {
				printf( '<div class="notice notice-success"><p>کلید API جدید فروشنده (فقط همین یک‌بار نمایش داده می‌شود): <code style="direction:ltr">%s</code></p></div>', esc_html( $key ) );
			} );
		}
		if ( ! empty( $_POST['dastyar_remove_logo'] ) ) {
			delete_user_meta( $uid, '_dastyar_logo_id' );
		}
		// رده فروشنده (v1.5.0)
		if ( isset( $_POST['dastyar_tier'] ) ) {
			Dastyar_Tiers::set_vendor_tier( $uid, sanitize_key( wp_unslash( $_POST['dastyar_tier'] ) ) );
		}
	}
}
