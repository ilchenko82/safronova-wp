<?php
/**
 * Админка «как в Vigbo»: панель «Управление сайтом» на главном экране,
 * ничего лишнего в меню, автоматическая обложка записи, без комментариев,
 * страница входа с логотипом сайта.
 *
 * @package safronova
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ID страницы по её адресу (slug).
 *
 * @param string $slug Адрес.
 * @return int
 */
function safronova_page_id( $slug ) {
	$p = get_page_by_path( $slug );
	return $p ? (int) $p->ID : 0;
}

/**
 * Ссылка на редактирование страницы по адресу.
 *
 * @param string $slug Адрес.
 * @return string
 */
function safronova_edit_page_url( $slug ) {
	$id = safronova_page_id( $slug );
	return $id ? (string) get_edit_post_link( $id, 'raw' ) : admin_url( 'edit.php?post_type=page' );
}

/* ---------- Главный экран админки ---------- */

add_action( 'wp_dashboard_setup', 'safronova_dashboard', 1 );
/**
 * Убираем стандартные виджеты WordPress и ставим свою панель.
 */
function safronova_dashboard() {
	remove_action( 'welcome_panel', 'wp_welcome_panel' );
	foreach ( array(
		'dashboard_primary'         => 'side',
		'dashboard_quick_press'     => 'side',
		'dashboard_site_health'     => 'normal',
		'dashboard_activity'        => 'normal',
		'dashboard_right_now'       => 'normal',
		'dashboard_php_nag'         => 'normal',
		'dashboard_recent_comments' => 'normal',
	) as $id => $ctx ) {
		remove_meta_box( $id, 'dashboard', $ctx );
	}
	wp_add_dashboard_widget( 'sf_dashboard', 'Управление сайтом', 'safronova_dashboard_html' );
	wp_add_dashboard_widget( 'sf_dashboard_help', 'Подсказки', 'safronova_dashboard_help_html' );
}

/**
 * Большие кнопки быстрых действий.
 */
function safronova_dashboard_html() {
	$leads = function_exists( 'safronova_new_leads_count' ) ? safronova_new_leads_count() : 0;
	$items = array(
		array( 'dashicons-edit-page', 'Новая запись в блог', admin_url( 'post-new.php' ), 'Фотосессия: заголовок, фото, обложка' ),
		array( 'dashicons-format-gallery', 'Фотосесія вагітних', safronova_edit_page_url( 'pregnancy' ), 'Добавить или убрать фото в галерее' ),
		array( 'dashicons-format-gallery', 'Фотосесія з малюком', safronova_edit_page_url( 'mothersandkids' ), 'Галерея портфолио' ),
		array( 'dashicons-format-gallery', 'Підводна фотосесія', safronova_edit_page_url( 'podvodnye-fotosessii' ), 'Галерея портфолио' ),
		array( 'dashicons-slides', 'Слайдер на главной', safronova_edit_page_url( 'home' ), 'Фото, которые сменяются на главной' ),
		array( 'dashicons-money-alt', 'Цены', safronova_edit_page_url( 'tseny-new' ), 'Пакеты и стоимость' ),
		array( 'dashicons-format-quote', 'Отзывы', safronova_edit_page_url( 'reviews' ), 'Добавить новый отзыв' ),
		array( 'dashicons-editor-help', 'Вопросы и ответы', safronova_edit_page_url( 'faq' ), 'Страница FAQ' ),
		array( 'dashicons-email-alt', 'Заявки с сайта' . ( $leads ? ' (' . $leads . ' новых)' : '' ), admin_url( 'edit.php?post_type=sf_lead' ), 'Сообщения из формы «Контакти»' ),
		array( 'dashicons-admin-media', 'Все фото', admin_url( 'upload.php' ), 'Медиатека' ),
		array( 'dashicons-menu', 'Меню', admin_url( 'nav-menus.php' ), 'Пункты меню в шапке' ),
		array( 'dashicons-visibility', 'Открыть сайт', home_url( '/' ), 'Посмотреть, как видят посетители' ),
	);
	echo '<div class="sf-dash">';
	foreach ( $items as $it ) {
		$target = ( home_url( '/' ) === $it[2] ) ? ' target="_blank" rel="noopener"' : '';
		echo '<a class="sf-dash__btn" href="' . esc_url( $it[2] ) . '"' . $target . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $target — статичная строка.
		echo '<span class="dashicons ' . esc_attr( $it[0] ) . '"></span>';
		echo '<strong>' . esc_html( $it[1] ) . '</strong><small>' . esc_html( $it[3] ) . '</small></a>';
	}
	echo '</div>';
}

/**
 * Короткие подсказки.
 */
function safronova_dashboard_help_html() {
	echo '<ul class="sf-dash-help">';
	echo '<li><strong>Новая запись в блог:</strong> «Новая запись в блог» → заголовок → нажмите «+» и выберите в «Паттернах» → «Сафронова» готовый блок «Запись блога: фото». Обложка в ленте блога подставится сама из первой фотографии (или задайте её справа: «Изображение записи»).</li>';
	echo '<li><strong>Фото в портфолио:</strong> откройте страницу галереи → нажмите на галерею → «Добавить» → выберите фото. Порядок меняется перетаскиванием. Клик по фото на сайте откроет его на весь экран.</li>';
	echo '<li><strong>Новый отзыв:</strong> страница «Отзывы» → «+» → «Паттерны» → «Сафронова» → «Отзыв» → замените фото, имя и текст.</li>';
	echo '<li><strong>Не забывайте</strong> нажимать синюю кнопку «Обновить» / «Опубликовать» справа вверху.</li>';
	echo '<li>Сообщения из формы приходят на почту и сохраняются в разделе «Заявки».</li>';
	echo '</ul>';
}

add_action( 'admin_head-index.php', 'safronova_dashboard_css' );
/**
 * Стили панели.
 */
function safronova_dashboard_css() {
	?>
	<style>
		#sf_dashboard { grid-column: 1 / -1; }
		.sf-dash { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; }
		.sf-dash__btn { display: flex; flex-direction: column; gap: 4px; padding: 16px; border: 1px solid #dcdcde; border-radius: 8px; background: #fff; text-decoration: none; color: #1d2327; transition: border-color .2s, box-shadow .2s; }
		.sf-dash__btn:hover { border-color: #2271b1; box-shadow: 0 2px 8px rgba(0,0,0,.08); color: #2271b1; }
		.sf-dash__btn .dashicons { font-size: 28px; width: 28px; height: 28px; color: #2271b1; margin-bottom: 4px; }
		.sf-dash__btn strong { font-size: 14px; }
		.sf-dash__btn small { color: #646970; }
		.sf-dash-help li { margin-bottom: 10px; line-height: 1.5; }
	</style>
	<?php
}

add_action( 'admin_init', 'safronova_dashboard_layout' );
/**
 * Панель — во всю ширину, одной колонкой.
 */
function safronova_dashboard_layout() {
	$uid = get_current_user_id();
	if ( $uid && ! get_user_meta( $uid, 'sf_dash_layout', true ) ) {
		update_user_meta( $uid, 'screen_layout_dashboard', 1 );
		update_user_meta( $uid, 'meta-box-order_dashboard', array( 'normal' => 'sf_dashboard,sf_dashboard_help' ) );
		update_user_meta( $uid, 'sf_dash_layout', 1 );
	}
}

/* ---------- Комментарии выключены полностью ---------- */

add_action( 'init', 'safronova_disable_comments', 100 );
/**
 * Убираем поддержку комментариев у записей и страниц.
 */
function safronova_disable_comments() {
	foreach ( array( 'post', 'page', 'attachment' ) as $t ) {
		remove_post_type_support( $t, 'comments' );
		remove_post_type_support( $t, 'trackbacks' );
	}
}
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 10 );

add_action( 'admin_menu', 'safronova_admin_menu_cleanup', 999 );
/**
 * Лишние пункты меню админки.
 */
function safronova_admin_menu_cleanup() {
	remove_menu_page( 'edit-comments.php' );
	if ( ! current_user_can( 'manage_options' ) ) {
		remove_menu_page( 'tools.php' );
	}
}

add_action( 'wp_before_admin_bar_render', 'safronova_admin_bar_cleanup' );
/**
 * Без значка комментариев и логотипа WordPress в верхней панели.
 */
function safronova_admin_bar_cleanup() {
	global $wp_admin_bar;
	if ( $wp_admin_bar ) {
		$wp_admin_bar->remove_node( 'comments' );
		$wp_admin_bar->remove_node( 'wp-logo' );
	}
}

/* ---------- Обложка записи из первой фотографии ---------- */

add_action( 'save_post_post', 'safronova_auto_thumbnail', 20, 2 );
/**
 * Если обложка (изображение записи) не задана — берём первое фото из записи.
 *
 * @param int     $post_id ID записи.
 * @param WP_Post $post    Запись.
 */
function safronova_auto_thumbnail( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || has_post_thumbnail( $post_id ) ) {
		return;
	}
	if ( preg_match( '/wp-image-(\d+)/', (string) $post->post_content, $m ) || preg_match( '/"id":(\d+)/', (string) $post->post_content, $m ) ) {
		$att = (int) $m[1];
		if ( $att && wp_attachment_is_image( $att ) ) {
			set_post_thumbnail( $post_id, $att );
		}
	}
}

/* ---------- Страница входа ---------- */

add_action( 'login_enqueue_scripts', 'safronova_login_logo' );
/**
 * Логотип сайта вместо логотипа WordPress.
 */
function safronova_login_logo() {
	$url = '';
	$id  = (int) get_theme_mod( 'custom_logo' );
	if ( $id ) {
		$url = (string) wp_get_attachment_image_url( $id, 'full' );
	} elseif ( file_exists( SAFRONOVA_DIR . '/assets/img/logo.jpg' ) ) {
		$url = SAFRONOVA_URI . '/assets/img/logo.jpg';
	}
	if ( ! $url ) {
		return;
	}
	echo '<style>#login h1 a{background-image:url(' . esc_url( $url ) . ');background-size:contain;width:170px;height:154px;}body.login{background:#fff;}</style>';
}
add_filter( 'login_headerurl', 'safronova_login_url' );
/**
 * Ссылка логотипа на странице входа.
 *
 * @return string
 */
function safronova_login_url() {
	return home_url( '/' );
}

/* ---------- Права редактора ---------- */

add_action( 'admin_init', 'safronova_editor_caps' );
/**
 * Пользователь с ролью «Редактор» может менять меню (как владелец сайта в Vigbo),
 * но не может ставить плагины и менять тему.
 */
function safronova_editor_caps() {
	$role = get_role( 'editor' );
	if ( $role && ! $role->has_cap( 'edit_theme_options' ) ) {
		$role->add_cap( 'edit_theme_options' );
	}
}

add_action( 'admin_footer_text', 'safronova_admin_footer' );
/**
 * Подпись внизу админки.
 *
 * @return string
 */
function safronova_admin_footer() {
	return 'Сайт Елены Сафроновой · вопросы по сайту — в «Подсказки» на главном экране админки';
}
