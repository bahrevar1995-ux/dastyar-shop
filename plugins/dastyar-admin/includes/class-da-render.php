<?php
/**
 * هلپرهای رندر کنسول: کارت برنددار، جدول، پیام، کادر KPI.
 * خروجی‌ها همیشه از esc_* عبور می‌کنند؛ آیکون‌ها SVG ساده‌اند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Render {

	/** کارت ماژول با هدر (عنوان + توضیح + شماره ایده) */
	public static function card( array $mod, $body_html ) {
		echo '<section class="da-card">';
		echo '<header class="da-card-h"><div><h3>' . esc_html( $mod['title'] )
			. ' <span class="da-modid">#' . (int) $mod['n'] . '</span></h3>'
			. '<p>' . esc_html( $mod['desc'] ) . '</p></div></header>';
		echo '<div class="da-card-b">' . $body_html . '</div>';
		echo '</section>';
	}

	/** کادر KPI بزرگ */
	public static function kpi( $label, $value, $sub = '', $cls = '' ) {
		return '<div class="da-kpi ' . esc_attr( $cls ) . '"><span class="da-kpi-v">' . $value . '</span>'
			. '<span class="da-kpi-l">' . esc_html( $label ) . '</span>'
			. ( $sub ? '<span class="da-kpi-s">' . $sub . '</span>' : '' ) . '</div>';
	}

	/** جدول ساده از آرایه ردیف‌ها */
	public static function table( array $head, array $rows, $cls = '' ) {
		$out = '<table class="widefat striped da-table ' . esc_attr( $cls ) . '"><thead><tr>';
		foreach ( $head as $h ) {
			$out .= '<th>' . esc_html( (string) $h ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		if ( ! $rows ) {
			$out .= '<tr><td colspan="' . count( $head ) . '"><span class="da-empty">موردی یافت نشد.</span></td></tr>';
		}
		foreach ( $rows as $r ) {
			$out .= '<tr>';
			foreach ( $r as $c ) {
				$out .= '<td>' . $c . '</td>'; // سلول‌ها از سمت سازنده امن‌سازی می‌شوند
			}
			$out .= '</tr>';
		}
		$out .= '</tbody></table>';
		return $out;
	}

	/** پیل وضعیت رنگی */
	public static function pill( $text, $tone = 'green' ) {
		return '<span class="da-pill da-pill-' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
	}

	/** خلاصه موفقیت/خطا بعد از اکشن‌ها (از پارامتر GET) */
	public static function notices() {
		$map = array(
			'saved'     => array( 'success', 'ذخیره شد.' ),
			'done'      => array( 'success', 'انجام شد.' ),
			'error'     => array( 'error', 'خطا رخ داد؛ دوباره تلاش کنید.' ),
			'noop'      => array( 'warning', 'موردی برای انجام نبود.' ),
			'denied'    => array( 'error', 'دسترسی یا نشست نامعتبر است.' ),
		);
		$k = sanitize_key( (string) ( $_GET['da_msg'] ?? '' ) );
		$e = sanitize_text_field( (string) ( $_GET['da_err'] ?? '' ) );
		if ( $e ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $e ) . '</p></div>';
		}
		if ( isset( $map[ $k ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $map[ $k ][0] ) . ' is-dismissible"><p>' . esc_html( $map[ $k ][1] ) . '</p></div>';
		}
		$c = (int) ( $_GET['da_count'] ?? 0 );
		if ( $c > 0 ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $c ) . ' مورد با موفقیت پردازش شد.</p></div>';
		}
	}

	/** لینک برگشت — اگر رفرر (صفحه‌ای که فرم از آن ارسال شد) موجود باشد، دقیقاً همان‌جا
	 *  (چه پیشخوان وردپرس، چه پنل مستقل کارمندان) با پیام نتیجه برمی‌گردیم؛ در غیر این
	 *  صورت به صفحه پیشخوان وردپرس مربوطه بازمی‌گردیم (رفتار قدیم، سازگاری کامل). */
	public static function back_url( $page, $msg = 'done', $extra = array() ) {
		$args = array_merge( array( 'da_msg' => $msg ), $extra );
		$ref  = wp_get_referer();
		if ( $ref ) {
			return add_query_arg( $args, $ref );
		}
		$args['page'] = $page;
		return admin_url( 'admin.php?' . http_build_query( $args ) );
	}

	/** wrapper ریدایرکت استاندارد اکشن‌ها */
	public static function redirect_back( $page, $msg = 'done', $extra = array() ) {
		wp_safe_redirect( self::back_url( $page, $msg, $extra ) );
		exit;
	}
}
