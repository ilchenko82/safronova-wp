/**
 * Тема Safronova: мобильное меню, слайдер на главной,
 * полноэкранный просмотр фото в галереях, форма обратной связи.
 * Без jQuery и сторонних библиотек.
 */
(function () {
	'use strict';

	var data = window.safronovaData || {};
	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ---------- высота шапки (для слайдера на весь экран) ---------- */
	function setHeaderHeight() {
		var h = document.querySelector('.sf-header');
		if (h) {
			document.documentElement.style.setProperty('--sf-header-h', h.offsetHeight + 'px');
		}
	}

	/* ---------- мобильное меню ---------- */
	function initMobileMenu() {
		var burger = document.querySelector('.sf-burger');
		var panel = document.getElementById('sf-mobile');
		if (!burger || !panel) { return; }
		var closeBtn = panel.querySelector('.sf-mobile__close');

		function open() {
			panel.classList.add('is-open');
			panel.setAttribute('aria-hidden', 'false');
			burger.setAttribute('aria-expanded', 'true');
			document.body.classList.add('sf-menu-open');
			if (closeBtn) { closeBtn.focus(); }
		}
		function close() {
			panel.classList.remove('is-open');
			panel.setAttribute('aria-hidden', 'true');
			burger.setAttribute('aria-expanded', 'false');
			document.body.classList.remove('sf-menu-open');
			burger.focus();
		}
		burger.addEventListener('click', open);
		if (closeBtn) { closeBtn.addEventListener('click', close); }
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && panel.classList.contains('is-open')) { close(); }
		});

		// «Портфоліо» в мобильном меню раскрывает подпункты, как на прежнем сайте.
		panel.querySelectorAll('.menu-item-has-children > a').forEach(function (a) {
			a.addEventListener('click', function (e) {
				var li = a.parentNode;
				if (!li.classList.contains('is-open')) {
					e.preventDefault();
					li.classList.add('is-open');
				}
			});
		});
	}

	/* ---------- слайдер на главной ---------- */
	function initHero() {
		var hero = document.querySelector('.wp-block-gallery.sf-hero');
		if (!hero) { return; }
		var slides = Array.prototype.slice.call(hero.querySelectorAll(':scope > .wp-block-image'));
		if (!slides.length) { return; }

		// фокус кадра из класса sf-pos-X-Y
		slides.forEach(function (s) {
			var m = s.className.match(/sf-pos-(\d+)-(\d+)/);
			var img = s.querySelector('img');
			if (m && img) { img.style.objectPosition = m[1] + '% ' + m[2] + '%'; }
			if (img) { img.loading = 'eager'; img.removeAttribute('loading'); }
		});

		var i = 0;
		slides[0].classList.add('is-active');
		hero.classList.add('is-ready');

		if (slides.length > 1 && !reduceMotion) {
			var pause = parseInt(data.heroPause, 10) || 2000;
			setInterval(function () {
				if (document.hidden) { return; }
				slides[i].classList.remove('is-active');
				i = (i + 1) % slides.length;
				slides[i].classList.add('is-active');
			}, pause + 1000);
		}

		// параллакс: фон двигается медленнее страницы
		if (!reduceMotion) {
			var ticking = false;
			window.addEventListener('scroll', function () {
				if (ticking) { return; }
				ticking = true;
				window.requestAnimationFrame(function () {
					var y = Math.min(window.scrollY, hero.offsetHeight) * 0.25;
					hero.querySelectorAll('img').forEach(function (img) {
						img.style.transform = 'translate3d(0,' + y + 'px,0)';
					});
					ticking = false;
				});
			}, { passive: true });
		}
	}

	/* ---------- полноэкранный просмотр фото ---------- */
	function initLightbox() {
		var galleries = document.querySelectorAll('.wp-block-gallery.sf-gallery');
		if (!galleries.length) { return; }

		var lb = document.createElement('div');
		lb.className = 'sf-lb';
		lb.setAttribute('role', 'dialog');
		lb.setAttribute('aria-modal', 'true');
		lb.setAttribute('aria-label', 'Просмотр фото');
		lb.innerHTML =
			'<img class="sf-lb__img" alt="">' +
			'<button class="sf-lb__btn sf-lb__close" type="button" aria-label="Закрыть"><svg viewBox="0 0 20 20"><path d="M1 1l18 18M19 1L1 19" stroke="currentColor" stroke-width="1.6" fill="none"/></svg></button>' +
			'<button class="sf-lb__btn sf-lb__prev" type="button" aria-label="Предыдущее фото"><svg viewBox="0 0 12 22"><path d="M11 1L1 11l10 10" stroke="currentColor" stroke-width="1.5" fill="none"/></svg></button>' +
			'<button class="sf-lb__btn sf-lb__next" type="button" aria-label="Следующее фото"><svg viewBox="0 0 12 22"><path d="M1 1l10 10L1 21" stroke="currentColor" stroke-width="1.5" fill="none"/></svg></button>' +
			'<div class="sf-lb__count" aria-live="polite"></div>';
		document.body.appendChild(lb);

		var imgEl = lb.querySelector('.sf-lb__img');
		var countEl = lb.querySelector('.sf-lb__count');
		var list = [];
		var idx = 0;
		var lastFocus = null;

		function fullSrc(img) {
			// самый большой вариант из srcset, иначе src
			var best = img.currentSrc || img.src;
			var set = img.getAttribute('srcset');
			if (set) {
				var max = 0;
				set.split(',').forEach(function (part) {
					var p = part.trim().split(/\s+/);
					var w = parseInt(p[1], 10) || 0;
					if (w > max) { max = w; best = p[0]; }
				});
			}
			return best;
		}
		function show(n) {
			idx = (n + list.length) % list.length;
			imgEl.style.opacity = '0';
			var src = fullSrc(list[idx]);
			var pre = new Image();
			pre.onload = pre.onerror = function () {
				imgEl.src = src;
				imgEl.alt = list[idx].alt || '';
				imgEl.style.opacity = '1';
			};
			pre.src = src;
			countEl.textContent = (idx + 1) + ' / ' + list.length;
			// подгружаем соседние
			[idx + 1, idx - 1].forEach(function (k) {
				var el = list[(k + list.length) % list.length];
				if (el) { (new Image()).src = fullSrc(el); }
			});
		}
		function open(gallery, img) {
			list = Array.prototype.slice.call(gallery.querySelectorAll('img'));
			lastFocus = document.activeElement;
			show(list.indexOf(img));
			lb.classList.add('is-open');
			document.body.classList.add('sf-menu-open');
			lb.querySelector('.sf-lb__close').focus();
		}
		function close() {
			lb.classList.remove('is-open');
			document.body.classList.remove('sf-menu-open');
			if (lastFocus) { lastFocus.focus(); }
		}

		galleries.forEach(function (g) {
			g.addEventListener('click', function (e) {
				var img = e.target.closest('img');
				if (!img) { return; }
				e.preventDefault();
				open(g, img);
			});
			g.querySelectorAll('img').forEach(function (img) {
				img.setAttribute('tabindex', '0');
				img.addEventListener('keydown', function (e) {
					if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(g, img); }
				});
			});
		});
		lb.querySelector('.sf-lb__close').addEventListener('click', close);
		lb.querySelector('.sf-lb__prev').addEventListener('click', function () { show(idx - 1); });
		lb.querySelector('.sf-lb__next').addEventListener('click', function () { show(idx + 1); });
		imgEl.addEventListener('click', function () { show(idx + 1); });
		lb.addEventListener('click', function (e) { if (e.target === lb) { close(); } });
		document.addEventListener('keydown', function (e) {
			if (!lb.classList.contains('is-open')) { return; }
			if (e.key === 'Escape') { close(); }
			if (e.key === 'ArrowLeft') { show(idx - 1); }
			if (e.key === 'ArrowRight') { show(idx + 1); }
		});
		// свайп на телефоне
		var x0 = null;
		lb.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
		lb.addEventListener('touchend', function (e) {
			if (x0 === null) { return; }
			var dx = e.changedTouches[0].clientX - x0;
			if (Math.abs(dx) > 40) { show(dx < 0 ? idx + 1 : idx - 1); }
			x0 = null;
		});
	}

	/* ---------- форма обратной связи ---------- */
	function initForms() {
		document.querySelectorAll('.sf-form').forEach(function (form) {
			// подписи-плейсхолдеры прячутся, когда поле заполнено
			form.querySelectorAll('.sf-form__field input, .sf-form__field textarea').forEach(function (f) {
				var sync = function () { f.parentNode.classList.toggle('is-filled', f.value.trim() !== ''); };
				f.addEventListener('input', sync);
				sync();
			});

			form.addEventListener('submit', function (e) {
				if (!window.fetch || !window.FormData || !data.ajaxUrl) { return; }
				e.preventDefault();
				var msg = form.querySelector('.sf-form__msg');
				var btn = form.querySelector('.sf-form__btn');
				var email = form.querySelector('input[name="sf_email"]');
				msg.classList.remove('is-error');
				if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
					msg.textContent = data.msgRequired;
					msg.classList.add('is-error');
					email.focus();
					return;
				}
				btn.disabled = true;
				msg.textContent = data.msgSending;
				fetch(data.ajaxUrl, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						msg.textContent = (res && res.data && res.data.message) || data.msgError;
						if (res && res.success) {
							form.reset();
							form.querySelectorAll('.sf-form__field').forEach(function (f) { f.classList.remove('is-filled'); });
						} else {
							msg.classList.add('is-error');
						}
					})
					.catch(function () {
						msg.textContent = data.msgError;
						msg.classList.add('is-error');
					})
					.then(function () { btn.disabled = false; });
			});
		});
	}

	function init() {
		setHeaderHeight();
		initMobileMenu();
		initHero();
		initLightbox();
		initForms();
		window.addEventListener('resize', setHeaderHeight);
		window.addEventListener('load', setHeaderHeight);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
