<?php
/**
 * پیشنهاد محصول — فروشنده محصولی که در پلتفرم نیست را معرفی می‌کند تا توسط ما تأمین و اضافه شود.
 *
 * - ذخیره با CPT استاندارد وردپرس (dastyar_suggestion) — جدول جدید ساخته نشده
 * - فروشنده در پنل خود (My Account ← پیشنهاد محصول) ثبت می‌کند و وضعیت هر درخواست را می‌بیند
 * - مدیر در «دستیار شاپ ← پیشنهادهای محصول» وضعیت را مدیریت می‌کند:
 *   در انتظار بررسی / در حال اقدام / تأمین شد / رد شد (+ یادداشت مدیر)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Suggestions {

	const POST_TYPE = 'dastyar_suggestion';
	const STATUS    = '_dastyar_sugg_status';
	const NOTE      = '_dastyar_sugg_note';
	const IMAGE     = '_dastyar_sugg_image';
	const LINK      = '_dastyar_sugg_link';

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
	}

	public static function register_cpt() {
		register_post_type( self::POST_TYPE, array(
			'label'           => 'پیشنهادهای محصول',
			'public'          => false,
			'show_ui'         => false, // مدیریت از صفحه اختصاصی «دستیار شاپ ← پیشنهادهای محصول»
			'supports'        => array( 'title', 'editor', 'author' ),
			'capability_type' => 'post',
		) );
	}

	public static function statuses() {
		return array(
			'pending'     => 'در انتظار بررسی',
			'in_progress' => 'در حال اقدام',
			'supplied'    => 'تأمین شد',
			'rejected'    => 'رد شد',
		);
	}

	public static function status_label( $slug ) {
		$all = self::statuses();
		return $all[ $slug ] ?? $all['pending'];
	}

	/** رنگ نشان وضعیت برای نمایش در پنل فروشنده و پیشخوان */
	public static function status_color( $slug ) {
		$map = array(
			'pending'     => '#b78a00',
			'in_progress' => '#2271b1',
			'supplied'    => '#17a16d',
			'rejected'    => '#c0392b',
		);
		return $map[ $slug ] ?? '#666';
	}

	/* ------------------------------------------------------------------
	 * ثبت درخواست (سمت فروشنده)
	 * ---------------------------------------------------------------- */

	/**
	 * @return int|WP_Error
	 */
	public static function submit( $uid, $name, $desc = '', $link = '' ) {
		$name = sanitize_text_field( (string) $name );
		if ( '' === $name ) {
			return new WP_Error( 'dastyar_no_name', 'نام محصول الزامی است.' );
		}

		$post_id = wp_insert_post( array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => $name,
			'post_content' => sanitize_textarea_field( (string) $desc ),
			'post_author'  => (int) $uid,
			'post_status'  => 'publish',
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::STATUS, 'pending' );
		$link = esc_url_raw( (string) $link );
		if ( $link ) {
			update_post_meta( $post_id, self::LINK, $link );
		}

		// تصویر (اختیاری)
		if ( ! empty( $_FILES['dastyar_sugg_image']['name'] ) ) {
			$image_id = self::upload_image( $post_id );
			if ( is_wp_error( $image_id ) ) {
				// خطای تصویر نباید مانع ثبت درخواست شود؛ فقط اطلاع داده می‌شود
				wc_add_notice( 'درخواست ثبت شد اما تصویر بارگذاری نشد: ' . $image_id->get_error_message(), 'notice' );
			} elseif ( $image_id ) {
				update_post_meta( $post_id, self::IMAGE, (int) $image_id );
			}
		}

		$vendor = get_userdata( $uid );
		wp_mail(
			get_option( 'admin_email' ),
			'پیشنهاد محصول جدید: ' . $name,
			sprintf(
				"فروشنده %s پیشنهاد محصول ثبت کرد:\n\nمحصول: %s\nتوضیح: %s\nلینک نمونه: %s\n\nمدیریت: %s",
				$vendor ? $vendor->display_name : $uid,
				$name,
				$desc,
				$link ?: '—',
				admin_url( 'admin.php?page=dastyar-suggestions' )
			)
		);

		return $post_id;
	}

	protected static function upload_image( $post_id ) {
		$allowed = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$check   = wp_check_filetype( sanitize_file_name( $_FILES['dastyar_sugg_image']['name'] ), $allowed );
		if ( ! $check['type'] ) {
			return new WP_Error( 'dastyar_bad_image', 'فقط فایل تصویری (JPG/PNG/WebP) مجاز است.' );
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		return media_handle_upload( 'dastyar_sugg_image', $post_id );
	}

	/* ------------------------------------------------------------------
	 * نمایش در پنل فروشنده (My Account)
	 * ---------------------------------------------------------------- */

	public function render_account( $uid ) {
		echo '<div class="dvp-card"><h3>پیشنهاد محصول</h3>';
		echo '<p class="dvp-hint" style="margin-top:-4px">محصولی که به آن نیاز دارید اما در دستیار شاپ موجود نیست را معرفی کنید؛ ما آن را بررسی و در صورت امکان تأمین و به پلتفرم اضافه می‌کنیم.</p>';

		// فرم ثبت
		echo '<form method="post" enctype="multipart/form-data" class="dvp-suggest-form">';
		wp_nonce_field( 'dastyar_suggest' );
		echo '<input type="hidden" name="dastyar_action" value="suggest">';
		echo '<p><label>نام محصول <b style="color:#d63638">*</b></label>';
		echo '<input type="text" name="sugg_name" placeholder="مثلاً: ماشین اصلاح سر و صورت مدل X" required></p>';
		echo '<p><label>توضیحات / مشخصات موردنیاز (اختیاری)</label>';
		echo '<textarea name="sugg_desc" rows="3" placeholder="برند، مدل، رنج قیمت مورد انتظار، تعداد تقریبی ماهانه…"></textarea></p>';
		echo '<p><label>لینک نمونه (اختیاری)</label>';
		echo '<input type="url" name="sugg_link" dir="ltr" placeholder="https://"></p>';
		echo '<p><label>تصویر محصول (اختیاری — JPG/PNG/WebP)</label>';
		echo '<input type="file" name="dastyar_sugg_image" accept=".jpg,.jpeg,.png,.webp"></p>';
		echo '<button type="submit" class="dvp-btn">ثبت پیشنهاد محصول ←</button>';
		echo '</form></div>';

		// لیست درخواست‌های خود فروشنده
		$posts = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'author'         => (int) $uid,
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		echo '<div class="dvp-card"><h3>درخواست‌های شما</h3>';
		if ( ! $posts ) {
			echo '<p class="dvp-empty">هنوز پیشنهادی ثبت نکرده‌اید.</p></div>';
			return;
		}
		echo '<div class="dvp-table-wrap"><table class="dvp-table"><thead><tr>';
		echo '<th>تصویر</th><th>محصول</th><th>تاریخ</th><th>وضعیت</th><th>یادداشت مدیر</th>';
		echo '</tr></thead><tbody>';
		foreach ( $posts as $p ) {
			$status   = (string) get_post_meta( $p->ID, self::STATUS, true ) ?: 'pending';
			$note     = (string) get_post_meta( $p->ID, self::NOTE, true );
			$image_id = (int) get_post_meta( $p->ID, self::IMAGE, true );
			$img      = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
			echo '<tr>';
			printf( '<td>%s</td>', $img ? '<span class="dvp-othumb"><img src="' . esc_url( $img ) . '" alt=""></span>' : '—' );
			printf( '<td><strong>%s</strong>%s</td>', esc_html( $p->post_title ), $p->post_content ? '<br><small class="dvp-hint">' . esc_html( wp_trim_words( $p->post_content, 18 ) ) . '</small>' : '' );
			printf( '<td>%s</td>', esc_html( Dastyar_Jalali::day( $p->post_date ) ) );
			printf(
				'<td><span class="dvp-badge" style="background:%s">%s</span></td>',
				esc_attr( self::status_color( $status ) ),
				esc_html( self::status_label( $status ) )
			);
			printf( '<td>%s</td>', $note ? esc_html( $note ) : '—' );
			echo '</tr>';
		}
		echo '</tbody></table></div></div>';
	}
}
