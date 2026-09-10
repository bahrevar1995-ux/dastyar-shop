<?php
/**
 * محافظ طول نامک (Slug) محصولات — v1.10.22
 *
 * ریشه باگ: محصولاتی با عنوان فارسی نسبتاً طولانی، نامک درازی می‌گیرند که وقتی برای
 * آدرس اینترنتی درصدی/انکود می‌شود (هر حرف فارسی ← ۶ کاراکتر٪XX٪XX)، طول واقعی آدرس
 * چند برابر می‌شود. روی هاست این سایت، از یک طول مشخص به بعد، این آدرس‌ها دیگر با هیچ
 * قانون بازنویسی لینکی تطبیق پیدا نمی‌کنند و به‌جای صفحه محصول، صفحه اصلی نمایش داده
 * می‌شود (۴۰۴ نامرئی). راه‌حل کوتاه‌مدت برای محصولات موجود، کوتاه‌کردن دستی نامک بود؛
 * این کلاس همان کار را برای همه محصولات (فعلی و آینده) خودکار می‌کند.
 *
 * نکته امنیت اتصال فروشندگان: افزونه Connector محصولات را صرفاً با شناسه عددی
 * (_dastyar_remote_id) و درخواست REST به «products/{id}» همگام می‌کند — نه با نامک/آدرس.
 * پس تغییر نامک هیچ آسیبی به اتصال فروشگاه فروشنده‌ها نمی‌زند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Slug_Guard {

	/** حداکثر طول امن نامک (کاراکتر خام فارسی/لاتین، قبل از انکود شدن به URL) */
	const MAX_LEN = 60;

	public function __construct() {
		// wp_unique_post_slug آخرین فیلتری‌ست که نامک نهایی از آن عبور می‌کند — چه موقع
		// ساخت محصول جدید، چه ویرایش دستی نامک، چه ایمپورت از افزونه‌های دیگر (مثل DigiMaster).
		add_filter( 'wp_unique_post_slug', array( $this, 'shorten' ), 20, 6 );
		add_action( 'admin_post_dastyar_fix_slugs', array( __CLASS__, 'handle_fix_request' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	/** هشدار بالای پیشخوان اگر محصولات قدیمی با نامک دراز پیدا شود + دکمه اصلاح یک‌کلیکی */
	public function admin_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( isset( $_GET['dastyar_slugs_fixed'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>✔ نامک %d محصول با آدرس خیلی دراز کوتاه و اصلاح شد.</p></div>',
				(int) $_GET['dastyar_slugs_fixed']
			);
			return;
		}
		$count = self::count_too_long();
		if ( $count > 0 ) {
			printf(
				'<div class="notice notice-warning"><p>⚠ %d محصول با آدرس (نامک) خیلی دراز پیدا شد که ممکن است باز نشوند (مثل باگ صفحه محصولی که قبلاً دیدید). <a href="%s" class="button button-primary">اصلاح خودکار همه آن‌ها ←</a></p></div>',
				$count,
				esc_url( self::fix_link() )
			);
		}
	}

	/**
	 * @param string $slug          نامک نهایی (پس از بررسی یکتایی توسط خود وردپرس)
	 * @param int    $post_id
	 * @param string $post_status
	 * @param string $post_type
	 * @param int    $post_parent
	 * @param string $original_slug
	 */
	public function shorten( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
		if ( 'product' !== $post_type ) {
			return $slug;
		}
		if ( mb_strlen( $slug, 'UTF-8' ) <= self::MAX_LEN ) {
			return $slug;
		}

		$base = self::truncate( $slug, self::MAX_LEN );
		if ( '' === $base ) {
			$base = 'product-' . (int) $post_id; // حالت خیلی نادر: بعد از کوتاه‌کردن چیزی نماند
		}

		$try = $base;
		$i   = 2;
		while ( self::exists( $try, $post_id, $post_type, $post_parent ) ) {
			$suffix = '-' . $i++;
			$try    = self::truncate( $base, self::MAX_LEN - mb_strlen( $suffix, 'UTF-8' ) ) . $suffix;
		}
		return $try;
	}

	/** برش از آخرین خط‌تیره داخل محدوده (تا وسط یک کلمه/کاراکتر چندبایتی بریده نشود) */
	protected static function truncate( $slug, $max ) {
		$max = max( 1, (int) $max );
		if ( mb_strlen( $slug, 'UTF-8' ) <= $max ) {
			return $slug;
		}
		$cut = mb_substr( $slug, 0, $max, 'UTF-8' );
		$pos = mb_strrpos( $cut, '-', 0, 'UTF-8' );
		if ( false !== $pos && $pos >= 8 ) { // حداقل چند کاراکتر معنی‌دار بماند
			$cut = mb_substr( $cut, 0, $pos, 'UTF-8' );
		}
		return trim( $cut, '-' );
	}

	/** آیا این نامک قبلاً برای پست دیگری استفاده شده؟ (ساده‌شده هم‌ارزِ بررسی خودِ وردپرس) */
	protected static function exists( $slug, $post_id, $post_type, $post_parent ) {
		global $wpdb;
		$sql  = "SELECT post_name FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND ID != %d";
		$args = array( $slug, $post_type, (int) $post_id );
		if ( $post_parent > 0 ) {
			$sql   .= ' AND post_parent = %d';
			$args[] = (int) $post_parent;
		}
		$sql .= ' LIMIT 1';
		return (bool) $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * ابزار یک‌باره از پیشخوان برای اصلاح محصولات قدیمی که از قبل نامک دراز دارند
	 * (محصولات تازه از همین حالا خودکار درست ساخته می‌شوند؛ این فقط قدیمی‌ها را جارو می‌کند).
	 * لینک اجرا: پیشخوان ← هر صفحه‌ای ← افزودن ?dastyar_fix_slugs=1&_wpnonce=... به آدرس
	 * (لینک آماده با نانس در صفحه تنظیمات کاتالوگ/دسته‌ها ساخته می‌شود، اگر آن صفحه موجود باشد).
	 */
	public static function fix_existing( $limit = 50 ) {
		global $wpdb;
		$fixed = 0;
		$ids   = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND CHAR_LENGTH(post_name) > %d LIMIT %d",
			self::MAX_LEN * 2, // طول ذخیره‌شده بایتی نسبت به کاراکتر UTF-8 فرق دارد؛ آستانه بایتی محافظه‌کارانه
			(int) $limit
		) );
		foreach ( (array) $ids as $pid ) {
			$pid  = (int) $pid;
			$slug = get_post_field( 'post_name', $pid );
			if ( mb_strlen( $slug, 'UTF-8' ) <= self::MAX_LEN ) {
				continue;
			}
			$new = wp_unique_post_slug( $slug, $pid, 'publish', 'product', 0 );
			if ( $new !== $slug ) {
				wp_update_post( array( 'ID' => $pid, 'post_name' => $new ) );
				$fixed++;
			}
		}
		return $fixed;
	}

	/** تعداد محصولاتی که هنوز نامک خیلی درازی دارند (برای نمایش هشدار در پیشخوان) */
	public static function count_too_long() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND CHAR_LENGTH(post_name) > %d",
			self::MAX_LEN * 2
		) );
	}

	/** دکمه/لینک اجرای اصلاح گروهی — به هر صفحه پیشخوان می‌توان با admin_url اضافه کرد */
	public static function fix_link() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=dastyar_fix_slugs' ), 'dastyar_fix_slugs' );
	}

	/** هندلر admin-post برای اجرای اصلاح گروهی از پیشخوان */
	public static function handle_fix_request() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyar_fix_slugs' ) ) {
			wp_die( 'دسترسی غیرمجاز یا نشست نامعتبر.' );
		}
		$fixed = self::fix_existing( 200 );
		$back  = wp_get_referer() ?: admin_url();
		wp_safe_redirect( add_query_arg( 'dastyar_slugs_fixed', $fixed, $back ) );
		exit;
	}
}
