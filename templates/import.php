<?php
/**
 * Main import template
 */
?>
<div class="cdi-content">
	<?php cdi_interface()->remove_content_form(); ?>
	<div id="cherry-import-progress" class="cdi-progress">
		<span class="cdi-progress__bar"><span class="cdi-progress__label"><span></span></span></span>
	</div>
	<table class="cdi-install-summary">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Import Summary', 'cherry-data-importer' ); ?></th>
				<th class="completed-cell"><?php esc_html_e( 'Completed', 'cherry-data-importer' ); ?></th>
				<th colspan="2"><?php esc_html_e( 'Progress', 'cherry-data-importer' ); ?></th>
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
					<td>
						<span class="cdi-install-summary__percent">0</span>%
					</td>
					<td>
						<div class="cdi-progress progress-tiny"><span class="cdi-progress__bar"><span></span></span></div>
					</td>
				</tr>
				<?php

			}

		?>
		</tbody>
	</table>
</div>