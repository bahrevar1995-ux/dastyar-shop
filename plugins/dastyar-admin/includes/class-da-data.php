<?php
/**
 * لایه داده مشترک کنسول — بدون اختراع دوباره چرخ:
 * از Dastyar_Wallet (مانده/تراکنش)، Dastyar_Vendors (فهرست متصل‌ها)، Dastyar_Logger (لاگ API)،
 * Dastyar_Pricelog (افزایش قیمت‌ها)، Dastyar_Tiers (رده‌ها) و جدول dastyar_wallet_txns مرکز استفاده می‌کند.
 * همه خوانش‌ها Fail-Safe‌اند؛ اگر افزونه مرکز غایب بود کارت‌ها خالی و مودبانه نمایش داده می‌شوند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Data {

	const SALES_KEY    = '_dastyar_goods_total';        // درآمد تامین هر سفارش راه‌دور (متا سفارش مرکز)
	const VENDOR_KEY   = '_dastyar_vendor_id';          // فروشنده صاحب سفارش راه‌دور
	const VENDOR_PRICE = '_dastyar_vendor_sale_price';  // قیمت فروش فروشنده روی قلم (متا آیتم)
	const TRACK_KEY    = '_dastyar_tracking_code';      // کد رهگیری محموله (کلیدی که سایت فروشنده هم می‌خواند)
	const SHIP_KEY     = '_dastyar_shipping_total';

	/** وضعیت‌هایی که «فروش» محسوب می‌شوند */
	public static function sold_statuses() {
		return array( 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-' . ( class_exists( 'Dastyar_Statuses' ) ? Dastyar_Statuses::POSTED : 'posted' ) );
	}

	/** دسترسی امن به کیف پول مرکز */
	public static function wallet() {
		if ( class_exists( 'Dastyar' ) && method_exists( 'Dastyar', 'instance' ) ) {
			$d = Dastyar::instance();
			if ( $d && isset( $d->wallet ) && is_object( $d->wallet ) ) {
				return $d->wallet;
			}
		}
		return null;
	}

	/** دسترسی امن به کلاس فروشندگان مرکز */
	public static function vendors_api() {
		if ( class_exists( 'Dastyar' ) && method_exists( 'Dastyar', 'instance' ) ) {
			$d = Dastyar::instance();
			if ( $d && isset( $d->vendors ) && is_object( $d->vendors ) ) {
				return $d->vendors;
			}
		}
		return null;
	}

	public static function logger() {
		if ( class_exists( 'Dastyar' ) && method_exists( 'Dastyar', 'instance' ) ) {
			$d = Dastyar::instance();
			if ( $d && isset( $d->logger ) && is_object( $d->logger ) ) {
				return $d->logger;
			}
		}
		return null;
	}

	/** مانده کیف پول کاربر (۰ در صورت نبود سرویس) */
	public static function balance( $uid ) {
		$w = self::wallet();
		if ( $w && method_exists( $w, 'get_balance' ) ) {
			return (float) $w->get_balance( (int) $uid );
		}
		return 0.0;
	}

	/** فهرست کاربران-فروشنده (نقش dastyar_vendor) + متاهای کلیدی */
	public static function vendors() {
		$api = self::vendors_api();
		$out = array();
		foreach ( (array) get_users( array( 'role' => 'dastyar_vendor' ) ) as $u ) {
			$uid  = (int) $u->ID;
			$site = $api && method_exists( $api, 'site_url' ) ? (string) $api->site_url( $uid ) : (string) get_user_meta( $uid, '_dastyar_site_url', true );
			$out[] = array(
				'id'        => $uid,
				'name'      => (string) $u->display_name,
				'email'     => (string) $u->user_email,
				'registered'=> (string) $u->user_registered,
				'shop'      => (string) get_user_meta( $uid, '_dastyar_shop_name', true ),
				'shop_type' => (string) get_user_meta( $uid, '_dastyar_shop_type', true ),
				'site'      => $site,
				'tags'      => (string) get_user_meta( $uid, '_da_tags', true ),
				'note'      => (string) get_user_meta( $uid, '_da_note', true ),
				'balance'   => self::balance( $uid ),
			);
		}
		return $out;
	}

	/** فروشنده‌های در انتظار تأیید (متای ثبت‌نام مرکز) */
	public static function pending_vendors() {
		$key = defined( 'DA_ADMIN_REG_PENDING' ) ? DA_ADMIN_REG_PENDING : ( class_exists( 'Dastyar_Registration' ) ? Dastyar_Registration::PENDING : '_dastyar_pending_vendor' );
		return (array) get_users( array( 'meta_key' => $key, 'meta_value' => 1 ) );
	}

	/**
	 * سفارش‌های یک بازه زمانی (ثبت در مرکز). دفاع دو‌لایه: خود ووکامرس + فیلتر timestamp
	 * تا در محیط تست/داده‌های ناقص هم بازه دقیق بماند.
	 */
	public static function orders_range( $from_ts, $to_ts, $limit = 500 ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$args = array(
			'limit'        => $limit,
			'status'       => self::sold_statuses(),
			'date_created' => (int) $from_ts . '...' . (int) $to_ts,
			'orderby'      => 'date',
			'order'        => 'ASC',
		);
		$out = array();
		foreach ( (array) wc_get_orders( $args ) as $o ) {
			$dc = method_exists( $o, 'get_date_created' ) ? $o->get_date_created() : null;
			$ts = $dc && method_exists( $dc, 'getTimestamp' ) ? (int) $dc->getTimestamp() : 0;
			if ( $ts && ( $ts < $from_ts || $ts > $to_ts ) ) {
				continue;
			}
			$out[] = $o;
		}
		return $out;
	}

	/** فقط سفارش‌های راه‌دور (متعلق به فروشندگان) */
	public static function remote_orders( array $orders ) {
		$out = array();
		foreach ( $orders as $o ) {
			if ( (int) $o->get_meta( self::VENDOR_KEY ) > 0 ) {
				$out[] = $o;
			}
		}
		return $out;
	}

	/** درآمد تامین یک سفارش (goods_total)، با بازگشت‌پذیری روی جمع آیتم‌ها */
	public static function order_goods( $o ) {
		$g = (float) $o->get_meta( self::SALES_KEY );
		return $g;
	}

	/**
	 * نقشه فروش به تفکیک فروشنده: uid => [orders,sum,last]
	 */
	public static function sales_map( array $orders ) {
		$map = array();
		foreach ( self::remote_orders( $orders ) as $o ) {
			$uid = (int) $o->get_meta( self::VENDOR_KEY );
			if ( ! isset( $map[ $uid ] ) ) {
				$map[ $uid ] = array( 'orders' => 0, 'sum' => 0.0, 'last' => 0 );
			}
			$map[ $uid ]['orders']++;
			$map[ $uid ]['sum'] += self::order_goods( $o );
			$dc = $o->get_date_created();
			$ts = $dc && method_exists( $dc, 'getTimestamp' ) ? (int) $dc->getTimestamp() : 0;
			if ( $ts > $map[ $uid ]['last'] ) {
				$map[ $uid ]['last'] = $ts;
			}
		}
		return $map;
	}

	/** مجموع گردش سفارش‌ها (درآمد تامین راه‌دور + مبلغ سفارش‌های مستقیم مرکز) */
	public static function orders_totals( array $orders ) {
		$goods = 0.0;
		$ship  = 0.0;
		foreach ( $orders as $o ) {
			if ( (int) $o->get_meta( self::VENDOR_KEY ) > 0 ) {
				$goods += self::order_goods( $o );
				$s = (float) $o->get_meta( self::SHIP_KEY );
				$ship += $s > 0 ? $s : (float) $o->get_shipping_total();
			} else {
				$goods += (float) $o->get_total();
			}
		}
		return array( 'goods' => $goods, 'ship' => $ship );
	}

	/** تراکنش‌های کیف پول کاربر (۵۰ تای اخیر) */
	public static function wallet_txns( $uid, $limit = 50 ) {
		$w = self::wallet();
		if ( $w && method_exists( $w, 'get_transactions' ) ) {
			return (array) $w->get_transactions( (int) $uid, (int) $limit );
		}
		return array();
	}

	/** جمع تراکنش‌های کیف پول همه فروشندگان در یک بازه (نوع credit/debit) از جدول مرکز */
	public static function wallet_flow( $from_ts, $to_ts ) {
		global $wpdb;
		$out = array( 'credit' => 0.0, 'debit' => 0.0 );
		if ( ! isset( $wpdb ) ) {
			return $out;
		}
		$table = $wpdb->prefix . 'dastyar_wallet_txns';
		$from  = gmdate( 'Y-m-d H:i:s', $from_ts );
		$to    = gmdate( 'Y-m-d H:i:s', $to_ts );
		$rows  = (array) $wpdb->get_results( "SELECT type, amount FROM {$table} WHERE created_at >= '{$from}' AND created_at <= '{$to}'" );
		foreach ( $rows as $r ) {
			$r = (array) $r;
			$t = (string) ( $r['type'] ?? '' );
			if ( isset( $out[ $t ] ) ) {
				$out[ $t ] += (float) ( $r['amount'] ?? 0 );
			}
		}
		return $out;
	}

	/** آخرین لاگ‌های API مرکز (از کلاس خود مرکز) */
	public static function api_logs( $limit = 300 ) {
		$l = self::logger();
		if ( $l && method_exists( $l, 'get_logs' ) ) {
			return (array) $l->get_logs( (int) $limit );
		}
		return array();
	}

	/** قالب‌بندی پول */
	public static function money( $n ) {
		return number_format( (float) $n ) . ' تومان';
	}

	/** ساعت/تاریخ خوانا */
	public static function ago( $ts ) {
		$d = time() - (int) $ts;
		if ( $d < 3600 ) {
			return max( 1, (int) ( $d / 60 ) ) . ' دقیقه پیش';
		}
		if ( $d < 86400 ) {
			return (int) ( $d / 3600 ) . ' ساعت پیش';
		}
		return (int) ( $d / 86400 ) . ' روز پیش';
	}

	/* بازه‌های استاندارد */
	public static function t_today() { return strtotime( gmdate( 'Y-m-d 00:00:00' ) ); }
	public static function t_month() { return strtotime( gmdate( 'Y-m-01 00:00:00' ) ); }
	public static function t_prev_month_start() { return strtotime( gmdate( 'Y-m-01 00:00:00', strtotime( 'first day of last month' ) ) ); }
	public static function t_prev_month_end() { return self::t_month() - 1; }
}
