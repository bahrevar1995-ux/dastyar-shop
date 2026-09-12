<?php
/**
 * (v1.10.0 — «Command Center») سیستم کامپوننت واحد پنل کانکتور دستیار
 *
 * پس از آزمون ۵ پیشنهاد گرافیکی، مسیر «داشبورد فرماندهی» (Command Center) انتخاب شد و
 * تحلیل ساختاری تأیید شد: به‌جای «پوستهٔ نجات» روی مارک‌آپ‌های باستانی، همهٔ تب‌های پنل
 * فقط با همین کلاس‌ها رندر می‌شوند. پیش‌نم‌ همه انتخابگرها = dcx2. خروجی rescue/polish حذف شده است.
 *
 * اجزا: کارت (card) — جدول (dcx2-table) — پیل (dcx2-pill) — KPI پهن با مینی‌چارت — بنر به‌روزرسانی —
 * نوار اقدام سریع — رویدادها (feed) — آکاردئون تنظیمات (details.dcx2-acc) — حریت: هنوز کلاس‌های
 * عمومی وردپرس (widefat, form-table, .button) داخل .dcx2 به‌صورت یکپارچه استایل می‌خورند تا
 * خروجی ماژول‌های جانبی (مثل صفحهٔ مدیریت عودت) هم در همان زبان جای بگیرند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DastyarC_Ui {

	/* ==================================================================
	 * استایل‌ها
	 * ================================================================== */

	/** <style> کامل سیستم — در top صفحه هاب چاپ می‌شود */
	public static function css() {
		?>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
		<style id="dcx2-css">
		/* ═══ پس‌زمینه و کروم ادمین ═══ */
		body.dastyarc-hub #wpbody-content{background:
			radial-gradient(800px 480px at 100% 0, rgba(47,206,150,.09), transparent 60%),
			radial-gradient(700px 460px at 0 100%, rgba(23,161,109,.06), transparent 55%),
			#f3f6f4;background-attachment:fixed;padding-bottom:0}
		body.dastyarc-hub .wrap{margin:0}
		body.dastyarc-hub .notice{margin:12px 18px 0}
		/* ═══ متغیرها ═══ */
		.dcx2{--g1:#17a16d;--g2:#2fce96;--ink:#1f2233;--mut:#7a8194;--line:#e6eae8;
			--bg:#f4f7f5;--card:#ffffff;--grad:linear-gradient(135deg,#17a16d,#2fce96);
			--shadow:0 8px 24px -12px rgba(31,34,51,.14);--red:#e0263f;--amber:#b45309;--blue:#2563eb;
			color:var(--ink);font-family:'Vazirmatn',Tahoma,sans-serif;font-size:13px;line-height:1.9}
		.dcx2 *{box-sizing:border-box}
		.dcx2 b,.dcx2 strong{font-weight:800}
		.dcx2 a{text-decoration:none}
		.dcx2 a:hover{text-decoration:none}
		/* ═══ چیدمان ═══ */
		.dcx2-shell{max-width:1380px;margin:10px auto 40px;padding:0 16px;display:grid;grid-template-columns:236px 1fr;gap:20px;align-items:start}
		/* ═══ ریل ═══ */
		.dcx2-rail{position:sticky;top:48px;background:var(--card);border:1px solid var(--line);border-radius:18px;
			padding:16px 12px;box-shadow:0 8px 26px -12px rgba(31,34,51,.12)}
		.dcx2-brand{display:flex;gap:9px;align-items:center;padding:2px 8px 13px;border-bottom:1px solid var(--line);margin-bottom:11px}
		.dcx2-mark{width:38px;height:38px;border-radius:11px;background:var(--grad);display:flex;align-items:center;justify-content:center;color:#fff;flex:none;box-shadow:0 4px 14px -4px rgba(23,161,109,.5)}
		.dcx2-brand b{font-size:12.5px;display:block;line-height:1.6}
		.dcx2-brand small{font-size:9.5px;color:var(--mut);display:block;direction:ltr;text-align:right}
		.dcx2-nav a{display:flex;align-items:center;gap:9px;padding:9.5px 11px;border-radius:11px;color:var(--mut);
			font-weight:700;font-size:12.5px;margin-bottom:2px;position:relative;transition:background .15s}
		.dcx2-nav a svg{width:16px;height:16px;flex:none}
		.dcx2-nav a:hover{background:rgba(47,206,150,.08);color:var(--ink)}
		.dcx2-nav a.on{background:linear-gradient(135deg,rgba(23,161,109,.12),rgba(47,206,150,.16));color:var(--ink)}
		.dcx2-nav a.on:before{content:"";position:absolute;right:-12px;top:22%;bottom:22%;width:4px;border-radius:4px;background:var(--grad)}
		.dcx2-nav .t{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.dcx2-nav .b{min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--red);color:#fff;font-size:10px;font-weight:800;display:inline-flex;align-items:center;justify-content:center}
		.dcx2-grp{margin:12px 8px 5px;font-size:9.5px;color:var(--mut);font-weight:800;letter-spacing:.4px}
		.dcx2-rsync{margin-top:13px;padding-top:11px;border-top:1px dashed var(--line);font-size:10.5px;color:var(--mut);display:flex;align-items:center;gap:7px}
		.dcx2-rsync .ok{width:8px;height:8px;border-radius:50%;background:var(--g2);box-shadow:0 0 0 3px rgba(47,206,150,.2);flex:none}
		.dcx2-rsync .ver{margin-right:auto;background:rgba(23,161,109,.1);color:var(--g1);border:1px solid rgba(23,161,109,.25);padding:1px 8px;border-radius:999px;font-size:9.5px;font-weight:800;direction:ltr}
		/* ═══ هدر ═══ */
		.dcx2-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:6px 0 16px}
		.dcx2-head h1{margin:0;padding:0;font-size:19px;font-weight:800;color:var(--ink);line-height:1.6}
		.dcx2-head h1 small{display:block;font-size:11px;color:var(--mut);font-weight:600;margin-top:1px}
		.dcx2-chip{display:flex;align-items:center;gap:7px;background:#e9f8f0;border:1px solid #bce6cd;color:#0f7a4d;
			border-radius:99px;padding:5px 13px;font-size:11px;font-weight:800}
		.dcx2-chip .d{width:8px;height:8px;border-radius:50%;background:var(--g2);box-shadow:0 0 0 3px rgba(47,206,150,.25);animation:dcx2p 2s infinite;flex:none}
		.dcx2-chip.off{background:#fdf0f0;border-color:#f0c8c8;color:#c81e38}
		.dcx2-chip.off .d{background:#c81e38;box-shadow:none;animation:none}
		@keyframes dcx2p{50%{box-shadow:0 0 0 7px rgba(47,206,150,0)}}
		.dcx2-qa{margin-right:auto;display:flex;gap:8px;flex-wrap:wrap}
		/* ═══ دکمه‌ها ═══ */
		.dcx2-btn{border:1.5px solid var(--line);background:var(--card);color:var(--ink);cursor:pointer;font-family:inherit;
			font-weight:800;font-size:11.5px;border-radius:11px;padding:8px 14px;display:inline-flex;align-items:center;gap:6px;text-decoration:none;line-height:1.6}
		.dcx2-btn:hover{background:#f4f8f6;border-color:#cfe0d8}
		.dcx2-btn.prime{background:var(--grad);color:#fff;border-color:transparent;box-shadow:0 6px 14px -6px rgba(23,161,109,.45)}
		.dcx2-btn.prime:hover{filter:brightness(1.06)}
		.dcx2-btn .up{background:var(--red);border-radius:99px;color:#fff;font-size:9.5px;padding:0 6px;line-height:16px}
		.dcx2-btn.sm{padding:5px 11px;font-size:10.5px;border-radius:9px}
		/* ═══ بنر به‌روزرسانی ═══ */
		.dcx2-upd{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,#fff7ed,#fff1e2);
			border:1.5px solid #f4c98b;border-radius:14px;padding:11px 15px;margin-bottom:16px;font-size:12px;flex-wrap:wrap}
		.dcx2-upd b{color:#b45309}
		.dcx2-upd.lv-critical,.dcx2-upd.lv-urgent{background:linear-gradient(135deg,#fdeeee,#fde6e6);border-color:#f2b0b0}
		.dcx2-upd.lv-critical b,.dcx2-upd.lv-urgent b{color:#e0263f}
		.dcx2-upd .acts{margin-right:auto;display:flex;gap:8px}
		/* ═══ شریط هشدار ═══ */
		.dcx2-warn{display:flex;align-items:center;gap:10px;background:#fff8eb;border:1.5px solid #f0d7a0;border-radius:13px;
			padding:9px 14px;margin-bottom:16px;font-size:11.5px;color:#8a6d00;font-weight:700;flex-wrap:wrap}
		.dcx2-warn a{margin-right:auto;color:#b45309;font-weight:800;border-bottom:1px dashed #b45309}
		/* ═══ KPI ═══ */
		.dcx2-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px}
		.dcx2-kpi{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:15px 16px;
			box-shadow:var(--shadow);position:relative;overflow:hidden}
		.dcx2-kpi>i{position:absolute;left:0;top:0;width:4px;height:100%;background:var(--grad)}
		.dcx2-kpi .lb{font-size:11px;color:var(--mut);font-weight:700}
		.dcx2-kpi .vl{font-size:21px;font-weight:800;margin:2px 0;direction:ltr;text-align:right}
		.dcx2-kpi .tg{font-size:10px;font-weight:800;padding:1px 8px;border-radius:99px;white-space:nowrap}
		.dcx2-kpi svg.spk{position:absolute;bottom:8px;left:14px;opacity:.55}
		.dcx2-tg.up{background:#e9f8f0;color:#0f7a4d}.dcx2-tg.wr{background:#fff1e2;color:#b45309}
		.dcx2-tg.dn{background:#fdecec;color:#c81e38}.dcx2-tg.bl{background:#eef4ff;color:#2563eb}
		/* ═══ کارت ═══ */
		.dcx2-card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);margin-bottom:16px}
		.dcx2-card>header{font-size:13px;font-weight:800;padding:13px 16px;border-bottom:1px solid var(--line);
			display:flex;align-items:center;gap:8px;flex-wrap:wrap}
		.dcx2-card>header .more{margin-right:auto;font-size:10.5px;color:var(--mut);font-weight:700}
		.dcx2-card>.in{padding:14px 16px}
		/* ═══ جدول ═══ */
		.dcx2-table{width:100%;border-collapse:collapse;font-size:12px}
		.dcx2-table th{font-size:10.5px;color:var(--mut);font-weight:800;text-align:right;padding:10px 16px;
			border-bottom:1px solid var(--line);background:#fafcfc}
		.dcx2-table td{padding:9px 16px;border-bottom:1px solid #f0f3f2;vertical-align:middle}
		.dcx2-table tr:last-child td{border:0}
		.dcx2-table tr:hover td{background:#f9fcfa}
		/* ═══ پیل ═══ */
		.dcx2-pill{display:inline-block;padding:2px 9px;border-radius:99px;font-size:10px;font-weight:800;white-space:nowrap;border:1px solid transparent}
		.dcx2-pill--g{background:#e9f8f0;color:#0f7a4d}
		.dcx2-pill--b{background:#eef4ff;color:#2563eb}
		.dcx2-pill--y{background:#fff8eb;color:#b45309}
		.dcx2-pill--r{background:#fdecec;color:#c81e38}
		.dcx2-pill--x{background:#f0f2f5;color:#5b6472}
		/* ═══ رویدادها ═══ */
		.dcx2-feed{padding:6px 8px 8px;margin:0}
		.dcx2-feed li{list-style:none;display:flex;gap:9px;padding:8px 10px;border-radius:10px;font-size:11.5px}
		.dcx2-feed li:hover{background:#f7faf9}
		.dcx2-feed .t{margin-right:auto;font-size:10px;color:var(--mut);white-space:nowrap}
		.dcx2-feed .dt{width:9px;height:9px;border-radius:50%;margin-top:7px;flex:none}
		.dcx2-feed .dt--info{background:#17a16d}.dcx2-feed .dt--warning{background:#d98a1f}.dcx2-feed .dt--error{background:#e0263f}
		.dcx2-feed .dt--notice{background:#2563eb}
		.dcx2-health{color:var(--mut);font-size:10.5px;padding:10px 16px;border-top:1px dashed var(--line)}
		/* ═══ گرید محتوا ═══ */
		.dcx2-grid2{display:grid;grid-template-columns:1.6fr 1fr;gap:16px;align-items:start}
		/* ═══ فرم‌ها و جداول بومی وردپرس درون هاب ═══ */
		.dcx2 .widefat{background:var(--card);border:1px solid var(--line)!important;border-radius:14px;overflow:hidden;box-shadow:var(--shadow)}
		.dcx2 .widefat thead th,.dcx2 .widefat thead td{font-size:10.5px;color:var(--mut);font-weight:800;background:#fafcfc;border-bottom:1px solid var(--line)!important}
		.dcx2 .widefat tbody td{vertical-align:middle;border-color:#f0f3f2!important}
		.dcx2 input[type=text],.dcx2 input[type=url],.dcx2 input[type=number],.dcx2 input[type=search],.dcx2 input[type=password],
		.dcx2 select,.dcx2 textarea{border:1.5px solid var(--line)!important;border-radius:9px!important;background:#fafbfc!important;
			box-shadow:none!important;padding:7px 11px;font-family:'Vazirmatn',Tahoma,sans-serif!important;transition:border-color .15s,box-shadow .15s}
		.dcx2 input:focus,.dcx2 select:focus,.dcx2 textarea:focus{border-color:var(--g1)!important;box-shadow:0 0 0 3px rgba(23,161,109,.14)!important;outline:none}
		.dcx2 button,.dcx2 .button,.dcx2 input[type=submit]{font-family:'Vazirmatn',Tahoma,sans-serif!important;border-radius:9px!important}
		.dcx2 .button-primary{background:var(--g1)!important;border-color:var(--g1)!important}
		.dcx2 .button-primary:hover{background:#12875c!important;border-color:#12875c!important}
		.dcx2 .form-table{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:8px 18px 14px;margin:8px 0 6px;box-shadow:var(--shadow)}
		.dcx2 .form-table th{color:var(--ink);font-weight:700}
		.dcx2 .description{color:var(--mut)}
		/* ═══ فیلترها/نوار ابزار تب‌ها ═══ */
		.dcx2-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:var(--card);border:1px solid var(--line);
			border-radius:14px;padding:11px 14px;margin:12px 0;box-shadow:var(--shadow)}
		.dcx2-bar.bulk{border-right:4px solid var(--g1)}
		.dcx2-pager{display:flex;gap:8px;align-items:center;margin:14px 2px}
		.dcx2-pager .off{opacity:.45;pointer-events:none}
		.dcx2-empty{padding:30px!important;text-align:center;color:var(--mut);font-size:12.5px}
		/* ═══ آکاردئون تنظیمات ═══ */
		.dcx2-acc{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);margin-bottom:12px;overflow:hidden}
		.dcx2-acc>summary{cursor:pointer;list-style:none;padding:15px 17px;font-weight:800;font-size:13.5px;display:flex;align-items:center;gap:10px}
		.dcx2-acc>summary::-webkit-details-marker{display:none}
		.dcx2-acc>summary .ico{width:30px;height:30px;border-radius:9px;background:rgba(23,161,109,.1);color:var(--g1);display:flex;align-items:center;justify-content:center;flex:none}
		.dcx2-acc>summary .sub{font-size:10.5px;color:var(--mut);font-weight:600;display:block;line-height:1.6}
		.dcx2-acc>summary .chev{margin-right:auto;color:var(--mut);transition:transform .2s}
		.dcx2-acc[open]>summary{border-bottom:1px solid var(--line)}
		.dcx2-acc[open]>summary .chev{transform:rotate(90deg)}
		.dcx2-acc>.body{padding:4px 17px 18px}
		/* ═══ هیرو کیف پول ═══ */
		.dcx2-hero{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;
			background:linear-gradient(135deg,#1a3a2e,#0d5c3f);border-radius:18px;padding:24px 26px;color:#fff;
			box-shadow:0 14px 34px -14px rgba(13,92,63,.5);margin-bottom:16px;position:relative;overflow:hidden}
		.dcx2-hero:before{content:"";position:absolute;inset:0;background:radial-gradient(500px 240px at 90% 0,rgba(255,255,255,.14),transparent 60%);pointer-events:none}
		.dcx2-hero h2{margin:0 0 10px;font-size:14px;font-weight:800;color:#bff3de}
		.dcx2-hero .bal{font-size:30px;font-weight:800;line-height:1.6;direction:ltr;text-align:left}
		.dcx2-hero .bal small{font-size:12px;color:#a9dcc4;font-weight:600}
		.dcx2-hero .note{font-size:11.5px;color:#bfdccf;line-height:2;max-width:560px;margin-top:10px}
		/* ═══ رسپانسیو ═══ */
		/* ───────── ستون محتوا / نوتیس ───────── */
		.dcx2-main{min-width:0}
		.dcx2-notice{margin:2px 0 14px;padding:11px 14px;border-radius:11px;font-size:12px;font-weight:600;border:1px solid rgba(23,161,109,.3);background:rgba(23,161,109,.07);color:#12845a}
		.dcx2-notice.x-bad{border-color:#f0c3c3;background:rgba(224,38,63,.06);color:#c81e38}
		.dcx2-health b{color:var(--g1)}
		.dcx2-health.bad b{color:#c81e38}

		/* ───────── هیروی کیف پول (داشبورد) ───────── */
		.dcx2-card.dcx2-hero{background:var(--grad);border:0;box-shadow:0 14px 34px -14px rgba(23,161,109,.5);padding:18px;border-radius:18px}
		.dcx2-hero .hd{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;color:#fff;font-size:13.5px;font-weight:800}
		.dcx2-hero .hd form{margin:0}
		.dcx2-hero .hd .dcx2-btn{color:#fff;border-color:rgba(255,255,255,.35);background:transparent}
		.dcx2-hero .hd .dcx2-btn:hover{background:rgba(255,255,255,.12)}
		.dcx2-hero .tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:14px}
		.dcx2-hero .tile{background:#fff;border-radius:13px;padding:16px;box-shadow:0 4px 14px rgba(15,80,55,.10)}
		.dcx2-hero .tile span{display:block;font-size:11px;color:var(--mut);margin-bottom:8px;font-weight:700}
		.dcx2-hero .tile b{font-size:19px;color:#14684a}
		.dcx2-hero .tile.lo b{font-size:16px;color:var(--ink)}
		.dcx2-hero .acts{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
		.dcx2-hero .acts .dcx2-btn{border-color:rgba(255,255,255,.5);background:rgba(255,255,255,.10);color:#fff}
		.dcx2-hero .acts .dcx2-btn:hover{background:rgba(255,255,255,.18)}
		.dcx2-hero .acts .dcx2-btn.prime{background:#fff;border-color:#fff;color:var(--g1);box-shadow:0 6px 14px -6px rgba(0,0,0,.25)}
		.dcx2-hero .hint{display:block;margin-top:12px;color:rgba(255,255,255,.8);font-size:11px}

		/* ───────── آکوردئون تنظیمات (v1.10.0) ───────── */
		.dcx2-acc{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);margin-bottom:14px;overflow:hidden}
		.dcx2-acc>summary{display:flex;align-items:center;gap:10px;padding:15px 18px;cursor:pointer;list-style:none;font-size:13.5px;user-select:none}
		.dcx2-acc>summary::-webkit-details-marker{display:none}
		.dcx2-acc>summary .hnt{font-size:11px;color:var(--mut);font-weight:600}
		.dcx2-acc>summary .ch{margin-right:auto;color:var(--mut);display:inline-flex;transition:transform .2s}
		.dcx2-acc[open]>summary .ch{transform:rotate(90deg)}
		.dcx2-acc>summary:hover{background:rgba(47,206,150,.05)}
		.dcx2-acc>.dcx2-acc-body{border-top:1px solid var(--line);padding:6px 20px 16px}
		.dcx2-sec{font-size:12.5px;font-weight:800;margin:14px 0 2px;padding-bottom:6px;border-bottom:1px dashed var(--line)}
		.dcx2-sec:before{content:"";display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--g1);margin-left:8px}

		/* ───────── لیست هشدارهای قیمت (داشبورد) ───────── */
		.dcx2-alerts{margin:0;padding:6px 16px 12px;list-style:none}
		.dcx2-alerts li{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px dashed var(--line)}
		.dcx2-alerts li:last-child{border-bottom:0}
		.dcx2-alerts li b{font-size:12.5px;display:block;font-weight:800}
		.dcx2-alerts small{color:var(--mut);font-size:11px}

		@media(max-width:1060px){.dcx2-kpis{grid-template-columns:repeat(2,1fr)}.dcx2-grid2{grid-template-columns:1fr}}
		@media(max-width:820px){.dcx2-shell{grid-template-columns:1fr}.dcx2-rail{position:static}
			.dcx2-nav{display:flex;flex-wrap:wrap}.dcx2-nav a.on:before{display:none}.dcx2-kpis{grid-template-columns:1fr 1fr}}
		@media(max-width:560px){.dcx2-kpis{grid-template-columns:1fr}}
		</style>
		<?php
	}

	/* ==================================================================
	 * آیکون‌های SVG خطی
	 * ================================================================== */

	public static function icon( $n, $size = 17 ) {
		$icons = array(
			'mark'   => '<path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/>',
			'chart'  => '<path d="M3 17l5-5 4 4 8-9M14 7h6v6"/>',
			'box'    => '<path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/>',
			'edit'   => '<path d="M11 4H4v7l9 9 7-7-9-9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
			'wallet' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M16 15h2"/>',
			'coin'   => '<path d="M12 2v20M17 6H9.5a2.5 2.5 0 0 0 0 5h5a2.5 2.5 0 0 1 0 5H6"/>',
			'rma'    => '<path d="M9 14l-5-4 5-4M4 10h9a6 6 0 0 1 0 12v-2"/>',
			'ticket' => '<path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
			'gear'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.9 2.9l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.9-2.9l.1.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.9-2.9l.1.1a1.7 1.7 0 0 0 1.9.3h0a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5h0a1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.9 2.9l-.1.1a1.7 1.7 0 0 0-.3 1.9v0a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
			'plus'   => '<path d="M12 5v14M5 12h14"/>',
			'sync'   => '<path d="M21 2l-2 2A9 9 0 1 0 21 13M21 2v6h-6"/>',
			'link'   => '<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
			'price'  => '<path d="M20.59 13.41 11.41 4.23A2 2 0 0 0 10.08 4H4a2 2 0 0 0-2 2v6.08a2 2 0 0 0 .59 1.42l9.17 9.17a2 2 0 0 0 2.83 0l5.99-5.99a2 2 0 0 0 0-2.83z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
			'page'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
			'chev'   => '<polyline points="9 6 15 12 9 18"/>',
			'eye'    => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
			'send'   => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
		);
		$p = $icons[ $n ] ?? $icons['box'];
		return '<svg viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
	}

	/* ==================================================================
	 * کامپوننت‌ها
	 * ================================================================== */

	/** پیل وضعیت */
	public static function pill( $text, $tone = 'g' ) {
		return '<span class="dcx2-pill dcx2-pill--' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
	}

	/** تبدیل وضعیت سفارش به پیل مناسب (ترکیبی/تعلیق دست‌به‌خصوص) */
	public static function order_status_pill( WC_Order $order ) {
		if ( DastyarC_Orders::is_link_broken( $order ) ) {
			return self::pill( 'تعلیق شده', 'r' );
		}
		$slug = $order->get_status();
		if ( $order->get_meta( '_dastyarc_mixed' ) ) {
			return self::pill( 'سفارش ترکیبی', 'y' );
		}
		$map = array(
			'processing'      => 'b',
			'dastyar-sent'    => 'b',
			'on-hold'         => 'y',
			'posted'          => 'y',
			'completed'       => 'g',
			'cancelled'       => 'r',
			'refunded'        => 'r',
			'failed'          => 'r',
			'pending'         => 'x',
		);
		$tone = $map[ $slug ] ?? 'x';
		return self::pill( wc_get_order_status_name( $slug ), $tone );
	}

	/** پیل‌های کوچک وضعیت دو «بخش» سفارش ترکیبی (دستیار/فروشنده) */
	public static function order_parts_pills( WC_Order $order ) {
		if ( ! $order->get_meta( '_dastyarc_mixed' ) ) {
			return '';
		}
		$pc = (string) ( $order->get_meta( '_dastyarc_part_center' ) ?: 'processing' );
		$pv = (string) ( $order->get_meta( '_dastyarc_part_vendor' ) ?: 'pending' );
		$tones = array( 'completed' => 'g', 'posted' => 'y', 'processing' => 'b', 'pending' => 'x', 'cancelled' => 'r' );
		return '<span style="display:inline-flex;gap:4px;flex-wrap:wrap">'
			. self::pill( 'دستیار: ' . DastyarC_Orders::part_label( $pc ), $tones[ $pc ] ?? 'x' )
			. self::pill( 'فروشنده: ' . DastyarC_Orders::part_label( $pv ), $tones[ $pv ] ?? 'x' )
			. '</span>';
	}

	/** کارت باز */
	public static function card_open( $title, $note = '' ) {
		echo '<section class="dcx2-card"><header>' . esc_html( $title );
		if ( '' !== $note ) {
			echo '<span class="more">' . esc_html( $note ) . '</span>';
		}
		echo '</header>';
	}

	public static function card_close() {
		echo '</section>';
	}

	/** KPI پهن با خط sparkline اختیاری */
	public static function kpi( $label, $value_html, $tag = '', $tone = 'up', $spark = array(), $color = '#2fce96' ) {
		echo '<div class="dcx2-kpi"><i></i>'
			. '<span class="lb">' . esc_html( $label ) . '</span>'
			. '<div class="vl">' . wp_kses_post( $value_html );
		if ( '' !== $tag ) {
			echo ' <span class="tg dcx2-tg ' . esc_attr( $tone ) . '">' . esc_html( $tag ) . '</span>';
		}
		echo '</div>';
		if ( $spark ) {
			echo self::spark( $spark, $color );
		}
		echo '</div>';
	}

	/** مینی‌چارت خطی از آرایه شمارش‌ها (SVG) */
	public static function spark( array $counts, $color = '#2fce96', $w = 64, $h = 24 ) {
		if ( count( $counts ) < 2 ) {
			return '';
		}
		$max = max( array_merge( $counts, array( 1 ) ) );
		$min = 0;
		$n   = count( $counts );
		$pts = array();
		foreach ( $counts as $i => $c ) {
			$x = round( $i * ( $w - 2 ) / ( $n - 1 ), 1 ) + 1;
			$y = round( $h - 2 - ( ( (float) $c - $min ) / max( 1, $max - $min ) ) * ( $h - 6 ), 1 );
			$pts[] = $x . ',' . $y;
		}
		return '<svg class="spk" width="' . (int) $w . '" height="' . (int) $h . '" viewBox="0 0 ' . (int) $w . ' ' . (int) $h . '" aria-hidden="true">'
			. '<polyline points="' . esc_attr( implode( ' ', $pts ) ) . '" fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="2" stroke-linecap="round"/></svg>';
	}

	/** آیتم خالی */
	public static function empty_state( $text ) {
		echo '<div class="dcx2-card dcx2-empty">' . esc_html( $text ) . '</div>';
	}

	/** دکمه */
	public static function btn( $text, $href, $prime = false, $extra_class = '' ) {
		echo '<a class="dcx2-btn' . ( $prime ? ' prime' : '' ) . ( $extra_class ? ' ' . esc_attr( $extra_class ) : '' ) . '" href="' . esc_url( $href ) . '">' . wp_kses_post( $text ) . '</a>';
	}

	/** فید رویدادها */
	public static function feed( array $items ) {
		echo '<ul class="dcx2-feed">';
		foreach ( $items as $it ) {
			printf(
				'<li><span class="dt %s"></span><span>%s</span><span class="t">%s</span></li>',
				esc_attr( 'dt--' . (string) ( $it['lvl'] ?? 'info' ) ),
				esc_html( (string) ( $it['text'] ?? '' ) ),
				esc_html( (string) ( $it['time'] ?? '' ) )
			);
		}
		echo '</ul>';
	}
}
