<?php
/**
 * Plugin Name:       تامین‌کننده‌ها
 * Plugin URI:        https://dastyar.shop
 * Description:       مدیریت تامین‌کننده‌ها در سایت مرکز: تعیین تامین‌کننده و قیمت تامین برای هر محصول، ستون و فیلتر در لیست محصولات (از جمله فیلتر «محصولات بدون تامین‌کننده»)، شماره تماس و آدرس هر تامین‌کننده، تعیین دسته‌جمعی، و خروجی اکسل ماهانه استعلام قیمت (قابل چاپ A4). بخش تامین‌کننده فقط برای کاربران دارای دسترسی مدیریت فروشگاه نمایش داده می‌شود و برای فروشندگان مخفی است. از v2.0.0: «پنل تامین‌کننده» با ورود مستقل (نام کاربری/گذرواژه)، ثبت محصول با تأیید مرکز، قیمت‌گذاری فرمولی، سفارش‌ها (قبول=تحویل به پست / کد رهگیری=تکمیل)، گزارش مرجوعی از مرکز، مالی (کارت/شبا + واریزی‌ها)، نمودار فروش، کد کوتاه [dastyar_supplier_panel].
 * Version:           2.1.3
 * Author:            Dastyar
 * Text Domain:       dastyar-suppliers
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DSUPP_VERSION', '2.1.3' );
define( 'DSUPP_TAX', 'dsupp_supplier' );            // تاکسونومی تامین‌کننده روی محصول
define( 'DSUPP_META_COST', '_dastyar_cost' );       // قیمت تامین‌کننده — مشترک با فیلد «قیمت تامین‌کننده (دستیار)» (چرخ دوباره اختراع نشد)
define( 'DSUPP_META_TID', '_dsupp_supplier_id' );   // شناسه ترم تامین‌کننده (دنورمال برای نمایش سریع)
define( 'DSUPP_META_TNAME', '_dsupp_supplier_name' ); // نام تامین‌کننده (دنورمال برای ستون محصولات)
define( 'DSUPP_META_TSLUG', '_dsupp_supplier_slug' ); // نامک تامین‌کننده (برای لینک فیلتر)
define( 'DSUPP_META_PHONE', '_dsupp_phone' );       // شماره تماس تامین‌کننده (متای ترم)
define( 'DSUPP_META_ADDR', '_dsupp_addr' );         // آدرس تامین‌کننده (متای ترم)
define( 'DSUPP_NONE', '__none__' );                 // مقدار ویژه فیلتر «فقط محصولات بدون تامین‌کننده»
// v2.0.0 — پنل تامین‌کننده
define( 'DSUPP_META_USER', '_dsupp_username' );     // نام کاربری ورود پنل (متای ترم)
define( 'DSUPP_META_HASH', '_dsupp_passhash' );     // هش گذرواژه پنل (متای ترم — هرگز متن ساده نه)
define( 'DSUPP_META_CARD', '_dsupp_card' );         // شماره کارت تسویه (متای ترم)
define( 'DSUPP_META_SHEBA', '_dsupp_sheba' );       // شماره شبا (متای ترم)
define( 'DSUPP_META_FORMULA', '_dsupp_formula' );   // فرمول قیمت‌گذاری هر تامین‌کننده: array(type,value) type in percent|fixed|multiplier
// v2.1.0 — پیوند به کاربر وردپرس با نقش «تامین‌کننده»
define( 'DSUPP_META_UID', '_dsupp_user_id' );       // شناسه کاربر وردپرس پیوندشده به تامین‌کننده (متای ترم) — یوزرنیم/پسورد همان کاربر = ورود پنل
// v2.1.1 — خودترمیمی: پیوند دوطرفه (سمت کاربر هم ثبت می‌شود تا گم نشود)
define( 'DSUPP_UMETA_TID', '_dsupp_tid' );          // شناسه تامین‌کننده پیوندشده (متای کاربر)
define( 'DSUPP_META_SUBMITTED', '_dsupp_submitted' ); // نشان «ثبت‌شده از پنل تامین‌کننده» روی محصول

/**
 * کلاس اصلی افزونه تامین‌کننده‌ها
 */
final class DSupp {

	const CAP = 'manage_woocommerce'; // فقط مدیر فروشگاه — نه فروشندگان (مورد ۳)

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );

		// مدیریت تامین‌کننده‌ها (افزودن/ویرایش/حذف) + خروجی + تعیین دسته‌جمعی
		add_action( 'admin_post_dsupp_add_supplier', array( $this, 'handle_add_supplier' ) );
		add_action( 'admin_post_dsupp_edit_supplier', array( $this, 'handle_edit_supplier' ) );
		add_action( 'admin_post_dsupp_del_supplier', array( $this, 'handle_del_supplier' ) );
		add_action( 'admin_post_dsupp_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_dsupp_assign_save', array( $this, 'handle_assign_save' ) );

		// ویرایش محصول (مورد ۲ و ۴) — نمایش فقط برای مدیر، داخل کالبک
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post_product', array( $this, 'save_product' ), 10, 1 );

		// لیست محصولات: ستون (مورد ۵) + فیلتر (مورد ۶) + اقدام دسته‌جمعی (مورد ۹)
		add_filter( 'manage_product_posts_columns', array( $this, 'columns' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'column_echo' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'filter_select' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_none_filter' ) ); // فیلتر «بدون تامین‌کننده»
		add_filter( 'bulk_actions-edit-product', array( $this, 'bulk_action_add' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( $this, 'bulk_action_handle' ), 10, 3 );

		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/* ------------------------------------------------------------------
	 * تاکسونومی تامین‌کننده (فقط مدیریتی — بدون صفحه پیش‌فرض، ما صفحه خودمان را داریم)
	 * ---------------------------------------------------------------- */

	public function register_taxonomy() {
		register_taxonomy( DSUPP_TAX, 'product', array(
			'label'              => 'تامین‌کننده‌ها',
			'labels'             => array(
				'name'          => 'تامین‌کننده‌ها',
				'singular_name' => 'تامین‌کننده',
			),
			'public'             => true,
			'rewrite'            => false,
			'query_var'          => true, // فیلتر لیست محصولات با ?dsupp_supplier=slug به‌صورت بومی کار می‌کند (مورد ۶)
			'hierarchical'       => false,
			'show_ui'            => false, // هیچ صفحه بومی — فقط صفحه «تامین‌کننده‌ها» خودمان
			'show_in_nav_menus'  => false,
			'show_in_quick_edit' => false,
			'show_admin_column'  => false,
			'meta_box_cb'        => false, // متاباکس بومی مخفی — متاباکس خودمان (با قیمت تامین) جایگزین است
			'capabilities'       => array(
				'manage_terms' => self::CAP,
				'edit_terms'   => self::CAP,
				'delete_terms' => self::CAP,
				'assign_terms' => self::CAP,
			),
		) );
	}

	/* ------------------------------------------------------------------
	 * ابزارهای کمکی
	 * ---------------------------------------------------------------- */

	/** لیست تامین‌کننده‌ها (حتی بدون محصول) */
	public static function suppliers() {
		$terms = get_terms( array( 'taxonomy' => DSUPP_TAX, 'hide_empty' => false ) );
		return is_array( $terms ) ? $terms : array();
	}

	/** نام ترم بر اساس شناسه (فالبک امن «») */
	public static function term_name( $term_id ) {
		$t = function_exists( 'get_term' ) ? get_term( (int) $term_id, DSUPP_TAX ) : null;
		return ( $t && ! is_wp_error( $t ) && isset( $t->name ) ) ? (string) $t->name : '';
	}

	/** تبدیل عدد فارسی/جداکننده‌دار به عدد ساده قابل ذخیره */
	public static function norm_num( $raw ) {
		$s = trim( (string) $raw );
		$s = strtr( $s, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9' ) );
		$s = preg_replace( '/[^\d.]/', '', $s );
		return null === $s ? '' : $s;
	}

	/** نرمال‌سازی شماره تماس: ارقام فارسی ← لاتین + نگه‌داشتن + و - و فاصله */
	public static function norm_tel( $raw ) {
		$s = trim( (string) $raw );
		$s = strtr( $s, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9' ) );
		$s = preg_replace( '/[^\d+\-\s]/', '', $s );
		$s = trim( (string) preg_replace( '/\s+/', ' ', (string) $s ) );
		return self::cut( $s, 40 );
	}

	/** کوتاه‌کردن متن‌های طولانی برای نمایش در جدول */
	public static function cut( $s, $n ) {
		$s = (string) $s;
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $s, 'UTF-8' ) > $n ) {
			return mb_substr( $s, 0, $n, 'UTF-8' ) . '…';
		}
		return $s;
	}

	/** شماره تماس تامین‌کننده (متای ترم — فالبک امن «») */
	public static function supplier_phone( $term_id ) {
		return function_exists( 'get_term_meta' ) ? (string) get_term_meta( (int) $term_id, DSUPP_META_PHONE, true ) : '';
	}

	/** آدرس تامین‌کننده (متای ترم — فالبک امن «») */
	public static function supplier_addr( $term_id ) {
		return function_exists( 'get_term_meta' ) ? (string) get_term_meta( (int) $term_id, DSUPP_META_ADDR, true ) : '';
	}

	/** خواندن شناسه تامین‌کننده محصول (اول دنورمال، سپس خود تاکسونومی) */
	public static function product_supplier_id( $product_id ) {
		$tid = (int) get_post_meta( $product_id, DSUPP_META_TID, true );
		if ( $tid ) {
			return $tid;
		}
		$terms = get_the_terms( $product_id, DSUPP_TAX );
		if ( is_array( $terms ) && $terms ) {
			$t = reset( $terms );
			return isset( $t->term_id ) ? (int) $t->term_id : 0;
		}
		return 0;
	}

	/* ------------------------------------------------------------------
	 * صفحه مدیریت تامین‌کننده‌ها (زیرمنوی «محصولات») — مورد ۱
	 * ---------------------------------------------------------------- */

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=product',
			'تامین‌کننده‌ها',
			'تامین‌کننده‌ها',
			self::CAP,
			'dsupp-suppliers',
			array( $this, 'page_suppliers' )
		);
		// صفحه مخفی «تعیین دسته‌جمعی تامین‌کننده» — فقط از اقدام دسته‌جمعی محصولات باز می‌شود (مورد ۹)
		add_submenu_page(
			null,
			'تعیین دسته‌جمعی تامین‌کننده',
			'تعیین دسته‌جمعی تامین‌کننده',
			self::CAP,
			'dsupp-assign',
			array( $this, 'page_assign' )
		);
	}

	public function page_suppliers() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$terms = self::suppliers();

		// حالت ویرایش: با لینک «ویرایش» هر ردیف، فرم به حالت به‌روزرسانی همان تامین‌کننده درمی‌آید
		$edit_id   = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$edit_term = null;
		if ( $edit_id ) {
			foreach ( $terms as $t ) {
				if ( (int) $t->term_id === $edit_id ) {
					$edit_term = $t;
					break;
				}
			}
		}

		echo '<div class="wrap" dir="rtl">';
		echo '<style>
			.dsupp-grid{display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;max-width:1100px}
			.dsupp-box{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px 18px}
			.dsupp-box h2{margin:0 0 10px;font-size:15px}
			.dsupp-add{flex:0 0 300px}
			.dsupp-list{flex:1 1 560px}
			.dsupp-add input[type=text]{width:100%;margin-bottom:10px}
			.dsupp-add textarea{width:100%;margin-bottom:10px;resize:vertical}
			.dsupp-add label{display:block;margin-top:6px}
			.dsupp-hint{color:#646970;font-size:12px;line-height:2;margin:8px 0 0}
			.dsupp-count{color:#646970;font-size:12px}
			.dsupp-empty{color:#787c82;padding:14px 6px}
			.dsupp-actions a{margin-inline-end:10px}
			.dsupp-del{color:#b32d2e}
			.dsupp-ltr{direction:ltr;unicode-bidi:isolate;display:inline-block}
			.dsupp-dash{color:#a7aaad}
			.dsupp-note{background:#eafaf3;border:1px solid #bfe9d7;color:#14664a;border-radius:10px;padding:10px 14px;max-width:1100px;margin:14px 0 0;font-size:12.5px;line-height:2}
		</style>';
		echo '<h1>تامین‌کننده‌ها</h1>';

		// اعلان‌ها
		if ( isset( $_GET['dsupp_msg'] ) ) {
			$m = sanitize_key( wp_unslash( $_GET['dsupp_msg'] ) );
			if ( 'added' === $m ) {
				echo '<div class="notice notice-success is-dismissible"><p>تامین‌کننده جدید اضافه شد.</p></div>';
			} elseif ( 'edited' === $m ) {
				echo '<div class="notice notice-success is-dismissible"><p>تغییرات تامین‌کننده ذخیره شد.</p></div>';
			} elseif ( 'deleted' === $m ) {
				echo '<div class="notice notice-success is-dismissible"><p>تامین‌کننده حذف شد و اتصالش به محصولات هم پاک شد.</p></div>';
			} elseif ( 'dup' === $m ) {
				echo '<div class="notice notice-warning is-dismissible"><p>این تامین‌کننده از قبل وجود دارد.</p></div>';
			} elseif ( 'empty' === $m ) {
				echo '<div class="notice notice-error is-dismissible"><p>نام تامین‌کننده خالی است.</p></div>';
			} elseif ( 'ref_on' === $m ) {
				echo '<div class="notice notice-success is-dismissible"><p>ارجاع سفارش به تامین‌کننده <strong>فعال</strong> شد — تب «سفارش‌ها» در پنل تامین‌کننده دیده می‌شود.</p></div>';
			} elseif ( 'ref_off' === $m ) {
				echo '<div class="notice notice-warning is-dismissible"><p>ارجاع سفارش به تامین‌کننده <strong>غیرفعال</strong> شد — تب «سفارش‌ها» از پنل تامین‌کننده حذف شد و قبول/کد رهگیری انجام نمی‌شود.</p></div>';
			}
		}
		// v2.0.0 — اعتبار پنل تازه‌ساخته‌شده فقط همین یک‌بار نمایش داده می‌شود (برای تحویل به تامین‌کننده)
		$flash_key = 'dsupp_newcred_' . get_current_user_id();
		$flash     = function_exists( 'get_transient' ) ? get_transient( $flash_key ) : false;
		if ( is_array( $flash ) && ! empty( $flash['user'] ) ) {
			echo '<div class="notice notice-success"><p><strong>اطلاعات ورود پنل ساخته شد</strong> — فقط همین‌جا و همین یک‌بار نمایش داده می‌شود؛ یادداشت کنید و به تامین‌کننده بدهید:<br>';
			echo 'نام کاربری: <code class="dsupp-ltr">' . esc_html( $flash['user'] ) . '</code>';
			if ( ! empty( $flash['pass'] ) ) {
				echo ' — گذرواژه: <code class="dsupp-ltr">' . esc_html( $flash['pass'] ) . '</code>';
			}
			echo '</p></div>';
			if ( function_exists( 'delete_transient' ) ) {
				delete_transient( $flash_key );
			}
		}

		// v2.1.3 — کلید فعال/غیرفعال «ارجاع سفارش به تامین‌کننده»
		if ( class_exists( 'DSupp_Panel' ) ) {
			$ref_on = DSupp_Panel::referral_on();
			echo '<div class="dsupp-box" style="max-width:1100px;margin:0 0 14px;padding:12px 16px">' . '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0">';
			wp_nonce_field( 'dsupp_referral_toggle' );
			echo '<input type="hidden" name="action" value="dsupp_referral_toggle">';
			echo '<strong>ارجاع سفارش به تامین‌کننده:</strong> ';
			echo '<span class="dsupp-hint" style="margin:0">وضعیت فعلی: <b style="color:' . ( $ref_on ? '#17a16d' : '#b32d2e' ) . '">' . ( $ref_on ? 'فعال' : 'غیرفعال' ) . '</b></span>';
			echo '<label class="dsupp-hint" style="margin:0"><input type="checkbox" name="dsupp_referral" value="1"' . checked( $ref_on, true, false ) . '> تامین‌کننده بتواند سفارش‌های خودش را ببیند، قبول کند و کد رهگیری ثبت کند</label> ';
			echo '<button type="submit" class="button button-primary">ذخیره تنظیم</button>';
			echo '</form></div>';
		}

		echo '<div class="dsupp-grid">';

		// فرم افزودن / ویرایش (در حالت ویرایش با همان فیلدها پر می‌شود)
		if ( $edit_term ) {
			$eid = (int) $edit_term->term_id;
			echo '<div class="dsupp-box dsupp-add"><h2>ویرایش تامین‌کننده: ' . esc_html( $edit_term->name ) . '</h2>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'dsupp_edit_supplier_' . $eid );
			echo '<input type="hidden" name="action" value="dsupp_edit_supplier">';
			echo '<input type="hidden" name="dsupp_edit_id" value="' . $eid . '">';
			echo '<label for="dsupp_name"><strong>نام تامین‌کننده</strong></label>';
			echo '<input type="text" id="dsupp_name" name="dsupp_name" required value="' . esc_attr( $edit_term->name ) . '">';
			echo '<label for="dsupp_phone"><strong>شماره تماس</strong> (اختیاری)</label>';
			echo '<input type="text" id="dsupp_phone" name="dsupp_phone" class="dsupp-ltr" value="' . esc_attr( self::supplier_phone( $eid ) ) . '" placeholder="مثلاً 09123456789">';
			echo '<label for="dsupp_addr"><strong>آدرس</strong> (اختیاری)</label>';
			echo '<textarea id="dsupp_addr" name="dsupp_addr" rows="3" placeholder="آدرس دفتر یا انبار تامین‌کننده…">' . esc_textarea( self::supplier_addr( $eid ) ) . '</textarea>';
			// v2.0.0 — فیلدهای پنل تامین‌کننده
			echo '<hr style="border:none;border-top:1px dashed #dcdcde;margin:12px 0">';
			// v2.1.0 — پیوند کاربر وردپرس (نقش تامین‌کننده) ← یوزرنیم/پسورد همان کاربر = ورود پنل
			echo '<label for="dsupp_user_id"><strong>کاربر وردپرس (نقش تامین‌کننده)</strong> — اگر انتخاب شود، ورود پنل با نام کاربری/گذرواژه همین کاربر انجام می‌شود</label>';
			echo class_exists( 'DSupp_Panel' ) ? DSupp_Panel::user_select_html( DSupp_Panel::linked_uid( $eid ) ) : '';
			echo '<p class="dsupp-hint">کاربر جدید را از منوی «کاربران تامین‌کننده» (زیر محصولات) بسازید، بعد این‌جا پیوندش کنید.</p>';
			echo '<label for="dsupp_username"><strong>نام کاربری پنل</strong> (ورود تامین‌کننده به پنل خودش)</label>';
			echo '<input type="text" id="dsupp_username" name="dsupp_username" class="dsupp-ltr" value="' . esc_attr( class_exists( 'DSupp_Panel' ) ? DSupp_Panel::username( $eid ) : '' ) . '" placeholder="مثلاً rezaei-supply">';
			echo '<label for="dsupp_password"><strong>گذرواژه جدید پنل</strong> (خالی = بدون تغییر)</label>';
			echo '<input type="text" id="dsupp_password" name="dsupp_password" class="dsupp-ltr" value="" placeholder="فقط اگر می‌خواهید عوض شود بنویسید…">';
			echo '<label for="dsupp_card"><strong>شماره کارت تسویه</strong> (اختیاری)</label>';
			echo '<input type="text" id="dsupp_card" name="dsupp_card" class="dsupp-ltr" maxlength="19" value="' . esc_attr( class_exists( 'DSupp_Panel' ) ? DSupp_Panel::card_display( $eid ) : '' ) . '" placeholder="16 رقم">';
			echo '<label for="dsupp_sheba"><strong>شماره شبا</strong> (اختیاری)</label>';
			echo '<input type="text" id="dsupp_sheba" name="dsupp_sheba" class="dsupp-ltr" value="' . esc_attr( class_exists( 'DSupp_Panel' ) ? DSupp_Panel::sheba_display( $eid ) : '' ) . '" placeholder="IR + 24 رقم">';
			echo '<button type="submit" class="button button-primary">ذخیره تغییرات</button> ';
			echo '<a class="button" href="' . esc_url( $this->suppliers_url() ) . '">انصراف</a>';
			echo '</form></div>';
		} else {
			echo '<div class="dsupp-box dsupp-add"><h2>افزودن تامین‌کننده جدید</h2>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'dsupp_add_supplier' );
			echo '<input type="hidden" name="action" value="dsupp_add_supplier">';
			echo '<label for="dsupp_name"><strong>نام تامین‌کننده</strong> (مثلاً: تامین‌کننده آقای رضایی)</label>';
			echo '<input type="text" id="dsupp_name" name="dsupp_name" required placeholder="نام تامین‌کننده را بنویسید…">';
			echo '<label for="dsupp_phone"><strong>شماره تماس</strong> (اختیاری)</label>';
			echo '<input type="text" id="dsupp_phone" name="dsupp_phone" class="dsupp-ltr" placeholder="مثلاً 09123456789">';
			echo '<label for="dsupp_addr"><strong>آدرس</strong> (اختیاری)</label>';
			echo '<textarea id="dsupp_addr" name="dsupp_addr" rows="3" placeholder="آدرس دفتر یا انبار تامین‌کننده…"></textarea>';
			// v2.0.0 — فیلدهای پنل تامین‌کننده (خالی = خودکار ساخته می‌شود و یک‌بار نشان داده می‌شود)
			echo '<hr style="border:none;border-top:1px dashed #dcdcde;margin:12px 0">';
			// v2.1.0 — پیوند کاربر وردپرس (نقش تامین‌کننده) ← یوزرنیم/پسورد همان کاربر = ورود پنل
			echo '<label for="dsupp_user_id"><strong>کاربر وردپرس (نقش تامین‌کننده)</strong> — اگر انتخاب شود، ورود پنل با نام کاربری/گذرواژه همین کاربر انجام می‌شود</label>';
			echo class_exists( 'DSupp_Panel' ) ? DSupp_Panel::user_select_html( 0 ) : '';
			echo '<p class="dsupp-hint">کاربر جدید را از منوی «کاربران تامین‌کننده» (زیر محصولات) بسازید، بعد این‌جا پیوندش کنید.</p>';
			echo '<label for="dsupp_username"><strong>نام کاربری پنل</strong> (اختیاری — خالی = خودکار)</label>';
			echo '<input type="text" id="dsupp_username" name="dsupp_username" class="dsupp-ltr" placeholder="مثلاً rezaei-supply">';
			echo '<label for="dsupp_password"><strong>گذرواژه پنل</strong> (اختیاری — خالی = خودکار)</label>';
			echo '<input type="text" id="dsupp_password" name="dsupp_password" class="dsupp-ltr" placeholder="اگر ننویسید، سیستم می‌سازد">';
			echo '<label for="dsupp_card"><strong>شماره کارت تسویه</strong> (اختیاری)</label>';
			echo '<input type="text" id="dsupp_card" name="dsupp_card" class="dsupp-ltr" maxlength="19" placeholder="16 رقم">';
			echo '<label for="dsupp_sheba"><strong>شماره شبا</strong> (اختیاری)</label>';
			echo '<input type="text" id="dsupp_sheba" name="dsupp_sheba" class="dsupp-ltr" placeholder="IR + 24 رقم">';
			echo '<button type="submit" class="button button-primary">افزودن</button>';
			echo '<p class="dsupp-hint">بعد از افزودن، در صفحه ویرایش هر محصول می‌توانید آن را به محصول وصل کنید. برای تغییر بعدی نام/تماس/آدرس، دکمه «ویرایش» ردیف آن تامین‌کننده را بزنید.</p>';
			echo '</form></div>';
		}

		// لیست
		echo '<div class="dsupp-box dsupp-list"><h2>لیست تامین‌کننده‌ها <span class="dsupp-count">(' . count( $terms ) . ' مورد)</span></h2>';
		if ( ! $terms ) {
			echo '<p class="dsupp-empty">هنوز تامین‌کننده‌ای اضافه نشده است — از کادر سمت چپ اولین مورد را بسازید.</p>';
		} else {
			echo '<table class="widefat striped" style="margin-top:4px"><thead><tr>';
			echo '<th style="width:22%">نام تامین‌کننده</th><th style="width:15%">شماره تماس</th><th style="width:25%">آدرس</th><th>تعداد محصول</th><th style="width:27%">اقدامات</th>';
			echo '</tr></thead><tbody>';
			foreach ( $terms as $t ) {
				$tid   = (int) $t->term_id;
				$export = wp_nonce_url(
					admin_url( 'admin-post.php?action=dsupp_export&supplier=' . $tid ),
					'dsupp_export_' . $tid
				);
				$del = wp_nonce_url(
					admin_url( 'admin-post.php?action=dsupp_del_supplier&supplier=' . $tid ),
					'dsupp_del_supplier_' . $tid
				);
				$assign_url = admin_url( 'edit.php?post_type=product&dsupp_supplier=' . rawurlencode( (string) $t->slug ) );
				$edit_url   = add_query_arg( array( 'post_type' => 'product', 'page' => 'dsupp-suppliers', 'edit' => $tid ), admin_url( 'edit.php' ) );
				$phone      = self::supplier_phone( $tid );
				$addr       = self::supplier_addr( $tid );
				echo '<tr>';
				echo '<td><strong>' . esc_html( $t->name ) . '</strong></td>';
				echo '<td>' . ( '' !== $phone ? '<span class="dsupp-ltr">' . esc_html( $phone ) . '</span>' : '<span class="dsupp-dash">—</span>' ) . '</td>';
				echo '<td>' . ( '' !== $addr ? esc_html( self::cut( $addr, 60 ) ) : '<span class="dsupp-dash">—</span>' ) . '</td>';
				echo '<td><a href="' . esc_url( $assign_url ) . '">' . (int) $t->count . ' محصول</a></td>';
				echo '<td class="dsupp-actions">';
				echo '<a class="button button-small" href="' . esc_url( $edit_url ) . '">ویرایش</a>';
				echo '<a class="button button-small" href="' . esc_url( $export ) . '">خروجی اکسل (استعلام قیمت)</a>';
				echo '<a class="dsupp-del" href="' . esc_url( $del ) . '" onclick="return confirm(\'تامین‌کننده حذف شود؟ اتصالش به همه محصولات هم پاک می‌شود.\');">حذف</a>';
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		echo '</div>'; // grid

		echo '<div class="dsupp-note">';
		echo '<strong>راهنمای خروجی استعلام قیمت (ماهانه):</strong> دکمه «خروجی اکسل» کنار هر تامین‌کننده، فایل اکسلی می‌دهد با ستون‌های «نام محصول | قیمت قبل (ثبت‌شده فعلی) | قیمت جدید (خالی)» — قیمت‌های جدید استعلام‌شده را در ستون خالی بنویسید. فایل برای چاپ روی کاغذ A4 تنظیم شده است.<br>';
		echo '<strong>تعیین دسته‌جمعی:</strong> در «محصولات ← همه محصولات» چند محصول را تیک بزنید ← از اقدامات دسته‌جمعی «تعیین تامین‌کننده» ← اجرا.<br>';
		echo '<strong>فیلترها:</strong> در همان لیست محصولات، از دراپ‌داون «همه تامین‌کننده‌ها» می‌توانید محصولات هر تامین‌کننده یا «فقط محصولات بدون تامین‌کننده» را ببینید.';
		echo '</div>';

		echo '</div>'; // wrap
	}

	/* ------------------------------------------------------------------
	 * ویرایش محصول: بخش «تامین‌کننده» (موارد ۲ و ۴) — فقط مدیر (مورد ۳)
	 * ---------------------------------------------------------------- */

	public function register_metabox( $post_type ) {
		if ( 'product' !== $post_type || ! current_user_can( self::CAP ) ) {
			return; // مورد ۳: برای فروشندگان/کاربران غیرمدیر هیچ کادری ثبت نمی‌شود
		}
		add_meta_box(
			'dsupp_supplier_box',
			'تامین‌کننده',
			array( $this, 'metabox_html' ),
			'product',
			'side',
			'default'
		);
	}

	public function metabox_html( $post ) {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$terms    = self::suppliers();
		$cur      = self::product_supplier_id( (int) $post->ID );
		$cur_cost = (string) get_post_meta( (int) $post->ID, DSUPP_META_COST, true );

		wp_nonce_field( 'dsupp_product_meta', 'dsupp_product_nonce' );

		echo '<p style="margin:0 0 8px"><label for="dsupp_supplier"><strong>این محصول از چه تامین‌کننده‌ای تهیه می‌شود؟</strong></label></p>';
		echo '<select id="dsupp_supplier" name="dsupp_supplier" style="width:100%;margin-bottom:10px">';
		echo '<option value="">— بدون تامین‌کننده —</option>';
		foreach ( $terms as $t ) {
			echo '<option value="' . (int) $t->term_id . '"' . selected( $cur, (int) $t->term_id, false ) . '>' . esc_html( $t->name ) . '</option>';
		}
		echo '</select>';

		echo '<p style="margin:0 0 6px"><label for="dsupp_cost"><strong>قیمت تامین‌کننده (تومان)</strong></label></p>';
		echo '<input type="text" inputmode="decimal" id="dsupp_cost" name="dsupp_cost" value="' . esc_attr( $cur_cost ) . '" style="width:100%;direction:ltr;text-align:left" placeholder="مثلاً 450000">';
		echo '<p class="description" style="margin:8px 0 0">قیمت خرید شما از تامین‌کننده برای این محصول — در ستون لیست محصولات و خروجی استعلام دیده می‌شود.</p>';
		echo '<p style="margin:8px 0 0"><a href="' . esc_url( admin_url( 'edit.php?post_type=product&page=dsupp-suppliers' ) ) . '" target="_blank">مدیریت تامین‌کننده‌ها</a></p>';
	}

	public function save_product( $post_id ) {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$nonce = isset( $_POST['dsupp_product_nonce'] ) ? sanitize_key( wp_unslash( $_POST['dsupp_product_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'dsupp_product_meta' ) ) {
			return;
		}

		// تامین‌کننده (یک انتخاب — جایگزینی کامل)
		$tid = isset( $_POST['dsupp_supplier'] ) ? absint( wp_unslash( $_POST['dsupp_supplier'] ) ) : 0;
		if ( $tid ) {
			wp_set_object_terms( $post_id, array( $tid ), DSUPP_TAX, false );
			$t = function_exists( 'get_term' ) ? get_term( $tid, DSUPP_TAX ) : null;
			update_post_meta( $post_id, DSUPP_META_TID, $tid );
			update_post_meta( $post_id, DSUPP_META_TNAME, ( $t && ! is_wp_error( $t ) && isset( $t->name ) ) ? (string) $t->name : '' );
			update_post_meta( $post_id, DSUPP_META_TSLUG, ( $t && ! is_wp_error( $t ) && isset( $t->slug ) ) ? (string) $t->slug : '' );
		} else {
			wp_set_object_terms( $post_id, array(), DSUPP_TAX, false );
			delete_post_meta( $post_id, DSUPP_META_TID );
			delete_post_meta( $post_id, DSUPP_META_TNAME );
			delete_post_meta( $post_id, DSUPP_META_TSLUG );
		}

		// قیمت تامین‌کننده (مورد ۴) — همان فیلد مشترک «قیمت تامین‌کننده (دستیار)»
		$cost = self::norm_num( isset( $_POST['dsupp_cost'] ) ? wp_unslash( $_POST['dsupp_cost'] ) : '' );
		if ( '' !== $cost ) {
			update_post_meta( $post_id, DSUPP_META_COST, $cost );
		} else {
			delete_post_meta( $post_id, DSUPP_META_COST );
		}
	}

	/* ------------------------------------------------------------------
	 * لیست محصولات: ستون (مورد ۵) + فیلتر (مورد ۶)
	 * ---------------------------------------------------------------- */

	public function columns( $cols ) {
		if ( ! current_user_can( self::CAP ) ) {
			return $cols; // مورد ۳
		}
		// قبل از ستون «تاریخ» درج شود
		$new   = array();
		$added = false;
		foreach ( $cols as $k => $v ) {
			if ( 'date' === $k && ! $added ) {
				$new['dsupp_supplier'] = 'تامین‌کننده';
				$added = true;
			}
			$new[ $k ] = $v;
		}
		if ( ! $added ) {
			$new['dsupp_supplier'] = 'تامین‌کننده';
		}
		return $new;
	}

	public function column_echo( $column, $post_id ) {
		if ( 'dsupp_supplier' !== $column || ! current_user_can( self::CAP ) ) {
			return;
		}
		$tid   = (int) get_post_meta( $post_id, DSUPP_META_TID, true );
		$name  = (string) get_post_meta( $post_id, DSUPP_META_TNAME, true );
		$slug  = (string) get_post_meta( $post_id, DSUPP_META_TSLUG, true );
		$cost  = (string) get_post_meta( $post_id, DSUPP_META_COST, true );

		if ( ! $tid || '' === $name ) {
			echo '<span style="color:#a7aaad">—</span>';
			return;
		}
		$url = admin_url( 'edit.php?post_type=product&dsupp_supplier=' . rawurlencode( $slug ) );
		echo '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $name ) . '</strong></a>';
		if ( '' !== $cost ) {
			echo '<br><span style="color:#646970;font-size:11.5px">قیمت تامین: ' . wp_kses_post( wc_price( (float) $cost ) ) . '</span>';
		}
	}

	public function filter_select( $post_type ) {
		if ( 'product' !== $post_type || ! current_user_can( self::CAP ) ) {
			return; // مورد ۳
		}
		$terms = self::suppliers();
		if ( ! $terms ) {
			return;
		}
		$cur = isset( $_GET[ DSUPP_TAX ] ) ? sanitize_text_field( wp_unslash( $_GET[ DSUPP_TAX ] ) ) : '';
		echo '<select name="' . esc_attr( DSUPP_TAX ) . '" id="dsupp-filter">';
		echo '<option value="">همه تامین‌کننده‌ها</option>';
		echo '<option value="' . esc_attr( DSUPP_NONE ) . '"' . selected( $cur, DSUPP_NONE, false ) . '>— فقط محصولات بدون تامین‌کننده —</option>';
		foreach ( $terms as $t ) {
			echo '<option value="' . esc_attr( $t->slug ) . '"' . selected( $cur, $t->slug, false ) . '>' . esc_html( $t->name ) . '</option>';
		}
		echo '</select>';
		// وردپرس با query_var تاکسونومی، فیلتر را خودش اعمال می‌کند — بدون کوئری دستی
	}

	/**
	 * فیلتر «فقط محصولات بدون تامین‌کننده»: مقدار ویژه __none__ به‌جای نامک ترم می‌آید.
	 * وردپرس بعد از pre_get_posts کوئری تاکسونومی را از روی query varها دوباره پارس می‌کند؛
	 * پس مقدار ویژه را خالی می‌کنیم تا کوئری تاکسونومیِ بی‌نتیجه ساخته نشود و به‌جایش
	 * محصولاتی که متای تامین‌کننده ندارند را با meta_query می‌گیریم (هماهنگ با ستون «—»).
	 */
	public function apply_none_filter( $q ) {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}
		if ( ! is_object( $q ) || ! method_exists( $q, 'get' ) || ! method_exists( $q, 'set' ) ) {
			return;
		}
		if ( method_exists( $q, 'is_main_query' ) && ! $q->is_main_query() ) {
			return;
		}
		if ( 'product' !== (string) $q->get( 'post_type' ) ) {
			return;
		}
		if ( DSUPP_NONE !== (string) $q->get( DSUPP_TAX ) ) {
			return;
		}
		$q->set( DSUPP_TAX, '' ); // مقدار ویژه قبل از پارس مجدد تاکسونومی خنثی می‌شود
		$meta = $q->get( 'meta_query' );
		$meta = is_array( $meta ) ? $meta : array();
		$meta[] = array(
			'key'     => DSUPP_META_TID,
			'compare' => 'NOT EXISTS',
		);
		$q->set( 'meta_query', $meta );
	}

	/* ------------------------------------------------------------------
	 * اقدام دسته‌جمعی «تعیین تامین‌کننده» (مورد ۹)
	 * ---------------------------------------------------------------- */

	public function bulk_action_add( $actions ) {
		if ( current_user_can( self::CAP ) ) {
			$actions['dsupp_assign'] = 'تعیین تامین‌کننده';
		}
		return $actions;
	}

	public function bulk_action_handle( $redirect_to, $action, $post_ids ) {
		if ( 'dsupp_assign' !== $action || ! current_user_can( self::CAP ) ) {
			return $redirect_to;
		}
		$ids = array_slice( array_map( 'intval', (array) $post_ids ), 0, 200 );
		if ( ! $ids ) {
			return $redirect_to;
		}
		// آدرس ریدایرکت را خام می‌سازیم (بدون انکود HTMLای wp_nonce_url در هدر Location) — تجربه باگ لیبل
		return add_query_arg(
			array(
				'page'     => 'dsupp-assign',
				'ids'      => implode( ',', $ids ),
				'_wpnonce' => wp_create_nonce( 'dsupp_assign_page' ),
			),
			admin_url( 'admin.php' )
		);
	}

	public function page_assign() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'dsupp_assign_page' ) ) {
			wp_die( 'نشست نامعتبر است — دوباره از لیست محصولات شروع کنید.' );
		}
		$ids = array_filter( array_slice( array_map( 'intval', explode( ',', (string) ( $_GET['ids'] ?? '' ) ) ), 0, 200 ) );
		if ( ! $ids ) {
			wp_die( 'محصولی انتخاب نشده است.' );
		}
		$terms = self::suppliers();
		if ( ! $terms ) {
			wp_die( 'هنوز تامین‌کننده‌ای نساخته‌اید — ابتدا از صفحه «تامین‌کننده‌ها» یک مورد اضافه کنید.' );
		}

		echo '<div class="wrap" dir="rtl"><h1>تعیین تامین‌کننده برای ' . count( $ids ) . ' محصول</h1>';
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;max-width:560px">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dsupp_assign_save' );
		echo '<input type="hidden" name="action" value="dsupp_assign_save">';
		echo '<input type="hidden" name="ids" value="' . esc_attr( implode( ',', $ids ) ) . '">';
		echo '<p><label for="dsupp_supplier"><strong>تامین‌کننده:</strong></label></p>';
		echo '<select id="dsupp_supplier" name="dsupp_supplier" style="width:100%;margin-bottom:14px">';
		foreach ( $terms as $t ) {
			echo '<option value="' . (int) $t->term_id . '">' . esc_html( $t->name ) . '</option>';
		}
		echo '<option value="0">— حذف تامین‌کننده از این محصولات —</option>';
		echo '</select>';
		echo '<button type="submit" class="button button-primary">اعمال تامین‌کننده</button> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=product' ) ) . '">انصراف</a>';
		echo '<p class="description" style="margin-top:12px">تامین‌کننده قبلی هر محصول با انتخاب جدید جایگزین می‌شود (هر محصول یک تامین‌کننده دارد).</p>';
		echo '</form></div></div>';
	}

	public function handle_assign_save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'dsupp_assign_save' ) ) {
			wp_die( 'نشست نامعتبر است.' );
		}
		$ids = array_filter( array_slice( array_map( 'intval', explode( ',', (string) ( $_POST['ids'] ?? '' ) ) ), 0, 200 ) );
		$tid = isset( $_POST['dsupp_supplier'] ) ? absint( wp_unslash( $_POST['dsupp_supplier'] ) ) : 0;
		if ( ! $ids ) {
			wp_die( 'محصولی انتخاب نشده است.' );
		}
		foreach ( $ids as $pid ) {
			if ( $tid ) {
				wp_set_object_terms( $pid, array( $tid ), DSUPP_TAX, false );
				$t = function_exists( 'get_term' ) ? get_term( $tid, DSUPP_TAX ) : null;
				update_post_meta( $pid, DSUPP_META_TID, $tid );
				update_post_meta( $pid, DSUPP_META_TNAME, ( $t && ! is_wp_error( $t ) && isset( $t->name ) ) ? (string) $t->name : '' );
				update_post_meta( $pid, DSUPP_META_TSLUG, ( $t && ! is_wp_error( $t ) && isset( $t->slug ) ) ? (string) $t->slug : '' );
			} else {
				wp_set_object_terms( $pid, array(), DSUPP_TAX, false );
				delete_post_meta( $pid, DSUPP_META_TID );
				delete_post_meta( $pid, DSUPP_META_TNAME );
				delete_post_meta( $pid, DSUPP_META_TSLUG );
			}
		}
		wp_safe_redirect( add_query_arg( array( 'post_type' => 'product', 'dsupp_assigned' => count( $ids ) ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public function notices() {
		if ( isset( $_GET['dsupp_assigned'] ) && current_user_can( self::CAP ) ) {
			$n = (int) $_GET['dsupp_assigned'];
			if ( $n > 0 ) {
				echo '<div class="notice notice-success is-dismissible"><p>تامین‌کننده برای ' . $n . ' محصول تعیین شد.</p></div>';
			}
		}
	}

	/* ------------------------------------------------------------------
	 * افزودن / حذف تامین‌کننده
	 * ---------------------------------------------------------------- */

	public function handle_add_supplier() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'dsupp_add_supplier' ) ) {
			wp_die( 'نشست نامعتبر است.' );
		}
		$name = isset( $_POST['dsupp_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dsupp_name'] ) ) : '';
		if ( '' === $name ) {
			wp_safe_redirect( $this->suppliers_url( 'empty' ) );
			exit;
		}
		if ( function_exists( 'term_exists' ) && term_exists( $name, DSUPP_TAX ) ) {
			wp_safe_redirect( $this->suppliers_url( 'dup' ) );
			exit;
		}
		$phone = self::norm_tel( sanitize_text_field( wp_unslash( $_POST['dsupp_phone'] ?? '' ) ) );
		$addr  = self::cut( sanitize_textarea_field( wp_unslash( $_POST['dsupp_addr'] ?? '' ) ), 300 );
		$r     = wp_insert_term( $name, DSUPP_TAX );
		$tid   = ( is_array( $r ) && isset( $r['term_id'] ) ) ? (int) $r['term_id'] : 0;
		if ( $tid && function_exists( 'update_term_meta' ) ) {
			if ( '' !== $phone ) {
				update_term_meta( $tid, DSUPP_META_PHONE, $phone );
			}
			if ( '' !== $addr ) {
				update_term_meta( $tid, DSUPP_META_ADDR, $addr );
			}
		}
		// v2.0.0 — ساخت اعتبار پنل (خالی = خودکار + نمایش یک‌باره به مدیر)
		if ( $tid && class_exists( 'DSupp_Panel' ) ) {
			DSupp_Panel::save_admin_fields( $tid, 'add' );
		}
		wp_safe_redirect( $this->suppliers_url( 'added' ) );
		exit;
	}

	/** ویرایش تامین‌کننده موجود: نام + شماره تماس + آدرس (+ همگام‌سازی نام دنورمال روی محصولات) */
	public function handle_edit_supplier() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$tid   = isset( $_POST['dsupp_edit_id'] ) ? absint( wp_unslash( $_POST['dsupp_edit_id'] ) ) : 0;
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! $tid || '' === $nonce || ! wp_verify_nonce( $nonce, 'dsupp_edit_supplier_' . $tid ) ) {
			wp_die( 'نشست نامعتبر است.' );
		}
		$name = isset( $_POST['dsupp_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dsupp_name'] ) ) : '';
		if ( '' === $name ) {
			wp_safe_redirect( $this->suppliers_url( 'empty' ) );
			exit;
		}
		// نام تکراری؟ (به‌جز خود همین تامین‌کننده)
		if ( function_exists( 'term_exists' ) ) {
			$ex = term_exists( $name, DSUPP_TAX );
			$ex = is_array( $ex ) ? (int) ( $ex['term_id'] ?? 0 ) : (int) $ex;
			if ( $ex && $ex !== $tid ) {
				wp_safe_redirect( $this->suppliers_url( 'dup' ) );
				exit;
			}
		}
		if ( function_exists( 'wp_update_term' ) ) {
			wp_update_term( $tid, DSUPP_TAX, array( 'name' => $name ) );
		}
		if ( function_exists( 'update_term_meta' ) ) {
			$phone = self::norm_tel( sanitize_text_field( wp_unslash( $_POST['dsupp_phone'] ?? '' ) ) );
			$addr  = self::cut( sanitize_textarea_field( wp_unslash( $_POST['dsupp_addr'] ?? '' ) ), 300 );
			if ( '' !== $phone ) {
				update_term_meta( $tid, DSUPP_META_PHONE, $phone );
			} elseif ( function_exists( 'delete_term_meta' ) ) {
				delete_term_meta( $tid, DSUPP_META_PHONE );
			}
			if ( '' !== $addr ) {
				update_term_meta( $tid, DSUPP_META_ADDR, $addr );
			} elseif ( function_exists( 'delete_term_meta' ) ) {
				delete_term_meta( $tid, DSUPP_META_ADDR );
			}
		}
		// همگام‌سازی نام دنورمال روی محصولات متصل (ستون لیست محصولات از این متا می‌خواند)
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'meta_key'       => DSUPP_META_TID,
			'meta_value'     => $tid,
		) );
		foreach ( (array) $ids as $pid ) {
			update_post_meta( (int) $pid, DSUPP_META_TNAME, $name );
		}
		// v2.0.0 — فیلدهای پنل (نام کاربری/گذرواژه/کارت/شبا)
		if ( class_exists( 'DSupp_Panel' ) ) {
			DSupp_Panel::save_admin_fields( $tid, 'edit' );
		}
		wp_safe_redirect( $this->suppliers_url( 'edited' ) );
		exit;
	}

	public function handle_del_supplier() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$tid = isset( $_GET['supplier'] ) ? absint( $_GET['supplier'] ) : 0;
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $tid || '' === $nonce || ! wp_verify_nonce( $nonce, 'dsupp_del_supplier_' . $tid ) ) {
			wp_die( 'نشست نامعتبر است.' );
		}
		if ( function_exists( 'wp_delete_term' ) ) {
			wp_delete_term( $tid, DSUPP_TAX ); // اتصال به محصولات هم پاک می‌شود
		}
		self::clear_supplier_meta( $tid ); // پاک‌سازی متاهای دنورمال
		wp_safe_redirect( $this->suppliers_url( 'deleted' ) );
		exit;
	}

	/** حذف متاهای دنورمال یک تامین‌کننده، از روی همه محصولاتش */
	public static function clear_supplier_meta( $term_id ) {
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'meta_key'       => DSUPP_META_TID,
			'meta_value'     => (int) $term_id,
		) );
		foreach ( (array) $ids as $pid ) {
			delete_post_meta( $pid, DSUPP_META_TID );
			delete_post_meta( $pid, DSUPP_META_TNAME );
			delete_post_meta( $pid, DSUPP_META_TSLUG );
		}
	}

	private function suppliers_url( $msg = '' ) {
		$args = array( 'post_type' => 'product', 'page' => 'dsupp-suppliers' );
		if ( $msg ) {
			$args['dsupp_msg'] = $msg;
		}
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	/* ------------------------------------------------------------------
	 * خروجی اکسل استعلام قیمت (موارد ۷ و ۸)
	 * ---------------------------------------------------------------- */

	public function handle_export() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$tid = isset( $_GET['supplier'] ) ? absint( $_GET['supplier'] ) : 0;
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $tid || '' === $nonce || ! wp_verify_nonce( $nonce, 'dsupp_export_' . $tid ) ) {
			wp_die( 'نشست نامعتبر است.' );
		}
		$name = self::term_name( $tid );
		$rows = self::export_rows( $tid );
		if ( ! $rows ) {
			wp_die( 'محصولی به این تامین‌کننده متصل نیست — چیزی برای خروجی نیست.' );
		}
		$headers = array( 'نام محصول', 'قیمت قبل (ثبت‌شده فعلی)', 'قیمت جدید' ); // مورد ۷ — سومین ستون عمداً خالی می‌ماند
		$fname   = 'suppliers-' . ( sanitize_title( $name ) ? sanitize_title( $name ) : 'supplier' ) . '-' . gmdate( 'Y-m-d' ) . '.xlsx';
		DSupp_Xlsx::download( $fname, $headers, $rows, 'محصولات ' . ( $name ? $name : 'تامین‌کننده' ) );
	}

	/** سطرهای خروجی: [نام محصول, قیمت تامین فعلی(←فالبک قیمت محصول), ''] */
	public static function export_rows( $term_id ) {
		$q = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => 2000,
			'no_found_rows'  => true,
			'tax_query'      => array(
				array(
					'taxonomy' => DSUPP_TAX,
					'field'    => 'term_id',
					'terms'    => array( (int) $term_id ),
				),
			),
		) );
		$rows = array();
		foreach ( (array) $q->posts as $p ) {
			$pid = isset( $p->ID ) ? (int) $p->ID : 0;
			if ( ! $pid ) {
				continue;
			}
			$title = isset( $p->post_title ) ? (string) $p->post_title : ( function_exists( 'get_the_title' ) ? (string) get_the_title( $pid ) : (string) $pid );
			$price = self::norm_num( get_post_meta( $pid, DSUPP_META_COST, true ) );
			if ( '' === $price ) {
				$price = self::norm_num( get_post_meta( $pid, '_regular_price', true ) );
			}
			if ( '' === $price ) {
				$price = self::norm_num( get_post_meta( $pid, '_price', true ) );
			}
			$rows[] = array( $title, $price, '' ); // ستون سوم خالی — محل نوشتن قیمت‌های استعلام جدید
		}
		return $rows;
	}
}

/**
 * سازنده مینیمال فایل XLSX (بدون وابستگی خارجی — Zip/XML خام)
 * + تنظیمات چاپ A4 (کاغذ A4، عمودی، Fit-to-Width) و نمایش راست‌به‌چپ
 */
final class DSupp_Xlsx {

	private static function esc( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	private static function col_letters() {
		return array( 'A', 'B', 'C' );
	}

	/** @return array<string,string> مسیر داخل زیپ ← محتوای XML */
	public static function build_parts( array $headers, array $rows, $sheet_name = 'Sheet1' ) {
		$letters = self::col_letters();

		// سطر هدر + سطرهای داده
		$sheet_rows = '';
		$r          = 1;
		$cells      = '';
		foreach ( $headers as $i => $h ) {
			$cells .= '<c r="' . $letters[ $i ] . $r . '" t="inlineStr" s="1"><is><t>' . self::esc( $h ) . '</t></is></c>';
		}
		$sheet_rows .= '<row r="' . $r . '" ht="24" customHeight="1">' . $cells . '</row>';
		foreach ( $rows as $row ) {
			$r++;
			$cells = '';
			// ستون ۱: نام (متن)
			$cells .= '<c r="A' . $r . '" t="inlineStr" s="2"><is><t>' . self::esc( (string) ( $row[0] ?? '' ) ) . '</t></is></c>';
			// ستون ۲: قیمت فعلی (عددی)
			$num = (string) ( $row[1] ?? '' );
			if ( '' !== $num && is_numeric( $num ) ) {
				$cells .= '<c r="B' . $r . '" s="2"><v>' . self::esc( $num ) . '</v></c>';
			} else {
				$cells .= '<c r="B' . $r . '" s="2"/>';
			}
			// ستون ۳: قیمت جدید — همیشه خالی (محل استعلام جدید)
			$cells .= '<c r="C' . $r . '" s="2"/>';
			$sheet_rows .= '<row r="' . $r . '">' . $cells . '</row>';
		}

		$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
			'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>' .
			'<dimension ref="A1:C' . max( 1, $r ) . '"/>' .
			'<sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews>' .
			'<sheetFormatPr defaultRowHeight="16"/>' .
			'<cols><col min="1" max="1" width="44" customWidth="1"/><col min="2" max="3" width="22" customWidth="1"/></cols>' .
			'<sheetData>' . $sheet_rows . '</sheetData>' .
			'<printOptions horizontalCentered="1"/>' .
			'<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>' .
			'<pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="0"/>' .
			'</worksheet>';

		$types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
			'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
			'<Default Extension="xml" ContentType="application/xml"/>' .
			'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
			'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
			'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
			'</Types>';

		$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
			'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
			'</Relationships>';

		$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
			'<sheets><sheet name="' . self::esc( mb_substr( (string) $sheet_name, 0, 31 ) ) . '" sheetId="1" r:id="rId1"/></sheets>' .
			'</workbook>';

		$wb_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
			'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
			'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
			'</Relationships>';

		$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
			'<fonts count="2">' .
			'<font><sz val="11"/><name val="Tahoma"/></font>' .
			'<font><b/><sz val="11"/><name val="Tahoma"/></font>' .
			'</fonts>' .
			'<fills count="3">' .
			'<fill><patternFill patternType="none"/></fill>' .
			'<fill><patternFill patternType="gray125"/></fill>' .
			'<fill><patternFill patternType="solid"><fgColor rgb="FFE8F0E9"/><bgColor indexed="64"/></patternFill></fill>' .
			'</fills>' .
			'<borders count="2">' .
			'<border><left/><right/><top/><bottom/><diagonal/></border>' .
			'<border>' .
			'<left style="thin"><color rgb="FF444444"/></left>' .
			'<right style="thin"><color rgb="FF444444"/></right>' .
			'<top style="thin"><color rgb="FF444444"/></top>' .
			'<bottom style="thin"><color rgb="FF444444"/></bottom>' .
			'<diagonal/></border>' .
			'</borders>' .
			'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
			'<cellXfs count="3">' .
			'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
			'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' .
			'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>' .
			'</cellXfs>' .
			'</styleSheet>';

		return array(
			'[Content_Types].xml'      => $types,
			'_rels/.rels'              => $rels,
			'xl/workbook.xml'          => $workbook,
			'xl/_rels/workbook.xml.rels' => $wb_rels,
			'xl/styles.xml'            => $styles,
			'xl/worksheets/sheet1.xml' => $sheet,
		);
	}

	/** ساخت فایل و ارسال برای دانلود */
	public static function download( $filename, array $headers, array $rows, $sheet_name = 'Sheet1' ) {
		$parts = self::build_parts( $headers, $rows, $sheet_name );

		if ( ! class_exists( 'PclZip' ) ) {
			$pclzip = ABSPATH . 'wp-admin/includes/class-pclzip.php';
			if ( file_exists( $pclzip ) ) {
				require_once $pclzip;
			}
		}
		if ( ! class_exists( 'PclZip' ) ) {
			wp_die( 'خطا در ساخت فایل اکسل: کتابخانه فشرده‌سازی وردپرس (PclZip) در دسترس نیست.' );
		}

		$tmp = function_exists( 'wp_tempnam' ) ? wp_tempnam( $filename ) : tempnam( sys_get_temp_dir(), 'dsupp' );
		if ( ! $tmp ) {
			wp_die( 'خطا در ساخت فایل موقت.' );
		}
		$zip   = new PclZip( $tmp );
		$files = array();
		foreach ( $parts as $path => $content ) {
			$files[] = array(
				PCLZIP_ATT_FILE_NAME    => $path,
				PCLZIP_ATT_FILE_CONTENT => $content,
			);
		}
		$list = $zip->create( $files );
		if ( ! $list ) {
			@unlink( $tmp );
			wp_die( 'خطا در ساخت فایل اکسل.' );
		}

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}
}

/* ==================================================================
 * پنل تامین‌کننده (v2.0.0) — ۱۳ مورد درخواست کاربر:
 * ۱) ورود مستقل هر تامین‌کننده با نام کاربری/گذرواژه (نشست ترنزینت ۱۲ساعته، بدون کاربر وردپرس)
 * ۲) ثبت محصول + قیمت در پنل  ۳) انتشار فقط با تأیید مدیر مرکز  ۴) قیمت به متای مشترک «قیمت تامین‌کننده»
 * ۵) صفحه قیمت‌گذاری مرکز با فرمول per-supplier (درصد/مبلغ ثابت/ضریب) ← قیمت نهایی محصول
 * ۶) سفارش‌های تامین‌کننده در پنل: قبول=«تحویل پست شده» + کد رهگیری=«تکمیل شده» (متای مشترک رهگیری هسته)
 * ۷) گزارش عودت/مرجوعی از اپراتور مرکز به پنل تامین‌کننده
 * ۸/۹) حریم خصوصی دوطرفه: تامین‌کننده فقط داده خودش را می‌بیند؛ فروشنده هم هیچ داده تامین‌کننده‌ای نمی‌بیند
 * ۱۰) مالی: شماره کارت/شبا + لیست واریزی‌های مرکز + صفحه ثبت واریزی/مستندات در پیشخوان
 * ۱۱) کد کوتاه [dastyar_supplier_panel]  ۱۲) گرافیک هماهنگ قالب  ۱۳) نمودار و آمار فروش (SVG بدون کتابخانه)
 * ================================================================= */
final class DSupp_Panel {

	const SESS_TTL = 43200;               // ۱۲ ساعت
	const COOKIE   = 'dsupp_sess';        // نام کوکی نشست
	const OPT_PAY  = 'dsupp_payments';    // واریزی‌های مرکز به تامین‌کننده‌ها
	const OPT_RMA  = 'dsupp_rma_reports'; // گزارش‌های عودت/مرجوعی مرکز به تامین‌کننده‌ها
	const CAP      = 'manage_woocommerce';
	const ROLE     = 'dsupp_supplier';      // v2.1.0 — نقش کاربری وردپرس «تامین‌کننده»
	const OPT_REFERRAL = 'dsupp_order_referral'; // v2.1.3 — yes/no ارجاع سفارش به تامین‌کننده (پیش‌فرض yes = رفتار قبلی)

	protected static $booted = false;

	public static function boot() {
		if ( self::$booted ) { return; }
		self::$booted = true;

		// v2.1.0 — نقش «تامین‌کننده» + محدودیت‌های امنیتی حساب تامین‌کننده
		add_action( 'init', array( __CLASS__, 'register_role' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_block_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'maybe_hide_admin_bar' ) );

		// پنل فرانت (کد کوتاه) + پردازش فرم‌های فرانت روی init
		// (v2.1.2 — حیاتی:) اولویت ۲۰، یعنی حتماً «بعد از» ثبت تاکسونومی dsupp_supplier در init اولویت ۱۰؛
		// در غیر این صورت get_terms روی فرانت WP_Error می‌دهد ← لیست تامین‌کننده‌ها خالی ← ورود همیشه «پیوند نشده»/«اشتباه» می‌شد
		add_action( 'init', array( __CLASS__, 'front_posts' ), 20 );
		add_shortcode( 'dastyar_supplier_panel', array( __CLASS__, 'shortcode' ) );

		// صفحات پیشخوان مرکز
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_dsupp_user_add', array( __CLASS__, 'handle_user_add' ) );
		add_action( 'admin_post_dsupp_user_link', array( __CLASS__, 'handle_user_link' ) ); // v2.1.1 — پیوند دستی (ترمیمی)
		add_action( 'admin_post_dsupp_referral_toggle', array( __CLASS__, 'handle_referral_toggle' ) ); // v2.1.3 — فعال/غیرفعال ارجاع سفارش
		add_action( 'admin_post_dsupp_pricing_save', array( __CLASS__, 'handle_pricing_save' ) );
		add_action( 'admin_post_dsupp_recalc', array( __CLASS__, 'handle_recalc' ) );
		add_action( 'admin_post_dsupp_approve', array( __CLASS__, 'handle_approve' ) );
		add_action( 'admin_post_dsupp_reject', array( __CLASS__, 'handle_reject' ) );
		add_action( 'admin_post_dsupp_rma_add', array( __CLASS__, 'handle_rma_add' ) );
		add_action( 'admin_post_dsupp_rma_del', array( __CLASS__, 'handle_rma_del' ) );
		add_action( 'admin_post_dsupp_pay_add', array( __CLASS__, 'handle_pay_add' ) );
		add_action( 'admin_post_dsupp_pay_del', array( __CLASS__, 'handle_pay_del' ) );
	}

	/** (v2.1.3) آیا ارجاع سفارش به تامین‌کننده فعال است؟ (پیش‌فرض فعال = رفتار قبلی — تغییری در رفتار موجود نمی‌دهد) */
	public static function referral_on() {
		return 'no' !== (string) get_option( self::OPT_REFERRAL, 'yes' );
	}

	/* ------------------------------------------------------------------
	 * v2.1.0 — نقش کاربری «تامین‌کننده» و پیوند کاربر ↔ تامین‌کننده
	 * ---------------------------------------------------------------- */

	/** ثبت یک‌باره نقش (فقط read — بدون هیچ دسترسی پیشخوان) */
	public static function register_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, 'تامین‌کننده', array( 'read' => true ) );
		}
	}

	/** کاربر با نقش تامین‌کننده اجازه ورود به پیشخوان وردپرس ندارد (پنل خودش را دارد) */
	public static function maybe_block_admin() {
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) { return; }
		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) { return; }
		$u = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		if ( $u && self::user_is_supplier( $u ) && ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
	}

	/** نوار مدیریت برای حساب تامین‌کننده پنهان است */
	public static function maybe_hide_admin_bar( $show ) {
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			$u = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
			if ( $u && self::user_is_supplier( $u ) ) { return false; }
		}
		return $show;
	}

	/** آیا کاربر (WP_User) نقش تامین‌کننده دارد؟ */
	public static function user_is_supplier( $u ) {
		return $u && in_array( self::ROLE, (array) ( $u->roles ?? array() ), true );
	}

	/** شناسه کاربر وردپرس پیوندشده به یک تامین‌کننده (۰ = بدون پیوند) */
	public static function linked_uid( $tid ) {
		return function_exists( 'get_term_meta' ) ? (int) get_term_meta( (int) $tid, DSUPP_META_UID, true ) : 0;
	}

	/** (v2.1.1) نوشتن پیوند در هر دو سمت + پاکسازی پیوندهای قبلی ناسازگار */
	public static function write_link( $uid, $tid ) {
		$uid = (int) $uid;
		$tid = (int) $tid;
		if ( ! $uid || ! $tid ) { return; }
		$prev_uid = self::linked_uid( $tid );
		if ( $prev_uid && $prev_uid !== $uid && function_exists( 'delete_user_meta' ) ) {
			delete_user_meta( $prev_uid, DSUPP_UMETA_TID ); // این تامین‌کننده قبلاً به کاربر دیگری وصل بود
		}
		$prev_tid = function_exists( 'get_user_meta' ) ? (int) get_user_meta( $uid, DSUPP_UMETA_TID, true ) : 0;
		if ( $prev_tid && $prev_tid !== $tid && function_exists( 'delete_term_meta' ) ) {
			delete_term_meta( $prev_tid, DSUPP_META_UID ); // این کاربر قبلاً به تامین‌کننده دیگری وصل بود
		}
		update_term_meta( $tid, DSUPP_META_UID, $uid );
		if ( function_exists( 'update_user_meta' ) ) { update_user_meta( $uid, DSUPP_UMETA_TID, $tid ); }
	}

	/** تامین‌کننده‌ای که این کاربر وردپرس به آن پیوند شده (۰ = هیچ‌کدام) — v2.1.1 دو طرفه می‌خواند و خودترمیم می‌کند */
	public static function tid_by_user_id( $uid ) {
		$uid = (int) $uid;
		if ( ! $uid ) { return 0; }
		// مسیر سریع: متای کاربر (اگر سمت ترم گم شده باشد، بازسازی می‌شود)
		if ( function_exists( 'get_user_meta' ) ) {
			$tid = (int) get_user_meta( $uid, DSUPP_UMETA_TID, true );
			if ( $tid ) {
				foreach ( DSupp::suppliers() as $t ) {
					if ( (int) ( $t->term_id ?? 0 ) === $tid ) {
						if ( self::linked_uid( $tid ) !== $uid ) {
							update_term_meta( $tid, DSUPP_META_UID, $uid ); // خودترمیمی سمت ترم
						}
						return $tid;
					}
				}
			}
		}
		// مسیر اسکن ترم‌ها (اگر سمت کاربر گم شده باشد، بازسازی می‌شود)
		foreach ( DSupp::suppliers() as $t ) {
			$id = isset( $t->term_id ) ? (int) $t->term_id : 0;
			if ( $id && self::linked_uid( $id ) === $uid ) {
				if ( function_exists( 'update_user_meta' ) && (int) get_user_meta( $uid, DSUPP_UMETA_TID, true ) !== $id ) {
					update_user_meta( $uid, DSUPP_UMETA_TID, $id ); // خودترمیمی سمت کاربر
				}
				return $id;
			}
		}
		return 0;
	}

	/** دراپ‌داون کاربران با نقش تامین‌کننده برای فرم افزودن/ویرایش تامین‌کننده */
	public static function user_select_html( $current = 0 ) {
		$current = (int) $current;
		$out     = '<select id="dsupp_user_id" name="dsupp_user_id">';
		$out    .= '<option value="0"' . selected( $current, 0, false ) . '>— بدون پیوند —</option>';
		$users   = function_exists( 'get_users' ) ? get_users( array( 'role' => self::ROLE ) ) : array();
		foreach ( (array) $users as $u ) {
			$uid  = (int) $u->ID;
			$lbl  = (string) ( $u->user_login ?? '' );
			$lbl .= $u->display_name ? ' — ' . (string) $u->display_name : '';
			$out .= '<option value="' . $uid . '"' . selected( $current, $uid, false ) . '>' . esc_html( $lbl ) . '</option>';
		}
		$out .= '</select>';
		return $out;
	}

	/* ------------------------------------------------------------------
	 * خواندن متاهای ترم (فالبک امن)
	 * ---------------------------------------------------------------- */

	public static function username( $tid ) {
		return function_exists( 'get_term_meta' ) ? (string) get_term_meta( (int) $tid, DSUPP_META_USER, true ) : '';
	}

	public static function pass_hash( $tid ) {
		return function_exists( 'get_term_meta' ) ? (string) get_term_meta( (int) $tid, DSUPP_META_HASH, true ) : '';
	}

	public static function card( $tid ) {
		return function_exists( 'get_term_meta' ) ? (string) get_term_meta( (int) $tid, DSUPP_META_CARD, true ) : '';
	}

	public static function sheba( $tid ) {
		return function_exists( 'get_term_meta' ) ? (string) get_term_meta( (int) $tid, DSUPP_META_SHEBA, true ) : '';
	}

	public static function formula( $tid ) {
		$f = function_exists( 'get_term_meta' ) ? get_term_meta( (int) $tid, DSUPP_META_FORMULA, true ) : '';
		$f = is_array( $f ) ? $f : array();
		$t = isset( $f['type'] ) ? (string) $f['type'] : 'percent';
		if ( ! in_array( $t, array( 'percent', 'fixed', 'multiplier' ), true ) ) { $t = 'percent'; }
		return array( 'type' => $t, 'value' => (float) ( $f['value'] ?? 0 ) );
	}

	/* ------------------------------------------------------------------
	 * نرمال‌سازی ورودی‌ها
	 * ---------------------------------------------------------------- */

	/** شماره کارت: فقط رقم (ارقام فارسی هم قبول) */
	public static function norm_card( $raw ) {
		$s = DSupp::norm_num( $raw );
		return (string) preg_replace( '/\D/', '', str_replace( '.', '', (string) $s ) );
	}

	/** شماره شبا: حروف بزرگ + ارقام لاتین؛ اگر ۲۴ رقم خالی بود IR اضافه می‌شود */
	public static function norm_sheba( $raw ) {
		$s = strtoupper( trim( (string) $raw ) );
		$s = strtr( $s, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9' ) );
		$s = (string) preg_replace( '/[^A-Z0-9]/', '', $s );
		if ( preg_match( '/^\d{24}$/', $s ) ) { $s = 'IR' . $s; }
		return $s;
	}

	public static function is_valid_sheba( $s ) {
		return 1 === preg_match( '/^IR\d{24}$/', (string) $s );
	}

	/** نمایش گروه‌بندی‌شده (خوانا) */
	public static function card_chunked( $card ) {
		$c = self::norm_card( $card );
		return 16 === strlen( $c ) ? implode( '-', str_split( $c, 4 ) ) : $c;
	}

	public static function card_display( $tid ) {
		return self::card_chunked( self::card( $tid ) );
	}

	/** نمایش ماسک‌شده (در پنل، روی مقدار ذخیره‌شده) */
	public static function card_masked( $tid ) {
		$c = self::norm_card( self::card( $tid ) );
		return 16 === strlen( $c ) ? '****-****-****-' . substr( $c, -4 ) : '';
	}

	public static function sheba_display( $tid ) {
		$s = self::sheba( $tid );
		return $s ? wordwrap( $s, 4, ' ', true ) : '';
	}

	/* ------------------------------------------------------------------
	 * ذخیره فیلدهای پنل از فرم پیشخوان (افزودن/ویرایش تامین‌کننده)
	 * ---------------------------------------------------------------- */

	public static function save_admin_fields( $tid, $mode = 'edit' ) {
		$tid = (int) $tid;
		if ( ! $tid || ! function_exists( 'update_term_meta' ) ) { return; }
		$in = wp_unslash( $_POST ); // phpcs:ignore -- دسترسی و نانس در هندلر اصلی بررسی شد

		// نام کاربری
		$u = isset( $in['dsupp_username'] ) ? sanitize_user( (string) $in['dsupp_username'], true ) : '';
		if ( 'add' === $mode ) {
			if ( '' === $u ) { $u = 'sup' . $tid; }
			while ( self::tid_by_username( $u ) ) { $u .= 'x'; } // یکتا شود
			update_term_meta( $tid, DSUPP_META_USER, $u );
		} elseif ( '' !== $u ) {
			$other = self::tid_by_username( $u );
			if ( ! $other || $other === $tid ) {
				update_term_meta( $tid, DSUPP_META_USER, $u );
			}
			// نام کاربری تکراری ← بی‌تغییر می‌ماند (سیاست امن، بدون غافلگیری)
		}
		$final_user = self::username( $tid );

		// گذرواژه
		$p    = isset( $in['dsupp_password'] ) ? trim( (string) $in['dsupp_password'] ) : '';
		$made = '';
		if ( 'add' === $mode && '' === $p ) {
			$p    = wp_generate_password( 10, false, false );
			$made = $p;
		}
		if ( '' !== $p && strlen( $p ) >= 4 && function_exists( 'wp_hash_password' ) ) {
			update_term_meta( $tid, DSUPP_META_HASH, wp_hash_password( $p ) );
		}
		if ( 'add' === $mode && $final_user ) {
			set_transient( 'dsupp_newcred_' . get_current_user_id(), array( 'user' => $final_user, 'pass' => $made ), 120 );
		}

		// v2.1.0 — پیوند کاربر وردپرس (نقش تامین‌کننده): یوزرنیم/پسورد همان کاربر = ورود پنل
		// v2.1.1 — پیوند دوطرفه: سمت کاربر هم ثبت می‌شود + پیوند قبلی پاکسازی می‌شود
		if ( isset( $in['dsupp_user_id'] ) ) {
			$uid  = absint( $in['dsupp_user_id'] );
			$prev = self::linked_uid( $tid );
			if ( $uid ) {
				$wu = function_exists( 'get_userdata' ) ? get_userdata( $uid ) : false;
				if ( $wu && self::user_is_supplier( $wu ) ) {
					self::write_link( $uid, $tid );
				}
				// کاربر بدون نقش تامین‌کننده ← بی‌تغییر (سیاست امن)
			} else {
				delete_term_meta( $tid, DSUPP_META_UID ); // «بدون پیوند» انتخاب شد
				if ( $prev && function_exists( 'delete_user_meta' ) ) { delete_user_meta( $prev, DSUPP_UMETA_TID ); }
			}
		}

		// کارت (خالی = پاکسازی؛ غیر ۱۶ رقم = بی‌تغییر)
		if ( isset( $in['dsupp_card'] ) ) {
			$c = self::norm_card( $in['dsupp_card'] );
			if ( '' === $c ) { delete_term_meta( $tid, DSUPP_META_CARD ); }
			elseif ( 16 === strlen( $c ) ) { update_term_meta( $tid, DSUPP_META_CARD, $c ); }
		}
		// شبا (خالی = پاکسازی؛ نامعتبر = بی‌تغییر)
		if ( isset( $in['dsupp_sheba'] ) ) {
			$s = self::norm_sheba( $in['dsupp_sheba'] );
			if ( '' === $s ) { delete_term_meta( $tid, DSUPP_META_SHEBA ); }
			elseif ( self::is_valid_sheba( $s ) ) { update_term_meta( $tid, DSUPP_META_SHEBA, $s ); }
		}
	}

	/* ------------------------------------------------------------------
	 * نشست ورود پنل (مستقل از کاربران وردپرس)
	 * ---------------------------------------------------------------- */

	public static function tid_by_username( $u ) {
		$u = (string) $u;
		if ( '' === $u ) { return 0; }
		foreach ( DSupp::suppliers() as $t ) {
			$id = isset( $t->term_id ) ? (int) $t->term_id : 0;
			if ( $id && self::username( $id ) === $u ) { return $id; }
		}
		return 0;
	}

	protected static function set_cookie( $name, $val, $exp ) {
		if ( defined( 'DSUPP_PANEL_TESTING' ) ) {
			$GLOBALS['__dsupp_cookies'][ $name ] = $val;
			return;
		}
		if ( ! headers_sent() ) {
			@setcookie( $name, $val, $exp, defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/', defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '', function_exists( 'is_ssl' ) && is_ssl(), true );
		}
	}

	public static function start_session( $tid ) {
		$token = wp_generate_password( 40, false, false );
		set_transient( 'dsupp_sess_' . $token, (int) $tid, self::SESS_TTL );
		self::set_cookie( self::COOKIE, $token, time() + self::SESS_TTL );
	}

	public static function kill_session() {
		$tid   = 0;
		$token = self::sess_token();
		if ( $token ) {
			delete_transient( 'dsupp_sess_' . $token );
		}
		self::set_cookie( self::COOKIE, '', time() - 3600 );
		return $tid;
	}

	protected static function sess_token() {
		$t = isset( $_COOKIE[ self::COOKIE ] ) ? (string) $_COOKIE[ self::COOKIE ] : '';
		return (string) preg_replace( '/[^A-Za-z0-9]/', '', $t );
	}

	public static function current_tid() {
		$token = self::sess_token();
		if ( '' === $token ) { return 0; }
		$tid = get_transient( 'dsupp_sess_' . $token );
		return (int) $tid;
	}

	/* ------------------------------------------------------------------
	 * قیمت‌گذاری (مورد ۵) — موتور فرمول + اعمال روی محصول
	 * ---------------------------------------------------------------- */

	/** قیمت نهایی از روی هزینه تامین‌کننده و فرمول — خروجی گردِ رو به بالای ۱۰۰۰ تومان */
	public static function price_final( $cost, $formula ) {
		$c = (float) DSupp::norm_num( $cost );
		if ( $c <= 0 ) { return 0.0; }
		$t = is_array( $formula ) && isset( $formula['type'] ) ? (string) $formula['type'] : 'percent';
		$v = is_array( $formula ) ? (float) ( $formula['value'] ?? 0 ) : 0.0;
		if ( 'fixed' === $t ) {
			$raw = $c + $v;
		} elseif ( 'multiplier' === $t ) {
			$raw = $c * max( 0.0, $v );
		} else {
			$raw = $c * ( 1 + $v / 100 );
		}
		if ( $raw >= 1000 ) {
			$raw = ceil( $raw / 1000 ) * 1000;
		} else {
			$raw = ceil( $raw );
		}
		return (float) $raw;
	}

	/** اعمال قیمت نهایی روی محصول (متاهای استاندارد ووکامرس) */
	public static function apply_price( $pid, $tid ) {
		$pid  = (int) $pid;
		$cost = (float) DSupp::norm_num( (string) get_post_meta( $pid, DSUPP_META_COST, true ) );
		if ( ! $pid || $cost <= 0 ) { return false; }
		$final = self::price_final( $cost, self::formula( $tid ) );
		if ( $final <= 0 ) { return false; }
		update_post_meta( $pid, '_regular_price', (string) $final );
		update_post_meta( $pid, '_price', (string) $final );
		return $final;
	}

	/** بازمحاسبه همه محصولات منتشرشده یک تامین‌کننده ← خروجی: تعداد به‌روزشده */
	public static function recalc_supplier( $tid ) {
		$tid = (int) $tid;
		if ( ! $tid || ! function_exists( 'wc_get_products' ) ) { return 0; }
		$products = wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'return' => 'objects' ) );
		$n        = 0;
		foreach ( (array) $products as $p ) {
			$pid = ( is_object( $p ) && method_exists( $p, 'get_id' ) ) ? (int) $p->get_id() : ( is_numeric( $p ) ? (int) $p : 0 );
			if ( ! $pid || DSupp::product_supplier_id( $pid ) !== $tid ) { continue; }
			// گارد دفاعی: حتی اگر کوئری وضعیت را رعایت نکرد، فقط منتشرشده‌ها
			if ( is_object( $p ) && method_exists( $p, 'get_status' ) && 'publish' !== $p->get_status() ) { continue; }
			if ( self::apply_price( $pid, $tid ) ) { $n++; }
		}
		return $n;
	}

	/* ------------------------------------------------------------------
	 * محصولات تامین‌کننده (پنل)
	 * ---------------------------------------------------------------- */

	public static function product_price_cost( $pid ) {
		return (string) get_post_meta( (int) $pid, DSUPP_META_COST, true );
	}

	public static function product_final_price( $pid ) {
		$p = (string) get_post_meta( (int) $pid, '_regular_price', true );
		return $p;
	}

	public static function supplier_products( $tid ) {
		$tid  = (int) $tid;
		$list = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'limit' => -1, 'status' => array( 'publish', 'pending' ), 'return' => 'objects' ) ) : array();
		$out  = array();
		foreach ( (array) $list as $p ) {
			$pid = ( is_object( $p ) && method_exists( $p, 'get_id' ) ) ? (int) $p->get_id() : 0;
			if ( ! $pid || DSupp::product_supplier_id( $pid ) !== $tid ) { continue; }
			$out[] = array(
				'id'     => $pid,
				'name'   => method_exists( $p, 'get_name' ) ? (string) $p->get_name() : '',
				'status' => method_exists( $p, 'get_status' ) ? (string) $p->get_status() : '',
				'cost'   => self::product_price_cost( $pid ),
				'final'  => self::product_final_price( $pid ),
			);
		}
		return $out;
	}

	/** ثبت محصول جدید از پنل ← وضعیت «در انتظار بررسی» تا تأیید مرکز (مورد ۲ و ۳) */
	public static function submit_product( $tid, $in ) {
		$title = isset( $in['dsupp_title'] ) ? DSupp::cut( sanitize_text_field( $in['dsupp_title'] ), 120 ) : '';
		$price = DSupp::norm_num( $in['dsupp_price'] ?? '' );
		$desc  = isset( $in['dsupp_desc'] ) ? DSupp::cut( sanitize_textarea_field( $in['dsupp_desc'] ), 2000 ) : '';
		if ( '' === $title || '' === (string) $price || (float) $price <= 0 ) {
			return 'bad';
		}
		$pid = (int) wp_insert_post( array(
			'post_type'    => 'product',
			'post_status'  => 'pending',
			'post_title'   => $title,
			'post_content' => $desc,
		), true );
		if ( ! $pid ) { return 'bad'; }
		update_post_meta( $pid, DSUPP_META_COST, (string) $price ); // مورد ۴: همان فیلد «قیمت تامین‌کننده»
		update_post_meta( $pid, DSUPP_META_TID, (int) $tid );
		update_post_meta( $pid, DSUPP_META_TNAME, DSupp::term_name( $tid ) );
		update_post_meta( $pid, DSUPP_META_SUBMITTED, 1 );
		if ( function_exists( 'wp_set_object_terms' ) ) {
			wp_set_object_terms( $pid, array( (int) $tid ), DSUPP_TAX, false );
		}
		return 'prod_pending';
	}

	/** ویرایش محصول خودش (نام/توضیح/قیمت) — اگر منتشر شده، قیمت نهایی دوباره محاسبه می‌شود */
	public static function edit_own_product( $tid, $in ) {
		$pid = isset( $in['dsupp_pid'] ) ? absint( $in['dsupp_pid'] ) : 0;
		if ( ! $pid || DSupp::product_supplier_id( $pid ) !== (int) $tid ) { return 'noorder'; }
		$title = isset( $in['dsupp_title'] ) ? DSupp::cut( sanitize_text_field( $in['dsupp_title'] ), 120 ) : '';
		$price = DSupp::norm_num( $in['dsupp_price'] ?? '' );
		$desc  = isset( $in['dsupp_desc'] ) ? DSupp::cut( sanitize_textarea_field( $in['dsupp_desc'] ), 2000 ) : '';
		if ( '' === $title || '' === (string) $price || (float) $price <= 0 ) { return 'bad'; }
		wp_update_post( array( 'ID' => $pid, 'post_title' => $title, 'post_content' => $desc ) );
		update_post_meta( $pid, DSUPP_META_COST, (string) $price );
		if ( 'publish' === self::product_status( $pid ) ) {
			self::apply_price( $pid, $tid );
		}
		return 'prod_saved';
	}

	/** وضعیت محصول با فالبک (ووکامرس ← هسته) */
	protected static function product_status( $pid ) {
		if ( function_exists( 'wc_get_product' ) ) {
			$p = wc_get_product( (int) $pid );
			if ( $p && method_exists( $p, 'get_status' ) ) { return (string) $p->get_status(); }
		}
		if ( function_exists( 'get_post_status' ) ) {
			$s = get_post_status( (int) $pid );
			if ( $s ) { return (string) $s; }
		}
		return '';
	}

	/** حذف فقط برای «در انتظار تأیید» (محصول منتشرشده را مرکز مدیریت می‌کند) */
	public static function delete_own_product( $tid, $pid ) {
		$pid = (int) $pid;
		if ( ! $pid || DSupp::product_supplier_id( $pid ) !== (int) $tid ) { return 'noorder'; }
		$st = self::product_status( $pid );
		if ( '' !== $st && 'pending' !== $st ) { return 'cant'; }
		wp_trash_post( $pid );
		return 'prod_deleted';
	}

	/* ------------------------------------------------------------------
	 * سفارش‌های تامین‌کننده (مورد ۶)
	 * ---------------------------------------------------------------- */

	/** آیتم‌های سفارش که محصولشان متعلق به این تامین‌کننده است + جمع سهم او */
	public static function order_own_rows( $order, $tid ) {
		$items = is_object( $order ) && method_exists( $order, 'get_items' ) ? $order->get_items() : array();
		$mine  = array();
		$sum   = 0.0;
		foreach ( (array) $items as $it ) {
			$pid = ( is_object( $it ) && method_exists( $it, 'get_product_id' ) ) ? (int) $it->get_product_id() : 0;
			if ( ! $pid || DSupp::product_supplier_id( $pid ) !== (int) $tid ) { continue; }
			$mine[] = $it;
			$sum   += (float) ( ( is_object( $it ) && method_exists( $it, 'get_total' ) ) ? $it->get_total() : 0 );
		}
		return array( $mine, $sum );
	}

	/** همه سفارش‌های دارای حداقل یک آیتم از این تامین‌کننده */
	public static function supplier_orders( $tid ) {
		$tid    = (int) $tid;
		$orders = function_exists( 'wc_get_orders' ) ? wc_get_orders( array( 'limit' => -1, 'status' => array( 'processing', 'on-hold', 'posted', 'completed' ) ) ) : array();
		$out    = array();
		foreach ( (array) $orders as $o ) {
			list( $mine, $sum ) = self::order_own_rows( $o, $tid );
			if ( $mine ) {
				$out[] = array( 'order' => $o, 'items' => $mine, 'total' => $sum );
			}
		}
		return $out;
	}

	protected static function order_belonging( $tid, $oid ) {
		if ( ! function_exists( 'wc_get_order' ) ) { return null; }
		$o = wc_get_order( (int) $oid );
		if ( ! $o ) { return null; }
		list( $mine, ) = self::order_own_rows( $o, $tid );
		return $mine ? $o : null;
	}

	/** قبول سفارش = تغییر وضعیت به «تحویل پست شده» (مورد ۶) */
	public static function accept_order( $tid, $oid ) {
		if ( ! self::referral_on() ) { return 'off'; } // v2.1.3
		$o = self::order_belonging( $tid, $oid );
		if ( ! $o ) { return 'noorder'; }
		$st = method_exists( $o, 'get_status' ) ? (string) $o->get_status() : '';
		if ( ! in_array( $st, array( 'processing', 'on-hold' ), true ) ) { return 'badstate'; }
		$posted = class_exists( 'Dastyar_Statuses' ) ? Dastyar_Statuses::POSTED : 'posted';
		$note   = 'تامین‌کننده «' . DSupp::term_name( $tid ) . '» سفارش را قبول و به پست تحویل داد.';
		if ( method_exists( $o, 'update_status' ) ) {
			$o->update_status( $posted, $note );
		}
		return 'accepted';
	}

	/** کد رهگیری = ثبت در متای مشترک هسته + وضعیت «تکمیل شده» (مورد ۶ — چرخ دوباره اختراع نشد) */
	public static function track_order( $tid, $oid, $code ) {
		if ( ! self::referral_on() ) { return 'off'; } // v2.1.3
		$o = self::order_belonging( $tid, $oid );
		if ( ! $o ) { return 'noorder'; }
		$code = self::norm_tracking( $code );
		if ( '' === $code ) { return 'bad'; }
		$st = method_exists( $o, 'get_status' ) ? (string) $o->get_status() : '';
		if ( ! in_array( $st, array( 'processing', 'on-hold', 'posted' ), true ) ) { return 'badstate'; }
		update_post_meta( (int) $oid, '_dastyar_tracking_code', $code ); // هوک پیامک کد رهگیری هسته روی همین متاست
		if ( method_exists( $o, 'update_status' ) ) {
			$o->update_status( 'completed', 'کد رهگیری پست توسط تامین‌کننده «' . DSupp::term_name( $tid ) . '» ثبت شد: ' . $code );
		}
		return 'tracked';
	}

	public static function norm_tracking( $raw ) {
		$s = trim( (string) $raw );
		$s = strtr( $s, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9' ) );
		$s = (string) preg_replace( '/[^A-Za-z0-9\-]/', '', strtoupper( $s ) ); // فقط حروف/رقم/خط‌تیره
		return DSupp::cut( $s, 40 );
	}

	/* ------------------------------------------------------------------
	 * مرجوعی (مورد ۷) + واریزی‌ها (مورد ۱۰) — ذخیره‌سازی مشترک مرکز/پنل
	 * ---------------------------------------------------------------- */

	public static function rma_rows( $tid = 0 ) {
		$rows = (array) get_option( self::OPT_RMA, array() );
		$rows = array_values( array_filter( $rows, 'is_array' ) );
		if ( $tid ) {
			$tid  = (int) $tid;
			$rows = array_values( array_filter( $rows, function( $r ) use ( $tid ) { return (int) ( $r['tid'] ?? 0 ) === $tid; } ) );
		}
		return $rows;
	}

	public static function add_rma( $tid, $order_id, $reason, $amount, $note ) {
		$rows   = self::rma_rows();
		$rows[] = array(
			'id'      => time() . rand( 100, 999 ),
			'tid'     => (int) $tid,
			'order'   => (int) $order_id,
			'reason'  => DSupp::cut( (string) $reason, 200 ),
			'amount'  => (float) DSupp::norm_num( $amount ),
			'note'    => DSupp::cut( (string) $note, 300 ),
			'time'    => time(),
		);
		$rows = array_slice( $rows, -300 );
		update_option( self::OPT_RMA, $rows, false );
	}

	public static function del_rma( $id ) {
		$id   = (string) $id;
		$rows = array_values( array_filter( self::rma_rows(), function( $r ) use ( $id ) { return (string) ( $r['id'] ?? '' ) !== $id; } ) );
		update_option( self::OPT_RMA, $rows, false );
	}

	public static function pay_rows( $tid = 0 ) {
		$rows = (array) get_option( self::OPT_PAY, array() );
		$rows = array_values( array_filter( $rows, 'is_array' ) );
		if ( $tid ) {
			$tid  = (int) $tid;
			$rows = array_values( array_filter( $rows, function( $r ) use ( $tid ) { return (int) ( $r['tid'] ?? 0 ) === $tid; } ) );
		}
		return $rows;
	}

	public static function add_payment( $tid, $amount, $ref, $note ) {
		$rows   = self::pay_rows();
		$rows[] = array(
			'id'   => time() . rand( 100, 999 ),
			'tid'  => (int) $tid,
			'amt'  => (float) DSupp::norm_num( $amount ),
			'ref'  => DSupp::cut( (string) $ref, 60 ),
			'note' => DSupp::cut( (string) $note, 200 ),
			'time' => time(),
		);
		$rows = array_slice( $rows, -300 );
		update_option( self::OPT_PAY, $rows, false );
	}

	public static function del_payment( $id ) {
		$id   = (string) $id;
		$rows = array_values( array_filter( self::pay_rows(), function( $r ) use ( $id ) { return (string) ( $r['id'] ?? '' ) !== $id; } ) );
		update_option( self::OPT_PAY, $rows, false );
	}

	public static function payments_sum( $tid ) {
		$s = 0.0;
		foreach ( self::pay_rows( $tid ) as $r ) { $s += (float) ( $r['amt'] ?? 0 ); }
		return $s;
	}

	/* ------------------------------------------------------------------
	 * آمار و نمودار (مورد ۱۳)
	 * ---------------------------------------------------------------- */

	public static function sales_stats( $tid ) {
		$stats = array( 'sales' => 0.0, 'due' => 0.0, 'active' => 0, 'orders' => 0, 'months' => array() );
		$mk    = array();
		for ( $i = 5; $i >= 0; $i-- ) {
			$mk[ gmdate( 'Y-m', strtotime( 'first day of -' . $i . ' months' ) ) ] = 0.0;
		}
		foreach ( self::supplier_orders( $tid ) as $r ) {
			$o  = $r['order'];
			$st = method_exists( $o, 'get_status' ) ? (string) $o->get_status() : '';
			$stats['sales']  += $r['total'];
			$stats['orders'] += 1;
			if ( in_array( $st, array( 'posted', 'completed' ), true ) ) { $stats['due'] += $r['total']; }
			if ( in_array( $st, array( 'processing', 'on-hold' ), true ) ) { $stats['active'] += 1; }
			$dt = method_exists( $o, 'get_date_created' ) ? $o->get_date_created() : null;
			$ts = ( $dt instanceof DateTimeInterface ) ? $dt->getTimestamp() : time();
			$k  = gmdate( 'Y-m', $ts );
			if ( array_key_exists( $k, $mk ) ) { $mk[ $k ] += $r['total']; }
		}
		$stats['months'] = $mk;
		return $stats;
	}

	/** نمودار میله‌ای SVG درون‌خطی (بدون کتابخانه خارجی — سازگار با حریم خصوصی و سرعت) */
	public static function chart_svg( $months ) {
		$vals  = array_values( (array) $months );
		$keys  = array_keys( (array) $months );
		$max   = max( 1, max( array_map( 'floatval', $vals ?: array( 0 ) ) ) );
		$w     = 560; $h = 200; $base = 160; $top = 26;
		$n     = max( 1, count( $vals ) );
		$slot  = ( $w - 40 ) / $n;
		$bw    = min( 54, $slot - 18 );
		$svg   = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="auto" role="img" aria-label="نمودار فروش شش ماه اخیر" style="display:block">';
		$svg  .= '<line x1="20" y1="' . $base . '" x2="' . ( $w - 10 ) . '" y2="' . $base . '" stroke="#dfe3ec" stroke-width="1"/>';
		foreach ( $vals as $i => $v ) {
			$v   = (float) $v;
			$bh  = $v > 0 ? max( 3, ( $v / $max ) * ( $base - $top ) ) : 3;
			$x   = 20 + $i * $slot + ( $slot - $bw ) / 2;
			$y   = $base - $bh;
			$lab = date_i18n( 'Y/m', strtotime( $keys[ $i ] . '-01' ) );
			$svg .= '<g>';
			$svg .= '<rect x="' . round( $x, 1 ) . '" y="' . round( $y, 1 ) . '" width="' . round( $bw, 1 ) . '" height="' . round( $bh, 1 ) . '" rx="6" fill="' . ( $v > 0 ? '#17a16d' : '#e8ebf1' ) . '"><title>' . esc_html( self::money( $v ) ) . '</title></rect>';
			if ( $v > 0 ) {
				$svg .= '<text x="' . round( $x + $bw / 2, 1 ) . '" y="' . round( $y - 6, 1 ) . '" text-anchor="middle" font-size="10" fill="#242536">' . esc_html( self::money_short( $v ) ) . '</text>';
			}
			$svg .= '<text x="' . round( $x + $bw / 2, 1 ) . '" y="' . ( $base + 18 ) . '" text-anchor="middle" font-size="10" fill="#8a8fa3">' . esc_html( $lab ) . '</text>';
			$svg .= '</g>';
		}
		$svg .= '</svg>';
		return $svg;
	}

	/** قالب‌بندی مبلغ برای نمایش */
	public static function money( $n ) {
		return number_format( (float) $n ) . ' تومان';
	}

	/** نمایش فشرده مبلغ روی نمودار: ۱۲٫۵م (میلیون) / ۸۰۰هـ (هزار) */
	public static function money_short( $n ) {
		$n = (float) $n;
		if ( $n >= 1000000000 ) { return rtrim( rtrim( number_format( $n / 1000000000, 1 ), '0' ), '.' ) . ' میلیارد'; }
		if ( $n >= 1000000 ) { return rtrim( rtrim( number_format( $n / 1000000, 1 ), '0' ), '.' ) . 'م'; }
		if ( $n >= 1000 ) { return number_format( (float) round( $n / 1000 ), 0 ) . 'هـ'; }
		return number_format( $n );
	}

	/* ------------------------------------------------------------------
	 * پردازش فرم‌های فرانت پنل (روی init — صفحه عادی با کد کوتاه)
	 * ---------------------------------------------------------------- */

	public static function front_posts() {
		if ( empty( $_POST['dsupp_action'] ) ) { return; } // phpcs:ignore
		$act = sanitize_key( wp_unslash( $_POST['dsupp_action'] ) );
		$in  = wp_unslash( $_POST ); // phpcs:ignore

		if ( 'login' === $act ) {
			$nonce = isset( $in['_wpnonce'] ) ? sanitize_key( $in['_wpnonce'] ) : '';
			if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'dsupp_login' ) ) {
				self::back_front( 'bad' );
			}
			$u  = isset( $in['dsupp_user'] ) ? sanitize_user( (string) $in['dsupp_user'], true ) : '';
			// (v2.0.1) فاصله‌های ابتدا/انتهای کپی-پیست تحمل می‌شوند (در ذخیره هم گذرواژه trim می‌شود)
			$pw = isset( $in['dsupp_pass'] ) ? trim( (string) $in['dsupp_pass'] ) : '';
			// v2.1.0 — اول با حساب کاربری وردپرس (نقش «تامین‌کننده») احراز می‌شود: یوزرنیم/پسورد همان کاربر = ورود پنل
			$wp_user = $u && function_exists( 'get_user_by' ) ? get_user_by( 'login', $u ) : false;
			if ( $wp_user && self::user_is_supplier( $wp_user )
				&& '' !== $pw && function_exists( 'wp_check_password' )
				&& wp_check_password( $pw, (string) $wp_user->user_pass, (int) $wp_user->ID ) ) {
				$linked = self::tid_by_user_id( (int) $wp_user->ID );
				if ( $linked ) {
					self::start_session( $linked );
					self::back_front( 'welcome' );
				}
				self::back_front( 'unlink' ); // حساب ساخته شده ولی به هیچ تامین‌کننده‌ای پیوند نشده
			}
			// مسیر قبلی (سازگار): نام کاربری/گذرواژه مستقل پنل روی متای ترم — بدون تغییر رفتار
			$t  = $u ? self::tid_by_username( $u ) : 0;
			if ( $t && '' !== $pw && function_exists( 'wp_check_password' ) && wp_check_password( $pw, (string) self::pass_hash( $t ) ) ) {
				self::start_session( $t );
				self::back_front( 'welcome' );
			}
			self::back_front( 'bad' );
		}

		if ( 'logout' === $act ) {
			self::kill_session();
			self::back_front( 'bye' );
		}

		$tid   = self::current_tid();
		$nonce = isset( $in['_wpnonce'] ) ? sanitize_key( $in['_wpnonce'] ) : '';
		if ( ! $tid || '' === $nonce || ! wp_verify_nonce( $nonce, 'dsupp_panel' ) ) {
			self::back_front( 'bad' );
		}

		switch ( $act ) {
			case 'prod_add':
				self::back_front( self::submit_product( $tid, $in ) );
				break;
			case 'prod_edit':
				self::back_front( self::edit_own_product( $tid, $in ) );
				break;
			case 'prod_del':
				self::back_front( self::delete_own_product( $tid, isset( $in['dsupp_pid'] ) ? absint( $in['dsupp_pid'] ) : 0 ) );
				break;
			case 'order_accept':
				self::back_front( self::accept_order( $tid, isset( $in['dsupp_oid'] ) ? absint( $in['dsupp_oid'] ) : 0 ) );
				break;
			case 'order_track':
				self::back_front( self::track_order( $tid, isset( $in['dsupp_oid'] ) ? absint( $in['dsupp_oid'] ) : 0, isset( $in['dsupp_code'] ) ? $in['dsupp_code'] : '' ) );
				break;
			case 'finance_save':
				self::back_front( self::save_finance( $tid, $in ) );
				break;
		}
	}

	/** ذخیره کارت/شبا از پنل تامین‌کننده (مورد ۱۰) — نامعتبر = بدون تغییر */
	public static function save_finance( $tid, $in ) {
		$cset = isset( $in['dsupp_card'] );
		$sset = isset( $in['dsupp_sheba'] );
		$bad  = false;
		if ( $cset ) {
			$c = self::norm_card( $in['dsupp_card'] );
			if ( '' === $c ) { delete_term_meta( (int) $tid, DSUPP_META_CARD ); }
			elseif ( 16 === strlen( $c ) ) { update_term_meta( (int) $tid, DSUPP_META_CARD, $c ); }
			else { $bad = true; }
		}
		if ( $sset ) {
			$s = self::norm_sheba( $in['dsupp_sheba'] );
			if ( '' === $s ) { delete_term_meta( (int) $tid, DSUPP_META_SHEBA ); }
			elseif ( self::is_valid_sheba( $s ) ) { update_term_meta( (int) $tid, DSUPP_META_SHEBA, $s ); }
			else { $bad = true; }
		}
		return $bad ? 'fin_bad' : 'fin_saved';
	}

	/** برگشت به همان صفحه پنل با پیام */
	protected static function back_front( $msg ) {
		$base = self::panel_base_url(); // (v2.0.1) اولویت با _wp_http_referer استاندارد وردپرس
		$url  = $base ? $base : home_url( '/' );
		$url  = function_exists( 'remove_query_arg' ) ? remove_query_arg( 'dsupp_msg', $url ) : $url;
		$tab = isset( $_POST['dsupp_tab'] ) ? sanitize_key( wp_unslash( $_POST['dsupp_tab'] ) ) : ''; // phpcs:ignore
		if ( $tab && function_exists( 'add_query_arg' ) ) {
			$url = add_query_arg( 'dsupp_tab', $tab, $url );
		}
		wp_safe_redirect( function_exists( 'add_query_arg' ) ? add_query_arg( 'dsupp_msg', $msg, $url ) : $url );
		exit;
	}

	/**
	 * (v2.0.1) آدرس صفحه پنل برای بازگشت:
	 * ۱) فیلد استاندارد _wp_http_referer (خودکار توسط wp_nonce_field در فرم‌ها خروجی داده می‌شود)
	 * ۲) wp_get_referer  ۳) خالی ← برگشت به خانه — فقط میزبانِ خودِ سایت پذیرفته می‌شود.
	 */
	protected static function panel_base_url() {
		$host = function_exists( 'wp_parse_url' ) ? (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) : '';
		$cand = array();
		if ( isset( $_POST['_wp_http_referer'] ) ) { $cand[] = (string) wp_unslash( $_POST['_wp_http_referer'] ); } // phpcs:ignore
		$ref = wp_get_referer();
		if ( $ref ) { $cand[] = (string) $ref; }
		foreach ( $cand as $c ) {
			$c = trim( $c );
			if ( '' === $c ) { continue; }
			if ( 0 === strpos( $c, '/' ) && ( ! isset( $c[1] ) || '/' !== $c[1] ) ) { $c = home_url( $c ); }
			$h = function_exists( 'wp_parse_url' ) ? (string) wp_parse_url( $c, PHP_URL_HOST ) : '';
			if ( '' !== $host && $h === $host ) { return $c; }
		}
		return '';
	}

	/* ------------------------------------------------------------------
	 * رندر پنل فرانت (کد کوتاه — مورد ۱۱ و ۱۲)
	 * ---------------------------------------------------------------- */

	public static function shortcode( $atts = array() ) {
		// (v2.0.1) صفحه پنل هرگز در کش صفحه (LiteSpeed و امثال آن) نشود — گرنه پس از ورود باز صفحه ورود دیده می‌شود
		if ( ! defined( 'DSUPP_PANEL_TESTING' ) && ! headers_sent() ) {
			nocache_headers();
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
		}
		ob_start();
		self::render();
		return (string) ob_get_clean();
	}

	protected static function styles() {
		echo '<style>
		.dsup{font-family:Vazirmatn,Tahoma,sans-serif;direction:rtl;max-width:1000px;margin:0 auto;color:#242536}
		.dsup *{box-sizing:border-box}
		.dsup-card{background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(36,37,54,.07);padding:18px 20px;margin-bottom:16px;border:1px solid #eceef4}
		.dsup-card h2{margin:0 0 12px;font-size:16px;color:#242536}
		.dsup-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
		.dsup-brand{font-weight:bold;font-size:15px;color:#242536}
		.dsup-brand span{color:#17a16d}
		.dsup-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}
		.dsup-tabs a{padding:8px 18px;border-radius:999px;text-decoration:none;font-size:13px;background:#fff;color:#242536;border:1px solid #e3e6ee}
		.dsup-tabs a.on{background:#17a16d;color:#fff;border-color:#17a16d}
		.dsup-msg{border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13px}
		.dsup-msg.ok{background:#e7f6f0;border:1px solid #bfe8d8;color:#0f7a52}
		.dsup-msg.err{background:#fdecec;border:1px solid #f3c9c9;color:#c0392b}
		.dsup-grid{display:flex;gap:12px;flex-wrap:wrap}
		.dsup-stat{flex:1;min-width:150px;background:#f7f9fb;border:1px solid #eceef3;border-radius:12px;padding:12px 14px}
		.dsup-stat .t{font-size:12px;color:#7a8092}
		.dsup-stat .v{font-size:17px;font-weight:bold;color:#242536;margin-top:4px}
		.dsup table{width:100%;border-collapse:collapse;font-size:13px}
		.dsup th{background:#242536;color:#fff;padding:8px 10px;text-align:right;font-weight:normal}
		.dsup td{padding:9px 10px;border-bottom:1px solid #eef0f4;vertical-align:middle}
		.dsup tr:nth-child(even) td{background:#fafbfc}
		.dsup input[type=text],.dsup input[type=password],.dsup input[type=number],.dsup textarea{width:100%;padding:9px 12px;border:1px solid #dfe3ec;border-radius:10px;font-family:inherit;font-size:13px;margin-bottom:10px}
		.dsup label{display:block;font-size:12.5px;margin-bottom:4px;color:#242536;font-weight:bold}
		.dsup-btn{display:inline-block;background:#17a16d;color:#fff;border:none;border-radius:10px;padding:9px 22px;font-size:13px;cursor:pointer;font-family:inherit;text-decoration:none}
		.dsup-btn:hover{background:#12855a}
		.dsup-btn.gray{background:#eef0f4;color:#242536;border:1px solid #dfe2ea;padding:6px 14px;font-size:12px}
		.dsup-btn.red{background:#fdecec;color:#c0392b;border:1px solid #f3c9c9;padding:6px 14px;font-size:12px}
		.dsup-badge{display:inline-block;padding:3px 12px;border-radius:999px;font-size:11.5px;font-weight:bold}
		.dsup-badge.pending{background:#fff3d6;color:#8a6d00}
		.dsup-badge.publish{background:#e6f6f0;color:#0f7a52}
		.dsup-badge.posted{background:#ffe9a8;color:#6b4e00}
		.dsup-badge.completed{background:#e6f6f0;color:#0f7a52}
		.dsup-badge.processing,.dsup-badge.on-hold{background:#eef0fa;color:#4a4f7a}
		.dsup-login{max-width:380px;margin:40px auto;text-align:center}
		.dsup-muted{color:#8a8fa3;font-size:12px}
		.dsup-ltr{direction:ltr;unicode-bidi:isolate;display:inline-block}
		.dsup-addr{background:#f7f9fb;border:1px dashed #dfe3ec;border-radius:10px;padding:10px 14px;font-size:12.5px;margin:10px 0}
		.dsup-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
		.dsup-row > div{flex:1;min-width:180px}
		</style>';
	}

	public static function render() {
		$tid = self::current_tid();
		self::styles();
		echo '<div class="dsup">';
		self::render_msgs(); // (v2.0.1) پیام‌ها — از جمله خطای ورود — روی صفحه ورود هم نمایش داده شوند
		if ( ! $tid ) {
			self::render_login();
			echo '</div>';
			return;
		}
		$tab  = isset( $_GET['dsupp_tab'] ) ? sanitize_key( wp_unslash( $_GET['dsupp_tab'] ) ) : 'dash';
		$tabs = array( 'dash' => 'پیشخوان', 'products' => 'محصولات', 'orders' => 'سفارش‌ها', 'rma' => 'مرجوعی‌ها', 'finance' => 'مالی' );
		// (v2.1.3) اگر مرکز ارجاع سفارش را غیرفعال کرده باشد، تب سفارش‌ها اصلاً دیده نمی‌شود
		if ( ! self::referral_on() ) { unset( $tabs['orders'] ); }
		if ( ! isset( $tabs[ $tab ] ) ) { $tab = 'dash'; }

		echo '<div class="dsup-card dsup-head">';
		echo '<div class="dsup-brand">پنل تامین‌کننده <span>|</span> ' . esc_html( DSupp::term_name( $tid ) ) . '</div>';
		echo '<form method="post" style="margin:0"><input type="hidden" name="dsupp_action" value="logout"><button type="submit" class="dsup-btn gray">خروج از پنل</button></form>';
		echo '</div>';

		echo '<div class="dsup-tabs">';
		foreach ( $tabs as $k => $label ) {
			$u = add_query_arg( 'dsupp_tab', $k );
			echo '<a href="' . esc_url( $u ) . '"' . ( $k === $tab ? ' class="on"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</div>';

		if ( 'products' === $tab ) { self::render_products( $tid ); }
		elseif ( 'orders' === $tab ) { self::render_orders( $tid ); }
		elseif ( 'rma' === $tab ) { self::render_rma( $tid ); }
		elseif ( 'finance' === $tab ) { self::render_finance( $tid ); }
		else { self::render_dash( $tid ); }
		echo '</div>';
	}

	protected static function tab_field() {
		$tab = isset( $_GET['dsupp_tab'] ) ? sanitize_key( wp_unslash( $_GET['dsupp_tab'] ) ) : '';
		echo $tab ? '<input type="hidden" name="dsupp_tab" value="' . esc_attr( $tab ) . '">' : '';
	}

	protected static function render_msgs() {
		$m = isset( $_GET['dsupp_msg'] ) ? sanitize_key( wp_unslash( $_GET['dsupp_msg'] ) ) : '';
		$map = array(
			'welcome'      => array( 'ok', 'خوش آمدید — ورود موفق بود.' ),
			'bye'          => array( 'ok', 'از پنل خارج شدید.' ),
			'bad'          => array( 'err', 'نام کاربری یا گذرواژه اشتباه است — یا نشست شما منقضی شده؛ دوباره وارد شوید.' ),
			'unlink'       => array( 'err', 'حساب شما فعال است ولی هنوز به هیچ تامین‌کننده‌ای پیوند نشده — با مرکز تماس بگیرید.' ),
			'off'          => array( 'err', 'ارجاع سفارش به تامین‌کننده توسط مرکز غیرفعال شده است — با مرکز تماس بگیرید.' ),
			'prod_pending' => array( 'ok', 'محصول ثبت شد — پس از تأیید مرکز در محصولات سایت نمایش داده می‌شود.' ),
			'prod_saved'   => array( 'ok', 'تغییرات محصول ذخیره شد.' ),
			'prod_deleted' => array( 'ok', 'محصول حذف شد.' ),
			'cant'         => array( 'err', 'این محصول منتشر شده و فقط مرکز می‌تواند آن را حذف کند.' ),
			'noorder'      => array( 'err', 'سفارش/محصول متعلق به شما پیدا نشد.' ),
			'badstate'     => array( 'err', 'با وضعیت فعلی، این اقدام قابل انجام نیست.' ),
			'accepted'     => array( 'ok', 'سفارش قبول شد و وضعیت آن «تحویل پست شده» ثبت گردید.' ),
			'tracked'      => array( 'ok', 'کد رهگیری ثبت شد و سفارش «تکمیل شده» علامت خورد؛ برای مشتری پیامک رهگیری هم می‌رود.' ),
			'fin_saved'    => array( 'ok', 'اطلاعات مالی ذخیره شد.' ),
			'fin_bad'      => array( 'err', 'شماره کارت باید ۱۶ رقم و شبا باید IR به‌همراه ۲۴ رقم باشد — بدون تغییر ماند.' ),
		);
		if ( $m && isset( $map[ $m ] ) ) {
			echo '<div class="dsup-msg ' . esc_attr( $map[ $m ][0] ) . '">' . esc_html( $map[ $m ][1] ) . '</div>';
		}
	}

	protected static function render_login() {
		echo '<div class="dsup-card dsup-login">';
		echo '<div class="dsup-brand" style="margin-bottom:14px;font-size:17px;">ورود به پنل تامین‌کننده <span>دستیار شاپ</span></div>';
		echo '<form method="post">';
		wp_nonce_field( 'dsupp_login' );
		echo '<input type="hidden" name="dsupp_action" value="login">';
		echo '<label for="dsupp_user">نام کاربری</label>';
		echo '<input type="text" id="dsupp_user" name="dsupp_user" class="dsup-ltr" style="width:100%" required>';
		echo '<label for="dsupp_pass">گذرواژه</label>';
		echo '<input type="password" id="dsupp_pass" name="dsupp_pass" class="dsup-ltr" style="width:100%" required>';
		echo '<button type="submit" class="dsup-btn" style="width:100%">ورود</button>';
		echo '</form>';
		echo '<p class="dsup-muted" style="margin-top:12px">نام کاربری و گذرواژه را مرکز در اختیار شما قرار داده است.</p>';
		echo '<p class="dsup-muted">فراموش کردید؟ با پشتیبانی دستیار شاپ تماس بگیرید تا گذرواژه جدید برایتان ساخته شود.</p>';
		echo '</div>';
	}

	protected static function render_dash( $tid ) {
		$stats = self::sales_stats( $tid );
		$pays  = self::payments_sum( $tid );
		$bal   = $stats['due'] - $pays;
		echo '<div class="dsup-grid">';
		foreach ( array(
			array( 'فروش کل', self::money( $stats['sales'] ) ),
			array( 'سفارش‌های فعال', number_format( $stats['active'] ) . ' سفارش' ),
			array( 'مجموع واریزی‌های مرکز', self::money( $pays ) ),
			array( 'مانده تسویه', self::money( max( 0, $bal ) ) ),
		) as $c ) {
			echo '<div class="dsup-stat"><div class="t">' . esc_html( $c[0] ) . '</div><div class="v">' . esc_html( $c[1] ) . '</div></div>';
		}
		echo '</div>';
		echo '<div class="dsup-card" style="margin-top:16px"><h2>نمودار فروش ۶ ماه اخیر</h2>';
		echo self::chart_svg( $stats['months'] ); // phpcs:ignore -- HTML درونی ساخته‌شده
		echo '</div>';

		$recent = array_slice( self::supplier_orders( $tid ), 0, 5 );
		echo '<div class="dsup-card"><h2>آخرین سفارش‌های شما</h2>';
		if ( ! $recent ) {
			echo '<p class="dsup-muted">هنوز سفارشی برای محصولات شما ثبت نشده است.</p>';
		} else {
			echo '<table><tr><th>سفارش</th><th>وضعیت</th><th>سهم شما</th></tr>';
			foreach ( $recent as $r ) {
				$o = $r['order'];
				echo '<tr><td>#' . ( method_exists( $o, 'get_id' ) ? (int) $o->get_id() : 0 ) . '</td>'
					. '<td>' . self::status_badge( method_exists( $o, 'get_status' ) ? $o->get_status() : '' ) . '</td>'
					. '<td>' . esc_html( self::money( $r['total'] ) ) . '</td></tr>';
			}
			echo '</table>';
		}
		echo '</div>';
	}

	protected static function status_badge( $status ) {
		$names = array( 'pending' => 'در انتظار تأیید', 'publish' => 'منتشرشده', 'processing' => 'در حال پردازش', 'on-hold' => 'در انتظار بررسی', 'posted' => 'تحویل پست شده', 'completed' => 'تکمیل شده' );
		$label = isset( $names[ $status ] ) ? $names[ $status ] : $status;
		return '<span class="dsup-badge ' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	protected static function render_products( $tid ) {
		// حالت ویرایش
		$edit_pid = isset( $_GET['dsupp_edit'] ) ? absint( $_GET['dsupp_edit'] ) : 0;
		$own_ids  = array_map( function( $r ) { return $r['id']; }, self::supplier_products( $tid ) );

		echo '<div class="dsup-card"><h2>' . ( $edit_pid ? 'ویرایش محصول' : 'ثبت محصول جدید' ) . '</h2>';
		echo '<p class="dsup-muted" style="margin-top:0">محصول پس از <b>تأیید مرکز</b> در سایت نمایش داده می‌شود. قیمت نهایی فروش هم با فرمول مرکز روی قیمت شما محاسبه می‌شود.</p>';
		$vals = array( 'title' => '', 'price' => '', 'desc' => '' );
		if ( $edit_pid && in_array( $edit_pid, $own_ids, true ) ) {
			$p = function_exists( 'wc_get_product' ) ? wc_get_product( $edit_pid ) : null;
			$vals['title'] = ( $p && method_exists( $p, 'get_name' ) ) ? (string) $p->get_name() : '';
			$vals['price'] = self::product_price_cost( $edit_pid );
			$vals['desc']  = ( $p && method_exists( $p, 'get_description' ) ) ? (string) $p->get_description() : '';
		}
		echo '<form method="post">';
		wp_nonce_field( 'dsupp_panel' );
		self::tab_field();
		echo '<input type="hidden" name="dsupp_action" value="' . ( $edit_pid ? 'prod_edit' : 'prod_add' ) . '">';
		if ( $edit_pid ) { echo '<input type="hidden" name="dsupp_pid" value="' . (int) $edit_pid . '">'; }
		echo '<div class="dsup-row"><div><label>نام محصول</label><input type="text" name="dsupp_title" required value="' . esc_attr( $vals['title'] ) . '"></div>';
		echo '<div><label>قیمت تامین شما (تومان)</label><input type="text" inputmode="numeric" name="dsupp_price" required value="' . esc_attr( $vals['price'] ) . '" placeholder="مثلاً 850000"></div></div>';
		echo '<label>توضیح کوتاه (اختیاری)</label><textarea name="dsupp_desc" rows="3" placeholder="اندازه، رنگ، جنس، گارانتی...">' . esc_textarea( $vals['desc'] ) . '</textarea>';
		echo '<button type="submit" class="dsup-btn">' . ( $edit_pid ? 'ذخیره تغییرات' : 'ثبت محصول' ) . '</button>';
		if ( $edit_pid ) { echo ' <a class="dsup-btn gray" href="' . esc_url( add_query_arg( 'dsupp_tab', 'products' ) ) . '">انصراف</a>'; }
		echo '</form></div>';

		$rows = self::supplier_products( $tid );
		echo '<div class="dsup-card"><h2>محصولات شما (' . count( $rows ) . ')</h2>';
		if ( ! $rows ) {
			echo '<p class="dsup-muted">هنوز محصولی ثبت نکرده‌اید — از فرم بالا اولین محصولتان را بسازید.</p>';
		} else {
			echo '<table><tr><th>محصول</th><th>قیمت شما</th><th>قیمت فروش سایت</th><th>وضعیت</th><th>عملیات</th></tr>';
			foreach ( $rows as $r ) {
				$edit_u = add_query_arg( array( 'dsupp_tab' => 'products', 'dsupp_edit' => $r['id'] ) );
				echo '<tr><td><b>' . esc_html( $r['name'] ) . '</b></td>'
					. '<td>' . esc_html( self::money( (float) DSupp::norm_num( $r['cost'] ) ) ) . '</td>'
					. '<td>' . ( 'publish' === $r['status'] ? esc_html( self::money( (float) DSupp::norm_num( $r['final'] ) ) ) : '<span class="dsup-muted">پس از تأیید</span>' ) . '</td>'
					. '<td>' . self::status_badge( $r['status'] ) . '</td>'
					. '<td><a class="dsup-btn gray" href="' . esc_url( $edit_u ) . '">ویرایش</a>';
				if ( 'pending' === $r['status'] ) {
					echo ' <form method="post" style="display:inline" onsubmit="return confirm(\'این محصول حذف شود؟\');">'
						. wp_nonce_field( 'dsupp_panel', '_wpnonce', true, false )
						. '<input type="hidden" name="dsupp_action" value="prod_del"><input type="hidden" name="dsupp_pid" value="' . (int) $r['id'] . '">'
						. self::tab_field_str()
						. '<button type="submit" class="dsup-btn red">حذف</button></form>';
				}
				echo '</td></tr>';
			}
			echo '</table>';
		}
		echo '</div>';
	}

	/** نسخه رشته‌ای فیلد تب (برای داخل فرم‌های این‌لاین) */
	protected static function tab_field_str() {
		$tab = isset( $_GET['dsupp_tab'] ) ? sanitize_key( wp_unslash( $_GET['dsupp_tab'] ) ) : '';
		return $tab ? '<input type="hidden" name="dsupp_tab" value="' . esc_attr( $tab ) . '">' : '';
	}

	protected static function render_orders( $tid ) {
		$rows = self::supplier_orders( $tid );
		echo '<div class="dsup-card"><h2>سفارش‌های شما (' . count( $rows ) . ')</h2>';
		if ( ! $rows ) {
			echo '<p class="dsup-muted">سفارشی که شامل محصولات شما باشد هنوز ثبت نشده است.</p></div>';
			return;
		}
		echo '</div>';
		foreach ( $rows as $r ) {
			$o  = $r['order'];
			$st = method_exists( $o, 'get_status' ) ? (string) $o->get_status() : '';
			$id = method_exists( $o, 'get_id' ) ? (int) $o->get_id() : 0;
			echo '<div class="dsup-card">';
			echo '<div class="dsup-head"><h2 style="margin:0">سفارش #' . $id . '</h2>' . self::status_badge( $st ) . '</div>';

			// آیتم‌های همین تامین‌کننده (فقط داده خودش — بدون هیچ قیمت تموم‌شده فروشنده/مرکز)
			echo '<table><tr><th>محصول</th><th>تعداد</th><th>جمع خط</th></tr>';
			foreach ( $r['items'] as $it ) {
				$name = method_exists( $it, 'get_name' ) ? (string) $it->get_name() : '';
				$qty  = method_exists( $it, 'get_quantity' ) ? (int) $it->get_quantity() : 1;
				$tot  = method_exists( $it, 'get_total' ) ? (float) $it->get_total() : 0;
				echo '<tr><td>' . esc_html( $name ) . '</td><td>× ' . $qty . '</td><td>' . esc_html( self::money( $tot ) ) . '</td></tr>';
			}
			echo '<tr><td colspan="2" style="border-bottom:none"><b>جمع سهم شما</b></td><td style="border-bottom:none"><b>' . esc_html( self::money( $r['total'] ) ) . '</b></td></tr></table>';

			// نشانی ارسال (برای ارسال بسته توسط تامین‌کننده لازم است)
			$addr = self::order_shipping_text( $o );
			if ( $addr ) { echo '<div class="dsup-addr"><b>نشانی ارسال:</b> ' . esc_html( $addr ) . '</div>'; }

			if ( in_array( $st, array( 'processing', 'on-hold' ), true ) ) {
				echo '<form method="post" style="display:inline;margin-left:8px">';
				wp_nonce_field( 'dsupp_panel' );
				self::tab_field();
				echo '<input type="hidden" name="dsupp_action" value="order_accept"><input type="hidden" name="dsupp_oid" value="' . $id . '">';
				echo '<button type="submit" class="dsup-btn" onclick="return confirm(\'سفارش قبول و به پست تحویل داده شد؟\');">قبول سفارش (تحویل به پست)</button></form>';
			}
			if ( in_array( $st, array( 'processing', 'on-hold', 'posted' ), true ) ) {
				echo '<form method="post" style="display:inline-block;margin-top:8px">';
				wp_nonce_field( 'dsupp_panel' );
				self::tab_field();
				echo '<input type="hidden" name="dsupp_action" value="order_track"><input type="hidden" name="dsupp_oid" value="' . $id . '">';
				echo '<input type="text" name="dsupp_code" class="dsup-ltr" placeholder="کد رهگیری پست" style="width:210px;display:inline-block;margin:0 0 0 8px" required>';
				echo '<button type="submit" class="dsup-btn gray">ثبت کد رهگیری (= تکمیل سفارش)</button></form>';
			}
			if ( 'completed' === $st ) {
				$code = (string) get_post_meta( $id, '_dastyar_tracking_code', true );
				echo '<p class="dsup-muted">سفارش تکمیل شد' . ( $code ? ' — کد رهگیری: <span class="dsup-ltr">' . esc_html( $code ) . '</span>' : '' ) . '</p>';
			}
			echo '</div>';
		}
	}

	/** متن نشانی ارسال سفارش (با فالبک‌ها و گارد متدها) */
	protected static function order_shipping_text( $o ) {
		$g = function( $m ) use ( $o ) { return method_exists( $o, $m ) ? trim( (string) call_user_func( array( $o, $m ) ) ) : ''; };
		$fn = $g( 'get_shipping_first_name' ) . ' ' . $g( 'get_shipping_last_name' );
		if ( trim( $fn ) === '' ) { $fn = $g( 'get_billing_first_name' ) . ' ' . $g( 'get_billing_last_name' ); }
		$parts = array_filter( array(
			trim( $fn ),
			trim( $g( 'get_shipping_state' ) . ' ' . $g( 'get_shipping_city' ) ),
			$g( 'get_shipping_address_1' ),
			$g( 'get_shipping_address_2' ),
			$g( 'get_shipping_postcode' ) ? 'کدپستی: ' . $g( 'get_shipping_postcode' ) : '',
			$g( 'get_billing_phone' ) ? 'تلفن: ' . $g( 'get_billing_phone' ) : '',
		) );
		return implode( ' — ', array_filter( $parts ) );
	}

	protected static function render_rma( $tid ) {
		$rows = array_reverse( self::rma_rows( $tid ) );
		echo '<div class="dsup-card"><h2>گزارش‌های عودت و مرجوعی از مرکز (' . count( $rows ) . ')</h2>';
		if ( ! $rows ) {
			echo '<p class="dsup-muted">گزارش مرجوعی‌ای برای شما ثبت نشده است.</p>';
		} else {
			echo '<table><tr><th>تاریخ</th><th>سفارش</th><th>دلیل</th><th>مبلغ کسر</th><th>توضیح مرکز</th></tr>';
			foreach ( $rows as $r ) {
				echo '<tr><td>' . esc_html( date_i18n( 'Y/m/d', (int) $r['time'] ) ) . '</td>'
					. '<td>' . ( ! empty( $r['order'] ) ? '#' . (int) $r['order'] : '<span class="dsup-muted">—</span>' ) . '</td>'
					. '<td>' . esc_html( (string) $r['reason'] ) . '</td>'
					. '<td>' . ( (float) ( $r['amount'] ?? 0 ) > 0 ? esc_html( self::money( $r['amount'] ) ) : '<span class="dsup-muted">—</span>' ) . '</td>'
					. '<td>' . esc_html( (string) ( $r['note'] ?? '' ) ) . '</td></tr>';
			}
			echo '</table>';
		}
		echo '</div>';
	}

	protected static function render_finance( $tid ) {
		// ذخیره کارت/شبا
		echo '<div class="dsup-card"><h2>اطلاعات تسویه (کارت و شبا)</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'dsupp_panel' );
		self::tab_field();
		echo '<input type="hidden" name="dsupp_action" value="finance_save">';
		echo '<div class="dsup-row"><div><label>شماره کارت (۱۶ رقم)</label><input type="text" name="dsupp_card" class="dsup-ltr" maxlength="19" value="' . esc_attr( self::card_display( $tid ) ) . '" placeholder="6037-****-****-****"></div>';
		echo '<div><label>شماره شبا (IR + ۲۴ رقم)</label><input type="text" name="dsupp_sheba" class="dsup-ltr" value="' . esc_attr( self::sheba_display( $tid ) ) . '" placeholder="IR062960000000100324200001"></div></div>';
		echo '<button type="submit" class="dsup-btn">ذخیره اطلاعات بانکی</button></form>';
		$mask = self::card_masked( $tid );
		if ( $mask ) { echo '<p class="dsup-muted" style="margin:8px 0 0">کارت ثبت‌شده فعلی: <span class="dsup-ltr">' . esc_html( $mask ) . '</span></p>'; }
		echo '</div>';

		$pays = array_reverse( self::pay_rows( $tid ) );
		$sum  = self::payments_sum( $tid );
		echo '<div class="dsup-card"><h2>واریزی‌های مرکز به شما (' . count( $pays ) . ')</h2>';
		echo '<div class="dsup-grid" style="margin-bottom:12px"><div class="dsup-stat"><div class="t">مجموع واریزی‌ها</div><div class="v">' . esc_html( self::money( $sum ) ) . '</div></div></div>';
		if ( ! $pays ) {
			echo '<p class="dsup-muted">هنوز واریزی‌ای ثبت نشده است — پس از هر تسویه مرکز، سند آن این‌جا نمایش داده می‌شود.</p>';
		} else {
			echo '<table><tr><th>تاریخ</th><th>مبلغ</th><th>شماره سند/پیگیری</th><th>توضیح</th></tr>';
			foreach ( $pays as $r ) {
				echo '<tr><td>' . esc_html( date_i18n( 'Y/m/d', (int) $r['time'] ) ) . '</td>'
					. '<td><b>' . esc_html( self::money( $r['amt'] ?? 0 ) ) . '</b></td>'
					. '<td><span class="dsup-ltr">' . esc_html( (string) ( $r['ref'] ?? '' ) ) . '</span></td>'
					. '<td>' . esc_html( (string) ( $r['note'] ?? '' ) ) . '</td></tr>';
			}
			echo '</table>';
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------
	 * صفحات پیشخوان مرکز (قیمت‌گذاری / در انتظار تأیید / مرجوعی / واریزی‌ها)
	 * ---------------------------------------------------------------- */

	public static function admin_menu() {
		$parent = 'edit.php?post_type=product';
		add_submenu_page( $parent, 'قیمت‌گذاری تامین‌کننده‌ها', 'قیمت‌گذاری تامین‌کننده', self::CAP, 'dsupp-pricing', array( __CLASS__, 'page_pricing' ) );
		add_submenu_page( $parent, 'محصولات در انتظار تأیید تامین‌کننده‌ها', 'در انتظار تأیید (تامین)', self::CAP, 'dsupp-pending', array( __CLASS__, 'page_pending' ) );
		add_submenu_page( $parent, 'گزارش مرجوعی به تامین‌کننده‌ها', 'مرجوعی به تامین‌کننده', self::CAP, 'dsupp-returns', array( __CLASS__, 'page_returns' ) );
		add_submenu_page( $parent, 'واریزی‌ها و مستندات تامین‌کننده‌ها', 'واریزی‌ها (تامین)', self::CAP, 'dsupp-payments', array( __CLASS__, 'page_payments' ) );
		// v2.1.0 — منوی جدید «کاربران تامین‌کننده»: ساخت کاربر با نقش تامین‌کننده + پیوند به تامین‌کننده
		add_submenu_page( $parent, 'کاربران تامین‌کننده‌ها', 'کاربران تامین‌کننده', self::CAP, 'dsupp-users', array( __CLASS__, 'page_users' ) );
	}

	/** بازگشت به صفحه «کاربران تامین‌کننده» با پیام */
	protected static function back_users( $msg ) {
		$url = add_query_arg( array( 'page' => 'dsupp-users', 'dsupp_msg' => $msg ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/** صفحه پیشخوان مرکز: «کاربران تامین‌کننده» — ساخت کاربر با نقش تامین‌کننده + مشاهده پیوندها (v2.1.0) */
	public static function page_users() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'دسترسی غیرمجاز' ); }
		echo '<div class="wrap dsupa" dir="rtl">';
		self::admin_css();
		echo '<h1>کاربران تامین‌کننده (نقش: تامین‌کننده)</h1>';
		self::admin_msg( array(
			'u_added'  => 'کاربر ساخته شد — نام کاربری و گذرواژه همین کاربر، ورود پنل تامین‌کننده است.',
			'u_empty'  => 'نام کاربری الزامی است.',
			'u_dup'    => 'این نام کاربری قبلاً گرفته شده — یک نام دیگر انتخاب کنید.',
			'u_short'  => 'گذرواژه باید دست‌کم ۴ نویسه باشد.',
			'u_fail'   => 'انجام نشد — دوباره تلاش کنید.',
			'u_linked' => 'پیوند ثبت شد — حالا تامین‌کننده با همان نام کاربری/گذرواژه وارد پنل می‌شود.',
		) );

		// اعتبار تازه — فقط یک‌بار به مدیر نشان داده می‌شود (برای تحویل به تامین‌کننده)
		$fkey  = 'dsupp_newusr_' . get_current_user_id();
		$flash = get_transient( $fkey );
		if ( is_array( $flash ) && ! empty( $flash['user'] ) ) {
			echo '<div class="box" style="background:#eefbf5;border:1px solid #17a16d"><h2>اعتبار تازه ساخته‌شده — همین حالا کپی و تحویل دهید (فقط همین یک‌بار دیده می‌شود)</h2>';
			echo '<p>نام کاربری: <code class="ltr">' . esc_html( (string) $flash['user'] ) . '</code>';
			if ( ! empty( $flash['pass'] ) ) {
				echo ' — گذرواژه: <code class="ltr">' . esc_html( (string) $flash['pass'] ) . '</code>';
			} else {
				echo ' — (گذرواژه همان بود که خودتان نوشتید)';
			}
			echo '</p></div>';
			delete_transient( $fkey );
		}

		// فرم ساخت کاربر جدید
		$terms = DSupp::suppliers();
		echo '<div class="box"><h2>ساخت کاربر جدید با نقش «تامین‌کننده»</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'dsupp_user_add' );
		echo '<input type="hidden" name="action" value="dsupp_user_add">';
		echo '<p><label>نام نمایشی: <input type="text" name="dsupp_display" style="width:200px" placeholder="مثلاً: تامین‌کننده رضایی"></label> ';
		echo '<label>نام کاربری (ورود پنل): <input type="text" name="dsupp_new_user" class="ltr" style="width:180px" required placeholder="rezaei-supply"></label></p>';
		echo '<p><label>گذرواژه (خالی = خودکار): <input type="text" name="dsupp_new_pass" class="ltr" style="width:180px" placeholder="اگر ننویسید، سیستم می‌سازد"></label> ';
		echo '<label>ایمیل (اختیاری): <input type="email" name="dsupp_email" class="ltr" style="width:220px"></label></p>';
		if ( $terms ) {
			echo '<p><label>پیوند به تامین‌کننده: <select name="dsupp_tid"><option value="0">— بدون پیوند (بعداً از صفحه تامین‌کننده‌ها) —</option>';
			foreach ( $terms as $t ) { echo '<option value="' . (int) $t->term_id . '">' . esc_html( $t->name ) . '</option>'; }
			echo '</select></label></p>';
		}
		echo '<button type="submit" class="button button-primary">ساخت کاربر</button></form>';
		echo '<p class="muted">نقش «تامین‌کننده» فقط اجازه مشاهده دارد و اصلاً وارد پیشخوان وردپرس نمی‌شود — ورود فقط از صفحه پنل تامین‌کننده با همین نام کاربری/گذرواژه انجام می‌شود.</p>';
		echo '</div>';

		// v2.1.1 — پیوند دستی (ترمیمی): اگر ورود «پیوند نشده» گفت، از این‌جا یک‌بار تنظیم کنید
		$users_link = function_exists( 'get_users' ) ? get_users( array( 'role' => self::ROLE ) ) : array();
		if ( $users_link && $terms ) {
			echo '<div class="box" style="background:#fffdf5;border:1px dashed #e0c275"><h2>پیوند دستی کاربر به تامین‌کننده (ترمیمی)</h2>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'dsupp_user_link' );
			echo '<input type="hidden" name="action" value="dsupp_user_link">';
			echo '<p><label>کاربر: <select name="dsupp_link_uid">';
			foreach ( $users_link as $u ) {
				$lbl = (string) ( $u->user_login ?? '' ) . ( $u->display_name ? ' — ' . (string) $u->display_name : '' );
				echo '<option value="' . (int) $u->ID . '">' . esc_html( $lbl ) . '</option>';
			}
			echo '</select></label> ';
			echo '<label>تامین‌کننده: <select name="dsupp_link_tid">';
			foreach ( $terms as $t ) { echo '<option value="' . (int) $t->term_id . '">' . esc_html( $t->name ) . '</option>'; }
			echo '</select></label> ';
			echo '<button type="submit" class="button button-primary">ثبت پیوند</button></p></form>';
			echo '<p class="muted">اگر هنگام ورود به پنل پیام «حساب شما فعال است ولی هنوز به هیچ تامین‌کننده‌ای پیوند نشده» را دیدید، کاربر و تامین‌کننده را این‌جا انتخاب و «ثبت پیوند» را بزنید — پیوند در هر دو سمت ذخیره و ترمیم می‌شود. معمولاً پیوند خودکار هنگام ساخت/ویرایش هم ثبت می‌شود؛ این کادر برای اطمینان است.</p>';
			echo '</div>';
		}

		// فهرست کاربران + وضعیت پیوند
		$users = function_exists( 'get_users' ) ? get_users( array( 'role' => self::ROLE ) ) : array();
		echo '<div class="box"><h2>فهرست کاربران با نقش تامین‌کننده (' . count( (array) $users ) . ')</h2>';
		if ( ! $users ) { echo '<p class="muted">هنوز کاربری با نقش تامین‌کننده نساخته‌اید.</p>'; }
		else {
			echo '<table><tr><th>نام کاربری</th><th>نام نمایشی</th><th>ایمیل</th><th>پیوند به تامین‌کننده</th><th>وضعیت پیوند</th><th></th></tr>';
			foreach ( $users as $u ) {
				$uid  = (int) $u->ID;
				$tid  = self::tid_by_user_id( $uid );
				$link = $tid ? DSupp::term_name( $tid ) : '—';
				// (v2.1.1) وضعیت دو سمت پیوند برای تشخیص سریع
				$side_t = $tid ? ( self::linked_uid( $tid ) === $uid ) : false;
				$side_u = $tid ? ( function_exists( 'get_user_meta' ) && (int) get_user_meta( $uid, DSUPP_UMETA_TID, true ) === $tid ) : false;
				$stat   = $tid ? ( ( $side_t && $side_u ) ? 'سالم (دو سمت)' : 'نیمه — با ورود بعدی خودترمیم می‌شود' ) : '—';
				echo '<tr><td><code class="ltr">' . esc_html( (string) ( $u->user_login ?? '' ) ) . '</code> <span class="muted">#' . $uid . '</span></td>';
				echo '<td>' . esc_html( (string) ( $u->display_name ?? '' ) ) . '</td>';
				echo '<td class="ltr">' . esc_html( (string) ( $u->user_email ?? '' ) ) . '</td>';
				echo '<td><strong>' . esc_html( $link ) . '</strong></td>';
				echo '<td>' . esc_html( $stat ) . '</td>';
				echo '<td><a class="button" href="' . esc_url( admin_url( 'admin.php?page=dsupp-suppliers' . ( $tid ? '&edit=' . $tid : '' ) ) ) . '">' . ( $tid ? 'ویرایش پیوند' : 'پیوند دادن' ) . '</a></td></tr>';
			}
			echo '</table>';
		}
		echo '</div>';
		echo '</div>'; // .wrap
	}

	/** ساخت کاربر جدید با نقش تامین‌کننده (+ پیوند اختیاری به تامین‌کننده) */
	public static function handle_user_add() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'دسترسی غیرمجاز' ); }
		check_admin_referer( 'dsupp_user_add' );
		$in  = wp_unslash( $_POST ); // phpcs:ignore
		$name = isset( $in['dsupp_display'] ) ? sanitize_text_field( (string) $in['dsupp_display'] ) : '';
		$user = isset( $in['dsupp_new_user'] ) ? sanitize_user( (string) $in['dsupp_new_user'], true ) : '';
		$pass = isset( $in['dsupp_new_pass'] ) ? trim( (string) $in['dsupp_new_pass'] ) : '';
		$mail = isset( $in['dsupp_email'] ) ? sanitize_email( (string) $in['dsupp_email'] ) : '';
		$tid  = isset( $in['dsupp_tid'] ) ? absint( $in['dsupp_tid'] ) : 0;
		if ( '' === $user ) { self::back_users( 'u_empty' ); }
		if ( function_exists( 'username_exists' ) && username_exists( $user ) ) { self::back_users( 'u_dup' ); }
		$made = '';
		if ( '' === $pass ) {
			$pass = wp_generate_password( 10, false, false );
			$made = $pass;
		}
		if ( strlen( $pass ) < 4 ) { self::back_users( 'u_short' ); }
		$uid = function_exists( 'wp_insert_user' ) ? wp_insert_user( array(
			'user_login'   => $user,
			'user_pass'    => $pass,
			'user_email'   => ( is_email( $mail ) ? $mail : '' ),
			'display_name' => $name ? $name : $user,
			'role'         => self::ROLE,
		) ) : 0;
		if ( ! is_numeric( $uid ) || (int) $uid <= 0 ) { self::back_users( 'u_fail' ); }
		$uid = (int) $uid;
		if ( $tid ) { self::write_link( $uid, $tid ); } // پیوند دوطرفه کاربر ↔ تامین‌کننده
		set_transient( 'dsupp_newusr_' . get_current_user_id(), array( 'user' => $user, 'pass' => $made ), 120 );
		self::back_users( 'u_added' );
	}

	/** (v2.1.3) فعال/غیرفعال کردن ارجاع سفارش به تامین‌کننده (از صفحه تامین‌کننده‌ها) */
	public static function handle_referral_toggle() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'دسترسی غیرمجاز' ); }
		check_admin_referer( 'dsupp_referral_toggle' );
		$on = isset( $_POST['dsupp_referral'] ) && '1' === sanitize_key( wp_unslash( $_POST['dsupp_referral'] ) );
		update_option( self::OPT_REFERRAL, $on ? 'yes' : 'no' );
		$url = add_query_arg( array( 'page' => 'dsupp-suppliers', 'dsupp_msg' => $on ? 'ref_on' : 'ref_off' ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/** پیوند دستی (ترمیمی) کاربر ↔ تامین‌کننده — برای وقتی ورود «پیوند نشده» می‌دهد (v2.1.1) */
	public static function handle_user_link() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'دسترسی غیرمجاز' ); }
		check_admin_referer( 'dsupp_user_link' );
		$in  = wp_unslash( $_POST ); // phpcs:ignore
		$uid = isset( $in['dsupp_link_uid'] ) ? absint( $in['dsupp_link_uid'] ) : 0;
		$tid = isset( $in['dsupp_link_tid'] ) ? absint( $in['dsupp_link_tid'] ) : 0;
		if ( ! $uid || ! $tid ) { self::back_users( 'u_fail' ); }
		$wu = function_exists( 'get_userdata' ) ? get_userdata( $uid ) : false;
		if ( ! $wu || ! self::user_is_supplier( $wu ) ) { self::back_users( 'u_fail' ); }
		self::write_link( $uid, $tid );
		self::back_users( 'u_linked' );
	}

	protected static function admin_guard( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'دسترسی غیرمجاز' ); }
		check_admin_referer( $nonce_action );
	}

	protected static function admin_back( $page, $msg, $extra = array() ) {
		$args = array_merge( array( 'page' => $page, 'dsupp_msg' => $msg ), $extra );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	protected static function admin_css() {
		echo '<style>
		.dsupa{max-width:980px}
		.dsupa .box{background:#fff;border:1px solid #e0e3ea;border-radius:12px;padding:16px 18px;margin-bottom:16px}
		.dsupa h2{margin:0 0 12px;font-size:15px}
		.dsupa table{width:100%;border-collapse:collapse;font-size:13px}
		.dsupa th{background:#242536;color:#fff;padding:8px 10px;text-align:right;font-weight:normal}
		.dsupa td{padding:9px 10px;border-bottom:1px solid #eef0f4;vertical-align:middle}
		.dsupa tr:nth-child(even) td{background:#fafbfc}
		.dsupa .muted{color:#8a8fa3;font-size:12px}
		.dsupa .ltr{direction:ltr;unicode-bidi:isolate;display:inline-block}
		.dsupa input[type=text],.dsupa input[type=number]{padding:6px 10px;border:1px solid #dfe3ec;border-radius:8px}
		.dsupa select{padding:6px 8px;border:1px solid #dfe3ec;border-radius:8px}
		.dsupa .notice{margin:0 0 14px}
		.dsupa .badge{display:inline-block;background:#fff3d6;color:#8a6d00;border-radius:999px;padding:3px 12px;font-size:11.5px;font-weight:bold}
		</style>';
	}

	protected static function admin_msg( $map ) {
		$m = isset( $_GET['dsupp_msg'] ) ? sanitize_key( wp_unslash( $_GET['dsupp_msg'] ) ) : '';
		if ( $m && isset( $map[ $m ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( is_callable( $map[ $m ] ) ? call_user_func( $map[ $m ] ) : $map[ $m ] ) . '</p></div>';
		} elseif ( 'bad' === $m ) {
			echo '<div class="notice notice-error is-dismissible"><p>ورودی نامعتبر بود — بدون تغییر ماند.</p></div>';
		}
	}

	/* ---------- صفحه قیمت‌گذاری (مورد ۵) ---------- */

	public static function page_pricing() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'دسترسی غیرمجاز' ); }
		echo '<div class="wrap dsupa" dir="rtl">';
		self::admin_css();
		echo '<h1>قیمت‌گذاری تامین‌کننده‌ها</h1>';
		self::admin_msg( array(
			'saved'  => 'فرمول قیمت‌گذاری ذخیره شد.',
			'recalc' => function() { $n = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0; return 'قیمت ' . $n . ' محصول منتشرشده با فرمول جدید بازمحاسبه شد.'; },
		) );
		echo '<div class="box"><h2>فرمول چگونه کار می‌کند؟</h2><p class="muted" style="line-height:2;margin:0">برای هر تامین‌کننده یک فرمول تعیین می‌کنید؛ قیمت نهایی فروش سایت = فرمول روی «قیمت تامین‌کننده». «درصد روی هزینه» مثلاً ۱۵ یعنی ۱۵٪ سود؛ «مبلغ ثابت» یعنی مثلاً ۲۰٬۰۰۰ تومان اضافه؛ «ضریب» مثلاً ۱٫۲ یعنی قیمت ×۱٫۲. قیمت نهایی همیشه به نزدیک‌ترین ۱٬۰۰۰ تومانِ بالا گرد می‌شود و هنگام تأیید محصول یا تغییر قیمت، خودکار اعمال می‌شود. برای محصولات موجود بعد از تغییر فرمول، «بازمحاسبه» را بزنید.</p></div>';

		$terms = DSupp::suppliers();
		if ( ! $terms ) {
			echo '<div class="box"><p class="muted">هنوز تامین‌کننده‌ای ندارید — اول از صفحه «تامین‌کننده‌ها» بسازید.</p></div></div>';
			return;
		}
		foreach ( $terms as $t ) {
			$tid = (int) $t->term_id;
			$f   = self::formula( $tid );
			echo '<div class="box"><h2>' . esc_html( $t->name ) . ' <span class="muted">(' . (int) ( $t->count ?? 0 ) . ' محصول)</span></h2>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">';
			wp_nonce_field( 'dsupp_pricing_save_' . $tid );
			echo '<input type="hidden" name="action" value="dsupp_pricing_save"><input type="hidden" name="dsupp_tid" value="' . $tid . '">';
			echo '<select name="dsupp_ftype">'
				. '<option value="percent"' . selected( $f['type'], 'percent', false ) . '>درصد روی هزینه (٪)</option>'
				. '<option value="fixed"' . selected( $f['type'], 'fixed', false ) . '>مبلغ ثابت (تومان)</option>'
				. '<option value="multiplier"' . selected( $f['type'], 'multiplier', false ) . '>ضریب (×)</option>'
				. '</select>';
			echo '<input type="text" name="dsupp_fvalue" class="ltr" value="' . esc_attr( (string) ( $f['value'] ?: '' ) ) . '" placeholder="مثلاً 15" style="width:110px">';
			echo '<span class="muted">نمونه: هزینه ۱٬۰۰۰٬۰۰۰ → فروش <b>' . esc_html( self::money( self::price_final( 1000000, $f ) ) ) . '</b></span>';
			echo '<button type="submit" class="button button-primary">ذخیره فرمول</button>';
			echo '</form>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:10px">';
			wp_nonce_field( 'dsupp_recalc_' . $tid );
			echo '<input type="hidden" name="action" value="dsupp_recalc"><input type="hidden" name="dsupp_tid" value="' . $tid . '">';
			echo '<button type="submit" class="button" onclick="return confirm(\'قیمت همه محصولات منتشرشده این تامین‌کننده با فرمول بازمحاسبه شود؟\');">بازمحاسبه قیمت همه محصولات این تامین‌کننده</button>';
			echo '</form></div>';
		}
		echo '</div>';
	}

	public static function handle_pricing_save() {
		$tid = isset( $_POST['dsupp_tid'] ) ? absint( $_POST['dsupp_tid'] ) : 0;
		self::admin_guard( 'dsupp_pricing_save_' . $tid );
		$t = isset( $_POST['dsupp_ftype'] ) ? sanitize_key( wp_unslash( $_POST['dsupp_ftype'] ) ) : 'percent';
		if ( ! in_array( $t, array( 'percent', 'fixed', 'multiplier' ), true ) ) { $t = 'percent'; }
		$v = (float) DSupp::norm_num( wp_unslash( $_POST['dsupp_fvalue'] ?? '' ) );
		update_term_meta( $tid, DSUPP_META_FORMULA, array( 'type' => $t, 'value' => $v ) );
		self::admin_back( 'dsupp-pricing', 'saved' );
	}

	public static function handle_recalc() {
		$tid = isset( $_POST['dsupp_tid'] ) ? absint( $_POST['dsupp_tid'] ) : 0;
		self::admin_guard( 'dsupp_recalc_' . $tid );
		$n = self::recalc_supplier( $tid );
		self::admin_back( 'dsupp-pricing', 'recalc', array( 'n' => $n ) );
	}

	/* ---------- صفحه در انتظار تأیید (مورد ۳) ---------- */

	/** محصولات در انتظار بررسیِ متعلق به تامین‌کننده‌ها */
	public static function pending_products() {
		$list = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'limit' => -1, 'status' => 'pending', 'return' => 'objects' ) ) : array();
		$out  = array();
		foreach ( (array) $list as $p ) {
			$pid = ( is_object( $p ) && method_exists( $p, 'get_id' ) ) ? (int) $p->get_id() : 0;
			$tid = $pid ? DSupp::product_supplier_id( $pid ) : 0;
			if ( ! $pid || ! $tid ) { continue; }
			$out[] = array( 'id' => $pid, 'tid' => $tid, 'name' => method_exists( $p, 'get_name' ) ? (string) $p->get_name() : '' );
		}
		return $out;
	}

	public static function page_pending() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'دسترسی غیرمجاز' ); }
		echo '<div class="wrap dsupa" dir="rtl">';
		self::admin_css();
		echo '<h1>محصولات در انتظار تأیید تامین‌کننده‌ها</h1>';
		self::admin_msg( array( 'approved' => 'محصول تأیید و منتشر شد؛ قیمت نهایی هم اعمال گردید.', 'rejected' => 'محصول رد و به زباله‌دان منتقل شد.' ) );
		$rows = self::pending_products();
		echo '<div class="box">';
		if ( ! $rows ) {
			echo '<p class="muted">محصول در انتظاری از هیچ تامین‌کننده‌ای نیست.</p>';
		} else {
			echo '<table><tr><th>محصول</th><th>تامین‌کننده</th><th>قیمت تامین</th><th>قیمت نهایی (طبق فرمول)</th><th>عملیات</th></tr>';
			foreach ( $rows as $r ) {
				$cost  = (float) DSupp::norm_num( self::product_price_cost( $r['id'] ) );
				$final = self::price_final( $cost, self::formula( $r['tid'] ) );
				echo '<tr><td><b>' . esc_html( $r['name'] ) . '</b> <span class="muted">#' . (int) $r['id'] . '</span></td>'
					. '<td>' . esc_html( DSupp::term_name( $r['tid'] ) ) . '</td>'
					. '<td>' . esc_html( self::money( $cost ) ) . '</td>'
					. '<td><b>' . esc_html( self::money( $final ) ) . '</b></td>'
					. '<td>'
					. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'
					. wp_nonce_field( 'dsupp_approve_' . $r['id'], '_wpnonce', true, false )
					. '<input type="hidden" name="action" value="dsupp_approve"><input type="hidden" name="dsupp_pid" value="' . (int) $r['id'] . '">'
					. '<button type="submit" class="button button-primary button-small">تأیید و انتشار</button></form> '
					. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'
					. wp_nonce_field( 'dsupp_reject_' . $r['id'], '_wpnonce', true, false )
					. '<input type="hidden" name="action" value="dsupp_reject"><input type="hidden" name="dsupp_pid" value="' . (int) $r['id'] . '">'
					. '<button type="submit" class="button button-small" onclick="return confirm(\'این محصول رد و به زباله‌دان برود؟\');">رد</button></form>'
					. '</td></tr>';
			}
			echo '</table>';
		}
		echo '</div></div>';
	}

	public static function handle_approve() {
		$pid = isset( $_POST['dsupp_pid'] ) ? absint( $_POST['dsupp_pid'] ) : 0;
		self::admin_guard( 'dsupp_approve_' . $pid );
		$tid = DSupp::product_supplier_id( $pid );
		if ( $pid && $tid ) {
			wp_update_post( array( 'ID' => $pid, 'post_status' => 'publish' ) );
			self::apply_price( $pid, $tid );
		}
		self::admin_back( 'dsupp-pending', 'approved' );
	}

	public static function handle_reject() {
		$pid = isset( $_POST['dsupp_pid'] ) ? absint( $_POST['dsupp_pid'] ) : 0;
		self::admin_guard( 'dsupp_reject_' . $pid );
		if ( $pid ) { wp_trash_post( $pid ); }
		self::admin_back( 'dsupp-pending', 'rejected' );
	}

	/* ---------- صفحه مرجوعی (مورد ۷) ---------- */

	public static function page_returns() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'دسترسی غیرمجاز' ); }
		echo '<div class="wrap dsupa" dir="rtl">';
		self::admin_css();
		echo '<h1>گزارش عودت/مرجوعی به تامین‌کننده‌ها</h1>';
		self::admin_msg( array( 'rma_added' => 'گزارش برای پنل تامین‌کننده ارسال شد.', 'rma_deleted' => 'گزارش حذف شد.' ) );
		$terms = DSupp::suppliers();

		echo '<div class="box"><h2>ارسال گزارش جدید</h2>';
		if ( ! $terms ) {
			echo '<p class="muted">اول تامین‌کننده بسازید.</p>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'dsupp_rma_add' );
			echo '<input type="hidden" name="action" value="dsupp_rma_add">';
			echo '<p><label>تامین‌کننده: <select name="dsupp_tid">';
			foreach ( $terms as $t ) { echo '<option value="' . (int) $t->term_id . '">' . esc_html( $t->name ) . '</option>'; }
			echo '</select></label> ';
			echo '<label>شماره سفارش (اختیاری): <input type="number" name="dsupp_order" class="ltr" style="width:110px"></label> ';
			echo '<label>مبلغ کسر (تومان، اختیاری): <input type="text" name="dsupp_amount" class="ltr" style="width:130px"></label></p>';
			echo '<p><label>دلیل عودت: <input type="text" name="dsupp_reason" style="width:340px" required placeholder="مثلاً: کالا معیوب از انبار مشتری برگشت"></label></p>';
			echo '<p><label>توضیح برای تامین‌کننده: <input type="text" name="dsupp_note" style="width:340px" placeholder="جزئیات، زمان تحویل به انبار مرکز، ..."></label></p>';
			echo '<button type="submit" class="button button-primary">ارسال به پنل تامین‌کننده</button></form>';
		}
		echo '</div>';

		$rows = array_reverse( self::rma_rows() );
		echo '<div class="box"><h2>گزارش‌های ارسال‌شده (' . count( $rows ) . ')</h2>';
		if ( ! $rows ) { echo '<p class="muted">گزارشی ثبت نشده است.</p>'; }
		else {
			echo '<table><tr><th>تاریخ</th><th>تامین‌کننده</th><th>سفارش</th><th>دلیل</th><th>مبلغ کسر</th><th>توضیح</th><th></th></tr>';
			foreach ( $rows as $r ) {
				echo '<tr><td>' . esc_html( date_i18n( 'Y/m/d', (int) $r['time'] ) ) . '</td>'
					. '<td>' . esc_html( DSupp::term_name( (int) $r['tid'] ) ) . '</td>'
					. '<td>' . ( ! empty( $r['order'] ) ? '#' . (int) $r['order'] : '—' ) . '</td>'
					. '<td>' . esc_html( (string) $r['reason'] ) . '</td>'
					. '<td>' . ( (float) ( $r['amount'] ?? 0 ) > 0 ? esc_html( self::money( $r['amount'] ) ) : '—' ) . '</td>'
					. '<td>' . esc_html( (string) ( $r['note'] ?? '' ) ) . '</td>'
					. '<td><a class="button button-small" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dsupp_rma_del&id=' . rawurlencode( (string) $r['id'] ) ), 'dsupp_rma_del' ) ) . '" onclick="return confirm(\'حذف شود؟\');">حذف</a></td></tr>';
			}
			echo '</table>';
		}
		echo '</div></div>';
	}

	public static function handle_rma_add() {
		self::admin_guard( 'dsupp_rma_add' );
		$tid    = isset( $_POST['dsupp_tid'] ) ? absint( $_POST['dsupp_tid'] ) : 0;
		$reason = isset( $_POST['dsupp_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['dsupp_reason'] ) ) : '';
		if ( ! $tid || '' === $reason ) { self::admin_back( 'dsupp-returns', 'bad' ); }
		self::add_rma(
			$tid,
			isset( $_POST['dsupp_order'] ) ? absint( $_POST['dsupp_order'] ) : 0,
			$reason,
			wp_unslash( $_POST['dsupp_amount'] ?? '' ),
			isset( $_POST['dsupp_note'] ) ? sanitize_text_field( wp_unslash( $_POST['dsupp_note'] ) ) : ''
		);
		self::admin_back( 'dsupp-returns', 'rma_added' );
	}

	public static function handle_rma_del() {
		self::admin_guard( 'dsupp_rma_del' );
		self::del_rma( isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '' );
		self::admin_back( 'dsupp-returns', 'rma_deleted' );
	}

	/* ---------- صفحه واریزی‌ها (مورد ۱۰) ---------- */

	public static function page_payments() {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'دسترسی غیرمجاز' ); }
		echo '<div class="wrap dsupa" dir="rtl">';
		self::admin_css();
		echo '<h1>واریزی‌ها و مستندات تامین‌کننده‌ها</h1>';
		self::admin_msg( array( 'pay_added' => 'واریز ثبت شد و در پنل تامین‌کننده قابل مشاهده است.', 'pay_deleted' => 'واریز حذف شد.' ) );
		$terms = DSupp::suppliers();

		echo '<div class="box"><h2>ثبت واریز جدید</h2>';
		if ( ! $terms ) {
			echo '<p class="muted">اول تامین‌کننده بسازید.</p>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'dsupp_pay_add' );
			echo '<input type="hidden" name="action" value="dsupp_pay_add">';
			echo '<p><label>تامین‌کننده: <select name="dsupp_tid">';
			foreach ( $terms as $t ) {
				$extra = array();
				if ( self::card( $t->term_id ) ) { $extra[] = 'کارت ' . self::card_masked( $t->term_id ); }
				if ( self::sheba( $t->term_id ) ) { $extra[] = 'شبا دارد'; }
				echo '<option value="' . (int) $t->term_id . '">' . esc_html( $t->name ) . ( $extra ? ' — ' . esc_html( implode( '، ', $extra ) ) : '' ) . '</option>';
			}
			echo '</select></label> ';
			echo '<label>مبلغ (تومان): <input type="text" name="dsupp_amount" class="ltr" style="width:140px" required></label> ';
			echo '<label>شماره سند/پیگیری بانکی: <input type="text" name="dsupp_ref" class="ltr" style="width:170px"></label></p>';
			echo '<p><label>توضیح: <input type="text" name="dsupp_note" style="width:420px" placeholder="مثلاً: تسویه سفارش‌های تیرماه"></label></p>';
			echo '<button type="submit" class="button button-primary">ثبت واریز</button></form>';
			echo '<p class="muted">مقصد واریز (کارت/شبای هر تامین‌کننده) از صفحه «تامین‌کننده‌ها ← ویرایش» یا از خود پنلِ تامین‌کننده ثبت می‌شود و همین‌جا جلوی نام او دیده می‌شود.</p>';
		}
		echo '</div>';

		$rows = array_reverse( self::pay_rows() );
		echo '<div class="box"><h2>واریزی‌های ثبت‌شده (' . count( $rows ) . ')</h2>';
		if ( ! $rows ) { echo '<p class="muted">واریزی ثبت نشده است.</p>'; }
		else {
			echo '<table><tr><th>تاریخ</th><th>تامین‌کننده</th><th>مبلغ</th><th>سند/پیگیری</th><th>توضیح</th><th></th></tr>';
			foreach ( $rows as $r ) {
				echo '<tr><td>' . esc_html( date_i18n( 'Y/m/d', (int) $r['time'] ) ) . '</td>'
					. '<td>' . esc_html( DSupp::term_name( (int) $r['tid'] ) ) . '</td>'
					. '<td><b>' . esc_html( self::money( $r['amt'] ?? 0 ) ) . '</b></td>'
					. '<td><span class="ltr">' . esc_html( (string) ( $r['ref'] ?? '' ) ) . '</span></td>'
					. '<td>' . esc_html( (string) ( $r['note'] ?? '' ) ) . '</td>'
					. '<td><a class="button button-small" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dsupp_pay_del&id=' . rawurlencode( (string) $r['id'] ) ), 'dsupp_pay_del' ) ) . '" onclick="return confirm(\'حذف شود؟\');">حذف</a></td></tr>';
			}
			echo '</table>';
		}
		echo '</div></div>';
	}

	public static function handle_pay_add() {
		self::admin_guard( 'dsupp_pay_add' );
		$tid = isset( $_POST['dsupp_tid'] ) ? absint( $_POST['dsupp_tid'] ) : 0;
		$amt = (float) DSupp::norm_num( wp_unslash( $_POST['dsupp_amount'] ?? '' ) );
		if ( ! $tid || $amt <= 0 ) { self::admin_back( 'dsupp-payments', 'bad' ); }
		self::add_payment(
			$tid,
			$amt,
			isset( $_POST['dsupp_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['dsupp_ref'] ) ) : '',
			isset( $_POST['dsupp_note'] ) ? sanitize_text_field( wp_unslash( $_POST['dsupp_note'] ) ) : ''
		);
		self::admin_back( 'dsupp-payments', 'pay_added' );
	}

	public static function handle_pay_del() {
		self::admin_guard( 'dsupp_pay_del' );
		self::del_payment( isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '' );
		self::admin_back( 'dsupp-payments', 'pay_deleted' );
	}
}

// راه‌اندازی
DSupp::instance();
DSupp_Panel::boot();
