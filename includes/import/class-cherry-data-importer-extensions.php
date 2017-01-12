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

if ( ! class_exists( 'Cherry_Data_Importer_Extensions' ) ) {

	/**
	 * Define Cherry_Data_Importer_Extensions class
	 */
	class Cherry_Data_Importer_Extensions {

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
		public function __construct() {
			// Prevent from errors triggering while MotoPress Booking posts importing (loving it)
			add_filter( 'cherry_import_skip_post', array( $this, 'prevent_import_errors' ), 10, 2 );
		}

		/**
		 * Prevent PHP errors on import.
		 *
		 * @param  bool   $skip Default skip value.
		 * @param  array  $data Plugin data.
		 * @return bool
		 */
		public function prevent_import_errors( $skip, $data ) {

			if ( isset( $data['post_type'] ) && 'mphb_booking' === $data['post_type'] ) {
				return true;
			}

			return $skip;
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
 * Returns instance of Cherry_Data_Importer_Extensions
 *
 * @return object
 */
function cdi_extensions() {
	return Cherry_Data_Importer_Extensions::get_instance();
}

cdi_extensions();
