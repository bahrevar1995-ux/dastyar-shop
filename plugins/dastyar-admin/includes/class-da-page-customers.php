<?php
/**
 * صفحه «مشتری‌ها» — ماژول‌های ۱ تا ۸ (+۴۶ نرخ فعال‌ماندن) و اکشن‌های مربوطه.
 * داده‌ها: نقش dastyar_vendor + متاهای رجیستری مرکز (_dastyar_shop_name/_shop_type/…)
 * و متا اختصاصی کنسول: _da_tags _da_note _da_suspended.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Page_Customers {

	/** نقشه فروش کل هر مشتری (برای مرتب‌سازی/رتبه) — کش درون‌درخواست */
	protected static function lifetime_sales() {
		static $map = null;
		if ( null === $map ) {
			$map = DA_Data::sales_map( DA_Data::orders_range( 0, time(), 900 ) );
		}
		return $map;
	}

	/* ── ۱ ─ دفترچه مشتری‌ها (+ فیلتر جستجو/برچسب) */
	public static function render_m01() {
		$q    = sanitize_text_field( (string) ( $_GET['da_q'] ?? '' ) );
		$tag  = sanitize_text_field( (string) ( $_GET['da_tag'] ?? '' ) );
		$sales= self::lifetime_sales();
		$rows = array();
		$tags = array();
		foreach ( DA_Data::vendors() as $v ) {
			if ( $v['tags'] ) {
				foreach ( explode( '،', str_replace( ',', '،', $v['tags'] ) ) as $t ) {
					$t = trim( $t );
					if ( '' !== $t ) { $tags[ $t ] = true; }
				}
			}
			if ( '' !== $q && false === mb_stripos( $v['name'] . ' ' . $v['shop'] . ' ' . $v['email'] . ' ' . $v['site'], $q ) ) {
				continue;
			}
			if ( '' !== $tag && false === mb_strpos( $v['tags'], $tag ) ) {
				continue;
			}
			$s   = $sales[ $v['id'] ] ?? array( 'orders' => 0, 'sum' => 0.0, 'last' => 0 );
			$stat= self::status_pill( $v['id'] );
			$rows[] = array(
				'<strong>' . esc_html( $v['shop'] ?: $v['name'] ) . '</strong><br><small>' . esc_html( $v['name'] ) . ' — ' . esc_html( $v['email'] ) . '</small>',
				esc_html( $v['shop_type'] ?: '—' ),
				$v['tags'] ? esc_html( $v['tags'] ) : '<span class="da-empty">—</span>',
				DA_Data::money( $v['balance'] ),
				number_format( $s['orders'] ) . ' / ' . DA_Data::money( $s['sum'] ),
				$s['last'] ? esc_html( DA_Data::ago( $s['last'] ) ) : '<span class="da-empty">هرگز</span>',
				$stat,
				DA_Modules::enabled( 'm02' ) ? '<a class="da-btn gray" href="' . esc_url( admin_url( 'admin.php?page=da-customers&da_uid=' . $v['id'] ) ) . '">کارت ۳۶۰°</a>' : '',
			);
		}
		echo '<form method="get" class="da-form-row da- no-print"><input type="hidden" name="page" value="da-customers">'
			. '<input class="da-input" type="text" name="da_q" value="' . esc_attr( $q ) . '" placeholder="جستجو: نام، فروشگاه، ایمیل، سایت…" style="min-width:260px">';
		if ( $tags ) {
			echo '<select class="da-input" name="da_tag"><option value="">همه برچسب‌ها</option>';
			foreach ( array_keys( $tags ) as $t ) {
				echo '<option value="' . esc_attr( $t ) . '" ' . selected( $tag, $t, false ) . '>' . esc_html( $t ) . '</option>';
			}
			echo '</select>';
		}
		echo '<button class="da-btn">جستجو</button></form>';
		echo DA_Render::table( array( 'مشتری', 'نوع فروشگاه', 'برچسب', 'مانده کیف', 'سفارش/فروش کل', 'آخرین سفارش', 'وضعیت', '' ), $rows );
	}

	/* ── ۲ ─ کارت ۳۶۰ درجه مشتری */
	public static function render_m02() {
		$uid = (int) ( $_GET['da_uid'] ?? 0 );
		if ( ! $uid ) {
			echo '<p class="da-empty">از ستون آخر «دفترچه مشتری‌ها» روی «کارت ۳۶۰°» یک مشتری بزنید تا پرونده کاملش این‌جا باز شود.</p>';
			return;
		}
		$u = get_userdata( $uid );
		if ( ! $u ) {
			echo '<p class="da-empty">کاربر یافت نشد.</p>';
			return;
		}
		$sales  = self::lifetime_sales();
		$s      = $sales[ $uid ] ?? array( 'orders' => 0, 'sum' => 0.0, 'last' => 0 );
		$bal    = DA_Data::balance( $uid );
		$shop   = (string) get_user_meta( $uid, '_dastyar_shop_name', true );
		$type   = (string) get_user_meta( $uid, '_dastyar_shop_type', true );
		$site   = DA_Data::vendors_api() && method_exists( DA_Data::vendors_api(), 'site_url' ) ? (string) DA_Data::vendors_api()->site_url( $uid ) : '';
		$limit  = (float) get_user_meta( $uid, '_da_credit_limit', true );

		echo '<div class="da-grid">'
			. DA_Render::kpi( 'مانده کیف پول', DA_Data::money( $bal ), $limit ? 'سقف اعتبار: ' . DA_Data::money( $limit ) : '', $bal < 0 ? 'red' : '' )
			. DA_Render::kpi( 'سفارش‌های کل', number_format( $s['orders'] ) )
			. DA_Render::kpi( 'فروش کل', DA_Data::money( $s['sum'] ) )
			. DA_Render::kpi( 'آخرین سفارش', $s['last'] ? esc_html( DA_Data::ago( $s['last'] ) ) : '—' )
			. '</div>';

		echo '<p><strong>فروشگاه:</strong> ' . esc_html( $shop ?: '—' ) . ' — <strong>نوع:</strong> ' . esc_html( $type ?: '—' )
			. ' — <strong>سایت:</strong> ' . ( $site ? '<a href="' . esc_url( $site ) . '" target="_blank" dir="ltr">' . esc_html( $site ) . '</a>' : '—' )
			. ' — <strong>عضویت:</strong> ' . esc_html( (string) $u->user_registered ) . '</p>';

		// ۱۰ سفارش اخیر این مشتری
		$rows = array();
		$ords = wc_get_orders( array( 'limit' => 60, 'orderby' => 'date', 'order' => 'DESC' ) );
		foreach ( (array) $ords as $o ) {
			if ( (int) $o->get_meta( DA_Data::VENDOR_KEY ) !== $uid ) { continue; }
			$rows[] = array(
				'#' . esc_html( $o->get_order_number() ),
				esc_html( (string) $o->get_meta( '_dastyar_remote_order_number' ) ?: '—' ),
				esc_html( wc_get_order_status_name( $o->get_status() ) ),
				DA_Data::money( DA_Data::order_goods( $o ) ),
				esc_html( $o->get_date_created() ? gmdate( 'Y-m-d H:i', $o->get_date_created()->getTimestamp() ) : '' ),
			);
			if ( count( $rows ) >= 10 ) { break; }
		}
		echo '<h4 style="margin:14px 0 6px">۱۰ سفارش اخیر</h4>';
		echo DA_Render::table( array( 'سفارش مرکز', 'شماره در سایت فروشنده', 'وضعیت', 'مبلغ تامین', 'تاریخ' ), $rows );

		// آخرین تراکنش‌های کیف پول
		if ( DA_Modules::enabled( 'm13' ) ) {
			echo '<h4 style="margin:14px 0 6px">۵ تراکنش اخیر کیف پول</h4><ul style="margin:0">';
			foreach ( array_slice( DA_Data::wallet_txns( $uid, 5 ), 0, 5 ) as $t ) {
				$t = (array) $t;
				echo '<li><small>' . esc_html( (string) ( $t['created_at'] ?? '' ) ) . ' — '
					. esc_html( ( 'credit' === ( $t['type'] ?? '' ) ? '+' : '-' ) . DA_Data::money( (float) ( $t['amount'] ?? 0 ) ) )
					. ' <span class="da-empty">' . esc_html( (string) ( $t['description'] ?? ( $t['desc'] ?? '' ) ) ) . '</span></small></li>';
			}
			echo '</ul>';
		}

		// تیکت‌های مشتری
		$tickets = (array) get_posts( array( 'post_type' => 'dastyar_ticket', 'posts_per_page' => 100, 'fields' => 'ids', 'author' => $uid ) );
		echo '<p class="da-hint">تیکت‌های ثبت‌شده این مشتری: ' . count( $tickets ) . ' — مدیریت تیکت‌ها از منوی «دستیار شاپ ← پشتیبانی» انجام می‌شود.</p>';
	}

	/* ── ۳ ─ رتبه‌بندی برنزی/نقره‌ای/طلایی */
	public static function render_m03() {
		$sales = self::lifetime_sales();
		$rows  = array();
		foreach ( DA_Data::vendors() as $v ) {
			$s = $sales[ $v['id'] ]['sum'] ?? 0.0;
			$rank = self::rank_of( (float) $s );
			$tier = class_exists( 'Dastyar_Tiers' ) ? Dastyar_Tiers::tier_of( $v['id'] ) : null;
			$rows[] = array(
				esc_html( $v['shop'] ?: $v['name'] ),
				DA_Data::money( (float) $s ),
				$tier ? esc_html( (string) $tier['name'] ) : '<span class="da-empty">بدون رده مرکز</span>',
				$rank,
			);
		}
		echo '<p class="da-hint">آستانه‌ها (فروش کل): نقره‌ای ≥ ' . esc_html( DA_Data::money( (float) DA_Page_Settings::option( 'rank_silver' ) ) )
			. ' — طلایی ≥ ' . esc_html( DA_Data::money( (float) DA_Page_Settings::option( 'rank_gold' ) ) ) . ' — از تنظیمات کنسول قابل تغییر است. ستون «رده مرکز» همان سیستم Tier خود افزونه مرکز است.</p>';
		echo DA_Render::table( array( 'مشتری', 'فروش کل', 'رده مرکز (Tier دستیار)', 'نشان کنسول' ), $rows );
	}

	/** نشان فروش بر اساس آستانه‌های تنظیمات */
	public static function rank_of( $sum ) {
		if ( $sum >= (float) DA_Page_Settings::option( 'rank_gold' ) ) {
			return DA_Render::pill( 'طلایی', 'green' );
		}
		if ( $sum >= (float) DA_Page_Settings::option( 'rank_silver' ) ) {
			return DA_Render::pill( 'نقره‌ای', 'gray' );
		}
		return DA_Render::pill( 'برنزی', 'amber' );
	}

	/* ── ۴ ─ مشتری‌های کم‌تحرک */
	public static function render_m04() {
		$days  = (int) DA_Page_Settings::option( 'inactive_days' );
		$sales = self::lifetime_sales();
		$rows  = array();
		foreach ( DA_Data::vendors() as $v ) {
			$last = (int) ( $sales[ $v['id'] ]['last'] ?? 0 );
			$gap  = $last ? ( time() - $last ) / 86400 : 9999;
			if ( $gap <= $days ) { continue; }
			$rows[] = array(
				esc_html( $v['shop'] ?: $v['name'] ),
				$last ? esc_html( DA_Data::ago( $last ) ) : 'هیچ سفارشی نداشته',
				number_format( (int) ( $sales[ $v['id'] ]['orders'] ?? 0 ) ),
				DA_Data::money( (float) ( $sales[ $v['id'] ]['sum'] ?? 0 ) ),
				DA_Render::pill( $gap >= 9999 ? 'هرگز فعال نشده' : 'خطر ریزش', 'red' ),
			);
		}
		echo '<p class="da-hint">مشتری‌هایی که بیش از ' . $days . ' روز سفارش نداده‌اند — پیشنهاد: تماس یا اطلاعیه هدفمند (ماژول ۸).</p>';
		echo DA_Render::table( array( 'مشتری', 'آخرین سفارش', 'تعداد کل', 'فروش کل', 'هشدار' ), $rows );
	}

	/* ── ۵ ─ یادداشت داخلی مشتری */
	public static function render_m05() {
		$uid = (int) ( $_GET['da_uid'] ?? 0 );
		if ( ! $uid ) {
			echo '<p class="da-empty">ابتدا «کارت ۳۶۰°» مشتری را باز کنید؛ یادداشت داخلی همان‌جا ثبت و نمایش داده می‌شود.</p>';
			return;
		}
		$note = (string) get_user_meta( $uid, '_da_note', true );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. wp_nonce_field( DA_Admin::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="da_customer_note"><input type="hidden" name="uid" value="' . $uid . '">'
			. '<textarea class="da-input" name="note" rows="3" style="width:100%" placeholder="یادداشت محرمانه مدیر درباره این مشتری…">' . esc_textarea( $note ) . '</textarea>'
			. '<p><button class="da-btn">ذخیره یادداشت</button> '
			. '<a class="da-btn gray" href="' . esc_url( admin_url( 'admin.php?page=da-customers&da_uid=' . $uid ) ) . '">برگشت به کارت ۳۶۰°</a></p></form>';
	}

	/* ── ۶ ─ تأیید / معلق / رد */
	public static function render_m06() {
		$uid = (int) ( $_GET['da_uid'] ?? 0 );
		if ( $uid ) {
			echo '<div class="da-form-row">';
			foreach ( array(
				'approve'    => array( '✅ تأیید و فعال‌سازی', 'da-btn' ),
				'suspend'    => array( '⏸ تعلیق حساب', 'da-btn gray' ),
				'reactivate' => array( '▶ لغو تعلیق', 'da-btn gray' ),
				'reject'     => array( '⛔ رد حساب', 'da-btn red' ),
			) as $do => $b ) {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=da_vendor_status&do=' . $do . '&uid=' . $uid ), DA_Admin::NONCE );
				echo '<a class="' . esc_attr( $b[1] ) . '" href="' . esc_url( $url ) . '" onclick="return confirm(\'مطمئنید؟\')">' . esc_html( $b[0] ) . '</a>';
			}
			echo '</div>';
		}
		$pending = DA_Data::pending_vendors();
		if ( $pending ) {
			echo '<h4 style="margin:10px 0 6px">در انتظار تأیید (' . count( $pending ) . ')</h4>';
			$rows = array();
			foreach ( $pending as $u ) {
				$puid = (int) $u->ID;
				$rows[] = array(
					esc_html( $u->display_name ) . '<br><small>' . esc_html( (string) get_user_meta( $puid, '_dastyar_shop_name', true ) ) . ' — ' . esc_html( $u->user_email ) . '</small>',
					esc_html( (string) get_user_meta( $puid, '_dastyar_shop_type', true ) ?: '—' ),
					'<a class="da-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=da_vendor_status&do=approve&uid=' . $puid ), DA_Admin::NONCE ) ) . '">تأیید</a> '
					. '<a class="da-btn red" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=da_vendor_status&do=reject&uid=' . $puid ), DA_Admin::NONCE ) ) . '">رد</a>',
				);
			}
			echo DA_Render::table( array( 'متقاضی', 'نوع فروشگاه', 'اقدام' ), $rows );
		} elseif ( ! $uid ) {
			echo '<p class="da-empty">درخواست در انتظار تأییدی نیست. برای اقدام روی حساب موجود، «کارت ۳۶۰°» مشتری را باز کنید.</p>';
		}
	}

	/* ── ۷ ─ برچسب‌گذاری */
	public static function render_m07() {
		$uid = (int) ( $_GET['da_uid'] ?? 0 );
		if ( ! $uid ) {
			echo '<p class="da-empty">برچسب‌ها را از «کارت ۳۶۰°» هر مشتری تنظیم کنید؛ بعد از بالای «دفترچه مشتری‌ها» روی همان برچسب فیلتر کنید.</p>';
			return;
		}
		$tags = (string) get_user_meta( $uid, '_da_tags', true );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="da-form-row">'
			. wp_nonce_field( DA_Admin::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="da_customer_tags"><input type="hidden" name="uid" value="' . $uid . '">'
			. '<input class="da-input" type="text" name="tags" value="' . esc_attr( $tags ) . '" placeholder="مثلاً: VIP، خوش‌حساب" style="min-width:280px">'
			. '<button class="da-btn">ذخیره برچسب‌ها</button></form>'
			. '<p class="da-hint">برچسب‌ها را با ویرگول جدا کنید؛ در ستون «برچسب» دفترچه مشتری‌ها دیده می‌شوند.</p>';
	}

	/* ── ۸ ─ اطلاعیه انبوه (همه یا گروه برچسبی) */
	public static function render_m08() {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. wp_nonce_field( DA_Admin::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="da_bcast_save">'
			. '<div class="da-form-row"><input class="da-input" name="title" placeholder="عنوان اعلان" required style="min-width:240px">'
			. '<select class="da-input" name="tag"><option value="">همه مشتری‌ها</option>';
		foreach ( self::all_tags() as $t ) {
			echo '<option value="' . esc_attr( $t ) . '">فقط برچسب «' . esc_html( $t ) . '»</option>';
		}
		echo '</select></div>'
			. '<textarea class="da-input" name="msg" rows="3" style="width:100%" placeholder="متن اعلان…" required></textarea>'
			. '<p><button class="da-btn">انتشار اعلان</button></p></form>';

		$rows = array();
		foreach ( array_reverse( DA_Admin::broadcasts() ) as $i => $b ) {
			$rows[] = array(
				esc_html( (string) ( $b['title'] ?? '' ) ),
				esc_html( (string) ( $b['tag'] ?? '' ) ?: 'همه' ),
				esc_html( (string) ( $b['time'] ?? '' ) ),
				! empty( $b['active'] ) ? DA_Render::pill( 'فعال', 'green' ) . ' <a class="da-btn gray" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=da_bcast_save&off=1' ), DA_Admin::NONCE ) ) . '">خاموش</a>' : DA_Render::pill( 'خاموش', 'gray' ),
			);
			if ( count( $rows ) >= 5 ) { break; }
		}
		echo '<h4 style="margin:12px 0 6px">۵ اعلان اخیر</h4>';
		echo DA_Render::table( array( 'عنوان', 'مخاطب', 'زمان', 'وضعیت' ), $rows );
		echo '<p class="da-hint">اعلان فعال از مسیر REST دستیار در پیشخوان سایت فروشنده نمایش داده می‌شود (کانکتور 1.7.11+).</p>';
	}

	/* ── ۴۶ ─ نرخ فعال‌ماندن مشتری‌ها */
	public static function render_m46_alt() {
		$months = 6;
		// ساخت نقشه ماه←مجموعه فروشندگان فعال
		$sets = array();
		for ( $i = $months; $i >= 0; $i-- ) {
			$start = strtotime( gmdate( 'Y-m-01 00:00:00', strtotime( '-' . $i . ' months' ) ) );
			$end   = strtotime( gmdate( 'Y-m-01 00:00:00', strtotime( '-' . ( $i - 1 ) . ' months' ) ) );
			if ( 0 === $i ) { $end = time(); }
			$map = DA_Data::sales_map( DA_Data::orders_range( $start, $end, 400 ) );
			$sets[ gmdate( 'Y-m', $start ) ] = array_keys( $map );
		}
		$rows = array();
		$keys = array_keys( $sets );
		for ( $i = 0; $i + 1 < count( $keys ); $i++ ) {
			$a = $sets[ $keys[ $i ] ];
			$b = $sets[ $keys[ $i + 1 ] ];
			if ( ! $a ) { continue; }
			$stay = count( array_intersect( $a, $b ) );
			$pct  = (int) round( $stay / count( $a ) * 100 );
			$rows[] = array(
				esc_html( $keys[ $i ] ) . ' ← ' . esc_html( $keys[ $i + 1 ] ),
				count( $a ),
				$stay,
				DA_Render::pill( $pct . '٪', $pct >= 60 ? 'green' : ( $pct >= 40 ? 'amber' : 'red' ) ),
			);
		}
		echo '<p class="da-hint">از مشتری‌هایی که در ماه اول فروش داشتند، چند نفر/چند درصد در ماه بعد هم سفارش داشتند؟ (۶ ماه اخیر)</p>';
		echo DA_Render::table( array( 'دوره', 'فعالان ماه اول', 'مانده در ماه بعد', 'نرخ ماندگاری' ), $rows );
	}

	/* ==================================================================
	 * اکشن‌ها
	 * ================================================================== */

	public static function save_status() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$uid = (int) ( $_GET['uid'] ?? 0 );
		$do  = sanitize_key( (string) ( $_GET['do'] ?? '' ) );
		if ( $uid && get_userdata( $uid ) ) {
			$u = new WP_User( $uid );
			switch ( $do ) {
				case 'approve':
					$u->set_role( 'dastyar_vendor' );
					$key = class_exists( 'Dastyar_Registration' ) ? Dastyar_Registration::PENDING : '_dastyar_pending_vendor';
					delete_user_meta( $uid, $key );
					delete_user_meta( $uid, '_dastyar_vendor_rejected' );
					delete_user_meta( $uid, '_da_suspended' );
					break;
				case 'suspend':
					$u->set_role( 'customer' );
					update_user_meta( $uid, '_da_suspended', 1 );
					break;
				case 'reactivate':
					$u->set_role( 'dastyar_vendor' );
					delete_user_meta( $uid, '_da_suspended' );
					break;
				case 'reject':
					$u->set_role( 'customer' );
					update_user_meta( $uid, '_dastyar_vendor_rejected', 1 );
					$key = class_exists( 'Dastyar_Registration' ) ? Dastyar_Registration::PENDING : '_dastyar_pending_vendor';
					delete_user_meta( $uid, $key );
					break;
				default:
					DA_Render::redirect_back( 'da-customers', 'error' );
			}
			update_user_meta( $uid, '_da_status_reason', sanitize_text_field( (string) ( $_GET['reason'] ?? '' ) ) );
			DA_Render::redirect_back( 'da-customers', 'done', array( 'da_uid' => $uid ) );
		}
		DA_Render::redirect_back( 'da-customers', 'error' );
	}

	public static function save_note() {
		self::guard();
		$uid = (int) ( $_POST['uid'] ?? 0 );
		if ( $uid ) {
			update_user_meta( $uid, '_da_note', sanitize_textarea_field( wp_unslash( (string) ( $_POST['note'] ?? '' ) ) ) );
		}
		DA_Render::redirect_back( 'da-customers', 'saved', array( 'da_uid' => $uid ) );
	}

	public static function save_tags() {
		self::guard();
		$uid = (int) ( $_POST['uid'] ?? 0 );
		if ( $uid ) {
			update_user_meta( $uid, '_da_tags', sanitize_text_field( wp_unslash( (string) ( $_POST['tags'] ?? '' ) ) ) );
		}
		DA_Render::redirect_back( 'da-customers', 'saved', array( 'da_uid' => $uid ) );
	}

	/** ذخیره/خاموش‌کردن اعلان */
	public static function save_broadcast() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( ( $_POST['_wpnonce'] ?? $_GET['_wpnonce'] ) ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
		$list = DA_Admin::broadcasts();
		if ( ! empty( $_GET['off'] ) ) {
			foreach ( $list as $i => $b ) { $list[ $i ]['active'] = 0; }
			update_option( DA_Admin::BROADCASTS, $list );
			DA_Render::redirect_back( 'da-customers', 'done' );
		}
		$title = sanitize_text_field( wp_unslash( (string) ( $_POST['title'] ?? '' ) ) );
		$msg   = sanitize_textarea_field( wp_unslash( (string) ( $_POST['msg'] ?? '' ) ) );
		$tag   = sanitize_text_field( wp_unslash( (string) ( $_POST['tag'] ?? '' ) ) );
		if ( '' === $title || '' === $msg ) {
			DA_Render::redirect_back( 'da-customers', 'error' );
		}
		foreach ( $list as $i => $b ) { $list[ $i ]['active'] = 0; } // فقط یک اعلان فعال
		$list[] = array( 'title' => $title, 'msg' => $msg, 'tag' => $tag, 'time' => current_time( 'mysql' ), 'active' => 1 );
		$list = array_slice( $list, -20 );
		update_option( DA_Admin::BROADCASTS, $list );
		DA_Render::redirect_back( 'da-customers', 'done' );
	}

	/* هلپرها */
	protected static function guard() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ?? '' ), DA_Admin::NONCE ) ) {
			wp_die( 'نشست نامعتبر' );
		}
	}

	protected static function status_pill( $uid ) {
		if ( class_exists( 'Dastyar_Registration' ) && get_user_meta( $uid, Dastyar_Registration::PENDING, true ) ) {
			return DA_Render::pill( 'در انتظار تأیید', 'amber' );
		}
		if ( get_user_meta( $uid, '_da_suspended', true ) ) {
			return DA_Render::pill( 'معلق', 'red' );
		}
		if ( get_user_meta( $uid, '_dastyar_vendor_rejected', true ) ) {
			return DA_Render::pill( 'رد شده', 'gray' );
		}
		return DA_Render::pill( 'فعال', 'green' );
	}

	protected static function all_tags() {
		$tags = array();
		foreach ( DA_Data::vendors() as $v ) {
			foreach ( explode( '،', str_replace( ',', '،', (string) $v['tags'] ) ) as $t ) {
				$t = trim( $t );
				if ( '' !== $t ) { $tags[ $t ] = true; }
			}
		}
		return array_keys( $tags );
	}
}
