<?php
/**
 * نوار پیشرفت سفارش برای مشتری فروشگاه (v1.5.0).
 *
 * در صفحه «مشاهده سفارش» (My Account) و «تشکر از خرید» — فقط برای سفارش‌هایی که
 * اقلام دستیار دارند — مراحل سفارش به‌صورت بصری نمایش داده می‌شود:
 *   ثبت سفارش ← ارسال به مرکز ← خروج از انبار (کد رهگیری) ← تحویل به مشتری
 * با رنگ و طرح سازمانی (#17a16d).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DastyarC_Progress {

	public function __construct() {
		// صفحه مشاهده سفارش در My Account (جزئیات سفارش)
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render' ), 20 );
		// صفحه تشکر (بلافاصله پس از خرید)
		add_action( 'woocommerce_thankyou', array( $this, 'render_by_id' ), 20 );
	}

	public function render_by_id( $order_id ) {
		$order = wc_get_order( (int) $order_id );
		if ( $order ) {
			$this->render( $order );
		}
	}

	/**
	 * رندر نوار پیشرفت برای یک سفارش (فقط اگر قلم دستیار داشته باشد).
	 * @param WC_Order|int $order
	 */
	public function render( $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( (int) $order );
		}
		if ( ! $order || ! DastyarC_Orders::has_remote_items( $order ) ) {
			return;
		}

		$steps = self::steps( $order );
		if ( ! $steps ) {
			return;
		}

		$this->css();

		echo '<div class="dastyarc-progress" role="list" aria-label="مراحل پیشرفت سفارش از انبار دستیار">';
		foreach ( $steps as $step ) {
			printf(
				'<div class="dcp-step %s" role="listitem"><span class="dcp-dot">%s</span><span class="dcp-label">%s</span></div>',
				esc_attr( $step['state'] ), // done | active | todo
				$step['icon_done'],
				esc_html( $step['label'] )
			);
		}
		echo '</div>';
	}

	/**
	 * محاسبه وضعیت مراحل برای یک سفارش — قابل تست جداگانه.
	 * @return array<int,array>
	 */
	public static function steps( WC_Order $order ) {
		$sent  = (bool) $order->get_meta( '_dastyarc_sent' );
		$track = (string) $order->get_meta( '_dastyar_tracking_code' );
		$done  = in_array( $order->get_status(), array( 'completed' ), true );

		// وضعیت هر مرحله: done (سبز کامل) | active (در جریان) | todo
		$states = array(
			$done || $track || $sent ? 'done' : 'active', // ۱) ثبت سفارش — همیشه انجام شده؛ active اگر هنوز هیچ اتفاقی نیفتاده
			$sent || $track || $done ? 'done' : ( (bool) $order->get_meta( '_dastyarc_has_items' ) ? 'active' : 'todo' ), // ۲) ارسال به مرکز
			$track || $done ? 'done' : ( $sent ? 'active' : 'todo' ), // ۳) خروج از انبار
			$done ? 'done' : ( $track ? 'active' : 'todo' ), // ۴) تحویل
		);

		$labels = array(
			'ثبت سفارش',
			'ارسال به مرکز',
			'خروج از انبار',
			'تحویل به مشتری',
		);

		$out = array();
		foreach ( $labels as $i => $label ) {
			$out[] = array(
				'label'     => $label,
				'state'     => $states[ $i ],
				'icon_done' => 'done' === $states[ $i ] ? '✓' : ( $i + 1 ),
			);
		}
		return $out;
	}

	/** استایل برند — فقط یک‌بار در صفحه */
	protected function css() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		echo '<style>' .
			'.dastyarc-progress{display:flex;justify-content:space-between;gap:6px;margin:22px auto;max-width:640px;padding:16px 20px;background:linear-gradient(180deg,#f6fcf9,#eef8f3);border:1.5px solid #cdeee0;border-radius:16px}' .
			'.dcp-step{flex:1;display:flex;flex-direction:column;align-items:center;gap:7px;position:relative;text-align:center}' .
			'.dcp-step:not(:last-child):before{content:"";position:absolute;top:15px;right:calc(-50% + 16px);left:calc(50% + 16px);height:3px;border-radius:2px;background:#d9ece2}' .
			'.dcp-step.done:not(:last-child):before{background:#17a16d}' .
			'.dcp-dot{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:#fff;border:2px solid #d9ece2;color:#8aa79a;font-weight:800;font-size:13px;position:relative;z-index:1}' .
			'.dcp-step.done .dcp-dot{background:#17a16d;border-color:#17a16d;color:#fff}' .
			'.dcp-step.active .dcp-dot{border-color:#17a16d;color:#12875c;box-shadow:0 0 0 4px rgba(23,161,109,.18);animation:dcp-pulse 1.6s infinite}' .
			'.dcp-label{font-size:11.5px;color:#5b7a6d;font-weight:600;line-height:1.6}' .
			'.dcp-step.done .dcp-label{color:#0f5132;font-weight:800}' .
			'.dcp-step.active .dcp-label{color:#12875c;font-weight:800}' .
			'@keyframes dcp-pulse{0%{box-shadow:0 0 0 3px rgba(23,161,109,.25)}70%{box-shadow:0 0 0 9px rgba(23,161,109,0)}100%{box-shadow:0 0 0 3px rgba(23,161,109,.25)}}' .
			'</style>';
	}
}
