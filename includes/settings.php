<?php
/**
 * Admin settings: ElevenLabs API + configurable voices and languages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'tts_portal_add_settings_page' );
add_action( 'admin_init', 'tts_portal_register_settings' );
add_action( 'admin_enqueue_scripts', 'tts_portal_settings_assets' );

function tts_portal_add_settings_page() {
	add_options_page(
		'TTS Portal',
		'TTS Portal',
		'manage_options',
		'tts-portal',
		'tts_portal_render_settings_page'
	);
}

function tts_portal_register_settings() {
	register_setting(
		'tts_portal_settings',
		'tts_portal_elevenlabs_api_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'tts_portal_settings',
		'tts_portal_elevenlabs_base_url',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => 'https://api.elevenlabs.io',
		)
	);

	register_setting(
		'tts_portal_settings',
		'tts_portal_voices',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'tts_portal_sanitize_voices',
			'default'           => array(),
		)
	);

	register_setting(
		'tts_portal_settings',
		'tts_portal_languages',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'tts_portal_sanitize_languages',
			'default'           => tts_portal_default_languages(),
		)
	);
}

/**
 * Sanitize the languages repeater from the settings form.
 *
 * @param mixed $input Raw POST value.
 * @return array<int, array{code: string, label: string}>
 */
function tts_portal_sanitize_languages( $input ) {
	if ( ! is_array( $input ) ) {
		return array();
	}

	$clean   = array();
	$seen    = array();
	$invalid = array();
	foreach ( $input as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$code  = isset( $row['code'] ) ? strtolower( trim( sanitize_text_field( $row['code'] ) ) ) : '';
		$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
		if ( '' === $code || isset( $seen[ $code ] ) ) {
			continue;
		}
		// ElevenLabs expects a two-letter ISO 639-1 code.
		if ( ! preg_match( '/^[a-z]{2}$/', $code ) ) {
			$invalid[] = $code;
			continue;
		}
		$seen[ $code ] = true;
		$clean[]       = array(
			'code'  => $code,
			'label' => $label,
		);
	}

	if ( $invalid ) {
		add_settings_error(
			'tts_portal_languages',
			'invalid_language_code',
			sprintf(
				'Skipped invalid language code(s): %s. Use two-letter ISO 639-1 codes such as sv or en.',
				esc_html( implode( ', ', $invalid ) )
			)
		);
	}

	return $clean;
}

/**
 * Sanitize the voices repeater from the settings form.
 *
 * @param mixed $input Raw POST value.
 * @return array<int, array{id: string, label: string}>
 */
function tts_portal_sanitize_voices( $input ) {
	if ( ! is_array( $input ) ) {
		return array();
	}

	$clean = array();
	foreach ( $input as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$id    = isset( $row['id'] ) ? sanitize_text_field( $row['id'] ) : '';
		$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
		if ( '' === $id ) {
			continue;
		}
		$clean[] = array(
			'id'    => $id,
			'label' => $label,
		);
	}

	return $clean;
}

function tts_portal_settings_assets( $hook ) {
	if ( 'settings_page_tts-portal' !== $hook ) {
		return;
	}

	wp_enqueue_script(
		'tts-portal-settings',
		get_template_directory_uri() . '/assets/settings-voices.js',
		array(),
		'1.1.0',
		true
	);
}

function tts_portal_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$key_locked = defined( 'TTS_PORTAL_ELEVENLABS_API_KEY' ) && TTS_PORTAL_ELEVENLABS_API_KEY;
	$url_locked = defined( 'TTS_PORTAL_ELEVENLABS_BASE_URL' ) && TTS_PORTAL_ELEVENLABS_BASE_URL;

	$voices = get_option( 'tts_portal_voices', array() );
	if ( ! is_array( $voices ) || empty( $voices ) ) {
		$voices = array(
			array(
				'id'    => '',
				'label' => '',
			),
		);
	}

	$languages = get_option( 'tts_portal_languages', null );
	if ( ! is_array( $languages ) || empty( $languages ) ) {
		$languages = tts_portal_default_languages();
	}
	?>
	<div class="wrap">
		<h1>TTS Portal</h1>
		<p>Configure ElevenLabs and the voices and languages shown in the portal dropdowns.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'tts_portal_settings' ); ?>

			<h2 class="title">API</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="tts_portal_elevenlabs_api_key">ElevenLabs API key</label></th>
					<td>
						<?php if ( $key_locked ) : ?>
							<p class="description">Set via <code>TTS_PORTAL_ELEVENLABS_API_KEY</code> in <code>wp-config.php</code>.</p>
						<?php else : ?>
							<input
								type="password"
								class="regular-text"
								id="tts_portal_elevenlabs_api_key"
								name="tts_portal_elevenlabs_api_key"
								value="<?php echo esc_attr( get_option( 'tts_portal_elevenlabs_api_key', '' ) ); ?>"
								autocomplete="off"
							/>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tts_portal_elevenlabs_base_url">API base URL</label></th>
					<td>
						<?php if ( $url_locked ) : ?>
							<p class="description">Set via <code>TTS_PORTAL_ELEVENLABS_BASE_URL</code> in <code>wp-config.php</code>.</p>
						<?php else : ?>
							<input
								type="url"
								class="regular-text"
								id="tts_portal_elevenlabs_base_url"
								name="tts_portal_elevenlabs_base_url"
								value="<?php echo esc_attr( get_option( 'tts_portal_elevenlabs_base_url', 'https://api.elevenlabs.io' ) ); ?>"
							/>
							<p class="description">
								Default: <code>https://api.elevenlabs.io</code>.
								For EU residency keys use <code>https://api.eu.residency.elevenlabs.io</code>.
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<h2 class="title">Voices</h2>
			<p class="description">Add ElevenLabs voice IDs and the labels shown in the portal dropdown. Empty ID rows are ignored on save.</p>

			<table class="widefat striped" id="tts-voices-table" style="max-width:900px;margin:12px 0 16px;">
				<thead>
					<tr>
						<th style="width:40%">Label</th>
						<th style="width:45%">Voice ID</th>
						<th style="width:15%"></th>
					</tr>
				</thead>
				<tbody id="tts-voices-rows">
					<?php foreach ( $voices as $i => $voice ) : ?>
						<tr class="tts-repeater-row">
							<td>
								<input
									type="text"
									class="regular-text"
									name="tts_portal_voices[<?php echo esc_attr( (string) $i ); ?>][label]"
									value="<?php echo esc_attr( isset( $voice['label'] ) ? $voice['label'] : '' ); ?>"
									placeholder="e.g. Swedish Female"
									style="width:100%"
								/>
							</td>
							<td>
								<input
									type="text"
									class="regular-text"
									name="tts_portal_voices[<?php echo esc_attr( (string) $i ); ?>][id]"
									value="<?php echo esc_attr( isset( $voice['id'] ) ? $voice['id'] : '' ); ?>"
									placeholder="ElevenLabs voice ID"
									style="width:100%"
								/>
							</td>
							<td>
								<button type="button" class="button tts-repeater-remove">Remove</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<button type="button" class="button" id="tts-add-voice">Add voice</button>
			</p>

			<h2 class="title">Languages</h2>
			<p class="description">
				Languages shown in the portal dropdown. The code is sent to ElevenLabs as <code>language_code</code> and must be an
				ISO 639-1 code (e.g. <code>sv</code>, <code>en</code>). Swedish (<code>sv</code>) is the default when listed, otherwise the first row.
				Empty code rows are ignored on save.
			</p>

			<table class="widefat striped" id="tts-languages-table" style="max-width:900px;margin:12px 0 16px;">
				<thead>
					<tr>
						<th style="width:40%">Label</th>
						<th style="width:45%">Language code</th>
						<th style="width:15%"></th>
					</tr>
				</thead>
				<tbody id="tts-languages-rows">
					<?php foreach ( $languages as $i => $language ) : ?>
						<tr class="tts-repeater-row">
							<td>
								<input
									type="text"
									class="regular-text"
									name="tts_portal_languages[<?php echo esc_attr( (string) $i ); ?>][label]"
									value="<?php echo esc_attr( isset( $language['label'] ) ? $language['label'] : '' ); ?>"
									placeholder="e.g. Swedish"
									style="width:100%"
								/>
							</td>
							<td>
								<input
									type="text"
									class="regular-text"
									name="tts_portal_languages[<?php echo esc_attr( (string) $i ); ?>][code]"
									value="<?php echo esc_attr( isset( $language['code'] ) ? $language['code'] : '' ); ?>"
									placeholder="e.g. sv"
									style="width:100%"
								/>
							</td>
							<td>
								<button type="button" class="button tts-repeater-remove">Remove</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<button type="button" class="button" id="tts-add-language">Add language</button>
			</p>

			<?php submit_button( 'Save settings' ); ?>
		</form>
	</div>

	<template id="tts-voice-row-template">
		<tr class="tts-repeater-row">
			<td>
				<input type="text" class="regular-text" name="tts_portal_voices[__i__][label]" value="" placeholder="e.g. Swedish Female" style="width:100%" />
			</td>
			<td>
				<input type="text" class="regular-text" name="tts_portal_voices[__i__][id]" value="" placeholder="ElevenLabs voice ID" style="width:100%" />
			</td>
			<td>
				<button type="button" class="button tts-repeater-remove">Remove</button>
			</td>
		</tr>
	</template>

	<template id="tts-language-row-template">
		<tr class="tts-repeater-row">
			<td>
				<input type="text" class="regular-text" name="tts_portal_languages[__i__][label]" value="" placeholder="e.g. Swedish" style="width:100%" />
			</td>
			<td>
				<input type="text" class="regular-text" name="tts_portal_languages[__i__][code]" value="" placeholder="e.g. sv" style="width:100%" />
			</td>
			<td>
				<button type="button" class="button tts-repeater-remove">Remove</button>
			</td>
		</tr>
	</template>
	<?php
}
