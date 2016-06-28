<?php
/**
 * Main importer class
 */
class Cherry_WXR_Importer extends WP_Importer {
	/**
	 * Maximum supported WXR version
	 */
	const MAX_WXR_VERSION = 1.2;

	/**
	 * Regular expression for checking if a post references an attachment
	 *
	 * Note: This is a quick, weak check just to exclude text-only posts. More
	 * vigorous checking is done later to verify.
	 */
	const REGEX_HAS_ATTACHMENT_REFS = '!
		(
			# Match anything with an image or attachment class
			class=[\'"].*?\b(wp-image-\d+|attachment-[\w\-]+)\b
		|
			# Match anything that looks like an upload URL
			src=[\'"][^\'"]*(
				[0-9]{4}/[0-9]{2}/[^\'"]+\.(jpg|jpeg|png|gif)
			|
				content/uploads[^\'"]+
			)[\'"]
		)!ix';

	/**
	 * Version of WXR we're importing.
	 *
	 * Defaults to 1.0 for compatibility. Typically overridden by a
	 * `<wp:wxr_version>` tag at the start of the file.
	 *
	 * @var string
	 */
	protected $version = '1.0';

	/**
	 * Importer options array
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Import file
	 *
	 * @var string
	 */
	private $file = null;

	/**
	 * Reader instance
	 *
	 * @var object
	 */
	public $reader = null;

	/**
	 * Logger object
	 *
	 * @var object
	 */
	public $logger = null;

	/**
	 * Processed posts count
	 *
	 * @var integer
	 */
	private $processed = 0;

	/**
	 * Constructor
	 *
	 * @param array $options {
	 *     @var bool $prefill_existing_posts Should we prefill `post_exists` calls? (True prefills and uses more memory, false checks once per imported post and takes longer. Default is true.)
	 *     @var bool $prefill_existing_comments Should we prefill `comment_exists` calls? (True prefills and uses more memory, false checks once per imported comment and takes longer. Default is true.)
	 *     @var bool $prefill_existing_terms Should we prefill `term_exists` calls? (True prefills and uses more memory, false checks once per imported term and takes longer. Default is true.)
	 *     @var bool $update_attachment_guids Should attachment GUIDs be updated to the new URL? (True updates the GUID, which keeps compatibility with v1, false doesn't update, and allows deduplication and reimporting. Default is false.)
	 *     @var bool $fetch_attachments Fetch attachments from the remote server. (True fetches and creates attachment posts, false skips attachments. Default is false.)
	 *     @var bool $aggressive_url_search Should we search/replace for URLs aggressively? (True searches all posts' content for old URLs and replaces, false checks for `<img class="wp-image-*">` only. Default is false.)
	 *     @var int $default_author User ID to use if author is missing or invalid. (Default is null, which leaves posts unassigned.)
	 * }
	 */
	public function __construct( $options = array(), $file = null ) {

		$this->options = wp_parse_args( $options, array(
			'prefill_existing_posts'    => true,
			'prefill_existing_comments' => true,
			'prefill_existing_terms'    => true,
			'update_attachment_guids'   => true,
			'fetch_attachments'         => true,
			'aggressive_url_search'     => false,
			'default_author'            => null,
		) );

		$this->file   = $file;
		$this->reader = $this->get_reader( $this->file );
		$this->logger = new Cherry_Data_Importer_Logger();

	}

	/**
	 * Prepare import process
	 *
	 * @return void
	 */
	public function prepare_import() {

		timer_start();
		$count = 0;
		while ( $this->reader->read() ) {

			if ( $this->reader->nodeType !== XMLReader::ELEMENT ) {
				continue;
			}

			$count = $this->increment( $count );

		}

		cdi_cache()->update( 'total_count', $count );

		timer_stop( true, 4 );

	}

	/**
	 * Increment passed value depending from processed item
	 *
	 * @param  integer $current Value to increment.
	 * @return integer
	 */
	public function increment( $current = 1 ) {

		switch ( $this->reader->name ) {
			case 'wp:wxr_version':
			case 'generator':
			case 'blog_title':
			case 'wp:base_site_url':
			case 'wp:base_blog_url':
			case 'item':
			case 'wp:wp_author':
			case 'wp:category':
			case 'wp:tag':
			case 'wp:term':
			case 'wp:options':
				$current++;
				break;

			default:
				// Skip this node, probably handled by something already
				break;
		}

		return $current;

	}

	/**
	 * Chunked import handler
	 *
	 * @param  integer $num    Number items to process (chunk size).
	 * @param  integer $offset Items offset (chunk number)
	 * @return void
	 */
	public function chunked_import( $num = 30, $offset = 0 ) {

		$skiped    = 0;
		$processed = 0;

		while ( $this->reader->read() ) {

			if ( $this->reader->nodeType !== XMLReader::ELEMENT ) {
				continue;
			}

			if ( $skiped <= $offset ) {
				$skiped = $this->increment( $skiped );
				continue;
			}

			switch ( $this->reader->name ) {
				case 'wp:wxr_version':

					// Upgrade to the correct version
					$version = $this->reader->readString();

					if ( version_compare( $version, self::MAX_WXR_VERSION, '>' ) ) {}

					cdi_cache()->update( 'version', $version );

					// Handled everything in this node, move on to the next
					$this->next();
					break;

				case 'generator':
					cdi_cache()->update( 'generator', $this->reader->readString() );
					$this->next();
					break;

				case 'blog_title':
					$title = $this->reader->readString();
					cdi_cache()->update( 'title', $title );
					update_option( 'blogname', $title );
					$this->next();
					break;

				case 'wp:base_site_url':
					cdi_cache()->update( 'siteurl', $this->reader->readString() );
					$this->next();
					break;

				case 'wp:base_blog_url':
					cdi_cache()->update( 'home', $this->reader->readString() );
					$this->next();
					break;

				case 'wp:options':

					$node = $this->reader->expand();

					$parsed = $this->parse_options_node( $node );

					if ( is_wp_error( $parsed ) ) {
						// Skip the rest of this post
						$this->next();
						break;
					}

					$status = $this->process_options( $parsed );

					// Handled everything in this node, move on to the next
					$this->next();
					break;

				case 'wp:wp_author':

					$node = $this->reader->expand();

					$parsed = $this->parse_author_node( $node );

					if ( is_wp_error( $parsed ) ) {
						// Skip the rest of this post
						$this->next();
						break;
					}

					$status = $this->process_author( $parsed['data'], $parsed['meta'] );

					// Handled everything in this node, move on to the next
					$this->next();
					break;

				case 'item':

					$node   = $this->reader->expand();
					$parsed = $this->parse_post_node( $node );

					if ( is_wp_error( $parsed ) ) {
						// Skip the rest of this post
						$this->next();
						break;
					}

					$this->process_post( $parsed['data'], $parsed['meta'], $parsed['comments'], $parsed['terms'] );

					// Handled everything in this node, move on to the next
					$this->next();

					break;

				case 'wp:category':

					$node   = $this->reader->expand();
					$parsed = $this->parse_term_node( $node, 'category' );

					if ( is_wp_error( $parsed ) ) {
						// Skip the rest of this post
						$this->next();
						break;
					}

					$status = $this->process_term( $parsed['data'], $parsed['meta'] );

					// Handled everything in this node, move on to the next
					$this->next();
					break;

				case 'wp:tag':
					$node = $this->reader->expand();

					$parsed = $this->parse_term_node( $node, 'tag' );
					if ( is_wp_error( $parsed ) ) {
						// Skip the rest of this post
						$this->reader->next();
						break;
					}

					$status = $this->process_term( $parsed['data'], $parsed['meta'] );

					// Handled everything in this node, move on to the next
					$this->reader->next();
					break;

				case 'wp:term':
					$node = $this->reader->expand();

					$parsed = $this->parse_term_node( $node );
					if ( is_wp_error( $parsed ) ) {
						// Skip the rest of this post
						$this->reader->next();
						break;
					}

					$status = $this->process_term( $parsed['data'], $parsed['meta'] );

					// Handled everything in this node, move on to the next
					$this->reader->next();
					break;

				default:
					// Skip this node, probably handled by something already
					break;
			}

			if ( $this->processed === $num ) {
				break;
			}
		}

	}

	/**
	 * Increase processed posts counter and move to next item.
	 *
	 * @return function [description]
	 */
	private function next() {
		$this->processed = $this->increment( $this->processed );
		$this->reader->next();
	}

	/**
	 * Prepare options data
	 *
	 * @param  object $node Parsed XML options object.
	 * @return array
	 */
	protected function parse_options_node( $node ) {

		$data = array();

		foreach ( $node->childNodes as $child ) {

			// We only care about child elements
			if ( $child->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}

			$option_key = str_replace( 'wp:', '', $child->tagName );
			$data[ $option_key ] = $child->textContent;

		}

		return $data;

	}

	/**
	 * Prepare author data
	 *
	 * @param  object $node Parsed XML author object.
	 * @return array
	 */
	protected function parse_author_node( $node ) {

		$data = array();
		$meta = array();

		foreach ( $node->childNodes as $child ) {

			// We only care about child elements
			if ( $child->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}

			switch ( $child->tagName ) {
				case 'wp:author_login':
					$data['user_login'] = $child->textContent;
					break;

				case 'wp:author_id':
					$data['ID'] = $child->textContent;
					break;

				case 'wp:author_email':
					$data['user_email'] = $child->textContent;
					break;

				case 'wp:author_display_name':
					$data['display_name'] = $child->textContent;
					break;

				case 'wp:author_first_name':
					$data['first_name'] = $child->textContent;
					break;

				case 'wp:author_last_name':
					$data['last_name'] = $child->textContent;
					break;
			}
		}

		$result = compact( 'data', 'meta' );

		$saved = cdi_cache()->update( $data['user_login'], $result, 'users' );

		return $result;
	}

	/**
	 * Parse a post node into post data.
	 *
	 * @param  DOMElement $node Parent node of post data (typically `item`).
	 * @return array|WP_Error Post data array on success, error otherwise.
	 */
	protected function parse_post_node( $node ) {

		$data     = array();
		$meta     = array();
		$comments = array();
		$terms    = array();

		foreach ( $node->childNodes as $child ) {

			// We only care about child elements
			if ( $child->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}

			switch ( $child->tagName ) {
				case 'wp:post_type':
					$data['post_type'] = $child->textContent;
					break;

				case 'title':
					$data['post_title'] = $child->textContent;
					break;

				case 'guid':
					$data['guid'] = $child->textContent;
					break;

				case 'dc:creator':
					$data['post_author'] = $child->textContent;
					break;

				case 'content:encoded':
					$data['post_content'] = $child->textContent;
					break;

				case 'excerpt:encoded':
					$data['post_excerpt'] = $child->textContent;
					break;

				case 'wp:post_id':
					$data['post_id'] = $child->textContent;
					break;

				case 'wp:post_date':
					$data['post_date'] = $child->textContent;
					break;

				case 'wp:post_date_gmt':
					$data['post_date_gmt'] = $child->textContent;
					break;

				case 'wp:comment_status':
					$data['comment_status'] = $child->textContent;
					break;

				case 'wp:ping_status':
					$data['ping_status'] = $child->textContent;
					break;

				case 'wp:post_name':
					$data['post_name'] = $child->textContent;
					break;

				case 'wp:status':
					$data['post_status'] = $child->textContent;

					if ( $data['post_status'] === 'auto-draft' ) {
						// Bail now
						return new WP_Error(
							'wxr_importer.post.cannot_import_draft',
							__( 'Cannot import auto-draft posts' ),
							$data
						);
					}
					break;

				case 'wp:post_parent':
					$data['post_parent'] = $child->textContent;
					break;

				case 'wp:menu_order':
					$data['menu_order'] = $child->textContent;
					break;

				case 'wp:post_password':
					$data['post_password'] = $child->textContent;
					break;

				case 'wp:is_sticky':
					$data['is_sticky'] = $child->textContent;
					break;

				case 'wp:attachment_url':
					$data['attachment_url'] = $child->textContent;
					break;

				case 'wp:postmeta':
					$meta_item = $this->parse_meta_node( $child );
					if ( ! empty( $meta_item ) ) {
						$meta[] = $meta_item;
					}
					break;

				case 'wp:comment':
					$comment_item = $this->parse_comment_node( $child );
					if ( ! empty( $comment_item ) ) {
						$comments[] = $comment_item;
					}
					break;

				case 'category':
					$term_item = $this->parse_category_node( $child );
					if ( ! empty( $term_item ) ) {
						$terms[] = $term_item;
					}
					break;
			}
		}

		return compact( 'data', 'meta', 'comments', 'terms' );
	}

	/**
	 * Parse a meta node into meta data.
	 *
	 * @param DOMElement $node Parent node of meta data (typically `wp:postmeta` or `wp:commentmeta`).
	 * @return array|null Meta data array on success, or null on error.
	 */
	protected function parse_meta_node( $node ) {
		foreach ( $node->childNodes as $child ) {
			// We only care about child elements
			if ( $child->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}

			switch ( $child->tagName ) {
				case 'wp:meta_key':
					$key = $child->textContent;
					break;

				case 'wp:meta_value':
					$value = $child->textContent;
					break;
			}
		}

		if ( empty( $key ) || empty( $value ) ) {
			return null;
		}

		return compact( 'key', 'value' );
	}

	/**
	 * Parse a comment node into comment data.
	 *
	 * @param DOMElement $node Parent node of comment data (typically `wp:comment`).
	 * @return array Comment data array.
	 */
	protected function parse_comment_node( $node ) {
		$data = array(
			'commentmeta' => array(),
		);

		foreach ( $node->childNodes as $child ) {
			// We only care about child elements
			if ( $child->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}

			switch ( $child->tagName ) {
				case 'wp:comment_id':
					$data['comment_id'] = $child->textContent;
					break;
				case 'wp:comment_author':
					$data['comment_author'] = $child->textContent;
					break;

				case 'wp:comment_author_email':
					$data['comment_author_email'] = $child->textContent;
					break;

				case 'wp:comment_author_IP':
					$data['comment_author_IP'] = $child->textContent;
					break;

				case 'wp:comment_author_url':
					$data['comment_author_url'] = $child->textContent;
					break;

				case 'wp:comment_user_id':
					$data['comment_user_id'] = $child->textContent;
					break;

				case 'wp:comment_date':
					$data['comment_date'] = $child->textContent;
					break;

				case 'wp:comment_date_gmt':
					$data['comment_date_gmt'] = $child->textContent;
					break;

				case 'wp:comment_content':
					$data['comment_content'] = $child->textContent;
					break;

				case 'wp:comment_approved':
					$data['comment_approved'] = $child->textContent;
					break;

				case 'wp:comment_type':
					$data['comment_type'] = $child->textContent;
					break;

				case 'wp:comment_parent':
					$data['comment_parent'] = $child->textContent;
					break;

				case 'wp:commentmeta':
					$meta_item = $this->parse_meta_node( $child );
					if ( ! empty( $meta_item ) ) {
						$data['commentmeta'][] = $meta_item;
					}
					break;
			}
		}

		return $data;
	}

	/**
	 * Parse a comment node into comment data.
	 *
	 * @param  DOMElement $node Parent node of comment data (typically `wp:comment`).
	 * @return array Comment data array.
	 */
	protected function parse_category_node( $node ) {
		$data = array(
			// Default taxonomy to "category", since this is a `<category>` tag
			'taxonomy' => 'category',
		);
		$meta = array();

		if ( $node->hasAttribute( 'domain' ) ) {
			$data['taxonomy'] = $node->getAttribute( 'domain' );
		}
		if ( $node->hasAttribute( 'nicename' ) ) {
			$data['slug'] = $node->getAttribute( 'nicename' );
		}

		$data['name'] = $node->textContent;

		if ( empty( $data['slug'] ) ) {
			return null;
		}

		// Just for extra compatibility
		if ( $data['taxonomy'] === 'tag' ) {
			$data['taxonomy'] = 'post_tag';
		}

		return $data;
	}

	/**
	 * Parse a term node into term data.
	 *
	 * @param  DOMElement $node Parent node of term data.
	 * @param  string     $type Term type.
	 * @return array Comment data array.
	 */
	protected function parse_term_node( $node, $type = 'term' ) {
		$data = array();
		$meta = array();

		$tag_name = array(
			'id'          => 'wp:term_id',
			'taxonomy'    => 'wp:term_taxonomy',
			'slug'        => 'wp:term_slug',
			'parent'      => 'wp:term_parent',
			'name'        => 'wp:term_name',
			'description' => 'wp:term_description',
		);
		$taxonomy = null;

		// Special casing!
		switch ( $type ) {
			case 'category':
				$tag_name['slug']        = 'wp:category_nicename';
				$tag_name['parent']      = 'wp:category_parent';
				$tag_name['name']        = 'wp:cat_name';
				$tag_name['description'] = 'wp:category_description';
				$tag_name['taxonomy']    = null;

				$data['taxonomy'] = 'category';
				break;

			case 'tag':
				$tag_name['slug']        = 'wp:tag_slug';
				$tag_name['parent']      = null;
				$tag_name['name']        = 'wp:tag_name';
				$tag_name['description'] = 'wp:tag_description';
				$tag_name['taxonomy']    = null;

				$data['taxonomy'] = 'post_tag';
				break;
		}

		foreach ( $node->childNodes as $child ) {
			// We only care about child elements
			if ( $child->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}

			$key = array_search( $child->tagName, $tag_name );
			if ( $key ) {
				$data[ $key ] = $child->textContent;
			}
		}

		if ( empty( $data['taxonomy'] ) ) {
			return null;
		}

		// Compatibility with WXR 1.0
		if ( $data['taxonomy'] === 'tag' ) {
			$data['taxonomy'] = 'post_tag';
		}

		return compact( 'data', 'meta' );
	}

	/**
	 * Process import options
	 *
	 * @param  array $data Parsed options to process
	 */
	protected function process_options( $data ) {

		if ( empty( $data ) ) {
			return;
		}

		foreach ( $data as $key => $value ) {
			update_option( $key, $this->maybe_decode( $value ) );
		}

	}

	/**
	 * If passed JSON-encoded array - decode it and return, otherwise - return passed value.
	 *
	 * @param  string $value String to decode.
	 * @return mixed
	 */
	protected function maybe_decode( $value ) {

		$maybe_array = json_decode( $value, true );

		if ( ! is_array( $maybe_array ) ) {
			return $value;
		}

		return $maybe_array;

	}

	/**
	 * Create new posts based on import information
	 *
	 * Posts marked as having a parent which doesn't exist will become top level items.
	 * Doesn't create a new post if: the post type doesn't exist, the given post ID
	 * is already noted as imported or a post with the same title and date already exists.
	 * Note that new/updated terms, comments and meta are imported for the last of the above.
	 */
	protected function process_post( $data, $meta, $comments, $terms ) {
		/**
		 * Pre-process post data.
		 *
		 * @param array $data Post data. (Return empty to skip.)
		 * @param array $meta Meta data.
		 * @param array $comments Comments on the post.
		 * @param array $terms Terms on the post.
		 */
		$data = apply_filters( 'wxr_importer.pre_process.post', $data, $meta, $comments, $terms );
		if ( empty( $data ) ) {
			return false;
		}

		$original_id = isset( $data['post_id'] )     ? (int) $data['post_id']     : 0;
		$parent_id   = isset( $data['post_parent'] ) ? (int) $data['post_parent'] : 0;
		$author_id   = isset( $data['post_author'] ) ? (int) $data['post_author'] : 0;

		$processed_posts     = cdi_cache()->get( 'posts', 'mapping' );
		$processed_user_slug = cdi_cache()->get( 'user_slug', 'mapping' );
		$processed_terms     = cdi_cache()->get( 'terms', 'mapping' );
		$remap_posts         = cdi_cache()->get( 'posts', 'requires_remapping' );

		// Have we already processed this?
		if ( isset( $processed_posts[ $original_id ] ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $data['post_type'] );

		// Is this type even valid?
		if ( ! $post_type_object ) {
			$this->logger->warning( sprintf(
				__( 'Failed to import "%s": Invalid post type %s', 'cherry-data-importer' ),
				$data['post_title'],
				$data['post_type']
			) );
			return false;
		}

		$post_exists = $this->post_exists( $data );
		if ( $post_exists ) {
			$this->logger->info( sprintf(
				__('%s "%s" already exists.', 'cherry-data-importer'),
				$post_type_object->labels->singular_name,
				$data['post_title']
			) );

			// Even though this post already exists, new comments might need importing
			$this->process_comments( $comments, $original_id, $data, $post_exists );

			return false;
		}

		// Map the parent post, or mark it as one we need to fix
		$requires_remapping = false;
		if ( $parent_id ) {
			if ( isset( $processed_posts[ $parent_id ] ) ) {
				$data['post_parent'] = $processed_posts[ $parent_id ];
			} else {
				$meta[] = array( 'key' => '_wxr_import_parent', 'value' => $parent_id );
				$requires_remapping = true;

				$data['post_parent'] = 0;
			}
		}

		// Map the author, or mark it as one we need to fix
		$author = sanitize_user( $data['post_author'], true );
		if ( empty( $author ) ) {
			// Missing or invalid author, use default if available.
			$data['post_author'] = $this->options['default_author'];
		} elseif ( isset( $processed_user_slug[ $author ] ) ) {
			$data['post_author'] = $processed_user_slug[ $author ];
		} else {
			$meta[] = array( 'key' => '_wxr_import_user_slug', 'value' => $author );
			$requires_remapping = true;

			$data['post_author'] = (int) get_current_user_id();
		}

		// Does the post look like it contains attachment images?
		if ( preg_match( self::REGEX_HAS_ATTACHMENT_REFS, $data['post_content'] ) ) {
			$meta[] = array( 'key' => '_wxr_import_has_attachment_refs', 'value' => true );
			$requires_remapping = true;
		}

		// Whitelist to just the keys we allow
		$postdata = array(
			'import_id' => $data['post_id'],
		);
		$allowed = array(
			'post_author'    => true,
			'post_date'      => true,
			'post_date_gmt'  => true,
			'post_content'   => true,
			'post_excerpt'   => true,
			'post_title'     => true,
			'post_status'    => true,
			'post_name'      => true,
			'comment_status' => true,
			'ping_status'    => true,
			'guid'           => true,
			'post_parent'    => true,
			'menu_order'     => true,
			'post_type'      => true,
			'post_password'  => true,
		);
		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$postdata[ $key ] = $data[ $key ];
		}

		$postdata = apply_filters( 'wp_import_post_data_processed', $postdata, $data );

		if ( 'attachment' === $postdata['post_type'] ) {
			if ( ! $this->options['fetch_attachments'] ) {
				$this->logger->notice( sprintf(
					__( 'Skipping attachment "%s", fetching attachments disabled' ),
					$data['post_title']
				) );
				return false;
			}
			$remote_url = ! empty( $data['attachment_url'] ) ? $data['attachment_url'] : $data['guid'];
			$post_id    = $this->process_attachment( $postdata, $meta, $remote_url );
		} else {
			$post_id = wp_insert_post( $postdata, true );
			do_action( 'wp_import_insert_post', $post_id, $original_id, $postdata, $data );
		}

		if ( is_wp_error( $post_id ) ) {
			$this->logger->error( sprintf(
				__( 'Failed to import "%s" (%s)', 'cherry-data-importer' ),
				$data['post_title'],
				$post_type_object->labels->singular_name
			) );
			$this->logger->debug( $post_id->get_error_message() );

			/**
			 * Post processing failed.
			 *
			 * @param WP_Error $post_id Error object.
			 * @param array $data Raw data imported for the post.
			 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
			 * @param array $comments Raw comment data, already processed by {@see process_comments}.
			 * @param array $terms Raw term data, already processed.
			 */
			do_action( 'wxr_importer.process_failed.post', $post_id, $data, $meta, $comments, $terms );
			return false;
		}

		// Ensure stickiness is handled correctly too
		if ( $data['is_sticky'] == 1 ) {
			stick_post( $post_id );
		}

		// map pre-import ID to local ID
		$processed_posts[ $original_id ] = (int) $post_id;
		if ( $requires_remapping ) {
			$remap_posts[ $post_id ] = true;
			cdi_cache()->update( 'posts', $remap_posts, 'requires_remapping' );
		}
		$this->mark_post_exists( $data, $post_id );
		cdi_cache()->update( 'posts', $processed_posts, 'mapping' );

		$this->logger->info( sprintf(
			__( 'Imported "%s" (%s)', 'cherry-data-importer' ),
			$data['post_title'],
			$post_type_object->labels->singular_name
		) );
		$this->logger->debug( sprintf(
			__( 'Post %d remapped to %d', 'cherry-data-importer' ),
			$original_id,
			$post_id
		) );

		// Handle the terms too
		$terms = apply_filters( 'wp_import_post_terms', $terms, $post_id, $data );

		if ( ! empty( $terms ) ) {
			$term_ids = array();
			foreach ( $terms as $term ) {
				$taxonomy = $term['taxonomy'];
				$key = sha1( $taxonomy . ':' . $term['slug'] );

				if ( isset( $processed_terms[ $key ] ) ) {
					$term_ids[ $taxonomy ][] = (int) $processed_terms[ $key ];
				} else {
					$meta[] = array( 'key' => '_wxr_import_term', 'value' => $term );
					$requires_remapping = true;
				}
			}

			foreach ( $term_ids as $tax => $ids ) {
				$tt_ids = wp_set_post_terms( $post_id, $ids, $tax );
				do_action( 'wp_import_set_post_terms', $tt_ids, $ids, $tax, $post_id, $data );
			}
		}

		$this->process_comments( $comments, $post_id, $data );
		$this->process_post_meta( $meta, $post_id, $data );

		if ( 'nav_menu_item' === $data['post_type'] ) {
			$this->process_menu_item_meta( $post_id, $data, $meta );
		}

		/**
		 * Post processing completed.
		 *
		 * @param int $post_id New post ID.
		 * @param array $data Raw data imported for the post.
		 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
		 * @param array $comments Raw comment data, already processed by {@see process_comments}.
		 * @param array $terms Raw term data, already processed.
		 */
		do_action( 'wxr_importer.processed.post', $post_id, $data, $meta, $comments, $terms );
	}

	/**
	 * Process and import user data.
	 *
	 * @param  array $comments List of comment data arrays.
	 * @param  int   $post_id  Post to associate with.
	 * @param  array $post     Post data.
	 * @return int|WP_Error Number of comments imported on success, error otherwise.
	 */
	protected function process_author( $data, $meta ) {
		/**
		 * Pre-process user data.
		 *
		 * @param array $data User data. (Return empty to skip.)
		 * @param array $meta Meta data.
		 */
		$data = apply_filters( 'wxr_importer.pre_process.user', $data, $meta );

		if ( empty( $data ) ) {
			return false;
		}

		// Have we already handled this user?
		$original_id         = isset( $data['ID'] ) ? $data['ID'] : 0;
		$original_slug       = $data['user_login'];
		$processed_user_slug = cdi_cache()->get( 'user_slug', 'mapping' );
		$processed_users     = cdi_cache()->get( 'users', 'mapping' );

		if ( empty( $processed_user_slug ) ) {
			$processed_user_slug = array();
		}

		if ( empty( $processed_users ) ) {
			$processed_users = array();
		}

		if ( isset( $processed_users[ $original_id ] ) ) {
			$existing = $processed_users[ $original_id ];

			// Note the slug mapping if we need to too
			if ( ! isset( $processed_user_slug[ $original_slug ] ) ) {
				$processed_user_slug[ $original_slug ] = $existing;
			}

			return false;
		}

		if ( isset( $processed_user_slug[ $original_slug ] ) ) {
			$existing = $processed_user_slug[ $original_slug ];

			// Ensure we note the mapping too
			$processed_users[ $original_id ] = $existing;

			return false;
		}

		// Allow overriding the user's slug
		$login              = $original_slug;
		$user_slug_override = cdi_cache()->get( 'user_slug_override' );
		if ( isset( $user_slug_override[ $login ] ) ) {
			$login = $user_slug_override[ $login ];
		}

		$userdata = array(
			'user_login'   => sanitize_user( $login, true ),
			'user_pass'    => wp_generate_password(),
		);

		$allowed = array(
			'user_email'   => true,
			'display_name' => true,
			'first_name'   => true,
			'last_name'    => true,
		);

		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$userdata[ $key ] = $data[ $key ];
		}

		$user_id = wp_insert_user( wp_slash( $userdata ) );

		if ( is_wp_error( $user_id ) ) {
			$this->logger->error( sprintf(
				__( 'Failed to import user "%s"', 'wordpress-importer' ),
				$userdata['user_login']
			) );
			$this->logger->debug( $user_id->get_error_message() );

			/**
			* User processing failed.
			*
			* @param WP_Error $user_id Error object.
			* @param array $userdata Raw data imported for the user.
			*/
			do_action( 'wxr_importer.process_failed.user', $user_id, $userdata );
			return false;
		}

		if ( $original_id ) {
			$processed_users[ $original_id ] = $user_id;
		}
		$processed_user_slug[ $original_slug ] = $user_id;

		$this->logger->info( sprintf(
			__( 'Imported user "%s"', 'wordpress-importer' ),
			$userdata['user_login']
		) );

		$this->logger->debug( sprintf(
			__( 'User %d remapped to %d', 'wordpress-importer' ),
			$original_id,
			$user_id
		) );

		// TODO: Implement meta handling once WXR includes it
		/**
		* User processing completed.
		*
		* @param int $user_id New user ID.
		* @param array $userdata Raw data imported for the user.
		*/
		do_action( 'wxr_importer.processed.user', $user_id, $userdata );

	}

	/**
	 * Process and import comment data.
	 *
	 * @param  array $comments List of comment data arrays.
	 * @param  int   $post_id  Post to associate with.
	 * @param  array $post     Post data.
	 * @return int|WP_Error Number of comments imported on success, error otherwise.
	 */
	protected function process_term( $data, $meta ) {
		/**
		 * Pre-process term data.
		 *
		 * @param array $data Term data. (Return empty to skip.)
		 * @param array $meta Meta data.
		 */
		$data = apply_filters( 'wxr_importer.pre_process.term', $data, $meta );
		if ( empty( $data ) ) {
			return false;
		}

		$original_id       = isset( $data['id'] )      ? (int) $data['id']      : 0;
		$parent_id         = isset( $data['parent'] )  ? (int) $data['parent']  : 0;
		$mapping_key       = sha1( $data['taxonomy'] . ':' . $data['slug'] );
		$processed_terms   = cdi_cache()->get( 'terms', 'mapping' );
		$processed_term_id = cdi_cache()->get( 'term_id', 'mapping' );

		if ( $existing = $this->term_exists( $data ) ) {
			$processed_terms[ $mapping_key ]   = $existing;
			$processed_term_id[ $original_id ] = $existing;
			return false;
		}

		// WP really likes to repeat itself in export files
		if ( isset( $processed_terms[ $mapping_key ] ) ) {
			return false;
		}

		$termdata = array();
		$allowed = array(
			'slug' => true,
			'description' => true,
		);

		// Map the parent comment, or mark it as one we need to fix
		// TODO: add parent mapping and remapping
		/*$requires_remapping = false;
		if ( $parent_id ) {
			if ( isset( $this->mapping['term'][ $parent_id ] ) ) {
				$data['parent'] = $this->mapping['term'][ $parent_id ];
			} else {
				// Prepare for remapping later
				$meta[] = array( 'key' => '_wxr_import_parent', 'value' => $parent_id );
				$requires_remapping = true;

				// Wipe the parent for now
				$data['parent'] = 0;
			}
		}*/

		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$termdata[ $key ] = $data[ $key ];
		}

		$result = wp_insert_term( $data['name'], $data['taxonomy'], $termdata );
		if ( is_wp_error( $result ) ) {
			$this->logger->warning( sprintf(
				__( 'Failed to import %s %s', 'wordpress-importer' ),
				$data['taxonomy'],
				$data['name']
			) );
			$this->logger->debug( $result->get_error_message() );
			do_action( 'wp_import_insert_term_failed', $result, $data );

			/**
			 * Term processing failed.
			 *
			 * @param WP_Error $result Error object.
			 * @param array $data Raw data imported for the term.
			 * @param array $meta Meta data supplied for the term.
			 */
			do_action( 'wxr_importer.process_failed.term', $result, $data, $meta );
			return false;
		}

		$term_id = $result['term_id'];

		$processed_terms[ $mapping_key ]   = $term_id;
		$processed_term_id[ $original_id ] = $term_id;

		cdi_cache()->update( 'terms', $processed_terms, 'mapping' );
		cdi_cache()->update( 'term_id', $processed_term_id, 'mapping' );

		$this->logger->info( sprintf(
			__( 'Imported "%s" (%s)', 'wordpress-importer' ),
			$data['name'],
			$data['taxonomy']
		) );
		$this->logger->debug( sprintf(
			__( 'Term %d remapped to %d', 'wordpress-importer' ),
			$original_id,
			$term_id
		) );

		do_action( 'wp_import_insert_term', $term_id, $data );

		/**
		 * Term processing completed.
		 *
		 * @param int $term_id New term ID.
		 * @param array $data Raw data imported for the term.
		 */
		do_action( 'wxr_importer.processed.term', $term_id, $data );
	}

	/**
	 * Process and import comment data.
	 *
	 * @param  array $comments List of comment data arrays.
	 * @param  int   $post_id  Post to associate with.
	 * @param  array $post     Post data.
	 * @return int|WP_Error Number of comments imported on success, error otherwise.
	 */
	protected function process_comments( $comments, $post_id, $post, $post_exists = false ) {

		$comments = apply_filters( 'wp_import_post_comments', $comments, $post_id, $post );
		if ( empty( $comments ) ) {
			return 0;
		}

		$num_comments       = 0;
		$processed_comments = cdi_cache()->get( 'comments', 'mapping' );
		$processed_users    = cdi_cache()->get( 'users', 'mapping' );
		$remap_comments     = cdi_cache()->get( 'comments', 'requires_remapping' );

		// Sort by ID to avoid excessive remapping later
		usort( $comments, array( $this, 'sort_comments_by_id' ) );

		foreach ( $comments as $key => $comment ) {
			/**
			 * Pre-process comment data
			 *
			 * @param array $comment Comment data. (Return empty to skip.)
			 * @param int $post_id Post the comment is attached to.
			 */
			$comment = apply_filters( 'wxr_importer.pre_process.comment', $comment, $post_id );
			if ( empty( $comment ) ) {
				return false;
			}

			$original_id = isset( $comment['comment_id'] )      ? (int) $comment['comment_id']      : 0;
			$parent_id   = isset( $comment['comment_parent'] )  ? (int) $comment['comment_parent']  : 0;
			$author_id   = isset( $comment['comment_user_id'] ) ? (int) $comment['comment_user_id'] : 0;

			// if this is a new post we can skip the comment_exists() check
			// TODO: Check comment_exists for performance
			if ( $post_exists && $exists = $this->comment_exists( $comment ) ) {
				$processed_comments[ $original_id ] = $exists;
				continue;
			}

			// Remove meta from the main array
			$meta = isset( $comment['commentmeta'] ) ? $comment['commentmeta'] : array();
			unset( $comment['commentmeta'] );

			// Map the parent comment, or mark it as one we need to fix
			$requires_remapping = false;
			if ( $parent_id ) {
				if ( isset( $processed_comments[ $parent_id ] ) ) {
					$comment['comment_parent'] = $processed_comments[ $parent_id ];
				} else {
					// Prepare for remapping later
					$meta[] = array( 'key' => '_wxr_import_parent', 'value' => $parent_id );
					$requires_remapping = true;

					// Wipe the parent for now
					$comment['comment_parent'] = 0;
				}
			}

			// Map the author, or mark it as one we need to fix
			if ( $author_id ) {
				if ( isset( $processed_users[ $author_id ] ) ) {
					$comment['user_id'] = $processed_users[ $author_id ];
				} else {
					// Prepare for remapping later
					$meta[] = array( 'key' => '_wxr_import_user', 'value' => $author_id );
					$requires_remapping = true;

					// Wipe the user for now
					$comment['user_id'] = 0;
				}
			}

			// Run standard core filters
			$comment['comment_post_ID'] = $post_id;
			$comment = wp_filter_comment( $comment );

			// wp_insert_comment expects slashed data
			$comment_id = wp_insert_comment( wp_slash( $comment ) );
			$processed_comments[ $original_id ] = $comment_id;
			if ( $requires_remapping ) {
				$remap_comments[ $comment_id ] = true;
			}
			$this->mark_comment_exists( $comment, $comment_id );

			/**
			 * Comment has been imported.
			 *
			 * @param int $comment_id New comment ID
			 * @param array $comment Comment inserted (`comment_id` item refers to the original ID)
			 * @param int $post_id Post parent of the comment
			 * @param array $post Post data
			 */
			do_action( 'wp_import_insert_comment', $comment_id, $comment, $post_id, $post );

			// Process the meta items
			foreach ( $meta as $meta_item ) {
				$value = maybe_unserialize( $meta_item['value'] );
				add_comment_meta( $comment_id, wp_slash( $meta_item['key'] ), wp_slash( $value ) );
			}

			/**
			 * Post processing completed.
			 *
			 * @param int $post_id New post ID.
			 * @param array $comment Raw data imported for the comment.
			 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
			 * @param array $post_id Parent post ID.
			 */
			do_action( 'wxr_importer.processed.comment', $comment_id, $comment, $meta, $post_id );

			$num_comments++;
		}

		cdi_cache()->update( 'comments', $processed_comments, 'mapping' );
		cdi_cache()->update( 'comments', $remap_comments, 'requires_remapping' );

		return $num_comments;
	}

	/**
	 * Process and import post meta items.
	 *
	 * @param  array $meta    List of meta data arrays.
	 * @param  int   $post_id Post to associate with.
	 * @param  array $post    Post data.
	 * @return int|WP_Error   Number of meta items imported on success, error otherwise.
	 */
	protected function process_post_meta( $meta, $post_id, $post ) {

		if ( empty( $meta ) ) {
			return true;
		}

		$processed_users = cdi_cache()->get( 'users', 'mapping' );

		foreach ( $meta as $meta_item ) {
			/**
			 * Pre-process post meta data.
			 *
			 * @param array $meta_item Meta data. (Return empty to skip.)
			 * @param int $post_id Post the meta is attached to.
			 */
			$meta_item = apply_filters( 'wxr_importer.pre_process.post_meta', $meta_item, $post_id );
			if ( empty( $meta_item ) ) {
				return false;
			}

			$key = apply_filters( 'import_post_meta_key', $meta_item['key'], $post_id, $post );
			$value = false;

			if ( '_edit_last' == $key ) {
				$value = intval( $meta_item['value'] );
				if ( ! isset( $processed_users[ $value ] ) ) {
					// Skip!
					continue;
				}

				$value = $processed_users[ $value ];
			}

			if ( $key ) {
				// export gets meta straight from the DB so could have a serialized string
				if ( ! $value ) {
					$value = maybe_unserialize( $meta_item['value'] );
				}

				add_post_meta( $post_id, $key, $value );
				do_action( 'import_post_meta', $post_id, $key, $value );

				// if the post has a featured image, take note of this in case of remap
				if ( '_thumbnail_id' == $key ) {
					cdi_cache()->update( $post_id, (int) $value, 'featured_images' );
				}
			}
		}

		return true;
	}

	/**
	 * Attempt to create a new menu item from import data
	 *
	 * Fails for draft, orphaned menu items and those without an associated nav_menu
	 * or an invalid nav_menu term. If the post type or term object which the menu item
	 * represents doesn't exist then the menu item will not be imported (waits until the
	 * end of the import to retry again before discarding).
	 *
	 * @param array $item Menu item details from WXR file
	 */
	protected function process_menu_item_meta( $post_id, $data, $meta ) {

		$item_type          = get_post_meta( $post_id, '_menu_item_type', true );
		$original_object_id = get_post_meta( $post_id, '_menu_item_object_id', true );
		$object_id          = null;
		$processed_term_id  = cdi_cache()->get( 'term_id', 'mapping' );
		$processed_posts    = cdi_cache()->get( 'posts', 'mapping' );
		$missing_items      = cdi_cache()->get( 'missing_menu_items' );
		$remap_posts        = cdi_cache()->get( 'posts', 'requires_remapping' );

		$this->logger->debug( sprintf( 'Processing menu item %s', $item_type ) );

		$requires_remapping = false;
		switch ( $item_type ) {
			case 'taxonomy':
				if ( isset( $processed_term_id[ $original_object_id ] ) ) {
					$object_id = $processed_term_id[ $original_object_id ];
				} else {
					add_post_meta( $post_id, '_wxr_import_menu_item', wp_slash( $original_object_id ) );
					$requires_remapping = true;
				}
				break;

			case 'post_type':
				if ( isset( $processed_posts[ $original_object_id ] ) ) {
					$object_id = $processed_posts[ $original_object_id ];
				} else {
					add_post_meta( $post_id, '_wxr_import_menu_item', wp_slash( $original_object_id ) );
					$requires_remapping = true;
				}
				break;

			case 'custom':
				// Custom refers to itself, wonderfully easy.
				$object_id = $post_id;
				break;

			default:
				// associated object is missing or not imported yet, we'll retry later
				$missing_items[] = $item;
				$this->logger->debug( 'Unknown menu item type' );
				break;
		}

		if ( $requires_remapping ) {
			$remap_posts[ $post_id ] = true;
			cdi_cache()->update( 'posts', $remap_posts, 'requires_remapping' );
		}

		if ( empty( $object_id ) ) {
			// Nothing needed here.
			return;
		}

		$this->logger->debug( sprintf( 'Menu item %d mapped to %d', $original_object_id, $object_id ) );
		update_post_meta( $post_id, '_menu_item_object_id', wp_slash( $object_id ) );
	}

	/**
	 * If fetching attachments is enabled then attempt to create a new attachment
	 *
	 * @param  array  $post Attachment post details from WXR.
	 * @param  string $url  URL to fetch attachment from.
	 * @return int|WP_Error Post ID on success, WP_Error otherwise.
	 */
	protected function process_attachment( $post, $meta, $remote_url ) {

		// try to use _wp_attached file for upload folder placement to ensure the same location as the export site
		// e.g. location is 2003/05/image.jpg but the attachment post_date is 2010/09, see media_handle_upload()
		$post['upload_date'] = $post['post_date'];
		foreach ( $meta as $meta_item ) {
			if ( $meta_item['key'] !== '_wp_attached_file' ) {
				continue;
			}

			if ( preg_match( '%^[0-9]{4}/[0-9]{2}%', $meta_item['value'], $matches ) ) {
				$post['upload_date'] = $matches[0];
			}
			break;
		}

		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $remote_url ) ) {
			$remote_url = rtrim( $this->base_url, '/' ) . $remote_url;
		}

		$upload = $this->fetch_remote_file( $remote_url, $post );
		if ( is_wp_error( $upload ) ) {
			return $upload;
		}

		$info = wp_check_filetype( $upload['file'] );
		if ( ! $info ) {
			return new WP_Error( 'attachment_processing_error', __( 'Invalid file type', 'cherry-data-importer' ) );
		}

		$post['post_mime_type'] = $info['type'];

		// WP really likes using the GUID for display. Allow updating it.
		// See https://core.trac.wordpress.org/ticket/33386
		if ( $this->options['update_attachment_guids'] ) {
			$post['guid'] = $upload['url'];
		}

		// as per wp-admin/includes/upload.php
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$attachment_metadata = wp_generate_attachment_metadata( $post_id, $upload['file'] );
		wp_update_attachment_metadata( $post_id, $attachment_metadata );

		// Map this image URL later if we need to
		cdi_cache()->update( $remote_url, $upload['url'], 'url_remap' );

		// If we have a HTTPS URL, ensure the HTTP URL gets replaced too
		if ( substr( $remote_url, 0, 8 ) === 'https://') {
			$insecure_url = 'http' . substr( $remote_url, 5 );
			cdi_cache()->update( $insecure_url, $upload['url'], 'url_remap' );
		}

		if ( $this->options['aggressive_url_search'] ) {
			// remap resized image URLs, works by stripping the extension and remapping the URL stub.
			/*if ( preg_match( '!^image/!', $info['type'] ) ) {
				$parts = pathinfo( $url );
				$name = basename( $parts['basename'], ".{$parts['extension']}" ); // PATHINFO_FILENAME in PHP 5.2

				$parts_new = pathinfo( $upload['url'] );
				$name_new = basename( $parts_new['basename'], ".{$parts_new['extension']}" );

				$this->url_remap[$parts['dirname'] . '/' . $name] = $parts_new['dirname'] . '/' . $name_new;
			}*/
		}

		return $post_id;
	}

	/**
	 * Attempt to download a remote file attachment
	 *
	 * @param  string $url  URL of item to fetch.
	 * @param  array  $post Attachment details.
	 * @return array|WP_Error Local file location details on success, WP_Error otherwise.
	 */
	protected function fetch_remote_file( $url, $post ) {
		// extract the file name and extension from the url
		$file_name = basename( $url );

		// get placeholder file in the upload dir with a unique, sanitized filename
		$upload = wp_upload_bits( $file_name, 0, '', $post['upload_date'] );
		if ( $upload['error'] ) {
			return new WP_Error( 'upload_dir_error', $upload['error'] );
		}

		// fetch the remote url and write it to the placeholder file
		$response = wp_remote_get( $url, array(
			'stream'   => true,
			'filename' => $upload['file']
		) );

		// request failed
		if ( is_wp_error( $response ) ) {
			@unlink( $upload['file'] );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// make sure the fetch was successful
		if ( $code !== 200 ) {
			@unlink( $upload['file'] );
			return new WP_Error(
				'import_file_error',
				sprintf(
					__( 'Remote server returned %1$d %2$s for %3$s', 'cherry-data-importer' ),
					$code,
					get_status_header_desc( $code ),
					$url
				)
			);
		}

		$filesize = filesize( $upload['file'] );
		$headers = wp_remote_retrieve_headers( $response );

		if ( isset( $headers['content-length'] ) && $filesize != $headers['content-length'] ) {
			@unlink( $upload['file'] );
			return new WP_Error(
				'import_file_error',
				__( 'Remote file is incorrect size', 'cherry-data-importer' )
			);
		}

		if ( 0 == $filesize ) {
			@unlink( $upload['file'] );
			return new WP_Error(
				'import_file_error',
				__( 'Zero size file downloaded', 'cherry-data-importer' )
			);
		}

		$max_size = (int) $this->max_attachment_size();
		if ( ! empty( $max_size ) && $filesize > $max_size ) {
			@unlink( $upload['file'] );
			return new WP_Error(
				'import_file_error',
				sprintf( __( 'Remote file is too large, limit is %s', 'cherry-data-importer' ), size_format( $max_size ) )
			);
		}

		return $upload;
	}

	/**
	 * Decide what the maximum file size for downloaded attachments is.
	 * Default is 0 (unlimited), can be filtered via import_attachment_size_limit
	 *
	 * @return int Maximum attachment file size to import
	 */
	protected function max_attachment_size() {
		return apply_filters( 'import_attachment_size_limit', 0 );
	}

	/**
	 * Does the post exist?
	 *
	 * @param array $data Post data to check against.
	 * @return int|bool Existing post ID if it exists, false otherwise.
	 */
	protected function post_exists( $data ) {

		// Constant-time lookup if we prefilled
		$exists_key     = $data['guid'];
		$existing_posts = cdi_cache()->get( 'posts', 'exists' );

		if ( $this->options['prefill_existing_posts'] ) {
			return isset( $existing_posts[ $exists_key ] ) ? $existing_posts[ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it
		if ( isset( $existing_posts[ $exists_key ] ) ) {
			return $existing_posts[ $exists_key ];
		}

		// Still nothing, try post_exists, and cache it
		$exists = post_exists( $data['post_title'], $data['post_content'], $data['post_date'] );
		$existing_posts[ $exists_key ] = $exists;

		cdi_cache()->update( 'posts', $existing_posts, 'exists' );

		return $exists;
	}

	/**
	 * Mark the post as existing.
	 *
	 * @param array $data Post data to mark as existing.
	 * @param int $post_id Post ID.
	 */
	protected function mark_post_exists( $data, $post_id ) {
		$exists_key                          = $data['guid'];
		$existing_posts                      = cdi_cache()->get( 'posts', 'exists' );
		$this->exists['post'][ $exists_key ] = $post_id;
		cdi_cache()->update( 'posts', $existing_posts, 'exists' );
	}

	/**
	 * Does the comment exist?
	 *
	 * @param array $data Comment data to check against.
	 * @return int|bool Existing comment ID if it exists, false otherwise.
	 */
	protected function comment_exists( $data ) {
		$exists_key        = sha1( $data['comment_author'] . ':' . $data['comment_date'] );
		$existing_comments = cdi_cache()->get( 'comments', 'exists' );

		// Constant-time lookup if we prefilled
		if ( $this->options['prefill_existing_comments'] ) {
			return isset( $existing_comments[ $exists_key ] ) ? $existing_comments[ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it
		if ( isset( $existing_comments[ $exists_key ] ) ) {
			return $existing_comments[ $exists_key ];
		}

		// Still nothing, try comment_exists, and cache it
		$exists = comment_exists( $data['comment_author'], $data['comment_date'] );
		$this->existing_comments[ $exists_key ] = $exists;

		cdi_cache()->update( 'comments', $existing_comments, 'exists' );

		return $exists;
	}

	/**
	 * Mark the comment as existing.
	 *
	 * @param array $data Comment data to mark as existing.
	 * @param int $comment_id Comment ID.
	 */
	protected function mark_comment_exists( $data, $comment_id ) {
		$exists_key                       = sha1( $data['comment_author'] . ':' . $data['comment_date'] );
		$existing_comments                = cdi_cache()->get( 'comments', 'exists' );
		$existing_comments[ $exists_key ] = $comment_id;
		cdi_cache()->update( 'comments', $existing_comments, 'exists' );
	}

	/**
	 * Does the term exist?
	 *
	 * @param  array    $data Term data to check against.
	 * @return int|bool Existing term ID if it exists, false otherwise.
	 */
	protected function term_exists( $data ) {

		$exists_key     = sha1( $data['taxonomy'] . ':' . $data['slug'] );
		$existing_terms = cdi_cache()->get( 'terms', 'exists' );

		// Constant-time lookup if we prefilled
		if ( $this->options['prefill_existing_terms'] ) {
			return isset( $existing_terms[ $exists_key ] ) ? $existing_terms[ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it
		if ( isset( $existing_terms[ $exists_key ] ) ) {
			return $existing_terms[ $exists_key ];
		}

		// Still nothing, try comment_exists, and cache it
		$exists = term_exists( $data['slug'], $data['taxonomy'] );
		if ( is_array( $exists ) ) {
			$exists = $exists['term_id'];
		}

		$existing_terms[ $exists_key ] = $exists;
		cdi_cache()->update( 'terms', $existing_terms, 'exists' );

		return $exists;
	}

	/**
	 * Mark the term as existing.
	 *
	 * @param array $data Term data to mark as existing.
	 * @param int $term_id Term ID.
	 */
	protected function mark_term_exists( $data, $term_id ) {
		$exists_key                    = sha1( $data['taxonomy'] . ':' . $data['slug'] );
		$existing_terms                = cdi_cache()->get( 'terms', 'exists' );
		$existing_terms[ $exists_key ] = $comment_id;
		cdi_cache()->update( 'terms', $existing_terms, 'exists' );
	}

	/**
	 * Get a stream reader for the file.
	 *
	 * @param string $file Path to the XML file.
	 * @return XMLReader|WP_Error Reader instance on success, error otherwise.
	 */
	protected function get_reader( $file ) {
		// Avoid loading external entities for security
		$old_value = null;
		if ( function_exists( 'libxml_disable_entity_loader' ) ) {
			// $old_value = libxml_disable_entity_loader( true );
		}

		$reader = new XMLReader();
		$status = $reader->open( $file );

		if ( ! is_null( $old_value ) ) {
			// libxml_disable_entity_loader( $old_value );
		}

		if ( ! $status ) {
			return new WP_Error( 'wxr_importer.cannot_parse', __( 'Could not open the file for parsing', 'cherry-data-importer' ) );
		}

		return $reader;
	}
}
