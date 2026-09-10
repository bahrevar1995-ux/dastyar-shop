<?php
/**
 * وضعیت سفارش سفارشی «تحویل پست شده» (v1.10.4 — درخواست کاربر)
 *
 * مرحله‌ای بین «در حال انجام» و «تکمیل شده»: وقتی بسته به پست تحویل داده شد ولی
 * هنوز به دست مشتری نهایی نرسیده، مدیر وضعیت را «تحویل پست شده» می‌کند.
 * - روی همان الگوی وضعیت سفارشی «ارسال شده به دستیارشاپ» سمت فروشنده (Connector) ساخته شد
 *   (چرخ دوباره اختراع نشد — اتولودر Dastyar_Statuses ← class-dastyar-statuses.php).
 * - در timeline پنل (Dastyar_Growth) به مرحله ۳ «تحویل به پست» نگاشت می‌شود.
 * - تلفیق با رفتار قبلی: کد رهگیری هم همچنان مرحله ۳ را روشن می‌کند.
 * - v1.10.5: اقدام دسته‌جمعی «تغییر وضعیت به تحویل پست شده» در لیست سفارش‌ها (هر دو لیست
 *   HPOS و کلاسیک — پردازش با هندلر بومی ووکامرس برای mark_<status> روی وضعیت‌های ثبت‌شده
 *   انجام می‌شود؛ ما فقط گزینه را به دراپ‌داون اضافه می‌کنیم — چرخ دوباره اختراع نشد) و
 *   نمایش زرد وضعیت در لیست سفارش‌های پیشخوان (قرص وضعیت با پس‌زمینه زرد و متن تیره).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dastyar_Statuses {

	const POSTED = 'posted';                 // ← wc-posted
	const LABEL  = 'تحویل پست شده';

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add' ) );
		// هم معنای «پرداخت‌شده» می‌دهد (دسترسی دانلود/قابلیت‌ها حفظ شود) هم به گزارش‌های فروش اضافه می‌کند
		add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'paid' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( $this, 'reports' ) );

		// v1.10.5 — اقدام دسته‌جمعی «تغییر وضعیت به تحویل پست شده» در لیست سفارش‌ها (HPOS + کلاسیک)
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'bulk_action' ), 20 );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'bulk_action' ), 20 );

		// v1.10.5 — قرص زرد وضعیت در لیست سفارش‌های پیشخوان
		add_action( 'admin_head', array( $this, 'list_css' ) );
	}

	/** کلید کامل وضعیت با پیشوند wc- */
	public static function slug() {
		return 'wc-' . self::POSTED;
	}

	/** ثبت وضعیت (init) */
	public static function register() {
		register_post_status( self::slug(), array(
			'label'                     => self::LABEL,
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: شمارش سفارش‌ها */
			'label_count'               => _n_noop( self::LABEL . ' <span class="count">(%s)</span>', self::LABEL . ' <span class="count">(%s)</span>', 'dastyar-core' ),
		) );
	}

	/** درج در دراپ‌داون وضعیت‌ها، بلافاصله بعد از «در حال انجام» */
	public static function add( $statuses ) {
		$new = array();
		foreach ( (array) $statuses as $slug => $label ) {
			$new[ $slug ] = $label;
			if ( 'wc-processing' === $slug ) {
				$new[ self::slug() ] = self::LABEL;
			}
		}
		if ( ! isset( $new[ self::slug() ] ) ) {
			$new[ self::slug() ] = self::LABEL; // فالبک: دیده نشد ← ته لیست
		}
		return $new;
	}

	/** این وضعیت «پرداخت‌شده» محسوب می‌شود */
	public static function paid( $statuses ) {
		if ( ! in_array( self::POSTED, (array) $statuses, true ) ) {
			$statuses[] = self::POSTED;
		}
		return $statuses;
	}

	/** در گزارش‌های فروش ووکامرس هم لحاظ شود */
	public static function reports( $statuses ) {
		if ( ! in_array( self::POSTED, (array) $statuses, true ) ) {
			$statuses[] = self::POSTED;
		}
		return $statuses;
	}

	/* ------------------------------------------------------------------
	 * v1.10.5 — اقدام دسته‌جمعی + استایل زرد در لیست سفارش‌ها
	 * ---------------------------------------------------------------- */

	/**
	 * گزینه «تغییر وضعیت به تحویل پست شده» در دراپ‌داون اقدامات دسته‌جمعی لیست سفارش‌ها.
	 * پردازش را خود ووکامرس انجام می‌دهد: هر اکشن mark_<status> برای وضعیت ثبت‌شده
	 * (wc-posted از فیلتر wc_order_statuses خودمان می‌آید) در هر دو لیست HPOS و کلاسیک
	 * به update_status بومی می‌رسد — فقط گزینه دسته‌جمعی را اضافه می‌کنیم.
	 */
	public static function bulk_action( $actions ) {
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return $actions;
		}
		$label = sprintf( 'تغییر وضعیت به «%s»', self::LABEL );
		$key   = 'mark_' . self::POSTED;
		$new   = array();
		$done  = false;
		foreach ( (array) $actions as $k => $v ) {
			if ( 'trash' === $k && ! $done ) {
				$new[ $key ] = $label; // قبل از «حذف»
				$done        = true;
			}
			$new[ $k ] = $v;
		}
		if ( ! $done ) {
			$new[ $key ] = $label;
		}
		return $new;
	}

	/** رنگ زرد عنوان وضعیت «تحویل پست شده» در لیست سفارش‌های پیشخوان (قرص زرد با متن تیره) */
	public function list_css() {
		$scr = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id  = ( $scr && isset( $scr->id ) ) ? (string) $scr->id : '';
		if ( ! in_array( $id, array( 'woocommerce_page_wc-orders', 'edit-shop_order', 'shop_order' ), true ) ) {
			return; // فقط صفحات لیست سفارش‌ها
		}
		echo '<style>.order-status.status-posted{background:#ffe9a8 !important;color:#6b4e00 !important;border-bottom-color:#f0d060 !important}.column-order_status .status-posted{background:#ffe9a8 !important;color:#6b4e00 !important}</style>';
	}
}
