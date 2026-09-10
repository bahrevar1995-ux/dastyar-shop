<?php
/**
 * رجیستری ۵۰ ماژول کنسول مدیریت مرکز — هر ماژول شناسه، عنوان، توضیح، گروه، صفحه
 * و متد رندر مخصوص خود را دارد. فعال/غیرفعال‌سازی از «تنظیمات کنسول» انجام می‌شود
 * (گزینه dastyar_admin_modules؛ نبود کلید = فعال — پیش‌فرض کاربر: همه روشن).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Modules {

	/** گروه‌ها: برچسب فارسی برای صفحه تنظیمات */
	public static function groups() {
		return array(
			'customers' => 'مدیریت مشتری‌ها',
			'wallet'    => 'کیف پول',
			'reports'   => 'آمار و گزارش فروش',
			'orders'    => 'سفارش‌ها و عملیات',
			'finance'   => 'مالی و تسویه',
			'notify'    => 'اطلاع‌رسانی و ارتباط',
			'health'    => 'سلامت پلتفرم',
			'tools'     => 'ابزارهای راحتی',
			'dash'      => 'داشبورد کنسول',
		);
	}

	/** صفحه‌های کنسول: slug → [عنوان، slug منو، کلاس] */
	public static function pages() {
		return array(
			'dash'      => array( 'داشبورد کنسول', 'da-dash', 'DA_Page_Dash' ),
			'customers' => array( 'مشتری‌ها', 'da-customers', 'DA_Page_Customers' ),
			'wallet'    => array( 'کیف پول', 'da-wallet', 'DA_Page_Wallet' ),
			'reports'   => array( 'گزارش‌ها', 'da-reports', 'DA_Page_Reports' ),
			'orders'    => array( 'عملیات سفارش', 'da-orders', 'DA_Page_Orders' ),
			'finance'   => array( 'مالی و تسویه', 'da-finance', 'DA_Page_Finance' ),
			'health'    => array( 'سلامت پلتفرم', 'da-health', 'DA_Page_Health' ),
			'tools'     => array( 'ابزارها', 'da-tools', 'DA_Page_Tools' ),
		);
	}

	/**
	 * نقشه کامل ۵۰ ماژول: id => [ n(عدد ایده), title, desc, group, page, cb ]
	 * cb = متدی در کلاس صفحه‌ی مقصد که خروجی HTML کارت ماژول را چاپ می‌کند.
	 * page = 'widget' یعنی ماژول محلی برای نمایش در منو ندارد (ماژول ۴۸ روی پیشخوان اصلی وردپرس سوار است)
	 * و page = 'global' برای ماژول‌های فراگیر (۲۴ خروجی CSV که داخل کارت‌های گزارش دکمه دارد).
	 */
	public static function all() {
		return array(
			// ─── مدیریت مشتری‌ها ───
			'm01' => array( 'n' => 1,  'title' => 'دفترچه مشتری‌ها', 'desc' => 'فهرست کامل مشتری‌ها با جستجو، فیلتر برچسب و جزئیات کلیدی هرکدام', 'group' => 'customers', 'page' => 'customers', 'cb' => 'render_m01' ),
			'm02' => array( 'n' => 2,  'title' => 'کارت ۳۶۰ درجه مشتری', 'desc' => 'یک‌نگاه کامل به هر مشتری: سفارش‌ها، کیف پول، تیکت‌ها، محصولات و یادداشت‌ها', 'group' => 'customers', 'page' => 'customers', 'cb' => 'render_m02' ),
			'm03' => array( 'n' => 3,  'title' => 'رتبه‌بندی مشتری‌ها', 'desc' => 'برنزی / نقره‌ای / طلایی بر اساس حجم خرید (آستانه‌ها در تنظیمات کنسول)', 'group' => 'customers', 'page' => 'customers', 'cb' => 'render_m03' ),
			'm04' => array( 'n' => 4,  'title' => 'مشتری‌های کم‌تحرک', 'desc' => 'مشتری‌هایی که بیش از حد مجاز روز سفارش نداده‌اند — خطر ریزش', 'group' => 'customers', 'page' => 'customers', 'cb' => 'render_m04' ),
			'm05' => array( 'n' => 5,  'title' => 'یادداشت داخلی مشتری', 'desc' => 'یادداشت محرمانه روی هر مشتری که فقط مدیر مرکز می‌بیند', 'group' => 'customers', 'page' => 'customers', 'cb' => 'render_m05' ),
			'm06' => array( 'n' => 6,  'title' => 'تأیید / معلق / رد فروشنده', 'desc' => 'تغییر وضعیت حساب فروشنده با یک کلیک + ثبت دلیل', 'group' => 'customers', 'page' => 'customers', 'cb' => 'render_m06' ),
			'm07' => array( 'n' => 7,  'title' => 'برچسب‌گذاری مشتری‌ها', 'desc' => 'VIP، خوش‌حساب، خواب‌رفته… — برچسب دلخواه + فیلتر روی فهرست', 'group' => 'customers', 'page' => 'customers', 'cb' => 'render_m07' ),
			'm08' => array( 'n' => 8,  'title' => 'اطلاعیه انبوه', 'desc' => 'اعلان به همه یا یک گروه برچسبی؛ در پیشخوان سایت فروشنده نمایش داده می‌شود', 'group' => 'customers', 'page' => 'customers', 'cb' => 'render_m08' ),
			// ─── کیف پول ───
			'm09' => array( 'n' => 9,  'title' => 'برد کیف پول', 'desc' => 'مانده کیف پول همه مشتری‌ها در یک نگاه (سبز/قرمز) + جمع کل', 'group' => 'wallet', 'page' => 'wallet', 'cb' => 'render_m09' ),
			'm10' => array( 'n' => 10, 'title' => 'صف شارژهای ناتمام', 'desc' => 'سفارش‌های شارژ کیف پولی که هنوز پرداخت نشده‌اند (رهاشده/در جریان)؛ صرفاً اطلاع‌رسانی — نیازی به اقدام دستی نیست', 'group' => 'wallet', 'page' => 'wallet', 'cb' => 'render_m10' ),
			'm11' => array( 'n' => 11, 'title' => 'شارژ دستی کیف پول', 'desc' => 'افزایش/کاهش دستی مانده هر مشتری با ثبت سند و یادداشت', 'group' => 'wallet', 'page' => 'wallet', 'cb' => 'render_m11' ),
			'm12' => array( 'n' => 12, 'title' => 'هشدار بدهکاران', 'desc' => 'مشتری‌هایی با مانده منفی یا بدهی بیش از سقف اعتبار', 'group' => 'wallet', 'page' => 'wallet', 'cb' => 'render_m12' ),
			'm13' => array( 'n' => 13, 'title' => 'دفتر گردش حساب', 'desc' => 'صورت‌حساب کامل هر مشتری مثل گزارش بانکی + دکمه چاپ', 'group' => 'wallet', 'page' => 'wallet', 'cb' => 'render_m13' ),
			'm14' => array( 'n' => 14, 'title' => 'سقف اعتبار', 'desc' => 'حد اعتبار مجاز جداگانه برای هر مشتری (با هشدار ماژول ۱۲)', 'group' => 'wallet', 'page' => 'wallet', 'cb' => 'render_m14' ),
			'm15' => array( 'n' => 15, 'title' => 'یادآور کیف پول کم', 'desc' => 'لیست مشتری‌های زیر حداقل مانده + علامت‌گذاری «یادآوری شد»', 'group' => 'wallet', 'page' => 'wallet', 'cb' => 'render_m15' ),
			// ─── داشبورد ───
			'm16' => array( 'n' => 16, 'title' => 'ویترین امروز', 'desc' => 'سفارش امروز، درآمد امروز، میانگین سبد و شاخص‌های کلیدی روز', 'group' => 'dash', 'page' => 'dash', 'cb' => 'render_m16' ),
			'm17' => array( 'n' => 17, 'title' => 'نمودار فروش', 'desc' => 'میله‌ای ۳۰ روز اخیر + مقایسه مجموع با ۳۰ روز قبل از آن', 'group' => 'reports', 'page' => 'dash', 'cb' => 'render_m17' ),
			'm27' => array( 'n' => 27, 'title' => 'پیش‌بینی فروش هفته بعد', 'desc' => 'برآورد ساده بر مبنای میانگین فروش ۱۴ روز اخیر', 'group' => 'reports', 'page' => 'dash', 'cb' => 'render_m27' ),
			'm29' => array( 'n' => 29, 'title' => 'تابلوی زنده سفارش‌ها', 'desc' => 'شمارش لحظه‌ای سفارش‌ها به تفکیک هر وضعیت', 'group' => 'orders', 'page' => 'dash', 'cb' => 'render_m29' ),
			'm30' => array( 'n' => 30, 'title' => 'KPI انبار', 'desc' => 'میانگین زمان از ثبت سفارش تا «ارسال شده به دستیار» (ساعت)', 'group' => 'orders', 'page' => 'dash', 'cb' => 'render_m30' ),
			'm31' => array( 'n' => 31, 'title' => 'سفارش‌های دیرکرد', 'desc' => 'سفارش‌هایی که از SLA آماده‌سازی (تنظیمات) گذشته‌اند', 'group' => 'orders', 'page' => 'dash', 'cb' => 'render_m31' ),
			'm36' => array( 'n' => 36, 'title' => 'گردش مالی پلتفرم', 'desc' => 'جمع شارژها، برداشت‌ها و فروش این ماه در یک نگاه', 'group' => 'finance', 'page' => 'dash', 'cb' => 'render_m36' ),
			'm49' => array( 'n' => 49, 'title' => 'مقایسه با ماه قبل', 'desc' => 'این ماه در برابر ماه قبل با فلش سبز/قرمز روی هر عدد', 'group' => 'reports', 'page' => 'dash', 'cb' => 'render_m49' ),
			// ─── گزارش‌ها ───
			'm18' => array( 'n' => 18, 'title' => 'پرفروش‌ترین محصول‌ها', 'desc' => 'رتبه‌بندی محصول‌ها بر اساس تعداد و مبلغ فروش در بازه', 'group' => 'reports', 'page' => 'reports', 'cb' => 'render_m18' ),
			'm19' => array( 'n' => 19, 'title' => 'پرفروش‌ترین مشتری‌ها', 'desc' => 'سهم هر مشتری از فروش کل این ماه', 'group' => 'reports', 'page' => 'reports', 'cb' => 'render_m19' ),
			'm20' => array( 'n' => 20, 'title' => 'درآمد و حاشیه هر سفارش', 'desc' => 'درآمد مرکز (تامین+کرایه) و حاشیه قیمت‌گذاری فروشنده روی هر سفارش', 'group' => 'reports', 'page' => 'reports', 'cb' => 'render_m20' ),
			'm21' => array( 'n' => 21, 'title' => 'محصولات خواب‌رفته', 'desc' => 'محصول‌هایی که در ۳۰ روز اخیر هیچ فروشی نداشته‌اند', 'group' => 'reports', 'page' => 'reports', 'cb' => 'render_m21' ),
			'm22' => array( 'n' => 22, 'title' => 'ساعت طلایی سفارش‌ها', 'desc' => 'سفارش‌ها بیشتر چه ساعت‌هایی از شبانه‌روز می‌آیند؟', 'group' => 'reports', 'page' => 'reports', 'cb' => 'render_m22' ),
			'm23' => array( 'n' => 23, 'title' => 'نقشه شهرها', 'desc' => 'خریداران نهایی بیشتر از کدام شهرها هستند؟', 'group' => 'reports', 'page' => 'reports', 'cb' => 'render_m23' ),
			'm24' => array( 'n' => 24, 'title' => 'خروجی CSV', 'desc' => 'دکمه دانلود اکسل/CSV در کارت‌های گزارش (نمایشی به‌اندازه داده)', 'group' => 'reports', 'page' => 'global', 'cb' => '' ),
			'm25' => array( 'n' => 25, 'title' => 'گزارش مرجوعی‌ها', 'desc' => 'تعداد و درصد مرجوعی‌ها + پرتکرارترین دلایل', 'group' => 'reports', 'page' => 'reports', 'cb' => 'render_m25' ),
			'm26' => array( 'n' => 26, 'title' => 'میانگین سبد سفارش', 'desc' => 'میانگین مبلغ هر سفارش این ماه در برابر ماه قبل', 'group' => 'reports', 'page' => 'reports', 'cb' => 'render_m26' ),
			'm28' => array( 'n' => 28, 'title' => 'گزارش تخفیف‌ها', 'desc' => 'مجموع تخفیف‌های اعمال‌شده روی سفارش‌ها در بازه', 'group' => 'reports', 'page' => 'reports', 'cb' => 'render_m28' ),
			'm34' => array( 'n' => 34, 'title' => 'نبض انبار', 'desc' => 'کالاهایی که موجودی‌شان به آستانه هشدار رسیده', 'group' => 'reports', 'page' => 'reports', 'cb' => 'render_m34' ),
			'm35' => array( 'n' => 35, 'title' => 'پیش‌بینی اتمام موجودی', 'desc' => 'با سرعت فروش فعلی، هر کالا چند روز دیگر تمام می‌شود؟', 'group' => 'reports', 'page' => 'reports', 'cb' => 'render_m35' ),
			// ─── عملیات سفارش ───
			'm32' => array( 'n' => 32, 'title' => 'چاپ گروهی فاکتور', 'desc' => 'انتخاب چند سفارش و چاپ یکجای فاکتور/برچسب ارسال', 'group' => 'orders', 'page' => 'orders', 'cb' => 'render_m32' ),
			'm33' => array( 'n' => 33, 'title' => 'ثبت گروهی کد رهگیری', 'desc' => 'هر خط: شماره سفارش،کد رهگیری ← ثبت یکجا + یادداشت سفارش', 'group' => 'orders', 'page' => 'orders', 'cb' => 'render_m33' ),
			// ─── مالی ───
			'm37' => array( 'n' => 37, 'title' => 'تسویه پایان ماه', 'desc' => 'صورت‌حساب ماهانه خودکار هر مشتری + دکمه «تسویه شد»', 'group' => 'finance', 'page' => 'finance', 'cb' => 'render_m37' ),
			'm38' => array( 'n' => 38, 'title' => 'گزارش مالیات', 'desc' => 'برآورد مالیات/ارزش افزوده فروش ماه با نرخ قابل تنظیم', 'group' => 'finance', 'page' => 'finance', 'cb' => 'render_m38' ),
			'm39' => array( 'n' => 39, 'title' => 'دفتر سند دستی', 'desc' => 'ثبت دستی هزینه/درآمدهای خارج از پلتفرم + تراز', 'group' => 'finance', 'page' => 'finance', 'cb' => 'render_m39' ),
			// ─── اطلاع‌رسانی ───
			'm40' => array( 'n' => 40, 'title' => 'بنر اطلاعیه فروشنده‌ها', 'desc' => 'یک اعلان فعال که در پیشخوان همه سایت‌های فروشنده دیده می‌شود (از طریق REST دستیار)', 'group' => 'notify', 'page' => 'tools', 'cb' => 'render_m40' ),
			'm41' => array( 'n' => 41, 'title' => 'خبرنامه تغییر قیمت تامین', 'desc' => 'خلاصه هفتگی افزایش‌های قیمت برای مطلع‌کردن مشتری‌ها', 'group' => 'notify', 'page' => 'tools', 'cb' => 'render_m41' ),
			'm42' => array( 'n' => 42, 'title' => 'آمار تیکت‌ها', 'desc' => 'تعداد به تفکیک وضعیت + میانگین عمر تیکت باز', 'group' => 'notify', 'page' => 'tools', 'cb' => 'render_m42' ),
			// ─── سلامت پلتفرم ───
			'm43' => array( 'n' => 43, 'title' => 'مانیتور اتصال فروشگاه‌ها', 'desc' => 'آنلاین/آفلاین بودن سایت هر مشتری بر اساس آخرین ping', 'group' => 'health', 'page' => 'health', 'cb' => 'render_m43' ),
			'm44' => array( 'n' => 44, 'title' => 'خطاهای API', 'desc' => 'آخرین پاسخ‌های ناموفق مرکز به فروشگاه‌ها (۴xx/۵xx)', 'group' => 'health', 'page' => 'health', 'cb' => 'render_m44' ),
			'm45' => array( 'n' => 45, 'title' => 'رادار نسخه‌ها', 'desc' => 'نسخه افزونه نصب‌شده روی سایت هر مشتری + هشدار آپدیت', 'group' => 'health', 'page' => 'health', 'cb' => 'render_m45' ),
			'm46' => array( 'n' => 46, 'title' => 'نرخ فعال‌ماندن مشتری‌ها', 'desc' => 'چند درصد مشتری‌های فعال هر ماه، ماه بعد هم فروش داشته‌اند', 'group' => 'health', 'page' => 'customers', 'cb' => 'render_m46_alt' ),
			// ─── ابزارها ───
			'm47' => array( 'n' => 47, 'title' => 'جستجوی جهانی', 'desc' => 'شماره سفارش / تلفن / نام → نتیجه فوری از سفارش‌ها، مشتری‌ها و محصول‌ها', 'group' => 'tools', 'page' => 'tools', 'cb' => 'render_m47' ),
			'm48' => array( 'n' => 48, 'title' => 'ویجت «امروز من»', 'desc' => 'خلاصه روز در صفحه اصلی پیشخوان وردپرس (داشبورد)', 'group' => 'tools', 'page' => 'widget', 'cb' => '' ),
			'm50' => array( 'n' => 50, 'title' => 'کمپین امتیاز مشتری‌ها', 'desc' => 'امتیاز بر اساس فروش ماه + اهدای پاداش کیف پول با یک کلیک', 'group' => 'tools', 'page' => 'tools', 'cb' => 'render_m50' ),
		);
	}

	/** گزینه ذخیره‌شده: نبود کلید یعنی فعال (پیش‌فرض: همه روشن) */
	public static function enabled( $id ) {
		$mods = get_option( DASTYAR_ADMIN_MODULES_OPTION, array() );
		if ( ! is_array( $mods ) ) {
			return true;
		}
		return ! isset( $mods[ $id ] ) || (int) $mods[ $id ] === 1;
	}

	/** فهرست ماژول‌های فعال یک صفحه */
	public static function enabled_on_page( $page ) {
		$out = array();
		foreach ( self::all() as $id => $m ) {
			if ( $m['page'] === $page && self::enabled( $id ) && '' !== $m['cb'] ) {
				$out[ $id ] = $m;
			}
		}
		return $out;
	}

	/** شمارش کل/فعال برای نمایش در تنظیمات */
	public static function stats() {
		$on = 0;
		foreach ( self::all() as $id => $m ) {
			if ( self::enabled( $id ) ) {
				$on++;
			}
		}
		return array( 'on' => $on, 'total' => count( self::all() ) );
	}
}
