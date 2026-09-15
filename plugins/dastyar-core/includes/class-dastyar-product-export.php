<?php
/**
 * تبدیل WC_Product سایت مرکزی به JSON استاندارد برای ارسال به سایت فروشنده.
 * نکته مهم: قیمت اصلی محصول ارسال نمی‌شود — فقط supplier_price.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Product_Export {

	public static function format( $product ) {
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( $product );
		}
		if ( ! $product ) {
			return array();
		}

		$data = array(
			'id'               => $product->get_id(),
			'name'             => $product->get_name(),
			'type'             => $product->get_type(),
			'status'           => $product->get_status(),
			'sku'              => $product->get_sku(),
			'description'      => $product->get_description(),
			'short_description'=> $product->get_short_description(),
			'supplier_price'   => self::supplier_price( $product ),
			'manage_stock'     => $product->get_manage_stock(),
			'stock_quantity'   => $product->get_stock_quantity(),
			'stock_status'     => $product->get_stock_status(),
			'weight'           => $product->get_weight(),
			'length'           => $product->get_length(),
			'width'            => $product->get_width(),
			'height'           => $product->get_height(),
			'categories'       => self::terms( $product->get_id(), 'product_cat' ),
			'tags'             => self::terms( $product->get_id(), 'product_tag' ),
			'images'           => self::images( $product ),
			'attributes'       => self::attributes( $product ),
			'variations'       => array(),
			'modified'         => get_post_modified_time( 'c', true, $product->get_id() ),
		);

		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$data['variations'][] = self::variation( $variation );
				}
			}
		}

		// اثرانگشت محتوا — برای تشخیص تغییر در سمت فروشنده
		$data['hash'] = md5( wp_json_encode( $data ) );

		return apply_filters( 'dastyar_export_product', $data, $product );
	}

	/**
	 * خروجی سبک «فقط موجودی» — برای endpoint سینک سریع موجودی (بدون تصویر/توضیحات).
	 * شامل موجودی خود محصول + همه وارییشن‌ها (برای محصولات متغیر).
	 */
	public static function stock_payload( $product ) {
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( $product );
		}
		if ( ! $product ) {
			return array();
		}
		$data = array(
			'id'             => $product->get_id(),
			'status'         => $product->get_status(),
			'manage_stock'   => $product->get_manage_stock(),
			'stock_quantity' => $product->get_stock_quantity(),
			'stock_status'   => $product->get_stock_status(),
			'variations'     => array(),
		);
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$data['variations'][] = array(
						'id'             => $variation->get_id(),
						'manage_stock'   => $variation->get_manage_stock(),
						'stock_quantity' => $variation->get_stock_quantity(),
						'stock_status'   => $variation->get_stock_status(),
					);
				}
			}
		}
		return $data;
	}

	/** قیمت تامین‌کننده (متا اختصاصی دستیار). اگر تنظیم نشده، قیمت محصول مرکز = همان قیمت تامین در نظر گرفته می‌شود. */
	public static function supplier_price( $product ) {
		$meta = $product->get_meta( '_dastyar_supplier_price', true );
		if ( '' === $meta || null === $meta ) {
			$meta = $product->get_price();
			// محصول متغیر بدون متای تامین روی والد: کوچک‌ترین قیمت تامینِ وارییشن‌ها (v1.7.1)
			// — قبلاً get_price والد متغیر خالی/نادرست برمی‌گشت و لیست فروشنده قیمت‌های معیوب مثل «۱ تومان» نشان می‌داد
			if ( $product->is_type( 'variable' ) ) {
				$prices = array();
				foreach ( $product->get_children() as $vid ) {
					$variation = wc_get_product( $vid );
					if ( ! $variation ) {
						continue;
					}
					$vprice = self::supplier_price( $variation );
					if ( $vprice > 0 ) {
						$prices[] = $vprice;
					}
				}
				if ( $prices ) {
					$meta = min( $prices );
				}
			}
		}
		return (float) $meta;
	}

	/**
	 * گزینه‌های ویژگی به لیست تمیز (v1.7.1).
	 * برخی فروشگاه‌ها گزینه‌های ویژگی اختصاصی را با جداکننده «|» در یک رشته وارد می‌کنند
	 * («سفید | مشکی») — بدون این تجزیه، محصول متغیر در سایت فروشنده با یک گزینه واحدِ
	 * متشکل از چ متن چسبیده ساخته می‌شد و مطابقت وارییشن‌ها شکسته می‌شد.
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

	public static function terms( $product_id, $taxonomy ) {
		$out   = array();
		$terms = get_the_terms( $product_id, $taxonomy );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[] = array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug );
			}
		}
		return $out;
	}

	public static function images( $product ) {
		$out = array();
		$ids = array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) );
		foreach ( $ids as $aid ) {
			$src = wp_get_attachment_image_src( $aid, 'full' );
			if ( ! $src ) {
				continue;
			}
			$out[] = array(
				'src'  => $src[0],
				'alt'  => (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ),
				'name' => get_the_title( $aid ),
			);
		}
		return $out;
	}

	public static function attributes( $product ) {
		$out = array();
		foreach ( $product->get_attributes() as $attr ) {
			if ( ! $attr instanceof WC_Product_Attribute ) {
				continue;
			}
			$is_tax = (bool) $attr->is_taxonomy();
			$options = array();
			if ( $is_tax ) {
				$terms = wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) );
				$options = array_values( (array) $terms );
			} else {
				// ویژگی اختصاصی: رشته‌های جداشده با «|» هم تجزیه می‌شوند (v1.7.1)
				$options = self::split_options( (array) $attr->get_options() );
			}
			$out[] = array(
				'name'        => wc_attribute_label( $attr->get_name() ),
				'slug'        => $attr->get_name(),
				'is_taxonomy' => $is_tax,
				'options'     => $options,
				'variation'   => (bool) $attr->get_variation(),
				'visible'     => (bool) $attr->get_visible(),
			);
		}
		return $out;
	}

	public static function variation( WC_Product_Variation $variation ) {
		$attrs = array();
		foreach ( $variation->get_attributes() as $key => $value ) {
			$tax = str_replace( 'attribute_', '', $key );
			if ( taxonomy_exists( $tax ) ) {
				$term    = get_term_by( 'slug', $value, $tax );
				$attrs[] = array(
					'taxonomy' => $tax,
					'name'     => wc_attribute_label( $tax ),
					'value'    => $term ? $term->name : $value,
					'slug'     => $value,
				);
			} else {
				$attrs[] = array(
					'taxonomy' => '',
					'name'     => $tax,
					'value'    => $value,
					'slug'     => '',
				);
			}
		}
		return array(
			'id'             => $variation->get_id(),
			'sku'            => $variation->get_sku(),
			'supplier_price' => self::supplier_price( $variation ),
			'manage_stock'   => $variation->get_manage_stock(),
			'stock_quantity' => $variation->get_stock_quantity(),
			'stock_status'   => $variation->get_stock_status(),
			'weight'         => $variation->get_weight(),
			'length'         => $variation->get_length(),
			'width'          => $variation->get_width(),
			'height'         => $variation->get_height(),
			'image'          => ( $i = wp_get_attachment_image_src( $variation->get_image_id(), 'full' ) ) ? $i[0] : '',
			'attributes'     => $attrs,
		);
	}
}
