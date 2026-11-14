/**
 * «Оформление»: выбор логотипа и иконки сайта из медиатеки.
 */
(function ($) {
	'use strict';
	$(document).on('click', '.sfa-media-pick', function () {
		var $form = $(this).closest('form');
		var frame = wp.media({ title: $(this).data('title'), library: { type: 'image' }, button: { text: 'Выбрать' }, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var src = (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url);
			$form.find('.sfa-media-id').val(a.id);
			$form.find('.sfa-media-preview').empty().append($('<img>').attr({ src: src, alt: '' }));
		});
		frame.open();
	});
}(jQuery));
