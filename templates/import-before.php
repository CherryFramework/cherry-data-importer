<?php
/**
 * Starter import template
 */
?>
<div>
	<?php echo cdi_interface()->get_welcome_message(); ?>
	<?php echo cdi_interface()->get_import_files_select(); ?>
	<?php echo cdi_interface()->get_import_file_input(); ?>
	<input type="hidden" name="referrer" value="<?php echo cdi_tools()->get_page_url(); ?>">
	<button id="cherry-import-start" class="cdi-btn">
		<?php esc_html_e( 'Start import', 'cherry-data-importer' ); ?>
		<span class="dashicons dashicons-arrow-right-alt"></span>
	</button>
</div>
