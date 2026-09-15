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
 * Built-in languages, used until an admin saves their own list.
 *
 * @return array<int, array{code: string, label: string}>
 */
function tts_portal_default_languages() {
	return array(
		array( 'code' => 'sv', 'label' => 'Swedish' ),
		array( 'code' => 'no', 'label' => 'Norwegian' ),
		array( 'code' => 'fi', 'label' => 'Finnish' ),
		array( 'code' => 'de', 'label' => 'German' ),
		array( 'code' => 'da', 'label' => 'Danish' ),
		array( 'code' => 'en', 'label' => 'English' ),
	);
}

/**
 * Languages for the portal dropdown (from admin settings), keyed by
 * ISO 639-1 code (sent to ElevenLabs as `language_code`).
 *
 * @return array<string, string>
 */
function tts_portal_get_languages() {
	$languages = get_option( 'tts_portal_languages', null );
	if ( ! is_array( $languages ) || empty( $languages ) ) {
		$languages = tts_portal_default_languages();
	}

	$clean = array();
	foreach ( $languages as $language ) {
		if ( ! is_array( $language ) ) {
			continue;
		}
		$code  = isset( $language['code'] ) ? trim( (string) $language['code'] ) : '';
		$label = isset( $language['label'] ) ? trim( (string) $language['label'] ) : '';
		if ( '' === $code ) {
			continue;
		}
		$clean[ $code ] = '' !== $label ? $label : $code;
	}

	return $clean;
}

/**
 * Swedish when configured, otherwise the first language in the list.
 */
function tts_portal_get_default_language() {
	$languages = tts_portal_get_languages();
	if ( isset( $languages['sv'] ) ) {
		return 'sv';
	}
	return (string) key( $languages );
}

/**
 * Display label for a language code. Also resolves built-in languages an
 * admin has since removed, so older log rows keep a readable label.
 */
function tts_portal_language_label( $code ) {
	$languages = tts_portal_get_languages();
	if ( isset( $languages[ $code ] ) ) {
		return $languages[ $code ];
	}
	foreach ( tts_portal_default_languages() as $language ) {
		if ( $language['code'] === $code ) {
			return $language['label'];
		}
	}
	return $code;
}

/**
 * eleven_flash_v2_5       -> multilingual incl. Norwegian, supports language_code (default)
 * eleven_multilingual_v2  -> higher quality, but ignores language_code and lacks Norwegian
 * eleven_v3               -> most expressive, supports language_code, 5k char limit
 */
function tts_portal_get_model_id() {
	return 'eleven_flash_v2_5';
}

/** Higher stability = steadier, lower = more expressive. */
function tts_portal_get_voice_settings() {
	return array(
		'stability'        => 0.5,
		'similarity_boost' => 0.75,
	);
}
