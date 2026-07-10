<?php
/**
 * REST API: /wp-json/tts-portal/v1/voices and /tts
 * Both require a logged-in WordPress user.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'tts_portal_register_rest_routes' );
add_filter( 'rest_pre_serve_request', 'tts_portal_serve_audio_response', 10, 4 );

function tts_portal_register_rest_routes() {
	register_rest_route(
		'tts-portal/v1',
		'/voices',
		array(
			'methods'             => 'GET',
			'callback'            => 'tts_portal_rest_voices',
			'permission_callback' => 'tts_portal_rest_permission',
		)
	);

	register_rest_route(
		'tts-portal/v1',
		'/tts',
		array(
			'methods'             => 'POST',
			'callback'            => 'tts_portal_rest_tts',
			'permission_callback' => 'tts_portal_rest_permission',
		)
	);
}

function tts_portal_rest_permission() {
	return is_user_logged_in();
}

function tts_portal_rest_voices() {
	return rest_ensure_response(
		array(
			'voices' => tts_portal_get_voices(),
		)
	);
}

function tts_portal_rest_tts( WP_REST_Request $request ) {
	$text     = trim( (string) $request->get_param( 'text' ) );
	$voice_id = (string) $request->get_param( 'voiceId' );

	if ( '' === $text ) {
		return new WP_Error( 'missing_text', 'Text is missing', array( 'status' => 400 ) );
	}
	if ( '' === $voice_id ) {
		return new WP_Error( 'missing_voice', 'No voice selected', array( 'status' => 400 ) );
	}

	$known = false;
	foreach ( tts_portal_get_voices() as $voice ) {
		if ( isset( $voice['id'] ) && $voice['id'] === $voice_id ) {
			$known = true;
			break;
		}
	}
	if ( ! $known ) {
		return new WP_Error( 'unknown_voice', 'Unknown voice', array( 'status' => 400 ) );
	}

	$audio = tts_portal_synthesize( $text, $voice_id );
	if ( is_wp_error( $audio ) ) {
		tts_portal_log_request(
			array(
				'text'          => $text,
				'voice_id'      => $voice_id,
				'status'        => 'error',
				'error_message' => $audio->get_error_message(),
			)
		);
		return new WP_Error(
			$audio->get_error_code(),
			$audio->get_error_message(),
			array( 'status' => 502 )
		);
	}

	$audio_file = '';
	$saved      = tts_portal_save_audio( $audio, $text );
	if ( ! is_wp_error( $saved ) ) {
		$audio_file = $saved;
	} else {
		error_log( 'TTS Portal: failed to save audio — ' . $saved->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	tts_portal_log_request(
		array(
			'text'       => $text,
			'voice_id'   => $voice_id,
			'status'     => 'success',
			'audio_file' => $audio_file,
		)
	);

	$response = new WP_REST_Response( $audio, 200 );
	$response->header( 'Content-Type', 'audio/mpeg' );
	$response->header( 'Content-Disposition', 'inline; filename="tts.mp3"' );
	$response->header( 'X-TTS-Portal-Audio', '1' );
	return $response;
}

/**
 * Serve raw MP3 bytes instead of JSON-encoding the REST body.
 *
 * @param bool             $served  Whether the request has already been served.
 * @param WP_HTTP_Response $result  Result to send to the client.
 * @param WP_REST_Request  $request Request used to generate the response.
 * @param WP_REST_Server   $server  Server instance.
 * @return bool
 */
function tts_portal_serve_audio_response( $served, $result, $request, $server ) {
	if ( $served || ! ( $result instanceof WP_REST_Response ) ) {
		return $served;
	}

	$headers = $result->get_headers();
	if ( empty( $headers['X-TTS-Portal-Audio'] ) ) {
		return $served;
	}

	$data = $result->get_data();
	if ( ! is_string( $data ) ) {
		return $served;
	}

	header( 'Content-Type: audio/mpeg' );
	header( 'Content-Disposition: inline; filename="tts.mp3"' );
	header( 'Content-Length: ' . strlen( $data ) );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary audio
	echo $data;
	return true;
}
