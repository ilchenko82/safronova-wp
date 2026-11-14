<?php
/**
 * Простая статистика как во вкладке «Статистика» Vigbo:
 * просмотры страниц по дням и источники переходов. Без cookies и без хранения IP-адресов.
 *
 * @package Safronova
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'template_redirect', 'safronova_stats_hit', 99 );
/**
 * Учитывает просмотр страницы посетителем (не администратором и не ботом).
 */
function safronova_stats_hit() {
	if ( is_user_logged_in() || is_feed() || is_robots() || is_preview() || is_404() || is_search() ) {
		return;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
	$ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	if ( 'get' !== $method || '' === $ua || preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python|headless|lighthouse/i', $ua ) ) {
		return;
	}
	global $wp;
	$path = '/' . substr( trim( (string) $wp->request, '/' ), 0, 120 );

	$ref  = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	$host = $ref ? strtolower( (string) wp_parse_url( $ref, PHP_URL_HOST ) ) : '';
	$self = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$host = preg_replace( '/^(www\.|m\.|l\.|lm\.)/', '', $host );
	if ( $host === preg_replace( '/^www\./', '', $self ) ) {
		$host = '';
	}

	$day = wp_date( 'Y-m-d' );
	$s   = get_option( 'sf_stats', array() );
	if ( ! is_array( $s ) ) {
		$s = array();
	}
	if ( ! isset( $s[ $day ] ) ) {
		$s[ $day ] = array(
			'p' => array(),
			'r' => array(),
		);
		ksort( $s );
		$s = array_slice( $s, -120, null, true ); // храним 4 месяца
	}
	$s[ $day ]['p'][ $path ] = ( $s[ $day ]['p'][ $path ] ?? 0 ) + 1;
	$src                     = $host ? $host : '(прямые заходы)';
	$s[ $day ]['r'][ $src ]  = ( $s[ $day ]['r'][ $src ] ?? 0 ) + 1;
	update_option( 'sf_stats', $s, false );
}

/**
 * Экран «Статистика».
 */
function safronova_screen_stats() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$period = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- выбор периода.
	if ( ! in_array( $period, array( 7, 30, 90 ), true ) ) {
		$period = 30;
	}
	$s     = (array) get_option( 'sf_stats', array() );
	$days  = array();
	$pages = array();
	$refs  = array();
	$total = 0;
	for ( $i = $period - 1; $i >= 0; $i-- ) {
		$d          = wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
		$n          = isset( $s[ $d ]['p'] ) ? array_sum( (array) $s[ $d ]['p'] ) : 0;
		$days[ $d ] = $n;
		$total     += $n;
		foreach ( (array) ( $s[ $d ]['p'] ?? array() ) as $p => $c ) {
			$pages[ $p ] = ( $pages[ $p ] ?? 0 ) + $c;
		}
		foreach ( (array) ( $s[ $d ]['r'] ?? array() ) as $r => $c ) {
			$refs[ $r ] = ( $refs[ $r ] ?? 0 ) + $c;
		}
	}
	arsort( $pages );
	arsort( $refs );
	$max   = max( 1, max( $days ) );
	$today = end( $days );
	$leads = count(
		get_posts(
			array(
				'post_type'      => 'sf_lead',
				'post_status'    => 'private',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'date_query'     => array( array( 'after' => $period . ' days ago' ) ),
			)
		)
	);
	?>
	<div class="wrap sfa-wrap">
		<div class="sfa-head">
			<h1>Статистика</h1>
			<div class="sfa-seg">
				<?php foreach ( array( 7 => '7 дней', 30 => '30 дней', 90 => '90 дней' ) as $k => $label ) : ?>
					<a class="<?php echo $k === $period ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=sf-stats&days=' . $k ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="sfa-kpis">
			<section class="sfa-card"><small>Просмотров за период</small><b><?php echo esc_html( number_format_i18n( $total ) ); ?></b></section>
			<section class="sfa-card"><small>Сегодня</small><b><?php echo esc_html( number_format_i18n( (int) $today ) ); ?></b></section>
			<section class="sfa-card"><small>В среднем за день</small><b><?php echo esc_html( number_format_i18n( $total / $period, 1 ) ); ?></b></section>
			<section class="sfa-card"><small>Заявок с сайта</small><b><?php echo esc_html( (string) $leads ); ?></b></section>
		</div>
		<section class="sfa-card">
			<h2>Просмотры по дням</h2>
			<div class="sfa-chart" role="img" aria-label="<?php echo esc_attr( 'Просмотры по дням, всего ' . $total ); ?>">
				<?php foreach ( $days as $d => $n ) : ?>
					<span style="height:<?php echo esc_attr( (string) max( 2, round( $n / $max * 100 ) ) ); ?>%" title="<?php echo esc_attr( wp_date( 'j M', strtotime( $d ) ) . ': ' . $n ); ?>"></span>
				<?php endforeach; ?>
			</div>
			<div class="sfa-chart__axis"><span><?php echo esc_html( wp_date( 'j M', strtotime( (string) array_key_first( $days ) ) ) ); ?></span><span>сегодня</span></div>
		</section>
		<div class="sfa-grid2">
			<section class="sfa-card">
				<h2>Популярные страницы</h2>
				<?php safronova_stats_table( array_slice( $pages, 0, 12, true ), true ); ?>
			</section>
			<section class="sfa-card">
				<h2>Откуда приходят</h2>
				<?php safronova_stats_table( array_slice( $refs, 0, 12, true ), false ); ?>
			</section>
		</div>
		<p class="sfa-hint">Считаются просмотры посетителей. Ваши собственные визиты (пока вы вошли в админку) и поисковые роботы не учитываются. Cookies и IP-адреса не сохраняются.</p>
	</div>
	<?php
}

/**
 * Таблица «название — число».
 *
 * @param array $rows  Строки.
 * @param bool  $links Пути страниц — делать ссылками.
 */
function safronova_stats_table( $rows, $links ) {
	if ( ! $rows ) {
		echo '<p class="sfa-hint">Пока нет данных.</p>';
		return;
	}
	$max = max( 1, max( $rows ) );
	echo '<ul class="sfa-bars">';
	foreach ( $rows as $k => $n ) {
		$label = $links ? urldecode( (string) $k ) : (string) $k;
		echo '<li><span class="sfa-bars__fill" style="width:' . esc_attr( (string) round( $n / $max * 100 ) ) . '%"></span>';
		if ( $links ) {
			echo '<a href="' . esc_url( home_url( (string) $k ) ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
		} else {
			echo '<span>' . esc_html( $label ) . '</span>';
		}
		echo '<b>' . esc_html( number_format_i18n( $n ) ) . '</b></li>';
	}
	echo '</ul>';
}
