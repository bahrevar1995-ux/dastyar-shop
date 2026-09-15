<?php
/**
 * نشان «ارسال از انبار بندرگناوه» (v1.5.0).
 * v1.7.0: نشان به «بالای عنوان محصول» منتقل شد (هوک woocommerce_single_product_summary با اولویت ۴ —
 * دقیقاً قبل از عنوان که در اولویت ۵ چاپ می‌شود).
 *
 * فقط برای محصولاتی که از دستیار شاپ به فروشگاه اضافه شده‌اند (متا _dastyar_remote_id)
 * در صفحه تکی محصول نمایش داده می‌شود؛ با طرح برند سازمانی.
 * از «دستیار شاپ ← تنظیمات دستیار» قابل فعال/غیرفعال کردن است (پیش‌فرض: فعال).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Badge {

	const OPTION = 'dastyarc_warehouse_badge';

	public function __construct() {
		// بالای عنوان محصول — عنوان ووکامرس روی همین هوک با اولویت ۵ چاپ می‌شود (v1.7.0)
		add_action( 'woocommerce_single_product_summary', array( $this, 'render' ), 4 );
	}

	/** آیا نشان فعال است؟ (پیش‌فرض: فعال) */
	public static function enabled() {
		return 'yes' === get_option( self::OPTION, 'yes' );
	}

	public function render() {
		if ( ! self::enabled() ) {
			return;
		}

		global $product;
		if ( ! $product instanceof WC_Product && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// فقط محصولات واردشده از دستیار (محصول یا والدش در حالت متغیر)
		$pid       = (int) $product->get_id();
		$parent_id = method_exists( $product, 'get_parent_id' ) ? (int) $product->get_parent_id() : 0;
		$imported  = (bool) get_post_meta( $pid, '_dastyar_remote_id', true );
		if ( ! $imported && $parent_id ) {
			$imported  = (bool) get_post_meta( $parent_id, '_dastyar_remote_id', true );
			$check_id  = $imported ? $parent_id : $pid;
		} else {
			$check_id = $pid;
		}
		if ( ! $imported ) {
			return;
		}
		// (v1.7.8) محصول «از دراپ‌شیپینگ خارج» شده ← دیگر از انبار دستیار ارسال نمی‌شود؛ نشان نمایش داده نشود
		if ( get_post_meta( $check_id, '_dastyarc_detached', true ) ) {
			return;
		}

		$text = (string) apply_filters( 'dastyarc_warehouse_badge_text', '🚚 ارسال از انبار بندرگناوه', $pid );

		// بالای عنوان محصول — رپر بلوکی با فاصله پایین (v1.7.0)
		echo '<div class="dastyarc-warehouse-badge-wrap" style="margin:0 0 12px">' .
			'<span class="dastyarc-warehouse-badge" style="display:inline-flex;align-items:center;gap:7px;padding:7px 16px;background:linear-gradient(135deg,#e9f8f1,#dff3ea);border:1.5px solid #17a16d;color:#0f5132;border-radius:12px;font-size:13.5px;font-weight:700;box-shadow:0 2px 8px rgba(23,161,109,.12)">' .
			esc_html( $text ) .
		'</span></div>';
	}
}
