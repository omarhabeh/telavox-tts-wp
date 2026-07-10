<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'tts-portal' ); ?>>
<?php wp_body_open(); ?>

<div class="tts-portal-wrap">
	<img
		class="tts-logo"
		src="<?php echo esc_url( get_template_directory_uri() . '/assets/telavox-logo.svg' ); ?>"
		alt="Telavox"
		width="200"
		height="36"
	/>

	<div id="appView" class="card">
		<div class="topbar">
			<div>
				<h1>Telavox Text to speech</h1>
				<span class="who" id="whoami"></span>
			</div>
			<a class="logout" id="logoutBtn" href="#">Log out</a>
		</div>

		<div class="field">
			<label for="voice">Voice</label>
			<select id="voice"></select>
		</div>

		<div class="field">
			<label for="text">Text</label>
			<textarea id="text" placeholder="Type the text to be read aloud..."></textarea>
		</div>

		<div class="row">
			<button type="button" id="generateBtn">Generate</button>
		</div>
		<div id="appError" class="error"></div>

		<div id="result" class="result hidden">
			<audio id="player" controls></audio>
			<div class="row">
				<a id="downloadLink" class="download" download>Download</a>
				<button type="button" class="secondary" id="redoBtn">Redo</button>
			</div>
		</div>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
