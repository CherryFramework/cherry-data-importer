<?php wp_enqueue_script( 'cherry-data-export' ); ?>
<div class="cdi-kit">
	<?php
		$img = cdi()->get_setting( array( 'export', 'logo' ) );
		if ( $img ) {
			printf( '<img src="%s" alt="" class="cdi-logo">', esc_url( $img ) );
		}
	?>
	<div class="cdi-wrap">
		<p><?php echo cdi()->get_setting( array( 'export', 'message' ) ); ?></p>
		<a href="<?php echo cdi_export_interface()->get_export_url(); ?>" class="cdi-btn primary" id="cherry-export">
			<span class="dashicons dashicons-upload"></span>
			<?php _e( 'Export', 'cherry-data-importer' ); ?>
		</a>
		<div class="cdi-loader cdi-hidden"></div>
	</div>
</div>