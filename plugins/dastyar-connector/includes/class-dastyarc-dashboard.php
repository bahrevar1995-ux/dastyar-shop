<?php
/**
 * صفحه «آمار دستیار شاپ» در منوی مستقل دستیار شاپ (پیشخوان سایت فروشنده) — v1.7.0.
 * v1.8.0 — بازنویسی کامل بخش «سفارش‌های دستیار» به درخواست کاربر: دیتاست واحد دقیق (order_dataset)،
 * نوار شاخص‌ها، نمودار میله‌ای ۳۰ روزه و دونات وضعیت (SVG خالص، تاریخ/اعداد شمسی-فارسی، بدون اموجی).
 * تا v1.6.x این بخش‌ها به‌صورت «ابزارک پیشخوان وردپرس» نمایش داده می‌شدند؛ به درخواست مشتری
 * از صفحه پیشخوان وردپرس حذف شدند و یکجا در صفحه «دستیار شاپ ← آمار» جمع‌اند؛ هر بخش همچنان
 * از «دستیار شاپ ← تنظیمات دستیار» قابل فعال/غیرفعال است.
 * داده‌شماری‌ها با سقف‌های ۲۰۰/۵۰۰ قبلی نادقیق بودند ← بدون سقف (-1) و دقیق شمارش می‌شوند.
 *
 * بخش‌ها:
 *  ۱) راه‌اندازی دستیار     → ویزارد ۳ مرحله‌ای (کلید ← اتصال ← افزودن محصول) با درصد پیشرفت (v1.5.0)
 *  ۲) وضعیت اتصال به مرکز   → Ping (کش ۶۰ ثانیه‌ای)، آخرین سینک‌ها، دکمه «سینک کامل موجودی» + هشدار رو به اتمام
 *  ۳) سفارش‌های دستیار ★    → شمار دقیق + لینک مستقیم به تب فیلتر سفارش‌ها + نمودار ۷ روز اخیر
 *  ۴) عودت و مرجوعی         → شمارش وضعیت‌ها + آخرین گزارش‌ها
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Dashboard {

	const OPTION = 'dastyarc_widgets_enabled';

	/** ابزارک‌های موجود: کلید => عنوان */
	public static function widgets() {
		return array(
			'setup'      => 'راه‌اندازی دستیار شاپ (ویزارد)',
			'connection' => 'وضعیت اتصال به دستیار شاپ',
			'orders'     => 'سفارش‌های دستیار ★',
			'rma'        => 'عودت و مرجوعی',
		);
	}

	/** آیا ابزارک فعال است؟ (پیش‌فرض: فعال) */
	public static function enabled( $key ) {
		$opt = get_option( self::OPTION, null );
		if ( ! is_array( $opt ) || ! array_key_exists( $key, $opt ) ) {
			return true;
		}
		return ! empty( $opt[ $key ] );
	}

	/** حالت تاریک سازمانی فعال است؟ (v1.5.0) */
	public static function dark() {
		return 'yes' === get_option( 'dastyarc_dark_mode', 'no' );
	}

	public function __construct() {
		// v1.7.0: ثبت ابزارک در «پیشخوان وردپرس» حذف شد ← بخش‌ها در صفحه «دستیار شاپ ← آمار».
	}

	/**
	 * رندر کامل صفحه «آمار» — از DastyarC_Admin::page_stats صدا زده می‌شود (v1.7.0).
	 * هر بخش طبق سوییچ‌های تنظیمات (همان dastyarc_widgets_enabled) فعال/غیرفعال است.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}

		echo '<div class="wrap dastyarc-stats-page">';
		$this->css();

		if ( self::dark() ) {
			echo '<div class="dcd-page dcd-dark">';
		} else {
			echo '<div class="dcd-page">';
		}
		// (v1.7.5 — مورد ۸) سرصفحه هماهنگ با صفحات dcp و سبک سایت مرکز + چیپ نسخه
		echo '<div class="dcd-page-head">' .
			'<h1 class="dcd-page-title">📈 آمار دستیار شاپ <span class="dcp-ver" title="نسخه افزونه دستیار کانکتور">نسخه کانکتور ' . esc_html( defined( 'DASTYARC_VERSION' ) ? DASTYARC_VERSION : '' ) . '</span></h1>' .
			'<p class="dcd-page-sub">تصویر زنده و دقیقِ اتصال، محصولات، سفارش‌ها و عودت‌های فروشگاه شما در یک نگاه — همه بخش‌ها از «تنظیمات دستیار» قابل فعال/غیرفعال‌اند.</p>' .
			'</div>';

		echo '<div class="dcd-page-grid">';
		$sections = array(
			'setup'      => '🚀 راه‌اندازی دستیار شاپ',
			'connection' => '🌱 وضعیت اتصال به دستیار شاپ',
			'orders'     => '⭐ سفارش‌های دستیار شاپ',
			'rma'        => '📦 عودت و مرجوعی',
		);
		foreach ( $sections as $key => $title ) {
			if ( ! self::enabled( $key ) ) {
				continue;
			}
			$method = 'render_' . $key;
			$full   = 'orders' === $key ? ' dcd-card-full' : ''; // v1.8.0 — پنل سفارش‌ها تمام‌عرض
			echo '<section class="dcd-card dcd-card-' . esc_attr( $key ) . $full . '">';
			echo '<h2 class="dcd-card-title">' . esc_html( $title ) . '</h2>';
			echo '<div class="dcd-card-body">';
			$this->$method();
			echo '</div></section>';
		}
		echo '</div>'; // .dcd-page-grid

		printf(
			'<p class="dcd-meta" style="margin-top:14px">آخرین بارگذاری صفحه: %s — داده‌ها با هر بارگذاری از فروشگاه شما (و هر ۶۰ ثانیه از مرکز) تازه می‌شوند. <a href="%s">بازخوانی صفحه ↻</a></p>',
			esc_html( DastyarC_Jalali::dt( null ) ),
			esc_url( admin_url( 'admin.php?page=dastyarc-stats' ) )
		);
		echo '</div></div>'; // .dcd-page /.wrap
	}

	/* ------------------------------------------------------------------
	 * ابزارک ۱: ویزارد راه‌اندازی (v1.5.0)
	 * ---------------------------------------------------------------- */

	/**
	 * مراحل راه‌اندازی — قابل تست جداگانه.
	 * @return array<int,array{key:string,label:string,done:bool,url:string,action:string}>
	 */
	public static function setup_steps() {
		$configured = DastyarC_Client::configured();

		// اتصال (کش ۶۰ ثانیه‌ای مشترک با ابزارک اتصال)
		$connected = false;
		if ( $configured ) {
			$ping = get_transient( 'dastyarc_dash_ping' );
			if ( false === $ping ) {
				$pong = DastyarC_Client::ping();
				$ping = is_wp_error( $pong ) ? array( 'ok' => false, 'msg' => $pong->get_error_message() ) : array( 'ok' => true, 'msg' => '' );
				set_transient( 'dastyarc_dash_ping', $ping, 60 );
			}
			$connected = ! empty( $ping['ok'] );
		}

		// افزودن اولین محصول
		$has_product = count( (array) get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_key'       => '_dastyar_remote_id',
			'meta_compare'   => 'EXISTS',
		) ) ) > 0;

		return array(
			array(
				'key'    => 'keys',
				'label'  => 'وارد کردن آدرس مرکز و API Key',
				'done'   => $configured,
				'url'    => admin_url( 'admin.php?page=dastyarc-settings' ),
				'action' => 'تنظیمات اتصال',
			),
			array(
				'key'    => 'connect',
				'label'  => 'برقراری اتصال موفق به مرکز',
				'done'   => $connected,
				'url'    => admin_url( 'admin.php?page=dastyarc-settings' ),
				'action' => 'تست اتصال',
			),
			array(
				'key'    => 'import',
				'label'  => 'افزودن اولین محصول دستیار به فروشگاه',
				'done'   => $has_product,
				'url'    => admin_url( 'admin.php?page=dastyarc-products' ),
				'action' => 'محصولات دستیار',
			),
		);
	}

	public function render_setup() {
		$steps = self::setup_steps();
		$done  = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );
		$pct   = (int) round( $done / count( $steps ) * 100 );

		echo '<ul class="dcd-steps">';
		foreach ( $steps as $i => $step ) {
			printf(
				'<li class="dcd-step %s"><span class="dcd-stepnum">%s</span><span class="dcd-steplabel">%s</span>%s</li>',
				$step['done'] ? 'dcd-done' : '',
				$step['done'] ? '✓' : (string) ( $i + 1 ),
				esc_html( $step['label'] ),
				$step['done'] ? '' : ' <a class="dcd-steplink" href="' . esc_url( $step['url'] ) . '">' . esc_html( $step['action'] ) . ' ←</a>'
			);
		}
		echo '</ul>';

		printf(
			'<div class="dcd-progressbar"><span style="width:%d%%"></span></div><p class="dcd-meta" style="text-align:center;margin-top:6px">%d%% تکمیل شد (%d از %d مرحله)</p>',
			$pct,
			$pct,
			(int) $done,
			count( $steps )
		);

		if ( $pct >= 100 ) {
			echo '<p class="dcd-finish">🎉 آفرین! فروشگاه شما به دستیار شاپ متصل و آماده فروش است.</p>';
		} else {
			echo '<p class="dcd-meta">با تکمیل این ۳ مرحله، فروش دراپ‌شیپینگ شما شروع می‌شود.</p>';
		}
	}

	/* ------------------------------------------------------------------
	 * ابزارک ۲: وضعیت اتصال
	 * ---------------------------------------------------------------- */

	public function render_connection() {
		$configured = DastyarC_Client::configured();
		$central    = (string) get_option( 'dastyarc_central_url', '' );

		if ( ! $configured ) {
			echo '<p class="dcd-alert">⚠ اتصال به مرکز هنوز تنظیم نشده است.</p>';
			echo '<p><a class="button button-primary dcd-btn" href="' . esc_url( admin_url( 'admin.php?page=dastyarc-settings' ) ) . '">رفتن به تنظیمات اتصال</a></p>';
			return;
		}

		// Ping با کش ۶۰ ثانیه‌ای تا داشبورد سریع بماند
		$ping = get_transient( 'dastyarc_dash_ping' );
		if ( false === $ping ) {
			$pong = DastyarC_Client::ping();
			$ping = is_wp_error( $pong ) ? array( 'ok' => false, 'msg' => $pong->get_error_message() ) : array( 'ok' => true, 'msg' => (string) ( $pong['vendor_name'] ?? '' ) );
			set_transient( 'dastyarc_dash_ping', $ping, 60 );
		}

		if ( $ping['ok'] ) {
			echo '<p class="dcd-status dcd-ok"><span class="dcd-dot"></span> متصل به مرکز' . ( $ping['msg'] ? ' — <strong>' . esc_html( $ping['msg'] ) . '</strong>' : '' ) . '</p>';
		} else {
			echo '<p class="dcd-status dcd-bad"><span class="dcd-dot"></span> قطع اتصال: ' . esc_html( $ping['msg'] ) . '</p>';
		}
		printf( '<p class="dcd-meta">مرکز: <code>%s</code></p>', esc_html( $central ) );

		// آمار سینک — شمارش دقیق بدون سقف (v1.7.0؛ قبلاً ۵۰۰ سقف داشت و بزرگ‌ترها را نمی‌گرفت)
		$products   = count( get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_dastyar_remote_id', 'meta_compare' => 'EXISTS' ) ) );
		$last_sync  = (int) get_option( 'dastyarc_last_sync', 0 );
		$last_stock = (int) get_option( 'dastyarc_last_stock_sync', 0 );
		echo '<div class="dcd-grid">';
		$this->stat( (string) (int) $products, 'محصول دستیار در فروشگاه' );
		$this->stat( $last_sync ? DastyarC_Jalali::dt( $last_sync ) : '—', 'آخرین سینک محصول' );
		$this->stat( $last_stock ? DastyarC_Jalali::dt( $last_stock ) : '—', 'آخرین سینک موجودی' );
		echo '</div>';

		// هشدار هوشمند موجودی (v1.5.0): رو به اتمام / ناموجود
		$stock = self::low_stock_report();
		if ( $stock['low'] > 0 ) {
			printf( '<p class="dcd-alert">⚠ موجودی <strong>%d</strong> محصول دستیار رو به اتمام است (کمتر از ۳ عدد).</p>', (int) $stock['low'] );
		}
		if ( $stock['out'] > 0 ) {
			printf( '<p class="dcd-alert dcd-alert-red">⛔ <strong>%d</strong> محصول دستیار هم‌اکنون ناموجود است.</p>', (int) $stock['out'] );
		}

		// دکمه سینک کامل موجودی (v1.5.0 — درخواست مستقیم مشتری)
		echo '<p class="dcd-links" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=dastyarc-products' ) ) . '">محصولات دستیار</a>';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=dastyarc-settings' ) ) . '">تنظیمات</a>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0">';
		wp_nonce_field( 'dastyarc_full_sync' );
		echo '<input type="hidden" name="action" value="dastyarc_full_sync">';
		echo '<button class="button button-primary dcd-btn">🔄 سینک کامل موجودی الان</button>';
		echo '</form></p>';
	}

	/**
	 * گزارش موجودی محصولات دستیار — قابل تست جداگانه (v1.5.0).
	 * @return array{low:int,out:int}
	 */
	public static function low_stock_report() {
		$low = 0;
		$out = 0;
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_dastyar_remote_id',
			'meta_compare'   => 'EXISTS',
		) );
		foreach ( (array) $ids as $lid ) {
			$p = wc_get_product( $lid );
			if ( ! $p || 'instock' !== (string) $p->get_stock_status() ) {
				if ( $p ) {
					$out++;
				}
				continue;
			}
			$qty = $p->get_stock_quantity();
			if ( null !== $qty && (int) $qty <= 2 ) {
				$low++;
			}
		}
		return array( 'low' => $low, 'out' => $out );
	}

	protected function stat( $value, $label ) {
		printf(
			'<div class="dcd-stat"><strong>%s</strong><span>%s</span></div>',
			esc_html( $value ),
			esc_html( $label )
		);
	}

	/* ------------------------------------------------------------------
	 * بخش ۳: سفارش‌های دستیار ★ — بازنویسی کامل v1.8.0 (درخواست کاربر)
	 * «اطلاعات اشتباه بود؛ گرافیک از قالب ابزارکی خارج و یکپارچه/حرفه‌ای شود»
	 * منبع واحد داده: order_dataset() — یک کوئری بدون سقف، همه آمار از همان دیتاست
	 * محاسبه می‌شود (قبلاً ۳ کوئری جدا با تعریف‌های متفاوت «در جریان» ⇒ ناسازگاری اعداد).
	 * ---------------------------------------------------------------- */

	/** وضعیت محلی سفارش → گروه نمایشی */
	public static function status_groups() {
		return array(
			'pending'                      => 'pending',
			'processing'                   => 'progress',
			'on-hold'                      => 'progress',
			DastyarC_Orders::SENT_STATUS   => 'sent',
			'posted'                       => 'posted',
			'completed'                    => 'done',
			'cancelled'                    => 'cancelled',
			'failed'                       => 'cancelled',
			'refunded'                     => 'cancelled',
		);
	}

	/** تعریف گروه‌ها: برچسب + رنگ برند */
	public static function group_defs() {
		return array(
			'pending'   => array( 'label' => 'در انتظار پرداخت',    'color' => '#8b8f9c' ),
			'progress'  => array( 'label' => 'در حال انجام',        'color' => '#2271b1' ),
			'sent'      => array( 'label' => 'ارسال شده به مرکز',   'color' => '#17a16d' ),
			'posted'    => array( 'label' => 'تحویل پست شده',       'color' => '#b78a00' ),
			'done'      => array( 'label' => 'تکمیل شده',           'color' => '#0f7a52' ),
			'cancelled' => array( 'label' => 'لغو / ناموفق / مرجوع', 'color' => '#c0392b' ),
		);
	}

	/**
	 * دیتاست کانونیک سفارش‌های دستیار — قابل تست مستقل (آرایه سفارش را می‌توان تزریق کرد).
	 * @param array|null $orders null ← کوئری زنده؛ وگرنه آرایه WC_Order
	 * @return array{total:int,sum:float,sum30:float,by_group:array,days:array,max_day:int,avg30:float}
	 */
	public static function order_dataset( $orders = null ) {
		static $cache = null;
		if ( null === $orders && null !== $cache ) {
			return $cache;
		}
		if ( null === $orders ) {
			$orders = wc_get_orders( array(
				'limit'      => -1,
				'meta_query' => array( array( 'key' => '_dastyarc_has_items', 'compare' => 'EXISTS' ) ),
			) );
		}

		$map  = self::status_groups();
		$defs = self::group_defs();
		// مبنای سطل‌های روز = نیمه‌شب امروز به وقت سایت؛ برچسب‌ها شمسی‌اند
		$now = function_exists( 'current_time' ) ? current_time( 'timestamp' ) : time();
		if ( ! is_numeric( $now ) ) {
			$now = time();
		}
		$today = (int) $now - ( (int) $now % DAY_IN_SECONDS );

		$days = array();
		for ( $ix = 29; $ix >= 0; $ix-- ) {
			$ts             = $today - $ix * DAY_IN_SECONDS;
			$key            = gmdate( 'Y-m-d', $ts );
			$days[ $key ]   = array(
				'ts'    => $ts,
				'count' => 0,
				'sum'   => 0.0,
				'label' => DastyarC_Jalali::format( 'j F', $ts ),
				'day'   => DastyarC_Jalali::format( 'j', $ts ),
				'dow'   => DastyarC_Jalali::format( 'D', $ts ),
			);
		}

		$out = array(
			'total'    => 0,
			'sum'      => 0.0,
			'sum30'    => 0.0,
			'avg30'    => 0.0,
			'max_day'  => 0,
			'by_group' => array(),
			'days'     => $days,
		);
		foreach ( $defs as $g => $def ) {
			$out['by_group'][ $g ] = array( 'count' => 0, 'sum' => 0.0 ) + $def;
		}

		foreach ( (array) $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_status' ) ) {
				continue;
			}
			$st = preg_replace( '/^wc-/', '', (string) $order->get_status() );
			$g  = $map[ $st ] ?? 'progress';
			if ( ! isset( $out['by_group'][ $g ] ) ) {
				$g = 'progress';
			}
			$total = method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0.0;

			$out['total']++;
			$out['by_group'][ $g ]['count']++;
			$out['by_group'][ $g ]['sum'] += $total;
			if ( 'cancelled' !== $g ) {
				$out['sum'] += $total; // مجموع فروش = بدون لغو/ناموفق/مرجوع
			}

			$dt  = method_exists( $order, 'get_date_created' ) ? $order->get_date_created() : null;
			$key = ( $dt && method_exists( $dt, 'date' ) ) ? $dt->date( 'Y-m-d' ) : ( ( $dt && method_exists( $dt, 'format' ) ) ? $dt->format( 'Y-m-d' ) : '' );
			if ( $key && isset( $out['days'][ $key ] ) ) {
				$out['days'][ $key ]['count']++;
				$out['days'][ $key ]['sum'] += $total;
				if ( 'cancelled' !== $g ) {
					$out['sum30'] += $total;
				}
			}
		}
		foreach ( $out['days'] as $day ) {
			$out['max_day'] = max( $out['max_day'], (int) $day['count'] );
		}
		$c30      = array_sum( array_map( function ( $x ) { return (int) $x['count']; }, $out['days'] ) );
		$out['avg30'] = $c30 / 30;

		if ( null === $orders ) {
			$cache = $out;
		}
		return $out;
	}

	public function render_orders() {
		$d          = self::order_dataset();
		$filter_url = $this->orders_filter_url();
		$g          = $d['by_group'];
		$delivered  = (int) $g['posted']['count'] + (int) $g['done']['count'];

		/* ─ نوار شاخص‌ها (KPI) ─ */
		echo '<div class="dcd2-kpirow">';
		$this->kpi( DastyarC_Jalali::num( $d['total'] ), 'کل سفارش‌های دستیار', DastyarC_Jalali::fa( strip_tags( wc_price( $d['sum'] ) ) ) . ' مجموع فروش', '#17a16d', 'orders' );
		$this->kpi( DastyarC_Jalali::fa( strip_tags( wc_price( $d['sum30'] ) ) ), 'فروش ۳۰ روز اخیر', 'بدون سفارش‌های لغوشده', '#2fce96', 'coin' );
		$this->kpi( (string) (int) $g['progress']['count'], 'در حال انجام', 'پرداخت‌شده، آماده ارسال به مرکز/در مرکز', '#2271b1', 'gear' );
		$this->kpi( (string) (int) $g['sent']['count'], 'ارسال شده به مرکز', 'در صف آماده‌سازی انبار دستیار', '#17a16d', 'truck' );
		$this->kpi( (string) $delivered, 'تحویل‌شده', 'تحویل پست + تکمیل', '#b78a00', 'check' );
		$this->kpi( (string) (int) $g['cancelled']['count'], 'لغو / ناموفق', 'بازگشت وجه خودکار به کیف پول', '#c0392b', 'cross' );
		echo '</div>';

		/* ─ نمودارها ─ */
		echo '<div class="dcd2-charts">';
		echo '<div class="dcd2-panel dcd2-bars">';
		echo '<div class="dcd2-panel-head"><strong>روند سفارش‌های دستیار — ۳۰ روز اخیر</strong><span>میانگین روزانه: ' . esc_html( DastyarC_Jalali::num( round( $d['avg30'], 1 ), 1 ) ) . ' سفارش</span></div>';
		$this->bars_svg( $d );
		echo '</div>';
		echo '<div class="dcd2-panel dcd2-donut">';
		echo '<div class="dcd2-panel-head"><strong>توزیع وضعیت سفارش‌ها</strong><span>' . esc_html( DastyarC_Jalali::num( $d['total'] ) ) . ' سفارش</span></div>';
		$this->donut_svg( $d );
		echo '</div>';
		echo '</div>';

		echo '<p class="dcd-links" style="margin-top:14px"><a class="button button-primary dcd-btn" href="' . esc_url( $filter_url ) . '">مشاهده و مدیریت سفارش‌های دستیار</a></p>';
	}

	/** کارت شاخص (KPI) با آیکون SVG خطی برند */
	protected function kpi( $value, $label, $sub, $color, $icon ) {
		printf(
			'<div class="dcd2-kpi" style="--kpi:%1$s"><span class="dcd2-kpi-ic">%2$s</span><div><strong>%3$s</strong><span class="dcd2-kpi-lb">%4$s</span><small>%5$s</small></div></div>',
			esc_attr( $color ),
			$this->svg_icon( $icon, $color ),
			esc_html( DastyarC_Jalali::fa( $value ) ),
			esc_html( $label ),
			esc_html( $sub )
		);
	}

	/** نقشه آیکون‌های خطی SVG (بدون اموجی — v1.8.0) */
	protected function svg_icon( $name, $color = '#17a16d' ) {
		$p = array(
			'orders' => '<path d="M6 3h12l1.5 4H4.5L6 3zm-1.5 4h15v11a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2V7z" fill="none" stroke="%c" stroke-width="1.7" stroke-linejoin="round"/><path d="M9 11a3 3 0 0 0 6 0" fill="none" stroke="%c" stroke-width="1.7" stroke-linecap="round"/>',
			'coin'   => '<circle cx="12" cy="12" r="8.5" fill="none" stroke="%c" stroke-width="1.7"/><path d="M12 7.5v9M9 9.5h4.2a1.9 1.9 0 0 1 0 3.8H10a1.9 1.9 0 0 0 0 3.8H14" fill="none" stroke="%c" stroke-width="1.7" stroke-linecap="round"/>',
			'gear'   => '<circle cx="12" cy="12" r="3.2" fill="none" stroke="%c" stroke-width="1.7"/><path d="M12 3.5v3M12 17.5v3M20.5 12h-3M6.5 12h-3M18 6l-2.2 2.2M8.2 15.8L6 18M18 18l-2.2-2.2M8.2 8.2L6 6" stroke="%c" stroke-width="1.7" stroke-linecap="round"/>',
			'truck'  => '<path d="M3 6.5h11v10H3zM14 9.5h4l3 3.5v3.5h-7" fill="none" stroke="%c" stroke-width="1.7" stroke-linejoin="round"/><circle cx="7" cy="17.5" r="1.8" fill="none" stroke="%c" stroke-width="1.6"/><circle cx="17" cy="17.5" r="1.8" fill="none" stroke="%c" stroke-width="1.6"/>',
			'check'  => '<circle cx="12" cy="12" r="8.5" fill="none" stroke="%c" stroke-width="1.7"/><path d="M8 12.3l2.8 2.7L16.5 9" fill="none" stroke="%c" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>',
			'cross'  => '<circle cx="12" cy="12" r="8.5" fill="none" stroke="%c" stroke-width="1.7"/><path d="M8.8 8.8l6.4 6.4M15.2 8.8l-6.4 6.4" stroke="%c" stroke-width="1.9" stroke-linecap="round"/>',
		);
		$body = isset( $p[ $name ] ) ? str_replace( '%c', $color, $p[ $name ] ) : '';
		return '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true">' . $body . '</svg>';
	}

	/** نمودار میله‌ای SVG خالص — ۳۰ روز اخیر (برچسب‌های شمسی) */
	protected function bars_svg( $d ) {
		$W = 900;
		$H = 240;
		$pad_t = 26;
		$pad_b = 34;
		$n        = count( $d['days'] );
		$slot     = $W / $n;
		$bw       = min( 20.0, $slot * 0.58 );
		$max      = max( 1, (int) $d['max_day'] );
		$usable_h = $H - $pad_t - $pad_b;

		echo '<svg class="dcd2-svgbars" viewBox="0 0 ' . (int) $W . ' ' . (int) $H . '" role="img" aria-label="نمودار سفارش‌های ۳۰ روز اخیر" preserveAspectRatio="none">';
		// خطوط شبکه افقی (۰، میانگین، بیشترین)
		foreach ( array( 0, 0.5, 1 ) as $f ) {
			$y = $H - $pad_b - $usable_h * $f;
			printf( '<line x1="0" x2="%d" y1="%.1f" y2="%.1f" class="dcd2-grid"/>', $W, $y, $y );
			printf( '<text x="6" y="%.1f" class="dcd2-ytxt">%s</text>', $y - 5, esc_html( DastyarC_Jalali::num( $max * $f ) ) );
		}
		$ix = 0;
		foreach ( $d['days'] as $day ) {
			$c   = (int) $day['count'];
			$bh  = $c > 0 ? max( 3.0, $usable_h * $c / $max ) : 2.0;
			$x   = $W - ( $ix + 0.5 ) * $slot - $bw / 2; // راست‌به‌چپ: امروز سمت راست
			$y   = $H - $pad_b - $bh;
			$cls = $c > 0 ? 'dcd2-bar' : 'dcd2-bar dcd2-bar-zero';
			printf(
				'<rect class="%s" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3"><title>%s — %s سفارش</title></rect>',
				esc_attr( $cls ),
				$x,
				$y,
				$bw,
				$bh,
				esc_attr( $day['label'] ),
				esc_html( DastyarC_Jalali::num( $c ) )
			);
			if ( $c > 0 ) {
				printf( '<text x="%.1f" y="%.1f" class="dcd2-bval">%s</text>', $x + $bw / 2, $y - 5, esc_html( DastyarC_Jalali::num( $c ) ) );
			}
			// برچسب محور: هر ۵ روز یک‌بار
			if ( 0 === $ix % 5 || $ix === $n - 1 ) {
				printf( '<text x="%.1f" y="%d" class="dcd2-xtxt">%s</text>', $x + $bw / 2, $H - 12, esc_html( $day['day'] ) );
			}
			$ix++;
		}
		// نام ماه شمسی دو سر محور
		$first = reset( $d['days'] );
		$last  = end( $d['days'] );
		printf( '<text x="%.0f" y="%d" class="dcd2-xm" text-anchor="start">%s</text>', $slot * 0.5, $H - 12, esc_html( DastyarC_Jalali::format( 'F', $first['ts'] ) ) );
		printf( '<text x="%.0f" y="%d" class="dcd2-xm" text-anchor="end">%s</text>', $W - $slot * 0.5, $H - 12, esc_html( DastyarC_Jalali::format( 'F', $last['ts'] ) ) );
		echo '</svg>';
	}

	/** دونات SVG توزیع وضعیت + لِجند */
	protected function donut_svg( $d ) {
		$total = max( 1, (int) $d['total'] );
		$R     = 62;
		$C     = 2 * M_PI * $R;
		$acc   = 0.0;

		echo '<div class="dcd2-donutwrap">';
		echo '<svg viewBox="0 0 160 160" class="dcd2-svgdonut" role="img" aria-label="توزیع وضعیت سفارش‌ها">';
		echo '<circle cx="80" cy="80" r="' . $R . '" class="dcd2-dbg"/>';
		foreach ( $d['by_group'] as $g ) {
			$c = (int) $g['count'];
			if ( $c <= 0 ) {
				continue;
			}
			$frac = $c / $total;
			$len  = $frac * $C;
			printf(
				'<circle cx="80" cy="80" r="%d" class="dcd2-dseg" stroke="%s" stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f"><title>%s — %s</title></circle>',
				$R,
				esc_attr( $g['color'] ),
				max( 0.01, $len - 1.5 ),
				$C,
				- $acc * $C + $C / 4,
				esc_attr( $g['label'] ),
				esc_attr( DastyarC_Jalali::num( $c ) . ' سفارش' )
			);
			$acc += $frac;
		}
		printf( '<text x="80" y="76" class="dcd2-dnum" text-anchor="middle">%s</text>', esc_html( DastyarC_Jalali::num( $d['total'] ) ) );
		echo '<text x="80" y="98" class="dcd2-dcap" text-anchor="middle">سفارش دستیار</text>';
		echo '</svg>';

		echo '<ul class="dcd2-legend">';
		foreach ( $d['by_group'] as $g ) {
			$c = (int) $g['count'];
			if ( $c <= 0 ) {
				continue;
			}
			printf(
				'<li><span class="dcd2-dot" style="background:%s"></span><span class="dcd2-lg-lb">%s</span><strong>%s</strong><small>%s٪</small></li>',
				esc_attr( $g['color'] ),
				esc_html( $g['label'] ),
				esc_html( DastyarC_Jalali::num( $c ) ),
				esc_html( DastyarC_Jalali::num( round( $c / $total * 100, 1 ), 1 ) )
			);
		}
		if ( 0 === (int) $d['total'] ) {
			echo '<li class="dcd-empty">هنوز سفارش دستیاری ثبت نشده است.</li>';
		}
		echo '</ul></div>';
	}

	/** لینک تب فیلتر سفارش‌های دستیار — سازگار با HPOS و کلاسیک */
	protected function orders_filter_url() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders&dastyarc_orders=1' );
		}
		return admin_url( 'edit.php?post_type=shop_order&dastyarc_orders=1' );
	}

	/* ------------------------------------------------------------------
	 * ابزارک ۴: عودت و مرجوعی
	 * ---------------------------------------------------------------- */

	public function render_rma() {
		$new       = DastyarC_Rma::pending_bubble_count();
		$listening = $this->count_by_status( array( 'submitted', 'reviewing', 'need_info' ) );
		$waiting   = $this->count_by_status( array( 'approved', 'await_item', 'item_received', 'final_review' ) );

		echo '<div class="dcd-grid">';
		$this->stat_card( (string) (int) $new, 'گزارش جدید (ارسال‌نشده)', admin_url( 'admin.php?page=dastyarc-rma' ), 'amber' );
		$this->stat_card( (string) (int) $listening, 'در حال بررسی مرکز', admin_url( 'admin.php?page=dastyarc-rma' ), 'green' );
		$this->stat_card( (string) (int) $waiting, 'در جریان عودت کالا', admin_url( 'admin.php?page=dastyarc-rma' ), 'green' );
		echo '</div>';

		$recent = get_posts( array(
			'post_type'      => DastyarC_Rma::POST_TYPE,
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		if ( $recent ) {
			echo '<table class="widefat striped dcd-table"><thead><tr><th>گزارش</th><th>وضعیت</th></tr></thead><tbody>';
			foreach ( $recent as $p ) {
				$st = (string) get_post_meta( $p->ID, DastyarC_Rma::M_STATUS, true );
				printf(
					'<tr><td><a href="%s">#%d — سفارش %s</a></td><td><span class="dcd-badge" style="background:%s">%s</span></td></tr>',
					esc_url( admin_url( 'admin.php?page=dastyarc-rma&view=' . (int) $p->ID ) ),
					(int) $p->ID,
					esc_html( (string) get_post_meta( $p->ID, DastyarC_Rma::M_ORDER, true ) ),
					esc_attr( DastyarC_Rma::status_color( $st ) ),
					esc_html( DastyarC_Rma::status_label( $st ) )
				);
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="dcd-empty">هنوز گزارش عودتی ثبت نشده است.</p>';
		}

		echo '<p class="dcd-links"><a class="button button-primary dcd-btn" href="' . esc_url( admin_url( 'admin.php?page=dastyarc-rma' ) ) . '">مدیریت گزارش‌های عودت</a></p>';
	}

	protected function count_by_status( array $statuses ) {
		// شمارش دقیق بدون سقف (v1.7.0)
		return count( get_posts( array(
			'post_type'      => DastyarC_Rma::POST_TYPE,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => DastyarC_Rma::M_STATUS, 'value' => $statuses, 'compare' => 'IN' ),
			),
		) ) );
	}

	protected function stat_card( $num, $label, $url, $tone = 'green' ) {
		printf(
			'<a class="dcd-stat dcd-stat-%s dcd-stat-link" href="%s"><strong>%s</strong><span>%s</span></a>',
			esc_attr( $tone ),
			esc_url( $url ),
			esc_html( $num ),
			esc_html( $label )
		);
	}

	/* ------------------------------------------------------------------
	 * استایل برند (فقط صفحه پیشخوان) + حالت تاریک اختیاری
	 * ---------------------------------------------------------------- */

	public function css() {
		echo '<style>' .
			// چیدمان صفحه «آمار دستیار شاپ» (v1.7.0 — جایگزین ابزارک‌های پیشخوان وردپرس)
			'.dastyarc-stats-page .dcd-page{max-width:1240px;margin:16px 0}' .
			'.dcd-page-head{margin:6px 0 16px}' .
			'.dcd-page-title{margin:0;font-size:22px;font-weight:800;color:#242536;display:flex;align-items:center;gap:10px;flex-wrap:wrap}' .
			'.dcd-page-title .dcp-ver{background:#eaf7f1;color:#0f7a52;border:1px solid #cdeedd;padding:3px 11px;border-radius:999px;font-size:11px;font-weight:700;direction:ltr}' .
			/* v1.7.5 — مورد ۸: کارت‌های هماهنگ با زبان dcp سایت مرکز (مرز خنثی + سربرگ تیره یکدست) */
			'.dcd-page-sub{margin:6px 0 0;color:#687a72;font-size:13px;max-width:760px;line-height:1.9}' .
			'.dcd-page-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(430px,1fr));gap:16px;align-items:start}' .
			'.dcd-card{background:#fff;border:1px solid #e6e8ef;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(16,24,40,.05)}' .
			'.dcd-card-title{margin:0;padding:12px 16px;background:#242536;color:#fff;font-weight:800;font-size:14px}' .
			'.dcd-card-body{padding:14px 16px 16px}' .
			'@media(max-width:782px){.dcd-page-grid{grid-template-columns:1fr}}' .
			// اسلکتورهای پست‌باکس قدیمی (بدون استفاده از v1.7.0 — فقط برای سازگاری باقی می‌مانند)
			'#dastyarc_dash_setup .postbox-header,#dastyarc_dash_connection .postbox-header,#dastyarc_dash_orders .postbox-header,#dastyarc_dash_rma .postbox-header{background:linear-gradient(135deg,#17a16d,#0f7a52);border-color:#0f7a52}' .
			'#dastyarc_dash_setup .postbox-header h2,#dastyarc_dash_connection .postbox-header h2,#dastyarc_dash_orders .postbox-header h2,#dastyarc_dash_rma .postbox-header h2{color:#fff;font-weight:800}' .
			'#dastyarc_dash_setup .postbox-header .handle-actions button,#dastyarc_dash_connection .postbox-header .handle-actions button,#dastyarc_dash_orders .postbox-header .handle-actions button,#dastyarc_dash_rma .postbox-header .handle-actions button{color:#fff}' .
			'#dastyarc_dash_setup,#dastyarc_dash_connection,#dastyarc_dash_orders,#dastyarc_dash_rma{border:1.5px solid #cdeee0;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(23,161,109,.08)}' .
			'.dcd-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:12px}' .
			'.dcd-stat{display:flex;flex-direction:column;align-items:center;gap:2px;background:#f4fbf8;border:1px solid #cdeee0;border-radius:12px;padding:14px 8px;text-decoration:none;transition:.2s}' .
			'.dcd-stat-link:hover{box-shadow:0 6px 16px rgba(23,161,109,.18);transform:translateY(-2px)}' .
			'.dcd-stat strong{font-size:22px;color:#12875c}' .
			'.dcd-stat span{font-size:11.5px;color:#4d6b5f;text-align:center}' .
			'.dcd-stat-amber strong{color:#b78103}' .
			'.dcd-badge{display:inline-block;color:#fff;border-radius:10px;padding:1px 9px;font-size:11px}' .
			'.dcd-table{margin-top:10px}.dcd-links{margin:12px 0 0}' .
			'.dcd-btn{background:#17a16d!important;border-color:#17a16d!important}' .
			'.dcd-empty{color:#687a72}' .
			'.dcd-big{font-size:14.5px}.dcd-big strong{font-size:24px;color:#12875c}' .
			'.dcd-meta{color:#687a72;font-size:12.5px}' .
			'.dcd-status{font-weight:700;font-size:14px;display:flex;align-items:center;gap:8px}' .
			'.dcd-status .dcd-dot{display:inline-block;width:10px;height:10px;border-radius:50%}' .
			'.dcd-ok{color:#0f7a52}.dcd-ok .dcd-dot{background:#17a16d;box-shadow:0 0 0 4px rgba(23,161,109,.18)}' .
			'.dcd-bad{color:#c0392b}.dcd-bad .dcd-dot{background:#c0392b;box-shadow:0 0 0 4px rgba(192,57,43,.15)}' .
			'.dcd-alert{background:#fff8e5;border:1px solid #f0c36d;border-radius:10px;padding:8px 12px}' .
			'.dcd-alert-red{background:#fdecea!important;border-color:#f0a9a3!important}' .
			/* ویزارد راه‌اندازی (v1.5.0) */
			'.dcd-steps{margin:0 0 14px;padding:0;list-style:none}' .
			'.dcd-step{display:flex;align-items:center;gap:10px;padding:9px 12px;margin-bottom:8px;background:#f4fbf8;border:1px solid #cdeee0;border-radius:12px;font-size:13px}' .
			'.dcd-stepnum{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#fff;border:2px solid #a9dcc4;color:#12875c;font-weight:800;font-size:12px;flex-shrink:0}' .
			'.dcd-step.dcd-done{background:#e5f5ee;border-color:#17a16d}' .
			'.dcd-step.dcd-done .dcd-stepnum{background:#17a16d;border-color:#17a16d;color:#fff}' .
			'.dcd-step.dcd-done .dcd-steplabel{color:#0f5132;font-weight:700}' .
			'.dcd-steplink{margin-right:auto;color:#12875c;font-weight:700;text-decoration:none;white-space:nowrap}' .
			'.dcd-steplink:hover{color:#17a16d}' .
			'.dcd-progressbar{height:10px;border-radius:6px;background:#e3efe9;overflow:hidden}' .
			'.dcd-progressbar span{display:block;height:100%;border-radius:6px;background:linear-gradient(90deg,#2ac28a,#17a16d);transition:width .5s}' .
			'.dcd-finish{background:#e5f5ee;border:1px solid #17a16d;border-radius:12px;padding:10px 14px;color:#0f5132;font-weight:800;text-align:center}' .
			/* نمودار هفتگی (v1.5.0) */
			'.dcd-chartwrap{background:#f4fbf8;border:1px solid #cdeee0;border-radius:12px;padding:12px 14px;margin:12px 0}' .
			'.dcd-charthead{color:#284436;font-size:13px;margin-bottom:8px}' .
			'.dcd-chart{display:flex;align-items:flex-end;gap:6px;height:100px}' .
			'.dcd-col{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:3px;height:100%}' .
			'.dcd-val{font-size:10.5px;color:#12875c;font-weight:700}' .
			'.dcd-bar{width:70%;max-width:30px;background:linear-gradient(180deg,#2ac28a,#17a16d);border-radius:6px 6px 3px 3px;min-height:3px;transition:height .4s}' .
			'.dcd-day{font-size:10.5px;color:#4d6b5f}' .
			/* ── پنل حرفه‌ای آمار سفارش‌ها (v1.8.0) ── */
			'.dcd-card-full{grid-column:1/-1}' .
			'.dcd2-kpirow{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:16px}' .
			'.dcd2-kpi{display:flex;gap:10px;align-items:center;background:linear-gradient(180deg,#fbfdfc,#f4fbf8);border:1px solid #e2efe7;border-radius:16px;padding:12px 13px;position:relative;overflow:hidden;transition:.2s}' .
			'.dcd2-kpi:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(23,161,109,.12)}' .
			'.dcd2-kpi:before{content:"";position:absolute;inset-inline-start:0;top:0;bottom:0;width:4px;background:var(--kpi,#17a16d)}' .
			'.dcd2-kpi-ic{display:inline-flex;width:42px;height:42px;border-radius:12px;background:#fff;border:1px solid #e2efe7;align-items:center;justify-content:center;flex-shrink:0}' .
			'.dcd2-kpi strong{display:block;font-size:19px;color:#242536;line-height:1.2;direction:rtl}' .
			'.dcd2-kpi .dcd2-kpi-lb{display:block;font-size:11.5px;font-weight:700;color:#4d6b5f;margin-top:1px}' .
			'.dcd2-kpi small{display:block;font-size:10.5px;color:#8b978f;margin-top:2px;line-height:1.5}' .
			'.dcd2-charts{display:grid;grid-template-columns:1.9fr 1.1fr;gap:14px;align-items:stretch}' .
			'@media(max-width:960px){.dcd2-charts{grid-template-columns:1fr}}' .
			'.dcd2-panel{background:#fbfdfc;border:1px solid #e2efe7;border-radius:16px;padding:14px 16px}' .
			'.dcd2-panel-head{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;color:#284436;font-size:13px;flex-wrap:wrap}' .
			'.dcd2-panel-head span{color:#8b978f;font-size:11.5px;font-weight:400}' .
			'.dcd2-svgbars{width:100%;height:auto;display:block}' .
			'.dcd2-grid{stroke:#e6efe9;stroke-width:1}' .
			'.dcd2-ytxt{font-size:10px;fill:#9aa8a0}' .
			'.dcd2-bar{fill:url(#dcd2bargrad);fill:#2fce96;transition:.2s}' .
			'.dcd2-bar:hover{fill:#17a16d}' .
			'.dcd2-bar-zero{fill:#e3efe9}' .
			'.dcd2-bval{font-size:10px;fill:#12875c;font-weight:700;text-anchor:middle}' .
			'.dcd2-xtxt{font-size:10px;fill:#8b978f;text-anchor:middle}' .
			'.dcd2-xm{font-size:10.5px;fill:#4d6b5f;font-weight:700}' .
			'.dcd2-donutwrap{display:flex;gap:16px;align-items:center;flex-wrap:wrap;justify-content:center}' .
			'.dcd2-svgdonut{width:150px;height:150px;flex-shrink:0}' .
			'.dcd2-dbg{fill:none;stroke:#e6efe9;stroke-width:20}' .
			'.dcd2-dseg{fill:none;stroke-width:20;transition:stroke-width .15s}' .
			'.dcd2-dseg:hover{stroke-width:24}' .
			'.dcd2-dnum{font-size:26px;font-weight:800;fill:#242536}' .
			'.dcd2-dcap{font-size:10.5px;fill:#8b978f}' .
			'.dcd2-legend{margin:0;padding:0;list-style:none;min-width:170px;flex:1}' .
			'.dcd2-legend li{display:flex;align-items:center;gap:7px;padding:5px 0;border-bottom:1px dashed #e6efe9;font-size:11.8px;color:#4d6b5f}' .
			'.dcd2-legend li:last-child{border-bottom:0}' .
			'.dcd2-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}' .
			'.dcd2-lg-lb{flex:1}' .
			'.dcd2-legend strong{color:#242536}' .
			'.dcd2-legend small{color:#9aa8a0;min-width:34px;text-align:left}' .
			'</style>';

		// حالت تاریک سازمانی (v1.5.0) — v1.7.0: روی صفحه آمار هم اعمال می‌شود (.dcd-dark .dcd-card)
		if ( self::dark() ) {
			echo '<style>' .
				'.dcd-dark .dcd-card{background:#131f1a;border-color:#243c31}' .
				'.dcd-dark .dcd-card-body{color:#cfe2d8}' .
				'.dcd-dark .dcd-page-title{color:#eaf7f1}.dcd-dark .dcd-page-sub{color:#93aca0}' .
				'#dastyarc_dash_setup,#dastyarc_dash_connection,#dastyarc_dash_orders,#dastyarc_dash_rma{background:#131f1a;border-color:#243c31;color:#cfe2d8}' .
				'#dastyarc_dash_setup .inside,#dastyarc_dash_connection .inside,#dastyarc_dash_orders .inside,#dastyarc_dash_rma .inside{background:#131f1a;color:#cfe2d8}' .
				'.dcd-stat{background:#1a2a22;border-color:#2c4639}' .
				'.dcd-stat strong{color:#5fd6a8}.dcd-stat span{color:#93aca0}' .
				'.dcd-stat-amber strong{color:#e3b341}' .
				'.dcd-chartwrap{background:#1a2a22;border-color:#2c4639}' .
				'.dcd-charthead,.dcd-val{color:#5fd6a8}.dcd-day{color:#93aca0}' .
				'.dcd-bar{background:linear-gradient(180deg,#2ac28a,#0f7a52)}' .
				'.dcd-big,.dcd-big strong,.dcd-empty,.dcd-meta{color:#cfe2d8}.dcd-big strong{color:#5fd6a8}' .
				'.dcd-step{background:#1a2a22;border-color:#2c4639;color:#cfe2d8}' .
				'.dcd-stepnum{background:#131f1a;border-color:#2c4639;color:#5fd6a8}' .
				'.dcd-step.dcd-done{background:#16352a;border-color:#17a16d}' .
				'.dcd-progressbar{background:#243c31}' .
				'.dcd-finish{background:#16352a;color:#cfeedf}' .
				'.dcd-alert{background:#2a2410;border-color:#6d5a1d;color:#e8d9a0}' .
				'.dcd-alert-red{background:#2a1414!important;border-color:#6d2a26!important;color:#f0b8b3!important}' .
				'.dcd-table td,.dcd-table th{color:#cfe2d8!important}' .
				/* ── حالت تاریک: پنل آمار v1.8.0 ── */
				'.dcd-dark .dcd2-kpi{background:#16221c;border-color:#243c31}' .
				'.dcd-dark .dcd2-kpi-ic{background:#131f1a;border-color:#2c4639}' .
				'.dcd-dark .dcd2-kpi strong{color:#e8f5ee}.dcd-dark .dcd2-kpi .dcd2-kpi-lb{color:#9dc4b1}' .
				'.dcd-dark .dcd2-kpi small{color:#7e968a}' .
				'.dcd-dark .dcd2-panel{background:#16221c;border-color:#243c31}' .
				'.dcd-dark .dcd2-panel-head{color:#cfe2d8}.dcd-dark .dcd2-panel-head span{color:#7e968a}' .
				'.dcd-dark .dcd2-grid{stroke:#243c31}' .
				'.dcd-dark .dcd2-ytxt,.dcd-dark .dcd2-xtxt{fill:#7e968a}' .
				'.dcd-dark .dcd2-xm{fill:#9dc4b1}' .
				'.dcd-dark .dcd2-bar{fill:#2ac28a}.dcd-dark .dcd2-bar:hover{fill:#5fd6a8}' .
				'.dcd-dark .dcd2-bar-zero{fill:#243c31}' .
				'.dcd-dark .dcd2-bval{fill:#5fd6a8}' .
				'.dcd-dark .dcd2-dbg{stroke:#243c31}' .
				'.dcd-dark .dcd2-dnum{fill:#e8f5ee}.dcd-dark .dcd2-dcap{fill:#7e968a}' .
				'.dcd-dark .dcd2-legend li{color:#9dc4b1;border-bottom-color:#243c31}' .
				'.dcd-dark .dcd2-legend strong{color:#e8f5ee}.dcd-dark .dcd2-legend small{color:#7e968a}' .
				'</style>';
		}
	}
}
