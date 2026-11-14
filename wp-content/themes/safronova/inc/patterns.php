<?php
/**
 * Готовые блоки («паттерны») в стиле сайта: вставляются кнопкой «+» → «Паттерны» → «Сафронова».
 * Ничего не нужно верстать — только заменить фото и текст.
 *
 * @package safronova
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'safronova_register_patterns' );
/**
 * Регистрация категории и паттернов.
 */
function safronova_register_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern_category( 'safronova', array( 'label' => 'Сафронова' ) );

	$img_v = '<!-- wp:image {"width":"560px","sizeSlug":"full","linkDestination":"none","align":"center"} -->' . "\n" . '<figure class="wp-block-image aligncenter size-full is-resized"><img alt="" style="width:560px"/></figure>' . "\n" . '<!-- /wp:image -->';
	$img_h = '<!-- wp:image {"width":"850px","sizeSlug":"full","linkDestination":"none","align":"center"} -->' . "\n" . '<figure class="wp-block-image aligncenter size-full is-resized"><img alt="" style="width:850px"/></figure>' . "\n" . '<!-- /wp:image -->';

	$spacer_col = function ( $w ) {
		return '<!-- wp:column {"width":"' . $w . '","className":"sf-col-spacer"} -->' . "\n" . '<div class="wp-block-column sf-col-spacer" style="flex-basis:' . $w . '"></div>' . "\n" . '<!-- /wp:column -->';
	};
	$row = function ( $side, $mid, $inner ) use ( $spacer_col ) {
		return '<!-- wp:columns {"className":"sf-row"} -->' . "\n" . '<div class="wp-block-columns sf-row">' . $spacer_col( $side )
			. '<!-- wp:column {"width":"' . $mid . '"} -->' . "\n" . '<div class="wp-block-column" style="flex-basis:' . $mid . '">' . $inner . '</div>' . "\n" . '<!-- /wp:column -->'
			. $spacer_col( $side ) . '</div>' . "\n" . '<!-- /wp:columns -->';
	};
	$text = function ( $lh, $inner ) {
		return '<!-- wp:group {"className":"sf-text","style":{"typography":{"lineHeight":"' . $lh . '"}},"layout":{"type":"default"}} -->' . "\n" . '<div class="wp-block-group sf-text" style="line-height:' . $lh . '">' . $inner . '</div>' . "\n" . '<!-- /wp:group -->';
	};
	$p = function ( $html, $align = '' ) {
		return $align
			? '<!-- wp:paragraph {"align":"' . $align . '"} -->' . "\n" . '<p class="has-text-align-' . $align . '">' . $html . '</p>' . "\n" . '<!-- /wp:paragraph -->'
			: '<!-- wp:paragraph -->' . "\n" . '<p>' . $html . '</p>' . "\n" . '<!-- /wp:paragraph -->';
	};

	// 1. Запись блога: вертикальные фото по центру + горизонтальное шире.
	$blog = $row( '25%', '50%', $img_v . "\n" . $img_v . "\n" . $img_v ) . "\n\n" . $row( '12.5%', '75%', $img_h ) . "\n\n" . $row( '25%', '50%', $img_v . "\n" . $img_v );
	register_block_pattern(
		'safronova/blog-photos',
		array(
			'title'       => 'Запись блога: фото',
			'description' => 'Вертикальные фото по центру и горизонтальное пошире — как в записях блога. Нажмите на пустую рамку и выберите фото.',
			'categories'  => array( 'safronova' ),
			'postTypes'   => array( 'post' ),
			'blockTypes'  => array( 'core/post-content' ),
			'content'     => $blog,
		)
	);

	// 2. Галерея портфолио (клик — фото на весь экран).
	$gallery = '<!-- wp:gallery {"columns":1,"imageCrop":false,"linkTo":"none","sizeSlug":"full","className":"sf-gallery"} -->' . "\n" . '<figure class="wp-block-gallery has-nested-images columns-1 sf-gallery"></figure>' . "\n" . '<!-- /wp:gallery -->';
	register_block_pattern(
		'safronova/portfolio-gallery',
		array(
			'title'       => 'Галерея портфолио',
			'description' => 'Фото одно под другим, по клику открываются на весь экран со стрелками.',
			'categories'  => array( 'safronova', 'gallery' ),
			'content'     => $row( '12.5%', '75%', $gallery ),
		)
	);

	// 3. Отзыв: круглое фото + имя + текст.
	$review_img = '<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"is-style-rounded"} -->' . "\n" . '<figure class="wp-block-image size-full is-style-rounded"><img alt=""/></figure>' . "\n" . '<!-- /wp:image -->';
	$review_txt = $text( '0.5', $p( '&nbsp;' ) . $p( '&nbsp;' ) . $p( '<strong>ИМЯ</strong>' ) ) . "\n" . $text( '1.5', $p( '<span style="font-size:14px">Текст отзыва…</span>' ) );
	register_block_pattern(
		'safronova/review',
		array(
			'title'       => 'Отзыв',
			'description' => 'Круглое фото клиентки, имя и текст отзыва.',
			'categories'  => array( 'safronova', 'testimonials' ),
			'content'     => '<!-- wp:columns {"className":"sf-row"} -->' . "\n" . '<div class="wp-block-columns sf-row"><!-- wp:column {"width":"33.3333%"} -->' . "\n" . '<div class="wp-block-column" style="flex-basis:33.3333%">' . $review_img . '</div>' . "\n" . '<!-- /wp:column --><!-- wp:column {"width":"66.6667%"} -->' . "\n" . '<div class="wp-block-column" style="flex-basis:66.6667%">' . $review_txt . '</div>' . "\n" . '<!-- /wp:column --></div>' . "\n" . '<!-- /wp:columns -->',
		)
	);

	// 4. Пакет услуг: фото + название и цена + список.
	$pkg_img   = '<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->' . "\n" . '<figure class="wp-block-image size-full"><img alt=""/></figure>' . "\n" . '<!-- /wp:image -->';
	$pkg_title = $text( '1', $p( '<span style="font-size:24px"><span style="font-family:\'Playfair Display\', serif">НАЗВАНИЕ / 10 000 грн</span></span>' ) );
	$pkg_list  = $text( '1.4', $p( '<span style="font-size:14px">- пункт 1</span>' ) . $p( '<span style="font-size:14px">- пункт 2</span>' ) . $p( '<span style="font-size:14px">- пункт 3</span>' ) );
	register_block_pattern(
		'safronova/price-package',
		array(
			'title'       => 'Пакет услуг',
			'description' => 'Фото, название пакета с ценой и список того, что входит.',
			'categories'  => array( 'safronova' ),
			'content'     => $pkg_img . "\n" . $pkg_title . "\n" . $pkg_list,
		)
	);

	// 5. Заголовок страницы.
	register_block_pattern(
		'safronova/page-title',
		array(
			'title'       => 'Заголовок страницы',
			'description' => 'Большой заголовок шрифтом Playfair Display по центру, как на всех страницах сайта.',
			'categories'  => array( 'safronova', 'text' ),
			'content'     => $text( '2', $p( '<span style="font-family:\'Playfair Display\', serif"><span style="font-size:50px">ЗАГОЛОВОК</span></span>', 'center' ) ),
		)
	);
}

add_action( 'init', 'safronova_unregister_core_patterns', 20 );
/**
 * Чтобы не путаться — прячем десятки стандартных паттернов WordPress, остаются только наши.
 */
function safronova_unregister_core_patterns() {
	remove_theme_support( 'core-block-patterns' );
}
add_filter( 'should_load_remote_block_patterns', '__return_false' );
