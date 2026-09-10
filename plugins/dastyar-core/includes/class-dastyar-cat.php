<?php
/**
 * Orchestrator کاتالوگ دستیار — نسخه ادغام‌شده در هسته (v1.8.0).
 *
 * این کپی فقط در «حالت ادغام» سرو می‌شود (یعنی وقتی افزونه مستقل
 * «دستیار شاپ — کاتالوگ محصولات» فعال نیست؛ گیت اتولودر در فایل اصلی هسته).
 * تفاوت با نسخه مستقل: ساختن ادمین به Dastyar_Cat_Admin_Ext سپرده می‌شود
 * تا صفحه تنظیمات به‌جای منوی مستقل، زیرمجموعه «دستیار شاپ» باشد (پنل واحد).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dastyar_Cat {

	private static $instance = null;

	/** @var Dastyar_Cat_Admin_Ext|null */
	public $admin = null;
	/** @var Dastyar_Cat_Archive */
	public $archive;
	/** @var Dastyar_Cat_Single */
	public $single;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->archive = new Dastyar_Cat_Archive();
		$this->single  = new Dastyar_Cat_Single();
		if ( is_admin() ) {
			$this->admin = new Dastyar_Cat_Admin_Ext();
		}
	}

	/** پیش‌فرض‌های نصب */
	public static function activate() {
		if ( ! get_option( Dastyar_Cat_Settings::OPTION ) ) {
			add_option( Dastyar_Cat_Settings::OPTION, Dastyar_Cat_Settings::defaults() );
		}
	}
}
