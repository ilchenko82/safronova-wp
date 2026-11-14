<?php
/**
 * Коды аналитики: Facebook (Meta) Pixel и Google Analytics.
 * Настройки → «Коды аналитики» — достаточно вставить номер, код подключится сам.
 *
 * @package safronova
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'safronova_analytics_menu' );
/**
 * Пункт в меню «Настройки».
 */
function safronova_analytics_menu() {
	add_options_page( 'Коды аналитики', 'Коды аналитики', 'manage_options', 'sf-analytics', 'safronova_analytics_page' );
}

add_action( 'admin_init', 'safronova_analytics_settings' );
/**
 * Поля настроек.
 */
function safronova_analytics_settings() {
	register_setting(
		'sf_analytics',
		'sf_fb_pixel',
		array(
			'type'              => 'string',
			'sanitize_callback' => function ( $v ) {
				return preg_replace( '/\D/', '', (string) $v );
			},
			'default'           => '',
		)
	);
	register_setting(
		'sf_analytics',
		'sf_ga_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => function ( $v ) {
				$v = strtoupper( trim( (string) $v ) );
				return preg_match( '/^(G|GT|AW)-[A-Z0-9]+$/', $v ) ? $v : '';
			},
			'default'           => '',
		)
	);
}

/**
 * Страница настроек.
 */
function safronova_analytics_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>Коды аналитики</h1>
		<p>Вставьте номер — код подключится на всех страницах сайта автоматически. Пустое поле — код выключен.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'sf_analytics' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="sf_fb_pixel">Facebook / Meta Pixel ID</label></th>
					<td><input type="text" class="regular-text" id="sf_fb_pixel" name="sf_fb_pixel" value="<?php echo esc_attr( (string) get_option( 'sf_fb_pixel', '' ) ); ?>" placeholder="например 216643308703754">
					<p class="description">Только цифры. Найти: Meta Business Suite → Events Manager → ваш пиксель.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="sf_ga_id">Google Analytics (GA4)</label></th>
					<td><input type="text" class="regular-text" id="sf_ga_id" name="sf_ga_id" value="<?php echo esc_attr( (string) get_option( 'sf_ga_id', '' ) ); ?>" placeholder="G-XXXXXXXXXX">
					<p class="description">Идентификатор потока данных вида G-XXXXXXXXXX.</p></td>
				</tr>
			</table>
			<?php submit_button( 'Сохранить' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'wp_head', 'safronova_analytics_head', 20 );
/**
 * Вывод кодов на сайте (не для вошедших администраторов — чтобы не портить статистику).
 */
function safronova_analytics_head() {
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return;
	}
	$fb = preg_replace( '/\D/', '', (string) get_option( 'sf_fb_pixel', '' ) );
	$ga = (string) get_option( 'sf_ga_id', '' );
	if ( $fb ) {
		?>
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?php echo esc_js( $fb ); ?>');fbq('track','PageView');</script>
<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=<?php echo esc_attr( $fb ); ?>&amp;ev=PageView&amp;noscript=1"></noscript>
		<?php
	}
	if ( $ga && preg_match( '/^(G|GT|AW)-[A-Z0-9]+$/', $ga ) ) {
		?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga ); ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo esc_js( $ga ); ?>');</script>
		<?php
	}
}
