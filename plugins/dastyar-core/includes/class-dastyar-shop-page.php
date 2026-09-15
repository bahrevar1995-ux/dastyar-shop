<?php
/**
 * صفحه محصول فروشگاه مرکزی — منطق نمایش بر اساس نقش کاربری (v1.5.1)
 *
 * - مهمان / نقش عمومی: نه قیمت، نه سبد خرید ← باکس «ورود / ثبت نام» (مشاهده قیمت همکاری فقط برای همکاران)
 * - نقش dastyar_vendor (فروشنده دستیار): قیمت همکاری نمایش داده می‌شود و به‌جای «افزودن به سبد خرید»
 *   دکمه «افزودن به فروشگاه من» است؛ کلیک = پوش وب‌هوک امضاشده به سایت خودش (product.import)
 *   و اگر قبلاً اضافه شده ← «✔ اضافه شده به فروشگاه شما»
 * - مدیر فروشگاه (manage_woocommerce): هیچ قفلی اعمال نمی‌شود (تست/مدیریت مثل قبل)
 *
 * رعایت رده‌بندی محرمانه: محصول خارج از رده فروشنده دکمه فعال نمی‌گیرد (بدون افشای وجود رده).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Shop_Page {

	/** متای کاربر مرکزی فروشنده: لیست شناسه‌های محصول مرکزی که به فروشگاهش اضافه شده */
	const IMPORTED_META = '_dastyar_imported_pids';

	public function __construct() {
		// قفل نمایشی فرانت (فیلترهای استاندارد ووکامرس — چرخ دوباره اختراع نمی‌شود)
		add_filter( 'woocommerce_is_purchasable', array( $this, 'lock_purchasable' ), 999, 2 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'lock_price' ), 999, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_box' ), 35 );

		// دکمه «افزودن به فروشگاه من»
		add_action( 'wp_ajax_dastyar_import_to_shop', array( $this, 'ajax_import' ) );
	}

	/* ------------------------------------------------------------------
	 * نقش‌ها
	 * ---------------------------------------------------------------- */

	/** آیا کاربر فعلی فروشنده دستیار است؟ (شناسه کاربر یا 0) */
	public static function current_vendor_id() {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return 0;
		}
		return in_array( 'dastyar_vendor', (array) $user->roles, true ) ? (int) $user->ID : 0;
	}

	/** آیا نمایش صفحه محصول برای این کاربر قفل می‌شود؟ (مدیر فروشگاه آزاد است) */
	public static function is_locked() {
		return ! current_user_can( 'manage_woocommerce' );
	}

	/* ------------------------------------------------------------------
	 * فیلترهای نمایشی
	 * ---------------------------------------------------------------- */

	/** حذف دکمه افزودن به سبد خرید در فرانت برای مهمان/عمومی/فروشنده (به‌جای آن دکمه‌های خودمان) */
	public function lock_purchasable( $purchasable, $product ) {
		if ( is_admin() ) {
			return $purchasable; // فقط نمایش فرانت قفل می‌شود — ساخت سفارش برنامه‌ای/REST دست‌نخورده
		}
		if ( ! self::is_locked() ) {
			return $purchasable;
		}
		return false;
	}

	/** پنهان‌کردن قیمت از مهمان و نقش عمومی — فروشنده قیمت همکاری را می‌بیند */
	public function lock_price( $price_html, $product ) {
		if ( is_admin() || ! self::is_locked() ) {
			return $price_html;
		}
		return self::current_vendor_id() ? $price_html : '';
	}

	/* ------------------------------------------------------------------
	 * باکس جایگزین در صفحه تکی محصول
	 * ---------------------------------------------------------------- */

	public function render_box() {
		if ( is_admin() || ! self::is_locked() ) {
			return;
		}
		global $product;
		if ( ! $product instanceof WC_Product && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$uid = self::current_vendor_id();
		if ( ! $uid ) {
			$this->render_guest_box();
			return;
		}
		$this->render_vendor_box( $uid, (int) $product->get_id() );
	}

	/** باکس مهمان/عمومی — ابزارهای فروشنده «نمایش داده ولی خاموش» می‌شوند (v1.9.5: تار و غیرقابل‌کلیک) + دعوت به ورود */
	protected function render_guest_box() {
		$login_url = function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'myaccount' ) > 0
			? get_permalink( wc_get_page_id( 'myaccount' ) )
			: home_url( '/my-account/' );

		echo '<div class="dastyar-guest-lock" style="background:#e9f6ee;border:1.5px solid #bfe6d2;border-radius:14px;padding:26px 22px;margin:14px 0;text-align:center;box-sizing:border-box">';
		// v1.9.5 (بازخورد کاربر) — ابزار فروشنده برای کاربر معمولی حذف نمی‌شود؛ تار و غیرقابل‌کلیک دیده می‌شود تا انگیزه ورود/عضویت بسازد
		echo '<span style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #cde3d6;color:#5b7a6b;border-radius:999px;padding:4px 12px;font-size:11.5px;font-weight:800;margin:0 0 14px">' . $this->svg_lock() . ' ویژه فروشندگان دستیار</span><br>';
		echo '<span aria-disabled="true" title="ابزار فروشندگان دستیار — با ورود فعال می‌شود" style="display:inline-flex;align-items:center;gap:8px;background:#eef1f4;color:#9aa2ad;border:none;border-radius:10px;padding:12px 30px;font-size:15px;font-weight:700;line-height:1.6;cursor:not-allowed;opacity:.75;margin:0 0 16px">' . $this->svg_receipt() . ' ثبت سفارش دستی این محصول</span>';
		echo '<p style="margin:0 0 18px;color:#166b47;font-size:15px;font-weight:700;line-height:2">برای مشاهده قیمت همکاری و فعال شدن ابزارهای فروش<br>وارد حساب کاربری خود شوید.</p>';
		echo '<a class="button" href="' . esc_url( $login_url ) . '" style="display:inline-block;background:#17a16d;color:#fff;border:none;border-radius:10px;padding:11px 42px;font-size:15px;font-weight:700;text-decoration:none;line-height:1.6">ورود / ثبت نام</a>';
		echo '</div>';
	}

	/** آیکون قفل خطی — پیل «ویژه فروشندگان دستیار» (بدون اموجی) */
	protected function svg_lock() {
		return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="flex:none"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>';
	}

	/** باکس فروشنده — دکمه «افزودن به فروشگاه من» یا وضعیت «اضافه شده» */
	protected function render_vendor_box( $uid, $pid ) {
		// رده‌بندی محرمانه: خارج از رده ⇒ بدون دکمه فعال (بدون افشای رده)
		if ( ! Dastyar_Tiers::can_sync( $uid, $pid ) ) {
			echo '<div class="dastyar-vendor-box" style="background:#f7f8f7;border:1.5px dashed #d5d9d6;border-radius:14px;padding:20px;margin:14px 0;text-align:center">'
				. '<p style="margin:0;color:#6c757d;font-size:13.5px;font-weight:600">این محصول در حال حاضر برای فروشگاه شما قابل افزودن نیست.</p></div>';
			return;
		}

		if ( self::is_imported( $uid, $pid ) ) {
			echo '<div class="dastyar-vendor-box" style="background:#e9f6ee;border:1.5px solid #bfe6d2;border-radius:14px;padding:16px 20px;margin:14px 0;text-align:center">'
				. '<span style="display:inline-flex;align-items:center;gap:8px;background:#17a16d;color:#fff;border-radius:10px;padding:10px 26px;font-size:14.5px;font-weight:700;opacity:.85">✔ اضافه شده به فروشگاه شما</span></div>';
			return;
		}

		$nonce = wp_create_nonce( 'dastyar_import_shop_' . $pid );

		// (v1.7.3 — بازخورد کاربر، مورد ۴) فروشنده بدون سایت متصل:
		// به‌جای دکمه وارداتِ بی‌نتیجه، دکمه «ثبت سفارش دستی این محصول» ← پاپ‌آپِ همان ویزارد ۴ مرحله‌ای پنل.
		$site = Dastyar::instance()->vendors->site_url( $uid );
		if ( ! $site && class_exists( 'Dastyar' ) && Dastyar::instance()->manual_order && Dastyar::instance()->vendors->is_vendor( $uid ) ) {
			echo '<div class="dastyar-vendor-box" style="background:#e9f6ee;border:1.5px solid #bfe6d2;border-radius:14px;padding:20px;margin:14px 0;text-align:center">';
			echo '<p style="margin:0 0 12px;color:#166b47;font-size:13.5px;font-weight:600">بدون نیاز به فروشگاه، همین‌جا سفارش این محصول را برای مشتری‌تان ثبت کنید:</p>';
			$_p    = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
			$pname = $_p ? $_p->get_name() : get_the_title( $pid );
			printf(
				'<button type="button" class="dastyar-mo-open" data-name="%s" style="background:#17a16d;color:#fff;border:none;border-radius:10px;padding:12px 30px;font-size:15px;font-weight:700;cursor:pointer;line-height:1.6;display:inline-flex;align-items:center;gap:8px">%s ثبت سفارش دستی این محصول</button>',
				esc_attr( $pname ),
				$this->svg_receipt()
			);
			echo '<span class="dastyar-import-sub" style="display:block;margin-top:10px;font-size:12px;color:#4e615a">همان ۴ مرحله پنل کاربری: گیرنده ← محصول ← بازبینی ← حمل‌ونقل و پرداخت</span>';
			echo '</div>';
			$this->manual_order_modal_once( $uid );
			return;
		}

		echo '<div class="dastyar-vendor-box" style="background:#e9f6ee;border:1.5px solid #bfe6d2;border-radius:14px;padding:20px;margin:14px 0;text-align:center">';
		echo '<p style="margin:0 0 12px;color:#166b47;font-size:13.5px;font-weight:600">این محصول را مستقیم به فروشگاه خودتان اضافه کنید:</p>';
		printf(
			'<button type="button" class="dastyar-import-btn" data-pid="%d" data-nonce="%s" style="background:#17a16d;color:#fff;border:none;border-radius:10px;padding:12px 34px;font-size:15px;font-weight:700;cursor:pointer;line-height:1.6;display:inline-flex;align-items:center;gap:8px">%s افزودن به فروشگاه من</button>',
			(int) $pid,
			esc_attr( $nonce ),
			$this->svg_bag()
		);
		echo '<span class="dastyar-import-msg" style="display:block;margin-top:10px;font-size:12.5px;color:#555"></span>';
		echo '</div>';

		$this->import_js_once();
	}

	/** اسکریپت AJAX دکمه — فقط یک‌بار در صفحه */
	protected function import_js_once() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<script>
		(function(){
			var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			document.querySelectorAll('.dastyar-import-btn').forEach(function(btn){
				btn.addEventListener('click', function(){
					var box = btn.closest('.dastyar-vendor-box');
					var msg = box ? box.querySelector('.dastyar-import-msg') : null;
					btn.disabled = true;
					if (msg) { msg.textContent = 'در حال افزودن به فروشگاه شما…'; }
					var fd = new FormData();
					fd.append('action', 'dastyar_import_to_shop');
					fd.append('pid', btn.getAttribute('data-pid'));
					fd.append('nonce', btn.getAttribute('data-nonce'));
					fetch(ajax, { method:'POST', credentials:'same-origin', body: fd })
						.then(function(r){ return r.json(); })
						.then(function(res){
							if (res && res.success) {
								btn.classList.add('dastyar-imported');
								btn.textContent = '✔ اضافه شده به فروشگاه شما';
								btn.style.opacity = '.85';
								if (msg) { msg.textContent = ''; }
							} else {
								btn.disabled = false;
								if (msg) { msg.textContent = (res && res.data && res.data.message) ? res.data.message : 'خطا در افزودن؛ دوباره تلاش کنید.'; }
							}
						})
						.catch(function(){
							btn.disabled = false;
							if (msg) { msg.textContent = 'خطای ارتباط؛ دوباره تلاش کنید.'; }
						});
				});
			});
		})();
		</script>
		<?php
	}

	/* ------------------------------------------------------------------
	 * AJAX: پوش محصول به فروشگاه خود فروشنده
	 * ---------------------------------------------------------------- */

	public function ajax_import() {
		$pid = (int) ( $_POST['pid'] ?? 0 );
		check_ajax_referer( 'dastyar_import_shop_' . $pid, 'nonce' );

		$uid = self::current_vendor_id();
		if ( ! $uid ) {
			wp_send_json_error( array( 'message' => 'برای افزودن به فروشگاه، ابتدا با حساب فروشنده وارد شوید.' ), 403 );
		}

		$product = wc_get_product( $pid );
		if ( ! $product || 'product' !== get_post_type( $pid ) ) {
			wp_send_json_error( array( 'message' => 'محصول یافت نشد.' ), 404 );
		}

		// رده‌بندی محرمانه — بدون افشای جزئیات
		if ( ! Dastyar_Tiers::can_sync( $uid, $pid ) ) {
			wp_send_json_error( array( 'message' => 'این محصول در حال حاضر برای فروشگاه شما قابل افزودن نیست.' ), 403 );
		}

		$site = Dastyar::instance()->vendors->site_url( $uid );
		if ( ! $site ) {
			wp_send_json_error( array( 'message' => 'ابتدا فروشگاه خود را با کلید API به دستیار متصل کنید.' ), 400 );
		}

		// پوش رویداد product.import — سمت فروشگاه همان جریان استاندارد import اجرا می‌شود (منتشر)
		$sent = Dastyar::instance()->webhooks->send( $uid, 'product.import', array( 'product_id' => $pid ) );
		if ( true !== $sent ) {
			$msg = is_wp_error( $sent ) ? $sent->get_error_message() : 'اتصال برقرار نشد.';
			wp_send_json_error( array( 'message' => 'اتصال به فروشگاه شما ناموفق بود (' . $msg . '). کمی بعد دوباره تلاش کنید.' ), 502 );
		}

		self::mark_imported( $uid, $pid );
		wp_send_json_success( array( 'message' => 'محصول به فروشگاه شما اضافه شد.' ) );
	}

	/* ------------------------------------------------------------------
	 * سوابق «به فروشگاه اضافه شده» — از دو مسیر به‌روز می‌شود:
	 * ۱) دکمه صفحه محصول مرکز (این فایل)   ۲) دریافت محصول توسط API (پنل خود فروشنده — class-dastyar-rest)
	 * ---------------------------------------------------------------- */

	public static function is_imported( $uid, $pid ) {
		foreach ( (array) get_user_meta( (int) $uid, self::IMPORTED_META, true ) as $p ) {
			if ( (int) $p === (int) $pid ) {
				return true;
			}
		}
		return false;
	}

	public static function mark_imported( $uid, $pid ) {
		$uid = (int) $uid;
		$pid = (int) $pid;
		if ( ! $uid || ! $pid ) {
			return;
		}
		$ids = array_map( 'intval', (array) get_user_meta( $uid, self::IMPORTED_META, true ) );
		if ( ! in_array( $pid, $ids, true ) ) {
			$ids[] = $pid;
			update_user_meta( $uid, self::IMPORTED_META, array_slice( $ids, -500 ) ); // سقف ۵۰۰ رکورد اخیر
		}
	}

	/* ------------------------------------------------------------------
	 * آیکون‌های SVG خطی (بدون اموجی — درخواست کاربر)
	 * ---------------------------------------------------------------- */

	/** آیکون کیسه/فروشگاه — دکمه «افزودن به فروشگاه من» */
	protected function svg_bag() {
		return '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="flex:none"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
	}

	/** آیکون رسید/فاکتور — دکمه «ثبت سفارش دستی این محصول» */
	protected function svg_receipt() {
		return '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="flex:none"><path d="M6 2h12a1 1 0 0 1 1 1v19l-3-2-3 2-3-2-3 2V3a1 1 0 0 1 1-1z"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>';
	}

	/* ------------------------------------------------------------------
	 * پاپ‌آپ «ثبت سفارش دستی» (مورد ۴ بازخورد v1.7.3) — فقط برای فروشنده بدون سایت.
	 * چرخ دوباره اختراع نشده: بدنه مودال دقیقاً همان ویزارد ۴ مرحله‌ای پنل است
	 * (Dastyar_Manual_Order::render_account) و همه تعامل‌ها از AJAX موجود dastyar_moa.
	 * ---------------------------------------------------------------- */
	protected function manual_order_modal_once( $uid ) {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<style>
		.dastyar-mo-modal[hidden]{display:none!important}
		.dastyar-mo-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:18px;box-sizing:border-box}
		.dastyar-mo-overlay{position:absolute;inset:0;background:rgba(15,20,25,.55)}
		.dastyar-mo-dialog{position:relative;background:#fff;border-radius:16px;max-width:960px;width:100%;max-height:86vh;overflow-y:auto;padding:22px;box-shadow:0 24px 70px rgba(0,0,0,.35);box-sizing:border-box}
		.dastyar-mo-close{position:absolute;top:10px;inset-inline-start:10px;z-index:2;background:#f1f3f2;border:none;width:34px;height:34px;border-radius:50%;font-size:15px;cursor:pointer;color:#444;line-height:1;display:flex;align-items:center;justify-content:center;padding:0}
		.dastyar-mo-close:hover{background:#e2e7e4}
		@media (max-width:640px){.dastyar-mo-dialog{padding:16px 12px}}
		</style>
		<div class="dastyar-mo-modal" hidden aria-hidden="true">
			<div class="dastyar-mo-overlay"></div>
			<div class="dastyar-mo-dialog" role="dialog" aria-modal="true" aria-label="ثبت سفارش دستی">
				<button type="button" class="dastyar-mo-close" aria-label="بستن">✕</button>
				<div class="dastyar-mo-body">
					<?php Dastyar::instance()->manual_order->render_account( $uid ); ?>
				</div>
			</div>
		</div>
		<script>
		(function(){
			function modal(){ return document.querySelector('.dastyar-mo-modal'); }
			function open(btn){
				var m = modal(); if (!m) return;
				m.hidden = false; m.setAttribute('aria-hidden','false');
				document.documentElement.style.overflow = 'hidden';
				// اگر گیرنده قبلاً ذخیره شده، مستقیم برو به مرحله انتخاب محصول
				if (m.querySelector('#dastyar-wizard .dastyar-rec-summary')){
					var nx = m.querySelector('.dastyar-next[data-to="2"]');
					if (nx) nx.click();
				}
				// نام محصول همین صفحه را در جستجو بگذار تا فوری پیدا شود
				var name = btn.getAttribute('data-name') || '';
				if (name){
					var si = m.querySelector('#dastyar-mo-search');
					if (si) si.value = name;
					var sb = m.querySelector('#dastyar-mo-search-btn');
					if (sb) sb.click();
				}
			}
			function close(){
				var m = modal(); if (!m) return;
				m.hidden = true; m.setAttribute('aria-hidden','true');
				document.documentElement.style.overflow = '';
			}
			document.addEventListener('click', function(e){
				var ob = e.target.closest('.dastyar-mo-open');
				if (ob){ e.preventDefault(); open(ob); return; }
				if (e.target.closest('.dastyar-mo-close')){ close(); return; }
				if (e.target.classList && e.target.classList.contains('dastyar-mo-overlay')){ close(); }
			});
			document.addEventListener('keydown', function(e){
				if (e.key === 'Escape'){ var m = modal(); if (m && !m.hidden) close(); }
			});
		})();
		</script>
		<?php
	}
}
