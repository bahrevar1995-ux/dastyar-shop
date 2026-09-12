<?php
/**
 * ساخت/به‌روزرسانی محصول WooCommerce واقعی از JSON مرکز.
 *
 * - نوع محصول حفظ می‌شود: simple ↔ WC_Product_Simple / variable ↔ WC_Product_Variable + WC_Product_Variation
 * - وضعیت محصول جدید به انتخاب فروشنده: «پیش‌نویس» (پیش‌فرض) یا «منتشر شده» — در فراخوانی‌های داخلی (سینک/وب‌هوک) همیشه پيش‌نویس می‌ماند
 * - قیمت طبق فرمول قیمت‌گذاری فروشنده محاسبه می‌شود (هرگز قیمت مرکز کپی نمی‌شود)
 * - موجودی فقط اگر «همگام‌سازی خودکار موجودی» روشن باشد به‌روزرسانی می‌شود
 * - در به‌روزرسانی، وضعیت انتشار فعلی محصول حفظ می‌شود
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Importer {

	/** شناسه محصول محلی متناظر با محصول مرکزی (0 = وجود ندارد) */
	public static function local_id( $remote_id ) {
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_key'       => '_dastyar_remote_id',
			'meta_value'     => (int) $remote_id,
		) );
		return $ids ? (int) $ids[0] : 0;
	}

	/* ------------------------------------------------------------------
	 * (v1.7.8 — بازخورد کاربر) خروج موقت یک محصول از حالت دراپ‌شیپینگ:
	 * با متا _dastyarc_detached محصول «از دستیار جدا» می‌شود → فروشنده از انبار خودش ارسال می‌کند؛
	 * در این حالت هیچ سینک قیمت/موجودی/محتوای مرکزی روی آن نوشته نمی‌شود و سفارش‌هایش هم به مرکز نمی‌رود.
	 * با «اتصال مجدد» متا برداشته می‌شود تا همان محصول دوباره به پلتفرم متصل شود.
	 * ---------------------------------------------------------------- */

	/** آیا محصول محلی از دراپ‌شیپینگ خارج (به انبار خود فروشنده) شده است؟ */
	public static function is_detached( $local_id ) {
		return (bool) get_post_meta( (int) $local_id, '_dastyarc_detached', true );
	}

	/** خروج از دراپ‌شیپینگ (نگه‌داشتن نگاشت مرکزی برای اتصال مجدد) */
	public static function detach( $local_id ) {
		update_post_meta( (int) $local_id, '_dastyarc_detached', 1 );
	}

	/** اتصال مجدد به پلتفرم (حذف پرچم خروج) */
	public static function reattach( $local_id ) {
		delete_post_meta( (int) $local_id, '_dastyarc_detached' );
	}

	/** خطای استاندارد وضعیت جداشده — برای نمایش شفاف به فروشنده */
	public static function detached_error() {
		return new WP_Error( 'dastyarc_detached', 'این محصول از حالت دراپ‌شیپینگ خارج شده و فعلاً از انبار خود شما مدیریت می‌شود. برای همگام‌سازی با مرکز، ابتدا «اتصال مجدد» را بزنید.' );
	}

	/**
	 * واکشی محصول از مرکز و ذخیره
	 * @param int    $remote_id  شناسه محصول در مرکز
	 * @param string $new_status وضعیت محصولِ تازه‌ساخته‌شده: draft (پیش‌فرض) یا publish — فقط هنگام «ایجاد» اعمال می‌شود
	 */
	public static function import_remote( $remote_id, $new_status = 'draft' ) {
		$data = DastyarC_Client::product( $remote_id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return self::save( $data, $new_status );
	}

	/**
	 * ذخیره محصول (ایجاد یا به‌روزرسانی)
	 * @param string $new_status وضعیت محصولِ تازه‌ساخته‌شده (فقط هنگام ایجاد) — رفتار قبلی: draft
	 * @return int|WP_Error شناسه محصول محلی
	 */
	public static function save( array $data, $new_status = 'draft' ) {
		$remote_id = (int) ( $data['id'] ?? 0 );
		if ( ! $remote_id ) {
			return new WP_Error( 'dastyarc_bad_data', 'داده محصول نامعتبر است.' );
		}

		$local_id = self::local_id( $remote_id );
		$is_new   = ! $local_id;

		// (v1.7.8) محصول «از دراپ‌شیپینگ خارج» شده ← هیچ سینکی (قیمت/موجودی/محتوا) روی آن اعمال نمی‌شود
		if ( ! $is_new && self::is_detached( $local_id ) ) {
			return self::detached_error();
		}

		// اگر محتوا تغییر نکرده، فقط قیمت/موجودی طبق فرمول بازمحاسبه شود
		$hash        = (string) ( $data['hash'] ?? '' );
		$stored_hash = $local_id ? (string) get_post_meta( $local_id, '_dastyar_hash', true ) : '';
		// خودترمیمی نسخه (v1.7.3): محصولی که با نسخه قدیمی‌تر کانکتور وارد شده (مثلاً نوع/ویژگی خراب)
		// با نخستین سینک پس از به‌روزرسانی، «یک بار» بازسازی کامل می‌شود حتی اگر hash مرکز ثابت مانده باشد —
		// یعنی ویژگی‌های جداشده با «|»، نوع محصول و تنوع‌ها با منطق جدید دوباره اعمال می‌شوند.
		$imported_by   = $local_id ? (string) get_post_meta( $local_id, '_dastyar_imported_by', true ) : '';
		$needs_rebuild = ! $is_new && ( defined( 'DASTYARC_VERSION' ) ? DASTYARC_VERSION : '' ) !== $imported_by;
		$unchanged     = ! $is_new && ! $needs_rebuild && $hash && $hash === $stored_hash;

		$type    = ( $data['type'] ?? 'simple' ) === 'variable' ? 'variable' : 'simple';
		$product = $is_new
			? ( 'variable' === $type ? new WC_Product_Variable() : new WC_Product_Simple() )
			: wc_get_product( $local_id );

		if ( ! $product ) {
			return new WP_Error( 'dastyarc_no_product', 'محصول محلی یافت نشد.' );
		}

		// اصلاح نوع محصول محلی (v1.7.2): اگر نوع مرکز با نوع فعلی فروشگاه یکی نیست
		// (مثلاً محصول مرکز بعداً متغیر شده یا محصولِ قدیماً به‌اشتباه «ساده» وارد شده)،
		// شی با کلاس صحیح بازسازی می‌شود تا هنگام save همان ذخیره‌ساز ووکامرس
		// تاکسونومی product_type را هم به‌روز کند ← دیگر «متغیرِ مرکز = سادهِ محلی با قیمت خالی و ناموجود» نمی‌شود.
		if ( ! $is_new && (string) $product->get_type() !== $type ) {
			$product = 'variable' === $type ? new WC_Product_Variable( $local_id ) : new WC_Product_Simple( $local_id );
			if ( 'variable' === $type ) {
				// (v1.7.3) پاکسازی متاهای قیمتِ به‌ارث‌رسیده از حالت «ساده» — میراث وارداتِ خرابِ نسخه‌های قدیمی؛
				// والد متغیر قیمت ندارد و WC_Product_Variable::sync بعداً _price را از روی تنوع‌ها بازسازی می‌کند.
				foreach ( array( '_regular_price', '_sale_price', '_price' ) as $stale ) {
					delete_post_meta( $local_id, $stale );
				}
			}
		}

		$product->set_name( sanitize_text_field( (string) ( $data['name'] ?? '' ) ) );
		$product->set_description( wp_kses_post( (string) ( $data['description'] ?? '' ) ) );
		$product->set_short_description( wp_kses_post( (string) ( $data['short_description'] ?? '' ) ) );

		if ( $is_new ) {
			// وضعیت به انتخاب فروشنده (از صفحه محصولات دستیار) — پیش‌فرض امن: پیش‌نویس
			$product->set_status( 'publish' === $new_status ? 'publish' : 'draft' );
		} elseif ( isset( $data['status'] ) && 'publish' !== $data['status'] && 'yes' === get_option( 'dastyarc_unpublish_when_hidden', 'yes' ) ) {
			// محصول در مرکز غیرفعال شده → پیش‌نویس شود
			$product->set_status( 'draft' );
		}

		$sku = self::unique_sku( (string) ( $data['sku'] ?? '' ), $product->get_id() );
		if ( $sku ) {
			$product->set_sku( $sku );
		}

		// دسته‌بندی و برچسب — با نام (در صورت نبود، ساخته می‌شود)
		// (v1.7.8) انتقال دسته‌بندی‌های مرکز به انتخاب فروشنده در «تنظیمات دستیار» کنترل می‌شود (پیش‌فرض: فعال)
		if ( 'yes' === get_option( 'dastyarc_import_cats', 'yes' ) ) {
			self::set_terms( $product, (array) ( $data['categories'] ?? array() ), 'product_cat' );
		}
		self::set_terms( $product, (array) ( $data['tags'] ?? array() ), 'product_tag' );

		// ویژگی‌ها
		if ( ! $unchanged || $is_new ) {
			$product->set_attributes( self::build_attributes( (array) ( $data['attributes'] ?? array() ) ) );
		}

		$product_id = $product->save();
		if ( ! $product_id ) {
			return new WP_Error( 'dastyarc_save_failed', 'ذخیره محصول ناموفق بود.' );
		}

		// تصاویر (فقط هنگام تغییر محتوا برای جلوگیری از دانلود تکراری)
		if ( ! $unchanged || $is_new ) {
			self::set_images( $product_id, (array) ( $data['images'] ?? array() ) );
		}

		if ( 'variable' === $type ) {
			self::save_variations( $product_id, $data, $is_new );
			WC_Product_Variable::sync( $product_id );
		} else {
			$product = wc_get_product( $product_id );
			self::apply_price_and_stock( $product, (float) ( $data['supplier_price'] ?? 0 ), $data, $is_new );
			$product->save();
		}

		update_post_meta( $product_id, '_dastyar_remote_id', $remote_id );
		update_post_meta( $product_id, '_dastyar_hash', $hash );
		update_post_meta( $product_id, '_dastyar_supplier_price', (float) ( $data['supplier_price'] ?? 0 ) );
		update_post_meta( $product_id, '_dastyar_synced_at', current_time( 'mysql' ) );
		// (v1.7.3) مهر نسخه کانکتوری که با آن وارد شده — برای بازسازی یک‌باره محصولاتِ واردشده با نسخه‌های قدیمی
		update_post_meta( $product_id, '_dastyar_imported_by', defined( 'DASTYARC_VERSION' ) ? DASTYARC_VERSION : '' );

		return $product_id;
	}

	/* ------------------------------------------------------------------
	 * قیمت و موجودی
	 * ---------------------------------------------------------------- */

	protected static function apply_price_and_stock( $product, $supplier_price, array $remote, $is_new = true ) {
		// حالت اعمال قیمت (v1.6.0 — مورد ۱۱):
		//   'auto'   = فرمول بلافاصله با هر تغییر قیمت مرکز اعمال می‌شود (رفتار قبلی)
		//   'manual' = فقط متای تامین تازه نگه‌داشته می‌شود؛ فروشنده با دکمه «اعمال به‌روزرسانی قیمت‌ها» اعمال می‌کند
		$manual_mode = 'manual' === get_option( 'dastyarc_price_apply_mode', 'auto' );

		if ( ! $manual_mode || $is_new ) {
			$price = DastyarC_Price::calculate( $supplier_price, $product->get_id() );
			$product->set_regular_price( $price );
			$product->set_price( $price );
		} else {
			// حالت دستی: فقط در صورت تغییر قیمت تامین، محصول در صف «اعمال» علامت می‌خورد
			$old_supplier = (float) get_post_meta( $product->get_id(), '_dastyar_supplier_price', true );
			if ( $old_supplier !== (float) $supplier_price ) {
				update_post_meta( $product->get_id(), '_dastyar_price_pending', 1 );
			}
		}

		// همگام‌سازی خودکار موجودی (طبق تنظیم فروشنده)
		if ( 'yes' === get_option( 'dastyarc_stock_sync', 'yes' ) ) {
			$manage = ! empty( $remote['manage_stock'] );
			$product->set_manage_stock( $manage );
			if ( $manage ) {
				$product->set_stock_quantity( (int) ( $remote['stock_quantity'] ?? 0 ) );
			}
			$product->set_stock_status( (string) ( $remote['stock_status'] ?? 'instock' ) );
		}

		foreach ( array( 'weight', 'length', 'width', 'height' ) as $dim ) {
			if ( isset( $remote[ $dim ] ) && '' !== $remote[ $dim ] ) {
				$product->{"set_$dim"}( $remote[ $dim ] );
			}
		}
	}

	/**
	 * سینک سبک «فقط موجودی» — بدون بازنویسی کل محصول (v1.4.0).
	 * داده ورودی از endpoint /stock مرکز است؛ فقط وقتی چیزی تغییر کرده save می‌شود.
	 *
	 * @param int   $local_id شناسه محصول محلی فروشگاه
	 * @param array $remote   خروجی stock_payload مرکز (id, status, manage_stock, stock_quantity, stock_status, variations[])
	 * @return bool آیا چیزی تغییر کرد؟
	 */
	public static function apply_stock_only( $local_id, array $remote ) {
		// (v1.7.8) محصول جداشده ← سینک موجودی هم اعمال نمی‌شود (انبار دست فروشنده است)
		if ( self::is_detached( (int) $local_id ) ) {
			return false;
		}
		$product = wc_get_product( (int) $local_id );
		if ( ! $product ) {
			return false;
		}
		$changed = false;

		// غیرمنتشر شدن در مرکز → پیش‌نویس (هم‌راستا با رفتار واردات اصلی)
		if ( isset( $remote['status'] ) && 'publish' !== (string) $remote['status']
			&& 'yes' === get_option( 'dastyarc_unpublish_when_hidden', 'yes' )
			&& 'draft' !== $product->get_status() ) {
			$product->set_status( 'draft' );
			$changed = true;
		}

		if ( 'yes' === get_option( 'dastyarc_stock_sync', 'yes' ) ) {
			if ( isset( $remote['stock_status'] ) && (string) $remote['stock_status'] !== (string) $product->get_stock_status() ) {
				$product->set_stock_status( (string) $remote['stock_status'] );
				$changed = true;
			}
			if ( isset( $remote['manage_stock'] ) && (bool) $remote['manage_stock'] !== (bool) $product->get_manage_stock() ) {
				$product->set_manage_stock( (bool) $remote['manage_stock'] );
				$changed = true;
			}
			if ( $product->get_manage_stock() && isset( $remote['stock_quantity'] )
				&& null !== $remote['stock_quantity'] && (int) $remote['stock_quantity'] !== (int) $product->get_stock_quantity() ) {
				$product->set_stock_quantity( (int) $remote['stock_quantity'] );
				$changed = true;
			}
		}

		if ( $changed ) {
			$product->save();
		}

		// وارییشن‌ها (محصول متغیر) — نگاشت بر اساس شناسه وارییشن مرکزی
		if ( ! empty( $remote['variations'] ) && is_array( $remote['variations'] ) ) {
			$map = array();
			foreach ( (array) get_posts( array(
				'post_type'      => 'product_variation',
				'post_parent'    => $product->get_id(),
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_key'       => '_dastyar_remote_var_id',
			) ) as $vid ) {
				$map[ (int) get_post_meta( $vid, '_dastyar_remote_var_id', true ) ] = (int) $vid;
			}
			foreach ( $remote['variations'] as $v ) {
				$rvid = (int) ( $v['id'] ?? 0 );
				if ( ! $rvid || empty( $map[ $rvid ] ) ) {
					continue;
				}
				$variation = wc_get_product( $map[ $rvid ] );
				if ( ! $variation ) {
					continue;
				}
				$vchanged = false;
				if ( 'yes' === get_option( 'dastyarc_stock_sync', 'yes' ) ) {
					if ( isset( $v['stock_status'] ) && (string) $v['stock_status'] !== (string) $variation->get_stock_status() ) {
						$variation->set_stock_status( (string) $v['stock_status'] );
						$vchanged = true;
					}
					if ( isset( $v['manage_stock'] ) && (bool) $v['manage_stock'] !== (bool) $variation->get_manage_stock() ) {
						$variation->set_manage_stock( (bool) $v['manage_stock'] );
						$vchanged = true;
					}
					if ( $variation->get_manage_stock() && isset( $v['stock_quantity'] )
						&& null !== $v['stock_quantity'] && (int) $v['stock_quantity'] !== (int) $variation->get_stock_quantity() ) {
						$variation->set_stock_quantity( (int) $v['stock_quantity'] );
						$vchanged = true;
					}
				}
				if ( $vchanged ) {
					$variation->save();
					$changed = true;
				}
			}
		}

		if ( $changed ) {
			update_post_meta( $product->get_id(), '_dastyar_synced_at', current_time( 'mysql' ) );
		}
		return $changed;
	}

	/**
	 * اعمال فرمول قیمت‌گذاری روی همه محصولات همگام‌شده (v1.6.0 — مورد ۱۱)
	 * در حالت «دستی» با دکمه «اعمال به‌روزرسانی قیمت‌ها» صدا زده می‌شود.
	 * محصولات ساده: بر اساس متای _dastyar_supplier_price — وارییشن‌ها: بر اساس متای خودشان.
	 * @return int تعداد محصولاتی که قیمتشان تغییر کرد
	 */
	public static function apply_pending_prices() {
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'draft' ),
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_dastyar_remote_id',
		) );
		$done = 0;
		foreach ( (array) $ids as $pid ) {
			$done += self::apply_price_now( (int) $pid ) ? 1 : 0;
		}
		return $done;
	}

	/** اعمال فرمول روی یک محصول محلی همگام‌شده (والد + وارییشن‌ها) */
	public static function apply_price_now( $local_id ) {
		$local_id = (int) $local_id;
		if ( ! get_post_meta( $local_id, '_dastyar_remote_id', true ) ) {
			return false;
		}
		// (v1.7.8) محصول جداشده ← اعمال دستی قیمت هم انجام نمی‌شود
		if ( self::is_detached( $local_id ) ) {
			return false;
		}
		$product  = wc_get_product( $local_id );
		if ( ! $product ) {
			return false;
		}
		$changed  = false;
		$supplier = (float) get_post_meta( $local_id, '_dastyar_supplier_price', true );

		if ( $product->is_type( 'variable' ) ) {
			foreach ( (array) $product->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( ! $v ) {
					continue;
				}
				$v_supplier = (float) get_post_meta( $vid, '_dastyar_supplier_price', true );
				if ( $v_supplier <= 0 ) {
					$v_supplier = $supplier;
				}
				$price = DastyarC_Price::calculate( $v_supplier, $local_id );
				if ( (string) $price !== (string) $v->get_regular_price() ) {
					$v->set_regular_price( $price );
					$v->set_price( $price );
					$v->save();
					$changed = true;
				}
			}
			WC_Product_Variable::sync( $local_id );
		} else {
			$price = DastyarC_Price::calculate( $supplier, $local_id );
			if ( (string) $price !== (string) $product->get_regular_price() ) {
				$product->set_regular_price( $price );
				$product->set_price( $price );
				$product->save();
				$changed = true;
			}
		}

		delete_post_meta( $local_id, '_dastyar_price_pending' );
		return $changed;
	}

	/* ------------------------------------------------------------------
	 * دسته‌بندی / برچسب
	 * ---------------------------------------------------------------- */

	protected static function set_terms( $product, array $terms, $taxonomy ) {
		$ids = array();
		foreach ( $terms as $t ) {
			$name = sanitize_text_field( (string) ( $t['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$exists = term_exists( $name, $taxonomy );
			if ( ! $exists ) {
				$exists = wp_insert_term( $name, $taxonomy );
			}
			if ( ! is_wp_error( $exists ) && ! empty( $exists['term_id'] ) ) {
				$ids[] = (int) $exists['term_id'];
			}
		}
		if ( 'product_cat' === $taxonomy ) {
			$product->set_category_ids( $ids );
		} else {
			$product->set_tag_ids( $ids );
		}
	}

	/* ------------------------------------------------------------------
	 * ویژگی‌ها (Attributes) — ترجیح: سراسری (pa_*) در صورت وجود، در غیر این صورت اختصاصی
	 * ---------------------------------------------------------------- */

		protected static function build_attributes( array $attrs ) {
		$out = array();
		$i   = 0;
		foreach ( $attrs as $a ) {
			$name    = (string) ( $a['name'] ?? '' );
			$options = array_values( array_filter( array_map( 'strval', (array) ( $a['options'] ?? array() ) ) ) );
			if ( '' === $name || ! $options ) {
				continue;
			}
			// سازگاری (v1.7.2): اگر گزینه‌ها با جداکننده «|» در یک رشته رسیدند (نسخه‌های قدیمی مرکز یا
			// ورود رشته‌ای مدیر)، این‌جا هم به لیست تجزیه می‌شوند تا وارییشن‌ها صحیح مطابقت پیدا کنند
			$options = self::split_options( $options );

			$attr       = new WC_Product_Attribute();
			$tax        = ! empty( $a['is_taxonomy'] ) ? (string) ( $a['slug'] ?? '' ) : '';
			$tax_exists = $tax && taxonomy_exists( $tax );

			if ( $tax_exists ) {
				$term_ids = array();
				foreach ( $options as $option ) {
					$exists = term_exists( $option, $tax );
					if ( ! $exists ) {
						$exists = wp_insert_term( $option, $tax );
					}
					if ( ! is_wp_error( $exists ) ) {
						$term_ids[] = (int) $exists['term_id'];
					}
				}
				if ( $term_ids ) {
					$attr->set_id( wc_attribute_taxonomy_id_by_name( $tax ) );
					$attr->set_name( $tax );
					$attr->set_options( $term_ids );
				} else {
					$tax_exists = false;
				}
			}

			if ( ! $tax_exists ) {
				$attr->set_id( 0 );
				$attr->set_name( $name );
				$attr->set_options( $options );
			}

			$attr->set_position( $i++ );
			$attr->set_visible( ! empty( $a['visible'] ) );
			$attr->set_variation( ! empty( $a['variation'] ) );
			$out[] = $attr;
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * وارییشن‌ها
	 * ---------------------------------------------------------------- */

	protected static function save_variations( $product_id, array $data, $is_new = true ) {
		$supplier_default = (float) ( $data['supplier_price'] ?? 0 );
		$manual_mode      = 'manual' === get_option( 'dastyarc_price_apply_mode', 'auto' ); // v1.6.0
		$seen             = array();

		// نقشه وارییشن‌های محلی موجود بر اساس شناسه مرکزی
		$map = array();
		foreach ( (array) get_posts( array(
			'post_type'      => 'product_variation',
			'post_parent'    => $product_id,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => '_dastyar_remote_var_id',
		) ) as $vid ) {
			$map[ (int) get_post_meta( $vid, '_dastyar_remote_var_id', true ) ] = (int) $vid;
		}

		foreach ( (array) ( $data['variations'] ?? array() ) as $v ) {
			$rvid    = (int) ( $v['id'] ?? 0 );
			$seen[]  = $rvid;
			$local_v = isset( $map[ $rvid ] ) ? new WC_Product_Variation( $map[ $rvid ] ) : new WC_Product_Variation();
			$local_v->set_parent_id( $product_id );
			$local_v->set_status( 'publish' );

			// ویژگی‌های وارییشن
			$attrs = array();
			foreach ( (array) ( $v['attributes'] ?? array() ) as $at ) {
				if ( ! empty( $at['taxonomy'] ) && taxonomy_exists( $at['taxonomy'] ) ) {
					$term = get_term_by( 'name', (string) $at['value'], $at['taxonomy'] );
					if ( ! $term ) {
						$created = wp_insert_term( (string) $at['value'], $at['taxonomy'] );
						$term    = is_wp_error( $created ) ? null : get_term( $created['term_id'] );
					}
					if ( $term ) {
						$attrs[ $at['taxonomy'] ] = $term->slug;
					}
				} else {
					$key           = 'attribute_' === substr( (string) $at['name'], 0, 10 ) ? (string) $at['name'] : (string) $at['name'];
					$attrs[ $key ] = (string) $at['value'];
				}
			}
			$local_v->set_attributes( $attrs );

			$sku = self::unique_sku( (string) ( $v['sku'] ?? '' ), $local_v->get_id() );
			if ( $sku ) {
				$local_v->set_sku( $sku );
			}

			$supplier = isset( $v['supplier_price'] ) && (float) $v['supplier_price'] > 0 ? (float) $v['supplier_price'] : $supplier_default;

			if ( ! $manual_mode || $is_new || ! $local_v->get_id() ) {
				$price = DastyarC_Price::calculate( $supplier, $product_id );
				$local_v->set_regular_price( $price );
				$local_v->set_price( $price );
			} else {
				// حالت دستی: قیمت دست نمی‌خورد؛ در صورت تغییر تامین → در صف اعمال
				$old_v_supplier = (float) get_post_meta( (int) $local_v->get_id(), '_dastyar_supplier_price', true );
				if ( $old_v_supplier !== (float) $supplier ) {
					update_post_meta( $product_id, '_dastyar_price_pending', 1 );
				}
			}

			if ( 'yes' === get_option( 'dastyarc_stock_sync', 'yes' ) ) {
				$manage = ! empty( $v['manage_stock'] );
				$local_v->set_manage_stock( $manage );
				if ( $manage ) {
					$local_v->set_stock_quantity( (int) ( $v['stock_quantity'] ?? 0 ) );
				}
				$local_v->set_stock_status( (string) ( $v['stock_status'] ?? 'instock' ) );
			}

			$new_vid = $local_v->save();
			update_post_meta( $new_vid, '_dastyar_remote_var_id', $rvid );
			update_post_meta( $new_vid, '_dastyar_remote_product_id', (int) $data['id'] );
			update_post_meta( $new_vid, '_dastyar_supplier_price', $supplier ); // v1.6.0 — برای اعمال دستی فرمول
		}

		// وارییشن‌هایی که در مرکز حذف شده‌اند → ناموجود (حذف نمی‌کنیم تا سوابق سفارش خراب نشود)
		foreach ( $map as $rvid => $local_vid ) {
			if ( ! in_array( $rvid, $seen, true ) ) {
				$v = new WC_Product_Variation( $local_vid );
				$v->set_stock_status( 'outofstock' );
				$v->save();
			}
		}
	}

	/**
	 * تجزیه گزینه‌های ویژگی به لیست تمیز — «a | b | c» هم به سه گزینه تبدیل می‌شود (v1.7.2).
	 * (ورود با جداکننده | در محصولات متغیر روش رایج مدیران ووکامرس است)
	 */
	public static function split_options( array $options ) {
		$flat = array();
		foreach ( $options as $opt ) {
			foreach ( explode( '|', (string) $opt ) as $part ) {
				$part = trim( $part );
				if ( '' !== $part ) {
					$flat[] = $part;
				}
			}
		}
		return array_values( array_unique( $flat ) );
	}

	/* ------------------------------------------------------------------
	 * تصاویر
	 * ---------------------------------------------------------------- */

	protected static function set_images( $product_id, array $images ) {
		$attachment_ids = array();
		foreach ( $images as $img ) {
			$src = esc_url_raw( (string) ( $img['src'] ?? '' ) );
			if ( ! $src ) {
				continue;
			}
			$aid = self::sideload( $src, $product_id, (string) ( $img['alt'] ?? '' ) );
			if ( $aid ) {
				$attachment_ids[] = $aid;
			}
		}
		if ( ! $attachment_ids ) {
			return;
		}
		set_post_thumbnail( $product_id, $attachment_ids[0] );
		if ( count( $attachment_ids ) > 1 ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', array_slice( $attachment_ids, 1 ) ) );
		}
	}

	/** دانلود تصویر از مرکز و الصاق به رسانه — با جلوگیری از دانلود تکراری */
	protected static function sideload( $url, $product_id, $alt = '' ) {
		// آیا همین تصویر قبلاً برای همین محصول دانلود شده؟
		$existing = get_posts( array(
			'post_type'      => 'attachment',
			'post_parent'    => $product_id,
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_key'       => '_dastyar_remote_src',
			'meta_value'     => $url,
		) );
		if ( $existing ) {
			return (int) $existing[0];
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			DastyarC::log( 'Image download failed: ' . $url . ' — ' . $tmp->get_error_message(), 'warning' );
			return 0;
		}

		$name = basename( (string) parse_url( $url, PHP_URL_PATH ) );
		if ( ! preg_match( '/\.(jpe?g|png|gif|webp)$/i', $name ) ) {
			$mime = wp_get_image_mime( $tmp );
			$ext  = $mime ? str_replace( 'image/', '', $mime ) : 'jpg';
			$name = sanitize_file_name( 'dastyar-' . md5( $url ) . '.' . ( 'jpeg' === $ext ? 'jpg' : $ext ) );
		}

		$id = media_handle_sideload(
			array( 'name' => $name, 'tmp_name' => $tmp ),
			$product_id,
			$alt ? $alt : null
		);
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			DastyarC::log( 'Image sideload failed: ' . $url . ' — ' . $id->get_error_message(), 'warning' );
			return 0;
		}

		if ( $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}
		update_post_meta( $id, '_dastyar_remote_src', $url );
		return $id;
	}

	/* ------------------------------------------------------------------
	 * ابزارها
	 * ---------------------------------------------------------------- */

	/** SKU یکتا — در صورت تکرار، پسوند اضافه می‌شود */
	protected static function unique_sku( $sku, $exclude_id = 0 ) {
		$sku = trim( $sku );
		if ( '' === $sku ) {
			return '';
		}
		$base = $sku;
		$i    = 1;
		while ( ( $found = wc_get_product_id_by_sku( $sku ) ) && (int) $found !== (int) $exclude_id ) {
			$sku = $base . '-' . $i++;
		}
		return $sku;
	}

	/** حذف/غیرفعال شدن محصول در مرکز → پیش‌نویس در فروشگاه */
	public static function apply_deleted( $remote_id ) {
		$local_id = self::local_id( $remote_id );
		if ( ! $local_id ) {
			return;
		}
		// (v1.7.8) محصول جداشده ← حذف مرکز دیگر این محصول را پیش‌نویس نمی‌کند (مالکیت با فروشنده است)
		if ( self::is_detached( $local_id ) ) {
			return;
		}
		$product = wc_get_product( $local_id );
		if ( $product && 'draft' !== $product->get_status() ) {
			$product->set_status( 'draft' );
			$product->save();
		}
		update_post_meta( $local_id, '_dastyar_remote_deleted', current_time( 'mysql' ) );
		wp_mail(
			get_option( 'admin_email' ),
			'محصول دستیار در مرکز حذف شد',
			sprintf( 'محصول «%s» (شناسه مرکزی %d) در دستیار شاپ حذف یا غیرفعال شده و در فروشگاه شما پیش‌نویس شد.', get_the_title( $local_id ), $remote_id )
		);
	}
}
