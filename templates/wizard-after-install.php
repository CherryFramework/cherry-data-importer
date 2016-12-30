<?php
/**
 * Template part for displaying advanced popup
 */

$skin = tm_wizard_interface()->get_skin_data( 'slug' );
$type = ! empty( $_GET['type'] ) ? esc_attr( $_GET['type'] ) : 'lite';
$file = cdi()->get_setting( array( 'advanced_import', $skin, $type ) );
$file = cdi_tools()->secure_path( $file );
?>
<h2><?php esc_html_e( 'Attention!', 'cherry-data-importer' ); ?></h2>

<?php esc_html_e( 'We are ready to install demo data. Do you want to append demo content to your existing content or completely rewrite it?.', 'cherry-data-importer' ); ?>
<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
	<div class="tm-wizard-type__select">
		<label class="tm-wizard-type__item">
			<input type="radio" name="type" value="append" checked>
			<span class="tm-wizard-type__item-mask"></span>
			<span class="tm-wizard-type__item-label"><?php
				esc_html_e( 'Append demo content to my existing content', 'tm-wizard' );
			?></span>
		</label>
		<label class="tm-wizard-type__item">
			<input type="radio" name="type" value="replace">
			<span class="tm-wizard-type__item-mask"></span>
			<span class="tm-wizard-type__item-label"><?php
				esc_html_e( 'Replace my existing content with demo content', 'tm-wizard' );
			?></span>

		</label>
	</div>
	<input type="hidden" name="tab" value="import">
	<input type="hidden" name="step" value="2">
	<input type="hidden" name="file" value="<?php echo $file; ?>">
	<input type="hidden" name="page" value="<?php echo cdi()->slug; ?>">
	<button class="btn btn-primary" data-wizard="confirm-install" data-loader="true" data-href=""><span class="text"><?php
		esc_html_e( 'Start', 'tm-wizard' );
	?></span><span class="tm-wizard-loader"><span class="tm-wizard-loader__spinner"></span></span></button>
</form>