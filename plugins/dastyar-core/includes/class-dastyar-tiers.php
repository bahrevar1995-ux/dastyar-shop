<?php
/**
 * رده‌بندی فروشندگان (محرمانه — فقط مدیر مرکز می‌بیند؛ فروشنده هرگز متوجه نمی‌شود).
 *
 * کاربرد: مدیر مرکز رده‌های دلخواه می‌سازد (مثل «طلایی»، «نقره‌ای»)، برای هر رده
 * لیست محصولات مجاز را تعیین می‌کند و فروشنده‌ها را در رده‌ها قرار می‌دهد.
 * نتیجه: هر فروشنده فقط محصولات رده خودش را در API دریافت می‌کند (لیست/تکی/موجودی/پوش).
 *
 * - فروشنده «بدون رده» = دسترسی به همه محصولات (رفتار قبلی بدون تغییر)
 * - رده با لیست خالی = هیچ محصولی برای فروشنده قابل مشاهده نیست
 * - ذخیره‌سازی: گزینه dastyar_tiers + متای کاربر _dastyar_tier
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Tiers {

	const OPTION   = 'dastyar_tiers';
	const USERMETA = '_dastyar_tier';

	/** لیست همه رده‌ها: [ ['id'=>..., 'name'=>..., 'products'=>[int,...]], ... ] */
	public static function all() {
		$tiers = get_option( self::OPTION, array() );
		if ( ! is_array( $tiers ) ) {
			return array();
		}
		return array_values( array_filter( array_map( function ( $t ) {
			if ( ! is_array( $t ) || empty( $t['id'] ) ) {
				return null;
			}
			return array(
				'id'       => (string) $t['id'],
				'name'     => (string) ( $t['name'] ?? '' ),
				'products' => array_values( array_filter( array_map( 'absint', (array) ( $t['products'] ?? array() ) ) ) ),
			);
		}, $tiers ) ) );
	}

	/** یافتن یک رده با شناسه */
	public static function find( $tier_id ) {
		foreach ( self::all() as $t ) {
			if ( (string) $t['id'] === (string) $tier_id ) {
				return $t;
			}
		}
		return null;
	}

	/** رده فروشنده (null = بدون رده → دسترسی کامل) */
	public static function tier_of( $uid ) {
		$tid = (string) get_user_meta( (int) $uid, self::USERMETA, true );
		return '' !== $tid ? self::find( $tid ) : null;
	}

	/**
	 * محصولات مجاز برای فروشنده.
	 * @return array<int>|null  null = بدون رده (همه مجاز) — وگرنه لیست شناسه‌های مجاز
	 */
	public static function allowed_ids( $uid ) {
		$tier = self::tier_of( $uid );
		if ( ! $tier ) {
			return null;
		}
		return $tier['products'];
	}

	/** آیا محصول خاصی برای فروشنده مجاز است؟ */
	public static function can_sync( $uid, $product_id ) {
		$allowed = self::allowed_ids( $uid );
		if ( null === $allowed ) {
			return true;
		}
		return in_array( (int) $product_id, $allowed, true );
	}

	/**
	 * فیلتر لیست شناسه محصولات بر اساس رده فروشنده.
	 * @return array<int>
	 */
	public static function filter_ids( $uid, array $ids ) {
		$allowed = self::allowed_ids( $uid );
		$ids     = array_map( 'intval', $ids );
		if ( null === $allowed ) {
			return array_values( $ids );
		}
		return array_values( array_intersect( $ids, $allowed ) );
	}

	/** شناسه رده نسبت‌داده‌شده به فروشنده ('' = بدون رده) */
	public static function tier_id_of( $uid ) {
		return (string) get_user_meta( (int) $uid, self::USERMETA, true );
	}

	public static function set_vendor_tier( $uid, $tier_id ) {
		$tier_id = sanitize_key( (string) $tier_id );
		if ( '' === $tier_id || ! self::find( $tier_id ) ) {
			delete_user_meta( (int) $uid, self::USERMETA );
			return false;
		}
		update_user_meta( (int) $uid, self::USERMETA, $tier_id );
		return true;
	}

	/**
	 * ذخیره رده‌ها از POST ادمین (ساخت/ویرایش/حذف).
	 * ورودی: dastyar_tiers = [ row => ['id'=>?, 'name'=>?, 'products'=>?(csv), 'delete'=>?] ]
	 */
	public static function save_from_post() {
		$rows = (array) ( $_POST['dastyar_tiers'] ?? array() );
		$out  = array();
		foreach ( $rows as $row ) {
			$row = (array) $row;
			$id  = sanitize_key( (string) ( $row['id'] ?? '' ) );
			$nam = sanitize_text_field( wp_unslash( (string) ( $row['name'] ?? '' ) ) );
			if ( '' === $nam || ! empty( $row['delete'] ) ) {
				continue; // ردیف خالی یا علامت‌خورده برای حذف
			}
			if ( '' === $id ) {
				$id = sanitize_key( md5( $nam . microtime( true ) . wp_rand() ) );
				$id = substr( 't' . $id, 0, 12 );
			}
			$ids = array_values( array_filter( array_map( 'absint',
				explode( ',', str_replace( array( "\n", '،', ';', ' ' ), ',', (string) ( $row['products'] ?? '' ) ) ) ) ) );
			$out[] = array( 'id' => $id, 'name' => $nam, 'products' => $ids );
		}

		// حذف ارجاع فروشنده‌هایی که رده‌شان دیگر وجود ندارد
		$valid = wp_list_pluck( $out, 'id' );
		foreach ( get_users( array( 'meta_key' => self::USERMETA, 'fields' => 'ids', 'number' => 200 ) ) as $uid ) {
			$tid = self::tier_id_of( $uid );
			if ( '' !== $tid && ! in_array( $tid, $valid, true ) ) {
				delete_user_meta( $uid, self::USERMETA );
			}
		}

		update_option( self::OPTION, $out, false );
		return $out;
	}

	/** تعداد فروشنده‌های هر رده (برای نمایش در لیست رده‌ها) */
	public static function count_vendors( $tier_id ) {
		return count( get_users( array(
			'role'       => 'dastyar_vendor',
			'meta_key'   => self::USERMETA,
			'meta_value' => (string) $tier_id,
			'fields'     => 'ids',
		) ) );
	}
}
