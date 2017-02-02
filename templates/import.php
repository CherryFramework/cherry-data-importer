<?php
/**
 * Main import template
 */
?>
<div class="cdi-content">
	<?php cdi_slider()->slider_assets(); ?>
	<?php cdi_interface()->remove_content_form(); ?>
	<?php
		if ( isset( $_GET['type'] ) && 'append' === $_GET['type'] ) {
			cdi_slider()->render();
		}
	?>
	<div id="cherry-import-progress" class="cdi-progress">
		<span class="cdi-progress__bar">
			<span class="cdi-progress__label"><span></span></span>
		</span>
		<span class="cdi-progress__sub-label"></span>
	</div>
	<table class="cdi-install-summary">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Import Summary', 'cherry-data-importer' ); ?></th>
				<th class="completed-cell"><?php esc_html_e( 'Completed', 'cherry-data-importer' ); ?></th>
				<th colspan="3"><?php esc_html_e( 'Progress', 'cherry-data-importer' ); ?></th>
			</tr>
		</theead>
		<tbody>
		<?php

			$summary = cdi_cache()->get( 'import_summary' );
			$labels  = array(
				'posts'    => esc_html__( 'Posts', 'cherry-data-importer' ),
				'authors'  => esc_html__( 'Authors', 'cherry-data-importer' ),
				'comments' => esc_html__( 'Comments', 'cherry-data-importer' ),
				'media'    => esc_html__( 'Media', 'cherry-data-importer' ),
				'terms'    => esc_html__( 'Terms', 'cherry-data-importer' ),
				'tables'   => esc_html__( 'Tables', 'cherry-data-importer' ),
			);

			foreach ( $summary as $type => $total ) {

				if ( 0 === $total ) {
					continue;
				}

				?>
				<tr data-item="<?php echo $type; ?>" data-total="<?php echo $total; ?>">
					<td><?php echo $labels[ $type ]; ?></td>
					<td class="completed-cell">
						<span class="cdi-install-summary__done">0</span>
						/
						<span class="cdi-install-summary__total"><?php echo $total; ?></span>
					</td>
					<td class="cdi-complete-val">
						<span class="cdi-install-summary__percent">0</span>%
					</td>
					<td class="cdi-complete-progress">
						<div class="cdi-progress progress-tiny"><span class="cdi-progress__bar"><span></span></span></div>
					</td>
					<td class="cdi-complete-status">
						<div class="cdi-progress-status"></div>
					</td>
				</tr>
				<?php

			}

		?>
		</tbody>
	</table>
</div>