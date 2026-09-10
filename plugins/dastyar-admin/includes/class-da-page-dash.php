<?php
/**
 * صفحه داشبورد کنسول + ویجت «امروز من» (ماژول ۴۸)
 * ماژول‌ها: ۱۶ ویترین امروز، ۴۹ مقایسه ماه، ۳۶ گردش مالی، ۲۹ تابلوی سفارش‌ها،
 * ۳۰ KPI انبار، ۳۱ دیرکرد، ۲۷ پیش‌بینی فروش، ۱۷ نمودار فروش ۳۰ روز.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Page_Dash {

	/* ── ۱۶ ─ ویترین امروز */
	public static function render_m16() {
		$from  = DA_Data::t_today();
		$orders= DA_Data::orders_range( $from, time(), 300 );
		$tot   = DA_Data::orders_totals( $orders );
		$n     = count( $orders );
		$remote= count( DA_Data::remote_orders( $orders ) );
		$avg   = $n ? ( $tot['goods'] ) / max( 1, $remote ) : 0;
		$ven_new = 0;
		foreach ( DA_Data::vendors() as $v ) {
			if ( $v['registered'] && strtotime( $v['registered'] ) >= $from ) {
				$ven_new++;
			}
		}
		$pend = count( DA_Data::pending_vendors() );
		echo '<div class="da-grid">'
			. DA_Render::kpi( 'سفارش امروز', number_format( $n ), $remote . ' سفارش فروشندگان' )
			. DA_Render::kpi( 'درآمد تامین امروز', DA_Data::money( $tot['goods'] ) )
			. DA_Render::kpi( 'میانگین سبد امروز', $remote ? DA_Data::money( $avg ) : '—' )
			. DA_Render::kpi( 'کرایه امروز', DA_Data::money( $tot['ship'] ) )
			. DA_Render::kpi( 'مشتری جدید امروز', number_format( $ven_new ), $pend ? $pend . ' در انتظار تأیید' : '', $pend ? 'red' : '' )
			. '</div>';
	}

	/* ── ۴۹ ─ مقایسه این ماه با ماه قبل */
	public static function render_m49() {
		$cur   = DA_Data::orders_range( DA_Data::t_month(), time(), 500 );
		$prev  = DA_Data::orders_range( DA_Data::t_prev_month_start(), DA_Data::t_prev_month_end(), 500 );
		$ct    = DA_Data::orders_totals( $cur );
		$pt    = DA_Data::orders_totals( $prev );
		$rows  = array(
			array( 'تعداد سفارش', count( $cur ), count( $prev ) ),
			array( 'درآمد تامین', $ct['goods'], $pt['goods'] ),
			array( 'کرایه', $ct['ship'], $pt['ship'] ),
			array( 'میانگین سبد', count( $cur ) ? $ct['goods'] / count( $cur ) : 0, count( $prev ) ? $pt['goods'] / count( $prev ) : 0 ),
		);
		$html = '<div class="da-grid">';
		foreach ( $rows as $r ) {
			$is_num = is_int( $r[1] );
			$v1     = $is_num ? number_format( $r[1] ) : DA_Data::money( $r[1] );
			$v0     = $is_num ? number_format( $r[2] ) : DA_Data::money( $r[2] );
			$html  .= DA_Render::kpi( $r[0], $v1, 'ماه قبل: ' . $v0 . ' ' . DA_Chart::trend( $r[1], $r[2] ) );
		}
		echo $html . '</div>';
	}

	/* ── ۳۶ ─ گردش مالی پلتفرم (این ماه) */
	public static function render_m36() {
		$from = DA_Data::t_month();
		$flow = DA_Data::wallet_flow( $from, time() );
		$tot  = DA_Data::orders_totals( DA_Data::orders_range( $from, time(), 500 ) );
		echo '<div class="da-grid">'
			. DA_Render::kpi( 'شارژهای کیف پول (ماه)', DA_Data::money( $flow['credit'] ), 'واریزی مشتری‌ها' )
			. DA_Render::kpi( 'برداشت/پرداخت‌ها', DA_Data::money( $flow['debit'] ), 'تسویه و خرید از کیف', 'red' )
			. DA_Render::kpi( 'فروش تامین (ماه)', DA_Data::money( $tot['goods'] ) )
			. DA_Render::kpi( 'کرایه (ماه)', DA_Data::money( $tot['ship'] ) )
			. DA_Render::kpi( 'تراز نقد پلتفرم', DA_Data::money( $flow['credit'] - $flow['debit'] ), 'شارژ منهای برداشت', 'dark' )
			. '</div>';
	}

	/* ── ۲۹ ─ تابلوی زنده سفارش‌ها */
	public static function render_m29() {
		$posted_slug = 'wc-' . ( class_exists( 'Dastyar_Statuses' ) ? Dastyar_Statuses::POSTED : 'posted' );
		$labels = array(
			'wc-pending'      => 'در انتظار',
			'wc-processing'   => 'در حال انجام',
			'wc-on-hold'      => 'در انتظار بررسی',
			$posted_slug      => 'تحویل پست شده',
			'wc-completed'    => 'تکمیل شده',
			'wc-cancelled'    => 'لغو شده',
			'wc-refunded'     => 'مسترد/مرجوع',
		);
		$counts = array();
		foreach ( (array) wc_get_orders( array( 'limit' => 400, 'orderby' => 'date', 'order' => 'DESC' ) ) as $o ) {
			$s = 'wc-' . (string) $o->get_status();
			$counts[ $s ] = ( $counts[ $s ] ?? 0 ) + 1;
		}
		echo '<div class="da-grid">';
		foreach ( $labels as $slug => $label ) {
			$c = (int) ( $counts[ $slug ] ?? 0 );
			$cls = in_array( $slug, array( 'wc-cancelled', 'wc-refunded' ), true ) ? 'red' : '';
			$cls = 'wc-processing' === $slug ? 'dark' : $cls;
			echo DA_Render::kpi( $label, number_format( $c ), '', $cls );
		}
		echo '</div><p class="da-hint">بر اساس ۴۰۰ سفارش اخیر مرکز.</p>';
	}

	/* ── ۳۰ ─ KPI انبار: میانگین زمان تا ارسال به دستیار */
	public static function render_m30() {
		$sum = 0; $n = 0; $slow = 0;
		foreach ( array_slice( (array) wc_get_orders( array( 'limit' => 200, 'status' => array( 'wc-' . ( class_exists( 'Dastyar_Statuses' ) ? Dastyar_Statuses::POSTED : 'posted' ), 'wc-completed' ) ) ), 0, 200 ) as $o ) {
			$c = $o->get_date_created();
			$m = method_exists( $o, 'get_date_modified' ) ? $o->get_date_modified() : null;
			if ( ! $c || ! $m ) {
				continue;
			}
			$h = (int) $m->getTimestamp() - (int) $c->getTimestamp();
			if ( $h < 0 ) { continue; }
			$sum += $h / 3600;
			$n++;
			if ( $h > (float) DA_Page_Settings::option( 'sla_hours' ) * 3600 ) { $slow++; }
		}
		$avg = $n ? (int) round( $sum / $n ) : 0;
		echo '<div class="da-grid">'
			. DA_Render::kpi( 'میانگین آماده‌سازی تا ارسال', number_format( $avg ) . ' ساعت', 'از ' . $n . ' سفارش ارسال‌شده اخیر' )
			. DA_Render::kpi( 'کندتر از SLA', number_format( $slow ), 'نفر سفارش بیش از ' . (int) DA_Page_Settings::option( 'sla_hours' ) . ' ساعت', $slow ? 'red' : '' )
			. '</div>';
	}

	/* ── ۳۱ ─ سفارش‌های دیرکرد */
	public static function render_m31() {
		$sla  = (int) DA_Page_Settings::option( 'sla_hours' ) * 3600;
		$rows = array();
		foreach ( (array) wc_get_orders( array( 'limit' => 150, 'status' => array( 'wc-processing', 'wc-on-hold' ), 'orderby' => 'date', 'order' => 'ASC' ) ) as $o ) {
			$c = $o->get_date_created();
			if ( ! $c ) { continue; }
			$age = time() - (int) $c->getTimestamp();
			if ( $age < $sla ) { continue; }
			$uid = (int) $o->get_meta( DA_Data::VENDOR_KEY );
			$rows[] = array(
				'<a href="' . esc_url( admin_url( 'post.php?post=' . (int) $o->get_id() . '&action=edit' ) ) . '" target="_blank">#' . esc_html( $o->get_order_number() ) . '</a>',
				$uid ? esc_html( self::vendor_name( $uid ) ) : '—',
				esc_html( (int) round( $age / 3600 ) ) . ' ساعت',
				DA_Data::money( DA_Data::order_goods( $o ) ),
				DA_Render::pill( 'دیرکرد', 'red' ),
			);
			if ( count( $rows ) >= 30 ) { break; }
		}
		echo DA_Render::table( array( 'سفارش', 'مشتری', 'عمر', 'مبلغ تامین', 'وضعیت' ), $rows );
	}

	/* ── ۲۷ ─ پیش‌بینی فروش هفته آینده */
	public static function render_m27() {
		$from  = time() - 14 * 86400;
		$tot   = DA_Data::orders_totals( DA_Data::orders_range( $from, time(), 500 ) );
		$daily = $tot['goods'] / 14;
		echo '<div class="da-grid">'
			. DA_Render::kpi( 'میانگین فروش روزانه (۱۴ روز)', DA_Data::money( $daily ) )
			. DA_Render::kpi( 'پیش‌بینی فروش هفته آینده', DA_Data::money( $daily * 7 ), 'برآورد روندی — قطعی نیست', 'dark' )
			. '</div>';
	}

	/* ── ۱۷ ─ نمودار فروش ۳۰ روز + مقایسه */
	public static function render_m17() {
		$day   = 86400;
		$from  = DA_Data::t_today() - 29 * $day;
		$prevF = $from - 30 * $day;
		$orders = DA_Data::orders_range( $prevF, time(), 900 );
		$per    = array();
		$now_sum = 0.0; $prev_sum = 0.0;
		for ( $i = 0; $i < 30; $i++ ) {
			$per[ gmdate( 'j/n', $from + $i * $day ) ] = 0;
		}
		foreach ( $orders as $o ) {
			$c = $o->get_date_created();
			$ts = $c ? (int) $c->getTimestamp() : 0;
			$g = DA_Data::order_goods( $o ) ?: ( (int) $o->get_meta( DA_Data::VENDOR_KEY ) ? 0 : (float) $o->get_total() );
			if ( $ts >= $from ) {
				$k = gmdate( 'j/n', $ts );
				if ( isset( $per[ $k ] ) ) { $per[ $k ] += $g; }
				$now_sum += $g;
			} else {
				$prev_sum += $g;
			}
		}
		echo DA_Chart::bars( $per );
		echo '<p class="da-hint">مجموع ۳۰ روز اخیر: <strong>' . esc_html( DA_Data::money( $now_sum ) ) . '</strong>'
			. ' — ۳۰ روز قبل‌تر: ' . esc_html( DA_Data::money( $prev_sum ) ) . ' ' . DA_Chart::trend( $now_sum, $prev_sum ) . '</p>';
	}

	/* ── ۴۸ ─ ویجت «امروز من» روی پیشخوان وردپرس */
	public static function register_widget() {
		if ( ! DA_Modules::enabled( 'm48' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'da_today_widget', '🛍 امروز من — دستیار شاپ', array( __CLASS__, 'render_widget' ) );
	}

	/**
	 * شمارش موارد نیازمند توجه فوری — پایه ویجت پیشخوان («خلاصه قبل از ورود»).
	 * هر عدد > ۰ یعنی چیزی منتظر تصمیم مدیر است؛ همه از همان منابع ماژول‌های
	 * موجود (۴/۶/۱۰/۱۵/۳۱/۴۳) خوانده می‌شود — چرخ دوباره اختراع نشد.
	 */
	public static function attention_counts() {
		$out = array(
			'pending_vendors' => count( DA_Data::pending_vendors() ),
			'receipts'        => 0,
			'low_balance'     => 0,
			'offline'         => 0,
			'overdue'         => 0,
		);
		foreach ( (array) wc_get_orders( array( 'limit' => 150, 'status' => array( 'wc-on-hold', 'wc-pending', 'wc-processing' ) ) ) as $o ) {
			if ( $o->get_meta( '_dastyar_wallet_charge' ) && ! $o->get_meta( '_dastyar_wallet_credited' ) ) {
				$out['receipts']++;
			}
		}
		$low_th = (float) DA_Page_Settings::option( 'low_balance' );
		$off_h  = max( 1, (float) DA_Page_Settings::option( 'offline_hours', 6 ) );
		foreach ( DA_Data::vendors() as $v ) {
			if ( $v['balance'] < $low_th ) {
				$out['low_balance']++;
			}
			$last = (int) get_user_meta( $v['id'], '_dastyar_last_ping', true );
			if ( $last && ( time() - $last ) / 3600 >= $off_h ) {
				$out['offline']++;
			}
		}
		$sla = (int) DA_Page_Settings::option( 'sla_hours' ) * 3600;
		foreach ( (array) wc_get_orders( array( 'limit' => 150, 'status' => array( 'wc-processing', 'wc-on-hold' ) ) ) as $o ) {
			$c = $o->get_date_created();
			if ( $c && ( time() - (int) $c->getTimestamp() ) >= $sla ) {
				$out['overdue']++;
			}
		}
		return $out;
	}

	public static function render_widget() {
		$from   = DA_Data::t_today();
		$orders = DA_Data::orders_range( $from, time(), 300 );
		$tot    = DA_Data::orders_totals( $orders );
		$att    = self::attention_counts();

		echo '<div class="da-wrap"><div class="da-grid">'
			. DA_Render::kpi( 'سفارش امروز', number_format( count( $orders ) ) )
			. DA_Render::kpi( 'درآمد تامین امروز', DA_Data::money( $tot['goods'] ) )
			. DA_Render::kpi( 'مشتری فعال', number_format( count( DA_Data::vendors() ) ) )
			. '</div>';

		$alerts = array(
			array( $att['pending_vendors'], 'فروشنده در انتظار تأیید', 'admin.php?page=da-customers' ),
			array( $att['receipts'], 'شارژ کیف پول ناتمام', 'admin.php?page=da-wallet' ),
			array( $att['low_balance'], 'مشتری با مانده کیف پول کم', 'admin.php?page=da-wallet' ),
			array( $att['offline'], 'فروشگاه آفلاین', 'admin.php?page=da-health' ),
			array( $att['overdue'], 'سفارش دیرکرد از SLA', 'admin.php?page=da-dash' ),
		);
		$any = false;
		echo '<div style="margin-top:10px;display:flex;flex-direction:column;gap:6px">';
		foreach ( $alerts as $a ) {
			if ( $a[0] <= 0 ) {
				continue;
			}
			$any = true;
			printf(
				'<a href="%s" style="display:flex;justify-content:space-between;align-items:center;background:#fdf3e2;border:1px solid #eccb6a;border-radius:8px;padding:6px 10px;text-decoration:none;color:#7a5b00;font-size:12.5px;font-weight:700">
					<span>%s</span><span style="background:#7a5b00;color:#fff;border-radius:10px;padding:1px 9px;font-size:11px">%d</span>
				</a>',
				esc_url( admin_url( $a[2] ) ),
				esc_html( $a[1] ),
				(int) $a[0]
			);
		}
		echo '</div>';
		if ( ! $any ) {
			echo '<p class="da-hint" style="margin-top:8px">✅ همه‌چیز مرتب است؛ چیزی نیاز به بررسی فوری ندارد.</p>';
		}

		echo '<p class="da-hint" style="margin-top:10px"><a href="' . esc_url( admin_url( 'admin.php?page=da-dash' ) ) . '">باز کردن کنسول مرکز ←</a></p></div>';
	}

	/** نام فروشنده از id (کش‌درون‌متد) */
	public static function vendor_name( $uid ) {
		static $cache = array();
		if ( isset( $cache[ $uid ] ) ) { return $cache[ $uid ]; }
		$u = get_userdata( (int) $uid );
		$name = $u ? $u->display_name : 'کاربر #' . (int) $uid;
		$shop = (string) get_user_meta( (int) $uid, '_dastyar_shop_name', true );
		return $cache[ $uid ] = ( $shop ? $shop . ' — ' : '' ) . $name;
	}
}
