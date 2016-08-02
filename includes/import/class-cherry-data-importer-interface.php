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
		 * Returns XML-files count
		 *
		 * @var int
		 */
		private $xml_count = null;

		/**
		 * Importer slug
		 *
		 * @var string
		 */
		public $slug = 'cherry-import';

		/**
		 * Constructor for the class
		 */
		function __construct() {
			add_action( 'admin_init', array( $this, 'init' ) );
			add_action( 'wp_ajax_cherry-data-import-chunk', array( $this, 'import_chunk' ) );
			add_action( 'wp_ajax_cherry-data-import-get-file-path', array( $this, 'get_file_path' ) );
		}

		/**
		 * Returns current chunk size
		 *
		 * @return void
		 */
		public function chunk_size() {

			$size = cdi()->get_setting( array( 'import', 'chunk_size' ) );
			$size = intval( $size );

			if ( ! $size ) {
				return cdi()->chunk_size;
			} else {
				return $size;
			}

		}

		/**
		 * Init importer
		 *
		 * @return void
		 */
		public function init() {

			register_importer(
				$this->slug,
				__( 'TemplateMonster Demo Content Import', 'cherry-data-importer' ),
				__( 'Import demo content for TemplateMonster themes.', 'cherry-data-importer'),
				array( $this, 'dispatch' )
			);

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
					cdi_tools()->set_title( esc_html__( 'Step 2: Importing sample data', 'cherry-data-importer' ) );
					$this->import_step();
					break;

				case 3:
					cdi_tools()->set_title( esc_html__( 'Import finished', 'cherry-data-importer' ) );
					$this->import_after();
					break;

				default:
					cdi_tools()->set_title( esc_html__( 'Step 1: Select source to import', 'cherry-data-importer' ) );
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
			cdi()->get_template( 'import-after.php' );
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
			$chunks_count = ceil( intval( $count ) / $this->chunk_size() );

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

			$chunk     = intval( $_REQUEST['chunk'] );
			$chunks    = cdi_cache()->get( 'chunks_count' );
			$processed = cdi_cache()->get( 'processed_summary' );

			switch ( $chunk ) {

				case $chunks:

					// Process last step (remapping and finalizing)
					$this->remap_all();
					cdi_cache()->clear_cache();
					$data = array(
						'import_end' => true,
						'complete'   => 100,
						'processed'  => $processed,
						'redirect'   => add_query_arg(
							array( 'import' => $this->slug, 'step' => 3 ),
							admin_url( 'admin.php' )
						),
					);

					break;

				default:

					// Process regular step
					$offset   = $this->chunk_size() * ( $chunk - 1 );
					$importer = $this->get_importer();

					$importer->chunked_import( $this->chunk_size(), $offset );

					$data = array(
						'action'    => 'cherry-data-import-chunk',
						'chunk'     => $chunk + 1,
						'complete'  => round( ( $chunk * 100 ) / $chunks ),
						'processed' => $processed,
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
			$file    = null;

			if ( isset( $_REQUEST['file'] ) ) {
				$file = cdi_tools()->esc_path( esc_attr( $_REQUEST['file'] ) );
			}

			if ( ! $file || ! file_exists( $file ) ) {
				$file = cdi()->get_setting( array( 'xml', 'path' ) );
			}

			if ( is_array( $file ) ) {
				$file = $file[0];
			}

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

			/**
			 * Attach all terms remapping related callbacks to this hook
			 *
			 * @param  array Terms remapping data. Format: old_id => new_id
			 */
			do_action( 'cherry_data_import_remap_terms', cdi_cache()->get( 'term_id', 'mapping' ) );

			/**
			 * Attach all posts_meta remapping related callbacks to this hook
			 *
			 * @param  array posts_meta data. Format: new_id => related keys array
			 */
			do_action( 'cherry_data_import_remap_posts_meta', cdi_cache()->get( 'posts_meta', 'requires_remapping' ) );

			/**
			 * Attach all terms meta remapping related callbacks to this hook
			 *
			 * @param  array terms meta data. Format: new_id => related keys array
			 */
			do_action( 'cherry_data_import_remap_terms_meta', cdi_cache()->get( 'terms_meta', 'requires_remapping' ) );

		}

		/**
		 * Get welcome message for importer starter page
		 *
		 * @return string
		 */
		public function get_welcome_message() {

			$files = $this->get_xml_count();

			if ( 0 === $files ) {
				$message = __( 'Upload XML file with demo content', 'cherry-data-importer' );
			}

			if ( 1 === $files ) {
				$message = __( 'We found 1 XML file with demo content in your theme, install it?', 'cherry-data-importer' );
			}

			if ( 1 < $files ) {
				$message = sprintf(
					__( 'We found %s XML files in your theme. Please select one of them to install', 'cherry-data-importer' ),
					$files
				);
			}

			return '<div class="cdi-message">' . $message . '</div>';

		}

		/**
		 * Get available XML count
		 *
		 * @return int
		 */
		public function get_xml_count() {

			if ( null !== $this->xml_count ) {
				return $this->xml_count;
			}

			$files = cdi()->get_setting( array( 'xml', 'path' ) );

			if ( ! $files ) {
				$this->xml_count = 0;
			} elseif ( ! is_array( $files ) ) {
				$this->xml_count = 1;
			} else {
				$this->xml_count = count( $files );
			}

			return $this->xml_count;
		}

		/**
		 * Returns HTML-markup of import files select
		 *
		 * @return string
		 */
		public function get_import_files_select( $before = '<div>', $after = '</div>' ) {

			$files = cdi()->get_setting( array( 'xml', 'path' ) );

			if ( ! $files && ! is_array( $files ) ) {
				return;
			}

			if ( 1 >= count( $files ) ) {
				return;
			}

			$wrap_format = '<select name="import_file">%1$s</select>';
			$item_format = '<option value="%1$s" %3$s>%2$s</option>';
			$selected    = 'selected="selected"';

			$result = '';

			foreach ( $files as $name => $file ) {
				$result .= sprintf( $item_format, cdi_tools()->secure_path( $file ), $name, $selected );
				$selected = '';
			}

			return $before . sprintf( $wrap_format, $result ) . $after;

		}

		/**
		 * Retuns HTML markup for import file uploader
		 *
		 * @param  string $before HTML markup before input.
		 * @param  string $after  HTML markup after input.
		 * @return string
		 */
		public function get_import_file_input( $before = '<div>', $after = '</div>' ) {

			if ( ! cdi()->get_setting( array( 'xml', 'use_upload' ) ) ) {
				return;
			}

			$result = '<div class="import-file">';
			$result .= '<input type="hidden" name="upload_file" class="import-file__input">';
			$result .= '<input type="text" name="upload_file_nicename" class="import-file__placeholder">';
			$result .= '<button class="cdi-btn primary" id="cherry-file-upload">';
			$result .= esc_html__( 'Upload File', 'cherry-data-importer' );
			$result .= '</button>';

			$result .= '</div>';

			return $before . $result . $after;

		}

		/**
		 * Retrieve XML file path by URL
		 *
		 * @return string
		 */
		public function get_file_path() {

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

			if ( ! isset( $_REQUEST['file'] ) ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'XML file not passed', 'cherry-data-importer' ),
				) );
			}

			$path = str_replace( home_url( '/' ), ABSPATH, esc_url( $_REQUEST['file'] ) );

			wp_send_json_success( array(
				'path' => cdi_tools()->secure_path( $path ),
			) );

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
