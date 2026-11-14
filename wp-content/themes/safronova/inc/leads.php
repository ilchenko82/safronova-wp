<?php
/**
 * «Заявки с сайта»: каждое сообщение из формы обратной связи сохраняется в админке
 * (как раздел заявок в Vigbo) — даже если письмо на почту не дошло.
 *
 * @package safronova
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'safronova_register_leads' );
/**
 * Тип записей «Заявка».
 */
function safronova_register_leads() {
	register_post_type(
		'sf_lead',
		array(
			'labels'          => array(
				'name'               => 'Заявки с сайта',
				'singular_name'      => 'Заявка',
				'menu_name'          => 'Заявки',
				'all_items'          => 'Все заявки',
				'edit_item'          => 'Заявка',
				'search_items'       => 'Искать заявки',
				'not_found'          => 'Заявок пока нет',
				'not_found_in_trash' => 'В корзине заявок нет',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => false,
			'menu_position'   => 3,
			'menu_icon'       => 'dashicons-email-alt',
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);
}

/**
 * Сохранить заявку.
 *
 * @param string $name    Имя.
 * @param string $email   E-mail.
 * @param string $message Сообщение.
 * @param bool   $mailed  Удалось ли отправить письмо.
 * @return int ID заявки.
 */
function safronova_save_lead( $name, $email, $message, $mailed ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'sf_lead',
			'post_status'  => 'private',
			'post_title'   => wp_slash( ( $name ? $name : 'Без имени' ) . ' — ' . $email ),
			'post_content' => wp_slash( $message ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	update_post_meta( $id, '_sf_lead_name', $name );
	update_post_meta( $id, '_sf_lead_email', $email );
	update_post_meta( $id, '_sf_lead_mailed', $mailed ? 1 : 0 );
	update_post_meta( $id, '_sf_lead_new', 1 );
	return (int) $id;
}

/**
 * Количество непрочитанных заявок.
 *
 * @return int
 */
function safronova_new_leads_count() {
	$q = new WP_Query(
		array(
			'post_type'      => 'sf_lead',
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_sf_lead_new', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- заявок немного.
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	return (int) $q->found_posts;
}

add_action( 'admin_menu', 'safronova_leads_badge', 99 );
/**
 * Красный счётчик новых заявок в меню админки.
 */
function safronova_leads_badge() {
	global $menu;
	$n = safronova_new_leads_count();
	if ( ! $n || ! is_array( $menu ) ) {
		return;
	}
	foreach ( $menu as $k => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=sf_lead' === $item[2] ) {
			$menu[ $k ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $n . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- штатный способ добавить счётчик.
		}
	}
}

add_filter( 'manage_sf_lead_posts_columns', 'safronova_leads_columns' );
/**
 * Колонки списка заявок.
 *
 * @return array
 */
function safronova_leads_columns() {
	return array(
		'cb'         => '<input type="checkbox">',
		'title'      => 'Имя и e-mail',
		'sf_message' => 'Сообщение',
		'sf_mail'    => 'Письмо на почту',
		'date'       => 'Дата',
	);
}

add_action( 'manage_sf_lead_posts_custom_column', 'safronova_leads_column', 10, 2 );
/**
 * Значения колонок.
 *
 * @param string $col Колонка.
 * @param int    $id  ID заявки.
 */
function safronova_leads_column( $col, $id ) {
	if ( 'sf_message' === $col ) {
		$new = get_post_meta( $id, '_sf_lead_new', true );
		echo $new ? '<strong>Новая · </strong>' : '';
		echo esc_html( wp_trim_words( (string) get_post_field( 'post_content', $id ), 20 ) );
	} elseif ( 'sf_mail' === $col ) {
		echo get_post_meta( $id, '_sf_lead_mailed', true ) ? 'отправлено' : '<span style="color:#b32d2e">не дошло — ответьте вручную</span>';
	}
}

add_action( 'add_meta_boxes_sf_lead', 'safronova_lead_metabox' );
/**
 * Удобный просмотр заявки + отметка «прочитано».
 *
 * @param WP_Post $post Заявка.
 */
function safronova_lead_metabox( $post ) {
	delete_post_meta( $post->ID, '_sf_lead_new' );
	add_meta_box(
		'sf_lead_view',
		'Ответить',
		function ( $p ) {
			$email = (string) get_post_meta( $p->ID, '_sf_lead_email', true );
			$name  = (string) get_post_meta( $p->ID, '_sf_lead_name', true );
			echo '<p><strong>' . esc_html( $name ) . '</strong><br>' . esc_html( $email ) . '</p>';
			if ( is_email( $email ) ) {
				$href = 'mailto:' . $email . '?subject=' . rawurlencode( 'Фотосесія — відповідь на ваше повідомлення' );
				echo '<p><a class="button button-primary" href="' . esc_url( $href ) . '">Ответить письмом</a></p>';
			}
		},
		'sf_lead',
		'side',
		'high'
	);
}

add_filter( 'use_block_editor_for_post_type', 'safronova_leads_classic', 10, 2 );
/**
 * Заявки открываются в простом виде, без блочного редактора.
 *
 * @param bool   $use  Использовать блочный редактор.
 * @param string $type Тип записи.
 * @return bool
 */
function safronova_leads_classic( $use, $type ) {
	return 'sf_lead' === $type ? false : $use;
}
