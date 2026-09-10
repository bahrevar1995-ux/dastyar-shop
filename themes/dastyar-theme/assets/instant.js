/*
 * قالب «دستیار» — لود آنی صفحات (PJAX)
 *
 *  - کلیک روی لینک‌های داخلی: دریافت HTML صفحه مقصد، تعویض محتوای #dth-main،
 *    به‌روزرسانی تیتر و تاریخچه مرورگر (pushState)، نوار پیشرفت سبز بالای صفحه.
 *  - نگه‌داربودن پیش‌شبیه‌سازی (prefetch) هنگام هاور/تاچ → کلیک بعدی تقریباً آنی.
 *  - مسیرهای حساس (پیشخوان، سبد/تسویه، ورود وردپرس) و لینک‌های دانلودی/target
 *    و لنگرها کاملاً دور زده می‌شوند تا با ووکامرس و افزونه‌ها هیچ تداخلی پیش نیاید.
 *  - در هر خطای احتمالی، ناوبری عادی مرورگر جایگزین می‌شود (Location.href).
 */
(function () {
	'use strict';

	var cfg = window.DTH_CFG || {};
	var SEL = cfg.sel || '#dth-main';
	var EXCLUDE = cfg.exclude || [];
	var cache = new Map(); // url → {html, title, t}
	var TTL = 60000;
	var current = location.href;
	var progress = document.getElementById('dth-progress');
	var navigating = false;

	function progressStart() {
		if (!progress) { return; }
		progress.style.transition = 'none';
		progress.style.width = '0';
		progress.style.opacity = '1';
		void progress.offsetWidth;
		progress.style.transition = '';
		progress.style.width = '72%';
	}
	function progressDone() {
		if (!progress) { return; }
		progress.style.width = '100%';
		setTimeout(function () {
			progress.style.opacity = '0';
			setTimeout(function () { progress.style.width = '0'; }, 320);
		}, 120);
	}

	function sameOrigin(u) {
		try { return new URL(u, location.href).origin === location.origin; } catch (e) { return false; }
	}

	function isExcluded(u) {
		var p;
		try { p = new URL(u, location.href).pathname; } catch (e) { return true; }
		for (var i = 0; i < EXCLUDE.length; i++) {
			if (EXCLUDE[i] && p.indexOf(EXCLUDE[i]) === 0) { return true; }
		}
		return /\.(zip|pdf|jpg|jpeg|png|gif|webp|svg|docx?|xlsx?)($|\?)/i.test(u);
	}

	function shouldSkip(a, e) {
		if (!a || !a.href) { return true; }
		if (e && (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey)) { return true; }
		if (a.target && '_self' !== a.target) { return true; }
		if (a.hasAttribute('download')) { return true; }
		if (a.closest('#wpadminbar')) { return true; }
		if (a.hasAttribute('data-dth-nopjax')) { return true; }
		var href = a.getAttribute('href') || '';
		if (!href || href.indexOf('#') === 0 || href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) { return true; }
		if (!sameOrigin(a.href)) { return true; }
		if (isExcluded(a.href)) { return true; }
		// همان صفحه + لنگر متفاوت → اسکرول طبیعی
		try {
			var u = new URL(a.href);
			var c = new URL(location.href);
			if (u.pathname === c.pathname && u.search === c.search && u.hash !== c.hash) { return true; }
		} catch (err) { return true; }
		return false;
	}

	function get(url) {
		var hit = cache.get(url);
		if (hit && (Date.now() - hit.t) < TTL) { return Promise.resolve(hit); }
		return fetch(url, { credentials: 'same-origin', headers: { 'X-DTH-PJAX': '1' } })
			.then(function (r) {
				if (!r.ok) { throw new Error('http ' + r.status); }
				return r.text();
			})
			.then(function (txt) {
				var doc = new DOMParser().parseFromString(txt, 'text/html');
				var main = doc.querySelector(SEL);
				if (!main) { throw new Error('no main'); }
				var data = {
					html: main.innerHTML,
					title: doc.title || document.title,
					bodyClass: doc.body ? doc.body.className : '',
					t: Date.now()
				};
				cache.set(url, data);
				return data;
			});
	}

	function prefetch(url) {
		if (cache.has(url)) { return; }
		get(url).catch(function () { /* سکوت: ناوبری عادی جبران می‌کند */ });
	}

	function swap(data, url) {
		var main = document.querySelector(SEL);
		if (!main) { location.href = url; return; }
		main.innerHTML = data.html;
		document.title = data.title;
		if (data.bodyClass) { document.body.className = data.bodyClass; }
		// اجرای اسکریپت‌های درون‌خطی محتوای تازه (اسکریپت‌های خارجی همان‌های قبلی‌اند)
		main.querySelectorAll('script').forEach(function (old) {
			if (old.src) {
				if (!document.querySelector('script[src="' + old.src + '"]')) {
					var ext = document.createElement('script');
					ext.src = old.src;
					document.body.appendChild(ext);
				}
				old.parentNode && old.parentNode.removeChild(old);
				return;
			}
			var s = document.createElement('script');
			s.textContent = old.textContent;
			old.parentNode && old.parentNode.replaceChild(s, old);
		});
		current = url;
		if (window.DTHInit) { window.DTHInit(main); }
		window.dispatchEvent(new CustomEvent('dth:pjax', { detail: { url: url } }));
	}

	function navigate(url, push) {
		if (navigating) { return; }
		navigating = true;
		progressStart();
		get(url).then(function (data) {
			swap(data, url);
			if (push) { history.pushState({ dth: 1, url: url }, data.title, url); }
			if (location.hash) {
				var t = document.querySelector(location.hash);
				if (t) { t.scrollIntoView(); } else { window.scrollTo(0, 0); }
			} else {
				window.scrollTo(0, 0);
			}
			navigating = false;
			progressDone();
		}).catch(function () {
			navigating = false;
			location.href = url; // فالبک امن
		});
	}

	document.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest('a[href]') : null;
		if (!a || shouldSkip(a, e)) { return; }
		e.preventDefault();
		var url = a.href;
		if (url === current) { return; }
		navigate(url, true);
	});

	document.addEventListener('mouseover', function (e) {
		var a = e.target.closest ? e.target.closest('a[href]') : null;
		if (!a || shouldSkip(a, null)) { return; }
		prefetch(a.href);
	});
	document.addEventListener('touchstart', function (e) {
		var a = e.target.closest ? e.target.closest('a[href]') : null;
		if (!a || shouldSkip(a, null)) { return; }
		prefetch(a.href);
	}, { passive: true });

	window.addEventListener('popstate', function (e) {
		var url = (e.state && e.state.url) ? e.state.url : location.href;
		navigate(url, false);
	});

	try { history.replaceState({ dth: 1, url: location.href }, document.title, location.href); } catch (e) { /* قدیمی */ }
})();
