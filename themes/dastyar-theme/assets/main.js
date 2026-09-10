/*
 * قالب «دستیار» — تعاملات فرانت (کشوی موبایل، شمارنده آمار، انیمیشن ورود)
 * window.DTHInit(scope) بعد از تعویض محتوای لود آنی هم دوباره صدا زده می‌شود.
 */
(function () {
	'use strict';

	document.documentElement.className += ' dhm-js';

	function dhmFa(x) {
		x = Math.max(0, Math.round(x)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '٬');
		return x.replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; });
	}

	/*
	 * همهٔ ارقام لاتین متن صفحه → فارسی (v1.0.2)
	 * اعدادِ داخل ورودی‌ها، کدها، اسکریپت و ناحیه‌های ltr دست نمی‌خورند.
	 * تبدیل روی «گره متنی» سند انجام می‌شود تا HTML/لیسینرها سالم بمانند.
	 */
	var DTH_DIGITS_SKIP = 'input,textarea,script,style,code,pre,kbd,samp,option,[dir="ltr"],[data-dth-latin="1"]';
	function dthFaDigits(root) {
		var start = root && root.nodeType === 1 ? root : (root && root.querySelector ? root : document.body);
		if (!start || start === document && !document.body) { return; }
		var scope = start === document ? document.body : start;
		if (!scope) { return; }
		var walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT, {
			acceptNode: function (node) {
				if (!/[0-9]/.test(node.nodeValue)) { return NodeFilter.FILTER_REJECT; }
				var p = node.parentElement;
				if (!p || p.closest(DTH_DIGITS_SKIP)) { return NodeFilter.FILTER_REJECT; }
				return NodeFilter.FILTER_ACCEPT;
			}
		});
		var list = [], n;
		while ((n = walker.nextNode())) { list.push(n); }
		for (var i = 0; i < list.length; i++) {
			list[i].nodeValue = list[i].nodeValue.replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; });
		}
	}
	window.dthFaDigits = dthFaDigits;

	function dhmCount(el) {
		if (el.getAttribute('data-dhm-done')) { return; }
		el.setAttribute('data-dhm-done', '1');
		var to = parseFloat(el.getAttribute('data-dhm-to'));
		if (isNaN(to)) { return; }
		var t0 = null, dur = 1100;
		function step(ts) {
			if (!t0) { t0 = ts; }
			var p = Math.min(1, (ts - t0) / dur);
			var e = 1 - Math.pow(1 - p, 3);
			el.textContent = dhmFa(to * e);
			if (p < 1) { requestAnimationFrame(step); }
		}
		requestAnimationFrame(step);
	}

	function closeDrawer() {
		var d = document.querySelector('.dhm-drawer');
		if (d) { d.classList.remove('dhm-show'); }
		document.body.style.overflow = '';
	}

	document.addEventListener('click', function (e) {
		if (e.target.closest('.dhm-burger')) {
			var d = document.querySelector('.dhm-drawer');
			if (d) { d.classList.add('dhm-show'); }
			document.body.style.overflow = 'hidden';
		} else if (e.target.closest('.dhm-drawer-bg') || e.target.closest('.dhm-drawer-close') || e.target.closest('.dhm-drawer-nav a')) {
			closeDrawer();
		}
	});
	document.addEventListener('keydown', function (e) {
		if ('Escape' === e.key) { closeDrawer(); }
	});

	/**
	 * فعال‌سازی انیمیشن‌ها در اسکوپ داده‌شده (پیش‌فرض کل سند).
	 * بعد از لود آنی (PJAX) روی #dth-main دوباره اجرا می‌شود.
	 */
	window.DTHInit = function (scope) {
		var root = scope || document;
		try { dthFaDigits(root); } catch (e) {} // فارسی‌سازی ارقام در این اسکوپ (شامل محتوای PJAX)
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (es) {
				es.forEach(function (en) {
					if (en.isIntersecting) { io.unobserve(en.target); en.target.classList.add('dhm-vi'); }
				});
			}, { threshold: 0.12 });
			root.querySelectorAll('.dhm-rv:not(.dhm-vi)').forEach(function (el) { io.observe(el); });

			var io2 = new IntersectionObserver(function (es) {
				es.forEach(function (en) {
					if (en.isIntersecting) { io2.unobserve(en.target); dhmCount(en.target); }
				});
			}, { threshold: 0.5 });
			root.querySelectorAll('.dhm-count').forEach(function (el) { io2.observe(el); });
		} else {
			root.querySelectorAll('.dhm-rv:not(.dhm-vi)').forEach(function (el) { el.classList.add('dhm-vi'); });
			root.querySelectorAll('.dhm-count').forEach(function (el) {
				var to = parseFloat(el.getAttribute('data-dhm-to'));
				if (!isNaN(to)) { el.textContent = dhmFa(to); }
			});
		}
	};

	window.DTHInit(document);
	dthFaDigits(document);
})();

/*
 * v2.2.0 — سوییچ حالت نمایش (تاریک/روشن)
 * حالت انتخابی کاربر در localStorage ذخیره می‌شود؛ اگر انتخابی نکرده باشد
 * همان ترجیح سیستم (prefers-color-scheme) دنبال می‌شود.
 * لیسینر روی سند است تا پس از تعویض محتوای لود آنی هم پابرجا بماند.
 */
(function () {
	'use strict';
	function dthApply(t, save) {
		var el = document.documentElement;
		el.classList.toggle('dhm-dark', t === 'dark');
		el.style.colorScheme = t;
		if (save) {
			try { localStorage.setItem('dastyar-theme', t); } catch (e) {}
		}
		var btn = document.querySelectorAll('.dhm-theme-toggle');
		for (var i = 0; i < btn.length; i++) {
			btn[i].setAttribute('aria-pressed', t === 'dark' ? 'true' : 'false');
			btn[i].setAttribute('aria-label', t === 'dark' ? 'تغییر به حالت روشن' : 'تغییر به حالت تاریک');
		}
	}
	/* حالت فعلی ذخیره‌شده کاربر (اگر انتخابی نکرده، ترجیح سیستم) — بدون ذخیره دوباره */
	function dthSaved() {
		var t = null;
		try { t = localStorage.getItem('dastyar-theme'); } catch (e) {}
		if ('dark' === t || 'light' === t) { return t; }
		return ( window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ) ? 'dark' : 'light';
	}
	document.addEventListener('click', function (e) {
		var b = e.target && e.target.closest ? e.target.closest('.dhm-theme-toggle') : null;
		if (!b) { return; }
		dthApply(document.documentElement.classList.contains('dhm-dark') ? 'light' : 'dark', true);
	});
	/* اگر کاربر انتخابی نکرده، تغییر تم سیستم زنده دنبال شود */
	if (window.matchMedia) {
		var mq = window.matchMedia('(prefers-color-scheme: dark)');
		var follow = function () {
			try { if (null === localStorage.getItem('dastyar-theme')) { dthApply(mq.matches ? 'dark' : 'light', false); } } catch (e) {}
		};
		if (mq.addEventListener) { mq.addEventListener('change', follow); }
	}
	/*
	 * v2.4.2 — رفع باگ: برگشتن حالت تاریک/روشن هنگام ورود به برگه‌هایی مثل کاتالوگ.
	 * علت معمول: کش برگشت/جلوی مرورگر (bfcache) که یک اسنپ‌شات قدیمی صفحه (از قبل
	 * از انتخاب کاربر) را نشان می‌دهد، یا رفتن به صفحه‌ای که با فرم/GET معمولی
	 * (نه لود آنی) به‌طور کامل رفرش می‌شود. بعد از هر بازگشت/تعویض محتوا، حالت
	 * ذخیره‌شده دوباره اعمال می‌شود تا هیچ‌جا خودسرانه به تم دیگر برنگردد.
	 */
	window.addEventListener('pageshow', function () { dthApply(dthSaved(), false); });
	window.addEventListener('dth:pjax', function () { dthApply(dthSaved(), false); });
	dthApply(document.documentElement.classList.contains('dhm-dark') ? 'dark' : 'light', false);
})();

/*
 * v2.4.0 — پاپ‌آپ مشاوره تلفنی: فرم موبایل هیرو ← «نیاز به مشاوره داری؟»
 * تأیید ← ثبت AJAX در پیشخوان + پیام «به زودی باهات تماس می‌گیریم»؛
 * انصراف ← همان رفتار قبلی (ادامه به صفحه ورود/ثبت‌نام با پارامتر phone).
 * لیسنرها روی سند‌اند تا با لود آنی (PJAX) هم پابرجا بمانند؛ بدون fetch هم فالبک GET کار می‌کند.
 */
(function () {
	'use strict';
	function fa2en(s) {
		var fa = '۰۱۲۳۴۵۶۷۸۹', ar = '٠١٢٣٤٥٦٧٨٩', o = '', i, c, k;
		for (i = 0; i < s.length; i++) {
			c = s.charAt(i);
			k = fa.indexOf(c); if (k > -1) { o += k; continue; }
			k = ar.indexOf(c); if (k > -1) { o += k; continue; }
			o += c;
		}
		return o.replace(/\D+/g, '');
	}
	function norm(p) {
		p = fa2en(String(p || ''));
		if (p.indexOf('0098') === 0) { p = '0' + p.substr(4); }
		else if (p.indexOf('98') === 0 && p.length >= 12) { p = '0' + p.substr(2); }
		return p;
	}
	function nextUrl(f, phone) {
		var u = f.getAttribute('action') || '/';
		return u + (u.indexOf('?') > -1 ? '&' : '?') + 'phone=' + encodeURIComponent(phone);
	}
	function open(m, phone, f) {
		m.__ctx = { phone: phone, f: f };
		var ph = m.querySelector('.dth-leadm-phone');
		if (ph) { ph.textContent = phone; }
		var ask = m.querySelector('.dth-leadm-ask'), done = m.querySelector('.dth-leadm-done'), err = m.querySelector('.dth-leadm-err');
		if (ask) { ask.hidden = false; }
		if (done) { done.hidden = true; }
		if (err) { err.hidden = true; }
		var go = m.querySelector('.dth-leadm-go');
		if (go) { go.setAttribute('href', nextUrl(f, phone)); }
		m.classList.add('on');
		m.setAttribute('aria-hidden', 'false');
	}
	function close(m) {
		m.classList.remove('on');
		m.setAttribute('aria-hidden', 'true');
	}
	document.addEventListener('submit', function (e) {
		var f = e.target;
		if (!f.matches || !f.matches('form.dhm-lead[data-dth-lead="1"]')) { return; }
		var wrap = f.closest('section') || f.parentNode || document;
		var m = wrap.querySelector('.dth-leadm');
		if (!m) { return; } // پاپ‌آپ نبود ← رفتار قبلی
		var inp = f.querySelector('input[name="phone"]');
		var phone = norm(inp ? inp.value : '');
		if (phone.length < 10 || phone.indexOf('09') !== 0) { return; } // شماره نامعتبر ← رفتار قبلی (GET)
		e.preventDefault();
		open(m, phone, f);
	}, true);
	document.addEventListener('click', function (e) {
		var t = e.target && e.target.closest ? e.target : null;
		if (!t) { return; }
		var cl = t.closest ? t.closest('[data-lead-close]') : null;
		if (cl) { var mm = cl.closest('.dth-leadm'); if (mm) { close(mm); } return; }
		var no = t.closest ? t.closest('.dth-leadm-no') : null;
		if (no) {
			var m1 = no.closest('.dth-leadm');
			if (m1 && m1.__ctx) { close(m1); window.location.href = nextUrl(m1.__ctx.f, m1.__ctx.phone); }
			return;
		}
		var yes = t.closest ? t.closest('.dth-leadm-yes') : null;
		if (yes) {
			var m2 = yes.closest('.dth-leadm');
			if (!m2 || !m2.__ctx) { return; }
			var f = m2.__ctx.f;
			var ajax = f.getAttribute('data-ajax'), nonce = f.getAttribute('data-nonce');
			if (!ajax || !window.fetch) { close(m2); window.location.href = nextUrl(f, m2.__ctx.phone); return; }
			var err = m2.querySelector('.dth-leadm-err');
			if (err) { err.hidden = true; }
			yes.disabled = true;
			var old = yes.textContent;
			yes.textContent = 'در حال ثبت…';
			var body = new URLSearchParams({ action: 'dth_lead', nonce: nonce || '', phone: m2.__ctx.phone, page: window.location.href });
			fetch(ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					if (j && j.success) {
						var ask = m2.querySelector('.dth-leadm-ask'), done = m2.querySelector('.dth-leadm-done');
						if (ask) { ask.hidden = true; }
						if (done) { done.hidden = false; }
					} else if (err) { err.hidden = false; }
				})
				.catch(function () { if (err) { err.hidden = false; } })
				.finally(function () { yes.disabled = false; yes.textContent = old; });
		}
	});
	document.addEventListener('keydown', function (e) {
		if (27 !== e.keyCode) { return; }
		var m = document.querySelector('.dth-leadm.on');
		if (m) { close(m); }
	});
})();
