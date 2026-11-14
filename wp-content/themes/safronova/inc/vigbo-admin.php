<?php
/**
 * Админка в стиле Vigbo: шапка с вкладками САЙТ · ОФОРМЛЕНИЕ · НАСТРОЙКИ · СТАТИСТИКА,
 * нижняя панель на телефоне, экраны «Сайт», «Оформление», «Настройки», «Статистика».
 * Можно выключить («Классический вид WordPress») — у каждого пользователя своя настройка.
 *
 * @package Safronova
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Включён ли вид Vigbo для текущего пользователя (по умолчанию — да).
 *
 * @return bool
 */
function safronova_skin_on() {
	return is_user_logged_in() && '0' !== get_user_meta( get_current_user_id(), 'sf_vigbo_skin', true );
}

/**
 * Текущий экран — полноэкранный редактор (блоков или сайта)?
 *
 * @return bool
 */
function safronova_is_fullscreen_editor() {
	global $pagenow;
	if ( 'site-editor.php' === $pagenow || 'customize.php' === $pagenow ) {
		return true;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();
}

/**
 * Вкладки шапки.
 *
 * @return array key => [подпись, ссылка, иконка]
 */
function safronova_skin_tabs() {
	$tabs = array(
		'site'   => array( 'Сайт', admin_url( 'admin.php?page=sf-site' ), 'dashicons-admin-page' ),
		'design' => array( 'Оформление', admin_url( 'admin.php?page=sf-design' ), 'dashicons-art' ),
	);
	if ( current_user_can( 'manage_options' ) ) {
		$tabs['settings'] = array( 'Настройки', admin_url( 'admin.php?page=sf-settings' ), 'dashicons-admin-generic' );
	}
	$tabs['stats'] = array( 'Статистика', admin_url( 'admin.php?page=sf-stats' ), 'dashicons-chart-bar' );
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		unset( $tabs['design'] );
	}
	return $tabs;
}

/**
 * Какая вкладка активна на текущем экране.
 *
 * @return string
 */
function safronova_skin_current_tab() {
	global $pagenow;
	$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только выбор вкладки.
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'sf_lead' === $screen->post_type ) {
		return 'leads';
	}
	if ( in_array( $page, array( 'sf-design' ), true ) || in_array( $pagenow, array( 'nav-menus.php', 'themes.php', 'theme-editor.php' ), true ) ) {
		return 'design';
	}
	if ( 'sf-stats' === $page ) {
		return 'stats';
	}
	if ( 'sf-settings' === $page || 'sf-analytics' === $page || 0 === strpos( (string) $pagenow, 'options' )
		|| in_array( $pagenow, array( 'users.php', 'user-new.php', 'user-edit.php', 'profile.php', 'plugins.php', 'plugin-install.php', 'update-core.php', 'tools.php', 'import.php', 'export.php' ), true ) ) {
		return 'settings';
	}
	return 'site';
}

/* ---------------------------------------------------------------------
 * Пункты меню (в классическом виде они тоже видны в левом меню WordPress)
 * ------------------------------------------------------------------- */

add_action( 'admin_menu', 'safronova_skin_pages', 5 );
/**
 * Регистрирует экраны.
 */
function safronova_skin_pages() {
	add_menu_page( 'Сайт', 'Сайт (как в Vigbo)', 'edit_pages', 'sf-site', 'safronova_screen_site', 'dashicons-admin-home', 2 );
	add_submenu_page( 'sf-site', 'Страницы сайта', 'Страницы и меню', 'edit_pages', 'sf-site', 'safronova_screen_site' );
	add_submenu_page( 'sf-site', 'Оформление', 'Оформление', 'edit_theme_options', 'sf-design', 'safronova_screen_design' );
	add_submenu_page( 'sf-site', 'Настройки', 'Настройки', 'manage_options', 'sf-settings', 'safronova_screen_settings' );
	add_submenu_page( 'sf-site', 'Статистика', 'Статистика', 'edit_pages', 'sf-stats', 'safronova_screen_stats' );
}

/* ---------------------------------------------------------------------
 * Стили, скрипты, классы
 * ------------------------------------------------------------------- */

add_action( 'admin_enqueue_scripts', 'safronova_skin_assets' );
/**
 * Подключает оформление админки.
 *
 * @param string $hook Экран.
 */
function safronova_skin_assets( $hook ) {
	if ( ! safronova_skin_on() ) {
		return;
	}
	wp_enqueue_style( 'safronova-admin', SAFRONOVA_URI . '/assets/css/admin-vigbo.css', array( 'dashicons' ), SAFRONOVA_VERSION );
	wp_add_inline_style( 'safronova-admin', safronova_font_css() );

	if ( 'toplevel_page_sf-site' === $hook ) {
		wp_enqueue_script( 'safronova-admin-site', SAFRONOVA_URI . '/assets/js/admin-site.js', array( 'jquery', 'jquery-ui-sortable' ), SAFRONOVA_VERSION, true );
		wp_localize_script(
			'safronova-admin-site',
			'sfSite',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'sf_site_menus' ),
			)
		);
	}
	if ( false !== strpos( (string) $hook, 'sf-design' ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'safronova-admin-design', SAFRONOVA_URI . '/assets/js/admin-design.js', array( 'jquery' ), SAFRONOVA_VERSION, true );
	}
}

add_action( 'enqueue_block_editor_assets', 'safronova_dock_assets' );
/**
 * Панель виджетов как в Vigbo — в редакторе страниц и записей.
 */
function safronova_dock_assets() {
	if ( ! safronova_skin_on() ) {
		return;
	}
	global $pagenow;
	if ( 'site-editor.php' === $pagenow || 'widgets.php' === $pagenow ) {
		return;
	}
	wp_enqueue_style( 'safronova-dock', SAFRONOVA_URI . '/assets/css/admin-vigbo.css', array( 'dashicons' ), SAFRONOVA_VERSION );
	wp_enqueue_script(
		'safronova-dock',
		SAFRONOVA_URI . '/assets/js/editor-dock.js',
		array( 'wp-plugins', 'wp-element', 'wp-data', 'wp-blocks', 'wp-block-editor', 'wp-editor', 'wp-dom-ready' ),
		SAFRONOVA_VERSION,
		true
	);
}

add_filter( 'admin_body_class', 'safronova_skin_body_class' );
/**
 * Класс для CSS.
 *
 * @param string $classes Классы.
 * @return string
 */
function safronova_skin_body_class( $classes ) {
	if ( safronova_skin_on() ) {
		$classes .= ' sfa sfa-tab-' . safronova_skin_current_tab();
		if ( safronova_is_fullscreen_editor() ) {
			$classes .= ' sfa-editor';
		}
	}
	return $classes;
}

/* ---------------------------------------------------------------------
 * Шапка и нижняя панель
 * ------------------------------------------------------------------- */

add_action( 'in_admin_header', 'safronova_skin_header', 1 );
/**
 * Выводит шапку в стиле Vigbo.
 */
function safronova_skin_header() {
	if ( ! safronova_skin_on() || safronova_is_fullscreen_editor() ) {
		return;
	}
	$tabs    = safronova_skin_tabs();
	$current = safronova_skin_current_tab();
	$leads   = function_exists( 'safronova_new_leads_count' ) ? safronova_new_leads_count() : 0;
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	$user    = wp_get_current_user();
	$off_url = wp_nonce_url( admin_url( 'admin-post.php?action=sf_skin&on=0' ), 'sf_skin' );
	?>
	<header class="sfa-top" role="banner">
		<a class="sfa-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=sf-site' ) ); ?>">
			<?php
			if ( $logo_id ) {
				echo wp_get_attachment_image( $logo_id, 'medium', false, array( 'alt' => esc_attr( get_bloginfo( 'name' ) ) ) );
			} else {
				echo esc_html( get_bloginfo( 'name' ) );
			}
			?>
		</a>
		<nav class="sfa-tabs" aria-label="Разделы">
			<?php foreach ( $tabs as $key => $t ) : ?>
				<a class="sfa-tab<?php echo $key === $current ? ' is-active' : ''; ?>" href="<?php echo esc_url( $t[1] ); ?>"><?php echo esc_html( $t[0] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="sfa-right">
			<a class="sfa-leads<?php echo 'leads' === $current ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'edit.php?post_type=sf_lead' ) ); ?>">
				Заявки<?php if ( $leads ) : ?> <span class="sfa-badge"><?php echo esc_html( (string) $leads ); ?></span><?php endif; ?>
			</a>
			<a class="sfa-btn sfa-btn--ghost sfa-open" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external"></span><span>Открыть сайт</span></a>
			<details class="sfa-user">
				<summary aria-label="Профиль"><?php echo get_avatar( $user->ID, 32 ); ?></summary>
				<div class="sfa-user__menu">
					<strong><?php echo esc_html( $user->display_name ); ?></strong>
					<a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>">Профиль и пароль</a>
					<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">Все фото и файлы</a>
					<a href="<?php echo esc_url( $off_url ); ?>">Классический вид WordPress</a>
					<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Выйти</a>
				</div>
			</details>
		</div>
	</header>
	<nav class="sfa-bottom" aria-label="Разделы">
		<?php
		$bottom = $tabs;
		$bottom = array_slice( $bottom, 0, 2, true ) + array( 'leads' => array( 'Заявки', admin_url( 'edit.php?post_type=sf_lead' ), 'dashicons-email-alt' ) ) + array_slice( $bottom, 2, null, true );
		foreach ( $bottom as $key => $t ) :
			?>
			<a class="<?php echo $key === $current ? 'is-active' : ''; ?>" href="<?php echo esc_url( $t[1] ); ?>">
				<span class="dashicons <?php echo esc_attr( $t[2] ); ?>"></span>
				<span><?php echo esc_html( $t[0] ); ?></span>
				<?php if ( 'leads' === $key && $leads ) : ?><i class="sfa-dot"><?php echo esc_html( (string) $leads ); ?></i><?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php
}

/* ---------------------------------------------------------------------
 * Переключатель вида и перенаправления
 * ------------------------------------------------------------------- */

add_action( 'admin_post_sf_skin', 'safronova_skin_toggle' );
/**
 * Включает/выключает вид Vigbo.
 */
function safronova_skin_toggle() {
	check_admin_referer( 'sf_skin' );
	$on = isset( $_GET['on'] ) && '1' === sanitize_key( wp_unslash( $_GET['on'] ) );
	update_user_meta( get_current_user_id(), 'sf_vigbo_skin', $on ? '1' : '0' );
	wp_safe_redirect( $on ? admin_url( 'admin.php?page=sf-site' ) : admin_url( 'index.php' ) );
	exit;
}

add_action( 'admin_bar_menu', 'safronova_skin_bar_link', 999 );
/**
 * В классическом виде — ссылка «Вернуть вид Vigbo» в верхней панели.
 *
 * @param WP_Admin_Bar $bar Панель.
 */
function safronova_skin_bar_link( $bar ) {
	if ( ! is_admin() || ! is_user_logged_in() || safronova_skin_on() ) {
		return;
	}
	$bar->add_node(
		array(
			'id'    => 'sf-skin-on',
			'title' => '↩ Вид как в Vigbo',
			'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=sf_skin&on=1' ), 'sf_skin' ),
		)
	);
}

add_action( 'load-index.php', 'safronova_skin_dashboard_redirect' );
/**
 * Как в Vigbo: админка открывается на списке страниц.
 */
function safronova_skin_dashboard_redirect() {
	if ( safronova_skin_on() && current_user_can( 'edit_pages' ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=sf-site' ) );
		exit;
	}
}

add_action( 'load-edit.php', 'safronova_skin_pages_redirect' );
/**
 * Список страниц WordPress заменён экраном «Сайт» (кнопка «назад» из редактора ведёт туда).
 */
function safronova_skin_pages_redirect() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- только чтение параметров адреса.
	$type  = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	$extra = array_diff( array_keys( $_GET ), array( 'post_type' ) );
	// phpcs:enable
	if ( 'page' === $type && ! $extra && safronova_skin_on() ) {
		wp_safe_redirect( admin_url( 'admin.php?page=sf-site' ) );
		exit;
	}
}

add_filter( 'login_redirect', 'safronova_skin_login_redirect', 10, 3 );
/**
 * После входа — сразу на «Сайт».
 *
 * @param string           $to   Куда.
 * @param string           $req  Запрошено.
 * @param WP_User|WP_Error $user Пользователь.
 * @return string
 */
function safronova_skin_login_redirect( $to, $req, $user ) {
	if ( $user instanceof WP_User && $user->has_cap( 'edit_pages' ) && '0' !== get_user_meta( $user->ID, 'sf_vigbo_skin', true )
		&& ( '' === $req || admin_url() === $req || admin_url( '/' ) === $req ) ) {
		return admin_url( 'admin.php?page=sf-site' );
	}
	return $to;
}

/* ---------------------------------------------------------------------
 * Экран «Сайт»
 * ------------------------------------------------------------------- */

/**
 * ID меню для области.
 *
 * @param string $loc sf-left|sf-right.
 * @return int
 */
function safronova_menu_id( $loc ) {
	$locs = get_nav_menu_locations();
	if ( ! empty( $locs[ $loc ] ) && wp_get_nav_menu_object( $locs[ $loc ] ) ) {
		return (int) $locs[ $loc ];
	}
	$names = array(
		'sf-left'  => 'Меню слева',
		'sf-right' => 'Меню справа',
	);
	$obj = wp_get_nav_menu_object( $names[ $loc ] );
	return $obj ? (int) $obj->term_id : 0;
}

/**
 * Дерево пунктов меню: [ [item, children[]], ... ].
 *
 * @param int $menu_id Меню.
 * @return array
 */
function safronova_menu_tree( $menu_id ) {
	$tree = array();
	if ( ! $menu_id ) {
		return $tree;
	}
	$items = (array) wp_get_nav_menu_items( $menu_id );
	foreach ( $items as $it ) {
		if ( ! (int) $it->menu_item_parent ) {
			$tree[ $it->ID ] = array( $it, array() );
		}
	}
	foreach ( $items as $it ) {
		$p = (int) $it->menu_item_parent;
		if ( $p && isset( $tree[ $p ] ) ) {
			$tree[ $p ][1][] = $it;
		}
	}
	return array_values( $tree );
}

/**
 * Строка дерева страниц.
 *
 * @param int    $page_id  Страница (0 — ссылка).
 * @param string $label    Подпись в меню.
 * @param bool   $in_menu  Показывается в меню.
 * @param string $url      Ссылка (для пунктов-ссылок).
 * @param array  $children Подпункты (WP_Post пунктов меню).
 */
function safronova_site_row( $page_id, $label, $in_menu, $url = '', $children = array() ) {
	$page  = $page_id ? get_post( $page_id ) : null;
	$path  = $page ? wp_make_link_relative( get_permalink( $page ) ) : $url;
	$badge = '';
	if ( $page && (int) get_option( 'page_for_posts' ) === $page->ID ) {
		$badge = 'Блог';
	}
	if ( $page && 'publish' !== $page->post_status ) {
		$badge = 'Черновик';
	}
	?>
	<li class="sfa-row" data-page="<?php echo esc_attr( (string) $page_id ); ?>" data-url="<?php echo esc_attr( $page_id ? '' : $url ); ?>">
		<div class="sfa-row__in">
			<span class="sfa-handle dashicons dashicons-move" title="Перетащите, чтобы изменить порядок"></span>
			<div class="sfa-row__main">
				<input class="sfa-label" type="text" value="<?php echo esc_attr( $label ); ?>" aria-label="Название в меню" <?php disabled( ! $in_menu ); ?>>
				<span class="sfa-path"><?php echo esc_html( urldecode( $path ) ); ?><?php if ( $page && $in_menu && $page->post_title !== $label ) : ?> · <?php echo esc_html( $page->post_title ); ?><?php endif; ?><?php if ( $badge ) : ?> <em class="sfa-tag"><?php echo esc_html( $badge ); ?></em><?php endif; ?></span>
			</div>
			<label class="sfa-switch" title="Показывать в меню сайта">
				<input type="checkbox" class="sfa-show" <?php checked( $in_menu ); ?>><span></span><b>В меню</b>
			</label>
			<?php if ( $page && current_user_can( 'edit_post', $page->ID ) ) : ?>
				<a class="sfa-btn sfa-btn--sm" href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>">Редактировать</a>
			<?php endif; ?>
			<a class="sfa-icon" href="<?php echo esc_url( $page ? get_permalink( $page ) : $url ); ?>" target="_blank" rel="noopener" title="Открыть на сайте"><span class="dashicons dashicons-visibility"></span></a>
		</div>
		<ul class="sfa-sortable sfa-children">
			<?php
			foreach ( $children as $ch ) {
				safronova_site_row( 'page' === $ch->object ? (int) $ch->object_id : 0, $ch->title, true, 'page' === $ch->object ? '' : $ch->url );
			}
			?>
		</ul>
	</li>
	<?php
}

/**
 * Экран «Сайт»: страницы и меню, как дерево страниц Vigbo.
 */
function safronova_screen_site() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$front   = (int) get_option( 'page_on_front' );
	$menus   = array(
		'sf-left'  => 'Меню слева от логотипа',
		'sf-right' => 'Меню справа от логотипа',
	);
	$used    = array();
	$trees   = array();
	foreach ( $menus as $loc => $title ) {
		$trees[ $loc ] = safronova_menu_tree( safronova_menu_id( $loc ) );
		foreach ( $trees[ $loc ] as $node ) {
			$used[] = (int) $node[0]->object_id;
			foreach ( $node[1] as $ch ) {
				$used[] = (int) $ch->object_id;
			}
		}
	}
	$hidden = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => 200,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'exclude'        => array_filter( array_merge( $used, array( $front ) ) ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- страниц немного.
		)
	);
	$can_menu = current_user_can( 'edit_theme_options' );
	?>
	<div class="wrap sfa-wrap">
		<div class="sfa-head">
			<h1>Страницы сайта</h1>
			<div class="sfa-head__actions">
				<a class="sfa-btn" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>">+ Страница</a>
				<a class="sfa-btn sfa-btn--ghost" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>">+ Запись в блог</a>
			</div>
		</div>
		<p class="sfa-hint">Перетаскивайте страницы за <span class="dashicons dashicons-move"></span>, чтобы менять порядок в меню. Чтобы сделать подпункт — перетащите страницу внутрь другой. Переключатель «В меню» прячет страницу из меню, но она остаётся доступной по ссылке. Изменения сохраняются сами.</p>

		<?php if ( $front ) : ?>
			<section class="sfa-card sfa-card--flat">
				<h2>Главная страница <small>открывается по клику на логотип</small></h2>
				<div class="sfa-row sfa-row--static"><div class="sfa-row__in">
					<span class="sfa-handle dashicons dashicons-admin-home"></span>
					<div class="sfa-row__main"><strong><?php echo esc_html( get_the_title( $front ) ); ?></strong><span class="sfa-path">/ · слайд-шоу на весь экран</span></div>
					<a class="sfa-btn sfa-btn--sm" href="<?php echo esc_url( (string) get_edit_post_link( $front ) ); ?>">Редактировать</a>
					<a class="sfa-icon" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener" title="Открыть на сайте"><span class="dashicons dashicons-visibility"></span></a>
				</div></div>
			</section>
		<?php endif; ?>

		<div class="sfa-grid2">
			<?php foreach ( $menus as $loc => $title ) : ?>
				<section class="sfa-card">
					<h2><?php echo esc_html( $title ); ?></h2>
					<ul class="sfa-sortable sfa-tree" data-menu="<?php echo esc_attr( $loc ); ?>"<?php echo $can_menu ? '' : ' data-locked="1"'; ?>>
						<?php
						foreach ( $trees[ $loc ] as $node ) {
							$it = $node[0];
							safronova_site_row( 'page' === $it->object ? (int) $it->object_id : 0, $it->title, true, 'page' === $it->object ? '' : $it->url, $node[1] );
						}
						?>
					</ul>
				</section>
			<?php endforeach; ?>
		</div>

		<section class="sfa-card">
			<h2>Не в меню <small>скрытые страницы — открываются только по прямой ссылке</small></h2>
			<ul class="sfa-sortable sfa-tree" data-menu="hidden">
				<?php
				foreach ( $hidden as $p ) {
					safronova_site_row( $p->ID, $p->post_title, false );
				}
				?>
			</ul>
		</section>

		<section class="sfa-card">
			<div class="sfa-card__head">
				<h2>Блог</h2>
				<a class="sfa-link" href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">Все записи →</a>
			</div>
			<div class="sfa-posts">
				<?php
				$posts = get_posts(
					array(
						'posts_per_page' => 8,
						'post_status'    => array( 'publish', 'draft', 'future' ),
					)
				);
				foreach ( $posts as $p ) :
					?>
					<a class="sfa-post" href="<?php echo esc_url( (string) get_edit_post_link( $p->ID ) ); ?>">
						<span class="sfa-post__img"><?php echo get_the_post_thumbnail( $p, 'medium' ); ?></span>
						<span class="sfa-post__t"><?php echo esc_html( get_the_title( $p ) ); ?></span>
						<span class="sfa-path"><?php echo esc_html( 'publish' === $p->post_status ? get_the_date( 'd.m.Y', $p ) : 'Черновик' ); ?></span>
					</a>
				<?php endforeach; ?>
				<a class="sfa-post sfa-post--add" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><span class="dashicons dashicons-plus-alt2"></span><span>Новая запись</span></a>
			</div>
		</section>
		<div class="sfa-toast" role="status" aria-live="polite"></div>
	</div>
	<?php
}

add_action( 'wp_ajax_sf_save_menus', 'safronova_ajax_save_menus' );
/**
 * Сохраняет порядок и подписи меню с экрана «Сайт».
 */
function safronova_ajax_save_menus() {
	check_ajax_referer( 'sf_site_menus', 'nonce' );
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_send_json_error( array( 'message' => 'Недостаточно прав' ), 403 );
	}
	// JSON разбирается, а каждое поле дальше проверяется отдельно (absint, sanitize_text_field, esc_url_raw).
	$raw  = isset( $_POST['menus'] ) ? wp_unslash( $_POST['menus'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$data = json_decode( (string) $raw, true );
	if ( ! is_array( $data ) ) {
		wp_send_json_error( array( 'message' => 'Неверные данные' ), 400 );
	}
	$names = array(
		'sf-left'  => 'Меню слева',
		'sf-right' => 'Меню справа',
	);
	$locs  = get_nav_menu_locations();
	foreach ( $names as $loc => $name ) {
		if ( ! isset( $data[ $loc ] ) || ! is_array( $data[ $loc ] ) ) {
			continue;
		}
		$menu_id = safronova_menu_id( $loc );
		if ( ! $menu_id ) {
			$menu_id = (int) wp_create_nav_menu( $name );
		}
		if ( ! $menu_id ) {
			continue;
		}
		foreach ( (array) wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) ) as $old ) {
			wp_delete_post( $old->ID, true );
		}
		$pos = 0;
		foreach ( $data[ $loc ] as $node ) {
			$parent = safronova_save_menu_item( $menu_id, $node, 0, ++$pos );
			foreach ( (array) ( $node['children'] ?? array() ) as $child ) {
				safronova_save_menu_item( $menu_id, $child, $parent, ++$pos );
			}
		}
		$locs[ $loc ] = $menu_id;
	}
	set_theme_mod( 'nav_menu_locations', $locs );
	wp_send_json_success( array( 'message' => 'Сохранено' ) );
}

/**
 * Один пункт меню.
 *
 * @param int   $menu_id Меню.
 * @param array $node    {page, url, title}.
 * @param int   $parent  Родитель.
 * @param int   $pos     Позиция.
 * @return int ID пункта.
 */
function safronova_save_menu_item( $menu_id, $node, $parent, $pos ) {
	$page  = absint( $node['page'] ?? 0 );
	$title = sanitize_text_field( (string) ( $node['title'] ?? '' ) );
	$args  = array(
		'menu-item-title'     => $title,
		'menu-item-status'    => 'publish',
		'menu-item-parent-id' => $parent,
		'menu-item-position'  => $pos,
	);
	if ( $page && 'page' === get_post_type( $page ) ) {
		$args['menu-item-object']    = 'page';
		$args['menu-item-object-id'] = $page;
		$args['menu-item-type']      = 'post_type';
	} else {
		$url = esc_url_raw( (string) ( $node['url'] ?? '' ) );
		if ( ! $url ) {
			return 0;
		}
		$args['menu-item-url']  = $url;
		$args['menu-item-type'] = 'custom';
	}
	$id = wp_update_nav_menu_item( $menu_id, 0, $args );
	return is_wp_error( $id ) ? 0 : (int) $id;
}

/* ---------------------------------------------------------------------
 * Экран «Оформление»
 * ------------------------------------------------------------------- */

/**
 * Карточка-ссылка.
 *
 * @param string $icon  Dashicon.
 * @param string $title Заголовок.
 * @param string $text  Пояснение.
 * @param string $url   Ссылка.
 * @param string $btn   Кнопка.
 */
function safronova_link_card( $icon, $title, $text, $url, $btn = 'Открыть' ) {
	?>
	<section class="sfa-card sfa-tile">
		<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
		<h2><?php echo esc_html( $title ); ?></h2>
		<p><?php echo esc_html( $text ); ?></p>
		<a class="sfa-btn sfa-btn--ghost" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $btn ); ?></a>
	</section>
	<?php
}

/**
 * Экран «Оформление».
 */
function safronova_screen_design() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$logo = (int) get_theme_mod( 'custom_logo' );
	$icon = (int) get_option( 'site_icon' );
	$done = isset( $_GET['saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только сообщение.
	?>
	<div class="wrap sfa-wrap">
		<div class="sfa-head"><h1>Оформление</h1></div>
		<?php if ( $done ) : ?><div class="notice notice-success"><p>Сохранено.</p></div><?php endif; ?>
		<div class="sfa-tiles">
			<?php foreach ( array( 'logo' => array( 'Логотип', 'Показывается в центре шапки сайта.', $logo ), 'icon' => array( 'Иконка сайта', 'Маленькая картинка во вкладке браузера (квадрат, от 512×512).', $icon ) ) as $key => $c ) : ?>
				<section class="sfa-card sfa-tile">
					<h2><?php echo esc_html( $c[0] ); ?></h2>
					<p><?php echo esc_html( $c[1] ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sfa-media-form">
						<input type="hidden" name="action" value="sf_save_design">
						<input type="hidden" name="what" value="<?php echo esc_attr( $key ); ?>">
						<?php wp_nonce_field( 'sf_save_design' ); ?>
						<input type="hidden" name="attachment" value="<?php echo esc_attr( (string) $c[2] ); ?>" class="sfa-media-id">
						<div class="sfa-media-preview<?php echo 'icon' === $key ? ' is-icon' : ''; ?>"><?php echo $c[2] ? wp_get_attachment_image( $c[2], 'medium' ) : '<span>Не выбрано</span>'; ?></div>
						<button type="button" class="sfa-btn sfa-btn--ghost sfa-media-pick" data-title="<?php echo esc_attr( $c[0] ); ?>">Выбрать картинку</button>
						<button type="submit" class="sfa-btn">Сохранить</button>
					</form>
				</section>
			<?php endforeach; ?>
			<?php
			safronova_link_card( 'dashicons-menu', 'Меню', 'Порядок пунктов, подписи, скрытые страницы.', admin_url( 'admin.php?page=sf-site' ), 'Перейти к страницам' );
			safronova_link_card( 'dashicons-editor-textcolor', 'Шрифты и цвета', 'Общие стили сайта: шрифты, размеры, цвета текста и ссылок.', admin_url( 'site-editor.php?p=%2Fstyles' ), 'Изменить стили' );
			safronova_link_card( 'dashicons-align-wide', 'Подвал сайта', 'Нижняя часть всех страниц: соцсети, контакты, копирайт.', admin_url( 'site-editor.php?postType=wp_template_part&postId=' . rawurlencode( get_stylesheet() . '//footer' ) . '&canvas=edit' ), 'Редактировать подвал' );
			safronova_link_card( 'dashicons-layout', 'Шаблоны', 'Как выглядят записи блога, архив, страница 404.', admin_url( 'site-editor.php?p=%2Ftemplate' ), 'Открыть шаблоны' );
			safronova_link_card( 'dashicons-editor-code', 'CSS-стили', 'Свой CSS для тонкой настройки (в редакторе стилей: ⋮ → Дополнительный CSS).', admin_url( 'site-editor.php?p=%2Fstyles' ), 'Открыть' );
			safronova_link_card( 'dashicons-format-gallery', 'Фото и файлы', 'Все загруженные фотографии сайта.', admin_url( 'upload.php' ), 'Открыть медиатеку' );
			?>
		</div>
	</div>
	<?php
}

add_action( 'admin_post_sf_save_design', 'safronova_save_design' );
/**
 * Сохраняет логотип / иконку.
 */
function safronova_save_design() {
	check_admin_referer( 'sf_save_design' );
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'safronova' ) );
	}
	$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : '';
	$id   = isset( $_POST['attachment'] ) ? absint( $_POST['attachment'] ) : 0;
	if ( $id && ! wp_attachment_is_image( $id ) ) {
		$id = 0;
	}
	if ( 'logo' === $what ) {
		set_theme_mod( 'custom_logo', $id );
	} elseif ( 'icon' === $what && current_user_can( 'manage_options' ) ) {
		update_option( 'site_icon', $id );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=sf-design&saved=1' ) );
	exit;
}

/* ---------------------------------------------------------------------
 * Экран «Настройки»
 * ------------------------------------------------------------------- */

/**
 * Экран «Настройки».
 */
function safronova_screen_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$done = isset( $_GET['saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только сообщение.
	$raw  = current_user_can( 'unfiltered_html' );
	?>
	<div class="wrap sfa-wrap">
		<div class="sfa-head"><h1>Настройки</h1></div>
		<?php if ( $done ) : ?><div class="notice notice-success"><p>Настройки сохранены.</p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sf_save_settings">
			<?php wp_nonce_field( 'sf_save_settings' ); ?>
			<section class="sfa-card">
				<h2>Общие</h2>
				<div class="sfa-field"><label for="sf-name">Название сайта</label><input id="sf-name" name="blogname" type="text" value="<?php echo esc_attr( get_option( 'blogname' ) ); ?>"></div>
				<div class="sfa-field"><label for="sf-desc">Описание (для поисковиков и соцсетей)</label><input id="sf-desc" name="blogdescription" type="text" value="<?php echo esc_attr( get_option( 'blogdescription' ) ); ?>"></div>
				<div class="sfa-field"><label for="sf-mail">E-mail для заявок с сайта</label><input id="sf-mail" name="sf_contact_email" type="email" value="<?php echo esc_attr( get_option( 'sf_contact_email', get_option( 'admin_email' ) ) ); ?>"><small>Сюда приходят письма из формы «Контакты». Копия каждой заявки сохраняется во вкладке «Заявки».</small></div>
				<label class="sfa-check"><input type="checkbox" name="blog_hidden" value="1" <?php checked( '0' === (string) get_option( 'blog_public' ) ); ?>> Скрыть сайт от поисковых систем (на время тестов)</label>
			</section>
			<section class="sfa-card">
				<h2>Сторонний код</h2>
				<div class="sfa-field"><label for="sf-fb">Facebook Pixel ID</label><input id="sf-fb" name="sf_fb_pixel" type="text" inputmode="numeric" value="<?php echo esc_attr( get_option( 'sf_fb_pixel' ) ); ?>"></div>
				<div class="sfa-field"><label for="sf-ga">Google Analytics (G-XXXXXXX)</label><input id="sf-ga" name="sf_ga_id" type="text" value="<?php echo esc_attr( get_option( 'sf_ga_id' ) ); ?>"></div>
				<?php if ( $raw ) : ?>
					<div class="sfa-field"><label for="sf-head">Код в &lt;head&gt; (подтверждение прав, счётчики)</label><textarea id="sf-head" name="sf_head_code" rows="4" spellcheck="false"><?php echo esc_textarea( (string) get_option( 'sf_head_code' ) ); ?></textarea><small>Вставляется на все страницы как есть. Например, мета-тег Google Search Console.</small></div>
				<?php endif; ?>
			</section>
			<p><button type="submit" class="sfa-btn">Сохранить настройки</button></p>
		</form>
		<div class="sfa-tiles">
			<?php
			safronova_link_card( 'dashicons-admin-users', 'Пароль и профиль', 'Смена пароля, имени, e-mail входа.', admin_url( 'profile.php' ) );
			safronova_link_card( 'dashicons-groups', 'Пользователи', 'Кто ещё может входить в админку.', admin_url( 'users.php' ) );
			safronova_link_card( 'dashicons-search', 'SEO страниц', 'Заголовок и описание для Google задаются внизу редактора каждой страницы — блок «SEO».', admin_url( 'admin.php?page=sf-site' ), 'К страницам' );
			safronova_link_card( 'dashicons-update', 'Обновления', 'Обновления WordPress, темы и плагинов.', admin_url( 'update-core.php' ) );
			safronova_link_card( 'dashicons-wordpress', 'Классический вид WordPress', 'Полное меню WordPress — для продвинутых настроек. Вернуть этот вид можно кнопкой вверху.', wp_nonce_url( admin_url( 'admin-post.php?action=sf_skin&on=0' ), 'sf_skin' ), 'Переключить' );
			?>
		</div>
	</div>
	<?php
}

add_action( 'admin_post_sf_save_settings', 'safronova_save_settings' );
/**
 * Сохраняет «Настройки». Значения проходят через фильтры sanitize_option_* (register_setting).
 */
function safronova_save_settings() {
	check_admin_referer( 'sf_save_settings' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'safronova' ) );
	}
	$text = array( 'blogname', 'blogdescription', 'sf_fb_pixel', 'sf_ga_id' );
	foreach ( $text as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_option( $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
		}
	}
	if ( isset( $_POST['sf_contact_email'] ) ) {
		$mail = sanitize_email( wp_unslash( $_POST['sf_contact_email'] ) );
		if ( is_email( $mail ) ) {
			update_option( 'sf_contact_email', $mail );
		}
	}
	update_option( 'blog_public', empty( $_POST['blog_hidden'] ) ? '1' : '0' );
	if ( isset( $_POST['sf_head_code'] ) && current_user_can( 'unfiltered_html' ) ) {
		// Код от администратора с правом unfiltered_html сохраняется как есть — как поле «Сторонний код» в Vigbo.
		update_option( 'sf_head_code', wp_unslash( $_POST['sf_head_code'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- см. комментарий выше.
	}
	wp_safe_redirect( admin_url( 'admin.php?page=sf-settings&saved=1' ) );
	exit;
}

add_action( 'wp_head', 'safronova_head_code', 99 );
/**
 * Выводит «Код в <head>».
 */
function safronova_head_code() {
	$code = (string) get_option( 'sf_head_code' );
	if ( '' !== trim( $code ) ) {
		echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- код вставил администратор с правом unfiltered_html.
	}
}

/* ---------------------------------------------------------------------
 * Редактор: набор блоков как виджеты Vigbo
 * ------------------------------------------------------------------- */

add_filter( 'allowed_block_types_all', 'safronova_allowed_blocks', 10, 2 );
/**
 * Ограничивает список блоков в редакторе страниц и записей (меньше путаницы).
 *
 * @param bool|array              $allowed Разрешённые.
 * @param WP_Block_Editor_Context $ctx     Контекст.
 * @return bool|array
 */
function safronova_allowed_blocks( $allowed, $ctx ) {
	if ( empty( $ctx->post ) || ! safronova_skin_on() || ! in_array( $ctx->post->post_type, array( 'page', 'post' ), true ) ) {
		return $allowed;
	}
	return array(
		'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote', 'core/details',
		'core/image', 'core/gallery', 'core/video', 'core/embed', 'core/buttons', 'core/button',
		'core/spacer', 'core/separator', 'core/social-links', 'core/social-link',
		'core/columns', 'core/column', 'core/group', 'core/html', 'core/table', 'core/more',
		'core/block', 'core/pattern', 'core/freeform', 'core/shortcode',
		'safronova/contact-form',
	);
}
