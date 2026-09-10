<?php
/**
 * مدیریت فروشندگان = کاربران وردپرس با نقش dastyar_vendor
 * + تولید/اعتبارسنجی API Key مخصوص هر فروشنده
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Vendors {

	const KEY_HASH = '_dastyar_api_key_hash';
	const KEY_ENC  = '_dastyar_api_key_enc';
	const KEY_HINT = '_dastyar_api_key_hint';
	const SITE     = '_dastyar_site_url';
	const WALLET   = '_dastyar_wallet_balance';

	public function is_vendor( $uid ) {
		$u = get_userdata( $uid );
		if ( ! $u ) {
			return false;
		}
		return in_array( 'dastyar_vendor', (array) $u->roles, true ) || user_can( $uid, 'manage_woocommerce' );
	}

	/** تولید کلید جدید — متن ساده کلید فقط یک‌بار (همین‌جا) برگردانده می‌شود */
	public function generate_key( $uid ) {
		$key = 'dk_' . wp_generate_password( 48, false, false );
		update_user_meta( $uid, self::KEY_HASH, hash( 'sha256', $key ) );
		update_user_meta( $uid, self::KEY_ENC, self::encrypt( $key ) );
		update_user_meta( $uid, self::KEY_HINT, substr( $key, 0, 8 ) . '…' . substr( $key, -4 ) );
		return $key;
	}

	public function hint( $uid ) {
		return (string) get_user_meta( $uid, self::KEY_HINT, true );
	}

	/** یافتن فروشنده بر اساس API Key — مقایسه هش SHA-256 */
	public function vendor_by_key( $key ) {
		$users = get_users(
			array(
				'meta_key'   => self::KEY_HASH,
				'meta_value' => hash( 'sha256', (string) $key ),
				'number'     => 1,
				'fields'     => 'ids',
			)
		);
		return $users ? (int) $users[0] : 0;
	}

	/**
	 * کلید ساده برای امضای HMAC پیام‌های Push (مرکز → فروشنده).
	 * کلید به صورت رمزنگاری‌شده با نمک وردپرس ذخیره می‌شود.
	 */
	public function raw_key( $uid ) {
		$enc = (string) get_user_meta( $uid, self::KEY_ENC, true );
		return $enc ? self::decrypt( $enc ) : '';
	}

	private static function enc_key() {
		return substr( hash( 'sha256', wp_salt( 'auth' ) . 'dastyar-core' ), 0, 32 );
	}

	private static function enc_iv() {
		return substr( hash( 'sha256', wp_salt( 'secure_auth' ) . 'dastyar-core' ), 0, 16 );
	}

	public static function encrypt( $plain ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $plain );
		}
		return base64_encode( openssl_encrypt( $plain, 'AES-256-CBC', self::enc_key(), 0, self::enc_iv() ) );
	}

	public static function decrypt( $enc ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $enc );
		}
		$out = openssl_decrypt( base64_decode( $enc ), 'AES-256-CBC', self::enc_key(), 0, self::enc_iv() );
		return false === $out ? '' : $out;
	}

	public function site_url( $uid ) {
		return (string) get_user_meta( $uid, self::SITE, true );
	}

	public function set_site_url( $uid, $url ) {
		$url = esc_url_raw( (string) $url );
		if ( $url ) {
			update_user_meta( $uid, self::SITE, untrailingslashit( $url ) );
		}
	}

	/** لیست فروشنده‌هایی که اتصال فعال دارند (کلید + آدرس سایت) */
	public function connected_vendors() {
		$out = array();
		foreach ( get_users( array( 'role' => 'dastyar_vendor', 'fields' => 'ids' ) ) as $uid ) {
			if ( $this->site_url( $uid ) && $this->hint( $uid ) ) {
				$out[] = (int) $uid;
			}
		}
		return $out;
	}
}
