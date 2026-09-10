<?php
/**
 * نمودارهای SVG درون‌خطی (بدون هیچ کتابخانه خارجی — همیشه و همه‌جا کار می‌کند).
 * سبز سازمانی #17a16d + سرمه‌ای #242536. فقط خروجی امن/escapeشده.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Chart {

	/** میله‌ای عمودی: $pairs = [label=>value] */
	public static function bars( array $pairs, $height = 150 ) {
		if ( ! $pairs ) {
			return '<p class="da-empty">داده‌ای برای نمایش نیست.</p>';
		}
		$max = max( array_map( 'floatval', $pairs ) );
		$max = $max > 0 ? $max : 1;
		$n   = count( $pairs );
		$bw  = max( 6, (int) floor( 560 / $n ) - 4 );
		$w   = $n * ( $bw + 4 ) + 10;
		$out = '<svg class="da-chart" width="100%" viewBox="0 0 ' . $w . ' ' . ( $height + 26 ) . '" role="img">';
		$out .= '<line x1="0" y1="' . $height . '" x2="' . $w . '" y2="' . $height . '" stroke="#dde7e1" stroke-width="1.5"/>';
		$i = 0;
		foreach ( $pairs as $label => $val ) {
			$v   = (float) $val;
			$bh  = max( 2, (int) round( $v / $max * ( $height - 18 ) ) );
			$x   = 5 + $i * ( $bw + 4 );
			$y   = $height - $bh;
			$out .= '<rect x="' . $x . '" y="' . $y . '" width="' . $bw . '" height="' . $bh . '" rx="4" fill="#17a16d"><title>'
				. esc_html( (string) $label . ': ' . $val ) . '</title></rect>';
			if ( $i === 0 || $i === $n - 1 || 0 === $i % max( 1, (int) floor( $n / 6 ) ) ) {
				$out .= '<text x="' . ( $x + $bw / 2 ) . '" y="' . ( $height + 18 ) . '" font-size="9" text-anchor="middle" fill="#8aa093">' . esc_html( (string) $label ) . '</text>';
			}
			$i++;
		}
		$out .= '</svg>';
		return $out;
	}

	/** نوارهای افقی رتبه‌بندی: $rows = [['label','value','hint']] */
	public static function hbars( array $rows, $max_hint = '' ) {
		if ( ! $rows ) {
			return '<p class="da-empty">داده‌ای برای نمایش نیست.</p>';
		}
		$max = 0;
		foreach ( $rows as $r ) {
			$max = max( $max, (float) $r[1] );
		}
		$max = $max > 0 ? $max : 1;
		$out = '<div class="da-hbars">';
		foreach ( $rows as $r ) {
			$pct = max( 2, (int) round( (float) $r[1] / $max * 100 ) );
			$out .= '<div class="da-hbar-row"><span class="da-hbar-label">' . esc_html( (string) $r[0] ) . '</span>'
				. '<span class="da-hbar-track"><span class="da-hbar-fill" style="width:' . $pct . '%"></span></span>'
				. '<span class="da-hbar-val">' . esc_html( (string) ( $r[2] ?? $r[1] ) ) . '</span></div>';
		}
		$out .= '</div>' . ( $max_hint ? '<p class="da-hint">' . esc_html( $max_hint ) . '</p>' : '' );
		return $out;
	}

	/** فلش روند سبز/قرمز */
	public static function trend( $now, $prev ) {
		$prev = (float) $prev;
		$now  = (float) $now;
		if ( $prev <= 0 ) {
			return $now > 0
				? '<span class="da-trend up">▲ جدید</span>'
				: '<span class="da-trend flat">—</span>';
		}
		$pct = (int) round( ( $now - $prev ) / $prev * 100 );
		if ( $pct >= 0 ) {
			return '<span class="da-trend up">▲ ' . $pct . '٪</span>';
		}
		return '<span class="da-trend down">▼ ' . abs( $pct ) . '٪</span>';
	}
}
