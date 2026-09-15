<?php
/**
 * ارکستراتور کنسول مدیریت مرکز:
 * منوی مستقل «کنسول مرکز» + صفحه‌ها + اکشن‌های admin_post + استایل برند
 * + مسیر REST اعلان برای سایت‌های فروشنده (ماژول‌های ۸/۴۰) + رادار نسخه (ماژول ۴۵)
 * + ویجت «امروز من» روی پیشخوان وردپرس (ماژول ۴۸).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Admin {

	const NONCE       = 'da_console';
	const BROADCASTS  = 'dastyar_admin_broadcasts';
	const LEDGER      = 'dastyar_admin_ledger';

	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_head', array( $this, 'css' ) );

		// ذخیره تنظیمات کنسول (کلیدهای ماژول‌ها + آستانه‌ها)
		add_action( 'admin_post_da_modules_save', array( 'DA_Page_Settings', 'save' ) );
		add_action( 'admin_post_da_tg_test', array( 'DA_Telegram', 'save_test' ) );

		// اکشن‌های صفحه‌ها (همه: cap + nonce)
		add_action( 'admin_post_da_vendor_status', array( 'DA_Page_Customers', 'save_status' ) );
		add_action( 'admin_post_da_customer_note', array( 'DA_Page_Customers', 'save_note' ) );
		add_action( 'admin_post_da_customer_tags', array( 'DA_Page_Customers', 'save_tags' ) );
		add_action( 'admin_post_da_bcast_save', array( 'DA_Page_Customers', 'save_broadcast' ) );
		add_action( 'admin_post_da_wallet_adjust', array( 'DA_Page_Wallet', 'save_adjust' ) );
		add_action( 'admin_post_da_credit_limit', array( 'DA_Page_Wallet', 'save_credit_limit' ) );
		add_action( 'admin_post_da_lowbal_mark', array( 'DA_Page_Wallet', 'save_lowbal_mark' ) );
		add_action( 'admin_post_da_bulk_tracking', array( 'DA_Page_Orders', 'save_bulk_tracking' ) );
		add_action( 'admin_post_da_print', array( 'DA_Page_Orders', 'print_invoices' ) );
		add_action( 'admin_post_da_ledger_add', array( 'DA_Page_Finance', 'ledger_add' ) );
		add_action( 'admin_post_da_ledger_del', array( 'DA_Page_Finance', 'ledger_del' ) );
		add_action( 'admin_post_da_settle', array( 'DA_Page_Finance', 'save_settle' ) );
		add_action( 'admin_post_da_points_reward', array( 'DA_Page_Tools', 'save_points_reward' ) );
		add_action( 'admin_post_da_csv', array( 'DA_Page_Reports', 'export_csv' ) );

		// ماژول ۴۸ — ویجت «امروز من» روی پیشخوان اصلی وردپرس
		add_action( 'wp_dashboard_setup', array( 'DA_Page_Dash', 'register_widget' ) );

		// ماژول‌های ۸/۴۰ — مسیر REST اعلان برای سایت‌های فروشنده (احراز با همان کلید API دستیار)
		add_action( 'rest_api_init', array( $this, 'rest_notice' ) );

		// ماژول‌های ۴۳/۴۵ — ضبط نسخه کانکتور + آخرین ping هر فروشنده (کاملاً افزایشی؛ مرکز را تغییر نمی‌دهد)
		add_filter( 'rest_pre_dispatch', array( $this, 'capture_ping_meta' ), 20, 3 );
	}

	/* ------------------------------------------------------------------
	 * منوی کنسول مرکز
	 * ---------------------------------------------------------------- */

	public function menu() {
		$st = DA_Modules::stats();
		add_menu_page(
			'کنسول مرکز',
			'کنسول مرکز <span class="dastyar-live-dot" aria-hidden="true"></span>',
			'manage_woocommerce',
			'da-dash',
			array( $this, 'render_page' ),
			'dashicons-chart-area',
			4
		);
		foreach ( DA_Modules::pages() as $key => $p ) {
			add_submenu_page( 'da-dash', $p[0], $p[0], 'manage_woocommerce', $p[1], array( $this, 'render_page' ) );
		}
		add_submenu_page( 'da-dash', 'تنظیمات کنسول', '⚙ کلید ماژول‌ها (' . (int) $st['on'] . '/' . (int) $st['total'] . ')', 'manage_woocommerce', 'da-settings', array( 'DA_Page_Settings', 'render' ) );
	}

	/** رندر مشترک صفحه‌ها: هر ماژول فعالِ صفحه به‌صورت کارت */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$page_slug = sanitize_key( (string) ( $_GET['page'] ?? 'da-dash' ) );
		$def       = null;
		foreach ( DA_Modules::pages() as $key => $p ) {
			if ( $p[1] === $page_slug ) {
				$def = array( 'key' => $key, 'title' => $p[0], 'class' => $p[2] );
			}
		}
		if ( ! $def ) {
			$def = array( 'key' => 'dash', 'title' => 'داشبورد کنسول', 'class' => 'DA_Page_Dash' );
		}
		echo '<div class="wrap da-wrap" dir="rtl">';
		echo '<h1 class="da-page-title"><span class="da-logo-dot"></span>' . esc_html( $def['title'] )
			. ' <span class="da-ver">کنسول ' . esc_html( DASTYAR_ADMIN_VERSION ) . '</span></h1>';
		DA_Render::notices();

		$enabled = DA_Modules::enabled_on_page( $def['key'] );
		if ( ! $enabled ) {
			echo '<div class="da-card"><p class="da-empty">همه ماژول‌های این صفحه غیرفعال‌اند. از <a href="'
				. esc_url( admin_url( 'admin.php?page=da-settings' ) ) . '">تنظیمات کنسول</a> روشنشان کنید.</p></div>';
		}
		foreach ( $enabled as $id => $mod ) {
			$cls = $def['class'];
			if ( ! class_exists( $cls ) || ! method_exists( $cls, $mod['cb'] ) ) {
				continue;
			}
			ob_start();
			try {
				call_user_func( array( $cls, $mod['cb'] ) );
			} catch ( \Throwable $e ) {
				echo '<p class="da-empty">این کارت موقتاً در دسترس نیست.</p>';
			}
			$body = ob_get_clean();
			DA_Render::card( $mod, $body );
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------
	 * استایل برند کنسول (سبز #17a16d + سرمه‌ای #242536) + فونت وزیرمتن
	 * ---------------------------------------------------------------- */

	public function css() {
		$page = (string) ( $_GET['page'] ?? '' );
		global $pagenow;
		$on_dash_widget = ( 'index.php' === (string) $pagenow ) && DA_Modules::enabled( 'm48' );
		if ( 0 !== strpos( $page, 'da-' ) && ! $on_dash_widget ) {
			return;
		}
		echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">';
		echo '<style>
		.da-wrap,.da-wrap *{box-sizing:border-box}
		.da-wrap{font-family:Vazirmatn,Tahoma,sans-serif}
		.da-page-title{display:flex;align-items:center;gap:10px;color:#242536}
		.da-logo-dot{width:10px;height:10px;border-radius:50%;background:#17a16d;box-shadow:0 0 0 0 rgba(23,161,109,.5);animation:daPulse 1.8s ease-out infinite;display:inline-block}
		@keyframes daPulse{0%{box-shadow:0 0 0 0 rgba(23,161,109,.5)}70%{box-shadow:0 0 0 9px rgba(23,161,109,0)}100%{box-shadow:0 0 0 0 rgba(23,161,109,0)}}
		.da-ver{font-size:11px;color:#8aa093;font-weight:400}
		.da-card{background:#fff;border:1.5px solid #e3eee8;border-radius:16px;margin:16px 0;box-shadow:0 4px 16px rgba(23,161,109,.07);overflow:hidden}
		.da-card-h{padding:12px 18px;background:linear-gradient(135deg,#f4fbf7,#eef7f2);border-bottom:1px solid #e8f2ec}
		.da-card-h h3{margin:0;color:#242536;font-size:15px;display:flex;align-items:center;gap:8px}
		.da-card-h p{margin:4px 0 0;color:#6f857b;font-size:12px}
		.da-modid{font-size:10px;background:#17a16d;color:#fff;border-radius:8px;padding:2px 7px;font-weight:700}
		.da-card-b{padding:16px 18px}
		.da-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}
		.da-kpi{background:linear-gradient(180deg,#f8fcfa,#eef8f2);border:1.5px solid #dff0e6;border-radius:14px;padding:14px;text-align:center;display:flex;flex-direction:column;gap:4px}
		.da-kpi.red{background:linear-gradient(180deg,#fff7f7,#fdeeee);border-color:#f6dcdc}
		.da-kpi.dark{background:linear-gradient(180deg,#242536,#2f3046);border-color:#242536}
		.da-kpi.dark .da-kpi-v,.da-kpi.dark .da-kpi-l{color:#fff}
		.da-kpi.dark .da-kpi-s{color:#a9b2c9}
		.da-kpi-v{font-size:20px;font-weight:800;color:#12875c}
		.da-kpi-l{font-size:12px;color:#5b6f65;font-weight:600}
		.da-kpi-s{font-size:11px;color:#8aa093}
		.da-table th{white-space:nowrap}
		.da-pill{display:inline-block;border-radius:10px;padding:1px 10px;font-size:11px;font-weight:700;border:1px solid transparent}
		.da-pill-green{background:#e5f5ee;border-color:#a9dcc4;color:#0f5132}
		.da-pill-red{background:#fdecec;border-color:#f2b6b6;color:#922222}
		.da-pill-amber{background:#fff5d6;border-color:#eccb6a;color:#7a5b00}
		.da-pill-gray{background:#eef1f5;border-color:#d3dae2;color:#49566b}
		.da-empty{color:#8aa093;font-size:12.5px}
		.da-hint{color:#8aa093;font-size:11px;margin:8px 0 0}
		.da-trend{font-weight:800;font-size:11.5px;border-radius:8px;padding:2px 8px}
		.da-trend.up{color:#0f5132;background:#e5f5ee}
		.da-trend.down{color:#922222;background:#fdecec}
		.da-trend.flat{color:#8aa093}
		.da-hbars{display:flex;flex-direction:column;gap:6px}
		.da-hbar-row{display:flex;align-items:center;gap:8px;font-size:12px}
		.da-hbar-label{min-width:110px;text-align:left;color:#40554b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.da-hbar-track{flex:1;background:#eef4f0;border-radius:8px;height:14px;overflow:hidden}
		.da-hbar-fill{display:block;height:100%;background:linear-gradient(90deg,#17a16d,#2fc98e);border-radius:8px}
		.da-hbar-val{min-width:90px;text-align:left;font-weight:700;color:#242536}
		.da-chart{display:block;margin:6px 0}
		.da-btn{display:inline-block;background:#17a16d;border:1px solid #12875c;color:#fff!important;border-radius:10px;padding:6px 16px;font-size:12.5px;font-weight:700;cursor:pointer;text-decoration:none!important}
		.da-btn:hover{background:#12875c;color:#fff!important}
		.da-btn.gray{background:#fff;color:#40554b!important;border:1.5px solid #cfe0d6}
		.da-btn.gray:hover{background:#f2f8f4}
		.da-btn.red{background:#c9284a;border-color:#a51e3b}
		.da-input,input.da-input,textarea.da-input,select.da-input{border:1.5px solid #d6e6dc;border-radius:10px;padding:6px 10px;font-family:inherit;font-size:12.5px;background:#fbfdfc}
		.da-form-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0}
		.da-switch{position:relative;display:inline-block;width:42px;height:24px;flex:none}
		.da-switch input{opacity:0;width:0;height:0}
		.da-slider{position:absolute;inset:0;border-radius:24px;background:#d5ded9;transition:.2s;cursor:pointer}
		.da-slider:before{content:"";position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;top:3px;right:3px;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
		.da-switch input:checked + .da-slider{background:#17a16d}
		.da-switch input:checked + .da-slider:before{transform:translateX(-18px)}
		.da-modrow{display:flex;align-items:center;gap:10px;border:1px solid #e7f0ea;border-radius:12px;padding:8px 12px;background:#fbfdfc;margin:6px 0}
		.da-modrow .t{font-weight:700;color:#242536;font-size:12.5px}
		.da-modrow .d{color:#6f857b;font-size:11px}
		.da-modrow .num{min-width:26px;height:22px;border-radius:8px;background:#eef6f1;color:#12875c;font-weight:800;font-size:11px;display:flex;align-items:center;justify-content:center}
		.da-grouptitle{margin:18px 0 6px;color:#17a16d;font-weight:800;display:flex;align-items:center;gap:8px}
		.da-ledger-inc{color:#0f5132;font-weight:700}
		.da-ledger-dec{color:#922222;font-weight:700}
		@media print{.da-card-h .da-btn,#adminmenumain,#wpadminbar,.da-no-print{display:none!important}.da-card{box-shadow:none;border-color:#ccc}}
		</style>';
	}

	/* ------------------------------------------------------------------
	 * REST: اعلان فعال برای سایت‌های فروشنده (ماژول‌های ۸ و ۴۰)
	 * ---------------------------------------------------------------- */

	public function rest_notice() {
		register_rest_route( 'dastyar/v1', '/notice', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'notice_payload' ),
			'permission_callback' => array( $this, 'notice_permission' ),
		) );
	}

	/** شناسه فروشنده متقاضی در جریان همین درخواست REST (برای فیلتر مخاطب) */
	protected static $notice_uid = 0;

	/** احراز با همان کلید API که دستیار مرکز برای فروشندگان صادر می‌کند */
	public function notice_permission( $request ) {
		if ( ! DA_Modules::enabled( 'm08' ) && ! DA_Modules::enabled( 'm40' ) ) {
			return false;
		}
		$key = (string) $request->get_header( 'x-dastyar-key' );
		if ( '' === $key ) {
			return false;
		}
		$api = DA_Data::vendors_api();
		if ( ! $api || ! method_exists( $api, 'vendor_by_key' ) ) {
			return false;
		}
		$res = $api->vendor_by_key( $key );
		$uid = is_numeric( $res ) ? (int) $res : ( is_object( $res ) && isset( $res->ID ) ? (int) $res->ID : 0 );
		if ( $uid > 0 ) {
			self::$notice_uid = $uid;
			return true;
		}
		return false;
	}

	/**
	 * جدیدترین اعلان فعالِ «مخصوص این فروشنده» (فیلتر مخاطب سمت مرکز):
	 * اعلان‌های بی‌برچسب برای همه + اعلان‌هایی که برچسبشان در برچسب‌های خود فروشنده است.
	 */
	public static function notice_for( $uid ) {
		$mine = array();
		foreach ( explode( '،', str_replace( ',', '،', (string) get_user_meta( (int) $uid, '_da_tags', true ) ) ) as $t ) {
			$t = trim( $t );
			if ( '' !== $t ) { $mine[] = $t; }
		}
		$b = self::broadcasts();
		for ( $i = count( $b ) - 1; $i >= 0; $i-- ) {
			if ( empty( $b[ $i ]['active'] ) ) { continue; }
			$tag = trim( (string) ( $b[ $i ]['tag'] ?? '' ) );
			if ( '' !== $tag && ! in_array( $tag, $mine, true ) ) { continue; }
			return array(
				'ok'     => true,
				'active' => true,
				'title'  => (string) ( $b[ $i ]['title'] ?? '' ),
				'msg'    => (string) ( $b[ $i ]['msg'] ?? '' ),
				'tag'    => $tag,
				'time'   => (string) ( $b[ $i ]['time'] ?? '' ),
			);
		}
		return array( 'ok' => true, 'active' => false );
	}

	public function notice_payload() {
		return rest_ensure_response( self::notice_for( self::$notice_uid ) );
	}

	public static function broadcasts() {
		$b = get_option( self::BROADCASTS, array() );
		return is_array( $b ) ? $b : array();
	}

	/* ------------------------------------------------------------------
	 * رادار نسخه + آخرین ping فروشنده (ماژول‌های ۴۳/۴۵)
	 * بدون دست‌کاری مرکز: روی همان جریان REST فعلی سوار می‌شویم.
	 * ---------------------------------------------------------------- */

	public function capture_ping_meta( $result, $server, $request ) {
		try {
			if ( ! ( $request instanceof WP_REST_Request ) ) {
				return $result;
			}
			$route = (string) $request->get_route();
			if ( false === strpos( $route, '/dastyar/v1/ping' ) ) {
				return $result;
			}
			$key = (string) $request->get_header( 'x-dastyar-key' );
			$api = DA_Data::vendors_api();
			if ( '' === $key || ! $api || ! method_exists( $api, 'vendor_by_key' ) ) {
				return $result;
			}
			$res = $api->vendor_by_key( $key );
			$uid = is_numeric( $res ) ? (int) $res : ( is_object( $res ) && isset( $res->ID ) ? (int) $res->ID : 0 );
			if ( $uid <= 0 ) {
				return $result;
			}
			update_user_meta( $uid, '_dastyar_last_ping', time() );
			// نسخه کانکتور به‌صورت پارامتر GET روی /ping می‌آید (کانکتور 1.7.11+)
			$version = (string) $request->get_param( 'version' );
			if ( '' !== $version ) {
				update_user_meta( $uid, '_dastyar_conn_version', sanitize_text_field( $version ) );
			}
		} catch ( \Throwable $e ) {
			// سکوت — مانیتورینگ هرگز نباید جریان اصلی را بشکند
		}
		return $result;
	}
}
