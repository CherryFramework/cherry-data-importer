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
