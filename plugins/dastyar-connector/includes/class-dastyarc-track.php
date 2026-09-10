<?php
/**
 * برگه عمومی «پیگیری سفارش» برای مشتریان نهایی فروشگاه فروشنده:
 * با شماره سفارش و/یا شماره تماس → نمایش وضعیت سفارش + کد رهگیری دستیار
 *
 * برگه هنگام فعال‌سازی (و در صورت حذف، به‌صورت خودکار) ساخته می‌شود.
 * شورتکد: [dastyarc_track] — می‌توانید در هر برگه‌ای استفاده کنید.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Track {

	const PAGE_OPTION = 'dastyarc_track_page_id';

	public function __construct() {
		add_shortcode( 'dastyarc_track', array( $this, 'shortcode' ) );
		// مهم: wp_insert_post نباید در plugins_loaded اجرا شود (فایل‌های ادمین هنوز لود نشده‌اند)
		// اجرا را به admin_init می‌سپاریم تا در کنار سایر افزونه‌ها/قالب، Fatal رخ ندهد
		add_action( 'admin_init', array( __CLASS__, 'ensure_page' ), 20 );
	}

	/** ساخت برگه اگر وجود ندارد (بدون نیاز به فعال‌سازی مجدد) — Fail-Safe: هرگز Fatal نمی‌دهد */
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
					'post_title'   => 'پیگیری سفارش',
					'post_name'    => 'order-tracking',
					'post_status'  => 'publish',
					'post_content' => '[dastyarc_track]',
				),
				true
			);
			if ( $page_id && ! is_wp_error( $page_id ) ) {
				update_option( self::PAGE_OPTION, $page_id );
			}
		} catch ( \Throwable $e ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->log( 'error', 'Track page creation failed: ' . $e->getMessage(), array( 'source' => 'dastyar-connector' ) );
			}
		}
	}

	/* ------------------------------------------------------------------
	 * شورتکد و فرم
	 * ---------------------------------------------------------------- */

	public function shortcode() {
		$html = '';
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! empty( $_POST['dastyarc_track_nonce'] ) ) {
			$html .= $this->handle_lookup();
		}
		// هیرو برند (هماهنگ با تم دستیار — v1.6.0)
		$hero = '<div class="dastyarc-hero"><div class="dastyarc-hero-icon">📦</div>'
			. '<h2 class="dastyarc-hero-title">پیگیری سفارش</h2>'
			. '<p class="dastyarc-hero-sub">وضعیت ارسال سفارش خود را همین‌جا دنبال کنید</p></div>';
		return '<div class="dastyarc-wrap">' . $hero . $html . $this->form() . '</div>' . $this->styles();
	}

	protected function form() {
		ob_start();
		?>
		<form method="post" class="dastyarc-track-form">
			<?php wp_nonce_field( 'dastyarc_track', 'dastyarc_track_nonce' ); ?>
			<div class="dastyarc-track-row">
				<label>شماره سفارش</label>
				<input type="text" name="dastyarc_order_no" inputmode="numeric" placeholder="مثلاً 1052">
			</div>
			<div class="dastyarc-track-row">
				<label>شماره تماس</label>
				<input type="text" name="dastyarc_phone" inputmode="tel" placeholder="مثلاً 0912…">
			</div>
			<p class="dastyarc-track-hint">حداقل یکی از دو مورد را وارد کنید.</p>
			<button type="submit" class="dastyarc-track-btn">پیگیری سفارش</button>
		</form>
		<?php
		return ob_get_clean();
	}

	protected function styles() {
		return '<style>
			@import url("https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css");
			.dastyarc-wrap{--dc-green:#17a16d;--dc-navy:#242536;--dc-line:#e6e8ef;max-width:820px;margin:0 auto;padding:10px 0;font-family:"Vazirmatn","IRANSans",Tahoma,sans-serif}
			.dastyarc-wrap *{box-sizing:border-box;font-family:"Vazirmatn","IRANSans",Tahoma,sans-serif}
			.dastyarc-hero{background:linear-gradient(135deg,var(--dc-green),#0f7a52);border-radius:20px;padding:30px 24px;text-align:center;color:#fff;box-shadow:0 12px 34px rgba(23,161,109,.28);margin-bottom:22px}
			.dastyarc-hero-icon{font-size:42px;line-height:1;filter:drop-shadow(0 4px 8px rgba(0,0,0,.22))}
			.dastyarc-hero-title{margin:10px 0 4px;font-size:25px;font-weight:900;color:#fff;text-align:center}
			.dastyarc-hero-sub{margin:0;font-size:14px;color:#fff;text-align:center}
			.dastyarc-track-form{max-width:460px;margin:16px auto;padding:24px 26px;border:1px solid var(--dc-line);border-radius:16px;background:#fff;box-shadow:0 6px 22px rgba(16,80,55,.08);text-align:center}
			.dastyarc-track-row{margin-bottom:14px;text-align:right}
			.dastyarc-track-row label{display:block;margin-bottom:6px;font-weight:700;font-size:13.5px;color:var(--dc-navy)}
			.dastyarc-track-row input{width:100%;padding:11px 14px;border:1.5px solid #d3e7dc;border-radius:12px;direction:ltr;text-align:right;font-family:inherit;transition:.2s;background:#fbfefd}
			.dastyarc-track-row input:focus{border-color:var(--dc-green);outline:none;box-shadow:0 0 0 3px rgba(23,161,109,.14)}
			.dastyarc-track-hint{color:#6b857a;font-size:12.5px}
			.dastyarc-track-btn{background:var(--dc-green);color:#fff;border:0;padding:12px 34px;border-radius:12px;cursor:pointer;font-size:15.5px;font-weight:800;font-family:inherit;transition:.2s;box-shadow:0 5px 16px rgba(23,161,109,.3)}
			.dastyarc-track-btn:hover{background:#12865a;transform:translateY(-2px)}
			.dastyarc-track-result{max-width:520px;margin:16px auto;padding:20px 22px;border:1px solid #b3e2c6;border-radius:16px;background:#f0fff6;box-shadow:0 5px 18px rgba(23,161,109,.1)}
			.dastyarc-track-result.error{border-color:#f5c2c7;background:#fff5f5}
			.dastyarc-track-result table{width:100%;border-collapse:collapse}
			.dastyarc-track-result td{padding:8px 4px;border-bottom:1px dashed #dcefe5;color:#284436;font-size:13.5px}
			.dastyarc-track-code{font-size:19px;font-weight:800;letter-spacing:1px;direction:ltr;display:inline-block;color:var(--dc-green)}
		</style>';
	}

	/* ------------------------------------------------------------------
	 * جستجو
	 * ---------------------------------------------------------------- */

	protected function handle_lookup() {
		if ( ! wp_verify_nonce( sanitize_key( $_POST['dastyarc_track_nonce'] ), 'dastyarc_track' ) ) {
			return $this->result( null, 'نشست نامعتبر است. دوباره تلاش کنید.' );
		}

		$order_no = absint( $_POST['dastyarc_order_no'] ?? 0 );
		$phone    = self::normalize_phone( (string) ( $_POST['dastyarc_phone'] ?? '' ) );

		if ( ! $order_no && strlen( $phone ) < 10 ) {
			return $this->result( null, 'لطفاً شماره سفارش یا شماره تماس معتبر وارد کنید.' );
		}

		$order = null;
		if ( $order_no ) {
			$maybe = wc_get_order( $order_no );
			if ( $maybe && strlen( $phone ) >= 10 ) {
				// اگر هر دو داده شده، تطابق تلفن هم بررسی می‌شود
				if ( self::normalize_phone( $maybe->get_billing_phone() ) === $phone ) {
					$order = $maybe;
				} else {
					return $this->result( null, 'شماره سفارش با این شماره تماس مطابقت ندارد.' );
				}
			} elseif ( $maybe ) {
				$order = $maybe;
			}
		}
		if ( ! $order && strlen( $phone ) >= 10 ) {
			$order = $this->find_by_phone( $phone );
		}

		if ( ! $order ) {
			return $this->result( null, 'سفارشی با این مشخصات یافت نشد.' );
		}
		return $this->result( $order, '' );
	}

	/** جستجوی سفارش بر اساس تلفن صورتحساب — سازگار با HPOS و حالت کلاسیک */
 protected function find_by_phone( $phone ) {
		$hpos = false;
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}

		$ids = array();
		if ( $hpos ) {
			global $wpdb;
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}wc_order_addresses
				 WHERE address_type = 'billing' AND phone LIKE %s
				 ORDER BY order_id DESC LIMIT 5",
				'%' . $wpdb->esc_like( $phone ) . '%'
			) ); // phpcs:ignore
		} else {
			$ids = wc_get_orders( array(
				'limit'       => 5,
				'meta_key'    => '_billing_phone',
				'meta_value'  => $phone,
				'meta_compare'=> 'LIKE',
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'ids',
			) );
		}

		foreach ( (array) $ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				return $order;
			}
		}
		return null;
	}

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

	protected function result( $order, $error ) {
		if ( $error ) {
			return '<div class="dastyarc-track-result error"><strong>' . esc_html( $error ) . '</strong></div>';
		}
		if ( ! $order ) {
			return '';
		}

		$tracking = (string) $order->get_meta( '_dastyar_tracking_code' );
		$carrier  = (string) $order->get_meta( '_dastyar_tracking_carrier' );
		// کد رهگیری دستی فروشنده برای سفارش‌های غیردستیار (v1.6.0 — مورد ۷)
		$self_track   = (string) $order->get_meta( '_dastyarc_self_tracking_code' );
		$self_carrier = (string) $order->get_meta( '_dastyarc_self_tracking_carrier' );

		ob_start();
		?>
		<div class="dastyarc-track-result">
			<table>
				<tr>
					<td>شماره سفارش</td>
					<td><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></td>
				</tr>
				<tr>
					<td>تاریخ ثبت</td>
					<td><?php echo esc_html( DastyarC_Jalali::dt( $order->get_date_created(), 'Y/m/d' ) ); ?></td>
				</tr>
				<tr>
					<td>وضعیت سفارش</td>
					<td><strong><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></strong></td>
				</tr>
				<?php if ( $tracking ) : ?>
				<tr>
					<td>کد رهگیری مرسوله (دستیار)</td>
					<td>
						<span class="dastyarc-track-code"><?php echo esc_html( $tracking ); ?></span>
						<?php echo $carrier ? '<br><small>شرکت حمل: ' . esc_html( $carrier ) . '</small>' : ''; ?>
					</td>
				</tr>
				<?php endif; ?>
				<?php if ( $self_track && ! $tracking ) : ?>
				<tr>
					<td>کد رهگیری مرسوله</td>
					<td>
						<span class="dastyarc-track-code"><?php echo esc_html( $self_track ); ?></span>
						<?php echo $self_carrier ? '<br><small>روش ارسال: ' . esc_html( $self_carrier ) . '</small>' : ''; ?>
					</td>
				</tr>
				<?php endif; ?>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}
}
