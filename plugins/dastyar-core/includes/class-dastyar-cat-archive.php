<?php
/**
 * آرشیو کاتالوگ — کدکوتاه [dastyar_catalog]
 * هیرو + جستجو + چیپ‌های دسته‌بندی با آیکون + گرید کارت محصول + CTA همکاری + نشان‌های اعتماد.
 * داده‌ها = همان محصولات استاندارد WooCommerce (چرخ دوباره اختراع نمی‌شود).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Cat_Archive {

	public function __construct() {
		add_shortcode( 'dastyar_catalog', array( $this, 'shortcode' ) );
	}

	/**
	 * کدکوتاه — ویژگی‌ها (اختیاری): columns per_page title_a title_b subtitle show_search show_chips show_popular category
	 * (v1.1.1 — show_popular: کنترل مستقل نوار «دسته‌های پرطرفدار»، جدا از چیپ‌ها)
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'columns'      => '',
			'per_page'     => '',
			'title_a'      => '',
			'title_b'      => '',
			'subtitle'     => '',
			'show_search'  => '',
			'show_chips'   => '',
			'show_popular' => '',
			'category'     => '',
		), $atts, 'dastyar_catalog' );

		return $this->render( $atts );
	}

	/** رندر کامل آرشیو */
	public function render( array $over = array() ) {
		$s = Dastyar_Cat_Settings::all();
		$pick = function ( $key ) use ( $over, $s ) {
			return isset( $over[ $key ] ) && '' !== (string) $over[ $key ] ? $over[ $key ] : $s[ $key ];
		};

		$columns      = max( 2, min( 6, (int) $pick( 'columns' ) ) );
		$per_page     = max( 1, min( 48, (int) $pick( 'per_page' ) ) );
		$orderby      = (string) $s['orderby'];
		$show_search  = 'yes' === $pick( 'show_search' );
		$show_chips   = 'yes' === $pick( 'show_chips' );
		// v1.1.1 — نوار «دسته‌های پرطرفدار» سوییچ مستقل خودش را دارد (جدای از چیپ‌ها)
		$show_popular = 'yes' === $pick( 'show_popular' );

		// پارامترهای GET (فرم سمت سرور — بدون نیاز به JS)
		$q     = sanitize_text_field( wp_unslash( $_GET['dct_q'] ?? '' ) );
		$cat   = sanitize_title( (string) ( $_GET['dct_cat'] ?? '' ) );
		if ( '' !== trim( (string) ( $over['category'] ?? '' ) ) ) {
			$cat = sanitize_title( $over['category'] ); // فیلتر ثابت کدکوتاه
		}
		$paged = max( 1, (int) ( $_GET['dct_page'] ?? 1 ) );

		// ترتیب
		$order_args = array( 'orderby' => 'date', 'order' => 'DESC' );
		if ( 'popularity' === $orderby ) {
			$order_args = array( 'orderby' => 'popularity', 'order' => 'DESC' );
		} elseif ( 'price' === $orderby ) {
			$order_args = array( 'orderby' => 'price', 'order' => 'ASC' );
		} elseif ( 'price-desc' === $orderby ) {
			$order_args = array( 'orderby' => 'price', 'order' => 'DESC' );
		} elseif ( 'title' === $orderby ) {
			$order_args = array( 'orderby' => 'title', 'order' => 'ASC' );
		}

		$base_args = array_merge( array( 'status' => 'publish', 'return' => 'objects' ), $order_args );
		if ( '' !== $cat ) {
			// v1.2.2 — چون فیلتر فقط مادرها را نشان می‌دهد، انتخاب مادر باید محصولاتِ تخصیص‌یافته فقط به زیرشاخه‌هایش را هم پوشش دهد
			$slugs = array( $cat );
			if ( function_exists( 'get_term_by' ) ) {
				$ct = get_term_by( 'slug', $cat, 'product_cat' );
				if ( $ct && ! is_wp_error( $ct ) ) {
					$kids = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'child_of' => (int) $ct->term_id, 'fields' => 'slugs' ) );
					if ( is_array( $kids ) && ! is_wp_error( $kids ) ) {
						$slugs = array_values( array_unique( array_merge( $slugs, $kids ) ) );
					}
				}
			}
			$base_args['category'] = $slugs;
		}

		if ( '' !== $q ) {
			// جستجو: واکشی کامل + فیلتر PHP روی نام (دقیق و قابل اتکا) سپس برش صفحه
			$all      = (array) wc_get_products( array_merge( $base_args, array( 'limit' => -1, 's' => $q ) ) );
			$all      = array_values( array_filter( $all, function ( $p ) use ( $q ) {
				return false !== mb_stripos( (string) $p->get_name(), $q );
			} ) );
			$total    = count( $all );
			$products = array_slice( $all, ( $paged - 1 ) * $per_page, $per_page );
		} else {
			// مسیر سریع: صفحه‌بندی native
			$products = (array) wc_get_products( array_merge( $base_args, array( 'limit' => $per_page, 'page' => $paged ) ) );
			$ids      = (array) wc_get_products( array_merge( $base_args, array( 'limit' => -1, 'return' => 'ids' ) ) );
			$total    = count( $ids );
		}
		$pages = max( 1, (int) ceil( $total / $per_page ) );

		// زیرعنوان با {count}
		$subtitle = str_replace( '{count}', Dastyar_Cat_Settings::fa_num( Dastyar_Cat_Settings::products_count() ), (string) $pick( 'subtitle' ) );

		$this->css();

		ob_start();
		echo '<div class="dct-wrap" dir="rtl">';

		// ─── هیرو ───
		echo '<div class="dct-hero">';
		printf(
			'<h2 class="dct-title">%s <span class="dct-accent">%s</span></h2>',
			esc_html( (string) $pick( 'title_a' ) ),
			esc_html( (string) $pick( 'title_b' ) )
		);
		if ( '' !== trim( $subtitle ) ) {
			printf( '<p class="dct-sub">%s</p>', esc_html( $subtitle ) );
		}
		echo '</div>';

		// ─── نوار جستجو + فیلتر دسته‌بندی (v1.1.0 — مورد ۱) ───
		if ( $show_search ) {
			$all_terms = self::product_cat_terms(); // v1.2.2 — فیلتر سلکتی و نوار پرطرفدار فقط دسته‌های مادر
			echo '<form class="dct-searchrow" method="get" action="" id="dct-filter-form">';
			// حفظ سایر پارامترهای صفحه (مثل page_id)
			foreach ( (array) $_GET as $k => $v ) {
				if ( in_array( $k, array( 'dct_q', 'dct_cat', 'dct_page' ), true ) || ! is_scalar( $v ) ) {
					continue;
				}
				printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $k ), esc_attr( $v ) );
			}
			echo '<div class="dct-searchbox">';
			printf( '<input type="search" name="dct_q" value="%s" placeholder="جستجو در بین محصولات…" class="dct-input">', esc_attr( $q ) );
			// فیلتر واقعی دسته‌بندی — با تغییر، فرم خودکار ارسال می‌شود
			echo '<select name="dct_cat" class="dct-select" aria-label="فیلتر دسته‌بندی" onchange="this.form.submit()">';
			echo '<option value="">همه دسته‌بندی‌ها</option>';
			foreach ( (array) $all_terms as $t ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $t->slug ), selected( $cat, $t->slug, false ), esc_html( $t->name ) );
			}
			echo '</select>';
			echo '<button type="submit" class="dct-btn dct-btn-solid">جستجو</button>';
			if ( '' !== $cat || '' !== $q ) {
				printf( '<a class="dct-btn dct-btn-ghost" href="%s">✕ حذف فیلترها</a>', esc_url( add_query_arg( array( 'dct_q' => null, 'dct_cat' => null, 'dct_page' => null ) ) ) );
			}
			echo '</div></form>';

			// دسته‌های پرطرفدار + پاپ‌آپ «مشاهده همه» — v1.1.1: گیت مستقل show_popular (قبلاً با چیپ‌ها گره خورده بود)
			if ( $show_popular && $all_terms ) {
				$this->render_popular_cats( (array) $all_terms, $cat, (int) ( $s['popular_limit'] ?? 6 ) );
			}
		}

		// ─── چیپ‌های دسته‌بندی ───
		if ( $show_chips ) {
			$this->render_chips( (int) $s['chips_limit'], $cat, $q );
		}

		// ─── گرید محصولات ───
		echo '<div class="dct-grid" style="grid-template-columns:repeat(' . (int) $columns . ',1fr)">';
		if ( ! $products ) {
			echo '<div class="dct-empty">🔎 محصولی مطابق جستجوی شما پیدا نشد.</div>';
		}
		foreach ( $products as $product ) {
			$this->render_card( $product );
		}
		echo '</div>';

		// ─── صفحه‌بندی ───
		if ( $pages > 1 ) {
			$this->render_pager( $pages, $paged );
		}

		// v1.2.0 — اسکریپت دکمه «افزودن به فروشگاه من» (فقط اگر دکمه‌ای رندر شده باشد)
		if ( ! empty( $this->need_vendor_js ) ) {
			$this->vendor_js_once();
		}

		// ─── CTA همکاری ───
		if ( 'yes' === $s['cta_enabled'] ) {
			echo '<div class="dct-cta">';
			// v1.9.3 (موارد ۷/۸) — آیکن SVG خطی به‌جای اموجی ستاره
			echo '<div class="dct-cta-star">' . self::line_icon( 'spark' ) . '</div>';
			echo '<div class="dct-cta-side">';
			printf( '<h3>%s</h3>', esc_html( (string) $s['cta_title'] ) );
			printf( '<p>%s</p>', esc_html( (string) $s['cta_text'] ) );
			if ( '' !== (string) $s['cta_btn'] ) {
				printf( '<a class="dct-btn dct-btn-solid" href="%s">%s</a>', esc_url( (string) ( $s['cta_url'] ?: '#' ) ), esc_html( (string) $s['cta_btn'] ) );
			}
			echo '</div></div>';
		}

		// ─── نشان‌های اعتماد ───
		$this->render_badges( (array) $s['trust_items'], 'dct-trust' );

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * (v1.2.2 — بازخورد کاربر، مورد ۳) فقط دسته‌های «مادر» در فیلتر سلکتی، چیپ‌ها و پرطرفدارها —
	 * نمایش همه زیرشاخه‌ها کاربر را گیج می‌کرد. pad_counts باعث می‌شود شمارش پرطرفدارها شامل محصولات زیرشاخه هم بشود
	 * و مادرِ بدون محصولِ مستقیم که زیرشاخه‌های فعال دارد پنهان نماند.
	 */
	protected static function product_cat_terms() {
		$ts = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'parent'     => 0,
			'pad_counts' => true,
		) );
		if ( is_wp_error( $ts ) || ! is_array( $ts ) ) {
			return array();
		}
		// فیلتر دفاعی دولایه — حتی اگر آرگومان parent در محیط نادیده گرفته شود
		return array_values( array_filter( $ts, function ( $t ) { return 0 === (int) ( $t->parent ?? 0 ); } ) );
	}

	/** چیپ‌های دسته‌بندی + «بیشتر» */
	protected function render_chips( $limit, $active_cat, $q ) {
		$terms = self::product_cat_terms(); // v1.2.2 — فقط مادرها
		if ( ! $terms ) {
			return;
		}
		$limit   = max( 1, (int) $limit );
		$visible = array_slice( $terms, 0, $limit );
		$more    = array_slice( $terms, $limit );

		echo '<div class="dct-chips" id="dct-chips">';
		// همه
		printf(
			'<a class="dct-chip dct-chip-all%s" href="%s"><span class="dct-ic">🗂</span><span>همه</span></a>',
			'' === $active_cat ? ' dct-chip-on' : '',
			esc_url( add_query_arg( array( 'dct_cat' => null, 'dct_page' => null ) ) )
		);
		foreach ( $visible as $t ) {
			$this->render_chip( $t, $active_cat );
		}
		if ( $more ) {
			echo '<details class="dct-more"><summary class="dct-chip"><span class="dct-ic">⋯</span><span>بیشتر</span></summary><div class="dct-more-list">';
			foreach ( $more as $t ) {
				$this->render_chip( $t, $active_cat );
			}
			echo '</div></details>';
		}
		echo '</div>';
	}

	/** آیکن SVG لاین‌دیزاین «آتش» — نشانه پرطرفدار (بدون اموجی) */
	public static function fire_icon() {
		return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3c1.2 3.5-2.4 5-2.4 8a4.4 4.4 0 0 0 8.8.6C18.4 7.6 15 5.6 12 3z"/><path d="M10 14.6a2 2 0 1 0 4 0c0-1.6-1.2-2.1-2-3.4-.8 1.3-2 1.8-2 3.4z"/></svg>';
	}

	/** v1.9.4 — برش متن به n کاراکتر (بدون «...»؛ برش نرم بصری با فید CSS روی کارت) */
	public static function trim_chars( $text, $limit ) {
		$text  = trim( (string) $text );
		$limit = (int) $limit;
		if ( $limit < 8 ) {
			$limit = 8;
		}
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $text, 'UTF-8' ) > $limit ) {
			return rtrim( mb_substr( $text, 0, $limit, 'UTF-8' ) );
		}
		return $text;
	}

	/** v1.9.3 — آیکن‌های SVG خطی مشترک کاتالوگ (بدون اموجی در فرانت) */
	public static function line_icon( $name ) {
		$inner = array(
			'arrow-l' => '<path d="M14.5 5.5l-6.5 6.5 6.5 6.5"/>',
			'check'   => '<path d="M4.5 12.5l5 5 10-11"/>',
			'shield'  => '<path d="M12 3l7 3v5.5c0 4.5-3 7.8-7 9.5-4-1.7-7-5-7-9.5V6z"/><path d="M8.8 11.8l2.3 2.3 4.2-4.4"/>',
			'truck'   => '<path d="M1.5 7h12.5v9H1.5zM14 10h4l3.5 3.5V16H14z"/><circle cx="6" cy="18.5" r="1.8"/><circle cx="17.5" cy="18.5" r="1.8"/>',
			'box'     => '<path d="M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4m0 0l9-4m-9 4v10"/>',
			'headset' => '<path d="M4 13a8 8 0 1 1 16 0"/><rect x="2.5" y="12.5" width="4" height="6" rx="1.8"/><rect x="17.5" y="12.5" width="4" height="6" rx="1.8"/><path d="M20 18.5v.7a2.6 2.6 0 0 1-2.6 2.6H13"/>',
			'spark'   => '<path d="M12 2l2 6 6 2-6 2-2 6-2-6-6-2 6-2z"/>',
			'tag'     => '<path d="M20.6 13.4L13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
		);
		if ( ! isset( $inner[ $name ] ) ) {
			$name = 'check';
		}
		return '<svg class="dct-li" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner[ $name ] . '</svg>';
	}

	/** v1.9.3 (مورد ۸) — نگاشت آیکن‌‌های اموجی تنظیمات نشان‌ها به SVG خطی حرفه‌ای */
	public static function badge_icon( $raw ) {
		$raw = trim( (string) $raw );
		$map = array(
			'truck'   => array( '🚚', '🚛', 'ارسال', 'تحویل', 'ship' ),
			'shield'  => array( '🛡', '💯', '✅', '✔', '✓', 'ضمانت', 'گارانتی', 'اورجینال', 'اصل' ),
			'box'     => array( '📦', 'بسته', 'محرمانه' ),
			'headset' => array( '🎧', '📞', '☎', '💬', '🤝', 'پشتیبانی', 'مشاوره' ),
			'tag'     => array( '💰', '💳', '🏷', 'قیمت', 'تخفیف', 'پرداخت' ),
			'spark'   => array( '✨', '⭐', '🌟', '💫', 'پرفروش', 'محبوب' ),
		);
		foreach ( $map as $name => $needles ) {
			foreach ( $needles as $n ) {
				if ( '' !== $raw && false !== mb_strpos( $raw, $n ) ) {
					return self::line_icon( $name );
				}
			}
		}
		// هر ورودی دیگر (شامل هر اموجی ناشناخته) ← تیک حرفه‌ای
		return self::line_icon( 'check' );
	}

	/**
	 * نوار «دسته‌های پرطرفدار» (بیشترین تعداد محصول) + دکمه «مشاهده همه» و پاپ‌آپ انتخاب دسته (v1.1.0).
	 * با فیلترهای داینامیک ویکی‌پدیا سازگار است: هر لینک همان پارامتر dct_cat را می‌سازد.
	 */
	protected function render_popular_cats( array $terms, $active_cat, $limit = 6 ) {
		$by_count = $terms;
		usort( $by_count, function ( $a, $b ) { return (int) ( $b->count ?? 0 ) <=> (int) ( $a->count ?? 0 ); } );
		$popular = array_slice( $by_count, 0, max( 1, (int) $limit ) );

		echo '<div class="dct-popular">';
		echo '<span class="dct-pop-lb">' . self::fire_icon() . ' دسته‌های پرطرفدار:</span>';
		foreach ( $popular as $t ) {
			printf(
				'<a class="dct-pop%s" href="%s">%s<span class="dct-pop-n">%s</span><span class="dct-pop-c">(%s)</span></a>',
				'' !== $active_cat && $active_cat === $t->slug ? ' dct-pop-on' : '',
				esc_url( add_query_arg( array( 'dct_cat' => $t->slug, 'dct_page' => null ) ) ),
				self::fire_icon(),
				esc_html( $t->name ),
				esc_html( Dastyar_Cat_Settings::fa_num( (int) ( $t->count ?? 0 ) ) )
			);
		}
		echo '<button type="button" class="dct-pop dct-pop-all" id="dct-show-cats">مشاهده همه ←</button>';
		echo '</div>';

		// پاپ‌آپ «همه دسته‌بندی‌ها» — انتخاب هر دسته = همان فیلتر سمت سرور
		echo '<div class="dct-cats-modal" id="dct-cats-modal" hidden>';
		echo '<div class="dct-cm-card" role="dialog" aria-modal="true" aria-label="انتخاب دسته‌بندی">';
		echo '<div class="dct-cm-head"><strong>' . self::fire_icon() . ' انتخاب دسته‌بندی</strong>';
		echo '<button type="button" class="dct-cm-close" id="dct-cm-close" aria-label="بستن">✕</button></div>';
		echo '<div class="dct-cm-body">';
		printf(
			'<a class="dct-cm-item%s" href="%s"><span class="dct-ic">🗂</span><span>همه محصولات</span></a>',
			'' === $active_cat ? ' dct-cm-on' : '',
			esc_url( add_query_arg( array( 'dct_cat' => null, 'dct_page' => null ) ) )
		);
		$by_name = $terms;
		usort( $by_name, function ( $a, $b ) { return strcasecmp( (string) $a->name, (string) $b->name ); } );
		foreach ( $by_name as $t ) {
			printf(
				'<a class="dct-cm-item%s" href="%s"><span class="dct-ic">%s</span><span>%s</span><span class="dct-cm-c">%s</span></a>',
				'' !== $active_cat && $active_cat === $t->slug ? ' dct-cm-on' : '',
				esc_url( add_query_arg( array( 'dct_cat' => $t->slug, 'dct_page' => null ) ) ),
				esc_html( Dastyar_Cat_Settings::cat_icon( $t->term_id ) ),
				esc_html( $t->name ),
				esc_html( Dastyar_Cat_Settings::fa_num( (int) ( $t->count ?? 0 ) ) )
			);
		}
		echo '</div></div></div>';

		// اسکریپت کوچک باز/بست پاپ‌آپ (محلی و یک‌بار)
		static $js_done = false;
		if ( ! $js_done ) {
			$js_done = true;
			?>
			<script>
			document.addEventListener('click', function(e){
				var modal = document.getElementById('dct-cats-modal');
				if (!modal) { return; }
				if (e.target.closest('#dct-show-cats')) {
					modal.hidden = false;
					document.body.style.overflow = 'hidden';
					return;
				}
				if (e.target.closest('#dct-cm-close') || e.target === modal) {
					modal.hidden = true;
					document.body.style.overflow = '';
				}
			});
			document.addEventListener('keydown', function(e){
				var modal = document.getElementById('dct-cats-modal');
				if (e.key === 'Escape' && modal && !modal.hidden) {
					modal.hidden = true;
					document.body.style.overflow = '';
				}
			});
			</script>
			<?php
		}
	}

	protected function render_chip( $term, $active_cat ) {
		printf(
			'<a class="dct-chip%s" href="%s"><span class="dct-ic">%s</span><span>%s</span></a>',
			$active_cat === $term->slug ? ' dct-chip-on' : '',
			esc_url( add_query_arg( array( 'dct_cat' => $term->slug, 'dct_page' => null ) ) ),
			esc_html( Dastyar_Cat_Settings::cat_icon( $term->term_id ) ),
			esc_html( $term->name )
		);
	}

	/** کارت محصول */
	protected function render_card( $product ) {
		if ( ! $product || ! is_object( $product ) ) {
			return;
		}
		$pid  = (int) $product->get_id();
		$url  = get_permalink( $pid );
		$img  = '';
		$iid  = method_exists( $product, 'get_image_id' ) ? (int) $product->get_image_id() : 0;
		if ( $iid && function_exists( 'wp_get_attachment_image' ) ) {
			$img = (string) wp_get_attachment_image( $iid, 'woocommerce_thumbnail' );
		}
		// زیرنویس: اولین دسته محصول
		$cat_name = '';
		if ( function_exists( 'get_the_terms' ) ) {
			$terms = get_the_terms( $pid, 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$first    = reset( $terms );
				$cat_name = $first->name;
			}
		}

		echo '<div class="dct-card">';
		printf( '<a class="dct-thumb" href="%s">', esc_url( $url ) );
		if ( $img ) {
			echo $img; // phpcs:ignore — خروجی استاندارد ووکامرس
		} else {
			echo '<span class="dct-nopic">🎁</span>';
		}
		// v1.9.3 (مورد ۳) — دسته‌بندی به‌صورت پیل مینیمال شناور گوشه راست-بالای تصویر
		if ( '' !== $cat_name ) {
			printf( '<span class="dct-pcat">%s</span>', esc_html( $cat_name ) );
		}
		echo '</a>';
		echo '<div class="dct-cbody">';
		// v1.9.3 (مورد ۲) — عنوان تک‌خطی + تول‌تیپ نام کامل؛ v1.9.4 (مورد ۱): متن عنوان خودش بریده می‌شود و با فید محو می‌شود
		$pname_full = (string) $product->get_name();
		$pname_disp = self::trim_chars( $pname_full, (int) apply_filters( 'dastyar_card_title_len', 36 ) );
		printf( '<h3 class="dct-pname"><a href="%s" title="%s">%s</a></h3>', esc_url( $url ), esc_attr( $pname_full ), esc_html( $pname_disp ) );
		// v1.2.0 — قیمت متناسب با نقش کاربر + دکمه «افزودن به فروشگاه من» برای فروشندگان متصل
		echo $this->card_price_html( $product );
		echo $this->vendor_button_html( $product );
		// v1.9.1 — ردیف پایین کارت: «جزئیات» + قلب علاقه‌مندی (فروشنده لاگین؛ دیگر شناور بالای کارت نیست)
		echo '<div class="dct-foot">';
		// v1.9.3 (مورد ۶) — آیکن SVG خطی حرفه‌ای به‌جای کاراکتر «←»
		printf( '<a class="dct-details" href="%s">جزئیات %s</a>', esc_url( $url ), self::line_icon( 'arrow-l' ) );
		if ( class_exists( 'Dastyar_Growth' ) ) {
			echo Dastyar_Growth::heart_html( (int) $product->get_id(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی کنترل‌شده
		}
		echo '</div>';
		echo '</div></div>';
	}

	/** (v1.2.0) نیازمندی چاپ اسکریپت دکمه فروشنده */
	protected $need_vendor_js = false;

	/* ------------------------------------------------------------------
	 * قیمت نقش‌محور در کارت‌ها (v1.2.0 — بازخورد کاربر، مورد ۲):
	 *   مدیر فروشگاه (manage_woocommerce) ← قیمت فروشگاه (get_price_html)
	 *   فروشنده دستیار (متصل)            ← «قیمت همکاری» = متای تامین دستیار
	 *   مهمان / سایر نقش‌ها               ← بدون قیمت + راهنمای ورود (هماهنگ با قفل قیمت Core)
	 * ---------------------------------------------------------------- */

	/**
	 * (v1.2.0) قیمت نقش‌محور — هم در کارت‌ها و هم (v1.2.1) در صفحه تکی استفاده می‌شود.
	 * @param bool $for_single وقتی true: برای مهمان چیزی برنمی‌گرداند (در صفحه تکی، باکس مهمانِ Core همان پیام را دارد و قیمت «فقط» برای مدیر/فروشنده است)
	 */
	public function card_price_html( $product, $for_single = false ) {
		if ( 'yes' !== Dastyar_Cat_Settings::get( 'price_roles', 'yes' ) ) {
			return '';
		}
		$strip = function ( $h ) {
			return trim( function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $h ) : strip_tags( (string) $h ) );
		};

		// مدیر — قیمت واقعی فروشگاه (با تخفیف و فرمت استاندارد ووکامرس)
		if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ) ) {
			$html = method_exists( $product, 'get_price_html' ) ? (string) $product->get_price_html() : '';
			if ( '' === $strip( $html ) ) {
				$gp = method_exists( $product, 'get_price' ) ? $product->get_price() : '';
				$html = ( '' !== $gp && function_exists( 'wc_price' ) ) ? wc_price( (float) $gp ) : '';
			}
			return '' === $strip( $html ) ? '' : '<div class="dct-price">' . $html . '</div>';
		}

		// فروشنده متصل — قیمت همکاری (متای تامین؛ برای متغیر: کمینه تامین تنوع‌ها)
		if ( self::current_vendor_uid() ) {
			$sup = self::supplier_price_of( $product );
			return $sup > 0 && function_exists( 'wc_price' )
				? '<div class="dct-price dct-price-vendor"><span class="dct-price-lb">قیمت همکاری</span><strong>' . wc_price( $sup ) . '</strong></div>'
				: '';
		}

		// مهمان/سایر نقش‌ها — نمایش قیمت ندارد (همسوی Core)
		if ( $for_single ) {
			return ''; // v1.2.1 — درخواست کاربر: در صفحه تکی قیمت «فقط» برای ادمین و فروشنده؛ پیام ورود را باکس مهمان Core می‌دهد
		}
		$login = function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'myaccount' ) > 0
			? get_permalink( wc_get_page_id( 'myaccount' ) )
			: home_url( '/my-account/' );
		return '<div class="dct-price dct-price-guest"><a href="' . esc_url( $login ) . '">برای مشاهده قیمت همکاری وارد شوید</a></div>';
	}

	/** شناسه فروشنده دستیارِ واردشده (ترجیحاً از Core؛ در غیاب آن با نقش کاربری) */
	protected static function current_vendor_uid() {
		if ( class_exists( 'Dastyar_Shop_Page' ) ) {
			return (int) Dastyar_Shop_Page::current_vendor_id();
		}
		$u = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		return ( $u && $u->ID && in_array( 'dastyar_vendor', (array) $u->roles, true ) ) ? (int) $u->ID : 0;
	}

	/** قیمت تامین محصول — متا دستیار؛ فالبک برای متغیر: کمینه تامین تنوع‌ها؛ در نهایت قیمت خود محصول (قرارداد مرکز) */
	protected static function supplier_price_of( $product ) {
		$meta = method_exists( $product, 'get_meta' ) ? $product->get_meta( '_dastyar_supplier_price', true ) : '';
		if ( '' !== $meta && null !== $meta && is_numeric( $meta ) ) {
			return (float) $meta;
		}
		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) && method_exists( $product, 'get_children' ) ) {
			$ps = array();
			foreach ( (array) $product->get_children() as $vid ) {
				$v = function_exists( 'wc_get_product' ) ? wc_get_product( $vid ) : null;
				if ( ! $v ) {
					continue;
				}
				$vp = self::supplier_price_of( $v );
				if ( $vp > 0 ) {
					$ps[] = $vp;
				}
			}
			if ( $ps ) {
				return min( $ps );
			}
		}
		return method_exists( $product, 'get_price' ) ? (float) $product->get_price() : 0.0;
	}

	/* ------------------------------------------------------------------
	 * دکمه «افزودن به فروشگاه من» در کارت‌ها (v1.2.0 — بازخورد کاربر، مورد ۴):
	 * از همان AJAX و NONCE موجود Core (dastyar_import_to_shop) استفاده می‌شود — هیچ منطق جدیدی
	 * ساخته نشده؛ نتیجه همان پوش وب‌هوک product.import به فروشگاه خود فروشنده است و
	 * وضعیت «✔ افزوده شده» با صفحه تکی محصول (باکس Core) به اشتراک گذاشته می‌شود.
	 * ---------------------------------------------------------------- */

	protected function vendor_button_html( $product ) {
		$pid = method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;
		$uid = self::current_vendor_uid();
		if ( ! $pid || ! $uid ) {
			return '';
		}
		// رده‌بندی محرمانه — سرور هم هنگام کلیک دوباره چک می‌کند
		if ( class_exists( 'Dastyar_Tiers' ) && ! Dastyar_Tiers::can_sync( $uid, $pid ) ) {
			return '';
		}
		// (v1.2.2) فروشنده بدون سایت متصل: دکمه واردات که با خطای «فروشگاه را متصل کنید» برمی‌گردد نمایش داده نمی‌شود؛
		// مسیر او در صفحه محصول تکی دکمه «ثبت سفارش دستی این محصول» است (Core v1.7.3).
		if ( class_exists( 'Dastyar' ) && Dastyar::instance()->vendors && method_exists( Dastyar::instance()->vendors, 'site_url' ) && ! Dastyar::instance()->vendors->site_url( $uid ) ) {
			return '';
		}
		if ( class_exists( 'Dastyar_Shop_Page' ) && Dastyar_Shop_Page::is_imported( $uid, $pid ) ) {
			return '<div class="dct-vadded dastyar-imported">✔ افزوده شده در فروشگاه شما</div>';
		}
		$this->need_vendor_js = true;
		$nonce                = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'dastyar_import_shop_' . $pid ) : '';
		return '<button type="button" class="dct-vadd dastyar-import-btn" data-pid="' . (int) $pid . '" data-nonce="' . esc_attr( $nonce ) . '">' . self::bag_icon() . ' افزودن به فروشگاه من</button><span class="dct-vadd-msg dastyar-import-msg"></span>';
	}

	/** آیکن SVG لاین‌دیزاین «کیف فروشگاه» (v1.2.2 — مورد ۲: جایگزین اموجی در دکمه افزودن به فروشگاه) */
	public static function bag_icon() {
		return '<svg class="dct-vi" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.5 7.5h15l-1.2 11.7a2 2 0 0 1-2 1.8H7.7a2 2 0 0 1-2-1.8L4.5 7.5z"/><path d="M8.2 10.2V6.3a3.8 3.8 0 1 1 7.6 0v3.9"/></svg>';
	}

	/** اسکریپت AJAX دکمه‌های گرید — فقط یک‌بار در صفحه (همان قرارداد Core) */
	protected function vendor_js_once() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<script>
		(function(){
			var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			document.querySelectorAll('.dct-vadd.dastyar-import-btn').forEach(function(btn){
				btn.addEventListener('click', function(){
					var msg = btn.parentNode ? btn.parentNode.querySelector('.dct-vadd-msg') : null;
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
								btn.textContent = '✔ افزوده شده در فروشگاه شما';
								btn.classList.add('dct-vadded');
								btn.style.border = 'none';
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

	/** صفحه‌بندی ساده (شماره‌ها + قبلی/بعدی) — سازگار با RTL */
	protected function render_pager( $pages, $paged ) {
		echo '<nav class="dct-pager" role="navigation" aria-label="صفحات کاتالوگ">';
		if ( $paged > 1 ) {
			printf( '<a class="dct-page dct-page-nav" href="%s">→ قبلی</a>', esc_url( add_query_arg( 'dct_page', $paged - 1 ) ) );
		}
		$from = max( 1, $paged - 2 );
		$to   = min( $pages, $paged + 2 );
		if ( $from > 1 ) {
			printf( '<a class="dct-page" href="%s">%s</a>', esc_url( add_query_arg( 'dct_page', 1 ) ), Dastyar_Cat_Settings::fa_num( 1 ) );
			if ( $from > 2 ) {
				echo '<span class="dct-dots">…</span>';
			}
		}
		for ( $i = $from; $i <= $to; $i++ ) {
			if ( $i === $paged ) {
				printf( '<span class="dct-page dct-page-on">%s</span>', Dastyar_Cat_Settings::fa_num( $i ) );
			} else {
				printf( '<a class="dct-page" href="%s">%s</a>', esc_url( add_query_arg( 'dct_page', $i ) ), Dastyar_Cat_Settings::fa_num( $i ) );
			}
		}
		if ( $to < $pages ) {
			if ( $to < $pages - 1 ) {
				echo '<span class="dct-dots">…</span>';
			}
			printf( '<a class="dct-page" href="%s">%s</a>', esc_url( add_query_arg( 'dct_page', $pages ) ), Dastyar_Cat_Settings::fa_num( $pages ) );
		}
		if ( $paged < $pages ) {
			printf( '<a class="dct-page dct-page-nav" href="%s">بعدی ←</a>', esc_url( add_query_arg( 'dct_page', $paged + 1 ) ) );
		}
		echo '</nav>';
	}

	/** ردیف نشان‌ها (اشتراک آرشیو/تکی) */
	public function render_badges( array $items, $class = 'dct-trust' ) {
		if ( ! $items ) {
			return;
		}
		printf( '<div class="%s">', esc_attr( $class ) );
		foreach ( $items as $it ) {
			$it = (array) $it;
			echo '<div class="dct-badge">';
			printf( '<span class="dct-bic">%s</span>', self::badge_icon( (string) ( $it['icon'] ?? '' ) ) );
			printf( '<span class="dct-bt">%s</span>', esc_html( (string) ( $it['title'] ?? '' ) ) );
			if ( '' !== (string) ( $it['sub'] ?? '' ) ) {
				printf( '<span class="dct-bs">%s</span>', esc_html( (string) $it['sub'] ) );
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/** استایل برند — فقط یک‌بار در هر درخواست (public تا صفحه تکی هم هزاده شود) */
	public function css() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		echo '<style>
		@import url("https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css");
		/* v1.9.2 — هماهنگی کامل با زبان طراحی قالب دستیار (سبز #17a16d / سرمه‌ای #242536، خطوط خنثی، مینیمال) */
		.dct-wrap{--dct-green:#17a16d;--dct-green-dark:#12875c;--dct-mint:#2fce96;--dct-dark:#242536;--dct-navy:#242536;--dct-line:#e6e8ef;--dct-bg:#f6f7fa;--dct-text:#2b2c3a;--dct-muted:#8a8d9d;font-family:"Vazirmatn","IRANSans",Tahoma,sans-serif;max-width:1140px;margin:0 auto;padding:10px 6px;color:var(--dct-text);line-height:1.8}
		.dct-wrap *{box-sizing:border-box}
		/* هیرو — v1.9.3 (مورد ۱): برگشت به متن ساده، باکس پشت متن حذف شد */
		.dct-hero{text-align:center;margin:26px 0 22px}
		.dct-title{font-size:28px;font-weight:900;margin:0 0 8px;color:var(--dct-navy)}
		.dct-accent{color:var(--dct-green)}
		.dct-sub{color:var(--dct-muted);font-size:14px;margin:0}
		/* جستجو */
		.dct-searchrow{margin:18px auto;max-width:640px}
		.dct-searchbox{display:flex;gap:8px;background:#fff;border:1px solid var(--dct-line);border-radius:14px;padding:6px;transition:border-color .15s,box-shadow .15s}
		.dct-searchbox:focus-within{border-color:var(--dct-green);box-shadow:0 0 0 3px #17a16d22}
		.dct-input{flex:1;border:0;background:transparent;padding:10px 14px;font-size:14px;font-family:inherit;color:var(--dct-text)}
		.dct-input:focus{outline:none}
		.dct-btn{border:0;cursor:pointer;border-radius:10px;padding:10px 20px;font-size:13.5px;font-weight:800;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px;line-height:1.6;transition:background .15s,border-color .15s,color .15s}
		.dct-btn-solid{background:var(--dct-green);color:#fff}
		.dct-btn-solid:hover{background:var(--dct-green-dark)}
		.dct-btn-ghost{background:#fff;color:var(--dct-navy);border:1px solid var(--dct-line)}
		.dct-btn-ghost:hover{border-color:var(--dct-green);color:var(--dct-green)}
		/* فیلتر دسته‌بندی + پرطرفدارها */
		.dct-select{border:1px solid var(--dct-line);background:#f6f7fa;border-radius:10px;padding:10px 12px;font-size:13px;font-weight:700;font-family:inherit;color:var(--dct-navy);max-width:200px;cursor:pointer}
		.dct-select:focus{outline:none;border-color:var(--dct-green);background:#fff}
		.dct-searchrow form,.dct-searchbox input,.dct-searchbox select{box-sizing:border-box}
		.dct-popular{display:flex;flex-wrap:wrap;align-items:center;gap:10px;justify-content:center;margin:8px auto 24px;max-width:820px}
		.dct-pop-lb{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:800;color:#b45309}
		.dct-pop-lb svg{vertical-align:-2px}
		.dct-pop{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px solid var(--dct-line);color:var(--dct-navy);border-radius:999px;padding:6px 13px;font-size:12px;font-weight:800;text-decoration:none;cursor:pointer;font-family:inherit;transition:.15s}
		.dct-pop svg{vertical-align:-2px;color:#b45309}
		.dct-pop .dct-pop-c{color:var(--dct-muted);font-weight:600}
		.dct-pop:hover{border-color:#e8c48a;color:#8a4b08}
		.dct-pop-on{background:#fff8ec;border-color:#e8c48a;color:#8a4b08}
		.dct-pop-all{border-style:dashed;color:var(--dct-muted);background:var(--dct-bg)}
		.dct-pop-all:hover{border-color:var(--dct-green);color:var(--dct-green)}
		.dct-cats-modal{position:fixed;inset:0;z-index:99999;background:rgba(36,37,54,.45);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:22px}
		.dct-cats-modal[hidden]{display:none}
		.dct-cm-card{background:#fff;border-radius:16px;width:100%;max-width:560px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 22px 60px rgba(36,37,54,.25);border-top:4px solid var(--dct-green)}
		.dct-cm-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--dct-line)}
		.dct-cm-head strong{display:inline-flex;align-items:center;gap:7px;font-size:15px;color:var(--dct-navy)}
		.dct-cm-head strong svg{color:var(--dct-green)}
		.dct-cm-close{background:#f6f7fa;border:1px solid var(--dct-line);border-radius:9px;width:30px;height:30px;cursor:pointer;font-size:13px;color:#5b5e6e;line-height:1}
		.dct-cm-close:hover{border-color:var(--dct-green);color:var(--dct-green)}
		.dct-cm-body{overflow-y:auto;padding:14px 16px;display:flex;flex-wrap:wrap;gap:8px}
		.dct-cm-item{display:inline-flex;align-items:center;gap:7px;background:#f6f7fa;border:1px solid var(--dct-line);border-radius:10px;padding:9px 14px;font-size:13px;font-weight:700;color:var(--dct-text);text-decoration:none;transition:.15s}
		.dct-cm-item:hover{border-color:var(--dct-green);color:var(--dct-navy)}
		.dct-cm-on{background:#e9f6ee;border-color:var(--dct-green);color:var(--dct-navy)}
		.dct-cm-c{color:var(--dct-muted);font-size:11.5px;font-weight:600}
		/* چیپ‌ها */
		.dct-chips{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin:18px 0 26px;position:relative}
		.dct-chip{display:inline-flex;align-items:center;gap:7px;background:#fff;border:1px solid var(--dct-line);border-radius:10px;padding:9px 16px;font-size:13px;font-weight:700;color:var(--dct-text);text-decoration:none;transition:.15s;cursor:pointer}
		.dct-chip:hover{border-color:var(--dct-green);color:var(--dct-navy)}
		.dct-chip-on{background:#e9f6ee;border-color:var(--dct-green);color:var(--dct-navy)}
		.dct-ic{font-size:15px}
		.dct-more{position:relative}
		.dct-more>summary{list-style:none}
		.dct-more>summary::-webkit-details-marker{display:none}
		.dct-more[open]>summary{margin-bottom:8px}
		.dct-more-list{position:absolute;z-index:20;top:calc(100% + 6px);right:0;background:#fff;border:1px solid var(--dct-line);border-radius:12px;padding:10px;box-shadow:0 16px 34px rgba(36,37,54,.13);display:flex;flex-wrap:wrap;gap:8px;min-width:280px}
		/* گرید */
		.dct-grid{display:grid;gap:18px;margin:8px 0 20px}
		.dct-card{background:#fff;border:1px solid var(--dct-line);border-radius:16px;padding:16px;display:flex;flex-direction:column;transition:border-color .18s,box-shadow .18s}
		.dct-card:hover{border-color:#c9cede;box-shadow:0 10px 24px rgba(36,37,54,.08)}
		/* قیمت نقش‌محور در کارت */
		/* v1.9.3 (مورد ۴) — قیمت سمت چپ کارت، سبز برند */
		.dct-price{margin:2px 0 12px;font-size:14px;color:var(--dct-text);font-weight:700;line-height:1.7;display:flex;align-items:center;gap:8px;justify-content:flex-end;flex-wrap:wrap}
		.dct-price .amount{font-weight:800;color:var(--dct-green)}
		.dct-price del{color:var(--dct-muted)}
		.dct-price del .amount{color:var(--dct-muted);font-weight:600}
		.dct-price-vendor{justify-content:space-between}
		.dct-price-vendor strong{color:var(--dct-green);font-size:15px}
		.dct-price-lb{background:#e9f6ee;color:#166b47;border:1px solid #bfe6d2;border-radius:999px;padding:2px 10px;font-size:11px;font-weight:800;white-space:nowrap}
		.dct-price-guest a{font-size:12px;color:var(--dct-muted);text-decoration:none;border-bottom:1px dashed #cfd3e0;transition:.15s}
		.dct-price-guest a:hover{color:var(--dct-green);border-color:var(--dct-green)}
		/* دکمه «افزودن به فروشگاه من» در کارت فروشندگان */
		.dct-vadd{width:100%;background:var(--dct-green);color:#fff;border:none;border-radius:10px;padding:10px 12px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit;margin:0 0 8px;transition:background .15s;line-height:1.7;display:inline-flex;align-items:center;justify-content:center;gap:7px}
		.dct-vadd .dct-vi{width:15px;height:15px;vertical-align:-2px}
		.dct-vadd:hover{background:var(--dct-green-dark)}
		.dct-vadd[disabled]{opacity:.8;cursor:default}
		.dct-vadd.dct-vadded{background:#e9f6ee;color:#166b47;border:1px solid #bfe6d2;cursor:default}
		.dct-vadded{background:#e9f6ee;color:#166b47;border:1px solid #bfe6d2;border-radius:10px;padding:9px 12px;font-size:12px;font-weight:800;text-align:center;margin:0 0 8px}
		.dct-vadd-msg{display:block;font-size:11.5px;color:#b45309;margin:2px 0 6px;text-align:center}
		.dct-vadd-msg:empty{display:none}
		/* v1.9.3 (مورد ۵) — ارتفاع ثابت تصویر ← همه کارت‌ها هم‌قد */
		.dct-thumb{position:relative;display:flex;justify-content:center;align-items:center;height:190px;margin-bottom:12px}
		.dct-thumb img{max-width:100%;max-height:190px;object-fit:contain;border-radius:10px}
		.dct-nopic{font-size:52px;opacity:.35}
		/* v1.9.4 (مورد ۱) — عنوان تک‌خطی با فید محو در انتهای خط (به‌جای سه‌نقطه) */
		.dct-pname{font-size:15px;font-weight:800;margin:0 0 10px;line-height:1.7;white-space:nowrap;overflow:hidden}
		.dct-pname a{color:var(--dct-navy);text-decoration:none;display:block;white-space:nowrap;overflow:hidden;-webkit-mask-image:linear-gradient(to right,transparent 0,#000 42px);mask-image:linear-gradient(to right,transparent 0,#000 42px)}
		.dct-pname a:hover{color:var(--dct-green)}
		/* v1.9.3 (مورد ۵) — بدنه فلکس تا «جزئیات» همیشه کف کارت بچسبد ← کارت‌های یکسان */
		.dct-cbody{display:flex;flex-direction:column;flex:1}
		/* v1.9.3 (مورد ۳) — پیل دسته‌بندی مینیمال روی تصویر (گوشه راست-بالا) */
		.dct-pcat{position:absolute;top:9px;right:9px;z-index:2;background:rgba(255,255,255,.94);backdrop-filter:blur(4px);border:1px solid var(--dct-line);border-radius:999px;padding:3px 11px;font-size:11px;font-weight:800;color:var(--dct-navy);line-height:1.8;box-shadow:0 1px 4px rgba(36,37,54,.08);margin:0}
		.dct-details{margin-top:auto;display:flex;align-items:center;justify-content:center;gap:6px;border:1px solid var(--dct-line);color:var(--dct-navy);background:#fff;border-radius:10px;padding:9px 14px;font-size:13px;font-weight:800;text-decoration:none;transition:.15s}
		.dct-details:hover{background:var(--dct-green);color:#fff;border-color:var(--dct-green)}
		/* v1.9.3 (مورد ۶) — آیکن SVG خطی حرفه‌ای به‌جای «←» */
		.dct-details .dct-li{width:15px;height:15px}
		/* v1.9.1 — ردیف پایین کارت: جزئیات + قلب */
		.dct-foot{display:flex;align-items:center;gap:8px;margin-top:auto}
		.dct-foot .dct-details{flex:1;margin-top:0}
		.dct-card .dgr-heart{font-family:inherit}
		.dct-empty{grid-column:1/-1;text-align:center;color:var(--dct-muted);background:#fff;border:1.5px dashed #d5dae6;border-radius:16px;padding:36px;font-size:14px}
		/* صفحه‌بندی — هماهنگ با پیجینیشن قالب */
		.dct-pager{display:flex;justify-content:center;align-items:center;gap:6px;margin:10px 0 26px;flex-wrap:wrap}
		.dct-page{min-width:38px;height:38px;display:inline-flex;align-items:center;justify-content:center;text-align:center;padding:0 12px;border:1px solid var(--dct-line);border-radius:10px;background:#fff;color:var(--dct-navy);font-size:13.5px;font-weight:800;text-decoration:none;cursor:pointer;transition:.15s}
		.dct-page:hover{border-color:var(--dct-green)}
		.dct-page-on{background:var(--dct-green)!important;color:#fff!important;border-color:var(--dct-green)}
		.dct-dots{color:var(--dct-muted);padding:0 4px}
		/* CTA — v1.9.3 (مورد ۷): کارت سفید مینیمال با فاصله‌بندی استاندارد */
		.dct-cta{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid var(--dct-line);border-radius:16px;padding:20px 24px;margin:14px 0 26px;flex-wrap:wrap}
		.dct-cta-star{display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:14px;background:#17a16d1a;color:var(--dct-green);flex-shrink:0;line-height:0}
		.dct-cta-star .dct-li{width:22px;height:22px}
		.dct-cta-side{flex:1;min-width:220px}
		.dct-cta h3{margin:0 0 5px;font-size:16px;font-weight:900;color:var(--dct-navy)}
		.dct-cta p{margin:0;color:var(--dct-muted);font-size:13px}
		.dct-cta .dct-btn-solid{margin-right:auto}
		/* نشان‌های اعتماد */
		.dct-trust{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:8px 0 6px}
		.dct-badge{background:#fff;border:1px solid var(--dct-line);border-radius:14px;padding:16px 10px;text-align:center}
		/* v1.9.3 (مورد ۸) — آیکن SVG خطی سبز، بدون اموجی */
		.dct-bic{display:block;margin-bottom:8px;line-height:0;color:var(--dct-green)}
		.dct-bic .dct-li{width:24px;height:24px}
		.dct-bt{display:block;font-size:13px;font-weight:800;color:var(--dct-navy)}
		.dct-bs{display:block;font-size:11.5px;color:var(--dct-muted);margin-top:3px}
		@media (max-width:1024px){.dct-grid{grid-template-columns:repeat(3,1fr)!important}.dct-trust{grid-template-columns:repeat(2,1fr)}}
		@media (max-width:768px){.dct-grid{grid-template-columns:repeat(2,1fr)!important}.dct-title{font-size:22px}.dct-searchbox{flex-wrap:wrap}}
		@media (max-width:480px){.dct-grid{grid-template-columns:1fr!important}.dct-trust{grid-template-columns:1fr 1fr}}
		</style>';
	}
}
