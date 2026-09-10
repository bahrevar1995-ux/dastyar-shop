<?php
/**
 * Template Name: دستیار — آموزش استفاده
 *
 * راهنمای قدم‌به‌قدم شروع فروش با دستیار شاپ برای سه نوع فروشنده:
 *  آ) فروشگاه وردپرسی (ووکامرس) ← مسیر خودکار (افزونه اتصال‌دهنده)
 *  ب) فروشگاه اینستاگرامی       ← مسیر دستی (ثبت سفارش دستی از پنل)
 *  ج) فروشگاه با سایت‌ساز آماده  ← مسیر دستی (تا عرضه اتصال خودکار)
 *
 * گرافیک: تایم‌لاین شماره‌دار + آیکن‌های SVG برند + تب‌های مسیر (بدون کتابخانه خارجی،
 * همراه با فالبک بدون جاوااسکریپت و بهینه برای چاپ).
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$dtt_s = Dastyar_Theme_Settings::all();

$dtt_auth     = Dastyar_Theme::page_url( 'auth_page', 'auth' );
$dtt_catalog  = Dastyar_Theme::page_url( 'catalog_page', 'catalog' );
$dtt_download = Dastyar_Theme::page_url( 'download_page', 'download' );
$dtt_panel    = Dastyar_Theme::page_url( 'panel_page', 'panel' );
$dtt_contact  = Dastyar_Theme::page_url( 'contact_page', 'contact' );

/** کارت مرحله تایم‌لاین */
$dtt_step = function ( $no, $icon, $title, $text, $chips = '' ) {
	echo '<li class="dtt-step">';
	printf( '<span class="dtt-step-no">%s</span>', esc_html( $no ) );
	echo '<div class="dtt-step-card">';
	printf( '<h4><span class="dtt-step-ic">%s</span>%s</h4>', Dastyar_Theme_Landing::icon( $icon ), esc_html( $title ) );
	printf( '<p>%s</p>', esc_html( $text ) );
	if ( $chips ) {
		// چیپ‌ها رشته‌های داخلی و معتمد قالب‌اند (حاوی آیکن SVG) — kses آن‌ها را می‌زداید
		echo '<div class="dtt-chips">' . $chips . '</div>';
	}
	echo '</div></li>';
};
/** چیپ راهنما */
$dtt_chip = function ( $text, $tone = 'tip' ) {
	return '<span class="dtt-chip dtt-chip-' . esc_attr( $tone ) . '">' . Dastyar_Theme_Landing::icon( 'suggest' ) . esc_html( $text ) . '</span>';
};
?>

<script>document.documentElement.className += ' dtt-js';</script>

<style>
/* ---------- صفحه آموزش استفاده (v1.0.5) ---------- */
.dtt-hero-mini{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:16px}
.dtt-mini{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:12px;padding:8px 14px;font-size:13px;font-weight:700;text-decoration:none}
.dtt-mini svg{width:15px;height:15px}
.dtt-mini:hover{background:rgba(255,255,255,.2)}
.dtt-yes-mini{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:800;border-radius:8px;padding:1px 8px;margin-right:2px}
.dtt-auto{background:#17a16d;color:#fff}
.dtt-manual{background:#e0a800;color:#fff}

/* نوار مسیرهای شروع (مشترک) */
.dtt-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin:26px 0 6px}
.dtt-cell{background:#fff;border:1px solid #e6ecea;border-radius:14px;padding:14px 12px;text-align:center;position:relative}
.dtt-cell b{display:block;font-size:13px;color:#242536;margin:8px 0 3px}
.dtt-cell span{font-size:11.5px;color:#687a72;line-height:1.9;display:block}
.dtt-cell .dtt-cell-ic{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:11px;background:#eaf6f0;color:#17a16d}
.dtt-cell .dtt-cell-ic svg{width:17px;height:17px}
.dtt-cell .dtt-cell-no{position:absolute;top:-10px;right:12px;background:#242536;color:#fff;font-size:11px;font-weight:800;border-radius:8px;padding:1px 8px}
.dtt-cell a{color:#17a16d;font-weight:800;text-decoration:none}

/* تب‌های مسیر — v2.1.1 (درخواست کاربر): دیگر به بالا نمی‌چسبد */
.dtt-tabs{display:flex;gap:8px;background:#fff;border:1px solid #e6ecea;border-radius:16px;padding:8px;margin:22px 0;box-shadow:0 6px 20px rgba(36,37,54,.06)}
.dtt-tab{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;padding:12px 8px;border:1.5px solid transparent;border-radius:12px;background:#f7faf9;cursor:pointer;font:inherit;font-size:13px;font-weight:800;color:#4d6b5f;transition:.2s}
.dtt-tab small{font-size:10.5px;font-weight:700;color:#8a978f}
.dtt-tab svg{width:20px;height:20px}
.dtt-tab:hover{border-color:#bfe6d2}
.dtt-tab.on{background:#242536;color:#fff}
.dtt-tab.on small{color:#a9e7cd}
.dtt-js .dtt-tp:not(.on){display:none}
.dtt-tp{border:1px solid #e6ecea;border-radius:16px;background:#fbfcfc;padding:22px 20px;margin-bottom:26px}
.dtt-tp-head{display:flex;align-items:center;gap:10px;margin-bottom:6px}
.dtt-tp-head h3{margin:0;font-size:16px;color:#242536}
.dtt-tp-head p{margin:0;font-size:12.5px;color:#687a72}

/* تایم‌لاین */
.dtt-time{list-style:none;margin:14px 0 0;padding:0;position:relative}
.dtt-time:before{content:'';position:absolute;right:19px;top:10px;bottom:10px;width:2px;background:linear-gradient(180deg,#cdeee0,#f0f4f2)}
.dtt-step{display:flex;gap:14px;align-items:flex-start;margin-bottom:14px;position:relative}
.dtt-step-no{flex:none;width:40px;height:40px;border-radius:50%;background:#17a16d;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;border:4px solid #fff;box-shadow:0 2px 8px rgba(23,161,109,.25);z-index:1}
.dtt-step-card{flex:1;background:#fff;border:1px solid #e8f0ec;border-radius:14px;padding:14px 16px}
.dtt-step-card h4{margin:0 0 6px;font-size:14px;color:#242536;display:flex;align-items:center;gap:8px}
.dtt-step-ic{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:9px;background:#eaf6f0;color:#17a16d;flex:none}
.dtt-step-ic svg{width:14px;height:14px}
.dtt-step-card p{margin:0;font-size:13.5px;color:#4d5d55;line-height:2}
.dtt-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.dtt-chip{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;border-radius:9px;padding:3px 10px}
.dtt-chip svg{width:12px;height:12px}
.dtt-chip-tip{background:#eaf6f0;color:#12875c}
.dtt-chip-warn{background:#fdf4d8;color:#8a6600}
.dtt-chip-ok{background:#e7f0fe;color:#1d4f9c}
.dtt-kbd{display:inline-block;background:#f0f4f2;border:1px solid #e0e7e4;border-bottom-width:2px;border-radius:6px;padding:0 7px;font-size:11.5px;color:#242536;font-weight:700}

/* جعبه‌های توضیح */
.dtt-note{display:flex;gap:10px;border:1px dashed #bfe6d2;background:#f4fbf8;border-radius:12px;padding:12px 14px;font-size:13px;color:#284436;line-height:1.9;margin:14px 0 0}
.dtt-note svg{width:18px;height:18px;color:#17a16d;flex:none;margin-top:2px}

/* واژه‌نامه + کد رهگیری */
.dtt-duo{display:grid;grid-template-columns:1.2fr 1fr;gap:14px;margin:26px 0}
.dtt-box{background:#fff;border:1px solid #e6ecea;border-radius:16px;padding:18px 20px}
.dtt-box h3{margin:0 0 12px;font-size:15px;color:#242536;display:flex;align-items:center;gap:8px}
.dtt-box h3 svg{width:17px;height:17px;color:#17a16d}
.dtt-gl{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.dtt-gi{background:#f7faf9;border:1px solid #eef3f0;border-radius:10px;padding:9px 12px}
.dtt-gi b{display:block;font-size:12.5px;color:#12875c;margin-bottom:3px}
.dtt-gi span{font-size:11.5px;color:#687a72;line-height:1.8}
.dtt-track-code{background:#242536;color:#fff;border-radius:12px;padding:14px 16px;text-align:center}
.dtt-track-code b{display:block;font-size:12px;color:#8fe3c2;margin-bottom:5px}
.dtt-track-code span{display:block;font-size:12px;color:#cfd3e4;line-height:2}
.dtt-track-code code{display:inline-block;background:rgba(255,255,255,.1);border:1px dashed rgba(255,255,255,.3);border-radius:8px;padding:4px 14px;font-size:15px;color:#fff;margin-top:8px;letter-spacing:2px}

/* FAQ آکاردئون بومی */
.dtt-faq details{background:#fff;border:1px solid #e6ecea;border-radius:12px;margin-bottom:8px;overflow:hidden}
.dtt-faq summary{cursor:pointer;padding:13px 16px;font-size:13.5px;font-weight:800;color:#242536;list-style:none;display:flex;align-items:center;gap:8px}
.dtt-faq summary svg{width:14px;height:14px;color:#17a16d;transition:transform .2s;flex:none}
.dtt-faq details[open] summary svg{transform:rotate(-90deg)}
.dtt-faq details p{margin:0;padding:0 16px 14px;font-size:13px;color:#4d5d55;line-height:2}

/* CTA پایانی */
.dtt-cta{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;background:linear-gradient(135deg,#242536,#17182a);border-radius:18px;padding:22px 24px;color:#fff;margin:10px 0 4px}
.dtt-cta h3{margin:0 0 4px;font-size:17px;color:#fff}
.dtt-cta p{margin:0;font-size:13px;color:#a9adc4}
.dtt-cta .dhm-btn{flex:none}

@media (max-width:860px){
	.dtt-strip{grid-template-columns:repeat(2,1fr)}
	.dtt-duo{grid-template-columns:1fr}
	.dtt-gl{grid-template-columns:1fr}
	.dtt-tab small{display:none}
}
@media print{
	.dtt-tabs,.dtt-cta,.dth-page-hero{display:none!important}
	.dtt-js .dtt-tp:not(.on){display:block}
	.dtt-time:before{display:none}
}
</style>

<!-- ================= هیرو (v2.1.0: متن‌ها از تنظیمات قالب مدیریت می‌شوند) ================= -->
<?php if ( Dastyar_Theme_Settings::page_hero_on( 'tutorial' ) ) : ?>
<section class="dth-page-hero" dir="rtl"><div class="dhm-in">
	<h1><?php echo esc_html( $dtt_s['tut_title'] ); ?></h1>
	<?php if ( '' !== trim( (string) $dtt_s['tut_sub'] ) ) : ?>
		<p><?php echo esc_html( $dtt_s['tut_sub'] ); ?></p>
	<?php endif; ?>
	<div class="dtt-hero-mini">
		<a class="dtt-mini" href="#dtt-twp"><?php echo Dastyar_Theme_Landing::icon( 'rocket' ); ?> <?php echo esc_html( $dtt_s['tut_m1'] ); ?> <span class="dtt-yes-mini dtt-auto">خودکار</span></a>
		<a class="dtt-mini" href="#dtt-tig"><?php echo Dastyar_Theme_Landing::icon( 'phone' ); ?> <?php echo esc_html( $dtt_s['tut_m2'] ); ?> <span class="dtt-yes-mini dtt-manual">دستی</span></a>
		<a class="dtt-mini" href="#dtt-tsb"><?php echo Dastyar_Theme_Landing::icon( 'home' ); ?> <?php echo esc_html( $dtt_s['tut_m3'] ); ?> <span class="dtt-yes-mini dtt-manual">دستی</span></a>
	</div>
</div></section>
<?php endif; ?>

<section class="dhm-sec" dir="rtl"><div class="dhm-in">

<!-- ================= شروع مشترک ================= -->
<h2 class="dhm-sec-t"><?php echo esc_html( $dtt_s['tut_start_t'] ); ?></h2>
<p class="dhm-sec-s"><?php echo esc_html( $dtt_s['tut_start_s'] ); ?></p>

<div class="dtt-strip">
	<div class="dtt-cell"><span class="dtt-cell-no">۱</span><span class="dtt-cell-ic"><?php echo Dastyar_Theme_Landing::icon( 'user' ); ?></span>
		<b>ثبت درخواست فروشندگی</b><span>از <a href="<?php echo esc_url( $dtt_auth ); ?>">فرم ورود/ثبت‌نام</a> نام فروشگاه و مشخصات‌ات را بنویس.</span></div>
	<div class="dtt-cell"><span class="dtt-cell-no">۲</span><span class="dtt-cell-ic"><?php echo Dastyar_Theme_Landing::icon( 'shield' ); ?></span>
		<b>تأیید حساب</b><span>تیم دستیار شاپ درخواست‌ات را بررسی و تأیید می‌کند.</span></div>
	<div class="dtt-cell"><span class="dtt-cell-no">۳</span><span class="dtt-cell-ic"><?php echo Dastyar_Theme_Landing::icon( 'chart' ); ?></span>
		<b>ورود به پنل فروشنده</b><span>از <a href="<?php echo esc_url( $dtt_panel ); ?>">پنل فروشنده</a> سفارش‌ها، کیف پول و اتصال را مدیریت می‌کنی.</span></div>
	<div class="dtt-cell"><span class="dtt-cell-no">۴</span><span class="dtt-cell-ic"><?php echo Dastyar_Theme_Landing::icon( 'card' ); ?></span>
		<b>شارژ کیف پول</b><span>از بخش کیف پول، حساب اعتباری‌ات را از درگاه امن شارژ کن.</span></div>
	<div class="dtt-cell"><span class="dtt-cell-no">۵</span><span class="dtt-cell-ic"><?php echo Dastyar_Theme_Landing::icon( 'package' ); ?></span>
		<b>انتخاب مسیر فروش</b><span>از تب‌های پایین، مسیری را انتخاب کن که شبیه فروشگاه توست.</span></div>
</div>

<!-- ================= تب‌های مسیر ================= -->
<div class="dtt-tabs" role="tablist" aria-label="انتخاب مسیر آموزش">
	<button type="button" class="dtt-tab on" data-dtt="twp" role="tab" aria-selected="true">
		<?php echo Dastyar_Theme_Landing::icon( 'rocket' ); ?> فروشگاه وردپرسی
		<small>سایت شخصی + ووکامرس — خودکار</small>
	</button>
	<button type="button" class="dtt-tab" data-dtt="tig" role="tab" aria-selected="false">
		<?php echo Dastyar_Theme_Landing::icon( 'phone' ); ?> فروشگاه اینستاگرامی
		<small>بدون سایت — ثبت سفارش دستی</small>
	</button>
	<button type="button" class="dtt-tab" data-dtt="tsb" role="tab" aria-selected="false">
		<?php echo Dastyar_Theme_Landing::icon( 'home' ); ?> سایت‌سازهای آماده
		<small>فروشگاه‌ساز — ثبت سفارش دستی</small>
	</button>
</div>

<!-- ================= مسیر آ: وردپرسی ================= -->
<section class="dtt-tp on" id="dtt-twp">
	<div class="dtt-tp-head">
		<h3>مسیر وردپرسی — همه‌چیز خودکار</h3>
		<span class="dtt-yes-mini dtt-auto">اتوماتیک</span>
	</div>
	<p class="dhm-sec-s">سایت وردپرسی با ووکامرس داری؟ یک‌بار افزونه اتصال‌دهنده را نصب می‌کنی؛ از آن به بعد محصول، موجودی، سفارش و حتی کد رهگیری — همه خودکار.</p>

	<ol class="dtt-time">
		<?php
		$dtt_step( '۱', 'download', 'دانلود افزونه اتصال‌دهنده',
			'از صفحه «دریافت پلاگین» فایل افزونه دستیار کانکتور را دانلود کن. این افزونه پل بین سایت تو و مرکز دستیار است.',
			'<a class="dtt-chip dtt-chip-ok" style="text-decoration:none" href="' . esc_url( $dtt_download ) . '">' . Dastyar_Theme_Landing::icon( 'arrow-l' ) . 'رفتن به صفحه دانلود</a>' );
		$dtt_step( '۲', 'package', 'نصب روی سایت خودت',
			'در پیشخوان وردپرس فروشگاه‌ت برو به افزونه‌ها ← افزودن ← بارگذاری افزونه، فایل را انتخاب و نصب کن و سپس «فعال‌سازی» را بزن.' );
		$dtt_step( '۳', 'api', 'ساخت کلید اتصال (API)',
			'در پنل فروشنده ← بخش «اتصال فروشگاه» روی «ساخت کلید جدید» بزن و کلید را همان لحظه کپی کن؛ کلید فقط یک‌بار نمایش داده می‌شود.',
			$dtt_chip( 'کلید را جای امن نگه دار؛ مثل رمز حساب است', 'warn' ) );
		$dtt_step( '۴', 'sync', 'وصل‌کردن سایت به مرکز',
			'در پیشخوان وردپرس سایت خودت ← منوی «اتصال دستیار» ← کلید را بچسبان و ذخیره کن. سبز شدن وضعیت اتصال یعنی سایت‌ات به مرکز وصل شده.' );
		$dtt_step( '۵', 'package', 'افزودن محصول به فروشگاهت',
			'از کاتالوگ دستیار روی «افزودن به فروشگاه من» بزن؛ محصول با عکس و توضیحات به سایت‌ات می‌آید و قیمت فروش، خودکار با فرمول سود خودت محاسبه می‌شود.',
			$dtt_chip( 'موجودی و قیمت تأمین به‌صورت خودکار سینک می‌شوند', 'tip' ) );
		$dtt_step( '۶', 'rocket', 'سفارش، خودکار به مرکز می‌رسد',
			'مشتری از سایت تو خرید می‌کند و سفارش بدون هیچ کاری از سوی تو، به‌صورت خودکار برای مرکز ثبت می‌شود. این همان «ثبت سفارش اتوماتیک» است.',
			$dtt_chip( 'برای مسیر خودکار، ثبت سفارش دستی اصلاً لازم نیست', 'ok' ) );
		$dtt_step( '۷', 'card', 'پرداخت از کیف پول',
			'مبلغ تأمین کالا از کیف پولت به‌صورت خودکار کم می‌شود؛ پس قبل از شروع فروش، موجودی کیف پول را در سطح کافی نگه دار.',
			$dtt_chip( 'موجودی ناکافی = توقف سفارش مشتری', 'warn' ) );
		$dtt_step( '۸', 'truck', 'پیگیری و کد رهگیری',
			'وقتی مرکز بسته را با پست پیشتاز راهی کند، کد رهگیری خودکار روی همان سفارش می‌نشیند؛ هم در پیشخوان سایت خودت و هم در بخش سفارش‌های پنل قابل مشاهده است و می‌توانی برای مشتری‌ات بفرستی.' );
		?>
	</ol>

	<div class="dtt-note"><?php echo Dastyar_Theme_Landing::icon( 'suggest' ); ?><div>برای اینکه سفارش خودکار بی‌دردسر بماند، دو چیز همیشه کامل باشد: <strong>نام و موبایل و آدرس دقیق مشتری</strong> و <strong>موجودی کافی کیف پول</strong>. بقیه کارها را سیستم انجام می‌دهد.</div></div>
</section>

<!-- ================= مسیر ب: اینستاگرامی ================= -->
<section class="dtt-tp" id="dtt-tig">
	<div class="dtt-tp-head">
		<h3>مسیر اینستاگرامی — بدون سایت، با ثبت سفارش دستی</h3>
		<span class="dtt-yes-mini dtt-manual">دستی</span>
	</div>
	<p class="dhm-sec-s">در اینستاگرام می‌فروشی؟ اصلاً به سایت نیاز نداری؛ با پنل فروشنده، سفارش هر مشتری را در چند دقیقه «دستی» ثبت می‌کنی و ما بقیه مسیر را می‌رویم.</p>

	<ol class="dtt-time">
		<?php
		$dtt_step( '۱', 'chart', 'ورود به پنل فروشنده',
			'وارد پنل فروشنده شو؛ این‌جا مرکز فرمان توست: کاتالوگ، کیف پول، ثبت سفارش و پیگیری.' );
		$dtt_step( '۲', 'package', 'انتخاب محصول از کاتالوگ',
			'در کاتالوگ دستیار محصول‌ها را ببین. «قیمت همکاری» همان بهای تمام‌شده برای توست؛ هر محصول را با قیمت دلخواه خودت در پیج‌ات تبلیغ کن.',
			$dtt_chip( 'قبل از تبلیغ، موجودی محصول را حتماً چک کن', 'warn' ) );
		$dtt_step( '۳', 'user', 'گرفتن سفارش از مشتری',
			'از مشتری این موارد را دقیق بگیر: نام و نام‌خانوادگی، شماره موبایل، استان و شهر، آدرس کامل پستی و کدپستی.',
			$dtt_chip( 'خطای رایج: آدرس ناقص ← تأخیر در ارسال', 'warn' ) );
		$dtt_step( '۴', 'orders', 'ثبت سفارش دستی در پنل',
			'در پنل ← «ثبت سفارش دستی»: محصول و تعداد را انتخاب کن و مشخصات مشتری را بنویس. مبلغ تأمین از کیف پولت کم می‌شود و سفارش بلافاصله ثبت می‌شود.',
			$dtt_chip( 'این همان «ثبت سفارش دستی» است — بدون سایت هم فروشنده‌ای', 'ok' ) );
		$dtt_step( '۵', 'clock', 'پیگیری وضعیت سفارش',
			'در بخش «سفارش‌ها» وضعیت هر سفارش را مرحله‌به‌مرحله می‌بینی: ثبت شد ← در حال پردازش ← ارسال شد.' );
		$dtt_step( '۶', 'truck', 'کد رهگیری برای مشتری',
			'به‌محض سپردن بسته، کد رهگیری روی سفارش می‌آید؛ آن را کپی کن و در دایرکت برای مشتری‌ات بفرست تا خودش بسته را رهگیری کند.' );
		?>
	</ol>
</section>

<!-- ================= مسیر ج: سایت‌سازها ================= -->
<section class="dtt-tp" id="dtt-tsb">
	<div class="dtt-tp-head">
		<h3>مسیر سایت‌سازهای آماده — فعلاً دستی، مطمئن و ساده</h3>
		<span class="dtt-yes-mini dtt-manual">دستی</span>
	</div>
	<p class="dhm-sec-s">افزونه اتصال خودکار فعلاً فقط برای سایت‌های وردپرسی (ووکامرس) است؛ اما با سایت‌سازت هم می‌توانی همین امروز فروش را شروع کنی — با ثبت سفارش دستی از پنل.</p>

	<ol class="dtt-time">
		<?php
		$dtt_step( '۱', 'chart', 'ورود به پنل و بازدید کاتالوگ',
			'وارد پنل فروشنده شو و در کاتالوگ دستیار محصول‌ها و قیمت همکاری را ببین؛ این همان بهای تمام‌شده برای توست.' );
		$dtt_step( '۲', 'home', 'آپلود محصول در سایت‌سازت',
			'عکس و توضیح محصول را از صفحه محصول کاتالوگ بردار و در فروشگاه سایت‌سازت با قیمت دلخواه خودت (با حاشیه سودت) منتشر کن.',
			$dtt_chip( 'قیمت فروش آزاد است — قیمت همکاری، بهای تمام‌شده توست', 'tip' ) );
		$dtt_step( '۳', 'user', 'ثبت سفارش در سایت خودت',
			'وقتی مشتری از سایت‌ات خرید کرد، اطلاعات تحویلش را کامل نگه دار: نام، موبایل، استان و شهر، آدرس کامل و کدپستی.' );
		$dtt_step( '۴', 'orders', 'ثبت سفارش دستی در پنل',
			'در پنل ← «ثبت سفارش دستی» همان محصول و تعداد را برای مشتری ثبت کن؛ مبلغ تأمین از کیف پول کم می‌شود و ارسال با ماست.',
			$dtt_chip( 'تا عرضه اتصال خودکار سایت‌سازها، همین راه، سریع‌ترین راه است', 'ok' ) );
		$dtt_step( '۵', 'truck', 'پیگیری و کد رهگیری',
			'در بخش «سفارش‌ها» وضعیت را ببین؛ کد رهگیری به‌محض سپردن بسته صادر می‌شود و می‌توانی در سایت‌سازت برای مشتری ارسالش کنی.' );
		?>
	</ol>

	<div class="dtt-note"><?php echo Dastyar_Theme_Landing::icon( 'rocket' ); ?><div>اتصال خودکار برای فروشگاه‌سازهای آماده در برنامه توسعه ماست؛ وقتی آماده شود، از داخل پنل به شما خبر می‌دهیم.</div></div>
</section>

<!-- ================= واژه‌نامه + کد رهگیری ================= -->
<div class="dtt-duo">
	<div class="dtt-box">
		<h3><?php echo Dastyar_Theme_Landing::icon( 'book' ); ?> چهار واژه کلیدی که همیشه می‌بینی</h3>
		<div class="dtt-gl">
			<div class="dtt-gi"><b>قیمت همکاری</b><span>بهای تمام‌شده کالا برای تو؛ تفاوت آن با قیمت فروش‌ات، سود توست.</span></div>
			<div class="dtt-gi"><b>کیف پول</b><span>حساب اعتباری تو نزد مرکز؛ هزینه تأمین سفارش‌ها از آن کم می‌شود.</span></div>
			<div class="dtt-gi"><b>ثبت سفارش دستی</b><span>ثبت سفارش مشتری به‌صورت دستی از پنل — برای فروش بدون اتصال خودکار.</span></div>
			<div class="dtt-gi"><b>کد رهگیری</b><span>شماره مرسوله‌ای که هنگام سپردن بسته برای پیگیری مسیر آن صادر می‌شود.</span></div>
		</div>
	</div>
	<div class="dtt-box">
		<h3><?php echo Dastyar_Theme_Landing::icon( 'truck' ); ?> کد رهگیری یعنی چه؟</h3>
		<p style="font-size:13px;color:#4d5d55;line-height:2;margin:0 0 12px">کد رهگیری شماره‌ای است که هنگام سپردن بسته به اداره پست (یا باربری) برای مرسوله شما صادر می‌شود. مشتری‌ات با واردکردن آن در سامانه رهگیری مرسولات پست، لحظه‌به‌لحظه محل بسته را می‌بیند.</p>
		<div class="dtt-track-code">
			<b>از کجا پیدایش کنم؟</b>
			<span>پنل فروشنده ← سفارش‌ها ← باز کردن سفارش ← کادر «کد رهگیری»</span>
			<code>۱۲۳۴۵۶۷۸۹۰۱۲۳۴۵۶۷۸۹۰۱۲۳</code>
		</div>
	</div>
</div>

<!-- ================= پرسش‌های پرتکرار ================= -->
<div class="dtt-box dtt-faq" style="margin-bottom:26px">
	<h3><?php echo Dastyar_Theme_Landing::icon( 'tickets' ); ?> پرسش‌های پرتکرار فروشنده‌ها</h3>
	<details open>
		<summary><?php echo Dastyar_Theme_Landing::icon( 'arrow-l' ); ?> وجه کالا کی و از کجا کم می‌شود؟</summary>
		<p>در هر دو مسیر (خودکار و دستی) همان لحظه ثبت‌شدن سفارش در مرکز، مبلغ تأمین کالا از کیف پول شما کسر می‌شود. قیمت فروش شما جداست و سود آن خودتان است.</p>
	</details>
	<details>
		<summary><?php echo Dastyar_Theme_Landing::icon( 'arrow-l' ); ?> اگر موجودی کیف پولم کافی نباشد چه می‌شود؟</summary>
		<p>سفارش در وضعیت صورتحساب می‌ماند تا کیف پول را شارژ کنید و بعد تسویه شود. برای جلوگیری از تأخیر در ارسال، پیش از شروع فروش هر محصول، کیف پول را به اندازه چند سفارش شارژ نگه دارید.</p>
	</details>
	<details>
		<summary><?php echo Dastyar_Theme_Landing::icon( 'arrow-l' ); ?> کد رهگیری چه زمانی صادر می‌شود؟</summary>
		<p>وقتی بسته آماده و با پست پیشتاز از اداره پست روانه می‌شود — معمولاً همان روز یا نهایتاً روز کاری بعد. کد روی سفارش شما می‌نشیند و در بخش «سفارش‌ها» قابل مشاهده است.</p>
	</details>
	<details>
		<summary><?php echo Dastyar_Theme_Landing::icon( 'arrow-l' ); ?> قیمت فروش محصولات را می‌توانم خودم تعیین کنم؟</summary>
		<p>بله؛ شما فقط از «قیمت همکاری» کالا را تأمین می‌کنید و قیمت فروش به مشتری نهایی کاملاً با خودتان است — در مسیر وردپرس با فرمول سود خودکار و در مسیرهای دستی به‌صورت آزاد.</p>
	</details>
	<details>
		<summary><?php echo Dastyar_Theme_Landing::icon( 'arrow-l' ); ?> بدون داشتن سایت هم می‌توانم فروشنده شوم؟</summary>
		<p>بله؛ مسیر «آموزش اینستاگرامی» دقیقاً برای همین طراحی شده: ثبت‌نام، انتخاب محصول از کاتالوگ و «ثبت سفارش دستی» — بدون نیاز به هیچ سایتی.</p>
	</details>
	<details>
		<summary><?php echo Dastyar_Theme_Landing::icon( 'arrow-l' ); ?> از ثبت سفارش تا تحویل چقدر طول می‌کشد؟</summary>
		<p>پس از ثبت، سفارش بلافاصله وارد چرخه آماده‌سازی می‌شود؛ زمان نهایی به مقصد و نوع ارسال بستگی دارد، اما وضعیت دقیق هر مرحله در پنل ← سفارش‌ها دیده می‌شود.</p>
	</details>
</div>

<!-- ================= CTA ================= -->
<div class="dtt-cta">
	<div>
		<h3>آماده‌ای اولین فروش‌ات را بسازی؟</h3>
		<p>همین حالا درخواست فروشندگی‌ات را ثبت کن؛ مراحل بالا راهنمای قدم‌به‌قدم توست.</p>
	</div>
	<a class="dhm-btn dhm-btn-primary" href="<?php echo esc_url( $dtt_auth ); ?>">شروع ثبت‌نام فروشنده <?php echo Dastyar_Theme_Landing::icon( 'arrow-l' ); ?></a>
	<a class="dhm-btn dhm-btn-outline" href="<?php echo esc_url( $dtt_catalog ); ?>">دیدن کاتالوگ محصولات</a>
</div>
<div class="dtt-note" style="margin-top:12px"><?php echo Dastyar_Theme_Landing::icon( 'tickets' ); ?><div>جایی گیر کردی؟ از برگه <a href="<?php echo esc_url( $dtt_contact ); ?>" style="color:#12875c;font-weight:800">تماس با ما</a> با پشتیبانی در میان بگذار؛ پاسخ‌گوی آموزش هم هستیم.</div></div>

</div></section>

<script>
(function () {
	var tabs = document.querySelectorAll('.dtt-tab');
	function go(id) {
		tabs.forEach(function (b) {
			var on = b.getAttribute('data-dtt') === id;
			b.classList.toggle('on', on);
			b.setAttribute('aria-selected', on ? 'true' : 'false');
		});
		document.querySelectorAll('.dtt-tp').forEach(function (p) {
			p.classList.toggle('on', p.id === 'dtt-' + id);
		});
	}
	tabs.forEach(function (b) {
		b.addEventListener('click', function (e) {
			e.preventDefault();
			var id = b.getAttribute('data-dtt');
			go(id);
			try { history.replaceState(null, '', '#dtt-' + id); } catch (err) {}
		});
	});
	// لینک‌های هیرو هم مثل کلیک روی تب عمل کنند (نه اسکرول به پنل مخفی)
	document.querySelectorAll('a[href^="#dtt-"]').forEach(function (a) {
		a.addEventListener('click', function (e) {
			var id = a.getAttribute('href').replace('#dtt-', '');
			if (document.getElementById('dtt-' + id)) {
				e.preventDefault();
				go(id);
				try { history.replaceState(null, '', '#dtt-' + id); } catch (err) {}
				var bar = document.querySelector('.dtt-tabs');
				if (bar) { bar.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
			}
		});
	});
	var h = (location.hash || '').replace('#dtt-', '');
	if (h && document.getElementById('dtt-' + h)) { go(h); }
})();
</script>

<?php
get_footer();
