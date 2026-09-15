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

	use DastyarC_Admin_Stats, DastyarC_Admin_Products, DastyarC_Admin_Bulk, DastyarC_Admin_Wallet,
		DastyarC_Admin_Rma, DastyarC_Admin_Ticket, DastyarC_Admin_Settings, DastyarC_Admin_Orders,
		DastyarC_Admin_Health;

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
		add_action( 'admin_post_dastyarc_lock', array( $this, 'handle_lock' ) ); // v1.12.0 — قفل سینک محصول
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
			'stats'    => array( 'داشبورد', '', 'chart', 'main' ),
			'products' => array( 'محصولات دستیار', '', 'box', 'main' ),
			'bulk'     => array( 'ویرایش دسته‌جمعی', '', 'edit', 'main' ),
			'wallet'   => array( 'کیف پول', '', 'wallet', 'main' ),
			'rma'      => array( 'عودت و مرجوعی', '', 'rma', 'main' ),
			'health'   => array( 'سلامت', '', 'mark', 'manage' ),
			'ticket'   => array( 'تیکت و پشتیبانی', '', 'ticket', 'manage' ),
			'settings' => array( 'تنظیمات', '', 'gear', 'manage' ),
		);
	}

	/** لینک داخلی یک تب هاب (با قابلیت نگهداشت آرگومان‌های اضافه) */
	public static function hub_url( $tab, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'dastyarc-hub', 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * لینک راهنمای سایت (v1.12.0) — نشانی پایه با فیلتر dastyarc_docs_base تعریف می‌شود؛
	 * تا وقتی تعریف نشده، چیزی چاپ نمی‌کند (لینک مرده نداریم).
	 */
	public static function guide_link( $slug, $label = '📖 راهنمای این بخش' ) {
		$base = (string) apply_filters( 'dastyarc_docs_base', '' );
		if ( '' === $base ) {
			return '';
		}
		return '<a class="dcx2-btn sm" href="' . esc_url( trailingslashit( $base ) . ltrim( (string) $slug, '/' ) ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
	}

	/** کلاس بدنه ادمین — برای رنگ اسکرین پنل واحد */
	public function hub_body_class( $classes ) {
		if ( 'dastyarc-hub' === sanitize_key( (string) ( $_GET['page'] ?? '' ) ) ) {
			$classes .= ' dastyarc-hub';
		}
		return $classes;
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
						<h1><?php echo esc_html( $tabs[ $tab ][0] ); ?><?php if ( '' !== (string) $tabs[ $tab ][1] ) : ?><small><?php echo esc_html( $tabs[ $tab ][1] ); ?></small><?php endif; ?></h1>
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
			case 'health':
				$this->tab_health();
				break;
			case 'ticket':
				$this->tab_tickets();
				break;
			case 'settings':
				$this->tab_settings();
				break;
		}
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
