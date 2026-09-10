<?php
/**
 * Plugin Name:       Dastyar Core — هسته مرکزی دستیار شاپ
 * Description:        هسته پلتفرم دراپ‌شیپینگ دستیار شاپ: REST API مرکزی، مدیریت فروشندگان، کیف پول، صورتحساب‌ها، سینک محصولات و انتقال سفارشات — کاملاً مبتنی بر WooCommerce.
 * Version:           1.10.33
 * Author:            Dastyar Shop
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * Text Domain:       dastyar-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DASTYAR_CORE_VERSION', '1.10.33' );
define( 'DASTYAR_CORE_FILE', __FILE__ );
define( 'DASTYAR_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'DASTYAR_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * سازگاری با HPOS (جداول سفارش سفارشی ووکامرس)
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', __FILE__, true );
	}
} );

/**
 * Autoloader کلاس‌های Dastyar_*
 * نام فایل: class-dastyar-*.php — نام کلاس: Dastyar_*
 */
spl_autoload_register( function ( $class ) {
	// کلاس اصلی «Dastyar» (بدون زیرخط) هم باید لود شود
	if ( 'Dastyar' !== $class && strpos( $class, 'Dastyar_' ) !== 0 ) {
		return;
	}
	// v1.8.0 — گیت «پنل واحد»: اگر افزونه‌های مستقل «پنل فروشنده»/«کاتالوگ» فعال‌اند،
	// اتولودر خودشان رهبر می‌ماند و فایل‌های ادغام‌شده هسته سرو نمی‌شوند.
	if ( ( 'Dastyar_Panel' === $class || 0 === strpos( $class, 'Dastyar_Panel_' ) ) && defined( 'DASTYAR_PANEL_FILE' ) ) {
		return;
	}
	if ( ( 'Dastyar_Cat' === $class || 0 === strpos( $class, 'Dastyar_Cat_' ) ) && defined( 'DCT_URL' ) ) {
		return;
	}
	$file = DASTYAR_CORE_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

register_activation_hook( __FILE__, array( 'Dastyar_Install', 'install' ) );
register_deactivation_hook( __FILE__, array( 'Dastyar_Install', 'deactivate' ) );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>افزونه «Dastyar Core» برای کار کردن به WooCommerce نیاز دارد. لطفاً ابتدا WooCommerce را نصب و فعال کنید.</p></div>';
		} );
		return;
	}
	load_plugin_textdomain( 'dastyar-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	Dastyar::instance();
} );
