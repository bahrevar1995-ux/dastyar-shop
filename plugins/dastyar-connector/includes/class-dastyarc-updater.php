<?php
/**
 * (v1.9.3 — درخواست کاربر) اعلان و به‌روزرسانی افزونه از داخل پیشخوان فروشنده
 *
 * مرکز در «وضعیت و تنظیمات» خودش مانیفست به‌روزرسانی تعریف می‌کند (نسخه + لینک ZIP +
 * برچسب اولویت: عادی/مهم/ضروری/فوری + متن اعلان + شرح تغییرات). این کلاس طرف فروشندهست:
 *  - مانیفست را از REST مرکز (`GET /connector-update`) می‌گیرد و ۶ ساعت کش می‌کند؛
 *    ذخیره تنظیمات در مرکز، بلافاصله با وب‌هوک `connector.update` کش را تازه می‌کند.
 *  - نمایش در «نوار ابزار بالای پیشخوان» (admin bar) با قرص رنگی بر اساس برچسب اولویت +
 *    هشدار ماندگار سراسری در صفحات پیشخوان برای برچسب‌های «ضروری» و «فوری».
 *  - به‌روزرسانی یک‌کلیکی از خود پیشخوان فروشنده: تزریق به‌روزرسانی به مکانیزم بومی وردپرس
 *    (صفحه افزونه‌ها + «به‌روزرسانی‌ها») + مودال «نمایش جزئیات/تغییرات» (plugins_api)
 *    + لینک مستقیم «اکنون به‌روزرسانی» با نانس استاندارد upgrade-plugin.
 *  - نام پوشه نصب‌شده با فیلتر upgrader_source_selection اصلاح می‌شود تا ZIPهای با نام
 *    نسخه‌دار (`dastyar-connector-1.9.3.zip`) افزونه را جایگزین درست کنند، نه پوشه جدید بسازند.
 *
 * هیچ درخواست HTTPی در رندر توابع نمایشی انجام نمی‌شود (فقط خواندن کش)؛ تازه‌سازی هم
 * با قفل ۳۰دقیقه‌ای و فقط روی صفحات حساس (افزونه‌ها/به‌روزرسانی‌ها/پیشخوان/هاب دستیار) انجام می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DastyarC_Updater {

	const PLUGIN_FILE       = 'dastyar-connector/dastyar-connector.php';
	const PLUGIN_DIR        = 'dastyar-connector'; // نام پوشه‌ای که باید روی دیسک معنایش بماند
	const CACHE_KEY         = 'dastyarc_update_manifest';
	const LOCK_KEY          = 'dastyarc_update_fetch_lock';
	const CACHE_TTL         = 6 * HOUR_IN_SECONDS;
	public static $manifest; // کش هم‌طور کنونی در حافظه (جلوگیری از محاسبه تکراری در یک درخواست)

	public function __construct() {
		// تزریق نتیجه چک به‌روزرسانی به مکانیزم بومی وردپرس (فهرست افزونه‌ها + صفحه به‌روزرسانی‌ها)
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ), 20 );
		// مودال «نمایش جزئیات نسخه …/تغییرات» در فهرست افزونه‌ها
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 20, 3 );
		// اصلاح نام پوشه هنگام نصب از ZIP نسخه‌دار
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 3 );
		// نمایش اعلان در نوار ابزار بالای پیشخوان (و نوار ابزار فرانت اگر فعال باشد)
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 80 );
		add_action( 'admin_head', array( $this, 'bar_css' ) );
		add_action( 'wp_head', array( $this, 'bar_css' ) );
		// هشدار ماندگار سراسری برای برچسب‌های «ضروری» و «فوری» (+ هشدار صفحه افزونه‌ها برای «مهم»)
		add_action( 'admin_notices', array( $this, 'urgent_notice' ) );
		// تازه‌سازی کش با قفل ۳۰دقیقه‌ای روی صفحات حساس مدیریت
		add_action( 'admin_init', array( $this, 'maybe_refresh' ) );
		// پاک‌سازی کش بعد از به‌روزرسانی موفق خودمان
		add_action( 'upgrader_process_complete', array( $this, 'after_upgrade' ), 10, 2 );
	}

	/* ==================================================================
	 * مانیفست مرکزی (خواندن از کش / واکشی از مرکز)
	 * ================================================================== */

	/**
	 * گرفتن مانیفست به‌روزرسانی از کش؛ اگر قدیمی بود (و force) از مرکز واکشی می‌شود.
	 * @return array{version:string,package:string,label:string,label_title:string,message:string,changelog:string,released:string}|null
	 */
	public function manifest( $force = false ) {
		if ( null !== self::$manifest && ! $force ) {
			return self::$manifest;
		}
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['version'] ) && ! $force ) {
			self::$manifest = $cached;
			return self::$manifest;
		}
		if ( ! DastyarC_Client::configured() ) {
			self::$manifest = is_array( $cached ) ? $cached : null;
			return self::$manifest;
		}
		$res  = DastyarC_Client::connector_update();
		$info = null;
		if ( ! is_wp_error( $res ) && is_array( $res ) && ! empty( $res['ok'] ) ) {
			$info = self::sanitize_manifest( $res );
		} elseif ( is_wp_error( $res ) ) {
			DastyarC::log( 'Update manifest fetch failed: ' . $res->get_error_message(), 'warning' );
		}
		if ( $info ) {
			set_transient( self::CACHE_KEY, $info, self::CACHE_TTL );
			self::$manifest = $info;
		} else {
			// خطا: کش قبلی (اگر هست) را برمی‌گردانیم تا اعلان ناپدید نشود
			self::$manifest = is_array( $cached ) ? $cached : null;
		}
		return self::$manifest;
	}

	/** پاکیزه‌سازی صرف از ورودی مرکز (هم برای پاسخ REST هم برای پوش وب‌هوک) */
	public static function sanitize_manifest( $in ) {
		$labels  = self::labels();
		$label   = (string) ( $in['label'] ?? 'normal' );
		if ( ! isset( $labels[ $label ] ) ) {
			$label = 'normal';
		}
		return array(
			'version'     => sanitize_text_field( (string) ( $in['version'] ?? '' ) ),
			'package'     => esc_url_raw( (string) ( $in['package'] ?? '' ) ),
			'label'       => $label,
			'label_title' => $labels[ $label ],
			'message'     => sanitize_text_field( (string) ( $in['message'] ?? '' ) ),
			'changelog'   => sanitize_textarea_field( (string) ( $in['changelog'] ?? '' ) ),
			'released'    => sanitize_text_field( (string) ( $in['released'] ?? '' ) ),
		);
	}

	/** برچسب‌ها + رنگ تلقّین‌شده به UI */
	public static function labels() {
		return array(
			'normal'    => 'عادی',
			'important' => 'مهم',
			'critical'  => 'ضروری',
			'urgent'    => 'فوری',
		);
	}

	/** آیا نسخه جدیدتری برای نصب هست؟ (نسخه مرکز > نسخه نصب‌شده + لینک فایل موجود) */
	public function has_update() {
		$m = $this->manifest();
		return is_array( $m ) && '' !== ( $m['version'] ?? '' ) && '' !== ( $m['package'] ?? '' )
			&& version_compare( (string) $m['version'], DASTYARC_VERSION, '>' );
	}

	/** دادهٔ مشترک بین انواع نمایش (نال اگر به‌روزرسانی نباشد) */
	protected function update_payload() {
		if ( ! $this->has_update() ) {
			return null;
		}
		$m = $this->manifest();
		return array(
			'manifest' => $m,
			'version'  => (string) $m['version'],
			'label'    => (string) $m['label'],
			'title'    => (string) $m['label_title'],
			'message'  => (string) $m['message'],
			'url'      => wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( self::PLUGIN_FILE ) ), 'upgrade-plugin_' . self::PLUGIN_FILE ),
			'info_url' => esc_url( self::info_link() ),
		);
	}

	/** (v1.10.0) دسترسی عمومی به داده به‌روزرسانی — برای بنر هاب «Command Center» */
	public function payload_public() {
		return $this->update_payload();
	}

	/** لینک مودال بومی وردپرس «نمایش جزئیات نسخه» */
	protected static function info_link() {
		return add_query_arg( array(
			'tab'           => 'plugin-information',
			'plugin'        => self::PLUGIN_DIR,
			'section'       => 'changelog',
			'TB_iframe'     => 'true',
			'width'         => 600,
			'height'        => 420,
		), self_admin_url( 'plugin-install.php' ) );
	}

	/* ==================================================================
	 * اتصال به مکانیزم بومی به‌روزرسانی وردپرس
	 * ================================================================== */

	/** به‌روزرسانی را به «update_plugins» transient بومی تزریق می‌کند تا در فهرست افزونه‌ها و «به‌روزرسانی‌ها» دیده شود */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}
		// کش قدیمی و فعلی را هم مشخص می‌کنیم تا وردپرس درست رنگ بزند
		if ( ! isset( $transient->checked[ self::PLUGIN_FILE ] ) ) {
			$transient->checked[ self::PLUGIN_FILE ] = DASTYARC_VERSION;
		}
		$m = $this->manifest();
		if ( $m && '' !== ( $m['version'] ?? '' ) && '' !== ( $m['package'] ?? '' ) && version_compare( (string) $m['version'], (string) $transient->checked[ self::PLUGIN_FILE ], '>' ) ) {
			$transient->response[ self::PLUGIN_FILE ] = (object) array(
				'id'           => 0,
				'slug'         => self::PLUGIN_DIR,
				'plugin'       => self::PLUGIN_FILE,
				'new_version'  => (string) $m['version'],
				'url'          => '',
				'package'      => esc_url_raw( (string) $m['package'] ),
				'dcu_priority' => (string) $m['label'], // برای مصرف‌کننده‌های بعدی
			);
			if ( isset( $transient->no_update[ self::PLUGIN_FILE ] ) ) {
				unset( $transient->no_update[ self::PLUGIN_FILE ] );
			}
		}
		return $transient;
	}

	/** مودال «نمایش جزئیات نسخه {x}» در فهرست افزونه‌ها (توضیحات + تغییرات + برچسب اولویت) */
	public function plugins_api( $res, $action, $args ) {
		if ( 'plugin_information' !== (string) $action || empty( $args->slug ) || self::PLUGIN_DIR !== (string) $args->slug ) {
			return $res;
		}
		$m = $this->manifest();
		if ( ! $m || '' === ( $m['version'] ?? '' ) ) {
			return $res;
		}
		$changelog_html = '';
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $m['changelog'] ) as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				$changelog_html .= '<li>' . esc_html( $line ) . '</li>';
			}
		}
		if ( '' === $changelog_html ) {
			$changelog_html = '<li>' . esc_html( (string) $m['message'] ) . '</li>';
		}
		$desc = sprintf(
			'<p><strong>برچسب اولویت: %s</strong> — نسخه بومی منتشرشده توسط مرکز دستیار شاپ</p><p>%s</p>',
			esc_html( (string) $m['label_title'] ),
			esc_html( (string) $m['message'] )
		);
		return (object) array(
			'name'          => 'Dastyar Connector — اتصال‌دهنده فروشنده به دستیار شاپ',
			'slug'          => self::PLUGIN_DIR,
			'version'       => (string) $m['version'],
			'author'        => '<a href="https://dastyarshop.com">Dastyar Shop</a>',
			'homepage'      => '',
			'requires'      => '6.0',
			'tested'        => get_bloginfo( 'version' ),
			'last_updated'  => (string) $m['released'],
			'download_link' => esc_url_raw( (string) $m['package'] ),
			'sections'      => array(
				'description' => $desc,
				'changelog'   => '<h4>نسخه ' . esc_html( (string) $m['version'] ) . '</h4><ul>' . $changelog_html . '</ul>',
			),
			'banners'       => array(),
			'icons'         => array(),
			'rating'        => 100,
			'num_ratings'   => 1,
		);
	}

	/** پوشه نصب‌شده از ZIP نسخه‌دار (`dastyar-connector-1.9.3.zip`) را به نام درست `dastyar-connector` برمی‌گرداند */
	public function fix_source_dir( $source, $remote_source, $upgrader ) {
		if ( ! isset( $upgrader->skin ) || empty( $upgrader->skin->options )
			|| empty( $upgrader->skin->options['type'] ) || 'plugin' !== (string) $upgrader->skin->options['type']
		) {
			return $source;
		}
		// تشخیص ارتقای «ما» (هم حالت تکی هم داخل به‌روزرسانی دسته‌جمعی): Plugin_Upgrader_Skin plugin_info را پر می‌کند
		$plugin_upgrading = '';
		$skin             = $upgrader->skin;
		if ( isset( $skin->plugin_info ) && is_array( $skin->plugin_info ) && ! empty( $skin->plugin_info['plugin'] ) ) {
			$plugin_upgrading = (string) $skin->plugin_info['plugin'];
		} elseif ( ! empty( $skin->options['plugin'] ) ) {
			$plugin_upgrading = (string) $skin->options['plugin'];
		}
		if ( self::PLUGIN_FILE !== $plugin_upgrading ) {
			return $source;
		}
		// پوشه درست دارد؟ دست نمی‌زنیم
		if ( basename( $source ) === self::PLUGIN_DIR ) {
			return $source;
		}
		global $wp_filesystem;
		$corrected = trailingslashit( (string) $remote_source ) . self::PLUGIN_DIR . '/';
		if ( $wp_filesystem->move( $source, $corrected, true ) ) {
			DastyarC::log( 'Update source folder renamed to ' . self::PLUGIN_DIR );
			return $corrected;
		}
		return $source;
	}

	/* ==================================================================
	 * نمایش‌ها
	 * ================================================================== */

	/** اعلان در «نوار ابزار بالای پیشخوان» — قرص رنگی بر اساس برچسب + لینک‌های به‌روزرسانی و تغییرات */
	public function admin_bar( $bar ) {
		if ( ! $bar || ! ( current_user_can( 'update_plugins' ) || current_user_can( 'manage_woocommerce' ) ) ) {
			return;
		}
		$p = $this->update_payload();
		if ( ! $p ) {
			return;
		}
		$tooltip = sprintf( 'نسخه جدید %s افزونه اتصال دستیار شاپ منتشر شده است (برچسب: %s). %s',
			$p['version'],
			$p['title'],
			'' !== $p['message'] ? $p['message'] : 'برای مشاهده تغییرات و نصب عبارت زیر را انتخاب کنید.'
		);
		$bar->add_node( array(
			'id'    => 'dastyarc-update',
			'title' => sprintf( '<span class="dcu-ab-badge dcu-lv-%s"><i class="dcu-ab-dot" aria-hidden="true"></i> به‌روزرسانی دستیارشاپ: %s%s</span>',
				esc_attr( $p['label'] ),
				esc_html( $p['title'] ),
				esc_html( ' (' . $p['version'] . ')' )
			),
			'href'  => $p['url'],
			'meta'  => array( 'title' => $tooltip, 'class' => 'dcu-ab-root' ),
		) );
		$bar->add_node( array(
			'parent' => 'dastyarc-update',
			'id'     => 'dastyarc-update-install',
			'title'  => sprintf( '⬇ همین حالا به‌روزرسانی به %s', $p['version'] ),
			'href'   => $p['url'],
		) );
		$bar->add_node( array(
			'parent' => 'dastyarc-update',
			'id'     => 'dastyarc-update-changes',
			'title'  => '📋 مشاهده تغییرات این نسخه',
			'href'   => $p['info_url'],
			'meta'   => array( 'class' => 'thickbox' ),
		) );
	}

	/** استایل قرص اعلان نوارابزار (هم پیشخوان هم نوار فرانت اگر نمایش داده شود) */
	public function bar_css() {
		if ( ! is_admin_bar_showing() || ! $this->has_update() ) {
			return;
		}
		?>
		<style id="dcu-ab-css">
		#wp-admin-bar-dastyarc-update .dcu-ab-badge{display:inline-flex!important;align-items:center;gap:6px;margin-top:4px;padding:2px 12px;border-radius:999px;background:#4b4f57;color:#fff;font-weight:700;font-size:12px;line-height:1.7;box-shadow:0 2px 10px rgba(0,0,0,.25)}
		#wp-admin-bar-dastyarc-update .dcu-ab-dot{width:8px;height:8px;border-radius:50%;background:#7ac943;display:inline-block}
		#wp-admin-bar-dastyarc-update .dcu-lv-normal{background:#2f6f9f}
		#wp-admin-bar-dastyarc-update .dcu-lv-important{background:#d98a1f}
		#wp-admin-bar-dastyarc-update .dcu-lv-critical{background:#ce3c3c}
		#wp-admin-bar-dastyarc-update .dcu-lv-urgent{background:#b32d2e;animation:dcuPulse 1.1s infinite}
		#wp-admin-bar-dastyarc-update .dcu-lv-important .dcu-ab-dot{background:#ffd867}
		#wp-admin-bar-dastyarc-update .dcu-lv-critical .dcu-ab-dot{background:#ffc9c9}
		#wp-admin-bar-dastyarc-update .dcu-lv-urgent .dcu-ab-dot{background:#fff}
		@keyframes dcuPulse{0%,100%{box-shadow:0 0 0 0 rgba(179,45,46,.55)}50%{box-shadow:0 0 0 9px rgba(179,45,46,0)}}
		#wpadminbar #wp-admin-bar-dastyarc-update a:focus>.dcu-ab-badge,#wpadminbar #wp-admin-bar-dastyarc-update:hover>.ab-item .dcu-ab-badge{filter:brightness(1.13)}
		</style>
		<?php
	}

	/** هشدار ماندگار پیشخوان بر اساس برچسب: «ضروری»/«فوری» همه‌جا ماندگار، «مهم» فقط صفحه افزونه‌ها */
	public function urgent_notice() {
		$p = $this->update_payload();
		if ( ! $p ) {
			return;
		}
		global $pagenow;
		$is_plugins_screen = in_array( (string) $pagenow, array( 'plugins.php', 'update-core.php' ), true );
		if ( 'important' === $p['label'] && ! $is_plugins_screen ) {
			return;
		}
		if ( 'normal' === $p['label'] ) {
			return;
		}
		$strong = in_array( $p['label'], array( 'critical', 'urgent' ), true );
		?>
		<div class="notice notice-error dcu-urgent-notice<?php echo $strong ? '' : ' is-dismissible'; ?>" style="border-right-width:6px;padding:14px 16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
			<div style="flex:1 1 340px">
				<strong style="font-size:13px">⬆ به‌روزرسانی افزونه «اتصال دستیار شاپ» — برچسب: <?php echo esc_html( $p['title'] ); ?></strong>
				<p style="margin:6px 0 0">نسخه <code dir="ltr"><?php echo esc_html( $p['version'] ); ?></code> منتشر شده است.
					<?php echo '' !== $p['message'] ? esc_html( $p['message'] ) : ''; ?>
					<?php echo $strong ? '<br><small>این اعلان تا انجام به‌روزرسانی نمایش داده می‌شود و قابل بستن نیست.</small>' : ''; ?>
				</p>
			</div>
			<div style="display:flex;gap:8px;flex-wrap:wrap">
				<a class="button button-primary" style="background:#b32d2e;border-color:#b32d2e" href="<?php echo esc_url( $p['url'] ); ?>">⬇ همین حالا به‌روزرسانی</a>
				<a class="button thickbox" href="<?php echo esc_url( $p['info_url'] ); ?>">مشاهده تغییرات</a>
			</div>
		</div>
		<?php
	}

	/* ==================================================================
	 * نگه‌داشت کش
	 * ================================================================== */

	/** تازه‌سازی کش با قفل ۳۰دقیقه‌ای و فقط روی صفحاتی که به‌روزرسانی معنادار است */
	public function maybe_refresh() {
		global $pagenow;
		$interesting = in_array( (string) $pagenow, array( 'index.php', 'plugins.php', 'update-core.php', 'update.php' ), true )
			|| ( 'admin.php' === (string) $pagenow && 'dastyarc-hub' === (string) ( $_GET['page'] ?? '' ) );
		if ( ! $interesting || get_transient( self::LOCK_KEY ) ) {
			return;
		}
		set_transient( self::LOCK_KEY, 1, 30 * MINUTE_IN_SECONDS );
		$this->manifest( true );
	}

	/** بعد از به‌روزرسانی موفق افزونه ما، کش مانیفست پاک می‌شود تا اعلان برود */
	public function after_upgrade( $upgrader, $options ) {
		if ( empty( $options['action'] ) || 'update' !== (string) $options['action']
			|| empty( $options['type'] ) || 'plugin' !== (string) $options['type']
			|| empty( $options['plugins'] ) || ! is_array( $options['plugins'] )
			|| ! in_array( self::PLUGIN_FILE, $options['plugins'], true )
		) {
			return;
		}
		self::purge();
		DastyarC::log( 'Connector updated via built-in updater' );
	}

	/** پاک‌سازی کشها — برای پوش وب‌هوک و آخر ارتقا */
	public static function purge() {
		delete_transient( self::CACHE_KEY );
		delete_transient( self::LOCK_KEY );
		self::$manifest = null;
	}

	/** دریافت پوش `connector.update` از مرکز (Listener) — کش را همان لحظه با محتوای جدید تازه می‌کند */
	public static function handle_push( array $data ) {
		$info = self::sanitize_manifest( $data );
		set_transient( self::CACHE_KEY, $info, self::CACHE_TTL );
		self::$manifest = $info;
		DastyarC::log( sprintf( 'Update manifest pushed by center: v%s (%s)', $info['version'], $info['label'] ) );
	}
}
