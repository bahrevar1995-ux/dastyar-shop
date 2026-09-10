<?php
/**
 * Template Name: دستیار — لندینگ کمپین (جذب لید)
 *
 * صفحه فرود اختصاصی برای کمپین تبلیغاتی: بدون منوی ناوبری کامل سایت (عمداً — تا توجه
 * بازدیدکننده روی همون یه هدف، ثبت شماره تماس، بمونه)، با تصاویر واقعی از کاتالوگ،
 * مزیت‌ها، مراحل شروع، و آمار اعتمادسازی. فرم لید مستقیم به همون بک‌اند لیدهای فعلی
 * قالب وصله (Dastyar_Theme_Leads / اکشن dth_lead) — یعنی نتیجه‌ها همون‌جایی می‌شینه که
 * لیدهای هیرو می‌شینن: نمایش ← مشاوره‌ها.
 *
 * @package Dastyar_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dth_s = Dastyar_Theme_Settings::all();

$dth_cmp_products = array();
if ( function_exists( 'wc_get_products' ) ) {
	$dth_cmp_products = (array) wc_get_products( array(
		'status'  => 'publish',
		'limit'   => 4,
		'orderby' => 'date',
		'order'   => 'DESC',
	) );
}

$dth_cmp_benefits = array(
	array( 'rocket', 'شروع بدون سرمایه اولیه', 'نیازی به خرید انبار یا موجودی اولیه نیست؛ فقط بفروش، بقیه‌اش با ما.' ),
	array( 'boxes', 'تامین و بسته‌بندی با ما', 'کالا رو تامین، بسته‌بندی و آماده ارسال می‌کنیم — تو فقط فروش رو مدیریت کن.' ),
	array( 'truck', 'ارسال سریع سراسر کشور', 'با کد رهگیری شفاف؛ هم تو هم مشتری‌ت مرحله‌به‌مرحله وضعیت سفارش رو می‌بینید.' ),
	array( 'wallet', 'تسویه شفاف از کیف پول', 'صورتحساب و کیف پول همیشه در دسترس؛ هیچ هزینه پنهانی وجود نداره.' ),
	array( 'sync', 'اتصال خودکار به ووکامرس', 'با یک افزونه ساده، فروشگاه ووکامرسی خودت به کاتالوگ ما وصل می‌شه.' ),
	array( 'headset', 'پشتیبانی همیشگی', 'از ثبت‌نام تا اولین فروش و بعدش، تیم پشتیبانی کنارته.' ),
);

$dth_cmp_steps = array(
	array( 'user', 'ثبت‌نام رایگان', 'فرم زیر رو پر کن؛ باهات تماس می‌گیریم و ثبت‌نامت رو کامل می‌کنیم.' ),
	array( 'store', 'اتصال فروشگاه', 'افزونه دستیار رو روی فروشگاه ووکامرسی‌ات نصب می‌کنی — دو دقیقه‌ای.' ),
	array( 'cart', 'انتخاب محصول', 'از کاتالوگ ما محصول انتخاب می‌کنی و با یک کلیک به فروشگاهت اضافه می‌شه.' ),
	array( 'chart', 'فروش و رشد', 'مشتری سفارش می‌ده، ما تامین/ارسال می‌کنیم، سودت میاد تو کیف پولت.' ),
);

$dth_cmp_stats = Dastyar_Theme_Landing::parse_lines( (string) $dth_s['hero_ministats'] );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'dth-campaign-page' ); ?>>
<div class="dth-campaign">
<div class="dth-cmp-topbar">
	<div class="cin dth-cmp-topbar-in">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dth-cmp-logo"><?php bloginfo( 'name' ); ?></a>
		<?php
		$dth_cmp_phone = trim( (string) ( $dth_s['contact_phone'] ?? '' ) );
		if ( $dth_cmp_phone ) :
			?>
			<a href="tel:<?php echo esc_attr( preg_replace( '/\D/', '', $dth_cmp_phone ) ); ?>" class="dth-cmp-topphone"><?php echo Dastyar_Theme_Landing::icon( 'phone' ); ?><?php echo esc_html( $dth_cmp_phone ); ?></a>
		<?php endif; ?>
	</div>
</div>
<style>
	.dth-campaign{--c-green:#17a16d;--c-green-d:#12875c;--c-navy:#242536;--c-line:#e8eaf2;--c-bg:#f6f8fb;--c-muted:#6b6e84;font-family:"Vazirmatn","IRANSans",Tahoma,sans-serif;color:#2b2c3a}
	.dth-campaign .cin{max-width:1140px;margin:0 auto;padding:0 20px}
	.dth-campaign section{padding:56px 0}

	/* نوار بالا — فقط لوگو + تلفن، عمداً بدون منو تا حواس بازدیدکننده پرت نشه */
	.dth-cmp-topbar{background:#fff;border-bottom:1px solid var(--c-line)}
	.dth-cmp-topbar-in{display:flex;align-items:center;justify-content:space-between;padding:14px 20px}
	.dth-cmp-logo{font-size:17px;font-weight:900;color:var(--c-navy);text-decoration:none}
	.dth-cmp-topphone{display:inline-flex;align-items:center;gap:6px;color:var(--c-green);font-weight:800;font-size:13.5px;text-decoration:none}
	.dth-cmp-topphone svg{width:15px;height:15px}

	/* هیرو + فرم لید */
	.dth-cmp-hero{background:linear-gradient(180deg,#f2faf6 0%,#f6f8fb 100%);position:relative;overflow:hidden;padding:60px 0 70px}
	.dth-cmp-hero::before{content:"";position:absolute;top:-160px;inset-inline-start:-100px;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle,rgba(47,206,150,.15),transparent 65%)}
	.dth-cmp-hero-in{position:relative;z-index:1;display:grid;grid-template-columns:1.1fr .9fr;gap:36px;align-items:center}
	.dth-cmp-badge{display:inline-flex;align-items:center;gap:7px;background:#fff;border:1px solid var(--c-line);color:var(--c-green);border-radius:999px;padding:6px 16px;font-size:12.5px;font-weight:700;margin-bottom:18px}
	.dth-cmp-hero h1{font-size:36px;font-weight:900;line-height:1.6;margin:0 0 14px;color:var(--c-navy)}
	.dth-cmp-hero h1 span{color:var(--c-green)}
	.dth-cmp-hero p.sub{font-size:15.5px;color:var(--c-muted);line-height:2;max-width:480px;margin:0 0 22px}
	.dth-cmp-ticks{display:flex;flex-wrap:wrap;gap:10px 20px;margin-bottom:6px}
	.dth-cmp-ticks span{font-size:12.5px;font-weight:700;color:var(--c-navy);display:inline-flex;align-items:center;gap:6px}
	.dth-cmp-ticks svg{width:15px;height:15px;color:var(--c-green)}

	.dth-cmp-form{background:#fff;border:1px solid var(--c-line);border-radius:18px;padding:22px;box-shadow:0 20px 46px rgba(36,37,54,.1)}
	.dth-cmp-form h3{margin:0 0 4px;font-size:16px;font-weight:800;color:var(--c-navy)}
	.dth-cmp-form p{margin:0 0 16px;font-size:12.5px;color:var(--c-muted)}
	.dth-cmp-form input{width:100%;box-sizing:border-box;border:1.5px solid var(--c-line);border-radius:10px;padding:12px 14px;font-size:14px;font-family:inherit;margin-bottom:10px;background:#fbfdfc}
	.dth-cmp-form input:focus{outline:none;border-color:var(--c-green);box-shadow:0 0 0 3px rgba(23,161,109,.12)}
	.dth-cmp-form button{width:100%;background:var(--c-green);color:#fff;border:0;border-radius:12px;padding:13px;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit}
	.dth-cmp-form button:hover{background:var(--c-green-d)}
	.dth-cmp-form button:disabled{opacity:.65;cursor:default}
	.dth-cmp-ok{background:#eafaf2;border:1px solid #bfe6d2;color:#12875c;border-radius:10px;padding:12px;text-align:center;font-size:13px;font-weight:700;margin-top:4px}
	.dth-cmp-err{background:#fdecec;border:1px solid #f0c8c8;color:#a52828;border-radius:10px;padding:12px;text-align:center;font-size:13px;font-weight:700;margin-top:4px}
	.dth-cmp-priv{font-size:10.5px;color:#9aa0b5;text-align:center;margin-top:10px}

	/* عنوان بخش */
	.dth-cmp-h2{text-align:center;font-size:26px;font-weight:900;color:var(--c-navy);margin:0 0 8px}
	.dth-cmp-h2sub{text-align:center;color:var(--c-muted);font-size:14px;margin:0 0 36px}

	/* مزیت‌ها */
	.dth-cmp-benefits{background:#fff}
	.dth-cmp-bgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
	.dth-cmp-bcard{border:1px solid var(--c-line);border-radius:16px;padding:24px 20px}
	.dth-cmp-bic{width:44px;height:44px;border-radius:12px;background:#eafaf2;color:var(--c-green);display:flex;align-items:center;justify-content:center;margin-bottom:14px}
	.dth-cmp-bic svg{width:22px;height:22px}
	.dth-cmp-bcard h4{margin:0 0 8px;font-size:15px;font-weight:800;color:var(--c-navy)}
	.dth-cmp-bcard p{margin:0;font-size:13px;color:var(--c-muted);line-height:1.9}

	/* مراحل */
	.dth-cmp-steps{background:var(--c-bg)}
	.dth-cmp-sgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
	.dth-cmp-scard{background:#fff;border:1px solid var(--c-line);border-radius:16px;padding:22px 18px;text-align:center;position:relative}
	.dth-cmp-snum{position:absolute;top:-12px;right:16px;background:var(--c-navy);color:#fff;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800}
	.dth-cmp-sic{width:46px;height:46px;border-radius:50%;background:#eafaf2;color:var(--c-green);display:flex;align-items:center;justify-content:center;margin:6px auto 14px}
	.dth-cmp-sic svg{width:22px;height:22px}
	.dth-cmp-scard h4{margin:0 0 6px;font-size:14px;font-weight:800;color:var(--c-navy)}
	.dth-cmp-scard p{margin:0;font-size:12px;color:var(--c-muted);line-height:1.8}

	/* نمونه محصولات */
	.dth-cmp-products{background:#fff}
	.dth-cmp-pgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
	.dth-cmp-pcard{border:1px solid var(--c-line);border-radius:16px;overflow:hidden}
	.dth-cmp-pcard .ph{aspect-ratio:1/1;background:var(--c-bg);display:flex;align-items:center;justify-content:center;overflow:hidden}
	.dth-cmp-pcard .ph img{width:100%;height:100%;object-fit:cover}
	.dth-cmp-pcard .pt{padding:12px 14px}
	.dth-cmp-pcard .pt strong{display:block;font-size:12.5px;color:var(--c-navy);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
	.dth-cmp-pcard .pt span{font-size:12px;color:var(--c-green);font-weight:800}

	/* آمار اعتماد */
	.dth-cmp-stats{background:var(--c-navy);color:#fff}
	.dth-cmp-sgrid2{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;text-align:center}
	.dth-cmp-sgrid2 b{display:block;font-size:30px;font-weight:900;color:#2fce96;margin-bottom:6px}
	.dth-cmp-sgrid2 span{font-size:13px;color:rgba(255,255,255,.75)}

	/* CTA پایانی */
	.dth-cmp-final{background:linear-gradient(180deg,#f6f8fb 0%,#f2faf6 100%);text-align:center}
	.dth-cmp-final h2{font-size:26px;font-weight:900;color:var(--c-navy);margin:0 0 10px}
	.dth-cmp-final p{color:var(--c-muted);font-size:14.5px;margin:0 0 28px}
	.dth-cmp-final .dth-cmp-form{max-width:420px;margin:0 auto;text-align:right}

	.dth-cmp-foot{background:var(--c-navy);color:rgba(255,255,255,.55);text-align:center;padding:20px 0;font-size:12px}

	@media (max-width:900px){
		.dth-cmp-hero-in{grid-template-columns:1fr}
		.dth-cmp-bgrid,.dth-cmp-sgrid,.dth-cmp-pgrid{grid-template-columns:repeat(2,1fr)}
		.dth-cmp-sgrid2{grid-template-columns:1fr}
		.dth-cmp-hero h1{font-size:27px}
		section{padding:40px 0}
	}
</style>

<section class="dth-cmp-hero">
	<div class="cin dth-cmp-hero-in">
		<div>
			<span class="dth-cmp-badge">⭐ اولین پلتفرم دراپ‌شیپینگ هوشمند ایران</span>
			<h1>فروشگاه اینترنتی بزن، <span>بدون نگرانی از تامین و ارسال</span></h1>
			<p class="sub">دستیار شاپ تامین، بسته‌بندی، ارسال و کد رهگیری سفارش‌هات رو به عهده می‌گیره — تو فقط روی فروش تمرکز کن.</p>
			<div class="dth-cmp-ticks">
				<span><?php echo Dastyar_Theme_Landing::icon( 'check' ); ?> بدون هزینه ثبت‌نام</span>
				<span><?php echo Dastyar_Theme_Landing::icon( 'check' ); ?> بدون نیاز به انبار</span>
				<span><?php echo Dastyar_Theme_Landing::icon( 'check' ); ?> پشتیبانی رایگان</span>
			</div>
		</div>
		<div class="dth-cmp-form">
			<h3>مشاوره رایگان می‌خوای؟</h3>
			<p>شماره‌ت رو بذار، همین امروز باهات تماس می‌گیریم.</p>
			<form id="dth-cmp-lead">
				<input type="text" name="name" placeholder="اسمت (اختیاری)">
				<input type="tel" name="phone" placeholder="شماره موبایل" inputmode="numeric" required>
				<button type="submit">درخواست مشاوره رایگان ←</button>
				<p class="dth-cmp-ok" hidden>✓ ثبت شد؛ به‌زودی باهات تماس می‌گیریم.</p>
				<p class="dth-cmp-err" hidden>مشکلی پیش اومد؛ دوباره امتحان کن.</p>
			</form>
			<p class="dth-cmp-priv">شماره‌ت فقط برای تماس مشاوره استفاده می‌شه.</p>
		</div>
	</div>
</section>

<section class="dth-cmp-benefits">
	<div class="cin">
		<h2 class="dth-cmp-h2">چرا دستیار شاپ؟</h2>
		<p class="dth-cmp-h2sub">همه‌چیز طوری طراحی شده که تو فقط نگران فروش باشی</p>
		<div class="dth-cmp-bgrid">
			<?php foreach ( $dth_cmp_benefits as $b ) : ?>
				<div class="dth-cmp-bcard">
					<div class="dth-cmp-bic"><?php echo Dastyar_Theme_Landing::icon( $b[0] ); ?></div>
					<h4><?php echo esc_html( $b[1] ); ?></h4>
					<p><?php echo esc_html( $b[2] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="dth-cmp-steps">
	<div class="cin">
		<h2 class="dth-cmp-h2">چطور شروع کنم؟</h2>
		<p class="dth-cmp-h2sub">فقط ۴ قدم تا اولین فروشت</p>
		<div class="dth-cmp-sgrid">
			<?php foreach ( $dth_cmp_steps as $i => $st ) : ?>
				<div class="dth-cmp-scard">
					<span class="dth-cmp-snum"><?php echo esc_html( Dastyar_Theme_Settings::fa_num( $i + 1 ) ); ?></span>
					<div class="dth-cmp-sic"><?php echo Dastyar_Theme_Landing::icon( $st[0] ); ?></div>
					<h4><?php echo esc_html( $st[1] ); ?></h4>
					<p><?php echo esc_html( $st[2] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php if ( $dth_cmp_products ) : ?>
<section class="dth-cmp-products">
	<div class="cin">
		<h2 class="dth-cmp-h2">نمونه‌ای از محصولات آماده فروش</h2>
		<p class="dth-cmp-h2sub">صدها محصول دیگه هم تو کاتالوگ منتظرته</p>
		<div class="dth-cmp-pgrid">
			<?php foreach ( $dth_cmp_products as $p ) : if ( ! is_object( $p ) ) { continue; } ?>
				<div class="dth-cmp-pcard">
					<div class="ph"><?php echo $p->get_image( 'medium' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی کنترل‌شده ووکامرس ?></div>
					<div class="pt">
						<strong><?php echo esc_html( $p->get_name() ); ?></strong>
						<span><?php echo wp_kses_post( wc_price( (float) $p->get_price() ) ); ?></span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $dth_cmp_stats ) : ?>
<section class="dth-cmp-stats">
	<div class="cin">
		<div class="dth-cmp-sgrid2">
			<?php foreach ( array_slice( $dth_cmp_stats, 0, 3 ) as $row ) :
				$parts = explode( '|', $row, 2 );
				?>
				<div><b><?php echo esc_html( $parts[0] ?? '' ); ?></b><span><?php echo esc_html( $parts[1] ?? '' ); ?></span></div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="dth-cmp-final">
	<div class="cin">
		<h2>همین حالا شروع کن</h2>
		<p>شماره‌ت رو بذار، تیم دستیار شاپ باهات تماس می‌گیره و راه‌اندازی فروشگاهت رو قدم‌به‌قدم توضیح می‌ده.</p>
		<div class="dth-cmp-form">
			<form class="dth-cmp-lead-2">
				<input type="text" name="name" placeholder="اسمت (اختیاری)">
				<input type="tel" name="phone" placeholder="شماره موبایل" inputmode="numeric" required>
				<button type="submit">درخواست مشاوره رایگان ←</button>
				<p class="dth-cmp-ok" hidden>✓ ثبت شد؛ به‌زودی باهات تماس می‌گیریم.</p>
				<p class="dth-cmp-err" hidden>مشکلی پیش اومد؛ دوباره امتحان کن.</p>
			</form>
		</div>
	</div>
</section>

<div class="dth-cmp-foot">© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> — همه حقوق محفوظ است.</div>
</div>

<script>
(function(){
	function bind(form){
		if (!form) { return; }
		form.addEventListener('submit', function(e){
			e.preventDefault();
			var ok = form.querySelector('.dth-cmp-ok'), err = form.querySelector('.dth-cmp-err');
			var btn = form.querySelector('button[type=submit]');
			ok.hidden = true; err.hidden = true;
			var phoneInp = form.querySelector('input[name=phone]');
			var phone = (phoneInp.value || '').replace(/[^0-9]/g, '');
			if (phone.length < 10 || phone.indexOf('09') !== 0) {
				phoneInp.focus();
				err.textContent = 'شماره موبایل رو درست وارد کن (مثلاً 0912xxxxxxx).';
				err.hidden = false;
				return;
			}
			var old = btn.textContent;
			btn.disabled = true; btn.textContent = 'در حال ثبت…';
			var body = new URLSearchParams({
				action: 'dth_lead',
				nonce: '<?php echo esc_js( wp_create_nonce( 'dth_lead' ) ); ?>',
				phone: phone,
				page: window.location.href
			});
			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
				method: 'POST', credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			})
			.then(function(r){ return r.json(); })
			.then(function(j){
				if (j && j.success) { form.reset(); ok.hidden = false; }
				else { err.textContent = (j && j.data && j.data.msg) || 'مشکلی پیش اومد؛ دوباره امتحان کن.'; err.hidden = false; }
			})
			.catch(function(){ err.textContent = 'خطای شبکه؛ دوباره امتحان کن.'; err.hidden = false; })
			.finally(function(){ btn.disabled = false; btn.textContent = old; });
		});
	}
	bind(document.getElementById('dth-cmp-lead'));
	bind(document.querySelector('.dth-cmp-lead-2'));
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
