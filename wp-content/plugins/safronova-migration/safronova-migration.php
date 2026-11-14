<?php
/**
 * Plugin Name: Safronova — перенос с Vigbo
 * Description: Одноразовый импорт сайта safronova.com.ua с Vigbo: страницы, записи блога, меню, SEO, все фото в медиатеку, логотип и шрифты. После переноса плагин можно удалить.
 * Version: 0.1.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Elena Safronova Photography
 * License: GPL-2.0-or-later
 * Text Domain: safronova-migration
 *
 * @package safronova-migration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SFMIG_DIR', plugin_dir_path( __FILE__ ) );
define( 'SFMIG_CDN', 'https://static-cdn4-2.vigbo.tech/u44468/57218/' );
define( 'SFMIG_FONTS_CDN', 'https://cdn06-2.vigbo.tech/cms/kevin2/site/dev/fontspack/' );
define( 'SFMIG_THEME', 'safronova' );
define( 'SFMIG_BATCH', 3 );

/**
 * Активна ли тема Safronova (по её коду — папка может называться иначе, например safronova-main).
 *
 * @return bool
 */
function sfmig_theme_active() {
	return function_exists( 'safronova_render_header' ) || SFMIG_THEME === get_stylesheet();
}

/**
 * Данные для импорта (data/content.json).
 *
 * @return array
 */
function sfmig_data() {
	static $data = null;
	if ( null === $data ) {
		$raw  = file_get_contents( SFMIG_DIR . 'data/content.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- локальный файл плагина.
		$data = $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $data ) ) {
			$data = array();
		}
	}
	return $data;
}

/**
 * Полный список файлов для загрузки: фото страниц + логотип.
 *
 * @return string[]
 */
function sfmig_media_list() {
	$d    = sfmig_data();
	$list = isset( $d['media'] ) ? (array) $d['media'] : array();
	if ( ! empty( $d['logo'] ) ) {
		$list = array_values( array_diff( $list, array( $d['logo'] ) ) );
		array_unshift( $list, $d['logo'] ); // логотип первым — сразу появится в шапке
	}
	return array_values( $list );
}

/* =====================================================================
 * Страница в админке: Инструменты → Перенос с Vigbo
 * ===================================================================*/

add_action( 'admin_menu', 'sfmig_menu' );
/**
 * Пункт меню.
 */
function sfmig_menu() {
	add_management_page( 'Перенос с Vigbo', 'Перенос с Vigbo', 'manage_options', 'safronova-migration', 'sfmig_page' );
}

/**
 * HTML страницы импорта.
 */
function sfmig_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$total  = count( sfmig_media_list() );
	$map    = (array) get_option( 'sfmig_media_map', array() );
	$done   = count( $map );
	$errors = (array) get_option( 'sfmig_errors', array() );
	?>
	<div class="wrap">
		<h1>Перенос сайта с Vigbo</h1>
		<p>Плагин создаст все страницы и записи блога с прежними адресами, меню и SEO-заголовки, скачает все фотографии в медиатеку WordPress, установит логотип и шрифты.</p>
		<?php if ( ! sfmig_theme_active() ) : ?>
			<div class="notice notice-warning"><p><strong>Сначала активируйте тему «Safronova»</strong> (Внешний вид → Темы), затем запустите перенос.</p></div>
		<?php endif; ?>
		<table class="widefat striped" style="max-width:720px;margin:16px 0">
			<tbody>
				<tr><td>Страниц и записей</td><td><?php echo esc_html( count( (array) ( sfmig_data()['pages'] ?? array() ) ) . ' страниц, ' . count( (array) ( sfmig_data()['posts'] ?? array() ) ) . ' записей блога' ); ?></td></tr>
				<tr><td>Фото загружено</td><td id="sfmig-count"><?php echo esc_html( $done . ' из ' . $total ); ?></td></tr>
				<tr><td>Статус</td><td id="sfmig-status"><?php echo esc_html( get_option( 'sfmig_finished' ) ? 'Перенос завершён' : 'Ожидает запуска' ); ?></td></tr>
			</tbody>
		</table>
		<div style="max-width:720px;height:10px;background:#e5e5e5;border-radius:5px;overflow:hidden;margin-bottom:16px">
			<div id="sfmig-bar" style="height:100%;width:<?php echo esc_attr( (string) ( $total ? round( $done / $total * 100 ) : 0 ) ); ?>%;background:#2271b1;transition:width .3s"></div>
		</div>
		<?php
		$probe        = get_page_by_path( 'pregnancy' );
		$content_done = (bool) get_option( 'sfmig_content_done' ) || ( $probe && get_post_meta( $probe->ID, '_sf_mig', true ) );
		?>
		<?php if ( $content_done ) : ?>
			<p><label><input type="checkbox" id="sfmig-recreate"> Заново создать страницы, записи и меню из копии Vigbo <strong>(ваши правки на этих страницах будут перезаписаны)</strong></label></p>
			<p class="description">Без галочки — только докачать недостающие фото, шрифты и логотип. Ваши изменения на страницах не трогаются.</p>
		<?php endif; ?>
		<p>
			<button type="button" class="button button-primary button-hero" id="sfmig-start" data-content-done="<?php echo $content_done ? '1' : '0'; ?>"><?php echo esc_html( $content_done ? 'Продолжить перенос' : 'Запустить перенос' ); ?></button>
		</p>
		<p class="description">Можно запускать повторно: уже загруженные фото не скачиваются заново, страницы обновляются, дубли не создаются. Не закрывайте вкладку до конца загрузки (обычно 5–15 минут).</p>
		<h2>Журнал</h2>
		<ol id="sfmig-log" style="max-width:720px;max-height:320px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px 10px 10px 34px">
			<?php foreach ( array_slice( $errors, -20 ) as $e ) : ?>
				<li style="color:#b32d2e"><?php echo esc_html( $e ); ?></li>
			<?php endforeach; ?>
		</ol>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', 'sfmig_admin_js' );
/**
 * Скрипт пошагового импорта.
 *
 * @param string $hook Текущая страница админки.
 */
function sfmig_admin_js( $hook ) {
	if ( 'tools_page_safronova-migration' !== $hook ) {
		return;
	}
	wp_register_script( 'sfmig', false, array(), '0.1.0', true );
	wp_enqueue_script( 'sfmig' );
	wp_add_inline_script(
		'sfmig',
		'window.sfmig=' . wp_json_encode(
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'sfmig' ),
			)
		) . ';' . sfmig_js()
	);
}

/**
 * JS: шаги content → media (пачками) → fonts → finalize.
 *
 * @return string
 */
function sfmig_js() {
	return <<<'JS'
(function(){
 var btn=document.getElementById('sfmig-start'); if(!btn){return;}
 var log=document.getElementById('sfmig-log'), st=document.getElementById('sfmig-status'), cnt=document.getElementById('sfmig-count'), bar=document.getElementById('sfmig-bar');
 function add(t,err){var li=document.createElement('li'); li.textContent=t; if(err){li.style.color='#b32d2e';} log.appendChild(li); log.scrollTop=log.scrollHeight;}
 function call(step,extra){var fd=new FormData(); fd.append('action','sfmig_step'); fd.append('nonce',sfmig.nonce); fd.append('step',step); for(var k in (extra||{})){fd.append(k,extra[k]);}
  return fetch(sfmig.ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){ if(!j||!j.success){throw new Error((j&&j.data&&j.data.message)||'Ошибка сервера');} return j.data;});}
 function media(tries){ return call('media').then(function(d){ (d.log||[]).forEach(function(l){add(l.t,l.e);}); cnt.textContent=d.done+' из '+d.total; bar.style.width=(d.total?Math.round(d.done/d.total*100):100)+'%'; if(!d.finished){return media(0);} }).catch(function(e){ if(tries<3){add('Повтор после ошибки: '+e.message,true); return new Promise(function(r){setTimeout(r,3000);}).then(function(){return media(tries+1);});} throw e; }); }
 btn.addEventListener('click',function(){
  btn.disabled=true;
  var rc=document.getElementById('sfmig-recreate');
  var skip=btn.getAttribute('data-content-done')==='1' && !(rc && rc.checked);
  var first=skip ? (st.textContent='Добавляю новые страницы (существующие не трогаю)…', call('missing')) : (st.textContent='Создаю страницы, записи и меню…', call('content'));
  first.then(function(d){(d.log||[]).forEach(function(l){add(l.t,l.e);}); st.textContent='Загружаю фото в медиатеку…'; return media(0);})
  .then(function(){st.textContent='Шрифты и логотип…'; return call('fonts');}).then(function(d){(d.log||[]).forEach(function(l){add(l.t,l.e);}); st.textContent='Заменяю ссылки на фото…'; return call('finalize');})
  .then(function(d){(d.log||[]).forEach(function(l){add(l.t,l.e);}); st.textContent='Перенос завершён ✔'; add('Готово. Откройте сайт и проверьте страницы. Плагин можно деактивировать и удалить.');})
  .catch(function(e){st.textContent='Остановлено: '+e.message; add(e.message,true);})
  .then(function(){btn.disabled=false; btn.textContent='Запустить ещё раз';});
 });
})();
JS;
}

/* =====================================================================
 * AJAX-шаги
 * ===================================================================*/

add_action( 'wp_ajax_sfmig_step', 'sfmig_ajax' );
/**
 * Обработчик шагов импорта.
 */
function sfmig_ajax() {
	check_ajax_referer( 'sfmig', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Недостаточно прав.' ), 403 );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- на части хостингов функция запрещена.
	}
	$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
	switch ( $step ) {
		case 'content':
			wp_send_json_success( array( 'log' => sfmig_import_content() ) );
			break;
		case 'missing':
			wp_send_json_success( array( 'log' => sfmig_import_missing() ) );
			break;
		case 'media':
			wp_send_json_success( sfmig_import_media_batch() );
			break;
		case 'fonts':
			wp_send_json_success( array( 'log' => sfmig_import_fonts() ) );
			break;
		case 'finalize':
			wp_send_json_success( array( 'log' => sfmig_finalize() ) );
			break;
	}
	wp_send_json_error( array( 'message' => 'Неизвестный шаг.' ), 400 );
}

/**
 * Запись в журнал ошибок.
 *
 * @param string $msg Текст.
 */
function sfmig_error( $msg ) {
	$e   = (array) get_option( 'sfmig_errors', array() );
	$e[] = $msg;
	update_option( 'sfmig_errors', array_slice( $e, -200 ), false );
}

/**
 * Обновление настроек темы Safronova (меню, логотип) — даже если тема ещё не активна.
 *
 * @param string $key   Ключ.
 * @param mixed  $value Значение.
 */
function sfmig_theme_mod( $key, $value ) {
	if ( sfmig_theme_active() ) {
		set_theme_mod( $key, $value );
		return;
	}
	$mods         = (array) get_option( 'theme_mods_' . SFMIG_THEME, array() );
	$mods[ $key ] = $value;
	update_option( 'theme_mods_' . SFMIG_THEME, $mods );
}

/* ---------- 1. Страницы, записи, меню, настройки ---------- */

/**
 * Создать или обновить запись.
 *
 * @param array  $item Данные.
 * @param string $type page|post.
 * @param array  $extra Доп. поля wp_insert_post.
 * @return int
 */
function sfmig_upsert( $item, $type, $extra = array() ) {
	$existing = get_page_by_path( $item['slug'], OBJECT, $type );
	$post     = array_merge(
		array(
			'post_type'      => $type,
			'post_status'    => 'publish',
			'post_title'     => wp_slash( $item['title'] ),
			'post_name'      => $item['slug'],
			'post_content'   => wp_slash( $item['content'] ),
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		),
		$extra
	);
	if ( $existing ) {
		$post['ID'] = $existing->ID;
		$id         = wp_update_post( $post, true );
	} else {
		$id = wp_insert_post( $post, true );
	}
	if ( is_wp_error( $id ) ) {
		sfmig_error( $item['slug'] . ': ' . $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_sf_mig', 1 );
	update_post_meta( $id, '_sf_seo_title', sanitize_text_field( $item['seo_title'] ?? '' ) );
	update_post_meta( $id, '_sf_seo_desc', sanitize_textarea_field( $item['seo_desc'] ?? '' ) );
	return (int) $id;
}

/**
 * Импорт контента.
 *
 * @return array Журнал.
 */
function sfmig_import_content() {
	$d   = sfmig_data();
	$log = array();
	if ( empty( $d['pages'] ) ) {
		return array( array( 't' => 'Файл data/content.json не найден или пуст.', 'e' => 1 ) );
	}

	// Стандартные «Привет, мир!» и «Пример страницы» — в корзину, если их не редактировали.
	foreach ( array( 1, 2 ) as $demo_id ) {
		$p = get_post( $demo_id );
		if ( $p && ! get_post_meta( $demo_id, '_sf_mig', true ) && 'trash' !== $p->post_status && $p->post_modified_gmt === $p->post_date_gmt ) {
			wp_trash_post( $demo_id );
			$log[] = array( 't' => 'Демо-запись «' . $p->post_title . '» перемещена в корзину' );
		}
	}

	// Фото, которые не скачались в прошлый раз, попробуем снова.
	update_option( 'sfmig_media_map', array_filter( (array) get_option( 'sfmig_media_map', array() ) ), false );

	$ids = array();
	foreach ( $d['pages'] as $pg ) {
		$ids[ $pg['slug'] ] = sfmig_upsert( $pg, 'page', array( 'menu_order' => (int) $pg['order'] ) );
		$log[]              = array( 't' => 'Страница: ' . $pg['title'] . ' → /' . ( 'home' === $pg['slug'] ? '' : $pg['slug'] ) );
	}

	$now = time();
	foreach ( (array) $d['posts'] as $ps ) {
		$ts = $now - ( (int) $ps['date_offset_days'] + 1 ) * DAY_IN_SECONDS;
		$id = sfmig_upsert(
			$ps,
			'post',
			array(
				'post_date'     => wp_date( 'Y-m-d H:i:s', $ts ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $ts ),
			)
		);
		if ( $id && ! empty( $ps['featured'] ) ) {
			update_post_meta( $id, '_sf_featured_src', esc_url_raw( $ps['featured'] ) );
		}
		$log[] = array( 't' => 'Запись блога: ' . $ps['title'] );
	}

	// Главная, блог, адреса как на Vigbo: /pregnancy, /blog, /blog/<запись>.
	if ( ! empty( $ids['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
	}
	if ( ! empty( $ids['blog'] ) ) {
		update_option( 'page_for_posts', $ids['blog'] );
	}
	update_option( 'posts_per_page', 9 );
	// Адреса записей как на Vigbo: /blog/<запись>. set_permalink_structure обновляет и настройку,
	// и объект правил в памяти — иначе сброс правил ниже построит их по старому формату.
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '/blog/%postname%/' );
	update_option( 'blogname', 'Елена Сафронова' );
	update_option( 'blogdescription', 'Фотограф беременности и новорожденных в Киеве' );
	update_option( 'sf_contact_email', 'info.elenasafronova@gmail.com' );
	// Facebook Pixel, который стоял на сайте Vigbo (виден в коде страниц safronova.com.ua).
	if ( ! get_option( 'sf_fb_pixel' ) ) {
		update_option( 'sf_fb_pixel', '216643308703754' );
	}
	update_option( 'default_comment_status', 'closed' );

	sfmig_build_menus( $ids );
	$log[] = array( 't' => 'Меню созданы: ПОРТФОЛІО (3 подпункта) | БЛОГ | ЦІНИ — логотип — ПИТАННЯ/ВІДПОВІДЬ | ВІДГУКИ | КОНТАКТИ' );

	flush_rewrite_rules();
	delete_option( 'sfmig_finished' );
	update_option( 'sfmig_content_done', time(), false );
	return $log;
}

/**
 * Режим «Продолжить»: создаёт только те страницы и записи, которых ещё нет
 * (например, скрытые страницы, добавленные в копию позже). Существующие — не трогает.
 *
 * @return array Журнал.
 */
function sfmig_import_missing() {
	$d   = sfmig_data();
	$log = array();
	foreach ( (array) ( $d['pages'] ?? array() ) as $pg ) {
		if ( get_page_by_path( $pg['slug'], OBJECT, 'page' ) ) {
			continue;
		}
		if ( sfmig_upsert( $pg, 'page', array( 'menu_order' => (int) $pg['order'] ) ) ) {
			$log[] = array( 't' => 'Новая страница: ' . $pg['title'] . ' → /' . $pg['slug'] . ( empty( $pg['hidden'] ) ? '' : ' (без пункта в меню, как на Vigbo)' ) );
		}
	}
	foreach ( (array) ( $d['posts'] ?? array() ) as $ps ) {
		if ( get_page_by_path( $ps['slug'], OBJECT, 'post' ) ) {
			continue;
		}
		$id = sfmig_upsert( $ps, 'post' );
		if ( $id && ! empty( $ps['featured'] ) ) {
			update_post_meta( $id, '_sf_featured_src', esc_url_raw( $ps['featured'] ) );
		}
		$log[] = array( 't' => 'Новая запись блога: ' . $ps['title'] );
	}
	update_option( 'sfmig_media_map', array_filter( (array) get_option( 'sfmig_media_map', array() ) ), false );
	delete_option( 'sfmig_finished' );
	if ( ! $log ) {
		$log[] = array( 't' => 'Все страницы уже на месте — докачиваю только недостающие фото' );
	}
	return $log;
}

/**
 * Меню слева и справа от логотипа.
 *
 * @param array $ids slug => ID страниц.
 */
function sfmig_build_menus( $ids ) {
	$menus = array(
		'sf-left'  => array(
			'name'  => 'Меню слева',
			'items' => array(
				array( 'ПОРТФОЛІО', 'pregnancy', array(
					array( 'Фотосесія вагітних', 'pregnancy' ),
					array( 'Фотосесія з малюком', 'mothersandkids' ),
					array( 'Підводна фотосесія', 'podvodnye-fotosessii' ),
				) ),
				array( 'БЛОГ', 'blog', array() ),
				array( 'ЦІНИ', 'tseny-new', array() ),
			),
		),
		'sf-right' => array(
			'name'  => 'Меню справа',
			'items' => array(
				array( 'ПИТАННЯ/ВІДПОВІДЬ', 'faq', array() ),
				array( 'ВІДГУКИ', 'reviews', array() ),
				array( 'КОНТАКТИ', 'contacts', array() ),
			),
		),
	);
	$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
	if ( ! sfmig_theme_active() ) {
		$mods      = (array) get_option( 'theme_mods_' . SFMIG_THEME, array() );
		$locations = isset( $mods['nav_menu_locations'] ) ? (array) $mods['nav_menu_locations'] : array();
	}

	foreach ( $menus as $loc => $m ) {
		$obj     = wp_get_nav_menu_object( $m['name'] );
		$menu_id = $obj ? (int) $obj->term_id : (int) wp_create_nav_menu( $m['name'] );
		if ( ! $menu_id || is_wp_error( $menu_id ) ) {
			sfmig_error( 'Не удалось создать меню ' . $m['name'] );
			continue;
		}
		foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $old ) {
			wp_delete_post( $old->ID, true );
		}
		$pos = 0;
		foreach ( $m['items'] as $it ) {
			$parent = sfmig_menu_item( $menu_id, $it[0], $ids[ $it[1] ] ?? 0, 0, ++$pos );
			foreach ( $it[2] as $child ) {
				sfmig_menu_item( $menu_id, $child[0], $ids[ $child[1] ] ?? 0, $parent, ++$pos );
			}
		}
		$locations[ $loc ] = $menu_id;
	}
	sfmig_theme_mod( 'nav_menu_locations', $locations );
}

/**
 * Пункт меню, ведущий на страницу.
 *
 * @param int    $menu_id ID меню.
 * @param string $title   Подпись.
 * @param int    $page_id ID страницы.
 * @param int    $parent  ID родительского пункта.
 * @param int    $pos     Позиция.
 * @return int
 */
function sfmig_menu_item( $menu_id, $title, $page_id, $parent, $pos ) {
	if ( ! $page_id ) {
		return 0;
	}
	$id = wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-title'     => $title,
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $page_id,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent,
			'menu-item-position'  => $pos,
		)
	);
	return is_wp_error( $id ) ? 0 : (int) $id;
}

/* ---------- 2. Фото ---------- */

/**
 * Загрузка следующей пачки фото.
 *
 * @return array
 */
function sfmig_import_media_batch() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$list  = sfmig_media_list();
	$map   = (array) get_option( 'sfmig_media_map', array() );
	$log   = array();
	$count = 0;

	foreach ( $list as $url ) {
		// уже загружено или уже пробовали в этом запуске (0 = ошибка, повтор при следующем запуске)
		if ( isset( $map[ $url ] ) && ( 0 === (int) $map[ $url ] || get_post( (int) $map[ $url ] ) ) ) {
			continue;
		}
		if ( $count >= SFMIG_BATCH ) {
			break;
		}
		++$count;
		$id = sfmig_sideload( $url );
		if ( $id ) {
			$map[ $url ] = $id;
			update_option( 'sfmig_media_map', $map, false );
			$d = sfmig_data();
			if ( ! empty( $d['logo'] ) && $d['logo'] === $url ) {
				sfmig_theme_mod( 'custom_logo', $id );
			}
			$log[] = array( 't' => 'Фото: ' . wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		} else {
			$map[ $url ] = 0; // пропускаем, чтобы не зациклиться; ссылка останется на Vigbo
			update_option( 'sfmig_media_map', $map, false );
			$log[] = array( 't' => 'Не удалось загрузить: ' . $url, 'e' => 1 );
		}
	}

	$done = 0;
	foreach ( $list as $url ) {
		if ( isset( $map[ $url ] ) ) {
			++$done;
		}
	}
	return array(
		'done'     => $done,
		'total'    => count( $list ),
		'finished' => $done >= count( $list ),
		'log'      => $log,
	);
}

/**
 * Скачать один файл (сначала из папки media/ плагина, затем с Vigbo) и добавить в медиатеку.
 *
 * @param string $url Адрес на Vigbo.
 * @return int ID вложения или 0.
 */
function sfmig_sideload( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$rel  = ltrim( str_replace( '/u44468/57218/', '', $path ), '/' );
	$name = sanitize_file_name( wp_basename( $path ) );
	if ( 0 === strpos( $name, 'u-03e3a38a' ) ) {
		$name = 'logo-safronova.jpg';
	}

	$local = SFMIG_DIR . 'media/' . $rel;
	$tmp   = '';
	if ( $rel && false === strpos( $rel, '..' ) && file_exists( $local ) ) {
		$tmp = wp_tempnam( $name );
		if ( ! copy( $local, $tmp ) ) {
			$tmp = '';
		}
	}
	if ( ! $tmp ) {
		$dl = download_url( $url, 90 );
		if ( is_wp_error( $dl ) ) {
			sfmig_error( 'Загрузка ' . $url . ': ' . $dl->get_error_message() );
			return 0;
		}
		$tmp = $dl;
	}

	$id = media_handle_sideload(
		array(
			'name'     => $name,
			'tmp_name' => $tmp,
		),
		0
	);
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		sfmig_error( 'Медиатека ' . $name . ': ' . $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_sf_vigbo_src', esc_url_raw( $url ) );
	return (int) $id;
}

/* ---------- 3. Шрифты ---------- */

/**
 * Скачать шрифты в тему (или в uploads/safronova/fonts, если тема закрыта для записи).
 *
 * @return array Журнал.
 */
function sfmig_import_fonts() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$log   = array();
	$theme = ( sfmig_theme_active() ? get_template_directory() : get_theme_root() . '/' . SFMIG_THEME ) . '/assets/fonts/';
	$up    = wp_get_upload_dir();
	$alt   = trailingslashit( $up['basedir'] ) . 'safronova/fonts/';
	foreach ( array( 'firasans-regular.woff', 'playfairdisplay-regular.woff', 'opensans-regular.woff' ) as $f ) {
		if ( file_exists( $theme . $f ) || file_exists( $alt . $f ) ) {
			$log[] = array( 't' => 'Шрифт уже на месте: ' . $f );
			continue;
		}
		$tmp = download_url( SFMIG_FONTS_CDN . $f, 60 );
		if ( is_wp_error( $tmp ) ) {
			$log[] = array( 't' => 'Шрифт ' . $f . ': ' . $tmp->get_error_message(), 'e' => 1 );
			continue;
		}
		$dest = ( is_dir( $theme ) && wp_is_writable( $theme ) ) ? $theme : $alt;
		wp_mkdir_p( $dest );
		if ( copy( $tmp, $dest . $f ) ) {
			$log[] = array( 't' => 'Шрифт установлен: ' . $f );
		} else {
			$log[] = array( 't' => 'Не удалось сохранить шрифт ' . $f, 'e' => 1 );
		}
		wp_delete_file( $tmp );
	}
	return $log;
}

/* ---------- 4. Замена ссылок, обложки, логотип ---------- */

/**
 * Заменить адреса Vigbo в содержимом на файлы медиатеки.
 *
 * @param string $content Содержимое записи.
 * @param array  $map     URL => ID вложения.
 * @return string
 */
function sfmig_rewrite_content( $content, $map ) {
	// Блоки изображений: добавляем ID вложения, чтобы WordPress выдавал адаптивные размеры (srcset).
	$content = preg_replace_callback(
		'#<!-- wp:image (\{[^}]*\}) -->(\s*<figure[^>]*>(?:<a [^>]*>)?)<img src="([^"]+)"#u',
		function ( $m ) use ( $map ) {
			$src = html_entity_decode( $m[3] );
			if ( empty( $map[ $src ] ) ) {
				return $m[0];
			}
			$id    = (int) $map[ $src ];
			$url   = wp_get_attachment_url( $id );
			$attrs = json_decode( $m[1], true );
			if ( ! $url || ! is_array( $attrs ) ) {
				return $m[0];
			}
			$attrs['id'] = $id;
			$json        = wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			return '<!-- wp:image ' . $json . ' -->' . $m[2] . '<img src="' . esc_url( $url ) . '" class="wp-image-' . $id . '"';
		},
		$content
	);
	// Всё остальное (если встретится) — простой заменой адреса.
	foreach ( $map as $src => $id ) {
		if ( $id && false !== strpos( $content, $src ) ) {
			$url = wp_get_attachment_url( (int) $id );
			if ( $url ) {
				$content = str_replace( $src, $url, $content );
			}
		}
	}
	return $content;
}

/**
 * Финальный шаг.
 *
 * @return array Журнал.
 */
function sfmig_finalize() {
	$map = array_filter( (array) get_option( 'sfmig_media_map', array() ) );
	$log = array();
	$q   = new WP_Query(
		array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => '_sf_mig', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- разовый импорт.
			'no_found_rows'  => true,
		)
	);
	foreach ( $q->posts as $p ) {
		$new = sfmig_rewrite_content( $p->post_content, $map );
		if ( $new !== $p->post_content ) {
			wp_update_post(
				array(
					'ID'           => $p->ID,
					'post_content' => wp_slash( $new ),
				)
			);
		}
		$feat = (string) get_post_meta( $p->ID, '_sf_featured_src', true );
		if ( $feat && ! empty( $map[ $feat ] ) ) {
			set_post_thumbnail( $p->ID, (int) $map[ $feat ] );
		}
	}
	$log[] = array( 't' => 'Ссылки на фото заменены в ' . count( $q->posts ) . ' страницах и записях' );

	$d = sfmig_data();
	if ( ! empty( $d['logo'] ) && ! empty( $map[ $d['logo'] ] ) ) {
		sfmig_theme_mod( 'custom_logo', (int) $map[ $d['logo'] ] );
		$log[] = array( 't' => 'Логотип установлен' );
	}

	$left = 0;
	foreach ( $q->posts as $p ) {
		$c = get_post_field( 'post_content', $p->ID );
		if ( false !== strpos( (string) $c, 'vigbo.tech' ) ) {
			++$left;
		}
	}
	$log[] = $left
		? array( 't' => 'Внимание: в ' . $left . ' страницах остались ссылки на Vigbo (фото не скачались). Запустите перенос ещё раз.', 'e' => 1 )
		: array( 't' => 'Ссылок на Vigbo не осталось — сайт полностью независим' );

	flush_rewrite_rules( true ); // true — заодно обновить .htaccess на Apache-хостинге
	update_option( 'sfmig_finished', time() );
	return $log;
}
