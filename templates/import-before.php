<?php
/**
 * Starter import template
 */
?>
<div>
	<?php echo cdi_interface()->get_welcome_message(); ?>
	<div class="cdi-actions">
		<?php echo cdi_interface()->get_import_files_select( '<div class="cdi-file-select">', '</div>' ); ?>
		<?php if ( 1 <= cdi_interface()->get_xml_count() && cdi()->get_setting( array( 'xml', 'use_upload' ) ) ) {
			echo '<span class="cdi-delimiter">' . __( 'or', 'cherry-data-importer' ) . '</span>';
		} ?>
		<?php echo cdi_interface()->get_import_file_input( '<div class="cdi-file-upload">', '</div>' ); ?>
	</div>
	<input type="hidden" name="referrer" value="<?php echo cdi_tools()->get_page_url(); ?>">
	<button id="cherry-import-start" class="cdi-btn primary">
		<span class="dashicons dashicons-download"></span>
		<?php esc_html_e( 'Start import', 'cherry-data-importer' ); ?>
	</button>
</div>
