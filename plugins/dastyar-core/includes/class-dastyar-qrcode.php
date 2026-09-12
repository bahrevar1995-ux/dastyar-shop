<?php
/**
 * تولید QR Code به‌صورت خالص PHP (بدون کتابخانه خارجی) — برای چاپ روی لیبل آدرس.
 *
 * پیاده‌سازی فشرده استاندارد ISO/IEC 18004:
 *  - حالت Byte (UTF-8)، سطح تصحیح خطا M
 *  - نسخه‌های ۱ تا ۴ (تا ۶۲ کاراکتر) — کافی برای لینک رهگیری مرسوله
 *  - انتخاب خودکار بهترین Mask بر اساس قوانین جریمه (Penalty N1..N4)
 *  - خروجی SVG (برداری — مناسب چاپ)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_QrCode {

	/** تعداد کدورد داده هر نسخه (ECL M) */
	private static $DATA_CW = array( 1 => 16, 2 => 28, 3 => 44, 4 => 64 );
	/** تعداد کدورد تصحیح خطا در هر بلوک (ECL M) */
	private static $ECC_LEN = array( 1 => 10, 2 => 16, 3 => 26, 4 => 18 );
	/** تعداد بلوک‌های هر نسخه (ECL M) */
	private static $BLOCKS  = array( 1 => 1, 2 => 1, 3 => 1, 4 => 2 );
	/** رشته اطلاعات فرمت ۱۵ بیتی برای ECL M و ماسک‌های ۰ تا ۷ */
	private static $FORMAT_M = array( 0x5412, 0x5125, 0x5E7C, 0x5B4B, 0x45F9, 0x40CE, 0x4F97, 0x4AA0 );
	/** جداول GF(256) */
	private static $gf_exp = array();
	private static $gf_log = array();

	/**
	 * ماتریس نهایی QR (آرایه [y][x] از ۰/۱) یا آرایه خالی اگر متن خیلی بلند باشد.
	 */
	public static function matrix( $text ) {
		$text  = (string) $text;
		$bytes = '' === $text ? array() : array_values( unpack( 'C*', $text ) );
		$len   = count( $bytes );
		$ver   = 0;
		foreach ( array( 1 => 14, 2 => 26, 3 => 42, 4 => 62 ) as $v => $cap ) {
			if ( $len <= $cap ) {
				$ver = $v;
				break;
			}
		}
		if ( ! $ver ) {
			return array();
		}
		return self::build( $bytes, $ver );
	}

	/**
	 * خروجی SVG آماده چاپ.
	 *
	 * @param string $text متن داخل کد
	 * @param int    $px   ابعاد بیرونی (پیکسل)
	 * @param string $dark رنگ ماژول‌های تیره
	 */
	public static function svg( $text, $px = 90, $dark = '#111111' ) {
		$m = self::matrix( $text );
		if ( ! $m ) {
			return '';
		}
		$size   = count( $m );
		$border = 2; // حاشیه خاموش (Quiet Zone)
		$dim    = $size + 2 * $border;
		$path   = '';
		for ( $y = 0; $y < $size; $y++ ) {
			for ( $x = 0; $x < $size; $x++ ) {
				if ( $m[ $y ][ $x ] ) {
					$path .= 'M' . ( $x + $border ) . ' ' . ( $y + $border ) . 'h1v1h-1z';
				}
			}
		}
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" width="' . (int) $px . '" height="' . (int) $px . '" shape-rendering="crispEdges">'
			. '<rect width="' . $dim . '" height="' . $dim . '" fill="#ffffff"/>'
			. '<path d="' . $path . '" fill="' . esc_attr( $dark ) . '"/></svg>';
	}

	/* ------------------------------------------------------------------
	 * ساخت ماتریس
	 * ---------------------------------------------------------------- */

	private static function build( array $bytes, $ver ) {
		$size    = 17 + 4 * $ver;
		$data_cw = self::$DATA_CW[ $ver ];

		// ── رمزگذاری داده (Byte Mode) ──
		$bits = array();
		$push = function ( $val, $n ) use ( &$bits ) {
			for ( $i = $n - 1; $i >= 0; $i-- ) {
				$bits[] = ( $val >> $i ) & 1;
			}
		};
		$push( 0x4, 4 );             // مُد Byte
		$push( count( $bytes ), 8 ); // طول (نسخه‌های ۱ تا ۹ → ۸ بیت)
		foreach ( $bytes as $b ) {
			$push( $b, 8 );
		}
		$cap_bits = $data_cw * 8;
		for ( $i = 0; $i < 4 && count( $bits ) < $cap_bits; $i++ ) { // Terminator
			$bits[] = 0;
		}
		while ( count( $bits ) % 8 ) {
			$bits[] = 0;
		}
		$data = array();
		for ( $i = 0, $n = count( $bits ); $i < $n; $i += 8 ) {
			$b = 0;
			for ( $j = 0; $j < 8; $j++ ) {
				$b = ( $b << 1 ) | $bits[ $i + $j ];
			}
			$data[] = $b;
		}
		$pad = array( 0xEC, 0x11 );
		$p   = 0;
		while ( count( $data ) < $data_cw ) {
			$data[] = $pad[ ( $p++ ) & 1 ];
		}

		// ── بلوک‌بندی + محاسبه ECC (Reed-Solomon) ──
		$nb        = self::$BLOCKS[ $ver ];
		$ecc_len   = self::$ECC_LEN[ $ver ];
		$per_block = (int) ( $data_cw / $nb );
		$blocks    = array();
		$eccs      = array();
		for ( $i = 0, $k = 0; $i < $nb; $i++ ) {
			$blk      = array_slice( $data, $k, $per_block );
			$k       += $per_block;
			$blocks[] = $blk;
			$eccs[]   = self::rs_remainder( $blk, $ecc_len );
		}

		// ── درهم‌تنیدگی (Interleave) ──
		$codewords = array();
		for ( $i = 0; $i < $per_block; $i++ ) {
			foreach ( $blocks as $b ) {
				$codewords[] = $b[ $i ];
			}
		}
		for ( $i = 0; $i < $ecc_len; $i++ ) {
			foreach ( $eccs as $e ) {
				$codewords[] = $e[ $i ];
			}
		}

		// ── بیت‌های داده + بیت‌های باقی‌مانده (نسخه‌های ۲ تا ۴ → ۷ بیت) ──
		$data_bits = array();
		foreach ( $codewords as $cw ) {
			for ( $i = 7; $i >= 0; $i-- ) {
				$data_bits[] = ( $cw >> $i ) & 1;
			}
		}
		$remainder = $ver >= 2 ? 7 : 0;
		for ( $i = 0; $i < $remainder; $i++ ) {
			$data_bits[] = 0;
		}

		// ── الگوهای ثابت ──
		$modules = array_fill( 0, $size, array_fill( 0, $size, 0 ) );
		$is_func = array_fill( 0, $size, array_fill( 0, $size, false ) );
		self::draw_finder( $modules, $is_func, 3, 3, $size );
		self::draw_finder( $modules, $is_func, $size - 4, 3, $size );
		self::draw_finder( $modules, $is_func, 3, $size - 4, $size );
		if ( $ver >= 2 ) {
			$ac = 4 * $ver + 10; // مرکز الگوی Alignment نسخه‌های ۲ تا ۴
			self::draw_align( $modules, $is_func, $ac, $ac );
		}
		for ( $i = 8; $i < $size - 8; $i++ ) { // Timing
			$v = ( 0 === $i % 2 ) ? 1 : 0;
			$modules[6][ $i ] = $v;
			$is_func[6][ $i ] = true;
			$modules[ $i ][6] = $v;
			$is_func[ $i ][6] = true;
		}
		self::draw_format( $modules, $is_func, $size, 0 ); // رزرو ناحیه فرمت

		// ── قرار دادن داده + انتخاب بهترین ماسک ──
		$best     = null;
		$best_pen = PHP_INT_MAX;
		for ( $mask = 0; $mask < 8; $mask++ ) {
			$trial = $modules;
			$i     = 0;
			for ( $right = $size - 1; $right >= 1; $right -= 2 ) {
				if ( 6 === $right ) {
					$right = 5;
				}
				for ( $vert = 0; $vert < $size; $vert++ ) {
					for ( $j = 0; $j < 2; $j++ ) {
						$x = $right - $j;
						$y = ( 0 === ( ( $right + 1 ) & 2 ) ) ? $size - 1 - $vert : $vert;
						if ( ! $is_func[ $y ][ $x ] && $i < count( $data_bits ) ) {
							$bit = $data_bits[ $i++ ];
							if ( self::mask_bit( $mask, $y, $x ) ) {
								$bit ^= 1;
							}
							$trial[ $y ][ $x ] = $bit;
						}
					}
				}
			}
			$dummy = array_fill( 0, $size, array_fill( 0, $size, false ) );
			self::draw_format( $trial, $dummy, $size, self::$FORMAT_M[ $mask ] );
			$pen = self::penalty( $trial, $size );
			if ( $pen < $best_pen ) {
				$best_pen = $pen;
				$best     = $trial;
			}
		}
		return $best;
	}

	private static function draw_finder( &$m, &$f, $cx, $cy, $size ) {
		for ( $dy = -4; $dy <= 4; $dy++ ) {
			for ( $dx = -4; $dx <= 4; $dx++ ) {
				$x = $cx + $dx;
				$y = $cy + $dy;
				if ( $x < 0 || $y < 0 || $x >= $size || $y >= $size ) {
					continue;
				}
				$dist        = max( abs( $dx ), abs( $dy ) );
				$m[ $y ][ $x ] = ( 2 !== $dist && 4 !== $dist ) ? 1 : 0;
				$f[ $y ][ $x ] = true;
			}
		}
	}

	private static function draw_align( &$m, &$f, $cx, $cy ) {
		for ( $dy = -2; $dy <= 2; $dy++ ) {
			for ( $dx = -2; $dx <= 2; $dx++ ) {
				$dist            = max( abs( $dx ), abs( $dy ) );
				$m[ $cy + $dy ][ $cx + $dx ] = ( 1 !== $dist ) ? 1 : 0;
				$f[ $cy + $dy ][ $cx + $dx ] = true;
			}
		}
	}

	/** نوشتن ۱۵ بیت اطلاعات فرمت در دو کپی استاندارد */
	private static function draw_format( &$m, &$f, $size, $bits15 ) {
		$set = function ( $x, $y, $v ) use ( &$m, &$f ) {
			$m[ $y ][ $x ] = $v;
			$f[ $y ][ $x ] = true;
		};
		for ( $i = 0; $i <= 5; $i++ ) {
			$set( 8, $i, ( $bits15 >> $i ) & 1 );
		}
		$set( 8, 7, ( $bits15 >> 6 ) & 1 );
		$set( 8, 8, ( $bits15 >> 7 ) & 1 );
		$set( 7, 8, ( $bits15 >> 8 ) & 1 );
		for ( $i = 9; $i < 15; $i++ ) {
			$set( 14 - $i, 8, ( $bits15 >> $i ) & 1 );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$set( $size - 1 - $i, 8, ( $bits15 >> $i ) & 1 );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$set( 8, $size - 15 + $i, ( $bits15 >> $i ) & 1 );
		}
		$set( 8, $size - 8, 1 ); // ماژول همیشه تیره
	}

	private static function mask_bit( $mask, $y, $x ) {
		switch ( $mask ) {
			case 0: return 0 === ( $x + $y ) % 2;
			case 1: return 0 === $y % 2;
			case 2: return 0 === $x % 3;
			case 3: return 0 === ( $x + $y ) % 3;
			case 4: return 0 === ( intdiv( $y, 2 ) + intdiv( $x, 3 ) ) % 2;
			case 5: return 0 === ( $x * $y ) % 2 + ( $x * $y ) % 3;
			case 6: return 0 === ( ( $x * $y ) % 2 + ( $x * $y ) % 3 ) % 2;
			case 7: return 0 === ( ( $x + $y ) % 2 + ( $x * $y ) % 3 ) % 2;
		}
		return false;
	}

	/* ------------------------------------------------------------------
	 * جریمه (انتخاب بهترین ماسک) — قوانین N1 تا N4 استاندارد
	 * ---------------------------------------------------------------- */

	private static function penalty( $m, $size ) {
		$pen = 0;

		// N1: رشته‌های هم‌رنگ ۵تایی یا بیشتر در سطر/ستون
		foreach ( array( true, false ) as $rowwise ) {
			for ( $i = 0; $i < $size; $i++ ) {
				$run_color = false;
				$run_len   = 0;
				for ( $j = 0; $j < $size; $j++ ) {
					$bit = $rowwise ? $m[ $i ][ $j ] : $m[ $j ][ $i ];
					if ( 0 === $j || $bit !== $run_color ) {
						if ( $run_len >= 5 ) {
							$pen += 3 + ( $run_len - 5 );
						}
						$run_color = $bit;
						$run_len   = 1;
					} else {
						$run_len++;
					}
				}
				if ( $run_len >= 5 ) {
					$pen += 3 + ( $run_len - 5 );
				}
			}
		}

		// N2: بلوک‌های ۲×۲ هم‌رنگ
		for ( $y = 0; $y < $size - 1; $y++ ) {
			for ( $x = 0; $x < $size - 1; $x++ ) {
				$c = $m[ $y ][ $x ];
				if ( $c === $m[ $y ][ $x + 1 ] && $c === $m[ $y + 1 ][ $x ] && $c === $m[ $y + 1 ][ $x + 1 ] ) {
					$pen += 3;
				}
			}
		}

		// N3: الگوی Finder‌مانند 10111010000 / 00001011101
		$pat1 = array( 1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0 );
		$pat2 = array_reverse( $pat1 );
		foreach ( array( true, false ) as $rowwise ) {
			for ( $i = 0; $i < $size; $i++ ) {
				$line = array();
				for ( $j = 0; $j < $size; $j++ ) {
					$line[] = $rowwise ? $m[ $i ][ $j ] : $m[ $j ][ $i ];
				}
				for ( $j = 0; $j + 11 <= $size; $j++ ) {
					$slice = array_slice( $line, $j, 11 );
					if ( $slice === $pat1 || $slice === $pat2 ) {
						$pen += 40;
					}
					// هم‌ارز با ۴ ماژول روشن قبل/بعد از الگوی اصلی ۱۰۱۱۱۰۱
					$center = array_slice( $line, $j, 7 );
					if ( array( 1, 0, 1, 1, 1, 0, 1 ) === $center ) {
						$before = $j >= 4 ? array_slice( $line, $j - 4, 4 ) : array();
						$after  = $j + 11 <= $size ? array_slice( $line, $j + 7, 4 ) : array();
						if ( ( 4 === count( $before ) && array( 0, 0, 0, 0 ) === $before )
							|| ( 4 === count( $after ) && array( 0, 0, 0, 0 ) === $after ) ) {
							$pen += 40;
						}
					}
				}
			}
		}

		// N4: توازن تیره/روشن
		$dark  = 0;
		foreach ( $m as $row ) {
			$dark += array_sum( $row );
		}
		$total = $size * $size;
		$k     = (int) floor( abs( $dark * 100 / $total - 50 ) / 5 );
		$pen  += $k * 10;

		return $pen;
	}

	/* ------------------------------------------------------------------
	 * Reed-Solomon روی GF(256) با چندجمله‌ای اولیه 0x11D
	 * ---------------------------------------------------------------- */

	private static function gf_init() {
		if ( self::$gf_exp ) {
			return;
		}
		$x = 1;
		for ( $i = 0; $i < 255; $i++ ) {
			self::$gf_exp[ $i ] = $x;
			self::$gf_log[ $x ] = $i;
			$x                <<= 1;
			if ( $x & 0x100 ) {
				$x ^= 0x11D;
			}
		}
		for ( $i = 255; $i < 512; $i++ ) {
			self::$gf_exp[ $i ] = self::$gf_exp[ $i - 255 ];
		}
	}

	private static function gf_mul( $x, $y ) {
		if ( 0 === $x || 0 === $y ) {
			return 0;
		}
		self::gf_init();
		return self::$gf_exp[ ( self::$gf_log[ $x ] + self::$gf_log[ $y ] ) % 255 ];
	}

	/** چندجمله‌ای مولد به درجه $degree */
	private static function rs_divisor( $degree ) {
		self::gf_init();
		$result              = array_fill( 0, $degree, 0 );
		$result[ $degree - 1 ] = 1;
		$root = 1;
		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$result[ $j ] = self::gf_mul( $result[ $j ], $root );
				if ( $j + 1 < $degree ) {
					$result[ $j ] ^= $result[ $j + 1 ];
				}
			}
			$root = self::gf_mul( $root, 0x02 );
		}
		return $result;
	}

	/** باقی‌مانده تقسیم داده بر چندجمله‌ای مولد = کدورد ECC */
	private static function rs_remainder( array $data, $degree ) {
		$divisor = self::rs_divisor( $degree );
		$result  = array_fill( 0, $degree, 0 );
		foreach ( $data as $b ) {
			$factor = $b ^ array_shift( $result );
			$result[] = 0;
			foreach ( $divisor as $i => $coef ) {
				$result[ $i ] ^= self::gf_mul( $coef, $factor );
			}
		}
		return $result;
	}
}
