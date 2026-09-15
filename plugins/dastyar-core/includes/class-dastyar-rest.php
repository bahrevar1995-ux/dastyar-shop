<?php
/**
 * REST API مرکزی — namespace: dastyar/v1
 *
 * احراز هویت: هدر X-Dastyar-Key (کلید مخصوص هر فروشنده)
 * ثبت سایت فروشنده: هدر X-Dastyar-Site (آدرس سایت فروشنده، برای Push)
 * همه درخواست‌ها در جدول dastyar_api_logs لاگ می‌شوند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Rest {

	const NS = 'dastyar/v1';

	/** @var int شناسه فروشنده احرازشده برای درخواست جاری */
	public static $vendor_id = 0;

	public static function register_routes() {
		$auth = array( __CLASS__, 'auth' );

		register_rest_route( self::NS, '/ping', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'ping' ),
			'permission_callback' => $auth,
		) );

		// v1.11.2 (درخواست کاربر) — مانیفست به‌روزرسانی افزونه کانکتور:
		// سایت فروشنده از این مسیر آخرین نسخه + برچسب اولویت (عادی/مهم/ضروری/فوری) + لینک فایل را می‌گیرد
		// تا به‌روزرسانی را داخل پیشخوان خودش نمایش دهد و با یک کلیک انجام دهد.
		register_rest_route( self::NS, '/connector-update', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'connector_update' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/products', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'products' ),
			'permission_callback' => $auth,
		) );

		// دسته‌بندی‌های محصول (v1.6.0 — برای فیلتر دسته در صفحه «محصولات دستیار» فروشنده)
		register_rest_route( self::NS, '/categories', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'categories' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/products/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'product' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/orders', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'create_order' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/orders/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_order' ),
			'permission_callback' => $auth,
		) );

		// v1.10.0 — سینک لغو سفارش از سایت فروشنده به مرکز (درخواست کاربر)
		register_rest_route( self::NS, '/orders/(?P<id>\d+)/status', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'update_order_status' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/orders/(?P<id>\d+)/refund-request', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'refund_request' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/wallet', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'wallet' ),
			'permission_callback' => $auth,
		) );

		// ساخت سفارش شارژ کیف پول از پیشخوان سایت فروشنده و برگرداندن لینک پرداخت مرکز (v1.7.0)
		register_rest_route( self::NS, '/wallet/charge', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'wallet_charge' ),
			'permission_callback' => $auth,
		) );

		// --- سیستم مرجوعی (RMA) ---
		register_rest_route( self::NS, '/rma', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rma_create' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rma_list' ),
				'permission_callback' => $auth,
			),
		) );

		register_rest_route( self::NS, '/rma/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rma_get' ),
			'permission_callback' => $auth,
		) );

		// --- قوانین عودت (متن مرکزی که روی برگه مرجوعی فروشنده‌ها نمایش داده می‌شود) ---
		register_rest_route( self::NS, '/rules', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rules' ),
			'permission_callback' => $auth,
		) );

		// --- سینک سبک موجودی (بدون دانلود مجدد کل محصول) ---
		register_rest_route( self::NS, '/stock', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'stock' ),
			'permission_callback' => $auth,
		) );

		// --- (v1.7.4 — بازخورد کاربر، مورد ۲) هشدارهای افزایش قیمت تامین برای نمایش در پیشخوان خود فروشنده ---
		register_rest_route( self::NS, '/pricelog', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'pricelog' ),
			'permission_callback' => $auth,
		) );

		// --- (v1.7.4 — بازخورد کاربر، مورد ۹) ثبت/مشاهده تیکت پشتیبانی از داخل پیشخوان سایت فروشنده ---
		register_rest_route( self::NS, '/tickets', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'tickets_list' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ticket_create' ),
				'permission_callback' => $auth,
			),
		) );
	}

	/**
	 * احراز هویت فروشنده بر اساس API Key + به‌روزرسانی خودکار آدرس سایت فروشنده.
	 */
	public static function auth( WP_REST_Request $request ) {
		$key = (string) $request->get_header( 'x-dastyar-key' );

		if ( '' === $key ) {
			Dastyar::instance()->logger->log_request( 0, $request->get_route(), $request->get_method(), 401, 'API Key ارسال نشده است' );
			return new WP_Error( 'dastyar_no_key', 'هدر X-Dastyar-Key ارسال نشده است.', array( 'status' => 401 ) );
		}

		$vendor_id = Dastyar::instance()->vendors->vendor_by_key( $key );

		if ( ! $vendor_id ) {
			Dastyar::instance()->logger->log_request( 0, $request->get_route(), $request->get_method(), 403, 'API Key نامعتبر' );
			return new WP_Error( 'dastyar_bad_key', 'API Key نامعتبر است.', array( 'status' => 403 ) );
		}

		self::$vendor_id = $vendor_id;

		// ثبت/به‌روزرسانی خودکار آدرس سایت فروشنده (برای ارسال وب‌هوک)
		$site = (string) $request->get_header( 'x-dastyar-site' );
		if ( $site ) {
			$current = Dastyar::instance()->vendors->site_url( $vendor_id );
			if ( untrailingslashit( esc_url_raw( $site ) ) !== $current ) {
				Dastyar::instance()->vendors->set_site_url( $vendor_id, $site );
			}
		}

		Dastyar::instance()->logger->log_request( $vendor_id, $request->get_route(), $request->get_method(), 200 );
		return true;
	}

	public static function ping( WP_REST_Request $request ) {
		$user = get_userdata( self::$vendor_id );
		return rest_ensure_response( array(
			'ok'          => true,
			'vendor_id'   => self::$vendor_id,
			'vendor_name' => $user ? $user->display_name : '',
			'site_url'    => Dastyar::instance()->vendors->site_url( self::$vendor_id ),
			'shop_type'   => (string) get_user_meta( self::$vendor_id, '_dastyar_shop_type', true ), // v1.7.4 — مورد ۱۰
			'time'        => current_time( 'c' ),
		) );
	}

	/** برچسب‌های مجاز اولویت به‌روزرسانی کانکتور (v1.11.2) */
	public static function update_labels() {
		return array(
			'normal'    => 'عادی',
			'important' => 'مهم',
			'critical'  => 'ضروری',
			'urgent'    => 'فوری',
		);
	}

	/** مانیفست به‌روزرسانی کانکتور که مدیر مرکز در «وضعیت و تنظیمات» تعریف کرده است (v1.11.2) */
	public static function update_manifest() {
		$m      = (array) get_option( 'dastyar_ctr_update', array() );
		$labels = self::update_labels();
		$label  = (string) ( $m['label'] ?? 'normal' );
		if ( ! isset( $labels[ $label ] ) ) {
			$label = 'normal';
		}
		return array(
			'version'     => (string) ( $m['version'] ?? '' ),
			'package'     => (string) ( $m['package'] ?? '' ),
			'label'       => $label,
			'label_title' => $labels[ $label ],
			'message'     => (string) ( $m['message'] ?? '' ),
			'changelog'   => (string) ( $m['changelog'] ?? '' ),
			'released'    => (string) ( $m['released'] ?? '' ),
		);
	}

	/** GET /connector-update — پاسخ به سایت فروشنده برای نمایش اعلان به‌روزرسانی در پیشخوانش (v1.11.2) */
	public static function connector_update() {
		return rest_ensure_response( array_merge( array( 'ok' => true, 'time' => current_time( 'c' ) ), self::update_manifest() ) );
	}

	/** (v1.7.4 — مورد ۲) هشدارهای اخیر افزایش قیمت تامین — خروجی برای تب پیشخوان سایت فروشنده */
	public static function pricelog() {
		$items = array();
		foreach ( (array) Dastyar_Pricelog::latest( 30 ) as $e ) {
			$items[] = array(
				'product_id'   => (int) ( $e['product_id'] ?? 0 ),
				'product_name' => (string) ( $e['product_name'] ?? '' ),
				'old'          => (float) ( $e['old'] ?? 0 ),
				'new'          => (float) ( $e['new'] ?? 0 ),
				'percent'      => (float) ( $e['percent'] ?? 0 ),
				'time'         => (string) ( $e['time'] ?? '' ),
				'time_fa'      => Dastyar_Jalali::format( 'Y/m/d - H:i', (string) ( $e['time'] ?? '' ) ), // v1.10.8 — شمسی
			);
		}
		return rest_ensure_response( array( 'data' => $items ) );
	}

	/** (v1.7.4 — مورد ۹) لیست تیکت‌های خود فروشنده (۲۰ مورد اخیر) */
	public static function tickets_list() {
		$out    = array();
		$status = array( 'open' => 'باز', 'answered' => 'پاسخ داده شده', 'closed' => 'بسته شده' );
		foreach ( (array) get_posts( array(
			'post_type'      => Dastyar_Tickets::POST_TYPE,
			'author'         => self::$vendor_id,
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) ) as $t ) {
			$st  = '';
			$tax = function_exists( 'wp_get_post_terms' ) ? wp_get_post_terms( $t->ID, Dastyar_Tickets::TAX, array( 'fields' => 'slugs' ) ) : array();
			if ( is_array( $tax ) && $tax ) {
				$st = (string) $tax[0];
			}
			$replies = array();
			foreach ( (array) get_comments( array( 'post_id' => $t->ID, 'number' => 10, 'order' => 'ASC' ) ) as $c ) {
				$replies[] = array(
					'author' => (int) $c->user_id === self::$vendor_id ? 'شما' : 'پشتیبانی',
					'body'   => (string) $c->comment_content,
					'date'   => (string) $c->comment_date,
				);
			}
			foreach ( $replies as $ri => $rep ) {
				$replies[ $ri ]['date_fa'] = Dastyar_Jalali::format( 'Y/m/d - H:i', (string) ( $rep['date'] ?? '' ) );
			}
			$out[] = array(
				'id'      => (int) $t->ID,
				'subject' => (string) $t->post_title,
				'body'    => (string) $t->post_content,
				'status'  => $st,
				'status_label' => $status[ $st ] ?? $st,
				'date'    => (string) $t->post_date,
				'date_fa' => Dastyar_Jalali::format( 'Y/m/d - H:i', (string) $t->post_date ), // v1.10.8 — شمسی
				'replies' => $replies,
			);
		}
		return rest_ensure_response( array( 'data' => $out ) );
	}

	/** (v1.7.4 — مورد ۹) ثبت تیکت جدید از پیشخوان سایت فروشنده */
	public static function ticket_create( WP_REST_Request $request ) {
		$p       = (array) $request->get_json_params();
		$subject = sanitize_text_field( wp_unslash( (string) ( $p['subject'] ?? '' ) ) );
		$message = sanitize_textarea_field( wp_unslash( (string) ( $p['message'] ?? '' ) ) );
		if ( '' === $subject || '' === $message ) {
			return new WP_Error( 'dastyar_ticket_invalid', 'عنوان و متن تیکت الزامی است.', array( 'status' => 400 ) );
		}
		$id = Dastyar::instance()->tickets->create( self::$vendor_id, $subject, $message );
		if ( ! $id ) {
			return new WP_Error( 'dastyar_ticket_fail', 'ثبت تیکت انجام نشد؛ دوباره تلاش کنید.', array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'id' => (int) $id, 'ok' => true ) );
	}

	/**
	 * لیست محصولات WooCommerce سایت مرکزی — منبع اصلی صفحه «محصولات دستیار» در سایت فروشنده.
	 * پارامترها: page, per_page, modified_after (unix), search, category (slug), order (asc|desc)
	 * نکته: قیمت اصلی فروشگاه هرگز ارسال نمی‌شود؛ فقط supplier_price.
	 */
	public static function products( WP_REST_Request $request ) {
		$page    = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per     = (int) $request->get_param( 'per_page' ) ?: 50;
		$per     = max( 1, min( 100, $per ) );
		$after   = (int) $request->get_param( 'modified_after' );
		$search  = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$cat     = sanitize_text_field( (string) $request->get_param( 'category' ) );
		$stock   = sanitize_key( (string) $request->get_param( 'stock_status' ) ); // v1.6.0
		$order   = strtolower( (string) $request->get_param( 'order' ) ) === 'asc' ? 'ASC' : 'DESC';

		$args = array(
			'status'   => $after ? 'any' : 'publish',
			'paginate' => true,
			'page'     => $page,
			'limit'    => $per,
			'orderby'  => 'modified',
			'order'    => $order,
			'return'   => 'objects',
		);
		if ( $after ) {
			$args['date_modified'] = '>' . $after;
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( '' !== $cat ) {
			$args['category'] = array( $cat );
		}
		// فیلتر موجود/ناموجود (v1.6.0)
		if ( in_array( $stock, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
			$args['stock_status'] = $stock;
		}

		// رده‌بندی فروشندگان: محدودسازی فهرست به محصولات مجاز رده (v1.5.0)
		$allowed = Dastyar_Tiers::allowed_ids( self::$vendor_id );
		if ( null !== $allowed ) {
			$args['include'] = $allowed;
		}

		$fetch = static function ( array $q ) {
			$q['paginate'] = true;
			$q['return']   = 'objects';
			$r = wc_get_products( $q );
			if ( is_object( $r ) ) {
				return array( (array) $r->products, (int) $r->total );
			}
			$r = (array) $r;
			return array( $r, count( $r ) );
		};

		/*
		 * (v1.7.6 — بازخورد کاربر) مرتب‌سازی سراسری «اول محصولات موجود، آخر لیست ناموجودها»:
		 * لیست دوگروهی [موجود‌ها → ناموجودها] سراسری ساخته می‌شود تا صفحه اول واقعا با محصولات موجود
		 * شروع شود (نه فقط داخل همان صفحه). فقط وقتی فروشنده فیلتر موجودی صریح نزده و جریان همگام‌سازی
		 * زمانی (modified_after) نیست — آن دو مسیر رفتار قبلی تک‌کوئری را حفظ می‌کنند.
		 */
		if ( '' === $stock && ! $after ) {
			$g_off            = ( $page - 1 ) * $per;
			$in_args          = $args;
			$in_args['stock_status'] = 'instock';
			unset( $in_args['page'] );
			$in_args['offset'] = $g_off;
			list( $in_all, $in_total ) = $fetch( $in_args );
			// دفاع: حتی اگر لایه کوئری فیلتر موجودی را نادیده بگیرد، ناموجود این‌جا می‌ماند بیرون
			$in_all  = array_values( array_filter( $in_all, function ( $p ) {
				return 'outofstock' !== (string) $p->get_stock_status();
			} ) );
			$objects   = array_slice( $in_all, 0, $per );
			$needed    = $per - count( $objects );
			$out_total = 0;
			if ( $needed > 0 ) {
				$out_args          = $args;
				$out_args['stock_status'] = 'outofstock';
				unset( $out_args['page'] );
				$out_args['offset'] = max( 0, $g_off - $in_total );
				$out_args['limit']  = $per;
				list( $out_all, $out_total ) = $fetch( $out_args );
				$out_all = array_values( array_filter( $out_all, function ( $p ) {
					return 'outofstock' === (string) $p->get_stock_status();
				} ) );
				$objects = array_merge( $objects, array_slice( $out_all, 0, $needed ) );
			} else {
				// فقط مجموع ناموجودها لازم است تا total/صفحه‌بندی فروشنده دقیق بماند (کوئری سبک ۱تایی)
				$cnt_args          = $args;
				$cnt_args['stock_status'] = 'outofstock';
				unset( $cnt_args['page'] );
				$cnt_args['offset'] = 0;
				$cnt_args['limit']  = 1;
				list( , $out_total ) = $fetch( $cnt_args );
			}
			$total = $in_total + $out_total;
		} else {
			$result  = wc_get_products( $args );
			$objects = is_object( $result ) ? $result->products : (array) $result;
			$total   = is_object( $result ) ? (int) $result->total : count( $objects );
		}

		$data = array();
		foreach ( $objects as $product ) {
			if ( 'trash' === $product->get_status() ) {
				continue;
			}
			if ( null !== $allowed && ! in_array( (int) $product->get_id(), $allowed, true ) ) {
				continue; // خارج از رده فروشنده
			}
			$data[] = Dastyar_Product_Export::format( $product );
		}

		return rest_ensure_response( array(
			'data'     => $data,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per,
		) );
	}

	/** دسته‌بندی‌های دارای محصول (v1.6.0) — برای فیلتر دسته سمت فروشنده */
	public static function categories() {
		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 300,
		) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}
		$data = array();
		foreach ( $terms as $t ) {
			$data[] = array(
				'id'    => (int) $t->term_id,
				'name'  => (string) $t->name,
				'slug'  => (string) $t->slug,
				'count' => (int) $t->count,
			);
		}
		return rest_ensure_response( array( 'data' => $data, 'total' => count( $data ) ) );
	}

	public static function product( WP_REST_Request $request ) {
		$product = wc_get_product( (int) $request['id'] );
		if ( ! $product || 'product' !== get_post_type( $product->get_id() ) ) {
			return new WP_Error( 'dastyar_not_found', 'محصول یافت نشد.', array( 'status' => 404 ) );
		}
		// رده‌بندی فروشندگان: محصول خارج از رده ⇒ ناموجود (v1.5.0)
		if ( ! Dastyar_Tiers::can_sync( self::$vendor_id, (int) $product->get_id() ) ) {
			return new WP_Error( 'dastyar_not_found', 'محصول یافت نشد.', array( 'status' => 404 ) );
		}
		// ثبت سابقه «افزوده‌شده به فروشگاه» — دریافت محصول از API همان import از پنل فروشنده است (v1.5.1)
		Dastyar_Shop_Page::mark_imported( self::$vendor_id, (int) $product->get_id() );
		return rest_ensure_response( Dastyar_Product_Export::format( $product ) );
	}

	/** دریافت سفارش از سایت فروشنده و ساخت Order استاندارد WooCommerce */
	public static function create_order( WP_REST_Request $request ) {
		try {
			$result = Dastyar_Order_Import::create( (array) $request->get_json_params(), self::$vendor_id );
		} catch ( Throwable $e ) {
			Dastyar::instance()->logger->log_request( self::$vendor_id, $request->get_route(), 'POST', 500, $e->getMessage() );
			return new WP_Error( 'dastyar_order_error', 'خطا در ثبت سفارش: ' . $e->getMessage(), array( 'status' => 500 ) );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/** وضعیت سفارش (برای reconcile و برگشت کد رهگیری در حالت Pull) */
	public static function get_order( WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request['id'] );
		if ( ! $order || (int) $order->get_meta( '_dastyar_vendor_id' ) !== self::$vendor_id ) {
			return new WP_Error( 'dastyar_not_found', 'سفارش یافت نشد.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array(
			'order_id'      => $order->get_id(),
			'number'        => $order->get_order_number(),
			'status'        => $order->get_status(),
			'total'         => (float) $order->get_total(),
			'shipping_total'=> (float) $order->get_shipping_total(),
			'tracking_code' => (string) $order->get_meta( '_dastyar_tracking_code' ),
			'carrier'       => (string) $order->get_meta( '_dastyar_tracking_carrier' ),
			'modified'      => $order->get_date_modified() ? $order->get_date_modified()->date( 'c' ) : '',
		) );
	}

	/**
	 * v1.10.0 — سینک لغو سفارش از سایت فروشنده به مرکز (درخواست کاربر):
	 * وقتی سفارش «در حال انجام» در سایت فروشنده لغو می‌شود، کانکتور این متد را صدا می‌زند
	 * تا سفارش متناظر در مرکز هم «لغو شده» شود. فقط «cancelled» پذیرفته می‌شود؛
	 * سایر وضعیت‌ها مثل قبل فقط از سمت مرکز مدیریت می‌شوند.
	 */
	public static function update_order_status( WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request['id'] );
		if ( ! $order || (int) $order->get_meta( '_dastyar_vendor_id' ) !== self::$vendor_id ) {
			return new WP_Error( 'dastyar_not_found', 'سفارش یافت نشد.', array( 'status' => 404 ) );
		}
		$body    = (array) $request->get_json_params();
		$status  = sanitize_key( (string) ( $body['status'] ?? '' ) );
		$allowed = (array) apply_filters( 'dastyar_vendor_push_statuses', array( 'cancelled' ) );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'dastyar_bad_status', 'این تغییر وضعیت از سایت فروشنده پذیرفته نیست؛ فقط «لغو سفارش» همگام می‌شود.', array( 'status' => 400 ) );
		}
		if ( $order->has_status( $status ) ) {
			return rest_ensure_response( array( 'ok' => true, 'changed' => false, 'status' => $status, 'order_id' => $order->get_id() ) );
		}
		/**
		 * v1.10.8 — قانون جدید کاربر: لغو از سمت فروشنده فقط تا وقتی سفارش مرکز هنوز قابل مدیریت است
		 * («در حال انجام»/«در انتظار پرداخت»/«در انتظار بررسی»). به‌محض «تحویل پست شدن» یا هر وضعیت
		 * دیگری لغو رد می‌شود و پیام شفاف همان وضعیت به فروشنده نمایش داده می‌شود.
		 */
		$cancellable = (array) apply_filters( 'dastyar_vendor_cancel_statuses', array( 'pending', 'processing', 'on-hold' ) );
		if ( ! $order->has_status( $cancellable ) ) {
			// نقشه داخلی — حتی اگر نام وضعیت در ووکامرس ترجمه نشده باشد، برچسب فارسی شفاف می‌سازیم
			$map   = array(
				'posted'     => 'تحویل پست شده (ارسال شده به پست)',
				'completed'  => 'تکمیل شده',
				'cancelled'  => 'لغو شده',
				'refunded'   => 'مرجوع شده',
				'failed'     => 'ناموفق',
				'on-hold'    => 'در انتظار بررسی',
				'processing' => 'در حال انجام',
				'pending'    => 'در انتظار پرداخت',
			);
			// تشخیص وضعیت فعلی از روی خود سفارش
			$cur = '';
			foreach ( array_keys( $map ) as $k ) {
				if ( $order->has_status( $k ) ) {
					$cur = $k;
					break;
				}
			}
			if ( '' === $cur ) {
				$cur = (string) $order->get_status();
			}
			$label = $map[ $cur ] ?? ( function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( 'wc-' . $cur ) : $cur );
			if ( '' === (string) $label || $label === 'wc-' . $cur ) {
				$label = $cur;
			}
			return new WP_Error(
				'dastyar_locked',
				sprintf( 'این سفارش در مرکز در وضعیت «%s» است و امکان لغو آن وجود ندارد؛ برای پیگیری از بخش تیکت‌ها با پشتیبانی در تماس باشید.', $label ),
				array( 'status' => 409 )
			);
		}
		$reason = sanitize_text_field( (string) ( $body['reason'] ?? '' ) );
		$note   = 'سفارش از سایت فروشنده لغو شد.' . ( '' !== $reason ? ' دلیل فروشنده: ' . $reason : '' );
		$order->update_status( $status, $note );
		Dastyar::instance()->logger->log_request( self::$vendor_id, $request->get_route(), 'POST', 200, 'سینک لغو از فروشنده ← سفارش #' . $order->get_order_number() );
		return rest_ensure_response( array( 'ok' => true, 'changed' => true, 'status' => $status, 'order_id' => $order->get_id() ) );
	}

	/** ثبت درخواست مرجوعی فروشنده — مدیریت و Refund واقعی با WooCommerce Refund در مرکز انجام می‌شود */
	public static function refund_request( WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request['id'] );
		if ( ! $order || (int) $order->get_meta( '_dastyar_vendor_id' ) !== self::$vendor_id ) {
			return new WP_Error( 'dastyar_not_found', 'سفارش یافت نشد.', array( 'status' => 404 ) );
		}
		$body   = (array) $request->get_json_params();
		$amount = (float) ( $body['amount'] ?? 0 );
		$reason = sanitize_textarea_field( (string) ( $body['reason'] ?? '' ) );

		$requests   = (array) $order->get_meta( '_dastyar_refund_requests' );
		$requests[] = array(
			'amount' => $amount,
			'reason' => $reason,
			'status' => 'pending',
			'at'     => current_time( 'mysql' ),
		);
		$order->update_meta_data( '_dastyar_refund_requests', $requests );
		$order->add_order_note( sprintf( 'درخواست مرجوعی جدید از فروشگاه فروشنده — مبلغ: %s — دلیل: %s', wc_price( $amount ), $reason ) );
		$order->save();

		wp_mail(
			get_option( 'admin_email' ),
			'درخواست مرجوعی جدید در دستیار شاپ',
			sprintf( "سفارش #%s\nمبلغ: %s\nدلیل: %s", $order->get_order_number(), $amount, $reason )
		);

		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function wallet( WP_REST_Request $request ) {
		$wallet = Dastyar::instance()->wallet;
		$txns   = (array) $wallet->get_transactions( self::$vendor_id, 50 );
		// v1.10.8 — تاریخ شمسی برای نمایش در پیشخوان فروشنده (فیلد date_fa؛ created_at خام هم حفظ می‌شود)
		foreach ( $txns as $tx ) {
			if ( is_object( $tx ) && isset( $tx->created_at ) ) {
				$tx->date_fa = Dastyar_Jalali::format( 'Y/m/d - H:i', (string) $tx->created_at );
			}
		}
		return rest_ensure_response( array(
			'balance'      => $wallet->get_balance( self::$vendor_id ),
			'transactions' => $txns,
		) );
	}

	/**
	 * ساخت سفارش «شارژ کیف پول» برای فروشنده احرازشده + برگرداندن لینک پرداخت مرکز (v1.7.0).
	 * فروشنده از پیشخوان وردپرس خودش این مسیر را صدا می‌زند و مرورگرش به صفحه پرداخت مرکز هدایت می‌شود؛
	 * پس از پرداخت موفق، همان هوک موجود (credit_paid_charge) کیف پول را خودکار شارژ می‌کند.
	 */
	public static function wallet_charge( WP_REST_Request $request ) {
		$amount = (float) $request->get_param( 'amount' );
		$order  = Dastyar_Payments::create_charge_order( self::$vendor_id, $amount );
		if ( is_wp_error( $order ) ) {
			Dastyar::instance()->logger->log_request( self::$vendor_id, $request->get_route(), 'POST', 400, 'wallet_charge: ' . $order->get_error_message() );
			return new WP_Error( 'dastyar_charge_failed', $order->get_error_message(), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array(
			'ok'          => true,
			'order_id'    => (int) $order->get_id(),
			'payment_url' => (string) $order->get_checkout_payment_url(),
		) );
	}

	/* ------------------------------------------------------------------
	 * RMA — ارسال درخواست مرجوعی / دریافت وضعیت‌ها (همگام‌سازی Pull)
	 * ---------------------------------------------------------------- */

	/** ارسال/ایجاد درخواست مرجوعی از سایت فروشنده */
	public static function rma_create( WP_REST_Request $request ) {
		try {
			$result = Dastyar_Rma::create_from_api( (array) $request->get_json_params(), self::$vendor_id );
		} catch ( Throwable $e ) {
			Dastyar::instance()->logger->log_request( self::$vendor_id, $request->get_route(), 'POST', 500, $e->getMessage() );
			return new WP_Error( 'dastyar_rma_error', 'خطا در ثبت مرجوعی: ' . $e->getMessage(), array( 'status' => 500 ) );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/** لیست درخواست‌های مرجوعی همین فروشنده (وضعیت + توضیح اپراتور + نتیجه) */
	public static function rma_list( WP_REST_Request $request ) {
		$limit = max( 1, min( 100, (int) $request->get_param( 'limit' ) ?: 50 ) );
		$out   = array();
		foreach ( get_posts( array(
			'post_type'      => Dastyar_Rma::POST_TYPE,
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => Dastyar_Rma::M_VENDOR, 'value' => self::$vendor_id ) ),
		) ) as $rma_id ) {
			$out[] = Dastyar_Rma::api_payload( (int) $rma_id );
		}
		return rest_ensure_response( array( 'data' => $out ) );
	}

	/** قوانین عودت و آدرس انبار مرجوعی — تعیین‌شده توسط مدیر مرکز، برای نمایش در سایت فروشنده */
	public static function rules( WP_REST_Request $request ) {
		return rest_ensure_response( array(
			'rules'          => (string) get_option( 'dastyar_rma_rules', '' ),
			'return_address' => (string) get_option( 'dastyar_return_address', '' ),
		) );
	}

	/**
	 * موجودی سبک محصولات — برای سینک دوره‌ای سریع سمت فروشنده.
	 * پارامترها: ids (لیست CSV شناسه‌های مرکزی — اختیاری), page, per_page
	 */
	public static function stock( WP_REST_Request $request ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'ids' ) ) ) );

		// رده‌بندی فروشندگان: فقط محصولات مجاز رده در پاسخ موجودی (v1.5.0)
		$allowed = Dastyar_Tiers::allowed_ids( self::$vendor_id );
		if ( null !== $allowed ) {
			$ids = Dastyar_Tiers::filter_ids( self::$vendor_id, $ids );
		}

		if ( $ids ) {
			$products = array();
			foreach ( array_slice( $ids, 0, 300 ) as $pid ) {
				$p = wc_get_product( $pid );
				if ( $p && 'product' === get_post_type( $pid ) ) {
					$products[] = $p;
				}
			}
		} else {
			$page = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
			$per  = max( 1, min( 200, (int) $request->get_param( 'per_page' ) ?: 100 ) );
			$args = array(
				'status' => 'publish',
				'limit'  => $per,
				'page'   => $page,
				'return' => 'objects',
			);
			if ( null !== $allowed ) {
				$args['include'] = $allowed;
			}
			$products = wc_get_products( $args );
		}

		$data = array();
		foreach ( (array) $products as $product ) {
			$payload = Dastyar_Product_Export::stock_payload( $product );
			if ( $payload ) {
				$data[] = $payload;
			}
		}
		return rest_ensure_response( array( 'data' => $data ) );
	}

	/** دریافت وضعیت یک درخواست مرجوعی (مالکیت = فروشنده احرازشده) */
	public static function rma_get( WP_REST_Request $request ) {
		$rma_id = (int) $request['id'];
		if ( ! $rma_id || Dastyar_Rma::POST_TYPE !== get_post_type( $rma_id )
			|| (int) get_post_meta( $rma_id, Dastyar_Rma::M_VENDOR, true ) !== self::$vendor_id ) {
			return new WP_Error( 'dastyar_not_found', 'درخواست یافت نشد.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( Dastyar_Rma::api_payload( $rma_id ) );
	}
}
