<?php
/**
 * Main exporter class
 *
 * @package   package_name
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Cherry_WXR_Exporter' ) ) {

	/**
	 * Define Cherry_WXR_Exporter class
	 */
	class Cherry_WXR_Exporter {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var   object
		 */
		private static $instance = null;

		/**
		 * Options array to export
		 *
		 * @var array
		 */
		public $export_options = null;

		/**
		 * Constructor for the class
		 */
		function __construct() {

			include_once( ABSPATH . '/wp-admin/includes/class-pclzip.php' );
			require_once( ABSPATH . '/wp-admin/includes/export.php' );

		}

		/**
		 * Get array of options to export with content
		 *
		 * @return void
		 */
		public function get_options_to_export() {

			if ( null === $this->export_options ) {

				$theme  = get_option( 'stylesheet' );

				$this->export_options = apply_filters( 'cherry_data_export_options', array(
					'blogname',
					'blogdescription',
					'users_can_register',
					'posts_per_page',
					'date_format',
					'time_format',
					'thumbnail_size_w',
					'thumbnail_size_h',
					'thumbnail_crop',
					'medium_size_w',
					'medium_size_h',
					'large_size_w',
					'large_size_h',
					'theme_mods_' . $theme,
					'show_on_front',
					'page_on_front',
					'page_for_posts',
				) );

			}

			return $this->export_options;

		}

		/**
		 * Process XML export
		 *
		 * @return string
		 */
		public function do_export( $into_file = true ) {

			ob_start();

			ini_set( 'max_execution_time', -1 );
			set_time_limit( 0 );

			$use_custom_export = apply_filters( 'cherry_data_use_custom_export', false );

			if ( $use_custom_export && function_exists( $use_custom_export ) ) {
				call_user_func( $use_custom_export );
			} else {
				export_wp();
			}

			$xml = ob_get_clean();

			$xml = $this->fix_blog_title( $xml );
			$xml = $this->add_extra_data( $xml );
			$xml = $this->prepare_data( $xml );

			if ( true === $into_file ) {

				$upload_dir      = wp_upload_dir();
				$upload_base_dir = $upload_dir['basedir'];
				$upload_base_url = $upload_dir['baseurl'];
				$filename        = $this->get_filename();
				$xml_dir         = $upload_base_dir . '/' . $filename;
				$xml_url         = $upload_base_url . '/' . $filename;

				file_put_contents( $xml_dir, $xml );

				return $xml_url;

			} else {
				return $xml;
			}

		}

		/**
		 * Returns filename for exported sample data
		 *
		 * @return void
		 */
		public function get_filename() {

			return apply_filters( 'cherry_data_export_filename', 'sample-data-' . date( 'm-d-Y' ) . '.xml' );

		}

		/**
		 * Rename blog title node in passed XML
		 *
		 * @param  string $xml Exported XML.
		 * @return string
		 */
		private function fix_blog_title( $xml ) {
			$xml = preg_replace( '/<title>(.*?)<\/title>/', '<blog_title>$1</blog_title>', $xml, 1 );
			return $xml;
		}

		/**
		 * Add options and widgets to XML
		 *
		 * @param  string $xml Exported XML.
		 * @return string
		 */
		private function add_extra_data( $xml ) {
			$xml = str_replace(
				"</wp:base_blog_url>", "</wp:base_blog_url>\r\n" . $this->get_options() . $this->get_widgets(), $xml
			);
			return $xml;
		}

		/**
		 * Prepare exported XML to sending
		 *
		 * @param  string $xml Exported XML
		 * @return string
		 */
		private function prepare_data( $xml ) {

			$xml = iconv( 'utf-8', 'utf-8//IGNORE', $xml );
			$xml = preg_replace( '/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $xml );
			return $xml;
		}

		/**
		 * Get options list in XML format.
		 *
		 * @return string
		 */
		public function get_options() {

			$options        = '';
			$format         = "\t\t<wp:%1$s>%2$s</wp:%1$s>\r\n";
			$export_options = $this->get_options_to_export();

			foreach ( $export_options as $option ) {

				$value = get_option( $option );

				if ( is_array( $value ) ) {
					$value = json_encode( $value );
				}

				if ( ! empty( $option ) ) {
					//$options .= sprintf( $format, $option, wxr_cdata( $value ) );
					$value   = wxr_cdata( $value );
					$options .= "\t\t<wp:{$option}>{$value}</wp:{$option}>\r\n";
				}

			}

			return "\t<wp:options>\r\n" . $options . "\t</wp:options>\r\n";

		}

		/**
		 * Get widgets data to export
		 *
		 * @return string
		 */
		private function get_widgets() {

			// Get all available widgets site supports
			$available_widgets = cdi_tools()->available_widgets();

			// Get all widget instances for each widget
			$widget_instances = array();
			foreach ( $available_widgets as $widget_data ) {

				// Get all instances for this ID base
				$instances = get_option( 'widget_' . $widget_data['id_base'] );

				// Have instances
				if ( ! empty( $instances ) ) {

					// Loop instances
					foreach ( $instances as $instance_id => $instance_data ) {

						// Key is ID (not _multiwidget)
						if ( is_numeric( $instance_id ) ) {
							$unique_instance_id = $widget_data['id_base'] . '-' . $instance_id;
							$widget_instances[ $unique_instance_id ] = $instance_data;
						}

					}

				}

			}

			// Gather sidebars with their widget instances
			$sidebars_widgets = get_option( 'sidebars_widgets' ); // get sidebars and their unique widgets IDs
			$sidebars_widget_instances = array();
			foreach ( $sidebars_widgets as $sidebar_id => $widget_ids ) {

				// Skip inactive widgets
				if ( 'wp_inactive_widgets' == $sidebar_id ) {
					continue;
				}

				// Skip if no data or not an array (array_version)
				if ( ! is_array( $widget_ids ) || empty( $widget_ids ) ) {
					continue;
				}

				// Loop widget IDs for this sidebar
				foreach ( $widget_ids as $widget_id ) {

					// Is there an instance for this widget ID?
					if ( isset( $widget_instances[ $widget_id ] ) ) {

						// Add to array
						$sidebars_widget_instances[ $sidebar_id ][ $widget_id ] = $widget_instances[ $widget_id ];

					}

				}

			}

			// Filter pre-encoded data
			$data = apply_filters( 'cherry_data_export_pre_get_widgets', $sidebars_widget_instances );

			// Encode the data for file contents
			$encoded_data = json_encode( $data );
			$encoded_data = apply_filters( 'cherry_data_export_get_widgets', $encoded_data );

			// Return contents
			return "\t<wp:widgets_data>" . wxr_cdata( $encoded_data ) . "</wp:widgets_data>\r\n";

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
 * Returns instance of Cherry_WXR_Exporter
 *
 * @return object
 */
function cdi_exporter() {
	return Cherry_WXR_Exporter::get_instance();
}
