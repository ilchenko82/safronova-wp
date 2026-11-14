<?php
/**
 * Тема Safronova — подключение всего необходимого.
 *
 * @package safronova
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAFRONOVA_VERSION', '0.1.0' );
define( 'SAFRONOVA_DIR', get_template_directory() );
define( 'SAFRONOVA_URI', get_template_directory_uri() );

require_once SAFRONOVA_DIR . '/inc/header.php';
require_once SAFRONOVA_DIR . '/inc/contact-form.php';
require_once SAFRONOVA_DIR . '/inc/seo.php';

add_action( 'after_setup_theme', 'safronova_setup' );
/**
 * Базовые возможности темы.
 */
function safronova_setup() {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 308,
			'width'       => 340,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	load_theme_textdomain( 'safronova', SAFRONOVA_DIR . '/languages' );

	register_nav_menus(
		array(
			'sf-left'  => __( 'Меню слева от логотипа', 'safronova' ),
			'sf-right' => __( 'Меню справа от логотипа', 'safronova' ),
		)
	);

	add_editor_style( 'style.css' );
}

/**
 * Адрес файла шрифта: сначала папка темы, затем запасная папка в uploads
 * (туда шрифты кладёт импортёр, если папка темы закрыта для записи).
 *
 * @param string $file Имя файла.
 * @return string[] Список URL.
 */
function safronova_font_urls( $file ) {
	$urls = array();
	if ( file_exists( SAFRONOVA_DIR . '/assets/fonts/' . $file ) ) {
		$urls[] = SAFRONOVA_URI . '/assets/fonts/' . $file;
	}
	$up = wp_get_upload_dir();
	if ( empty( $up['error'] ) && file_exists( trailingslashit( $up['basedir'] ) . 'safronova/fonts/' . $file ) ) {
		$urls[] = trailingslashit( $up['baseurl'] ) . 'safronova/fonts/' . $file;
	}
	return $urls;
}

/**
 * CSS с @font-face для шрифтов сайта (Fira Sans и Playfair Display, лицензия OFL).
 *
 * @return string
 */
function safronova_font_css() {
	$fonts = array(
		'Fira Sans'        => 'firasans-regular.woff',
		'Playfair Display' => 'playfairdisplay-regular.woff',
		'Open Sans'        => 'opensans-regular.woff',
	);
	$css   = '';
	foreach ( $fonts as $family => $file ) {
		$urls = safronova_font_urls( $file );
		if ( ! $urls ) {
			continue;
		}
		$src = array();
		foreach ( $urls as $u ) {
			$src[] = "url('" . esc_url_raw( $u ) . "') format('woff')";
		}
		$css .= "@font-face{font-family:'" . $family . "';font-style:normal;font-weight:400;font-display:swap;src:" . implode( ',', $src ) . ";}\n";
	}
	return $css;
}

add_action( 'wp_enqueue_scripts', 'safronova_assets' );
/**
 * Стили и скрипты сайта.
 */
function safronova_assets() {
	$ver = SAFRONOVA_VERSION . '.' . (string) filemtime( SAFRONOVA_DIR . '/style.css' );
	wp_enqueue_style( 'safronova', get_stylesheet_uri(), array(), $ver );
	wp_add_inline_style( 'safronova', safronova_font_css() );

	wp_enqueue_script(
		'safronova',
		SAFRONOVA_URI . '/assets/js/site.js',
		array(),
		SAFRONOVA_VERSION . '.' . (string) filemtime( SAFRONOVA_DIR . '/assets/js/site.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_localize_script(
		'safronova',
		'safronovaData',
		array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'heroPause'   => 2000,
			'msgSending'  => 'Отправка…',
			'msgError'    => 'Не удалось отправить сообщение. Попробуйте ещё раз или напишите на почту.',
			'msgRequired' => 'Пожалуйста, укажите корректный e-mail.',
		)
	);
}

add_action( 'enqueue_block_editor_assets', 'safronova_editor_assets' );
/**
 * Шрифты в редакторе блоков.
 */
function safronova_editor_assets() {
	wp_register_style( 'safronova-editor-fonts', false, array(), SAFRONOVA_VERSION );
	wp_enqueue_style( 'safronova-editor-fonts' );
	wp_add_inline_style( 'safronova-editor-fonts', safronova_font_css() );
}

add_action( 'init', 'safronova_register_blocks' );
/**
 * Динамические блоки темы: шапка и форма обратной связи.
 * В редакторе показываются через предпросмотр сервера.
 */
function safronova_register_blocks() {
	wp_register_script(
		'safronova-editor',
		SAFRONOVA_URI . '/assets/js/editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor' ),
		SAFRONOVA_VERSION,
		true
	);

	register_block_type(
		'safronova/header',
		array(
			'api_version'     => 3,
			'title'           => 'Шапка сайта',
			'category'        => 'theme',
			'editor_script'   => 'safronova-editor',
			'render_callback' => 'safronova_render_header',
			'supports'        => array( 'html' => false ),
		)
	);

	register_block_type(
		'safronova/contact-form',
		array(
			'api_version'     => 3,
			'title'           => 'Форма обратной связи',
			'category'        => 'widgets',
			'editor_script'   => 'safronova-editor',
			'render_callback' => 'safronova_render_contact_form',
			'supports'        => array( 'html' => false ),
		)
	);
}

add_filter( 'body_class', 'safronova_body_class' );
/**
 * Класс для главной страницы.
 *
 * @param string[] $classes Классы body.
 * @return string[]
 */
function safronova_body_class( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'sf-is-front';
	}
	return $classes;
}

/*
 * Блог: 9 записей на странице, как на прежнем сайте.
 */
add_action( 'pre_get_posts', 'safronova_posts_per_page' );
/**
 * Количество записей в ленте блога.
 *
 * @param WP_Query $q Запрос.
 */
function safronova_posts_per_page( $q ) {
	if ( ! is_admin() && $q->is_main_query() && $q->is_home() ) {
		$q->set( 'posts_per_page', 9 );
	}
}

/*
 * Убираем лишнее из <head>: эмодзи-скрипты и генератор не нужны.
 */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );
