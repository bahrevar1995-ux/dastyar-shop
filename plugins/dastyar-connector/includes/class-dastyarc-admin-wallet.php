<?php
/**
 * DastyarC_Admin_Wallet — trait بخش «کیف پول» پنل کانکتور (v1.12.0 — شکافتن کلاس ادمین).
 * فقط توسط DastyarC_Admin استفاده می‌شود؛ منطق نسبت به قبل هیچ تغییری نکرده است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait DastyarC_Admin_Wallet {

	/* ------------------------------------------------------------------
	 * صفحه «کیف پول» — موجودی + تراکنش‌ها + شارژ از طریق مرکز (v1.7.0)
	 * ---------------------------------------------------------------- */

	/**
	 * اسنپ‌شات کیف پول از مرکز (کش ۶۰ ثانیه‌ای تا صفحه سریع بماند) — قابل تست جداگانه.
	 * @return array{balance:float,transactions:array<int,object>}|WP_Error
	 */
	/** قالب فارسی مبلغ تومان — v1.10.1 (مشترک داشبورد/کیف پول) */
	public static function wallet_toman( $amount ) {
		return DastyarC_Jalali::num( (float) $amount ) . ' تومان';
	}

	public static function wallet_snapshot( $force = false ) {
		if ( ! DastyarC_Client::configured() ) {
			return new WP_Error( 'dastyarc_not_configured', 'ابتدا آدرس مرکز و API Key را در «دستیار شاپ ← تنظیمات دستیار» وارد و اتصال را برقرار کنید.' );
		}
		if ( ! $force ) {
			$cache = get_transient( 'dastyarc_wallet_snap' );
			if ( is_array( $cache ) ) {
				return $cache;
			}
		}
		$res = DastyarC_Client::wallet();
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$snap = array(
			'balance'      => (float) ( $res['balance'] ?? 0 ),
			'credit'       => ( '' !== (string) ( $res['credit'] ?? '' ) ) ? (float) $res['credit'] : '',
			'pending'      => ( '' !== (string) ( $res['pending'] ?? '' ) ) ? (float) $res['pending'] : '',
			'transactions' => (array) ( $res['transactions'] ?? array() ),
			'fetched'      => current_time( 'mysql' ),
		);
		set_transient( 'dastyarc_wallet_snap', $snap, 60 );
		return $snap;
	}

	/** (v1.10.0) تب کیف پول — هیرو گلاس + کارت شارژ + جدول ۵۰ تراکنش آخر */
	protected function tab_wallet() {
		// «به‌روزرسانی» دستی ← کش ۶۰ ثانیه‌ای شکسته می‌شود (قرارداد قبلی حفظ شد)
		$force = ! empty( $_GET['wallet_refresh'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'dastyarc_wallet_refresh' );
		if ( $force ) {
			delete_transient( 'dastyarc_wallet_snap' );
		}
		$snap = self::wallet_snapshot( $force );

		if ( is_wp_error( $snap ) ) {
			echo '<div class="dcx2-notice x-bad">' . esc_html( self::friendly_api_error( $snap ) ) . '</div>';
			echo '<section class="dcx2-card"><div class="in">';
			DastyarC_Ui::empty_state( 'در حال حاضر دریافت موجودی از مرکز ممکن نیست.' );
			DastyarC_Ui::btn( DastyarC_Ui::icon( 'gear', 13 ) . ' تنظیمات اتصال', self::hub_url( 'settings' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div></section>';
			return;
		}

		$balance = (float) $snap['balance'];
		?>
		<!-- هیرو موجودی -->
		<section class="dcx2-card dcx2-hero">
			<div class="hd">
				<b><?php echo DastyarC_Ui::icon( 'wallet', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> کیف پول شما در دستیار شاپ</b>
				<?php
				$refresh = wp_nonce_url( self::hub_url( 'wallet', array( 'wallet_refresh' => 1 ) ), 'dastyarc_wallet_refresh' );
				DastyarC_Ui::btn( DastyarC_Ui::icon( 'sync', 13 ) . ' به‌روزرسانی', $refresh ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<div class="tiles">
				<div class="tile"><span>موجودی کیف پول</span><b><?php echo esc_html( self::wallet_toman( $balance ) ); ?></b></div>
			</div>
		</section>

		<!-- کارت شارژ -->
		<section class="dcx2-card">
			<header><?php echo DastyarC_Ui::icon( 'wallet', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> شارژ کیف پول</header>
			<div class="in">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'dastyarc_wallet_charge' ); ?>
					<input type="hidden" name="action" value="dastyarc_wallet_charge">
					<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
						<?php
						$quick = (array) apply_filters( 'dastyarc_wallet_quick_amounts', array( 5000000, 10000000, 20000000, 50000000 ) );
						foreach ( array_filter( array_map( 'absint', $quick ) ) as $amt ) :
							?>
							<button class="dcx2-btn" type="submit" name="dastyarc_quick_amount" value="<?php echo (int) $amt; ?>">شارژ <?php echo esc_html( number_format( (int) $amt ) ); ?> تومان</button>
						<?php endforeach; ?>
					</div>
					<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
						<label style="display:flex;flex-direction:column;gap:4px;font-size:11.5px;color:var(--mut);font-weight:700">مبلغ دلخواه (تومان)
							<input type="number" min="1000" step="1000" name="amount" placeholder="مثلاً 8000000" style="min-width:220px"></label>
						<button class="dcx2-btn prime" type="submit"><?php echo DastyarC_Ui::icon( 'wallet', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> پرداخت و شارژ</button>
					</div>
				</form>
			</div>
		</section>

		<!-- تراکنش‌ها -->
		<section class="dcx2-card" id="dtx">
			<header><?php echo DastyarC_Ui::icon( 'coin', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> آخرین تراکنش‌های کیف پول</header>
			<?php
			$txns = array_slice( (array) $snap['transactions'], 0, 50 );
			if ( ! $txns ) {
				DastyarC_Ui::empty_state( 'تراکنشی ثبت نشده است.' );
			} else {
				?>
				<table class="dcx2-table">
					<thead><tr><th>شرح</th><th>مبلغ</th><th>موجودی بعد</th><th>زمان</th></tr></thead>
					<tbody>
					<?php
					foreach ( $txns as $tx ) :
						$tx    = is_object( $tx ) ? $tx : (object) $tx;
						$type  = (string) ( $tx->type ?? '' );
						$amt   = (float) ( $tx->amount ?? 0 );
						$after = (float) ( $tx->balance_after ?? 0 );
						$when  = (string) ( $tx->date_fa ?? '' );
						if ( '' === $when ) {
							$when = DastyarC_Jalali::dt( (string) ( $tx->created_at ?? '' ) );
						}
						$ok = ( 'credit' === $type );
						?>
						<tr>
							<td><?php echo esc_html( (string) ( $tx->description ?? ( $ok ? 'واریز' : 'برداشت' ) ) ); ?></td>
							<td><b class="dcx2-pill dcx2-pill--<?php echo $ok ? 'g' : 'r'; ?>"><?php echo $ok ? '+' : '−'; ?> <?php echo esc_html( number_format( $amt ) ); ?> تومان</b></td>
							<td style="color:var(--mut);font-size:11.5px"><?php echo esc_html( number_format( $after ) ); ?> تومان</td>
							<td style="color:var(--mut);font-size:11px"><?php echo esc_html( $when ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php
			}
			?>
		</section>
		<?php
	}

	/** هندلر شارژ کیف پول ← ساخت سفارش شارژ روی مرکز و هدایت فروشنده به درگاه (v1.7.0)
	 * v1.7.1: پشتیبانی از دکمه‌های شارژ فوری (dastyarc_quick_amount) + نرمال‌سازی ارقام فارسی/جداکننده هزارگان + پیام خطای فارسی راهنما */
	public function handle_wallet_charge() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), 'dastyarc_wallet_charge' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		// دکمه شارژ فوری (اگر کلیک شده) بر فیلد مبلغ دلخواه اولویت دارد
		$quick_raw = (string) ( $_POST['dastyarc_quick_amount'] ?? '' );
		$raw       = '' !== trim( $quick_raw ) ? $quick_raw : ( $_POST['amount'] ?? 0 );
		$amount    = self::normalize_amount( $raw );
		$back      = admin_url( 'admin.php?page=dastyarc-hub&tab=wallet' );
		if ( $amount < 1000 ) {
			$this->notice( 'مبلغ شارژ معتبر نیست (حداقل ۱٬۰۰۰ تومان).', 'error' );
			wp_safe_redirect( $back );
			exit;
		}
		$res = DastyarC_Client::wallet_charge( $amount );
		if ( is_wp_error( $res ) ) {
			$this->notice( 'خطا در ساخت سفارش شارژ: ' . self::friendly_api_error( $res ), 'error' );
			wp_safe_redirect( $back );
			exit;
		}
		$url = (string) ( $res['payment_url'] ?? '' );
		if ( '' === $url ) {
			$this->notice( 'پاسخ مرکز فاقد لینک پرداخت بود؛ دوباره تلاش کنید.', 'error' );
			wp_safe_redirect( $back );
			exit;
		}
		// مقصد دامنه مرکز است ← wp_safe_redirect فقط هاست داخلی را می‌پذیرد؛ پس ریدایرکت مستقیم
		wp_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * نرمال‌سازی مبلغ ورودی (v1.7.1): ارقام فارسی/عربی ← لاتین، حذف جداکننده هزارگان و فاصله.
	 * تا تایپ «۵٬۰۰۰٬۰۰۰» یا «8,000,000» هم به‌درستی 5000000/8000000 فهمیده شود.
	 */
	public static function normalize_amount( $raw ) {
		$s = strtr( (string) $raw, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			'٬' => '',  '،' => '',  ',' => '',  ' ' => '',  '٫' => '.',
		) );
		return (float) preg_replace( '/[^\d.]/', '', $s );
	}
}
