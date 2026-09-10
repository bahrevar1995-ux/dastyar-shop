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

		// موقعیت ۳ = بلافاصله زیر «پیشخوان» در منوی وردپرس (v1.5.0)
		add_menu_page(
			'دستیار شاپ',
			'دستیار شاپ' . $bubble . ' <span class="dastyar-live-dot" aria-hidden="true"></span>',
			'manage_woocommerce',
			'dastyarc-products',
			array( $this, 'page_products' ),
			'dashicons-networking',
			3
		);
		add_submenu_page( 'dastyarc-products', 'محصولات دستیار', 'محصولات دستیار', 'manage_woocommerce', 'dastyarc-products', array( $this, 'page_products' ) );
		add_submenu_page( 'dastyarc-products', 'هشدارهای قیمت تامین', 'هشدارهای قیمت', 'manage_woocommerce', 'dastyarc-pricelog', array( $this, 'page_price_alerts' ) ); // v1.7.5 — مورد ۲
		add_submenu_page( 'dastyarc-products', 'آمار دستیار', 'آمار', 'manage_woocommerce', 'dastyarc-stats', array( $this, 'page_stats' ) ); // v1.7.0 — جایگزین ابزارک‌های پیشخوان
		add_submenu_page( 'dastyarc-products', 'کیف پول دستیار', 'کیف پول', 'manage_woocommerce', 'dastyarc-wallet', array( $this, 'page_wallet' ) ); // v1.7.0 — مشاهده/شارژ کیف پول در پیشخوان خود فروشنده
		add_submenu_page( 'dastyarc-products', 'تیکت و پشتیبانی', 'تیکت و پشتیبانی', 'manage_woocommerce', 'dastyarc-tickets', array( $this, 'page_tickets' ) ); // v1.7.5 — مورد ۹
		add_submenu_page( 'dastyarc-products', 'ویرایش دسته‌جمعی دستیار', 'ویرایش دسته‌جمعی دستیار', 'manage_woocommerce', 'dastyarc-bulk', array( $this, 'page_bulk_edit' ) ); // v1.6.0 — مورد ۱۰
		add_submenu_page( 'dastyarc-products', 'عودت و مرجوعی', 'عودت و مرجوعی' . $bubble, 'manage_woocommerce', 'dastyarc-rma', array( $this, 'page_rma' ) );
		add_submenu_page( 'dastyarc-products', 'تنظیمات دستیار', 'تنظیمات دستیار', 'manage_woocommerce', 'dastyarc-settings', array( $this, 'page_settings' ) );
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
			'href'   => admin_url( 'admin.php?page=dastyarc-settings' ),
			'meta'   => array( 'title' => $on ? 'اتصال به مرکز برقرار است' : 'اتصال برقرار نیست — تنظیمات دستیار را بررسی کنید' ),
		) );
	}

	/** پنل عودت و مرجوعی (پیاده‌سازی در DastyarC_Rma) */
	public function page_rma() {
		DastyarC::instance()->rma->page_admin();
	}

	/* ------------------------------------------------------------------
	 * صفحه «محصولات دستیار» — لیست از WooCommerce سایت مرکزی + افزودن تکی/دسته‌جمعی
	 * ---------------------------------------------------------------- */

	public function page_products() {
		// (v1.7.3) بازطراحی بصری صفحه — با حفظ کامل عملکرد (فرم‌ها، دکمه‌ها، nonce و هوک‌های JS همان‌های قبلی‌اند)
		echo '<style>
		.dcp-wrap h1.wp-heading-inline{font-size:22px;font-weight:800;color:#242536}
		.dcp-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin:8px 0 2px}
		.dcp-head .dcp-sub{margin:6px 0 0;color:#6b7280;font-size:12.5px;line-height:1.9}
		.dcp-ver{flex:none;background:#eaf7f1;color:#0f7a52;border:1px solid #cdeedd;padding:4px 12px;border-radius:999px;font-size:11.5px;font-weight:700;direction:ltr}
		.dcp-card{background:#fff;border:1px solid #e6e8ef;border-radius:14px;box-shadow:0 1px 3px rgba(16,24,40,.05)}
		.dcp-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 14px;margin:14px 0}
		.dcp-filters input[type=search],.dcp-filters select{border:1px solid #dcdce4!important;border-radius:9px!important;box-shadow:none!important;min-height:36px;padding:5px 12px;background:#fafbfc;margin:0;transition:border-color .15s,box-shadow .15s}
		.dcp-filters input[type=search]:focus,.dcp-filters select:focus{border-color:#17a16d!important;box-shadow:0 0 0 3px rgba(23,161,109,.15)!important;outline:none}
		.dcp-btn{border-radius:9px!important;min-height:36px;line-height:1;padding:0 16px!important;font-weight:700;border:1px solid #dcdce4!important;background:#fff!important;color:#242536!important;box-shadow:none!important;transition:all .15s}
		.dcp-btn:hover{background:#f4f5f9!important;border-color:#c6c9d4!important}
		.dcp-btn.dcp-btn-g,.dcp-btn-g{background:#17a16d!important;border-color:#17a16d!important;color:#fff!important}
		.dcp-btn.dcp-btn-g:hover,.dcp-btn-g:hover{background:#12875c!important;border-color:#12875c!important}
		.dcp-bulk{display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding:12px 14px;margin:0 0 12px;border-right:4px solid #17a16d}
		.dcp-bulk strong{color:#242536}
		.dcp-bulk select{border-radius:8px}
		.dcp-bulk .description{width:100%;color:#787c86;font-size:11.5px}
		.dcp-table-wrap{overflow:hidden;margin:0}
		.dcp-table{border:0!important;margin:0}
		/* v1.7.5 — مورد ۵: سربرگ تیره ← متن حتماً سفید (در برخی پوسته‌های ادمین پیش‌فرض رنگ بازنویسی می‌شد و متن مشکی ناخوانا روی نِیوی می‌نشست) */
		.dcp-table thead th,.dcp-table thead td{background:#242536!important;color:#fff!important;font-weight:700;padding:12px 10px;border:0!important}
		.dcp-table thead th *,.dcp-table thead td *{color:#fff!important}
		.dcp-table thead input{margin:0}
		.dcp-table tbody td{vertical-align:middle;padding:10px;border-color:#f0f1f6!important}
		.dcp-table tbody tr{transition:background .12s}
		.dcp-table tbody tr:hover{background:#f6fbf8}
		.dcp-img{width:44px;height:44px;object-fit:cover;border-radius:10px;box-shadow:0 1px 4px rgba(16,24,40,.12)}
		.dcp-title{font-weight:700;color:#242536}
		.dcp-sku{color:#8b8f9c;font-size:11px;direction:ltr;display:inline-block}
		.dcp-pill{display:inline-block;padding:3px 11px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid transparent;white-space:nowrap}
		.dcp-pill-var{background:#eef2ff;color:#3730a3;border-color:#dfe3ff}
		.dcp-pill-simple{background:#f3f4f6;color:#4b5563;border-color:#e5e7eb}
		.dcp-pill-in{background:#ecf9f2;color:#0f5132;border-color:#c6e9d8}
		.dcp-pill-out{background:#fdf0f0;color:#a52828;border-color:#f0c8c8}
		.dcp-added{background:#ecf9f2;color:#0f7a52;border-color:#c6e9d8}
		.dcp-detached{background:#fdf6e7!important;color:#8a5a00!important;border-color:#f0d9a8!important}
		.dcp-btn-detach{color:#b42318!important}
		.dcp-btn-detach:hover{color:#8f1a11!important;border-color:#b42318!important}
		.dcp-price{font-weight:600;color:#242536}
		.dcp-price small{color:#9aa0ac;font-weight:400}
		.dcp-your{color:#0f7a52}
		.dcp-actions .button{white-space:nowrap}
		.dcp-empty{text-align:center!important;color:#8b8f9c;padding:34px!important;font-size:13px}
		.dcp-pages{display:flex;gap:6px;flex-wrap:wrap;margin:14px 2px}
		.dcp-pages .button{min-width:34px;justify-content:center;display:inline-flex;align-items:center;height:34px;padding:0 10px!important}
		.dcp-pages-nav{justify-content:center;align-items:center;flex-wrap:nowrap}
		.dcp-pages-nav .dcp-nav{min-width:92px;gap:4px}
		.dcp-pages-nav .dcp-nav .dcp-icn{display:inline-flex;line-height:0}
		.dcp-pages-nav .dcp-nav-off{opacity:.38;cursor:default;pointer-events:none}
		.dcp-pageinfo{display:inline-flex;align-items:center;gap:4px;font-size:12.5px;color:#242536;padding:0 8px;white-space:nowrap}
		.dcp-pageinfo strong{color:#17a16d;font-size:13px}
		.dcp-pageinfo .dcp-muted{color:#8a90a0;font-size:11.5px}
		@media(max-width:900px){.dcp-head{flex-direction:column}}
		</style>';
		echo '<div class="wrap dcp-wrap">'
			. '<div class="dcp-head"><div class="dcp-head-t"><h1 class="wp-heading-inline">محصولات دستیار</h1>'
			. '<p class="dcp-sub">محصولات سایت مرکز را مرور و فیلتر کنید و به فروشگاه خود اضافه کنید؛ «قیمت شما» از روی قیمت تامین و فرمول قیمت‌گذاری شما محاسبه می‌شود.</p></div>'
			. '<span class="dcp-ver" title="نسخه افزونه دستیار کانکتور">نسخه کانکتور ' . esc_html( defined( 'DASTYARC_VERSION' ) ? DASTYARC_VERSION : '' ) . '</span></div>';

		if ( ! DastyarC_Client::configured() ) {
			printf( '<div class="notice notice-warning"><p>ابتدا اتصال را در <a href="%s">تنظیمات دستیار</a> کامل کنید.</p></div></div>', esc_url( admin_url( 'admin.php?page=dastyarc-settings' ) ) );
			return;
		}

		$page   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$search = sanitize_text_field( (string) ( $_GET['s'] ?? '' ) );
		// فیلترهای v1.6.0: دسته‌بندی، موجودی، تعداد در صفحه (مورد ۴)
		$dcat   = sanitize_title( (string) ( $_GET['dcat'] ?? '' ) );
		$dstock = in_array( $_GET['dstock'] ?? '', array( 'instock', 'outofstock' ), true ) ? sanitize_key( $_GET['dstock'] ) : '';
		// v1.7.5 — مورد ۶: تفکیک «افزوده‌شده / افزوده‌نشده» به فروشگاه فروشنده
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
			printf( '<div class="notice notice-error"><p>خطا در دریافت محصولات از مرکز: %s</p></div>', esc_html( $result->get_error_message() ) );
			$cached = get_option( self::SNAPSHOT );
			$result = $cached ?: array( 'data' => array(), 'total' => 0 );
			if ( $cached ) {
				echo '<div class="notice notice-info"><p>نمایش آخرین نسخه کش‌شده محصولات.</p></div>';
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
			'meta_key'       => '_dastyar_remote_id',
		) ) as $pid ) {
			$imported[ (int) get_post_meta( $pid, '_dastyar_remote_id', true ) ] = $pid;
		}
		// (v1.7.8) نقشه محصولاتی که به‌صورت موقت از دراپ‌شیپینگ خارج شده‌اند (ارسال از انبار خود فروشنده)
		$detached = array();
		foreach ( $imported as $rid => $lid ) {
			if ( get_post_meta( $lid, '_dastyarc_detached', true ) ) {
				$detached[ $rid ] = $lid;
			}
		}

		// (v1.7.5 — مورد ۶) فیلتر «افزوده‌شده / افزوده‌نشده» روی همین صفحه نتایج + شمارش وضعیت
		$dimp_total = count( $items );
		$dimp_added = 0;
		foreach ( $items as $it0 ) {
			if ( isset( $imported[ (int) ( $it0['id'] ?? 0 ) ] ) ) {
				$dimp_added++;
			}
		}
		if ( '' !== $dimp ) {
			$items = array_values( array_filter( $items, function ( $it0 ) use ( $imported, $dimp ) {
				$is_in = isset( $imported[ (int) ( $it0['id'] ?? 0 ) ] );
				return 'added' === $dimp ? $is_in : ! $is_in;
			} ) );
		}

		// (v1.7.6 — بازخورد کاربر) محصولات ناموجودِ مرکز به انتهای همان صفحه لیست منتقل می‌شوند؛
		// ترتیب نسبی بقیه آیتم‌ها (مرتب‌سازی مرکز: جدیدترین/جستجو/…) کاملاً حفظ می‌شود.
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

		// وضعیت پیش‌فرضِ افزودن (به انتخاب فروشنده در همین صفحه — آخرین انتخاب به خاطر سپرده می‌شود)
		$pref_status = (string) get_user_meta( get_current_user_id(), 'dastyarc_import_status', true );
		if ( ! in_array( $pref_status, array( 'draft', 'publish' ), true ) ) {
			$pref_status = 'draft';
		}

		// دسته‌بندی‌های مرکز برای فیلتر (با فالبک به دسته‌های استخراج‌شده از نسخه کش‌شده)
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

		// فرم جستجو + فیلترها (v1.6.0) — پوسته v1.7.3
		echo '<form method="get" class="dcp-card dcp-filters">';
		echo '<input type="hidden" name="page" value="dastyarc-products">';
		printf( '<input type="search" name="s" value="%s" placeholder="جستجو در محصولات مرکز…" style="min-width:220px">', esc_attr( $search ) );

		// فیلتر دسته‌بندی
		echo '<select name="dcat" style="min-width:170px"><option value="">همه دسته‌بندی‌ها</option>';
		foreach ( $cats_for_filter as $slug => $name ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $dcat, $slug, false ), esc_html( $name ) );
		}
		echo '</select>';

		// فیلتر موجودی
		echo '<select name="dstock" style="min-width:140px"><option value="">موجودی: همه</option>';
		printf( '<option value="instock"%s>فقط موجود</option>', selected( $dstock, 'instock', false ) );
		printf( '<option value="outofstock"%s>فقط ناموجود</option>', selected( $dstock, 'outofstock', false ) );
		echo '</select>';

		// (v1.7.5 — مورد ۶) فیلتر وضعیت افزودن به فروشگاه
		echo '<select name="dimp" style="min-width:160px"><option value="">وضعیت افزودن: همه</option>';
		printf( '<option value="added"%s>افزوده شده به فروشگاهم</option>', selected( $dimp, 'added', false ) );
		printf( '<option value="notadded"%s>هنوز اضافه نشده</option>', selected( $dimp, 'notadded', false ) );
		echo '</select>';
		printf( '<span class="description" style="margin:0">در این صفحه: <strong style="color:#0f7a52">%d</strong> افزوده‌شده / <strong>%d</strong> افزوده‌نشده</span>', (int) $dimp_added, (int) max( 0, $dimp_total - $dimp_added ) );

		// تعداد در صفحه
		echo '<select name="dpp" style="min-width:120px">';
		foreach ( array( 10, 20, 25, 50 ) as $pp ) {
			printf( '<option value="%d"%s>%d مورد در صفحه</option>', $pp, selected( $per, $pp, false ), $pp );
		}
		echo '</select>';

		echo '<button class="button button-primary dcp-btn dcp-btn-g">فیلتر / جستجو</button> ';
		if ( '' !== $search || '' !== $dcat || '' !== $dstock || '' !== $dimp || 20 !== $per ) {
			echo '<a class="button dcp-btn" href="' . esc_url( admin_url( 'admin.php?page=dastyarc-products' ) ) . '">حذف فیلترها</a> ';
		}
		echo '<a class="button dcp-btn" href="' . esc_url( admin_url( 'admin.php?page=dastyarc-products&refresh=1' ) ) . '">به‌روزرسانی از مرکز</a>';
		echo '</form>';

		// نوار ابزار + فرم افزودن دسته‌جمعی
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dastyarc_bulk_import' );
		echo '<input type="hidden" name="action" value="dastyarc_bulk_import">';

		echo '<div class="dcp-card dcp-bulk">';
		echo '<strong>افزودن دسته‌جمعی:</strong> ';
		echo '<label for="dastyarc-import-status">وضعیت محصولات پس از افزودن: ';
		echo '<select name="import_status" id="dastyarc-import-status">';
		printf( '<option value="draft" %s>پیش‌نویس — بعداً خودتان منتشر می‌کنید</option>', selected( $pref_status, 'draft', false ) );
		printf( '<option value="publish" %s>منتشر شده — بلافاصله در فروشگاه نمایش داده شود</option>', selected( $pref_status, 'publish', false ) );
		echo '</select></label> ';
		submit_button( 'افزودن محصولات انتخاب‌شده به فروشگاه', 'primary dcp-btn dcp-btn-g', 'submit', false );
		echo '<span class="description">تیک محصولات موردنظر را بزنید؛ این انتخاب روی دکمه «افزودن به فروشگاه» تک‌تک محصولات هم اعمال می‌شود. برای سینک موجودی چند محصولِ از قبل افزوده‌شده، از لیست «محصولات» ووکامرس اقدام گروهی «سینک موجودی از دستیار» را بزنید.</span>';
		echo '</div>';

		echo '<div class="dcp-card dcp-table-wrap"><table class="widefat striped dcp-table"><thead><tr>';
		echo '<td class="check-column"><input type="checkbox" id="dastyarc-select-all" title="انتخاب همه"></td>';
		echo '<th style="width:50px">تصویر</th><th>عنوان</th><th>SKU</th><th>نوع</th><th>موجودی مرکز</th><th>قیمت تامین</th><th>قیمت شما</th><th>وضعیت</th><th>اقدام</th>';
		echo '</tr></thead><tbody>';

		if ( ! $items ) {
			echo '<tr><td colspan="10" class="dcp-empty">محصولی مطابق فیلترها یافت نشد.</td></tr>';
		}

		foreach ( $items as $item ) {
			$remote_id = (int) $item['id'];
			$local_id  = $imported[ $remote_id ] ?? 0;
			$image     = ! empty( $item['images'][0]['src'] ) ? $item['images'][0]['src'] : wc_placeholder_img_src();
			$supplier  = (float) $item['supplier_price'];
			$your      = $local_id ? DastyarC_Price::calculate( $supplier, $local_id ) : DastyarC_Price::calculate( $supplier );

			// نمایش بازه قیمت محصول متغیر (v1.7.2): به‌جای یک عددِ نادرست، کمینه←بیشینه قیمت تامین/فروش وارییشن‌ها
			$supplier_html = wc_price( $supplier );
			$your_html     = wc_price( (float) $your );
			if ( 'variable' === (string) ( $item['type'] ?? '' ) && ! empty( $item['variations'] ) ) {
				$var_prices = array();
				foreach ( (array) $item['variations'] as $vv ) {
					$vv = (array) $vv; // آرایه یا استانداردآبجکت هر دو پذیرفته می‌شود
					$vp = (float) ( $vv['supplier_price'] ?? 0 );
					if ( $vp > 0 ) {
						$var_prices[] = $vp;
					}
				}
				if ( $var_prices ) {
					$v_min  = min( $var_prices );
					$v_max  = max( $var_prices );
					$y_min  = (float) ( $local_id ? DastyarC_Price::calculate( $v_min, $local_id ) : DastyarC_Price::calculate( $v_min ) );
					$y_max  = (float) ( $local_id ? DastyarC_Price::calculate( $v_max, $local_id ) : DastyarC_Price::calculate( $v_max ) );
					$supplier_html = $v_min === $v_max ? wc_price( $v_min ) : wc_price( $v_min ) . ' <small>تا</small> ' . wc_price( $v_max );
					$your_html     = $y_min === $y_max ? wc_price( $y_min ) : wc_price( $y_min ) . ' <small>تا</small> ' . wc_price( $y_max );
				}
			}

			echo '<tr>';
			if ( $local_id ) {
				echo '<td class="check-column"></td>';
			} else {
				printf( '<th scope="row" class="check-column"><input type="checkbox" class="dastyarc-cb" name="remote_ids[]" value="%d"></th>', (int) $remote_id );
			}
			printf( '<td><img class="dcp-img" src="%s" alt=""></td>', esc_url( $image ) );
			printf( '<td><strong class="dcp-title">%s</strong></td>', esc_html( $item['name'] ) );
			printf( '<td><span class="dcp-sku">%s</span></td>', esc_html( (string) $item['sku'] ?: '—' ) );
			printf(
				'<td><span class="dcp-pill %s">%s</span></td>',
				'variable' === $item['type'] ? 'dcp-pill-var' : 'dcp-pill-simple',
				'variable' === $item['type'] ? 'متغیر' : 'ساده'
			);
			// وضعیت موجودی مرکز (v1.6.0)
			$rstock = (string) ( $item['stock_status'] ?? 'instock' );
			printf(
				'<td><span class="dcp-pill %s">%s</span></td>',
				'outofstock' === $rstock ? 'dcp-pill-out' : 'dcp-pill-in',
				'outofstock' === $rstock ? 'ناموجود' : 'موجود'
			);
			printf( '<td><span class="dcp-price">%s</span></td>', wp_kses_post( $supplier_html ) );
			printf( '<td><span class="dcp-price dcp-your"><strong>%s</strong></span></td>', wp_kses_post( $your_html ) );
			if ( $local_id && isset( $detached[ $remote_id ] ) ) {
				// (v1.7.8) خارج‌شده از دراپ‌شیپینگ — ارسال از انبار خود فروشنده
				echo '<td><span class="dcp-pill dcp-detached">خارج از دراپ‌شیپینگ (انبار شما)</span></td>';
			} elseif ( $local_id ) {
				$status_obj = get_post_status_object( get_post_status( $local_id ) );
				printf( '<td><span class="dcp-pill dcp-added">✔ افزوده شده (%s)</span></td>', esc_html( $status_obj ? $status_obj->label : '' ) );
			} else {
				echo '<td>—</td>';
			}
			echo '<td class="dcp-actions">';
			if ( $local_id && isset( $detached[ $remote_id ] ) ) {
				printf(
					'<a class="button dcp-btn" href="%s">ویرایش محصول</a> <a class="button button-primary dcp-btn dcp-btn-g" href="%s">اتصال مجدد به دستیار</a>',
					esc_url( admin_url( 'post.php?post=' . $local_id . '&action=edit' ) ),
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_reattach&remote_id=' . $remote_id ), 'dastyarc_reattach_' . $remote_id ) )
				);
			} elseif ( $local_id ) {
				printf(
					'<a class="button dcp-btn" href="%s">ویرایش محصول</a> <a class="button dcp-btn" href="%s">سینک مجدد</a> <a class="button dcp-btn dcp-btn-detach" href="%s" title="خروج موقت از دراپ‌شیپینگ — ارسال از انبار خودتان؛ هر زمان قابل برگشت است">قطع اتصال</a>',
					esc_url( admin_url( 'post.php?post=' . $local_id . '&action=edit' ) ),
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_resync&remote_id=' . $remote_id ), 'dastyarc_resync_' . $remote_id ) ),
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_detach&remote_id=' . $remote_id ), 'dastyarc_detach_' . $remote_id ) )
				);
			} else {
				printf(
					'<a class="button button-primary dcp-btn dcp-btn-g dastyarc-add-single" href="%s">افزودن به فروشگاه</a>',
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_import&remote_id=' . $remote_id . '&status=' . $pref_status ), 'dastyarc_import_' . $remote_id ) )
				);
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';

		// صفحه‌بندی (v1.7.8 — بازخورد کاربر): به‌جای فهرست طولانی شماره‌ها → «قبلی | صفحه N از M | بعدی» با آیکون
		$pages = max( 1, (int) ceil( $total / $per ) );
		if ( $pages > 1 ) {
			$pg_url = function ( $p ) use ( $search, $dcat, $dstock, $dimp, $per ) {
				return esc_url( add_query_arg( array(
					'page'   => 'dastyarc-products',
					'paged'  => max( 1, (int) $p ),
					's'      => $search,
					'dcat'   => $dcat,
					'dstock' => $dstock,
					'dimp'   => $dimp,
					'dpp'    => $per,
				), admin_url( 'admin.php' ) ) );
			};
			$chev_r = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>'; // چپ‌راست (قبلی در RTL)
			$chev_l = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>'; // راست‌چپ (بعدی در RTL)
			echo '<div class="tablenav bottom"><div class="tablenav-pages dcp-pages dcp-pages-nav">';
			if ( $page > 1 ) {
				printf( '<a class="button dcp-btn dcp-nav" href="%s"><span class="dcp-icn">%s</span> قبلی</a>', $pg_url( $page - 1 ), $chev_r );
			} else {
				echo '<span class="button dcp-btn dcp-nav dcp-nav-off"><span class="dcp-icn">' . $chev_r . '</span> قبلی</span>';
			}
			printf( '<span class="dcp-pageinfo">صفحه <strong>%d</strong> از <strong>%d</strong><span class="dcp-muted"> — %d محصول</span></span>', (int) $page, (int) $pages, (int) $total );
			if ( $page < $pages ) {
				printf( '<a class="button dcp-btn dcp-nav" href="%s">بعدی <span class="dcp-icn">%s</span></a>', $pg_url( $page + 1 ), $chev_l );
			} else {
				echo '<span class="button dcp-btn dcp-nav dcp-nav-off">بعدی <span class="dcp-icn">' . $chev_l . '</span></span>';
			}
			echo '</div></div>';
		}
		echo '</form>';

		// اسکریپت‌های کمکی صفحه: انتخاب همه + اعمال وضعیت انتخابی روی دکمه‌های تک‌محصولی
		?>
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

		echo '</div>';
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
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-products' ) );
			exit;
		}

		if ( 'publish' === $status ) {
			$this->notice( 'محصول با موفقیت به فروشگاه اضافه و بلافاصله «منتشر» شد.' );
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-products' ) );
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
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-products' ) );
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
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-products' ) );
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
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-products' ) );
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
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-products' ) );
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
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-products' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * صفحه تنظیمات
	 * ---------------------------------------------------------------- */

	public function page_settings() {
		// (v1.7.5 — مورد ۸) پوسته گرافیکی هماهنگ با سایت مرکز: همان زبان dcp (سرصفحه + چیپ نسخه + کارت‌های سفید)
		self::dcp_shell_css();
		echo '<style>' .
			'.dcp-settings h2.title{font-size:14px;font-weight:800;color:#242536;margin:18px 0 0;padding:0 2px}' .
			'.dcp-settings h2.title:before{content:"";display:inline-block;width:9px;height:9px;border-radius:50%;background:#17a16d;margin-left:8px;box-shadow:0 0 0 3px rgba(23,161,109,.15)}' .
			'.dcp-settings .form-table{background:#fff;border:1px solid #e6e8ef;border-radius:14px;padding:8px 20px 16px;margin:8px 0 6px;box-shadow:0 1px 3px rgba(16,24,40,.05)}' .
			'.dcp-settings .form-table th{color:#242536;font-weight:700;padding:16px 10px 16px 0}' .
			'.dcp-settings .form-table td{padding:14px 10px}' .
			'.dcp-settings .form-table input[type=text],.dcp-settings .form-table input[type=url],.dcp-settings .form-table input[type=number],.dcp-settings .form-table input[type=password],.dcp-settings .form-table select,.dcp-settings .form-table textarea{border:1px solid #dcdce4;border-radius:9px;padding:8px 12px;background:#fafbfc;box-shadow:none;transition:border-color .15s,box-shadow .15s}' .
			'.dcp-settings .form-table input:focus,.dcp-settings .form-table select:focus,.dcp-settings .form-table textarea:focus{border-color:#17a16d;box-shadow:0 0 0 3px rgba(23,161,109,.15);outline:none}' .
			'.dcp-settings .description{color:#787c86;font-size:11.5px}' .
			'.dcp-settings .submit .button-primary,.dcp-settings .button-primary{border-radius:9px!important;min-height:38px;line-height:1;padding:0 22px!important;font-weight:800;background:#17a16d!important;border-color:#17a16d!important;box-shadow:none!important}' .
			'.dcp-settings .submit .button-primary:hover,.dcp-settings .button-primary:hover{background:#12875c!important;border-color:#12875c!important}' .
			'.dcp-settings hr{border:0;border-top:1px dashed #e6e8ef;margin:20px 0}' .
			'</style>';
		echo '<div class="wrap dcp-wrap dcp-settings">';
		$this->dcp_page_head( 'تنظیمات دستیار', 'اتصال به مرکز، فرمول قیمت‌گذاری، همگام‌سازی و گزینه‌های نمایشی فروشگاه شما.' );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dastyarc_save_settings' );
		echo '<input type="hidden" name="action" value="dastyarc_save_settings">';
		// نشانگر «فرم کامل تنظیمات» — فقط از همین صفحه ارسال می‌شود تا فرم‌های کوتاه (مثل فرم قیمت‌گذاری پنل) تنظیمات دیگر را بازنشانی نکنند (v1.5.0)
		echo '<input type="hidden" name="dastyarc_full_settings" value="1">';

		echo '<h2 class="title">اتصال به سایت مرکزی</h2><table class="form-table">';
		printf(
			'<tr><th><label>آدرس سایت مرکزی</label></th><td><input type="url" name="dastyarc_central_url" class="regular-text" value="%s" placeholder="https://dastyar.shop" style="direction:ltr"></td></tr>',
			esc_attr( get_option( 'dastyarc_central_url' ) )
		);
		printf(
			'<tr><th><label>API Key فروشنده</label></th><td><input type="text" name="dastyarc_api_key" class="regular-text" value="%s" style="direction:ltr"> <p class="description">از پنل فروشنده در سایت مرکزی: حساب کاربری ← اتصال API</p></td></tr>',
			esc_attr( get_option( 'dastyarc_api_key' ) )
		);
		echo '</table>';

		echo '<h2 class="title">فرمول قیمت‌گذاری</h2><table class="form-table">';
		$mode = get_option( 'dastyarc_price_mode', 'percent' );
		echo '<tr><th>حالت قیمت‌گذاری</th><td>';
		printf( '<label><input type="radio" name="dastyarc_price_mode" value="percent" %s> درصد افزایش نسبت به قیمت تامین</label><br>', checked( $mode, 'percent', false ) );
		printf( '<label><input type="radio" name="dastyarc_price_mode" value="fixed" %s> افزایش مبلغ ثابت روی قیمت تامین</label>', checked( $mode, 'fixed', false ) );
		echo '</td></tr>';
		printf(
			'<tr><th><label>مقدار</label></th><td><input type="number" step="any" name="dastyarc_price_value" value="%s"> <span class="description">در حالت درصدی: عدد درصد (مثل 30) — در حالت ثابت: مبلغ (مثل 200000)</span></td></tr>',
			esc_attr( get_option( 'dastyarc_price_value', 30 ) )
		);
		$round = get_option( 'dastyarc_price_round', 'none' );
		echo '<tr><th><label>گرد کردن قیمت</label></th><td><select name="dastyarc_price_round">';
		foreach ( array( 'none' => 'بدون گرد کردن', '10' => 'نزدیک‌ترین ۱۰', '100' => 'نزدیک‌ترین ۱۰۰', '1000' => 'نزدیک‌ترین ۱۰۰۰' ) as $val => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $round, $val, false ), esc_html( $label ) );
		}
		echo '</select></td></tr>';

		// نحوه اعمال فرمول با تغییر قیمت مرکز (v1.6.0 — مورد ۱۱)
		$apply_mode = get_option( 'dastyarc_price_apply_mode', 'auto' );
		echo '<tr><th><label>اعمال به‌روزرسانی قیمت مرکز</label></th><td>';
		printf( '<label style="display:block;margin-bottom:6px"><input type="radio" name="dastyarc_price_apply_mode" value="auto" %s> <strong>خودکار</strong> — با هر تغییر قیمت در مرکز، فرمول قیمت‌گذاری شما بلافاصله روی فروشگاه اعمال شود</label>', checked( $apply_mode, 'auto', false ) );
		printf( '<label style="display:block;margin-bottom:6px"><input type="radio" name="dastyarc_price_apply_mode" value="manual" %s> <strong>دستی</strong> — قیمت تامین جدید فقط ثبت شود؛ اعمال آن با دکمه «اعمال به‌روزرسانی قیمت‌ها» به انتخاب شما</label>', checked( $apply_mode, 'manual', false ) );
		if ( 'manual' === $apply_mode ) {
			printf(
				'<p style="margin:8px 0 0"><a class="button" style="background:#17a16d;border-color:#17a16d;color:#fff;border-radius:8px" href="%s">✔ اعمال به‌روزرسانی قیمت‌ها (اکنون)</a> <span class="description">این دکمه در صفحه «ویرایش دسته‌جمعی دستیار» هم هست.</span></p>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_apply_prices' ), 'dastyarc_apply_prices' ) )
			);
		}
		echo '<p class="description">در هر دو حالت، «فرمول» (درصد/مبلغ + گرد کردن + Override محصولی) مبنای قیمت نهایی است و موجودی همیشه خودکار همگام می‌شود.</p></td></tr></table>';

		echo '<h2 class="title">همگام‌سازی و سفارش‌ها</h2><table class="form-table">';
		printf(
			'<tr><th>موجودی</th><td><label><input type="checkbox" name="dastyarc_stock_sync" value="yes" %s> همگام‌سازی خودکار موجودی با مرکز</label></td></tr>',
			checked( get_option( 'dastyarc_stock_sync', 'yes' ), 'yes', false )
		);
		// (v1.7.8 — بازخورد کاربر) انتقال «دسته‌بندی محصولات» مرکز به انتخاب فروشنده
		printf(
			'<tr><th>دسته‌بندی محصولات</th><td><label><input type="checkbox" name="dastyarc_import_cats" value="yes" %s> انتقال دسته‌بندی‌های دستیار شاپ هنگام افزودن محصول</label><p class="description">اگر غیرفعال کنید، محصولات <strong>بدون دسته‌بندی‌های مرکز</strong> به فروشگاه شما اضافه می‌شوند و شما خودتان دسته‌بندی‌شان را تعیین می‌کنید. (محصولاتی که قبلاً اضافه شده‌اند تغییری نمی‌کنند)</p></td></tr>',
			checked( get_option( 'dastyarc_import_cats', 'yes' ), 'yes', false )
		);

		// حالت انتقال سفارش‌ها: خودکار یا دستی (به انتخاب فروشنده)
		$order_mode = get_option( 'dastyarc_order_mode', 'auto' );
		echo '<tr><th>حالت انتقال سفارش‌ها به مرکز</th><td>';
		printf(
			'<label style="display:block;margin-bottom:8px"><input type="radio" name="dastyarc_order_mode" value="auto" %s> <strong>خودکار</strong> — به‌محض رسیدن سفارش به وضعیت‌های انتخابیِ پایین، به‌صورت خودکار به دستیار شاپ ارسال می‌شود.</label>',
			checked( $order_mode, 'auto', false )
		);
		printf(
			'<label style="display:block"><input type="radio" name="dastyarc_order_mode" value="manual" %s> <strong>دستی</strong> — هیچ ارسال خودکاری انجام نمی‌شود؛ شما از لیست سفارش‌های ووکامرس (ستون «دستیار» یا اقدام دسته‌جمعی «ارسال به دستیار شاپ») سفارش‌ها را ارسال می‌کنید.</label>',
			checked( $order_mode, 'manual', false )
		);
		echo '</td></tr>';

		$statuses = (array) get_option( 'dastyarc_send_statuses', array( 'processing' ) );
		echo '<tr><th>ارسال خودکار سفارش به مرکز در وضعیت</th><td class="dastyarc-statuses-cell">';
		foreach ( wc_get_order_statuses() as $slug => $label ) {
			$slug_clean = str_replace( 'wc-', '', $slug );
			printf( '<label style="margin-left:14px"><input type="checkbox" name="dastyarc_send_statuses[]" value="%s" %s> %s</label>', esc_attr( $slug_clean ), checked( in_array( $slug_clean, $statuses, true ), true, false ), esc_html( $label ) );
		}
		echo '<p class="description">فقط در حالت «خودکار» اعمال می‌شود.</p></td></tr>';
		printf(
			'<tr><th>کد رهگیری</th><td><label><input type="checkbox" name="dastyarc_tracking_completes" value="yes" %s> بعد از دریافت کد رهگیری از مرکز، سفارش «تکمیل شده» شود</label></td></tr>',
			checked( get_option( 'dastyarc_tracking_completes', 'yes' ), 'yes', false )
		);
		echo '</table>';

		// نمایش فروشگاه (v1.5.0)
		echo '<h2 class="title">نمایش فروشگاه</h2><table class="form-table">';
		printf(
			'<tr><th>نشان انبار</th><td><label><input type="checkbox" name="dastyarc_warehouse_badge" value="yes" %s> نمایش «🚚 ارسال از انبار بندرگناوه» بالای عنوان محصولات دستیار (طرح برند سازمانی)</label><p class="description">فقط روی محصولاتی نمایش داده می‌شود که از دستیار شاپ به فروشگاه اضافه شده‌اند.</p></td></tr>',
			checked( DastyarC_Badge::enabled(), true, false )
		);
		echo '</table>';

		// بخش‌های صفحه «آمار» فروشنده (قابل فعال/غیرفعال — v1.7.0: از پیشخوان وردپرس به منوی دستیار منتقل شد)
		echo '<h2 class="title">بخش‌های صفحه آمار</h2><table class="form-table"><tr><th>صفحه «دستیار شاپ ← آمار»</th><td>';
		foreach ( DastyarC_Dashboard::widgets() as $key => $label ) {
			printf(
				'<label style="display:block;margin-bottom:6px"><input type="checkbox" name="dastyarc_widgets_enabled[%s]" value="1" %s> %s</label>',
				esc_attr( $key ),
				checked( DastyarC_Dashboard::enabled( $key ), true, false ),
				esc_html( $label )
			);
		}
		echo '<p class="description">این بخش‌ها با تم رنگی سازمانی در صفحه «دستیار شاپ ← آمار» نمایش داده می‌شوند (راه‌اندازی، وضعیت اتصال، سفارش‌های دستیار ★ و عودت و مرجوعی). از نسخه ۱.۷.۰ ابزارک‌ها از «پیشخوان وردپرس» حذف و به صفحه آمار منتقل شده‌اند.</p></td></tr>';

		// حالت تاریک (v1.5.0)
		echo '<tr><th>حالت تاریک سازمانی</th><td>';
		printf(
			'<label><input type="checkbox" name="dastyarc_dark_mode" value="yes" %s> فعال — ابزارک‌های پیشخوان، منوی دستیار و متاباکس سفارش با پوسته تیره سازمانی نمایش داده شوند</label>',
			checked( DastyarC_Dashboard::dark(), true, false )
		);
		echo '</td></tr></table>';

		submit_button( 'ذخیره تنظیمات' );
		echo '</form>';

		// با انتخاب حالت «دستی»، وضعیت‌های ارسال خودکار غیرفعال به نظر برسند
		?>
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
		<?php

		// تست اتصال
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:20px">';
		wp_nonce_field( 'dastyarc_test' );
		echo '<input type="hidden" name="action" value="dastyarc_test">';
		submit_button( 'تست اتصال به مرکز', 'secondary', 'submit', false );
		echo '</form>';

		// ─── همگام‌سازی فوری موجودی (رفع سریع ناموجود/موجود شدن‌ها) ───
		$last_stock = (int) get_option( 'dastyarc_last_stock_sync', 0 );
		echo '<hr><h2 class="title">همگام‌سازی موجودی با مرکز</h2><table class="form-table"><tr><td>';
		printf(
			'<p class="description">موجودی محصولات دستیار به‌صورت خودکار هر ۱۵ دقیقه + هنگام تغییر در مرکز همگام می‌شود. آخرین اجرا: %s — برای سینک سریع، دکمه «سینک کامل موجودی» ابزارک اتصال در صفحه پیشخوان هم هست.</p>',
			$last_stock ? esc_html( date_i18n( 'Y/m/d H:i', $last_stock ) ) : '—'
		);
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		wp_nonce_field( 'dastyarc_stock_sync_now' );
		echo '<input type="hidden" name="action" value="dastyarc_stock_sync_now">';
		submit_button( '🔄 همگام‌سازی فوری موجودی الان', 'secondary', 'submit', false );
		echo '</form></td></tr></table>';

		// ─── قوانین عودت (فقط‌خواندنی — از مرکز می‌آید) ───
		$rules_now  = trim( (string) get_option( 'dastyarc_rma_rules', '' ) );
		$rules_sync = (string) get_option( 'dastyarc_rules_synced_at', '' );
		echo '<h2 class="title">قوانین عودت و مرجوعی</h2><table class="form-table"><tr><td>';
		echo '<div style="background:#f2faf6;border:1px solid #cdeee0;border-right:4px solid #17a16d;border-radius:12px;padding:14px 18px;max-width:720px;line-height:2">';
		if ( '' !== $rules_now ) {
			echo nl2br( esc_html( $rules_now ) );
			printf( '<p class="description" style="margin:8px 0 0">آخرین همگام‌سازی از مرکز: %s</p>', esc_html( $rules_sync ?: '—' ) );
		} else {
			echo '<p style="margin:0" class="description">قوانین عودت توسط <strong>سایت مرکزی (دستیار شاپ)</strong> تعیین می‌شود و اینجا فقط نمایش داده می‌شود. هنوز متنی از مرکز دریافت نشده — تا دریافت، متن پیش‌فرض روی برگه عمومی نمایش داده می‌شود.</p>';
		}
		echo '</div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:10px">';
		wp_nonce_field( 'dastyarc_rules_refresh' );
		echo '<input type="hidden" name="action" value="dastyarc_rules_refresh">';
		submit_button( '📜 دریافت مجدد قوانین از مرکز', 'secondary', 'submit', false );
		echo '</form></td></tr></table>';

		// برگه عمومی پیگیری سفارش
		$track_page_id = (int) get_option( DastyarC_Track::PAGE_OPTION );
		echo '<hr><h2 class="title">برگه «پیگیری سفارش» (برای مشتریان شما)</h2><table class="form-table"><tr><td>';
		if ( $track_page_id && 'page' === get_post_type( $track_page_id ) ) {
			printf( '<a href="%s" target="_blank" class="button">مشاهده برگه</a> ', esc_url( get_permalink( $track_page_id ) ) );
			printf( '<a href="%s" class="button">ویرایش برگه</a>', esc_url( admin_url( 'post.php?post=' . $track_page_id . '&action=edit' ) ) );
			echo '<p class="description">مشتریان شما در این برگه با شماره سفارش یا شماره تماس، وضعیت سفارش و کد رهگیری را می‌بینند. شورتکد: <code>[dastyarc_track]</code> (قابل استفاده در هر برگه‌ای)</p>';
		} else {
			echo '<p>برگه به‌صورت خودکار هنگام فعال‌سازی ساخته می‌شود. اگر حذف شده، افزونه را یک‌بار غیرفعال و دوباره فعال کنید.</p>';
		}
		echo '</td></tr></table>';

		// عودت و مرجوعی (RMA): لینک برگه عمومی (متن قوانین داخل فرم اصلی بالاست)
		$rma_page_id = (int) get_option( DastyarC_Rma::PAGE_OPTION );
		echo '<hr><h2 class="title">برگه عمومی «عودت و مرجوعی»</h2><table class="form-table">';
		echo '<tr><td>';
		if ( $rma_page_id && 'page' === get_post_type( $rma_page_id ) ) {
			printf( '<a href="%s" target="_blank" class="button">مشاهده برگه</a> ', esc_url( get_permalink( $rma_page_id ) ) );
			printf( '<a href="%s" class="button">ویرایش برگه</a> ', esc_url( admin_url( 'post.php?post=' . $rma_page_id . '&action=edit' ) ) );
			printf( '<a href="%s" class="button button-primary" style="background:#17a16d;border-color:#17a16d">مدیریت گزارش‌های عودت</a>', esc_url( admin_url( 'admin.php?page=dastyarc-rma' ) ) );
			echo '<p class="description">مشتری گزارش عودت را در این برگه ثبت می‌کند؛ شما پس از بازبینی آن را برای دستیار شاپ ارسال می‌کنید و نتیجه همین‌جا دیده می‌شود. شورتکد: <code>[dastyarc_rma]</code> (قابل استفاده در هر برگه‌ای)</p>';
		} else {
			echo '<p>برگه «عودت و مرجوعی کالا» به‌صورت خودکار ساخته می‌شود. اگر حذف شده، افزونه را یک‌بار غیرفعال و دوباره فعال کنید.</p>';
		}
		echo '</td></tr></table>';

		echo '</div>';
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
			// ابزارک‌های پیشخوان
			$widgets = array();
			foreach ( array_keys( DastyarC_Dashboard::widgets() ) as $key ) {
				$widgets[ $key ] = ! empty( $_POST['dastyarc_widgets_enabled'][ $key ] ) ? 1 : 0;
			}
			update_option( DastyarC_Dashboard::OPTION, $widgets );
			// نشان انبار + حالت تاریک (v1.5.0)
			update_option( DastyarC_Badge::OPTION, ! empty( $_POST['dastyarc_warehouse_badge'] ) ? 'yes' : 'no' );
			update_option( 'dastyarc_dark_mode', ! empty( $_POST['dastyarc_dark_mode'] ) ? 'yes' : 'no' );
		}

		delete_transient( 'dastyarc_central_cats' ); // کش فیلتر دسته‌بندی‌ها تازه شود (v1.6.0)

		$this->notice( 'تنظیمات ذخیره شد.' );
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-settings' ) );
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
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-settings' ) );
		exit;
	}

	/** همگام‌سازی فوری موجودی (دکمه تنظیمات) */
	public function handle_stock_sync_now() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_stock_sync_now' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$this->run_full_stock_sync();
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-settings' ) );
		exit;
	}

	/** سینک کامل موجودی از صفحه آمار/قدیماً ابزارک → بازگشت به همان صفحه (v1.5.0 / v1.7.0: پیش‌فرض صفحه آمار) */
	public function handle_full_sync() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_full_sync' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$this->run_full_stock_sync();
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=dastyarc-stats' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * صفحه «آمار دستیار شاپ» — جایگزین ابزارک‌های پیشخوان وردپرس (v1.7.0)
	 * ---------------------------------------------------------------- */

	public function page_stats() {
		if ( DastyarC::instance()->dashboard ) {
			DastyarC::instance()->dashboard->render_page();
		}
	}

	/* ------------------------------------------------------------------
	 * صفحه «کیف پول» — موجودی + تراکنش‌ها + شارژ از طریق مرکز (v1.7.0)
	 * ---------------------------------------------------------------- */

	/**
	 * اسنپ‌شات کیف پول از مرکز (کش ۶۰ ثانیه‌ای تا صفحه سریع بماند) — قابل تست جداگانه.
	 * @return array{balance:float,transactions:array<int,object>}|WP_Error
	 */
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

	public function page_wallet() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}

		// «بازخوانی» دستی ← کش ۶۰ ثانیه‌ای شکسته می‌شود
		$force = ! empty( $_GET['wallet_refresh'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_wallet_refresh' );
		if ( $force ) {
			delete_transient( 'dastyarc_wallet_snap' );
		}
		$snap = self::wallet_snapshot( $force );

		echo '<div class="wrap dastyarc-wallet-page">';
		// v1.7.1 — فاصله‌بندی اصلاح‌شده هیرو: line-height استاندارد + حاشیه‌های تنفسی + حذف letter-spacing منفی (برای فارسی نامناسب بود)
		echo '<style>' .
			'.dastyarc-wallet-page .dc-wal{max-width:980px;margin:16px 0}' .
			'.dc-wal-hero{display:flex;align-items:center;justify-content:space-between;gap:22px;flex-wrap:wrap;background:linear-gradient(135deg,#242536,#1a1b2c);border-radius:18px;padding:28px 30px;color:#fff;box-shadow:0 10px 30px rgba(36,37,54,.18);margin-bottom:18px}' .
			'.dc-wal-hero h2{margin:0 0 12px;font-size:15px;font-weight:800;color:#bff3de;display:flex;align-items:center;gap:8px;line-height:2}' .
			'.dc-wal-bal{font-size:32px;font-weight:900;color:#fff;line-height:1.6;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;margin:2px 0 6px}' .
			'.dc-wal-bal small{font-size:12.5px;color:#a9dcc4;font-weight:600;line-height:1.9}' .
			'.dc-wal-hero .dc-wal-note{font-size:12px;color:#a8bcc9;margin-top:14px;line-height:2.1;max-width:560px}' .
			'.dc-wal-card{background:#fff;border:1.5px solid #cdeee0;border-radius:16px;padding:20px 20px;box-shadow:0 4px 16px rgba(23,161,109,.07);margin-bottom:16px}' .
			'.dc-wal-card h3{margin:0 0 12px;font-size:14px;color:#242536;line-height:2}' .
			'.dc-wal-quick{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}' .
			'.dc-wal-q{display:inline-flex;padding:8px 16px;border-radius:10px;border:1.5px solid #17a16d;background:#f4fbf8;color:#0f7a52;font-weight:800;font-size:12.5px;cursor:pointer}' .
			'.dc-wal-q:hover{background:#17a16d;color:#fff}' .
			'.dc-wal-charge{display:flex;gap:8px;align-items:end;flex-wrap:wrap}' .
			'.dc-wal-charge input[type=number]{min-width:200px;padding:8px 10px;border:1px solid #cdd8d3;border-radius:10px}' .
			'.dc-wal-btn{background:#17a16d!important;border-color:#17a16d!important;color:#fff!important;font-weight:800;padding:9px 22px!important;border-radius:10px!important;height:auto!important;cursor:pointer}' .
			'.dc-wal-table{width:100%;border-collapse:collapse}' .
			'.dc-wal-table th{background:#f4fbf8;color:#284436;font-size:12px;padding:9px 10px;text-align:right;border-bottom:2px solid #cdeee0}' .
			'.dc-wal-table td{padding:9px 10px;border-bottom:1px solid #edf4f0;font-size:12.5px;color:#33524a}' .
			'.dc-wal-amt-ok{color:#0f7a52;font-weight:800}.dc-wal-amt-neg{color:#c0392b;font-weight:800}' .
			'.dc-wal-muted{color:#687a72;font-size:12px}' .
			'.dc-wal-alert{background:#fdecea;border:1px solid #f0a9a3;border-radius:12px;padding:10px 14px;color:#8c2b23;margin-bottom:14px}' .
		'</style>';

		echo '<div class="dc-wal"><h1 class="dc-wal-pagetitle screen-reader-text">کیف پول دستیار شاپ</h1>';

		if ( is_wp_error( $snap ) ) {
			// v1.7.1 — پیام RAW لاتین (خطای ۴۰۴/cURL) به پیام فارسیِ قابل‌فهمِ راهنما ترجمه می‌شود
			echo '<div class="dc-wal-alert">⚠ ' . esc_html( self::friendly_api_error( $snap ) ) . '</div>';
			echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=dastyarc-settings' ) ) . '">تنظیمات اتصال</a></p>';
			echo '</div></div>';
			return;
		}

		$balance     = (float) $snap['balance'];
		$refresh_url = wp_nonce_url( admin_url( 'admin.php?page=dastyarc-wallet&wallet_refresh=1' ), 'dastyarc_wallet_refresh' );

		// هیرو موجودی
		echo '<div class="dc-wal-hero"><div>' .
			'<h2>👛 کیف پول شما در دستیار شاپ</h2>' .
			'<div class="dc-wal-bal">' . number_format( $balance ) . ' <small>تومان موجود است</small></div>' .
			'<div class="dc-wal-note">کیف پول شما برای تسویه فاکتورهای دستیار به‌کار می‌رود؛ مبلغ پس از پرداخت موفق در مرکز خودکار شارژ می‌شود.</div>' .
		'</div>' .
		'<div><a class="button" href="' . esc_url( $refresh_url ) . '">↻ بازخوانی</a></div>' .
		'</div>';

		// کارت شارژ: مبالغ پیشنهادی یک‌دکمه‌ای + مبلغ دلخواه
		echo '<div class="dc-wal-card"><h3>شارژ کیف پول</h3>';
		echo '<p class="dc-wal-muted">پس از کلیک، به درگاه پرداخت دستیار شاپ (سایت مرکز) هدایت می‌شوید؛ پس از پرداخت موفق کیف پول خودکار شارژ می‌شود.</p>';
		$quick = (array) apply_filters( 'dastyarc_wallet_quick_amounts', array( 5000000, 10000000, 20000000, 50000000 ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dastyarc_wallet_charge' );
		echo '<input type="hidden" name="action" value="dastyarc_wallet_charge">';
		echo '<div class="dc-wal-quick">';
		// v1.7.1 — دکمه‌های شارژ فوری نام جدا (dastyarc_quick_amount) دارند تا با فیلد «amount» تداخل نکنند
		// (هم‌نام بودن باعث می‌شد مقدار خالی فیلد دلخواه روی مبلغ دکمه بنشیند و خطای «مبلغ معتبر نیست» داده شود)
		foreach ( array_filter( array_map( 'absint', $quick ) ) as $amt ) {
			printf( '<button class="dc-wal-q" type="submit" name="dastyarc_quick_amount" value="%d">شارژ %s تومان</button>', (int) $amt, number_format( (int) $amt ) );
		}
		echo '</div>';
		echo '<div class="dc-wal-charge">' .
			'<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#4d6b5f">مبلغ دلخواه (تومان)' .
			'<input type="number" min="1000" step="1000" name="amount" placeholder="مثلاً 8000000"></label>' .
			'<button class="dc-wal-btn" type="submit">💳 پرداخت و شارژ</button>' .
		'</div>';
		echo '</form></div>';

		// تراکنش‌های اخیر
		echo '<div class="dc-wal-card"><h3>آخرین تراکنش‌های کیف پول</h3>';
		$txns = array_slice( (array) $snap['transactions'], 0, 20 );
		if ( $txns ) {
			echo '<table class="dc-wal-table"><thead><tr><th>شرح</th><th>مبلغ</th><th>موجودی بعد</th><th>زمان</th></tr></thead><tbody>';
			foreach ( $txns as $tx ) {
				$tx = is_object( $tx ) ? $tx : (object) $tx; // پاسخ JSON مرکز ← آرایه
				$type   = (string) ( $tx->type ?? '' );
				$amount = (float) ( $tx->amount ?? 0 );
				$after  = (float) ( $tx->balance_after ?? 0 );
				$when   = (string) ( $tx->date_fa ?? '' );
				if ( '' === $when ) {
					$when = DastyarC_Jalali::dt( (string) ( $tx->created_at ?? '' ) );
				}
				$sign   = 'credit' === $type ? '+' : '−';
				$cls    = 'credit' === $type ? 'dc-wal-amt-ok' : 'dc-wal-amt-neg';
				printf(
					'<tr><td>%s</td><td><span class="%s">%s %s تومان</span></td><td>%s تومان</td><td>%s</td></tr>',
					esc_html( (string) ( $tx->description ?? ( 'credit' === $type ? 'واریز' : 'برداشت' ) ) ),
					esc_attr( $cls ),
					esc_html( $sign ),
					number_format( $amount ),
					number_format( $after ),
					esc_html( $when )
				);
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="dc-wal-muted">هنوز تراکنشی ثبت نشده است؛ با اولین شارژ، تراکنش‌ها اینجا دیده می‌شوند.</p>';
		}
		echo '</div>';

		echo '</div></div>'; // .dc-wal /.wrap
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
		$back      = admin_url( 'admin.php?page=dastyarc-wallet' );
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
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-settings' ) );
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

		// بخش پایین: کد رهگیری دستی (فقط سفارش‌های غیردستیار قابل ویرایش است)
		$self_track   = $order->get_meta( '_dastyarc_self_tracking_code' );
		$self_carrier = $order->get_meta( '_dastyarc_self_tracking_carrier' );
		echo '<div class="dc-trackcard' . ( $has_items ? ' dc-trackcard-dim' : '' ) . '" style="margin-top:10px">';
		echo '<p class="dc-tc-title">📮 کد رهگیری سفارش (ارسال توسط شما):</p>';
		if ( $has_items ) {
			echo '<p class="description" style="margin:0">چون این سفارش محصول دستیار دارد، رهگیری آن فقط از طریق کد رهگیری دستیارشاپ (بالا) انجام می‌شود.</p>';
			if ( $self_track ) {
				printf( '<p style="margin:6px 0 0"><small>کد دستی ثبت‌شده: <code dir="ltr">%s</code>%s</small></p>', esc_html( (string) $self_track ), $self_carrier ? ' (' . esc_html( (string) $self_carrier ) . ')' : '' );
			}
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
		if ( DastyarC_Orders::has_remote_items( $order ) ) {
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

	public function page_bulk_edit() {
		echo '<div class="wrap"><h1 class="wp-heading-inline">ویرایش دسته‌جمعی دستیار</h1>';

		if ( isset( $_GET['bkbd_done'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>✔ ویرایش دسته‌جمعی روی %d محصول اعمال شد.</p></div>', (int) $_GET['bkbd_done'] );
		}
		// وضعیت فرمول دستی (مورد ۱۱): اگر به‌روزرسانی‌ها در صف اعمال باشند، دکمه اعمال همان‌جا دیده می‌شود
		$pending = (int) count( (array) get_posts( array(
			'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1,
			'meta_key' => '_dastyar_price_pending', 'meta_value' => 1,
		) ) );
		if ( $pending > 0 ) {
			echo '<div class="notice notice-warning is-dismissible"><p>';
			printf( '<strong>%d محصول</strong> قیمت تامین جدیدی از مرکز گرفته و در حالت «دستی» در صف اعمال است. ', $pending );
			echo '<a class="button button-primary" style="background:#17a16d;border-color:#17a16d" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_apply_prices' ), 'dastyarc_apply_prices' ) ) . '">✔ اعمال به‌روزرسانی قیمت‌ها</a>';
			echo '</p></div>';
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
		$items     = $this->synced_products_query( $args );
		$count_args = $args;
		unset( $count_args['limit'], $count_args['page'] );
		$all_count = count( (array) $this->synced_products_query( array_merge( $count_args, array( 'limit' => -1, 'return' => 'ids' ) ) ) );
		$pages     = max( 1, (int) ceil( $all_count / $per ) );

		// فرم فیلترها
		echo '<form method="get" style="margin:12px 0;display:flex;flex-wrap:wrap;gap:8px;align-items:center">';
		echo '<input type="hidden" name="page" value="dastyarc-bulk">';
		wp_dropdown_categories( array(
			'taxonomy'         => 'product_cat',
			'name'             => 'bkbd_cat',
			'show_option_all'  => 'همه دسته‌بندی‌ها',
			'hierarchical'     => true,
			'orderby'          => 'name',
			'order'            => 'ASC',
			'selected'         => $bkbd_cat,
			'value_field'      => 'term_id',
			'class'            => 'postform',
		) );
		printf( '<input type="search" name="bkbd_s" value="%s" placeholder="جستجو در نام محصول…">', esc_attr( $bkbd_s ) );
		echo '<button class="button button-primary">فیلتر</button> ';
		if ( $bkbd_cat || '' !== $bkbd_s ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=dastyarc-bulk' ) ) . '">حذف فیلترها</a>';
		}
		echo '</form>';
		printf( '<p class="description">%d محصول دستیار در فروشگاه شما یافت شد. تیک موارد موردنظر را بزنید، نوع تغییر را انتخاب کنید و «اعمال» را بزنید.</p>', $all_count );

		// ‌‌فرم اعمال
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="dastyarc-bulk-form">';
		wp_nonce_field( 'dastyarc_bulk_edit' );
		echo '<input type="hidden" name="action" value="dastyarc_bulk_edit">';

		echo '<div style="margin:10px 0;padding:12px 14px;background:#fff;border:1px solid #ccd0d4;border-radius:8px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">';
		echo '<strong>تغییر قیمت فروش:</strong> ';
		echo '<select name="price_dir"><option value="">بدون تغییر قیمت</option><option value="inc_pct">افزایش درصدی ٪+</option><option value="dec_pct">کاهش درصدی ٪−</option><option value="inc_fix">افزایش مبلغ ثابت +</option><option value="dec_fix">کاهش مبلغ ثابت −</option></select>';
		echo '<input type="number" step="any" min="0" name="price_adj" placeholder="مقدار (درصد یا مبلغ)" style="width:160px;direction:ltr"> ';
		echo '<strong>وضعیت موجودی:</strong> ';
		echo '<select name="stock_set"><option value="">بدون تغییر</option><option value="instock">موجود</option><option value="outofstock">ناموجود</option></select> ';
		submit_button( 'اعمال روی محصولات انتخاب‌شده', 'primary', 'submit', false );
		echo '<span class="description">تغییر قیمت روی قیمت فروش فعلی (نمایش‌داده‌شده در ستون) اعمال می‌شود.</span>';
		echo '</div>';

		echo '<table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" id="dastyarc-bulk-all"></td><th style="width:50px">تصویر</th><th>عنوان</th><th>دسته‌بندی</th><th>قیمت تامین فعلی</th><th>قیمت فروش فعلی</th><th>موجودی</th></tr></thead><tbody>';
		if ( ! $items ) {
			echo '<tr><td colspan="7">محصول دستیاری مطابق فیلترها یافت نشد.</td></tr>';
		}
		foreach ( $items as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			$pid      = $product->get_id();
			$supplier = (float) get_post_meta( $pid, '_dastyar_supplier_price', true );
			$cats     = get_the_terms( $pid, 'product_cat' );
			$cat_html = $cats && ! is_wp_error( $cats ) ? esc_html( implode( '، ', wp_list_pluck( $cats, 'name' ) ) ) : '—';
			$img      = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
			echo '<tr>';
			printf( '<th scope="row" class="check-column"><input type="checkbox" class="dastyarc-bulk-cb" name="ids[]" value="%d"></th>', (int) $pid );
			printf( '<td>%s</td>', $img ? '<img src="' . esc_url( $img ) . '" style="width:36px;height:36px;object-fit:cover;border-radius:5px">' : '—' );
			printf( '<td><strong>%s</strong>%s<br><small style="color:#8a97a3">مرکز: #%d</small></td>', esc_html( $product->get_name() ), 'publish' === $product->get_status() ? '' : ' <span class="description">(پیش‌نویس)</span>', (int) get_post_meta( $pid, '_dastyar_remote_id', true ) );
			printf( '<td>%s</td>', $cat_html );
			printf( '<td>%s</td>', $supplier > 0 ? wp_kses_post( wc_price( $supplier ) ) : '—' );
			printf( '<td><strong>%s</strong></td>', wp_kses_post( wc_price( (float) $product->get_price() ) ) );
			printf(
				'<td><span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;%s">%s</span></td>',
				! $product->is_in_stock() ? 'background:#fdf0f0;color:#a52828' : 'background:#ecf9f2;color:#0f5132',
				! $product->is_in_stock() ? 'ناموجود' : 'موجود'
			);
			echo '</tr>';
		}
		echo '</tbody></table>';

		// صفحه‌بندی
		if ( $pages > 1 ) {
			echo '<div class="tablenav bottom"><div class="tablenav-pages">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$url = add_query_arg( array( 'page' => 'dastyarc-bulk', 'paged' => $i, 'bkbd_cat' => $bkbd_cat, 'bkbd_s' => $bkbd_s ), admin_url( 'admin.php' ) );
				printf( '<a class="button"%s href="%s">%d</a> ', $i === $page ? ' style="font-weight:bold"' : '', esc_url( $url ), $i );
			}
			echo '</div></div>';
		}

		echo '</form>';
		?>
		<script>
		jQuery(function($){
			$('#dastyarc-bulk-all').on('change', function(){ $('.dastyarc-bulk-cb').prop('checked', this.checked); });
		});
		</script>
		<?php
		echo '</div>';
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
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-bulk' ) );
			exit;
		}
		if ( '' === $dir && '' === $stk ) {
			$this->notice( 'نوع تغییر قیمت یا وضعیت موجودی را مشخص کنید.', 'error' );
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-bulk' ) );
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
		wp_safe_redirect( add_query_arg( 'bkbd_done', $done, wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-bulk' ) ) );
		exit;
	}

	/** دکمه «اعمال به‌روزرسانی قیمت‌ها» (v1.6.0 — مورد ۱۱، حالت دستی فرمول قیمت) */
	public function handle_apply_prices() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_apply_prices' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$done = DastyarC_Importer::apply_pending_prices();
		$this->notice( sprintf( 'فرمول قیمت‌گذاری روی %d محصول اعمال شد.', $done ) );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=dastyarc-bulk' ) );
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

	/* ------------------------------------------------------------------
	 * (v1.7.5 — مورد ۲) صفحه «هشدارهای قیمت» — آلارم‌های افزایش قیمت تامین مرکز،
	 * حالا در پیشخوان خود فروشنده (قبلاً در پنل مرکز بود و حذف شد). منبع: REST /pricelog مرکز.
	 * ---------------------------------------------------------------- */

	/** پوسته ظاهری مشترک صفحات جدید — همان زبان طراحی dcp صفحه محصولات (سبک سایت مرکز) */
	protected static function dcp_shell_css() {
		echo '<style>' .
			'.dcp-wrap h1.wp-heading-inline{font-size:22px;font-weight:800;color:#242536}' .
			'.dcp-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin:8px 0 2px}' .
			'.dcp-head .dcp-sub{margin:6px 0 0;color:#6b7280;font-size:12.5px;line-height:1.9}' .
			'.dcp-ver{flex:none;background:#eaf7f1;color:#0f7a52;border:1px solid #cdeedd;padding:4px 12px;border-radius:999px;font-size:11.5px;font-weight:700;direction:ltr}' .
			'.dcp-card{background:#fff;border:1px solid #e6e8ef;border-radius:14px;box-shadow:0 1px 3px rgba(16,24,40,.05)}' .
			'.dcp-btn{border-radius:9px!important;min-height:36px;line-height:1;padding:0 16px!important;font-weight:700;border:1px solid #dcdce4!important;background:#fff!important;color:#242536!important;box-shadow:none!important;transition:all .15s}' .
			'.dcp-btn:hover{background:#f4f5f9!important;border-color:#c6c9d4!important}' .
			'.dcp-btn.dcp-btn-g,.dcp-btn-g{background:#17a16d!important;border-color:#17a16d!important;color:#fff!important}' .
			'.dcp-btn.dcp-btn-g:hover,.dcp-btn-g:hover{background:#12875c!important;border-color:#12875c!important}' .
			'.dcp-table{border:0!important;margin:0}' .
			'.dcp-table thead th{background:#242536!important;color:#fff!important;font-weight:700;padding:12px 10px;border:0!important}' .
			'.dcp-table tbody td{vertical-align:middle;padding:10px;border-color:#f0f1f6!important}' .
			'.dcp-table tbody tr:hover{background:#f6fbf8}' .
			'.dcp-pill{display:inline-block;padding:3px 11px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid transparent;white-space:nowrap}' .
			'.dcp-pill-up{background:#fdf0f0;color:#a52828;border-color:#f0c8c8}' .
			'.dcp-pill-ok{background:#ecf9f2;color:#0f7a52;border-color:#c6e9d8}' .
			'.dcp-pill-wait{background:#fff7e8;color:#92400e;border-color:#f0dcbb}' .
			'.dcp-pill-gray{background:#f3f4f6;color:#4b5563;border-color:#e5e7eb}' .
			'.dcp-empty{text-align:center;color:#8b8f9c;padding:30px;font-size:13px}' .
			'.dcp-num{font-weight:700;color:#242536;white-space:nowrap}' .
			'.dcp-old{color:#9aa0ac;text-decoration:line-through;font-size:12px;margin-left:6px}' .
			'.dcp-arrow{color:#c6c9d4;margin:0 2px}' .
			'</style>';
	}

	/** سربرگ استاندارد صفحه + چیپ نسخه (زبان طراحی مرکز) */
	protected function dcp_page_head( $title, $sub ) {
		echo '<div class="dcp-head"><div class="dcp-head-t"><h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>'
			. '<p class="dcp-sub">' . esc_html( $sub ) . '</p></div>'
			. '<span class="dcp-ver" title="نسخه افزونه دستیار کانکتور">نسخه کانکتور ' . esc_html( defined( 'DASTYARC_VERSION' ) ? DASTYARC_VERSION : '' ) . '</span></div>';
	}

	public function page_price_alerts() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		// بازخوانی دستی ← کش ۶۰ ثانیه‌ای می‌شکند
		if ( ! empty( $_GET['pl_refresh'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_pl_refresh' ) ) {
			delete_transient( 'dastyarc_pricelog' );
		}
		$items = get_transient( 'dastyarc_pricelog' );
		$err   = '';
		if ( false === $items ) {
			$res = DastyarC_Client::configured()
				? DastyarC_Client::pricelog()
				: new WP_Error( 'dastyarc_not_configured', 'ابتدا اتصال را در «تنظیمات دستیار» کامل کنید.' );
			if ( is_wp_error( $res ) ) {
				$err   = self::friendly_api_error( $res );
				$items = array();
			} else {
				$items = (array) ( $res['data'] ?? array() );
				set_transient( 'dastyarc_pricelog', $items, 60 );
			}
		}

		self::dcp_shell_css();
		echo '<div class="wrap dcp-wrap">';
		$this->dcp_page_head( 'هشدارهای قیمت تامین', 'هر افزایش قیمت تامین در مرکز این‌جا آلارم می‌شود تا قیمت فروشگاهتان را به‌موقع به‌روز کنید.' );
		printf(
			'<p style="margin:10px 0 14px"><a class="button dcp-btn" href="%s">به‌روزرسانی از مرکز</a></p>',
			esc_url( wp_nonce_url( admin_url( 'admin.php?page=dastyarc-pricelog&pl_refresh=1' ), 'dastyarc_pl_refresh' ) )
		);
		if ( $err ) {
			printf( '<div class="notice notice-error"><p>خطا در دریافت هشدارها: %s</p></div></div>', esc_html( $err ) );
			return;
		}
		if ( ! $items ) {
			echo '<div class="dcp-card dcp-empty">افزایش قیمتی اخیراً ثبت نشده است ✔</div></div>';
			return;
		}
		echo '<div class="dcp-card"><table class="widefat striped dcp-table"><thead><tr>'
			. '<th>محصول</th><th>قیمت تامین قبلی</th><th>قیمت تامین جدید</th><th>میزان افزایش</th><th>زمان</th>'
			. '</tr></thead><tbody>';
		foreach ( $items as $e ) {
			$pct = (float) ( $e['percent'] ?? 0 );
			echo '<tr>';
			printf( '<td><strong>%s</strong> <span style="color:#9aa0ac;font-size:11px">#%d</span></td>', esc_html( (string) ( $e['product_name'] ?? '' ) ), (int) ( $e['product_id'] ?? 0 ) );
			printf( '<td class="dcp-num"><span class="dcp-old">%s</span></td>', esc_html( number_format( (float) ( $e['old'] ?? 0 ) ) . ' تومان' ) );
			printf( '<td class="dcp-num" style="color:#a52828">%s <span class="dcp-arrow">←</span></td>', esc_html( number_format( (float) ( $e['new'] ?? 0 ) ) . ' تومان' ) );
			printf( '<td>%s</td>', $pct > 0 ? '<span class="dcp-pill dcp-pill-up">+' . esc_html( rtrim( rtrim( number_format( $pct, 1 ), '0' ), '.' ) ) . '٪</span>' : '<span class="dcp-pill dcp-pill-gray">—</span>' );
			printf( '<td style="color:#6b7280;font-size:12px;direction:ltr;text-align:right">%s</td>', esc_html( ! empty( $e['time_fa'] ) ? (string) $e['time_fa'] : DastyarC_Jalali::dt( (string) ( $e['time'] ?? '' ) ) ) );
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="description" style="margin:12px 2px;color:#787c86">این لیست در پیشخوان سایت خودتان نمایش داده می‌شود؛ پنل مرکز دیگر این بخش را ندارد.</p></div>';
	}

	/* ------------------------------------------------------------------
	 * (v1.7.5 — مورد ۹) صفحه «تیکت و پشتیبانی» — ثبت سوال/تیکت برای مرکز از داخل
	 * پیشخوان خود فروشنده + مشاهده وضعیت و پاسخ‌ها (REST /tickets مرکز).
	 * ---------------------------------------------------------------- */
	public function page_tickets() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
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

		self::dcp_shell_css();
		echo '<style>.dcp-tk-form{padding:16px 18px;margin:14px 0}' .
			'.dcp-tk-form input[type=text],.dcp-tk-form textarea{width:100%;border:1px solid #dcdce4;border-radius:9px;padding:10px 13px;font-family:inherit;font-size:13.5px;box-sizing:border-box;background:#fafbfc;transition:border-color .15s,box-shadow .15s}' .
			'.dcp-tk-form input[type=text]:focus,.dcp-tk-form textarea:focus{border-color:#17a16d;box-shadow:0 0 0 3px rgba(23,161,109,.15);outline:none}' .
			'.dcp-tk-form label{font-weight:700;color:#242536;font-size:13px;display:block;margin:0 0 6px}' .
			'.dcp-tk-item{padding:14px 18px;margin:0 0 12px}' .
			'.dcp-tk-item h3{margin:0 0 4px;font-size:14px;color:#242536}' .
			'.dcp-tk-meta{color:#8b8f9c;font-size:11.5px;margin-bottom:8px;direction:ltr;text-align:right}' .
			'.dcp-tk-body{color:#374151;font-size:13px;line-height:2;margin:0 0 8px}' .
			'.dcp-tk-rep{border-right:3px solid #cdeedd;background:#f6fbf8;border-radius:8px;padding:8px 12px;margin:6px 0;font-size:12.5px;line-height:1.9;color:#166b47}' .
			'.dcp-tk-rep strong{display:block;font-size:11px;color:#0f7a52;margin-bottom:2px}' .
			'.dcp-tk-rep.dcp-tk-me{border-color:#dcdce4;background:#f9fafb;color:#4b5563}' .
			'</style>';
		echo '<div class="wrap dcp-wrap">';
		$this->dcp_page_head( 'تیکت و پشتیبانی', 'سوال یا مشکل‌تان را مستقیم برای تیم دستیار شاپ بفرستید؛ پاسخ در همین صفحه (و پنل مرکز) اعلام می‌شود.' );

		// فرم ثبت تیکت
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="dcp-card dcp-tk-form">';
		wp_nonce_field( 'dastyarc_ticket_submit' );
		echo '<input type="hidden" name="action" value="dastyarc_ticket_submit">';
		echo '<p style="margin:0 0 12px"><label for="dastyarc-tk-subject">موضوع تیکت</label><input type="text" id="dastyarc-tk-subject" name="subject" required maxlength="190" placeholder="مثلاً: سؤال درباره هزینه ارسال"></p>';
		echo '<p style="margin:0 0 14px"><label for="dastyarc-tk-body">شرح سوال یا مشکل</label><textarea id="dastyarc-tk-body" name="message" rows="5" required placeholder="جزئیات را بنویسید…"></textarea></p>';
		submit_button( 'ارسال تیکت به مرکز ←', 'primary dcp-btn dcp-btn-g', 'submit', false );
		echo '</form>';

		echo '<div style="display:flex;align-items:center;gap:10px;margin:16px 0 10px"><h2 style="margin:0;font-size:16px;color:#242536">تیکت‌های اخیر شما</h2>'
			. '<a class="button dcp-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=dastyarc-tickets&tk_refresh=1' ), 'dastyarc_tk_refresh' ) ) . '">به‌روزرسانی</a></div>';
		if ( $err ) {
			printf( '<div class="notice notice-error"><p>خطا در دریافت تیکت‌ها: %s</p></div></div>', esc_html( $err ) );
			return;
		}
		if ( ! $items ) {
			echo '<div class="dcp-card dcp-empty">هنوز تیکتی ثبت نکرده‌اید — اولین سوالتان را از فرم بالا بپرسید.</div></div>';
			return;
		}
		$pill = array( 'open' => 'dcp-pill-wait', 'answered' => 'dcp-pill-ok', 'closed' => 'dcp-pill-gray' );
		foreach ( $items as $t ) {
			$st  = (string) ( $t['status'] ?? 'open' );
			$cls = $pill[ $st ] ?? 'dcp-pill-gray';
			echo '<div class="dcp-card dcp-tk-item">';
			printf( '<h3>%s <span class="dcp-pill %s">%s</span></h3>', esc_html( (string) ( $t['subject'] ?? '' ) ), esc_attr( $cls ), esc_html( (string) ( $t['status_label'] ?? $st ) ) );
			printf( '<div class="dcp-tk-meta">#%d — %s</div>', (int) ( $t['id'] ?? 0 ), esc_html( ! empty( $t['date_fa'] ) ? (string) $t['date_fa'] : DastyarC_Jalali::dt( (string) ( $t['date'] ?? '' ) ) ) );
			printf( '<p class="dcp-tk-body">%s</p>', nl2br( esc_html( (string) ( $t['body'] ?? '' ) ) ) );
			foreach ( (array) ( $t['replies'] ?? array() ) as $r ) {
				$mine = 'شما' === (string) ( $r['author'] ?? '' );
				printf(
					'<div class="dcp-tk-rep%s"><strong>%s — %s</strong>%s</div>',
					$mine ? ' dcp-tk-me' : '',
					esc_html( (string) ( $r['author'] ?? '' ) ),
					esc_html( ! empty( $r['date_fa'] ) ? (string) $r['date_fa'] : DastyarC_Jalali::dt( (string) ( $r['date'] ?? '' ) ) ),
					nl2br( esc_html( (string) ( $r['body'] ?? '' ) ) )
				);
			}
			echo '</div>';
		}
		echo '</div>';
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
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-tickets' ) );
			exit;
		}
		$res = DastyarC_Client::ticket_create( $subject, $message );
		if ( is_wp_error( $res ) ) {
			$this->notice( 'ثبت تیکت ناموفق بود: ' . self::friendly_api_error( $res ), 'error' );
		} else {
			delete_transient( 'dastyarc_tickets' );
			$this->notice( 'تیکت شما در مرکز ثبت شد؛ پاسخ در همین صفحه اعلام می‌شود.' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-tickets' ) );
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
}
