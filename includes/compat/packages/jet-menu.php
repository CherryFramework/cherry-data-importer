<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Cherry_Data_Importer_Jet_Menu_Compat' ) ) {

	/**
	 * Define Cherry_Data_Importer_Jet_Menu_Compat class
	 */
	class Cherry_Data_Importer_Jet_Menu_Compat {

		/**
		 * Constructor for the class
		 */
		public function __construct() {
			add_action( 'cherry-data-importer/export/custom-data', array( $this, 'add_menu_meta_on_export' ) );
		}

		/**
		 * Add menu meta on export
		 */
		public function add_menu_meta_on_export() {

			$locations = get_nav_menu_locations();

			if ( empty( $locations ) ) {
				return;
			}

			$result = array();

			foreach ( $locations as $location => $menu_id ) {
				$settings = jet_menu_settings_nav()->get_settings( $menu_id );

				if ( ! empty( $settings ) ) {
					$result[ $menu_id ] = $settings;
				}

			}

			printf(
				"\t<wp:jet_menu_settings>%s</wp:jet_menu_settings>\r\n",
				wxr_cdata( json_encode( $result ) )
			);

		}

	}

}

new Cherry_Data_Importer_Jet_Menu_Compat();
