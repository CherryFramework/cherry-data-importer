<?php
/*
Plugin Name: Cherry Data Importer
Plugin URI: http://www.templatemonster.com/wordpress-themes.php
Description: Import posts, pages, comments, custom fields, categories, tags and more from a WordPress export file.
Author: TemplateMonster
Author URI: http://www.templatemonster.com/wordpress-themes.php
Version: 1.0.0
Text Domain: cherry-data-importer
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/**
 * Main importer plugin class
 *
 * @package   Cherry_Data_Importer
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Cherry_Data_Importer' ) ) {

	/**
	 * Define Cherry_Data_Importer class
	 */
	class Cherry_Data_Importer {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var   object
		 */
		private static $instance = null;

		/**
		 * Holder for importer object instance.
		 *
		 * @var object
		 */
		private $importer = null;

		/**
		 * Holder for importer object instance.
		 *
		 * @var object
		 */
		private $exporter = null;

		/**
		 * Plugin base url
		 *
		 * @var string
		 */
		private $url = null;

		/**
		 * Plugin base path
		 *
		 * @var string
		 */
		private $path = null;

		/**
		 * Constructor for the class
		 */
		function __construct() {

			add_action( 'init', array( $this, 'start_session' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ) );

			$this->load();
			$this->load_import();

			define( 'CHERRY_DEBUG', true );

		}

		/**
		 * Run session
		 *
		 * @return void
		 */
		public function start_session() {

			if ( ! session_id() ) {
				session_start();
			}

		}

		/**
		 * Get plugin template
		 *
		 * @param  string $template Template name.
		 * @return void
		 */
		public function get_template( $template ) {

			$file = locate_template( 'cherry-data-importer/' . $template );

			if ( ! $file ) {
				$file = $this->path( 'templates/' . $template );
			}

			if ( file_exists( $file ) ) {
				include $file;
			}

		}

		/**
		 * Load globally required files
		 */
		public function load() {
			require $this->path( 'includes/class-cherry-data-importer-cache.php' );
			require $this->path( 'includes/class-cherry-data-importer-logger.php' );
			require $this->path( 'includes/class-cherry-data-importer-tools.php' );
		}

		/**
		 * Include import files
		 */
		public function load_import() {

			$this->load_wp_importer();
			require $this->path( 'includes/import/class-cherry-data-importer-interface.php' );

			cdi_interface();
		}

		/**
		 * Returns path to file or dir inside plugin folder
		 *
		 * @param  string $path Path inside plugin dir.
		 * @return string
		 */
		public function path( $path = null ) {

			if ( ! $this->path ) {
				$this->path = trailingslashit( plugin_dir_path( __FILE__ ) );
			}

			return $this->path . $path;

		}

		/**
		 * Returns url to file or dir inside plugin folder
		 *
		 * @param  string $path Path inside plugin dir.
		 * @return string
		 */
		public function url( $path = null ) {

			if ( ! $this->url ) {
				$this->url = trailingslashit( plugin_dir_url( __FILE__ ) );
			}

			return $this->url . $path;

		}

		/**
		 * Prepare assets URL depending from CHERRY_DEBUG value
		 *
		 * @param  string $path Base file path.
		 * @return string
		 */
		public function assets_url( $path ) {

			if ( defined( 'CHERRY_DEBUG' ) && true === CHERRY_DEBUG ) {
				$path = str_replace( array( '..', '//' ), array( '.', '/' ), sprintf( $path, null ) );
			} else {
				$path = sprintf( $path, 'min' );
			}

			return $this->url( 'assets/' . $path );

		}

		/**
		 * Register plugin script and styles
		 *
		 * @return void
		 */
		public function register_assets() {

			wp_register_script( 'cherry-data-import', $this->assets_url( 'js/%s/cherry-data-import.js' ) );

			wp_localize_script( 'cherry-data-import', 'CherryDataImport', array(
				'nonce' => wp_create_nonce( 'cherry-data-import' ),
			) );

		}

		/**
		 * Loads default WordPress importer
		 *
		 * @return void
		 */
		public function load_wp_importer() {

			if ( ! class_exists( 'WP_Importer' ) ) {
				require ABSPATH . '/wp-admin/includes/class-wp-importer.php';
			}

		}

		/**
		 * Return importer instance.
		 *
		 * @return object
		 */
		public function importer() {
			return $this->importer;
		}

		/**
		 * Return exporter instance
		 *
		 * @return object
		 */
		public function exporter() {
			return $this->importer;
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
 * Returns instance of Cherry_Data_Importer
 *
 * @return object
 */
function cdi() {
	return Cherry_Data_Importer::get_instance();
}

cdi();
