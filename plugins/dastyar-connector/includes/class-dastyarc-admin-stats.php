<?php
/**
 * DastyarC_Admin_Stats — trait بخش «داشبورد» پنل کانکتور (v1.12.0 — شکافتن کلاس ادمین).
 * فقط توسط DastyarC_Admin استفاده می‌شود؛ منطق نسبت به قبل هیچ تغییری نکرده است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait DastyarC_Admin_Stats {

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
			<div class="dcx2-notice">سینک موجودی انجام شد.</div>
		<?php endif; ?>

		<?php $onb = $this->onboarding_state(); ?>
		<?php if ( ! $onb['done'] ) : ?>
		<!-- چک‌لیست شروع کار (v1.12.0) — فقط تا وقتی همه تیک نخورده‌اند -->
		<section class="dcx2-card">
			<header>شروع کار با دستیار شاپ</header>
			<ul style="list-style:none;margin:0;padding:0">
			<?php foreach ( $onb['items'] as $it ) : ?>
				<li style="display:flex;align-items:center;gap:10px;padding:7px 0;border-top:1px solid var(--line)">
					<span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:<?php echo $it[0] ? '#17a16d' : '#eef2f0'; ?>;color:#fff;font-size:13px;font-weight:800"><?php echo $it[0] ? '✔' : ''; ?></span>
					<?php if ( $it[0] || '' === $it[2] ) : ?>
						<span style="<?php echo $it[0] ? 'color:var(--mut)' : ''; ?>"><?php echo esc_html( $it[1] ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( $it[2] ); ?>"><b><?php echo esc_html( $it[1] ); ?> ←</b></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
			</ul>
		</section>
		<?php endif; ?>

		<?php $acts = $this->action_items(); ?>
		<?php if ( $acts ) : ?>
		<!-- اقدام لازم (v1.12.0) -->
		<section class="dcx2-card">
			<header>⚠ اقدام لازم</header>
			<ul style="list-style:none;margin:0;padding:0">
			<?php foreach ( $acts as $a ) : ?>
				<li style="padding:7px 0;border-top:1px solid var(--line)"><a href="<?php echo esc_url( $a[1] ); ?>"><b><?php echo esc_html( $a[0] ); ?> ←</b></a></li>
			<?php endforeach; ?>
			</ul>
		</section>
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
				'',
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
				<div class="tile"><span>موجودی کیف پول</span><b><?php echo null === $bal ? '—' : esc_html( self::wallet_toman( $bal ) ); ?></b></div>
			</div>
			<div class="acts">
				<?php DastyarC_Ui::btn( DastyarC_Ui::icon( 'wallet', 13 ) . ' شارژ کیف پول', self::hub_url( 'wallet' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php DastyarC_Ui::btn( DastyarC_Ui::icon( 'coin', 13 ) . ' گردش حساب', self::hub_url( 'wallet' ) . '#dtx' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</section>

		<div class="dcx2-grid2">
			<!-- آخرین سفارش‌های متصل -->
			<section class="dcx2-card">
				<header><?php echo DastyarC_Ui::icon( 'chart', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> آخرین سفارش‌های شما<a class="more" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>">همهٔ سفارش‌ها ←</a></header>
				<?php if ( empty( $orders['recent'] ) ) : ?>
					<?php DastyarC_Ui::empty_state( 'سفارش متصلی ثبت نشده است.' ); ?>
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
					<?php DastyarC_Ui::empty_state( 'هشدار فعالی وجود ندارد.' ); ?>
				<?php else : ?>
					<ul class="dcx2-alerts">
					<?php foreach ( array_slice( $alerts, 0, 5 ) as $it ) : ?>
						<li><span style="display:inline-flex;color:#b45309"><?php echo DastyarC_Ui::icon( 'coin', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><span>
							<b><?php echo esc_html( (string) ( $it['title'] ?? '' ) ); ?></b>
							<small><?php echo esc_html( self::wallet_toman( (float) ( $it['prev_supply_price'] ?? 0 ) ) ); ?> ← <b style="color:#c2410c"><?php echo esc_html( self::wallet_toman( (float) ( $it['supply_price'] ?? 0 ) ) ); ?></b></small>
						</span></li>
					<?php endforeach; ?>
					<?php if ( $n_alert > 5 ) : ?><li style="justify-content:center"><small>و <?php echo esc_html( (string) DastyarC_Jalali::num( (string) ( $n_alert - 5 ) ) ); ?> مورد دیگر.</small></li><?php endif; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>

		<!-- کارت رویدادها + سلامت -->
		<section class="dcx2-card">
			<header><?php echo DastyarC_Ui::icon( 'sync', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> رویدادهای اخیر<button type="button" class="dcx2-btn sm" id="dcx2-copyrep" style="margin-right:auto">کپی گزارش</button></header>
			<div id="dcx2-repwrap" style="display:none;margin:10px 0">
				<textarea id="dcx2-reptext" rows="8" readonly style="width:100%;direction:ltr;text-align:left;font-family:monospace;font-size:11px;line-height:1.8"><?php echo esc_textarea( self::support_report() ); ?></textarea>
			</div>
			<script>
			(function(){
				var b = document.getElementById('dcx2-copyrep');
				if (!b) return;
				b.addEventListener('click', function(){
					var w = document.getElementById('dcx2-repwrap'), t = document.getElementById('dcx2-reptext');
					if (!w || !t) return;
					var show = (w.style.display === 'none');
					w.style.display = show ? 'block' : 'none';
					if (!show) return;
					t.focus(); t.select();
					if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(t.value); }
					else { try { document.execCommand('copy'); } catch (e) {} }
					b.textContent = 'کپی شد ✔';
				});
			})();
			</script>
			<?php
			if ( empty( $events ) ) {
				DastyarC_Ui::empty_state( 'رویدادی ثبت نشده است.' );
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

	/**
	 * وضعیت چک‌لیست شروع کار (v1.12.0) — کش ۱۰ دقیقه‌ای
	 * @return array [done, items: [ticked, label, url]]
	 */
	protected function onboarding_state() {
		$cached = get_transient( 'dastyarc_onboarding' );
		if ( is_array( $cached ) && isset( $cached['items'] ) ) {
			return $cached;
		}
		$has_product = (bool) get_posts( array(
			'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1,
			'meta_key' => '_dastyar_remote_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		) );
		$sent_orders  = function_exists( 'wc_get_orders' ) ? wc_get_orders( array( 'limit' => 1, 'meta_key' => '_dastyarc_sent', 'meta_value' => 1 ) ) : array();
		$has_sent     = ! empty( $sent_orders );
		$healthy      = 'ok' === self::health_state( false );
		$items        = array(
			array( true, 'اتصال به مرکز برقرار است', '' ),
			array( $has_product, 'اولین محصول افزوده شد', self::hub_url( 'products' ) ),
			array( $has_sent, 'اولین سفارش به مرکز ارسال شد', admin_url( 'edit.php?post_type=shop_order' ) ),
			array( $healthy, 'سلامت فروشگاه سبز است', self::hub_url( 'health' ) ),
		);
		$out = array( 'done' => ( $has_product && $has_sent && $healthy ), 'items' => $items );
		set_transient( 'dastyarc_onboarding', $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * موارد نیازمند اقدام فروشنده (v1.12.0) — کش ۵ دقیقه‌ای
	 * @return array [ [label, url], ... ]
	 */
	protected function action_items() {
		$cached = get_transient( 'dastyarc_action_items' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$items = array();
		// خطاهای ۷۲ ساعت اخیر
		$n_err = 0;
		foreach ( DastyarC::events( 150 ) as $ev ) {
			if ( 'error' === (string) ( $ev['l'] ?? '' ) && (int) ( $ev['t'] ?? 0 ) > time() - 72 * HOUR_IN_SECONDS ) {
				$n_err++;
			}
		}
		if ( $n_err ) {
			$items[] = array( $n_err . ' خطا در ۷۲ ساعت اخیر', self::hub_url( 'ticket' ) );
		}
		// سینک عقب‌افتاده
		$stale = false;
		foreach ( array( 'dastyarc_sync_tick', 'dastyarc_reconcile_tick', 'dastyarc_stock_tick' ) as $hook ) {
			$next = wp_next_scheduled( $hook );
			if ( ! $next || $next < time() - 1800 ) {
				$stale = true;
				break;
			}
		}
		$last = (int) get_option( 'dastyarc_last_sync', 0 );
		if ( ! $last || $last < time() - 26 * HOUR_IN_SECONDS ) {
			$stale = true;
		}
		if ( $stale ) {
			$items[] = array( 'سینک عقب‌افتاده است', self::hub_url( 'health' ) );
		}
		// سفارش‌های در انتظار ارسال به مرکز
		$statuses = array_values( array_filter( array_map( 'sanitize_key', (array) get_option( 'dastyarc_send_statuses', array( 'processing' ) ) ) ) );
		if ( $statuses && function_exists( 'wc_get_orders' ) ) {
			$n_ord = count( wc_get_orders( array(
				'limit'      => 100,
				'status'     => $statuses,
				'meta_query' => array(
					array( 'key' => '_dastyarc_has_items', 'value' => 1 ),
					array( 'key' => '_dastyarc_sent', 'compare' => 'NOT EXISTS' ),
				),
			) ) );
			if ( $n_ord ) {
				$items[] = array( $n_ord . ' سفارش در انتظار ارسال به مرکز', admin_url( 'edit.php?post_type=shop_order' ) );
			}
		}
		// مرجوعی‌های ارسال‌نشده به مرکز
		if ( class_exists( 'DastyarC_Rma' ) ) {
			$n_rma = count( get_posts( array(
				'post_type' => DastyarC_Rma::POST_TYPE, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 100,
				'meta_query' => array( array( 'key' => '_dastyarc_rma_remote_id', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			) ) );
			if ( $n_rma ) {
				$items[] = array( $n_rma . ' مرجوعی در انتظار ارسال', self::hub_url( 'rma' ) );
			}
		}
		set_transient( 'dastyarc_action_items', $items, 5 * MINUTE_IN_SECONDS );
		return $items;
	}

	/** گزارش متنی پشتیبانی: نسخه‌ها + خلاصه سلامت + ۳۰ رویداد اخیر (v1.12.0) */
	public static function support_report() {
		global $wp_version;
		$lines = array(
			'=== Dastyar Connector report ===',
			'connector: ' . ( defined( 'DASTYARC_VERSION' ) ? DASTYARC_VERSION : '?' ),
			'wp: ' . (string) ( $wp_version ?? '' ) . ' / wc: ' . ( defined( 'WC_VERSION' ) ? WC_VERSION : '?' ) . ' / php: ' . PHP_VERSION . ' / mem: ' . (string) ini_get( 'memory_limit' ),
			'health: ' . self::health_state( false ),
		);
		foreach ( self::health_checks( false ) as $c ) {
			if ( 'ok' !== $c[0] ) {
				$lines[] = '[' . $c[0] . '] ' . $c[1] . ': ' . $c[2];
			}
		}
		$lines[] = '--- events ---';
		foreach ( DastyarC::events( 30 ) as $ev ) {
			$lines[] = gmdate( 'Y-m-d H:i', (int) ( $ev['t'] ?? 0 ) ) . ' [' . (string) ( $ev['l'] ?? '' ) . '] ' . (string) ( $ev['m'] ?? '' );
		}
		$txt = implode( "\n", $lines );
		$txt = (string) preg_replace( '/(api[_-]?key\\s*[=:]\\s*)(\\S+)/i', '$1[redacted]', $txt );
		return $txt;
	}

	/** حالت خوش‌آمد/راه‌اندازی داشبورد وقتی اتصال هنوز پیکربندی نشده است (v1.10.0) */
	protected function dashboard_onboarding() {
		?>
		<section class="dcx2-card" style="overflow:hidden">
			<div style="background:var(--grad);color:#fff;padding:30px 22px;text-align:center">
				<div style="width:52px;height:52px;margin:0 auto 12px;border-radius:14px;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center"><?php echo DastyarC_Ui::icon( 'mark', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div style="font-size:17px;font-weight:800">به کانکتور دستیار شاپ خوش آمدید</div>
				<div style="margin-top:16px"><a class="dcx2-btn prime" style="background:#fff;border-color:#fff;color:var(--g1);box-shadow:none" href="<?php echo esc_url( self::hub_url( 'settings' ) ); ?>">شروع اتصال به مرکز ←</a></div>
			</div>
			<div class="in" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
				<div><?php echo DastyarC_Ui::pill( 'گام ۱ — کلید فروشنده', 'b' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div><?php echo DastyarC_Ui::pill( 'گام ۲ — سینک اولیه', 'b' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div><?php echo DastyarC_Ui::pill( 'گام ۳ — شروع فروش', 'b' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
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

	/** شمارنده سبک هشدارهای قیمت از ترنزینت (برای KPI داشبورد — بدون درخواست اضافه به مرکز) */
	public static function price_alerts() {
		$items = get_transient( 'dastyarc_pricelog' );
		return is_array( $items ) ? $items : array();
	}
}
