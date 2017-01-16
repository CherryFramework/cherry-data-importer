<?php
/**
 * Regenerate thumbnails template
 */
?>
<div class="cdi-content">
	<?php cdi_slider()->slider_assets(); ?>
	<?php cdi_slider()->render(); ?>
	<div id="cherry-import-progress" class="cdi-progress">
		<span class="cdi-progress__placeholder"><?php
			esc_html_e( 'Starting process, please wait few seconds...', 'cherry-data-importer' );
		?></span>
		<span class="cdi-progress__bar"><span class="cdi-progress__label"><span></span></span></span>
	</div>
</div>