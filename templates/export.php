<?php wp_enqueue_script( 'cherry-data-export' ); ?>
<div class="cdi-kit">
	<p><?php _e( 'or export all content with TemplateMonster Data Export tool', 'cherry-data-importer' ); ?></p>
	<a href="<?php echo cdi_export_interface()->get_export_url(); ?>" class="cdi-btn primary" id="cherry-export">
		<span class="dashicons dashicons-upload"></span>
		<?php _e( 'Export', 'cherry-data-importer' ); ?>
	</a>
	<div class="cdi-loader cdi-hidden"></div>
</div>