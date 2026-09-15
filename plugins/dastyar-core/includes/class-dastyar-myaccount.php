<?php
/**
 * پنل فروشنده روی WooCommerce My Account — بدون ساخت پنل جداگانه.
 * Endpoint های اختصاصی (مرتب‌شده بر اساس نیاز فروشنده):
 *  - dastyar-orders        → سفارش‌ها (منتقل‌شده + دستی)
 *  - dastyar-manual-order  → ثبت سفارش دستی (بدون نیاز به افزونه روی سایت فروشنده)
 *  - dastyar-invoices      → صورتحساب‌ها + تفکیک کالا/حمل
 *  - dastyar-wallet        → کیف پول
 *  - dastyar-suggest       → پیشنهاد محصول
 *  - dastyar-api           → اتصال API + دانلود افزونه اتصال‌دهنده
 *  - dastyar-tickets       → تیکت‌ها
 *
 * منوی پیش‌فرض ووکامرس برای فروشنده‌ها بازچینی می‌شود: «آدرس‌ها» و «دانلودها» حذف و
 * «جزئیات حساب» با فیلدهای اختصاصی فروشنده + بارگذاری لوگو تبدیل به «حساب کاربری» می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_MyAccount {

	public static function endpoints() {
		return array(
			'dastyar-orders'       => 'سفارش‌ها',
			'dastyar-manual-order' => 'ثبت سفارش دستی',
			'dastyar-invoices'     => 'صورتحساب‌ها',
			'dastyar-wallet'       => 'کیف پول',
			'dastyar-suggest'      => 'پیشنهاد محصول',
			'dastyar-api'          => 'اتصال API و دانلود افزونه',
			'dastyar-tickets'      => 'پشتیبانی (تیکت)',
		);
	}

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'add_endpoints' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ) );
		foreach ( array_keys( self::endpoints() ) as $endpoint ) {
			add_action( 'woocommerce_account_' . $endpoint . '_endpoint', array( $this, 'render_' . str_replace( array( 'dastyar-', '-' ), array( '', '_' ), $endpoint ) ) );
		}
		add_action( 'template_redirect', array( $this, 'handle_forms' ) );

		// حساب کاربری: فیلدهای فروشنده + انتقال بارگذاری لوگو به همین‌جا
		add_action( 'woocommerce_edit_account_form', array( $this, 'account_fields' ) );
		add_filter( 'woocommerce_edit_account_form_tag', array( $this, 'account_form_tag' ) );
		add_action( 'woocommerce_save_account_details', array( $this, 'save_account_fields' ) );

		// لوگوی فروشنده = آواتار کاربر (در سایت) + عکس پروفایل در پنل
		add_filter( 'get_avatar_url', array( $this, 'vendor_avatar_url' ), 10, 3 );
		add_action( 'woocommerce_account_dashboard', array( $this, 'dashboard_avatar' ) );

		// استایل برند پنل فروشنده
		add_action( 'wp_head', array( $this, 'account_css' ) );
	}

	/**
	 * آواتار کاربران فروشنده ← لوگوی انتخاب‌شده‌ی آن‌ها (همه‌جای وردپرس: دیدگاه‌ها، ابزارک‌ها، پیشخوان…)
	 */
	public function vendor_avatar_url( $url, $id_or_email, $args ) {
		$uid = 0;
		if ( is_numeric( $id_or_email ) ) {
			$uid = (int) $id_or_email;
		} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) && function_exists( 'get_user_by' ) ) {
			$user = get_user_by( 'email', $id_or_email );
			$uid  = $user ? (int) $user->ID : 0;
		} elseif ( $id_or_email instanceof WP_User ) {
			$uid = (int) $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Comment ) {
			$uid = (int) $id_or_email->user_id;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$uid = (int) $id_or_email->post_author;
		}
		if ( ! $uid ) {
			return $url;
		}
		$logo_id = (int) get_user_meta( $uid, '_dastyar_logo_id', true );
		if ( ! $logo_id ) {
			return $url;
		}
		$size = ( isset( $args['size'] ) && (int) $args['size'] > 96 ) ? 'medium' : 'thumbnail';
		$src  = wp_get_attachment_image_url( $logo_id, $size );
		return $src ? $src : $url;
	}

	/** عکس پروفایل (لوگوی فروشنده) در بالای داشبورد پنل My Account — کنار خوش‌آمدگویی */
	public function dashboard_avatar() {
		$uid = get_current_user_id();
		if ( ! $uid || ! Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			return;
		}
		$logo_id = (int) get_user_meta( $uid, '_dastyar_logo_id', true );
		if ( ! $logo_id ) {
			return;
		}
		$src = wp_get_attachment_image_url( $logo_id, 'thumbnail' );
		if ( ! $src ) {
			return;
		}
		$user = get_userdata( $uid );
		$shop = (string) get_user_meta( $uid, '_dastyar_shop_name', true );
		$name = $shop ?: ( $user ? $user->display_name : '' );
		printf(
			'<div class="dastyar-avatar-hello"><img src="%s" alt="لوگوی فروشگاه"><span class="dastyar-avatar-hello-text">سلام %s 👋<small>به پنل فروشندگی دستیار شاپ خوش آمدید.</small></span></div>',
			esc_url( $src ),
			esc_html( $name )
		);
	}

	public static function add_endpoints() {
		foreach ( array_keys( self::endpoints() ) as $endpoint ) {
			add_rewrite_endpoint( $endpoint, EP_ROOT | EP_PAGES );
		}
	}

	/**
	 * بازچینی منوی پنل بر اساس نیاز فروشنده:
	 * - کاربرِ در انتظار تأیید فقط «خروج» می‌بیند
	 * - فروشنده: حذف «سفارش‌ها/دانلودها/آدرس‌ها»ی پیش‌فرض ووکامرس + مرتب‌سازی بخش‌های دستیار
	 */
	public function menu_items( $items ) {
		$uid    = get_current_user_id();
		$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : __( 'خروج', 'woocommerce' );

		// در انتظار تأیید مدیر → هیچ بخشی فعال نیست
		if ( Dastyar_Registration::is_pending( $uid ) ) {
			return array( 'customer-logout' => $logout );
		}

		if ( ! Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			return $items; // مشتری عادی — بدون تغییر
		}

		$new = array();
		if ( isset( $items['dashboard'] ) ) {
			$new['dashboard'] = $items['dashboard'];
		}
		foreach ( self::endpoints() as $key => $label ) {
			$new[ $key ] = $label;
		}
		$new['edit-account']     = 'حساب کاربری';
		$new['customer-logout']  = $logout;
		return $new;
	}

	/* ------------------------------------------------------------------
	 * فرم‌ها
	 * ---------------------------------------------------------------- */

	public function handle_forms() {
		// (v1.7.3) پرداخت کیف‌پولِ سفارش دستی از داخل پاپ‌آپ صفحه محصول هم POST می‌شود؛ فقط امکان پردازش را می‌دهیم.
		$is_ctx = ( function_exists( 'is_account_page' ) && is_account_page() ) || is_singular( 'product' );
		if ( ! $is_ctx || ! is_user_logged_in() || empty( $_POST['dastyar_action'] ) ) {
			return;
		}
		$uid    = get_current_user_id();
		$action = sanitize_key( $_POST['dastyar_action'] );

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dastyar_' . $action ) ) {
			wc_add_notice( 'نشست نامعتبر است.', 'error' );
			return;
		}

		// پس از پرداخت کیف‌پول روی صفحه محصول، کاربر همان‌جا بماند تا نتیجه را ببیند
		$from_product = ! is_account_page();

		switch ( $action ) {

			case 'wallet_charge':
				// fallback بدون‌JS: سفارش شارژ مستقیم (بدون سبد) + رفتن به صفحه پرداخت سفارش
				$order = Dastyar_Payments::create_charge_order( $uid, (float) ( $_POST['amount'] ?? 0 ) );
				if ( is_wp_error( $order ) ) {
					wc_add_notice( $order->get_error_message(), 'error' );
					break;
				}
				wp_safe_redirect( $order->get_checkout_payment_url() );
				exit;

			case 'wallet_pay':
				$order = wc_get_order( (int) ( $_POST['order_id'] ?? 0 ) );
				if ( ! $order || (int) $order->get_customer_id() !== $uid ) {
					wc_add_notice( 'سفارش یافت نشد.', 'error' );
					break;
				}
				$result = Dastyar::instance()->wallet->pay_order_now( $order, $uid );
				if ( is_wp_error( $result ) ) {
					wc_add_notice( $result->get_error_message(), 'error' );
				} else {
					wc_add_notice( 'فاکتور از کیف پول پرداخت شد و سفارش در حال پردازش است.' );
				}
				break;

			case 'api_key':
				$key = Dastyar::instance()->vendors->generate_key( $uid );
				set_transient( 'dastyar_new_key_' . $uid, $key, 5 * MINUTE_IN_SECONDS );
				wc_add_notice( 'کلید API جدید ساخته شد. آن را کپی کنید؛ دوباره نمایش داده نمی‌شود.' );
				break;

			case 'suggest':
				$result = Dastyar_Suggestions::submit(
					$uid,
					sanitize_text_field( wp_unslash( $_POST['sugg_name'] ?? '' ) ),
					sanitize_textarea_field( wp_unslash( $_POST['sugg_desc'] ?? '' ) ),
					esc_url_raw( wp_unslash( $_POST['sugg_link'] ?? '' ) )
				);
				if ( is_wp_error( $result ) ) {
					wc_add_notice( $result->get_error_message(), 'error' );
				} else {
					wc_add_notice( 'پیشنهاد محصول شما ثبت شد و در صف بررسی قرار گرفت. نتیجه در همین بخش اعلام می‌شود.' );
				}
				break;

			case 'logo': // سازگاری با نسخه قبل — فرم لوگو به «حساب کاربری» منتقل شده است
				self::save_logo( $uid );
				break;

			case 'ticket_new':
				$ticket_id = Dastyar::instance()->tickets->create(
					$uid,
					sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
					sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) )
				);
				wc_add_notice( $ticket_id ? 'تیکت ثبت شد.' : 'عنوان و متن تیکت الزامی است.', $ticket_id ? 'success' : 'error' );
				break;

			case 'ticket_reply':
				Dastyar::instance()->tickets->reply(
					(int) ( $_POST['ticket_id'] ?? 0 ),
					$uid,
					sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) )
				);
				wc_add_notice( 'پاسخ ثبت شد.' );
				break;
		}

		if ( $from_product ) {
			// بازگشت به همان صفحه محصول (نوتیس ووکامرس بالای صفحه نمایش داده می‌شود)
			$back = wp_get_referer();
			wp_safe_redirect( $back ? $back : get_permalink( (int) get_queried_object_id() ) );
			exit;
		}
		wp_safe_redirect( wc_get_account_endpoint_url( $this->current_endpoint() ) );
		exit;
	}

	/** آپلود/حذف لوگوی فروشنده (برای لیبل آدرس) — الان از «حساب کاربری» صدا زده می‌شود */
	public static function save_logo( $uid ) {
		if ( ! empty( $_POST['remove_logo'] ) ) {
			delete_user_meta( $uid, '_dastyar_logo_id' );
			wc_add_notice( 'لوگو حذف شد.' );
			return;
		}
		if ( empty( $_FILES['dastyar_logo']['name'] ) ) {
			return; // فایل اجباری نیست (ذخیره حساب کاربری بدون تعویض لوگو)
		}
		$allowed = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$check   = wp_check_filetype( sanitize_file_name( $_FILES['dastyar_logo']['name'] ), $allowed );
		if ( ! $check['type'] ) {
			wc_add_notice( 'فقط فایل تصویری (JPG/PNG/WebP) مجاز است.', 'error' );
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( 'dastyar_logo', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			wc_add_notice( 'خطا در بارگذاری لوگو: ' . $attachment_id->get_error_message(), 'error' );
			return;
		}
		update_user_meta( $uid, '_dastyar_logo_id', (int) $attachment_id );
		wc_add_notice( 'لوگو با موفقیت ذخیره شد و روی لیبل‌های آدرس استفاده می‌شود.' );
	}

	protected function current_endpoint() {
		global $wp_query;
		foreach ( array_keys( self::endpoints() ) as $ep ) {
			if ( isset( $wp_query->query_vars[ $ep ] ) ) {
				return $ep;
			}
		}
		return 'dastyar-orders';
	}

	/** جمع کالاهای یک سفارش (بدون حمل) — با fallback برای سفارش‌های قدیمی */
	public static function goods_total( WC_Order $order ) {
		$goods = (float) $order->get_meta( '_dastyar_goods_total' );
		if ( $goods <= 0 ) {
			foreach ( $order->get_items() as $item ) {
				$goods += (float) $item->get_total();
			}
		}
		return $goods;
	}

	/* ------------------------------------------------------------------
	 * حساب کاربری — فیلدهای فروشنده + لوگو (انتقال‌یافته از بخش API)
	 * ---------------------------------------------------------------- */

	public function account_form_tag() {
		echo ' enctype="multipart/form-data"';
	}

	public function account_fields() {
		$uid = get_current_user_id();
		if ( ! Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			return;
		}

		echo '<fieldset class="dastyar-account-extra"><legend>اطلاعات فروشنده دستیار شاپ</legend>';

		woocommerce_form_field( 'dastyar_shop_name', array(
			'type'        => 'text',
			'label'       => 'نام فروشگاه',
			'class'       => array( 'form-row-first' ),
			'value'       => get_user_meta( $uid, '_dastyar_shop_name', true ),
		), get_user_meta( $uid, '_dastyar_shop_name', true ) );

		woocommerce_form_field( 'billing_phone', array(
			'type'        => 'tel',
			'label'       => 'موبایل',
			'class'       => array( 'form-row-last' ),
		), get_user_meta( $uid, 'billing_phone', true ) );

		woocommerce_form_field( 'dastyar_site_url', array(
			'type'        => 'url',
			'label'       => 'آدرس سایت فروشگاه',
			'description' => 'با اولین اتصال افزونه Connector به‌صورت خودکار هم به‌روزرسانی می‌شود.',
			'class'       => array( 'form-row-wide' ),
			'custom_attributes' => array( 'style' => 'direction:ltr' ),
		), get_user_meta( $uid, Dastyar_Vendors::SITE, true ) );

		echo '<div class="clear"></div>';

		// لوگوی فروشنده (برای چاپ روی لیبل آدرس)
		$logo_id  = (int) get_user_meta( $uid, '_dastyar_logo_id', true );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		echo '<div class="dastyar-logo-box"><p class="form-row form-row-wide" style="margin-bottom:6px"><strong>لوگوی فروشنده</strong><br><span class="description">این لوگو روی «لیبل آدرس» (برچسب A5 همراه مرسوله) چاپ می‌شود.</span></p>';
		if ( $logo_url ) {
			printf( '<p><img src="%s" alt="لوگوی فروشنده" style="max-height:80px;border:1px solid #ddd;padding:6px;background:#fff;border-radius:8px"></p>', esc_url( $logo_url ) );
			echo '<p><label><input type="checkbox" name="remove_logo" value="1"> حذف لوگوی فعلی</label></p>';
		}
		echo '<p class="form-row form-row-wide"><input type="file" name="dastyar_logo" accept=".jpg,.jpeg,.png,.webp"> <small class="description">JPG/PNG/WebP — برای تعویض، فایل جدید انتخاب و دکمه «ذخیره تغییرات» را بزنید</small></p>';
		echo '</div></fieldset>';
	}

	public function save_account_fields( $uid ) {
		if ( ! Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			return;
		}
		if ( isset( $_POST['dastyar_shop_name'] ) ) {
			update_user_meta( $uid, '_dastyar_shop_name', sanitize_text_field( wp_unslash( $_POST['dastyar_shop_name'] ) ) );
		}
		if ( isset( $_POST['billing_phone'] ) ) {
			update_user_meta( $uid, 'billing_phone', sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) );
		}
		if ( isset( $_POST['dastyar_site_url'] ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['dastyar_site_url'] ) );
			if ( $url ) {
				Dastyar::instance()->vendors->set_site_url( $uid, $url );
			} else {
				delete_user_meta( $uid, Dastyar_Vendors::SITE );
			}
		}
		// لوگو (تعویض یا حذف)
		if ( ! empty( $_POST['remove_logo'] ) || ! empty( $_FILES['dastyar_logo']['name'] ) ) {
			self::save_logo( $uid );
		}
	}

	/* ------------------------------------------------------------------
	 * رندر صفحات
	 * ---------------------------------------------------------------- */

	/** سفارش‌های فروشنده (منتقل‌شده از سایت فروشگاه + ثبت دستی) */
	public function render_orders() {
		$orders = wc_get_orders( array(
			'customer_id' => get_current_user_id(),
			'limit'       => 50,
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );
		echo '<h3>سفارش‌ها</h3>';
		if ( ! $orders ) {
			echo '<p>سفارشی ثبت نشده است.</p>';
			return;
		}
		echo '<table class="shop_table shop_table_responsive"><thead><tr>';
		echo '<th>سفارش</th><th>منشأ</th><th>تاریخ</th><th>وضعیت</th><th>مبلغ</th><th>کد رهگیری</th>';
		echo '</tr></thead><tbody>';
		foreach ( $orders as $order ) {
			$remote = $order->get_meta( '_dastyar_remote_order_number' );
			$manual = (bool) $order->get_meta( '_dastyar_manual_order' );
			$track  = $order->get_meta( '_dastyar_tracking_code' );
			$origin = $remote
				? 'فروشگاه شما #' . esc_html( $remote )
				: ( $manual ? '<span style="background:#17a16d;color:#fff;border-radius:12px;padding:2px 10px;font-size:11px">ثبت دستی</span>' : '—' );
			printf(
				'<tr><td>#%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $order->get_order_number() ),
				wp_kses_post( $origin ),
				esc_html( Dastyar_Jalali::dt( $order->get_date_created(), 'Y/m/d' ) ),
				esc_html( wc_get_order_status_name( $order->get_status() ) ),
				wp_kses_post( $order->get_formatted_order_total() ),
				$track ? esc_html( $track ) : '—'
			);
		}
		echo '</tbody></table>';
	}

	/** ثبت سفارش دستی (بدون نیاز به افزونه روی سایت فروشنده) */
	public function render_manual_order() {
		Dastyar::instance()->manual_order->render_account( get_current_user_id() );
	}

	/**
	 * صورتحساب‌ها = سفارش‌های WooCommerce با وضعیت «در انتظار پرداخت»
	 * مبلغ قابل پرداخت = جمع کالاها (قیمت تامین) + هزینه حمل مرکزی — با جدول تفکیک
	 */
	public function render_invoices() {
		$uid    = get_current_user_id();
		$orders = wc_get_orders( array(
			'customer_id' => $uid,
			'status'      => array( 'pending' ),
			'limit'       => 50,
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );
		echo '<h3>صورتحساب‌ها</h3>';
		if ( ! $orders ) {
			echo '<p>صورتحساب پرداخت‌نشده‌ای ندارید.</p>';
			return;
		}
		echo '<table class="shop_table shop_table_responsive"><thead><tr>';
		echo '<th>سفارش</th><th>تاریخ</th><th>جمع کالاها</th><th>حمل‌ونقل</th><th>مبلغ قابل پرداخت</th><th>پرداخت</th>';
		echo '</tr></thead><tbody>';
		foreach ( $orders as $order ) {
			echo '<tr>';
			printf( '<td>#%s</td>', esc_html( $order->get_order_number() ) );
			printf( '<td>%s</td>', esc_html( Dastyar_Jalali::dt( $order->get_date_created(), 'Y/m/d' ) ) );
			printf( '<td>%s</td>', wp_kses_post( wc_price( self::goods_total( $order ) ) ) );
			printf( '<td>%s</td>', wp_kses_post( wc_price( (float) $order->get_shipping_total() ) ) );
			printf( '<td><strong>%s</strong></td>', wp_kses_post( $order->get_formatted_order_total() ) );
			echo '<td>';
			printf(
				'<a class="button" href="%s">پرداخت از درگاه</a> ',
				esc_url( $order->get_checkout_payment_url() )
			);
			echo '<form method="post" style="display:inline">';
			wp_nonce_field( 'dastyar_wallet_pay' );
			printf( '<input type="hidden" name="dastyar_action" value="wallet_pay"><input type="hidden" name="order_id" value="%d">', (int) $order->get_id() );
			echo '<button type="submit" class="button alt">پرداخت از کیف پول</button></form>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">مبلغ هر صورتحساب = جمع کالاها به قیمت تامین + هزینه حمل‌ونقل طبق تعرفه دستیار شاپ.</p>';
	}

	/** کیف پول: کارت موجودی + طرح‌های شارژ پیشنهادی + شارژ مستقیم با پاپ‌آپ انتخاب درگاه */
	public function render_wallet() {
		$uid     = get_current_user_id();
		$balance = Dastyar::instance()->wallet->get_balance( $uid );

		// ─── کارت موجودی (Hero) ───
		echo '<div class="dastyar-wallet-hero">';
		echo '<div class="dastyar-wallet-hero-icon">👛</div>';
		echo '<div class="dastyar-wallet-hero-info">';
		echo '<span class="dastyar-wallet-hero-label">موجودی کیف پول شما</span>';
		printf( '<strong class="dastyar-wallet-hero-amount">%s</strong>', wp_kses_post( wc_price( $balance ) ) );
		echo '<span class="dastyar-wallet-hero-desc">با شارژ کیف پول، سفارش‌های مشتریانتان به‌صورت خودکار و بدون معطلی پرداخت می‌شوند — دیگر نگران از دست رفتن فروش نباشید.</span>';
		echo '</div></div>';

		// ─── طرح‌های پیشنهادی شارژ ───
		$plans = apply_filters( 'dastyar_wallet_charge_plans', array(
			array( 'amount' => 10000000, 'title' => 'طرح پایه',    'desc' => 'مناسب شروع و تست فروشگاه' ),
			array( 'amount' => 25000000, 'title' => 'طرح رشد',     'desc' => 'پوشش خیال‌راحت سفارش‌های ماه', 'popular' => true ),
			array( 'amount' => 50000000, 'title' => 'طرح حرفه‌ای', 'desc' => 'برای فروشگاه‌های پرفروش و بدون توقف' ),
		) );

		echo '<h3 class="dastyar-plans-heading">شارژ سریع با طرح‌های پیشنهادی</h3>';
		echo '<div class="dastyar-plan-grid">';
		foreach ( $plans as $plan ) {
			$amount  = (float) ( $plan['amount'] ?? 0 );
			$popular = ! empty( $plan['popular'] );
			if ( $amount <= 0 ) {
				continue;
			}
			printf( '<button type="submit" name="amount" value="%s" form="dastyar-charge-form" formnovalidate class="dastyar-plan-card%s">', esc_attr( $amount ), $popular ? ' popular' : '' );
			if ( $popular ) {
				echo '<span class="dastyar-plan-badge">★ پرطرفدارترین</span>';
			}
			printf( '<span class="dastyar-plan-title">%s</span>', esc_html( (string) ( $plan['title'] ?? '' ) ) );
			printf( '<span class="dastyar-plan-amount">%s</span>', wp_kses_post( wc_price( $amount ) ) );
			if ( ! empty( $plan['desc'] ) ) {
				printf( '<span class="dastyar-plan-desc">%s</span>', esc_html( (string) $plan['desc'] ) );
			}
			echo '<span class="dastyar-plan-cta">شارژ و پرداخت ←</span>';
			echo '</button>';
		}
		echo '</div>';

		// ─── شارژ با مبلغ دلخواه ───
		echo '<p class="dastyar-wallet-or"><span>یا مبلغ دلخواه خود را وارد کنید</span></p>';
		echo '<form method="post" class="dastyar-wallet-charge dastyar-custom-charge" id="dastyar-charge-form">';
		wp_nonce_field( 'dastyar_wallet_charge' );
		echo '<input type="hidden" name="dastyar_action" value="wallet_charge">';
		printf( '<input type="number" min="0" step="any" name="amount" id="dastyar-charge-amount" placeholder="مبلغ دلخواه (%s)"> ', esc_html( get_woocommerce_currency_symbol() ) );
		echo '<button type="submit" class="button alt dastyar-btn-green">شارژ کیف پول</button></form>';
		echo '<p class="description">پس از انتخاب طرح یا وارد کردن مبلغ، مستقیم به درگاه پرداخت (بدون سبد خرید و صفحه تسویه) منتقل می‌شوید.</p>';

		$txns = Dastyar::instance()->wallet->get_transactions( $uid, 50 );
		if ( $txns ) {
			echo '<h4>تراکنش‌ها</h4><table class="shop_table"><thead><tr><th>تاریخ</th><th>نوع</th><th>مبلغ</th><th>مانده</th><th>شرح</th></tr></thead><tbody>';
			foreach ( $txns as $t ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( Dastyar_Jalali::dt( (string) $t->created_at ) ),
					esc_html( 'credit' === $t->type ? 'واریز' : 'برداشت' ),
					wp_kses_post( wc_price( (float) $t->amount ) ),
					wp_kses_post( wc_price( (float) $t->balance_after ) ),
					esc_html( $t->description )
				);
			}
			echo '</tbody></table>';
		}
	}

	/** پیشنهاد محصول */
	public function render_suggest() {
		Dastyar::instance()->suggestions->render_account( get_current_user_id() );
	}

	/** اتصال API + راهنمای نصب افزونه اتصال‌دهنده */
	public function render_api() {
		$uid     = get_current_user_id();
		$vendors = Dastyar::instance()->vendors;
		$new_key = get_transient( 'dastyar_new_key_' . $uid );
		if ( $new_key ) {
			delete_transient( 'dastyar_new_key_' . $uid );
		}

		echo '<h3>اتصال API</h3>';

		if ( $new_key ) {
			printf( '<div class="woocommerce-message">کلید API جدید شما (فقط یک‌بار نمایش داده می‌شود):<br><code style="direction:ltr;display:inline-block">%s</code></div>', esc_html( $new_key ) );
		}

		printf( '<p>کلید فعلی: <code>%s</code></p>', esc_html( $vendors->hint( $uid ) ?: 'هنوز ساخته نشده است' ) );
		printf( '<p>آدرس سایت فروشگاه شما: <code>%s</code></p>', esc_html( $vendors->site_url( $uid ) ?: 'پس از اولین اتصال به‌صورت خودکار ثبت می‌شود' ) );

		echo '<form method="post">';
		wp_nonce_field( 'dastyar_api_key' );
		echo '<input type="hidden" name="dastyar_action" value="api_key">';
		echo '<button type="submit" class="button">تولید کلید جدید (ابطال کلید قبلی)</button></form>';

		echo '<h4>اطلاعات اتصال (برای تنظیمات افزونه Connector)</h4><ul style="list-style:disc inside">';
		printf( '<li>آدرس سایت مرکزی: <code style="font-size:13px">%s</code> ← <strong>همین آدرس</strong> را در تنظیمات افزونه Connector وارد کنید.</li>', esc_html( untrailingslashit( home_url() ) ) );
		echo '<li>احراز هویت با هدر <code>X-Dastyar-Key</code> انجام می‌شود (کلید همین صفحه).</li>';
		echo '</ul>';
		echo '<details style="margin-bottom:14px"><summary class="description" style="cursor:pointer">جزئیات فنی برای توسعه‌دهندگان</summary>';
		printf( '<p class="description" style="margin-top:6px">آدرس پایه REST API: <code>%s</code></p>', esc_html( home_url( '/wp-json/dastyar/v1' ) ) );
		echo '</details>';

		// راهنمای نصب افزونه اتصال‌دهنده + دکمه دانلود
		echo '<div class="dastyar-plugin-guide">';
		echo '<h3 style="margin-top:0">افزونه اتصال‌دهنده فروشنده (Dastyar Connector)</h3>';
		echo '<ol style="line-height:2.2;margin:0">';
		echo '<li>افزونه «Dastyar Connector» را از صفحه دانلود دریافت کنید.</li>';
		echo '<li>روی سایت فروشگاه خود: پیشخوان ← افزونه‌ها ← افزودن ← بارگذاری افزونه ← نصب و فعال‌سازی (ووکامرس باید فعال باشد).</li>';
		echo '<li>از منوی «دستیار شاپ ← تنظیمات دستیار» آدرس سایت مرکزی و کلید API بالا را وارد و ذخیره کنید.</li>';
		echo '<li>محصولات را از صفحه «محصولات دستیار» به‌صورت تکی یا دسته‌جمعی به فروشگاه خود اضافه کنید.</li>';
		echo '</ol>';
		$dl = class_exists( 'Dastyar_Panel_Settings' ) ? (string) Dastyar_Panel_Settings::get( 'connector_url' ) : '';
		if ( ! $dl ) {
			$dl = home_url( '/plugin/' ); // خودکار از دامنه فعلی سایت
		}
		printf( '<p style="margin:14px 0 0"><a class="dastyar-download-btn" href="%s" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em;height:1em;vertical-align:-.15em;margin-left:.35em"><path d="M12 3v11m0 0l-4-4m4 4l4-4M4 20h16"/></svg>دانلود افزونه اتصال‌دهنده</a></p>', esc_url( $dl ) );
		echo '</div>';
	}

	/** تیکت‌ها */
	public function render_tickets() {
		Dastyar::instance()->tickets->render_account();
	}

	/* ------------------------------------------------------------------
	 * استایل برند پنل فروشنده (رنگ سازمانی #17a16d) — فقط صفحات My Account
	 * ---------------------------------------------------------------- */

	public function account_css() {
		// (v1.7.3) صفحه تکی محصول هم نیاز دارد: ویزارد «ثبت سفارش دستی» داخل پاپ‌آپ محصول رندر می‌شود.
		// همه سلکتورهای این بلوک به dastyar-* اسکوپ شده‌اند، پس خروجی روی صفحه محصول بی‌خطر است.
		$on_product = is_singular( 'product' );
		if ( ! function_exists( 'is_account_page' ) || ( ! is_account_page() && ! $on_product ) ) {
			return;
		}
		?>
		<style>
		/* ناوبری پنل فروشنده */
		.woocommerce-MyAccount-navigation ul li.is-active a,
		.woocommerce-MyAccount-navigation ul li a:hover{background:#17a16d!important;color:#fff!important}
		.woocommerce-MyAccount-navigation ul li{border-radius:10px;overflow:hidden}
		.woocommerce-MyAccount-navigation ul li.is-active{box-shadow:0 4px 14px rgba(23,161,109,.25)}
		.woocommerce-MyAccount-navigation ul li a{transition:.2s}
		/* تیترهای بخش‌ها */
		.woocommerce-MyAccount-content h3{color:#12875c}
		/* کارت‌های بخش «ثبت سفارش دستی» */
		.dastyar-card{background:#fff;border:1px solid #e4efe9;border-radius:14px;padding:18px 20px;margin:16px 0;box-shadow:0 2px 10px rgba(23,161,109,.07)}
		.dastyar-card h4{margin:0 0 12px;color:#12875c}
		.dastyar-grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
		@media (max-width:640px){.dastyar-grid2{grid-template-columns:1fr}}
		.dastyar-rec-summary{background:#f0faf5;border:1px solid #cdeee0;border-radius:10px;padding:10px 14px;margin-bottom:14px;color:#146c4b}
		.dastyar-btn-green{background:#17a16d!important;color:#fff!important;border-color:#17a16d!important}
		.dastyar-btn-green:hover{background:#12875c!important;color:#fff!important}
		.dastyar-btn-big{padding:12px 30px!important;font-size:16px!important;border-radius:10px!important}
		.dastyar-btn-remove{color:#c0392b!important}
		.dastyar-search-form{display:flex;gap:8px;margin-bottom:16px}
		.dastyar-search-form .input-text{flex:1}
		.dastyar-products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px}
		.dastyar-product-card{border:1px solid #e4efe9;border-radius:12px;padding:12px;text-align:center;background:#fcfefd;transition:.2s}
		.dastyar-product-card:hover{box-shadow:0 6px 18px rgba(23,161,109,.15);transform:translateY(-2px)}
		.dastyar-product-card img{width:100%;height:120px;object-fit:cover;border-radius:8px;margin-bottom:8px}
		.dastyar-product-name{font-size:13px;font-weight:700;min-height:38px;margin-bottom:8px}
		.dastyar-product-price{color:#12875c;font-weight:700;margin-bottom:8px}
		.dastyar-var-select{width:100%;margin-bottom:8px;font-size:12px}
		.dastyar-add-row{display:flex;gap:6px;justify-content:center}
		.dastyar-qty{width:60px!important}
		.dastyar-pagination{margin-top:14px}
		.dastyar-goods-total{background:#f0faf5;border-radius:10px;padding:10px 14px;color:#146c4b}
		/* ── گرافیک ویزارد ثبت سفارش دستی (v1.6.0) ── */
		.dastyar-manual-form .woocommerce-form-row{display:flex;flex-direction:column;margin-bottom:16px}
		.dastyar-manual-form label{font-weight:700;font-size:13px;color:#242536;margin-bottom:6px}
		.dastyar-manual-form label .required{color:#dc2626}
		.dastyar-manual-form .input-text,
		.dastyar-manual-form select{border:1.5px solid #d3e7dc!important;border-radius:12px!important;padding:11px 14px!important;font-family:inherit;font-size:14px;background:#fbfefd;transition:.2s;box-shadow:inset 0 1px 3px rgba(23,161,109,.04)}
		.dastyar-manual-form .input-text:focus,
		.dastyar-manual-form select:focus{border-color:#17a16d!important;outline:none;box-shadow:0 0 0 3px rgba(23,161,109,.14);background:#fff}
		.dastyar-manual-form button[type="submit"],
		#dastyar-wizard .dastyar-step-nav .button{border-radius:12px!important;padding:12px 26px!important;font-weight:800!important;font-size:14px!important;font-family:inherit;line-height:1.5}
		#dastyar-rec-save{background:#17a16d!important;border-color:#17a16d!important;color:#fff!important}
		#dastyar-rec-save:hover{background:#12875c!important}
		#dastyar-wizard .dastyar-next{background:#17a16d!important;border-color:#17a16d!important;color:#fff!important;font-size:15px!important;padding:12px 30px!important}
		#dastyar-wizard .dastyar-next:hover{background:#12875c!important;color:#fff!important}
		#dastyar-wizard .dastyar-prev{background:#fff!important;border:1.5px solid #d3e7dc!important;color:#43524b!important}
		#dastyar-wizard .dastyar-prev:hover{border-color:#17a16d!important;color:#12875c!important}
		.dastyar-search-form .input-text{border:1.5px solid #d3e7dc;border-radius:12px;padding:11px 14px;font-size:14px}
		.dastyar-search-form .button{border-radius:12px;padding:11px 20px;font-weight:700}
		.dastyar-more-hint-txt{font-size:12.5px;color:#8a8d9d}
		#dastyar-mo-search-btn{background:#17a16d!important;border-color:#17a16d!important;color:#fff!important}
		/* آواتار فروشنده (لوگو) در داشبورد پنل */
		.dastyar-avatar-hello{display:flex;align-items:center;gap:14px;background:#f4fbf8;border:1.5px solid #cdeee0;border-radius:16px;padding:14px 18px;margin-bottom:20px;box-shadow:0 3px 12px rgba(23,161,109,.08)}
		.dastyar-avatar-hello img{width:60px;height:60px;border-radius:50%;object-fit:cover;border:2.5px solid #17a16d;background:#fff;padding:2px}
		.dastyar-avatar-hello-text{font-size:16.5px;font-weight:800;color:#0f5132;line-height:1.7}
		.dastyar-avatar-hello-text small{display:block;font-size:12px;font-weight:400;color:#6b857a}
		/* باکس لوگو در حساب کاربری */
		.dastyar-account-extra{border:1px solid #e4efe9;border-radius:14px;padding:16px 18px;margin:22px 0}
		.dastyar-account-extra legend{color:#12875c;font-weight:800;padding:0 10px}
		.dastyar-logo-box{background:#f8fbf9;border-radius:10px;padding:12px 14px;margin-top:8px}
		/* راهنمای دانلود افزونه */
		.dastyar-plugin-guide{background:linear-gradient(135deg,#f2fbf7,#e9f7f0);border:1px solid #cdeee0;border-radius:14px;padding:18px 20px;margin-top:22px}
		.dastyar-plugin-guide h3{color:#12875c}
		.dastyar-download-btn{display:inline-block;background:#17a16d;color:#fff!important;padding:13px 28px;border-radius:10px;font-weight:800;font-size:15px;text-decoration:none;transition:.2s;box-shadow:0 4px 14px rgba(23,161,109,.3)}
		.dastyar-download-btn:hover{background:#12875c;transform:translateY(-2px)}
		.dastyar-alert-inline{background:#fdf0f0;border:1px solid #f3caca;color:#a52828;border-radius:10px;padding:10px 12px}
		/* لیست درگاه‌های پرداخت (Dastyar_Payments::gateway_picker_html) — استفاده «ثبت سفارش دستی» */
		.dastyar-gw-list{display:grid;gap:10px}
		.dastyar-gw-form{margin:0!important}
		.dastyar-gw-btn{display:flex;align-items:center;gap:10px;width:100%;background:#f7fbf9;border:1.5px solid #ddefe6;border-radius:12px;padding:12px 14px;cursor:pointer;font-size:14px;font-weight:700;color:#123;transition:.2s;font-family:inherit}
		.dastyar-gw-btn:hover{border-color:#17a16d;background:#f0faf5;transform:translateY(-1px)}
		.dastyar-gw-icon img{max-height:26px;vertical-align:middle}
		.dastyar-gw-title{flex:1;text-align:right}
		.dastyar-gw-go{color:#17a16d;font-size:12px;white-space:nowrap}
		.dastyar-gw-empty{text-align:center;color:#a52828}
		.dastyar-wallet-balance{background:#f0faf5;border:1px solid #cdeee0;border-radius:10px;padding:10px 14px;display:inline-block}
		/* کیف پول — کارت موجودی و طرح‌های پیشنهادی */
		.dastyar-wallet-hero{display:flex;align-items:center;gap:18px;background:linear-gradient(135deg,#17a16d,#0f7a52);border-radius:18px;padding:24px 26px;color:#fff;box-shadow:0 10px 30px rgba(23,161,109,.32);margin:8px 0 26px}
		.dastyar-wallet-hero-icon{font-size:44px;line-height:1;filter:drop-shadow(0 4px 8px rgba(0,0,0,.2))}
		.dastyar-wallet-hero-info{display:flex;flex-direction:column;gap:5px}
		.dastyar-wallet-hero-label{font-size:13px;opacity:.9;font-weight:600}
		.dastyar-wallet-hero-amount{font-size:31px;font-weight:900;letter-spacing:.3px}
		.dastyar-wallet-hero-desc{font-size:12.5px;opacity:.88;line-height:1.9;max-width:520px}
		.dastyar-plans-heading{margin:0 0 14px;color:#12875c;font-size:19px}
		.dastyar-plan-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:8px}
		@media (max-width:760px){.dastyar-plan-grid{grid-template-columns:1fr}}
		.dastyar-plan-card{position:relative;display:flex;flex-direction:column;align-items:center;gap:7px;background:#fff;border:2px solid #e4efe9;border-radius:16px;padding:22px 16px 16px;cursor:pointer;font-family:inherit;text-align:center}
		.dastyar-plan-card.popular{border-color:#17a16d;background:linear-gradient(180deg,#f2fcf7,#fff);box-shadow:0 8px 24px rgba(23,161,109,.2)}
		.dastyar-plan-badge{position:absolute;top:-13px;right:50%;transform:translateX(50%);background:#17a16d;color:#fff;font-size:11px;font-weight:800;border-radius:12px;padding:3px 12px;white-space:nowrap;box-shadow:0 4px 10px rgba(23,161,109,.35)}
		.dastyar-plan-title{font-size:15px;font-weight:800;color:#0f3d2c}
		.dastyar-plan-amount{font-size:22px;font-weight:900;color:#12875c}
		.dastyar-plan-desc{font-size:12px;color:#6b7f76;line-height:1.8;min-height:34px}
		.dastyar-plan-cta{margin-top:4px;background:#17a16d;color:#fff;border-radius:9px;padding:7px 18px;font-size:13px;font-weight:800}
		.dastyar-plan-card.popular .dastyar-plan-cta{background:#0f7a52}
		.dastyar-wallet-or{display:flex;align-items:center;gap:12px;color:#8aa39a;font-size:13px;margin:20px 0 12px}
		.dastyar-wallet-or:before,.dastyar-wallet-or:after{content:"";flex:1;height:1px;background:#dcebe4}
		.dastyar-custom-charge{display:flex;gap:10px;align-items:center}
		.dastyar-custom-charge input[type="number"]{max-width:260px;border:1.5px solid #cdeee0;border-radius:10px;padding:10px 14px}
		.dastyar-custom-charge input[type="number"]:focus{border-color:#17a16d;outline:none}
		/* ویزارد ثبت سفارش دستی */
		.dastyar-steps{display:flex;gap:8px;margin:18px 0;flex-wrap:wrap}
		.dastyar-step{flex:1;min-width:120px;background:#fff;border:1.5px solid #e4efe9;border-radius:12px;padding:10px 8px;cursor:pointer;text-align:center;font-family:inherit;transition:.2s;color:#666}
		.dastyar-step-num{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#eef4f1;color:#12875c;font-weight:800;margin-bottom:4px}
		.dastyar-step-label{display:block;font-size:12px;font-weight:700}
		.dastyar-step.active{border-color:#17a16d;box-shadow:0 4px 14px rgba(23,161,109,.18);color:#12875c}
		.dastyar-step.active .dastyar-step-num{background:#17a16d;color:#fff}
		.dastyar-step.done{opacity:.85}
		.dastyar-step.done .dastyar-step-num{background:#cdeee0;color:#12875c}
		#dastyar-wizard .dastyar-pane{display:none}
		#dastyar-wizard .dastyar-pane.active{display:block;animation:dastyarFade .3s ease}
		@keyframes dastyarFade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
		.dastyar-step-nav{margin:16px 0 0;display:flex;gap:10px}
		#dastyar-rec-msg{color:#12875c;font-size:12px}
		.dastyar-ship-list{display:grid;gap:8px;margin-top:10px}
		.dastyar-ship-option{display:flex;align-items:center;gap:10px;background:#f7fbf9;border:1.5px solid #ddefe6;border-radius:10px;padding:10px 14px;cursor:pointer}
		.dastyar-ship-option:hover{border-color:#17a16d}
		.dastyar-ship-title{flex:1;font-weight:700}
		.dastyar-ship-cost{color:#12875c;font-weight:700}
		.dastyar-done-box{background:#f0faf5;border:1px solid #cdeee0;border-radius:14px;padding:18px;margin-top:14px}
		.dastyar-done-box h4{margin-top:0;color:#12875c}
		.dastyar-wallet-inline{display:inline-block;margin:0 0 10px}
		.dastyar-btn-ghost-g{background:#eef4f1!important;color:#12875c!important;border-color:#cdeee0!important;font-weight:700}
		.dastyar-toast{position:fixed;bottom:24px;right:50%;transform:translateX(50%) translateY(20px);background:#12382b;color:#fff;padding:12px 22px;border-radius:12px;font-size:14px;z-index:100001;opacity:0;transition:.35s;box-shadow:0 8px 24px rgba(0,0,0,.25)}
		.dastyar-toast.show{opacity:1;transform:translateX(50%) translateY(0)}
		.dastyar-toast.error{background:#a52828}
		</style>
		<?php
	}
}
