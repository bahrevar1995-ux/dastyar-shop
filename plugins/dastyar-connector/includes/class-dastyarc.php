<?php
/**
 * Orchestrator افزونه Connector
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DastyarC {

	/** @var DastyarC|null */
	private static $instance = null;

	/** @var DastyarC_Listener */
	public $listener;
	/** @var DastyarC_Orders */
	public $orders;
	/** @var DastyarC_Sync */
	public $sync;
	/** @var DastyarC_Track */
	public $track;
	/** @var DastyarC_Rma */
	public $rma;
	/** @var DastyarC_MyAccount */
	public $myaccount;
	/** @var DastyarC_Badge */
	public $badge;
	/** @var DastyarC_Progress */
	public $progress;
	/** @var DastyarC_Admin|null */
	public $admin = null;
	/** @var DastyarC_Dashboard|null */
	public $dashboard = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->listener  = new DastyarC_Listener();
		$this->orders    = new DastyarC_Orders();
		$this->sync      = new DastyarC_Sync();
		$this->track     = new DastyarC_Track();
		$this->rma       = new DastyarC_Rma();
		$this->myaccount = new DastyarC_MyAccount();
		// نشان «ارسال از انبار بندرگناوه» + نوار پیشرفت سفارش مشتری (v1.5.0)
		$this->badge     = new DastyarC_Badge();
		$this->progress  = new DastyarC_Progress();
		// (v1.9.3) اعلان به‌روزرسانی مرکزی در نوار ابزار + به‌روزرسانی یک‌کلیکی از پیشخوان فروشنده
		$this->updater   = new DastyarC_Updater();

		if ( is_admin() ) {
			$this->admin     = new DastyarC_Admin();
			$this->dashboard = new DastyarC_Dashboard();
		}

		// بازه کرون اختصاصی ۱۵ دقیقه‌ای (سینک سریع موجودی)
		add_filter( 'cron_schedules', array( 'DastyarC_Install', 'schedules' ) );

		// شنونده وب‌هوک‌های مرکز
		add_action( 'rest_api_init', array( $this->listener, 'register_routes' ) );

		// همگام‌سازی دوره‌ای (Pull) — جبران خطای وب‌هوک‌ها
		add_action( 'dastyarc_sync_tick', array( $this->sync, 'run' ) );
		add_action( 'dastyarc_reconcile_tick', array( $this->sync, 'reconcile_orders' ) );
		add_action( 'dastyarc_stock_tick', array( $this->sync, 'sync_stock' ) );
	}

	public static function log( $message, $level = 'info' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array( 'source' => 'dastyar-connector' ) );
		}
		// (v1.10.0) حلقه رویدادهای سبک برای کارت «رویدادهای اخیر» داشبورد فرماندهی — ۳۰ مورد آخر
		if ( ! in_array( $level, array( 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' ), true ) ) {
			return;
		}
		if ( in_array( $level, array( 'critical', 'alert', 'emergency' ), true ) ) {
			$level = 'error';
		}
		$events   = (array) get_option( 'dastyarc_events', array() );
		$events[] = array( 't' => time(), 'l' => $level, 'm' => (string) $message );
		if ( count( $events ) > 30 ) {
			$events = array_slice( $events, -30 );
		}
		update_option( 'dastyarc_events', $events, false );
	}

	/** آخرین رویدادها (تازه‌اول) برای داشبورد (v1.10.0) */
	public static function events( $limit = 8 ) {
		$events = array_reverse( (array) get_option( 'dastyarc_events', array() ) );
		return array_slice( $events, 0, max( 1, (int) $limit ) );
	}

	/** آیا در X ساعت اخیر رویداد خطا داشته‌ایم؟ → وضعیت سلامت اتصال در داشبورد (v1.10.0) */
	public static function has_recent_error( $hours = 72 ) {
		foreach ( self::events( 30 ) as $ev ) {
			if ( 'error' === (string) ( $ev['l'] ?? '' ) && ( (int) ( $ev['t'] ?? 0 ) > time() - $hours * HOUR_IN_SECONDS ) ) {
				return true;
			}
		}
		return false;
	}
}
