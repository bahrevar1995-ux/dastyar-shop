<?php
/**
 * Orchestrator پنل فروشنده — نسخه ادغام‌شده در هسته (v1.8.0).
 *
 * این کپی فقط در «حالت ادغام» سرو می‌شود (یعنی وقتی افزونه مستقل
 * «دستیار شاپ — پنل فروشنده» فعال نیست؛ گیت اتولودر در فایل اصلی هسته).
 * تفاوت با نسخه مستقل: ساختن ادمین به Dastyar_Panel_Admin_Ext سپرده می‌شود
 * تا صفحه تنظیمات به‌جای منوی مستقل، زیرمجموعه «دستیار شاپ» باشد (پنل واحد).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dastyar_Panel {

	/** @var Dastyar_Panel|null */
	private static $instance = null;

	/** @var Dastyar_Panel_Front */
	public $front;

	/** @var Dastyar_Panel_Admin_Ext|null */
	public $admin = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->front = new Dastyar_Panel_Front();
		if ( is_admin() ) {
			$this->admin = new Dastyar_Panel_Admin_Ext();
		}
	}
}
