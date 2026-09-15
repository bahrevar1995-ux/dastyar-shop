<?php
/**
 * DastyarC_Admin_Rma — trait بخش «مرجوعی» پنل کانکتور (v1.12.0 — شکافتن کلاس ادمین).
 * فقط توسط DastyarC_Admin استفاده می‌شود؛ منطق نسبت به قبل هیچ تغییری نکرده است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait DastyarC_Admin_Rma {

	/** پنل عودت و مرجوعی (پیاده‌سازی در DastyarC_Rma) */
	/**
	 * (v1.10.0 — Command Center) تب «عودت و مرجوعی» — کلاس RMA با مارک‌اپ بومی (widefat/form-table) الگوی dcx2 را به‌ارث می‌برد
	 */
	protected function tab_rma() {
		DastyarC::instance()->rma->page_admin();
	}

	/** دریافت مجدد قوانین عودت از مرکز (دکمه تنظیمات) */
	public function handle_rules_refresh() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_rules_refresh' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$ok = DastyarC_Rma::sync_rules_from_central();
		$this->notice(
			$ok ? 'قوانین عودت از مرکز دریافت و به‌روزرسانی شد ✔' : 'دریافت قوانین ناموفق بود؛ اتصال به مرکز را بررسی کنید.',
			$ok ? 'success' : 'error'
		);
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=settings' ) );
		exit;
	}
}
