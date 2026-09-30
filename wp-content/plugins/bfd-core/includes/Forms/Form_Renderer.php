<?php
namespace BFD\Core\Forms;

defined( 'ABSPATH' ) || exit;

final class Form_Renderer {

	/** Visitor-facing error text by result code (literal strings so they can be translated). */
	public static function message( string $code ): string {
		switch ( $code ) {
			case 'invalid':
				return __( 'Please check the highlighted fields and try again.', 'bfd-core' );
			case 'timing':
				return __( 'Please wait a moment and try again.', 'bfd-core' );
			case 'rate':
				return __( 'Too many submissions. Please try again later.', 'bfd-core' );
			case 'security':
				return __( 'Security check failed. Please reload the page and try again.', 'bfd-core' );
			case 'captcha':
				return __( 'Please complete the spam check and try again.', 'bfd-core' );
			default:
				return __( 'Sorry, something went wrong. Please try again later.', 'bfd-core' );
		}
	}

	public static function timestamp_token( int $form_id ): string {
		$ts = time();
		return $ts . '.' . hash_hmac( 'sha256', $form_id . '|' . $ts, wp_salt( 'nonce' ) );
	}

	public static function render( int $form_id ): void {
		$form = Form_Definition::load( $form_id );
		if ( ! $form ) {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<p class="bfd-form__notice">' . esc_html__( 'Form not found or not published.', 'bfd-core' ) . '</p>';
			}
			return;
		}

		$form_js = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ! file_exists( BFD_CORE_DIR . 'assets/js/form.min.js' ) ? 'form.js' : 'form.min.js';
		wp_enqueue_script(
			'bfd-form',
			BFD_CORE_URL . 'assets/js/' . $form_js,
			array(),
			BFD_CORE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$dom_id = 'bfd-form-' . $form->id;
		$status = self::status_for( $form->id, $form );
		?>
		<form id="<?php echo esc_attr( $dom_id ); ?>" class="bfd-form" method="post"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			enctype="multipart/form-data"
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<input type="hidden" name="action" value="bfd_form_submit">
			<input type="hidden" name="form_id" value="<?php echo (int) $form->id; ?>">
			<input type="hidden" name="bfd_ts" value="<?php echo esc_attr( self::timestamp_token( $form->id ) ); ?>">
			<?php wp_nonce_field( 'bfd_form_' . $form->id, '_bfd_nonce', false ); ?>
			<div class="bfd-form__hp" aria-hidden="true">
				<label><?php esc_html_e( 'Leave this field empty', 'bfd-core' ); ?>
					<input type="text" name="bfd_website" tabindex="-1" autocomplete="off"></label>
			</div>

			<?php
			$groups = self::groups( $form );
			$multi  = count( $groups ) > 1;
			foreach ( $groups as $n => $group ) :
				?>
				<?php if ( $multi ) : ?>
					<fieldset class="bfd-form__step" data-step="<?php echo (int) $n; ?>" data-title="<?php echo esc_attr( $group['title'] ); ?>">
						<?php if ( '' !== $group['title'] ) : ?>
							<legend><?php echo esc_html( $group['title'] ); ?></legend>
						<?php endif; ?>
				<?php endif; ?>
				<?php foreach ( $group['fields'] as $f ) : ?>
					<?php self::field( $form, $f ); ?>
				<?php endforeach; ?>
				<?php if ( $multi ) : ?>
					</fieldset>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( $multi ) : ?>
				<p class="bfd-form__progress" aria-live="polite" hidden></p>
				<div class="bfd-form__nav" hidden>
					<button type="button" class="btn btn--ghost" data-prev><?php esc_html_e( 'Back', 'bfd-core' ); ?></button>
					<button type="button" class="btn" data-next><?php esc_html_e( 'Next', 'bfd-core' ); ?></button>
				</div>
			<?php endif; ?>

			<?php if ( Turnstile::enabled() ) : ?>
				<?php
				// phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion -- third-party script, always the latest version.
				wp_enqueue_script(
					'cf-turnstile',
					'https://challenges.cloudflare.com/turnstile/v0/api.js',
					array(),
					null,
					array(
						'in_footer' => true,
						'strategy'  => 'async',
					)
				);
				// phpcs:enable WordPress.WP.EnqueuedResourceParameters.MissingVersion // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion 
				?>
				<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( Turnstile::site_key() ); ?>"></div>
			<?php endif; ?>

			<div class="bfd-form__actions">
				<button type="submit" class="btn"><?php echo esc_html( $form->settings['submit_label'] ); ?></button>
			</div>
			<div class="bfd-form__msg<?php echo $status ? ' is-' . esc_attr( $status['type'] ) : ''; ?>" role="status" aria-live="polite"><?php echo $status ? esc_html( $status['text'] ) : ''; ?></div>
		</form>
		<?php
	}

	/** Result message after a non-JS redirect (?bfd_form=ID&bfd_form_status=ok|error&bfd_code=...). */
	private static function status_for( int $form_id, Form_Definition $form ): ?array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only, whitelisted values.
		if ( ! isset( $_GET['bfd_form'], $_GET['bfd_form_status'] ) || (int) $_GET['bfd_form'] !== $form_id ) {
			return null;
		}
		if ( 'ok' === $_GET['bfd_form_status'] ) {
			return array(
				'type' => 'success',
				'text' => $form->settings['success_message'],
			);
		}
		$code = isset( $_GET['bfd_code'] ) ? sanitize_key( wp_unslash( $_GET['bfd_code'] ) ) : 'server';
		// phpcs:enable
		return array(
			'type' => 'error',
			'text' => self::message( $code ),
		); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
	}

	private static function field( Form_Definition $form, array $f ): void {
		$name = 'f_' . $f['key'];
		$id   = 'bfd-' . $form->id . '-' . $f['key'];
		$req  = ! empty( $f['required'] );
		$attr = ( $req ? ' required aria-required="true"' : '' ) . ' aria-describedby="' . esc_attr( $id ) . '-err"';

		$cond_attr = '';
		if ( ! empty( $f['show_if'] ) ) {
			$cond_attr = ' data-show-if="' . esc_attr(
				wp_json_encode(
					array(
						'f'  => $f['show_if']['field'],
						'op' => $f['show_if']['op'],
						'v'  => $f['show_if']['value'],
					)
				)
			) . '"';
		}
		echo '<div class="bfd-form__field bfd-form__field--' . esc_attr( $f['type'] ) . '" data-field="' . esc_attr( $f['key'] ) . '"' . $cond_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.

		if ( 'checkbox' === $f['type'] ) {
			printf(
				'<label class="bfd-form__check"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s> <span>%4$s</span></label>',
				esc_attr( $id ),
				esc_attr( $name ),
				$attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from escaped parts.
				esc_html( $f['label'] )
			);
		} else {
			printf(
				'<label for="%s">%s%s</label>',
				esc_attr( $id ),
				esc_html( $f['label'] ),
				$req ? ' <span class="bfd-form__req" aria-hidden="true">*</span>' : ''
			);

			switch ( $f['type'] ) {
				case 'textarea':
					printf( '<textarea id="%s" name="%s" rows="5" maxlength="5000"%s></textarea>', esc_attr( $id ), esc_attr( $name ), $attr ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					break;
				case 'select':
					printf( '<select id="%s" name="%s"%s><option value="">%s</option>', esc_attr( $id ), esc_attr( $name ), $attr, esc_html__( 'Select…', 'bfd-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					foreach ( Form_Definition::parse_options( (string) ( $f['options'] ?? '' ) ) as $value => $label ) {
						printf( '<option value="%s">%s</option>', esc_attr( (string) $value ), esc_html( $label ) );
					}
					echo '</select>';
					break;
				case 'file':
					$exts = implode( ',', array_map( static fn( $e ) => '.' . $e, Submission_Handler::allowed_extensions() ) );
					printf( '<input type="file" id="%s" name="%s" accept="%s"%s>', esc_attr( $id ), esc_attr( $name ), esc_attr( $exts ), $attr ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					break;
				default:
					$html_type = in_array( $f['type'], array( 'email', 'tel', 'number' ), true ) ? $f['type'] : 'text';
					printf( '<input type="%s" id="%s" name="%s" maxlength="500"%s>', esc_attr( $html_type ), esc_attr( $id ), esc_attr( $name ), $attr ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		printf( '<span class="bfd-form__error" id="%s-err" data-error-for="%s" role="alert"></span>', esc_attr( $id ), esc_attr( $f['key'] ) );
		echo '</div>';
	}

	/**
	 * Split fields into steps at each "step" field. Without step breaks there is a single group.
	 *
	 * @return array<int, array{title:string, fields:array}>
	 */
	private static function groups( Form_Definition $form ): array {
		$groups  = array();
		$current = array(
			'title'  => '',
			'fields' => array(),
		);
		foreach ( $form->fields as $f ) {
			if ( 'step' === $f['type'] ) {
				if ( $current['fields'] || $groups ) {
					$groups[] = $current;
				}
				$current = array(
					'title'  => $f['label'],
					'fields' => array(),
				);
				continue;
			}
			$current['fields'][] = $f;
		}
		$groups[] = $current;
		return $groups;
	}
}
