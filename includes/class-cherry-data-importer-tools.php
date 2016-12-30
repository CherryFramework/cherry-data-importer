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
		 * Get page title
		 *
		 * @param  string $before HTML before title.
		 * @param  string $after  HTML after title.
		 * @param  bool   $echo   Echo or return.
		 * @return string|void
		 */
		public function get_page_title( $before = '', $after = '', $echo = false ) {

			if ( ! isset( cdi()->current_tab ) || empty( cdi()->current_tab ) ) {
				return;
			}

			$title = cdi()->current_tab['name'];

			if ( 'import' === cdi()->current_tab['id'] ) {

				$step = ! empty( $_GET['step'] ) ? intval( $_GET['step'] ) : 1;

				switch ( $step ) {
					case 2:
						$title = esc_html__( 'Importing sample data', 'cherry-data-importer' );
						break;
					case 3:
						$title = esc_html__( 'Regenerate thumbnails', 'cherry-data-importer' );
						break;
					case 4:
						$title = esc_html__( 'Import finished', 'cherry-data-importer' );
						break;
					default:
						$title = esc_html__( 'Select source to import', 'cherry-data-importer' );
						break;
				}
			}

			$title = $before . apply_filters( 'cherry_data_importer_tab_title', $title ) . $after;

			if ( $echo ) {
				echo $title;
			} else {
				return $title;
			}
		}

		/**
		 * Get current page URL
		 *
		 * @return string
		 */
		public function get_page_url() {
			return sprintf(
				'%1$s://%2$s%3$s',
				is_ssl() ? 'https' : 'http',
				$_SERVER['HTTP_HOST'],
				$_SERVER['REQUEST_URI']
			);
		}

		/**
		 * Get recommended server params
		 *
		 * @return array
		 */
		public function server_params() {

			return apply_filters(
				'cherry_data_importer_recommended_params',
				array(
					'memory_limit'        => array(
						'value' => 128,
						'units' => 'Mb',
					),
					'post_max_size'       => array(
						'value' => 8,
						'units' => 'Mb',
					),
					'upload_max_filesize' => array(
						'value' => 8,
						'units' => 'Mb',
					),
					'max_input_time'      => array(
						'value' => 45,
						'units' => 's',
					),
					'max_execution_time'  => array(
						'value' => 30,
						'units' => 's',
					),
				)
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
		 * Remove existing content from website
		 *
		 * @since  1.1.0
		 * @return null
		 */
		public function clear_content() {

			if ( ! current_user_can( 'delete_users' ) ) {
				return;
			}

			$attachments = get_posts( array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
			) );

			if ( ! empty( $attachments ) ) {
				foreach ( $attachments as $attachment ) {
					wp_delete_attachment( $attachment->ID, true );
				}
			}

			global $wpdb;

			$tables_to_clear = array(
				$wpdb->commentmeta,
				$wpdb->comments,
				$wpdb->links,
				$wpdb->postmeta,
				$wpdb->posts,
				$wpdb->termmeta,
				$wpdb->terms,
				$wpdb->term_relationships,
				$wpdb->term_taxonomy,
			);

			foreach ( $tables_to_clear as $table ) {
				$wpdb->query( "TRUNCATE {$table};" );
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
 * Returns instance of Cherry_Data_Importer_Tools
 *
 * @return object
 */
function cdi_tools() {
	return Cherry_Data_Importer_Tools::get_instance();
}
