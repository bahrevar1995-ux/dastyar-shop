<?php
/**
 * پیشخوان قالب «دستیار» — «نمایش ← تنظیمات دستیار»
 *
 * همه محتوای سایت از این‌جا قابل مدیریت است: هدر و اعلان، رفتار (لود آنی و
 * گارد فروشگاه)، بخش‌های لندینگ، صفحات درباره/تماس/دریافت/پرداخت، وبلاگ و فوتر +
 * ابزارکیت ساخت خودکار برگه‌ها. استایل/ايدی‌ها با افزونه دستیار هوم مشترک (dhm-*) است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Theme_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
	}

	public function menu() {
		if ( function_exists( 'add_theme_page' ) ) {
			add_theme_page(
				'تنظیمات قالب دستیار',
				'تنظیمات دستیار',
				'manage_options',
				'dastyar-theme',
				array( $this, 'page_settings' )
			);
		}
	}

	/** آدرس صفحه تنظیمات */
	protected static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'dastyar-theme' ), $args ), admin_url( 'themes.php' ) );
	}

	/* ------------------------------------------------------------------
	 *  ذخیره تنظیمات + ابزارکیت ساخت برگه‌ها
	 * ---------------------------------------------------------------- */

	public function maybe_save() {
		global $pagenow;
		if ( 'themes.php' !== (string) $pagenow ) {
			return; // گارد v1.0.1: خارج از صفحه تنظیمات خودمان، هیچ پردازش/خروجی‌ای در پیشخوان نداریم
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// ابزارکیت: ساخت/به‌روزرسانی برگه‌ها + فرانت‌پیج + منو
		if ( ! empty( $_POST['dth_setup_pages'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'dth_setup_pages' ) ) {
			Dastyar_Theme::auto_pages( true );
			wp_safe_redirect( self::page_url( array( 'dth_saved' => 'kit' ) ) );
			exit;
		}

		if ( empty( $_POST['dth_save_settings'] ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'dth_save_settings' ) ) {
			return;
		}

		$cur = Dastyar_Theme_Settings::all();
		$new = array();

		// سوییچ‌ها
		$toggles = array(
			'announce_enabled', 'instant_load', 'shop_guard', 'contact_form_enabled', 'footer_credit',
			'sec_hero', 'sec_stats', 'sec_features', 'sec_how', 'sec_plans', 'sec_testi', 'sec_faq', 'sec_cta',
			'plan_1_hot', 'plan_2_hot', 'plan_3_hot',
			// v2.0.0 — سوییچ‌های نمایش بخش‌های بیشتر
			'page_hero_on', 'contact_cards_on', 'pay_cta_on', 'about_stats_on', 'dl_steps_on',
			// v2.1.0 — سوییچ دکمه‌های هدر + بخش‌های جدید صفحه اصلی
			'hbtn_cta1_on', 'hbtn_cta2_on', 'hbtn_tut_on',
			'sec_catalog', 'sec_video', 'sec_benefits',
			// v2.4.0 — سوییچ پاپ‌آپ مشاوره تلفنی
			'leads_on',
			// v2.4.1 — سوییچ نمایش باکس‌های مجوز فوتر
			'trust_badges_on',
			// v2.4.9 — سوییچ منوی اپلیکیشنی موبایل
			'mobnav_enabled',
		);
		// v2.1.0 — سوییچ جداگانه هیرو برای هر نوع برگه
		foreach ( array_keys( Dastyar_Theme_Settings::page_hero_ids() ) as $dth_phid ) {
			$toggles[] = 'ph_' . $dth_phid;
		}
		foreach ( $toggles as $k ) {
			$new[ $k ] = ! empty( $_POST[ $k ] ) ? 'yes' : 'no';
		}

		// فیلدهای تک‌خطی
		$texts = array(
			'logo_text', 'cta1_text', 'cta2_text', 'announce_text',
			'hero_badge', 'hero_title', 'hero_hl', 'hero_sub', 'hero_b1_text', 'hero_b2_text',
			// v2.0.0 — کارت شاخص هیرو + متن برگه‌های سیستمی
			'heroc_title', 'heroc_price', 'heroc_old', 'heroc_off', 'heroc_profit_label', 'heroc_profit', 'heroc_btn',
			'panel_title', 'auth_title', 'auth_sub', 'catalog_chip', 'catalog_title', 'catalog_sub',
			// v2.1.0 — متن‌های برگه آموزش استفاده
			'tut_title', 'tut_sub', 'tut_m1', 'tut_m2', 'tut_m3', 'tut_start_t', 'tut_start_s',
			// v2.4.0 — متن‌های پاپ‌آپ مشاوره تلفنی (تک‌خطی‌ها)
			'lead_pop_title', 'lead_pop_yes', 'lead_pop_no', 'lead_done_t',
			// v2.1.0 — بخش‌های جدید صفحه اصلی (کاتالوگ/ویدیو/مزایا)
			'cat_title', 'cat_sub', 'cat_btn',
			'video_title', 'video_sub', 'video_note', 'video_btn',
			'ben_badge', 'ben_title', 'ben_sub', 'ben_cta_text',
			'feat_title', 'feat_sub', 'how_title', 'how_sub', 'plans_title', 'plans_sub',
			'testi_title', 'testi_sub', 'faq_title', 'faq_sub', 'cta_title', 'cta_text', 'cta_btn',
			'about_title', 'about_sub',
			'contact_title', 'contact_sub', 'contact_phone', 'contact_email', 'contact_address', 'contact_hours',
			'dl_title', 'dl_sub', 'dl_version',
			'pay_title', 'pay_sub',
			'blog_title', 'blog_sub',
			// v2.4.1 — عنوان باکس‌های مجوز فوتر
			'trust_badge_1_title', 'trust_badge_2_title', 'trust_badge_3_title',
		);
		// v2.4.9 — منوی اپلیکیشنی موبایل: نوع/برچسب هر خانه (تا ۵ خانه)
		for ( $n = 1; $n <= 5; $n++ ) {
			$texts[] = 'mobnav_' . $n . '_type';
			$texts[] = 'mobnav_' . $n . '_label';
			$texts[] = 'mobnav_' . $n . '_icon';
		}
		for ( $n = 1; $n <= 4; $n++ ) {
			$texts[] = 'stats_' . $n . '_num';
			$texts[] = 'stats_' . $n . '_label';
			$texts[] = 'pay_' . $n . '_t';
			$texts[] = 'pay_' . $n . '_d';
		}
		for ( $n = 1; $n <= 6; $n++ ) {
			$texts[] = 'feat_' . $n . '_title';
			$texts[] = 'feat_' . $n . '_desc';
		}
		for ( $n = 1; $n <= 3; $n++ ) {
			$texts[] = 'how_' . $n . '_title';
			$texts[] = 'how_' . $n . '_desc';
			$texts[] = 'plan_' . $n . '_name';
			$texts[] = 'plan_' . $n . '_price';
			$texts[] = 'plan_' . $n . '_per';
			$texts[] = 'plan_' . $n . '_btn';
			$texts[] = 'testi_' . $n . '_name';
			$texts[] = 'testi_' . $n . '_shop';
		}
		foreach ( $texts as $k ) {
			$new[ $k ] = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ( isset( $cur[ $k ] ) ? $cur[ $k ] : '' );
		}

		// فیلدهای URL
		$urls = array( 'cta1_url', 'cta2_url', 'announce_url', 'hero_b1_url', 'hero_b2_url', 'heroc_url', 'cta_url', 'plan_1_url', 'plan_2_url', 'plan_3_url', 'dl_url', 'video_url', 'video_btn_url', 'ben_cta_url' );
		for ( $n = 1; $n <= 5; $n++ ) {
			$urls[] = 'mobnav_' . $n . '_url';
		}
		foreach ( $urls as $k ) {
			$new[ $k ] = isset( $_POST[ $k ] ) ? esc_url_raw( wp_unslash( $_POST[ $k ] ) ) : ( isset( $cur[ $k ] ) ? $cur[ $k ] : '' );
		}

		// فیلدهای چندخطی
		$areas = array(
			'nav_links', 'hero_points', 'hero_minicards', 'hero_notes', 'hero_ministats', 'hero_orbit_msgs', 'faq_items', 'plan_1_feats', 'plan_2_feats', 'plan_3_feats',
			'testi_1_quote', 'testi_2_quote', 'testi_3_quote',
			'about_text', 'about_points', 'dl_changes', 'dl_note', 'footer_about', 'footer_links', 'pj_exclude',
			// v2.4.0 — متن‌های چندخطی پاپ‌آپ مشاوره
			'lead_pop_text', 'lead_done_s',
		);
		foreach ( $areas as $k ) {
			$new[ $k ] = isset( $_POST[ $k ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $k ] ) ) : ( isset( $cur[ $k ] ) ? $cur[ $k ] : '' );
		}

		// v2.4.1 — کد باکس‌های مجوز فوتر (اینماد/درگاه پرداخت/…): کد HTML/جاوااسکریپت خام است،
		// نباید مثل بقیه فیلدهای چندخطی تگ‌هایش پاک شود. مثل ابزارک «HTML سفارشی» وردپرس،
		// فقط برای کاربرانی که دسترسی unfiltered_html دارند خام ذخیره می‌شود؛ در غیر این‌صورت با
		// wp_kses_post پالایش می‌شود (اسکریپت حذف می‌شود ولی لینک/تصویر باقی می‌ماند).
		foreach ( array( 'trust_badge_1_code', 'trust_badge_2_code', 'trust_badge_3_code' ) as $k ) {
			if ( ! isset( $_POST[ $k ] ) ) {
				$new[ $k ] = isset( $cur[ $k ] ) ? $cur[ $k ] : '';
				continue;
			}
			$raw = wp_unslash( $_POST[ $k ] );
			$new[ $k ] = current_user_can( 'unfiltered_html' ) ? $raw : wp_kses_post( $raw );
		}

		// آیکن کارت‌های امکانات
		$choices = Dastyar_Theme_Landing::icon_choices();
		for ( $n = 1; $n <= 6; $n++ ) {
			$ic = isset( $_POST[ 'feat_' . $n . '_icon' ] ) ? sanitize_key( wp_unslash( $_POST[ 'feat_' . $n . '_icon' ] ) ) : ( isset( $cur[ 'feat_' . $n . '_icon' ] ) ? $cur[ 'feat_' . $n . '_icon' ] : 'check' );
			$new[ 'feat_' . $n . '_icon' ] = isset( $choices[ $ic ] ) ? $ic : 'check';
		}

		// v2.0.0 — آیکن کارت شاخص هیرو + سبک هیرو (انتخابی امن)
		$hic = isset( $_POST['heroc_icon'] ) ? sanitize_key( wp_unslash( $_POST['heroc_icon'] ) ) : ( isset( $cur['heroc_icon'] ) ? $cur['heroc_icon'] : 'package' );
		$new['heroc_icon'] = isset( $choices[ $hic ] ) ? $hic : 'package';
		$hs  = isset( $_POST['hero_style'] ) ? sanitize_key( wp_unslash( $_POST['hero_style'] ) ) : ( isset( $cur['hero_style'] ) ? $cur['hero_style'] : 'cards' );
		// v2.1.0 — پنج سبک هیرو
		$new['hero_style'] = in_array( $hs, array( 'cards', 'split', 'constellation', 'leadform', 'banner', 'flow3d' ), true ) ? $hs : 'cards';

		// v2.1.0 — سبک هدر (۴ حالت) و سبک پنل کاربری (۵ حالت) — انتخابی امن
		$hst = isset( $_POST['header_style'] ) ? sanitize_key( wp_unslash( $_POST['header_style'] ) ) : ( isset( $cur['header_style'] ) ? $cur['header_style'] : 'default' );
		$new['header_style'] = in_array( $hst, array( 'default', 'dark', 'center', 'minimal' ), true ) ? $hst : 'default';
		$pst = isset( $_POST['panel_style'] ) ? sanitize_key( wp_unslash( $_POST['panel_style'] ) ) : ( isset( $cur['panel_style'] ) ? $cur['panel_style'] : 'classic' );
		$new['panel_style'] = in_array( $pst, array( 'classic', 'full', 'bare', 'gray', 'card' ), true ) ? $pst : 'classic';
		// v2.1.1 — سبک فوتر (۵ حالت) — انتخابی امن
		$fst = isset( $_POST['footer_style'] ) ? sanitize_key( wp_unslash( $_POST['footer_style'] ) ) : ( isset( $cur['footer_style'] ) ? $cur['footer_style'] : 'columns' );
		$new['footer_style'] = in_array( $fst, array( 'columns', 'center', 'deep', 'split', 'slim' ), true ) ? $fst : 'columns';

		// v2.1.0 — تعداد/ستون نمونه کاتالوگ (اعداد امن با کلمپ)
		$new['cat_count'] = (string) max( 1, min( 12, (int) ( isset( $_POST['cat_count'] ) ? wp_unslash( $_POST['cat_count'] ) : ( isset( $cur['cat_count'] ) ? $cur['cat_count'] : 4 ) ) ) );
		$new['cat_cols']  = (string) max( 2, min( 6, (int) ( isset( $_POST['cat_cols'] ) ? wp_unslash( $_POST['cat_cols'] ) : ( isset( $cur['cat_cols'] ) ? $cur['cat_cols'] : 4 ) ) ) );

		// v2.3.0 — لوگوی دوحالته روشن/تاریک (شناسه پیوست، امن با absint؛ نبود مقدار = حفظ قبلی)
		foreach ( array( 'logo_light_id', 'logo_dark_id' ) as $dth_lk ) {
			$new[ $dth_lk ] = isset( $_POST[ $dth_lk ] )
				? (string) absint( wp_unslash( $_POST[ $dth_lk ] ) )
				: ( isset( $cur[ $dth_lk ] ) ? (string) (int) $cur[ $dth_lk ] : '0' );
		}

		update_option( Dastyar_Theme_Settings::OPTION, array_merge( $cur, $new ) );
		wp_safe_redirect( self::page_url( array( 'dth_saved' => '1' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 *  کمک‌متدهای رندر فرم (همان الگوی افزونه دستیار هوم)
	 * ---------------------------------------------------------------- */

	protected function row_check( $s, $key, $label, $desc = '' ) {
		printf(
			'<tr><th>%s</th><td><label><input type="checkbox" name="%s" value="yes"%s></label>%s</td></tr>',
			esc_html( $label ),
			esc_attr( $key ),
			checked( isset( $s[ $key ] ) ? $s[ $key ] : 'yes', 'yes', false ),
			$desc ? '<p class="description">' . $desc . '</p>' : ''
		);
	}

	protected function row_text( $s, $key, $label, $class = 'regular-text', $desc = '', $ltr = false ) {
		printf(
			'<tr><th>%s</th><td><input type="text" name="%s" value="%s" class="%s"%s>%s</td></tr>',
			esc_html( $label ),
			esc_attr( $key ),
			esc_attr( isset( $s[ $key ] ) ? $s[ $key ] : '' ),
			esc_attr( $class ),
			$ltr ? ' dir="ltr"' : '',
			$desc ? '<p class="description">' . $desc . '</p>' : ''
		);
	}

	protected function row_url( $s, $key, $label, $desc = '' ) {
		$this->row_text( $s, $key, $label, 'large-text', $desc, true );
	}

	protected function row_area( $s, $key, $label, $desc = '', $rows = 3 ) {
		printf(
			'<tr><th>%s</th><td><textarea name="%s" class="large-text" rows="%d">%s</textarea>%s</td></tr>',
			esc_html( $label ),
			esc_attr( $key ),
			(int) $rows,
			esc_textarea( isset( $s[ $key ] ) ? $s[ $key ] : '' ),
			$desc ? '<p class="description">' . $desc . '</p>' : ''
		);
	}

	/**
	 * v2.4.1 — ردیف کد خام (برای باکس‌های مجوز فوتر: اینماد/درگاه پرداخت/…).
	 * برخلاف row_area، مقدار عیناً (بدون پالایش نمایشی متفاوت) به‌صورت تک‌فونت/چپ‌چین نمایش داده می‌شود
	 * تا چسباندن کد HTML/جاوااسکریپت راحت باشد؛ پالایش واقعی هنگام ذخیره در maybe_save انجام می‌شود.
	 */
	protected function row_code( $s, $key, $label, $desc = '', $rows = 5 ) {
		printf(
			'<tr><th>%s</th><td><textarea name="%s" class="large-text code" rows="%d" dir="ltr" style="font-family:Consolas,Menlo,monospace;font-size:12.5px;direction:ltr;text-align:left">%s</textarea>%s</td></tr>',
			esc_html( $label ),
			esc_attr( $key ),
			(int) $rows,
			esc_textarea( isset( $s[ $key ] ) ? $s[ $key ] : '' ),
			$desc ? '<p class="description">' . $desc . '</p>' : ''
		);
	}

	/**
	 * v2.3.0 — ردیف انتخاب تصویر از کتابخانه رسانه (برای لوگوی روشن/تاریک).
	 * خروجی: اینپوت مخفی (شناسه پیوست) + پیش‌نمایش + دکمه‌های انتخاب/حذف؛ تعامل با JS همان کارت.
	 */
	protected function row_media( $s, $key, $label, $desc = '' ) {
		$dth_id  = isset( $s[ $key ] ) ? (int) $s[ $key ] : 0;
		$dth_url = ( $dth_id && function_exists( 'wp_get_attachment_image_url' ) ) ? (string) wp_get_attachment_image_url( $dth_id, 'medium' ) : '';
		echo '<tr><th>' . esc_html( $label ) . '</th><td>';
		printf( '<input type="hidden" id="dth-%1$s" name="%1$s" value="%2$d">', esc_attr( $key ), $dth_id );
		printf(
			'<span style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap"><img id="dth-%1$s-img" src="%2$s" alt="" style="max-height:44px;max-width:190px;width:auto;display:%3$s;background:#f6f7fa;border:1px solid #e6e8ef;border-radius:8px;padding:5px">',
			esc_attr( $key ),
			esc_url( $dth_url ),
			'' !== $dth_url ? 'inline-block' : 'none'
		);
		printf( '<button type="button" class="button" data-dth-media="%s">انتخاب تصویر</button>', esc_attr( $key ) );
		printf( '<button type="button" class="button" data-dth-clear="%s"%s>حذف</button></span>', esc_attr( $key ), $dth_id ? '' : ' style="display:none"' );
		echo $desc ? '<p class="description">' . $desc . '</p>' : '';
		echo '</td></tr>';
	}

	/** چیپ وضعیت برگه‌های خودکار */
	protected function page_chip( $s, $key, $title ) {
		$pid = (int) ( isset( $s[ $key ] ) ? $s[ $key ] : 0 );
		if ( $pid ) {
			printf(
				'<span class="dhm-chip dhm-chip-ok">✓ %s — <a href="%s" target="_blank">مشاهده</a> | <a href="%s">ویرایش</a></span>',
				esc_html( $title ),
				esc_url( function_exists( 'get_permalink' ) ? get_permalink( $pid ) : '#' ),
				esc_url( admin_url( 'post.php?post=' . $pid . '&action=edit' ) )
			);
		} else {
			printf( '<span class="dhm-chip">… %s — ساخته نشده (از ابزارکیت بسازید)</span>', esc_html( $title ) );
		}
	}

	/* ------------------------------------------------------------------
	 *  رندر صفحه تنظیمات
	 * ---------------------------------------------------------------- */

	public function page_settings() {
		$s = Dastyar_Theme_Settings::all();

		// v2.3.0 — کتابخانه رسانه برای انتخابگر لوگو (فقط همین صفحه — گارد maybe_save خارج از این صفحه همه‌چیز را مسدود می‌کند)
		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}

		echo '<div class="wrap dhm-admin" dir="rtl">';
		echo '<style>
			.dhm-admin h1{font-size:22px;font-weight:800;color:#242536}
			.dhm-admin .dhm-card{background:#fff;border:1.5px solid #e3ebe7;border-radius:14px;padding:20px 22px;margin:18px 0;max-width:980px}
			.dhm-admin h2{font-size:16px;font-weight:800;color:#14201b;border-bottom:2px solid #17a16d;display:inline-block;padding-bottom:6px;margin:0 0 14px}
			.dhm-admin .dhm-note{background:#e9f6ee;border:1.5px solid #bfe6d2;border-radius:12px;padding:14px 18px;color:#166b47;max-width:980px}
			.dhm-admin .dhm-note-const{background:#f6f7fa;border:1.5px solid #e6e8ef;border-radius:12px;padding:14px 18px;color:#3a3c4e;max-width:980px}
			.dhm-admin table.form-table th{width:200px;font-weight:700}
			.dhm-admin code{background:#eef7f2;border-radius:6px;padding:3px 8px;color:#0f5132;font-weight:700}
			.dhm-admin .button-primary{background:#17a16d!important;border-color:#17a16d!important;border-radius:10px!important;font-weight:700}
			.dhm-admin .dhm-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px 26px}
			.dhm-admin .dhm-mini{background:#f7faf8;border:1px solid #eef2f0;border-radius:10px;padding:12px 14px;margin-bottom:10px}
			.dhm-admin .dhm-mini input,.dhm-admin .dhm-mini select,.dhm-admin .dhm-mini textarea{max-width:100%}
			.dhm-admin .dhm-mini label{font-size:12px;font-weight:700;color:#5a5d6e;display:block;margin:6px 0 2px}
			.dhm-admin .dhm-chip{display:inline-block;background:#f6f7fa;border:1px solid #e6e8ef;border-radius:999px;padding:5px 14px;margin:3px;font-size:12px;font-weight:700;color:#5a5d6e}
			.dhm-admin .dhm-chip-ok{background:#e9f6ee;border-color:#bfe6d2;color:#166b47}
			.dhm-admin .dhm-anchor{position:sticky;top:32px;z-index:2;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);padding:8px 0;max-width:980px}
			.dhm-admin .dhm-hero-sw{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px 18px;max-width:640px}
			.dhm-admin .dhm-sw{font-size:12.5px;font-weight:700;color:#3a3c4e;background:#f7faf8;border:1px solid #eef2f0;border-radius:9px;padding:6px 10px;display:inline-flex;align-items:center;gap:6px}
		</style>';

		echo '<h1>قالب دستیار — مدیریت محتوای سایت</h1>';
		if ( ! empty( $_GET['dth_saved'] ) ) {
			$m = 'kit' === $_GET['dth_saved'] ? 'ابزارکیت اجرا شد؛ برگه‌ها و منو ساخته/به‌روزرسانی شدند.' : 'تنظیمات ذخیره شد.';
			echo '<div class="notice notice-success" style="max-width:980px"><p>' . esc_html( $m ) . '</p></div>';
		}

		printf(
			'<div class="dhm-note">قالب «دستیار» نسخه <code>%s</code> — همه محتوای سایت (لندینگ، هدر، فوتر و صفحات داخلی) از همین‌جا قابل ویرایش است؛ بدون تداخل با ووکامرس و هسته دستیار. صفحات خودکار را از ابزارکیت انتهای صفحه بسازید.</div>',
			esc_html( DTH_VERSION )
		);

		// لنگرها (ناوبری سریع بین کارت‌ها)
		echo '<div class="dhm-anchor">';
		foreach ( array(
			'behavior' => 'رفتار', 'header' => 'هدر', 'hero' => 'هیرو', 'leads' => 'مشاوره', 'features' => 'امکانات', 'plans' => 'تعرفه‌ها',
			'homecat' => 'کاتالوگ', 'homevideo' => 'ویدیو', 'homeben' => 'مزایا',
			'about' => 'درباره', 'contact' => 'تماس', 'download' => 'دریافت', 'pay' => 'پرداخت',
			'tutorial' => 'آموزش', 'syspages' => 'برگه‌های سیستمی', 'footer' => 'فوتر', 'kit' => 'ابزارکیت',
		) as $id => $lb ) {
			printf( '<a class="dhm-chip" href="#dth-%s">%s</a>', esc_attr( $id ), esc_html( $lb ) );
		}
		echo '</div>';

		echo '<form method="post">';
		wp_nonce_field( 'dth_save_settings' );
		echo '<input type="hidden" name="dth_save_settings" value="1">';

		// ---------- رفتار ----------
		echo '<div class="dhm-card" id="dth-behavior"><h2>رفتار سایت</h2><table class="form-table">';
		$this->row_check( $s, 'instant_load', 'لود آنی صفحات', 'نقشه‌برداری هوشمند (PJAX): کلیک روی لینک‌های داخلی بدون رفرش کامل — نوار پیشرفت سبز بالای صفحه نمایش داده می‌شود.' );
		$this->row_area( $s, 'pj_exclude', 'مسیرهای مستثنی از لود آنی', 'هر خط یک پیشوند مسیر. پیش‌فرض‌ها: <code>/wp-admin/</code>، <code>/wp-login.php</code>، <code>/checkout</code>، <code>/cart</code>، <code>/my-account</code>', 4 );
		$this->row_check( $s, 'shop_guard', 'گارد فروشگاه ووکامرس', 'صفحات فروشگاه/محصول/دسته‌بندی ووکامرس برای کاربران عادی نمایش داده نشود و به برگه «محصولات» (کاتالوگ دستیار) هدایت شوند؛ مدیران هرچیزی را می‌بینند.' );
		echo '</table></div>';

		// ---------- هدر ----------
		echo '<div class="dhm-card" id="dth-header"><h2>هدر سایت</h2><table class="form-table">';
		// v2.1.0 — سبک هدر
		$dth_hs = isset( $s['header_style'] ) ? $s['header_style'] : 'default';
		echo '<tr><th>سبک هدر</th><td><select name="header_style">';
		foreach ( array(
			'default' => 'پیش‌فرض — شیشه‌ای سفید چسبان',
			'dark'    => 'تیره — سرمه‌ای برند با متن سفید',
			'center'  => 'وسط‌چین — لوگو و منو در مرکز',
			'minimal' => 'مینیمال — فقط لوگو و دکمه‌ها در خط ساده',
		) as $hk => $hl ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $hk ), selected( $dth_hs, $hk, false ), esc_html( $hl ) );
		}
		echo '</select><p class="description">چهار سبک آماده برای هدر؛ قابل تغییر هر زمان.</p></td></tr>';
		$this->row_text( $s, 'logo_text', 'متن لوگو', 'regular-text', 'خالی = نام سایت. اگر «لوگوی سفارشی» در سفارشی‌سازی تنظیم شده باشد، همان تصویر نمایش داده می‌شود.', false );
		// v2.3.0 — لوگوی جداگانه برای حالت روشن و تاریک (به درخواست کاربر: «در حالت تاریک اونجوری نمایش بده»)
		$this->row_media( $s, 'logo_light_id', 'لوگو در حالت روشن', 'تصویری که در حالت روشن سایت نمایش داده می‌شود. همین که یکی از این دو فیلد پر شود، جایگزین لوگوی فعلی هدر می‌شوند.' );
		$this->row_media( $s, 'logo_dark_id', 'لوگو در حالت تاریک', 'تصویری که «فقط در حالت تاریک» نمایش داده می‌شود (مثلاً نسخه سفید/روشن لوگو). اگر خالی بماند، همان لوگوی روشن در هر دو حالت دیده می‌شود.' );
		// اسکریپت انتخابگر رسانه (فقط همین دو فیلد — بدون وابستگی تازه)
		echo '<script>(function(){function b(t,a,f){var n=document.querySelectorAll("["+a+"]");for(var i=0;i<n.length;i++){n[i].addEventListener("click",function(e){e.preventDefault();f(e.currentTarget);});}}'
			. 'b(0,"data-dth-media",function(t){if(typeof wp==="undefined"||!wp.media){return;}var k=t.getAttribute("data-dth-media");var f=wp.media({title:"انتخاب لوگو",button:{text:"انتخاب تصویر"},multiple:false});f.on("select",function(){var at=f.state().get("selection").first().toJSON();var u=(at.sizes&&at.sizes.medium)?at.sizes.medium.url:at.url;var inp=document.getElementById("dth-"+k);var img=document.getElementById("dth-"+k+"-img");var clr=document.querySelector("[data-dth-clear=\'"+k+"\']");if(inp){inp.value=at.id;}if(img){img.src=u;img.style.display=u?"inline-block":"none";}if(clr){clr.style.display="";}});f.open();});'
			. 'b(0,"data-dth-clear",function(t){var k=t.getAttribute("data-dth-clear");var inp=document.getElementById("dth-"+k);var img=document.getElementById("dth-"+k+"-img");if(inp){inp.value=0;}if(img){img.removeAttribute("src");img.style.display="none";}t.style.display="none";});'
			. '})();</script>';
		$this->row_area( $s, 'nav_links', 'لینک‌های منو (فالبک)', 'اگر به مکان «منوی اصلی» منویی تخصیص نداده باشید، این فهرست (هر خط: <code>برچسب|لینک</code>) نمایش داده می‌شود.', 4 );
		$this->row_text( $s, 'cta1_text', 'متن دکمه اصلی' );
		$this->row_url( $s, 'cta1_url', 'لینک دکمه اصلی', 'خالی = خودکار: برگه «ورود / ثبت‌نام»، سپس برگه پنل فروشنده افزونه دانیار پنل.' );
		$this->row_check( $s, 'hbtn_cta1_on', 'نمایش دکمه اصلی (ورود/ثبت‌نام)' );
		$this->row_text( $s, 'cta2_text', 'متن دکمه دوم', 'regular-text', 'خالی‌گذاشتن متن ← دکمه نمایش داده نمی‌شود.' );
		$this->row_url( $s, 'cta2_url', 'لینک دکمه دوم', 'خالی = خودکار: برگه «محصولات» (کاتالوگ).' );
		$this->row_check( $s, 'hbtn_cta2_on', 'نمایش دکمه دوم (مشاهده محصولات)' );
		$this->row_check( $s, 'hbtn_tut_on', 'نمایش دکمه «آموزش استفاده»', 'در هدر دسکتاپ و کشوی موبایل — برای مهمان و کاربر واردشده.' );
		$this->row_check( $s, 'announce_enabled', 'نمایش نوار اعلانات بالای هدر' );
		$this->row_text( $s, 'announce_text', 'متن اعلان', 'large-text' );
		$this->row_url( $s, 'announce_url', 'لینک اعلان (اختیاری)' );
		echo '</table></div>';

		// ---------- v2.4.0: مشاوره تلفنی ----------
		echo '<div class="dhm-card" id="dth-leads"><h2>مشاوره تلفنی (باکس موبایل هیرو)</h2><table class="form-table">';
		echo '<tr><th>کارکرد</th><td><p class="description" style="margin:0">وقتی هیرو روی سبک «فرم جذب» است و بازدیدکننده شماره موبایلش را وارد می‌کند، به‌جای رفتن مستقیم به صفحه ورود، پاپ‌آپ «نیاز به مشاوره داری؟» باز می‌شود. با تأیید کاربر، شماره‌اش در «<a href="' . esc_url( admin_url( 'themes.php?page=dastyar-theme-leads' ) ) . '">نمایش ← مشاوره‌ها</a>» ثبت می‌شود (+ اطلاع‌رسانی پیشخوان و ایمیل به مدیر). با انصراف، مثل قبل به صفحه ورود می‌رود.</p></td></tr>';
		$this->row_check( $s, 'leads_on', 'فعال‌بودن پاپ‌آپ مشاوره', 'خاموش ← همان رفتار قبلی: هدایت مستقیم به صفحه ورود.' );
		$this->row_text( $s, 'lead_pop_title', 'تیتر پاپ‌آپ' );
		$this->row_area( $s, 'lead_pop_text', 'متن پاپ‌آپ', '', 2 );
		$this->row_text( $s, 'lead_pop_yes', 'دکمه تأیید' );
		$this->row_text( $s, 'lead_pop_no', 'دکمه انصراف' );
		$this->row_text( $s, 'lead_done_t', 'تیتر پیام موفقیت' );
		$this->row_area( $s, 'lead_done_s', 'متن پیام موفقیت', '', 2 );
		echo '</table></div>';

		// ---------- هیرو ----------
		echo '<div class="dhm-card" id="dth-hero"><h2>صفحه اصلی — بخش هیرو</h2><table class="form-table">';
		$this->row_check( $s, 'sec_hero', 'نمایش بخش هیرو' );
		// v2.1.0 — انتخاب سبک هیرو (۵ سبک)
		$dth_hst = isset( $s['hero_style'] ) ? $s['hero_style'] : 'cards';
		echo '<tr><th>سبک هیرو</th><td><select name="hero_style">';
		foreach ( array(
			'cards'         => 'کارت‌های شناور کاتالوگ (پیش‌فرض)',
			'split'         => 'اسپلیت خلوت — متن راست + فضای خالی چپ',
			'constellation' => 'مداری — متن مرکزی + کارت‌های شناور دو سو',
			'leadform'      => 'فرم شروع — فیلد موبایل + دکمه (جذب سریع)',
			'banner'        => 'باکس سرمه‌ای کلاسیک (نسخه قبل)',
			'flow3d'        => 'کارتن سه‌بعدی معلق + پیام‌های چرخان (جدید)',
		) as $hsk => $hsl ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $hsk ), selected( $dth_hst, $hsk, false ), esc_html( $hsl ) );
		}
		echo '</select><p class="description">۶ سبک آماده برای هیرو؛ هر زمان خواستید می‌توانید بین آن‌ها جابه‌جا شوید — محتوای همه حفظ می‌شود. سبک‌ها از همان متن‌های همین بخش (+کارت‌ها و آمار پایین) استفاده می‌کنند.</p></td></tr>';
		$this->row_text( $s, 'hero_badge', 'متن نشان بالا', 'large-text' );
		$this->row_text( $s, 'hero_title', 'تیتر اصلی', 'large-text' );
		$this->row_text( $s, 'hero_hl', 'بخش سبزِ تیتر', 'large-text' );
		$this->row_text( $s, 'hero_sub', 'زیرتیتر', 'large-text' );
		$this->row_text( $s, 'hero_b1_text', 'متن دکمه اصلی هیرو' );
		$this->row_url( $s, 'hero_b1_url', 'لینک دکمه اصلی هیرو', 'خالی = همان لینک دکمه اصلی هدر.' );
		$this->row_text( $s, 'hero_b2_text', 'متن دکمه دوم هیرو' );
		$this->row_url( $s, 'hero_b2_url', 'لینک دکمه دوم هیرو', 'پیش‌فرض <code>#features</code>.' );
		$this->row_area( $s, 'hero_points', 'نقاط اعتماد (فقط سبک باکس کلاسیک)', 'هر خط یک نکته — با تیک سبز نمایش داده می‌شود.' );
		$this->row_area( $s, 'hero_orbit_msgs', 'پیام‌های حلقه دور کارتن (فقط سبک «کارتن سه‌بعدی معلق»)', 'هر خط یک پیام — حداکثر ۶ خط؛ به‌صورت دایره‌وار دور کارتن می‌چرخند.', 4 );
		$this->row_area( $s, 'hero_ministats', 'آمار جمع‌وجور هیرو (سبک کارت‌های شناور)', 'هر خط: <code>عدد|لیبل</code> — تا ۴ مورد.', 4 );
		echo '</table>';
		// کارت شاخص
		echo '<div class="dhm-grid"><div class="dhm-mini"><strong>کارت شاخص هیرو (وسط صحنه)</strong>';
		$choices = Dastyar_Theme_Landing::icon_choices();
		echo '<label>آیکن محصول</label><select name="heroc_icon" class="widefat">';
		foreach ( $choices as $ik => $il ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $ik ), selected( $s['heroc_icon'], $ik, false ), esc_html( $il ) );
		}
		echo '</select>';
		printf( '<label>عنوان محصول (خالی ← کارت شاخص نمایش داده نمی‌شود)</label><input type="text" name="heroc_title" value="%s" class="widefat">', esc_attr( $s['heroc_title'] ) );
		printf( '<label>قیمت</label><input type="text" name="heroc_price" value="%s" class="widefat">', esc_attr( $s['heroc_price'] ) );
		printf( '<label>قیمت خط‌خورده (اختیاری)</label><input type="text" name="heroc_old" value="%s" class="widefat">', esc_attr( $s['heroc_old'] ) );
		printf( '<label>برچسب تخفیف (اختیاری)</label><input type="text" name="heroc_off" value="%s" class="widefat" placeholder="مثلاً ٪۲۳">', esc_attr( $s['heroc_off'] ) );
		printf( '<label>لیبل سود</label><input type="text" name="heroc_profit_label" value="%s" class="widefat">', esc_attr( $s['heroc_profit_label'] ) );
		printf( '<label>مبلغ سود (خالی ← باکس سود نمایش داده نمی‌شود)</label><input type="text" name="heroc_profit" value="%s" class="widefat">', esc_attr( $s['heroc_profit'] ) );
		printf( '<label>متن دکمه</label><input type="text" name="heroc_btn" value="%s" class="widefat">', esc_attr( $s['heroc_btn'] ) );
		printf( '<label>لینک دکمه (خالی ← دکمه اصلی هدر)</label><input type="text" name="heroc_url" value="%s" class="widefat" dir="ltr">', esc_attr( $s['heroc_url'] ) );
		echo '</div></div>';
		echo '<table class="form-table">';
		$this->row_area( $s, 'hero_minicards', 'کارت‌های کوچک شناور (تا ۳ عدد)', 'هر خط: <code>عنوان|قیمت|رنگ|آیکن</code> — رنگ: ۱ سبز، ۲ سرمه‌ای، ۳ نارنجی، ۴ بنفش. مثال: <code>ساعت هوشمند|۱٬۴۵۰٬۰۰۰ تومان|2|watch</code>', 3 );
		$this->row_area( $s, 'hero_notes', 'اعلان‌های شناور پلتفرم (تا ۲ عدد)', 'هر خط: <code>متن|آیکن</code> — مثال: <code>سفارش جدید ثبت شد|cart</code>', 2 );
		echo '</table></div>';

		// ---------- آمار ----------
		echo '<div class="dhm-card" id="dth-stats"><h2>نوار آمار</h2><table class="form-table">';
		$this->row_check( $s, 'sec_stats', 'نمایش نوار آمار' );
		echo '</table><div class="dhm-grid">';
		for ( $n = 1; $n <= 4; $n++ ) {
			echo '<div class="dhm-mini"><strong>آمار ' . esc_html( Dastyar_Theme_Settings::fa_num( $n ) ) . '</strong>';
			printf( '<label>عدد</label><input type="text" name="stats_%d_num" value="%s" class="widefat" placeholder="مثل 2500+">', (int) $n, esc_attr( $s[ 'stats_' . $n . '_num' ] ) );
			printf( '<label>لیبل</label><input type="text" name="stats_%d_label" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'stats_' . $n . '_label' ] ) );
			echo '</div>';
		}
		echo '</div><p class="description">اعداد با شمارنده متحرک و ارقام فارسی نمایش داده می‌شوند؛ پسوند <code>+</code> یا <code>%</code> پشتیبانی می‌شود.</p></div>';

		// ---------- امکانات ----------
		echo '<div class="dhm-card" id="dth-features"><h2>بخش امکانات</h2><table class="form-table">';
		$this->row_check( $s, 'sec_features', 'نمایش بخش امکانات' );
		$this->row_text( $s, 'feat_title', 'تیتر بخش', 'large-text' );
		$this->row_text( $s, 'feat_sub', 'زیرتیتر بخش', 'large-text' );
		echo '</table><div class="dhm-grid">';
		$choices = Dastyar_Theme_Landing::icon_choices();
		for ( $n = 1; $n <= 6; $n++ ) {
			echo '<div class="dhm-mini"><strong>کارت ' . esc_html( Dastyar_Theme_Settings::fa_num( $n ) ) . '</strong>';
			echo '<label>آیکن</label><select name="feat_' . (int) $n . '_icon" class="widefat">';
			foreach ( $choices as $ik => $il ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $ik ), selected( $s[ 'feat_' . $n . '_icon' ], $ik, false ), esc_html( $il ) );
			}
			echo '</select>';
			printf( '<label>تیتر (خالی ← کارت حذف می‌شود)</label><input type="text" name="feat_%d_title" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'feat_' . $n . '_title' ] ) );
			printf( '<label>توضیح</label><input type="text" name="feat_%d_desc" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'feat_' . $n . '_desc' ] ) );
			echo '</div>';
		}
		echo '</div></div>';

		// ---------- مراحل ----------
		echo '<div class="dhm-card" id="dth-how"><h2>مراحل شروع (۳ قدم)</h2><table class="form-table">';
		$this->row_check( $s, 'sec_how', 'نمایش بخش مراحل' );
		$this->row_text( $s, 'how_title', 'تیتر بخش', 'large-text' );
		$this->row_text( $s, 'how_sub', 'زیرتیتر بخش', 'large-text' );
		echo '</table><div class="dhm-grid">';
		for ( $n = 1; $n <= 3; $n++ ) {
			echo '<div class="dhm-mini"><strong>قدم ' . esc_html( Dastyar_Theme_Settings::fa_num( $n ) ) . '</strong>';
			printf( '<label>تیتر</label><input type="text" name="how_%d_title" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'how_' . $n . '_title' ] ) );
			printf( '<label>توضیح</label><input type="text" name="how_%d_desc" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'how_' . $n . '_desc' ] ) );
			echo '</div>';
		}
		echo '</div></div>';

		// ---------- تعرفه‌ها ----------
		echo '<div class="dhm-card" id="dth-plans"><h2>تعرفه‌ها / رده‌های همکاری</h2><table class="form-table">';
		$this->row_check( $s, 'sec_plans', 'نمایش بخش تعرفه‌ها' );
		$this->row_text( $s, 'plans_title', 'تیتر بخش', 'large-text' );
		$this->row_text( $s, 'plans_sub', 'زیرتیتر بخش', 'large-text' );
		echo '</table><div class="dhm-grid">';
		for ( $n = 1; $n <= 3; $n++ ) {
			echo '<div class="dhm-mini"><strong>تعرفه ' . esc_html( Dastyar_Theme_Settings::fa_num( $n ) ) . '</strong>';
			printf( '<label>نام (خالی ← کارت حذف می‌شود)</label><input type="text" name="plan_%d_name" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'plan_' . $n . '_name' ] ) );
			printf( '<label>قیمت/مبلغ</label><input type="text" name="plan_%d_price" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'plan_' . $n . '_price' ] ) );
			printf( '<label>دوره/توضیح قیمت</label><input type="text" name="plan_%d_per" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'plan_' . $n . '_per' ] ) );
			printf( '<label>ویژگی‌ها (هر خط یک مورد)</label><textarea name="plan_%d_feats" class="widefat" rows="3">%s</textarea>', (int) $n, esc_textarea( $s[ 'plan_' . $n . '_feats' ] ) );
			printf( '<label>متن دکمه</label><input type="text" name="plan_%d_btn" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'plan_' . $n . '_btn' ] ) );
			printf( '<label>لینک دکمه (خالی ← دکمه اصلی)</label><input type="text" name="plan_%d_url" value="%s" class="widefat" dir="ltr">', (int) $n, esc_attr( $s[ 'plan_' . $n . '_url' ] ) );
			printf( '<label style="margin-top:8px"><input type="checkbox" name="plan_%d_hot" value="yes"%s> برجسته‌سازی به‌عنوان «محبوب‌ترین»</label>', (int) $n, checked( $s[ 'plan_' . $n . '_hot' ], 'yes', false ) );
			echo '</div>';
		}
		echo '</div></div>';

		// ---------- نظرات ----------
		echo '<div class="dhm-card" id="dth-testi"><h2>نظرات فروشنده‌ها</h2><table class="form-table">';
		$this->row_check( $s, 'sec_testi', 'نمایش بخش نظرات' );
		$this->row_text( $s, 'testi_title', 'تیتر بخش', 'large-text' );
		$this->row_text( $s, 'testi_sub', 'زیرتیتر بخش', 'large-text' );
		echo '</table><div class="dhm-grid">';
		for ( $n = 1; $n <= 3; $n++ ) {
			echo '<div class="dhm-mini"><strong>نظر ' . esc_html( Dastyar_Theme_Settings::fa_num( $n ) ) . '</strong>';
			printf( '<label>متن نظر (خالی ← کارت حذف می‌شود)</label><textarea name="testi_%d_quote" class="widefat" rows="3">%s</textarea>', (int) $n, esc_textarea( $s[ 'testi_' . $n . '_quote' ] ) );
			printf( '<label>نام فروشگاه/فروشنده</label><input type="text" name="testi_%d_name" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'testi_' . $n . '_name' ] ) );
			printf( '<label>دسته/توضیح کوتاه</label><input type="text" name="testi_%d_shop" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'testi_' . $n . '_shop' ] ) );
			echo '</div>';
		}
		echo '</div></div>';

		// ---------- سوالات متداول ----------
		echo '<div class="dhm-card" id="dth-faq"><h2>سوالات متداول</h2><table class="form-table">';
		$this->row_check( $s, 'sec_faq', 'نمایش بخش سوالات (صفحه اصلی + برگه سوالات متداول)', 'این فهرست هم در صفحه اصلی و هم در برگه مستقل «سوالات متداول» استفاده می‌شود.' );
		$this->row_text( $s, 'faq_title', 'تیتر بخش', 'large-text' );
		$this->row_area( $s, 'faq_items', 'سؤال‌ها و پاسخ‌ها', 'هر خط: <code>سؤال|پاسخ</code> — آکوردئون.', 6 );
		echo '</table></div>';

		// ---------- نمونه کاتالوگ (v2.1.0) ----------
		echo '<div class="dhm-card" id="dth-homecat"><h2>صفحه اصلی — نمونه کاتالوگ</h2><table class="form-table">';
		$this->row_check( $s, 'sec_catalog', 'نمایش بخش نمونه کاتالوگ', 'بخشی از محصولات کاتالوگ دستیار با همان کدکوتاه <code>[dastyar_catalog]</code> نمایش داده می‌شود (جستجو/چیپ‌ها خاموش). نیاز به افزونه دستیار کاتالوگ فعال.' );
		$this->row_text( $s, 'cat_title', 'تیتر بخش', 'large-text' );
		$this->row_text( $s, 'cat_sub', 'زیرتیتر بخش', 'large-text' );
		echo '<tr><th>تعداد محصول</th><td><input type="number" name="cat_count" value="' . esc_attr( $s['cat_count'] ) . '" min="1" max="12" class="small-text"> <span class="description">۱ تا ۱۲</span></td></tr>';
		echo '<tr><th>تعداد ستون</th><td><input type="number" name="cat_cols" value="' . esc_attr( $s['cat_cols'] ) . '" min="2" max="6" class="small-text"> <span class="description">۲ تا ۶</span></td></tr>';
		$this->row_text( $s, 'cat_btn', 'متن دکمه «مشاهده همه»', 'regular-text', 'خالی ← دکمه نمایش داده نمی‌شود.' );
		echo '</table></div>';

		// ---------- ویدیو آموزش (v2.1.0) ----------
		echo '<div class="dhm-card" id="dth-homevideo"><h2>صفحه اصلی — ویدیو آموزشی</h2><table class="form-table">';
		$this->row_check( $s, 'sec_video', 'فعال بودن بخش ویدیو', '<strong>نکته:</strong> تا وقتی «لینک ویدیو» خالی باشد، این بخش در سایت نمایش داده نمی‌شود — پس بدون نگرانی می‌توانید روشن بگذارید.' );
		$this->row_text( $s, 'video_title', 'تیتر بخش', 'large-text' );
		$this->row_text( $s, 'video_sub', 'زیرتیتر بخش', 'large-text' );
		$this->row_url( $s, 'video_url', 'لینک ویدیو (آپارات/یوتیوب یا فایل mp4)', 'آدرس جاسازی آپارات (مثل <code>https://www.aparat.com/video/video/embed/videohash/XXXX/vt/frame</code>) یا لینک مستقیم فایل <code>mp4</code>.' );
		$this->row_text( $s, 'video_note', 'یادداشت زیر ویدیو (اختیاری)', 'large-text' );
		$this->row_text( $s, 'video_btn', 'متن دکمه زیر ویدیو' );
		$this->row_url( $s, 'video_btn_url', 'لینک دکمه زیر ویدیو', 'خالی = برگه «آموزش استفاده».' );
		echo '</table></div>';

		// ---------- مزایا (v2.1.0) ----------
		echo '<div class="dhm-card" id="dth-homeben"><h2>صفحه اصلی — بخش مزایا</h2><table class="form-table">';
		$this->row_check( $s, 'sec_benefits', 'نمایش بخش مزایا' );
		$this->row_text( $s, 'ben_badge', 'متن نشان', 'regular-text' );
		$this->row_text( $s, 'ben_title', 'تیتر بخش', 'large-text' );
		$this->row_text( $s, 'ben_sub', 'زیرتیتر بخش', 'large-text' );
		$this->row_area( $s, 'ben_items', 'موارد مزایا', 'هر خط یک مورد — با تیک سبز در دو ستون نمایش داده می‌شود.', 6 );
		$this->row_text( $s, 'ben_cta_text', 'متن دکمه' );
		$this->row_url( $s, 'ben_cta_url', 'لینک دکمه', 'خالی = همان لینک دکمه اصلی هدر.' );
		echo '</table></div>';

		// ---------- CTA پایانی ----------
		echo '<div class="dhm-card" id="dth-cta"><h2>CTA پایانی صفحه اصلی</h2><table class="form-table">';
		$this->row_check( $s, 'sec_cta', 'نمایش CTA پایانی' );
		$this->row_text( $s, 'cta_title', 'تیتر', 'large-text' );
		$this->row_text( $s, 'cta_text', 'متن', 'large-text' );
		$this->row_text( $s, 'cta_btn', 'متن دکمه' );
		$this->row_url( $s, 'cta_url', 'لینک دکمه', 'خالی = همان لینک دکمه اصلی هدر.' );
		echo '</table></div>';

		// ---------- درباره ما ----------
		echo '<div class="dhm-card" id="dth-about"><h2>برگه «درباره ما»</h2><table class="form-table">';
		$this->row_text( $s, 'about_title', 'تیتر', 'large-text' );
		$this->row_text( $s, 'about_sub', 'زیرتیتر', 'large-text' );
		$this->row_area( $s, 'about_text', 'متن معرفی', 'هر خط یک پاراگراف.', 5 );
		$this->row_area( $s, 'about_points', 'نقاط برجسته', 'هر خط یک مورد — با تیک سبز.', 4 );
		$this->row_check( $s, 'about_stats_on', 'نمایش نوار آمار در انتهای برگه', 'همان آمار صفحه اصلی (بخش «نوار آمار») زیر متن درباره ما نمایش داده می‌شود.' );
		echo '</table></div>';

		// ---------- تماس با ما ----------
		echo '<div class="dhm-card" id="dth-contact"><h2>برگه «تماس با ما»</h2><table class="form-table">';
		$this->row_text( $s, 'contact_title', 'تیتر', 'large-text' );
		$this->row_text( $s, 'contact_sub', 'زیرتیتر', 'large-text' );
		$this->row_text( $s, 'contact_phone', 'تلفن' );
		$this->row_text( $s, 'contact_email', 'ایمیل', 'regular-text', '', true );
		$this->row_text( $s, 'contact_address', 'نشانی', 'large-text' );
		$this->row_text( $s, 'contact_hours', 'ساعات پاسخ‌گویی', 'regular-text' );
		$this->row_check( $s, 'contact_cards_on', 'نمایش کارت‌های اطلاعات تماس', 'تلفن، ایمیل، نشانی و ساعات پاسخ‌گویی به‌صورت کارت نمایش داده می‌شوند.' );
		$this->row_check( $s, 'contact_form_enabled', 'فعال‌بودن فرم تماس', 'پیام‌ها به ایمیل مدیر سایت (' . esc_html( (string) get_option( 'admin_email', '' ) ) . ') ارسال می‌شود.' );
		echo '</table></div>';

		// ---------- دریافت پلاگین ----------
		echo '<div class="dhm-card" id="dth-download"><h2>برگه «دریافت پلاگین»</h2><table class="form-table">';
		$this->row_text( $s, 'dl_title', 'تیتر', 'large-text' );
		$this->row_text( $s, 'dl_sub', 'زیرتیتر', 'large-text' );
		$this->row_text( $s, 'dl_version', 'نسخه فعلی', 'regular-text' );
		$this->row_url( $s, 'dl_url', 'آدرس دانلود افزونه' );
		$this->row_area( $s, 'dl_changes', 'ویژگی‌ها/تغییرات', 'هر خط یک مورد — با تیک سبز.', 4 );
		$this->row_area( $s, 'dl_note', 'یادداشت/نیازمندی‌ها', '', 3 );
		$this->row_check( $s, 'dl_steps_on', 'نمایش باکس «مسیر نصب»', 'راهنمای قدم‌به‌قدم نصب افزونه اتصال‌دهنده کنار کارت دانلود.' );
		echo '</table></div>';

		// ---------- روند پرداخت ----------
		echo '<div class="dhm-card" id="dth-pay"><h2>برگه «روند پرداخت»</h2><table class="form-table">';
		$this->row_text( $s, 'pay_title', 'تیتر', 'large-text' );
		$this->row_text( $s, 'pay_sub', 'زیرتیتر', 'large-text' );
		echo '</table><div class="dhm-grid">';
		for ( $n = 1; $n <= 4; $n++ ) {
			echo '<div class="dhm-mini"><strong>مرحله ' . esc_html( Dastyar_Theme_Settings::fa_num( $n ) ) . '</strong>';
			printf( '<label>تیتر</label><input type="text" name="pay_%d_t" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'pay_' . $n . '_t' ] ) );
			printf( '<label>توضیح</label><input type="text" name="pay_%d_d" value="%s" class="widefat">', (int) $n, esc_attr( $s[ 'pay_' . $n . '_d' ] ) );
			echo '</div>';
		}
		echo '</div><table class="form-table">';
		$this->row_check( $s, 'pay_cta_on', 'نمایش CTA پایانی («کیف پول‌تان را شارژ کنید»)' );
		echo '</table></div>';

		// ---------- وبلاگ ----------
		echo '<div class="dhm-card" id="dth-blog"><h2>برگه «مقالات» (وبلاگ)</h2><table class="form-table">';
		$this->row_text( $s, 'blog_title', 'تیتر', 'large-text' );
		$this->row_text( $s, 'blog_sub', 'زیرتیتر', 'large-text' );
		echo '<tr><th>مدیریت نوشته‌ها</th><td>نوشته‌ها را از پیشخوان ← <a href="' . esc_url( admin_url( 'edit.php' ) ) . '">نوشته‌ها</a> مدیریت کنید؛ این برگه خودکار لیستشان را با کارت‌های برند نشان می‌دهد.</td></tr>';
		echo '</table></div>';

		// ---------- برگه آموزش استفاده (v2.1.0) ----------
		echo '<div class="dhm-card" id="dth-tutorial"><h2>برگه «آموزش استفاده»</h2><table class="form-table">';
		$this->row_text( $s, 'tut_title', 'تیتر هیرو', 'large-text' );
		$this->row_text( $s, 'tut_sub', 'زیرتیتر هیرو', 'large-text' );
		$this->row_text( $s, 'tut_m1', 'نام مسیر اول (خودکار — وردپرس)', 'regular-text' );
		$this->row_text( $s, 'tut_m2', 'نام مسیر دوم (اینستاگرام)', 'regular-text' );
		$this->row_text( $s, 'tut_m3', 'نام مسیر سوم (سایت‌سازها)', 'regular-text' );
		$this->row_text( $s, 'tut_start_t', 'تیتر بخش «شروع کار»', 'large-text' );
		$this->row_text( $s, 'tut_start_s', 'زیرتیتر بخش «شروع کار»', 'large-text' );
		echo '<tr><th>سوییچ هیرو</th><td><p class="description">روشن/خاموش‌کردن هیروی این برگه از کارت «برگه‌های سیستمی» (ردیف «هیروی هر برگه» ← آموزش استفاده) انجام می‌شود.</p></td></tr>';
		echo '</table></div>';

		// ---------- برگه‌های سیستمی (v2.0.0) ----------
		echo '<div class="dhm-card" id="dth-syspages"><h2>برگه‌های سیستمی — پنل کاربری / ورود / کاتالوگ</h2><table class="form-table">';
		echo '<tr><td colspan="2"><p class="description">تیتر و زیرتیتر برگه‌هایی که محتوایشان با افزونه‌های دستیار (پنل/کاتالوگ) رندر می‌شود از این‌جا مدیریت می‌شود؛ خالی‌گذاشتن زیرتیتر ← نمایش داده نمی‌شود.</p></td></tr>';
		$this->row_text( $s, 'panel_title', 'تیتر برگه پنل کاربری', 'large-text' );
		$this->row_text( $s, 'auth_title', 'تیتر برگه ورود / ثبت‌نام', 'large-text' );
		$this->row_text( $s, 'auth_sub', 'زیرتیتر برگه ورود / ثبت‌نام', 'large-text' );
		$this->row_text( $s, 'catalog_chip', 'متن نشان برگه کاتالوگ', 'regular-text' );
		$this->row_text( $s, 'catalog_title', 'تیتر برگه کاتالوگ', 'large-text' );
		$this->row_text( $s, 'catalog_sub', 'زیرتیتر برگه کاتالوگ', 'large-text' );
		$this->row_check( $s, 'page_hero_on', 'نمایش هیروی برگه‌های داخلی (کلید اصلی)', 'باکس عنوان بالای برگه‌های داخلی. خاموش ← هیچ برگه‌ای هیرو ندارد؛ روشن ← برای هر برگه از چک‌باکس‌های زیر تصمیم بگیرید.' );
		// v2.1.0 — سوییچ جداگانه هیرو برای هر نوع برگه
		echo '<tr><th>هیروی هر برگه (جداگانه)</th><td><div class="dhm-hero-sw">';
		foreach ( Dastyar_Theme_Settings::page_hero_ids() as $dth_phid => $dth_phlb ) {
			printf(
				'<label class="dhm-sw"><input type="checkbox" name="%s" value="yes"%s> %s</label>',
				esc_attr( 'ph_' . $dth_phid ),
				checked( Dastyar_Theme_Settings::yes( 'ph_' . $dth_phid ), true, false ),
				esc_html( $dth_phlb )
			);
		}
		echo '</div><p class="description">فقط وقتی کلید اصلی بالا روشن باشد اثر دارند. مثلاً هیروی «پنل کاربری» را خاموش کنید تا پنل تمام‌عرض و جدی شود.</p></td></tr>';
		// v2.1.0 — سبک پنل کاربری
		$dth_pst = isset( $s['panel_style'] ) ? $s['panel_style'] : 'classic';
		echo '<tr><th>سبک برگه پنل کاربری</th><td><select name="panel_style">';
		foreach ( array(
			'classic' => 'کلاسیک — هیروی باریک + پنل روی کاغذ سفید',
			'full'    => 'تمام — هیروی کامل با زیرتیتر',
			'bare'    => 'بدون هیرو — پنل از بالای صفحه شروع شود (جدی و فشرده)',
			'gray'    => 'خاکستری — پس‌زمینه ملایم دور پنل',
			'card'    => 'کارت — پنل داخل قاب سفید با سایه',
		) as $pk => $pl ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $pk ), selected( $dth_pst, $pk, false ), esc_html( $pl ) );
		}
		echo '</select><p class="description">نمای برگه «پنل کاربری فروشنده» را انتخاب کنید؛ هر زمان قابل تغییر است.</p></td></tr>';
		echo '</table></div>';

		// ---------- فوتر ----------
		echo '<div class="dhm-card" id="dth-footer"><h2>فوتر سایت</h2><table class="form-table">';
		// v2.1.1 — سبک فوتر (۵ حالت)
		$dth_fst = isset( $s['footer_style'] ) ? $s['footer_style'] : 'columns';
		echo '<tr><th>سبک فوتر</th><td><select name="footer_style">';
		foreach ( array(
			'columns' => 'چندستونه کلاسیک (پیش‌فرض)',
			'center'  => 'وسط‌چین مینیمال — لوگو و لینک‌ها در مرکز',
			'deep'    => 'سرمه‌ای عمیق — خط مرزی سبز بالای فوتر',
			'split'   => 'دوپاره — برند پهن بالا، ستون‌های لینک پایین',
			'slim'    => 'نوار فشرده تک‌ردیفه — برای سایت‌های خلوت',
		) as $fk => $fl ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $fk ), selected( $dth_fst, $fk, false ), esc_html( $fl ) );
		}
		echo '</select><p class="description">۵ سبک آماده برای فوتر؛ محتوای همه از همین کارت خوانده می‌شود و هر زمان قابل تعویض است.</p></td></tr>';
		$this->row_area( $s, 'footer_about', 'متن معرفی (ستون اول)', '', 3 );
		$this->row_area( $s, 'footer_links', 'لینک‌های سریع', 'هر خط: <code>برچسب|لینک</code> — در کنار منوی فوتر (در صورت تخصیص) نمایش داده می‌شود.', 4 );
		$this->row_check( $s, 'footer_credit', 'نمایش کپی‌رایت و اعتبار قالب' );
		echo '</table></div>';

		// v2.4.1 — باکس‌های مجوز فوتر (اینماد / درگاه پرداخت / …)
		echo '<div class="dhm-card" id="dth-trust-badges"><h2>باکس‌های مجوز فوتر (اینماد، درگاه پرداخت و…)</h2>';
		echo '<p class="description">کد آماده هر مجوز (مثلاً کد دریافتی از سایت اینماد یا لوگوی درگاه پرداخت) را همان‌طور که هست در کادر پایین بچسبانید؛ در فوتر سایت به‌صورت یک باکس کنار هم نمایش داده می‌شوند. هر باکس خالی باشد، نمایش داده نمی‌شود.</p>';
		echo '<table class="form-table">';
		$this->row_check( $s, 'trust_badges_on', 'نمایش ردیف باکس‌های مجوز در فوتر' );
		for ( $n = 1; $n <= 3; $n++ ) {
			$this->row_text( $s, 'trust_badge_' . $n . '_title', 'عنوان باکس ' . $n, 'regular-text', '' );
			$this->row_code( $s, 'trust_badge_' . $n . '_code', 'کد باکس ' . $n, 'کد اینماد معمولاً یک تگ <code>&lt;script&gt;</code> است؛ کد درگاه پرداخت می‌تواند لینک/تصویر ساده باشد.' );
		}
		echo '</table></div>';

		// v2.4.9 — منوی اپلیکیشنی موبایل (نوار پایین ثابت)
		echo '<div class="dhm-card" id="dth-mobnav"><h2>منوی اپلیکیشنی موبایل</h2>';
		echo '<p class="description">یک نوار ناوبری ثابت پایین صفحه، مخصوص نمایش موبایل — دقیقاً حس اپلیکیشن. تا ۵ خانه قابل تنظیم؛ خانه‌های «خاموش» نمایش داده نمی‌شوند.</p>';
		echo '<table class="form-table">';
		$this->row_check( $s, 'mobnav_enabled', 'نمایش منوی اپلیکیشنی در موبایل' );
		$mobnav_types = array(
			'off'     => '— خاموش —',
			'home'    => 'خانه (صفحه اصلی)',
			'catalog' => 'محصولات (کاتالوگ)',
			'cart'    => 'سبد خرید',
			'account' => 'حساب من / ورود',
			'custom'  => 'سفارشی (لینک دلخواه)',
		);
		for ( $n = 1; $n <= 5; $n++ ) {
			$cur_type = isset( $s[ 'mobnav_' . $n . '_type' ] ) ? $s[ 'mobnav_' . $n . '_type' ] : 'off';
			echo '<tr><th>خانه ' . Dastyar_Theme_Settings::fa_num( $n ) . '</th><td>';
			echo '<select name="mobnav_' . $n . '_type" style="min-width:220px">';
			foreach ( $mobnav_types as $tk => $tl ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $tk ), selected( $cur_type, $tk, false ), esc_html( $tl ) );
			}
			echo '</select> ';
			printf( '<input type="text" name="mobnav_%d_label" value="%s" class="regular-text" placeholder="برچسب (مثلاً خانه)" style="max-width:160px">', $n, esc_attr( $s[ 'mobnav_' . $n . '_label' ] ?? '' ) );
			echo '<div style="margin-top:8px">';
			echo '<select name="mobnav_' . $n . '_icon">';
			foreach ( Dastyar_Theme_Landing::icon_choices() as $ik => $il ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $ik ), selected( $s[ 'mobnav_' . $n . '_icon' ] ?? '', $ik, false ), esc_html( $il ) );
			}
			echo '</select> <span class="description">آیکون (فقط برای نوع «سفارشی» استفاده می‌شود؛ بقیه انواع آیکون خودشان را دارند)</span>';
			printf( '<br><input type="url" name="mobnav_%d_url" value="%s" class="regular-text" dir="ltr" placeholder="https://… (فقط برای نوع سفارشی)" style="margin-top:6px">', $n, esc_attr( $s[ 'mobnav_' . $n . '_url' ] ?? '' ) );
			echo '</div></td></tr>';
		}
		echo '</table></div>';

		submit_button( 'ذخیره همه تنظیمات' );
		echo '</form>';

		// ---------- ابزارکیت ----------
		echo '<div class="dhm-card" id="dth-kit"><h2>ابزارکیت ساخت خودکار برگه‌ها</h2>';
		echo '<p>وضعیت برگه‌های خودکار قالب:</p><p>';
		$this->page_chip( $s, 'home_page', 'صفحه اصلی' );
		$this->page_chip( $s, 'catalog_page', 'محصولات (کاتالوگ)' );
		$this->page_chip( $s, 'tutorial_page', 'آموزش استفاده' );
		$this->page_chip( $s, 'about_page', 'درباره ما' );
		$this->page_chip( $s, 'contact_page', 'تماس با ما' );
		$this->page_chip( $s, 'faq_page', 'سوالات متداول' );
		$this->page_chip( $s, 'pay_page', 'روند پرداخت' );
		$this->page_chip( $s, 'download_page', 'دریافت پلاگین' );
		$this->page_chip( $s, 'auth_page', 'ورود / ثبت‌نام' );
		$this->page_chip( $s, 'panel_page', 'پنل کاربری' );
		$this->page_chip( $s, 'blog_page', 'مقالات' );
		echo '</p><form method="post">';
		wp_nonce_field( 'dth_setup_pages' );
		echo '<input type="hidden" name="dth_setup_pages" value="1">';
		submit_button( 'ساخت/به‌روزرسانی برگه‌ها + منو', 'secondary' );
		echo '</form><p class="description">برگه‌هایی که شناسه دارند دست‌نخورده می‌مانند؛ موارد جاافتاده ساخته، فرانت‌پیج (اگر تنظیم نباشد) و منوی دستیار (اگر تخصیص نداده باشید) پیکربندی می‌شود. برگه‌های <strong>پنل کاربری</strong> و <strong>ورود/ثبت‌نام</strong> از کدکوتاه‌های افزونه «دانیار پنل» و برگه <strong>محصولات</strong> از کدکوتاه <code>[dastyar_catalog]</code> استفاده می‌کنند.</p></div>';

		echo '</div>';
	}
}
