<?php
/**
 * Удаление плагина: чистим служебные настройки импорта.
 * Страницы, записи и фото остаются на сайте.
 *
 * @package safronova-migration
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'sfmig_media_map' );
delete_option( 'sfmig_errors' );
delete_option( 'sfmig_finished' );
