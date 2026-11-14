/**
 * Панель виджетов как в редакторе Vigbo: один клик — и блок добавлен после выделенного
 * (или в конец страницы). На телефоне панель прокручивается вбок.
 */
(function (wp) {
	'use strict';
	if (!wp || !wp.plugins || !wp.element || !wp.blocks || !wp.data) { return; }
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var createPortal = wp.element.createPortal;
	var cb = wp.blocks.createBlock;

	function p(text) { return cb('core/paragraph', { content: text || '' }); }

	var ITEMS = [
		['Заголовок', 'heading', function () { return cb('core/heading', { level: 2, textAlign: 'center' }); }],
		['Текст', 'editor-paragraph', function () { return p(); }],
		['Цитата', 'format-quote', function () { return cb('core/quote', {}, [p()]); }],
		['Аккордеон', 'list-view', function () { return cb('core/details', { summary: 'Вопрос' }, [p('Ответ')]); }],
		['Фотография', 'format-image', function () { return cb('core/image', { sizeSlug: 'full', align: 'center' }); }],
		['Галерея', 'format-gallery', function () { return cb('core/gallery', { columns: 1, imageCrop: false, linkTo: 'none', sizeSlug: 'full', className: 'sf-gallery' }); }],
		['Плитка фото', 'grid-view', function () { return cb('core/gallery', { columns: 3, imageCrop: false, linkTo: 'none', sizeSlug: 'full', className: 'sf-gallery sf-gallery--grid' }); }],
		['Видео', 'video-alt3', function () { return cb('core/embed', { providerNameSlug: 'youtube', type: 'video', responsive: true }); }],
		['Кнопка', 'button', function () { return cb('core/buttons', { layout: { type: 'flex', justifyContent: 'center' } }, [cb('core/button', { text: 'Записаться' })]); }],
		['Форма', 'email', function () { return cb('safronova/contact-form'); }],
		['Колонки', 'columns', function () { return cb('core/columns', { className: 'sf-row' }, [cb('core/column', {}, [p()]), cb('core/column', {}, [p()])]); }],
		['Отступ', 'image-flip-vertical', function () { return cb('core/spacer', { height: '50px' }); }],
		['Разделитель', 'minus', function () { return cb('core/separator', { className: 'sf-line sf-w-100' }); }],
		['Соц. иконки', 'share', function () {
			return cb('core/social-links', { openInNewTab: true, className: 'sf-social', layout: { type: 'flex', justifyContent: 'center' } }, [
				cb('core/social-link', { service: 'instagram', url: 'https://www.instagram.com/' }),
				cb('core/social-link', { service: 'facebook', url: 'https://www.facebook.com/' })
			]);
		}],
		['Встроенный код', 'editor-code', function () { return cb('core/html'); }]
	];

	function insert(make) {
		var sel = wp.data.select('core/block-editor');
		var dis = wp.data.dispatch('core/block-editor');
		var block = make();
		var id = sel.getSelectedBlockClientId();
		var root = id ? sel.getBlockRootClientId(id) : '';
		var index = id ? sel.getBlockIndex(id) + 1 : sel.getBlockCount('');
		// подняться выше, если внутрь выделенного контейнера такой блок вставить нельзя (например, в галерею)
		while (id && !sel.canInsertBlockType(block.name, root || undefined)) {
			id = root;
			root = id ? sel.getBlockRootClientId(id) : '';
			index = id ? sel.getBlockIndex(id) + 1 : sel.getBlockCount('');
		}
		dis.insertBlocks(block, index, root || undefined, true);
	}

	function openPatterns() {
		var ed = wp.data.dispatch('core/editor');
		var ep = wp.data.dispatch('core/edit-post');
		var fn = (ed && ed.setIsInserterOpened) || (ep && ep.setIsInserterOpened);
		if (fn) { fn({ tab: 'patterns', category: 'safronova' }); }
	}

	function Dock() {
		var st = useState(false);
		var closed = st[0];
		var setClosed = st[1];
		var type = wp.data.useSelect(function (s) { var e = s('core/editor'); return e && e.getCurrentPostType ? e.getCurrentPostType() : ''; }, []);
		if (type !== 'page' && type !== 'post') { return null; }
		var btns = ITEMS.map(function (it) {
			return el('button', { type: 'button', key: it[0], className: 'sfa-dock__b', onClick: function () { insert(it[2]); }, title: 'Добавить: ' + it[0] },
				el('span', { className: 'dashicons dashicons-' + it[1] }), el('span', null, it[0]));
		});
		btns.push(el('button', { type: 'button', key: 'ready', className: 'sfa-dock__b sfa-dock__b--accent', onClick: openPatterns, title: 'Готовые блоки' },
			el('span', { className: 'dashicons dashicons-screenoptions' }), el('span', null, 'Готовые блоки')));
		return createPortal(
			el('div', { className: 'sfa-dock' + (closed ? ' is-closed' : ''), role: 'toolbar', 'aria-label': 'Добавить на страницу' },
				el('button', { type: 'button', className: 'sfa-dock__toggle', onClick: function () { setClosed(!closed); }, 'aria-expanded': !closed },
					el('span', { className: 'dashicons dashicons-' + (closed ? 'plus-alt2' : 'arrow-down-alt2') }), el('span', null, closed ? 'Виджеты' : 'Скрыть')),
				el('div', { className: 'sfa-dock__list' }, btns)),
			document.body
		);
	}

	wp.plugins.registerPlugin('safronova-dock', { render: Dock });
}(window.wp));
