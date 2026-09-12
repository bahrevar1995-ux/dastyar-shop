<?php
/**
 * پیشخوان سایت فروشنده:
 * - منوی مستقل «دستیار شاپ» (زیر «پیشخوان»، جدای از WooCommerce): محصولات دستیار + تنظیمات دستیار
 * - «محصولات دستیار»: افزودن تکی و دسته‌جمعی با انتخاب وضعیت (پیش‌نویس/منتشر شده) در همان صفحه
 * - ستون دوآیکونه «دستیار» در لیست سفارش‌ها (✈ ارسال + 📮 رهگیری — بدون ستاره، ترازشده) + اقدام دسته‌جمعی
 * - سینک دسته‌جمعی محصولات (اقدام گروهی در لیست محصولات ووکامرس) + سینک کامل از ابزارک پیشخوان (v1.5.0)
 * - متاباکس «دستیار شاپ» در صفحه سفارش + کپی کد رهگیری (v1.5.0) + درخواست مرجوعی
 * - نشان «ارسال از انبار بندرگناوه» (بالای عنوان محصول — v1.7.0) + حالت تاریک سازمانی (قابل تنظیم — v1.5.0)
 * - صفحه «کیف پول» در پیشخوان خود فروشنده: مشاهده موجودی/تراکنش‌ها + شارژ از طریق مرکز (v1.7.0)
 * - صفحه «آمار» در منوی دستیار: فعالیت‌های فروشگاه یک‌جا (جایگزین ابزارک‌های پیشخوان وردپرس — v1.7.0)
 * - فیلدهای Override قیمت‌گذاری در صفحه ویرایش محصولِ دستیاری
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Admin {

	const SNAPSHOT = 'dastyarc_products_snapshot';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		// (v1.10.0 — Command Center) ریدایرکت/باونس اسلاگ‌های قدیمی حذف شد؛ فقط یک صفحه: dastyarc-hub
		add_filter( 'admin_body_class', array( $this, 'hub_body_class' ) );
		add_action( 'admin_post_dastyarc_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_dastyarc_bulk_import', array( $this, 'handle_bulk_import' ) );
		add_action( 'admin_post_dastyarc_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_dastyarc_cancel_order', array( $this, 'handle_cancel_order' ) ); // v1.8.0 — لغو سفارش متصل
		add_action( 'admin_post_dastyarc_test', array( $this, 'handle_test' ) );
		add_action( 'admin_post_dastyarc_resync', array( $this, 'handle_resync' ) );
		add_action( 'admin_post_dastyarc_refund', array( $this, 'handle_refund' ) );
		add_action( 'admin_post_dastyarc_self_track', array( $this, 'handle_self_track' ) ); // v1.6.0 — کد رهگیری دستی سفارش
		add_action( 'admin_post_dastyarc_bulk_edit', array( $this, 'handle_bulk_edit' ) ); // v1.6.0 — ویرایش دسته‌جمعی قیمت/موجودی
		add_action( 'admin_post_dastyarc_apply_prices', array( $this, 'handle_apply_prices' ) ); // v1.6.0 — اعمال دستی فرمول قیمت
		add_action( 'admin_post_dastyarc_stock_sync_now', array( $this, 'handle_stock_sync_now' ) );
		// (v1.7.8 — بازخورد کاربر) خروج موقت محصول از دراپ‌شیپینگ (ارسال از انبار خود فروشنده) + اتصال مجدد به پلتفرم
		add_action( 'admin_post_dastyarc_detach', array( $this, 'handle_detach' ) );
		add_action( 'admin_post_dastyarc_reattach', array( $this, 'handle_reattach' ) );
		add_action( 'admin_post_dastyarc_rules_refresh', array( $this, 'handle_rules_refresh' ) );
		// سینک کامل موجودی از ابزارک/صفحه آمار (v1.5.0)
		add_action( 'admin_post_dastyarc_full_sync', array( $this, 'handle_full_sync' ) );
		// شارژ کیف پول از صفحه «کیف پول» در پیشخوان خود فروشنده (v1.7.0)
		add_action( 'admin_post_dastyarc_wallet_charge', array( $this, 'handle_wallet_charge' ) );

		// ثبت تیکت پشتیبانی از پیشخوان خود فروشنده (v1.7.5 — مورد ۹)
		add_action( 'admin_post_dastyarc_ticket_submit', array( $this, 'handle_ticket_submit' ) );

		// نشانگر وضعیت اتصال «دستیار فعال/غیرفعال» در نوار بالای پیشخوان (v1.7.5 — مورد ۴)
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_status' ), 90 );

		// متاباکس سفارش
		add_action( 'add_meta_boxes', array( $this, 'order_metabox' ) );
		// (v1.7.10 — رفع باگ 503 گزارش‌شده) قبل از 1.7.10 این متد روی woocommerce_update_order بود و داخلش
		// $order->save() صدا می‌زد ← همان هوک دوباره اجرا و حلقه بی‌نهایت می‌ساخت ← ازدحام/خستگی PHP ← 503 سرور.
		// اصلاح: روی woocommerce_before_order_object_save سوار است؛ یعنی متاها با «همان یک ذخیره‌ی درجریان» سفارش نوشته می‌شوند
		// بدون هیچ save() اضافه‌ای (کلاسیک + HPOS، WC ≥ 3). بازگشتی به‌ساختار غیرممکن است.
		add_action( 'woocommerce_before_order_object_save', array( $this, 'save_self_track' ), 20 );
		// (v1.9.2) سفارش ترکیبی: ذخیره کد رهگیری «بخش فروشنده» در متاباکس + دکمه «تکمیل بخش فروشنده»
		add_action( 'woocommerce_before_order_object_save', array( $this, 'save_mixed_vendor' ), 20 );
		add_action( 'admin_post_dastyarc_vendor_done', array( $this, 'handle_vendor_done' ) );

		// ستون «دستیار» در لیست سفارش‌ها (کلاسیک + HPOS)
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'order_columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'order_column_legacy' ), 10, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'order_columns' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'order_column_hpos' ), 10, 2 );

		// اقدام دسته‌جمعی «ارسال به دستیار شاپ» (کلاسیک + HPOS)
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'bulk_actions' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'bulk_send' ), 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'bulk_send' ), 10, 3 );

		// اقدام دسته‌جمعی «سینک موجودی از دستیار» در لیست محصولات ووکامرس (v1.5.0)
		add_filter( 'bulk_actions-edit-product', array( $this, 'product_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( $this, 'product_bulk_sync' ), 10, 3 );

		// فیلتر «محصولات دستیارشاپ» در لیست محصولات ووکامرس فروشنده (v1.6.0 — مورد ۹)
		add_action( 'restrict_manage_posts', array( $this, 'products_source_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'products_source_query' ) );

		// تب/فیلتر «سفارش‌های دستیار ★» در بالای لیست سفارش‌ها (کلاسیک + HPOS)
		add_filter( 'views_edit-shop_order', array( $this, 'orders_view' ) );
		add_filter( 'views_woocommerce_page_wc-orders', array( $this, 'orders_view' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_orders_query' ) );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( $this, 'filter_orders_args' ) );

		// فیلدهای Override قیمت در محصول دستیاری
		add_action( 'woocommerce_product_options_pricing', array( $this, 'product_price_override_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_price_override' ) );

		add_action( 'admin_notices', array( $this, 'notices' ) );
		// (v1.7.11) اعلان زنده از مرکز — ماژول‌های ۸/۴۰ کنسول مدیریت مرکز (فقط داخل صفحه‌های دستیار نمایش داده می‌شود)
		add_action( 'admin_notices', array( $this, 'notice_banner' ), 5 );

		// استایل برند منوی «دستیار شاپ» در پیشخوان
		add_action( 'admin_head', array( $this, 'menu_brand_css' ) );
	}

	/* ------------------------------------------------------------------
	 * منوی مستقل دستیار شاپ (جدای از WooCommerce)
	 * ---------------------------------------------------------------- */

	public function menu() {
		// حباب اعلان: تعداد گزارش‌های عودت جدیدی که هنوز برای مرکز ارسال نشده‌اند
		$new_rmas = DastyarC_Rma::pending_bubble_count();
		$bubble   = $new_rmas > 0 ? ' <span class="awaiting-mod count-' . (int) $new_rmas . '"><span class="pending-count">' . (int) $new_rmas . '</span></span>' : '';

		// (v1.9.0 — بازطراحی بصری) همه ۸ صفحه قبلی در یک «پنل واحد» تب‌دار جمع شد: dastyarc-hub.
		// موقعیت ۳ = بلافاصله زیر «پیشخوان» در منوی وردپرس (v1.5.0)
		// (v1.9.1 — بازخورد کاربر) آیکون منو از دش‌آیکون شبکه به «جعبه مینیمال» برند تغییر کرد
		$menu_icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/></svg>' );
		add_menu_page(
			'دستیار شاپ',
			'دستیار شاپ' . $bubble . ' <span class="dastyar-live-dot" aria-hidden="true"></span>',
			'manage_woocommerce',
			'dastyarc-hub',
			array( $this, 'page_hub' ),
			$menu_icon,
			3
		);

		// (v1.10.0 — Command Center) زیرمنوهای باونسی قدیمی حذف شدند؛ یک صفحه واحد با ۷ تب کافی است
	}

	/**
	 * (v1.7.5 — بازخورد کاربر، مورد ۳) تب «دستیار شاپ» دیگر اصلاً رنگی نیست (نه سبز، نه تیره سازمانی)؛
	 * مثل بقیه منوهای وردپرس بی‌رنگ است و فقط یک «نقطه سبز موج‌دار» کنار نامش وضعیت زنده بودن را نشان می‌دهد.
	 * (v1.7.5 — مورد ۴) استایل نشانگر «دستیار فعال/غیرفعال» در نوار ابزار بالای پیشخوان هم همین‌جا چاپ می‌شود.
	 */
	public function menu_brand_css() {
		// (v1.7.7 — بازخورد کاربر) صرف‌نظر از کاشی لوگو؛ برگشت به حالت ساده: فقط نقطه سبز موج‌دار کنار نام
		echo '<style>' .
			'.dastyar-live-dot{display:inline-block;width:8px;height:8px;margin-inline-start:7px;vertical-align:middle;border-radius:50%;background:#17a16d;box-shadow:0 0 0 0 rgba(23,161,109,.55);animation:dastyarLivePulse 1.8s ease-out infinite}' .
			'@keyframes dastyarLivePulse{0%{box-shadow:0 0 0 0 rgba(23,161,109,.55)}70%{box-shadow:0 0 0 9px rgba(23,161,109,0)}100%{box-shadow:0 0 0 0 rgba(23,161,109,0)}}' .
			'#wpadminbar .dastyarc-abs{display:inline-flex;align-items:center;gap:6px;line-height:1}' .
			'#wpadminbar .dastyarc-abs i{width:8px;height:8px;border-radius:50%;flex:none}' .
			'#wpadminbar .dastyarc-abs.on i{background:#2fce96;box-shadow:0 0 0 0 rgba(47,206,150,.55);animation:dastyarLivePulse 1.8s ease-out infinite}' .
			'#wpadminbar .dastyarc-abs.off i{background:#ef4444}' .
			'#wpadminbar .dastyarc-abs.on{color:#8fe8c4}' .
			'#wpadminbar .dastyarc-abs.off{color:#fca5a5}' .
			'</style>';
	}

	/** وضعیت اتصال به مرکز — با کش ۱۲۰ ثانیه‌ای تا هر بارگذاری پیشخوان به مرکز ضربه نزند (قابل تست جداگانه) */
	public static function connection_state() {
		if ( ! DastyarC_Client::configured() ) {
			return 'off';
		}
		$cache = get_transient( 'dastyarc_conn_state' );
		if ( false !== $cache ) {
			return '1' === $cache ? 'on' : 'off';
		}
		$ok = ! is_wp_error( DastyarC_Client::ping() );
		set_transient( 'dastyarc_conn_state', $ok ? '1' : '0', 120 );
		return $ok ? 'on' : 'off';
	}

	/** (v1.7.5 — مورد ۴) نشانگر «دستیار فعال/غیرفعال» در نوار بالای پیشخوان، کنار نام کاربر */
	public function admin_bar_status( $bar ) {
		if ( ! is_object( $bar ) || ! method_exists( $bar, 'add_node' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$on = 'on' === self::connection_state();
		$bar->add_node( array(
			'id'     => 'dastyarc-conn-status',
			'parent' => 'top-secondary',
			'title'  => '<span class="dastyarc-abs ' . ( $on ? 'on' : 'off' ) . '"><i></i>' . ( $on ? 'دستیار فعال' : 'دستیار غیرفعال' ) . '</span>',
			'href'   => admin_url( 'admin.php?page=dastyarc-hub&tab=settings' ),
			'meta'   => array( 'title' => $on ? 'اتصال به مرکز برقرار است' : 'اتصال برقرار نیست — تنظیمات دستیار را بررسی کنید' ),
		) );
	}

	/** پنل عودت و مرجوعی (پیاده‌سازی در DastyarC_Rma) */
	/**
	 * (v1.10.0 — Command Center) تب «عودت و مرجوعی» — کلاس RMA با مارک‌اپ بومی (widefat/form-table) الگوی dcx2 را به‌ارث می‌برد
	 */
	protected function tab_rma() {
		DastyarC::instance()->rma->page_admin();
	}

	/** (v1.10.0) تب «تنظیمات قدیمی» حذف شد — پیکربندی در ۴ آکوردئون داخل tab_settings است */
	public function page_settings() {
		$this->tab_settings();
	}

	/* ------------------------------------------------------------------
	 * صفحه «محصولات دستیار» — لیست از WooCommerce سایت مرکزی + افزودن تکی/دسته‌جمعی
	 * ---------------------------------------------------------------- */

	/** (v1.10.0 — Command Center) تب «محصولات دستیار» — بازنویسی کامل مارک‌اپ با سیستم dcx2 (قرارداد داده/فرم‌ها/نوشته‌ها عیناً حفظ شد) */
	protected function tab_products() {
		if ( ! DastyarC_Client::configured() ) {
			echo '<div class="dcx2-warn">ابتدا اتصال به مرکز را کامل کنید.<a href="' . esc_url( self::hub_url( 'settings' ) ) . '">برو به تنظیمات ←</a></div>';
			DastyarC_Ui::empty_state( 'محصولات دستیار پس از اتصال به مرکز، این‌جا نمایش داده می‌شوند.' );
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

		$result = DastyarC_Client::products( $query );
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
					<b style="font-size:12.5px"><?php echo DastyarC_Ui::icon( 'plus', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> افزودن دسته‌جمعی:</b>
					<label style="display:flex;align-items:center;gap:6px;font-size:11.5px;color:var(--mut);font-weight:700">وضعیت پس از افزودن:
						<select name="import_status" id="dastyarc-import-status">
							<option value="draft" <?php selected( $pref_status, 'draft' ); ?>>پیش‌نویس — بعداً خودتان منتشر می‌کنید</option>
							<option value="publish" <?php selected( $pref_status, 'publish' ); ?>>منتشر شده — بلافاصله نمایش داده شود</option>
						</select>
					</label>
					<button class="dcx2-btn prime" type="submit">افزودن محصولات انتخاب‌شده به فروشگاه</button>
					<small style="width:100%;font-size:10.5px;color:var(--mut)">تیک محصولات موردنظر را بزنید؛ این انتخاب روی دکمه «افزودن به فروشگاه» تک‌تک محصولات هم اعمال می‌شود. برای سینک موجودی چند محصولِ از قبل افزوده‌شده، از لیست «محصولات» ووکامرس اقدام گروهی «سینک موجودی از دستیار» را بزنید.</small>
				</div>
			</section>

			<!-- جدول محصولات -->
			<section class="dcx2-card" style="overflow-x:auto">
				<table class="dcx2-table">
					<thead><tr>
						<td style="width:36px"><input type="checkbox" id="dastyarc-select-all" title="انتخاب همه"></td>
						<th style="width:52px">تصویر</th><th>عنوان</th><th>SKU</th><th>نوع</th><th>موجودی مرکز</th><th>قیمت تامین</th><th>قیمت شما</th><th>وضعیت</th><th>اقدام</th>
					</tr></thead>
					<tbody>
					<?php if ( ! $items ) : ?>
						<tr><td colspan="10" style="text-align:center;color:var(--mut);padding:30px!important">محصولی مطابق فیلترها یافت نشد.</td></tr>
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
							<?php if ( $local_id ) : ?>
								<td><span style="display:inline-flex;color:var(--g1)" title="قبلاً افزوده شده — برای مدیریت از ستون «اقدام» استفاده کنید"><?php echo '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></td>
							<?php else : ?>
								<td><input type="checkbox" class="dastyarc-cb" name="remote_ids[]" value="<?php echo (int) $remote_id; ?>"></td>
							<?php endif; ?>
							<td><img src="<?php echo esc_url( $image ); ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:10px;box-shadow:0 1px 4px rgba(16,24,40,.12)"></td>
							<td><strong style="font-size:12.5px"><?php echo esc_html( (string) $item['name'] ); ?></strong></td>
							<td><span style="color:var(--mut);font-size:11px;direction:ltr;display:inline-block"><?php echo esc_html( (string) $item['sku'] ?: '—' ); ?></span></td>
							<td><?php echo 'variable' === $item['type'] ? DastyarC_Ui::pill( 'متغیر', 'b' ) : DastyarC_Ui::pill( 'ساده', 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><?php echo 'outofstock' === (string) ( $item['stock_status'] ?? 'instock' ) ? DastyarC_Ui::pill( 'ناموجود', 'r' ) : DastyarC_Ui::pill( 'موجود', 'g' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><span style="font-weight:700;font-size:12.5px"><?php echo wp_kses_post( $supplier_html ); ?></span></td>
							<td><b style="color:var(--g1);font-size:12.5px"><?php echo wp_kses_post( $your_html ); ?></b></td>
							<td><?php
								if ( $local_id && isset( $detached[ $remote_id ] ) ) {
									echo DastyarC_Ui::pill( 'خارج از دراپ‌شیپینگ (انبار شما)', 'y' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								} elseif ( $local_id ) {
									$st_obj = get_post_status_object( get_post_status( $local_id ) );
									echo DastyarC_Ui::pill( 'افزوده شده (' . ( $st_obj ? $st_obj->label : '' ) . ')', 'g' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								} else {
									echo '<span style="color:var(--mut)">—</span>';
								}
							?></td>
							<td><div style="display:flex;flex-wrap:wrap;gap:5px"><?php
								if ( $local_id && isset( $detached[ $remote_id ] ) ) {
									echo '<a class="dcx2-btn sm" href="' . esc_url( admin_url( 'post.php?post=' . $local_id . '&action=edit' ) ) . '">ویرایش محصول</a> '
										. '<a class="dcx2-btn prime sm" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_reattach&remote_id=' . $remote_id ), 'dastyarc_reattach_' . $remote_id ) ) . '">اتصال مجدد به دستیار</a>';
								} elseif ( $local_id ) {
									echo '<a class="dcx2-btn sm" href="' . esc_url( admin_url( 'post.php?post=' . $local_id . '&action=edit' ) ) . '">ویرایش محصول</a> '
										. '<a class="dcx2-btn sm" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_resync&remote_id=' . $remote_id ), 'dastyarc_resync_' . $remote_id ) ) . '">سینک مجدد</a> '
										. '<a class="dcx2-btn sm" style="color:#c81e38;border-color:#f0c3c3" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_detach&remote_id=' . $remote_id ), 'dastyarc_detach_' . $remote_id ) ) . '" title="خروج موقت از دراپ‌شیپینگ — ارسال از انبار خودتان؛ هر زمان قابل برگشت است">قطع اتصال</a>';
								} else {
									echo '<a class="dcx2-btn prime sm dastyarc-add-single" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_import&remote_id=' . $remote_id . '&status=' . $pref_status ), 'dastyarc_import_' . $remote_id ) ) . '">افزودن به فروشگاه</a>';
								}
							?></div></td>
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

		$result = DastyarC_Importer::import_remote( $remote_id, $status );

		if ( is_wp_error( $result ) ) {
			$this->notice( 'خطا در افزودن محصول: ' . $result->get_error_message(), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
			exit;
		}

		if ( 'publish' === $status ) {
			$this->notice( 'محصول با موفقیت به فروشگاه اضافه و بلافاصله «منتشر» شد.' );
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
		} else {
			$this->notice( 'محصول با موفقیت به فروشگاه اضافه شد و در وضعیت «پیش‌نویس» قرار گرفت. پس از بررسی، آن را منتشر کنید.' );
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
			$this->notice( 'هیچ محصولی انتخاب نشده است. تیک محصولات موردنظر را بزنید و دوباره تلاش کنید.', 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
			exit;
		}

		// در افزودن دسته‌جمعی، دانلود تصاویر ممکن است کمی طول بکشد
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}

		$ok   = 0;
		$fail = 0;
		foreach ( $remote_ids as $remote_id ) {
			$result = DastyarC_Importer::import_remote( $remote_id, $status );
			if ( is_wp_error( $result ) ) {
				$fail++;
				DastyarC::log( sprintf( 'Bulk import failed for remote #%d: %s', $remote_id, $result->get_error_message() ), 'error' );
			} else {
				$ok++;
			}
		}

		$label = 'publish' === $status ? 'منتشر شده' : 'پیش‌نویس';
		$msg   = sprintf( 'افزودن دسته‌جمعی انجام شد: %d محصول با وضعیت «%s» به فروشگاه اضافه شد.', $ok, $label );
		if ( $fail ) {
			$msg .= sprintf( ' %d مورد ناموفق بود (جزئیات: ووکامرس ← وضعیت ← گزارش‌ها ← dastyar-connector).', $fail );
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
			$this->notice( 'محصول از حالت دراپ‌شیپینگ خارج شد؛ حالا از انبار خود شما مدیریت می‌شود. هر زمان خواستید با «اتصال مجدد» به پلتفرم برگردانید.' );
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
				$this->notice( 'اتصال مجدد برقرار شد و محصول با مرکز همگام شد ✔' );
			}
		} else {
			$this->notice( 'این محصول هنوز به فروشگاه اضافه نشده است.', 'warning' );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) );
		exit;
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
				<summary><?php echo DastyarC_Ui::icon( 'link', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <b>اتصال به سایت مرکزی</b><span class="hnt">نشانی و کلید API فروشنده شما</span><span class="ch"><?php echo DastyarC_Ui::icon( 'chev', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></summary>
				<div class="dcx2-acc-body">
					<table class="form-table" role="presentation">
						<tr>
							<th><label>آدرس سایت مرکزی</label></th>
							<td><input type="url" name="dastyarc_central_url" class="regular-text" value="<?php echo esc_attr( get_option( 'dastyarc_central_url' ) ); ?>" placeholder="https://dastyar.shop" style="direction:ltr"></td>
						</tr>
						<tr>
							<th><label>API Key فروشنده</label></th>
							<td><input type="text" name="dastyarc_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'dastyarc_api_key' ) ); ?>" style="direction:ltr">
								<p class="description">از پنل فروشنده در سایت مرکزی: حساب کاربری ← اتصال API — پس از ذخیره، دکمه «تست اتصال» در کارت اقدام‌های پایین صفحه فعال است.</p></td>
						</tr>
					</table>
				</div>
			</details>

			<!-- ══ گروه ۲: قیمت‌گذاری و سینک ══ -->
			<details class="dcx2-acc" open>
				<summary><?php echo DastyarC_Ui::icon( 'price', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <b>قیمت‌گذاری و همگام‌سازی</b><span class="hnt">فرمول قیمت، سینک موجودی و انتقال سفارش‌ها</span><span class="ch"><?php echo DastyarC_Ui::icon( 'chev', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></summary>
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
								<span class="description">در حالت درصدی: عدد درصد (مثل 30) — در حالت ثابت: مبلغ (مثل 200000)</span></td>
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
								<label style="display:block;margin-bottom:6px"><input type="radio" name="dastyarc_price_apply_mode" value="auto" <?php checked( $apply_mode, 'auto' ); ?>> <strong>خودکار</strong> — با هر تغییر قیمت در مرکز، فرمول شما بلافاصله روی فروشگاه اعمال شود</label>
								<label style="display:block;margin-bottom:6px"><input type="radio" name="dastyarc_price_apply_mode" value="manual" <?php checked( $apply_mode, 'manual' ); ?>> <strong>دستی</strong> — قیمت تامین جدید فقط ثبت شود؛ اعمال با دکمه «اعمال به‌روزرسانی قیمت‌ها» (تب ویرایش دسته‌جمعی)</label>
								<p class="description">در هر دو حالت، موجودی همیشه خودکار همگام می‌شود.</p>
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
							<th>دسته‌بندی محصولات</th>
							<td><label><input type="checkbox" name="dastyarc_import_cats" value="yes" <?php checked( get_option( 'dastyarc_import_cats', 'yes' ), 'yes' ); ?>> انتقال دسته‌بندی‌های دستیار شاپ هنگام افزودن محصول</label>
								<p class="description">اگر غیرفعال کنید، محصولات <strong>بدون دسته‌بندی‌های مرکز</strong> اضافه می‌شوند و خودتان دسته‌بندی‌شان را تعیین می‌کنید.</p></td>
						</tr>
						<tr>
							<th>حالت انتقال سفارش‌ها به مرکز</th>
							<td>
								<label style="display:block;margin-bottom:8px"><input type="radio" name="dastyarc_order_mode" value="auto" <?php checked( $order_mode, 'auto' ); ?>> <strong>خودکار</strong> — با رسیدن سفارش به وضعیت‌های انتخابی، خودکار به دستیار شاپ ارسال شود</label>
								<label style="display:block"><input type="radio" name="dastyarc_order_mode" value="manual" <?php checked( $order_mode, 'manual' ); ?>> <strong>دستی</strong> — ارسال فقط از لیست سفارش‌های ووکامرس با اقدام «ارسال به دستیار شاپ»</label>
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
								<p class="description">فقط در حالت «خودکار» اعمال می‌شود.</p>
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
				<summary><?php echo DastyarC_Ui::icon( 'eye', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <b>نمایش فروشگاه و برگه‌ها</b><span class="hnt">نشان انبار، برگه پیگیری سفارش و برگه عودت</span><span class="ch"><?php echo DastyarC_Ui::icon( 'chev', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></summary>
				<div class="dcx2-acc-body">
					<table class="form-table" role="presentation">
						<tr>
							<th>نشان انبار</th>
							<td><label><input type="checkbox" name="dastyarc_warehouse_badge" value="yes" <?php checked( DastyarC_Badge::enabled() ); ?>> نمایش «ارسال از انبار بندرگناوه» بالای عنوان محصولات دستیار (طرح برند سازمانی)</label>
								<p class="description">فقط روی محصولاتی نمایش داده می‌شود که از دستیار شاپ به فروشگاه اضافه شده‌اند.</p></td>
						</tr>
						<tr>
							<th>برگه «پیگیری سفارش»</th>
							<td>
								<?php $track_page_id = (int) get_option( DastyarC_Track::PAGE_OPTION ); ?>
								<?php if ( $track_page_id && 'page' === get_post_type( $track_page_id ) ) : ?>
									<a href="<?php echo esc_url( get_permalink( $track_page_id ) ); ?>" target="_blank" class="dcx2-btn sm">مشاهده برگه</a>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $track_page_id . '&action=edit' ) ); ?>" class="dcx2-btn sm">ویرایش برگه</a>
									<p class="description">مشتریان با شماره سفارش/تماس، وضعیت و کد رهگیری را می‌بینند. شورتکد: <code>[dastyarc_track]</code></p>
								<?php else : ?>
									<p class="description">برگه هنگام فعال‌سازی خودکار ساخته می‌شود؛ اگر حذف شده، افزونه را یک‌بار غیرفعال و دوباره فعال کنید.</p>
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
									<p class="description">مشتری گزارش عودت را در این برگه ثبت می‌کند. شورتکد: <code>[dastyarc_rma]</code></p>
								<?php else : ?>
									<p class="description">اگر برگه حذف شده، افزونه را یک‌بار غیرفعال و دوباره فعال کنید.</p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</div>
			</details>

			<!-- ══ گروه ۴: مرجوعی ══ -->
			<details class="dcx2-acc">
				<summary><?php echo DastyarC_Ui::icon( 'rma', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <b>قوانین عودت و مرجوعی</b><span class="hnt">متنِ خواندنی که از مرکز می‌آید</span><span class="ch"><?php echo DastyarC_Ui::icon( 'chev', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></summary>
				<div class="dcx2-acc-body">
					<div style="background:#f2faf6;border:1px solid #cdeee0;border-right:4px solid var(--g1);border-radius:12px;padding:14px 18px;line-height:2;max-width:720px">
						<?php if ( '' !== $rules_now ) : ?>
							<?php echo nl2br( esc_html( $rules_now ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<p class="description" style="margin:8px 0 0">آخرین همگام‌سازی از مرکز: <?php echo esc_html( $rules_sync ?: '—' ); ?></p>
						<?php else : ?>
							<p style="margin:0" class="description">قوانین عودت توسط <strong>سایت مرکزی (دستیار شاپ)</strong> تعیین می‌شود و این‌جا فقط نمایش داده می‌شود. هنوز متنی از مرکز دریافت نشده — تا دریافت، متن پیش‌فرض روی برگه عمومی نمایش داده می‌شود.</p>
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

	/* ------------------------------------------------------------------
	 * صفحه «کیف پول» — موجودی + تراکنش‌ها + شارژ از طریق مرکز (v1.7.0)
	 * ---------------------------------------------------------------- */

	/**
	 * اسنپ‌شات کیف پول از مرکز (کش ۶۰ ثانیه‌ای تا صفحه سریع بماند) — قابل تست جداگانه.
	 * @return array{balance:float,transactions:array<int,object>}|WP_Error
	 */
	/** قالب فارسی مبلغ تومان — v1.10.1 (مشترک داشبورد/کیف پول) */
	public static function wallet_toman( $amount ) {
		return DastyarC_Jalali::num( number_format( (float) $amount ) ) . ' تومان';
	}

	public static function wallet_snapshot( $force = false ) {
		if ( ! DastyarC_Client::configured() ) {
			return new WP_Error( 'dastyarc_not_configured', 'ابتدا آدرس مرکز و API Key را در «دستیار شاپ ← تنظیمات دستیار» وارد و اتصال را برقرار کنید.' );
		}
		if ( ! $force ) {
			$cache = get_transient( 'dastyarc_wallet_snap' );
			if ( is_array( $cache ) ) {
				return $cache;
			}
		}
		$res = DastyarC_Client::wallet();
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$snap = array(
			'balance'      => (float) ( $res['balance'] ?? 0 ),
			'transactions' => (array) ( $res['transactions'] ?? array() ),
		);
		set_transient( 'dastyarc_wallet_snap', $snap, 60 );
		return $snap;
	}

	/** (v1.10.0) تب کیف پول — هیرو گلاس + کارت شارژ + جدول ۵۰ تراکنش آخر */
	protected function tab_wallet() {
		// «به‌روزرسانی» دستی ← کش ۶۰ ثانیه‌ای شکسته می‌شود (قرارداد قبلی حفظ شد)
		$force = ! empty( $_GET['wallet_refresh'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_wallet_refresh' );
		if ( $force ) {
			delete_transient( 'dastyarc_wallet_snap' );
		}
		$snap = self::wallet_snapshot( $force );

		if ( is_wp_error( $snap ) ) {
			echo '<div class="dcx2-notice x-bad">' . esc_html( self::friendly_api_error( $snap ) ) . '</div>';
			echo '<section class="dcx2-card"><div class="in">';
			DastyarC_Ui::empty_state( 'در حال حاضر دریافت موجودی از مرکز ممکن نیست.' );
			DastyarC_Ui::btn( DastyarC_Ui::icon( 'gear', 13 ) . ' تنظیمات اتصال', self::hub_url( 'settings' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div></section>';
			return;
		}

		$balance = (float) $snap['balance'];
		?>
		<!-- هیرو موجودی -->
		<section class="dcx2-card dcx2-hero">
			<div class="hd">
				<b><?php echo DastyarC_Ui::icon( 'wallet', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> کیف پول شما در دستیار شاپ</b>
				<?php
				$refresh = wp_nonce_url( self::hub_url( 'wallet', array( 'wallet_refresh' => 1 ) ), 'dastyarc_wallet_refresh' );
				DastyarC_Ui::btn( DastyarC_Ui::icon( 'sync', 13 ) . ' به‌روزرسانی', $refresh ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<div class="tiles">
				<div class="tile"><span>موجودی قابل برداشت/خرید</span><b><?php echo esc_html( number_format( $balance ) ); ?> <small style="font-size:11px;font-weight:600">تومان</small></b></div>
				<?php if ( '' !== (string) $snap['credit'] ) : ?>
				<div class="tile lo"><span>اعتبار هدیه</span><b><?php echo esc_html( number_format( (float) $snap['credit'] ) ); ?> تومان</b></div>
				<?php endif; ?>
				<?php if ( '' !== (string) $snap['pending'] ) : ?>
				<div class="tile lo"><span>در انتظار تسویه</span><b><?php echo esc_html( number_format( (float) $snap['pending'] ) ); ?> تومان</b></div>
				<?php endif; ?>
			</div>
			<small class="hint">کیف پول برای تسویه خودکار فاکتورهای دستیار به‌کار می‌رود؛ مبلغ پس از پرداخت موفق در مرکز خودکار شارژ می‌شود.</small>
		</section>

		<!-- کارت شارژ -->
		<section class="dcx2-card">
			<header><?php echo DastyarC_Ui::icon( 'wallet', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> شارژ کیف پول<span class="more">پس از کلیک به درگاه پرداخت سایت مرکز هدایت می‌شوید؛ شارژ خودکار ثبت می‌گردد</span></header>
			<div class="in">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'dastyarc_wallet_charge' ); ?>
					<input type="hidden" name="action" value="dastyarc_wallet_charge">
					<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
						<?php
						$quick = (array) apply_filters( 'dastyarc_wallet_quick_amounts', array( 5000000, 10000000, 20000000, 50000000 ) );
						foreach ( array_filter( array_map( 'absint', $quick ) ) as $amt ) :
							?>
							<button class="dcx2-btn" type="submit" name="dastyarc_quick_amount" value="<?php echo (int) $amt; ?>">شارژ <?php echo esc_html( number_format( (int) $amt ) ); ?> تومان</button>
						<?php endforeach; ?>
					</div>
					<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
						<label style="display:flex;flex-direction:column;gap:4px;font-size:11.5px;color:var(--mut);font-weight:700">مبلغ دلخواه (تومان)
							<input type="number" min="1000" step="1000" name="amount" placeholder="مثلاً 8000000" style="min-width:220px"></label>
						<button class="dcx2-btn prime" type="submit"><?php echo DastyarC_Ui::icon( 'wallet', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> پرداخت و شارژ</button>
					</div>
				</form>
			</div>
		</section>

		<!-- تراکنش‌ها -->
		<section class="dcx2-card" id="dtx">
			<header><?php echo DastyarC_Ui::icon( 'coin', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> آخرین تراکنش‌های کیف پول<span class="more">۵۰ تراکنش اخیر — گزارش کامل در پنل مرکز</span></header>
			<?php
			$txns = array_slice( (array) $snap['transactions'], 0, 50 );
			if ( ! $txns ) {
				DastyarC_Ui::empty_state( 'هنوز تراکنشی ثبت نشده است؛ با اولین شارژ، تراکنش‌ها این‌جا دیده می‌شوند.' );
			} else {
				?>
				<table class="dcx2-table">
					<thead><tr><th>شرح</th><th>مبلغ</th><th>موجودی بعد</th><th>زمان</th></tr></thead>
					<tbody>
					<?php
					foreach ( $txns as $tx ) :
						$tx    = is_object( $tx ) ? $tx : (object) $tx;
						$type  = (string) ( $tx->type ?? '' );
						$amt   = (float) ( $tx->amount ?? 0 );
						$after = (float) ( $tx->balance_after ?? 0 );
						$when  = (string) ( $tx->date_fa ?? '' );
						if ( '' === $when ) {
							$when = DastyarC_Jalali::dt( (string) ( $tx->created_at ?? '' ) );
						}
						$ok = ( 'credit' === $type );
						?>
						<tr>
							<td><?php echo esc_html( (string) ( $tx->description ?? ( $ok ? 'واریز' : 'برداشت' ) ) ); ?></td>
							<td><b class="dcx2-pill dcx2-pill--<?php echo $ok ? 'g' : 'r'; ?>"><?php echo $ok ? '+' : '−'; ?> <?php echo esc_html( number_format( $amt ) ); ?> تومان</b></td>
							<td style="color:var(--mut);font-size:11.5px"><?php echo esc_html( number_format( $after ) ); ?> تومان</td>
							<td style="color:var(--mut);font-size:11px"><?php echo esc_html( $when ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php
			}
			?>
		</section>
		<?php
	}

	/** هندلر شارژ کیف پول ← ساخت سفارش شارژ روی مرکز و هدایت فروشنده به درگاه (v1.7.0)
	 * v1.7.1: پشتیبانی از دکمه‌های شارژ فوری (dastyarc_quick_amount) + نرمال‌سازی ارقام فارسی/جداکننده هزارگان + پیام خطای فارسی راهنما */
	public function handle_wallet_charge() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_wallet_charge' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		// دکمه شارژ فوری (اگر کلیک شده) بر فیلد مبلغ دلخواه اولویت دارد
		$quick_raw = (string) ( $_POST['dastyarc_quick_amount'] ?? '' );
		$raw       = '' !== trim( $quick_raw ) ? $quick_raw : ( $_POST['amount'] ?? 0 );
		$amount    = self::normalize_amount( $raw );
		$back      = admin_url( 'admin.php?page=dastyarc-hub&tab=wallet' );
		if ( $amount < 1000 ) {
			$this->notice( 'مبلغ شارژ معتبر نیست (حداقل ۱٬۰۰۰ تومان).', 'error' );
			wp_safe_redirect( $back );
			exit;
		}
		$res = DastyarC_Client::wallet_charge( $amount );
		if ( is_wp_error( $res ) ) {
			$this->notice( 'خطا در ساخت سفارش شارژ: ' . self::friendly_api_error( $res ), 'error' );
			wp_safe_redirect( $back );
			exit;
		}
		$url = (string) ( $res['payment_url'] ?? '' );
		if ( '' === $url ) {
			$this->notice( 'پاسخ مرکز فاقد لینک پرداخت بود؛ دوباره تلاش کنید.', 'error' );
			wp_safe_redirect( $back );
			exit;
		}
		// مقصد دامنه مرکز است ← wp_safe_redirect فقط هاست داخلی را می‌پذیرد؛ پس ریدایرکت مستقیم
		wp_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * نرمال‌سازی مبلغ ورودی (v1.7.1): ارقام فارسی/عربی ← لاتین، حذف جداکننده هزارگان و فاصله.
	 * تا تایپ «۵٬۰۰۰٬۰۰۰» یا «8,000,000» هم به‌درستی 5000000/8000000 فهمیده شود.
	 */
	public static function normalize_amount( $raw ) {
		$s = strtr( (string) $raw, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			'٬' => '',  '،' => '',  ',' => '',  ' ' => '',  '٫' => '.',
		) );
		return (float) preg_replace( '/[^\d.]/', '', $s );
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

	/** دریافت مجدد قوانین عودت از مرکز (دکمه تنظیمات) */
	public function handle_rules_refresh() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_rules_refresh' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$ok = DastyarC_Rma::sync_rules_from_central();
		$this->notice(
			$ok ? 'قوانین عودت از مرکز دریافت و به‌روزرسانی شد ✔' : 'دریافت قوانین ناموفق بود؛ اتصال به مرکز را بررسی کنید.',
			$ok ? 'success' : 'error'
		);
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=settings' ) );
		exit;
	}

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
			echo '<p class="description" style="margin:0">این سفارش اقلام دستیار ندارد؛ کد رهگیری دستیارشاپ برای آن وجود ندارد.</p>';
		} elseif ( $track ) {
			printf(
				'<span class="dc-trackcode">%s</span>%s <button type="button" class="dc-copy-btn" data-copy="%s">📋 کپی کد رهگیری</button><span class="dc-copy-note" style="font-size:11px;color:#12875c;margin-right:6px"></span>',
				esc_html( (string) $track ),
				$carrier ? ' <small style="color:#687a72">(' . esc_html( (string) $carrier ) . ')</small>' : '',
				esc_attr( (string) $track )
			);
		} else {
			echo '<p class="description" style="margin:0">هنوز کد رهگیری از مرکز ثبت نشده است؛ پس از خروج از انبار دستیار این‌جا نمایش داده می‌شود.</p>';
		}
		echo '</div>';

		/* ── (v1.9.2) پنل‌های «سفارش ترکیبی» و «تعلیق‌شده» ── */
		$broken   = DastyarC_Orders::is_link_broken( $order );
		$is_mixed = (bool) $order->get_meta( '_dastyarc_mixed' );

		if ( $broken ) {
			echo '<div class="dc-trackcard" style="border-color:#f0b7c0;background:rgba(224,38,63,.05)">';
			echo '<p class="dc-tc-title" style="color:#c81e38">⏸ سفارش «تعلیق‌شده» (طبق اعلام مرکز):</p>';
			echo '<p class="description" style="margin:0">مرکز این سفارش را تعلیق کرده؛ نه جزو سفارش‌های موفق است و نه توسط دستیار لغو شده — اتصال این سفارش با مرکز قطع شده و مدیریت کامل وضعیت و کد رهگیری آن با شماست. این سفارش دیگر خودکار با مرکز همگام نمی‌شود.</p>';
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
					<?php else : ?>
						<span class="description">کد رهگیری این بخش توسط مرکز ثبت می‌شود.</span>
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
					<span class="description" style="display:block;margin-top:5px">کدهای رهگیری بولد با «به‌روزرسانی سفارش» ذخیره می‌شوند؛ تا تکمیل هر دو بخش سفارش در وضعیت «سفارش ترکیبی» می‌ماند.</span></p>
				<?php else : ?>
					<p class="description" style="margin:0">✔ بخش فروشنده تکمیل شده؛ چنانچه بخش دستیار هم به اتمام برسد، کل سفارش خودکار «تکمیل شده» می‌شود.</p>
				<?php endif; ?>
			</div>
			<?php
		endif;

		// بخش پایین: کد رهگیری دستی — برای سفارش‌های غیردستیار و سفارش‌های «تعلیق‌شده» (v1.9.2) قابل ویرایش است
		$vendor_track_editable = ( ! $has_items || $broken );
		$self_track   = $order->get_meta( '_dastyarc_self_tracking_code' );
		$self_carrier = $order->get_meta( '_dastyarc_self_tracking_carrier' );
		echo '<div class="dc-trackcard' . ( $vendor_track_editable ? '' : ' dc-trackcard-dim' ) . '" style="margin-top:10px">';
		echo '<p class="dc-tc-title">📮 کد رهگیری سفارش (ارسال توسط شما):</p>';
		if ( ! $vendor_track_editable ) {
			echo '<p class="description" style="margin:0">چون این سفارش محصول دستیار دارد، رهگیری آن فقط از طریق کد رهگیری دستیارشاپ (بالا) انجام می‌شود.</p>';
			if ( $self_track ) {
				printf( '<p style="margin:6px 0 0"><small>کد دستی ثبت‌شده: <code dir="ltr">%s</code>%s</small></p>', esc_html( (string) $self_track ), $self_carrier ? ' (' . esc_html( (string) $self_carrier ) . ')' : '' );
			}
		} else {
			if ( $broken ) {
				echo '<p class="description" style="margin:0 0 7px">این سفارش تعلیق‌شده است؛ کد رهگیری آن را خودتان (به‌جای مرکز) این‌جا ثبت کنید.</p>';
			}
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
			echo '<p class="description" style="margin:0">این کد با دکمه «به‌روزرسانی سفارش» ذخیره می‌شود و در «برگه پیگیری سفارش» فروشگاه شما هم برای مشتری نمایش داده می‌شود.</p>';
		}
		echo '</div>';

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
			echo '<p class="description" style="margin:0">برای این سفارش کد رهگیری در مرکز ثبت شده؛ احتمالاً از انبار خارج شده و قابل لغو نیست — اگر نیاز است تیکت ثبت کنید.</p>';
		}
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=dastyarc_cancel_order&order_id=' . $order->get_id() ),
			'dastyarc_cancel_order_' . $order->get_id()
		);
		printf(
			'<p style="margin:8px 0 0"><a class="button" href="%s" onclick="return confirm(\'سفارش در فروشگاه شما و سایت مرکزی لغو می‌شود و مبلغ آن به‌صورت خودکار به کیف پول شما برمی‌گردد. ادامه می‌دهید؟\')" style="background:#a52828;border-color:#a52828;color:#fff;border-radius:10px;font-weight:700">لغو سفارش در فروشگاه و مرکز</a></p>',
			esc_url( $url )
		);
		echo '<p class="description" style="margin:8px 0 0">فقط تا وقتی سفارش در مرکز «در حال انجام» است امکان لغو دارید؛ پس از تحویل به پست لغو پذیرفته نمی‌شود.</p>';
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

	/** جاوااسکریپت کپی کد رهگیری در متاباکس (یک‌بار در صفحه) */
	protected function copy_admin_js() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		<script>
		(function(){
			document.querySelectorAll('.dc-copy-btn').forEach(function(btn){
				btn.addEventListener('click', function(){
					var note = btn.parentElement.querySelector('.dc-copy-note');
					var done = function(){ if (note) { note.textContent = '✓ کپی شد'; setTimeout(function(){ note.textContent=''; }, 1500); } };
					var val = btn.getAttribute('data-copy') || '';
					if (!val) { return; }
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(val).then(done, function(){
							var t=document.createElement('textarea'); t.value=val; document.body.appendChild(t); t.select();
							try { document.execCommand('copy'); } catch(e){} t.remove(); done();
						});
					} else {
						var t=document.createElement('textarea'); t.value=val; document.body.appendChild(t); t.select();
						try { document.execCommand('copy'); } catch(e){} t.remove(); done();
					}
				});
			});
		})();
		</script>
		<?php
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
					<small style="width:100%;font-size:10.5px;color:var(--mut)">تغییر قیمت روی قیمت فروش فعلی (نمایش‌داده‌شده در ستون) اعمال می‌شود.</small>
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
				<header><?php echo DastyarC_Ui::icon( 'ticket', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> ثبت تیکت جدید<span class="more">پاسخ معمولاً در ۲ ساعت کاری</span></header>
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
					<?php DastyarC_Ui::empty_state( 'هنوز تیکتی ثبت نکرده‌اید — اولین سوالتان را از فرم روبه‌رو بپرسید.' ); ?>
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

	protected function notice( $message, $type = 'success' ) {
		set_transient( 'dastyarc_notice_' . get_current_user_id(), array( $message, $type ), 60 );
	}

	/**
	 * (v1.7.11) بنر اعلان مرکز: مدیر مرکز از «کنسول مرکز» اطلاعیه منتشر می‌کند و این‌جا دیده می‌شود.
	 * دریافت با کش ۱۰ دقیقه‌ای (ترنزینت dastyarc_conn_notice) و مخصوص همین فروشنده (فیلتر مخاطب در مرکز).
	 * فقط در صفحه‌های خود افزونه؛ خطا در اتصال ← سکوت کامل (نمایش هیچ بنری).
	 */
	public function notice_banner() {
		$page = (string) ( $_GET['page'] ?? '' );
		if ( 0 !== strpos( $page, 'dastyarc' ) ) {
			return;
		}
		$data = get_transient( 'dastyarc_conn_notice' );
		if ( false === $data ) {
			$res  = class_exists( 'DastyarC_Client' ) ? DastyarC_Client::notice() : new WP_Error( 'dastyarc_noclient' );
			$data = is_wp_error( $res ) ? array( 'active' => false ) : (array) $res;
			set_transient( 'dastyarc_conn_notice', $data, 600 );
		}
		if ( empty( $data ) || empty( $data['active'] ) ) {
			return;
		}
		$title = trim( (string) ( $data['title'] ?? '' ) );
		$msg   = trim( (string) ( $data['msg'] ?? '' ) );
		if ( '' === $title && '' === $msg ) {
			return;
		}
		echo '<div class="notice notice-info is-dismissible dastyarc-central-notice" style="border-right:6px solid #17a16d;border-radius:12px;padding:10px 14px;box-shadow:0 3px 12px rgba(23,161,109,.12)"><p style="margin:0">'
			. '📢 <strong style="color:#0f5132">' . esc_html( $title !== '' ? $title : 'اعلان دستیار شاپ' ) . '</strong>'
			. ( '' !== $msg ? ' — ' . esc_html( $msg ) : '' ) . '</p></div>';
	}

	public function notices() {
		$n = get_transient( 'dastyarc_notice_' . get_current_user_id() );
		if ( $n ) {
			delete_transient( 'dastyarc_notice_' . get_current_user_id() );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $n[1] ), esc_html( $n[0] ) );
		}

		// نتیجه اقدام دسته‌جمعی سفارش‌ها
		if ( isset( $_GET['dastyarc_bulk_sent'] ) ) {
			$sent = (int) $_GET['dastyarc_bulk_sent'];
			$skip = (int) ( $_GET['dastyarc_bulk_skip'] ?? 0 );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( 'اقدام دسته‌جمعی دستیار: %d سفارش ارسال شد، %d مورد رد شد (قبلاً ارسال شده یا فاقد اقلام دستیار).', $sent, $skip ) )
			);
		}

		// نتیجه سینک دسته‌جمعی موجودی محصولات (v1.5.0)
		if ( isset( $_GET['dastyarc_stocked'] ) ) {
			$changed = (int) $_GET['dastyarc_stocked'];
			$skipped = (int) ( $_GET['dastyarc_stocked_skip'] ?? 0 );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( 'سینک دسته‌جمعی موجودی: %d محصول به‌روزرسانی شد%s.', $changed, $skipped ? sprintf( ' — %d مورد رد شد (محصول دستیار نیست یا خطا در اتصال)', $skipped ) : '' ) )
			);
		}
	}

	/* ------------------------------------------------------------------
	 * «پنل واحد» — v1.9.0 بازطراحی بصری کامل به درخواست کاربر:
	 * همه بخش‌های منوی دستیار شاپ در یک صفحه واحد با «ریل تب» سمت راست؛
	 * سبک گلس سرمه‌ای تیره (پیش‌فرض) + حالت روشن، هماهنگ با برند سایت مرکز
	 * (#17a16d سبز سازمانی / #2fce96 نعنایی / #242536 سرمه‌ای + وزیرمتن).
	 * هر تب همان رندرگر قبلی صفحه مستقل را اجرا می‌کند تا هیچ قابلیتی از دست نرود
	 * و لینک‌های قدیمی هم با hub_init_bounce به تب متناظر هدایت می‌شوند.
	 * ---------------------------------------------------------------- */

	/** تعریف تب‌های هاب: tab => array( عنوان, زیرعنوان, آیکون ) */
	/**
	 * (v1.10.0 — Command Center) جدول تب‌های پنل واحد: ۷ تب در دو گروه.
	 * تب «هشدارهای قیمت» حذف شد ← به داشبورد منتقل گردید (تحلیل ساختاری تأییدشده: یک تب کمتر، اطلاعات بالاتر).
	 * هر آیتم: [عنوان، زیرعنوان (در هدر صفحه)، آیکون، گروه ریل]
	 */
	public static function hub_table() {
		return array(
			'stats'    => array( 'داشبورد', 'امروز شما در یک نگاه — اتصال، سفارش‌ها، کیف پول و هشدارهای قیمت', 'chart', 'main' ),
			'products' => array( 'محصولات دستیار', 'کاتالوگ مرکز را مرور و فیلتر کنید و به فروشگاه خود اضافه کنید', 'box', 'main' ),
			'bulk'     => array( 'ویرایش دسته‌جمعی', 'تغییر قیمت و موجودی فقط روی محصولات دستیار همین فروشگاه اعمال می‌شود', 'edit', 'main' ),
			'wallet'   => array( 'کیف پول', 'تسویه فاکتورها از کیف پول کم می‌شود؛ شارژ پس از پرداخت موفق در مرکز خودکار ثبت می‌شود', 'wallet', 'main' ),
			'rma'      => array( 'عودت و مرجوعی', 'درخواست‌های عودت خریداران از ثبت تا تسویه نهایی در مرکز پیگیری می‌شود', 'rma', 'main' ),
			'ticket'   => array( 'تیکت و پشتیبانی', 'ارتباط مستقیم با تیم فنی دستیار شاپ — پاسخ معمولاً کمتر از ۲ ساعت کاری', 'ticket', 'manage' ),
			'settings' => array( 'تنظیمات', 'کلید اتصال و هر آنچه رفتار کانکتور را تعیین می‌کند — در ۴ گروه مرتب', 'gear', 'manage' ),
		);
	}

	/** لینک داخلی یک تب هاب (با قابلیت نگهداشت آرگومان‌های اضافه) */
	public static function hub_url( $tab, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'dastyarc-hub', 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	/** کلاس بدنه ادمین — برای رنگ اسکرین پنل واحد */
	public function hub_body_class( $classes ) {
		if ( 'dastyarc-hub' === sanitize_key( (string) ( $_GET['page'] ?? '' ) ) ) {
			$classes .= ' dastyarc-hub';
		}
		return $classes;
	}

	/** شمارنده سبک هشدارهای قیمت از ترنزینت (برای KPI داشبورد — بدون درخواست اضافه به مرکز) */
	public static function price_alerts() {
		$items = get_transient( 'dastyarc_pricelog' );
		return is_array( $items ) ? $items : array();
	}

	/** رندرگر صفحه واحد */
	/**
	 * (v1.10.0 — Command Center) شِل پنل واحد:
	 * ریل عمودی دو‌گروهی + هدر با چیپ اتصال و «اقدام سریع» + بنر به‌روزرسانی مرکزی.
	 * هیچ بافر&پالایشی (dcx-legacy/hub_polish_inline) وجود ندارد — محتوای هر تب مستقیماً با سیستم dcx2 رندر می‌شود.
	 */
	public function page_hub() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$tabs = self::hub_table();
		$tab  = sanitize_key( (string) ( $_GET['tab'] ?? 'stats' ) );
		if ( 'price' === $tab ) {
			$tab = 'stats'; // سازگاری لینک‌های قدیمی «هشدارهای قیمت» ← این حالا کارت داشبورد است
		}
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'stats';
		}

		$this->hub_css();

		$rma_badge = class_exists( 'DastyarC_Rma' ) ? (int) DastyarC_Rma::pending_bubble_count() : 0;
		$connected = class_exists( 'DastyarC_Client' ) && DastyarC_Client::configured();
		$last_sync = (int) get_option( 'dastyarc_last_stock_sync', 0 );
		// بنر به‌روزرسانی مرکزی (کش میانی — بدون درخواست HTTP در رندر)
		$upd = null;
		if ( DastyarC::instance()->updater ?? null ) {
			$upd = DastyarC::instance()->updater->payload_public();
		}
		?>
		<div class="dcx2">
			<div class="dcx2-shell">
				<!-- ریل ناوبری -->
				<aside class="dcx2-rail">
					<div class="dcx2-brand">
						<span class="dcx2-mark"><?php echo DastyarC_Ui::icon( 'mark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span><b>کانکتور دستیار شاپ</b><small><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?> ← <?php echo esc_html( (string) wp_parse_url( (string) get_option( 'dastyarc_central_url' ), PHP_URL_HOST ) ); ?></small></span>
					</div>
					<nav class="dcx2-nav">
						<?php
						$grp_done = array();
						foreach ( $tabs as $tkey => $tdef ) :
							$grp = (string) $tdef[3];
							if ( ! isset( $grp_done[ $grp ] ) && 'manage' === $grp ) :
								$grp_done[ $grp ] = true;
								echo '<div class="dcx2-grp">مدیریت</div>';
							endif;
							$badge = ( 'rma' === $tkey ) ? $rma_badge : 0;
							?>
							<a href="<?php echo esc_url( self::hub_url( $tkey ) ); ?>" class="<?php echo $tkey === $tab ? 'on' : ''; ?>">
								<?php echo DastyarC_Ui::icon( $tdef[2], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<span class="t"><?php echo esc_html( $tdef[0] ); ?></span>
								<?php if ( $badge > 0 ) : ?><span class="b"><?php echo esc_html( (string) DastyarC_Jalali::num( (string) $badge ) ); ?></span><?php endif; ?>
							</a>
						<?php endforeach; ?>
					</nav>
					<div class="dcx2-rsync"><span class="ok"></span><span>آخرین سینک: <?php echo $last_sync ? esc_html( human_time_diff( $last_sync, time() ) ) . ' پیش' : '—'; ?></span><span class="ver"><?php echo esc_html( defined( 'DASTYARC_VERSION' ) ? DASTYARC_VERSION : '' ); ?></span></div>
				</aside>

				<!-- ناحیه محتوا -->
				<main class="dcx2-main">
					<div class="dcx2-head">
						<h1><?php echo esc_html( $tabs[ $tab ][0] ); ?><small><?php echo esc_html( $tabs[ $tab ][1] ); ?></small></h1>
						<?php if ( $connected ) : ?>
							<span class="dcx2-chip"><span class="d"></span>متصل به مرکز</span>
						<?php else : ?>
							<span class="dcx2-chip off"><span class="d"></span>اتصال پیکربندی نشده</span>
						<?php endif; ?>
						<span class="dcx2-qa">
							<a class="dcx2-btn" href="<?php echo esc_url( self::hub_url( 'products' ) ); ?>"><?php echo DastyarC_Ui::icon( 'plus', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> افزودن محصول</a>
							<a class="dcx2-btn" href="<?php echo esc_url( self::hub_url( 'wallet' ) ); ?>"><?php echo DastyarC_Ui::icon( 'wallet', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> شارژ کیف پول</a>
							<?php if ( $connected ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;display:inline">
								<?php wp_nonce_field( 'dastyarc_stock_sync_now' ); ?>
								<input type="hidden" name="action" value="dastyarc_stock_sync_now">
								<button class="dcx2-btn" type="submit"><?php echo DastyarC_Ui::icon( 'sync', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> سینک دستی</button>
							</form>
							<?php endif; ?>
							<?php if ( $upd ) : ?>
								<a class="dcx2-btn prime" href="<?php echo esc_url( $upd['url'] ); ?>"><?php echo DastyarC_Ui::icon( 'sync', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> به‌روزرسانی <span class="up"><?php echo esc_html( $upd['title'] ); ?></span></a>
							<?php endif; ?>
						</span>
					</div>

					<?php if ( $upd && 'stats' === $tab ) : ?>
					<div class="dcx2-upd lv-<?php echo esc_attr( $upd['label'] ); ?>">
						<span style="display:inline-flex;color:#b45309"><?php echo DastyarC_Ui::icon( 'price', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span>نسخه <b><?php echo esc_html( $upd['version'] ); ?></b> کانکتور منتشر شده است <b>(<?php echo esc_html( $upd['title'] ); ?>)</b><?php echo '' !== $upd['message'] ? ' — ' . esc_html( $upd['message'] ) : ''; ?></span>
						<span class="acts"><a class="dcx2-btn prime" href="<?php echo esc_url( $upd['url'] ); ?>">همین حالا به‌روزرسانی</a><a class="dcx2-btn thickbox" href="<?php echo esc_url( $upd['info_url'] ); ?>">تغییرات</a></span>
					</div>
					<?php endif; ?>

					<?php $this->hub_render_tab( $tab ); ?>
				</main>
			</div>
		</div>
		<?php
	}


	/** (v1.10.0 — Command Center) رندرگر هر تب — همهٔ تب‌ها سیستم کامپوننت واحد dcx2 را به‌کار می‌برند */
	protected function hub_render_tab( $tab ) {
		switch ( $tab ) {
			case 'stats':
				$this->tab_dashboard();
				break;
			case 'products':
				$this->tab_products();
				break;
			case 'bulk':
				$this->tab_bulk();
				break;
			case 'wallet':
				$this->tab_wallet();
				break;
			case 'rma':
				$this->tab_rma();
				break;
			case 'ticket':
				$this->tab_tickets();
				break;
			case 'settings':
				$this->tab_settings();
				break;
		}
	}

	/* --------------------------------------------------------------------
	 * تب داشبورد — قلب «Command Center» (v1.10.0):
	 * ۴ KPI اسپارک‌لاینی + هیروی کیف پول + آخرین سفارش‌ها + کارت هشدار قیمت + خوراک رویدادها
	 * ---------------------------------------------------------------- */

	/** رندر تب داشبورد — قلب «Command Center» */
	protected function tab_dashboard() {
		if ( ! DastyarC_Client::configured() ) {
			$this->dashboard_onboarding();
			return;
		}

		$snap    = self::wallet_snapshot();
		if ( is_wp_error( $snap ) ) {
			$snap = array( 'balance' => '', 'credit' => '', 'pending' => '', 'transactions' => array(), 'fetched' => '' );
		}
		$orders  = $this->dash_orders();
		$alerts  = self::price_alerts();
		$events  = DastyarC::events( 8 );
		$healthy = ! DastyarC::has_recent_error();
		$bal     = '' !== $snap['balance'] ? (float) $snap['balance'] : null;
		$wc      = (int) $orders['week_cur'];
		$wkp     = (int) $orders['week_prev'];
		$dlt     = $wkp > 0 ? (int) round( ( $wc - $wkp ) / $wkp * 100 ) : ( $wc > 0 ? 100 : 0 );
		$n_alert = count( $alerts );
		?>
		<?php if ( isset( $_GET['stock_sync'] ) && 'done' === sanitize_key( (string) $_GET['stock_sync'] ) ) : ?>
			<div class="dcx2-notice">سینک دستی موجودی انجام شد و زمان «آخرین سینک» به‌روزرسانی گردید.</div>
		<?php endif; ?>

		<!-- شبکه KPI -->
		<div class="dcx2-kpis">
			<?php
			DastyarC_Ui::kpi(
				'سفارش‌های این هفته',
				esc_html( (string) DastyarC_Jalali::num( (string) $wc ) ),
				0 === $dlt ? 'بدون تغییر' : ( ( $dlt > 0 ? '+' : '' ) . DastyarC_Jalali::num( (string) $dlt ) . '٪ نسبت به هفته قبل' ),
				$dlt > 0 ? 'up' : ( $dlt < 0 ? 'dn' : 'bl' ),
				$orders['weekly'],
				'#17a16d'
			);
			DastyarC_Ui::kpi(
				'سفارش‌های در حال پردازش',
				esc_html( (string) DastyarC_Jalali::num( (string) $orders['inprog'] ) ),
				$orders['mixed'] > 0 ? DastyarC_Jalali::num( (string) $orders['mixed'] ) . ' مورد ترکیبی' : 'تحت کنترل',
				$orders['mixed'] > 0 ? 'wr' : 'up',
				array(),
				'#d98a1f'
			);
			DastyarC_Ui::kpi(
				'موجودی کیف پول',
				null === $bal ? '<span style="color:var(--mut);font-size:15px">نامشخص</span>' : esc_html( self::wallet_toman( $bal ) ),
				'' !== $snap['fetched'] ? 'کش ۱ ساعته' : 'عدم اتصال به مرکز',
				null === $bal ? 'dn' : 'bl',
				array(),
				'#2fce96'
			);
			DastyarC_Ui::kpi(
				'هشدارهای قیمت تامین',
				esc_html( (string) DastyarC_Jalali::num( (string) $n_alert ) ),
				$n_alert ? 'نیازمند اقدام' : 'بدون هشدار فعال',
				$n_alert ? 'wr' : 'up',
				array(),
				'#b45309'
			);
			?>
		</div>

		<!-- هیرو کیف پول -->
		<section class="dcx2-card dcx2-hero">
			<div class="hd">
				<b><?php echo DastyarC_Ui::icon( 'wallet', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> کیف پول شما در مرکز دستیار</b>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="dastyarc-hub">
					<input type="hidden" name="tab" value="stats">
					<?php wp_nonce_field( 'dastyarc_wallet_refresh' ); ?>
					<input type="hidden" name="wallet_refresh" value="1">
					<button class="dcx2-btn" type="submit"><?php echo DastyarC_Ui::icon( 'sync', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> به‌روزرسانی</button>
				</form>
			</div>
			<div class="tiles">
				<div class="tile"><span>موجودی قابل برداشت/خرید</span><b><?php echo null === $bal ? '—' : esc_html( self::wallet_toman( $bal ) ); ?></b></div>
				<div class="tile lo"><span>اعتبار هدیه</span><b><?php echo '' !== $snap['credit'] ? esc_html( self::wallet_toman( (float) $snap['credit'] ) ) : '—'; ?></b></div>
				<div class="tile lo"><span>در انتظار تسویه</span><b><?php echo '' !== $snap['pending'] ? esc_html( self::wallet_toman( (float) $snap['pending'] ) ) : '—'; ?></b></div>
			</div>
			<div class="acts">
				<?php DastyarC_Ui::btn( DastyarC_Ui::icon( 'wallet', 13 ) . ' شارژ کیف پول', self::hub_url( 'wallet' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php DastyarC_Ui::btn( DastyarC_Ui::icon( 'coin', 13 ) . ' گردش حساب', self::hub_url( 'wallet' ) . '#dtx' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<small class="hint">تسویه فاکتورهای سفارش خودکار از کیف پول کم می‌شود؛ موجودی تا ۱ ساعت کش می‌ماند.</small>
		</section>

		<div class="dcx2-grid2">
			<!-- آخرین سفارش‌های متصل -->
			<section class="dcx2-card">
				<header><?php echo DastyarC_Ui::icon( 'chart', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> آخرین سفارش‌های شما<a class="more" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>">همهٔ سفارش‌ها ←</a></header>
				<?php if ( empty( $orders['recent'] ) ) : ?>
					<?php DastyarC_Ui::empty_state( 'هنوز سفارش متصلی به مرکز ندارید — اولین سفارش، این‌جا نمایش داده می‌شود.' ); ?>
				<?php else : ?>
				<table class="dcx2-table">
					<thead><tr><th>سفارش</th><th>مشتری</th><th>وضعیت</th><th>مبلغ</th><th>زمان</th></tr></thead>
					<tbody>
					<?php
					foreach ( $orders['recent'] as $o ) :
						$oid   = $o->get_id();
						$buyer = trim( (string) $o->get_billing_first_name() . ' ' . (string) $o->get_billing_last_name() );
						?>
						<tr>
							<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $oid . '&action=edit' ) ); ?>" style="font-weight:800">#<?php echo esc_html( (string) DastyarC_Jalali::num( (string) $oid ) ); ?></a></td>
							<td><?php echo esc_html( $buyer ? $buyer : '—' ); ?></td>
							<td><?php echo DastyarC_Ui::order_status_pill( $o ) . ' ' . DastyarC_Ui::order_parts_pills( $o ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td><b><?php echo wp_kses_post( wc_price( (float) $o->get_total(), array( 'currency' => $o->get_currency() ) ) ); ?></b></td>
							<td><span style="font-size:10.5px;color:var(--mut)"><?php echo esc_html( human_time_diff( $o->get_date_created() ? $o->get_date_created()->getTimestamp() : time(), time() ) ); ?> پیش</span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</section>

			<!-- کارت هشدارهای قیمت تامین -->
			<section class="dcx2-card">
				<header><?php echo DastyarC_Ui::icon( 'coin', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> هشدارهای قیمت تامین<a class="more" href="<?php echo esc_url( self::hub_url( 'bulk' ) ); ?>">اصلاح قیمت‌ها ←</a></header>
				<?php if ( ! $n_alert ) : ?>
					<?php DastyarC_Ui::empty_state( 'قیمت تامین محصولات شما ثابت است — تغییرات بعدی این‌جا آلارم می‌شوند.' ); ?>
				<?php else : ?>
					<ul class="dcx2-alerts">
					<?php foreach ( array_slice( $alerts, 0, 5 ) as $it ) : ?>
						<li><span style="display:inline-flex;color:#b45309"><?php echo DastyarC_Ui::icon( 'coin', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><span>
							<b><?php echo esc_html( (string) ( $it['title'] ?? '' ) ); ?></b>
							<small><?php echo esc_html( self::wallet_toman( (float) ( $it['prev_supply_price'] ?? 0 ) ) ); ?> ← <b style="color:#c2410c"><?php echo esc_html( self::wallet_toman( (float) ( $it['supply_price'] ?? 0 ) ) ); ?></b></small>
						</span></li>
					<?php endforeach; ?>
					<?php if ( $n_alert > 5 ) : ?><li style="justify-content:center"><small>و <?php echo esc_html( (string) DastyarC_Jalali::num( (string) ( $n_alert - 5 ) ) ); ?> مورد دیگر — در هر سینک خودکار به‌روزرسانی می‌شود.</small></li><?php endif; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>

		<!-- کارت رویدادها + سلامت -->
		<section class="dcx2-card">
			<header><?php echo DastyarC_Ui::icon( 'sync', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> رویدادهای اخیر<span class="more">۳۰ رویداد آخر نگه‌داری می‌شوند — گزارش کامل در «لاگ‌های ووکامرس»</span></header>
			<?php
			if ( empty( $events ) ) {
				DastyarC_Ui::empty_state( 'هنوز رویدادی ثبت نشده است — نتایج سینک و ارسال سفارش‌ها این‌جا می‌آید.' );
			} else {
				$feed = array();
				foreach ( $events as $ev ) {
					$l = (string) ( $ev['l'] ?? 'info' );
					$feed[] = array(
						'lvl'  => in_array( $l, array( 'info', 'notice', 'warning', 'error' ), true ) ? $l : 'info',
						'text' => (string) ( $ev['m'] ?? '' ),
						'time' => human_time_diff( (int) ( $ev['t'] ?? time() ), time() ) . ' پیش',
					);
				}
				DastyarC_Ui::feed( $feed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
			<div class="dcx2-health<?php echo $healthy ? '' : ' bad'; ?>">وضعیت سلامت اتصال:
				<b><?php echo $healthy ? 'همه‌چیز پایدار است — بدون خطا در ۷۲ ساعت اخیر' : 'در ۷۲ ساعت اخیر خطا ثبت شده است — جزئیات در لاگ؛ اتصال را بررسی کنید'; ?></b>
			</div>
		</section>
		<?php
	}

	/** حالت خوش‌آمد/راه‌اندازی داشبورد وقتی اتصال هنوز پیکربندی نشده است (v1.10.0) */
	protected function dashboard_onboarding() {
		?>
		<section class="dcx2-card" style="overflow:hidden">
			<div style="background:var(--grad);color:#fff;padding:30px 22px;text-align:center">
				<div style="width:52px;height:52px;margin:0 auto 12px;border-radius:14px;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center"><?php echo DastyarC_Ui::icon( 'mark', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div style="font-size:17px;font-weight:800">به کانکتور دستیار شاپ خوش آمدید</div>
				<div style="font-size:12px;opacity:.85;margin-top:5px;max-width:520px;margin-right:auto;margin-left:auto;line-height:2">مرکز دستیار شاپ را به فروشگاه‌تان وصل کنید تا کاتالوگ، قیمت‌ها، سفارش‌ها و کیف پول‌تان هم‌زمان در یک‌جا مدیریت شود.</div>
				<div style="margin-top:16px"><a class="dcx2-btn prime" style="background:#fff;border-color:#fff;color:var(--g1);box-shadow:none" href="<?php echo esc_url( self::hub_url( 'settings' ) ); ?>">شروع اتصال به مرکز ←</a></div>
			</div>
			<div class="in" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
				<div><?php echo DastyarC_Ui::pill( 'گام ۱ — کلید فروشنده', 'b' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><p style="margin:8px 0 0;font-size:12px;line-height:2;color:var(--mut)">در بخش «تنظیمات»، کلید vkey فروشگاه‌تان را از پنل مرکز کپی و وارد کنید و روی «تست اتصال» بزنید.</p></div>
				<div><?php echo DastyarC_Ui::pill( 'گام ۲ — سینک اولیه', 'b' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><p style="margin:8px 0 0;font-size:12px;line-height:2;color:var(--mut)">پس از اتصال موفق، اولین سینک خودکار انجام می‌شود؛ قیمت‌ها و موجودی محصولات دستیار همیشه تازه می‌مانند.</p></div>
				<div><?php echo DastyarC_Ui::pill( 'گام ۳ — شروع فروش', 'b' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><p style="margin:8px 0 0;font-size:12px;line-height:2;color:var(--mut)">از تب «محصولات»، محصولات مرکز را به فروشگاه اضافه کنید و قیمت/موجودی فروش را در «ویرایش دسته‌جمعی» نهایی کنید.</p></div>
			</div>
		</section>
		<?php
	}


	/**
	 * نمونه داده سبک سفارش‌های متصل (۱۲۰ موردِ اخیر، کش ۳ دقیقه‌ای) — برای KPI + اسپارک‌لاین ۶ هفته
	 */
	protected function dash_orders() {
		$cache = get_transient( 'dastyarc_dash_orders' );
		if ( is_array( $cache ) && isset( $cache['weekly'] ) ) {
			return $cache;
		}
		$out = array(
			'weekly' => array_fill( 0, 6, 0 ),
			'week_cur' => 0,
			'week_prev' => 0,
			'inprog' => 0,
			'mixed' => 0,
			'recent' => array(),
		);
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $out;
		}
		$q = wc_get_orders(
			array(
				'limit'      => 120,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'return'     => 'objects',
				// سفارش‌های مرتبط با دستیار: یا دارای اقلام دستیار است یا قبلاً به مرکز ارسال شده
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array( 'key' => '_dastyarc_has_items', 'value' => '1' ),
					array( 'key' => '_dastyarc_remote_order_id', 'compare' => 'EXISTS' ),
				),
			)
		);
		foreach ( (array) $q as $o ) {
			$ts  = $o->get_date_created() ? $o->get_date_created()->getTimestamp() : time();
			$wka = (int) floor( ( time() - $ts ) / ( 7 * DAY_IN_SECONDS ) );
			if ( $wka < 6 ) {
				$out['weekly'][ 5 - $wka ]++;
			}
			if ( 0 === $wka ) {
				$out['week_cur']++;
			} elseif ( 1 === $wka ) {
				$out['week_prev']++;
			}
			if ( in_array( 'wc-' . $o->get_status(), array( 'wc-processing', 'wc-on-hold' ), true ) ) {
				$out['inprog']++;
				if ( $o->get_meta( '_dastyarc_mixed' ) ) {
					$out['mixed']++;
				}
			}
		}
		$out['recent'] = array_slice( (array) $q, 0, 6 );
		set_transient( 'dastyarc_dash_orders', $out, 3 * MINUTE_IN_SECONDS );
		return $out;
	}

	/** (v1.10.0 — Command Center) استایل پنل واحد = سیستم طراحی واحد DastyarC_Ui (به‌همراه کروم کرنل) */
	protected function hub_css() {
		?>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
		<style>
		body.dastyarc-hub #wpbody-content{padding-bottom:0}
		body.dastyarc-hub .notice{margin:12px 18px 0}
		body.dastyarc-hub .update-nag{margin:12px 18px 0}
		</style>
		<?php
		DastyarC_Ui::css();
	}
}
