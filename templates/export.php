<?php wp_enqueue_script( 'cherry-data-export' ); ?>
<div class="cdi-wrap">
	<?php cdi_tools()->get_page_title( '<h2 class="page-title">', '</h2>', true ); ?>
	<div class="cdi-message"><?php
		echo cdi()->get_setting( array( 'export', 'message' ) );
	?></div>
	<a href="<?php echo cdi_export_interface()->get_export_url(); ?>" class="cdi-btn primary" id="cherry-export">
		<span class="dashicons dashicons-upload"></span>
		<?php _e( 'Export', 'cherry-data-importer' ); ?>
	</a>
	<div class="cdi-loader cdi-hidden"></div>
</div>