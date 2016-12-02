<?php
/**
 * Starter import template
 */
?>
<div>
	<?php
		/**
		 * Hook before importer messages output.
		 *
		 * @hooked Cherry_Data_Importer_Interface::check_server_params - 10;
		 */
		do_action( 'cherry_data_importer_before_messages' );
	?>
	<?php echo cdi_interface()->get_welcome_message(); ?>
	<?php if ( cdi_interface()->is_advanced_import() ) : ?>
		<?php cdi_interface()->advanced_import(); ?>
	<?php else : ?>
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
	<?php endif; ?>
</div>
