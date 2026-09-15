<?php
/**
 * تاریخ شمسی (جلالی) — v1.10.8
 *
 * درخواست کاربر: «تاریخ‌های کل پلتفرم شمسی بشه (اتفاقاً برای فروشنده)».
 * همه خروجی‌های تاریخی افزونه‌های دستیار از این کلاس عبور می‌کنند تا فارغ از
 * نصب‌بودن/نبودن افزونه‌های جانبی (پارسی‌دیت و …) همیشه تاریخ شمسی چاپ شود؛
 * روی سایت‌هایی که پارسی‌دیت دارند هم نتیجه یکسان است ← تداخلی پیش نمی‌آید.
 *
 * تبدیل: الگوریتم استاندارد jdf (مصحح ورناکولار/بیرجندی) — دقیق برای سال‌های متداول.
 * توجه: این کلاس فقط «نمایش» تاریخ را شمسی می‌کند؛ ذخیره‌سازی دیتابیس/سفارش‌ها دست‌نخورده می‌ماند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Jalali {

	/** نام ماه‌های شمسی */
	public static function months() {
		return array( 1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
	}

	/** نام روزهای هفته (۰=شنبه … ۶=جمعه) */
	public static function weekdays() {
		return array( 'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه' );
	}

	/** ارقام فارسی */
	public static function fa( $str ) {
		return strtr( (string) $str, array(
			'0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
			'5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
		) );
	}

	/** عدد با جداکننده هزار + ارقام فارسی */
	public static function num( $n, $decimals = 0 ) {
		return self::fa( number_format( (float) $n, (int) $decimals ) );
	}

	/**
	 * میلادی → جلالی (الگوریتم jdf)
	 * @return array{0:int,1:int,2:int} (jy, jm, jd)
	 */
	public static function g2j( $gy, $gm, $gd ) {
		$gy    = (int) $gy;
		$gm    = (int) $gm;
		$gd    = (int) $gd;
		$gdm   = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = $gm > 2 ? $gy + 1 : $gy;
		$days  = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 ) + (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $gdm[ $gm - 1 ];
		$jy    = -1595 + 33 * (int) ( $days / 12053 );
		$days %= 12053;
		$jy   += 4 * (int) ( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy  += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + (int) ( $days / 31 );
			$jd = 1 + $days % 31;
		} else {
			$jm = 7 + (int) ( ( $days - 186 ) / 30 );
			$jd = 1 + ( $days - 186 ) % 30;
		}
		return array( $jy, $jm, $jd );
	}

	/**
	 * قالب‌بندی تاریخ شمسی — امضای مشابه date_i18n اما همیشه شمسی/فارسی.
	 *
	 * @param string                $format قالب (Y m d j n F M l D H i s G a A و لذین «\» برای حرف تحت‌اللفظی)
	 * @param int|string|object|null $src    تایم‌استمپ عددی، رشته mysql («Y-m-d H:i:s»)، شی WC_DateTime/DateTime، یا null=الان
	 * @param bool                  $fa      ارقام فارسی؟ (پیش‌فرض بله)
	 */
	public static function format( $format, $src = null, $fa = true ) {
		// استخراج اجزای زمانی
		if ( is_object( $src ) && ( method_exists( $src, 'date' ) || method_exists( $src, 'format' ) ) ) {
			// WC_DateTime (متد date) / DateTime خام (متد format) ← خود شی اجزای زمان را می‌دهد
			$g = method_exists( $src, 'date' ) ? 'date' : 'format';
			$Y = (int) $src->$g( 'Y' );
			$n = (int) $src->$g( 'n' );
			$j = (int) $src->$g( 'j' );
			$H = (int) $src->$g( 'H' );
			$i = (int) $src->$g( 'i' );
			$s = (int) $src->$g( 's' );
			$w = (int) $src->$g( 'w' );
		} elseif ( is_object( $src ) ) {
			return ''; // شی ناشناخته — چیزی چاپ نکن
		} else {
			if ( null === $src || '' === $src ) {
				$ts = time();
			} elseif ( is_numeric( $src ) ) {
				$ts = (int) $src;
			} else {
				$ts = strtotime( (string) $src );
			}
			if ( ! $ts ) {
				return '';
			}
			// همیشه gmdate ← مهم: روی سایت‌هایی که پارسی‌دیت/افزونه تاریخ فارسی دارند، wp_date
			// خودش خروجی شمسی می‌دهد و استفاده از آن باعث «دوبار تبدیل» (سال ~0784) می‌شد (باگ گزارش‌شده v1.8.0)
			$parts = explode( ' ', gmdate( 'Y n j H i s w', $ts ) );
			list( $Y, $n, $j, $H, $i, $s, $w ) = array_map( 'intval', $parts );
		}

		list( $jy, $jm, $jd ) = self::g2j( $Y, $n, $j );
		$months  = self::months();
		$weeks   = self::weekdays();
		$jdow    = ( $w + 1 ) % 7; // gmdate: 0=یکشنبه ← جلالی: 0=شنبه

		$out = '';
		$len = strlen( (string) $format );
		for ( $k = 0; $k < $len; $k++ ) {
			$ch = $format[ $k ];
			if ( '\\' === $ch && $k + 1 < $len ) {
				$out .= $format[ $k + 1 ];
				$k++;
				continue;
			}
			switch ( $ch ) {
				case 'Y':
					$out .= str_pad( (string) $jy, 4, '0', STR_PAD_LEFT );
					break;
				case 'y':
					$out .= str_pad( (string) ( $jy % 100 ), 2, '0', STR_PAD_LEFT );
					break;
				case 'm':
					$out .= str_pad( (string) $jm, 2, '0', STR_PAD_LEFT );
					break;
				case 'n':
					$out .= (string) $jm;
					break;
				case 'd':
					$out .= str_pad( (string) $jd, 2, '0', STR_PAD_LEFT );
					break;
				case 'j':
					$out .= (string) $jd;
					break;
				case 'F':
				case 'M':
					$out .= $months[ $jm ];
					break;
				case 'l':
				case 'D':
					$out .= $weeks[ $jdow ];
					break;
				case 'w':
					$out .= (string) $jdow;
					break;
				case 'H':
					$out .= str_pad( (string) $H, 2, '0', STR_PAD_LEFT );
					break;
				case 'G':
					$out .= (string) $H;
					break;
				case 'h':
					$out .= str_pad( (string) ( ( $H % 12 ) ?: 12 ), 2, '0', STR_PAD_LEFT );
					break;
				case 'g':
					$out .= (string) ( ( $H % 12 ) ?: 12 );
					break;
				case 'i':
					$out .= str_pad( (string) $i, 2, '0', STR_PAD_LEFT );
					break;
				case 's':
					$out .= str_pad( (string) $s, 2, '0', STR_PAD_LEFT );
					break;
				case 'a':
					$out .= $H < 12 ? 'ق.ظ' : 'ب.ظ';
					break;
				case 'A':
					$out .= $H < 12 ? 'قبل از ظهر' : 'بعد از ظهر';
					break;
				default:
					$out .= $ch;
			}
		}
		return $fa ? self::fa( $out ) : $out;
	}

	/** میان‌بر: تاریخ+ساعت رایج جداول «۱۴۰۴/۰۵/۲۳ - ۱۴:۳۰» */
	public static function dt( $src, $format = 'Y/m/d - H:i' ) {
		return self::format( $format, $src );
	}

	/** فقط تاریخ روز «۱۴۰۴/۰۵/۲۳» */
	public static function day( $src ) {
		return self::format( 'Y/m/d', $src );
	}
}
