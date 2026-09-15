<?php
/**
 * صفحه «گزارش‌ها» — ماژول‌های ۱۸ تا ۲۳، ۲۵، ۲۶، ۲۸، ۳۴، ۳۵ + خروجی CSV (۲۴).
 * تمام محاسبات روی داده‌های واقعی مرکز (سفارش‌های WooCommerce + متاها) انجام می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Page_Reports {

	/** سفارش‌های ماه جاری (کش درون‌درخواست برای چند کارت) */
	protected static function month_orders() {
		static $orders = null;
		if ( null === $orders ) {
			$orders = DA_Data::orders_range( DA_Data::t_month(), time(), 500 );
		}
		return $orders;
	}

	/** تجمیع فروش به تفکیک محصول از آیتم‌های سفارش‌ها */
	protected static function product_agg( array $orders ) {
		$agg = array();
		foreach ( $orders as $o ) {
			foreach ( (array) $o->get_items() as $it ) {
				$name = method_exists( $it, 'get_name' ) ? (string) $it->get_name() : 'قلم';
				if ( ! isset( $agg[ $name ] ) ) {
					$agg[ $name ] = array( 'qty' => 0, 'sum' => 0.0 );
				}
				$qty = method_exists( $it, 'get_quantity' ) ? (int) $it->get_quantity() : 1;
				$agg[ $name ]['qty'] += max( 1, $qty );
				$agg[ $name ]['sum'] += method_exists( $it, 'get_total' ) ? (float) $it->get_total() : 0.0;
			}
		}
		return $agg;
	}

	/* ── ۱۸ ─ پرفروش‌ترین محصول‌ها */
	public static function render_m18() {
		$agg = self::product_agg( self::month_orders() );
		uasort( $agg, function ( $a, $b ) { return $b['qty'] <=> $a['qty']; } );
		$rows = array();
		foreach ( array_slice( $agg, 0, 15, true ) as $name => $a ) {
			$rows[] = array( $name, $a['qty'], DA_Data::money( $a['sum'] ) );
		}
		echo '<p class="da-hint">این ماه — بر اساس تعداد فروخته‌شده. '
			. ( DA_Modules::enabled( 'm24' ) ? '<a class="da-btn gray" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=da_csv&what=top_products' ), DA_Admin::NONCE ) ) . '">⬇ خروجی CSV</a>' : '' ) . '</p>';
		echo DA_Chart::hbars( $rows );
	}

	/* ── ۱۹ ─ پرفروش‌ترین مشتری‌ها */
	public static function render_m19() {
		$map  = DA_Data::sales_map( self::month_orders() );
		uasort( $map, function ( $a, $b ) { return $b['sum'] <=> $a['sum']; } );
		$total= array_sum( array_map( function ( $m ) { return (float) $m['sum']; }, $map ) );
		$rows = array();
		foreach ( array_slice( $map, 0, 15, true ) as $uid => $m ) {
			$share = $total > 0 ? (int) round( $m['sum'] / $total * 100 ) : 0;
			$rows[]  = array( DA_Page_Dash::vendor_name( (int) $uid ), $m['sum'], DA_Data::money( $m['sum'] ) . ' (' . $share . '٪)' );
		}
		echo '<p class="da-hint">سهم هر مشتری از فروش تامین این ماه. '
			. ( DA_Modules::enabled( 'm24' ) ? '<a class="da-btn gray" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=da_csv&what=vendors' ), DA_Admin::NONCE ) ) . '">⬇ خروجی CSV</a>' : '' ) . '</p>';
		echo DA_Chart::hbars( $rows );
	}

	/* ── ۲۰ ─ درآمد مرکز و حاشیه فروشنده روی هر سفارش */
	public static function render_m20() {
		$rows = array();
		$sum_rev = 0.0; $sum_margin = 0.0;
		foreach ( array_reverse( DA_Data::remote_orders( self::month_orders() ) ) as $o ) {
			$goods = DA_Data::order_goods( $o );
			$rev   = $goods + (float) $o->get_shipping_total();
			$vendor_sale = 0.0;
			foreach ( (array) $o->get_items() as $it ) {
				$vp = method_exists( $it, 'get_meta' ) ? (float) $it->get_meta( DA_Data::VENDOR_PRICE ) : 0.0;
				$vendor_sale += $vp * ( method_exists( $it, 'get_quantity' ) ? max( 1, (int) $it->get_quantity() ) : 1 );
			}
			$margin = $vendor_sale > 0 ? $vendor_sale - $goods : 0.0;
			$sum_rev += $rev; $sum_margin += $margin;
			$rows[] = array(
				'#' . esc_html( $o->get_order_number() ),
				esc_html( DA_Page_Dash::vendor_name( (int) $o->get_meta( DA_Data::VENDOR_KEY ) ) ),
				DA_Data::money( $rev ),
				DA_Data::money( $margin ),
				$margin > 0 && $goods > 0 ? (int) round( $margin / $goods * 100 ) . '٪' : '—',
			);
			if ( count( $rows ) >= 30 ) { break; }
		}
		echo '<div class="da-grid">'
			. DA_Render::kpi( 'درآمد مرکز (ماه)', DA_Data::money( $sum_rev ), 'تامین + کرایه' )
			. DA_Render::kpi( 'جمع حاشیه فروشندگان', DA_Data::money( $sum_margin ), 'سودی که مشتری‌های ما روی فروش گذاشته‌اند', 'dark' )
			. '</div>';
		echo DA_Render::table( array( 'سفارش', 'مشتری', 'درآمد مرکز', 'حاشیه فروشنده', '٪' ), $rows );
	}

	/* ── ۲۱ ─ محصولات خواب‌رفته */
	public static function render_m21() {
		$sold = array();
		foreach ( DA_Data::orders_range( time() - 30 * 86400, time(), 500 ) as $o ) {
			foreach ( (array) $o->get_items() as $it ) {
				$name = method_exists( $it, 'get_name' ) ? (string) $it->get_name() : '';
				if ( '' !== $name ) { $sold[ $name ] = true; }
			}
		}
		$rows = array();
		foreach ( (array) wc_get_products( array( 'limit' => 300, 'status' => 'publish' ) ) as $p ) {
			$name = method_exists( $p, 'get_name' ) ? (string) $p->get_name() : '';
			if ( '' === $name || isset( $sold[ $name ] ) ) { continue; }
			$stock = method_exists( $p, 'get_stock_quantity' ) ? $p->get_stock_quantity() : null;
			$rows[] = array( esc_html( $name ), null === $stock ? '—' : number_format( (float) $stock ), DA_Render::pill( 'بدون فروش ۳۰ روز', 'amber' ) );
			if ( count( $rows ) >= 30 ) { break; }
		}
		echo '<p class="da-hint">این کالاها در ۳۰ روز اخیر هیچ فروشی نداشته‌اند — نامزد کمپین یا حذف از کاتالوگ.</p>';
		echo DA_Render::table( array( 'محصول', 'موجودی', 'وضعیت' ), $rows );
	}

	/* ── ۲۲ ─ ساعت طلایی سفارش‌ها */
	public static function render_m22() {
		$hours = array_fill( 0, 24, 0 );
		foreach ( DA_Data::orders_range( time() - 30 * 86400, time(), 900 ) as $o ) {
			$c = $o->get_date_created();
			if ( ! $c ) { continue; }
			$hours[ (int) gmdate( 'G', $c->getTimestamp() ) ]++;
		}
		$pairs = array();
		foreach ( $hours as $h => $n ) { $pairs[ $h ] = $n; }
		$best = array_search( max( $hours ), $hours, true );
		echo DA_Chart::bars( $pairs, 120 );
		echo '<p class="da-hint">پرتکرارترین ساعت سفارش‌گیری در ۳۰ روز اخیر: <strong>ساعت ' . (int) $best . '</strong> — مناسب برای زمان‌بندی اطلاعیه‌ها و پشتیبانی.</p>';
	}

	/* ── ۲۳ ─ نقشه شهرها */
	public static function render_m23() {
		$cities = array();
		foreach ( DA_Data::orders_range( time() - 30 * 86400, time(), 900 ) as $o ) {
			$addr = method_exists( $o, 'get_address' ) ? (array) $o->get_address( 'billing' ) : array();
			$city = trim( (string) ( $addr['city'] ?? '' ) );
			if ( '' === $city ) { $city = (string) ( $addr['state'] ?? '' ); }
			if ( '' === $city ) { $city = 'نامشخص'; }
			$cities[ $city ] = ( $cities[ $city ] ?? 0 ) + 1;
		}
		arsort( $cities );
		$rows = array();
		foreach ( array_slice( $cities, 0, 15, true ) as $c => $n ) {
			$rows[] = array( $c, $n, number_format( $n ) . ' سفارش' );
		}
		echo DA_Chart::hbars( $rows );
	}

	/* ── ۲۵ ─ گزارش مرجوعی‌ها */
	public static function render_m25() {
		$count  = 0;
		$status = array();
		$reason = array();
		foreach ( (array) get_posts( array( 'post_type' => 'dastyar_rma', 'posts_per_page' => 300 ) ) as $p ) {
			$count++;
			$pid = is_object( $p ) ? (int) $p->ID : (int) $p;
			$st  = (string) get_post_meta( $pid, '_dastyar_rma_status', true );
			$st  = $st ?: 'submitted';
			$status[ $st ] = ( $status[ $st ] ?? 0 ) + 1;
			$r   = trim( (string) get_post_meta( $pid, defined( 'DA_ADMIN_RMA_REASON' ) ? DA_ADMIN_RMA_REASON : ( class_exists( 'Dastyar_Rma' ) ? Dastyar_Rma::M_REASON : '_dastyar_rma_reason' ), true ) );
			if ( '' !== $r ) { $reason[ $r ] = ( $reason[ $r ] ?? 0 ) + 1; }
		}
		arsort( $reason );
		$rows = array();
		foreach ( $status as $st => $n ) { $rows[] = array( esc_html( $st ), number_format( $n ) ); }
		echo '<div class="da-grid">' . DA_Render::kpi( 'کل درخواست‌های مرجوعی', number_format( $count ) ) . '</div>';
		echo '<h4 style="margin:10px 0 6px">به تفکیک وضعیت</h4>' . DA_Render::table( array( 'وضعیت', 'تعداد' ), $rows );
		$rrows = array();
		foreach ( array_slice( $reason, 0, 10, true ) as $r => $n ) { $rrows[] = array( $r, $n, number_format( $n ) ); }
		if ( $rrows ) {
			echo '<h4 style="margin:12px 0 6px">پرتکرارترین دلایل</h4>' . DA_Chart::hbars( $rrows );
		}
	}

	/* ── ۲۶ ─ میانگین سبد */
	public static function render_m26() {
		$calc = function ( $orders ) {
			$tot = DA_Data::orders_totals( $orders );
			$n   = count( $orders );
			return $n ? $tot['goods'] / $n : 0;
		};
		$cur  = $calc( self::month_orders() );
		$prev = $calc( DA_Data::orders_range( DA_Data::t_prev_month_start(), DA_Data::t_prev_month_end(), 500 ) );
		echo '<div class="da-grid">'
			. DA_Render::kpi( 'میانگین سبد این ماه', DA_Data::money( $cur ), 'ماه قبل: ' . DA_Data::money( $prev ) . ' ' . DA_Chart::trend( $cur, $prev ) )
			. '</div>';
	}

	/* ── ۲۸ ─ گزارش تخفیف‌ها */
	public static function render_m28() {
		$sum = 0.0; $n = 0;
		foreach ( self::month_orders() as $o ) {
			$d = method_exists( $o, 'get_discount_total' ) ? (float) $o->get_discount_total() : 0.0;
			if ( $d > 0 ) { $sum += $d; $n++; }
		}
		echo '<div class="da-grid">'
			. DA_Render::kpi( 'سفارش‌های دارای تخفیف (ماه)', number_format( $n ) )
			. DA_Render::kpi( 'جمع تخفیف‌های ماه', DA_Data::money( $sum ) )
			. '</div>';
	}

	/* ── ۳۴ ─ نبض انبار */
	public static function render_m34() {
		$min  = (int) DA_Page_Settings::option( 'stock_alert' );
		$rows = array();
		foreach ( (array) wc_get_products( array( 'limit' => 400 ) ) as $p ) {
			if ( ! method_exists( $p, 'get_manage_stock' ) || ! $p->get_manage_stock() ) { continue; }
			$qty = method_exists( $p, 'get_stock_quantity' ) ? $p->get_stock_quantity() : null;
			if ( null === $qty || (int) $qty > $min ) { continue; }
			$rows[] = array(
				esc_html( (string) $p->get_name() ),
				number_format( (int) $qty ),
				(int) $qty <= 0 ? DA_Render::pill( 'اتمام!', 'red' ) : DA_Render::pill( 'رو به اتمام', 'amber' ),
			);
			if ( count( $rows ) >= 40 ) { break; }
		}
		echo '<p class="da-hint">کالاهای دارای «مدیریت موجودی» با موجودی ≤ ' . $min . ' — آستانه از تنظیمات کنسول.</p>';
		echo DA_Render::table( array( 'محصول', 'موجودی', 'وضعیت' ), $rows );
	}

	/* ── ۳۵ ─ پیش‌بینی اتمام موجودی */
	public static function render_m35() {
		$orders = DA_Data::orders_range( time() - 30 * 86400, time(), 900 );
		$speed  = array();
		foreach ( $orders as $o ) {
			foreach ( (array) $o->get_items() as $it ) {
				$name = method_exists( $it, 'get_name' ) ? (string) $it->get_name() : '';
				$qty  = method_exists( $it, 'get_quantity' ) ? max( 1, (int) $it->get_quantity() ) : 1;
				$speed[ $name ] = ( $speed[ $name ] ?? 0 ) + $qty;
			}
		}
		$rows = array();
		foreach ( (array) wc_get_products( array( 'limit' => 400 ) ) as $p ) {
			if ( ! method_exists( $p, 'get_manage_stock' ) || ! $p->get_manage_stock() ) { continue; }
			$qty  = method_exists( $p, 'get_stock_quantity' ) ? (int) $p->get_stock_quantity() : 0;
			$name = (string) $p->get_name();
			$per_day = ( $speed[ $name ] ?? 0 ) / 30;
			if ( $per_day <= 0 || $qty <= 0 ) { continue; }
			$days = (int) floor( $qty / $per_day );
			$rows[] = array(
				esc_html( $name ),
				number_format( $qty ),
				esc_html( number_format( $per_day, 1 ) . ' / روز' ),
				$days <= 7 ? DA_Render::pill( $days . ' روز!', 'red' ) : DA_Render::pill( $days . ' روز', $days <= 21 ? 'amber' : 'green' ),
			);
			if ( count( $rows ) >= 40 ) { break; }
		}
		usort( $rows, function ( $a, $b ) { return (int) strip_tags( $a[3] ) <=> (int) strip_tags( $b[3] ); } );
		echo '<p class="da-hint">بر اساس سرعت فروش ۳۰ روز اخیر (فروش روزانه = تعداد فروخته‌شده ÷ ۳۰).</p>';
		echo DA_Render::table( array( 'محصول', 'موجودی', 'سرعت فروش', 'روزهای باقی' ), $rows );
	}

	/* ==================================================================
	 * ۲۴ ─ خروجی CSV از گزارش‌ها
	 * ================================================================== */

	public static function csv_rows( $what ) {
		$head = array();
		$rows = array();
		switch ( $what ) {
			case 'top_products':
				$head = array( 'product', 'qty', 'sum' );
				foreach ( self::product_agg( self::month_orders() ) as $name => $a ) {
					$rows[] = array( $name, $a['qty'], $a['sum'] );
				}
				break;
			case 'vendors':
				$head = array( 'vendor', 'orders', 'sum' );
				foreach ( DA_Data::sales_map( self::month_orders() ) as $uid => $m ) {
					$rows[] = array( DA_Page_Dash::vendor_name( (int) $uid ), $m['orders'], $m['sum'] );
				}
				break;
			case 'wallet':
				$head = array( 'vendor', 'balance' );
				foreach ( DA_Data::vendors() as $v ) {
					$rows[] = array( $v['shop'] ?: $v['name'], $v['balance'] );
				}
				break;
			default:
				$head = array( 'date', 'orders', 'sum' );
				break;
		}
		return array( $head, $rows );
	}

	public static function export_csv() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$what = sanitize_key( (string) ( $_GET['what'] ?? '' ) );
		list( $head, $rows ) = self::csv_rows( $what );
		if ( function_exists( 'nocache_headers' ) ) { nocache_headers(); }
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=dastyar-' . $what . '-' . gmdate( 'Ymd' ) . '.csv' );
		echo "\xEF\xBB\xBF"; // BOM برای اکسل فارسی
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, $head );
		foreach ( $rows as $r ) { fputcsv( $out, $r ); }
		fclose( $out );
		exit;
	}
}
