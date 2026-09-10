<?php
/**
 * ابزارک‌های صفحه «پیشخوان وردپرس» سایت مرکزی — با تم رنگی سازمانی (#17a16d / #242536).
 * هر ابزارک به‌صورت جداگانه از صفحه «دستیار شاپ ← وضعیت و تنظیمات» قابل فعال/غیرفعال کردن است.
 *
 * ابزارک‌ها:
 *  ۱) نمای کلی پلتفرم      → آمار کلی + نمودار سفارش‌های ۷ روز اخیر
 *  ۲) فروش امروز           → مبلغ/تعداد/میانگین سفارش‌های دستیار امروز + برترین فروشندگان روز
 *  ۳) نبض کیف پول          → مجموع موجودی‌ها، فروشندگان موجودی‌کم، فهرست پایش
 *  ۴) عودت و مرجوعی        → شمارش بر اساس وضعیت + آخرین گزارش‌ها
 *  ۵) فروشندگان متصل       → لیست فروشندگان دارای اتصال فعال
 *  ۶) سلامت اتصال          → آخرین پینگ کانکتور هر فروشگاه (رادار) + نسخه کانکتور
 *
 * v1.5.0: نمودار میله‌ای سبک (بدون کتابخانه خارجی) + حالت تاریک سازمانی
 * v1.8.0: بازطراحی مینیمال/حرفه‌ای (هدر سرمه‌ای تخت، کارت‌های تخت، بدون گرادیان پررنگ)
 *         + ۳ ابزارک جدید (sales / wallets / health).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Dashboard {

	const OPTION = 'dastyar_widgets_enabled';

	/** سقف «موجودی کم» کیف پول (تومان) — قابل فیلتر */
	public static function wallet_low_threshold() {
		return (float) apply_filters( 'dastyar_dash_wallet_low', 100000 );
	}

	/** ابزارک‌های موجود: کلید => عنوان */
	public static function widgets() {
		return array(
			'overview' => 'نمای کلی پلتفرم دستیار شاپ',
			'sales'    => 'ابزارک فروش امروز',
			'wallets'  => 'ابزارک نبض کیف پول',
			'rma'      => 'ابزارک عودت و مرجوعی',
			'vendors'  => 'ابزارک فروشندگان متصل',
			'health'   => 'ابزارک سلامت اتصال',
		);
	}

	/** آیا ابزارک فعال است؟ (پیش‌فرض: فعال) */
	public static function enabled( $key ) {
		$opt = get_option( self::OPTION, null );
		if ( ! is_array( $opt ) || ! array_key_exists( $key, $opt ) ) {
			return true; // تا کاربر دوباره تنظیم نکرده، همه روشن
		}
		return ! empty( $opt[ $key ] );
	}

	/** حالت تاریک سازمانی فعال است؟ */
	public static function dark() {
		return 'yes' === get_option( 'dastyar_dark_mode', 'no' );
	}

	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register' ) );
		add_action( 'admin_head-index.php', array( $this, 'css' ) );
	}

	public function register() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( self::enabled( 'overview' ) ) {
			wp_add_dashboard_widget( 'dastyar_dash_overview', 'نمای کلی دستیار شاپ', array( $this, 'render_overview' ) );
		}
		if ( self::enabled( 'sales' ) ) {
			wp_add_dashboard_widget( 'dastyar_dash_sales', 'فروش امروز', array( $this, 'render_sales' ) );
		}
		if ( self::enabled( 'wallets' ) ) {
			wp_add_dashboard_widget( 'dastyar_dash_wallets', 'نبض کیف پول', array( $this, 'render_wallets' ) );
		}
		if ( self::enabled( 'rma' ) ) {
			wp_add_dashboard_widget( 'dastyar_dash_rma', 'عودت و مرجوعی', array( $this, 'render_rma' ) );
		}
		if ( self::enabled( 'vendors' ) ) {
			wp_add_dashboard_widget( 'dastyar_dash_vendors', 'فروشندگان متصل', array( $this, 'render_vendors' ) );
		}
		if ( self::enabled( 'health' ) ) {
			wp_add_dashboard_widget( 'dastyar_dash_health', 'سلامت اتصال فروشگاه‌ها', array( $this, 'render_health' ) );
		}
	}

	/* ------------------------------------------------------------------
	 * شمارنده‌های مشترک
	 * ---------------------------------------------------------------- */

	protected static function count_rma_by_status( array $statuses ) {
		return count( get_posts( array(
			'post_type'      => Dastyar_Rma::POST_TYPE,
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => Dastyar_Rma::M_STATUS, 'value' => $statuses, 'compare' => 'IN' ),
			),
		) ) );
	}

	/** مرجوعی‌های باز = نیازمند اقدام مدیر (همان لیست حباب منو) */
	public static function open_rma_statuses() {
		return array( 'submitted', 'reviewing', 'need_info', 'approved', 'await_item', 'item_received', 'final_review' );
	}

	/** نام نمایشی فروشنده (فروشگاه > نام کاربر) */
	protected static function vendor_label( $uid ) {
		$shop = (string) get_user_meta( $uid, '_dastyar_shop_name', true );
		if ( '' !== $shop ) {
			return $shop;
		}
		$user = get_userdata( $uid );
		return $user ? $user->display_name : ( 'کاربر #' . (int) $uid );
	}

	/* ------------------------------------------------------------------
	 * ابزارک ۱: نمای کلی
	 * ---------------------------------------------------------------- */

	public function render_overview() {
		$connected       = count( Dastyar::instance()->vendors->connected_vendors() );
		$pending_vendors = count( get_users( array( 'meta_key' => Dastyar_Registration::PENDING, 'meta_value' => 1, 'fields' => 'ids' ) ) );
		$open_rmas       = self::count_rma_by_status( self::open_rma_statuses() );

		echo '<div class="dsd-grid">';
		$this->stat_card( $connected, 'فروشنده متصل', admin_url( 'admin.php?page=dastyar-vendors' ), 'green' );
		$this->stat_card( $pending_vendors, 'در انتظار تأیید', admin_url( 'admin.php?page=dastyar-vendors' ), 'amber' );
		$this->stat_card( $open_rmas, 'مرجوعی باز', admin_url( 'admin.php?page=dastyar' ), 'red' );
		echo '</div>';

		// نمودار سفارش‌های دستیار ۷ روز اخیر (v1.5.0)
		$this->orders_chart();

		echo '<p class="dsd-links">';
		echo '<a class="button button-primary dsd-btn" href="' . esc_url( admin_url( 'admin.php?page=dastyar' ) ) . '">مدیریت مرجوعی‌ها</a> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=dastyar-status' ) ) . '">وضعیت و تنظیمات</a>';
		echo '</p>';

		if ( $pending_vendors > 0 ) {
			echo '<p class="dsd-alert">⏳ ' . (int) $pending_vendors . ' فروشنده در انتظار تأیید شماست.</p>';
		}
	}

	protected function stat_card( $num, $label, $url, $tone = 'green' ) {
		printf(
			'<a class="dsd-stat dsd-stat-%s" href="%s"><strong>%s</strong><span>%s</span></a>',
			esc_attr( $tone ),
			esc_url( $url ),
			esc_html( (string) $num ),
			esc_html( $label )
		);
	}

	/**
	 * نمودار میله‌ای خالص CSS — تعداد سفارش‌های دستیار در ۷ روز گذشته.
	 * بدون هیچ کتابخانه خارجی (سبک و چاپ‌پذیر).
	 */
	protected function orders_chart() {
		$data = array();
		$max  = 1;
		for ( $i = 6; $i >= 0; $i-- ) {
			$from   = strtotime( 'today -' . $i . ' days' );
			$to     = $from + DAY_IN_SECONDS - 1;
			$orders = wc_get_orders( array(
				'limit'        => 200,
				'return'       => 'ids',
				'date_created' => $from . '...' . $to,
				'meta_key'     => '_dastyar_vendor_id',
				'meta_compare' => 'EXISTS',
			) );
			$count = is_array( $orders ) ? count( $orders ) : 0;
			$max   = max( $max, $count );
			$data[] = array(
				'count' => $count,
				'label' => Dastyar_Jalali::format( 'D', $from ),
				'date'  => Dastyar_Jalali::format( 'Y/m/d', $from ),
			);
		}
		$total = array_sum( wp_list_pluck( $data, 'count' ) );

		echo '<div class="dsd-chartwrap">';
		printf( '<div class="dsd-charthead"><strong>سفارش‌های دستیار — ۷ روز اخیر</strong><span class="dsd-charttotal">%d سفارش</span></div>', (int) $total );
		echo '<div class="dsd-chart">';
		foreach ( $data as $d ) {
			$height = max( 3, (int) round( $d['count'] / $max * 100 ) );
			printf(
				'<div class="dsd-col" title="%s: %d سفارش"><span class="dsd-val">%d</span><span class="dsd-bar" style="height:%d%%"></span><span class="dsd-day">%s</span></div>',
				esc_attr( $d['date'] ),
				(int) $d['count'],
				(int) $d['count'],
				$height,
				esc_html( $d['label'] )
			);
		}
		echo '</div></div>';
	}

	/* ------------------------------------------------------------------
	 * ابزارک ۲: فروش امروز (v1.8.0)
	 * ---------------------------------------------------------------- */

	/** سفارش‌های دستیار امروز: [تعداد، مبلغ کل، جمع به تفکیک فروشنده] */
	public static function today_sales() {
		$from = (int) strtotime( 'today' );
		$to   = $from + DAY_IN_SECONDS - 1;

		$orders = function_exists( 'wc_get_orders' ) ? wc_get_orders( array(
			'limit'        => 300,
			'date_created' => $from . '...' . $to,
		) ) : array();

		$sum       = 0.0;
		$count     = 0;
		$by_vendor = array();
		foreach ( (array) $orders as $o ) {
			if ( ! is_object( $o ) || ! method_exists( $o, 'get_meta' ) ) {
				continue;
			}
			$vid = (int) $o->get_meta( '_dastyar_vendor_id' );
			if ( ! $vid ) {
				continue; // فقط سفارش‌های منتقل‌شده به مرکز
			}
			// فیلتر دفاعی تاریخ (در HPOS/CPT کوئری خودش می‌زند؛ این برای اطمینان است)
			$dt = method_exists( $o, 'get_date_created' ) ? $o->get_date_created() : null;
			$ts = ( $dt && method_exists( $dt, 'getTimestamp' ) ) ? (int) $dt->getTimestamp() : 0;
			if ( $ts && ( $ts < $from || $ts > $to ) ) {
				continue;
			}
			$amt = (float) $o->get_meta( '_dastyar_goods_total' );
			if ( $amt <= 0 && method_exists( $o, 'get_total' ) ) {
				$amt = (float) $o->get_total();
			}
			$sum += $amt;
			$count++;
			$by_vendor[ $vid ] = ( isset( $by_vendor[ $vid ] ) ? $by_vendor[ $vid ] : 0 ) + $amt;
		}
		arsort( $by_vendor );

		return array( 'count' => $count, 'sum' => $sum, 'by_vendor' => $by_vendor );
	}

	public function render_sales() {
		$t   = self::today_sales();
		$avg = $t['count'] ? $t['sum'] / $t['count'] : 0;

		echo '<div class="dsd-grid">';
		$this->stat_card( number_format( (float) $t['sum'] ), 'فروش امروز (تومان)', admin_url( 'edit.php?post_type=shop_order' ), 'green' );
		$this->stat_card( (int) $t['count'], 'سفارش امروز', admin_url( 'edit.php?post_type=shop_order' ), 'green' );
		$this->stat_card( number_format( (float) $avg ), 'میانگین سبد (تومان)', admin_url( 'edit.php?post_type=shop_order' ), 'navy' );
		echo '</div>';

		if ( ! empty( $t['by_vendor'] ) ) {
			echo '<div class="dsd-minihead">برترین فروشندگان امروز</div><ul class="dsd-list">';
			$i = 0;
			foreach ( $t['by_vendor'] as $vid => $amt ) {
				if ( ++$i > 5 ) {
					break;
				}
				printf(
					'<li><span class="dsd-rank">%d</span><strong>%s</strong><span class="dsd-amt">%s تومان</span></li>',
					(int) $i,
					esc_html( self::vendor_label( $vid ) ),
					esc_html( number_format( (float) $amt ) )
				);
			}
			echo '</ul>';
		} else {
			echo '<p class="dsd-empty">امروز هنوز سفارش دستیاری ثبت نشده است.</p>';
		}
	}

	/* ------------------------------------------------------------------
	 * ابزارک ۳: نبض کیف پول (v1.8.0)
	 * ---------------------------------------------------------------- */

	public function render_wallets() {
		$vendors = Dastyar::instance()->vendors->connected_vendors();
		$low_th  = self::wallet_low_threshold();

		$total = 0.0;
		$low   = 0;
		$rows  = array();
		foreach ( $vendors as $uid ) {
			$bal    = (float) Dastyar::instance()->wallet->get_balance( $uid );
			$total += $bal;
			if ( $bal < $low_th ) {
				$low++;
			}
			$rows[ (int) $uid ] = $bal;
		}
		arsort( $rows );

		echo '<div class="dsd-grid">';
		$this->stat_card( number_format( $total ), 'مجموع موجودی‌ها (تومان)', admin_url( 'admin.php?page=dastyar-vendors' ), 'green' );
		$this->stat_card( $low, 'موجودی کمتر از ' . number_format( $low_th ), admin_url( 'admin.php?page=dastyar-vendors' ), $low ? 'red' : 'green' );
		$this->stat_card( count( $vendors ), 'کیف پول فعال', admin_url( 'admin.php?page=dastyar-vendors' ), 'navy' );
		echo '</div>';

		if ( $rows ) {
			echo '<ul class="dsd-list">';
			$i = 0;
			foreach ( $rows as $uid => $bal ) {
				if ( ++$i > 6 ) {
					break;
				}
				printf(
					'<li><span class="dsd-dot%s"></span><strong>%s</strong><span class="dsd-amt%s">%s تومان</span></li>',
					$bal < $low_th ? ' dsd-dot-red' : '',
					esc_html( self::vendor_label( $uid ) ),
					$bal < $low_th ? ' dsd-amt-red' : '',
					esc_html( number_format( (float) $bal ) )
				);
			}
			echo '</ul>';
		} else {
			echo '<p class="dsd-empty">فروشنده متصلی یافت نشد.</p>';
		}
	}

	/* ------------------------------------------------------------------
	 * ابزارک ۴: عودت و مرجوعی
	 * ---------------------------------------------------------------- */

	public function render_rma() {
		$groups = array(
			'جدید و در بررسی'      => array( 'submitted', 'reviewing' ),
			'نیاز به اطلاعات'      => array( 'need_info' ),
			'در انتظار/دریافت کالا' => array( 'approved', 'await_item', 'item_received' ),
			'بررسی نهایی'          => array( 'final_review' ),
		);
		echo '<div class="dsd-grid dsd-grid-4">';
		foreach ( $groups as $label => $statuses ) {
			$this->stat_card( self::count_rma_by_status( $statuses ), $label, admin_url( 'admin.php?page=dastyar' ), 'green' );
		}
		echo '</div>';

		$recent = get_posts( array(
			'post_type'      => Dastyar_Rma::POST_TYPE,
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		if ( $recent ) {
			echo '<table class="widefat striped dsd-table"><thead><tr><th>گزارش</th><th>سفارش</th><th>وضعیت</th></tr></thead><tbody>';
			foreach ( $recent as $p ) {
				$st = (string) get_post_meta( $p->ID, Dastyar_Rma::M_STATUS, true );
				printf(
					'<tr><td><a href="%s">#%d</a></td><td>%s</td><td><span class="dsd-badge" style="background:%s">%s</span></td></tr>',
					esc_url( admin_url( 'admin.php?page=dastyar&view=' . (int) $p->ID ) ),
					(int) $p->ID,
					esc_html( (string) get_post_meta( $p->ID, Dastyar_Rma::M_ORDER, true ) ),
					esc_attr( Dastyar_Rma::status_color( $st ) ),
					esc_html( Dastyar_Rma::status_label( $st ) )
				);
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="dsd-empty">هنوز گزارش عودتی ثبت نشده است.</p>';
		}
		echo '<p class="dsd-links"><a class="button button-primary dsd-btn" href="' . esc_url( admin_url( 'admin.php?page=dastyar' ) ) . '">مشاهده همه گزارش‌ها</a></p>';
	}

	/* ------------------------------------------------------------------
	 * ابزارک ۵: فروشندگان متصل
	 * ---------------------------------------------------------------- */

	public function render_vendors() {
		$vendors = Dastyar::instance()->vendors->connected_vendors();
		echo '<p class="dsd-big"><strong>' . (int) count( $vendors ) . '</strong> فروشگاه به پلتفرم شما متصل است.</p>';
		if ( $vendors ) {
			echo '<ul class="dsd-list">';
			foreach ( array_slice( $vendors, 0, 8 ) as $uid ) {
				$user = get_userdata( $uid );
				$site = Dastyar::instance()->vendors->site_url( $uid );
				printf(
					'<li><span class="dsd-dot"></span><strong>%s</strong> <a href="%s" target="_blank" rel="noopener" class="dsd-site">%s</a></li>',
					esc_html( $user ? $user->display_name : ( 'کاربر #' . $uid ) ),
					esc_url( $site ),
					esc_html( $site )
				);
			}
			echo '</ul>';
		}
		echo '<p class="dsd-links"><a class="button button-primary dsd-btn" href="' . esc_url( admin_url( 'admin.php?page=dastyar-vendors' ) ) . '">مدیریت فروشندگان</a></p>';
	}

	/* ------------------------------------------------------------------
	 * ابزارک ۶: سلامت اتصال فروشگاه‌ها (v1.8.0)
	 * وضعیت پینگ کانکتور (متا _dastyar_last_ping + _dastyar_conn_version که
	 * رادار نسخه — در «کنسول مرکز» یا هر منبع دیگر — ثبت می‌کند).
	 * ---------------------------------------------------------------- */

	/** وضعیت پینگ: ok (≤۲۴ساعت) / warn (≤۷۲ساعت) / idle (قدیمی‌تر یا هرگز) */
	public static function ping_state( $ts ) {
		$ts = (int) $ts;
		if ( $ts <= 0 ) {
			return 'idle';
		}
		$age = time() - $ts;
		if ( $age <= DAY_IN_SECONDS ) {
			return 'ok';
		}
		if ( $age <= 3 * DAY_IN_SECONDS ) {
			return 'warn';
		}
		return 'idle';
	}

	public function render_health() {
		$vendors = Dastyar::instance()->vendors->connected_vendors();

		$ok = $warn = $idle = 0;
		$rows = array();
		foreach ( $vendors as $uid ) {
			$ts   = (int) get_user_meta( $uid, '_dastyar_last_ping', true );
			$st   = self::ping_state( $ts );
			$rows[] = array( 'uid' => (int) $uid, 'ts' => $ts, 'state' => $st );
			if ( 'ok' === $st ) {
				$ok++;
			} elseif ( 'warn' === $st ) {
				$warn++;
			} else {
				$idle++;
			}
		}

		echo '<div class="dsd-grid">';
		$this->stat_card( $ok, 'آنلاین (۲۴ ساعت)', admin_url( 'admin.php?page=dastyar-vendors' ), 'green' );
		$this->stat_card( $warn, 'دیرکرد (۷۲ ساعت)', admin_url( 'admin.php?page=dastyar-vendors' ), 'amber' );
		$this->stat_card( $idle, 'بدون سیگنال', admin_url( 'admin.php?page=dastyar-vendors' ), 'red' );
		echo '</div>';

		if ( $rows ) {
			echo '<ul class="dsd-list">';
			foreach ( array_slice( $rows, 0, 8 ) as $r ) {
				$ver = (string) get_user_meta( $r['uid'], '_dastyar_conn_version', true );
				printf(
					'<li><span class="dsd-sig dsd-sig-%s"></span><strong>%s</strong><span class="dsd-meta">%s%s</span></li>',
					esc_attr( $r['state'] ),
					esc_html( self::vendor_label( $r['uid'] ) ),
					esc_html( $r['ts'] ? ( 'آخرین پینگ: ' . Dastyar_Jalali::format( 'j F، H:i', $r['ts'] ) ) : 'بدون سیگنال' ),
					esc_html( '' !== $ver ? ' — کانکتور ' . $ver : '' )
				);
			}
			echo '</ul>';
		} else {
			echo '<p class="dsd-empty">فروشنده متصلی برای پایش یافت نشد.</p>';
		}
	}

	/* ------------------------------------------------------------------
	 * استایل برند (فقط صفحه پیشخوان) + حالت تاریک اختیاری
	 * v1.8.0 — مینیمال: هدر سرمه‌ای تخت + کارت‌های تخت + ریزکنترل‌های سبز.
	 * ---------------------------------------------------------------- */

	public function css() {
		echo '<style>' .
			// قاب ابزارک‌ها
			'#dastyar_dash_overview,#dastyar_dash_sales,#dastyar_dash_wallets,#dastyar_dash_rma,#dastyar_dash_vendors,#dastyar_dash_health{border:1px solid #e6ecea;border-radius:16px;overflow:hidden;box-shadow:0 2px 10px rgba(36,37,54,.05)}' .
			'#dastyar_dash_overview .postbox-header,#dastyar_dash_sales .postbox-header,#dastyar_dash_wallets .postbox-header,#dastyar_dash_rma .postbox-header,#dastyar_dash_vendors .postbox-header,#dastyar_dash_health .postbox-header{background:#242536;border-bottom:3px solid #17a16d}' .
			'#dastyar_dash_overview .postbox-header h2,#dastyar_dash_sales .postbox-header h2,#dastyar_dash_wallets .postbox-header h2,#dastyar_dash_rma .postbox-header h2,#dastyar_dash_vendors .postbox-header h2,#dastyar_dash_health .postbox-header h2{color:#fff;font-weight:800;font-size:13px}' .
			'#dastyar_dash_overview .postbox-header .handle-actions button,#dastyar_dash_sales .postbox-header .handle-actions button,#dastyar_dash_wallets .postbox-header .handle-actions button,#dastyar_dash_rma .postbox-header .handle-actions button,#dastyar_dash_vendors .postbox-header .handle-actions button,#dastyar_dash_health .postbox-header .handle-actions button{color:#fff}' .
			// کارت آماری تخت
			'.dsd-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:12px}' .
			'.dsd-grid-4{grid-template-columns:repeat(4,1fr)}' .
			'.dsd-stat{display:flex;flex-direction:column;align-items:center;gap:2px;background:#fafcfb;border:1px solid #e8f0ec;border-radius:12px;padding:13px 8px;text-decoration:none;transition:border-color .2s,box-shadow .2s}' .
			'.dsd-stat:hover{border-color:#17a16d;box-shadow:0 4px 12px rgba(23,161,109,.12)}' .
			'.dsd-stat strong{font-size:21px;color:#12875c;font-weight:800;letter-spacing:-.2px}' .
			'.dsd-stat span{font-size:11.5px;color:#56695f;text-align:center}' .
			'.dsd-stat-amber strong{color:#b78103}.dsd-stat-red strong{color:#c0392b}.dsd-stat-navy strong{color:#242536}' .
			// لیست‌های مینیمال
			'.dsd-list{margin:8px 0;padding:0;list-style:none}' .
			'.dsd-list li{display:flex;align-items:center;gap:8px;padding:6px 2px;border-bottom:1px solid #f0f4f2;font-size:12.5px}' .
			'.dsd-list li:last-child{border-bottom:0}' .
			'.dsd-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#17a16d;flex:none}' .
			'.dsd-dot-red{background:#c0392b}' .
			'.dsd-rank{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:7px;background:#eef5f1;color:#12875c;font-size:11px;font-weight:800;flex:none}' .
			'.dsd-amt{margin-right:auto;color:#12875c;font-weight:700;font-size:12px;white-space:nowrap}' .
			'.dsd-amt-red{color:#c0392b}' .
			'.dsd-meta{margin-right:auto;color:#8a978f;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' .
			'.dsd-minihead{font-size:12px;font-weight:800;color:#242536;margin:4px 0 2px;padding-right:2px}' .
			// نشانگر سلامت اتصال
			'.dsd-sig{display:inline-block;width:9px;height:9px;border-radius:50%;flex:none}' .
			'.dsd-sig-ok{background:#17a16d;box-shadow:0 0 0 3px rgba(23,161,109,.15)}' .
			'.dsd-sig-warn{background:#e0a800;box-shadow:0 0 0 3px rgba(224,168,0,.15)}' .
			'.dsd-sig-idle{background:#b8c4be}' .
			// متفرقه (سازگار با قبل)
			'.dsd-badge{display:inline-block;color:#fff;border-radius:10px;padding:1px 9px;font-size:11px}' .
			'.dsd-table{margin-top:10px}.dsd-links{margin:12px 0 0}' .
			'.dsd-btn{background:#17a16d!important;border-color:#17a16d!important;border-radius:9px!important}' .
			'.dsd-alert{background:#fff8e5;border:1px solid #f0c36d;border-radius:10px;padding:8px 12px}' .
			'.dsd-empty{color:#687a72;font-size:12.5px}' .
			'.dsd-big{font-size:14px}.dsd-big strong{font-size:24px;color:#12875c}' .
			'.dsd-site{color:#17a16d;text-decoration:none;font-size:12px}' .
			// نمودار هفتگی (v1.5.0)
			'.dsd-chartwrap{background:#fafcfb;border:1px solid #e8f0ec;border-radius:12px;padding:12px 14px;margin-bottom:12px}' .
			'.dsd-charthead{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;color:#242536;font-size:12.5px}' .
			'.dsd-charttotal{background:#17a16d;color:#fff;border-radius:10px;padding:1px 10px;font-size:11px}' .
			'.dsd-chart{display:flex;align-items:flex-end;gap:6px;height:110px}' .
			'.dsd-col{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:3px;height:100%}' .
			'.dsd-val{font-size:10.5px;color:#12875c;font-weight:700}' .
			'.dsd-bar{width:70%;max-width:34px;background:#17a16d;border-radius:6px 6px 3px 3px;min-height:3px;transition:height .4s;opacity:.9}' .
			'.dsd-day{font-size:10.5px;color:#8a978f}' .
			'</style>';

		// حالت تاریک سازمانی (v1.5.0)
		if ( self::dark() ) {
			echo '<style>' .
				'#dastyar_dash_overview,#dastyar_dash_sales,#dastyar_dash_wallets,#dastyar_dash_rma,#dastyar_dash_vendors,#dastyar_dash_health{background:#131f1a;border-color:#243c31;color:#cfe2d8}' .
				'#dastyar_dash_overview .inside,#dastyar_dash_sales .inside,#dastyar_dash_wallets .inside,#dastyar_dash_rma .inside,#dastyar_dash_vendors .inside,#dastyar_dash_health .inside{background:#131f1a;color:#cfe2d8}' .
				'.dsd-stat{background:#1a2a22;border-color:#2c4639}' .
				'.dsd-stat strong{color:#5fd6a8}.dsd-stat span{color:#93aca0}' .
				'.dsd-stat-amber strong{color:#e3b341}.dsd-stat-red strong{color:#e3706a}.dsd-stat-navy strong{color:#cfe2d8}' .
				'.dsd-chartwrap{background:#1a2a22;border-color:#2c4639}' .
				'.dsd-charthead{color:#cfe2d8}.dsd-val{color:#5fd6a8}.dsd-day{color:#93aca0}' .
				'.dsd-bar{background:#2ac28a}' .
				'.dsd-big,.dsd-big strong,.dsd-empty{color:#cfe2d8}.dsd-big strong{color:#5fd6a8}' .
				'.dsd-list li{border-color:#22382d}' .
				'.dsd-rank{background:#22382d;color:#5fd6a8}.dsd-amt{color:#5fd6a8}.dsd-amt-red{color:#e3706a}.dsd-meta{color:#93aca0}.dsd-minihead{color:#cfe2d8}' .
				'.dsd-table td,.dsd-table th{color:#cfe2d8!important}' .
				'.dsd-alert{background:#2a2410;border-color:#6d5a1d;color:#e8d9a0}' .
				'</style>';
		}
	}
}
