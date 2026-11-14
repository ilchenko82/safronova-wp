<?php
/**
 * Форма обратной связи (страница «Контакти»).
 * Письмо уходит на адрес из настройки sf_contact_email
 * (импортёр ставит info.elenasafronova@gmail.com), иначе — на e-mail администратора.
 *
 * @package safronova
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML формы.
 *
 * @return string
 */
function safronova_render_contact_form() {
	static $n = 0;
	++$n;
	$id   = 'sf-form-' . $n;
	$sent = isset( $_GET['sf_sent'] ) ? sanitize_key( wp_unslash( $_GET['sf_sent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только показ статуса после отправки без JS.

	ob_start();
	?>
	<form class="sf-form" id="<?php echo esc_attr( $id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
		<input type="hidden" name="action" value="sf_contact">
		<?php wp_nonce_field( 'sf_contact', 'sf_nonce' ); ?>
		<div class="sf-form__hp" aria-hidden="true">
			<textarea name="sf_comment" tabindex="-1" autocomplete="off"></textarea>
		</div>
		<div class="sf-form__field">
			<input type="text" name="sf_name" id="<?php echo esc_attr( $id ); ?>-name" maxlength="200" autocomplete="name">
			<label for="<?php echo esc_attr( $id ); ?>-name">Имя</label>
		</div>
		<div class="sf-form__field">
			<input type="email" name="sf_email" id="<?php echo esc_attr( $id ); ?>-email" maxlength="200" required autocomplete="email">
			<label for="<?php echo esc_attr( $id ); ?>-email">E-mail *</label>
		</div>
		<div class="sf-form__field">
			<textarea name="sf_message" id="<?php echo esc_attr( $id ); ?>-msg" maxlength="5000"></textarea>
			<label for="<?php echo esc_attr( $id ); ?>-msg">Сообщение</label>
		</div>
		<button type="submit" class="sf-form__btn">ОТПРАВИТЬ</button>
		<div class="sf-form__msg" role="status" aria-live="polite"><?php
		if ( 'ok' === $sent ) {
			echo esc_html( safronova_form_success_text() );
		} elseif ( 'err' === $sent ) {
			echo esc_html__( 'Не удалось отправить сообщение. Попробуйте ещё раз.', 'safronova' );
		}
		?></div>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'sf_contact_form', 'safronova_render_contact_form' );

/**
 * Текст после успешной отправки.
 *
 * @return string
 */
function safronova_form_success_text() {
	return (string) get_option( 'sf_contact_success', 'Спасибо! Ваше сообщение отправлено.' );
}

/**
 * Проверка и отправка. Возвращает true или текст ошибки.
 *
 * @return true|string
 */
function safronova_process_contact() {
	if ( ! isset( $_POST['sf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sf_nonce'] ) ), 'sf_contact' ) ) {
		return 'Сессия устарела. Обновите страницу и отправьте ещё раз.';
	}

	// Ловушка для спам-ботов: людям поле не видно.
	if ( ! empty( $_POST['sf_comment'] ) ) {
		return true;
	}

	$name    = isset( $_POST['sf_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sf_name'] ) ) : '';
	$email   = isset( $_POST['sf_email'] ) ? sanitize_email( wp_unslash( $_POST['sf_email'] ) ) : '';
	$message = isset( $_POST['sf_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sf_message'] ) ) : '';

	if ( ! is_email( $email ) ) {
		return 'Пожалуйста, укажите корректный e-mail.';
	}

	// Не чаще одного письма в 30 секунд с одного адреса.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'sf_cf_' . md5( $ip );
	if ( get_transient( $key ) ) {
		return 'Сообщение уже отправлено. Подождите немного перед следующим.';
	}

	$to = sanitize_email( (string) get_option( 'sf_contact_email', '' ) );
	if ( ! is_email( $to ) ) {
		$to = get_option( 'admin_email' );
	}

	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject = sprintf( 'Сообщение с сайта %s', $site );
	$body    = "Имя: {$name}\nE-mail: {$email}\n\nСообщение:\n{$message}\n\n—\nОтправлено с формы на странице «Контакти» (" . home_url( '/' ) . ')';
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	$headers[] = 'Reply-To: ' . ( $name ? str_replace( array( "\r", "\n", '<', '>', ',' ), '', $name ) . ' ' : '' ) . '<' . $email . '>';

	$mailed = wp_mail( $to, $subject, $body, $headers );
	// Заявка всегда сохраняется в админке (раздел «Заявки»), даже если почта не настроена.
	$saved = function_exists( 'safronova_save_lead' ) ? safronova_save_lead( $name, $email, $message, $mailed ) : 0;
	if ( ! $mailed && ! $saved ) {
		return 'Не удалось отправить сообщение. Попробуйте ещё раз или напишите на почту.';
	}
	set_transient( $key, 1, 30 );
	return true;
}

add_action( 'wp_ajax_sf_contact', 'safronova_contact_ajax' );
add_action( 'wp_ajax_nopriv_sf_contact', 'safronova_contact_ajax' );
/**
 * Отправка через AJAX (обычный случай).
 */
function safronova_contact_ajax() {
	$r = safronova_process_contact();
	if ( true === $r ) {
		wp_send_json_success( array( 'message' => safronova_form_success_text() ) );
	}
	wp_send_json_error( array( 'message' => $r ), 400 );
}

add_action( 'admin_post_sf_contact', 'safronova_contact_post' );
add_action( 'admin_post_nopriv_sf_contact', 'safronova_contact_post' );
/**
 * Отправка без JavaScript: возврат на страницу с отметкой результата.
 */
function safronova_contact_post() {
	$r    = safronova_process_contact();
	$back = wp_get_referer();
	if ( ! $back ) {
		$back = home_url( '/contacts/' );
	}
	wp_safe_redirect( add_query_arg( 'sf_sent', true === $r ? 'ok' : 'err', $back ) );
	exit;
}

add_action( 'admin_init', 'safronova_contact_settings' );
/**
 * Поле «E-mail для формы обратной связи» в Настройки → Общие.
 */
function safronova_contact_settings() {
	register_setting(
		'general',
		'sf_contact_email',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'default'           => '',
		)
	);
	add_settings_field(
		'sf_contact_email',
		'E-mail для формы обратной связи',
		'safronova_contact_email_field',
		'general'
	);
}

/**
 * HTML поля настройки.
 */
function safronova_contact_email_field() {
	$v = (string) get_option( 'sf_contact_email', '' );
	echo '<input type="email" class="regular-text" name="sf_contact_email" id="sf_contact_email" value="' . esc_attr( $v ) . '">';
	echo '<p class="description">Сюда приходят сообщения с формы на странице «Контакти». Если пусто — на e-mail администратора.</p>';
}
