<?php
/**
 * پنل مدیریت کاتالوگ دستیار:
 * - تنظیمات آرشیو (عنوان/زیرعنوان/ستون/تعداد/ترتیب/جستجو/چیپ‌ها/برگه کاتالوگ)
 * - بخش همکاری (CTA) + نشان‌های اعتماد (۴تایی)
 * - صفحه تکی (جایگزینی قالب، ۴ آیکون، تب‌ها، عنوان باکس منابع + ۴ دکمه منابع)
 * - نگاشت آیکون دسته‌بندی‌ها
 * - متاباکس «کاتالوگ دستیار» در ویرایش محصول (ویژگی‌های آیکون‌دار/محتویات/سوالات/۴ لینک منابع)
 * - راهنمای کدکوتاه‌ها
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Cat_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'product_metabox' ) );
		add_action( 'save_post_product', array( $this, 'save_product_fields' ) );
	}

	public function menu() {
		add_menu_page(
			'کاتالوگ دستیار',
			'کاتالوگ دستیار',
			'manage_woocommerce',
			'dct_catalog',
			array( $this, 'page_settings' ),
			'dashicons-book-alt',
			4
		);
	}

	/* ------------------------------------------------------------------
	 * ذخیره تنظیمات
	 * ---------------------------------------------------------------- */

	public function maybe_save_settings() {
		if ( ! isset( $_POST['dct_save_settings'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['dct_nonce'] ?? '' ), 'dct_save_settings' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}

		$d = Dastyar_Cat_Settings::defaults();
		$n = array();

		$n['title_a']      = sanitize_text_field( wp_unslash( $_POST['title_a'] ?? $d['title_a'] ) );
		$n['title_b']      = sanitize_text_field( wp_unslash( $_POST['title_b'] ?? $d['title_b'] ) );
		$n['subtitle']     = sanitize_text_field( wp_unslash( $_POST['subtitle'] ?? $d['subtitle'] ) );
		$n['columns']      = max( 2, min( 6, (int) ( $_POST['columns'] ?? $d['columns'] ) ) );
		$n['per_page']     = max( 1, min( 48, (int) ( $_POST['per_page'] ?? $d['per_page'] ) ) );
		$n['orderby']      = in_array( $_POST['orderby'] ?? '', array( 'date', 'popularity', 'price', 'price-desc', 'title' ), true ) ? sanitize_key( $_POST['orderby'] ) : 'date';
		$n['show_search']  = ! empty( $_POST['show_search'] ) ? 'yes' : 'no';
		$n['show_chips']   = ! empty( $_POST['show_chips'] ) ? 'yes' : 'no';
		$n['chips_limit']  = max( 1, min( 20, (int) ( $_POST['chips_limit'] ?? $d['chips_limit'] ) ) );
		$n['show_popular'] = ! empty( $_POST['show_popular'] ) ? 'yes' : 'no'; // v1.1.1 — سوییچ جدای نوار پرطرفدارها
		$n['popular_limit']= max( 1, min( 12, (int) ( $_POST['popular_limit'] ?? 6 ) ) );
		$n['price_roles']  = ! empty( $_POST['price_roles'] ) ? 'yes' : 'no'; // v1.2.0 — قیمت نقش‌محور در کارت‌ها
		$n['archive_page'] = (int) ( $_POST['archive_page'] ?? 0 );

		$n['cta_enabled'] = ! empty( $_POST['cta_enabled'] ) ? 'yes' : 'no';
		$n['cta_title']   = sanitize_text_field( wp_unslash( $_POST['cta_title'] ?? $d['cta_title'] ) );
		$n['cta_text']    = sanitize_text_field( wp_unslash( $_POST['cta_text'] ?? $d['cta_text'] ) );
		$n['cta_btn']     = sanitize_text_field( wp_unslash( $_POST['cta_btn'] ?? $d['cta_btn'] ) );
		$n['cta_url']     = esc_url_raw( wp_unslash( $_POST['cta_url'] ?? '' ) );

		$n['trust_items']  = $this->parse_icon_rows( (array) ( $_POST['trust_items'] ?? array() ), $d['trust_items'] );
		$n['single_icons'] = $this->parse_icon_rows( (array) ( $_POST['single_icons'] ?? array() ), $d['single_icons'] );

		$n['single_override'] = ! empty( $_POST['single_override'] ) ? 'yes' : 'no';
		foreach ( array( 'tab_intro', 'tab_specs', 'tab_package', 'tab_faq' ) as $tab ) {
			$n[ $tab ] = ! empty( $_POST[ $tab ] ) ? 'yes' : 'no';
		}
		$n['res_title'] = sanitize_text_field( wp_unslash( $_POST['res_title'] ?? $d['res_title'] ) );
		$n['res_sub']   = sanitize_text_field( wp_unslash( $_POST['res_sub'] ?? $d['res_sub'] ) );

		$n['res_items'] = array();
		foreach ( array( 'specs', 'desc', 'video', 'images' ) as $key ) {
			$row = (array) ( $_POST['res_items'][ $key ] ?? array() );
			$def = $d['res_items'][ $key ];
			$n['res_items'][ $key ] = array(
				'icon'    => sanitize_text_field( wp_unslash( $row['icon'] ?? $def['icon'] ) ),
				'label'   => sanitize_text_field( wp_unslash( $row['label'] ?? $def['label'] ) ),
				'action'  => sanitize_text_field( wp_unslash( $row['action'] ?? $def['action'] ) ),
				'enabled' => ! empty( $row['enabled'] ) ? 'yes' : 'no',
			);
		}

		update_option( Dastyar_Cat_Settings::OPTION, $n, false );

		// نگاشت آیکون دسته‌بندی‌ها
		$icons = array();
		foreach ( (array) ( $_POST['dct_icons'] ?? array() ) as $term_id => $emoji ) {
			$emoji = trim( sanitize_text_field( wp_unslash( $emoji ) ) );
			if ( '' !== $emoji ) {
				$icons[ (int) $term_id ] = $emoji;
			}
		}
		update_option( Dastyar_Cat_Settings::ICONS_OPT, $icons, false );

		wp_safe_redirect( admin_url( 'admin.php?page=dct_catalog&dct_saved=1' ) );
		exit;
	}

	/** تبدیل ردیف‌های آیکون/عنوان/زیرعنوان به آرایه استاندارد (۴ ردیف) */
	protected function parse_icon_rows( array $rows, array $defaults ) {
		$out = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$row = (array) ( $rows[ $i ] ?? array() );
			$def = (array) ( $defaults[ $i ] ?? array( 'icon' => '✔', 'title' => '', 'sub' => '' ) );
			$out[] = array(
				'icon'  => sanitize_text_field( wp_unslash( $row['icon'] ?? $def['icon'] ) ),
				'title' => sanitize_text_field( wp_unslash( $row['title'] ?? $def['title'] ) ),
				'sub'   => sanitize_text_field( wp_unslash( $row['sub'] ?? $def['sub'] ) ),
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * صفحه تنظیمات
	 * ---------------------------------------------------------------- */

	public function page_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$s = Dastyar_Cat_Settings::all();

		echo '<div class="wrap dct-admin" dir="rtl">';
		echo '<style>
			.dct-admin .dct-card{background:#fff;border:1.5px solid #e3ebe7;border-radius:14px;padding:20px 22px;margin:18px 0;max-width:980px}
			.dct-admin h1{font-size:22px;font-weight:800;color:#0f5132}
			.dct-admin h2{font-size:16px;font-weight:800;color:#14201b;border-bottom:2px solid #17a16d;display:inline-block;padding-bottom:6px;margin:0 0 14px}
			.dct-admin .dct-note{background:#e9f6ee;border:1.5px solid #bfe6d2;border-radius:12px;padding:14px 18px;color:#166b47;max-width:980px}
			.dct-admin table.form-table th{width:190px;font-weight:700}
			.dct-admin .dct-grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
			.dct-admin .dct-mini{border:1.5px solid #eef2f0;border-radius:12px;padding:12px;background:#fbfdfc}
			.dct-admin .dct-mini h4{margin:0 0 10px;font-size:13px;color:#0f5132}
			.dct-admin .dct-mini input{width:100%;margin-bottom:6px}
			.dct-admin code{background:#eef7f2;border-radius:6px;padding:3px 8px;color:#0f5132;font-weight:700}
			.dct-admin .button-primary{background:#17a16d!important;border-color:#17a16d!important;border-radius:10px!important;font-weight:700}
			.dct-admin .dct-subsec{font-size:13.5px;font-weight:800;color:#0f5132;margin:10px 0 2px;background:#f4fbf8;border:1.5px solid #cdeee0;border-right:4px solid #17a16d;border-radius:10px;padding:9px 14px;display:inline-block}
			.dct-saved{max-width:980px}
		</style>';

		echo '<h1>🗂 کاتالوگ محصولات دستیار شاپ</h1>';
		if ( ! empty( $_GET['dct_saved'] ) ) {
			echo '<div class="notice notice-success dct-saved"><p>✔ تنظیمات کاتالوگ ذخیره شد.</p></div>';
		}
		echo '<div class="dct-note">کاتالوگ را با کدکوتاه <code>[dastyar_catalog]</code> در هر برگه‌ای قرار بدهید؛ نمای تکی محصول (اگر فعال باشد) خودکار جایگزین صفحه محصول فروشگاه می‌شود. <a href="#dct-help">راهنمای کامل کدکوتاه‌ها</a></div>';

		echo '<form method="post">';
		wp_nonce_field( 'dct_save_settings', 'dct_nonce' );
		echo '<input type="hidden" name="dct_save_settings" value="1">';

		/* ─── آرشیو ─── */
		echo '<div class="dct-card"><h2>🏬 صفحه کاتالوگ (آرشیو)</h2><table class="form-table">';
		printf( '<tr><th>عنوان اصلی</th><td><input type="text" name="title_a" value="%s" class="regular-text"> <span class="description">متن تیره — مثل «کاتالوگ»</span></td></tr>', esc_attr( $s['title_a'] ) );
		printf( '<tr><th>قسمت تاکیدی عنوان</th><td><input type="text" name="title_b" value="%s" class="regular-text"> <span class="description">متن سبز — مثل «محصولات دستیار شاپ»</span></td></tr>', esc_attr( $s['title_b'] ) );
		printf( '<tr><th>زیرعنوان</th><td><input type="text" name="subtitle" value="%s" class="large-text"><p class="description">از <code>{count}</code> برای شمارش خودکار محصولات استفاده کنید.</p></td></tr>', esc_attr( $s['subtitle'] ) );
		echo '<tr><th>ستون‌های گرید</th><td><select name="columns">';
		foreach ( array( 3, 4, 5 ) as $c ) {
			printf( '<option value="%d"%s>%d ستونه</option>', $c, selected( (int) $s['columns'], $c, false ), $c );
		}
		echo '</select></td></tr>';
		printf( '<tr><th>تعداد در هر صفحه</th><td><input type="number" name="per_page" value="%d" min="4" max="48"></td></tr>', (int) $s['per_page'] );
		echo '<tr><th>مرتب‌سازی پیش‌فرض</th><td><select name="orderby">';
		foreach ( array( 'date' => 'جدیدترین', 'popularity' => 'پرفروش‌ترین', 'price' => 'ارزان‌ترین', 'price-desc' => 'گران‌ترین', 'title' => 'الفبایی (نام)' ) as $v => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $s['orderby'], $v, false ), esc_html( $l ) );
		}
		echo '</select></td></tr>';
		printf( '<tr><th>نوار جستجو</th><td><label><input type="checkbox" name="show_search" value="yes" %s> نمایش نوار جستجو در بالای کاتالوگ</label></td></tr>', checked( $s['show_search'], 'yes', false ) );
		// v1.1.1 — بخش‌بندی مستقل: چیپ‌های دسته‌بندی و نوار دسته‌های پرطرفدار دو کنترل کاملاً مجزا دارند
		echo '<tr><td colspan="2"><h3 class="dct-subsec">🧩 چیپ‌های دسته‌بندی</h3></td></tr>';
		printf( '<tr><th>نمایش چیپ‌ها</th><td><label><input type="checkbox" name="show_chips" value="yes" %s> نمایش ردیف چیپ‌های دسته‌بندی با آیکون</label></td></tr>', checked( $s['show_chips'], 'yes', false ) );
		printf( '<tr><th>سقف چیپ‌های قابل‌مشاهده</th><td><input type="number" name="chips_limit" value="%d" min="3" max="20"><p class="description">بقیه دسته‌ها زیر چیپ «بیشتر» قرار می‌گیرند.</p></td></tr>', (int) $s['chips_limit'] );
		echo '<tr><td colspan="2"><h3 class="dct-subsec">🔥 دسته‌های پرطرفدار</h3></td></tr>';
		printf( '<tr><th>نمایش نوار پرطرفدارها</th><td><label><input type="checkbox" name="show_popular" value="yes" %s> نمایش نوار «دسته‌های پرطرفدار» با آیکن آتش</label><p class="description">مستقل از چیپ‌ها — می‌توانید هر بخش را جداگانه روشن/خاموش کنید.</p></td></tr>', checked( $s['show_popular'], 'yes', false ) );
		printf( '<tr><th>تعداد دسته‌های پرطرفدار</th><td><input type="number" name="popular_limit" value="%d" min="1" max="12"><p class="description">نوار «دسته‌های پرطرفدار» با آیکن آتش زیر نوار جستجو (بر اساس بیشترین تعداد محصول) — دکمه «مشاهده همه» هم پاپ‌آپ انتخاب دسته را باز می‌کند.</p></td></tr>', (int) ( $s['popular_limit'] ?? 6 ) );
		echo '<tr><td colspan="2"><h3 class="dct-subsec">🏷️ قیمت در کارت محصولات</h3></td></tr>';
		// v1.2.0 — قیمت نقش‌محور (بازخورد کاربر، مورد ۲)
		printf( '<tr><th>قیمت بر اساس نقش</th><td><label><input type="checkbox" name="price_roles" value="yes" %s> نمایش قیمت متناسب با نقش کاربر در کارت‌ها</label><p class="description"><strong>مدیر</strong> ← قیمت فروشگاه • <strong>فروشنده دستیار</strong> ← «قیمت همکاری» (متای تامین؛ برای محصول متغیر کمینه تنوع‌ها) • <strong>مهمان/سایر نقش‌ها</strong> ← قیمت نمایش داده نمی‌شود و لینک ورود می‌بینند (هماهنگ با قوانین دستیار کر).</p></td></tr>', checked( $s['price_roles'] ?? 'yes', 'yes', false ) );
		echo '<tr><th>برگه کاتالوگ (برای مسیر)</th><td><select name="archive_page"><option value="0">— انتخاب کنید —</option>';
		foreach ( (array) get_pages() as $p ) {
			printf( '<option value="%d"%s>%s</option>', (int) $p->ID, selected( (int) $s['archive_page'], (int) $p->ID, false ), esc_html( $p->post_title ) );
		}
		echo '</select><p class="description">برگه‌ای که کدکوتاه <code>[dastyar_catalog]</code> در آن است — در بردکرامب صفحه محصول لینک می‌شود.</p></td></tr>';
		echo '</table></div>';

		/* ─── CTA همکاری ─── */
		echo '<div class="dct-card"><h2>🤝 بخش همکاری (CTA)</h2><table class="form-table">';
		printf( '<tr><th>فعال</th><td><label><input type="checkbox" name="cta_enabled" value="yes" %s> نمایش باکس همکاری در پایین کاتالوگ</label></td></tr>', checked( $s['cta_enabled'], 'yes', false ) );
		printf( '<tr><th>عنوان</th><td><input type="text" name="cta_title" value="%s" class="regular-text"></td></tr>', esc_attr( $s['cta_title'] ) );
		printf( '<tr><th>متن</th><td><input type="text" name="cta_text" value="%s" class="large-text"></td></tr>', esc_attr( $s['cta_text'] ) );
		printf( '<tr><th>متن دکمه</th><td><input type="text" name="cta_btn" value="%s" class="regular-text"></td></tr>', esc_attr( $s['cta_btn'] ) );
		printf( '<tr><th>لینک دکمه</th><td><input type="url" name="cta_url" value="%s" class="large-text" dir="ltr" placeholder="https://…"></td></tr>', esc_attr( $s['cta_url'] ) );
		echo '</table></div>';

		/* ─── نشان‌های اعتماد ─── */
		echo '<div class="dct-card"><h2>🛡 نشان‌های اعتماد (پایین آرشیو)</h2><div class="dct-grid4">';
		foreach ( (array) $s['trust_items'] as $i => $it ) {
			$this->render_icon_row_inputs( 'trust_items', $i, (array) $it, 'نشان ' . ( $i + 1 ) );
		}
		echo '</div></div>';

		/* ─── صفحه تکی ─── */
		echo '<div class="dct-card"><h2>📄 صفحه محصول (تکی)</h2><table class="form-table">';
		printf( '<tr><th>جایگزینی قالب</th><td><label><input type="checkbox" name="single_override" value="yes" %s> صفحه محصول فروشگاه با قالب کاتالوگ نمایش داده شود (گالری/تب‌ها/منابع — مثل طرح نمونه)</label></td></tr>', checked( $s['single_override'], 'yes', false ) );
		foreach ( array( 'tab_intro' => 'تب «معرفی محصول» (توضیحات)', 'tab_specs' => 'تب «مشخصات» (ویژگی‌های ووکامرس)', 'tab_package' => 'تب «محتویات بسته» (از متا محصول)', 'tab_faq' => 'تب «سوالات متداول» (از متا محصول)' ) as $key => $label ) {
			printf( '<tr><th>%s</th><td><label><input type="checkbox" name="%s" value="yes" %s> نمایش</label></td></tr>', esc_html( $label ), esc_attr( $key ), checked( $s[ $key ], 'yes', false ) );
		}
		printf( '<tr><th>عنوان باکس منابع</th><td><input type="text" name="res_title" value="%s" class="regular-text"></td></tr>', esc_attr( $s['res_title'] ) );
		printf( '<tr><th>زیرعنوان باکس منابع</th><td><input type="text" name="res_sub" value="%s" class="large-text"></td></tr>', esc_attr( $s['res_sub'] ) );
		echo '</table>';

		echo '<p style="font-weight:700;color:#14201b">۴ آیکون زیر باکس خرید:</p><div class="dct-grid4">';
		foreach ( (array) $s['single_icons'] as $i => $it ) {
			$this->render_icon_row_inputs( 'single_icons', $i, (array) $it, 'آیکون ' . ( $i + 1 ) );
		}
		echo '</div>';
		echo '</div>';

		/* ─── دکمه‌های منابع ─── */
		echo '<div class="dct-card"><h2>📥 دکمه‌های منابع اطلاعاتی (در صفحه محصول)</h2><div class="dct-grid4">';
		foreach ( array( 'specs' => 'مشخصات فنی', 'desc' => 'توضیحات محصول', 'video' => 'ویدیو معرفی', 'images' => 'تصاویر محصول' ) as $key => $title ) {
			$conf = (array) $s['res_items'][ $key ];
			echo '<div class="dct-mini">';
			printf( '<h4>%s</h4>', esc_html( $title ) );
			printf( '<input type="text" name="res_items[%s][icon]" value="%s" placeholder="ایموجی">', esc_attr( $key ), esc_attr( $conf['icon'] ) );
			printf( '<input type="text" name="res_items[%s][label]" value="%s" placeholder="برچسب">', esc_attr( $key ), esc_attr( $conf['label'] ) );
			printf( '<input type="text" name="res_items[%s][action]" value="%s" placeholder="متن اکشن (مثل دانلود فایل)">', esc_attr( $key ), esc_attr( $conf['action'] ) );
			printf( '<label style="font-size:12px"><input type="checkbox" name="res_items[%s][enabled]" value="yes" %s style="width:auto"> فعال — در صورت وجود لینک روی محصول نمایش داده شود</label>', esc_attr( $key ), checked( $conf['enabled'], 'yes', false ) );
			echo '</div>';
		}
		echo '</div><p class="description">لینک هر دکمه را در باکس «کاتالوگ دستیار» داخل صفحه ویرایش هر محصول وارد می‌کنید.</p></div>';

		/* ─── آیکون دسته‌بندی‌ها ─── */
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC', 'number' => 60 ) );
		if ( ! is_wp_error( $terms ) && $terms ) {
			$icons_map = (array) get_option( Dastyar_Cat_Settings::ICONS_OPT, array() );
			echo '<div class="dct-card"><h2>🏷 آیکون چیپ‌های دسته‌بندی</h2><table class="form-table">';
			foreach ( $terms as $t ) {
				printf(
					'<tr><th>%s <code style="font-size:11px;color:#9db3aa">%s</code></th><td><input type="text" name="dct_icons[%d]" value="%s" style="width:90px;text-align:center" placeholder="🔹"></td></tr>',
					esc_html( $t->name ),
					esc_html( $t->slug ),
					(int) $t->term_id,
					esc_attr( (string) ( $icons_map[ (int) $t->term_id ] ?? '' ) )
				);
			}
			echo '</table><p class="description">اگر آیکونی برای دسته‌ای تنظیم نکنید، «🔹» پیش‌فرض نمایش داده می‌شود.</p></div>';
		}

		submit_button( 'ذخیره تنظیمات کاتالوگ' );
		echo '</form>';

		/* ─── راهنمای کدکوتاه ─── */
		echo '<div class="dct-card" id="dct-help"><h2>📚 راهنمای کدکوتاه‌ها</h2>';
		echo '<table class="widefat striped" style="max-width:940px"><thead><tr><th>کدکوتاه</th><th>کاربرد</th></tr></thead><tbody>';
		$rows = array(
			array( '<code>[dastyar_catalog]</code>', 'کل صفحه کاتالوگ: هیرو + جستجو + چیپ‌ها + گرید + CTA + نشان‌ها' ),
			array( '<code>[dastyar_catalog columns="5" per_page="20"]</code>', 'سفارشی‌سازی ستون و تعداد در صفحه' ),
			array( '<code>[dastyar_catalog category="headphones"]</code>', 'کاتالوگ فقط یک دسته (اسلاگ دسته)' ),
			array( '<code>[dastyar_catalog show_search="no" show_chips="no"]</code>', 'فقط گرید محصولات — بدون جستجو و دسته‌ها' ),
			array( '<code>[dastyar_catalog show_chips="no" show_popular="yes"]</code>', 'فقط نوار «دسته‌های پرطرفدار» بدون چیپ‌ها — کنترل مستقل هر بخش (v1.1.1)' ),
			array( '<code>[dastyar_catalog title_a="کاتالوگ" title_b="موبایل و تبلت"]</code>', 'عنوان سفارشی برای این جای‌گذاری' ),
			array( '<code>[dastyar_catalog_product id="123"]</code>', 'نمای تکی یک محصول (گالری/تب‌ها/منابع) در هر برگه' ),
		);
		foreach ( $rows as $r ) {
			printf( '<tr><td style="direction:ltr;text-align:left">%s</td><td>%s</td></tr>', $r[0], $r[1] );
		}
		echo '</tbody></table>';
		echo '<p class="description">💡 صفحه تکی محصول اگر «جایگزینی قالب» فعال باشد، خودکار برای همه محصولات اعمال می‌شود و نیازی به کدکوتاه ندارد. محتوای تب‌ها/ویژگی‌ها/منابع هر محصول را از باکس «کاتالوگ دستیار» در ویرایش همان محصول پر کنید.</p>';
		echo '</div>';

		echo '</div>';
	}

	/** ورودی‌های یک نشان (آیکون/عنوان/زیرعنوان) */
	protected function render_icon_row_inputs( $group, $i, array $it, $title ) {
		echo '<div class="dct-mini">';
		printf( '<h4>%s</h4>', esc_html( $title ) );
		printf( '<input type="text" name="%s[%d][icon]" value="%s" placeholder="ایموجی" style="width:100%%;text-align:center">', esc_attr( $group ), (int) $i, esc_attr( $it['icon'] ?? '' ) );
		printf( '<input type="text" name="%s[%d][title]" value="%s" placeholder="عنوان">', esc_attr( $group ), (int) $i, esc_attr( $it['title'] ?? '' ) );
		printf( '<input type="text" name="%s[%d][sub]" value="%s" placeholder="زیرعنوان">', esc_attr( $group ), (int) $i, esc_attr( $it['sub'] ?? '' ) );
		echo '</div>';
	}

	/* ------------------------------------------------------------------
	 * متاباکس محصول — محتوای کاتالوگ هر محصول
	 * ---------------------------------------------------------------- */

	public function product_metabox() {
		add_meta_box(
			'dct_product_catalog',
			'🗂 کاتالوگ دستیار',
			array( $this, 'product_metabox_content' ),
			'product',
			'normal',
			'default'
		);
	}

	public function product_metabox_content( $post ) {
		wp_nonce_field( 'dct_product_save', 'dct_product_nonce' );
		$pid = (int) $post->ID;

		echo '<style>
		.dct-pm label{font-weight:700;display:block;margin:12px 0 4px}
		.dct-pm textarea,.dct-pm input[type=url]{width:100%;border:1.5px solid #e3ebe7;border-radius:8px;padding:8px 10px}
		.dct-pm .description{color:#7d908a;font-size:12px;margin:3px 0 0}
		.dct-pm-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 18px}
		</style><div class="dct-pm">';

		echo '<label>✨ ویژگی‌های محصول (ردیف آیکون‌دار — مثل «حذف نویز فعال»)</label>';
		printf( '<textarea name="dct_features" rows="4" placeholder="🔇|حذف نویز فعال&#10;🎵|کیفیت صدای Hi-Fi">%s</textarea>', esc_textarea( get_post_meta( $pid, '_dct_features', true ) ) );
		echo '<p class="description">هر خط یک ویژگی — قالب: <code>ایموجی|عنوان</code></p>';

		echo '<div class="dct-pm-grid"><div>';
		echo '<label>📦 محتویات بسته (تب مختص خودش)</label>';
		printf( '<textarea name="dct_package" rows="4" placeholder="هدفون&#10;کیس شارژ&#10;کابل USB-C">%s</textarea>', esc_textarea( get_post_meta( $pid, '_dct_package', true ) ) );
		echo '<p class="description">هر خط یک قلم.</p>';
		echo '</div><div>';
		echo '<label>❓ سوالات متداول (تب مختص خودش)</label>';
		printf( '<textarea name="dct_faq" rows="4" placeholder="گارانتی دارد؟|بله، ۱۲ ماه گارانتی شرکتی">%s</textarea>', esc_textarea( get_post_meta( $pid, '_dct_faq', true ) ) );
		echo '<p class="description">هر خط یک سوال — قالب: <code>سوال|پاسخ</code></p>';
		echo '</div></div>';

		echo '<label>📥 لینک‌های «منابع و اطلاعات تکمیلی» (اجباری نیست — هرکدام پر باشد، دکمه‌اش نمایش داده می‌شود)</label>';
		foreach ( array(
			'_dct_res_specs'  => '📄 مشخصات فنی (لینک فایل)',
			'_dct_res_desc'   => '📝 توضیحات محصول (لینک فایل)',
			'_dct_res_video'  => '▶️ ویدیو معرفی (لینک ویدیو)',
			'_dct_res_images' => '🖼️ تصاویر محصول (لینک آرشیو تصاویر)',
		) as $key => $label ) {
			printf( '<p style="margin:6px 0"><span style="display:inline-block;width:210px;font-size:12.5px;font-weight:700">%s</span><input type="url" name="%s" value="%s" dir="ltr" placeholder="https://…" style="max-width:calc(100%% - 230px)"></p>',
				esc_html( $label ), esc_attr( $key ), esc_attr( get_post_meta( $pid, $key, true ) ) );
		}

		echo '</div>';
	}

	public function save_product_fields( $post_id ) {
		if ( ! isset( $_POST['dct_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['dct_product_nonce'] ), 'dct_product_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}
		$textareas = array( '_dct_features' => 'dct_features', '_dct_package' => 'dct_package', '_dct_faq' => 'dct_faq' );
		foreach ( $textareas as $meta => $field ) {
			$val = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ?? '' ) );
			if ( '' !== $val ) {
				update_post_meta( $post_id, $meta, $val );
			} else {
				delete_post_meta( $post_id, $meta );
			}
		}
		foreach ( array( '_dct_res_specs' => 'dct_res_specs', '_dct_res_desc' => 'dct_res_desc', '_dct_res_video' => 'dct_res_video', '_dct_res_images' => 'dct_res_images' ) as $meta => $field ) {
			$val = esc_url_raw( wp_unslash( $_POST[ $field ] ?? '' ) );
			if ( '' !== $val ) {
				update_post_meta( $post_id, $meta, $val );
			} else {
				delete_post_meta( $post_id, $meta );
			}
		}
	}
}
