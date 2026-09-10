<?php
/**
 * صفحه «ابزارها» — ماژول‌های ۴۰ (بنر اطلاعیه فروشنده‌ها)، ۴۱ (خبرنامه تغییر قیمت تامین)،
 * ۴۲ (آمار تیکت‌ها)، ۴۷ (جستجوی جهانی)، ۵۰ (کمپین امتیاز).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Page_Tools {

	/* ── ۴۰ ─ بنر اطلاعیه فروشنده‌ها (روی همان زیرساخت اعلان ماژول ۸) */
	public static function render_m40() {
		$active = null;
		foreach ( array_reverse( DA_Admin::broadcasts() ) as $b ) {
			if ( ! empty( $b['active'] ) ) { $active = $b; break; }
		}
		if ( $active ) {
			echo '<div class="da-card" style="border-color:#17a16d"><div class="da-card-b" style="border-right:6px solid #17a16d">'
				. '<strong>' . esc_html( (string) $active['title'] ) . '</strong> '
				. DA_Render::pill( 'در حال نمایش به فروشنده‌ها', 'green' )
				. '<p style="margin:8px 0 0">' . esc_html( (string) $active['msg'] ) . '</p>'
				. '<p class="da-hint">از: ' . esc_html( (string) $active['time'] ) . ' — '
				. '<a class="da-btn gray" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=da_bcast_save&off=1' ), DA_Admin::NONCE ) ) . '">⏹ توقف نمایش</a></p>'
				. '</div></div>';
		} else {
			echo '<p class="da-empty">در حال حاضر بنر فعالی نیست.</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. wp_nonce_field( DA_Admin::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="da_bcast_save"><input type="hidden" name="tag" value="">'
			. '<div class="da-form-row"><input class="da-input" name="title" placeholder="عنوان بنر" required style="min-width:240px"></div>'
			. '<textarea class="da-input" name="msg" rows="2" style="width:100%" placeholder="متن بنر که در پیشخوان سایت همه فروشنده‌ها دیده می‌شود…" required></textarea>'
			. '<p><button class="da-btn">انتشار بنر برای همه</button></p></form>'
			. '<p class="da-hint">برای اطلاعیه هدفمند به یک گروه، از «اطلاعیه انبوه» (ماژول ۸ در صفحه مشتری‌ها) استفاده کنید.</p>';
	}

	/* ── ۴۱ ─ خبرنامه تغییر قیمت تامین (۷ روز اخیر) */
	public static function render_m41() {
		$week  = array();
		$cut   = time() - 7 * 86400;
		$items = class_exists( 'Dastyar_Pricelog' ) ? (array) Dastyar_Pricelog::latest( 30 ) : array();
		foreach ( $items as $e ) {
			$e  = (array) $e;
			$ts = strtotime( (string) ( $e['time'] ?? '' ) );
			if ( $ts && $ts < $cut ) { continue; }
			$week[] = $e;
		}
		$rows = array();
		foreach ( array_slice( $week, 0, 10 ) as $e ) {
			$rows[] = array(
				esc_html( (string) ( $e['product_name'] ?? '' ) ),
				DA_Data::money( (float) ( $e['old'] ?? 0 ) ),
				DA_Data::money( (float) ( $e['new'] ?? 0 ) ),
				DA_Render::pill( '+' . (int) round( (float) ( $e['percent'] ?? 0 ) ) . '٪', 'red' ),
			);
		}
		echo '<div class="da-grid">'
			. DA_Render::kpi( 'افزایش قیمت ۷ روز اخیر', number_format( count( $week ) ), 'منبع: لاگ رسمی قیمت مرکز' )
			. '</div>';
		echo DA_Render::table( array( 'محصول', 'قیمت قبلی', 'قیمت جدید', 'افزایش' ), $rows );
		echo '<p class="da-hint">این خلاصه را هفتگی برای مشتری‌ها اعلام کنید — تب «هشدارهای قیمت» در افزونه فروشنده هم از همین داده تغذیه می‌شود.</p>';
	}

	/* ── ۴۲ ─ آمار تیکت‌ها */
	public static function render_m42() {
		$posts  = (array) get_posts( array( 'post_type' => 'dastyar_ticket', 'posts_per_page' => 300 ) );
		$total  = count( $posts );
		$by_st  = array();
		$age    = 0; $open_n = 0;
		foreach ( $posts as $p ) {
			$pid = is_object( $p ) ? (int) $p->ID : (int) $p;
			$st  = 'open';
			if ( function_exists( 'wp_get_post_terms' ) ) {
				$terms = wp_get_post_terms( $pid, 'dastyar_ticket_status', array( 'fields' => 'slugs' ) );
				if ( $terms && ! is_wp_error( $terms ) ) { $st = (string) $terms[0]; }
			}
			$by_st[ $st ] = ( $by_st[ $st ] ?? 0 ) + 1;
			$date = is_object( $p ) && isset( $p->post_date ) ? strtotime( (string) $p->post_date ) : 0;
			if ( 'closed' !== $st && $date ) { $age += time() - $date; $open_n++; }
		}
		$labels = array( 'open' => 'باز', 'answered' => 'پاسخ داده شده', 'closed' => 'بسته شده' );
		echo '<div class="da-grid">'
			. DA_Render::kpi( 'کل تیکت‌ها', number_format( $total ) )
			. DA_Render::kpi( 'میانگین عمر تیکت‌های باز', $open_n ? number_format( (int) round( ( $age / $open_n ) / 86400, 1 ) ) . ' روز' : '—' )
			. '</div><div class="da-grid" style="margin-top:10px">';
		foreach ( $labels as $st => $lb ) {
			echo DA_Render::kpi( $lb, number_format( (int) ( $by_st[ $st ] ?? 0 ) ) );
		}
		echo '</div>';
	}

	/* ── ۴۷ ─ جستجوی جهانی */
	public static function render_m47() {
		$q = sanitize_text_field( (string) ( $_GET['da_s'] ?? '' ) );
		echo '<form method="get" class="da-form-row"><input type="hidden" name="page" value="da-tools">'
			. '<input class="da-input" type="text" name="da_s" value="' . esc_attr( $q ) . '" placeholder="شماره سفارش، تلفن، نام مشتری یا محصول…" style="min-width:300px">'
			. '<button class="da-btn">جستجو</button></form>';
		if ( '' === $q ) {
			echo '<p class="da-empty">عبارت را بنویسید و جستجو کنید؛ در سفارش‌ها، مشتری‌ها و محصول‌ها هم‌زمان گشته می‌شود.</p>';
			return;
		}
		// سفارش‌ها
		$o_rows = array();
		foreach ( (array) wc_get_orders( array( 'limit' => 150, 'orderby' => 'date', 'order' => 'DESC' ) ) as $o ) {
			$addr = method_exists( $o, 'get_address' ) ? (array) $o->get_address( 'billing' ) : array();
			$hay  = (string) $o->get_order_number()
				. ' ' . (string) $o->get_meta( '_dastyar_remote_order_number' )
				. ' ' . (string) ( $addr['phone'] ?? '' )
				. ' ' . (string) ( $addr['first_name'] ?? '' ) . ' ' . (string) ( $addr['last_name'] ?? '' );
			if ( false === mb_strpos( $hay, $q ) ) { continue; }
			$o_rows[] = array(
				'<a href="' . esc_url( admin_url( 'post.php?post=' . (int) $o->get_id() . '&action=edit' ) ) . '" target="_blank">#' . esc_html( $o->get_order_number() ) . '</a>',
				esc_html( trim( (string) ( ( $addr['first_name'] ?? '' ) . ' ' . ( $addr['last_name'] ?? '' ) ) ) ?: '—' ),
				esc_html( (string) ( $addr['phone'] ?? '' ) ),
				esc_html( wc_get_order_status_name( $o->get_status() ) ),
			);
			if ( count( $o_rows ) >= 10 ) { break; }
		}
		echo '<h4 style="margin:12px 0 4px">سفارش‌ها</h4>' . DA_Render::table( array( 'سفارش', 'گیرنده', 'تلفن', 'وضعیت' ), $o_rows );

		// مشتری‌ها
		$u_rows = array();
		foreach ( DA_Data::vendors() as $v ) {
			if ( false === mb_stripos( $v['name'] . ' ' . $v['shop'] . ' ' . $v['email'] . ' ' . $v['site'], $q ) ) { continue; }
			$u_rows[] = array(
				esc_html( $v['shop'] ?: $v['name'] ),
				esc_html( $v['email'] ),
				DA_Data::money( $v['balance'] ),
				DA_Modules::enabled( 'm02' ) ? '<a class="da-btn gray" href="' . esc_url( admin_url( 'admin.php?page=da-customers&da_uid=' . $v['id'] ) ) . '">کارت ۳۶۰°</a>' : '',
			);
			if ( count( $u_rows ) >= 10 ) { break; }
		}
		echo '<h4 style="margin:12px 0 4px">مشتری‌ها</h4>' . DA_Render::table( array( 'مشتری', 'ایمیل', 'مانده', '' ), $u_rows );

		// محصول‌ها
		$p_rows = array();
		foreach ( (array) wc_get_products( array( 'limit' => 300 ) ) as $p ) {
			$name = method_exists( $p, 'get_name' ) ? (string) $p->get_name() : '';
			if ( '' === $name || false === mb_stripos( $name, $q ) ) { continue; }
			$stk = method_exists( $p, 'get_manage_stock' ) && $p->get_manage_stock() && method_exists( $p, 'get_stock_quantity' )
				? number_format( (float) $p->get_stock_quantity() ) : '—';
			$p_rows[] = array( esc_html( $name ), $stk );
			if ( count( $p_rows ) >= 10 ) { break; }
		}
		echo '<h4 style="margin:12px 0 4px">محصول‌ها</h4>' . DA_Render::table( array( 'محصول', 'موجودی' ), $p_rows );
	}

	/* ── ۵۰ ─ کمپین امتیاز مشتری‌ها */
	public static function render_m50() {
		$per   = max( 1, (float) DA_Page_Settings::option( 'points_per' ) );
		$val   = max( 0, (float) DA_Page_Settings::option( 'point_value' ) );
		$map   = DA_Data::sales_map( DA_Data::orders_range( DA_Data::t_month(), time(), 500 ) );
		$rows  = array();
		uasort( $map, function ( $a, $b ) { return $b['sum'] <=> $a['sum']; } );
		$rank  = 0;
		foreach ( $map as $uid => $m ) {
			$rank++;
			$points = (int) floor( (float) $m['sum'] / $per );
			$rows[] = array(
				$rank <= 3 ? array( '🥇', '🥈', '🥉' )[ $rank - 1 ] : (string) $rank,
				esc_html( DA_Page_Dash::vendor_name( (int) $uid ) ),
				DA_Data::money( $m['sum'] ),
				number_format( $points ),
				$points > 0
					? '<a class="da-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=da_points_reward&uid=' . (int) $uid . '&points=' . $points ), DA_Admin::NONCE ) ) . '" onclick="return confirm(\'پاداش ' . esc_attr( DA_Data::money( $points * $val ) ) . ' به کیف پول واریز شود؟\')">اهدای پاداش</a>'
					: '<span class="da-empty">—</span>',
			);
			if ( $rank >= 20 ) { break; }
		}
		echo '<p class="da-hint">قانون: هر ' . esc_html( number_format( $per ) ) . ' تومان فروش این ماه = ۱ امتیاز؛ ارزش هر امتیاز = ' . esc_html( DA_Data::money( $val ) ) . ' — از تنظیمات کنسول. پاداش مستقیم به کیف پول مشتری واریز می‌شود.</p>';
		echo DA_Render::table( array( 'رتبه', 'مشتری', 'فروش ماه', 'امتیاز', 'اقدام' ), $rows );
	}

	public static function save_points_reward() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$uid    = (int) ( $_GET['uid'] ?? 0 );
		$points = (int) ( $_GET['points'] ?? 0 );
		$val    = max( 0, (float) DA_Page_Settings::option( 'point_value' ) );
		$w      = DA_Data::wallet();
		if ( $uid && $points > 0 && $w ) {
			$amount = $points * $val;
			if ( $amount > 0 ) {
				$w->credit( $uid, $amount, 0, 'پاداش کمپین امتیاز کنسول مرکز (' . $points . ' امتیاز)' );
			}
		}
		DA_Render::redirect_back( 'da-tools', 'done' );
	}
}
