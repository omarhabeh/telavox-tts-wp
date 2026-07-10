<?php
/**
 * Voice list and ElevenLabs model settings.
 * Voices are managed in Settings → TTS Portal (stored in the DB).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Voices for the portal dropdown (from admin settings).
 *
 * @return array<int, array{id: string, label: string}>
 */
function tts_portal_get_voices() {
	$voices = get_option( 'tts_portal_voices', null );

	if ( ! is_array( $voices ) ) {
		return array();
	}

	$clean = array();
	foreach ( $voices as $voice ) {
		if ( ! is_array( $voice ) ) {
			continue;
		}
		$id    = isset( $voice['id'] ) ? trim( (string) $voice['id'] ) : '';
		$label = isset( $voice['label'] ) ? trim( (string) $voice['label'] ) : '';
		if ( '' === $id ) {
			continue;
		}
		$clean[] = array(
			'id'    => $id,
			'label' => '' !== $label ? $label : $id,
		);
	}

	return $clean;
}

/**
 * eleven_multilingual_v2  -> best quality for Swedish (default)
 * eleven_flash_v2_5       -> faster/cheaper, still multilingual
 * eleven_turbo_v2_5       -> fast, low latency
 */
function tts_portal_get_model_id() {
	return 'eleven_multilingual_v2';
}

/** Higher stability = steadier, lower = more expressive. */
function tts_portal_get_voice_settings() {
	return array(
		'stability'        => 0.5,
		'similarity_boost' => 0.75,
	);
}
