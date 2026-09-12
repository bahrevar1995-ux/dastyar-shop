<?php
/**
 * سیستم عودت و مرجوعی (RMA) — سمت سایت فروشنده.
 *
 * اصل بنیادی: رابط اصلی با مشتری نهایی، فروشنده است؛ دستیار شاپ هیچ ارتباط مستقیمی
 * با مشتری نهایی ندارد. جریان کامل:
 *
 * ۱) مشتری در «برگه عودت و مرجوعی» سایت فروشنده (شورتکد [dastyarc_rma]) گزارش ثبت می‌کند
 *    یا فروشنده خودش (برای تماس تلفنی/واتساپ) به‌صورت دستی از پیشخوان ثبت می‌کند → وضعیت «ثبت شده»
 * ۲) فروشنده از منوی «دستیار شاپ ← عودت و مرجوعی» درخواست را بازبینی و با دکمه
 *    «ارسال برای دستیار شاپ» از طریق REST API به مرکز می‌فرستد
 * ۳) هر تغییر وضعیت در مرکز با وب‌هوک امضادار (رویداد rma.status) همین‌جا اعمال می‌شود
 *    + دکمه «به‌روزرسانی وضعیت» + سینک دوره‌ای خودکار (جبران خطا)
 * ۴) نتیجه نهایی (تعویض/لغو با بازپرداخت کیف پول/رد) در همین پنل + یادداشت سفارش دیده می‌شود
 *
 * بدون هیچ سیستم سفارش/کاربر جدید — درخواست‌ها CPT استاندارد وردپرس متصل به WooCommerce Order هستند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Rma {

	const POST_TYPE   = 'dastyarc_rma';
	const PAGE_OPTION = 'dastyarc_rma_page_id';

	/** متاهای درخواست */
	const M_ORDER    = '_dastyarc_rma_order';          // شناسه سفارش محلی WooCommerce
	const M_PHONE    = '_dastyarc_rma_phone';          // شماره تماس ثبت‌کننده (برای استعلام مشتری)
	const M_ITEMS    = '_dastyarc_rma_items';          // [{local_pid, local_vid, remote_pid, remote_var, qty, name}]
	const M_REASON   = '_dastyarc_rma_reason';
	const M_CUSTOMER = '_dastyarc_rma_customer_note';
	const M_VENDOR_N = '_dastyarc_rma_vendor_note';
	const M_ATTACH   = '_dastyarc_rma_attachments';    // [attachment_id]
	const M_STATUS   = '_dastyarc_rma_status';
	const M_REMOTE   = '_dastyarc_rma_remote_id';      // شناسه RMA در مرکز (پس از ارسال)
	const M_ADMIN_N  = '_dastyarc_rma_admin_note';     // توضیحات اپراتور مرکز
	const M_FINAL    = '_dastyarc_rma_final';          // exchange | cancel | reject
	const M_FAULT    = '_dastyarc_rma_fault';          // dastyar | customer
	const M_CREDIT   = '_dastyarc_rma_credit';         // مبلغ بازگشتی به کیف پول در مرکز
	const M_RETURN   = '_dastyarc_rma_return_address'; // آدرس مقصد مرجوعی (انبار)
	const M_SOURCE   = '_dastyarc_rma_source';         // customer | vendor
	const M_HISTORY  = '_dastyarc_rma_history';        // [{status,label,note,at}]
	const M_SYNCED   = '_dastyarc_rma_synced_at';

	/** وضعیت‌های ترمینال — دیگر Pull نمی‌شوند */
	const TERMINAL = array( 'exchanged', 'cancelled_refunded', 'rejected', 'closed' );

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_page' ), 20 );
		add_shortcode( 'dastyarc_rma', array( $this, 'shortcode' ) );

		// اقدام‌های پیشخوان فروشنده
		add_action( 'admin_post_dastyarc_rma_send', array( $this, 'handle_send' ) );
		add_action( 'admin_post_dastyarc_rma_refresh', array( $this, 'handle_refresh' ) );
		add_action( 'admin_post_dastyarc_rma_note', array( $this, 'handle_note' ) );
		add_action( 'admin_post_dastyarc_rma_manual', array( $this, 'handle_manual' ) );

		// سینک دوره‌ای درخواست‌های باز (روی همان کرون موجود Connector)
		add_action( 'dastyarc_reconcile_tick', array( __CLASS__, 'reconcile_rmas' ) );
	}

	public static function register_cpt() {
		register_post_type( self::POST_TYPE, array(
			'label'           => 'عودت و مرجوعی',
			'public'          => false,
			'show_ui'         => false, // مدیریت از صفحه اختصاصی «دستیار شاپ ← عودت و مرجوعی»
			'supports'        => array( 'title', 'author' ),
			'capability_type' => 'post',
		) );
	}

	/* ------------------------------------------------------------------
	 * وضعیت‌ها / برچسب‌ها
	 * ---------------------------------------------------------------- */

	/** «ثبت شده» فقط محلی است (قبل از ارسال به دستیار)؛ بقیه یکی‌به‌یک با وضعیت‌های مرکز */
	public static function statuses() {
		return array(
			'registered'         => 'ثبت شده',
			'submitted'          => 'ارسال شده برای دستیار شاپ',
			'reviewing'          => 'در حال بررسی',
			'need_info'          => 'نیاز به اطلاعات بیشتر',
			'approved'           => 'تایید شد',
			'rejected'           => 'رد شد',
			'await_item'         => 'منتظر ارسال کالا',
			'item_received'      => 'کالا دریافت شد',
			'final_review'       => 'بررسی نهایی',
			'exchanged'          => 'تعویض شد',
			'cancelled_refunded' => 'لغو و بازپرداخت شد',
			'closed'             => 'بسته شد',
		);
	}

	public static function status_label( $slug ) {
		$all = self::statuses();
		return $all[ $slug ] ?? $all['registered'];
	}

	public static function status_color( $slug ) {
		$map = array(
			'registered'         => '#555',
			'submitted'          => '#b78a00',
			'reviewing'          => '#2271b1',
			'need_info'          => '#b7400a',
			'approved'           => '#17a16d',
			'rejected'           => '#c0392b',
			'await_item'         => '#7f54b3',
			'item_received'      => '#2271b1',
			'final_review'       => '#2271b1',
			'exchanged'          => '#17a16d',
			'cancelled_refunded' => '#17a16d',
			'closed'             => '#555',
		);
		return $map[ $slug ] ?? '#666';
	}

	public static function finals() {
		return array(
			'exchange' => 'تعویض کالا',
			'cancel'   => 'لغو سفارش',
			'reject'   => 'رد مرجوعی',
		);
	}

	public static function final_label( $slug ) {
		$f = self::finals();
		return $f[ $slug ] ?? '';
	}

	public static function fault_label( $slug ) {
		return 'dastyar' === $slug ? 'دستیار شاپ' : ( 'customer' === $slug ? 'مشتری' : '' );
	}

	/** دلایل پیش‌فرض فرم عودت */
	public static function reasons() {
		return array(
			'کالا معیوب یا خراب است',
			'کالای اشتباه ارسال شده است',
			'سایز/رنگ با سفارش مطابقت ندارد',
			'مغایرت کالا با توضیحات و تصاویر',
			'انصراف از خرید',
			'دلیل دیگر',
		);
	}

	/** قوانین عودت — متن فقط توسط «سایت مرکزی» تعیین می‌شود و از آنجا همگام می‌آید (v1.4.0) */
	public static function rules() {
		$rules = trim( (string) get_option( 'dastyarc_rma_rules', '' ) );
		if ( '' === $rules ) {
			$rules = 'کالا تا ۴۸ ساعت پس از تحویل، در صورت سالم بودن و نداشتن خسارت، قابل عودت است. پس از ثبت درخواست، تیم ما آن را بررسی و نتیجه را از همین برگه اطلاع‌رسانی می‌کنیم. در صورت تایید عودت، کالا باید به آدرس اعلام‌شده ارسال شود تا بررسی نهایی انجام گردد. هزینه ارسال عودتی در صورت مقصر بودن فروشگاه بر عهده فروشگاه است.';
		}
		return $rules;
	}

	/**
	 * اعمال قوانین رسیده از مرکز (وب‌هوک rma.rules یا پاسخ GET /rules).
	 * قوانین فقط در مرکز نوشته می‌شود؛ این سمت فقط اعمال و کش می‌کند.
	 */
	public static function apply_rules( $data ) {
		$data = is_array( $data ) ? $data : array();
		if ( array_key_exists( 'rules', $data ) ) {
			update_option( 'dastyarc_rma_rules', wp_kses_post( (string) $data['rules'] ) );
			update_option( 'dastyarc_rules_synced_at', current_time( 'mysql' ) );
		}
		if ( ! empty( $data['return_address'] ) ) {
			update_option( 'dastyarc_return_address', sanitize_textarea_field( (string) $data['return_address'] ) );
		}
	}

	/** دریافت پولی قوانین از مرکز (جبران وب‌هوک ازدست‌رفته — روی کرون reconcile و دکمه دستی) */
	public static function sync_rules_from_central() {
		if ( ! DastyarC_Client::configured() ) {
			return false;
		}
		$res = DastyarC_Client::rules();
		if ( is_wp_error( $res ) ) {
			DastyarC::log( 'Rules sync failed: ' . $res->get_error_message(), 'warning' );
			return false;
		}
		self::apply_rules( $res );
		return true;
	}

	/**
	 * حباب اعلان برای منوی «عودت و مرجوعی» = گزارش‌های تازه‌ای که هنوز برای مرکز ارسال نشده‌اند
	 * (وضعیت «ثبت شده» — ثبت‌شده توسط مشتری یا خودِ فروشنده).
	 */
	public static function pending_bubble_count() {
		return count( get_posts( array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_key'       => self::M_STATUS,
			'meta_value'     => 'registered',
		) ) );
	}

	/** ساخت برگه عمومی «عودت و مرجوعی» (Fail-Safe — مثل برگه پیگیری سفارش) */
	public static function ensure_page() {
		try {
			if ( ! function_exists( 'wp_insert_post' ) ) {
				return;
			}
			$page_id = (int) get_option( self::PAGE_OPTION );
			if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
				return;
			}
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_title'   => 'عودت و مرجوعی کالا',
					'post_name'    => 'returns',
					'post_status'  => 'publish',
					'post_content' => '[dastyarc_rma]',
				),
				true
			);
			if ( $page_id && ! is_wp_error( $page_id ) ) {
				update_option( self::PAGE_OPTION, $page_id );
			}
		} catch ( \Throwable $e ) {
			DastyarC::log( 'RMA page creation failed: ' . $e->getMessage(), 'error' );
		}
	}

	/* ------------------------------------------------------------------
	 * اقلام دستیار یک سفارش + نگاشت به شناسه‌های مرکز (همان منطق ارسال سفارش)
	 * ---------------------------------------------------------------- */

	/** @return array ردیف‌های قابل عودت (فقط اقلام دستیار) */
	public static function remote_items_for_order( WC_Order $order ) {
		$out = array();
		foreach ( $order->get_items() as $item ) {
			$var_id = (int) $item->get_variation_id();
			$pid    = (int) $item->get_product_id();

			$remote_var  = $var_id ? (int) get_post_meta( $var_id, '_dastyar_remote_var_id', true ) : 0;
			$remote_prod = 0;
			if ( $var_id ) {
				$remote_prod = (int) get_post_meta( $var_id, '_dastyar_remote_product_id', true );
			}
			if ( ! $remote_prod ) {
				$remote_prod = $pid ? (int) get_post_meta( $pid, '_dastyar_remote_id', true ) : 0;
			}
			if ( ! $remote_prod ) {
				continue;
			}

			$out[] = array(
				'item'       => $item,
				'key'        => $pid . '_' . $var_id,
				'local_pid'  => $pid,
				'local_vid'  => $var_id,
				'remote_pid' => $remote_prod,
				'remote_var' => $remote_var,
				'qty'        => (int) $item->get_quantity(),
				'name'       => $item->get_name(),
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * ثبت درخواست محلی (مشتری از برگه عمومی / فروشنده از پیشخوان — تماس تلفنی و غیره)
	 * ---------------------------------------------------------------- */

	/**
	 * @param WC_Order $order
	 * @param array    $items  نقشه key(pid_vid) → تعداد
	 * @param string   $source customer | vendor
	 * @return int|WP_Error
	 */
	public static function create_local( $order, array $items, $reason, $customer_note, $vendor_note, $source, $phone = '', $files_key = '' ) {
		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'dastyarc_rma_no_order', 'سفارش معتبر نیست.' );
		}
		if ( ! $order->get_meta( '_dastyarc_remote_order_id' ) ) {
			return new WP_Error( 'dastyarc_rma_not_sent', 'این سفارش هنوز به دستیار شاپ ارسال نشده است؛ ابتدا سفارش باید به مرکز ارسال شده باشد.' );
		}

		$map = array();
		foreach ( self::remote_items_for_order( $order ) as $ri ) {
			$map[ $ri['key'] ] = $ri;
		}
		$sel = array();
		foreach ( $items as $key => $qty ) {
			$key = sanitize_text_field( (string) $key );
			if ( ! isset( $map[ $key ] ) ) {
				continue;
			}
			$ri    = $map[ $key ];
			$sel[] = array(
				'local_pid'  => $ri['local_pid'],
				'local_vid'  => $ri['local_vid'],
				'remote_pid' => $ri['remote_pid'],
				'remote_var' => $ri['remote_var'],
				'qty'        => max( 1, min( (int) $qty, $ri['qty'] ) ),
				'name'       => $ri['name'],
			);
		}
		if ( ! $sel ) {
			return new WP_Error( 'dastyarc_rma_no_items', 'هیچ قلم دستیاری برای عودت انتخاب نشده است.' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => 'در حال ثبت…',
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		wp_update_post( array(
			'ID'         => $post_id,
			'post_title' => sprintf( 'عودت #%d — سفارش #%s', $post_id, $order->get_order_number() ),
		) );

		update_post_meta( $post_id, self::M_ORDER, $order->get_id() );
		update_post_meta( $post_id, self::M_ITEMS, $sel );
		update_post_meta( $post_id, self::M_REASON, $reason );
		update_post_meta( $post_id, self::M_CUSTOMER, $customer_note );
		update_post_meta( $post_id, self::M_VENDOR_N, $vendor_note );
		update_post_meta( $post_id, self::M_STATUS, 'registered' );
		update_post_meta( $post_id, self::M_SOURCE, 'customer' === $source ? 'customer' : 'vendor' );
		if ( $phone ) {
			update_post_meta( $post_id, self::M_PHONE, $phone );
		}

		// ضمیمه تصویری (اختیاری — یک فایل)
		if ( $files_key && ! empty( $_FILES[ $files_key ]['tmp_name'] ) ) {
			$aid = self::store_upload( $files_key, $post_id );
			if ( $aid ) {
				update_post_meta( $post_id, self::M_ATTACH, array( $aid ) );
			}
		}

		self::history( $post_id, 'registered' );
		$order->add_order_note( sprintf(
			'گزارش عودت/مرجوعی #%d ثبت شد (%s — %d قلم).',
			$post_id,
			'customer' === $source ? 'از فرم سایت توسط مشتری' : 'ثبت دستی توسط فروشنده',
			count( $sel )
		) );
		do_action( 'dastyarc_rma_created', $post_id );
		return $post_id;
	}

	/** آپلود امن تصویر ضمیمه (حداکثر ۳ مگابایت — فقط jpg/png/webp) */
	protected static function store_upload( $files_key, $post_id ) {
		$file = $_FILES[ $files_key ];
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) {
			return 0;
		}
		if ( ! empty( $file['size'] ) && (int) $file['size'] > 3 * 1024 * 1024 ) {
			return 0;
		}
		$check = wp_check_filetype( (string) ( $file['name'] ?? '' ) );
		if ( empty( $check['type'] ) || ! in_array( $check['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$aid = media_handle_upload( $files_key, $post_id );
		return is_wp_error( $aid ) ? 0 : (int) $aid;
	}

	/** خواندن امن متا آرایه‌ای: در نبود متا، (array)'' یک عنصر خالی می‌سازد ← همیشه آرایه واقعی برگردان */
	protected static function meta_arr( $post_id, $key ) {
		$v = get_post_meta( $post_id, $key, true );
		return is_array( $v ) ? $v : array();
	}

	/** افزودن رکورد به تاریخچه وضعیت */
	protected static function history( $rma_id, $status, $note = '' ) {
		$h   = self::meta_arr( $rma_id, self::M_HISTORY );
		$h[] = array(
			'status' => $status,
			'label'  => self::status_label( $status ),
			'note'   => $note,
			'at'     => current_time( 'mysql' ),
		);
		update_post_meta( $rma_id, self::M_HISTORY, $h );
	}

	/* ------------------------------------------------------------------
	 * ارسال برای دستیار شاپ (Push به مرکز) + به‌روزرسانی (Pull)
	 * ---------------------------------------------------------------- */

	/** @return bool|WP_Error */
	public static function send_to_central( $rma_id ) {
		$rma_id = (int) $rma_id;
		$order  = wc_get_order( (int) get_post_meta( $rma_id, self::M_ORDER, true ) );
		if ( ! $order ) {
			return new WP_Error( 'dastyarc_rma_no_order', 'سفارش متناظر یافت نشد.' );
		}
		if ( (int) get_post_meta( $rma_id, self::M_REMOTE, true ) ) {
			return new WP_Error( 'dastyarc_rma_sent', 'این درخواست قبلاً برای دستیار شاپ ارسال شده است.' );
		}

		$items = array();
		foreach ( self::meta_arr( $rma_id, self::M_ITEMS ) as $it ) {
			$items[] = array(
				'product_id' => (int) ( $it['remote_var'] ? $it['remote_var'] : $it['remote_pid'] ),
				'qty'        => (int) $it['qty'],
			);
		}

		// ضمیمه‌ها به‌صورت base64 (هر کدام حداکثر ۳ مگابایت)
		$attachments = array();
		foreach ( self::meta_arr( $rma_id, self::M_ATTACH ) as $aid ) {
			$path = get_attached_file( (int) $aid );
			if ( ! $path || ! file_exists( $path ) || filesize( $path ) > 3 * 1024 * 1024 ) {
				continue;
			}
			$data = file_get_contents( $path ); // phpcs:ignore
			if ( ! $data ) {
				continue;
			}
			$attachments[] = array(
				'data' => base64_encode( $data ),
				'mime' => get_post_mime_type( (int) $aid ) ?: 'image/jpeg',
			);
		}

		$res = DastyarC_Client::rma_create( array(
			'remote_order_id'     => $order->get_id(),
			'remote_order_number' => $order->get_order_number(),
			'local_rma_id'        => $rma_id,
			'reason'              => (string) get_post_meta( $rma_id, self::M_REASON, true ),
			'customer_note'       => (string) get_post_meta( $rma_id, self::M_CUSTOMER, true ),
			'vendor_note'         => (string) get_post_meta( $rma_id, self::M_VENDOR_N, true ),
			'items'               => $items,
			'attachments'         => $attachments,
		) );

		if ( is_wp_error( $res ) ) {
			DastyarC::log( sprintf( 'RMA #%d send failed: %s', $rma_id, $res->get_error_message() ), 'error' );
			return $res;
		}

		update_post_meta( $rma_id, self::M_REMOTE, (int) $res['rma_id'] );
		update_post_meta( $rma_id, self::M_STATUS, 'submitted' );
		update_post_meta( $rma_id, self::M_SYNCED, current_time( 'mysql' ) );
		self::history( $rma_id, 'submitted' );
		$order->add_order_note( sprintf( 'گزارش عودت #%d برای دستیار شاپ ارسال شد (RMA مرکزی #%d).', $rma_id, (int) $res['rma_id'] ) );
		return true;
	}

	/**
	 * اعمال پاسخ مرکز روی درخواست محلی (از وب‌هوک rma.status یا Pull دستی/دوره‌ای)
	 * @param int   $local_rma_id شناسه CPT محلی
	 * @param array $data         دیتای مرکز (status, admin_note, final, fault, credit, return_address, rma_id)
	 */
	public static function apply_remote( $local_rma_id, $data ) {
		$local_rma_id = (int) $local_rma_id;
		$post         = get_post( $local_rma_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$old    = (string) get_post_meta( $local_rma_id, self::M_STATUS, true );
		$status = sanitize_key( (string) ( $data['status'] ?? '' ) );
		if ( $status && isset( self::statuses()[ $status ] ) ) {
			update_post_meta( $local_rma_id, self::M_STATUS, $status );
		}
		if ( ! empty( $data['rma_id'] ) ) {
			update_post_meta( $local_rma_id, self::M_REMOTE, (int) $data['rma_id'] );
		}
		if ( array_key_exists( 'admin_note', $data ) ) {
			update_post_meta( $local_rma_id, self::M_ADMIN_N, sanitize_textarea_field( (string) $data['admin_note'] ) );
		}
		if ( ! empty( $data['final'] ) ) {
			update_post_meta( $local_rma_id, self::M_FINAL, sanitize_key( (string) $data['final'] ) );
		}
		if ( ! empty( $data['fault'] ) ) {
			update_post_meta( $local_rma_id, self::M_FAULT, sanitize_key( (string) $data['fault'] ) );
		}
		if ( ! empty( $data['credit'] ) && (float) $data['credit'] > 0 ) {
			update_post_meta( $local_rma_id, self::M_CREDIT, (float) $data['credit'] );
		}
		if ( ! empty( $data['return_address'] ) ) {
			update_post_meta( $local_rma_id, self::M_RETURN, sanitize_textarea_field( (string) $data['return_address'] ) );
		}
		update_post_meta( $local_rma_id, self::M_SYNCED, current_time( 'mysql' ) );

		if ( $status && $status !== $old ) {
			self::history( $local_rma_id, $status, sanitize_textarea_field( (string) ( $data['admin_note'] ?? '' ) ) );
			$order = wc_get_order( (int) get_post_meta( $local_rma_id, self::M_ORDER, true ) );
			if ( $order ) {
				$extra = '';
				if ( ! empty( $data['final'] ) ) {
					$extra .= ' — نتیجه نهایی: ' . self::final_label( $data['final'] );
				}
				if ( ! empty( $data['credit'] ) && (float) $data['credit'] > 0 ) {
					$extra .= ' — بازگشت به کیف پول شما در مرکز: ' . wp_kses_post( wc_price( (float) $data['credit'] ) );
				}
				if ( ! empty( $data['return_address'] ) && 'await_item' === $status ) {
					$extra .= ' — آدرس ارسال کالای عودتی: ' . sanitize_textarea_field( (string) $data['return_address'] );
				}
				$order->add_order_note( sprintf( 'وضعیت عودت #%d از دستیار شاپ: %s%s', $local_rma_id, self::status_label( $status ), $extra ) );
			}
			do_action( 'dastyarc_rma_status_changed', $local_rma_id, $status, $data );
		}
		return true;
	}

	/** Pull دستی یک درخواست از مرکز */
	public static function refresh_from_central( $rma_id ) {
		$remote = (int) get_post_meta( (int) $rma_id, self::M_REMOTE, true );
		if ( ! $remote ) {
			return new WP_Error( 'dastyarc_rma_not_sent_yet', 'این درخواست هنوز برای دستیار شاپ ارسال نشده است.' );
		}
		$info = DastyarC_Client::rma_get( $remote );
		if ( is_wp_error( $info ) ) {
			return $info;
		}
		self::apply_remote( (int) $rma_id, $info );
		return true;
	}

	/** سینک دوره‌ای درخواست‌های باز (جبران وب‌هوک‌های ازدست‌رفته) — روی کرون موجود Connector */
	public static function reconcile_rmas() {
		if ( ! DastyarC_Client::configured() ) {
			return;
		}
		$ids = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => array(
				array( 'key' => self::M_REMOTE, 'compare' => 'EXISTS' ),
				array( 'key' => self::M_STATUS, 'value' => self::TERMINAL, 'compare' => 'NOT IN' ),
			),
		) );
		foreach ( (array) $ids as $id ) {
			self::refresh_from_central( (int) $id );
		}

		// قوانین عودت هم هر بار از مرکز پول می‌شود (تعریف‌شده فقط در مرکز)
		self::sync_rules_from_central();
	}

	/* ------------------------------------------------------------------
	 * اقدام‌های پیشخوان (admin-post)
	 * ---------------------------------------------------------------- */

	public function handle_send() {
		$rma_id = (int) ( $_GET['id'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_rma_send_' . $rma_id ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$res = self::send_to_central( $rma_id );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'dastyarc-hub', 'tab' => 'rma', 'view' => $rma_id, 'rma_error' => rawurlencode( $res->get_error_message() ) ), admin_url( 'admin.php' ) ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=rma&view=' . $rma_id . '&rma_sent=1' ) );
		}
		exit;
	}

	public function handle_refresh() {
		$rma_id = (int) ( $_GET['id'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_rma_refresh_' . $rma_id ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$res = self::refresh_from_central( $rma_id );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'dastyarc-hub', 'tab' => 'rma', 'view' => $rma_id, 'rma_error' => rawurlencode( $res->get_error_message() ) ), admin_url( 'admin.php' ) ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=rma&view=' . $rma_id . '&rma_synced=1' ) );
		}
		exit;
	}

	/** ویرایش توضیحات فروشنده — فقط قبل از ارسال به مرکز (هنگام ارسال همراه payload می‌رود) */
	public function handle_note() {
		$rma_id = (int) ( $_POST['rma_id'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_rma_note_' . $rma_id ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$post = get_post( $rma_id );
		if ( $post && self::POST_TYPE === $post->post_type && ! (int) get_post_meta( $rma_id, self::M_REMOTE, true ) ) {
			update_post_meta( $rma_id, self::M_VENDOR_N, sanitize_textarea_field( wp_unslash( $_POST['rma_vendor_note'] ?? '' ) ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=rma&view=' . $rma_id . '&rma_note_saved=1' ) );
		exit;
	}

	/** ثبت گزارش عودت دستی (تماس تلفنی/واتساپ مشتری) */
	public function handle_manual() {
		$order_id = (int) ( $_POST['order_id'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_rma_manual_' . $order_id ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$order = wc_get_order( $order_id );
		$items = array();
		foreach ( array_map( 'sanitize_text_field', (array) ( $_POST['sel'] ?? array() ) ) as $key ) {
			$items[ $key ] = max( 1, (int) ( $_POST['qty'][ $key ] ?? 1 ) );
		}
		$res = self::create_local(
			$order,
			$items,
			sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ),
			'',
			sanitize_textarea_field( wp_unslash( $_POST['vendor_note'] ?? '' ) ),
			'vendor',
			'',
			'dastyarc_rma_file'
		);
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'dastyarc-hub', 'tab' => 'rma', 'new' => 1, 'order_id' => $order_id, 'rma_error' => rawurlencode( $res->get_error_message() ) ), admin_url( 'admin.php' ) ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=dastyarc-hub&tab=rma&view=' . (int) $res . '&rma_created=1' ) );
		}
		exit;
	}

	/* ------------------------------------------------------------------
	 * صفحه پیشخوان «عودت و مرجوعی»
	 * ---------------------------------------------------------------- */

	public function page_admin() {
		if ( ! empty( $_GET['view'] ) ) {
			$this->page_admin_detail( (int) $_GET['view'] );
			return;
		}
		if ( ! empty( $_GET['new'] ) ) {
			$this->page_admin_new();
			return;
		}
		$this->page_admin_list();
	}

	/** اعلان‌های نتیجه */
	protected function notices_admin() {
		if ( isset( $_GET['rma_sent'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>درخواست عودت با موفقیت برای دستیار شاپ ارسال شد. تغییر وضعیت‌ها به‌صورت خودکار همین‌جا به‌روزرسانی می‌شوند.</p></div>';
		}
		if ( isset( $_GET['rma_synced'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>وضعیت درخواست از مرکز به‌روزرسانی شد.</p></div>';
		}
		if ( isset( $_GET['rma_note_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>توضیحات فروشنده ذخیره شد.</p></div>';
		}
		if ( isset( $_GET['rma_created'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>گزارش عودت ثبت شد. پس از بازبینی، با دکمه «ارسال برای دستیار شاپ» به مرکز بفرستید.</p></div>';
		}
		if ( ! empty( $_GET['rma_error'] ) ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( sanitize_text_field( wp_unslash( $_GET['rma_error'] ) ) ) );
		}
	}

	/** لیست درخواست‌ها + فیلتر وضعیت */
	protected function page_admin_list() {
		echo '<div class="dastyarc-rma-tab">';
		echo '<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px"><span style="font-size:13px;color:var(--mut)">مدیریت درخواست‌های عودت از ثبت تا تسویه نهایی</span>';
		printf( '<a href="%s" class="dcx2-btn prime" style="margin-right:auto">+ ثبت گزارش عودت (دستی)</a></div>', esc_url( admin_url( 'admin.php?page=dastyarc-hub&tab=rma&new=1' ) ) );
		echo '<hr class="wp-header-end">';
		$this->notices_admin();

		$page_id = (int) get_option( self::PAGE_OPTION );
		echo '<p class="description">گزارش‌هایی که مشتری در <a href="' . esc_url( $page_id ? get_permalink( $page_id ) : '#' ) . '" target="_blank">برگه عمومی «عودت و مرجوعی»</a> سایت شما ثبت می‌کند (یا خودتان برای تماس‌های تلفنی دستی ثبت می‌کنید) اینجا می‌رسد؛ پس از بازبینی آن‌ها را برای دستیار شاپ ارسال کنید و نتیجه (تعویض/لغو با بازپرداخت کیف پول/رد) را همین‌جا دنبال کنید. ارتباط مستقیم با مشتری نهایی بر عهده شماست.</p>';

		$f_status = sanitize_key( (string) ( $_GET['rma_status'] ?? '' ) );
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="margin:12px 0;display:flex;gap:8px;align-items:center">';
		echo '<input type="hidden" name="page" value="dastyarc-hub"><input type="hidden" name="tab" value="rma">';
		echo '<select name="rma_status"><option value="">همه وضعیت‌ها</option>';
		foreach ( self::statuses() as $slug => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $slug ), selected( $f_status, $slug, false ), esc_html( $label ) );
		}
		echo '</select>';
		submit_button( 'فیلتر', 'secondary', '', false );
		echo '</form>';

		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $f_status && array_key_exists( $f_status, self::statuses() ) ) {
			$args['meta_key']   = self::M_STATUS;
			$args['meta_value'] = $f_status;
		}
		$posts = get_posts( $args );

		if ( ! $posts ) {
			echo '<p>گزارش عودتی یافت نشد.</p></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr><th>شناسه</th><th>سفارش</th><th>منشأ</th><th>اقلام</th><th>دلیل</th><th>تاریخ ثبت</th><th>وضعیت</th><th>نتیجه / بازپرداخت</th><th></th></tr></thead><tbody>';
		foreach ( $posts as $p ) {
			$status = (string) get_post_meta( $p->ID, self::M_STATUS, true ) ?: 'registered';
			$order  = wc_get_order( (int) get_post_meta( $p->ID, self::M_ORDER, true ) );
			$qty    = 0;
			foreach ( self::meta_arr( $p->ID, self::M_ITEMS ) as $it ) {
				$qty += (int) ( $it['qty'] ?? 1 );
			}
			$final  = (string) get_post_meta( $p->ID, self::M_FINAL, true );
			$credit = (float) get_post_meta( $p->ID, self::M_CREDIT, true );
			$source = (string) get_post_meta( $p->ID, self::M_SOURCE, true );

			echo '<tr>';
			printf( '<td><strong>#%d</strong></td>', (int) $p->ID );
			printf( '<td>%s</td>', $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>' : '—' );
			printf( '<td>%s</td>', 'customer' === $source ? 'مشتری (فرم سایت)' : 'فروشنده (دستی)' );
			printf( '<td>%d</td>', $qty );
			printf( '<td>%s</td>', esc_html( wp_trim_words( (string) get_post_meta( $p->ID, self::M_REASON, true ), 12 ) ) );
			printf( '<td>%s</td>', esc_html( get_the_date( 'Y/m/d H:i', $p ) ) );
			printf( '<td><span style="display:inline-block;background:%s;color:#fff;border-radius:12px;padding:2px 10px;font-size:11px;white-space:nowrap">%s</span></td>', esc_attr( self::status_color( $status ) ), esc_html( self::status_label( $status ) ) );
			printf( '<td>%s</td>', $final ? esc_html( self::final_label( $final ) ) . ( $credit > 0 ? ' — ' . wp_kses_post( wc_price( $credit ) ) : '' ) : '—' );
			printf( '<td><a class="button button-small" href="%s">مدیریت</a></td>', esc_url( admin_url( 'admin.php?page=dastyarc-hub&tab=rma&view=' . (int) $p->ID ) ) );
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/** فرم ثبت گزارش دستی — دو مرحله: یافتن سفارش سپس انتخاب اقلام */
	protected function page_admin_new() {
		echo '<div>';
		printf( '<p style="margin-bottom:14px"><a href="%s" class="dcx2-btn sm">← بازگشت به لیست</a></p>', esc_url( admin_url( 'admin.php?page=dastyarc-hub&tab=rma' ) ) );
		$this->notices_admin();
		echo '<p class="description">برای گزارش‌هایی که مشتری تلفنی یا در واتساپ اعلام می‌کند، خودتان از اینجا درخواست عودت ثبت کنید؛ سپس از لیست آن را برای دستیار شاپ ارسال نمایید.</p>';

		$order_id = (int) ( $_GET['order_id'] ?? 0 );

		if ( ! $order_id ) {
			echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="max-width:460px;background:#fff;border:1px solid #ccd0d4;padding:14px 18px">';
			echo '<input type="hidden" name="page" value="dastyarc-hub"><input type="hidden" name="tab" value="rma"><input type="hidden" name="new" value="1">';
			echo '<p><label><strong>شماره سفارش مشتری</strong></label><br>';
			echo '<input type="number" name="order_id" min="1" style="width:100%;direction:ltr" required></p>';
			submit_button( 'یافتن اقلام سفارش', 'primary', '', false );
			echo '</form></div>';
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			echo '<div class="notice notice-error"><p>سفارشی با این شماره یافت نشد.</p></div></div>';
			return;
		}
		if ( ! $order->get_meta( '_dastyarc_remote_order_id' ) ) {
			echo '<div class="notice notice-warning"><p>این سفارش هنوز به دستیار شاپ ارسال نشده است؛ گزارش عودت فقط برای سفارش‌های ارسال‌شده به مرکز قابل ثبت است.</p></div></div>';
			return;
		}

		$rows = self::remote_items_for_order( $order );
		if ( ! $rows ) {
			echo '<div class="notice notice-error"><p>این سفارش قلم دستیاری ندارد.</p></div></div>';
			return;
		}

		printf( '<h2>سفارش #%s</h2>', esc_html( $order->get_order_number() ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" style="max-width:760px">';
		wp_nonce_field( 'dastyarc_rma_manual_' . $order_id );
		echo '<input type="hidden" name="action" value="dastyarc_rma_manual">';
		printf( '<input type="hidden" name="order_id" value="%d">', (int) $order_id );

		echo '<table class="widefat striped"><thead><tr><th style="width:44px"></th><th>کالا</th><th style="width:120px">تعداد عودتی</th></tr></thead><tbody>';
		foreach ( $rows as $ri ) {
			echo '<tr>';
			printf( '<td><input type="checkbox" name="sel[]" value="%s"></td>', esc_attr( $ri['key'] ) );
			printf( '<td>%s <small>(خرید: %d عدد)</small></td>', esc_html( $ri['name'] ), (int) $ri['qty'] );
			printf( '<td><input type="number" name="qty[%s]" value="1" min="1" max="%d" style="width:70px"></td>', esc_attr( $ri['key'] ), (int) $ri['qty'] );
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<p><label><strong>دلیل عودت</strong></label><br><select name="reason" style="min-width:300px">';
		foreach ( self::reasons() as $r ) {
			printf( '<option>%s</option>', esc_html( $r ) );
		}
		echo '</select></p>';

		echo '<p><label><strong>توضیحات فروشنده (اختیاری — هنگام ارسال برای دستیار شاپ می‌رود)</strong></label><br><textarea name="vendor_note" rows="3" style="width:100%" placeholder="مثلاً: مشتری تماس گرفت و گفت جعبه آسیب دیده…"></textarea></p>';

		echo '<p><label><strong>تصویر ضمیمه (اختیاری — jpg/png/webp حداکثر ۳ مگابایت)</strong></label><br><input type="file" name="dastyarc_rma_file" accept="image/jpeg,image/png,image/webp"></p>';

		submit_button( 'ثبت گزارش عودت', 'primary' );
		echo '</form></div>';
	}

	/** نمای تکی: جزئیات + اقدامها (ارسال/به‌روزرسانی/یادداشت) + تاریخچه */
	protected function page_admin_detail( $rma_id ) {
		$post = get_post( $rma_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			wp_die( 'گزارش عودت یافت نشد.' );
		}

		$status   = (string) get_post_meta( $rma_id, self::M_STATUS, true ) ?: 'registered';
		$order    = wc_get_order( (int) get_post_meta( $rma_id, self::M_ORDER, true ) );
		$items    = self::meta_arr( $rma_id, self::M_ITEMS );
		$reason   = (string) get_post_meta( $rma_id, self::M_REASON, true );
		$cust_n   = (string) get_post_meta( $rma_id, self::M_CUSTOMER, true );
		$vend_n   = (string) get_post_meta( $rma_id, self::M_VENDOR_N, true );
		$attach   = array_filter( array_map( 'intval', self::meta_arr( $rma_id, self::M_ATTACH ) ) );
		$remote   = (int) get_post_meta( $rma_id, self::M_REMOTE, true );
		$admin_n  = (string) get_post_meta( $rma_id, self::M_ADMIN_N, true );
		$final    = (string) get_post_meta( $rma_id, self::M_FINAL, true );
		$fault    = (string) get_post_meta( $rma_id, self::M_FAULT, true );
		$credit   = (float) get_post_meta( $rma_id, self::M_CREDIT, true );
		$ret_addr = (string) get_post_meta( $rma_id, self::M_RETURN, true );
		$history  = self::meta_arr( $rma_id, self::M_HISTORY );
		$synced   = (string) get_post_meta( $rma_id, self::M_SYNCED, true );
		$source   = (string) get_post_meta( $rma_id, self::M_SOURCE, true );
		$phone    = (string) get_post_meta( $rma_id, self::M_PHONE, true );

		echo '<div>';
		printf( '<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px"><b style="font-size:15px">گزارش عودت #%d</b><a href="%s" class="dcx2-btn sm" style="margin-right:auto">← بازگشت به لیست</a></div>', (int) $rma_id, esc_url( admin_url( 'admin.php?page=dastyarc-hub&tab=rma' ) ) );
		$this->notices_admin();

		// ── اقدام اصلی ──
		echo '<div style="margin:12px 0;padding:12px 16px;background:#fff;border:1px solid #ccd0d4;display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
		if ( ! $remote ) {
			$send_url = wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_rma_send&id=' . $rma_id ), 'dastyarc_rma_send_' . $rma_id );
			printf( '<a class="button button-primary button-hero" style="background:#17a16d;border-color:#17a16d" href="%s" onclick="return confirm(\'درخواست عودت برای دستیار شاپ ارسال شود؟ پس از ارسال، امکان ویرایش اقلام نیست.\')">ارسال برای دستیار شاپ ⇦</a>', esc_url( $send_url ) );
			echo '<span class="description">درخواست ابتدا «ثبت شده» است؛ با ارسال، وضعیت‌ها و نتیجه نهایی از مرکز همین‌جا به‌روزرسانی می‌شوند.</span>';
		} else {
			$ref_url = wp_nonce_url( admin_url( 'admin-post.php?action=dastyarc_rma_refresh&id=' . $rma_id ), 'dastyarc_rma_refresh_' . $rma_id );
			printf( '<span style="background:%s;color:#fff;border-radius:14px;padding:4px 14px;font-weight:700">%s</span>', esc_attr( self::status_color( $status ) ), esc_html( self::status_label( $status ) ) );
			printf( '<a class="button" href="%s">به‌روزرسانی وضعیت از مرکز</a>', esc_url( $ref_url ) );
			printf( '<span class="description">RMA مرکزی #%d%s</span>', (int) $remote, $synced ? ' — آخرین همگام‌سازی: ' . esc_html( $synced ) : '' );
		}
		echo '</div>';

		echo '<div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start">';

		// ── اطلاعات درخواست ──
		echo '<div style="flex:1;min-width:300px;background:#fff;border:1px solid #ccd0d4;padding:12px 16px">';
		echo '<h2 style="margin-top:0">جزئیات درخواست</h2><table class="widefat" style="border:0">';
		printf( '<tr><th style="width:130px">سفارش</th><td>%s</td></tr>', $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>' : '—' );
		printf( '<tr><th>منشأ</th><td>%s</td></tr>', 'customer' === $source ? 'مشتری (فرم سایت)' : 'فروشنده (ثبت دستی)' );
		if ( $phone ) {
			printf( '<tr><th>شماره تماس</th><td style="direction:ltr;text-align:right">%s</td></tr>', esc_html( $phone ) );
		}
		printf( '<tr><th>تاریخ ثبت</th><td>%s</td></tr>', esc_html( get_the_date( 'Y/m/d H:i', $post ) ) );
		printf( '<tr><th>دلیل عودت</th><td><strong>%s</strong></td></tr>', esc_html( $reason ) );
		if ( '' !== $cust_n ) {
			printf( '<tr><th>توضیحات مشتری</th><td>%s</td></tr>', nl2br( esc_html( $cust_n ) ) );
		}
		echo '</table>';

		echo '<h3>اقلام عودتی</h3><table class="widefat striped"><thead><tr><th>کالا</th><th style="width:70px">تعداد</th></tr></thead><tbody>';
		foreach ( $items as $it ) {
			printf( '<tr><td>%s</td><td>%d</td></tr>', esc_html( $it['name'] ?? '—' ), (int) ( $it['qty'] ?? 1 ) );
		}
		echo '</tbody></table>';

		if ( $attach ) {
			echo '<p><strong>تصاویر ضمیمه:</strong><br>';
			foreach ( $attach as $aid ) {
				$full = wp_get_attachment_image_url( $aid, 'full' );
				$th   = wp_get_attachment_image_url( $aid, 'thumbnail' );
				if ( $full && $th ) {
					printf( '<a href="%s" target="_blank" style="display:inline-block;margin:0 0 6px 6px"><img src="%s" style="width:72px;height:72px;object-fit:cover;border:1px solid #ddd;border-radius:6px"></a>', esc_url( $full ), esc_url( $th ) );
				}
			}
			echo '</p>';
		}

		// توضیحات فروشنده — فقط قبل از ارسال قابل ویرایش
		if ( ! $remote ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'dastyarc_rma_note_' . $rma_id );
			echo '<input type="hidden" name="action" value="dastyarc_rma_note">';
			printf( '<input type="hidden" name="rma_id" value="%d">', (int) $rma_id );
			echo '<p><label><strong>توضیحات فروشنده (هنگام ارسال برای دستیار شاپ می‌رود)</strong></label><br>';
			printf( '<textarea name="rma_vendor_note" rows="3" style="width:100%%">%s</textarea></p>', esc_textarea( $vend_n ) );
			submit_button( 'ذخیره توضیحات', 'secondary' );
			echo '</form>';
		} elseif ( '' !== $vend_n ) {
			printf( '<p><strong>توضیحات فروشنده:</strong><br>%s</p>', nl2br( esc_html( $vend_n ) ) );
		}
		echo '</div>';

		// ── پاسخ مرکز ──
		echo '<div style="flex:1;min-width:300px;background:#fff;border:1px solid #ccd0d4;padding:12px 16px">';
		echo '<h2 style="margin-top:0">پاسخ دستیار شاپ</h2>';
		if ( ! $remote ) {
			echo '<p class="description">هنوز برای مرکز ارسال نشده است؛ پاسخ مرکز پس از ارسال اینجا نمایش داده می‌شود.</p>';
		} else {
			echo '<table class="widefat" style="border:0">';
			printf( '<tr><th style="width:140px">وضعیت فعلی</th><td><span style="display:inline-block;background:%s;color:#fff;border-radius:12px;padding:2px 12px">%s</span></td></tr>', esc_attr( self::status_color( $status ) ), esc_html( self::status_label( $status ) ) );
			if ( '' !== $admin_n ) {
				printf( '<tr><th>توضیحات اپراتور</th><td>%s</td></tr>', nl2br( esc_html( $admin_n ) ) );
			}
			if ( $final ) {
				printf( '<tr><th>نتیجه نهایی</th><td><strong>%s</strong>%s</td></tr>', esc_html( self::final_label( $final ) ), $fault ? ' (مقصر: ' . esc_html( self::fault_label( $fault ) ) . ')' : '' );
			}
			if ( $credit > 0 ) {
				printf( '<tr><th>بازگشت به کیف پول</th><td><strong style="color:#17a16d">%s</strong> <small>— به کیف پول شما در مرکز دستیار شاپ شارژ شد</small></td></tr>', wp_kses_post( wc_price( $credit ) ) );
			}
			if ( '' !== $ret_addr ) {
				printf( '<tr><th>آدرس ارسال کالای عودتی</th><td>%s<p class="description">این آدرس را به مشتری اعلام کنید تا کالا را برگرداند.</p></td></tr>', nl2br( esc_html( $ret_addr ) ) );
			}
			echo '</table>';
		}

		// ── تاریخچه ──
		if ( $history ) {
			echo '<h3>تاریخچه وضعیت</h3><table class="widefat striped"><thead><tr><th>زمان</th><th>وضعیت</th><th>یادداشت</th></tr></thead><tbody>';
			foreach ( array_reverse( $history ) as $h ) {
				printf(
					'<tr><td>%s</td><td><span style="display:inline-block;background:%s;color:#fff;border-radius:10px;padding:1px 9px;font-size:11px">%s</span></td><td>%s</td></tr>',
					esc_html( $h['at'] ?? '' ),
					esc_attr( self::status_color( $h['status'] ?? '' ) ),
					esc_html( $h['label'] ?? '' ),
					esc_html( $h['note'] ?? '' )
				);
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		echo '</div></div>';
	}

	/* ==================================================================
	 * برگه عمومی سایت — قوانین + ثبت گزارش توسط مشتری + پیگیری وضعیت
	 * (ارتباط مستقیم دستیار شاپ با مشتری: هیچ؛ همه‌چیز از کانال فروشنده)
	 * ================================================================= */

	public function shortcode() {
		$html = $this->public_styles();
		$html .= '<div class="dastyarc-wrap">';

		// سربرگ برند (وسط‌چین و گرافیکی)
		$html .= '<div class="dastyarc-hero"><div class="dastyarc-hero-icon">🔄</div>'
			. '<h2 class="dastyarc-hero-title">عودت و مرجوعی کالا</h2>'
			. '<p class="dastyarc-hero-sub">ثبت گزارش عودت و پیگیری وضعیت درخواست‌های شما</p></div>';

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! empty( $_POST['dastyarc_rma_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_key( $_POST['dastyarc_rma_nonce'] ), 'dastyarc_rma_cust' ) ) {
				$html .= $this->public_msg( 'نشست نامعتبر است. دوباره تلاش کنید.', true );
			} elseif ( ! empty( $_POST['dastyarc_rma_action'] ) && 'submit' === $_POST['dastyarc_rma_action'] ) {
				$html .= $this->handle_customer_submit();
			}
		}

		// پس از احراز (شماره سفارش + شماره تماس) فرم کامل + وضعیت‌های قبلی دیده می‌شود
		$order = $this->verified_order_from_request();
		if ( $order ) {
			$html .= $this->public_order_panel( $order );
		} else {
			$html .= $this->public_lookup_form();
		}

		// قوانین عودت (v1.6.0 — مورد ۱۲: بالای فرم → زیر فرم، تا پرکردن فرم راحت‌تر باشد)
		$html .= '<div class="dastyarc-rma-rules"><h3>📜 قوانین عودت و مرجوعی کالا</h3><p>' . nl2br( esc_html( self::rules() ) ) . '</p></div>';

		$html .= '</div>';
		return $html;
	}

	/** فرم اولیه احراز: شماره سفارش + شماره تماس (مثل برگه پیگیری سفارش) */
	protected function public_lookup_form() {
		ob_start();
		?>
		<form method="post" class="dastyarc-rma-form">
			<?php wp_nonce_field( 'dastyarc_rma_cust', 'dastyarc_rma_nonce' ); ?>
			<h3>ثبت یا پیگیری درخواست عودت</h3>
			<p class="dastyarc-rma-hint">برای ثبت گزارش عودت یا مشاهده وضعیت درخواست‌های قبلی، شماره سفارش و شماره تماستان را وارد کنید.</p>
			<div class="dastyarc-rma-row"><label>شماره سفارش</label><input type="text" name="rma_order_no" inputmode="numeric" placeholder="مثلاً 1052" required></div>
			<div class="dastyarc-rma-row"><label>شماره تماس ثبت‌شده در سفارش</label><input type="text" name="rma_phone" inputmode="tel" placeholder="مثلاً 0912…" required></div>
			<button type="submit" class="dastyarc-rma-btn">ادامه</button>
		</form>
		<?php
		return ob_get_clean();
	}

	/** احراز سفارش از درخواست جاری — سفارش باید وجود داشته و تلفن صورتحسابش مطابقت کند */
	protected function verified_order_from_request() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['rma_order_no'] ) || empty( $_POST['dastyarc_rma_nonce'] ) ) {
			return null;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['dastyarc_rma_nonce'] ), 'dastyarc_rma_cust' ) ) {
			return null;
		}
		$order = wc_get_order( absint( $_POST['rma_order_no'] ) );
		if ( ! $order ) {
			return null;
		}
		$phone = self::normalize_phone( (string) ( $_POST['rma_phone'] ?? '' ) );
		if ( strlen( $phone ) < 10 || self::normalize_phone( $order->get_billing_phone() ) !== $phone ) {
			return null;
		}
		return $order;
	}

	/** پنل کامل سفارش: اقلام قابل عودت + فرم ثبت + درخواست‌های قبلی همین سفارش */
	protected function public_order_panel( WC_Order $order ) {
		$out = '<div class="dastyarc-rma-panel">';
		$out .= '<h3>سفارش #' . esc_html( $order->get_order_number() ) . ' <small>(' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . ')</small></h3>';

		// ── درخواست‌های قبلی همین سفارش (تاریخچه وضعیت برای مشتری) ──
		$prev = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array( array( 'key' => self::M_ORDER, 'value' => $order->get_id(), 'compare' => '=', 'type' => 'NUMERIC' ) ),
		) );
		if ( $prev ) {
			$out .= '<h4>درخواست‌های عودت شما</h4><table class="dastyarc-rma-table"><thead><tr><th>شناسه</th><th>تاریخ</th><th>وضعیت</th><th>نتیجه/یادداشت</th></tr></thead><tbody>';
			foreach ( $prev as $p ) {
				$st      = (string) get_post_meta( $p->ID, self::M_STATUS, true ) ?: 'registered';
				$final   = (string) get_post_meta( $p->ID, self::M_FINAL, true );
				$credit  = (float) get_post_meta( $p->ID, self::M_CREDIT, true );
				$admin_n = (string) get_post_meta( $p->ID, self::M_ADMIN_N, true );
				$ret     = (string) get_post_meta( $p->ID, self::M_RETURN, true );

				$out .= '<tr>';
				$out .= '<td><strong>#' . (int) $p->ID . '</strong></td>';
				$out .= '<td>' . esc_html( get_the_date( 'Y/m/d', $p ) ) . '</td>';
				$out .= '<td><span class="dastyarc-rma-badge" style="background:' . esc_attr( self::status_color( $st ) ) . '">' . esc_html( self::status_label( $st ) ) . '</span>';
				if ( 'await_item' === $st && $ret ) {
					$out .= '<br><small>آدرس ارسال کالا: ' . nl2br( esc_html( $ret ) ) . '</small>';
				}
				$out .= '</td><td>';
				if ( $final ) {
					$out .= 'نتیجه: <strong>' . esc_html( self::final_label( $final ) ) . '</strong>';
					if ( $credit > 0 ) {
						$out .= ' — مبلغ بازگشتی: ' . wp_kses_post( wc_price( $credit ) );
					}
				}
				if ( $admin_n ) {
					$out .= ( $final ? '<br>' : '' ) . '<small>' . esc_html( $admin_n ) . '</small>';
				}
				if ( ! $final && ! $admin_n ) {
					$out .= '—';
				}
				$out .= '</td></tr>';
			}
			$out .= '</tbody></table>';
		}

		// ── فرم ثبت گزارش جدید ──
		$rows = self::remote_items_for_order( $order );
		if ( ! $order->get_meta( '_dastyarc_remote_order_id' ) || ! $rows ) {
			$out .= '<p class="dastyarc-rma-hint">برای این سفارش، امکان ثبت گزارش عودت آنلاین نیست؛ لطفاً با پشتیبانی فروشگاه در تماس باشید.</p></div>';
			return $out;
		}

		$out .= '<form method="post" class="dastyarc-rma-form" enctype="multipart/form-data">';
		$out .= wp_nonce_field( 'dastyarc_rma_cust', 'dastyarc_rma_nonce', true, false );
		$out .= '<input type="hidden" name="dastyarc_rma_action" value="submit">';
		$out .= '<input type="hidden" name="rma_order_no" value="' . esc_attr( $order->get_order_number() ) . '">';
		$out .= '<input type="hidden" name="rma_phone" value="' . esc_attr( self::normalize_phone( (string) ( $_POST['rma_phone'] ?? '' ) ) ) . '">';
		$out .= '<h4>ثبت گزارش عودت جدید</h4>';
		$out .= '<table class="dastyarc-rma-table"><thead><tr><th></th><th>کالا</th><th>تعداد عودتی</th></tr></thead><tbody>';
		foreach ( $rows as $ri ) {
			$out .= '<tr><td><input type="checkbox" name="sel[]" value="' . esc_attr( $ri['key'] ) . '"></td>';
			$out .= '<td>' . esc_html( $ri['name'] ) . ' <small>(خرید: ' . (int) $ri['qty'] . ' عدد)</small></td>';
			$out .= '<td><input type="number" name="qty[' . esc_attr( $ri['key'] ) . ']" value="1" min="1" max="' . (int) $ri['qty'] . '" style="width:64px"></td></tr>';
		}
		$out .= '</tbody></table>';
		$out .= '<div class="dastyarc-rma-row"><label>دلیل عودت</label><select name="reason">';
		foreach ( self::reasons() as $r ) {
			$out .= '<option>' . esc_html( $r ) . '</option>';
		}
		$out .= '</select></div>';
		$out .= '<div class="dastyarc-rma-row"><label>توضیحات شما</label><textarea name="note" rows="3" placeholder="ایراد یا دلیل عودت را توضیح دهید…"></textarea></div>';
		$out .= '<div class="dastyarc-rma-row"><label>تصویر کالا (اختیاری — حداکثر ۳ مگابایت)</label><input type="file" name="dastyarc_rma_image" accept="image/jpeg,image/png,image/webp"></div>';
		$out .= '<button type="submit" class="dastyarc-rma-btn">ثبت گزارش عودت</button>';
		$out .= '</form></div>';
		return $out;
	}

	/** ثبت گزارش از فرم عمومی (مشتری) → CPT محلی با وضعیت «ثبت شده» (خودکار ارسال نمی‌شود) */
	protected function handle_customer_submit() {
		$order = $this->verified_order_from_request();
		if ( ! $order ) {
			return $this->public_msg( 'احراز سفارش ناموفق بود؛ شماره سفارش و شماره تماس را بررسی کنید.', true );
		}

		$items = array();
		foreach ( array_map( 'sanitize_text_field', (array) ( $_POST['sel'] ?? array() ) ) as $key ) {
			$items[ $key ] = max( 1, (int) ( $_POST['qty'][ $key ] ?? 1 ) );
		}

		$rma_id = self::create_local(
			$order,
			$items,
			sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ),
			sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
			'',
			'customer',
			self::normalize_phone( (string) ( $_POST['rma_phone'] ?? '' ) ),
			'dastyarc_rma_image'
		);

		if ( is_wp_error( $rma_id ) ) {
			return $this->public_msg( $rma_id->get_error_message(), true );
		}
		return $this->public_msg( sprintf( '✔ گزارش عودت شما با شناسه #%d ثبت شد. درخواست پس از بررسی فروشگاه پیگیری می‌شود و وضعیت آن را از همین برگه می‌توانید ببینید.', (int) $rma_id ), false );
	}

	protected function public_msg( $text, $error ) {
		return '<div class="dastyarc-rma-msg' . ( $error ? ' error' : '' ) . '">' . esc_html( $text ) . '</div>';
	}

	protected function public_styles() {
		return '<style>
			@import url("https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css");
			.dastyarc-wrap{max-width:820px;margin:0 auto;padding:10px 0;font-family:"Vazirmatn","IRANSans",Tahoma,sans-serif}
			.dastyarc-wrap *{box-sizing:border-box;font-family:"Vazirmatn","IRANSans",Tahoma,sans-serif}
			.dastyarc-hero{background:linear-gradient(135deg,#17a16d,#0f7a52);border-radius:20px;padding:30px 24px;text-align:center;color:#fff;box-shadow:0 12px 34px rgba(23,161,109,.28);margin-bottom:22px}
			.dastyarc-hero-icon{font-size:42px;line-height:1;filter:drop-shadow(0 4px 8px rgba(0,0,0,.22))}
			.dastyarc-hero-title{margin:10px 0 4px;font-size:25px;font-weight:900;color:#fff;text-align:center}
			.dastyarc-hero-sub{margin:0;font-size:14px;color:#fff;text-align:center}
			.dastyarc-rma-rules{max-width:100%;margin:18px 0 0;padding:18px 22px;border:1px solid #cdeee0;border-right:5px solid #17a16d;border-radius:14px;background:#f4fbf8;line-height:2.1;box-shadow:0 3px 12px rgba(23,161,109,.07)}
			.dastyarc-rma-rules h3{margin:0 0 8px;font-size:16.5px;color:#0f5132}
			.dastyarc-rma-rules p{margin:0;color:#33513f}
			.dastyarc-rma-form{max-width:100%;margin:14px 0;padding:22px 24px;border:1px solid #e0efe8;border-radius:16px;background:#fff;box-shadow:0 6px 22px rgba(16,80,55,.08);text-align:center}
			.dastyarc-rma-form h3,.dastyarc-rma-form h4{margin:0 0 10px;color:#0f5132}
			.dastyarc-rma-row{margin-bottom:14px;max-width:460px;margin-right:auto;margin-left:auto;text-align:right}
			.dastyarc-rma-row label{display:block;margin-bottom:6px;font-weight:700;font-size:13.5px;color:#284436}
			.dastyarc-rma-row input[type="text"],.dastyarc-rma-row input[type="number"],.dastyarc-rma-row select,.dastyarc-rma-row textarea{width:100%;padding:11px 14px;border:1.5px solid #d3e7dc;border-radius:10px;font-family:inherit;transition:.2s;background:#fbfefd}
			.dastyarc-rma-row input[type="text"]:focus,.dastyarc-rma-row input[type="number"]:focus,.dastyarc-rma-row select:focus,.dastyarc-rma-row textarea:focus{border-color:#17a16d;outline:none;box-shadow:0 0 0 3px rgba(23,161,109,.14)}
			.dastyarc-rma-row input[type="file"]{font-size:12.5px}
			.dastyarc-rma-hint{color:#6b857a;font-size:12.5px;max-width:460px;margin:0 auto 14px}
			.dastyarc-rma-btn{background:#17a16d;color:#fff;border:0;padding:12px 34px;border-radius:11px;cursor:pointer;font-size:15.5px;font-weight:800;font-family:inherit;transition:.2s;box-shadow:0 5px 16px rgba(23,161,109,.3)}
			.dastyarc-rma-btn:hover{background:#12865a;transform:translateY(-2px)}
			.dastyarc-rma-msg{max-width:820px;margin:14px auto;padding:14px 18px;border-radius:12px;border:1px solid #b3e2c6;background:#f0fff6;line-height:2;text-align:center;box-shadow:0 3px 10px rgba(23,161,109,.08)}
			.dastyarc-rma-msg.error{border-color:#f5c2c7;background:#fff5f5}
			.dastyarc-rma-panel{max-width:100%;margin:14px 0}
			.dastyarc-rma-panel h3{margin:0 0 10px;color:#0f5132;text-align:center}
			.dastyarc-rma-table{width:100%;border-collapse:collapse;margin:12px 0;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(16,80,55,.07)}
			.dastyarc-rma-table th,.dastyarc-rma-table td{border:1px solid #e6f1eb;padding:10px 12px;font-size:13.5px;text-align:center;vertical-align:top}
			.dastyarc-rma-table th{background:#f2faf6;color:#0f5132;font-weight:800}
			.dastyarc-rma-badge{display:inline-block;color:#fff;border-radius:11px;padding:3px 12px;font-size:11.5px;white-space:nowrap}
		</style>';
	}

	/** نرمال‌سازی شماره تماس (همان منطق برگه پیگیری) */
	protected static function normalize_phone( $phone ) {
		$d = preg_replace( '/\D+/', '', (string) $phone );
		if ( 0 === strpos( $d, '0098' ) ) {
			$d = '0' . substr( $d, 4 );
		} elseif ( 0 === strpos( $d, '98' ) && strlen( $d ) >= 12 ) {
			$d = '0' . substr( $d, 2 );
		} elseif ( 10 === strlen( $d ) && '9' === substr( $d, 0, 1 ) ) {
			$d = '0' . $d;
		}
		return $d;
	}
}
