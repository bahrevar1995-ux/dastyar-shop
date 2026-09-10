<?php
/**
 * Plugin Name:       Dastyar Shop — کنسول مدیریت مرکز (Admin Console)
 * Plugin URI:        https://dastyar.shop
 * Description:       کنسول فرماندهی دستیار شاپ برای پیشخوان «سایت مرکز»: ۵۰ ماژول مدیریت مشتری، کیف پول، گزارش فروش و سفارش‌ها، مالی، سلامت پلتفرم و ابزارها — هر ماژول با کلید فعال/غیرفعال جداگانه از صفحه «تنظیمات کنسول». به‌علاوه یک پنل مستقل کارمندان (کدکوتاه [dastyar_staff_panel]) با نقش «کارمند دستیار» — دسترسی کامل به همان ۵۰ ماژول بدون نیاز به ورود به پیشخوان وردپرس. در کنار افزونه اصلی مرکز (dastyar-core) کار می‌کند و چیزی از آن را تغییر نمی‌دهد.
 * Version:           1.4.0
 * Author:            Dastyar Shop
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * License:           Proprietary
 * Text Domain:       dastyar-admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DASTYAR_ADMIN_VERSION' ) ) {
	define( 'DASTYAR_ADMIN_VERSION', '1.4.0' );
}
if ( ! defined( 'DASTYAR_ADMIN_DIR' ) ) {
	define( 'DASTYAR_ADMIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'DASTYAR_ADMIN_URL' ) ) {
	define( 'DASTYAR_ADMIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'DASTYAR_ADMIN_MODULES_OPTION' ) ) {
	define( 'DASTYAR_ADMIN_MODULES_OPTION', 'dastyar_admin_modules' );
}

/** سازگاری با HPOS (جداول سفارش سفارشی ووکامرس) — هماهنگ با بقیه افزونه‌های خانواده دستیار */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/* اتولودر سبک هم‌خانواده با dastyar-core */
spl_autoload_register( function ( $class ) {
	if ( 0 !== strpos( $class, 'DA_' ) ) {
		return;
	}
	$file = DASTYAR_ADMIN_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}
} );

add_action( 'plugins_loaded', function () {
	$console = new DA_Admin();
	$console->boot();
	DA_Telegram::init();

	// v1.4.0 — پنل مستقل کارمندان (بدون دسترسی به پیشخوان وردپرس)
	( new DA_Staff() )->boot();
	( new DA_Panel() )->boot();
}, 16 );

register_deactivation_hook( __FILE__, array( 'DA_Telegram', 'deactivate' ) );
