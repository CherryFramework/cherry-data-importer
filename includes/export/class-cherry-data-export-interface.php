<?php
/**
 * Exporter interface
 *
 * @package   cherry_data_importer
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Cherry_Data_Export_Interface' ) ) {

	/**
	 * Define Cherry_Data_Export_Interface class
	 */
	class Cherry_Data_Export_Interface {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var   object
		 */
		private static $instance = null;

		/**
		 * Constructor for the class
		 */
		function __construct() {

			add_action( 'export_filters', array( $this, 'render_export_form' ) );
			add_action( 'wp_ajax_cherry-data-export', array( $this, 'run_export' ) );

		}

		/**
		 * Render export form HTML
		 *
		 * @return void
		 */
		public function render_export_form() {

			cdi()->get_template( 'export.php' );

		}

		/**
		 * Run export process
		 *
		 * @return void
		 */
		public function run_export() {

			if ( ! current_user_can( 'export' ) ) {
				wp_send_json_error( array( 'message' => 'You don\'t have permissions to do this' ) );
			}

			if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'cherry_ajax_nonce' ) ) {
				wp_send_json_error( array( 'message' => 'You don\'t have permissions to do this' ) );
			}

			require cdi()->path( 'includes/export/class-cherry-wxr-exporter.php' );

			$file = cdi_exporter()->do_export();

			if ( $file ) {
				wp_send_json_success( array( 'file' => $file ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Export failed', 'cherry-data-importer' ) ) );
			}

		}

		/**
		 * Returns the instance.
		 *
		 * @since  1.0.0
		 * @return object
		 */
		public static function get_instance() {

			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}
	}

}

/**
 * Returns instance of Cherry_Data_Export_Interface
 *
 * @return object
 */
function cdi_export_interface() {
	return Cherry_Data_Export_Interface::get_instance();
}

cdi_export_interface();
