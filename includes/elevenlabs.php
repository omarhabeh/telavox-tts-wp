<?php
/**
 * ElevenLabs TTS helper.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Call ElevenLabs and return MP3 audio bytes.
 *
 * @param string $text          Text to synthesize.
 * @param string $voice_id      ElevenLabs voice ID.
 * @param string $language_code ISO 639-1 code to enforce language and text normalization.
 * @return string|WP_Error Raw MP3 bytes, or WP_Error on failure.
 */
function tts_portal_synthesize( $text, $voice_id, $language_code ) {
	$api_key = tts_portal_get_api_key();
	if ( ! $api_key ) {
		return new WP_Error( 'missing_key', 'ElevenLabs API key is not configured. Set it under Settings → TTS Portal.' );
	}

	$base_url = tts_portal_get_api_base_url();
	$url      = trailingslashit( $base_url ) . 'v1/text-to-speech/' . rawurlencode( $voice_id );

	$response = wp_remote_post(
		$url,
		array(
			'timeout' => 60,
			'headers' => array(
				'xi-api-key'   => $api_key,
				'Content-Type' => 'application/json',
				'Accept'       => 'audio/mpeg',
			),
			'body'    => wp_json_encode(
				array(
					'text'           => $text,
					'model_id'       => tts_portal_get_model_id(),
					'language_code'  => $language_code,
					'voice_settings' => tts_portal_get_voice_settings(),
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'request_failed', $response->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	if ( $code < 200 || $code >= 300 ) {
		$detail = is_string( $body ) ? substr( $body, 0, 400 ) : '';
		return new WP_Error( 'elevenlabs_error', sprintf( 'ElevenLabs responded %d: %s', $code, $detail ) );
	}

	return $body;
}

/**
 * API key from wp-config constant or Settings option.
 */
function tts_portal_get_api_key() {
	if ( defined( 'TTS_PORTAL_ELEVENLABS_API_KEY' ) && TTS_PORTAL_ELEVENLABS_API_KEY ) {
		return TTS_PORTAL_ELEVENLABS_API_KEY;
	}
	return (string) get_option( 'tts_portal_elevenlabs_api_key', '' );
}

/**
 * Base URL. Use EU residency URL if your key is residency-scoped, e.g.
 * https://api.eu.residency.elevenlabs.io
 */
function tts_portal_get_api_base_url() {
	if ( defined( 'TTS_PORTAL_ELEVENLABS_BASE_URL' ) && TTS_PORTAL_ELEVENLABS_BASE_URL ) {
		return untrailingslashit( TTS_PORTAL_ELEVENLABS_BASE_URL );
	}
	$url = (string) get_option( 'tts_portal_elevenlabs_base_url', 'https://api.elevenlabs.io' );
	return untrailingslashit( $url ? $url : 'https://api.elevenlabs.io' );
}
