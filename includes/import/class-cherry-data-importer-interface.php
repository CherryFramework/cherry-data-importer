<?php
/**
 * Class description
 *
 * @package   package_name
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Cherry_Data_Importer_Interface' ) ) {

	/**
	 * Define Cherry_Data_Importer_Interface class
	 */
	class Cherry_Data_Importer_Interface {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var   object
		 */
		private static $instance = null;

		/**
		 * Variable for settings array.
		 *
		 * @var array
		 */
		public $settings = array();

		/**
		 * Importer instance
		 *
		 * @var object
		 */
		private $importer = null;

		/**
		 * Items number in single chunk
		 *
		 * @var integer
		 */
		private $chunk_size = 20;

		/**
		 * Constructor for the class
		 */
		function __construct() {
			add_action( 'admin_init', array( $this, 'init' ) );
			add_action( 'wp_ajax_cherry-data-import-chunk', array( $this, 'import_chunk' ) );
		}

		/**
		 * Init importer
		 *
		 * @return void
		 */
		public function init() {

			register_importer(
				'cherry-import',
				__( 'TemplateMonster Demo Content Import', 'cherry-data-importer' ),
				__( 'Import demo content for TemplateMonster themes.', 'cherry-data-importer'),
				array( $this, 'dispatch' )
			);

			$this->set_default_settings();
			$this->set_theme_settings();
		}

		/**
		 * Set default importer settings
		 *
		 * @return void
		 */
		public function set_default_settings() {

			$this->settings = array(
				'xml' => array(
					'enabled'    => true,
					'use_upload' => true,
					'path'       => false,
				),
				'json' => array(
					'enabled'    => true,
					'use_upload' => true,
					'path'       => false,
				),
			);

		}

		/**
		 * Maybe rewrite settings from active theme
		 *
		 * @return void
		 */
		public function set_theme_settings() {

			$manifest = locate_template( 'cherry-import-manifest.php' );

			if ( ! $manifest ) {
				return;
			}

			include $manifest;

			if ( ! isset( $settings ) ) {
				return;
			}

			foreach ( array( 'xml', 'json' ) as $type ) {
				if ( ! empty( $settings[ $type ] ) ) {
					$this->settings[ $type ] = wp_parse_args( $settings[ $type ], $this->settings[ $type ] );
				}
			}

		}

		/**
		 * Get setting by name
		 *
		 * @param  array $keys Settings key to get.
		 * @return void
		 */
		public function get_setting( $keys = array() ) {

			if ( empty( $keys ) || ! is_array( $keys ) ) {
				return false;
			}

			$temp_result = $this->settings;

			foreach ( $keys as $key ) {

				if ( ! isset( $temp_result[ $key ] ) ) {
					continue;
				}

				$temp_result = $temp_result[ $key ];
			}

			return $temp_result;

		}

		/**
		 * Run Cherry importer
		 *
		 * @return void
		 */
		public function dispatch() {
			$this->import_step();
		}

		/**
		 * Show main content import step
		 *
		 * @return void
		 */
		private function import_step() {

			wp_enqueue_script( 'cherry-data-import' );

			cdi()->get_template( 'page-header.php' );
			$importer = $this->get_importer();
			$importer->prepare_import();

			$count = cdi_cache()->get( 'total_count' );
			$chunks_count = ceil( intval( $count ) / $this->chunk_size );

			cdi_cache()->update( 'chunks_count', $chunks_count );

			cdi()->get_template( 'import.php' );
			cdi()->get_template( 'page-footer.php' );


		}

		/**
		 * Process single chunk import
		 *
		 * @return void
		 */
		public function import_chunk() {

			if ( ! current_user_can( 'import' ) ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'You don\'t have permissions to do this', 'cherry-data-importer' ),
				) );
			}

			if ( empty( $_REQUEST['chunk'] ) ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'Chunk number is missing in request', 'cherry-data-importer' ),
				) );
			}

			$chunk  = intval( $_REQUEST['chunk'] );
			$offset = $this->chunk_size * ( $chunk - 1 );

			$importer = $this->get_importer();
			$importer->chunked_import( $this->chunk_size, $offset );

			$chunks = cdi_cache()->get( 'chunks_count' );

			if ( $chunks == $chunk ) {
				cdi_cache()->clear_cache();
				$data = array(
					'import_end' => true,
					'complete'   => 100,
				);
			} else {
				$data = array(
					'action'   => 'cherry-data-import-chunk',
					'chunk'    => $chunk + 1,
					'complete' => round( ( $chunk * 100 ) / $chunks ),
				);
			}

			wp_send_json_success( $data );
		}

		/**
		 * Return importer object
		 *
		 * @return object
		 */
		public function get_importer() {

			if ( null !== $this->importer ) {
				return $this->importer;
			}

			require_once cdi()->path( 'includes/import/class-cherry-wxr-importer.php' );

			$options = array();
			$file    = $this->get_setting( array( 'xml', 'path' ) );

			return $this->importer = new Cherry_WXR_Importer( $options, $file );
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
 * Returns instance of Cherry_Data_Importer_Interface
 *
 * @return object
 */
function cdi_interface() {
	return Cherry_Data_Importer_Interface::get_instance();
}
