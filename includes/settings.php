<?php
/**
 * Admin settings: ElevenLabs API + configurable voices.
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
		'1.0.0',
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
	?>
	<div class="wrap">
		<h1>TTS Portal</h1>
		<p>Configure ElevenLabs and the voices shown in the portal dropdown.</p>
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
						<tr class="tts-voice-row">
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
								<button type="button" class="button tts-remove-voice">Remove</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<button type="button" class="button" id="tts-add-voice">Add voice</button>
			</p>

			<?php submit_button( 'Save settings' ); ?>
		</form>
	</div>

	<template id="tts-voice-row-template">
		<tr class="tts-voice-row">
			<td>
				<input type="text" class="regular-text" name="tts_portal_voices[__i__][label]" value="" placeholder="e.g. Swedish Female" style="width:100%" />
			</td>
			<td>
				<input type="text" class="regular-text" name="tts_portal_voices[__i__][id]" value="" placeholder="ElevenLabs voice ID" style="width:100%" />
			</td>
			<td>
				<button type="button" class="button tts-remove-voice">Remove</button>
			</td>
		</tr>
	</template>
	<?php
}
