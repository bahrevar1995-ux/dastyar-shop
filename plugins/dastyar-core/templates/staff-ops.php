<?php
/**
 * پوسته HTML مستقل و تمام‌صفحه‌ی «پنل عملیات دستیار» — نسخه بازطراحی «گلس مدرن» (v2).
 *
 * عمداً get_header()/get_footer() قالب صدا زده نمی‌شود — این صفحه باید کاملاً
 * مستقل از فضای محتوای معمولی سایت (و منوی آن) باشد؛ فقط wp_head/wp_footer برای
 * سازگاری با افزونه‌های دیگر (آنالیتیکس، پیکسل و غیره) حفظ شده‌اند.
 *
 * بازطراحی (درخواست کاربر):
 *  - ظاهر «گلس» (شیشه‌ای) با پس‌زمینه گرادیان نرم برند + سایدبار شناور گلس
 *  - KPI شیشه‌ای با آیکون‌کاشی رنگی + بج روند ▲▼ (محاسبه واقعی امروز/دیروز)
 *  - کارت رشد هفتگی گرادیان با مینی‌بارچارت ۷ روز واقعی
 *  - ریسپانسیو کامل: بریک‌پوینت‌های ۱۲۰۰/۱۰۲۴/۹۰۰/۴۸۰ — در موبایل سایدبار به
 *    دراور کناری (Drawer + Scrim + قفل اسکرول) تبدیل و تاپ‌بار اپلیکیشنی ظاهر می‌شود
 *  - حفظ کامل حالت تاریک (localStorage) + سازگاری با ۵۰ ماژول کنسول (کلاس‌های da-*)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<?php wp_head(); ?>
<script>(function(){try{if('dark'===localStorage.getItem('dastyar-ops-theme')){document.documentElement.classList.add('dop-dark');}}catch(e){}})();
function dopToggleDark(){
	var el = document.documentElement;
	var dark = el.classList.toggle('dop-dark');
	try{ localStorage.setItem('dastyar-ops-theme', dark ? 'dark' : 'light'); }catch(e){}
}
/* دراور موبایل: باز/بسته + اسکریم + قفل اسکرول صفحه */
function dopToggleNav(open){
	var s = document.getElementById('dop-side'), m = document.getElementById('dop-scrim');
	if(!s || !m){ return; }
	s.classList.toggle('open', !!open);
	m.classList.toggle('open', !!open);
	document.documentElement.classList.toggle('dop-noscroll', !!open);
}
</script>
<style>
	html,body{margin:0;padding:0}
	.dop-auth,.dop-shell,.dop-appbar,.dop-scrim,.dop-auth *,.dop-shell *,.dop-appbar *{box-sizing:border-box;font-family:"Vazirmatn","IRANSans",Tahoma,sans-serif}
	body{direction:rtl}

	/* ─── متغیرهای برند: سبز/سرمه‌ای دستیار ─── */
	:root{
		--dop-green:#17a16d; --dop-green-d:#12875c; --dop-mint:#2fce96; --dop-navy:#242536;
		--dop-line:#e6eaf0; --dop-bg:#f4f6f8; --dop-text:#24242f; --dop-muted:#7b8290;
		--dop-card:rgba(255,255,255,.82); --dop-card-br:rgba(255,255,255,.92);
		--dop-solid:#ffffff; --dop-shadow:0 8px 24px rgba(36,37,54,.06);
		--dop-th:#f7f9fa;
	}

	/* پس‌زمینه گرادیان نرم برند */
	body{
		min-height:100vh;color:var(--dop-text);
		background:
			radial-gradient(900px 500px at 85% -5%, rgba(47,206,150,.14), transparent 60%),
			radial-gradient(700px 460px at -10% 30%, rgba(90,110,255,.08), transparent 55%),
			radial-gradient(600px 500px at 60% 110%, rgba(23,161,109,.08), transparent 55%),
			var(--dop-bg);
		background-attachment:fixed;padding:18px;
	}
	html.dop-noscroll{overflow:hidden}

	.dop-logo{font-size:16px;font-weight:900;color:var(--dop-navy);display:flex;align-items:center;gap:10px;line-height:1.2}
	.dop-logo span{color:var(--dop-green)}
	.dop-mark{width:36px;height:36px;border-radius:12px;background:linear-gradient(135deg,var(--dop-green),var(--dop-mint));display:inline-flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 6px 14px rgba(23,161,109,.35);flex:none}
	.dop-mark svg{width:18px;height:18px}
	.dop-ltext{display:flex;flex-direction:column;font-size:15px;font-weight:900;color:var(--dop-navy)}
	.dop-ltext span{color:var(--dop-green)}
	.dop-ltext small{font-weight:400;font-size:10px;color:var(--dop-muted)}

	/* ═══════════ ورود ═══════════ */
	.dop-auth{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
	.dop-auth-box{background:var(--dop-card);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid var(--dop-card-br);border-radius:22px;padding:34px 30px;max-width:370px;width:100%;box-shadow:0 24px 60px rgba(36,37,54,.13);text-align:center}
	.dop-auth-box .dop-logo{justify-content:center}
	.dop-auth-sub{color:var(--dop-muted);font-size:12.5px;margin:8px 0 20px}
	.dop-auth-box form{text-align:right}
	.dop-auth-box input{display:block;width:100%;border:1.5px solid #d6e6dc;border-radius:11px;padding:11px 14px;font-family:inherit;font-size:13.5px;margin-bottom:10px;background:#fbfdfc;transition:.2s}
	.dop-auth-box input:focus{outline:none;border-color:var(--dop-green);box-shadow:0 0 0 3px rgba(23,161,109,.14)}
	.dop-auth-box button{width:100%;background:linear-gradient(135deg,var(--dop-green),#21b67e);border:0;color:#fff;border-radius:11px;padding:12px;font-size:14px;font-weight:800;cursor:pointer;margin-top:6px;box-shadow:0 8px 18px rgba(23,161,109,.28)}
	.dop-auth-box button:hover{background:linear-gradient(135deg,var(--dop-green-d),var(--dop-green))}
	.dop-err{background:#fdecec;border:1px solid #f3caca;color:#a52828;border-radius:10px;padding:9px 12px;font-size:12.5px;margin-bottom:12px}
	.dop-btn.ghost{display:inline-block;background:var(--dop-card);border:1.5px solid var(--dop-line);color:var(--dop-navy)!important;border-radius:10px;padding:8px 18px;font-size:12.5px;font-weight:700;text-decoration:none}

	/* ═══════════ چیدمان اصلی ═══════════ */
	.dop-shell{display:flex;gap:18px;align-items:flex-start;max-width:1440px;margin:0 auto}

	/* سایدبار شناور گلس */
	.dop-side{flex:0 0 250px;position:sticky;top:18px;background:var(--dop-card);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid var(--dop-card-br);border-radius:22px;padding:18px 14px;box-shadow:0 14px 40px rgba(36,37,54,.08)}
	.dop-side-head{display:flex;align-items:center;justify-content:space-between;padding:2px 6px 13px;border-bottom:1px solid var(--dop-line);margin-bottom:12px}
	.dop-x{display:none;background:var(--dop-bg);border:1px solid var(--dop-line);color:var(--dop-text);width:32px;height:32px;border-radius:9px;cursor:pointer;align-items:center;justify-content:center}
	.dop-tag{font-size:10.5px;color:var(--dop-muted);margin:10px 10px 4px;font-weight:700}
	.dop-nav{display:flex;flex-direction:column;gap:3px}
	.dop-glabel{font-size:10px;font-weight:800;color:#a6acb6;margin:14px 12px 4px}
	.dop-glabel:first-child{margin-top:0}
	.dop-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;color:#545a66;text-decoration:none;font-size:13px;font-weight:700;transition:.18s}
	.dop-link svg{width:17px;height:17px;opacity:.7}
	.dop-link.sub{font-size:12px;font-weight:600;padding-inline-start:22px}
	.dop-link:hover{background:rgba(23,161,109,.07);color:var(--dop-green-d)}
	.dop-link.on{background:linear-gradient(135deg,var(--dop-green),#21b67e);color:#fff;box-shadow:0 8px 18px rgba(23,161,109,.3)}
	.dop-link.on svg{opacity:1}

	/* تاپ‌بار اپلیکیشنی موبایل (فقط ≤900px) */
	.dop-appbar{display:none}
	.dop-burger{background:transparent;border:0;color:var(--dop-text);width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
	.dop-burger svg{width:20px;height:20px}
	.dop-app-brand{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:900;color:var(--dop-navy)}
	.dop-app-brand span{color:var(--dop-green)}
	.dop-app-brand svg{width:18px;height:18px;color:var(--dop-green)}
	.dop-scrim{position:fixed;inset:0;background:rgba(20,22,40,.45);z-index:55;opacity:0;pointer-events:none;transition:opacity .25s}
	.dop-scrim.open{opacity:1;pointer-events:auto}

	/* تاپ‌بار محتوا (دارک+یوزرچیپ) */
	.dop-topbar{display:flex;justify-content:flex-end;align-items:center;gap:10px;margin-bottom:16px}
	.dop-userchip{display:flex;align-items:center;gap:9px;background:var(--dop-card);backdrop-filter:blur(8px);border:1px solid var(--dop-card-br);border-radius:999px;padding:5px 14px 5px 12px;box-shadow:var(--dop-shadow)}
	.dop-uav{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--dop-green),var(--dop-mint));color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:900;flex:none}
	.dop-uinfo{display:flex;flex-direction:column;line-height:1.35}
	.dop-uinfo b{font-size:12.5px;color:var(--dop-text)}
	.dop-logout-link{color:var(--dop-muted);text-decoration:none;font-size:10px}
	.dop-logout-link:hover{color:#a52828}

	/* سلام‌و‌درود داشبورد */
	.dop-hello{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin:2px 0 18px;flex-wrap:wrap}
	.dop-hello h2{margin:0;font-size:21px;font-weight:900;color:var(--dop-text)}
	.dop-hello p{margin:3px 0 0;font-size:12.5px;color:var(--dop-muted)}

	.dop-main{flex:1;min-width:0;padding:4px 2px 40px}
	.dop-title{font-size:20px;font-weight:900;color:var(--dop-text);margin:0 0 16px}

	/* ═══════════ KPI گلس + آیکون‌کاشی + بج روند ═══════════ */
	.dop-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px}
	.dop-kpi{background:var(--dop-card);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid var(--dop-card-br);border-radius:18px;padding:16px;display:flex;flex-direction:column;gap:9px;box-shadow:var(--dop-shadow)}
	.dop-kpi-top{display:flex;align-items:center;justify-content:space-between;gap:8px}
	.dop-kpi-ic{width:40px;height:40px;border-radius:13px;display:inline-flex;align-items:center;justify-content:center;flex:none}
	.dop-kpi-ic svg{width:19px;height:19px}
	.kpi-blue .dop-kpi-ic{background:#e7f0fd;color:#1d4ed8}
	.kpi-green .dop-kpi-ic{background:#e5f5ee;color:#12875c}
	.kpi-purple .dop-kpi-ic{background:#f1e9fb;color:#6d28d9}
	.kpi-amber .dop-kpi-ic{background:#fdf3e2;color:#b4690e}
	.dop-trd{font-size:10.5px;font-weight:800;border-radius:999px;padding:3px 9px;white-space:nowrap}
	.dop-trd.up{background:#e5f5ee;color:#0f7a52}
	.dop-trd.down{background:#fdecec;color:#b02a2a}
	.dop-kpi b{display:block;font-size:23px;font-weight:900;line-height:1.25;font-variant-numeric:tabular-nums;word-break:break-word}
	.dop-kpi>span{font-size:12px;color:var(--dop-muted);font-weight:700}

	/* ═══════════ هیروی رشد هفتگی ═══════════ */
	.dop-growth{display:flex;align-items:center;justify-content:space-between;gap:18px;background:linear-gradient(135deg,var(--dop-green),var(--dop-mint));border-radius:20px;padding:20px 24px;color:#fff;margin-bottom:16px;box-shadow:0 14px 32px rgba(23,161,109,.28)}
	.dop-growth-info{display:flex;align-items:center;gap:14px;min-width:0}
	.dop-growth-ic{width:48px;height:48px;border-radius:14px;background:rgba(255,255,255,.18);display:inline-flex;align-items:center;justify-content:center;flex:none}
	.dop-growth-ic svg{width:22px;height:22px}
	.dop-growth b{display:block;font-size:28px;font-weight:900;line-height:1.1}
	.dop-growth p{margin:3px 0 0;font-size:12px;opacity:.92}
	.dop-growth.down{background:linear-gradient(135deg,#b0433c,#e07a70);box-shadow:0 14px 32px rgba(176,67,60,.25)}
	.dop-growth-bars{display:flex;align-items:flex-end;gap:5px;height:52px;flex:none}
	.dop-growth-bars i{width:9px;border-radius:5px 5px 2px 2px;background:rgba(255,255,255,.38);display:block}
	.dop-growth-bars i.max{background:#fff}

	.dop-alert{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#fff8e8;border:1px solid #f2dfae;color:#8a6a00;border-radius:14px;padding:11px 16px;font-size:12.5px;font-weight:700;margin-bottom:12px}
	.dop-alert.warn{background:#fdeeee;border-color:#f3c6c6;color:#a52828}
	.dop-alert a,.dop-alert .dop-btn.sm{flex:none}

	.dop-notice{border-radius:12px;padding:10px 16px;margin-bottom:16px;font-size:13px;font-weight:700}
	.dop-notice.ok{background:#ecf9f2;border:1px solid #c6e9d8;color:#0f5132}
	.dop-notice.err{background:#fdf0f0;border:1px solid #f0c8c8;color:#a52828}

	.dop-filterbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
	.dop-filterbar input[type=text]{flex:1;min-width:220px;border:1.5px solid var(--dop-line);border-radius:10px;padding:9px 12px;font-family:inherit;font-size:13px;background:var(--dop-card)}
	.dop-filterbar input:focus,.dop-filterbar select:focus{outline:none;border-color:var(--dop-green)!important;box-shadow:0 0 0 3px rgba(23,161,109,.12)}
	.dop-filterbar select{border:1.5px solid var(--dop-line);border-radius:10px;padding:9px 12px;font-family:inherit;font-size:13px;background:var(--dop-card);color:var(--dop-text)}
	.dop-toolbar{margin:0 0 12px}
	.dop-grid2{display:grid;grid-template-columns:1.3fr 1fr;gap:14px;align-items:start;margin-bottom:14px}
	@media (max-width:1100px){.dop-grid2{grid-template-columns:1fr}}

	.dop-collapse{background:var(--dop-card);border:1px solid var(--dop-card-br);border-radius:14px;margin-bottom:16px}
	.dop-collapse summary{cursor:pointer;padding:12px 16px;font-size:13px;font-weight:700;color:var(--dop-text);list-style:none}
	.dop-collapse summary::-webkit-details-marker{display:none}
	.dop-collapse-b{padding:0 16px 16px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start}
	.dop-collapse-b textarea{flex:1;min-width:260px;border:1.5px solid var(--dop-line);border-radius:10px;padding:9px 12px;font-family:inherit;font-size:12.5px;direction:ltr;text-align:left;background:var(--dop-solid);color:var(--dop-text)}

	/* ═══════════ جدول گلس ═══════════ */
	.dop-table-wrap{background:var(--dop-card);border:1px solid var(--dop-card-br);border-radius:16px;overflow:auto;box-shadow:var(--dop-shadow)}
	.dop-table{width:100%;border-collapse:collapse;font-size:12.5px;white-space:nowrap}
	.dop-table th,.dop-table td{padding:11px 14px;border-bottom:1px solid var(--dop-line);text-align:right}
	.dop-table th{background:var(--dop-th);color:var(--dop-muted);font-weight:800;position:sticky;top:0;font-size:11.5px}
	.dop-table tr:hover td{background:rgba(23,161,109,.03)}
	.dop-empty{color:var(--dop-muted);white-space:normal;padding:16px}
	.dop-muted{color:var(--dop-muted)}

	.dop-inline-f{display:inline-flex;gap:6px;align-items:center}
	.dop-inline-f select,.dop-inline-f input[type=text],.dop-inline-f input[type=number]{border:1.5px solid var(--dop-line);border-radius:8px;padding:5px 8px;font-family:inherit;font-size:12px;background:var(--dop-solid);color:var(--dop-text)}
	.dop-mini{background:var(--dop-green);border:1px solid var(--dop-green-d);color:#fff;border-radius:8px;padding:5px 12px;font-size:11.5px;font-weight:700;cursor:pointer}
	.dop-mini:hover{background:var(--dop-green-d)}

	.dop-btn{display:inline-block;background:linear-gradient(135deg,var(--dop-green),#21b67e);border:0;color:#fff!important;border-radius:10px;padding:9px 18px;font-size:12.5px;font-weight:800;cursor:pointer;text-decoration:none!important;box-shadow:0 6px 14px rgba(23,161,109,.22)}
	.dop-btn:hover{background:var(--dop-green-d)}
	.dop-btn.sm{padding:7px 14px;font-size:11.5px;white-space:nowrap}

	.dop-tabs{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap}
	.dop-tab{padding:7px 16px;border-radius:10px;background:var(--dop-card);border:1.5px solid var(--dop-line);color:var(--dop-text);text-decoration:none;font-size:12.5px;font-weight:700}
	.dop-tab.on{background:linear-gradient(135deg,var(--dop-green),#21b67e);border-color:transparent;color:#fff;box-shadow:0 6px 14px rgba(23,161,109,.22)}

	.dop-pill{display:inline-block;border-radius:999px;padding:3px 11px;font-size:11px;font-weight:800}
	.dop-pill.pending{background:#fdf3e2;color:#b4690e}
	.dop-pill.approved{background:#e5f5ee;color:#0f7a52}
	.dop-pill.rejected{background:#fdecec;color:#b02a2a}
	.dop-actions{display:flex;gap:6px;flex-wrap:wrap}

	.dop-status-pill{display:inline-block;border-radius:999px;padding:4px 12px;font-size:11px;font-weight:800;white-space:nowrap}
	.dop-status-pill.st-gray{background:#f1f3f2;color:#5b5e6e}
	.dop-status-pill.st-amber{background:#fdf3e2;color:#b4690e}
	.dop-status-pill.st-blue{background:#e7f0fd;color:#1d4ed8}
	.dop-status-pill.st-purple{background:#f1e9fb;color:#6d28d9}
	.dop-status-pill.st-green{background:#e5f5ee;color:#0f7a52}
	.dop-status-pill.st-red{background:#fdecec;color:#b02a2a}

	.dop-stacc summary{cursor:pointer;list-style:none}
	.dop-stacc summary::-webkit-details-marker{display:none}
	.dop-stacc[open] summary{margin-bottom:6px}
	.dop-stacc .dop-inline-f{display:flex;gap:6px}

	.dop-track-badge{border:0;border-radius:999px;padding:5px 12px;font-size:11px;font-weight:800;cursor:pointer;direction:ltr;font-family:inherit}
	.dop-track-badge.unset{background:#fdf3e2;color:#b4690e}
	.dop-track-badge.unset:hover{background:#fbe9c8}
	.dop-track-badge.set{background:#e5f5ee;color:#0f7a52}
	.dop-track-badge.set:hover{background:#d5efe3}

	/* پاپ‌آپ */
	.dop-modal{position:fixed;inset:0;z-index:200;display:none;align-items:center;justify-content:center}
	.dop-modal.open{display:flex}
	.dop-modal-bg{position:absolute;inset:0;background:rgba(20,22,40,.5);backdrop-filter:blur(3px)}
	.dop-modal-box{position:relative;background:var(--dop-solid);border-radius:18px;padding:24px 22px;max-width:340px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,.25)}
	.dop-modal-box h3{margin:0 0 14px;font-size:15px;color:var(--dop-text)}
	.dop-modal-x{position:absolute;top:10px;left:12px;background:var(--dop-bg);border:0;border-radius:50%;width:28px;height:28px;cursor:pointer;color:#888}
	.dop-modal-box input[type=text]{width:100%;border:1.5px solid var(--dop-line);border-radius:10px;padding:10px 12px;font-family:inherit;font-size:13.5px;margin-bottom:12px;box-sizing:border-box;background:var(--dop-solid);color:var(--dop-text)}
	.dop-modal-box .dop-btn{width:100%}

	.dop-thumbs{display:flex;align-items:center;gap:4px}
	.dop-thumb img{width:30px;height:30px;object-fit:cover;border-radius:7px;border:1px solid var(--dop-line);display:block}
	.dop-thumb-more{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;background:var(--dop-bg);border:1px solid var(--dop-line);font-size:10.5px;font-weight:800;color:var(--dop-muted)}

	/* نمودار ۱۴ روز */
	.dop-chart-card .da-card-b{padding-top:16px}
	.dop-chart{overflow:visible;display:block}
	.dop-chart-axis{stroke:var(--dop-line);stroke-width:1}
	.dop-chart-line{stroke:var(--dop-green);stroke-width:2.5}
	.dop-chart-dot{fill:#fff;stroke:var(--dop-green);stroke-width:2.5}
	.dop-chart-val{fill:var(--dop-navy);font-size:9px;font-weight:800;font-family:"Vazirmatn",Tahoma,sans-serif}
	.dop-chart-lbl{fill:var(--dop-muted);font-size:8.5px;font-family:"Vazirmatn",Tahoma,sans-serif}

	/* آخرین فروشنده‌ها */
	.dop-vlist{display:flex;flex-direction:column;gap:2px}
	.dop-vrow{display:flex;align-items:center;gap:12px;padding:10px 4px;border-bottom:1px solid var(--dop-line)}
	.dop-vrow:last-child{border-bottom:0}
	.dop-vav{width:36px;height:36px;border-radius:50%;background:#eef7f2;color:var(--dop-green-d);display:inline-flex;align-items:center;justify-content:center;font-weight:900;font-size:13.5px;flex:none}
	.dop-vinfo{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
	.dop-vinfo b{font-size:13px;color:var(--dop-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
	.dop-vinfo span{font-size:10.5px;color:var(--dop-muted)}
	.dop-vbal{font-size:12.5px;font-weight:800;color:var(--dop-green-d);white-space:nowrap}

	/* پرفروش‌ترین‌ها */
	.dop-plist{display:flex;flex-direction:column;gap:10px}
	.dop-prow{display:flex;align-items:center;gap:12px;background:rgba(23,161,109,.04);border:1px solid var(--dop-line);border-radius:14px;padding:10px 14px}
	.dop-pimg{width:44px;height:44px;border-radius:11px;overflow:hidden;flex:none;display:flex;align-items:center;justify-content:center;background:#fff;color:var(--dop-muted)}
	.dop-pimg img{width:100%;height:100%;object-fit:cover;display:block}
	.dop-pinfo{flex:1;min-width:0}
	.dop-pinfo b{display:block;font-size:12.5px;color:var(--dop-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:6px}
	.dop-pbar{height:5px;border-radius:6px;background:var(--dop-line);overflow:hidden}
	.dop-pbar span{display:block;height:100%;background:linear-gradient(90deg,var(--dop-green),var(--dop-mint));border-radius:6px}
	.dop-pstat{text-align:left;flex:none}
	.dop-pstat b{display:block;font-size:12.5px;color:var(--dop-green-d)}
	.dop-pstat span{display:block;font-size:10.5px;color:var(--dop-muted);margin-top:2px}

	/* ═══════════ سازگاری با خروجی ماژول‌های کنسول (da-*) ═══════════ */
	.da-wrap,.da-p-main{max-width:100%}
	.da-page-title{display:flex;align-items:center;gap:10px;color:var(--dop-text);font-size:20px;margin:0 0 16px}
	.da-logo-dot{width:10px;height:10px;border-radius:50%;background:var(--dop-green);display:inline-block}
	.da-card{background:var(--dop-card);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border:1px solid var(--dop-card-br);border-radius:18px;margin:0 0 16px;box-shadow:var(--dop-shadow);overflow:hidden}
	.da-card-h{padding:13px 18px;background:linear-gradient(135deg,rgba(23,161,109,.07),rgba(47,206,150,.04));border-bottom:1px solid var(--dop-line)}
	.da-card-h h3{margin:0;color:var(--dop-text);font-size:15px;display:flex;align-items:center;gap:8px}
	.da-card-h p{margin:4px 0 0;color:var(--dop-muted);font-size:12px}
	.da-modid{font-size:10px;background:var(--dop-green);color:#fff;border-radius:8px;padding:2px 7px;font-weight:700}
	.da-card-b{padding:16px 18px;overflow-x:auto}
	.da-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}
	.da-kpi{background:linear-gradient(180deg,rgba(23,161,109,.05),rgba(47,206,150,.03));border:1px solid var(--dop-line);border-radius:14px;padding:14px;text-align:center}
	.da-kpi.red{background:linear-gradient(180deg,rgba(220,60,60,.06),rgba(220,60,60,.02));border-color:#f0dcdc}
	.da-kpi.dark{background:linear-gradient(180deg,#242536,#2f3046);border-color:#242536}
	.da-kpi.dark .da-kpi-v,.da-kpi.dark .da-kpi-l{color:#fff}
	.da-kpi.dark .da-kpi-s{color:#a9b2c9}
	.da-kpi-v{display:block;font-size:22px;font-weight:900;color:var(--dop-green-d)}
	.da-kpi-l{display:block;font-size:11.5px;color:#6f857b;margin-top:4px}
	.da-kpi-s{display:block;font-size:10.5px;color:var(--dop-muted);margin-top:2px}
	.da-table{width:100%;border-collapse:collapse;font-size:12.5px;background:transparent}
	.da-table th,.da-table td{padding:9px 10px;border-bottom:1px solid var(--dop-line);text-align:right}
	.da-table th{background:var(--dop-th);color:var(--dop-muted);font-weight:800;font-size:11.5px}
	.da-empty{color:var(--dop-muted);font-size:12.5px;white-space:normal;padding:10px 4px}
	.da-pill{display:inline-block;border-radius:999px;padding:2px 10px;font-size:11px;font-weight:800}
	.da-pill-green{background:#e5f5ee;color:#0f7a52}
	.da-pill-red{background:#fdecec;color:#b02a2a}
	.da-pill-gray{background:#f1f3f2;color:#5b5e6e}
	.da-btn{display:inline-block;background:linear-gradient(135deg,var(--dop-green),#21b67e);border:0;color:#fff!important;border-radius:10px;padding:8px 16px;font-size:12.5px;font-weight:800;cursor:pointer;text-decoration:none!important}
	.da-btn:hover{background:var(--dop-green-d)}
	.da-btn.gray{background:#f1f3f2;border:1px solid var(--dop-line);color:var(--dop-navy)!important}
	.da-btn.red{background:#fdecec;border:1px solid #f3caca;color:#a52828!important}
	.notice{border-radius:10px;padding:9px 14px;margin:0 0 14px;font-size:12.5px;border:1px solid}
	.notice-success{background:#ecf9f2;border-color:#c6e9d8;color:#0f5132}
	.notice-error{background:#fdf0f0;border-color:#f0c8c8;color:#a52828}
	.notice-warning{background:#fef8e8;border-color:#f0e0a8;color:#7a5b00}

	/* دکمه سوییچ تاریک */
	.dop-darktoggle{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:12px;background:var(--dop-card);border:1px solid var(--dop-card-br);color:var(--dop-text);cursor:pointer;flex:none;box-shadow:var(--dop-shadow)}
	.dop-darktoggle svg{width:16px;height:16px}
	.dop-auth .dop-darktoggle{position:fixed;top:16px;left:16px}

	/* ═══════════ حالت تاریک ═══════════ */
	html.dop-dark{
		--dop-line:#262b4d; --dop-bg:#0d1020; --dop-text:#e8ebf5; --dop-muted:#8a90b0;
		--dop-card:rgba(22,26,48,.82); --dop-card-br:rgba(42,47,84,.6);
		--dop-solid:#161a30; --dop-shadow:0 10px 30px rgba(0,0,0,.35); --dop-th:#1c2038;
	}
	html.dop-dark body{
		background:
			radial-gradient(900px 500px at 85% -5%, rgba(47,206,150,.08), transparent 60%),
			radial-gradient(700px 460px at -10% 30%, rgba(90,110,255,.06), transparent 55%),
			#0d1020;
		background-attachment:fixed;color:var(--dop-text);
	}
	html.dop-dark .dop-logo,html.dop-dark .dop-ltext{color:#eceef8}
	html.dop-dark .dop-link{color:#b6bcd4}
	html.dop-dark .dop-link:hover{background:rgba(47,206,150,.09);color:#2fce96}
	html.dop-dark .dop-link.on{color:#0b2e1f}
	html.dop-dark .dop-glabel{color:#5c6284}
	html.dop-dark .dop-side-head{border-bottom-color:#262b4d}

	html.dop-dark .kpi-blue .dop-kpi-ic{background:rgba(138,180,248,.14);color:#8ab4f8}
	html.dop-dark .kpi-green .dop-kpi-ic{background:rgba(47,206,150,.14);color:#2fce96}
	html.dop-dark .kpi-purple .dop-kpi-ic{background:rgba(196,176,245,.14);color:#c4b0f5}
	html.dop-dark .kpi-amber .dop-kpi-ic{background:rgba(255,212,138,.14);color:#ffd48a}
	html.dop-dark .dop-trd.up{background:rgba(47,206,150,.13);color:#2fce96}
	html.dop-dark .dop-trd.down{background:rgba(255,150,150,.13);color:#ff9494}

	html.dop-dark body,html.dop-dark .dop-main,html.dop-dark .da-card-b,html.dop-dark .da-card-b *{color:#dfe3f6}
	html.dop-dark .da-card-b .da-empty,html.dop-dark .da-card-b .dop-muted{color:#8288a8!important}
	html.dop-dark .da-card-b svg line,html.dop-dark .da-card-b svg path[stroke]{stroke:#3a3e63}
	html.dop-dark .da-card-b svg text{fill:#9aa3c4}
	html.dop-dark .da-card-b svg rect{fill:#2fce96}
	html.dop-dark .dop-chart-line{stroke:#2fce96}
	html.dop-dark .dop-chart-dot{fill:#161a30;stroke:#2fce96}
	html.dop-dark .dop-chart-val{fill:#eceef8}
	html.dop-dark .dop-chart-lbl{fill:#8288a8}
	html.dop-dark .dop-chart-axis{stroke:#2e3252}
	html.dop-dark .dop-vrow{border-bottom-color:#262b4d}
	html.dop-dark .dop-vav{background:#1c2038;color:#2fce96}
	html.dop-dark .dop-vinfo b{color:#eceef8}
	html.dop-dark .dop-vbal{color:#2fce96}
	html.dop-dark .dop-prow{background:rgba(47,206,150,.05);border-color:#262b4d}
	html.dop-dark .dop-pimg{background:#1c2038;color:#8288a8}
	html.dop-dark .dop-pinfo b{color:#eceef8}
	html.dop-dark .dop-pbar{background:#2e3252}
	html.dop-dark .dop-pstat b{color:#2fce96}

	html.dop-dark .dop-auth-box{background:rgba(22,26,48,.85);border-color:rgba(42,47,84,.6)}
	html.dop-dark .dop-auth-box input{background:#0d1020;border-color:#2e3252;color:#dfe3f6}
	html.dop-dark .dop-err{background:#3a1f1f;border-color:#5a2b2b;color:#f5a3a3}

	html.dop-dark .dop-alert{background:rgba(255,212,138,.07);border-color:rgba(255,212,138,.3);color:#ffd48a}
	html.dop-dark .dop-alert.warn{background:rgba(255,123,123,.07);border-color:rgba(255,123,123,.3);color:#ff9494}
	html.dop-dark .dop-notice.ok{background:#123024;border-color:#1f5a3f;color:#7ce8b0}
	html.dop-dark .dop-notice.err{background:#3a1f1f;border-color:#5a2b2b;color:#f5a3a3}
	html.dop-dark .dop-tab{background:rgba(22,26,48,.82);border-color:#2e3252;color:#dfe3f6}
	html.dop-dark .dop-tab.on{color:#0b2e1f}
	html.dop-dark .dop-pill.pending{background:rgba(255,212,138,.13);color:#ffd48a}
	html.dop-dark .dop-pill.approved{background:rgba(47,206,150,.13);color:#2fce96}
	html.dop-dark .dop-pill.rejected{background:rgba(255,150,150,.13);color:#ff9494}
	html.dop-dark .dop-status-pill.st-gray{background:#2e3252;color:#b6bcd4}
	html.dop-dark .dop-status-pill.st-amber{background:rgba(255,212,138,.13);color:#ffd48a}
	html.dop-dark .dop-status-pill.st-blue{background:rgba(138,180,248,.13);color:#8ab4f8}
	html.dop-dark .dop-status-pill.st-purple{background:rgba(196,176,245,.13);color:#c4b0f5}
	html.dop-dark .dop-status-pill.st-green{background:rgba(47,206,150,.13);color:#2fce96}
	html.dop-dark .dop-status-pill.st-red{background:rgba(255,150,150,.13);color:#ff9494}
	html.dop-dark .dop-track-badge.unset{background:rgba(255,212,138,.13);color:#ffd48a}
	html.dop-dark .dop-track-badge.set{background:rgba(47,206,150,.13);color:#2fce96}
	html.dop-dark .dop-thumb-more{background:#1c2038;border-color:#2e3252;color:#9aa3c4}
	html.dop-dark .dop-modal-box{background:#161a30;border:1px solid #2e3252}
	html.dop-dark .dop-modal-x{background:#2e3252;color:#dfe3f6}

	html.dop-dark .da-kpi{background:linear-gradient(180deg,rgba(47,206,150,.07),rgba(47,206,150,.03));border-color:#262b4d}
	html.dop-dark .da-kpi.red{background:linear-gradient(180deg,rgba(255,123,123,.08),rgba(255,123,123,.03));border-color:#4a2b2b}
	html.dop-dark .da-kpi.dark{background:linear-gradient(180deg,#0b0d18,#12142a);border-color:#2e3252}
	html.dop-dark .da-kpi-v{color:#2fce96}
	html.dop-dark .da-kpi-l{color:#9aa3c4}
	html.dop-dark .da-btn.gray{background:#2e3252;border-color:#3a3e63;color:#dfe3f6!important}
	html.dop-dark .notice-success{background:#123024;border-color:#1f5a3f;color:#7ce8b0}
	html.dop-dark .notice-error{background:#3a1f1f;border-color:#5a2b2b;color:#f5a3a3}
	html.dop-dark .notice-warning{background:#3a2f14;border-color:#5a4a1f;color:#f0d590}

	/* ═══════════ ریسپانسیو ═══════════ */
	/* ≤1200: فشرده‌سازی ملایم */
	@media (max-width:1200px){
		.dop-side{flex:0 0 232px}
		.dop-kpi b{font-size:20px}
	}
	/* ≤1024: KPI دو ستونه + تک‌ستونه شدن گریدها */
	@media (max-width:1024px){
		.dop-kpis{grid-template-columns:repeat(2,1fr);gap:10px}
		.dop-grid2{grid-template-columns:1fr}
	}
	/* ≤900: حالت اپ — دراور + تاپ‌بار */
	@media (max-width:900px){
		body{padding:0}
		.dop-appbar{display:flex;position:sticky;top:0;z-index:40;align-items:center;gap:8px;padding:9px 12px;background:var(--dop-card);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid var(--dop-line)}
		.dop-shell{display:block;padding:0}
		.dop-side{position:fixed;top:0;right:0;bottom:0;left:auto;width:min(300px,84vw);height:100dvh;z-index:60;border-radius:0;border-left:0;border-top:0;border-bottom:0;transform:translateX(105%);transition:transform .28s ease;backdrop-filter:none;-webkit-backdrop-filter:none;background:var(--dop-solid);box-shadow:none;overflow-y:auto}
		.dop-side.open{transform:none;box-shadow:-24px 0 60px rgba(0,0,0,.22)}
		.dop-x{display:inline-flex}
		.dop-main{padding:14px 14px 84px}
		.dop-topbar{margin-bottom:14px}
		.dop-hello h2{font-size:17px}
		.dop-hello p{font-size:11.5px}
		.dop-growth{flex-direction:column;align-items:flex-start;border-radius:16px;padding:16px 18px;gap:10px}
		.dop-growth-bars{display:none}
		.dop-growth b{font-size:24px}
		.dop-filterbar{flex-direction:column;align-items:stretch}
		.dop-filterbar input[type=text],.dop-filterbar select{width:100%;min-width:0}
		.dop-table{font-size:11.5px}
		.dop-table th,.dop-table td{padding:8px}
		.dop-inline-f{flex-wrap:wrap}
		.dop-inline-f select{max-width:110px}
		.dop-actions{flex-direction:column;align-items:stretch}
		.dop-actions form{width:100%}
		.dop-actions .dop-mini,.dop-actions .dop-btn{width:100%;text-align:center}
		.dop-modal-box{padding:20px 16px}
		.dop-title{font-size:17px}
	}
	/* ≤480: جمع‌وجورتر */
	@media (max-width:480px){
		.dop-kpis{gap:8px}
		.dop-kpi{padding:12px}
		.dop-kpi-ic{width:32px;height:32px;border-radius:10px}
		.dop-kpi-ic svg{width:15px;height:15px}
		.dop-kpi b{font-size:16px}
		.dop-kpi>span{font-size:10.5px}
		.dop-trd{font-size:9.5px;padding:2px 7px}
		.dop-alert{flex-direction:column;align-items:flex-start;gap:8px}
		.dop-userchip .dop-uinfo{display:none}
		.dop-userchip .dop-logout-link{display:none}
		.dop-hello{flex-direction:column;align-items:flex-start;gap:4px}
	}
</style>
</head>
<body <?php body_class( 'dastyar-ops-page' ); ?>>
<?php
$gate = Dastyar_Staff_Panel::render_gate();
if ( null !== $gate ) {
	echo $gate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی کنترل‌شده داخلی
} else {
	echo Dastyar_Staff_Panel::render_shell(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی کنترل‌شده داخلی
}
?>
<?php wp_footer(); ?>
</body>
</html>
