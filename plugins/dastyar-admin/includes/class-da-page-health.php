<?php
/**
 * صفحه «سلامت پلتفرم» — ماژول‌های ۴۳ (مانیتور اتصال فروشگاه‌ها)، ۴۴ (خطاهای API)، ۴۵ (رادار نسخه‌ها).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Page_Health {

	/* ── ۴۳ ─ آنلاین/آفلاین بر اساس آخرین ping ضبط‌شده توسط رادار کنسول */
	public static function render_m43() {
		$rows = array();
		foreach ( DA_Data::vendors() as $v ) {
			$last = (int) get_user_meta( $v['id'], '_dastyar_last_ping', true );
			$on   = $last && ( time() - $last ) < 15 * 60;
			$rows[] = array(
				esc_html( $v['shop'] ?: $v['name'] ),
				$v['site'] ? '<a href="' . esc_url( $v['site'] ) . '" target="_blank" dir="ltr">' . esc_html( $v['site'] ) . '</a>' : '—',
				$last ? esc_html( DA_Data::ago( $last ) ) : 'هنوز ثبت نشده',
				$on ? DA_Render::pill( 'آنلاین', 'green' ) : DA_Render::pill( 'آفلاین', $last ? 'amber' : 'gray' ),
			);
		}
		echo '<p class="da-hint">اگر در ۱۵ دقیقه اخیر از سایت فروشنده تماسی (راهِ افزونه مرکز) رسیده باشد «آنلاین» است. ثبت از زمان نصب کنسول آغاز می‌شود.</p>';
		echo DA_Render::table( array( 'مشتری', 'سایت', 'آخرین تماس', 'وضعیت' ), $rows );
	}

	/* ── ۴۴ ─ خطاهای API (از لاگر خود مرکز) */
	public static function render_m44() {
		$rows = array();
		foreach ( DA_Data::api_logs( 300 ) as $r ) {
			$r = (array) $r;
			$st = (int) ( $r['status'] ?? 0 );
			if ( $st < 400 ) { continue; }
			$rows[] = array(
				esc_html( (string) ( $r['created_at'] ?? '' ) ),
				esc_html( DA_Page_Dash::vendor_name( (int) ( $r['vendor_id'] ?? 0 ) ) ),
				'<code dir="ltr">' . esc_html( (string) ( $r['method'] ?? '' ) . ' ' . ( $r['route'] ?? '' ) ) . '</code>',
				esc_html( (string) $st ),
				esc_html( (string) ( $r['message'] ?? '' ) ),
			);
			if ( count( $rows ) >= 30 ) { break; }
		}
		echo '<p class="da-hint">پاسخ‌های ناموفق مرکز به فروشگاه‌ها (۴xx/۵xx) — منبع: جدول رسمی dastyar_api_logs مرکز.</p>';
		echo DA_Render::table( array( 'زمان', 'مشتری', 'مسیر', 'کد', 'پیام' ), $rows );
	}

	/* ── ۴۵ ─ رادار نسخه‌ها */
	public static function render_m45() {
		$latest = (string) DA_Page_Settings::option( 'latest_conn' );
		$rows   = array();
		foreach ( DA_Data::vendors() as $v ) {
			$ver = (string) get_user_meta( $v['id'], '_dastyar_conn_version', true );
			$rows[] = array(
				esc_html( $v['shop'] ?: $v['name'] ),
				$ver ? '<code dir="ltr">' . esc_html( $ver ) . '</code>' : '<span class="da-empty">ثبت نشده</span>',
				'' === $ver ? DA_Render::pill( 'نامشخص', 'gray' )
					: ( version_compare( $ver, $latest, '<' ) ? DA_Render::pill( 'قدیمی — آپدیت شود', 'red' ) : DA_Render::pill( 'به‌روز', 'green' ) ),
			);
		}
		echo '<p class="da-hint">آخرین نسخه رسمی برای مقایسه: <code dir="ltr">' . esc_html( $latest ) . '</code> — از تنظیمات کنسول. '
			. 'نسخه از ping کانکتورها ضبط می‌شود (کانکتور 1.7.11+ نسخه را ارسال می‌کند).</p>';
		echo DA_Render::table( array( 'مشتری', 'نسخه نصب‌شده', 'وضعیت' ), $rows );
	}
}
