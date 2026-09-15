<?php
/**
 * DastyarC_Admin_Health — trait تب «سلامت» پنل کانکتور (v1.12.0).
 * خودآزمایی محیط (cURL، حافظه، کرون، اتصال…) — فقط توسط DastyarC_Admin استفاده می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait DastyarC_Admin_Health {

	/**
	 * اجرای همه بررسی‌های سلامت
	 * @param bool $probe پروب HTTP مرکز (در داشبورد برای سرعت خاموش است)
	 * @return array [ [state, label, detail], ... ] — state: ok|warn|bad
	 */
	public static function health_checks( $probe = true ) {
		$out = array();

		// ۱) اتصال به مرکز
		if ( ! DastyarC_Client::configured() ) {
			$out[] = array( 'bad', 'اتصال به مرکز', 'پیکربندی نشده است.' );
		} elseif ( ! $probe ) {
			$out[] = array( 'ok', 'اتصال به مرکز', 'پیکربندی شده است (دسترسی زنده بررسی نشد).' );
		} else {
			$ping = DastyarC_Client::ping();
			if ( is_wp_error( $ping ) ) {
				$out[] = array( 'bad', 'اتصال به مرکز', 'مرکز در دسترس نیست.' );
			} else {
				$out[] = array( 'ok', 'اتصال به مرکز', 'برقرار است.' );
			}
		}

		// ۲) cURL — ناقص‌بودنش همان خطای معروف curl_multi_init و سوختن تصاویر است
		if ( ! extension_loaded( 'curl' ) ) {
			$out[] = array( 'bad', 'cURL', 'نصب نیست.' );
		} elseif ( ! function_exists( 'curl_multi_init' ) ) {
			$out[] = array( 'bad', 'cURL', 'ناقص است (curl_multi) — دانلود تصاویر ناموفق می‌شود؛ از هاست بخواهید کاملش کند.' );
		} else {
			$cv    = function_exists( 'curl_version' ) ? curl_version() : array();
			$ver   = is_array( $cv ) && isset( $cv['version'] ) ? ' (' . (string) $cv['version'] . ')' : '';
			$out[] = array( 'ok', 'cURL', 'فعال است' . $ver . '.' );
		}

		// ۳) نسخه‌ها
		$out[] = version_compare( PHP_VERSION, '8.0', '>=' )
			? array( 'ok', 'PHP', PHP_VERSION )
			: ( version_compare( PHP_VERSION, '7.4', '>=' )
				? array( 'warn', 'PHP', PHP_VERSION . ' — ۸٫۰ پیشنهاد می‌شود.' )
				: array( 'bad', 'PHP', PHP_VERSION . ' — قدیمی است.' ) );
		if ( defined( 'WC_VERSION' ) ) {
			$out[] = version_compare( WC_VERSION, '7.0', '>=' )
				? array( 'ok', 'ووکامرس', WC_VERSION )
				: array( 'warn', 'ووکامرس', WC_VERSION . ' — به‌روزرسانی شود.' );
		} else {
			$out[] = array( 'bad', 'ووکامرس', 'فعال نیست.' );
		}

		// ۴) حافظه و زمان اجرا
		$mem_raw = ini_get( 'memory_limit' );
		$mem     = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( (string) $mem_raw ) : 0;
		if ( $mem < 0 ) {
			$out[] = array( 'ok', 'حافظه PHP', 'نامحدود.' );
		} elseif ( $mem >= 512 * 1048576 ) {
			$out[] = array( 'ok', 'حافظه PHP', (string) $mem_raw . '.' );
		} elseif ( $mem >= 256 * 1048576 ) {
			$out[] = array( 'warn', 'حافظه PHP', (string) $mem_raw . ' — 512M پیشنهاد می‌شود.' );
		} else {
			$out[] = array( 'bad', 'حافظه PHP', (string) $mem_raw . ' — کم است.' );
		}
		$tmax = (int) ini_get( 'max_execution_time' );
		$out[] = ( 0 === $tmax || $tmax >= 120 )
			? array( 'ok', 'سقف اجرا', 0 === $tmax ? 'نامحدود.' : $tmax . ' ثانیه.' )
			: array( 'warn', 'سقف اجرا', $tmax . ' ثانیه — ۱۲۰ پیشنهاد می‌شود.' );

		// ۵) آپلود و دیسک
		$up = wp_upload_dir();
		if ( ! empty( $up['error'] ) ) {
			$out[] = array( 'bad', 'پوشه آپلود', (string) $up['error'] );
		} elseif ( empty( $up['basedir'] ) || ! is_writable( (string) $up['basedir'] ) ) {
			$out[] = array( 'bad', 'پوشه آپلود', 'قابل نوشتن نیست.' );
		} else {
			$out[] = array( 'ok', 'پوشه آپلود', 'سالم است.' );
		}
		if ( function_exists( 'disk_free_space' ) ) {
			$free = @disk_free_space( ABSPATH );
			if ( false === $free ) {
				$out[] = array( 'warn', 'فضای دیسک', 'نامشخص.' );
			} elseif ( $free >= 500 * 1048576 ) {
				$out[] = array( 'ok', 'فضای دیسک', size_format( $free ) . ' آزاد.' );
			} elseif ( $free >= 100 * 1048576 ) {
				$out[] = array( 'warn', 'فضای دیسک', size_format( $free ) . ' آزاد — رو به اتمام.' );
			} else {
				$out[] = array( 'bad', 'فضای دیسک', size_format( $free ) . ' آزاد — بحرانی.' );
			}
		}

		// ۶) کرون‌ها + تازگی سینک
		$ticks = array(
			'dastyarc_sync_tick'      => 'سینک ساعتی',
			'dastyarc_reconcile_tick' => 'سینک سفارش‌ها',
			'dastyarc_stock_tick'     => 'سینک موجودی',
		);
		foreach ( $ticks as $hook => $label ) {
			$next = wp_next_scheduled( $hook );
			if ( ! $next ) {
				$out[] = array( 'bad', 'کرون: ' . $label, 'زمان‌بندی نشده — افزونه را یک‌بار غیرفعال/فعال کنید.' );
			} elseif ( $next < time() - 1800 ) {
				$out[] = array( 'warn', 'کرون: ' . $label, 'عقب‌افتاده — کرون سایت اجرا نمی‌شود.' );
			} else {
				$out[] = array( 'ok', 'کرون: ' . $label, 'فعال است.' );
			}
		}
		$last = (int) get_option( 'dastyarc_last_sync', 0 );
		if ( ! $last ) {
			$out[] = array( 'warn', 'آخرین سینک', 'هنوز انجام نشده است.' );
		} elseif ( $last < time() - 26 * HOUR_IN_SECONDS ) {
			$out[] = array( 'warn', 'آخرین سینک', human_time_diff( $last, time() ) . ' پیش — قدیمی است.' );
		} else {
			$out[] = array( 'ok', 'آخرین سینک', human_time_diff( $last, time() ) . ' پیش.' );
		}

		// ۷) افزونه‌های حساس به تداخل (فقط اطلاع‌رسانی)
		$known  = array(
			'ewww-image-optimizer' => 'EWWW', 'wp-smushit' => 'Smush', 'imagify' => 'Imagify',
			'shortpixel-image-optimiser' => 'ShortPixel', 'wordfence' => 'Wordfence', 'sucuri-scanner' => 'Sucuri',
		);
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$hits = array();
		foreach ( $active as $p ) {
			$parts = explode( '/', (string) $p );
			if ( isset( $known[ $parts[0] ] ) ) {
				$hits[] = $known[ $parts[0] ];
			}
		}
		$hits = array_unique( $hits );
		$out[] = $hits
			? array( 'warn', 'تداخل احتمالی', implode( '، ', $hits ) . ' — هنگام خطا موقتاً غیرفعال و تست کنید.' )
			: array( 'ok', 'تداخل احتمالی', 'مورد شناخته‌شده‌ای فعال نیست.' );

		// ۸) نسخه‌ها
		global $wp_version;
		$out[] = array( 'ok', 'نسخه‌ها', 'کانکتور ' . DASTYARC_VERSION . ' / وردپرس ' . (string) ( $wp_version ?? '' ) . '.' );

		return $out;
	}

	/** بدترین وضعیت سلامت: ok|warn|bad */
	public static function health_state( $probe = true ) {
		$worst = 'ok';
		foreach ( self::health_checks( $probe ) as $c ) {
			if ( 'bad' === $c[0] ) {
				return 'bad';
			}
			if ( 'warn' === $c[0] ) {
				$worst = 'warn';
			}
		}
		return $worst;
	}

	/** تب «سلامت» */
	protected function tab_health() {
		$checks = self::health_checks( true );
		$state  = 'ok';
		foreach ( $checks as $c ) {
			if ( 'bad' === $c[0] ) {
				$state = 'bad';
				break;
			}
			if ( 'warn' === $c[0] ) {
				$state = 'warn';
			}
		}
		$pill = array(
			'ok'   => array( '#17a16d', '✔ همه‌چیز سالم است' ),
			'warn' => array( '#d98a1f', '⚠ چند مورد نیازمند توجه' ),
			'bad'  => array( '#c81e38', '✖ مشکل جدی وجود دارد' ),
		);
		list( $color, $label ) = $pill[ $state ];
		?>
		<section class="dcx2-card">
			<div class="hd" style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
				<b><?php echo DastyarC_Ui::icon( 'mark', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> سلامت فروشگاه</b>
				<span style="margin-right:auto;background:<?php echo esc_attr( $color ); ?>;color:#fff;border-radius:99px;padding:3px 14px;font-size:12px;font-weight:800"><?php echo esc_html( $label ); ?></span>
			</div>
			<table class="widefat striped">
				<thead><tr><th style="width:70px">وضعیت</th><th style="width:170px">مورد</th><th>جزئیات</th></tr></thead>
				<tbody>
				<?php
				$dot = array( 'ok' => '#17a16d', 'warn' => '#d98a1f', 'bad' => '#c81e38' );
				$txt = array( 'ok' => 'سالم', 'warn' => 'هشدار', 'bad' => 'خطا' );
				foreach ( $checks as $c ) :
					?>
					<tr>
						<td><span style="display:inline-block;background:<?php echo esc_attr( $dot[ $c[0] ] ); ?>;color:#fff;border-radius:99px;padding:1px 12px;font-size:11px;font-weight:800"><?php echo esc_html( $txt[ $c[0] ] ); ?></span></td>
						<td><b><?php echo esc_html( $c[1] ); ?></b></td>
						<td><?php echo esc_html( $c[2] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php $g = self::guide_link( 'health' ); ?>
			<?php if ( '' !== $g ) : ?>
				<p style="margin:12px 0 0"><?php echo $g; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php endif; ?>
		</section>
		<?php
	}
}
