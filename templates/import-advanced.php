<?php
/**
 * Advanced import template
 */
$item = ! empty( cdi_interface()->data['advanced-item'] ) ? cdi_interface()->data['advanced-item'] : false;
$slug = ! empty( cdi_interface()->data['advanced-slug'] ) ? cdi_interface()->data['advanced-slug'] : false;

if ( ! $item || ! $slug ) {
	return;
}

$thumb    = ! empty( $item['thumb'] ) ? esc_url( $item['thumb'] ) : false;
$label    = ! empty( $item['label'] ) ? $item['label'] : false;
$demo_url = ! empty( $item['demo_url'] ) ? esc_url( $item['demo_url'] ) : false;
$plugins  = ! empty( $item['plugins'] ) ? $item['plugins'] : false;
?>
<div class="advanced-item">
	<div class="advanced-item__thumb">
		<?php
			if ( $thumb ) {
				printf( '<a href="%3$s"><img src="%1$s" alt="%2$s"></a>', $thumb, $label, $demo_url );
			}
		?>
	</div>
	<div class="advanced-item__content">
		<h3 class="advanced-item__title"><?php echo $label; ?></h3>
		<?php if ( ! empty( $plugins ) ) : ?>
		<div class="advanced-item__recommended-plugins"><?php
			esc_html_e( 'Recommended Plugins:', 'cherry-data-importer' );
		?></div>
		<div class="advanced-item__plugins-list"><?php
			foreach ( $plugins as $slug => $name ) {
				$plugin = sprintf( '%1$s/%1$s.php', $slug );
				printf(
					'<span class="advanced-item__plugin %2$s">%1$s</span>',
					$name,
					is_plugin_active( $plugin ) ? 'is-active' : 'is-inactive'
				);
			}
		?></div>
		<?php endif; ?>
		<div class="advanced-item__install-type">
			<label class="advanced-item__type-checkbox">
				<input type="checkbox"><?php esc_html_e( 'Optimize Demo Content', 'cherry-data-importer' ); ?>
			</label>
			<?php esc_html_e( 'Please select this option to install light version of demo content. Recommended for slow severs and shared web hosts', 'cherry-data-importer' ); ?>
		</div>
		<div class="advanced-item__install">
			<button class="cdi-btn primary"><?php
				esc_html_e( 'Install Demo', 'cherry-data-importer' );
			?></button>
			<a href="<?php echo $demo_url; ?>" class="cdi-btn"><?php
				esc_html_e( 'View Demo', 'cherry-data-importer' );
			?></a>
		</div>
	</div>
</div>
