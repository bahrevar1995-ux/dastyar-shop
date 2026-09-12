<?php
/**
 * پنل فروشنده روی WooCommerce My Account سایت فروشنده — بدون وردپرس-پنل جداگانه.
 * Endpoint ها فقط برای مدیر فروشگاه (capability: manage_woocommerce) نمایش داده می‌شوند،
 * بنابراین مشتریان عادی فروشگاه آن‌ها را نمی‌بینند.
 *
 *  - dastyarc-products   → محصولات دستیار
 *  - dastyarc-pricing    → تنظیمات قیمت‌گذاری
 *  - dastyarc-sent       → سفارشات منتقل شده
 *  - dastyarc-connection → اتصال API
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_MyAccount {

	public static function endpoints() {
		return array(
			'dastyarc-products'   => 'محصولات دستیار',
			'dastyarc-pricing'    => 'تنظیمات قیمت‌گذاری',
			'dastyarc-sent'       => 'سفارشات منتقل شده',
			'dastyarc-connection' => 'اتصال API',
		);
	}

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'add_endpoints' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ) );
		foreach ( array_keys( self::endpoints() ) as $endpoint ) {
			add_action( 'woocommerce_account_' . $endpoint . '_endpoint', array( $this, 'render_' . str_replace( 'dastyarc-', '', $endpoint ) ) );
		}
	}

	public static function add_endpoints() {
		foreach ( array_keys( self::endpoints() ) as $endpoint ) {
			add_rewrite_endpoint( $endpoint, EP_ROOT | EP_PAGES );
		}
	}

	/** فقط مدیر فروشگاه */
	public function menu_items( $items ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $items;
		}
		$logout = $items['customer-logout'] ?? null;
		unset( $items['customer-logout'] );
		foreach ( self::endpoints() as $key => $label ) {
			$items[ $key ] = $label;
		}
		if ( $logout ) {
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	/* ------------------------------------------------------------------ */

	/** محصولات دستیار — لینک سریع + خلاصه وضعیت (مدیریت کامل در پیشخوان) */
	public function render_products() {
		$synced = (int) wp_count_posts( 'product' )->publish;
		$count  = count( (array) get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_dastyar_remote_id',
		) ) );

		echo '<h3>محصولات دستیار</h3>';
		printf( '<p>تاکنون <strong>%d</strong> محصول از دستیار شاپ به فروشگاه شما اضافه شده است.</p>', $count );
		printf(
			'<p><a class="button alt" href="%s">مشاهده لیست کامل محصولات مرکز و افزودن محصول جدید</a></p>',
			esc_url( admin_url( 'admin.php?page=dastyarc-hub&tab=products' ) )
		);
		echo '<p class="description">نکته: محصولات واردشده ابتدا «پیش‌نویس» می‌شوند و پس از بررسی شما منتشر خواهند شد.</p>';
	}

	/** تنظیمات قیمت‌گذاری — همان تنظیمات صفحه پیشخوان، در My Account */
	public function render_pricing() {
		echo '<h3>تنظیمات قیمت‌گذاری</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dastyarc_save_settings' );
		echo '<input type="hidden" name="action" value="dastyarc_save_settings">';
		// فیلدهای اتصال را از مقادیر فعلی حفظ می‌کنیم
		printf( '<input type="hidden" name="dastyarc_central_url" value="%s">', esc_attr( get_option( 'dastyarc_central_url' ) ) );
		printf( '<input type="hidden" name="dastyarc_api_key" value="%s">', esc_attr( get_option( 'dastyarc_api_key' ) ) );

		$mode = get_option( 'dastyarc_price_mode', 'percent' );
		echo '<p><label><input type="radio" name="dastyarc_price_mode" value="percent" ' . checked( $mode, 'percent', false ) . '> افزایش درصدی روی قیمت تامین</label><br>';
		echo '<label><input type="radio" name="dastyarc_price_mode" value="fixed" ' . checked( $mode, 'fixed', false ) . '> افزایش مبلغ ثابت روی قیمت تامین</label></p>';
		printf(
			'<p><label>مقدار: <input type="number" step="any" name="dastyarc_price_value" value="%s" style="width:130px"></label> <small>در حالت درصدی: 30 یعنی ۳۰٪ — در حالت ثابت: مبلغ</small></p>',
			esc_attr( get_option( 'dastyarc_price_value', 30 ) )
		);
		$round = get_option( 'dastyarc_price_round', 'none' );
		echo '<p><label>گرد کردن: <select name="dastyarc_price_round">';
		foreach ( array( 'none' => 'بدون', '10' => 'نزدیک‌ترین ۱۰', '100' => 'نزدیک‌ترین ۱۰۰', '1000' => 'نزدیک‌ترین ۱۰۰۰' ) as $val => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $round, $val, false ), esc_html( $label ) );
		}
		echo '</select></label></p>';
		printf(
			'<p><label><input type="checkbox" name="dastyarc_stock_sync" value="yes" %s> همگام‌سازی خودکار موجودی با مرکز</label></p>',
			checked( get_option( 'dastyarc_stock_sync', 'yes' ), 'yes', false )
		);
		printf(
			'<p><label><input type="checkbox" name="dastyarc_tracking_completes" value="yes" %s> بعد از کد رهگیری، سفارش تکمیل شود</label></p>',
			checked( get_option( 'dastyarc_tracking_completes', 'yes' ), 'yes', false )
		);
		foreach ( (array) get_option( 'dastyarc_send_statuses', array( 'processing' ) ) as $st ) {
			printf( '<input type="hidden" name="dastyarc_send_statuses[]" value="%s">', esc_attr( $st ) );
		}
		echo '<p><button type="submit" class="button alt">ذخیره</button></p></form>';
	}

	/** سفارشات منتقل‌شده به مرکز */
	public function render_sent() {
		echo '<h3>سفارشات منتقل شده</h3>';
		$orders = wc_get_orders( array(
			'limit'        => 50,
			'meta_key'     => '_dastyarc_remote_order_id',
			'meta_compare' => 'EXISTS',
			'orderby'      => 'date',
			'order'        => 'DESC',
		) );
		if ( ! $orders ) {
			echo '<p>هنوز سفارشی به مرکز منتقل نشده است.</p>';
			return;
		}
		echo '<table class="shop_table"><thead><tr><th>سفارش</th><th>سفارش مرکزی</th><th>تاریخ</th><th>وضعیت</th><th>کد رهگیری</th></tr></thead><tbody>';
		foreach ( $orders as $order ) {
			printf(
				'<tr><td><a href="%s">#%s</a></td><td>#%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_url( $order->get_view_order_url() ),
				esc_html( $order->get_order_number() ),
				esc_html( $order->get_meta( '_dastyarc_remote_order_number' ) ),
				esc_html( DastyarC_Jalali::dt( $order->get_date_created(), 'Y/m/d' ) ),
				esc_html( wc_get_order_status_name( $order->get_status() ) ),
				esc_html( $order->get_meta( '_dastyar_tracking_code' ) ?: '—' )
			);
		}
		echo '</tbody></table>';
	}

	/** اتصال API — مشاهده وضعیت و تست */
	public function render_connection() {
		echo '<h3>اتصال API</h3>';
		printf( '<p>سایت مرکزی: <code>%s</code></p>', esc_html( get_option( 'dastyarc_central_url' ) ?: 'تنظیم نشده' ) );
		$key = (string) get_option( 'dastyarc_api_key' );
		printf( '<p>کلید API: <code>%s</code></p>', $key ? esc_html( substr( $key, 0, 8 ) . '…' . substr( $key, -4 ) ) : 'تنظیم نشده' );

		if ( DastyarC_Client::configured() ) {
			$ping = DastyarC_Client::ping();
			if ( is_wp_error( $ping ) ) {
				printf( '<p style="color:#d63638">وضعیت: اتصال برقرار نشد ✘ — %s</p>', esc_html( $ping->get_error_message() ) );
			} else {
				printf( '<p style="color:#008a20">وضعیت: متصل ✔ (فروشنده: %s)</p>', esc_html( $ping['vendor_name'] ?? '' ) );
			}
		}
		printf(
			'<p><a class="button" href="%s">ویرایش تنظیمات اتصال</a></p>',
			esc_url( admin_url( 'admin.php?page=dastyarc-hub&tab=settings' ) )
		);
	}
}
