/**
 * Экран «Сайт»: дерево страниц как в Vigbo — перетаскивание, «В меню», подписи, автосохранение.
 */
(function ($) {
	'use strict';
	var $wrap = $('.sfa-wrap');
	if (!$wrap.length || !window.sfSite) { return; }
	var $toast = $wrap.find('.sfa-toast');
	var timer = null;
	var toastTimer = null;

	function toast(text, err) {
		$toast.text(text).toggleClass('is-error', !!err).addClass('is-on');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { $toast.removeClass('is-on'); }, err ? 5000 : 1800);
	}

	function node($li) {
		return {
			page: parseInt($li.attr('data-page'), 10) || 0,
			url: $li.attr('data-url') || '',
			title: $.trim($li.find('> .sfa-row__in .sfa-label').val())
		};
	}

	function collect() {
		var out = {};
		$wrap.find('.sfa-tree[data-menu^="sf-"]').each(function () {
			var list = [];
			$(this).children('li').each(function () {
				var n = node($(this));
				n.children = [];
				$(this).find('> .sfa-children > li').each(function () { n.children.push(node($(this))); });
				list.push(n);
			});
			out[$(this).attr('data-menu')] = list;
		});
		return out;
	}

	function save() {
		clearTimeout(timer);
		timer = setTimeout(function () {
			toast('Сохраняю…');
			$.post(sfSite.ajax, { action: 'sf_save_menus', nonce: sfSite.nonce, menus: JSON.stringify(collect()) })
				.done(function (r) { toast(r && r.success ? 'Сохранено ✓' : ((r && r.data && r.data.message) || 'Ошибка'), !(r && r.success)); })
				.fail(function (x) { toast('Не сохранилось: ' + ((x.responseJSON && x.responseJSON.data && x.responseJSON.data.message) || x.status), true); });
		}, 400);
	}

	// Приводит строку в соответствие со списком, где она оказалась.
	function sync($li) {
		var inMenu = $li.closest('.sfa-tree').attr('data-menu') !== 'hidden';
		var $label = $li.find('> .sfa-row__in .sfa-label');
		$li.find('> .sfa-row__in .sfa-show').prop('checked', inMenu);
		$label.prop('disabled', !inMenu);
		if (inMenu && !$label.val()) { $label.val($li.find('> .sfa-row__in .sfa-path').text()); }
		// глубина — не больше двух уровней, как в Vigbo
		if ($li.parent().hasClass('sfa-children')) {
			$li.find('> .sfa-children > li').insertAfter($li);
		}
		// в «Не в меню» подпунктов нет
		if (!inMenu) {
			var $kids = $li.find('> .sfa-children > li');
			$kids.insertAfter($li);
			$kids.each(function () { sync($(this)); });
		}
	}

	var locked = $wrap.find('[data-locked]').length > 0;
	if (!locked) {
		$wrap.find('.sfa-sortable').sortable({
			connectWith: '.sfa-sortable',
			handle: '.sfa-handle',
			placeholder: 'sfa-placeholder',
			tolerance: 'pointer',
			cursorAt: { top: 20, left: 20 },
			start: function () { $('body').addClass('sfa-dragging'); },
			stop: function (e, ui) {
				$('body').removeClass('sfa-dragging');
				sync(ui.item);
				save();
			}
		});
	}

	$wrap.on('change', '.sfa-show', function () {
		var $li = $(this).closest('li');
		if (this.checked) {
			$wrap.find('.sfa-tree[data-menu="sf-right"]').append($li);
		} else {
			$wrap.find('.sfa-tree[data-menu="hidden"]').prepend($li);
		}
		sync($li);
		$('html, body').animate({ scrollTop: $li.offset().top - 120 }, 250);
		$li.addClass('is-flash');
		setTimeout(function () { $li.removeClass('is-flash'); }, 1200);
		save();
	});

	$wrap.on('change', '.sfa-label', save);
	$wrap.on('keydown', '.sfa-label', function (e) { if (e.key === 'Enter') { e.preventDefault(); this.blur(); } });
}(jQuery));
