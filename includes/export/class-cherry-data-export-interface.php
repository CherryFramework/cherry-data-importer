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

			add_action( 'admin_menu', array( $this, 'menu_page' ) );
			add_action( 'export_filters', array( $this, 'render_export_form' ) );
			add_action( 'wp_ajax_cherry-data-export', array( $this, 'run_export' ) );

		}

		/**
		 * Init exporter page
		 *
		 * @return void
		 */
		public function menu_page() {

			cdi()->register_tab(
				array(
					'id'   => 'export',
					'name' => esc_html__( 'Export', 'cherry-data-importer' ),
					'cb'   => array( $this, 'render_export_form' ),
				)
			);

		}

		/**
		 * Render export form HTML
		 *
		 * @return void
		 */
		public function render_export_form() {

			ob_start();
			cdi()->get_template( 'export.php' );
			return ob_get_clean();

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

			if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'cherry-data-export' ) ) {
				wp_send_json_error( array( 'message' => 'You don\'t have permissions to do this' ) );
			}

			require cdi()->path( 'includes/export/class-cherry-wxr-exporter.php' );

			$xml = cdi_exporter()->do_export( false );

			$this->download_headers( cdi_exporter()->get_filename() );

			echo $xml;

			die();

		}

		/**
		 * Send download headers
		 *
		 * @return void
		 */
		public function download_headers( $file = 'sample-data.xml' ) {

			session_write_close();

			header( 'Pragma: public' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Cache-Control: public' );
			header( 'Content-Description: File Transfer' );
			header( 'Content-type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . $file . '"' );
			header( 'Content-Transfer-Encoding: binary' );

		}

		/**
		 * Returns URL to generate export file (nonce must be added via JS, otherwise will not be processed)
		 *
		 * @return string
		 */
		public function get_export_url() {
			return add_query_arg( array( 'action' => 'cherry-data-export' ), admin_url( 'admin-ajax.php' ) );
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
