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

			$step = ! empty( $_GET['step'] ) ? intval( $_GET['step'] ) : 1;

			switch ( $step ) {
				case 2:
					$this->import_step();
					break;

				case 3:
					$this->import_after();
					break;

				default:
					$this->import_before();
					break;
			}


		}

		/**
		 * First import step
		 *
		 * @return void
		 */
		private function import_before() {

			wp_enqueue_script( 'cherry-data-import' );

			cdi()->get_template( 'page-header.php' );
			cdi()->get_template( 'import-before.php' );
			cdi()->get_template( 'page-footer.php' );

		}

		/**
		 * Last import step
		 *
		 * @return void
		 */
		private function import_after() {

			cdi()->get_template( 'page-header.php' );
			cdi()->get_template( 'import-before.php' );
			cdi()->get_template( 'page-footer.php' );

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

			$count        = cdi_cache()->get( 'total_count' );
			$chunks_count = ceil( intval( $count ) / $this->chunk_size );

			// Adds final step with ID and URL remapping. Sometimes it's expensice step separate it
			$chunks_count++;

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

			if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'cherry-data-import' ) ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'You don\'t have permissions to do this', 'cherry-data-importer' ),
				) );
			}

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
			$chunks = cdi_cache()->get( 'chunks_count' );

			switch ( $chunk ) {

				case $chunks:

					// Process last step (remapping and finalizing)
					$this->remap_all();
					cdi_cache()->clear_cache();
					$data = array(
						'import_end' => true,
						'complete'   => 100,
					);

					break;

				default:

					// Process regular step
					$offset   = $this->chunk_size * ( $chunk - 1 );
					$importer = $this->get_importer();

					$importer->chunked_import( $this->chunk_size, $offset );

					$data = array(
						'action'   => 'cherry-data-import-chunk',
						'chunk'    => $chunk + 1,
						'complete' => round( ( $chunk * 100 ) / $chunks ),
					);

					break;
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
		 * Remap all required data after installation completed
		 *
		 * @return void
		 */
		public function remap_all() {

			require_once cdi()->path( 'includes/import/class-cherry-data-importer-remap-callbacks.php' );

			/**
			 * Attach all posts remapping related callbacks to this hook
			 *
			 * @param  array Posts remapping data. Format: old_id => new_id
			 */
			do_action( 'cherry_data_import_remap_posts', cdi_cache()->get( 'posts', 'mapping' ) );

			/**
			 * Attach all terms remapping related callbacks to this hook
			 *
			 * @param  array Terms remapping data. Format: old_id => new_id
			 */
			do_action( 'cherry_data_import_remap_terms', cdi_cache()->get( 'term_id', 'mapping' ) );

			/**
			 * Attach all comments remapping related callbacks to this hook
			 *
			 * @param  array COmments remapping data. Format: old_id => new_id
			 */
			do_action( 'cherry_data_import_remap_comments', cdi_cache()->get( 'comments', 'mapping' ) );

		}

		/**
		 * Get welcome message for importer starter page
		 *
		 * @return string
		 */
		public function get_welcome_message() {

			$path = $this->get_setting( array( 'xml', 'path' ) );

			if ( ! $path ) {
				return __( 'Upload XML file with demo content', 'cherry-data-importer' );
			}

			if ( $path && ! is_array( $path ) ) {
				return __( 'We found 1 XML file with demo content in your theme, install it?', 'cherry-data-importer' );
			}

			if ( is_array( $path ) ) {
				return sprintf(
					__( 'We found %s XML files in your theme. Please select one of them', 'cherry-data-importer' ),
					count( $path )
				);
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
 * Returns instance of Cherry_Data_Importer_Interface
 *
 * @return object
 */
function cdi_interface() {
	return Cherry_Data_Importer_Interface::get_instance();
}
