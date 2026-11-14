<?php
/**
 * Шапка сайта: меню слева, логотип по центру, меню справа,
 * на телефоне — логотип слева и кнопка-«бургер» с меню на весь экран.
 *
 * @package safronova
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML логотипа: логотип из «Внешний вид → Настроить → Свойства сайта»,
 * иначе файл темы assets/img/logo.jpg, иначе название сайта текстом.
 *
 * @return string
 */
function safronova_logo_html() {
	$home = home_url( '/' );
	$id   = (int) get_theme_mod( 'custom_logo' );
	if ( $id ) {
		$img = wp_get_attachment_image(
			$id,
			'full',
			false,
			array(
				'alt'     => 'лого Сафронова',
				'loading' => false,
			)
		);
		if ( $img ) {
			return '<a href="' . esc_url( $home ) . '" rel="home">' . $img . '</a>';
		}
	}
	foreach ( array( 'logo.jpg', 'logo.png' ) as $file ) {
		if ( file_exists( SAFRONOVA_DIR . '/assets/img/' . $file ) ) {
			return '<a href="' . esc_url( $home ) . '" rel="home"><img src="' . esc_url( SAFRONOVA_URI . '/assets/img/' . $file ) . '" width="170" height="154" alt="лого Сафронова"></a>';
		}
	}
	return '<a class="sf-logo__text" href="' . esc_url( $home ) . '" rel="home">' . esc_html( get_bloginfo( 'name' ) ) . '</a>';
}

/**
 * Пункты меню (только <li>) для указанной области.
 *
 * @param string $location Область меню.
 * @return string
 */
function safronova_menu_items( $location ) {
	if ( ! has_nav_menu( $location ) ) {
		return '';
	}
	$html = wp_nav_menu(
		array(
			'theme_location' => $location,
			'container'      => false,
			'items_wrap'     => '%3$s',
			'depth'          => 2,
			'fallback_cb'    => false,
			'echo'           => false,
		)
	);
	return is_string( $html ) ? $html : '';
}

/**
 * Вывод блока safronova/header.
 *
 * @return string
 */
function safronova_render_header() {
	$left  = safronova_menu_items( 'sf-left' );
	$right = safronova_menu_items( 'sf-right' );

	$burger = '<svg viewBox="0 0 26 20" aria-hidden="true"><path d="M1 2h24M1 10h24M1 18h24" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/></svg>';
	$close  = '<svg viewBox="0 0 26 26" aria-hidden="true"><path d="M2 2l22 22M24 2L2 24" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/></svg>';

	ob_start();
	?>
	<div class="sf-header">
		<div class="sf-header__bar">
			<div class="sf-header__inner">
				<nav class="sf-nav sf-nav--left" aria-label="<?php echo esc_attr__( 'Основное меню', 'safronova' ); ?>">
					<ul><?php echo $left; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML сформирован wp_nav_menu(). ?></ul>
				</nav>
				<div class="sf-logo"><?php echo safronova_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- значения экранированы внутри функции. ?></div>
				<nav class="sf-nav sf-nav--right" aria-label="<?php echo esc_attr__( 'Дополнительное меню', 'safronova' ); ?>">
					<ul><?php echo $right; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML сформирован wp_nav_menu(). ?></ul>
				</nav>
				<button class="sf-burger" type="button" aria-controls="sf-mobile" aria-expanded="false" aria-label="<?php echo esc_attr__( 'Открыть меню', 'safronova' ); ?>">
					<?php echo $burger; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- статичный SVG. ?>
				</button>
			</div>
		</div>
		<div class="sf-mobile" id="sf-mobile" aria-hidden="true">
			<button class="sf-mobile__close" type="button" aria-label="<?php echo esc_attr__( 'Закрыть меню', 'safronova' ); ?>">
				<?php echo $close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- статичный SVG. ?>
			</button>
			<nav aria-label="<?php echo esc_attr__( 'Мобильное меню', 'safronova' ); ?>">
				<ul><?php echo $left . $right; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML сформирован wp_nav_menu(). ?></ul>
			</nav>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

add_shortcode( 'sf_header', 'safronova_render_header' );
