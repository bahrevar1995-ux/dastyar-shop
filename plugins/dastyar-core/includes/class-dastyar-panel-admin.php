<?php
/**
 * پنل مدیریت افزونه «پنل فروشنده دستیار» — منوی مستقل در پیشخوان
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Panel_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
	}

	public function menu() {
		add_menu_page(
			'پنل فروشنده دستیار',
			'پنل فروشنده',
			'manage_woocommerce',
			'dvp_panel',
			array( $this, 'page_settings' ),
			'dashicons-id-alt',
			5
		);
	}

	/* ------------------------------------------------------------------
	 *  ذخیره تنظیمات + ساخت خودکار برگه پنل
	 * ---------------------------------------------------------------- */

	public function maybe_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// ساخت خودکار برگه «پنل فروشنده»
		if ( ! empty( $_POST['dvp_create_page'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'dvp_create_page' ) ) {
			$this->create_panel_page();
			wp_safe_redirect( admin_url( 'admin.php?page=dvp_panel&dvp_saved=1' ) );
			exit;
		}

		if ( empty( $_POST['dvp_save_settings'] ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'dvp_save_settings' ) ) {
			return;
		}

		$yn  = function ( $k ) { return ! empty( $_POST[ $k ] ) ? 'yes' : 'no'; };
		$new = array(
			'title'           => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'subtitle'        => sanitize_text_field( wp_unslash( $_POST['subtitle'] ?? '' ) ),
			'panel_page'      => (int) ( $_POST['panel_page'] ?? 0 ),
			'auth_page'       => (int) ( $_POST['auth_page'] ?? 0 ),
			'orders_per_page' => max( 5, min( 50, (int) ( $_POST['orders_per_page'] ?? 15 ) ) ),
			'plans_enabled'   => $yn( 'plans_enabled' ),
			'connector_url'   => esc_url_raw( wp_unslash( $_POST['connector_url'] ?? '' ) ),
		);
		// v1.10.13 — طرح ظاهری پنل (انتخابی امن از فهرست مجاز)
		$skin              = isset( $_POST['panel_skin'] ) ? sanitize_key( wp_unslash( $_POST['panel_skin'] ) ) : 'classic';
		$new['panel_skin'] = array_key_exists( $skin, Dastyar_Panel_Settings::skins() ) ? $skin : 'classic';
		// کارت پیشنهاد داشبورد
		$cur                       = Dastyar_Panel_Settings::all();
		$new['promo_enabled']      = $yn( 'promo_enabled' );
		$new['promo_title']        = sanitize_text_field( wp_unslash( $_POST['promo_title'] ?? ( $cur['promo_title'] ?? '' ) ) );
		$new['promo_text']         = sanitize_text_field( wp_unslash( $_POST['promo_text'] ?? ( $cur['promo_text'] ?? '' ) ) );
		$new['promo_code']         = sanitize_text_field( wp_unslash( $_POST['promo_code'] ?? ( $cur['promo_code'] ?? '' ) ) );

		// v1.10.18 — بنر تبلیغاتی بالای جعبه «از اینجا شروع کن» در داشبورد
		$new['dash_banner_img'] = esc_url_raw( wp_unslash( $_POST['dash_banner_img'] ?? '' ) );
		$new['dash_banner_url'] = esc_url_raw( wp_unslash( $_POST['dash_banner_url'] ?? '' ) );
		$new['dash_banner_alt'] = sanitize_text_field( wp_unslash( $_POST['dash_banner_alt'] ?? '' ) );
		// v1.10.19 — نمایش/عدم‌نمایش جعبه «از اینجا شروع کن»
		$new['onboard_enabled'] = $yn( 'onboard_enabled' );

		foreach ( array( 'orders', 'manual', 'invoices', 'wallet', 'rma', 'suggest', 'api', 'tickets', 'contract' ) as $slug ) {
			$new[ 'sec_' . $slug ] = $yn( 'sec_' . $slug );
		}

		// قرارداد همکاری (v1.4.0)
		$new['contract_title']   = sanitize_text_field( wp_unslash( $_POST['contract_title'] ?? '' ) );
		$new['contract_text']    = wp_kses_post( wp_unslash( $_POST['contract_text'] ?? '' ) );
		$new['contract_pdf_url'] = esc_url_raw( wp_unslash( $_POST['contract_pdf_url'] ?? '' ) );

		update_option( Dastyar_Panel_Settings::OPTION, array_merge( Dastyar_Panel_Settings::all(), $new ) );
		wp_safe_redirect( admin_url( 'admin.php?page=dvp_panel&dvp_saved=1' ) );
		exit;
	}

	/** ساخت برگه حاوی کدکوتاه پنل + ثبت شناسه آن در تنظیمات */
	public function create_panel_page() {
		$page_id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_title'   => 'پنل فروشنده',
			'post_content' => '[dastyar_vendor_panel]',
			'post_status'  => 'publish',
		), true );
		if ( ! is_wp_error( $page_id ) && $page_id ) {
			update_option(
				Dastyar_Panel_Settings::OPTION,
				array_merge( Dastyar_Panel_Settings::all(), array( 'panel_page' => (int) $page_id ) )
			);
		}
		return $page_id;
	}

	/* ------------------------------------------------------------------
	 *  رندر صفحه تنظیمات
	 * ---------------------------------------------------------------- */

	public function page_settings() {
		$s     = Dastyar_Panel_Settings::all();
		$pages = function_exists( 'get_pages' ) ? get_pages() : array();

		echo '<div class="wrap dvp-admin" dir="rtl">';
		echo '<style>
			.dvp-admin h1{font-size:22px;font-weight:800;color:#0f5132}
			.dvp-admin .dvp-card{background:#fff;border:1.5px solid #e3ebe7;border-radius:14px;padding:20px 22px;margin:18px 0;max-width:960px}
			.dvp-admin h2{font-size:16px;font-weight:800;color:#14201b;border-bottom:2px solid #17a16d;display:inline-block;padding-bottom:6px;margin:0 0 14px}
			.dvp-admin .dvp-note{background:#e9f6ee;border:1.5px solid #bfe6d2;border-radius:12px;padding:14px 18px;color:#166b47;max-width:960px}
			.dvp-admin table.form-table th{width:200px;font-weight:700}
			.dvp-admin code{background:#eef7f2;border-radius:6px;padding:3px 8px;color:#0f5132;font-weight:700}
			.dvp-admin .button-primary{background:#17a16d!important;border-color:#17a16d!important;border-radius:10px!important;font-weight:700}
			.dvp-admin .dvp-secgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;max-width:700px}
			.dvp-admin .dvp-secgrid label{background:#f7faf8;border:1px solid #eef2f0;border-radius:10px;padding:8px 10px;font-size:12.5px;font-weight:700}
		</style>';

		echo '<h1>🧑‍💼 پنل فروشنده دستیار شاپ</h1>';
		if ( ! empty( $_GET['dvp_saved'] ) ) {
			echo '<div class="notice notice-success" style="max-width:960px"><p>✔ تنظیمات ذخیره شد.</p></div>';
		}

		// خلاصه وضعیت
		printf(
			'<div class="dvp-note">پنل فروشنده را با کدکوتاه <code>[dastyar_vendor_panel]</code> در هر برگه‌ای قرار بدهید؛ این افزونه کاملاً مستقل از «حساب کاربری من» ووکامرس است و همه قابلیت‌های فروشنده را یک‌جا ارائه می‌دهد. %s</div>',
			! empty( $s['panel_page'] )
				? sprintf( '✔ برگه پنل: <a href="%s" target="_blank">مشاهده</a>', esc_url( get_permalink( (int) $s['panel_page'] ) ) )
				: 'ℹ هنوز برگه‌ای برای پنل تعریف نشده است.'
		);

		echo '<form method="post">';
		wp_nonce_field( 'dvp_save_settings' );
		echo '<input type="hidden" name="dvp_save_settings" value="1">';

		// عمومی
		echo '<div class="dvp-card"><h2>⚙ عمومی</h2><table class="form-table">';
		printf( '<tr><th>عنوان پنل</th><td><input type="text" name="title" value="%s" class="regular-text"></td></tr>', esc_attr( $s['title'] ) );
		printf( '<tr><th>زیرعنوان</th><td><input type="text" name="subtitle" value="%s" class="large-text"></td></tr>', esc_attr( $s['subtitle'] ) );

		echo '<tr><th>برگه پنل فروشنده</th><td><select name="panel_page">';
		echo '<option value="0">— انتخاب کنید —</option>';
		foreach ( (array) $pages as $p ) {
			printf( '<option value="%d"%s>%s</option>', (int) $p->ID, selected( (int) $s['panel_page'], (int) $p->ID, false ), esc_html( $p->post_title ) );
		}
		echo '</select> <span class="description">برگه‌ای که کدکوتاه <code>[dastyar_vendor_panel]</code> در آن است (برای راهنمای این صفحه).</span></td></tr>';

		echo '<tr><th>برگه ورود/ثبت‌نام فروشنده</th><td><select name="auth_page">';
		echo '<option value="0">— پیش‌فرض: «حساب کاربری» ووکامرس —</option>';
		foreach ( (array) $pages as $p ) {
			printf( '<option value="%d"%s>%s</option>', (int) $p->ID, selected( (int) $s['auth_page'], (int) $p->ID, false ), esc_html( $p->post_title ) );
		}
		echo '</select> <span class="description">مقصد دکمه «ثبت درخواست فروشندگی» — فرم ورود/ثبت‌نام Core روی همان برگه پنل هم به‌صورت خودکار برای مهمان‌ها نمایش داده می‌شود.</span></td></tr>';

		printf( '<tr><th>تعداد سفارش در هر صفحه</th><td><input type="number" name="orders_per_page" value="%d" min="5" max="50"></td></tr>', (int) $s['orders_per_page'] );
		echo '</table></div>';

		// v1.10.13 — طرح ظاهری پنل (۶ گزینه، بدون حذف طرح فعلی)
		echo '<div class="dvp-card"><h2>🎨 طرح ظاهری پنل</h2>';
		echo '<p class="description">ظاهر پنل فروشنده را از بین ۶ طرح آماده انتخاب کنید؛ همه محتوا و قابلیت‌ها در هر طرحی یکسان می‌مانند، فقط چیدمان و رنگ‌بندی عوض می‌شود.</p>';
		echo '<table class="form-table"><tr><th>طرح</th><td><select name="panel_skin">';
		foreach ( Dastyar_Panel_Settings::skins() as $skin_id => $skin_label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $skin_id ), selected( $s['panel_skin'] ?? 'classic', $skin_id, false ), esc_html( $skin_label ) );
		}
		echo '</select></td></tr></table></div>';

		// بخش‌ها
		echo '<div class="dvp-card"><h2>🧩 بخش‌های پنل</h2><p class="description">بخش‌های «پیشخوان» و «حساب کاربری» همیشه فعال‌اند؛ بقیه را می‌توانید روشن/خاموش کنید.</p><div class="dvp-secgrid">';
		$secs = array(
			'orders'   => '🧾 سفارش‌ها',
			'manual'   => '⚡ ثبت سفارش دستی',
			'invoices' => '💳 صورتحساب‌ها',
			'wallet'   => '👛 کیف پول',
			'rma'      => '↩️ مرجوعی‌ها',
			'suggest'  => '💡 پیشنهاد محصول',
			'api'      => '🔌 اتصال فروشگاه',
			'tickets'  => '🎫 پشتیبانی',
			'contract' => '📜 قرارداد همکاری',
		);
		foreach ( $secs as $slug => $label ) {
			printf(
				'<label><input type="checkbox" name="sec_%s" value="yes"%s> %s</label>',
				esc_attr( $slug ),
				checked( $s[ 'sec_' . $slug ] ?? 'yes', 'yes', false ),
				esc_html( $label )
			);
		}
		echo '</div></div>';

		// کیف پول + اتصال
		echo '<div class="dvp-card"><h2>👛 کیف پول و اتصال</h2><table class="form-table">';
		printf(
			'<tr><th>طرح‌های شارژ سریع</th><td><label><input type="checkbox" name="plans_enabled" value="yes"%s> نمایش ۳ طرح پیشنهادی شارژ کیف پول</label><p class="description">مبالغ طرح‌ها با فیلتر <code>dastyar_wallet_charge_plans</code> هسته قابل سفارشی‌سازی است.</p></td></tr>',
			checked( $s['plans_enabled'], 'yes', false )
		);
		printf( '<tr><th>لینک دانلود افزونه اتصال‌دهنده</th><td><input type="url" name="connector_url" value="%s" class="large-text" dir="ltr"><p class="description">دکمه «دانلود افزونه اتصال‌دهنده» در بخش «اتصال فروشگاه» پنل.</p></td></tr>', esc_attr( $s['connector_url'] ) );
		echo '</table></div>';

		// کارت پیشنهاد داشبورد
		echo '<div class="dvp-card"><h2>🏷 کارت «پیشنهاد ویژه» در داشبورد</h2><table class="form-table">';
		printf(
			'<tr><th>فعال</th><td><label><input type="checkbox" name="promo_enabled" value="yes"%s> نمایش کارت پیشنهاد ویژه در کنار آخرین سفارش‌ها</label></td></tr>',
			checked( $s['promo_enabled'] ?? 'yes', 'yes', false )
		);
		printf( '<tr><th>عنوان کارت</th><td><input type="text" name="promo_title" value="%s" class="regular-text"></td></tr>', esc_attr( $s['promo_title'] ?? '' ) );
		printf( '<tr><th>متن کارت</th><td><input type="text" name="promo_text" value="%s" class="large-text"></td></tr>', esc_attr( $s['promo_text'] ?? '' ) );
		printf( '<tr><th>کد تخفیف (اختیاری)</th><td><input type="text" name="promo_code" value="%s" class="regular-text" dir="ltr" placeholder="OFF20"><p class="description">در صورت خالی‌بودن، چیپ کد نمایش داده نمی‌شود.</p></td></tr>', esc_attr( $s['promo_code'] ?? '' ) );
		echo '</table></div>';

		// v1.10.18 — بنر داشبورد (زیر ۴ باکس خلاصه وضعیت)
		echo '<div class="dvp-card"><h2>🖼 بنر داشبورد</h2>';
		echo '<p class="description">زیر ۴ باکس خلاصه وضعیت پیشخوان نمایش داده می‌شود؛ فقط تا وقتی فروشنده اولین سفارشش را ثبت نکرده. اگر «آدرس تصویر» خالی باشد، بنر نمایش داده نمی‌شود.</p>';
		echo '<table class="form-table">';
		printf( '<tr><th>آدرس تصویر بنر</th><td><input type="url" name="dash_banner_img" value="%s" class="large-text" dir="ltr" placeholder="https://…/banner.jpg"></td></tr>', esc_attr( $s['dash_banner_img'] ?? '' ) );
		printf( '<tr><th>لینک مقصد کلیک</th><td><input type="url" name="dash_banner_url" value="%s" class="large-text" dir="ltr" placeholder="https://…"></td></tr>', esc_attr( $s['dash_banner_url'] ?? '' ) );
		printf( '<tr><th>متن جایگزین تصویر</th><td><input type="text" name="dash_banner_alt" value="%s" class="regular-text"></td></tr>', esc_attr( $s['dash_banner_alt'] ?? '' ) );
		echo '</table></div>';

		// v1.10.19 — نمایش/عدم‌نمایش جعبه «از اینجا شروع کن»
		echo '<div class="dvp-card"><h2>🚀 جعبه «از اینجا شروع کن»</h2>';
		printf(
			'<p><label><input type="checkbox" name="onboard_enabled" value="yes"%s> نمایش جعبه «از اینجا شروع کن — ۳ قدم تا اولین فروش» در پیشخوان (تا قبل از اولین سفارش فروشنده)</label></p>',
			checked( $s['onboard_enabled'] ?? 'yes', 'yes', false )
		);
		echo '</div>';

		// قرارداد همکاری (v1.4.0)
		echo '<div class="dvp-card"><h2>📜 قرارداد همکاری فروشندگان</h2>';
		echo '<p class="description">اگر «متن قرارداد» پر باشد، تب «قرارداد همکاری» به پنل اضافه می‌شود و فروشنده در <strong>اولین ورود</strong> با پاپ‌آپ قرارداد مواجه می‌شود؛ تا تأیید نکند، پنل برایش قابل استفاده نیست. تأیید او با تاریخِ امضا در همان تب نمایش داده می‌شود («شما قرارداد را تأیید و امضا نموده‌اید»).</p>';
		echo '<table class="form-table">';
		printf( '<tr><th>عنوان قرارداد</th><td><input type="text" name="contract_title" value="%s" class="regular-text"></td></tr>', esc_attr( $s['contract_title'] ?? '' ) );
		printf( '<tr><th>متن قرارداد</th><td><textarea name="contract_text" rows="12" class="large-text">%s</textarea><p class="description">متن کامل مفاد قرارداد؛ در صفحه قرارداد و در پاپ‌آپ اولین ورود نمایش داده می‌شود. خالی = قابلیت قرارداد غیرفعال است.</p></td></tr>', esc_textarea( $s['contract_text'] ?? '' ) );
		printf( '<tr><th>لینک فایل PDF قرارداد</th><td><input type="url" name="contract_pdf_url" value="%s" class="large-text" dir="ltr" placeholder="https://…/contract.pdf"><p class="description">در صورت پر بودن، دکمه «دانلود فایل PDF قرارداد» در تب قرارداد و پاپ‌آپ دیده می‌شود.</p></td></tr>', esc_attr( $s['contract_pdf_url'] ?? '' ) );
		echo '</table></div>';

		submit_button( '💾 ذخیره تنظیمات' );
		echo '</form>';

		// ساخت خودکار برگه پنل
		echo '<div class="dvp-card"><h2>🪄 ساخت خودکار برگه پنل</h2>';
		echo '<p>اگر هنوز برگه‌ای برای پنل نساخته‌اید، با یک کلیک برگه «پنل فروشنده» حاوی کدکوتاه ساخته و در تنظیمات بالا ثبت می‌شود.</p>';
		echo '<form method="post">';
		wp_nonce_field( 'dvp_create_page' );
		echo '<input type="hidden" name="dvp_create_page" value="1">';
		submit_button( 'ساخت برگه «پنل فروشنده»', 'secondary' );
		echo '</form></div>';

		// راهنما
		echo '<div class="dvp-card"><h2>📚 راهنمای کدکوتاه</h2>';
		echo '<table class="widefat striped" style="max-width:940px"><thead><tr><th>کدکوتاه</th><th>کاربرد</th></tr></thead><tbody>';
		$rows = array(
			array( '<code>[dastyar_vendor_panel]</code>', 'کل پنل فروشنده: پیشخوان + سفارش‌ها + کیف پول + …' ),
			array( '<code>[dastyar_vendor_panel section="wallet"]</code>', 'باز شدن پنل روی یک بخش خاص (orders / manual / invoices / wallet / rma / suggest / api / tickets / account)' ),
		);
		foreach ( $rows as $r ) {
			printf( '<tr><td style="direction:ltr;text-align:left">%s</td><td>%s</td></tr>', $r[0], $r[1] );
		}
		echo '</tbody></table>';
		echo '<p class="description">💡 نکته: این افزونه به «Dastyar Core» نیاز دارد و با Core نسخه ۱.۵.۱ سازگار است. بخش «ثبت سفارش دستی» و «پیشنهاد محصول» از موتورهای آماده Core استفاده می‌کنند.</p>';
		echo '</div>';

		echo '</div>';
	}
}
