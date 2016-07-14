<?php
/**
 * Tools class
 *
 * @package   Cherry_Data_Importer
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Cherry_Data_Importer_Tools' ) ) {

	/**
	 * Define Cherry_Data_Importer_Tools class
	 */
	class Cherry_Data_Importer_Tools {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var   object
		 */
		private static $instance = null;

		/**
		 * Holder for admin page title
		 *
		 * @var string
		 */
		private $page_title = null;

		/**
		 * Returns available widgets data.
		 *
		 * @return array
		 */
		public function available_widgets() {

			global $wp_registered_widget_controls;

			$widget_controls   = $wp_registered_widget_controls;
			$available_widgets = array();

			foreach ( $widget_controls as $widget ) {

				if ( ! empty( $widget['id_base'] ) && ! isset( $available_widgets[ $widget['id_base']] ) ) {
					$available_widgets[ $widget['id_base'] ]['id_base'] = $widget['id_base'];
					$available_widgets[ $widget['id_base'] ]['name'] = $widget['name'];
				}

			}

			return apply_filters( 'cherry_data_export_available_widgets', $available_widgets );

		}

		/**
		 * Set page title
		 *
		 * @param string $title Page title
		 */
		public function set_title( $title = null ) {
			$this->page_title = $title;
		}

		/**
		 * Get page title
		 *
		 * @return string
		 */
		public function get_page_title() {
			return $this->page_title;
		}

		/**
		 * Get current page URL
		 *
		 * @return string
		 */
		public function get_page_url() {
			return sprintf(
				'%1$s://%2$s%3$s',
				$_SERVER['REQUEST_SCHEME'],
				$_SERVER['HTTP_HOST'],
				$_SERVER['REQUEST_URI']
			);
		}

		/**
		 * Escape unsecure for public usage part of file path and return base64 encoded result.
		 *
		 * @param  string $file Full file path
		 * @return string
		 */
		public function secure_path( $file ) {
			return base64_encode( str_replace( ABSPATH, '', $file ) );
		}

		/**
		 * Gets base64 encoded part of path, decode it and adds server path
		 *
		 * @param  string $file Encoded part of path.
		 * @return string
		 */
		public function esc_path( $file ) {
			return ABSPATH . base64_decode( $file );
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
 * Returns instance of Cherry_Data_Importer_Tools
 *
 * @return object
 */
function cdi_tools() {
	return Cherry_Data_Importer_Tools::get_instance();
}
