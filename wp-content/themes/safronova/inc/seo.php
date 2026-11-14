<?php
/**
 * SEO: заголовок вкладки (<title>) и meta description каждой страницы —
 * такие же, как были на Vigbo. Значения хранятся в полях записи
 * _sf_seo_title и _sf_seo_desc (импортёр заполняет их автоматически).
 * Если установлен Yoast SEO или Rank Math — тема ничего не выводит, чтобы не дублировать.
 *
 * @package safronova
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Включён ли сторонний SEO-плагин.
 *
 * @return bool
 */
function safronova_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

/**
 * ID записи, чьи SEO-данные нужны для текущей страницы.
 *
 * @return int
 */
function safronova_seo_object_id() {
	if ( is_home() ) {
		return (int) get_option( 'page_for_posts' );
	}
	if ( is_singular() ) {
		return (int) get_queried_object_id();
	}
	return 0;
}

add_filter( 'pre_get_document_title', 'safronova_document_title', 20 );
/**
 * Заголовок вкладки.
 *
 * @param string $title Заголовок по умолчанию.
 * @return string
 */
function safronova_document_title( $title ) {
	if ( safronova_seo_plugin_active() ) {
		return $title;
	}
	$id = safronova_seo_object_id();
	if ( $id ) {
		$t = (string) get_post_meta( $id, '_sf_seo_title', true );
		if ( '' !== $t ) {
			return $t;
		}
	}
	return $title;
}

add_action( 'wp_head', 'safronova_meta_tags', 1 );
/**
 * meta description и Open Graph.
 */
function safronova_meta_tags() {
	if ( safronova_seo_plugin_active() ) {
		return;
	}
	$id    = safronova_seo_object_id();
	$desc  = $id ? (string) get_post_meta( $id, '_sf_seo_desc', true ) : '';
	$title = wp_get_document_title();
	if ( '' === $desc && is_front_page() ) {
		$desc = (string) get_bloginfo( 'description' );
	}
	if ( '' !== $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( '' !== $desc ) {
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta property="og:url" content="' . esc_url( safronova_current_url() ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	if ( is_singular() && has_post_thumbnail() ) {
		$img = wp_get_attachment_image_url( (int) get_post_thumbnail_id(), 'large' );
		if ( $img ) {
			echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
		}
	}
}

/**
 * Адрес текущей страницы.
 *
 * @return string
 */
function safronova_current_url() {
	if ( is_singular() ) {
		return (string) get_permalink();
	}
	if ( is_home() ) {
		$p = (int) get_option( 'page_for_posts' );
		return $p ? (string) get_permalink( $p ) : home_url( '/' );
	}
	return home_url( '/' );
}

add_action( 'add_meta_boxes', 'safronova_seo_metabox' );
/**
 * Поля SEO в редакторе страниц и записей.
 */
function safronova_seo_metabox() {
	if ( safronova_seo_plugin_active() ) {
		return;
	}
	foreach ( array( 'page', 'post' ) as $type ) {
		add_meta_box( 'sf_seo', 'SEO (заголовок и описание для Google)', 'safronova_seo_metabox_html', $type, 'normal', 'low' );
	}
}

/**
 * HTML полей SEO.
 *
 * @param WP_Post $post Запись.
 */
function safronova_seo_metabox_html( $post ) {
	wp_nonce_field( 'sf_seo_save', 'sf_seo_nonce' );
	$t = (string) get_post_meta( $post->ID, '_sf_seo_title', true );
	$d = (string) get_post_meta( $post->ID, '_sf_seo_desc', true );
	echo '<p><label for="sf_seo_title"><strong>Заголовок вкладки (title)</strong></label><br>';
	echo '<input type="text" id="sf_seo_title" name="sf_seo_title" value="' . esc_attr( $t ) . '" style="width:100%"></p>';
	echo '<p><label for="sf_seo_desc"><strong>Описание (meta description)</strong></label><br>';
	echo '<textarea id="sf_seo_desc" name="sf_seo_desc" rows="3" style="width:100%">' . esc_textarea( $d ) . '</textarea></p>';
}

add_action( 'save_post', 'safronova_seo_save' );
/**
 * Сохранение полей SEO.
 *
 * @param int $post_id ID записи.
 */
function safronova_seo_save( $post_id ) {
	if ( ! isset( $_POST['sf_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sf_seo_nonce'] ) ), 'sf_seo_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['sf_seo_title'] ) ) {
		update_post_meta( $post_id, '_sf_seo_title', sanitize_text_field( wp_unslash( $_POST['sf_seo_title'] ) ) );
	}
	if ( isset( $_POST['sf_seo_desc'] ) ) {
		update_post_meta( $post_id, '_sf_seo_desc', sanitize_textarea_field( wp_unslash( $_POST['sf_seo_desc'] ) ) );
	}
}
