<?php
/**
 * سیستم عودت و مرجوعی (RMA) — سمت مرکز.
 *
 * اصل: مرجوعی = CPT استاندارد وردپرس (dastyar_rma) متصل به Order و متعهد WooCommerce؛
 * سیستم سفارش جدیدی ساخته نشده و تمام همگام‌سازی‌ها از طریق REST API + وب‌هوک امضادار
 * با افزونه Connector سایت فروشنده انجام می‌شود.
 *
 * - فروشنده درخواست را از سایت خودش ارسال می‌کند (REST POST /rma)
 * - مدیر در «دستیار شاپ ← مرجوعی‌ها» بررسی/تغییر وضعیت/نتیجه نهایی می‌کند
 * - تغییر وضعیت‌ها با رویداد rma.status به سایت فروشنده Push می‌شود
 * - نتیجه «لغو سفارش» = شارژ کیف پول فروشنده طبق قوانین:
 *   مقصر دستیار → مبلغ کالا + هزینه ارسال | مقصر مشتری → فقط مبلغ کالا
 * - دستیار شاپ هیچ ارتباط مستقیمی با مشتری نهایی ندارد
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Rma {

	const POST_TYPE = 'dastyar_rma';

	/** متاهای درخواست */
	const M_VENDOR   = '_dastyar_rma_vendor_id';     // شناسه کاربر فروشنده
	const M_ORDER    = '_dastyar_rma_order_id';      // شناسه سفارش WooCommerce مرکز
	const M_REMOTE   = '_dastyar_rma_remote_order';  // شماره سفارش در سایت فروشنده
	const M_LOCAL    = '_dastyar_rma_local_id';      // شناسه درخواست در سایت فروشنده
	const M_ITEMS    = '_dastyar_rma_items';         // [{product_id, variation_id, qty, name}]
	const M_REASON   = '_dastyar_rma_reason';
	const M_CUSTOMER = '_dastyar_rma_customer_note';
	const M_VENDOR_N = '_dastyar_rma_vendor_note';
	const M_ATTACH   = '_dastyar_rma_attachments';   // [attachment_id]
	const M_STATUS   = '_dastyar_rma_status';
	const M_ADMIN_N  = '_dastyar_rma_admin_note';
	const M_FINAL    = '_dastyar_rma_final';         // exchange | cancel | reject
	const M_FAULT    = '_dastyar_rma_fault';         // dastyar | customer
	const M_CREDIT   = '_dastyar_rma_credit';        // مبلغ برگشتی به کیف پول

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
	}

	public static function register_cpt() {
		register_post_type( self::POST_TYPE, array(
			'label'           => 'مرجوعی‌های دستیار (RMA)',
			'public'          => false,
			'show_ui'         => false, // مدیریت از صفحه اختصاصی «دستیار شاپ ← مرجوعی‌ها»
			'supports'        => array( 'title', 'author' ),
			'capability_type' => 'post',
		) );
	}

	/** وضعیت‌ها (سم-for-سم با لیست تأییدشده + «ثبت شده» فقط سمت فروشنده می‌ماند) */
	public static function statuses() {
		return array(
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
		return $all[ $slug ] ?? $all['submitted'];
	}

	public static function status_color( $slug ) {
		$map = array(
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

	/* ------------------------------------------------------------------
	 * ساخت درخواست از API (سمت فروشنده ارسال می‌کند)
	 * ---------------------------------------------------------------- */

	/** @return array|WP_Error */
	public static function create_from_api( array $data, $vendor_id ) {
		$remote_order_id = (int) ( $data['remote_order_id'] ?? 0 );
		$reason          = sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) );

		if ( ! $remote_order_id ) {
			return new WP_Error( 'dastyar_rma_no_order', 'شماره سفارش (remote_order_id) الزامی است.', array( 'status' => 400 ) );
		}
		if ( '' === $reason ) {
			return new WP_Error( 'dastyar_rma_no_reason', 'دلیل مرجوعی الزامی است.', array( 'status' => 400 ) );
		}

		// یافتن سفارش مرکزی متناظر با سفارش فروشنده (همان ref وارد شده از Connector)
		$remote_site = Dastyar::instance()->vendors->site_url( $vendor_id );
		$ref         = $vendor_id . ':' . $remote_site . ':' . $remote_order_id;
		$found       = wc_get_orders( array(
			'meta_key'   => '_dastyar_remote_ref',
			'meta_value' => $ref,
			'limit'      => 1,
			'return'     => 'ids',
		) );
		if ( ! $found ) {
			// تلاش دوم: با شناسه مستقیم سفارش مرکزی (اگر فروشنده شناسه مرکزی را دارد)
			$order = wc_get_order( $remote_order_id );
			if ( ! $order || (int) $order->get_meta( '_dastyar_vendor_id' ) !== $vendor_id ) {
				return new WP_Error( 'dastyar_rma_order_not_found', 'سفارش متناظر با این شماره برای فروشگاه شما یافت نشد.', array( 'status' => 404 ) );
			}
			$found = array( $order->get_id() );
		}
		$order     = wc_get_order( $found[0] );
		$local_id  = (int) ( $data['local_rma_id'] ?? 0 );

		// Idempotency: همان درخواست سایت فروشنده دوباره ارسال شود → همان رکورد قبلی
		if ( $local_id ) {
			$dups = get_posts( array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => self::M_VENDOR, 'value' => $vendor_id ),
					array( 'key' => self::M_LOCAL, 'value' => $local_id ),
				),
			) );
			if ( $dups ) {
				return self::api_payload( (int) $dups[0], true );
			}
		}

		// اقلام معتبر (باید در سفارش باشند)
		$items = array();
		$order_items = array();
		foreach ( $order->get_items() as $oi ) {
			$order_items[ $oi->get_product_id() ] = $oi;
			if ( $oi->get_variation_id() ) {
				$order_items[ $oi->get_variation_id() ] = $oi;
			}
		}
		foreach ( (array) ( $data['items'] ?? array() ) as $it ) {
			$pid = (int) ( $it['product_id'] ?? 0 );
			$qty = max( 1, (int) ( $it['qty'] ?? 1 ) );
			if ( ! $pid || ! isset( $order_items[ $pid ] ) ) {
				continue;
			}
			$oi = $order_items[ $pid ];
			$items[] = array(
				'product_id'   => $oi->get_product_id(),
				'variation_id' => $oi->get_variation_id(),
				'qty'          => min( $qty, (int) $oi->get_quantity() ),
				'name'         => $oi->get_name(),
			);
		}
		if ( ! $items ) {
			return new WP_Error( 'dastyar_rma_no_items', 'هیچ قلم معتبری (از اقلام این سفارش) در درخواست نیست.', array( 'status' => 400 ) );
		}

		$post_id = wp_insert_post( array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => sprintf( 'مرجوعی #%d — سفارش %s', $local_id ?: 0, $order->get_meta( '_dastyar_remote_order_number' ) ?: $order->get_order_number() ),
			'post_status' => 'publish',
			'post_author' => $vendor_id,
		), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// جایگذاری شناسه واقعی در عنوان
		wp_update_post( array( 'ID' => $post_id, 'post_title' => sprintf( 'مرجوعی #%d — سفارش %s', $post_id, $order->get_meta( '_dastyar_remote_order_number' ) ?: $order->get_order_number() ) ) );

		update_post_meta( $post_id, self::M_VENDOR, $vendor_id );
		update_post_meta( $post_id, self::M_ORDER, $order->get_id() );
		update_post_meta( $post_id, self::M_REMOTE, sanitize_text_field( (string) ( $data['remote_order_number'] ?? $order->get_meta( '_dastyar_remote_order_number' ) ) ) );
		update_post_meta( $post_id, self::M_LOCAL, $local_id );
		update_post_meta( $post_id, self::M_ITEMS, $items );
		update_post_meta( $post_id, self::M_REASON, $reason );
		update_post_meta( $post_id, self::M_CUSTOMER, sanitize_textarea_field( (string) ( $data['customer_note'] ?? '' ) ) );
		update_post_meta( $post_id, self::M_VENDOR_N, sanitize_textarea_field( (string) ( $data['vendor_note'] ?? '' ) ) );
		update_post_meta( $post_id, self::M_STATUS, 'submitted' );

		// پیوست‌ها (base64 از سمت فروشنده)
		self::store_attachments( $post_id, (array) ( $data['attachments'] ?? array() ) );

		$order->add_order_note( sprintf( 'درخواست مرجوعی #%d از فروشگاه فروشنده دریافت شد.', $post_id ) );
		$vendor_user = get_userdata( $vendor_id );
		wp_mail(
			get_option( 'admin_email' ),
			'درخواست مرجوعی جدید RMA #' . $post_id,
			sprintf( "فروشنده: %s\nسفارش: #%s (فروشگاه: #%s)\nاقلام: %d\nدلیل: %s\n\nمدیریت: %s",
				$vendor_user ? $vendor_user->display_name : $vendor_id,
				$order->get_order_number(),
				$order->get_meta( '_dastyar_remote_order_number' ),
				count( $items ),
				$reason,
				admin_url( 'admin.php?page=dastyar&view=' . $post_id )
			)
		);

		return self::api_payload( $post_id, false );
	}

	/** ذخیره پیوست‌های base64 به‌صورت Attachment استاندارد */
	protected static function store_attachments( $post_id, array $attachments ) {
		$ids  = array();
		$i    = 0;
		foreach ( array_slice( $attachments, 0, 5 ) as $att ) {
			$i++;
			$data = base64_decode( (string) ( $att['data'] ?? '' ), true );
			if ( false === $data || strlen( $data ) > 3 * 1024 * 1024 ) {
				continue; // حداکثر 3 مگابایت به‌ازای هر فایل
			}
			$mime = sanitize_text_field( (string) ( $att['mime'] ?? 'image/jpeg' ) );
			if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
				continue;
			}
			$ext  = str_replace( 'image/', '', $mime );
			$ext  = 'jpeg' === $ext ? 'jpg' : $ext;
			$file = wp_tempnam( 'rma' );
			if ( ! $file ) {
				continue;
			}
			file_put_contents( $file, $data );
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$id = media_handle_sideload(
				array( 'name' => 'rma-' . $post_id . '-' . $i . '.' . $ext, 'tmp_name' => $file, 'type' => $mime ),
				$post_id
			);
			if ( ! is_wp_error( $id ) ) {
				$ids[] = (int) $id;
			}
		}
		if ( $ids ) {
			update_post_meta( $post_id, self::M_ATTACH, $ids );
		}
	}

	/* ------------------------------------------------------------------
	 * تغییر وضعیت + اطلاع‌رسانی به فروشنده (Push رویداد rma.status)
	 * ---------------------------------------------------------------- */

	public static function set_status( $rma_id, $status, $admin_note = null ) {
		$statuses = self::statuses();
		if ( ! isset( $statuses[ $status ] ) ) {
			return false;
		}
		$old = (string) get_post_meta( $rma_id, self::M_STATUS, true );
		update_post_meta( $rma_id, self::M_STATUS, $status );
		if ( null !== $admin_note ) {
			update_post_meta( $rma_id, self::M_ADMIN_N, sanitize_textarea_field( (string) $admin_note ) );
		}

		if ( $old !== $status ) {
			$order = wc_get_order( (int) get_post_meta( $rma_id, self::M_ORDER, true ) );
			if ( $order ) {
				$order->add_order_note( sprintf( 'وضعیت مرجوعی #%d: %s', $rma_id, $statuses[ $status ] ) );
			}
			self::push_to_vendor( $rma_id );
			do_action( 'dastyar_rma_status_changed', $rma_id, $old, $status ); // v1.6.0 — اعلان پیامکی مرجوعی
		}
		return true;
	}

	/** Push وضعیت جاری به سایت فروشنده (امضادار — همان کانال وب‌هوک موجود) */
	public static function push_to_vendor( $rma_id ) {
		$vendor_id = (int) get_post_meta( $rma_id, self::M_VENDOR, true );
		if ( ! $vendor_id ) {
			return;
		}
		$status = (string) get_post_meta( $rma_id, self::M_STATUS, true );
		Dastyar::instance()->webhooks->send( $vendor_id, 'rma.status', array(
			'rma_id'         => (int) $rma_id,
			'local_rma_id'   => (int) get_post_meta( $rma_id, self::M_LOCAL, true ),
			'status'         => $status,
			'status_label'   => self::status_label( $status ),
			'admin_note'     => (string) get_post_meta( $rma_id, self::M_ADMIN_N, true ),
			'final'          => (string) get_post_meta( $rma_id, self::M_FINAL, true ),
			'fault'          => (string) get_post_meta( $rma_id, self::M_FAULT, true ),
			'credit'         => (float) get_post_meta( $rma_id, self::M_CREDIT, true ),
			'return_address' => 'await_item' === $status ? self::return_address() : '',
		), false );
	}

	/**
	 * ثبت نتیجه نهایی + عملکرد مالی (لغو سفارش = شارژ کیف پول طبق قوانین)
	 * @return float|WP_Error مبلغ شارژ‌شده کیف پول (0 در صورت تعویض/رد)
	 */
	public static function finalize( $rma_id, $final, $fault, $credit ) {
		$vendor_id = (int) get_post_meta( $rma_id, self::M_VENDOR, true );
		$order_id  = (int) get_post_meta( $rma_id, self::M_ORDER, true );

		if ( ! isset( self::finals()[ $final ] ) ) {
			return new WP_Error( 'dastyar_rma_bad_final', 'نتیجه نهایی نامعتبر است.' );
		}
		if ( get_post_meta( $rma_id, self::M_CREDIT, true ) > 0 && 'cancel' === $final ) {
			return new WP_Error( 'dastyar_rma_refunded', 'برای این درخواست قبلاً بازپرداخت به کیف پول انجام شده است.' );
		}

		update_post_meta( $rma_id, self::M_FINAL, $final );
		update_post_meta( $rma_id, self::M_FAULT, in_array( $fault, array( 'dastyar', 'customer' ), true ) ? $fault : '' );

		$done = 0.0;
		if ( 'cancel' === $final ) {
			$credit = max( 0, (float) $credit );
			if ( $credit <= 0 ) {
				return new WP_Error( 'dastyar_rma_bad_credit', 'برای «لغو سفارش» مبلغ بازگشتی باید بزرگ‌تر از صفر باشد.' );
			}
			$desc = sprintf( 'بازپرداخت مرجوعی #%d (سفارش #%d) — %s', $rma_id, $order_id, 'dastyar' === $fault ? 'کالا + هزینه ارسال (مقصر: دستیار)' : 'فقط مبلغ کالا (هزینه ارسال بر عهده مشتری)' );
			Dastyar::instance()->wallet->credit( $vendor_id, $credit, $order_id, $desc );
			update_post_meta( $rma_id, self::M_CREDIT, $credit );
			$done = $credit;
			self::set_status( $rma_id, 'cancelled_refunded' );
		} elseif ( 'exchange' === $final ) {
			self::set_status( $rma_id, 'exchanged' );
		} else {
			self::set_status( $rma_id, 'rejected' );
		}

		return $done;
	}

	/** خواندن امن متا آرایه‌ای: در نبود متا، (array)'' یک عنصر خالی می‌سازد ← همیشه آرایه واقعی برگردان */
	public static function meta_arr( $post_id, $key ) {
		$v = get_post_meta( $post_id, $key, true );
		return is_array( $v ) ? $v : array();
	}

	/** مبلغ پیشنهادی بازپرداخت = کالاها به قیمت تامین + (هزینه ارسال فقط اگر مقصر دستیار) */
	public static function proposed_credit( $rma_id, $fault ) {
		$order_id = (int) get_post_meta( $rma_id, self::M_ORDER, true );
		$items    = self::meta_arr( $rma_id, self::M_ITEMS );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return 0.0;
		}
		$goods = 0.0;
		foreach ( $order->get_items() as $oi ) {
			foreach ( $items as $it ) {
				$pid = (int) ( $it['product_id'] ?? 0 );
				if ( $pid === (int) $oi->get_product_id() || $pid === (int) $oi->get_variation_id() ) {
					$product = $oi->get_product();
					$goods  += ( $product ? Dastyar_Product_Export::supplier_price( $product ) : (float) $oi->get_total() / max( 1, (int) $oi->get_quantity() ) ) * (int) $it['qty'];
				}
			}
		}
		if ( 'dastyar' === $fault ) {
			$goods += (float) $order->get_shipping_total();
		}
		return round( $goods );
	}

	/** آدرس مقصد مرجوعی (انبار) — در «وضعیت و تنظیمات» مرکز */
	public static function return_address() {
		return (string) get_option( 'dastyar_return_address', '' );
	}

	/* ------------------------------------------------------------------
	 * خروجی API
	 * ---------------------------------------------------------------- */

	/** @return array */
	public static function api_payload( $rma_id, $duplicate = false ) {
		$status = (string) get_post_meta( $rma_id, self::M_STATUS, true );
		return array(
			'ok'             => true,
			'duplicate'      => $duplicate,
			'rma_id'         => (int) $rma_id,
			'local_rma_id'   => (int) get_post_meta( $rma_id, self::M_LOCAL, true ),
			'status'         => $status,
			'status_label'   => self::status_label( $status ),
			'admin_note'     => (string) get_post_meta( $rma_id, self::M_ADMIN_N, true ),
			'final'          => (string) get_post_meta( $rma_id, self::M_FINAL, true ),
			'fault'          => (string) get_post_meta( $rma_id, self::M_FAULT, true ),
			'credit'         => (float) get_post_meta( $rma_id, self::M_CREDIT, true ),
			'return_address' => 'await_item' === $status ? self::return_address() : '',
		);
	}
}
