<?php
/**
 * صفحه تکی محصول کاتالوگ — جایگزینی قالب فروشگاه (قابل خاموش‌کردن از پنل)
 * + کدکوتاه [dastyar_catalog_product id="…"] برای جاسازی نمای تکی در هر برگه.
 *
 * باکس خرید/ورود از افزونه Core (Dastyar_Shop_Page) هزاده می‌شود تا منطق نقش‌ها
 * (مهمان ← ورود/ثبت‌نام، فروشنده ← افزودن به فروشگاه) در یک جا بماند؛ در نبود آن، فالبک داخلی.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Cat_Single {

	public function __construct() {
		add_filter( 'template_include', array( $this, 'override' ), 99 );
		add_shortcode( 'dastyar_catalog_product', array( $this, 'shortcode' ) );
	}

	/** جایگزینی قالب صفحه محصول */
	public function override( $template ) {
		if ( function_exists( 'is_singular' ) && is_singular( 'product' ) && 'yes' === Dastyar_Cat_Settings::get( 'single_override', 'yes' ) ) {
			$custom = DCT_DIR . 'templates/single-product.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}
		return $template;
	}

	/** کدکوتاه نمای تکی */
	public function shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'dastyar_catalog_product' );
		$product = wc_get_product( (int) $atts['id'] );
		if ( ! $product ) {
			return '<div class="dct-empty" dir="rtl">محصول یافت نشد.</div>';
		}
		return $this->html( $product );
	}

	/** رندر HTML کامل صفحه تکی (خروجی: رشته HTML) */
	public function html( $product ) {
		$s   = Dastyar_Cat_Settings::all();
		$pid = (int) $product->get_id();

		ob_start();
		$this->css();
		echo '<div class="dct-wrap dct-single" dir="rtl">';

		// ─── بردکرامب ───
		$this->render_breadcrumb( $product );

		echo '<div class="dct-main">';

		// ─── گالری ───
		echo '<div class="dct-gallery">';
		$main_img = '';
		$iid      = method_exists( $product, 'get_image_id' ) ? (int) $product->get_image_id() : 0;
		if ( $iid && function_exists( 'wp_get_attachment_image' ) ) {
			$main_img = (string) wp_get_attachment_image( $iid, 'large', false, array( 'class' => 'dct-main-img' ) );
		}
		printf(
			'<div class="dct-mainwrap">%s</div>',
			$main_img ? $main_img : '<span class="dct-nopic-big">🎁</span>'
		);
		$thumbs = array_filter( array_merge(
			$iid ? array( $iid ) : array(),
			method_exists( $product, 'get_gallery_image_ids' ) ? (array) $product->get_gallery_image_ids() : array()
		) );
		if ( count( $thumbs ) > 1 ) {
			echo '<div class="dct-thumbs">';
			foreach ( $thumbs as $tid ) {
				$thumb = wp_get_attachment_image( (int) $tid, 'thumbnail' );
				$full  = wp_get_attachment_image_url( (int) $tid, 'large' );
				printf(
					'<button type="button" class="dct-thumbbtn" data-full="%s">%s</button>',
					esc_url( (string) $full ),
					$thumb ? $thumb : '🖼'
				);
			}
			echo '</div>';
		}
		echo '</div>';

		// ─── اطلاعات ───
		echo '<div class="dct-info">';

		// v1.9.3 (مورد ۹) — ردیف بالای اطلاعات: پیل دسته‌بندی + قلب علاقه‌مندی، چپ‌چین، گرافیک یکسان
		$cat_name = '';
		if ( function_exists( 'get_the_terms' ) ) {
			$terms = get_the_terms( $pid, 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$cat_name = reset( $terms )->name;
			}
		}
		echo '<div class="dct-topline">';
		if ( '' !== $cat_name ) {
			printf( '<span class="dct-info-cat">%s</span>', esc_html( $cat_name ) );
		}
		if ( class_exists( 'Dastyar_Growth' ) ) {
			echo Dastyar_Growth::heart_html( $pid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی کنترل‌شده
		}
		echo '</div>';

		printf( '<h1 class="dct-h1">%s</h1>', esc_html( (string) $product->get_name() ) );
		// v1.9.3 (مورد ۱۲) — «توضیحات کوتاه» به تب‌ها منتقل شد (دیگر اینجا چاپ نمی‌شود)

		// v1.2.1 — قیمت در صفحه تکی «فقط» برای مدیر و فروشنده (همان منطق نقش کارت‌ها؛ برای مهمان خروجی ندارد)
		$price_row = Dastyar_Cat::instance()->archive->card_price_html( $product, true );
		if ( '' !== $price_row ) {
			printf( '<div class="dct-single-price">%s</div>', $price_row );
		}
		// v1.9.5 (بازخورد کاربر) — بخش «کپی متن آماده» و دکمه‌اش کامل از صفحه محصول حذف شد

		// باکس خرید/ورود — ترجیحاً از Core
		echo '<div class="dct-purchase">';
		echo $this->purchase_box_html();
		echo '</div>';

		// ۴ آیکون زیر باکس
		Dastyar_Cat::instance()->archive->render_badges( (array) $s['single_icons'], 'dct-trust dct-sicons' );

		// v1.9.4 (مورد ۲ — بازخورد کاربر) — تب‌های توضیحات داخل ستون اطلاعات، زیر نشان‌ها؛ دکمه کپی به شکل تب
		$this->render_tabs( $product, $s );

		echo '</div>'; // info
		echo '</div>'; // main

		// ─── ویژگی‌های محصول (آیکون‌دار) ───
		$this->render_features( get_post_meta( $pid, '_dct_features', true ) );

		// ─── منابع و اطلاعات تکمیلی ───
		$this->render_resources( $pid, $s );

		echo '</div>';

		$this->js_once();
		return ob_get_clean();
	}

	/** بردکرامب: خانه / کاتالوگ محصولات / دسته / عنوان */
	protected function render_breadcrumb( $product ) {
		$archive_page = (int) Dastyar_Cat_Settings::get( 'archive_page', 0 );
		echo '<nav class="dct-crumb" aria-label="مسیر">';
		printf( '<a href="%s">خانه</a>', esc_url( home_url( '/' ) ) );
		if ( $archive_page ) {
			echo '<span class="dct-sep">/</span>';
			printf( '<a href="%s">کاتالوگ محصولات</a>', esc_url( get_permalink( $archive_page ) ) );
		}
		if ( function_exists( 'get_the_terms' ) ) {
			$terms = get_the_terms( (int) $product->get_id(), 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$t = reset( $terms );
				echo '<span class="dct-sep">/</span>';
				echo '<span>' . esc_html( $t->name ) . '</span>';
			}
		}
		echo '<span class="dct-sep">/</span>';
		echo '<span class="dct-crumb-cur">' . esc_html( (string) $product->get_name() ) . '</span>';
		echo '</nav>';
	}

	/** باکس خرید — هزاده از Core در صورت حضور، وگرنه فالبک داخلی */
	public function purchase_box_html() {
		if ( class_exists( 'Dastyar_Shop_Page' ) && class_exists( 'Dastyar' ) && Dastyar::instance()->shop_page ) {
			ob_start();
			Dastyar::instance()->shop_page->render_box();
			return ob_get_clean();
		}
		// فالبک مستقل: مهمان/عمومی → ورود؛ لاگین غیرفروشنده هم همان
		$login_url = function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'myaccount' ) > 0
			? get_permalink( wc_get_page_id( 'myaccount' ) )
			: home_url( '/my-account/' );
		return '<div class="dct-guest-lock" style="background:#e9f6ee;border:1px solid #bfe6d2;border-radius:14px;padding:24px 20px;text-align:center">'
			. '<p style="margin:0 0 16px;color:#166b47;font-size:14.5px;font-weight:700;line-height:2">برای مشاهده قیمت همکاری و ثبت سفارش<br>وارد حساب کاربری خود شوید.</p>'
			. '<a href="' . esc_url( $login_url ) . '" style="display:inline-block;background:#17a16d;color:#fff;border-radius:10px;padding:10px 38px;font-size:14.5px;font-weight:700;text-decoration:none">ورود / ثبت نام</a>'
			. '</div>';
	}

	/** تب‌های اطلاعات محصول */
	protected function render_tabs( $product, array $s ) {
		$tabs = array();

		// v1.9.3 (مورد ۱۲) — «توضیحات کوتاه» به‌صورت تب اول، در کنار تب «معرفی محصول»
		// نکته مهم (رفع باگ v1.10.15): این فیلد در ووکامرس مجاز به داشتن HTML امن است
		// (دقیقاً مثل تب «معرفی محصول»)؛ قبلاً با esc_html() کل محتوا فرار داده می‌شد، یعنی
		// اگر تامین‌کننده/فروشنده یک جدول یا لیست در توضیحات کوتاه گذاشته بود، به‌جای نمایش
		// جدول، خودِ تگ‌های HTML به‌صورت متن خام (<table>, <tr>, ...) روی صفحه دیده می‌شد —
		// این بود که در نگاه اول شبیه یک باگ نمایشی (ازجمله در حالت تاریک) به‌نظر می‌رسید.
		$short = method_exists( $product, 'get_short_description' ) ? trim( (string) $product->get_short_description() ) : '';
		if ( '' !== $short ) {
			$short_body = function_exists( 'wpautop' ) ? wpautop( $short ) : $short;
			$tabs['short'] = array( 'label' => 'توضیحات کوتاه', 'body' => '<div class="dct-tabtext">' . wp_kses_post( $short_body ) . '</div>' );
		}

		// معرفی
		if ( 'yes' === $s['tab_intro'] ) {
			$desc = method_exists( $product, 'get_description' ) ? trim( (string) $product->get_description() ) : '';
			if ( '' !== $desc ) {
				$body = function_exists( 'wpautop' ) ? wpautop( $desc ) : '<p>' . esc_html( $desc ) . '</p>';
				$tabs['intro'] = array( 'label' => 'معرفی محصول', 'body' => '<div class="dct-tabtext">' . wp_kses_post( $body ) . '</div>' );
			}
		}

		// مشخصات
		if ( 'yes' === $s['tab_specs'] ) {
			$rows = $this->spec_rows( $product );
			if ( $rows ) {
				$body = '<table class="dct-specs">';
				foreach ( $rows as $r ) {
					$body .= sprintf( '<tr><th>%s</th><td>%s</td></tr>', esc_html( $r[0] ), esc_html( $r[1] ) );
				}
				$body .= '</table>';
				$tabs['specs'] = array( 'label' => 'مشخصات', 'body' => $body );
			}
		}

		// محتویات بسته
		if ( 'yes' === $s['tab_package'] ) {
			$lines = Dastyar_Cat_Settings::lines( get_post_meta( (int) $product->get_id(), '_dct_package', true ) );
			if ( $lines ) {
				$body = '<ul class="dct-package">';
				foreach ( $lines as $line ) {
					$body .= '<li>📦 ' . esc_html( $line ) . '</li>';
				}
				$body .= '</ul>';
				$tabs['package'] = array( 'label' => 'محتویات بسته', 'body' => $body );
			}
		}

		// سوالات متداول
		if ( 'yes' === $s['tab_faq'] ) {
			$lines = Dastyar_Cat_Settings::lines( get_post_meta( (int) $product->get_id(), '_dct_faq', true ) );
			if ( $lines ) {
				$body = '<div class="dct-faqs">';
				foreach ( $lines as $line ) {
					list( $q, $a ) = Dastyar_Cat_Settings::pair( $line );
					if ( '' === $a ) {
						continue;
					}
					$body .= '<details class="dct-faq"><summary>' . esc_html( $q ) . '</summary><div class="dct-faq-a">' . esc_html( $a ) . '</div></details>';
				}
				$body .= '</div>';
				$tabs['faq'] = array( 'label' => 'سوالات متداول', 'body' => $body );
			}
		}

		if ( ! $tabs ) {
			return;
		}

		echo '<div class="dct-tabs">';
		echo '<div class="dct-tabnav" role="tablist">';
		$first = true;
		foreach ( $tabs as $key => $tab ) {
			printf(
				'<button type="button" class="dct-tabbtn%s" data-tab="%s" role="tab">%s</button>',
				$first ? ' dct-tab-on' : '',
				esc_attr( $key ),
				esc_html( $tab['label'] )
			);
			$first = false;
		}
		// v1.9.5 (بازخورد کاربر) — دکمه «کپی متن آماده» کامل از صفحه محصول حذف شد
		echo '</div>';
		$first = true;
		foreach ( $tabs as $key => $tab ) {
			printf(
				'<div class="dct-tabpane%s" data-pane="%s" role="tabpanel">%s</div>',
				$first ? ' dct-pane-on' : '',
				esc_attr( $key ),
				$tab['body'] // از بالا امن‌شده (esc/kses)
			);
			$first = false;
		}
		echo '</div>';
	}

	/**
	 * ردیف‌های جدول مشخصات از ویژگی‌های استاندارد ووکامرس
	 * @return array<int,array{0:string,1:string}>
	 */
	protected function spec_rows( $product ) {
		if ( ! method_exists( $product, 'get_attributes' ) ) {
			return array();
		}
		$rows = array();
		foreach ( (array) $product->get_attributes() as $attr ) {
			if ( ! is_object( $attr ) || ! method_exists( $attr, 'get_name' ) ) {
				continue;
			}
			$name = (string) $attr->get_name();
			$label = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $name, $product ) : $name;
			if ( method_exists( $attr, 'is_taxonomy' ) && $attr->is_taxonomy() ) {
				$vals = function_exists( 'wc_get_product_terms' )
					? (array) wc_get_product_terms( (int) $product->get_id(), $name, array( 'fields' => 'names' ) )
					: array();
			} else {
				$vals = method_exists( $attr, 'get_options' ) ? (array) $attr->get_options() : array();
			}
			$vals = array_filter( array_map( 'strval', $vals ) );
			if ( $vals ) {
				$rows[] = array( $label, implode( '، ', $vals ) );
			}
		}
		return $rows;
	}

	/** ویژگی‌های آیکون‌دار — هر خط: ایموجی|عنوان */
	protected function render_features( $raw ) {
		$lines = Dastyar_Cat_Settings::lines( $raw );
		if ( ! $lines ) {
			return;
		}
		echo '<div class="dct-features">';
		foreach ( $lines as $line ) {
			list( $icon, $label ) = Dastyar_Cat_Settings::pair( $line );
			if ( '' === $label ) {
				continue;
			}
			echo '<div class="dct-feat">';
			printf( '<span class="dct-fic">%s</span>', esc_html( '' !== $icon ? $icon : '✔' ) );
			printf( '<span class="dct-ft">%s</span>', esc_html( $label ) );
			echo '</div>';
		}
		echo '</div>';
	}

	/** باکس منابع و اطلاعات تکمیلی — فقط دکمه‌هایی که URL دارند (و در تنظیمات فعال‌اند) */
	protected function render_resources( $pid, array $s ) {
		$items = (array) $s['res_items'];
		$cards = array();
		foreach ( array( 'specs', 'desc', 'video', 'images' ) as $key ) {
			$conf = (array) ( $items[ $key ] ?? array() );
			$url  = trim( (string) get_post_meta( $pid, '_dct_res_' . $key, true ) );
			if ( '' === $url || 'yes' !== (string) ( $conf['enabled'] ?? 'yes' ) ) {
				continue;
			}
			$cards[] = array(
				'icon'   => (string) ( $conf['icon'] ?? '📄' ),
				'label'  => (string) ( $conf['label'] ?? '' ),
				'action' => (string) ( $conf['action'] ?? 'دانلود فایل' ),
				'url'    => $url,
			);
		}
		if ( ! $cards ) {
			return;
		}
		echo '<div class="dct-res">';
		printf( '<h3 class="dct-res-t">%s</h3>', esc_html( (string) $s['res_title'] ) );
		if ( '' !== trim( (string) $s['res_sub'] ) ) {
			printf( '<p class="dct-res-s">%s</p>', esc_html( (string) $s['res_sub'] ) );
		}
		echo '<div class="dct-res-grid">';
		foreach ( $cards as $c ) {
			printf(
				'<a class="dct-res-card" href="%s" target="_blank" rel="noopener"><span class="dct-ric">%s</span><span class="dct-rl">%s</span><span class="dct-ra">%s</span></a>',
				esc_url( $c['url'] ),
				esc_html( $c['icon'] ),
				esc_html( $c['label'] ),
				esc_html( $c['action'] )
			);
		}
		echo '</div></div>';
	}

	/** اسکریپت تب‌ها و گالری (ونیلا — هندلر مرکزی delegation، پایدار با لود آنی قالب) */
	protected function js_once() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<script>
		(function(){
			/* v1.9.3 — delegation روی document: حتی با تعویض DOM (لود آنی) هم کار می‌کند */
			document.addEventListener('click', function(e){
				var t = e.target.closest('.dct-tabbtn');
				if (t) {
					var box = t.closest('.dct-tabs');
					if (box) {
						box.querySelectorAll('.dct-tabbtn').forEach(function(b){ b.classList.remove('dct-tab-on'); });
						box.querySelectorAll('.dct-tabpane').forEach(function(p){ p.classList.remove('dct-pane-on'); });
						t.classList.add('dct-tab-on');
						var pane = box.querySelector('.dct-tabpane[data-pane="' + t.getAttribute('data-tab') + '"]');
						if (pane) { pane.classList.add('dct-pane-on'); }
						return;
					}
				}
				/* v1.9.3 (مورد ۱۰) — کلیک روی بندانگشتی: تصویر بزرگ همان محصول در قاب اصلی (پیش‌لود + محو نرم) */
				var b = e.target.closest('.dct-thumbbtn');
				if (b) {
					var gal  = b.closest('.dct-gallery');
					var main = gal ? gal.querySelector('.dct-mainwrap img') : null;
					var full = b.getAttribute('data-full');
					if (main && full && main.getAttribute('src') !== full) {
						gal.querySelectorAll('.dct-thumbbtn').forEach(function(x){ x.classList.remove('dct-tb-on'); });
						b.classList.add('dct-tb-on');
						main.style.opacity = '0.35';
						var pre = new Image();
						/* رفع باگ: wp_get_attachment_image() برای تصویر اصلی srcset/sizes هم چاپ می‌کند؛
						   تا این دو حذف نشوند، مرورگر برای انتخاب تصویر نمایشی همچنان از srcset قدیمی
						   (نه src جدیدی که با کلیک ست می‌شود) استفاده می‌کند و عملاً هیچ تغییری دیده نمی‌شود. */
						pre.onload  = function(){ main.src = full; main.removeAttribute('srcset'); main.removeAttribute('sizes'); main.style.opacity = ''; };
						pre.onerror = function(){ main.src = full; main.removeAttribute('srcset'); main.removeAttribute('sizes'); main.style.opacity = ''; };
						pre.src = full;
					}
				}
			});
		})();
		</script>
		<?php
	}

	/** استایل تکمیلی صفحه تکی (علاوه بر استایل آرشیو که همیشه چاپ می‌شود) */
	protected function css() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		// استایل پایه آرشیو را هم داشته باشیم (باکس‌ها/نشان‌ها/دکمه‌ها مشترک‌اند)
		Dastyar_Cat::instance()->archive->css();
		echo '<style>
		.dct-single{--dct-green:#17a16d;--dct-green-dark:#12875c;--dct-dark:#242536}
		/* v1.9.2 — همان زبان طراحی قالب: سرمه‌ای/سبز، خط ۱px خنثی، مینیمال */
		.dct-crumb{font-size:12.5px;color:var(--dct-muted,#8a8d9d);margin:6px 0 18px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
		.dct-crumb a{color:#5b5e6e;text-decoration:none}
		.dct-crumb a:hover{color:var(--dct-green)}
		.dct-sep{color:#c3c7d6}
		.dct-crumb-cur{color:var(--dct-navy,#242536);font-weight:700}
		.dct-main{display:grid;grid-template-columns:minmax(300px,480px) 1fr;gap:34px;align-items:start;margin-bottom:26px}
		/* رفع باگ: آیتم‌های Grid/Flex به‌طور پیش‌فرض min-width آن‌ها «auto» است، یعنی هرگز از
		   عرض محتوای داخلی‌شان کوچک‌تر نمی‌شوند. چون گالری تصاویر حالا یک ردیف افقی
		   اسکرول‌شونده (ریل) دارد، بدون min-width:0 کل ستون گالری (و در نتیجه کل صفحه)
		   به‌اندازه‌ی مجموع عرض بندانگشتی‌ها عریض می‌ماند و باعث اسکرول افقی کل صفحه
		   در موبایل می‌شود؛ min-width:0 به گالری/اطلاعات اجازه می‌دهد در قاب موبایل جا شوند
		   و اسکرول فقط داخل خودِ ردیف بندانگشتی‌ها بماند. */
		.dct-wrap.dct-single{overflow-x:hidden}
		.dct-gallery{position:relative;min-width:0}
		.dct-info{min-width:0}
		.dct-mainwrap{background:#fff;border:1px solid var(--dct-line,#e6e8ef);border-radius:16px;display:flex;justify-content:center;align-items:center;min-height:380px;padding:22px}
		.dct-mainwrap img,.dct-main-img{max-width:100%;max-height:420px;object-fit:contain;border-radius:12px;transition:opacity .18s ease}
		.dct-nopic-big{font-size:90px;opacity:.3}
		.dct-thumbs{display:flex;gap:10px;margin-top:12px;overflow-x:auto;flex-wrap:nowrap;scroll-snap-type:x proximity;-webkit-overflow-scrolling:touch;padding-bottom:6px;min-width:0}
		.dct-thumbs::-webkit-scrollbar{height:6px}
		.dct-thumbs::-webkit-scrollbar-thumb{background:var(--dct-line,#e6e8ef);border-radius:10px}
		.dct-thumbbtn{background:#fff;border:1px solid var(--dct-line,#e6e8ef);border-radius:10px;padding:6px;cursor:pointer;width:64px;height:64px;flex:0 0 auto;scroll-snap-align:start;display:flex;align-items:center;justify-content:center;overflow:hidden}
		.dct-thumbbtn img{max-width:100%;max-height:100%;object-fit:contain}
		.dct-thumbbtn:hover,.dct-tb-on{border-color:var(--dct-green)!important}
		.dct-h1{font-size:25px;font-weight:900;color:var(--dct-navy,#242536);margin:0 0 6px}
		/* v1.9.3 (مورد ۹) — ردیف بالای اطلاعات: پیل دسته‌بندی + قلب، چپ‌چین، گرافیک یکسان */
		.dct-topline{display:flex;align-items:center;gap:8px;justify-content:flex-end;margin-bottom:14px}
		.dct-info-cat{display:inline-flex;align-items:center;background:#f6f7fa;border:1px solid var(--dct-line,#e6e8ef);border-radius:12px;padding:7px 14px;font-size:12.5px;font-weight:800;color:var(--dct-navy,#242536);margin:0}
		.dct-topline .dgr-heart{border-radius:12px}
		/* v1.9.3 (مورد ۱۲) — توضیحات کوتاه به تب‌ها منتقل شد؛ قانون dct-short حذف شد */
		/* v1.2.1 — قیمت نقش‌محور در صفحه تکی (فقط مدیر/فروشنده) */
		.dct-single-price{margin:0 0 6px}
		.dct-single-price .dct-price{font-size:20px;font-weight:800;color:var(--dct-dark);line-height:1.8}
		.dct-single-price .dct-price del{color:var(--dct-muted,#8a8d9d);font-weight:600;font-size:15px;margin-left:10px}
		.dct-single-price .dct-price ins{text-decoration:none}
		.dct-single-price .dct-price-vendor strong{font-size:20px}
		.dct-single-price .dct-price-lb{font-size:11.5px;padding:3px 12px}
		.dct-purchase{margin:0 0 18px}
		.dct-sicons{margin:0}
		.dct-sicons .dct-badge{padding:12px 6px}
		.dct-sicons .dct-bic{font-size:20px}
		/* تب‌ها */
		.dct-tabs{background:#fff;border:1px solid var(--dct-line,#e6e8ef);border-radius:16px;padding:10px 22px 22px;margin-bottom:22px}
		/* v1.9.4 (مورد ۲) — تب‌ها داخل ستون اطلاعات چسبیده به نشان‌ها */
		.dct-info .dct-tabs{margin:18px 0 0;padding:6px 18px 18px}
		.dct-tabnav{display:flex;gap:22px;border-bottom:1px solid var(--dct-line,#e6e8ef);flex-wrap:wrap;align-items:center}
		/* v1.9.5 (بازخورد کاربر) — دکمه «کپی متن آماده» به‌همراه قوانین استایلش کامل از نوار تب‌ها حذف شد */
		.dct-tabbtn{background:none;border:0;font-family:inherit;font-size:14px;font-weight:800;color:var(--dct-muted,#8a8d9d);padding:14px 2px;cursor:pointer;border-bottom:2.5px solid transparent;margin-bottom:-1px}
		.dct-tabbtn:hover{color:var(--dct-navy,#242536)}
		.dct-tab-on{color:var(--dct-green)!important;border-bottom-color:var(--dct-green)}
		.dct-tabpane{display:none;padding:18px 2px 4px;font-size:14px;color:var(--dct-text,#2b2c3a);line-height:2.1}
		.dct-pane-on{display:block}
		.dct-specs{width:100%;border-collapse:collapse}
		.dct-specs th,.dct-specs td{border:1px solid var(--dct-line,#e6e8ef);padding:10px 14px;font-size:13.5px;text-align:right}
		.dct-specs th{background:#f6f7fa;width:34%;color:var(--dct-navy,#242536)}
		.dct-package{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px}
		.dct-package li{background:#f6f7fa;border:1px solid var(--dct-line,#e6e8ef);border-radius:10px;padding:10px 14px;font-size:13.5px}
		/* v1.9.2 — آکاردئون FAQ مینیمال با نشانگر +/− (مثل FAQ قالب؛ بدون اموجی) */
		.dct-faq{border:1px solid var(--dct-line,#e6e8ef);border-radius:12px;margin-bottom:10px;background:#fff}
		.dct-faq summary{cursor:pointer;padding:13px 16px;font-weight:800;font-size:13.5px;color:var(--dct-navy,#242536);list-style:none;display:flex;align-items:center;justify-content:space-between;gap:10px}
		.dct-faq summary::-webkit-details-marker{display:none}
		.dct-faq summary:after{content:"+";flex:none;width:24px;height:24px;border-radius:8px;background:#f6f7fa;color:var(--dct-green);font-weight:900;font-size:15px;line-height:1;display:inline-flex;align-items:center;justify-content:center}
		.dct-faq[open] summary{color:var(--dct-dark)}
		.dct-faq[open] summary:after{content:"−"}
		.dct-faq-a{padding:0 16px 14px;color:var(--dct-text,#2b2c3a);font-size:13.5px;line-height:2}
		/* ویژگی‌ها */
		.dct-features{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;background:#fff;border:1px solid var(--dct-line,#e6e8ef);border-radius:16px;padding:18px 12px;margin-bottom:22px}
		.dct-feat{text-align:center;flex:1;min-width:110px}
		.dct-fic{display:block;font-size:26px;margin-bottom:6px}
		.dct-ft{font-size:12.5px;font-weight:700;color:var(--dct-text,#2b2c3a)}
		/* منابع — کارت سفید مینیمال به‌جای گرادیانت سبز */
		.dct-res{background:#fff;border:1px solid var(--dct-line,#e6e8ef);border-radius:16px;padding:24px;margin-bottom:10px;text-align:center}
		.dct-res-t{margin:0 0 4px;font-size:17px;font-weight:900;color:var(--dct-navy,#242536)}
		.dct-res-s{margin:0 0 16px;font-size:12.5px;color:var(--dct-muted,#8a8d9d)}
		.dct-res-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
		.dct-res-card{background:#fff;border:1px solid var(--dct-line,#e6e8ef);border-radius:14px;padding:16px 10px;text-decoration:none;transition:.15s;display:flex;flex-direction:column;gap:4px;align-items:center}
		.dct-res-card:hover{border-color:var(--dct-green);box-shadow:0 10px 24px rgba(36,37,54,.08)}
		.dct-ric{font-size:22px}
		.dct-rl{font-size:13px;font-weight:800;color:var(--dct-navy,#242536)}
		.dct-ra{font-size:11.5px;color:var(--dct-green);font-weight:800}
		@media (max-width:900px){.dct-main{grid-template-columns:1fr}.dct-res-grid{grid-template-columns:repeat(2,1fr)}.dct-features{gap:16px}}

		/* ============================================================
		 * v1.10.20 — رفع باگ: کل صفحه محصول تکی تا امروز هیچ حالت تاریکی نداشت
		 * (نه فقط پیل دسته‌بندی و باکس معرفی که گزارش شد — همه باکس‌های سفید این صفحه)
		 * ============================================================ */
		html.dhm-dark .dct-crumb-cur{color:#dfe3f6}
		html.dhm-dark .dct-sep{color:#4b4f6b}
		html.dhm-dark .dct-mainwrap{background:#1b1e3c;border-color:#2e3252}
		html.dhm-dark .dct-thumbbtn{background:#1b1e3c;border-color:#2e3252}
		html.dhm-dark .dct-thumbs::-webkit-scrollbar-thumb{background:#2e3252}
		html.dhm-dark .dct-h1{color:#eceef8}
		html.dhm-dark .dct-info-cat{background:#232648;border-color:#2e3252;color:#dfe3f6}
		html.dhm-dark .dct-single-price .dct-price{color:#eceef8}
		html.dhm-dark .dct-tabs{background:#1b1e3c;border-color:#2e3252}
		html.dhm-dark .dct-tabnav{border-color:#2e3252}
		html.dhm-dark .dct-tabbtn{color:#9aa3c4}
		html.dhm-dark .dct-tabbtn:hover{color:#dfe3f6}
		html.dhm-dark .dct-tabpane{color:#c7cbe6}
		html.dhm-dark .dct-specs th,html.dhm-dark .dct-specs td{border-color:#2e3252;color:#dfe3f6}
		html.dhm-dark .dct-specs th{background:#232648}
		html.dhm-dark .dct-package li{background:#232648;border-color:#2e3252;color:#dfe3f6}
		html.dhm-dark .dct-faq{background:#1b1e3c;border-color:#2e3252}
		html.dhm-dark .dct-faq summary{color:#eceef8}
		html.dhm-dark .dct-faq summary:after{background:#232648}
		html.dhm-dark .dct-faq[open] summary{color:#fff}
		html.dhm-dark .dct-faq-a{color:#c7cbe6}
		html.dhm-dark .dct-features{background:#1b1e3c;border-color:#2e3252}
		html.dhm-dark .dct-ft{color:#c7cbe6}
		html.dhm-dark .dct-res{background:#1b1e3c;border-color:#2e3252}
		html.dhm-dark .dct-res-t{color:#eceef8}
		html.dhm-dark .dct-res-card{background:#232648;border-color:#2e3252}
		html.dhm-dark .dct-rl{color:#dfe3f6}
		html.dhm-dark .dct-badge{background:#1b1e3c;border-color:#2e3252;color:#dfe3f6}
		</style>';
	}
}

