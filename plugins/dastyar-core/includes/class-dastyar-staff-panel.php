<?php
/**
 * پنل عملیات دستیار — داشبورد مدیریتی کامل و مستقل برای کارمندان (بدون نیاز به
 * پیشخوان وردپرس): سفارش‌ها + تغییر وضعیت + کد رهگیری (تکی/گروهی) + فهرست و
 * تایید/رد/تعلیق فروشنده‌ها + نگاه کلی کیف‌پول‌ها.
 *
 * معماری: یک «قالب برگه» تمام‌صفحه و مستقل ثبت می‌شود (دقیقاً مثل قالب‌های صفحه‌ی
 * قالب سایت، ولی از خودِ این افزونه) — هیچ get_header/get_footer قالب فراخوانی
 * نمی‌شود، فقط wp_head/wp_footer (برای سازگاری با افزونه‌های دیگر مثل آنالیتیکس).
 *
 * دسترسی: current_user_can('manage_woocommerce') — همان قابلیتی که نقش «کارمند
 * دستیار» (افزونه کنسول مدیریت، در صورت نصب) می‌دهد؛ به آن افزونه وابستگی مستقیم ندارد.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Staff_Panel {

	const TPL_SLUG     = 'dastyar-staff-ops';
	const LOGIN_NONCE  = 'dastyar_ops_login';
	const ACTION_NONCE = 'dastyar_ops_action';

	public function __construct() {
		add_filter( 'theme_page_templates', array( $this, 'add_template' ) );
		add_filter( 'template_include', array( $this, 'load_template' ) );

		add_action( 'admin_post_dastyar_ops_status', array( __CLASS__, 'save_status' ) );
		add_action( 'admin_post_dastyar_ops_track', array( __CLASS__, 'save_track' ) );
		add_action( 'admin_post_dastyar_ops_label', array( __CLASS__, 'print_labels' ) );
		add_action( 'admin_post_dastyar_ops_vendor', array( __CLASS__, 'save_vendor_status' ) );
		add_action( 'admin_post_dastyar_ops_wallet', array( __CLASS__, 'save_wallet_adjust' ) );
	}

	public function add_template( $templates ) {
		$templates[ self::TPL_SLUG ] = 'دستیار — پنل عملیات (تمام‌صفحه، مستقل)';
		return $templates;
	}

	public function load_template( $template ) {
		if ( is_page() && self::TPL_SLUG === get_page_template_slug() ) {
			$custom = DASTYAR_CORE_DIR . 'templates/staff-ops.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}
		return $template;
	}

	public static function has_access() {
		return is_user_logged_in() && current_user_can( 'manage_woocommerce' );
	}

	protected static function self_url() {
		global $post;
		return $post ? get_permalink( $post ) : home_url( '/' );
	}

	/* ==================================================================
	 *  ورود / عدم دسترسی
	 * ============================================================== */

	public static function render_gate() {
		if ( ! is_user_logged_in() ) {
			return self::login_screen();
		}
		if ( ! self::has_access() ) {
			return self::denied_screen();
		}
		return null; // یعنی دسترسی هست؛ ادامه با render_shell()
	}

	protected static function login_screen() {
		$err = '';
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['dastyar_ops_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_key( $_POST['dastyar_ops_nonce'] ), self::LOGIN_NONCE ) ) {
				$err = 'نشست نامعتبر است؛ دوباره تلاش کنید.';
			} else {
				$user = wp_signon( array(
					'user_login'    => sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ),
					'user_password' => (string) ( $_POST['pwd'] ?? '' ),
					'remember'      => true,
				), is_ssl() );
				if ( is_wp_error( $user ) ) {
					$err = 'نام کاربری یا رمز عبور اشتباه است.';
				} else {
					wp_safe_redirect( self::self_url() );
					exit;
				}
			}
		}
		ob_start();
		?>
		<div class="dop-auth">
			<button type="button" class="dop-darktoggle" onclick="dopToggleDark()" title="حالت تاریک/روشن"><?php echo self::icon( 'moon' ); ?></button>
			<div class="dop-auth-box">
				<div class="dop-logo">دستیار<span>شاپ</span></div>
				<p class="dop-auth-sub">پنل عملیات — ورود کارمندان</p>
				<?php if ( $err ) : ?><p class="dop-err"><?php echo esc_html( $err ); ?></p><?php endif; ?>
				<form method="post">
					<?php wp_nonce_field( self::LOGIN_NONCE, 'dastyar_ops_nonce' ); ?>
					<input type="text" name="log" placeholder="نام کاربری یا ایمیل" required autofocus>
					<input type="password" name="pwd" placeholder="رمز عبور" required>
					<button type="submit">ورود</button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	protected static function denied_screen() {
		ob_start();
		?>
		<div class="dop-auth">
			<button type="button" class="dop-darktoggle" onclick="dopToggleDark()" title="حالت تاریک/روشن"><?php echo self::icon( 'moon' ); ?></button>
			<div class="dop-auth-box">
				<div class="dop-logo">دستیار<span>شاپ</span></div>
				<p class="dop-err">حساب شما به پنل عملیات دسترسی ندارد.</p>
				<p><a class="dop-btn ghost" href="<?php echo esc_url( wp_logout_url( self::self_url() ) ); ?>">خروج از حساب</a></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ==================================================================
	 *  پوسته اصلی: سایدبار + محتوا
	 * ============================================================== */

	public static function render_shell() {
		$sec = sanitize_key( (string) ( $_GET['osec'] ?? 'dash' ) );
		$nav = array(
			'dash'    => 'داشبورد',
			'orders'  => 'سفارش‌ها',
			'vendors' => 'فروشنده‌ها',
			'wallets' => 'کیف‌پول‌ها',
		);
		$has_console = class_exists( 'DA_Modules' );
		ob_start();
		?>
		<div class="dop-appbar">
			<button type="button" class="dop-burger" onclick="dopToggleNav(true)" aria-label="باز کردن منو"><?php echo self::icon( 'menu' ); ?></button>
			<span class="dop-app-brand"><?php echo self::icon( 'box' ); ?> دستیار<span>شاپ</span></span>
			<span style="flex:1"></span>
			<span class="dop-userchip" style="box-shadow:none"><span class="dop-uav"><?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( wp_get_current_user()->display_name, 0, 1 ) : substr( wp_get_current_user()->display_name, 0, 1 ) ); ?></span></span>
		</div>
		<div class="dop-scrim" id="dop-scrim" onclick="dopToggleNav(false)" aria-hidden="true"></div>
		<div class="dop-shell">
			<aside class="dop-side" id="dop-side">
				<div class="dop-side-head">
					<div class="dop-logo"><span class="dop-mark"><?php echo self::icon( 'box' ); ?></span><span class="dop-ltext">دستیار<span>شاپ</span><small>پنل عملیات</small></span></div>
					<button type="button" class="dop-x" onclick="dopToggleNav(false)" aria-label="بستن منو"><?php echo self::icon( 'close' ); ?></button>
				</div>
				<nav class="dop-nav">
					<p class="dop-glabel">عملیات روزانه</p>
					<?php foreach ( $nav as $key => $label ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'osec', $key, self::self_url() ) ); ?>" class="dop-link<?php echo $sec === $key ? ' on' : ''; ?>"><?php echo self::icon( self::icon_key( $key ) ); ?><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>

					<?php if ( $has_console ) : ?>
						<?php
						$cpg      = sanitize_key( (string) ( $_GET['cpg'] ?? '' ) );
						$c_groups = DA_Modules::groups();
						$c_pages  = DA_Modules::pages();
						$by_group = array();
						foreach ( $c_pages as $pkey => $p ) {
							$g = 'tools';
							foreach ( DA_Modules::all() as $m ) {
								if ( $m['page'] === $pkey ) {
									$g = $m['group'];
									break;
								}
							}
							$by_group[ $g ][ $pkey ] = $p;
						}
						?>
						<p class="dop-glabel">آمار، گزارش و کنسول</p>
						<?php foreach ( $c_groups as $gkey => $glabel ) :
							if ( empty( $by_group[ $gkey ] ) ) { continue; }
							foreach ( $by_group[ $gkey ] as $pkey => $p ) :
								$on = ( 'console' === $sec && $cpg === $pkey );
								?>
								<a href="<?php echo esc_url( add_query_arg( array( 'osec' => 'console', 'cpg' => $pkey ), self::self_url() ) ); ?>" class="dop-link sub<?php echo $on ? ' on' : ''; ?>"><?php echo esc_html( $p[0] ); ?></a>
							<?php endforeach;
						endforeach; ?>
					<?php endif; ?>

					<?php if ( class_exists( 'DA_Page_Staff' ) && class_exists( 'DA_Staff' ) && DA_Staff::current_user_is_owner() ) : ?>
						<p class="dop-glabel">مدیریت</p>
						<a href="<?php echo esc_url( add_query_arg( 'osec', 'staff', self::self_url() ) ); ?>" class="dop-link<?php echo 'staff' === $sec ? ' on' : ''; ?>"><?php echo self::icon( 'users' ); ?>مدیریت کارمندان</a>
					<?php endif; ?>
				</nav>
			</aside>
			<main class="dop-main">
				<div class="dop-topbar">
					<button type="button" class="dop-darktoggle" onclick="dopToggleDark()" title="حالت تاریک/روشن"><?php echo self::icon( 'moon' ); ?></button>
					<span class="dop-userchip">
						<span class="dop-uav"><?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( wp_get_current_user()->display_name, 0, 1 ) : substr( wp_get_current_user()->display_name, 0, 1 ) ); ?></span>
						<span class="dop-uinfo">
							<b><?php echo esc_html( wp_get_current_user()->display_name ); ?></b>
							<a class="dop-logout-link" href="<?php echo esc_url( wp_logout_url( self::self_url() ) ); ?>">خروج از حساب</a>
						</span>
					</span>
				</div>
				<?php
				switch ( $sec ) {
					case 'orders':
						self::sec_orders();
						break;
					case 'vendors':
						self::sec_vendors();
						break;
					case 'wallets':
						self::sec_wallets();
						break;
					case 'console':
						if ( $has_console ) {
							self::sec_console();
						} else {
							self::sec_dashboard();
						}
						break;
					case 'staff':
						if ( class_exists( 'DA_Page_Staff' ) && class_exists( 'DA_Staff' ) && DA_Staff::current_user_is_owner() ) {
							DA_Page_Staff::render();
						} else {
							echo '<div class="da-card"><p class="da-empty">این بخش در دسترس نیست.</p></div>';
						}
						break;
					default:
						self::sec_dashboard();
				}
				?>
			</main>
		</div>
		<?php
		return ob_get_clean();
	}

	protected static function icon_key( $k ) {
		$map = array( 'dash' => 'zap', 'orders' => 'box', 'vendors' => 'user', 'wallets' => 'wallet' );
		return $map[ $k ] ?? 'zap';
	}

	/** پیام‌های نتیجه اکشن‌ها از پارامتر GET */
	protected static function notices_html() {
		$k = sanitize_key( (string) ( $_GET['omsg'] ?? '' ) );
		$e = sanitize_text_field( (string) ( $_GET['oerr'] ?? '' ) );
		$map = array(
			'saved' => array( 'ok', 'ذخیره شد.' ),
			'done'  => array( 'ok', 'انجام شد.' ),
			'error' => array( 'err', $e ?: 'خطایی رخ داد.' ),
		);
		if ( isset( $map[ $k ] ) ) {
			printf( '<div class="dop-notice %s">%s</div>', esc_attr( $map[ $k ][0] ), esc_html( $map[ $k ][1] ) );
		}
	}

	/* ==================================================================
	 *  داشبورد
	 * ============================================================== */

	protected static function sec_dashboard() {
		self::notices_html();
		$today_from = strtotime( 'today' );
		// بازه دو روزه (امروز+دیروز) با «یک» کوئری → بج‌های روند واقعی امروز نسبت به دیروز
		$orders    = array();
		$y_orders  = array();
		foreach ( (array) wc_get_orders( array( 'limit' => 400, 'date_created' => '>=' . strtotime( '-1 day midnight' ) ) ) as $o ) {
			$c  = $o->get_date_created();
			$ts = $c ? (int) $c->getTimestamp() : 0;
			if ( $ts >= $today_from ) {
				$orders[] = $o;
			} else {
				$y_orders[] = $o;
			}
		}
		$sum   = 0;
		foreach ( $orders as $o ) {
			$sum += (float) $o->get_total();
		}
		$y_sum = 0;
		foreach ( $y_orders as $o ) {
			$y_sum += (float) $o->get_total();
		}
		$pending_vendors = get_users( array( 'role' => 'customer', 'meta_key' => Dastyar_Registration::PENDING, 'meta_value' => 1, 'fields' => 'ID' ) );
		$overdue         = 0;
		foreach ( (array) wc_get_orders( array( 'limit' => 200, 'status' => array( 'wc-processing', 'wc-on-hold' ) ) ) as $o ) {
			$c = $o->get_date_created();
			if ( $c && ( time() - (int) $c->getTimestamp() ) > 48 * HOUR_IN_SECONDS ) {
				$overdue++;
			}
		}

		// سربرگ خوش‌آمدگویی: نام کاربر + تاریخ جلالی کامل + جمع‌بندی امروز
		printf(
			'<div class="dop-hello"><div><h2>سلام، %s 👋</h2><p>%s — %s</p></div></div>',
			esc_html( wp_get_current_user()->display_name ),
			esc_html( class_exists( 'Dastyar_Jalali' ) ? Dastyar_Jalali::format( 'l، j F Y' ) : wp_date( 'Y/m/d' ) ),
			esc_html( sprintf( 'امروز %s سفارش جدید ثبت شده است', Dastyar_Panel_Settings::fa_num( count( $orders ) ) ) )
		);
		echo '<div class="dop-kpis">';
		echo self::kpi( 'box', 'سفارش امروز', Dastyar_Panel_Settings::fa_num( count( $orders ) ), 'blue', self::trend_str( count( $orders ), count( $y_orders ) ) );
		echo self::kpi( 'wallet', 'فروش امروز', wp_kses_post( wc_price( $sum ) ), 'green', self::trend_str( $sum, $y_sum ) );
		echo self::kpi( 'user', 'فروشنده در انتظار تایید', Dastyar_Panel_Settings::fa_num( count( $pending_vendors ) ), 'purple' );
		echo self::kpi( 'zap', 'سفارش دیرکرد (+۴۸ساعت)', Dastyar_Panel_Settings::fa_num( $overdue ), 'amber' );
		echo '</div>';

		self::weekly_growth_card();

		if ( count( $pending_vendors ) > 0 ) {
			printf(
				'<div class="dop-alert"><span>%d فروشنده منتظر تایید هستند.</span><a class="dop-btn sm" href="%s">مشاهده ←</a></div>',
				count( $pending_vendors ),
				esc_url( add_query_arg( 'osec', 'vendors', self::self_url() ) )
			);
		}
		if ( $overdue > 0 ) {
			printf(
				'<div class="dop-alert warn"><span>%d سفارش بیش از ۴۸ ساعت در جریان مانده‌اند.</span><a class="dop-btn sm" href="%s">مشاهده ←</a></div>',
				$overdue,
				esc_url( add_query_arg( 'osec', 'orders', self::self_url() ) )
			);
		}

		// ─── نمودار خطی/منحنی فروش ۱۴ روز اخیر با مبالغ نمایان ───
		echo '<div class="da-card dop-chart-card"><header class="da-card-h"><div><h3>' . self::icon( 'trend' ) . 'فروش ۱۴ روز اخیر</h3><p>برای رقم دقیق هر روز، نشانگر را روی نقطه نگه دارید.</p></div></header><div class="da-card-b">';
		self::sales_chart_14d();
		echo '</div></div>';

		echo '<div class="dop-grid2">';

		// ─── آخرین فروشنده‌های فعال + موجودی کیف پول ───
		echo '<div class="da-card"><header class="da-card-h"><div><h3>' . self::icon( 'users' ) . 'آخرین فروشنده‌های فعال</h3></div></header><div class="da-card-b">';
		self::latest_vendors_list();
		echo '</div></div>';

		// ─── تابلوی زنده سفارش‌ها (کنسول، در صورت فعال بودن) ───
		if ( class_exists( 'DA_Page_Dash' ) ) {
			echo '<div class="da-card"><header class="da-card-h"><div><h3>' . self::icon( 'activity' ) . 'تابلوی زنده سفارش‌ها</h3></div></header><div class="da-card-b">';
			try {
				DA_Page_Dash::render_m29();
			} catch ( \Throwable $e ) {
				echo '<p class="da-empty">در دسترس نیست.</p>';
			}
			echo '</div></div>';
		}

		echo '</div>'; // dop-grid2

		// ─── پرفروش‌ترین محصولات این ماه — طراحی اختصاصی، ردیف‌های جدا، حداکثر ۵ مورد ───
		echo '<div class="da-card"><header class="da-card-h"><div><h3>' . self::icon( 'award' ) . 'پرفروش‌ترین محصولات این ماه</h3></div></header><div class="da-card-b">';
		self::top_products_month( 5 );
		echo '</div></div>';

		if ( class_exists( 'DA_Modules' ) ) {
			printf(
				'<p style="margin-top:4px"><a class="dop-btn sm ghost" href="%s">مشاهده کل کنسول و ۵۰ ماژول آماری ←</a></p>',
				esc_url( add_query_arg( array( 'osec' => 'console', 'cpg' => 'dash' ), self::self_url() ) )
			);
		}
	}

	/** کارت برجسته رشد فروش این هفته نسبت به هفته قبل — نسخه هیرو با مینی‌بارچارت ۷ روزه */
	protected static function weekly_growth_card() {
		$this_from = strtotime( '-6 days midnight' );
		$last_from = strtotime( '-13 days midnight' );
		$last_to   = strtotime( '-7 days midnight' );

		$sold = array( 'wc-processing', 'wc-completed', 'wc-posted', 'wc-on-hold' );
		$sum_this = 0;
		$per_day  = array(); // bucket روزانه برای مینی‌بارچارت هیرو
		foreach ( (array) wc_get_orders( array( 'limit' => 300, 'date_created' => '>=' . $this_from, 'status' => $sold ) ) as $o ) {
			$t = (float) $o->get_total();
			$sum_this += $t;
			$c = $o->get_date_created();
			if ( $c ) {
				$key = gmdate( 'Y-m-d', (int) $c->getTimestamp() );
				$per_day[ $key ] = ( $per_day[ $key ] ?? 0 ) + $t;
			}
		}
		$sum_last = 0;
		$range    = gmdate( 'Y-m-d H:i:s', $last_from ) . '...' . gmdate( 'Y-m-d H:i:s', $last_to );
		foreach ( (array) wc_get_orders( array( 'limit' => 300, 'date_created' => $range, 'status' => $sold ) ) as $o ) {
			$sum_last += (float) $o->get_total();
		}

		if ( $sum_last <= 0 && $sum_this <= 0 ) {
			return; // داده کافی برای مقایسه نیست
		}
		$pct  = $sum_last > 0 ? round( ( ( $sum_this - $sum_last ) / $sum_last ) * 100 ) : 100;
		$down = $pct < 0;

		// مینی‌بارچارت ۷ روز اخیر (از همین کوئری اول — کوئری اضافه نمی‌شود)
		$bars = '';
		$max  = max( 1, $per_day ? max( $per_day ) : 1 );
		for ( $i = 6; $i >= 0; $i-- ) {
			$key = gmdate( 'Y-m-d', strtotime( "-{$i} days midnight" ) );
			$v   = (float) ( $per_day[ $key ] ?? 0 );
			$hp  = $v > 0 ? max( 14, (int) round( $v / $max * 100 ) ) : 8;
			$bars .= sprintf( '<i style="height:%d%%"%s title="%s"></i>', $hp, ( $v >= $max && $v > 0 ) ? ' class="max"' : '', esc_attr( (string) Dastyar_Jalali::format( 'j F', gmdate( 'Y-m-d H:i:s', strtotime( "-{$i} days midnight" ) ) ) ) );
		}

		printf(
			'<div class="dop-growth%s"><div class="dop-growth-info"><div class="dop-growth-ic">%s</div><div><b>%s%s٪</b><p>رشد فروش این هفته نسبت به هفته قبل • %s در برابر %s</p></div></div><div class="dop-growth-bars">%s</div></div>',
			$down ? ' down' : '',
			self::icon( $down ? 'trenddown' : 'trend' ),
			$down ? '' : '+',
			esc_html( Dastyar_Panel_Settings::fa_num( abs( $pct ) ) ),
			wp_kses_post( wc_price( $sum_this ) ),
			wp_kses_post( wc_price( $sum_last ) ),
			$bars // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML داخلی کنترل‌شده
		);
	}

	/** فهرست آخرین فروشنده‌های فعال به‌همراه موجودی کیف پول */
	protected static function latest_vendors_list() {
		$vendors = get_users( array( 'role' => 'dastyar_vendor', 'orderby' => 'registered', 'order' => 'DESC', 'number' => 5 ) );
		if ( ! $vendors ) {
			echo '<p class="da-empty">فروشنده فعالی یافت نشد.</p>';
			return;
		}
		echo '<div class="dop-vlist">';
		foreach ( $vendors as $v ) {
			$balance = class_exists( 'Dastyar' ) ? Dastyar::instance()->wallet->get_balance( $v->ID ) : 0;
			$initial = function_exists( 'mb_substr' ) ? mb_substr( $v->display_name, 0, 1 ) : substr( $v->display_name, 0, 1 );
			printf(
				'<div class="dop-vrow"><div class="dop-vav">%s</div><div class="dop-vinfo"><b>%s</b><span>%s</span></div><div class="dop-vbal">%s</div></div>',
				esc_html( $initial ),
				esc_html( $v->display_name ),
				esc_html( Dastyar_Jalali::dt( $v->user_registered, 'Y/m/d' ) ),
				wp_kses_post( wc_price( (float) $balance ) )
			);
		}
		echo '</div>';
	}

	/** پرفروش‌ترین محصولات این ماه — ردیف‌های جدا با تصویر، حداکثر $limit مورد */
	protected static function top_products_month( $limit = 5 ) {
		$from   = strtotime( 'first day of this month midnight' );
		$orders = wc_get_orders( array(
			'limit'        => 300,
			'status'       => array( 'wc-processing', 'wc-completed', 'wc-posted', 'wc-on-hold' ),
			'date_created' => '>=' . $from,
		) );
		$agg = array();
		foreach ( (array) $orders as $o ) {
			foreach ( (array) $o->get_items() as $it ) {
				$pid = method_exists( $it, 'get_product_id' ) ? (int) $it->get_product_id() : 0;
				if ( ! $pid ) {
					continue;
				}
				if ( ! isset( $agg[ $pid ] ) ) {
					$agg[ $pid ] = array( 'qty' => 0, 'sum' => 0.0 );
				}
				$agg[ $pid ]['qty'] += (int) $it->get_quantity();
				$agg[ $pid ]['sum'] += (float) $it->get_total();
			}
		}
		if ( ! $agg ) {
			echo '<p class="da-empty">هنوز فروشی این ماه ثبت نشده.</p>';
			return;
		}
		uasort( $agg, function ( $a, $b ) {
			return $b['qty'] <=> $a['qty'];
		} );
		$agg = array_slice( $agg, 0, $limit, true );
		$max = max( array_column( $agg, 'qty' ) );

		echo '<div class="dop-plist">';
		foreach ( $agg as $pid => $row ) {
			$p    = wc_get_product( $pid );
			$name = $p ? $p->get_name() : 'محصول حذف‌شده #' . $pid;
			$img  = $p ? $p->get_image( array( 44, 44 ) ) : '';
			$pct  = $max > 0 ? round( ( $row['qty'] / $max ) * 100 ) : 0;
			echo '<div class="dop-prow">';
			echo '<div class="dop-pimg">' . ( $img ?: self::icon( 'box' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن ووکامرس
			echo '<div class="dop-pinfo"><b>' . esc_html( $name ) . '</b>';
			echo '<div class="dop-pbar"><span style="width:' . (int) $pct . '%"></span></div>';
			echo '</div>';
			printf( '<div class="dop-pstat"><b>%s</b><span>%s فروش</span></div>', wp_kses_post( wc_price( $row['sum'] ) ), Dastyar_Panel_Settings::fa_num( $row['qty'] ) );
			echo '</div>';
		}
		echo '</div>';
	}

	/** نمودار خطی/منحنی فروش ۱۴ روز اخیر با مبلغ خوانا روی هر نقطه (SVG مستقل، سازگار با دارک‌مود) */
	protected static function sales_chart_14d() {
		$days = array();
		for ( $i = 13; $i >= 0; $i-- ) {
			$from  = strtotime( "-{$i} days midnight" );
			$to    = strtotime( '+1 day', $from );
			$sum   = 0;
			$range = gmdate( 'Y-m-d H:i:s', $from ) . '...' . gmdate( 'Y-m-d H:i:s', $to );
			foreach ( (array) wc_get_orders( array( 'limit' => 200, 'date_created' => $range, 'status' => array( 'wc-processing', 'wc-completed', 'wc-posted', 'wc-on-hold' ) ) ) as $o ) {
				$sum += (float) $o->get_total();
			}
			$days[] = array( 'label' => Dastyar_Jalali::dt( gmdate( 'Y-m-d H:i:s', $from ), 'm/d' ), 'amount' => $sum );
		}
		$max = max( 1, max( array_column( $days, 'amount' ) ) );
		$n   = count( $days );
		$w   = 700;
		$h   = 200;
		$padx = 24;
		$padt = 34; // فضای بالا برای برچسب مبلغ
		$padb = 26; // فضای پایین برای برچسب تاریخ

		$pts = array();
		foreach ( $days as $i => $d ) {
			$x = $padx + ( $n > 1 ? $i * ( ( $w - $padx * 2 ) / ( $n - 1 ) ) : 0 );
			$y = $padt + ( 1 - ( $d['amount'] / $max ) ) * ( $h - $padt - $padb );
			$pts[] = array( 'x' => $x, 'y' => $y, 'amount' => $d['amount'], 'label' => $d['label'] );
		}

		// مسیر منحنی نرم (میان‌یابی ساده با نقاط میانی — بدون نیاز به کتابخانه جاوااسکریپت)
		$path = sprintf( 'M %.1f,%.1f ', $pts[0]['x'], $pts[0]['y'] );
		for ( $i = 0; $i < $n - 1; $i++ ) {
			$mx = ( $pts[ $i ]['x'] + $pts[ $i + 1 ]['x'] ) / 2;
			$my = ( $pts[ $i ]['y'] + $pts[ $i + 1 ]['y'] ) / 2;
			$path .= sprintf( 'Q %.1f,%.1f %.1f,%.1f ', $pts[ $i ]['x'], $pts[ $i ]['y'], $mx, $my );
		}
		$path .= sprintf( 'L %.1f,%.1f', $pts[ $n - 1 ]['x'], $pts[ $n - 1 ]['y'] );
		$area  = $path . sprintf( ' L %.1f,%.1f L %.1f,%.1f Z', $pts[ $n - 1 ]['x'], $h - $padb, $pts[0]['x'], $h - $padb );

		echo '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="dop-chart" preserveAspectRatio="none" style="width:100%;height:230px">';
		echo '<defs><linearGradient id="dopAreaFill" x1="0" y1="0" x2="0" y2="1">'
			. '<stop offset="0%" stop-color="var(--dop-green)" stop-opacity="0.28"/>'
			. '<stop offset="100%" stop-color="var(--dop-green)" stop-opacity="0"/>'
			. '</linearGradient></defs>';
		echo '<line x1="' . $padx . '" y1="' . ( $h - $padb ) . '" x2="' . ( $w - $padx ) . '" y2="' . ( $h - $padb ) . '" class="dop-chart-axis"></line>';
		echo '<path d="' . esc_attr( $area ) . '" fill="url(#dopAreaFill)" stroke="none"></path>';
		echo '<path d="' . esc_attr( $path ) . '" fill="none" class="dop-chart-line"></path>';
		foreach ( $pts as $i => $p ) {
			$abbr = self::abbr_toman( $p['amount'] );
			printf(
				'<g><title>%s تومان — %s</title><circle cx="%.1f" cy="%.1f" r="3.4" class="dop-chart-dot"></circle><text x="%.1f" y="%.1f" text-anchor="middle" class="dop-chart-val">%s</text><text x="%.1f" y="%d" text-anchor="middle" class="dop-chart-lbl">%s</text></g>',
				number_format( $p['amount'] ),
				esc_html( $p['label'] ),
				$p['x'], $p['y'],
				$p['x'], max( 12, $p['y'] - 10 ), esc_html( $abbr ),
				$p['x'], $h - 6, esc_html( $p['label'] )
			);
		}
		echo '</svg>';
	}

	/** خلاصه‌سازی مبلغ برای نمایش کوتاه بالای هر ستون نمودار — مثلاً ۲.۴M */
	protected static function abbr_toman( $n ) {
		if ( $n >= 1000000 ) {
			return Dastyar_Panel_Settings::fa_num( rtrim( rtrim( number_format( $n / 1000000, 1 ), '0' ), '.' ) ) . 'M';
		}
		if ( $n >= 1000 ) {
			return Dastyar_Panel_Settings::fa_num( round( $n / 1000 ) ) . 'K';
		}
		return Dastyar_Panel_Settings::fa_num( $n );
	}

	/**
	 * کارت KPI — نسخه گلس: آیکون‌کاشی رنگی + بج روند اختیاری (▲/▼)
	 * @param string $trend مثلاً «▲ ۱۲٪» — خالی = بدون بج
	 */
	protected static function kpi( $icon, $label, $value, $kind = 'green', $trend = '' ) {
		$tr = '';
		if ( '' !== $trend ) {
			$down = false !== strpos( $trend, '▼' );
			$tr   = '<span class="dop-trd ' . ( $down ? 'down' : 'up' ) . '">' . esc_html( $trend ) . '</span>';
		}
		return '<div class="dop-kpi kpi-' . esc_attr( $kind ) . '"><div class="dop-kpi-top"><div class="dop-kpi-ic">' . self::icon( $icon ) . '</div>' . $tr . '</div><b>' . $value . '</b><span>' . esc_html( $label ) . '</span></div>';
	}

	/** برچسب روند درصدی بین دو عدد (▲/▼ + ارقام فارسی) — مبنای صفر = بدون برچسب */
	protected static function trend_str( $current, $previous ) {
		$previous = (float) $previous;
		if ( $previous <= 0 ) {
			return '';
		}
		$pct = (int) round( ( ( (float) $current - $previous ) / $previous ) * 100 );
		if ( 0 === $pct ) {
			return '';
		}
		return ( $pct > 0 ? '▲ ' : '▼ ' ) . Dastyar_Panel_Settings::fa_num( abs( $pct ) ) . '٪';
	}

	/* ==================================================================
	 *  کنسول — آمار، گزارش‌ها و ۵۰ ماژول افزونه «کنسول مدیریت مرکز» (در صورت نصب)
	 *  دقیقاً همان منطق DA_Admin::render_page()، بدون کوچک‌ترین تغییر در ماژول‌ها —
	 *  فقط داخل همین پوسته‌ی تمام‌صفحه نمایش داده می‌شود.
	 * ============================================================== */

	protected static function sec_console() {
		if ( ! class_exists( 'DA_Modules' ) ) {
			echo '<div class="da-card"><p class="da-empty">افزونه «کنسول مدیریت مرکز» نصب/فعال نیست.</p></div>';
			return;
		}
		self::notices_html_console();

		$cpg   = sanitize_key( (string) ( $_GET['cpg'] ?? '' ) );
		$pages = DA_Modules::pages();
		$def   = null;
		foreach ( $pages as $key => $p ) {
			if ( $key === $cpg ) {
				$def = array( 'key' => $key, 'title' => $p[0], 'class' => $p[2] );
			}
		}
		if ( ! $def ) {
			$def = array( 'key' => 'dash', 'title' => $pages['dash'][0], 'class' => $pages['dash'][2] );
		}

		echo '<h1 class="dop-title">' . esc_html( $def['title'] ) . '</h1>';

		$enabled = DA_Modules::enabled_on_page( $def['key'] );
		if ( ! $enabled ) {
			echo '<div class="da-card"><p class="da-empty">همه ماژول‌های این صفحه غیرفعال‌اند.</p></div>';
			return;
		}
		foreach ( $enabled as $id => $mod ) {
			$cls = $def['class'];
			if ( ! class_exists( $cls ) || ! method_exists( $cls, $mod['cb'] ) ) {
				continue;
			}
			ob_start();
			try {
				call_user_func( array( $cls, $mod['cb'] ) );
			} catch ( \Throwable $e ) {
				echo '<p class="da-empty">این کارت موقتاً در دسترس نیست.</p>';
			}
			$body = ob_get_clean();
			DA_Render::card( $mod, $body );
		}
	}

	/** پیام‌های نتیجه اکشن‌های ماژول‌های کنسول (کلیدهای da_msg/da_err — همان‌طور که DA_Render تولید می‌کند) */
	protected static function notices_html_console() {
		DA_Render::notices();
	}

	/* ==================================================================
	 *  سفارش‌ها — فهرست، تغییر وضعیت، کد رهگیری (تکی و گروهی)
	 * ============================================================== */

	protected static function sec_orders() {
		self::notices_html();
		echo '<h1 class="dop-title">مدیریت سفارش‌ها</h1>';

		$status_filter = sanitize_key( (string) ( $_GET['ost'] ?? '' ) );
		$q             = sanitize_text_field( (string) ( $_GET['oq'] ?? '' ) );

		echo '<form method="get" class="dop-filterbar">';
		printf( '<input type="hidden" name="osec" value="orders">' );
		printf( '<input type="text" name="oq" value="%s" placeholder="جستجو: شماره سفارش، موبایل، کد رهگیری">', esc_attr( $q ) );
		echo '<select name="ost">';
		echo '<option value="">همه وضعیت‌ها</option>';
		foreach ( wc_get_order_statuses() as $slug => $label ) {
			$val = str_replace( 'wc-', '', $slug );
			printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $status_filter, $val, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '<button type="submit" class="dop-btn sm">فیلتر</button>';
		echo '</form>';

		$args = array( 'limit' => 40, 'orderby' => 'date', 'order' => 'DESC' );
		if ( $status_filter ) {
			$args['status'] = $status_filter;
		}
		if ( $q ) {
			$args['s'] = $q;
		}
		$orders = wc_get_orders( $args );

		// ─── فرم چاپ لیبل آدرس (چک‌باکس هر ردیف) — دکمه چاپ همین بالای جدول ───
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" target="_blank" id="dop-label-form">';
		wp_nonce_field( self::ACTION_NONCE );
		echo '<input type="hidden" name="action" value="dastyar_ops_label">';
		echo '<p class="dop-toolbar"><button type="submit" class="dop-btn sm">' . self::icon( 'printer' ) . 'چاپ لیبل آدرس موارد انتخابی</button> <span class="dop-muted" style="font-size:11.5px">در زبانه جدید باز می‌شود.</span></p>';

		echo '<div class="dop-table-wrap"><table class="dop-table"><thead><tr>';
		echo '<th><input type="checkbox" onclick="this.closest(\'table\').querySelectorAll(\'.dop-rowchk\').forEach(function(c){c.checked=this.checked}.bind(this))"></th>';
		echo '<th>سفارش</th><th>محصولات</th><th>فروشنده</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th>کد رهگیری</th><th>تاریخ</th>';
		echo '</tr></thead><tbody>';
		if ( ! $orders ) {
			echo '<tr><td colspan="9" class="dop-empty">سفارشی یافت نشد.</td></tr>';
		}
		$nonce = wp_create_nonce( self::ACTION_NONCE );
		foreach ( (array) $orders as $o ) {
			$vid      = (int) $o->get_meta( '_dastyar_vendor_id' );
			$vname    = $vid ? get_the_author_meta( 'display_name', $vid ) : '—';
			$track    = (string) $o->get_meta( '_dastyar_tracking_code' );
			$oid      = (int) $o->get_id();
			$cancelled = in_array( $o->get_status(), array( 'cancelled', 'trash' ), true );

			echo '<tr>';
			printf( '<td><input type="checkbox" class="dop-rowchk" name="ids[]" value="%d"></td>', $oid );
			printf( '<td>#%s</td>', esc_html( $o->get_order_number() ) );

			// ─── تصاویر کوچک محصولات این سفارش ───
			echo '<td><div class="dop-thumbs">';
			$shown = 0;
			foreach ( (array) $o->get_items() as $it ) {
				if ( $shown >= 4 ) {
					break;
				}
				$p   = method_exists( $it, 'get_product' ) ? $it->get_product() : null;
				$img = $p ? $p->get_image( array( 30, 30 ) ) : '';
				if ( $img ) {
					echo '<span class="dop-thumb">' . $img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن ووکامرس
					$shown++;
				}
			}
			$extra = count( (array) $o->get_items() ) - $shown;
			if ( $extra > 0 ) {
				printf( '<span class="dop-thumb-more">+%d</span>', $extra );
			}
			if ( 0 === $shown && $extra <= 0 ) {
				echo '<span class="dop-muted">—</span>';
			}
			echo '</div></td>';

			printf( '<td>%s</td>', esc_html( $vname ) );
			printf( '<td>%s</td>', esc_html( trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) ?: '—' ) );
			printf( '<td>%s</td>', wp_kses_post( wc_price( (float) $o->get_total() ) ) );

			// ─── وضعیت: پیل رنگی (بسته) + آکاردئون تغییر وضعیت ───
			echo '<td><details class="dop-stacc"><summary><span class="dop-status-pill ' . esc_attr( self::status_color_key( $o->get_status() ) ) . '">' . esc_html( wc_get_order_status_name( $o->get_status() ) ) . '</span></summary>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="dop-inline-f">'
				. '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">'
				. '<input type="hidden" name="action" value="dastyar_ops_status">'
				. '<input type="hidden" name="order_id" value="' . $oid . '">'
				. '<select name="new_status">';
			foreach ( wc_get_order_statuses() as $slug => $label ) {
				$val = str_replace( 'wc-', '', $slug );
				printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $o->get_status(), $val, false ), esc_html( $label ) );
			}
			echo '</select><button type="submit" class="dop-mini">ثبت</button></form></details></td>';

			// ─── کد رهگیری: پاپ‌آپ به‌جای فیلد باز؛ سبز = ثبت‌شده، زرد = خالی؛ لغوشده‌ها دکمه ندارند ───
			echo '<td>';
			if ( $cancelled ) {
				echo '<span class="dop-muted">—</span>';
			} elseif ( $track ) {
				printf(
					'<button type="button" class="dop-track-badge set" data-order="%d" data-track="%s" onclick="dopOpenTrack(this)">%s</button>',
					$oid, esc_attr( $track ), esc_html( $track )
				);
			} else {
				printf(
					'<button type="button" class="dop-track-badge unset" data-order="%d" data-track="" onclick="dopOpenTrack(this)">ثبت کد رهگیری</button>',
					$oid
				);
			}
			echo '</td>';

			printf( '<td class="dop-muted">%s</td>', esc_html( $o->get_date_created() ? Dastyar_Jalali::dt( $o->get_date_created()->date( 'Y-m-d H:i:s' ) ) : '—' ) );
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '</form>';

		// ─── پاپ‌آپ مشترک ثبت/ویرایش کد رهگیری (یک نمونه برای کل صفحه) ───
		echo '<div class="dop-modal" id="dop-track-modal">
			<div class="dop-modal-bg" onclick="dopCloseTrack()"></div>
			<div class="dop-modal-box">
				<button type="button" class="dop-modal-x" onclick="dopCloseTrack()">' . self::icon( 'close' ) . '</button>
				<h3>ثبت کد رهگیری</h3>
				<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">
					<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">
					<input type="hidden" name="action" value="dastyar_ops_track">
					<input type="hidden" name="order_id" id="dop-track-oid" value="">
					<input type="text" name="tracking_code" id="dop-track-val" dir="ltr" placeholder="کد رهگیری را وارد کنید">
					<button type="submit" class="dop-btn">ثبت</button>
				</form>
			</div>
		</div>
		<script>
			function dopOpenTrack(btn){
				document.getElementById("dop-track-oid").value = btn.getAttribute("data-order");
				document.getElementById("dop-track-val").value = btn.getAttribute("data-track");
				document.getElementById("dop-track-modal").classList.add("open");
			}
			function dopCloseTrack(){ document.getElementById("dop-track-modal").classList.remove("open"); }
		</script>';
	}

	/** رنگ پیل هر وضعیت سفارش — برای تشخیص سریع چشمی در جدول */
	protected static function status_color_key( $status ) {
		$map = array(
			'pending'    => 'st-gray',
			'on-hold'    => 'st-amber',
			'processing' => 'st-blue',
			'posted'     => 'st-purple',
			'completed'  => 'st-green',
			'cancelled'  => 'st-red',
			'refunded'   => 'st-red',
			'failed'     => 'st-red',
		);
		return $map[ $status ] ?? 'st-gray';
	}

	public static function save_status() {
		self::guard_action();
		$order = wc_get_order( (int) ( $_POST['order_id'] ?? 0 ) );
		$new   = sanitize_key( $_POST['new_status'] ?? '' );
		if ( $order && $new ) {
			$order->update_status( $new, 'وضعیت از پنل عملیات دستیار تغییر کرد.' );
		}
		self::back( 'orders', 'done' );
	}

	public static function save_track() {
		self::guard_action();
		$order = wc_get_order( (int) ( $_POST['order_id'] ?? 0 ) );
		$code  = sanitize_text_field( wp_unslash( $_POST['tracking_code'] ?? '' ) );
		if ( $order ) {
			$order->update_meta_data( '_dastyar_tracking_code', $code );
			$order->add_order_note( 'کد رهگیری از پنل عملیات ثبت شد: ' . $code );
			$order->save();
		}
		self::back( 'orders', 'done' );
	}

	/** چاپ لیبل آدرس برای سفارش‌های انتخاب‌شده — در تب جدید باز می‌شود */
	public static function print_labels() {
		self::guard_action();
		$ids = array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) );
		if ( ! $ids ) {
			self::back( 'orders', 'error', array( 'oerr' => 'هیچ سفارشی انتخاب نشده بود.' ) );
		}
		echo '<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8"><title>لیبل آدرس — دستیار شاپ</title><style>'
			. 'body{font-family:Tahoma,sans-serif;color:#242536;margin:16px;background:#eee}'
			. '.lbl{background:#fff;border:2px dashed #242536;border-radius:10px;padding:16px 18px;margin:0 0 14px;page-break-inside:avoid;max-width:400px}'
			. '.lbl h3{margin:0 0 6px;border-bottom:2px solid #17a16d;padding-bottom:6px;font-size:15px}'
			. '.lbl p{margin:4px 0;font-size:13.5px;line-height:1.9}'
			. '.lbl .trk{font-weight:900;color:#12875c;direction:ltr;display:inline-block;font-size:14.5px}'
			. '.nop{display:inline-flex;align-items:center;gap:8px;background:#17a16d;color:#fff;border:0;border-radius:10px;padding:10px 22px;font-weight:800;cursor:pointer;margin-bottom:16px;font-family:inherit;font-size:13px}'
			. '.nop svg{width:16px;height:16px}'
			. '@media print{body{background:#fff}.nop{display:none}.lbl{border-style:solid}}'
			. '</style></head><body>'
			. '<button class="nop" onclick="window.print()">' . self::icon( 'printer' ) . 'چاپ همه لیبل‌ها</button>';
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( ! $o ) {
				continue;
			}
			$addr  = method_exists( $o, 'get_address' ) ? (array) $o->get_address( 'billing' ) : array();
			$name  = trim( (string) ( ( $addr['first_name'] ?? '' ) . ' ' . ( $addr['last_name'] ?? '' ) ) );
			$track = (string) $o->get_meta( '_dastyar_tracking_code' );
			echo '<div class="lbl">'
				. '<h3>دستیار شاپ — سفارش #' . esc_html( $o->get_order_number() ) . '</h3>'
				. '<p><strong>گیرنده:</strong> ' . esc_html( $name ?: '—' ) . '</p>'
				. '<p><strong>موبایل:</strong> <span dir="ltr">' . esc_html( (string) ( $addr['phone'] ?? '' ) ) . '</span></p>'
				. '<p><strong>آدرس:</strong> ' . esc_html( trim( (string) ( ( $addr['state'] ?? '' ) . '، ' . ( $addr['city'] ?? '' ) . '، ' . ( $addr['address_1'] ?? '' ) ) ) )
				. ( ! empty( $addr['postcode'] ) ? ' — کدپستی: ' . esc_html( (string) $addr['postcode'] ) : '' ) . '</p>'
				. ( $track ? '<p><strong>کد رهگیری:</strong> <span class="trk">' . esc_html( $track ) . '</span></p>' : '' )
				. '</div>';
		}
		echo '</body></html>';
		exit;
	}

	/* ==================================================================
	 *  فروشنده‌ها — فهرست + تایید/رد/تعلیق
	 * ============================================================== */

	protected static function sec_vendors() {
		self::notices_html();
		echo '<h1 class="dop-title">فروشنده‌ها</h1>';

		$filter  = sanitize_key( (string) ( $_GET['ovf'] ?? 'all' ) );
		$vendors = get_users( array( 'role__in' => array( 'customer', 'dastyar_vendor' ), 'number' => 200, 'orderby' => 'registered', 'order' => 'DESC' ) );

		echo '<div class="dop-tabs">';
		foreach ( array( 'all' => 'همه', 'pending' => 'در انتظار', 'approved' => 'تایید‌شده', 'rejected' => 'رد‌شده' ) as $k => $l ) {
			printf( '<a class="dop-tab%s" href="%s">%s</a>', $filter === $k ? ' on' : '', esc_url( add_query_arg( array( 'osec' => 'vendors', 'ovf' => $k ), self::self_url() ) ), esc_html( $l ) );
		}
		echo '</div>';

		echo '<div class="dop-table-wrap"><table class="dop-table"><thead><tr><th>نام</th><th>ایمیل</th><th>وضعیت</th><th>موجودی کیف پول</th><th>عملیات</th></tr></thead><tbody>';
		$shown = 0;
		foreach ( $vendors as $v ) {
			$pending  = (bool) get_user_meta( $v->ID, Dastyar_Registration::PENDING, true );
			$rejected = (bool) get_user_meta( $v->ID, '_dastyar_vendor_rejected', true );
			$status   = $rejected ? 'rejected' : ( $pending ? 'pending' : 'approved' );
			if ( 'all' !== $filter && $filter !== $status ) {
				continue;
			}
			$shown++;
			$balance = class_exists( 'Dastyar' ) ? Dastyar::instance()->wallet->get_balance( $v->ID ) : 0;
			$nonce   = wp_create_nonce( self::ACTION_NONCE );
			$labels  = array( 'pending' => 'در انتظار', 'approved' => 'تایید‌شده', 'rejected' => 'رد‌شده' );
			$pill    = $labels[ $status ];

			echo '<tr>';
			printf( '<td>%s</td>', esc_html( $v->display_name ) );
			printf( '<td dir="ltr">%s</td>', esc_html( $v->user_email ) );
			printf( '<td><span class="dop-pill %s">%s</span></td>', esc_attr( $status ), esc_html( $pill ) );
			printf( '<td>%s</td>', wp_kses_post( wc_price( (float) $balance ) ) );

			echo '<td class="dop-actions">';
			$actions = array(
				'approve' => array( 'تایید', 'pending' !== $status ? 'off' : '' ),
				'reject'  => array( 'رد', 'rejected' === $status ? 'off' : '' ),
				'suspend' => array( 'تعلیق', '' ),
			);
			foreach ( $actions as $act => $info ) {
				if ( 'off' === $info[1] ) {
					continue;
				}
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="dop-inline-f">'
					. '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">'
					. '<input type="hidden" name="action" value="dastyar_ops_vendor">'
					. '<input type="hidden" name="uid" value="' . (int) $v->ID . '">'
					. '<input type="hidden" name="vendor_action" value="' . esc_attr( $act ) . '">'
					. '<button type="submit" class="dop-mini">' . esc_html( $info[0] ) . '</button></form>';
			}
			echo '</td></tr>';
		}
		if ( 0 === $shown ) {
			echo '<tr><td colspan="5" class="dop-empty">موردی یافت نشد.</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function save_vendor_status() {
		self::guard_action();
		$uid = (int) ( $_POST['uid'] ?? 0 );
		$act = sanitize_key( $_POST['vendor_action'] ?? '' );
		if ( $uid ) {
			if ( 'approve' === $act ) {
				delete_user_meta( $uid, Dastyar_Registration::PENDING );
				delete_user_meta( $uid, '_dastyar_vendor_rejected' );
				$u = get_userdata( $uid );
				if ( $u && ! in_array( 'dastyar_vendor', (array) $u->roles, true ) ) {
					$u->set_role( 'dastyar_vendor' );
				}
			} elseif ( 'reject' === $act ) {
				update_user_meta( $uid, '_dastyar_vendor_rejected', 1 );
				delete_user_meta( $uid, Dastyar_Registration::PENDING );
			} elseif ( 'suspend' === $act ) {
				update_user_meta( $uid, Dastyar_Registration::PENDING, 1 );
			}
		}
		self::back( 'vendors', 'done' );
	}

	/* ==================================================================
	 *  کیف‌پول‌ها — نگاه کلی + شارژ/کسر دستی
	 * ============================================================== */

	protected static function sec_wallets() {
		self::notices_html();
		echo '<h1 class="dop-title">کیف‌پول فروشنده‌ها</h1>';

		$vendors = get_users( array( 'role' => 'dastyar_vendor', 'number' => 300 ) );
		echo '<div class="dop-table-wrap"><table class="dop-table"><thead><tr><th>فروشنده</th><th>موجودی</th><th>شارژ/کسر دستی</th></tr></thead><tbody>';
		if ( ! $vendors ) {
			echo '<tr><td colspan="3" class="dop-empty">فروشنده‌ای یافت نشد.</td></tr>';
		}
		$nonce = wp_create_nonce( self::ACTION_NONCE );
		foreach ( $vendors as $v ) {
			$balance = class_exists( 'Dastyar' ) ? Dastyar::instance()->wallet->get_balance( $v->ID ) : 0;
			echo '<tr>';
			printf( '<td>%s</td>', esc_html( $v->display_name ) );
			printf( '<td>%s</td>', wp_kses_post( wc_price( (float) $balance ) ) );
			echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="dop-inline-f">'
				. '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">'
				. '<input type="hidden" name="action" value="dastyar_ops_wallet">'
				. '<input type="hidden" name="uid" value="' . (int) $v->ID . '">'
				. '<select name="dir"><option value="credit">افزایش</option><option value="debit">کاهش</option></select>'
				. '<input type="number" name="amount" min="0" step="any" placeholder="مبلغ" style="width:110px">'
				. '<input type="text" name="note" placeholder="یادداشت" style="width:140px">'
				. '<button type="submit" class="dop-mini">ثبت</button></form></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function save_wallet_adjust() {
		self::guard_action();
		$uid    = (int) ( $_POST['uid'] ?? 0 );
		$amount = (float) ( $_POST['amount'] ?? 0 );
		$dir    = sanitize_key( $_POST['dir'] ?? 'credit' );
		$note   = sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) ) ?: 'ثبت دستی از پنل عملیات';
		if ( $uid && $amount > 0 && class_exists( 'Dastyar' ) ) {
			if ( 'debit' === $dir ) {
				Dastyar::instance()->wallet->debit( $uid, $amount, 0, $note );
			} else {
				Dastyar::instance()->wallet->credit( $uid, $amount, 0, $note );
			}
		}
		self::back( 'wallets', 'done' );
	}

	/* ==================================================================
	 *  ابزارهای مشترک
	 * ============================================================== */

	protected static function guard_action() {
		if ( ! self::has_access() || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), self::ACTION_NONCE ) ) {
			wp_die( 'دسترسی غیرمجاز یا نشست نامعتبر.' );
		}
	}

	protected static function back( $sec, $msg, $extra = array() ) {
		$ref  = wp_get_referer();
		$url  = $ref ?: home_url( '/' );
		$args = array_merge( array( 'osec' => $sec, 'omsg' => $msg ), $extra );
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	protected static function icon( $name ) {
		$paths = array(
			'zap'      => '<path d="M13 2L4 14h6l-1 8 9-12h-6z"/>',
			'box'      => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
			'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
			'wallet'   => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><circle cx="16" cy="14" r="1.4"/>',
			'moon'     => '<path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/>',
			'menu'     => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
			'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5"/><circle cx="17" cy="8.5" r="2.6"/><path d="M15.5 14.3c2.6.4 4.5 2.2 5 5.7"/>',
			'trend'    => '<path d="M3 17l5-5 4 4 8-9"/><path d="M14 7h6v6"/>',
			'trenddown' => '<path d="M3 7l5 5 4-4 8 9"/><path d="M14 17h6v-6"/>',
			'activity' => '<path d="M3 12h4l2-7 4 14 2-7h6"/>',
			'award'    => '<circle cx="12" cy="8" r="5.2"/><path d="M9 12.8L7 21l5-3 5 3-2-8.2"/>',
			'printer'  => '<path d="M6 9V3h12v6"/><rect x="4" y="9" width="16" height="8" rx="1.5"/><path d="M6 15h12v6H6z"/>',
			'chevron'  => '<path d="M9 6l6 6-6 6"/>',
			'close'    => '<path d="M6 6l12 12"/><path d="M18 6L6 18"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths[ $name ] . '</svg>';
	}
}
