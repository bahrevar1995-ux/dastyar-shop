<?php
/**
 * مدیریت کارمندان دستیار — فقط برای مدیر واقعی سایت (نه خودِ کارمندها) قابل مشاهده است.
 * ساخت/فهرست/لغو دسترسی حساب‌های نقش «کارمند دستیار» (DA_Staff::ROLE) — بدون نیاز
 * به رفتن به پیشخوان وردپرس ← کاربران.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Page_Staff {

	const NONCE = 'da_staff';

	public static function render() {
		if ( ! DA_Staff::current_user_is_owner() ) {
			echo '<div class="da-card"><p class="da-empty">این بخش فقط برای مدیر اصلی مجموعه است.</p></div>';
			return;
		}
		echo '<h1 class="da-page-title"><span class="da-logo-dot"></span>مدیریت کارمندان</h1>';
		DA_Render::notices();

		self::render_form();
		self::render_list();
	}

	protected static function render_form() {
		echo '<div class="da-card"><header class="da-card-h"><div><h3>افزودن کارمند جدید</h3><p>یک حساب کاربری با دسترسی کامل به پنل مدیریت (بدون دسترسی به پیشخوان وردپرس) ساخته می‌شود.</p></div></header>';
		echo '<div class="da-card-b">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="action" value="da_staff_save">';
		echo '<input type="hidden" name="staff_action" value="create">';
		echo '<div class="da-grid" style="margin-bottom:12px">';
		echo '<div><label style="display:block;font-size:12px;font-weight:700;margin-bottom:5px">نام نمایشی</label><input type="text" name="display_name" required style="width:100%;border:1.5px solid #d6e6dc;border-radius:9px;padding:8px 10px;font-family:inherit"></div>';
		echo '<div><label style="display:block;font-size:12px;font-weight:700;margin-bottom:5px">نام کاربری</label><input type="text" name="user_login" required dir="ltr" style="width:100%;border:1.5px solid #d6e6dc;border-radius:9px;padding:8px 10px;font-family:inherit"></div>';
		echo '<div><label style="display:block;font-size:12px;font-weight:700;margin-bottom:5px">ایمیل</label><input type="email" name="user_email" required dir="ltr" style="width:100%;border:1.5px solid #d6e6dc;border-radius:9px;padding:8px 10px;font-family:inherit"></div>';
		echo '<div><label style="display:block;font-size:12px;font-weight:700;margin-bottom:5px">رمز عبور موقت</label><input type="text" name="user_pass" required dir="ltr" style="width:100%;border:1.5px solid #d6e6dc;border-radius:9px;padding:8px 10px;font-family:inherit" placeholder="حداقل ۸ کاراکتر"></div>';
		echo '</div>';
		echo '<button type="submit" class="da-btn">➕ ساخت حساب کارمند</button>';
		echo '</form></div></div>';
	}

	protected static function render_list() {
		$staff = get_users( array( 'role' => DA_Staff::ROLE, 'orderby' => 'registered', 'order' => 'DESC' ) );
		$rows  = array();
		$nonce = wp_create_nonce( self::NONCE );
		foreach ( $staff as $u ) {
			$revoke  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline" onsubmit="return confirm(\'دسترسی این کارمند لغو شود؟\');">'
				. '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">'
				. '<input type="hidden" name="action" value="da_staff_save">'
				. '<input type="hidden" name="staff_action" value="revoke">'
				. '<input type="hidden" name="uid" value="' . (int) $u->ID . '">'
				. '<button type="submit" class="da-btn red">لغو دسترسی</button></form>';
			$rows[] = array(
				esc_html( $u->display_name ),
				esc_html( $u->user_login ),
				esc_html( $u->user_email ),
				esc_html( mysql2date( 'Y/m/d', $u->user_registered ) ),
				$revoke,
			);
		}
		echo '<div class="da-card"><header class="da-card-h"><div><h3>کارمندان فعال</h3><p>' . count( $staff ) . ' نفر دسترسی پنل دارند</p></div></header>';
		echo '<div class="da-card-b">' . DA_Render::table( array( 'نام', 'نام کاربری', 'ایمیل', 'تاریخ عضویت', '' ), $rows ) . '</div></div>';
	}

	public static function save() {
		if ( ! DA_Staff::current_user_is_owner() || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), self::NONCE ) ) {
			wp_die( 'دسترسی غیرمجاز یا نشست نامعتبر.' );
		}
		$staff_action = sanitize_key( $_POST['staff_action'] ?? '' );

		if ( 'create' === $staff_action ) {
			$login = sanitize_user( wp_unslash( $_POST['user_login'] ?? '' ) );
			$email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
			$name  = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
			$pass  = (string) ( $_POST['user_pass'] ?? '' );

			if ( '' === $login || ! is_email( $email ) || strlen( $pass ) < 8 ) {
				DA_Render::redirect_back( 'da-dash', 'error', array( 'da_err' => 'اطلاعات وارد شده کامل/معتبر نیست (رمز حداقل ۸ کاراکتر).' ) );
			}
			if ( username_exists( $login ) || email_exists( $email ) ) {
				DA_Render::redirect_back( 'da-dash', 'error', array( 'da_err' => 'این نام کاربری یا ایمیل قبلاً استفاده شده است.' ) );
			}
			$uid = wp_insert_user( array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name ?: $login,
				'role'         => DA_Staff::ROLE,
			) );
			if ( is_wp_error( $uid ) ) {
				DA_Render::redirect_back( 'da-dash', 'error', array( 'da_err' => $uid->get_error_message() ) );
			}
			DA_Render::redirect_back( 'da-dash', 'done' );
		} elseif ( 'revoke' === $staff_action ) {
			$uid = (int) ( $_POST['uid'] ?? 0 );
			$u   = $uid ? get_userdata( $uid ) : false;
			if ( $u && in_array( DA_Staff::ROLE, (array) $u->roles, true ) ) {
				$u->set_role( 'subscriber' ); // دسترسی لغو می‌شود؛ حساب کاربری حذف نمی‌شود
			}
			DA_Render::redirect_back( 'da-dash', 'done' );
		} else {
			DA_Render::redirect_back( 'da-dash', 'error' );
		}
	}
}
