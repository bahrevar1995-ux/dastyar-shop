<?php
/**
 * پوسته HTML مستقل و تمام‌صفحه‌ی «پنل عملیات دستیار».
 * عمداً get_header()/get_footer() قالب صدا زده نمی‌شود — این صفحه باید کاملاً
 * مستقل از فضای محتوای معمولی سایت (و منوی آن) باشد؛ فقط wp_head/wp_footer برای
 * سازگاری با افزونه‌های دیگر (آنالیتیکس، پیکسل و غیره) حفظ شده‌اند.
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
</script>
<style>
	html,body{margin:0;padding:0;background:#f6f7fa}
	.dop-auth,.dop-shell,.dop-auth *,.dop-shell *{box-sizing:border-box;font-family:"Vazirmatn","IRANSans",Tahoma,sans-serif}
	body{direction:rtl}

	/* برند: سبز/سرمه‌ای دستیار — دقیقاً هم‌رنگ پنل فروشنده */
	:root{--dop-green:#17a16d;--dop-green-d:#12875c;--dop-navy:#242536;--dop-line:#e6e8ef;--dop-bg:#f6f7fa;--dop-text:#2b2c3a;--dop-muted:#8a8d9d}

	.dop-logo{font-size:17px;font-weight:900;color:var(--dop-navy)}
	.dop-logo span{color:var(--dop-green)}

	/* ورود */
	.dop-auth{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:linear-gradient(180deg,#f2faf6,#f6f7fa)}
	.dop-auth-box{background:#fff;border:1px solid var(--dop-line);border-radius:18px;padding:32px 28px;max-width:360px;width:100%;box-shadow:0 20px 50px rgba(23,161,109,.1);text-align:center}
	.dop-auth-sub{color:var(--dop-muted);font-size:12.5px;margin:6px 0 20px}
	.dop-auth-box form{text-align:right}
	.dop-auth-box input{display:block;width:100%;border:1.5px solid #d6e6dc;border-radius:10px;padding:10px 13px;font-family:inherit;font-size:13.5px;margin-bottom:10px;background:#fbfdfc}
	.dop-auth-box button{width:100%;background:var(--dop-green);border:1px solid var(--dop-green-d);color:#fff;border-radius:10px;padding:11px;font-size:14px;font-weight:800;cursor:pointer;margin-top:6px}
	.dop-auth-box button:hover{background:var(--dop-green-d)}
	.dop-err{background:#fdecec;border:1px solid #f3caca;color:#a52828;border-radius:10px;padding:9px 12px;font-size:12.5px;margin-bottom:12px}
	.dop-btn.ghost{display:inline-block;background:#fff;border:1.5px solid var(--dop-line);color:var(--dop-navy)!important;border-radius:9px;padding:8px 18px;font-size:12.5px;font-weight:700;text-decoration:none}

	/* چیدمان اصلی — تمام‌عرض */
	.dop-shell{display:flex;min-height:100vh}
	.dop-side{flex:0 0 240px;background:var(--dop-navy);color:#fff;padding:22px 18px;display:flex;flex-direction:column;position:sticky;top:0;align-self:flex-start;min-height:100vh}
	.dop-side .dop-logo{color:#fff}
	.dop-side .dop-logo span{color:#7ce8b0}
	.dop-tag{font-size:11px;color:rgba(255,255,255,.5);margin:2px 0 22px}
	.dop-nav{display:flex;flex-direction:column;gap:3px;flex:1}
	.dop-glabel{font-size:10px;font-weight:800;color:rgba(255,255,255,.4);text-transform:uppercase;margin:14px 0 4px;padding:0 12px}
	.dop-glabel:first-child{margin-top:0}
	.dop-link{display:flex;align-items:center;gap:9px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.7);text-decoration:none;font-size:13px;font-weight:700}
	.dop-link.sub{font-size:12px;font-weight:600;padding-inline-start:16px}
	.dop-link:hover{background:rgba(255,255,255,.06);color:#fff}
	.dop-link.on{background:var(--dop-green);color:#fff}
	.dop-side-foot{border-top:1px solid rgba(255,255,255,.12);padding-top:14px;display:flex;flex-direction:row;align-items:center;justify-content:space-between;gap:8px;font-size:12px;flex-wrap:wrap}
	.dop-user{color:var(--dop-navy);font-weight:800;font-size:12.5px}	.dop-side-foot a{color:#f5a3a3;text-decoration:none}

	/* نوار بالای محتوا (نه سایدبار) — نام/خروج/دارک‌مود، همیشه سمت چپ صفحه */
	.dop-topbar{display:flex;direction:ltr;justify-content:flex-start;align-items:center;gap:12px;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--dop-line)}
	.dop-topbar-user{direction:rtl;display:flex;flex-direction:column;align-items:flex-start;gap:2px}
	.dop-topbar .dop-darktoggle{background:var(--dop-bg);color:var(--dop-navy);border:1px solid var(--dop-line)}
	.dop-topbar .dop-darktoggle:hover{background:#eef1f0}
	.dop-logout-link{color:var(--dop-muted);text-decoration:none;font-size:10.5px;font-weight:600;opacity:.8}
	.dop-logout-link:hover{color:#a52828;opacity:1;text-decoration:underline}

	.dop-main{flex:1;min-width:0;padding:28px 32px;max-width:1400px}
	.dop-title{font-size:22px;font-weight:900;color:var(--dop-navy);margin:0 0 18px}

	.dop-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px}
	.dop-kpi{border:0;border-radius:14px;padding:18px;text-align:center}
	.dop-kpi-ic{display:flex;align-items:center;justify-content:center;margin:0 auto 12px}
	.dop-kpi-ic svg{width:22px;height:22px}
	.dop-kpi b{display:block;font-size:24px;font-weight:900}
	.dop-kpi span{display:block;font-size:12px;margin-top:6px;opacity:.85}
	.dop-kpi.kpi-blue{background:#e7f0fd;color:#1d4ed8}
	.dop-kpi.kpi-green{background:#e5f5ee;color:#12875c}
	.dop-kpi.kpi-purple{background:#f1e9fb;color:#6d28d9}
	.dop-kpi.kpi-amber{background:#fdf3e2;color:#7a5b00}
	html.dop-dark .dop-kpi.kpi-blue{background:#1e2a4a;color:#8ab4f8}
	html.dop-dark .dop-kpi.kpi-green{background:#16332a;color:#7ce8b0}
	html.dop-dark .dop-kpi.kpi-purple{background:#2a2050;color:#c4b0f5}
	html.dop-dark .dop-kpi.kpi-amber{background:#3a2f14;color:#f0d590}

	/* کارت برجسته رشد هفتگی */
	.dop-growth{display:flex;align-items:center;gap:16px;background:#e5f5ee;border-radius:12px;padding:16px 18px;margin-bottom:16px}
	.dop-growth-ic{width:52px;height:52px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#12875c}
	.dop-growth-ic svg{width:24px;height:24px}
	.dop-growth p{margin:0 0 4px;font-size:13px;color:#12875c;opacity:.85}
	.dop-growth b{font-size:22px;font-weight:900;color:#12875c}
	.dop-growth.down{background:#fdecec}
	.dop-growth.down .dop-growth-ic{color:#922222}
	.dop-growth.down p,.dop-growth.down b{color:#922222}
	html.dop-dark .dop-growth{background:#16332a}
	html.dop-dark .dop-growth-ic{background:#1b1e3c;color:#7ce8b0}
	html.dop-dark .dop-growth p,html.dop-dark .dop-growth b{color:#7ce8b0}
	html.dop-dark .dop-growth.down{background:#3a1f1f}
	html.dop-dark .dop-growth.down .dop-growth-ic{color:#f5a3a3}
	html.dop-dark .dop-growth.down p,html.dop-dark .dop-growth.down b{color:#f5a3a3}

	/* نمودار خطی/منحنی فروش ۱۴ روزه */
	.dop-chart-card .da-card-b{padding-top:20px}
	.dop-chart{overflow:visible;display:block}
	.dop-chart-axis{stroke:var(--dop-line);stroke-width:1}
	.dop-chart-line{stroke:var(--dop-green);stroke-width:2.5}
	.dop-chart-dot{fill:#fff;stroke:var(--dop-green);stroke-width:2.5}
	.dop-chart-val{fill:var(--dop-navy);font-size:9px;font-weight:800;font-family:"Vazirmatn",Tahoma,sans-serif}
	.dop-chart-lbl{fill:var(--dop-muted);font-size:8.5px;font-family:"Vazirmatn",Tahoma,sans-serif}

	/* فهرست آخرین فروشنده‌های فعال */
	.dop-vlist{display:flex;flex-direction:column;gap:2px}
	.dop-vrow{display:flex;align-items:center;gap:12px;padding:10px 4px;border-bottom:1px solid var(--dop-line)}
	.dop-vrow:last-child{border-bottom:0}
	.dop-vav{width:36px;height:36px;border-radius:50%;background:var(--dop-bg);color:var(--dop-green-d);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;flex-shrink:0}
	.dop-vinfo{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
	.dop-vinfo b{font-size:13px;color:var(--dop-navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
	.dop-vinfo span{font-size:10.5px;color:var(--dop-muted)}
	.dop-vbal{font-size:12.5px;font-weight:800;color:var(--dop-green-d);white-space:nowrap}

	/* ردیف‌های جدای پرفروش‌ترین محصولات */
	.dop-plist{display:flex;flex-direction:column;gap:10px}
	.dop-prow{display:flex;align-items:center;gap:12px;background:var(--dop-bg);border:1px solid var(--dop-line);border-radius:12px;padding:10px 14px}
	.dop-pimg{width:44px;height:44px;border-radius:10px;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:#fff;color:var(--dop-muted)}
	.dop-pimg img{width:100%;height:100%;object-fit:cover;display:block}
	.dop-pinfo{flex:1;min-width:0}
	.dop-pinfo b{display:block;font-size:12.5px;color:var(--dop-navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:6px}
	.dop-pbar{height:5px;border-radius:6px;background:var(--dop-line);overflow:hidden}
	.dop-pbar span{display:block;height:100%;background:var(--dop-green);border-radius:6px}
	.dop-pstat{text-align:left;flex-shrink:0}
	.dop-pstat b{display:block;font-size:12.5px;color:var(--dop-green-d)}
	.dop-pstat span{display:block;font-size:10.5px;color:var(--dop-muted);margin-top:2px}

	.dop-alert{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#fdf3e2;border:1px solid #eccb6a;color:#7a5b00;border-radius:12px;padding:12px 16px;margin-bottom:12px;font-size:13px;font-weight:700}
	.dop-alert.warn{background:#fdecec;border-color:#f3caca;color:#a52828}

	.dop-notice{border-radius:10px;padding:10px 16px;margin-bottom:16px;font-size:13px;font-weight:700}
	.dop-notice.ok{background:#ecf9f2;border:1px solid #c6e9d8;color:#0f5132}
	.dop-notice.err{background:#fdf0f0;border:1px solid #f0c8c8;color:#a52828}

	.dop-filterbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
	.dop-filterbar input[type=text]{flex:1;min-width:220px;border:1.5px solid var(--dop-line);border-radius:9px;padding:9px 12px;font-family:inherit;font-size:13px}
	.dop-filterbar select{border:1.5px solid var(--dop-line);border-radius:9px;padding:9px 12px;font-family:inherit;font-size:13px;background:#fff}
	.dop-toolbar{margin:0 0 12px}
	.dop-grid2{display:grid;grid-template-columns:1.3fr 1fr;gap:16px;align-items:start;margin-bottom:16px}
	@media (max-width:1100px){.dop-grid2{grid-template-columns:1fr}}

	.dop-collapse{background:#fff;border:1px solid var(--dop-line);border-radius:12px;margin-bottom:16px}
	.dop-collapse summary{cursor:pointer;padding:12px 16px;font-size:13px;font-weight:700;color:var(--dop-navy);list-style:none}
	.dop-collapse summary::-webkit-details-marker{display:none}
	.dop-collapse-b{padding:0 16px 16px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start}
	.dop-collapse-b textarea{flex:1;min-width:260px;border:1.5px solid var(--dop-line);border-radius:9px;padding:9px 12px;font-family:inherit;font-size:12.5px;direction:ltr;text-align:left}

	.dop-table-wrap{background:#fff;border:1px solid var(--dop-line);border-radius:14px;overflow:auto}
	.dop-table{width:100%;border-collapse:collapse;font-size:12.5px;white-space:nowrap}
	.dop-table th,.dop-table td{padding:11px 14px;border-bottom:1px solid var(--dop-line);text-align:right}
	.dop-table th{background:var(--dop-bg);color:var(--dop-navy);font-weight:800;position:sticky;top:0}
	.dop-empty{color:var(--dop-muted);white-space:normal}
	.dop-muted{color:var(--dop-muted)}

	.dop-inline-f{display:inline-flex;gap:6px;align-items:center}
	.dop-inline-f select,.dop-inline-f input[type=text],.dop-inline-f input[type=number]{border:1.5px solid var(--dop-line);border-radius:7px;padding:5px 8px;font-family:inherit;font-size:12px}
	.dop-mini{background:var(--dop-green);border:1px solid var(--dop-green-d);color:#fff;border-radius:7px;padding:5px 12px;font-size:11.5px;font-weight:700;cursor:pointer}
	.dop-mini:hover{background:var(--dop-green-d)}

	.dop-btn{display:inline-block;background:var(--dop-green);border:1px solid var(--dop-green-d);color:#fff!important;border-radius:9px;padding:8px 16px;font-size:12.5px;font-weight:700;cursor:pointer;text-decoration:none!important}
	.dop-btn:hover{background:var(--dop-green-d)}
	.dop-btn.sm{padding:6px 14px;font-size:11.5px;white-space:nowrap}

	.dop-tabs{display:flex;gap:6px;margin-bottom:14px}
	.dop-tab{padding:7px 16px;border-radius:9px;background:#fff;border:1.5px solid var(--dop-line);color:var(--dop-navy);text-decoration:none;font-size:12.5px;font-weight:700}
	.dop-tab.on{background:var(--dop-green);border-color:var(--dop-green-d);color:#fff}

	.dop-pill{display:inline-block;border-radius:8px;padding:3px 10px;font-size:11px;font-weight:700}
	.dop-pill.pending{background:#fdf3e2;color:#7a5b00}
	.dop-pill.approved{background:#e5f5ee;color:#0f5132}
	.dop-pill.rejected{background:#fdecec;color:#922222}
	.dop-actions{display:flex;gap:6px;flex-wrap:wrap}

	/* پیل رنگی وضعیت سفارش — تشخیص سریع چشمی در جدول سفارش‌ها */
	.dop-status-pill{display:inline-block;border-radius:20px;padding:3px 11px;font-size:11px;font-weight:800;white-space:nowrap}
	.dop-status-pill.st-gray{background:#f1f3f2;color:#5b5e6e}
	.dop-status-pill.st-amber{background:#fdf3e2;color:#7a5b00}
	.dop-status-pill.st-blue{background:#e7f0fd;color:#1d4ed8}
	.dop-status-pill.st-purple{background:#f1e9fb;color:#6d28d9}
	.dop-status-pill.st-green{background:#e5f5ee;color:#0f5132}
	.dop-status-pill.st-red{background:#fdecec;color:#922222}

	/* آکاردئون تغییر وضعیت — بسته فقط پیل، باز شده دراپ‌داون */
	.dop-stacc summary{cursor:pointer;list-style:none}
	.dop-stacc summary::-webkit-details-marker{display:none}
	.dop-stacc[open] summary{margin-bottom:6px}
	.dop-stacc .dop-inline-f{display:flex;gap:6px}

	/* بج کد رهگیری — زرد=ثبت‌نشده، سبز=ثبت‌شده؛ کلیک = باز شدن پاپ‌آپ */
	.dop-track-badge{border:0;border-radius:20px;padding:5px 12px;font-size:11px;font-weight:800;cursor:pointer;direction:ltr;font-family:inherit}
	.dop-track-badge.unset{background:#fdf3e2;color:#7a5b00}
	.dop-track-badge.unset:hover{background:#fbe9c8}
	.dop-track-badge.set{background:#e5f5ee;color:#0f5132}
	.dop-track-badge.set:hover{background:#d5efe3}

	/* پاپ‌آپ مشترک ثبت کد رهگیری */
	.dop-modal{position:fixed;inset:0;z-index:200;display:none;align-items:center;justify-content:center}
	.dop-modal.open{display:flex}
	.dop-modal-bg{position:absolute;inset:0;background:rgba(36,37,54,.5)}
	.dop-modal-box{position:relative;background:#fff;border-radius:16px;padding:24px 22px;max-width:340px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,.25)}
	.dop-modal-box h3{margin:0 0 14px;font-size:15px;color:var(--dop-navy)}
	.dop-modal-x{position:absolute;top:10px;left:12px;background:var(--dop-bg);border:0;border-radius:50%;width:28px;height:28px;cursor:pointer;color:#666}
	.dop-modal-box input[type=text]{width:100%;border:1.5px solid var(--dop-line);border-radius:9px;padding:10px 12px;font-family:inherit;font-size:13.5px;margin-bottom:12px;box-sizing:border-box}
	.dop-modal-box .dop-btn{width:100%}

	/* تصاویر کوچک محصولات هر سفارش، تو جدول سفارش‌ها */
	.dop-thumbs{display:flex;align-items:center;gap:4px}
	.dop-thumb img{width:30px;height:30px;object-fit:cover;border-radius:6px;border:1px solid var(--dop-line);display:block}
	.dop-thumb-more{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;background:var(--dop-bg);border:1px solid var(--dop-line);font-size:10.5px;font-weight:800;color:var(--dop-muted)}

	/* ===== سازگاری با خروجی ماژول‌های افزونه «کنسول مدیریت مرکز» (DA_Render/DA_Page_*) =====
	   این کلاس‌ها را کد آن افزونه مستقیماً چاپ می‌کند؛ اینجا فقط استایلشان می‌دهیم تا داخل
	   همین پوسته یکدست به‌نظر برسند — هیچ HTML آن افزونه تغییر نکرده است. */
	.da-wrap,.da-p-main{max-width:100%}
	.da-page-title{display:flex;align-items:center;gap:10px;color:var(--dop-navy);font-size:20px;margin:0 0 16px}
	.da-logo-dot{width:10px;height:10px;border-radius:50%;background:var(--dop-green);display:inline-block}
	.da-card{background:#fff;border:1px solid var(--dop-line);border-radius:14px;margin:0 0 16px;box-shadow:0 4px 16px rgba(23,161,109,.06);overflow:hidden}
	.da-card-h{padding:12px 18px;background:linear-gradient(135deg,#f4fbf7,#eef7f2);border-bottom:1px solid #e8f2ec}
	.da-card-h h3{margin:0;color:var(--dop-navy);font-size:15px;display:flex;align-items:center;gap:8px}
	.da-card-h p{margin:4px 0 0;color:#6f857b;font-size:12px}
	.da-modid{font-size:10px;background:var(--dop-green);color:#fff;border-radius:8px;padding:2px 7px;font-weight:700}
	.da-card-b{padding:16px 18px;overflow-x:auto}
	.da-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}
	.da-kpi{background:var(--dop-bg);border:1px solid var(--dop-line);border-radius:12px;padding:14px;text-align:center}
	.da-kpi-v{display:block;font-size:22px;font-weight:900;color:var(--dop-green-d)}
	.da-kpi-l{display:block;font-size:11.5px;color:#6f857b;margin-top:4px}
	.da-kpi-s{display:block;font-size:10.5px;color:var(--dop-muted);margin-top:2px}
	.da-table{width:100%;border-collapse:collapse;font-size:12.5px;background:#fff}
	.da-table th,.da-table td{padding:9px 10px;border-bottom:1px solid var(--dop-line);text-align:right}
	.da-table th{background:var(--dop-bg);color:var(--dop-navy);font-weight:800}
	.da-empty{color:var(--dop-muted);font-size:12.5px;white-space:normal}
	.da-pill{display:inline-block;border-radius:8px;padding:2px 9px;font-size:11px;font-weight:700}
	.da-pill-green{background:#e5f5ee;color:#0f5132}
	.da-pill-red{background:#fdecec;color:#922222}
	.da-pill-gray{background:#f1f3f2;color:#5b5e6e}
	.da-btn{display:inline-block;background:var(--dop-green);border:1px solid var(--dop-green-d);color:#fff!important;border-radius:9px;padding:7px 16px;font-size:12.5px;font-weight:700;cursor:pointer;text-decoration:none!important}
	.da-btn:hover{background:var(--dop-green-d)}
	.da-btn.gray{background:#f1f3f2;border-color:var(--dop-line);color:var(--dop-navy)!important}
	.da-btn.red{background:#fdecec;border-color:#f3caca;color:#a52828!important}
	.notice{border-radius:8px;padding:9px 14px;margin:0 0 14px;font-size:12.5px;border:1px solid}
	.notice-success{background:#ecf9f2;border-color:#c6e9d8;color:#0f5132}
	.notice-error{background:#fdf0f0;border-color:#f0c8c8;color:#a52828}
	.notice-warning{background:#fef8e8;border-color:#f0e0a8;color:#7a5b00}

	/* دکمه سوییچ حالت تاریک */
	.dop-darktoggle{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.08);border:0;color:rgba(255,255,255,.8);cursor:pointer;flex-shrink:0}
	.dop-darktoggle:hover{background:rgba(255,255,255,.16)}
	.dop-darktoggle svg{width:16px;height:16px}
	.dop-auth .dop-darktoggle{position:fixed;top:16px;left:16px;background:#fff;border:1px solid var(--dop-line);color:var(--dop-navy)}

	/* ===================== حالت تاریک ===================== */
	html.dop-dark,html.dop-dark body{background:#12142a}
	/* رفع باگ اصلی دارک‌مود: قبلاً هیچ رنگ متن پایه‌ای ست نشده بود، پس اکثر متن‌ها
	   (خصوصاً متن‌های ماژول‌های کنسول که کلاس اختصاصی ندارند) رنگ پیش‌فرض مرورگر
	   (مشکی) می‌گرفتند که روی پس‌زمینه سرمه‌ای تقریباً نامرئی بود. */
	html.dop-dark body,html.dop-dark .dop-main,html.dop-dark .da-card-b,html.dop-dark .da-card-b *{color:#dfe3f6}
	html.dop-dark .da-card-b .da-empty,html.dop-dark .da-card-b .dop-muted{color:#8288a8!important}
	/* رنگ‌های ثابت SVG نمودارهای کنسول (DA_Chart) که در PHP به‌صورت attribute نوشته
	   شده‌اند؛ چون این‌ها attribute ساده‌اند نه inline style، با CSS قابل بازنویسی‌اند. */
	html.dop-dark .da-card-b svg line,html.dop-dark .da-card-b svg path[stroke]{stroke:#3a3e63}
	html.dop-dark .da-card-b svg text{fill:#9aa3c4}
	html.dop-dark .da-card-b svg rect{fill:#2fce96}
	html.dop-dark .dop-chart-line{stroke:#2fce96}
	html.dop-dark .dop-chart-dot{fill:#1b1e3c;stroke:#2fce96}
	html.dop-dark .dop-chart-val{fill:#eceef8}
	html.dop-dark .dop-chart-lbl{fill:#8288a8}
	html.dop-dark .dop-chart-axis{stroke:#2e3252}
	html.dop-dark .dop-vrow{border-bottom-color:#2e3252}
	html.dop-dark .dop-vav{background:#232648;color:#7ce8b0}
	html.dop-dark .dop-vinfo b{color:#eceef8}
	html.dop-dark .dop-vinfo span{color:#8288a8}
	html.dop-dark .dop-vbal{color:#7ce8b0}
	html.dop-dark .dop-prow{background:#181b36;border-color:#2e3252}
	html.dop-dark .dop-pimg{background:#232648;color:#8288a8}
	html.dop-dark .dop-pinfo b{color:#eceef8}
	html.dop-dark .dop-pbar{background:#2e3252}
	html.dop-dark .dop-pstat b{color:#7ce8b0}
	html.dop-dark .dop-pstat span{color:#8288a8}
	html.dop-dark .dop-auth{background:linear-gradient(180deg,#171a34,#12142a)}
	html.dop-dark .dop-auth-box{background:#1b1e3c;border-color:#2e3252;box-shadow:0 20px 50px rgba(0,0,0,.4)}
	html.dop-dark .dop-auth-box input{background:#12142a;border-color:#2e3252;color:#dfe3f6}
	html.dop-dark .dop-auth-sub{color:#9aa3c4}
	html.dop-dark .dop-err{background:#3a1f1f;border-color:#5a2b2b;color:#f5a3a3}
	html.dop-dark .dop-logo{color:#dfe3f6}

	html.dop-dark .dop-side{background:#0e1024}
	html.dop-dark .dop-side-foot{border-top-color:rgba(255,255,255,.08)}
	html.dop-dark .dop-topbar{border-bottom-color:#2e3252}
	html.dop-dark .dop-topbar .dop-darktoggle{background:#1b1e3c;border-color:#2e3252;color:#dfe3f6}
	html.dop-dark .dop-user{color:#dfe3f6}

	html.dop-dark .dop-title{color:#eceef8}
	html.dop-dark .dop-alert{background:#3a2f14;border-color:#5a4a1f;color:#f0d590}
	html.dop-dark .dop-alert.warn{background:#3a1f1f;border-color:#5a2b2b;color:#f5a3a3}
	html.dop-dark .dop-notice.ok{background:#123024;border-color:#1f5a3f;color:#7ce8b0}
	html.dop-dark .dop-notice.err{background:#3a1f1f;border-color:#5a2b2b;color:#f5a3a3}
	html.dop-dark .dop-filterbar input,html.dop-dark .dop-filterbar select{background:#1b1e3c;border-color:#2e3252;color:#dfe3f6}
	html.dop-dark .dop-collapse,html.dop-dark .dop-table-wrap{background:#1b1e3c;border-color:#2e3252}
	html.dop-dark .dop-table{background:#1b1e3c}
	html.dop-dark .dop-table th{background:#181b36;color:#dfe3f6}
	html.dop-dark .dop-table th,html.dop-dark .dop-table td{border-color:#2e3252;color:#dfe3f6}
	html.dop-dark .dop-muted,html.dop-dark .dop-empty{color:#8288a8}
	html.dop-dark .dop-tab{background:#1b1e3c;border-color:#2e3252;color:#dfe3f6}
	html.dop-dark .dop-inline-f select,html.dop-dark .dop-inline-f input{background:#12142a;border-color:#2e3252;color:#dfe3f6}
	html.dop-dark .dop-modal-box{background:#1b1e3c}
	html.dop-dark .dop-modal-box h3{color:#eceef8}
	html.dop-dark .dop-modal-box input[type=text]{background:#12142a;border-color:#2e3252;color:#dfe3f6}
	html.dop-dark .dop-modal-x{background:#2e3252;color:#dfe3f6}
	html.dop-dark .dop-thumb-more{background:#2e3252;border-color:#3a3e63;color:#9aa3c4}

	html.dop-dark .da-card{background:#1b1e3c;border-color:#2e3252}
	html.dop-dark .da-card-h{background:linear-gradient(135deg,#182a22,#171a34);border-bottom-color:#233426}
	html.dop-dark .da-card-h h3{color:#eceef8}
	html.dop-dark .da-card-h p{color:#9aa3c4}
	html.dop-dark .da-kpi{background:#181b36;border-color:#2e3252}
	html.dop-dark .da-kpi-l{color:#9aa3c4}
	html.dop-dark .da-table{background:#1b1e3c}
	html.dop-dark .da-table th{background:#181b36;color:#dfe3f6}
	html.dop-dark .da-table th,html.dop-dark .da-table td{border-color:#2e3252;color:#dfe3f6}
	html.dop-dark .da-empty{color:#8288a8}
	html.dop-dark .da-btn.gray{background:#2e3252;border-color:#3a3e63;color:#dfe3f6!important}
	html.dop-dark .notice-success{background:#123024;border-color:#1f5a3f;color:#7ce8b0}
	html.dop-dark .notice-error{background:#3a1f1f;border-color:#5a2b2b;color:#f5a3a3}
	html.dop-dark .notice-warning{background:#3a2f14;border-color:#5a4a1f;color:#f0d590}

	/* دکمه همبرگری منو — فقط در موبایل نمایش داده می‌شود */
	.dop-side-head{display:flex;align-items:center;justify-content:space-between}
	.dop-mobtoggle{display:none;background:rgba(255,255,255,.08);border:0;color:#fff;width:34px;height:34px;border-radius:9px;font-size:16px;cursor:pointer}

	@media (max-width:900px){
		.dop-shell{flex-direction:column}
		.dop-side{position:static;min-height:0;padding:16px 18px}
		.dop-tag{margin-bottom:14px}
		.dop-mobtoggle{display:inline-flex;align-items:center;justify-content:center}
		.dop-nav{display:none;flex-direction:column;margin-top:6px}
		.dop-nav.open{display:flex}

		.dop-main{padding:16px}
		.dop-topbar{margin-bottom:16px;padding-bottom:12px}
		.dop-topbar-user{gap:8px}
		.dop-user{font-size:11.5px}
		.dop-title{font-size:18px}

		.dop-kpis{grid-template-columns:repeat(2,1fr);gap:10px}
		.dop-kpi{padding:14px 10px}
		.dop-kpi b{font-size:18px}
		.dop-kpi span{font-size:10.5px}

		.dop-filterbar{flex-direction:column;align-items:stretch}
		.dop-filterbar input[type=text],.dop-filterbar select{width:100%;min-width:0}

		.dop-table{font-size:11.5px}
		.dop-table th,.dop-table td{padding:8px}
		.dop-inline-f{flex-wrap:wrap}
		.dop-inline-f select{max-width:110px}

		.dop-tabs{flex-wrap:wrap}
		.dop-actions{flex-direction:column;align-items:stretch}
		.dop-actions form{width:100%}
		.dop-actions .dop-mini{width:100%}

		.dop-modal-box{padding:20px 16px}
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
