<?php
/**
 * Plugin Name:       Dastyar Connector — اتصال‌دهنده فروشنده به دستیار شاپ
 * Description:        اتصال فروشگاه WooCommerce شما به پلتفرم دراپ‌شیپینگ دستیار شاپ: دریافت محصولات، قیمت‌گذاری خودکار، افزودن به فروشگاه، همگام‌سازی و انتقال سفارشات.
 * Version:           1.8.2
 * Author:            Dastyar Shop
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * Text Domain:       dastyar-connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DASTYARC_VERSION', '1.8.2' );
define( 'DASTYARC_FILE', __FILE__ );
define( 'DASTYARC_DIR', plugin_dir_path( __FILE__ ) );
define( 'DASTYARC_URL', plugin_dir_url( __FILE__ ) );

/** سازگاری با HPOS */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', __FILE__, true );
	}
} );

/** Autoloader کلاس‌های DastyarC_* + کلاس اصلی DastyarC */
spl_autoload_register( function ( $class ) {
	// کلاس اصلی «DastyarC» (بدون زیرخط) هم باید لود شود
	if ( 'DastyarC' !== $class && strpos( $class, 'DastyarC_' ) !== 0 ) {
		return;
	}
	$file = DASTYARC_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

register_activation_hook( __FILE__, array( 'DastyarC_Install', 'install' ) );
register_deactivation_hook( __FILE__, array( 'DastyarC_Install', 'deactivate' ) );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>افزونه «Dastyar Connector» برای کار کردن به WooCommerce نیاز دارد.</p></div>';
		} );
		return;
	}
	load_plugin_textdomain( 'dastyar-connector', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	DastyarC::instance();
} );
