<?php
/**
 * کلاس اصلی Orchestrator افزونه مرکزی
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dastyar {

	/** @var Dastyar|null */
	private static $instance = null;

	/** @var Dastyar_Logger */
	public $logger;
	/** @var Dastyar_Vendors */
	public $vendors;
	/** @var Dastyar_Wallet */
	public $wallet;
	/** @var Dastyar_Webhooks */
	public $webhooks;
	/** @var Dastyar_Tickets */
	public $tickets;
	/** @var Dastyar_MyAccount */
	public $myaccount;
	/** @var Dastyar_Registration */
	public $registration;
	/** @var Dastyar_Suggestions */
	public $suggestions;
	/** @var Dastyar_Manual_Order */
	public $manual_order;
	/** @var Dastyar_Payments */
	public $payments;
	/** @var Dastyar_Rma */
	public $rma;
	/** @var Dastyar_Shop_Page */
	public $shop_page;
	/** @var Dastyar_Sms */
	public $sms;
	/** @var Dastyar_Pricelog */
	public $pricelog;
	/** @var Dastyar_Admin|null */
	public $admin = null;
	/** @var Dastyar_Dashboard|null */
	public $dashboard = null;
	/** @var Dastyar_Unified */
	public $unified;

	/** @var Dastyar_Growth|null ابزارهای رشد فروشنده (v1.9.0) */
	public $growth;

	/** @var Dastyar_Statuses وضعیت سفارش «تحویل به پست» (v1.10.4) */
	public $statuses;

	/** @var Dastyar_Slug_Guard جلوگیری از نامک‌های خیلی دراز محصول (v1.10.22) */
	public $slug_guard;

	/** @var Dastyar_Staff_Panel داشبورد مدیریتی مستقل و تمام‌صفحه کارمندان (v1.10.24) */
	public $staff_panel;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'cron_schedules', array( 'Dastyar_Install', 'schedules' ) );

		$this->logger       = new Dastyar_Logger();
		$this->vendors      = new Dastyar_Vendors();
		$this->wallet       = new Dastyar_Wallet();
		$this->webhooks     = new Dastyar_Webhooks();
		$this->tickets      = new Dastyar_Tickets();
		$this->registration = new Dastyar_Registration();
		$this->suggestions  = new Dastyar_Suggestions();
		$this->myaccount    = new Dastyar_MyAccount();
		$this->manual_order = new Dastyar_Manual_Order();
		$this->payments     = new Dastyar_Payments();
		$this->rma          = new Dastyar_Rma();
		$this->shop_page    = new Dastyar_Shop_Page();
		$this->growth       = new Dastyar_Growth();  // v1.9.0 — ابزارهای رشد فروشنده (کپی متن، تایم‌لاین، چک‌لیست، علاقه‌مندی‌ها)
		$this->statuses     = new Dastyar_Statuses(); // v1.10.4 — وضعیت سفارش سفارشی «تحویل پست شده»
		$this->sms          = new Dastyar_Sms();      // v1.6.0 — اعلان‌رسانی پیامکی (ippanel)
		$this->pricelog     = new Dastyar_Pricelog(); // v1.6.0 — لاگ افزایش قیمت تامین
		$this->slug_guard   = new Dastyar_Slug_Guard(); // v1.10.22 — جلوگیری از نامک خیلی دراز محصول
		$this->staff_panel  = new Dastyar_Staff_Panel(); // v1.10.24 — داشبورد عملیاتی مستقل کارمندان

		if ( is_admin() ) {
			$this->admin     = new Dastyar_Admin();
			$this->dashboard = new Dastyar_Dashboard();
		}

		// v1.8.0 — «پنل واحد»: ادغام پنل فروشنده + کاتالوگ در هسته (فقط در نبود افزونه‌های مستقل).
		// نکته v1.8.1: بوت ادغام «بعد از» ساخت Dastyar_Admin می‌آید تا منوی مادر «dastyar»
		// قبل از ثبت زیرمنوهای Ext ثبت شده باشد (در وردپرس، نام هوک صفحه زیرمنو به ترتیب
		// ثبتِ منوی مادر وابسته است؛ ثبتِ زودتر ← لینک خام /wp-admin/slug ← ۴۰۴).
		$this->unified = new Dastyar_Unified();
		$this->unified->boot();

		// v1.9.8 (بازخورد کاربر — «محتوای صفحه وضعیت دو بار تکرار شده»): نمونه دوم Dastyar_Admin/
		// Dashboard که این‌جا باقی مانده بود پکه شد؛ ساخت دوباره باعث ثبت دوباره کال‌بک‌های
		// منو می‌شد و هر صفحه مدیریتی دستیار «دو بار» رندر می‌گشت. نمونه‌ها یک‌بار و «قبل از»
		// بوت پنل واحد ساخته می‌شوند تا ترتیب منو حفظ بماند.

		// ثبت REST API مرکزی
		add_action( 'rest_api_init', array( 'Dastyar_Rest', 'register_routes' ) );

		// پردازش صف Push محصولات (هر ۵ دقیقه)
		add_action( 'dastyar_push_process', array( $this->webhooks, 'process_queue' ) );
	}
}
