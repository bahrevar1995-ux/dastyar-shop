<?php
/**
 * تیکت پشتیبانی — بر پایه CPT (بدون جدول جدید)؛ پاسخ‌ها = کامنت‌های استاندارد وردپرس.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dastyar_Tickets {

	const POST_TYPE = 'dastyar_ticket';
	const TAX       = 'dastyar_ticket_status';

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'comment_post', array( $this, 'on_reply' ), 10, 2 );
	}

	public function register() {
		register_post_type( self::POST_TYPE, array(
			'label'        => 'تیکت‌های دستیار',
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'dastyar',
			'supports'     => array( 'title', 'editor', 'author', 'comments' ),
			'capability_type' => 'post',
		) );
		register_taxonomy( self::TAX, self::POST_TYPE, array(
			'label'        => 'وضعیت تیکت',
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'dastyar',
		) );
		foreach ( array( 'open' => 'باز', 'answered' => 'پاسخ داده شده', 'closed' => 'بسته شده' ) as $slug => $name ) {
			if ( ! term_exists( $slug, self::TAX ) ) {
				wp_insert_term( $name, self::TAX, array( 'slug' => $slug ) );
			}
		}
	}

	public function create( $uid, $subject, $message ) {
		if ( '' === $subject || '' === $message ) {
			return 0;
		}
		$post_id = wp_insert_post( array(
			'post_type'      => self::POST_TYPE,
			'post_title'     => $subject,
			'post_content'   => $message,
			'post_author'    => $uid,
			'post_status'    => 'publish',
			'comment_status' => 'open',
		) );
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}
		wp_set_object_terms( $post_id, 'open', self::TAX );
		wp_mail( get_option( 'admin_email' ), 'تیکت جدید دستیار: ' . $subject, $message );
		return $post_id;
	}

	public function reply( $ticket_id, $uid, $message ) {
		if ( ! $ticket_id || '' === trim( $message ) ) {
			return;
		}
		$ticket = get_post( $ticket_id );
		if ( ! $ticket || self::POST_TYPE !== $ticket->post_type || (int) $ticket->post_author !== $uid ) {
			return;
		}
		wp_new_comment( array(
			'comment_post_ID' => $ticket_id,
			'comment_content' => $message,
			'user_id'         => $uid,
			'comment_approved'=> 1,
		) );
	}

	/** بعد از ثبت کامنت: وضعیت تیکت به‌روزرسانی و اطلاع‌رسانی انجام می‌شود */
	public function on_reply( $comment_id, $approved ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}
		$ticket = get_post( $comment->comment_post_ID );
		if ( ! $ticket || self::POST_TYPE !== $ticket->post_type ) {
			return;
		}
		$user = get_userdata( $comment->user_id );
		if ( $user && ( in_array( 'administrator', (array) $user->roles, true ) || user_can( $user, 'manage_woocommerce' ) ) ) {
			wp_set_object_terms( $ticket->ID, 'answered', self::TAX );
			$author = get_userdata( $ticket->post_author );
			if ( $author && $author->user_email ) {
				wp_mail( $author->user_email, 'پاسخ جدید به تیکت: ' . $ticket->post_title, $comment->comment_content );
			}
		} else {
			wp_set_object_terms( $ticket->ID, 'open', self::TAX );
			wp_mail( get_option( 'admin_email' ), 'پاسخ کاربر به تیکت: ' . $ticket->post_title, $comment->comment_content );
		}
	}

	/** رندر بخش تیکت‌ها در My Account فروشنده */
	public function render_account() {
		$uid = get_current_user_id();

		// نمایش یک تیکت
		if ( ! empty( $_GET['ticket'] ) ) {
			$ticket = get_post( (int) $_GET['ticket'] );
			if ( $ticket && self::POST_TYPE === $ticket->post_type && (int) $ticket->post_author === $uid ) {
				$status = wp_get_object_terms( $ticket->ID, self::TAX, array( 'fields' => 'names' ) );
				printf( '<h3>%s <small>(%s)</small></h3>', esc_html( $ticket->post_title ), esc_html( $status ? $status[0] : '' ) );
				echo '<div class="dastyar-ticket-body">' . wp_kses_post( wpautop( $ticket->post_content ) ) . '</div>';

				$comments = get_comments( array( 'post_id' => $ticket->ID, 'status' => 'approve', 'order' => 'ASC' ) );
				foreach ( $comments as $c ) {
					$is_admin = user_can( $c->user_id, 'manage_woocommerce' );
					printf(
						'<div class="dastyar-ticket-reply %s" style="border:1px solid #ddd;padding:10px;margin:8px 0;border-radius:6px">'
						. '<strong>%s</strong> <small>%s</small><br>%s</div>',
						$is_admin ? 'is-admin' : '',
						esc_html( $is_admin ? 'پشتیبانی دستیار' : 'شما' ),
						esc_html( Dastyar_Jalali::dt( (string) $c->comment_date ) ),
						wp_kses_post( wpautop( $c->comment_content ) )
					);
				}

				echo '<form method="post">';
				wp_nonce_field( 'dastyar_ticket_reply' );
				echo '<input type="hidden" name="dastyar_action" value="ticket_reply">';
				printf( '<input type="hidden" name="ticket_id" value="%d">', (int) $ticket->ID );
				echo '<p><textarea name="message" rows="4" style="width:100%" required placeholder="پاسخ شما…"></textarea></p>';
				echo '<button type="submit" class="button alt">ارسال پاسخ</button></form>';
				printf( '<p><a href="%s">← بازگشت به لیست تیکت‌ها</a></p>', esc_url( wc_get_account_endpoint_url( 'dastyar-tickets' ) ) );
				return;
			}
		}

		// لیست تیکت‌ها
		echo '<h3>تیکت‌ها</h3>';
		$tickets = get_posts( array(
			'post_type'   => self::POST_TYPE,
			'author'      => $uid,
			'numberposts' => 50,
		) );
		if ( $tickets ) {
			echo '<table class="shop_table"><thead><tr><th>عنوان</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead><tbody>';
			foreach ( $tickets as $t ) {
				$status = wp_get_object_terms( $t->ID, self::TAX, array( 'fields' => 'names' ) );
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td><a class="button" href="%s">مشاهده</a></td></tr>',
					esc_html( $t->post_title ),
					esc_html( $status ? $status[0] : '—' ),
					esc_html( Dastyar_Jalali::day( $t->post_date ) ),
					esc_url( add_query_arg( 'ticket', $t->ID, wc_get_account_endpoint_url( 'dastyar-tickets' ) ) )
				);
			}
			echo '</tbody></table>';
		} else {
			echo '<p>تیکتی ثبت نشده است.</p>';
		}

		echo '<h4>ثبت تیکت جدید</h4><form method="post">';
		wp_nonce_field( 'dastyar_ticket_new' );
		echo '<input type="hidden" name="dastyar_action" value="ticket_new">';
		echo '<p><input type="text" name="subject" placeholder="عنوان تیکت" style="width:100%" required></p>';
		echo '<p><textarea name="message" rows="5" placeholder="شرح مشکل یا سوال…" style="width:100%" required></textarea></p>';
		echo '<button type="submit" class="button alt">ارسال تیکت</button></form>';
	}
}
