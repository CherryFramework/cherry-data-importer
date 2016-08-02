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

if ( ! class_exists( 'Cherry_Data_Importer_Callbacks' ) ) {

	/**
	 * Define Cherry_Data_Importer_Callbacks class
	 */
	class Cherry_Data_Importer_Callbacks {

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
			// Manipulations with posts remap array
			add_action( 'cherry_data_import_remap_posts', array( $this, 'process_options' ) );
			add_action( 'cherry_data_import_remap_posts', array( $this, 'postprocess_posts' ) );
			add_action( 'cherry_data_import_remap_posts', array( $this, 'process_term_thumb' ) );

			// Manipulations with terms remap array
			add_action( 'cherry_data_import_remap_terms', array( $this, 'process_term_parents' ) );
			add_action( 'cherry_data_import_remap_terms', array( $this, 'process_nav_menu' ) );
			add_action( 'cherry_data_import_remap_terms', array( $this, 'process_nav_menu_widgets' ) );
		}

		/**
		 * Set correctly term parents
		 *
		 * @param  array $data Mapped terms data.
		 * @return void|false
		 */
		public function process_term_parents( $data ) {

			$remap_terms         = cdi_cache()->get( 'terms', 'requires_remapping' );
			$processed_term_slug = cdi_cache()->get( 'term_slug', 'mapping' );

			if ( empty( $remap_terms ) ) {
				return false;
			}

			foreach ( $remap_terms as $term_id => $taxonomy ) {

				$parent_slug = get_term_meta( $term_id, '_wxr_import_parent', true );

				if ( ! $parent_slug ) {
					continue;
				}

				if ( empty( $processed_term_slug[ $parent_slug ] ) ) {
					continue;
				}

				wp_update_term( $term_id, $taxonomy, array(
					'parent' => (int) $processed_term_slug[ $parent_slug ],
				) );

			}

		}

		/**
		 * Replace term thumbnails IDs with new ones
		 *
		 * @param  array $data
		 * @return void
		 */
		public function process_term_thumb( $data ) {

			global $wpdb;

			$query = "
				SELECT term_id, meta_key, meta_value
				FROM $wpdb->termmeta
				WHERE meta_key LIKE '%_thumb'
			";

			$thumbnails = $wpdb->get_results( $query, ARRAY_A );

			if ( empty( $thumbnails ) ) {
				return;
			}

			foreach ( $thumbnails as $thumb_data ) {

				$term_id  = $thumb_data['term_id'];
				$meta_key = $thumb_data['meta_key'];
				$current  = $thumb_data['meta_value'];

				if ( ! empty( $data[ $current ] ) ) {
					update_term_meta( $term_id, $meta_key, $data[ $current ] );
				}

			}

		}

		/**
		 * Post-process posts.
		 *
		 * @param  array $todo Remap data.
		 * @return void
		 */
		public function postprocess_posts( $mapping ) {

			$todo      = cdi_cache()->get( 'posts', 'requires_remapping' );
			$user_slug = cdi_cache()->get( 'user_slug', 'mapping' );
			$url_remap = cdi_cache()->get_group( 'url_remap' );

			foreach ( $todo as $post_id => $_ ) {

				$data          = array();
				$updated_links = '';
				$old_links     = '';
				$post          = get_post( $post_id );

				$parent_id = get_post_meta( $post_id, '_wxr_import_parent', true );

				if ( ! empty( $parent_id ) && isset( $mapping['post'][ $parent_id ] ) ) {
					$data['post_parent'] = $mapping['post'][ $parent_id ];
				}

				$author_slug = get_post_meta( $post_id, '_wxr_import_user_slug', true );
				if ( ! empty( $author_slug ) && isset( $user_slug[ $author_slug ] ) ) {
					$data['post_author'] = $user_slug[ $author_slug ];
				}

				$has_attachments = get_post_meta( $post_id, '_wxr_import_has_attachment_refs', true );

				if ( ! empty( $has_attachments ) ) {

					$content = $post->post_content;

					// Replace all the URLs we've got
					$new_content = str_replace( array_keys( $url_remap ), $url_remap, $content );
					if ( $new_content !== $content ) {
						$data['post_content'] = $new_content;
					}
				}

				if ( in_array( get_post_type( $post_id ), array( 'page', 'post' ) ) ) {

					$old_links     = ! empty( $data['post_content'] ) ? $data['post_content'] : $post->post_content;
					$updated_links = str_replace( cdi_cache()->get( 'home' ), home_url(), $old_links );

					if ( $updated_links !== $old_links ) {
						$data['post_content'] = $updated_links;
					}

				}

				if ( get_post_type( $post_id ) === 'nav_menu_item' ) {
					$this->postprocess_menu_item( $post_id );
				}

				// Do we have updates to make?
				if ( empty( $data ) ) {
					continue;
				}

				// Run the update
				$data['ID'] = $post_id;
				$result     = wp_update_post( $data, true );

				if ( is_wp_error( $result ) ) {
					continue;
				}

				// Clear out our temporary meta keys
				delete_post_meta( $post_id, '_wxr_import_parent' );
				delete_post_meta( $post_id, '_wxr_import_user_slug' );
				delete_post_meta( $post_id, '_wxr_import_has_attachment_refs' );
			}

		}

		/**
		 * Post-process menu items.
		 *
		 * @param  int $post_id Processed post ID
		 * @return void
		 */
		public function postprocess_menu_item( $post_id ) {

			$menu_object_id = get_post_meta( $post_id, '_wxr_import_menu_item', true );

			if ( empty( $menu_object_id ) ) {
				// No processing needed!
				return;
			}

			$processed_term_id = cdi_cache()->get( 'term_id', 'mapping' );
			$processed_posts   = cdi_cache()->get( 'posts', 'mapping' );

			$menu_item_type = get_post_meta( $post_id, '_menu_item_type', true );

			switch ( $menu_item_type ) {
				case 'taxonomy':
					if ( isset( $processed_term_id[ $menu_object_id ] ) ) {
						$menu_object = $processed_term_id[ $menu_object_id ];
					}
					break;

				case 'post_type':
					if ( isset( $processed_posts[ $menu_object_id ] ) ) {
						$menu_object = $processed_posts[ $menu_object_id ];
					}
					break;

				default:
					// Cannot handle this.
					return;
			}

			if ( ! empty( $menu_object ) ) {
				update_post_meta( $post_id, '_menu_item_object_id', wp_slash( $menu_object ) );
			}

			delete_post_meta( $post_id, '_wxr_import_menu_item' );

		}

		/**
		 * Remap page ids in imported options
		 *
		 * @param  array $data Remap data.
		 * @return void
		 */
		public function process_options( $data ) {

			$options_to_process = array(
				'page_on_front',
				'page_for_posts',
			);

			foreach ( $options_to_process as $key ) {

				$current = get_option( $key );

				if ( ! $current || ! isset( $data[ $current ] ) ) {
					continue;
				}

				update_option( $key, $data[ $current ] );

			}

		}

		/**
		 * Remap nav menu ids
		 *
		 * @param  array $data Remap data.
		 * @return void
		 */
		public function process_nav_menu( $data ) {

			$locations = get_nav_menu_locations();

			if ( empty( $locations ) ) {
				return;
			}

			$new_locations = array();

			foreach ( $locations as $location => $id ) {

				if ( isset( $data[ $id ] ) ) {
					$new_locations[ $location ] = $data[ $id ];
				} else {
					$new_locations[ $location ] = $id;
				}

			}

			set_theme_mod( 'nav_menu_locations', $new_locations );

		}

		/**
		 * Remap menu IDs in widgets
		 *
		 * @param  array $data Remap data.
		 * @return void
		 */
		public function process_nav_menu_widgets( $data ) {

			$widget_menus = get_option( 'widget_nav_menu' );

			if ( empty( $widget_menus ) ) {
				return;
			}

			$new_widgets = array();

			foreach ( $widget_menus as $key => $widget ) {

				if ( '_multiwidget' === $key ) {
					$new_widgets['_multiwidget'] = $widget;
					continue;
				}

				if ( empty( $widget['nav_menu'] ) ) {
					$new_widgets[] = $widget;
					continue;
				}

				$id = $widget['nav_menu'];

				if ( isset( $data[ $id ] ) ) {
					$widget['nav_menu'] = $data[ $id ];
				}

				$new_widgets[ $key ] = $widget;

			}

			update_option( 'widget_nav_menu', $new_widgets );

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
 * Returns instance of Cherry_Data_Importer_Callbacks
 *
 * @return object
 */
function cdi_remap_callbacks() {
	return Cherry_Data_Importer_Callbacks::get_instance();
}

cdi_remap_callbacks();
