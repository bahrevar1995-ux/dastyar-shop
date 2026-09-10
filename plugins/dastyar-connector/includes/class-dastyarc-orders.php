<?php
/**
 * انتقال سفارش استاندارد WooCommerce فروشنده به سایت مرکزی
 * + وضعیت سفارش سفارشی «ارسال شده به دستیارشاپ» (wc-dastyar-sent)
 *
 * حالت انتقال (تنظیم dastyarc_order_mode):
 * - auto   → ارسال خودکار به‌محض رسیدن سفارش به وضعیت‌های انتخابی (رفتار پیش‌فرض قبلی، بدون تغییر)
 * - manual → ارسال فقط با دکمه/اقدام دستی فروشنده از لیست سفارش‌ها یا متاباکس سفارش
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Orders {

	const SENT_STATUS = 'dastyar-sent'; // نامک وضعیت (بدون wc-)

	public function __construct() {
		// هوک‌های ارسال خودکار فقط در حالت «خودکار» ثبت می‌شوند؛ در حالت «دستی» هیچ ارسال خودکاری اتفاق نمی‌افتد
		if ( 'manual' !== get_option( 'dastyarc_order_mode', 'auto' ) ) {
			foreach ( (array) get_option( 'dastyarc_send_statuses', array( 'processing' ) ) as $status ) {
				add_action( 'woocommerce_order_status_' . sanitize_key( $status ), array( $this, 'maybe_send' ), 10, 1 );
			}
		}
		add_action( 'admin_post_dastyarc_send_order', array( $this, 'handle_manual_send' ) );

		// v1.7.12 — همگام‌سازی لغو: سفارش متصل به مرکز که لغو می‌شود ← در مرکز هم «لغو شده» شود
		// v1.8.0 — روی status_changed سوار شدیم تا «وضعیت قبلی» هم در دسترس باشد: اگر مرکز لغو را نپذیرفت
		// (مثلاً سفارش «تحویل پست شده») وضعیت محلی به‌صورت خودکار به حالت قبل برمی‌گردد.
		add_action( 'woocommerce_order_status_changed', array( $this, 'push_cancel_wc' ), 20, 4 );
		add_action( 'admin_notices', array( $this, 'cancel_denied_notice' ) );

		// وضعیت سفارش سفارشی «ارسال شده به دستیارشاپ»
		add_action( 'init', array( $this, 'register_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_status' ) );
		add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'paid_statuses' ) );

		// نشانه‌گذاری سفارش‌هایی که قلم دستیار دارند (برای ستاره سبز + فیلتر لیست سفارش‌ها)
		add_action( 'woocommerce_checkout_create_order', array( $this, 'mark_dastyar_order' ) );
	}

	/** پرچم _dastyarc_has_items هنگام ثبت سفارش از چک‌اوت (بدون save مجدد — در همان فلو ذخیره می‌شود) */
	public function mark_dastyar_order( $order ) {
		if ( $order instanceof WC_Order && self::has_remote_items( $order ) ) {
			$order->update_meta_data( '_dastyarc_has_items', 1 );
		}
	}

	/* ------------------------------------------------------------------
	 * وضعیت سفارش سفارشی
	 * ---------------------------------------------------------------- */

	public function register_status() {
		register_post_status( 'wc-' . self::SENT_STATUS, array(
			'label'                     => 'ارسال شده به دستیارشاپ',
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'ارسال شده به دستیارشاپ <span class="count">(%s)</span>', 'ارسال شده به دستیارشاپ <span class="count">(%s)</span>' ),
		) );
	}

	public function add_status( $statuses ) {
		$new = array();
		foreach ( $statuses as $slug => $label ) {
			$new[ $slug ] = $label;
			if ( 'wc-processing' === $slug ) {
				$new[ 'wc-' . self::SENT_STATUS ] = 'ارسال شده به دستیارشاپ';
			}
		}
		if ( ! isset( $new[ 'wc-' . self::SENT_STATUS ] ) ) {
			$new[ 'wc-' . self::SENT_STATUS ] = 'ارسال شده به دستیارشاپ';
		}
		return $new;
	}

	/** این وضعیت به معنای «پرداخت‌شده» است تا رفتار ووکامرس (ایمیل/گزارش/قابلیت‌ها) حفظ شود */
	public function paid_statuses( $statuses ) {
		$statuses[] = self::SENT_STATUS;
		return $statuses;
	}

	/* ------------------------------------------------------------------
	 * منطق ارسال (عملکرد قبلی حفظ شده)
	 * ---------------------------------------------------------------- */

	public static function has_remote_items( WC_Order $order ) {
		foreach ( $order->get_items() as $item ) {
			$var_id = $item->get_variation_id();
			$pid    = $item->get_product_id();
			if ( $var_id && get_post_meta( $var_id, '_dastyar_remote_var_id', true ) ) {
				// (v1.7.8) والدِ جداشده ← قلم جزو دستیار حساب نمی‌شود
				$parent = (int) wp_get_post_parent_id( $var_id );
				if ( $parent && get_post_meta( $parent, '_dastyarc_detached', true ) ) {
					continue;
				}
				return true;
			}
			if ( $pid && get_post_meta( $pid, '_dastyar_remote_id', true )
				&& ! get_post_meta( $pid, '_dastyarc_detached', true ) ) { // (v1.7.8)
				return true;
			}
		}
		return false;
	}

	public function maybe_send( $order_id ) {
		// دفاع دولایه: حتی اگر هوک به هر دلیلی فراخوانی شد، در حالت «دستی» هیچ ارسال خودکاری انجام نشود
		if ( 'manual' === get_option( 'dastyarc_order_mode', 'auto' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_dastyarc_sent' ) || ! self::has_remote_items( $order ) ) {
			return;
		}
		self::send( $order );
	}

	/**
	 * v1.8.0 — نسخه هوک همگام‌سازی لغو که «وضعیت قبلی» را هم می‌داند (برای بازگشت خودکار هنگام رد مرکز).
	 */
	public function push_cancel_wc( $order_id, $from, $to, $order ) {
		if ( 'cancelled' !== (string) $to ) {
			return;
		}
		$this->push_cancel( $order_id, (string) $from );
	}

	/**
	 * v1.7.12 — پس از لغو سفارشِ متصل به مرکز، لغو را به مرکز همگام می‌کنیم.
	 * v1.8.0 — اگر مرکز لغو را نپذیرفت (کد dastyar_locked؛ مثلاً «تحویل پست شده») وضعیت محلی
	 * به‌صورت خودکار به حالت قبلی برمی‌گردد و اعلان شفاف به فروشنده نشان داده می‌شود.
	 * سفارش‌هایی که هرگز به مرکز نرفته‌اند (بدون شناسه مرکزی) نادیده گرفته می‌شوند؛
	 * مستقل از حالت ارسال خودکار/دستی — سینک وضعیت با ارسال سفارش فرق دارد.
	 *
	 * @param int    $order_id شناسه سفارش محلی
	 * @param string $from     وضعیت قبلی (در صورت رد مرکز، به همین وضعیت برمی‌گردیم)
	 */
	public function push_cancel( $order_id, $from = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$remote = (int) $order->get_meta( '_dastyarc_remote_order_id' );
		if ( ! $remote ) {
			return; // سفارش در مرکز ثبت نشده — چیزی برای همگام‌سازی نیست
		}
		$result = DastyarC_Client::update_order_status( $remote, 'cancelled' );
		if ( is_wp_error( $result ) ) {
			if ( 'dastyar_locked' === $result->get_error_code() ) {
				// مرکز لغو را نپذیرفت ← یادداشت شفاف + برگشت وضعیت محلی به حالت قبل + اعلان مدیریت
				$msg = $result->get_error_message();
				DastyarC::log( sprintf( 'Push cancel denied (order #%d → remote #%d): %s', $order_id, $remote, $msg ), 'error' );
				$order->add_order_note( 'مرکز لغو سفارش را نپذیرفت: ' . $msg );
				if ( '' !== (string) $from && 'cancelled' !== (string) $from ) {
					$order->set_status( $from, 'بازگشت خودکار به وضعیت قبلی پس از رد لغو توسط مرکز.' );
					$order->save();
				}
				set_transient( 'dastyarc_cancel_denied_' . get_current_user_id(), (string) $msg, 60 );
				return;
			}
			DastyarC::log( sprintf( 'Push cancel failed (order #%d → remote #%d): %s', $order_id, $remote, $result->get_error_message() ), 'error' );
			$order->add_order_note( 'همگام‌سازی لغو با مرکز ناموفق بود؛ لطفاً از پنل دستیار پیگیری کنید.' );
			return;
		}
		if ( ! empty( $result['ok'] ) && empty( $result['changed'] ) ) {
			return; // مرکز از قبل لغو بود — سکوت، بدون تکرار یادداشت
		}
		DastyarC::log( sprintf( 'Push cancel OK (order #%d → remote #%d)', $order_id, $remote ) );
		$order->add_order_note( 'لغو سفارش با سایت مرکزی همگام شد.' );
	}

	/** اعلان رد لغو توسط مرکز — در پیشخوان فروشنده (یک‌بارمصرف) */
	public function cancel_denied_notice() {
		$msg = get_transient( 'dastyarc_cancel_denied_' . get_current_user_id() );
		if ( ! $msg ) {
			return;
		}
		delete_transient( 'dastyarc_cancel_denied_' . get_current_user_id() );
		printf(
			'<div class="notice notice-error"><p><strong>لغو سفارش انجام نشد.</strong> %s وضعیت سفارش به حالت قبلی برگردانده شد.</p></div>',
			esc_html( (string) $msg ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- متن کنترل‌شده است
		);
	}

	/** برچسب فارسی وضعیت سفارش — یک‌جا برای پیام‌های لغو (v1.8.0) */
	public static function status_label( $slug ) {
		$slug = preg_replace( '/^wc-/', '', (string) $slug );
		$map  = array(
			'pending'             => 'در انتظار پرداخت',
			'processing'          => 'در حال انجام',
			'on-hold'             => 'در انتظار بررسی',
			'completed'           => 'تکمیل شده',
			'cancelled'           => 'لغو شده',
			'refunded'            => 'مرجوع شده',
			'failed'              => 'ناموفق',
			'posted'              => 'تحویل پست شده (ارسال شده به پست)',
			self::SENT_STATUS     => 'ارسال شده به دستیارشاپ',
		);
		if ( isset( $map[ $slug ] ) ) {
			return $map[ $slug ];
		}
		$label = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( 'wc-' . $slug ) : '';
		return ( '' === (string) $label || $label === 'wc-' . $slug ) ? $slug : $label;
	}

	public function handle_manual_send() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$order_id = (int) ( $_GET['order_id'] ?? 0 );
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_send_order_' . $order_id ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$order = wc_get_order( $order_id );
		if ( $order && ! $order->get_meta( '_dastyarc_sent' ) ) {
			self::send( $order );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	/**
	 * ساخت payload و ارسال به مرکز
	 * @return bool|WP_Error
	 */
	public static function send( WC_Order $order ) {
		$items = array();
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

			// (v1.7.8) قلمِ متعلق به محصول «از دراپ‌شیپینگ خارج‌شده» ← فروشنده خودش ارسال می‌کند؛ به مرکز منتقل نمی‌شود
			if ( $var_id ) {
				$parent = (int) wp_get_post_parent_id( $var_id );
				if ( $parent && get_post_meta( $parent, '_dastyarc_detached', true ) ) {
					continue;
				}
			}
			if ( $pid && get_post_meta( $pid, '_dastyarc_detached', true ) ) {
				continue;
			}

			$qty     = max( 1, (int) $item->get_quantity() );
			$items[] = array(
				'product_id'   => $remote_prod,
				'variation_id' => $remote_var,
				'quantity'     => $qty,
				'price'        => round( (float) $item->get_total() / $qty, wc_get_price_decimals() ),
			);
		}

		if ( ! $items ) {
			return new WP_Error( 'dastyarc_no_items', 'هیچ قلم دستیاری در سفارش وجود ندارد.' );
		}

		$shipping = array();
		foreach ( $order->get_shipping_methods() as $ship ) {
			$shipping[] = array(
				'method_title' => $ship->get_method_title(),
				'total'        => (float) $ship->get_total(),
			);
		}

		$payload = array(
			'remote_order_id'     => $order->get_id(),
			'remote_order_number' => $order->get_order_number(),
			'remote_site'         => home_url(),
			'billing'             => $order->get_address( 'billing' ),
			'shipping'            => $order->get_address( 'shipping' ),
			'customer_note'       => $order->get_customer_note(),
			'currency'            => $order->get_currency(),
			'items'               => $items,
			'shipping_lines'      => $shipping,
		);

		$result = DastyarC_Client::create_order( apply_filters( 'dastyarc_order_payload', $payload, $order ) );

		if ( is_wp_error( $result ) ) {
			$order->add_order_note( 'ارسال سفارش به دستیار ناموفق بود: ' . $result->get_error_message() );
			DastyarC::log( 'Order send failed #' . $order->get_id() . ': ' . $result->get_error_message(), 'error' );
			return $result;
		}

		$order->update_meta_data( '_dastyarc_sent', 1 );
		$order->update_meta_data( '_dastyarc_has_items', 1 );
		$order->update_meta_data( '_dastyarc_remote_order_id', (int) $result['order_id'] );
		$order->update_meta_data( '_dastyarc_remote_order_number', (string) $result['number'] );
		$order->add_order_note( sprintf(
			'سفارش به دستیار شاپ ارسال شد (سفارش مرکزی #%s — وضعیت: %s).',
			$result['number'],
			$result['status']
		) );

		// تغییر وضعیت محلی به «ارسال شده به دستیارشاپ»
		if ( in_array( $order->get_status(), array( 'processing', 'on-hold' ), true ) ) {
			$order->set_status( self::SENT_STATUS );
		}

		$order->save();

		do_action( 'dastyarc_order_sent', $order->get_id(), $result );
		return true;
	}

	/** ثبت درخواست مرجوعی در مرکز */
	public static function request_refund( $order_id, $amount, $reason ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'dastyarc_no_order', 'سفارش یافت نشد.' );
		}
		$remote = (int) $order->get_meta( '_dastyarc_remote_order_id' );
		if ( ! $remote ) {
			return new WP_Error( 'dastyarc_not_sent', 'این سفارش هنوز به مرکز ارسال نشده است.' );
		}
		if ( $amount <= 0 ) {
			return new WP_Error( 'dastyarc_bad_amount', 'مبلغ معتبر نیست.' );
		}
		$result = DastyarC_Client::refund_request( $remote, $amount, $reason );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$order->add_order_note( sprintf( 'درخواست مرجوعی به مرکز ارسال شد — مبلغ: %s — دلیل: %s', wc_price( $amount ), $reason ) );
		return true;
	}
}
