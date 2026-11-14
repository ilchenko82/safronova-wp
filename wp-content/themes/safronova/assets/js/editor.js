/**
 * Редактор блоков: показывает шапку и форму обратной связи
 * как предпросмотр с сервера (сами блоки выводятся PHP-кодом темы).
 */
(function (wp) {
	'use strict';
	if (!wp || !wp.blocks || !wp.element) { return; }
	var el = wp.element.createElement;
	var SSR = wp.serverSideRender;
	var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;

	function edit(name) {
		return function () {
			var props = useBlockProps ? useBlockProps() : {};
			return el('div', props, SSR ? el(SSR, { block: name }) : name);
		};
	}

	[
		['safronova/header', 'Шапка сайта', 'theme', 'Логотип и меню. Пункты меню: Внешний вид → Меню.'],
		['safronova/contact-form', 'Форма обратной связи', 'widgets', 'Имя, e-mail, сообщение — письмо уходит на почту владельца сайта.']
	].forEach(function (b) {
		if (wp.blocks.getBlockType(b[0])) { return; }
		wp.blocks.registerBlockType(b[0], {
			apiVersion: 3,
			title: b[1],
			category: b[2],
			description: b[3],
			supports: { html: false },
			edit: edit(b[0]),
			save: function () { return null; }
		});
	});
}(window.wp));
