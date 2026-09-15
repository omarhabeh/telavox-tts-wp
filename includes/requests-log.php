<?php
/**
 * TTS request log: custom table + admin list (admins only).
 * Successful generations also store a local MP3 copy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TTS_PORTAL_DB_VERSION', '1.2' );

/**
 * Full table name with WP prefix.
 */
function tts_portal_requests_table() {
	global $wpdb;
	return $wpdb->prefix . 'tts_portal_requests';
}

/**
 * Create / update the requests table.
 */
function tts_portal_install_requests_table() {
	global $wpdb;

	$table   = tts_portal_requests_table();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		user_email varchar(200) NOT NULL DEFAULT '',
		user_login varchar(60) NOT NULL DEFAULT '',
		display_name varchar(250) NOT NULL DEFAULT '',
		voice_id varchar(100) NOT NULL DEFAULT '',
		voice_label varchar(200) NOT NULL DEFAULT '',
		language_code varchar(10) NULL,
		text_content longtext NOT NULL,
		char_count int(11) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'success',
		error_message text NULL,
		audio_file varchar(500) NOT NULL DEFAULT '',
		ip_address varchar(100) NOT NULL DEFAULT '',
		user_agent text NULL,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY created_at (created_at),
		KEY status (status)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'tts_portal_db_version', TTS_PORTAL_DB_VERSION );
}

/**
 * Ensure table exists (theme switch + version bump).
 */
function tts_portal_maybe_install_table() {
	if ( get_option( 'tts_portal_db_version' ) !== TTS_PORTAL_DB_VERSION ) {
		tts_portal_install_requests_table();
	}
}
add_action( 'after_switch_theme', 'tts_portal_install_requests_table' );
add_action( 'admin_init', 'tts_portal_maybe_install_table' );

/**
 * Resolve voice label from id.
 */
function tts_portal_voice_label( $voice_id ) {
	foreach ( tts_portal_get_voices() as $voice ) {
		if ( isset( $voice['id'] ) && $voice['id'] === $voice_id ) {
			return isset( $voice['label'] ) ? (string) $voice['label'] : $voice_id;
		}
	}
	return $voice_id;
}

/**
 * Save MP3 bytes under uploads/tts-portal/YYYY/MM/.
 *
 * @param string $audio_bytes Raw MP3 data.
 * @param string $text        Source text (used for filename slug).
 * @return string|WP_Error Relative path from uploads basedir, or error.
 */
function tts_portal_save_audio( $audio_bytes, $text ) {
	$upload = wp_upload_dir();
	if ( ! empty( $upload['error'] ) ) {
		return new WP_Error( 'upload_dir', $upload['error'] );
	}

	$subdir = '/tts-portal/' . gmdate( 'Y' ) . '/' . gmdate( 'm' );
	$dir    = trailingslashit( $upload['basedir'] ) . ltrim( $subdir, '/' );

	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'mkdir_failed', 'Could not create audio storage folder.' );
	}

	// Empty index.php so the directory is not browsable.
	$index = trailingslashit( $upload['basedir'] ) . 'tts-portal/index.php';
	if ( ! file_exists( $index ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $index, "<?php\n// Silence is golden.\n" );
	}

	$slug = sanitize_title( mb_substr( trim( (string) $text ), 0, 40 ) );
	if ( '' === $slug ) {
		$slug = 'tts';
	}
	$filename = $slug . '-' . wp_generate_password( 8, false, false ) . '.mp3';
	$path     = trailingslashit( $dir ) . $filename;

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$written = file_put_contents( $path, $audio_bytes );
	if ( false === $written ) {
		return new WP_Error( 'write_failed', 'Could not save audio file.' );
	}

	return ltrim( $subdir . '/' . $filename, '/' );
}

/**
 * Absolute filesystem path for a stored audio_file value.
 */
function tts_portal_audio_abs_path( $audio_file ) {
	if ( ! $audio_file ) {
		return '';
	}
	$upload = wp_upload_dir();
	return trailingslashit( $upload['basedir'] ) . ltrim( $audio_file, '/' );
}

/**
 * Public URL for a stored audio_file value.
 */
function tts_portal_audio_url( $audio_file ) {
	if ( ! $audio_file ) {
		return '';
	}
	$upload = wp_upload_dir();
	return trailingslashit( $upload['baseurl'] ) . ltrim( $audio_file, '/' );
}

/**
 * Insert one TTS request row.
 *
 * @param array $args {
 *   @type string $text
 *   @type string $voice_id
 *   @type string $language_code
 *   @type string $status        success|error
 *   @type string $error_message
 *   @type string $audio_file    Relative path under uploads
 * }
 * @return int|false Insert ID or false.
 */
function tts_portal_log_request( $args ) {
	global $wpdb;

	$user     = wp_get_current_user();
	$text     = isset( $args['text'] ) ? (string) $args['text'] : '';
	$voice_id = isset( $args['voice_id'] ) ? (string) $args['voice_id'] : '';

	$inserted = $wpdb->insert(
		tts_portal_requests_table(),
		array(
			'user_id'       => $user->ID ? (int) $user->ID : 0,
			'user_email'    => $user->user_email ? $user->user_email : '',
			'user_login'    => $user->user_login ? $user->user_login : '',
			'display_name'  => $user->display_name ? $user->display_name : '',
			'voice_id'      => $voice_id,
			'voice_label'   => tts_portal_voice_label( $voice_id ),
			'language_code' => isset( $args['language_code'] ) ? (string) $args['language_code'] : '',
			'text_content'  => $text,
			'char_count'    => mb_strlen( $text ),
			'status'        => isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'success',
			'error_message' => isset( $args['error_message'] ) ? (string) $args['error_message'] : '',
			'audio_file'    => isset( $args['audio_file'] ) ? (string) $args['audio_file'] : '',
			'ip_address'    => tts_portal_client_ip(),
			'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 ) : '',
			'created_at'    => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( ! $inserted ) {
		return false;
	}

	return (int) $wpdb->insert_id;
}

/**
 * Best-effort client IP.
 */
function tts_portal_client_ip() {
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		return substr( trim( $parts[0] ), 0, 100 );
	}
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		return substr( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), 0, 100 );
	}
	return '';
}

/**
 * Admin menu: TTS Requests (manage_options only).
 */
function tts_portal_requests_admin_menu() {
	add_menu_page(
		'TTS Requests',
		'TTS Requests',
		'manage_options',
		'tts-portal-requests',
		'tts_portal_render_requests_page',
		'dashicons-microphone',
		58
	);
}
add_action( 'admin_menu', 'tts_portal_requests_admin_menu' );

/**
 * Render the requests list page.
 */
function tts_portal_render_requests_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'tts-portal' ) );
	}

	global $wpdb;
	$table = tts_portal_requests_table();

	$per_page = 20;
	$page     = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$offset   = ( $page - 1 ) * $per_page;
	$view_id  = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $view_id ) {
		tts_portal_render_request_detail( $view_id );
		return;
	}

	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, user_email, display_name, voice_label, voice_id, language_code, text_content, char_count, status, audio_file, created_at
			FROM {$table}
			ORDER BY created_at DESC, id DESC
			LIMIT %d OFFSET %d",
			$per_page,
			$offset
		)
	);

	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	?>
	<div class="wrap">
		<h1>TTS Requests</h1>
		<p>Every text-to-speech generation from the portal. <?php echo esc_html( number_format_i18n( $total ) ); ?> total.</p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" style="width:4%">ID</th>
					<th scope="col" style="width:14%">Email</th>
					<th scope="col" style="width:10%">Voice</th>
					<th scope="col" style="width:8%">Language</th>
					<th scope="col">Text</th>
					<th scope="col" style="width:5%">Chars</th>
					<th scope="col" style="width:7%">Status</th>
					<th scope="col" style="width:18%">Audio</th>
					<th scope="col" style="width:12%">Time</th>
					<th scope="col" style="width:5%"></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr>
						<td colspan="10">No requests logged yet.</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row->id ); ?></td>
							<td>
								<?php echo esc_html( $row->user_email ); ?>
								<?php if ( $row->display_name ) : ?>
									<br><span class="description"><?php echo esc_html( $row->display_name ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row->voice_label ? $row->voice_label : $row->voice_id ); ?></td>
							<td>
								<?php if ( $row->language_code ) : ?>
									<?php echo esc_html( tts_portal_language_label( $row->language_code ) ); ?>
								<?php else : ?>
									<span class="description">—</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( tts_portal_truncate( $row->text_content, 100 ) ); ?></td>
							<td><?php echo esc_html( (string) $row->char_count ); ?></td>
							<td>
								<?php if ( 'success' === $row->status ) : ?>
									<span style="color:#195E3D;font-weight:600;">success</span>
								<?php else : ?>
									<span style="color:#c0392b;font-weight:600;"><?php echo esc_html( $row->status ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$audio_url = ! empty( $row->audio_file ) ? tts_portal_audio_url( $row->audio_file ) : '';
								if ( $audio_url ) :
									?>
									<audio controls preload="none" style="max-width:100%;height:32px;vertical-align:middle;" src="<?php echo esc_url( $audio_url ); ?>"></audio>
									<br>
									<a href="<?php echo esc_url( $audio_url ); ?>" download>Download</a>
								<?php else : ?>
									<span class="description">—</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( mysql2date( 'Y-m-d H:i:s', $row->created_at ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=tts-portal-requests&view=' . (int) $row->id ) ); ?>">View</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $page,
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Single request detail view.
 */
function tts_portal_render_request_detail( $id ) {
	global $wpdb;

	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . tts_portal_requests_table() . ' WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$id
		)
	);

	$back      = admin_url( 'admin.php?page=tts-portal-requests' );
	$audio_url = ( $row && ! empty( $row->audio_file ) ) ? tts_portal_audio_url( $row->audio_file ) : '';
	?>
	<div class="wrap">
		<h1>TTS Request #<?php echo esc_html( (string) $id ); ?></h1>
		<p><a href="<?php echo esc_url( $back ); ?>">&larr; Back to all requests</a></p>

		<?php if ( ! $row ) : ?>
			<p>Request not found.</p>
		<?php else : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Time</th>
					<td><?php echo esc_html( mysql2date( 'Y-m-d H:i:s', $row->created_at ) ); ?></td>
				</tr>
				<tr>
					<th scope="row">Status</th>
					<td><?php echo esc_html( $row->status ); ?></td>
				</tr>
				<?php if ( $row->error_message ) : ?>
					<tr>
						<th scope="row">Error</th>
						<td><code><?php echo esc_html( $row->error_message ); ?></code></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row">User ID</th>
					<td><?php echo esc_html( (string) $row->user_id ); ?></td>
				</tr>
				<tr>
					<th scope="row">Email</th>
					<td><?php echo esc_html( $row->user_email ); ?></td>
				</tr>
				<tr>
					<th scope="row">Username</th>
					<td><?php echo esc_html( $row->user_login ); ?></td>
				</tr>
				<tr>
					<th scope="row">Display name</th>
					<td><?php echo esc_html( $row->display_name ); ?></td>
				</tr>
				<tr>
					<th scope="row">Voice</th>
					<td>
						<?php echo esc_html( $row->voice_label ); ?>
						<code><?php echo esc_html( $row->voice_id ); ?></code>
					</td>
				</tr>
				<tr>
					<th scope="row">Language</th>
					<td>
						<?php if ( $row->language_code ) : ?>
							<?php echo esc_html( tts_portal_language_label( $row->language_code ) ); ?>
							<code><?php echo esc_html( $row->language_code ); ?></code>
						<?php else : ?>
							<span class="description">—</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">Characters</th>
					<td><?php echo esc_html( (string) $row->char_count ); ?></td>
				</tr>
				<tr>
					<th scope="row">Text</th>
					<td><textarea class="large-text" rows="8" readonly><?php echo esc_textarea( $row->text_content ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row">Audio</th>
					<td>
						<?php if ( $audio_url ) : ?>
							<audio controls src="<?php echo esc_url( $audio_url ); ?>" style="width:100%;max-width:480px;"></audio>
							<p>
								<a class="button button-primary" href="<?php echo esc_url( $audio_url ); ?>" download>Download MP3</a>
								<code><?php echo esc_html( $row->audio_file ); ?></code>
							</p>
						<?php else : ?>
							<span class="description">No audio file saved for this request.</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">IP address</th>
					<td><?php echo esc_html( $row->ip_address ); ?></td>
				</tr>
				<tr>
					<th scope="row">User agent</th>
					<td><code><?php echo esc_html( $row->user_agent ); ?></code></td>
				</tr>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Truncate text for list view.
 */
function tts_portal_truncate( $text, $length = 120 ) {
	$text = (string) $text;
	if ( mb_strlen( $text ) <= $length ) {
		return $text;
	}
	return mb_substr( $text, 0, $length ) . '…';
}
