<?php
/**
 * Plugin Name:       دستیار بکاپ — پشتیبان‌گیری خودکار
 * Plugin URI:        https://dastyar.shop/
 * Description:       پشتیبان‌گیری خودکار روزانه از «همه سایت» (دیتابیس + فایل‌ها) روی خود هاست، با چرخه پاک‌شدن خودکار نسخه‌های قدیمی، دانلود با یک کلیک و راهنمای بازیابی قدم‌به‌قدم.
 * Version:           1.0.1
 * Author:            dastyar
 * License:           GPL-2.0-or-later
 * Text Domain:       dastyar-backup
 *
 * طراحی بر اساس انتخاب کاربر:
 *  ۱) نگه‌داشتن بکاپ‌ها روی هاست + پاک‌شدن خودکار قدیمی‌ها (فضا همیشه کم و ثابت)
 *  ۲) «همه‌چیز» در هر بکاپ: دیتابیس (database.sql) + wp-content + فایل‌های اصلی وردپرس
 *  ۳) بازیابی دستی با راهنمای تصویری ساده (داخل صفحه افزونه)
 *
 * نحوه کار موتور: برای این‌که روی هاست اشتراکی Timeout نخوریم، کار به مرحله‌های کوچک
 * تقسیم شده (دیتابیس ← فایل‌ها دسته‌های ۲۵۰تایی ← نهایی‌سازی) و بین هر مرحله یک رویداد
 * تکی WP-Cron چند ثانیه بعد خودش را ادامه می‌دهد.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'DBACKUP_VERSION', '1.0.1' );
define( 'DBACKUP_PLUGIN_FILE', __FILE__ );

/** کلاس اصلی افزونه پشتیبان‌گیری (تک‌فایل، سازگار با معماری دستیار) */
final class Dastyar_Backup {

	const OPTION    = 'dastyar_backup_settings';
	const STATE     = 'dastyar_backup_state';   // وضعیت اجرای جاری (autoload=خیر)
	const LOG       = 'dastyar_backup_log';     // ۲۰ رویداد آخیر
	const META      = 'dastyar_backup_meta';    // اطلاعات هر فایل بکاپ (حجم/MD5/زمان)
	const HOOK      = 'dastyar_backup_daily';   // کرون روزانه
	const STEPHOOK  = 'dastyar_backup_step';    // ادامه موتور
	const SLUG      = 'dastyar-backup';
	const PREFIX    = 'dastyar-backup-';
	const CAP       = 'manage_woocommerce';
	const BATCH     = 250;   // حداکثر فایل در هر مرحله
	const TIMEBUDGET = 20;   // سقف زمانی هر مرحله (ثانیه)

	protected static $booted       = false;
	protected static $last_skipped = 0; // فایل‌های خیلی حجیم که رد شدند

	public static function boot() {
		if ( self::$booted ) { return; }
		self::$booted = true;

		register_activation_hook( DBACKUP_PLUGIN_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( DBACKUP_PLUGIN_FILE, array( __CLASS__, 'deactivate' ) );

		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 25 );
		add_action( self::HOOK, array( __CLASS__, 'cron_start' ) );
		add_action( self::STEPHOOK, array( __CLASS__, 'run_step' ) );

		add_action( 'admin_post_dbackup_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_dbackup_run', array( __CLASS__, 'handle_run' ) );
		add_action( 'admin_post_dbackup_cancel', array( __CLASS__, 'handle_cancel' ) );
		add_action( 'admin_post_dbackup_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_dbackup_download', array( __CLASS__, 'handle_download' ) );
	}

	/* ------------------------------------------------------------------
	 * فعال‌سازی / غیرفعال‌سازی
	 * ---------------------------------------------------------------- */

	public static function activate() {
		$cur = get_option( self::OPTION, array() );
		update_option( self::OPTION, wp_parse_args( is_array( $cur ) ? $cur : array(), self::defaults() ) );
		self::protect_dir();
		self::schedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::STEPHOOK );
	}

	/* ------------------------------------------------------------------
	 * تنظیمات
	 * ---------------------------------------------------------------- */

	public static function defaults() {
		return array(
			'enabled'   => 'yes', // پشتیبان‌گیری خودکار روزانه
			'hour'      => 3,     // ساعت اجرا (۰ تا ۲۳ — پیش‌فرض ۳ بامداد)
			'keep'      => 3,     // چند نسخه اخیر نگه داشته شود (۱ تا ۷)
			'budget_mb' => 300,   // سقف فضای کل بکاپ‌ها (مگابایت)
			'with_core' => 'yes', // فایل‌های اصلی وردپرس هم داخل بکاپ
			'mail_fail' => 'yes', // ایمیل هشدار به مدیر اگر خطا رخ داد
		);
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/* ------------------------------------------------------------------
	 * مسیرها و محافظت از پوشه بکاپ‌ها
	 * ---------------------------------------------------------------- */

	public static function root() {
		return rtrim( ABSPATH, '/' ) . '/';
	}

	public static function content_dir() {
		return defined( 'WP_CONTENT_DIR' ) ? rtrim( WP_CONTENT_DIR, '/' ) : self::root() . 'wp-content';
	}

	public static function backups_dir() {
		return self::content_dir() . '/dastyar-backups';
	}

	/** ساخت پوشه + فایل‌های محافظ (جلوگیری از دانلود مستقیم از وب) */
	protected static function protect_dir() {
		$dir = self::backups_dir();
		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0755, true ) ) { return false; }
		$ht = $dir . '/.htaccess';
		if ( ! file_exists( $ht ) ) {
			@file_put_contents( $ht, "# Dastyar Backup — no direct access\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" );
		}
		$idx = $dir . '/index.php';
		if ( ! file_exists( $idx ) ) {
			@file_put_contents( $idx, "<?php // Silence is golden.\n" );
		}
		return is_writable( $dir );
	}

	/* ------------------------------------------------------------------
	 * زمان‌بندی کرون
	 * ---------------------------------------------------------------- */

	/** اولین وقوع «ساعت تنظیمات» در آینده (زمان سرور سایت) */
	public static function next_ts( $hour ) {
		$hour = max( 0, min( 23, (int) $hour ) );
		$now  = time();
		$t    = strtotime( date( 'Y-m-d', $now ) . ' ' . str_pad( (string) $hour, 2, '0', STR_PAD_LEFT ) . ':00:00' );
		if ( $t <= $now ) {
			$t = strtotime( '+1 day', $t );
		}
		return $t;
	}

	public static function schedule() {
		$s = self::settings();
		wp_clear_scheduled_hook( self::HOOK );
		if ( 'yes' === $s['enabled'] ) {
			wp_schedule_event( self::next_ts( $s['hour'] ), 'daily', self::HOOK );
		}
	}

	/* ------------------------------------------------------------------
	 * منو و صفحه مدیریت
	 * ---------------------------------------------------------------- */

	public static function menu() {
		$cb    = array( __CLASS__, 'render' );
		$found = false;
		global $menu;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $m ) {
				// شناسه واقعی منوی مادر در هسته دستیار: dastyar (v1.0.1 — قبلاً اشتباهی dastyar-dashboard دنبال می‌شد و صفحه زیر «ابزارها» می‌افتاد)
				if ( isset( $m[2] ) && 'dastyar' === $m[2] ) { $found = true; break; }
			}
		}
		if ( $found ) {
			add_submenu_page( 'dastyar', 'پشتیبان‌گیری دستیار', 'پشتیبان‌گیری', self::CAP, self::SLUG, $cb );
		} else {
			add_management_page( 'پشتیبان‌گیری دستیار', 'پشتیبان‌گیری دستیار', self::CAP, self::SLUG, $cb );
		}
	}

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$s      = self::settings();
		$state  = get_option( self::STATE );
		$state  = is_array( $state ) ? $state : null;
		$logs   = self::log_get();
		$lastOk = null;
		foreach ( $logs as $l ) { if ( ! empty( $l['ok'] ) ) { $lastOk = $l; break; } }
		$list   = self::backups_list();
		$total  = 0;
		foreach ( $list as $b ) { $total += $b['size']; }
		$budget = max( 50, (int) $s['budget_mb'] ) * 1048576;
		$next   = wp_next_scheduled( self::HOOK );
		$msg    = isset( $_GET['dbackup_msg'] ) ? sanitize_key( wp_unslash( $_GET['dbackup_msg'] ) ) : '';
		$msgs   = array(
			'saved'     => 'تنظیمات ذخیره شد.',
			'started'   => 'پشتیبان‌گیری شروع شد؛ این صفحه هر چند ثانیه خودش تازه می‌شود تا تمام شود.',
			'busy'      => 'هم‌اکنون یک پشتیبان‌گیری در حال اجراست؛ صبر کنید تمام شود.',
			'failed'    => 'پشتیبان‌گیری شروع نشد — علت را در کادر قرمز بالای صفحه ببینید.',
			'cancelled' => 'اجرای جاری لغو شد.',
			'deleted'   => 'فایل بکاپ حذف شد.',
		);
		if ( $state ) {
			echo '<meta http-equiv="refresh" content="10">';
		}
		?>
<style>
.dbk-wrap{max-width:860px;margin:18px auto;font-family:inherit}
.dbk-card{background:#fff;border:1px solid #e0e3ea;border-radius:12px;padding:18px 20px;margin-bottom:16px;box-shadow:0 1px 3px rgba(36,37,54,.05)}
.dbk-card h2{font-size:15px;margin:0 0 12px;color:#242536;display:flex;align-items:center;gap:8px}
.dbk-grid{display:flex;flex-wrap:wrap;gap:12px}
.dbk-stat{flex:1;min-width:200px;background:#f7f8fb;border:1px solid #eceef3;border-radius:10px;padding:12px 14px}
.dbk-stat .t{font-size:12px;color:#7a8092;margin-bottom:4px}
.dbk-stat .v{font-size:16px;font-weight:bold;color:#242536}
.dbk-ok{color:#17a16d}
.dbk-bad{color:#c0392b}
.dbk-btn{display:inline-block;background:#17a16d;color:#fff!important;border:none;border-radius:8px;padding:10px 18px;font-size:13px;cursor:pointer;text-decoration:none}
.dbk-btn:hover{background:#12855a}
.dbk-btn.gray{background:#eef0f4;color:#242536!important;border:1px solid #dfe2ea}
.dbk-btn.red{background:#fdecec;color:#c0392b!important;border:1px solid #f3c9c9}
.dbk-bar{background:#eef0f4;border-radius:999px;height:14px;overflow:hidden}
.dbk-bar > span{display:block;height:100%;background:#17a16d;border-radius:999px}
table.dbk{width:100%;border-collapse:collapse;font-size:13px}
table.dbk th{background:#242536;color:#fff;padding:8px 10px;text-align:right;font-weight:normal}
table.dbk td{padding:9px 10px;border-bottom:1px solid #eef0f4;vertical-align:middle}
table.dbk tr:nth-child(even) td{background:#fafbfc}
.dbk-msg{background:#e7f6f0;border:1px solid #bfe8d8;color:#0f7a52;border-radius:8px;padding:10px 14px;margin-bottom:14px}
.dbk-err{background:#fdecec;border:1px solid #f3c9c9;color:#c0392b;border-radius:8px;padding:10px 14px;margin-bottom:14px}
.dbk-guide{counter-reset:step;list-style:none;margin:0;padding:0}
.dbk-guide li{position:relative;padding:8px 34px 8px 0;border-bottom:1px dashed #eceef3;font-size:13px;line-height:1.9}
.dbk-guide li:last-child{border-bottom:none}
.dbk-guide li:before{counter-increment:step;content:counter(step);position:absolute;right:0;top:10px;width:22px;height:22px;border-radius:50%;background:#17a16d;color:#fff;text-align:center;line-height:22px;font-size:12px}
.dbk-hint{font-size:12px;color:#8a8fa0;margin-top:10px}
.dbk-row{margin-bottom:12px}
.dbk-row label{display:inline-block;min-width:190px;font-weight:bold;color:#242536}
.dbk-row input[type=number]{width:90px}
.dbk-row .d{font-size:12px;color:#8a8fa0;margin-right:194px}
</style>
<div class="wrap dbk-wrap">
	<h1 style="color:#242536;">پشتیبان‌گیری دستیار <span style="font-size:12px;color:#8a8fa0;">نسخه <?php echo esc_html( DBACKUP_VERSION ); ?></span></h1>

	<?php if ( $msg && isset( $msgs[ $msg ] ) ) : ?>
		<div class="dbk-msg"><?php echo esc_html( $msgs[ $msg ] ); ?></div>
	<?php endif; ?>
	<?php if ( ! empty( $logs ) && empty( $logs[0]['ok'] ) ) : ?>
		<div class="dbk-err"><b>آخرین اجرا ناموفق بود (<?php echo esc_html( self::fa_dt( $logs[0]['t'] ) ); ?>):</b> <?php echo esc_html( $logs[0]['msg'] ); ?></div>
	<?php endif; ?>

	<?php if ( $state ) :
		$pct  = 5;
		$step = 'در حال کپی دیتابیس…';
		if ( 'files' === $state['step'] ) {
			$pct  = $state['total'] ? 5 + (int) round( 95 * $state['pos'] / max( 1, $state['total'] ) ) : 5;
			$step = 'در حال بکاپ از فایل‌ها…';
		} elseif ( 'finalize' === $state['step'] ) {
			$pct  = 98;
			$step = 'در حال نهایی‌سازی فایل زیپ…';
		}
		?>
	<div class="dbk-card">
		<h2>پشتیبان‌گیری در حال انجام است</h2>
		<p style="font-size:13px;"><?php echo esc_html( $step ); ?> (مرحله‌ها خودکار ادامه پیدا می‌کنند؛ این صفحه هر ۱۰ ثانیه تازه می‌شود)</p>
		<div class="dbk-bar"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
		<p class="dbk-hint">پیشرفت: <?php echo (int) $pct; ?>٪ — شروع: <?php echo esc_html( self::fa_dt( $state['started'] ) ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('اجرای جاری لغو شود؟');">
			<input type="hidden" name="action" value="dbackup_cancel">
			<?php wp_nonce_field( 'dbackup_cancel' ); ?>
			<button type="submit" class="dbk-btn red">لغو اجرای جاری</button>
		</form>
	</div>
	<?php endif; ?>

	<div class="dbk-card">
		<h2>وضعیت</h2>
		<div class="dbk-grid">
			<div class="dbk-stat">
				<div class="t">آخرین پشتیبان موفق</div>
				<div class="v"><?php echo $lastOk ? esc_html( self::fa_dt( $lastOk['t'] ) ) . ' <span class="dbk-ok">(' . esc_html( self::ago( $lastOk['t'] ) ) . ')</span>' : '<span class="dbk-bad">هنوز بکاپی نگرفته‌ایم</span>'; ?></div>
			</div>
			<div class="dbk-stat">
				<div class="t">اجرای خودکار بعدی</div>
				<div class="v"><?php echo $next ? esc_html( self::fa_dt( $next ) ) : '<span class="dbk-bad">غیرفعال</span>'; ?></div>
			</div>
			<div class="dbk-stat">
				<div class="t">فضای اشغال‌شده بکاپ‌ها</div>
				<div class="v"><?php echo esc_html( self::fa_size( $total ) ); ?> <span style="font-size:12px;color:#8a8fa0;">از سقف <?php echo esc_html( self::fa_size( $budget ) ); ?></span></div>
			</div>
		</div>
		<?php if ( ! $state ) : ?>
		<p style="margin-top:14px;">
			<a class="dbk-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbackup_run' ), 'dbackup_run' ) ); ?>" onclick="return confirm('پشتیبان‌گیری کامل همین حالا شروع شود؟');">همین حالا پشتیبان بگیر</a>
			<span class="dbk-hint" style="display:inline-block;margin-right:10px;">هر شب ساعت <?php echo (int) $s['hour']; ?> به‌صورت خودکار هم انجام می‌شود.</span>
		</p>
		<?php endif; ?>
	</div>

	<div class="dbk-card">
		<h2>فایل‌های پشتیبان (<?php echo count( $list ); ?> نسخه نگه‌داری می‌شود)</h2>
		<?php if ( ! $list ) : ?>
			<p class="dbk-hint">هنوز فایلی ساخته نشده است. اولین بکاپ خودکار امشب ساعت <?php echo (int) $s['hour']; ?> ساخته می‌شود — یا با دکمه بالا همین حالا بسازید.</p>
		<?php else : ?>
		<table class="dbk">
			<tr><th>تاریخ بکاپ</th><th>حجم</th><th>تعداد فایل</th><th>مدت ساخت</th><th>امضای فایل (MD5)</th><th>عملیات</th></tr>
			<?php foreach ( $list as $b ) : ?>
			<tr>
				<td><b><?php echo esc_html( $b['label'] ); ?></b><?php if ( ! empty( $b['manual'] ) ) : ?> <span style="color:#8a8fa0;font-size:11px;">(دستی)</span><?php endif; ?></td>
				<td><?php echo esc_html( self::fa_size( $b['size'] ) ); ?></td>
				<td><?php echo isset( $b['files'] ) ? esc_html( number_format( $b['files'] ) ) : '—'; ?></td>
				<td><?php echo isset( $b['secs'] ) ? esc_html( self::fa_secs( $b['secs'] ) ) : '—'; ?></td>
				<td style="direction:ltr;text-align:left;font-family:monospace;font-size:11px;"><?php echo ! empty( $b['md5'] ) ? esc_html( substr( $b['md5'], 0, 10 ) ) . '…' : '—'; ?></td>
				<td>
					<a class="dbk-btn gray" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbackup_download&file=' . rawurlencode( $b['base'] ) ), 'dbackup_dl_' . $b['base'] ) ); ?>">دانلود</a>
					<a class="dbk-btn red" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbackup_delete&file=' . rawurlencode( $b['base'] ) ), 'dbackup_del_' . $b['base'] ) ); ?>" onclick="return confirm('این بکاپ حذف شود؟');">حذف</a>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
		<?php endif; ?>
		<p class="dbk-hint">توصیه: هر هفته آخرین بکاپ را دانلود کنید و روی کامپیوتر خودتان هم نگه دارید.</p>
	</div>

	<div class="dbk-card">
		<h2>تنظیمات</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="dbackup_save">
			<?php wp_nonce_field( 'dbackup_save' ); ?>
			<div class="dbk-row">
				<label>پشتیبان‌گیری خودکار روزانه</label>
				<label style="min-width:0;font-weight:normal;"><input type="checkbox" name="enabled" value="yes" <?php checked( $s['enabled'], 'yes' ); ?>> فعال باشد</label>
			</div>
			<div class="dbk-row">
				<label>ساعت اجرای روزانه</label>
				<input type="number" name="hour" min="0" max="23" value="<?php echo (int) $s['hour']; ?>"> <span class="dbk-hint">۰ تا ۲۳ — ساعات خلوت (مثل ۳ بامداد) بهتر است. اگر سایت آن ساعت بازدید نداشته باشد، در اولین بازدید بعدش اجرا می‌شود.</span>
			</div>
			<div class="dbk-row">
				<label>تعداد نسخه‌های نگه‌داری‌شده</label>
				<input type="number" name="keep" min="1" max="7" value="<?php echo (int) $s['keep']; ?>">
				<div class="d">نسخه‌های قدیمی‌تر خودکار پاک می‌شوند تا فضای هاست همیشه ثابت و کم بماند.</div>
			</div>
			<div class="dbk-row">
				<label>سقف فضای بکاپ‌ها (مگابایت)</label>
				<input type="number" name="budget_mb" min="50" max="5000" step="50" value="<?php echo (int) $s['budget_mb']; ?>">
				<div class="d">اگر مجموع بکاپ‌ها از این سقف بیشتر شود، قدیمی‌ترین‌ها پاک می‌شوند (حداقل یک نسخه همیشه می‌ماند).</div>
			</div>
			<div class="dbk-row">
				<label>شامل فایل‌های اصلی وردپرس</label>
				<label style="min-width:0;font-weight:normal;"><input type="checkbox" name="with_core" value="yes" <?php checked( $s['with_core'], 'yes' ); ?>> پوشه‌های wp-admin و wp-includes هم داخل بکاپ باشند (برای بازگردانی کامل «همه‌چیز»)</label>
			</div>
			<div class="dbk-row">
				<label>هشدار خطا با ایمیل</label>
				<label style="min-width:0;font-weight:normal;"><input type="checkbox" name="mail_fail" value="yes" <?php checked( $s['mail_fail'], 'yes' ); ?>> اگر پشتیبان‌گیری خطا داد، به ایمیل مدیر سایت خبر بده</label>
			</div>
			<?php submit_button( 'ذخیره تنظیمات' ); ?>
		</form>
	</div>

	<div class="dbk-card">
		<h2>راهنمای بازیابی (وقتی سایت مشکل پیدا کرد و خواستید به یک روز قبل برگردید)</h2>
		<ol class="dbk-guide">
			<li>از جدول بالا روی <b>«دانلود»</b> بزنید تا فایل زیپ بکاپ روی کامپیوترتان ذخیره شود.</li>
			<li>وارد پنل هاست شوید (cPanel یا DirectAdmin — همان جایی که هاست را خریده‌اید).</li>
			<li>به بخش <b>File Manager</b> ← پوشه <b>public_html</b> بروید؛ زیپ را آپلود کنید، روی آن راست‌کلیک کنید ← <b>Extract</b> ← گزینه «جایگزینی فایل‌های موجود» را تایید کنید.</li>
			<li>همان فایل زیپ را روی کامپیوتر خودتان باز کنید و فایل <b>database.sql</b> را از داخل آن بیرون بکشید.</li>
			<li>در پنل هاست ← <b>phpMyAdmin</b> ← از ستون چپ روی نام دیتابیس سایت کلیک کنید ← تب <b>Import</b> ← فایل database.sql را انتخاب کنید ← دکمه <b>Go</b>. صبر کنید تا پیام سبز «Import successfully» بیاید.</li>
			<li>سایت را باز کنید؛ همه‌چیز باید دقیقا به همان روزی برگردد که این بکاپ گرفته شده بود. اگر وارد پیشخوان نشدید، یک بار خروج/ورود کنید.</li>
		</ol>
		<p class="dbk-hint">اگر در هر قدمی گیر کردید، تازه‌ترین بکاپ را نگه دارید و به تیم پشتیبانی بگویید — بازیابی را با هم انجام می‌دهیم.</p>
	</div>

	<?php if ( $logs ) : ?>
	<div class="dbk-card">
		<h2>گزارش اجراها</h2>
		<table class="dbk">
			<tr><th>زمان</th><th>نتیجه</th><th>توضیح</th></tr>
			<?php foreach ( array_slice( $logs, 0, 8 ) as $l ) : ?>
			<tr>
				<td><?php echo esc_html( self::fa_dt( $l['t'] ) ); ?></td>
				<td><?php echo ! empty( $l['ok'] ) ? '<span class="dbk-ok">موفق</span>' : '<span class="dbk-bad">ناموفق</span>'; ?></td>
				<td><?php echo esc_html( $l['msg'] ); ?><?php echo ! empty( $l['file'] ) ? ' (' . esc_html( $l['file'] ) . ')' : ''; ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
	</div>
	<?php endif; ?>
</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * هندلرهای فرم
	 * ---------------------------------------------------------------- */

	protected static function back( $msg ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&dbackup_msg=' . $msg ) );
		exit;
	}

	protected static function guard( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( $nonce_action );
	}

	public static function handle_save() {
		self::guard( 'dbackup_save' );
		$s   = self::defaults();
		$old = self::settings();
		$in  = wp_unslash( $_POST ); // phpcs:ignore -- سطح دسترسی بالا بررسی شد
		$s['enabled']   = ( isset( $in['enabled'] ) && 'yes' === $in['enabled'] ) ? 'yes' : 'no';
		$s['hour']      = max( 0, min( 23, (int) ( $in['hour'] ?? $old['hour'] ) ) );
		$s['keep']      = max( 1, min( 7, (int) ( $in['keep'] ?? $old['keep'] ) ) );
		$s['budget_mb'] = max( 50, min( 5000, (int) ( $in['budget_mb'] ?? $old['budget_mb'] ) ) );
		$s['with_core'] = ( isset( $in['with_core'] ) && 'yes' === $in['with_core'] ) ? 'yes' : 'no';
		$s['mail_fail'] = ( isset( $in['mail_fail'] ) && 'yes' === $in['mail_fail'] ) ? 'yes' : 'no';
		update_option( self::OPTION, $s );
		self::schedule();
		self::back( 'saved' );
	}

	public static function handle_run() {
		self::guard( 'dbackup_run' );
		$r = self::start( true );
		self::back( in_array( $r, array( 'started', 'busy', 'failed' ), true ) ? $r : 'failed' );
	}

	public static function handle_cancel() {
		self::guard( 'dbackup_cancel' );
		$st = get_option( self::STATE );
		if ( is_array( $st ) ) {
			self::cleanup( $st );
			delete_option( self::STATE );
			self::log_add( false, 'اجرای پشتیبان‌گیری به‌صورت دستی لغو شد.', '' );
		}
		self::back( 'cancelled' );
	}

	public static function handle_delete() {
		$base = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
		self::guard( 'dbackup_del_' . $base );
		$path = self::file_path( $base );
		if ( $path && is_file( $path ) ) {
			@unlink( $path );
			self::meta_del( $base );
		}
		self::back( 'deleted' );
	}

	public static function handle_download() {
		$base = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'dbackup_dl_' . $base ) ) {
			wp_die( 'لینک دانلود معتبر نیست.' );
		}
		$path = self::file_path( $base );
		if ( ! $path || ! is_file( $path ) ) {
			wp_die( 'فایل پیدا نشد (شاید در چرخه پاک‌شدن حذف شده است).' );
		}
		if ( ! defined( 'DASTYAR_BACKUP_TESTING' ) ) {
			if ( function_exists( 'nocache_headers' ) ) { nocache_headers(); }
			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . $base . '"' );
			header( 'Content-Length: ' . filesize( $path ) );
		}
		readfile( $path ); // phpcs:ignore
		if ( ! defined( 'DASTYAR_BACKUP_TESTING' ) ) {
			exit;
		}
	}

	/** مسیر امن یک فایل بکاپ از روی نام آن (فقط فایل‌های خودمان در پوشه خودمان) */
	protected static function file_path( $base ) {
		if ( ! is_string( $base ) || ! preg_match( '/^' . preg_quote( self::PREFIX, '/' ) . '\d{8}-\d{6}\.zip$/', $base ) ) {
			return '';
		}
		$path = self::backups_dir() . '/' . $base;
		$real = realpath( $path );
		if ( ! $real || 0 !== strpos( $real, realpath( self::backups_dir() ) ) ) {
			return '';
		}
		return $real;
	}

	/* ------------------------------------------------------------------
	 * موتور پشتیبان‌گیری (مرحله‌ای — تن‌خور نیست)
	 * ---------------------------------------------------------------- */

	public static function cron_start() {
		$s = self::settings();
		if ( 'yes' !== $s['enabled'] ) { return; }
		if ( get_option( self::STATE ) ) { return; }
		self::start( false );
	}

	/** شروع یک اجرا: 'started' | 'busy' | 'failed' (خطای هم‌راستا — جزئیات در گزارش/کادر قرمز صفحه) */
	public static function start( $manual ) {
		if ( get_option( self::STATE ) ) {
			return 'busy';
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			self::log_add( false, 'افزونه Zip روی PHP هاست فعال نیست؛ از هاستینگ بخواهید آن را فعال کند.', '' );
			return 'failed';
		}
		if ( ! self::protect_dir() ) {
			self::fail( array(), 'پوشه بکاپ قابل نوشتن نیست — دسترسی پوشه wp-content را در هاست بررسی کنید.' );
			return 'failed';
		}
		$slug  = self::PREFIX . date( 'Ymd-His' );
		$state = array(
			'slug'    => $slug,
			'tmp'     => self::backups_dir() . '/in-progress-' . wp_generate_password( 10, false ) . '.zip',
			'sql'     => self::backups_dir() . '/' . $slug . '-db.sql',
			'step'    => 'db',
			'list'    => array(),
			'pos'     => 0,
			'total'   => 0,
			'started' => time(),
			'manual'  => $manual ? 1 : 0,
		);
		update_option( self::STATE, $state, false );
		wp_schedule_single_event( time() + 2, self::STEPHOOK );
		self::run_step(); // مرحله نخست همین‌جا؛ اگر همین‌جا خطا بدهد وضعیت پاک می‌شود
		return get_option( self::STATE ) ? 'started' : 'failed';
	}

	/** اجرای یک مرحله از موتور (خودش مرحله بعد را زمان‌بندی می‌کند) */
	public static function run_step() {
		$st = get_option( self::STATE );
		if ( ! is_array( $st ) || empty( $st['step'] ) ) { return; }
		if ( 'db' === $st['step'] ) {
			self::step_db( $st );
		} elseif ( 'files' === $st['step'] ) {
			self::step_files( $st );
		} elseif ( 'finalize' === $st['step'] ) {
			self::step_finalize( $st );
		}
	}

	protected static function save_state( $st ) {
		update_option( self::STATE, $st, false );
	}

	/** مرحله دیتابیس: ساخت database.sql + ساخت لیست فایل‌ها */
	protected static function step_db( $st ) {
		if ( ! self::dump_database( $st['sql'] ) ) {
			self::fail( $st, 'خواندن دیتابیس ممکن نشد — حافظه یا دسترسی کافی نیست.' );
			return;
		}
		self::$last_skipped = 0;
		$st['list']  = self::build_manifest();
		$st['total'] = count( $st['list'] );
		$st['step']  = 'files';
		self::save_state( $st );
		wp_schedule_single_event( time() + 2, self::STEPHOOK );
	}

	/** مرحله فایل‌ها: دسته‌ای اضافه‌کردن به زیپ */
	protected static function step_files( $st ) {
		$t0   = microtime( true );
		$zip  = new ZipArchive();
		$open = $zip->open( $st['tmp'], ZipArchive::CREATE );
		if ( true !== $open ) {
			self::fail( $st, 'ساخت فایل زیپ ناموفق بود (کد ' . ( is_scalar( $open ) ? $open : 'نامشخص' ) . ') — فضای خالی دیسک یا دسترسی نوشتن را در هاست بررسی کنید.' );
			return;
		}
		$root = self::root();
		$done = 0;
		while ( $st['pos'] < $st['total'] ) {
			$rel  = $st['list'][ $st['pos'] ];
			$full = $root . $rel;
			if ( is_file( $full ) && is_readable( $full ) ) {
				$zip->addFile( $full, $rel );
				$done++;
			}
			$st['pos']++;
			if ( $done >= self::BATCH ) { break; }
			if ( microtime( true ) - $t0 > self::TIMEBUDGET ) { break; }
		}
		$zip->close();
		if ( $st['pos'] >= $st['total'] ) {
			$st['step'] = 'finalize';
		}
		self::save_state( $st );
		wp_schedule_single_event( time() + 2, self::STEPHOOK );
	}

	/** مرحله نهایی: دیتابیس داخل زیپ + انتقال به نام نهایی + چرخه پاک‌سازی */
	protected static function step_finalize( $st ) {
		$zip  = new ZipArchive();
		$open = $zip->open( $st['tmp'], ZipArchive::CREATE );
		if ( true !== $open ) {
			self::fail( $st, 'بازکردن فایل زیپ برای نهایی‌سازی ممکن نشد.' );
			return;
		}
		if ( is_file( $st['sql'] ) ) {
			$zip->addFile( $st['sql'], 'database.sql' );
		}
		$zip->setArchiveComment( 'Dastyar Backup v' . DBACKUP_VERSION . ' — ' . date( 'Y-m-d H:i:s' ) );
		if ( ! $zip->close() ) {
			self::fail( $st, 'ذخیره نهایی فایل زیپ کامل نشد — احتمالاً فضای دیسک پر است.' );
			return;
		}
		if ( is_file( $st['sql'] ) ) { @unlink( $st['sql'] ); }

		$final = self::backups_dir() . '/' . $st['slug'] . '.zip';
		if ( ! @rename( $st['tmp'], $final ) ) {
			if ( ! @copy( $st['tmp'], $final ) ) {
				self::fail( $st, 'انتقال فایل بکاپ به نام نهایی ممکن نشد.' );
				return;
			}
			@unlink( $st['tmp'] );
		}

		$base = basename( $final );
		$secs = max( 0, time() - $st['started'] );
		self::meta_set( $base, array(
			'size'   => (int) filesize( $final ),
			'md5'    => (string) md5_file( $final ),
			'time'   => time(),
			'files'  => (int) $st['total'],
			'secs'   => $secs,
			'manual' => (int) $st['manual'],
		) );
		$msg = 'پشتیبان‌گیری ' . ( $st['manual'] ? 'دستی' : 'خودکار' ) . ' کامل شد — ' . self::fa_size( (int) filesize( $final ) ) . ' در ' . self::fa_secs( $secs ) . '.';
		if ( self::$last_skipped > 0 ) {
			$msg .= ' (' . self::$last_skipped . ' فایل خیلی حجیم داخل بکاپ نیامد)';
		}
		self::log_add( true, $msg, $base );
		self::rotate();
		self::meta_prune();
		delete_option( self::STATE );
	}

	/** پاک‌سازی موقت‌ها هنگام خطا یا لغو */
	protected static function cleanup( $st ) {
		if ( ! empty( $st['tmp'] ) && is_file( $st['tmp'] ) ) { @unlink( $st['tmp'] ); }
		if ( ! empty( $st['sql'] ) && is_file( $st['sql'] ) ) { @unlink( $st['sql'] ); }
	}

	/** خطا: لاگ + پاک‌سازی + ایمیل هشدار */
	protected static function fail( $st, $msg ) {
		self::log_add( false, $msg, '' );
		if ( is_array( $st ) ) { self::cleanup( $st ); }
		delete_option( self::STATE );
		$s = self::settings();
		if ( 'yes' === $s['mail_fail'] ) {
			$to = get_option( 'admin_email' );
			if ( is_string( $to ) && $to ) {
				wp_mail( $to, 'خطا در پشتیبان‌گیری خودکار سایت', "بکاپ امروز کامل نشد.\n\nعلت: {$msg}\n\nصفحه پشتیبان‌گیری: " . admin_url( 'admin.php?page=' . self::SLUG ) );
			}
		}
	}

	/** چرخه حذف قدیمی‌ها: هم بر اساس تعداد، هم سقف فضا */
	protected static function rotate() {
		$s    = self::settings();
		$keep = max( 1, min( 7, (int) $s['keep'] ) );
		$list = self::backups_list();
		while ( count( $list ) > $keep ) {
			$old = array_pop( $list );
			@unlink( $old['path'] );
			self::meta_del( $old['base'] );
		}
		$budget = max( 50, (int) $s['budget_mb'] ) * 1048576;
		$total  = 0;
		foreach ( $list as $b ) { $total += $b['size']; }
		while ( $total > $budget && count( $list ) > 1 ) {
			$old = array_pop( $list );
			@unlink( $old['path'] );
			self::meta_del( $old['base'] );
			$total -= $old['size'];
		}
	}

	/* ------------------------------------------------------------------
	 * لیست فایل‌ها (با حذف پوشه بکاپ خودمان، کش و فایل‌های زائد)
	 * ---------------------------------------------------------------- */

	protected static function build_manifest() {
		$s     = self::settings();
		$root  = self::root();
		$files = array();

		foreach ( (array) glob( $root . '*' ) as $f ) {
			if ( is_file( $f ) && self::keep_file( basename( $f ) ) ) {
				$files[] = basename( $f );
			}
		}

		$dirs = array( 'wp-content' );
		if ( 'yes' === $s['with_core'] ) {
			$dirs[] = 'wp-admin';
			$dirs[] = 'wp-includes';
		}
		foreach ( $dirs as $d ) {
			$base = $root . $d;
			if ( ! is_dir( $base ) ) { continue; }
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ( $it as $path => $info ) {
				if ( ! $info->isFile() ) { continue; }
				$rel = $d . str_replace( DIRECTORY_SEPARATOR, '/', substr( (string) $path, strlen( $base ) ) );
				if ( ! self::keep_path( $rel ) || ! self::keep_file( basename( $rel ) ) ) { continue; }
				if ( $info->getSize() > 209715200 ) { // فایل‌های بالای ۲۰۰ مگ
					self::$last_skipped++;
					continue;
				}
				$files[] = $rel;
			}
		}
		sort( $files );
		return $files;
	}

	protected static function keep_file( $name ) {
		$skip = array( 'error_log', 'php_errorlog', '.ds_store', 'thumbs.db', 'debug.log' );
		$n    = strtolower( (string) $name );
		if ( in_array( $n, $skip, true ) ) { return false; }
		if ( '.tmp' === substr( $n, -4 ) ) { return false; }
		return true;
	}

	protected static function keep_path( $rel ) {
		$skip_prefixes = array(
			'wp-content/dastyar-backups/',
			'wp-content/cache/',
			'wp-content/upgrade/',
			'wp-content/backups',
			'wp-content/updraft',
			'wp-content/ai1wm-backups',
		);
		foreach ( $skip_prefixes as $p ) {
			if ( 0 === strpos( $rel, $p ) ) { return false; }
		}
		return true;
	}

	/* ------------------------------------------------------------------
	 * خروجی دیتابیس (بدون نیاز به mysqldump — PHP خالص)
	 * ---------------------------------------------------------------- */

	protected static function dump_database( $path ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) { return false; }
		$fh = @fopen( $path, 'wb' );
		if ( ! $fh ) { return false; }

		$write = function( $s ) use ( $fh ) { return false !== fwrite( $fh, $s ); };
		$write( "-- Dastyar Backup DB dump — " . date( 'Y-m-d H:i:s' ) . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n" );

		$tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( ! is_array( $tables ) || ! $tables ) { fclose( $fh ); return false; }

		$esc = function( $v ) use ( $wpdb ) {
			if ( null === $v ) { return 'NULL'; }
			$s = (string) $v;
			return "'" . ( method_exists( $wpdb, '_real_escape' ) ? $wpdb->_real_escape( $s ) : addslashes( $s ) ) . "'";
		};

		foreach ( $tables as $t ) {
			$t      = (string) $t;
			$create = $wpdb->get_row( "SHOW CREATE TABLE `$t`", ARRAY_N );
			$write( "\nDROP TABLE IF EXISTS `$t`;\n" );
			if ( is_array( $create ) && ! empty( $create[1] ) ) {
				$write( (string) $create[1] . ";\n" );
			}
			$cols     = $wpdb->get_col( "SHOW COLUMNS FROM `$t`" );
			$cols_sql = ( is_array( $cols ) && $cols ) ? ' (`' . implode( '`,`', array_map( 'strval', $cols ) ) . '`)' : '';

			$offset  = 0;
			$lim     = 200;
			$per_ins = 100;
			while ( true ) {
				$rows = $wpdb->get_results( "SELECT * FROM `$t` LIMIT " . (int) $offset . ',' . (int) $lim, ARRAY_N );
				if ( ! is_array( $rows ) || ! $rows ) { break; }
				$batch = array();
				foreach ( $rows as $r ) {
					$vals    = array_map( $esc, array_values( (array) $r ) );
					$batch[] = '(' . implode( ',', $vals ) . ')';
					if ( count( $batch ) >= $per_ins ) {
						$write( "INSERT INTO `$t`$cols_sql VALUES\n" . implode( ",\n", $batch ) . ";\n" );
						$batch = array();
					}
				}
				if ( $batch ) {
					$write( "INSERT INTO `$t`$cols_sql VALUES\n" . implode( ",\n", $batch ) . ";\n" );
				}
				if ( count( $rows ) < $lim ) { break; }
				$offset += $lim;
			}
		}
		$write( "\nSET FOREIGN_KEY_CHECKS=1;\n" );
		fclose( $fh );
		return true;
	}

	/* ------------------------------------------------------------------
	 * فهرست بکاپ‌ها / متا / لاگ
	 * ---------------------------------------------------------------- */

	public static function backups_list() {
		$dir   = self::backups_dir();
		$paths = is_dir( $dir ) ? (array) glob( $dir . '/' . self::PREFIX . '*.zip' ) : array();
		rsort( $paths );
		$out  = array();
		$meta = (array) get_option( self::META, array() );
		foreach ( $paths as $p ) {
			$base = basename( $p );
			$m    = isset( $meta[ $base ] ) && is_array( $meta[ $base ] ) ? $meta[ $base ] : array();
			$ts   = isset( $m['time'] ) ? (int) $m['time'] : (int) @filemtime( $p );
			$dt   = DateTime::createFromFormat( 'Ymd-His', substr( $base, strlen( self::PREFIX ), 15 ) );
			$out[] = array(
				'path'   => $p,
				'base'   => $base,
				'label'  => $dt ? $dt->format( 'Y/m/d — H:i' ) : self::fa_dt( $ts ),
				'size'   => isset( $m['size'] ) ? (int) $m['size'] : (int) @filesize( $p ),
				'md5'    => isset( $m['md5'] ) ? (string) $m['md5'] : '',
				'files'  => isset( $m['files'] ) ? (int) $m['files'] : 0,
				'secs'   => isset( $m['secs'] ) ? (int) $m['secs'] : 0,
				'manual' => isset( $m['manual'] ) ? (int) $m['manual'] : 0,
				'time'   => $ts,
			);
		}
		return $out;
	}

	protected static function meta_set( $base, $row ) {
		$meta          = (array) get_option( self::META, array() );
		$meta[ $base ] = $row;
		update_option( self::META, $meta, false );
	}

	protected static function meta_del( $base ) {
		$meta = (array) get_option( self::META, array() );
		if ( isset( $meta[ $base ] ) ) {
			unset( $meta[ $base ] );
			update_option( self::META, $meta, false );
		}
	}

	/** حذف متای فایل‌هایی که دیگر وجود ندارند */
	protected static function meta_prune() {
		$meta = (array) get_option( self::META, array() );
		$dir  = self::backups_dir();
		$chg  = false;
		foreach ( $meta as $base => $row ) {
			if ( ! is_file( $dir . '/' . $base ) ) { unset( $meta[ $base ] ); $chg = true; }
		}
		if ( $chg ) { update_option( self::META, $meta, false ); }
	}

	protected static function log_add( $ok, $msg, $file ) {
		$logs = (array) get_option( self::LOG, array() );
		array_unshift( $logs, array( 't' => time(), 'ok' => $ok ? 1 : 0, 'msg' => (string) $msg, 'file' => (string) $file ) );
		$logs = array_slice( $logs, 0, 20 );
		update_option( self::LOG, $logs, false );
	}

	protected static function log_get() {
		$logs = (array) get_option( self::LOG, array() );
		return array_values( array_filter( $logs, 'is_array' ) );
	}

	/* ------------------------------------------------------------------
	 * قالب‌بندی اعداد و تاریخ (ساده و خوانا)
	 * ---------------------------------------------------------------- */

	public static function fa_size( $bytes ) {
		$bytes = (float) $bytes;
		if ( $bytes >= 1073741824 ) { return number_format( $bytes / 1073741824, 1 ) . ' گیگابایت'; }
		if ( $bytes >= 1048576 ) { return number_format( $bytes / 1048576, 1 ) . ' مگابایت'; }
		if ( $bytes >= 1024 ) { return number_format( $bytes / 1024, 1 ) . ' کیلوبایت'; }
		return number_format( $bytes ) . ' بایت';
	}

	public static function fa_dt( $ts ) {
		return date_i18n( 'Y/m/d — H:i', (int) $ts );
	}

	public static function fa_secs( $secs ) {
		$secs = (int) $secs;
		if ( $secs < 60 ) { return $secs . ' ثانیه'; }
		return floor( $secs / 60 ) . ' دقیقه' . ( $secs % 60 ? ' و ' . ( $secs % 60 ) . ' ثانیه' : '' );
	}

	public static function ago( $ts ) {
		$d = time() - (int) $ts;
		if ( $d < 3600 ) { return max( 1, (int) floor( $d / 60 ) ) . ' دقیقه پیش'; }
		if ( $d < 172800 ) { return (int) floor( $d / 3600 ) . ' ساعت پیش'; }
		return (int) floor( $d / 86400 ) . ' روز پیش';
	}
}

Dastyar_Backup::boot();
