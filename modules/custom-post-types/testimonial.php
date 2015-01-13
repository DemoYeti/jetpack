<?php
/*
 * Plugin Name: Jetpack Testimonial
 * Plugin URI:
 * Author: Automattic
 * Version: 0.1
 * License: GPL v2 or later
 * Text Domain: jetpack
 * Domain Path: /languages/
 */

class Jetpack_Testimonial {
	const CUSTOM_POST_TYPE       = 'jetpack-testimonial';
	const OPTION_NAME            = 'jetpack_testimonial';
	const OPTION_READING_SETTING = 'jetpack_testimonial_posts_per_page';

	var $version = '0.1';

	static function init() {
		static $instance = false;

		if ( ! $instance )
			$instance = new Jetpack_Testimonial;

		return $instance;
	}

	/**
	 * Conditionally hook into WordPress.
	 *
	 * Themes must declare that they support this module by adding
	 * add_theme_support( 'jetpack-testimonial' ); during after_setup_theme.
	 *
	 * If no theme support is found there is no need to hook into
	 * WordPress. We'll just return early instead.
	 */
	function __construct() {
		// Add an option to enable the CPT
		add_action( 'admin_init', array( $this, 'settings_api_init' ) );

		// Check on theme switch if theme supports CPT and setting is disabled
		add_action( 'after_switch_theme',                                              array( $this, 'activation_post_type_support' ) );

		// Make sure the post types are loaded for imports
		add_action( 'import_start',                                                    array( $this, 'register_post_types' ) );

		$setting = get_option( self::OPTION_NAME, '0' );

		// Bail early if Testimonial option is not set and the theme doesn't declare support
		if ( empty( $setting ) && ! $this->site_supports_custom_post_type() ) {
			return;
		}

		// Enable Omnisearch for Testimonials.
		if ( class_exists( 'Jetpack_Omnisearch_Posts' ) )
			new Jetpack_Omnisearch_Posts( self::CUSTOM_POST_TYPE );

		// CPT magic
		$this->register_post_types();
		add_action( sprintf( 'add_option_%s', self::OPTION_NAME ),                     array( $this, 'flush_rules_on_enable' ), 10 );
		add_action( sprintf( 'update_option_%s', self::OPTION_NAME ),                  array( $this, 'flush_rules_on_enable' ), 10 );
		add_action( sprintf( 'publish_%s', self::CUSTOM_POST_TYPE ),                   array( $this, 'flush_rules_on_first_project' ) );
		add_action( 'after_switch_theme',                                              array( $this, 'flush_rules_on_switch' ) );

		// Admin Customization
		add_filter( 'enter_title_here',                                                array( $this, 'change_default_title'    ) );
		add_filter( sprintf( 'manage_%s_posts_columns', self::CUSTOM_POST_TYPE),       array( $this, 'edit_title_column_label' ) );
		add_filter( 'post_updated_messages',                                           array( $this, 'updated_messages'        ) );
		add_action( 'customize_register',                                              array( $this, 'customize_register'      ) );

		$num_testimonials = self::count_testimonials();
		if ( ! empty( $num_testimonials ) )
			add_action( 'admin_menu',                                                  array( $this, 'add_customize_page'    ) );

		add_action( 'after_switch_theme',                                              array( $this, 'flush_rules_on_switch' ) );

		// Adjust CPT archive and custom taxonomies to obey CPT reading setting
		add_filter( 'pre_get_posts',                                                   array( $this, 'query_reading_setting' ) );

		// If called via REST API, we need to register later in lifecycle
		add_action( 'restapi_theme_init',                                              array( $this, 'maybe_register_cpt' ) );

		// Register [jetpack_testimonials] always and
		// register [testimonials] if [testimonials] isn't already set
		add_shortcode( 'jetpack_testimonials',                                         array( $this, 'jetpack_testimonial_shortcode' ) );

		if ( ! shortcode_exists( 'testimonials' ) ) {
			add_shortcode( 'testimonials',                                             array( $this, 'jetpack_testimonial_shortcode' ) );
		}

		// If CPT was enabled programatically and no CPT items exist when user switches away, disable
		if ( $setting && $this->site_supports_custom_post_type() ) {
			add_action( 'switch_theme',                                                array( $this, 'deactivation_post_type_support' ) );
		}
	}

	/**
	 * Add a checkbox field in 'Settings' > 'Writing'
	 * for enabling CPT functionality.
	 *
	 * @return null
	 */
	function settings_api_init() {
		add_settings_field(
			self::OPTION_NAME,
			'<span class="cpt-options">' . __( 'Testimonials', 'jetpack' ) . '</span>',
			array( $this, 'setting_html' ),
			'writing',
			'jetpack_cpt_section'
		);
		register_setting(
			'writing',
			self::OPTION_NAME,
			'intval'
		);

		// Check if CPT is enabled first so that intval doesn't get set to NULL on re-registering
		if ( get_option( self::OPTION_NAME, '0' ) ) {
			register_setting(
				'writing',
				self::OPTION_READING_SETTING,
				'intval'
			);
		}
	}



	/**
	 * HTML code to display a checkbox true/false option
	 * for the Testimonial CPT setting.
	 *
	 * @return html
	 */
	function setting_html() {
		if ( current_theme_supports( self::CUSTOM_POST_TYPE ) ) : ?>
			<p><?php printf( __( 'Your theme supports <strong>%s</strong>', 'jetpack' ), self::CUSTOM_POST_TYPE ); ?></p>
		<?php else : ?>
			<label for="<?php echo esc_attr( self::OPTION_NAME ); ?>">
				<input name="<?php echo esc_attr( self::OPTION_NAME ); ?>" id="<?php echo esc_attr( self::OPTION_NAME ); ?>" <?php echo checked( get_option( self::OPTION_NAME, '0' ), true, false ); ?> type="checkbox" value="1" />
				<?php esc_html_e( 'Enable Testimonials for this site.', 'jetpack' ); ?>
				<a target="_blank" href="http://en.support.wordpress.com/testimonials/"><?php esc_html_e( 'Learn More', 'jetpack' ); ?></a>
			</label>
		<?php endif;

		if ( get_option( self::OPTION_NAME, '0' ) || current_theme_supports( self::CUSTOM_POST_TYPE ) ) :
			printf( '<p><label for="%1$s">%2$s</label></p>',
				esc_attr( self::OPTION_READING_SETTING ),
				sprintf( __( 'Testimonial pages display at most %1$s testimonials', 'jetpack' ),
					sprintf( '<input name="%1$s" id="%1$s" type="number" step="1" min="1" value="%2$s" class="small-text" />',
						esc_attr( self::OPTION_READING_SETTING ),
						esc_attr( get_option( self::OPTION_READING_SETTING, '10' ) )
					)
				)
			);
		endif;
	}

	/**
	 * Should this Custom Post Type be made available?
	 */
	function site_supports_custom_post_type() {
		// If the current theme requests it.
		if ( current_theme_supports( self::CUSTOM_POST_TYPE ) || get_option( self::OPTION_NAME, '0' ) ) {
			return true;
		}

		// Otherwise, say no unless something wants to filter us to say yes.
		return (bool) apply_filters( 'jetpack_enable_cpt', false, self::CUSTOM_POST_TYPE );
	}

	/*
	 * Flush permalinks when CPT option is turned on/off
	 */
	function flush_rules_on_enable() {
		flush_rewrite_rules();
	}

	/*
	 * Count published testimonials and flush permalinks when first testimonial is published
	 */
	function flush_rules_on_first_testimonial() {
		$testimonials = get_transient( 'jetpack-testimonial-count-cache' );

		if ( false === $testimonials ) {
			flush_rewrite_rules();
			$testimonials = (int) wp_count_posts( self::CUSTOM_POST_TYPE )->publish;

			if ( ! empty( $testimonials ) ) {
				set_transient( 'jetpack-testimonial-count-cache', $testimonials, HOUR_IN_SECONDS * 12 );
			}
		}
	}

	/*
	 * Flush permalinks when CPT supported theme is activated
	 */
	function flush_rules_on_switch() {
		if ( current_theme_supports( self::CUSTOM_POST_TYPE ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * On plugin/theme activation, check if current theme supports CPT
	 */
	static function activation_post_type_support() {
		if ( current_theme_supports( self::CUSTOM_POST_TYPE ) ) {
			update_option( self::OPTION_NAME, '1' );
		}
	}

	/**
	 * On theme switch, check if CPT item exists and disable if not
	 */
	function deactivation_post_type_support() {
		$portfolios = get_posts( array(
			'fields'           => 'ids',
			'posts_per_page'   => 1,
			'post_type'        => self::CUSTOM_POST_TYPE,
			'suppress_filters' => false
		) );

		if ( empty( $portfolios ) ) {
			update_option( self::OPTION_NAME, '0' );
		}
	}

	/* Setup */
	function register_post_types() {
		if ( post_type_exists( self::CUSTOM_POST_TYPE ) ) {
			return;
		}

		register_post_type( self::CUSTOM_POST_TYPE, array(
			'description' => __( 'Customer Testimonials', 'jetpack' ),
			'labels' => array(
				'name'               => esc_html__( 'Testimonials',                   'jetpack' ),
				'singular_name'      => esc_html__( 'Testimonial',                    'jetpack' ),
				'menu_name'          => esc_html__( 'Testimonials',                   'jetpack' ),
				'all_items'          => esc_html__( 'All Testimonials',               'jetpack' ),
				'add_new'            => esc_html__( 'Add New',                        'jetpack' ),
				'add_new_item'       => esc_html__( 'Add New Testimonial',            'jetpack' ),
				'edit_item'          => esc_html__( 'Edit Testimonial',               'jetpack' ),
				'new_item'           => esc_html__( 'New Testimonial',                'jetpack' ),
				'view_item'          => esc_html__( 'View Testimonial',               'jetpack' ),
				'search_items'       => esc_html__( 'Search Testimonials',            'jetpack' ),
				'not_found'          => esc_html__( 'No Testimonials found',          'jetpack' ),
				'not_found_in_trash' => esc_html__( 'No Testimonials found in Trash', 'jetpack' ),
			),
			'supports' => array(
				'title',
				'editor',
				'thumbnail',
				'page-attributes',
			),
			'rewrite' => array(
				'slug'       => 'testimonial',
				'with_front' => false,
				'feeds'      => false,
				'pages'      => true,
			),
			'public'          => true,
			'show_ui'         => true,
			'menu_position'   => 20, // below Pages
			'capability_type' => 'page',
			'map_meta_cap'    => true,
			'has_archive'     => true,
			'query_var'       => 'testimonial',
		) );
	}

	/**
	 * Change ‘Enter Title Here’ text for the Testimonial.
	 */
	function change_default_title( $title ) {
		$screen = get_current_screen();

		if ( 'jetpack-testimonial' == $screen->post_type )
			$title = esc_html__( "Enter the customer's name here", 'jetpack' );

		return $title;
	}

	/**
	 * Change ‘Title’ column label on all Testimonials page.
	 */
	function edit_title_column_label( $columns ) {
		$columns['title'] = esc_html__( 'Customer Name', 'jetpack' );

		return $columns;
	}

	/**
	 * Update messages for the Testimonial admin.
	 */
	function updated_messages( $messages ) {
		global $post;

		$messages['jetpack-testimonial'] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => sprintf( __( 'Testimonial updated. <a href="%s">View testimonial</a>', 'jetpack'), esc_url( get_permalink( $post->ID ) ) ),
			2  => esc_html__( 'Custom field updated.', 'jetpack' ),
			3  => esc_html__( 'Custom field deleted.', 'jetpack' ),
			4  => esc_html__( 'Testimonial updated.', 'jetpack' ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( esc_html__( 'Testimonial restored to revision from %s', 'jetpack'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => sprintf( __( 'Testimonial published. <a href="%s">View testimonial</a>', 'jetpack' ), esc_url( get_permalink( $post->ID ) ) ),
			7  => esc_html__( 'Testimonial saved.', 'jetpack' ),
			8  => sprintf( __( 'Testimonial submitted. <a target="_blank" href="%s">Preview testimonial</a>', 'jetpack'), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post->ID ) ) ) ),
			9  => sprintf( __( 'Testimonial scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview testimonial</a>', 'jetpack' ),
			// translators: Publish box date format, see http://php.net/date
			date_i18n( __( 'M j, Y @ G:i', 'jetpack' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post->ID) ) ),
			10 => sprintf( __( 'Testimonial draft updated. <a target="_blank" href="%s">Preview testimonial</a>', 'jetpack' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post->ID ) ) ) ),
		);

		return $messages;
	}

	/**
	 * Follow CPT reading setting on CPT archive page
	 */
	function query_reading_setting( $query ) {
		if ( ! is_admin() &&
		     $query->is_main_query() &&
		     ( $query->is_post_type_archive( self::CUSTOM_POST_TYPE ) )
		) {
			$query->set( 'posts_per_page', get_option( self::OPTION_READING_SETTING, '10' ) );
		}
	}

	function set_testimonial_option() {
		$testimonials_option = get_option( 'jetpack_testimonial' );

		$testimonials = wp_count_posts( 'jetpack-testimonial' );
		$published_testimonials = $testimonials->publish;

		update_option( 'jetpack_testimonial', $published_testimonials );
	}

	function count_testimonials() {
		$testimonials = get_transient( 'jetpack-testimonial-count-cache' );

		if ( false === $testimonials ) {
			$testimonials = (int) wp_count_posts( 'jetpack-testimonial' )->publish;

			if ( ! empty( $testimonials ) ) {
				set_transient( 'jetpack-testimonial-count-cache', $testimonials, 60*60*12 );
			}
		}

		return $testimonials;
	}

	/**
	 * Adds a submenu link to the Customizer.
	 */
	function add_customize_page() {
		add_submenu_page(
			'edit.php?post_type=jetpack-testimonial',
			esc_html__( 'Customize Testimonials Archive', 'jetpack' ),
			esc_html__( 'Customize', 'jetpack' ),
			'edit_theme_options',
			add_query_arg( array( 'url' => urlencode( home_url( 'testimonial' ) ) ), 'customize.php' ) . '#accordion-section-jetpack_testimonials'
		);
	}

	/**
	 * Adds testimonial section to the Customizer.
	 */
	function customize_register( $wp_customize ) {
		jetpack_testimonial_custom_control_classes();

		$wp_customize->add_section( 'jetpack_testimonials', array(
			'title'          => esc_html__( 'Testimonials', 'jetpack' ),
			'theme_supports' => 'jetpack-testimonial',
			'priority'       => 130,
		) );

		$wp_customize->add_setting( 'jetpack_testimonials[page-title]', array(
			'default'              => esc_html__( 'Testimonials', 'jetpack' ),
			'sanitize_callback'    => array( 'Jetpack_Testimonial_Title_Control', 'sanitize_content' ),
			'sanitize_js_callback' => array( 'Jetpack_Testimonial_Title_Control', 'sanitize_content' ),
		) );
		$wp_customize->add_control( 'jetpack_testimonials[page-title]', array(
			'section' => 'jetpack_testimonials',
			'label'   => esc_html__( 'Testimonial Page Title', 'jetpack' ),
			'type'    => 'text',
		) );

		$wp_customize->add_setting( 'jetpack_testimonials[page-content]', array(
			'default'              => '',
			'sanitize_callback'    => array( 'Jetpack_Testimonial_Textarea_Control', 'sanitize_content' ),
			'sanitize_js_callback' => array( 'Jetpack_Testimonial_Textarea_Control', 'sanitize_content' ),
		) );
		$wp_customize->add_control( new Jetpack_Testimonial_Textarea_Control( $wp_customize, 'jetpack_testimonials[page-content]', array(
			'section'  => 'jetpack_testimonials',
			'settings' => 'jetpack_testimonials[page-content]',
			'label'    => esc_html__( 'Testimonial Page Content', 'jetpack' ),
		) ) );

		$wp_customize->add_setting( 'jetpack_testimonials[featured-image]', array(
			'default'              => '',
			'sanitize_callback'    => array( 'Jetpack_Testimonial_Image_Control', 'attachment_guid_to_id' ),
			'sanitize_js_callback' => array( 'Jetpack_Testimonial_Image_Control', 'attachment_guid_to_id' ),
			'theme_supports'       => 'post-thumbnails',
		) );
		$wp_customize->add_control( new Jetpack_Testimonial_Image_Control( $wp_customize, 'jetpack_testimonials[featured-image]', array(
			'section' => 'jetpack_testimonials',
			'label'   => esc_html__( 'Testimonial Page Featured Image', 'jetpack' ),
		) ) );
	}


	/**
	 * Our [testimonial] shortcode.
	 * Prints Testimonial data styled to look good on *any* theme.
	 *
	 * @return jetpack_testimonial_shortcode_html
	 */
	static function jetpack_testimonial_shortcode( $atts ) {
		// Default attributes
		$atts = shortcode_atts( array(
			'display_content' => true,
			'image'           => true,
			'columns'         => 1,
			'showposts'       => -1,
			'order'           => 'asc',
			'orderby'         => 'date',
		), $atts, 'testimonial' );

		// A little sanitization
		if ( $atts['display_content'] && 'true' != $atts['display_content'] ) {
			$atts['display_content'] = false;
		}

		$atts['columns'] = absint( $atts['columns'] );

		$atts['showposts'] = intval( $atts['showposts'] );


		if ( $atts['order'] ) {
			$atts['order'] = urldecode( $atts['order'] );
			$atts['order'] = strtoupper( $atts['order'] );
			if ( 'DESC' != $atts['order'] ) {
				$atts['order'] = 'ASC';
			}
		}

		if ( $atts['orderby'] ) {
			$atts['orderby'] = urldecode( $atts['orderby'] );
			$atts['orderby'] = strtolower( $atts['orderby'] );
			$allowed_keys = array('author', 'date', 'title', 'rand');

			$parsed = array();
			foreach ( explode( ',', $atts['orderby'] ) as $testimonial_index_number => $orderby ) {
				if ( ! in_array( $orderby, $allowed_keys ) ) {
					continue;
				}
				$parsed[] = $orderby;
			}

			if ( empty( $parsed ) ) {
				unset($atts['orderby']);
			} else {
				$atts['orderby'] = implode( ' ', $parsed );
			}
		}

		// enqueue shortcode styles when shortcode is used
		wp_enqueue_style( 'jetpack-testimonial-style', plugins_url( 'css/testimonial-shortcode.css', __FILE__ ), array(), '20140326' );

		return self::jetpack_testimonial_shortcode_html( $atts );
	}

	/**
	 * The Testimonial shortcode loop.
	 *
	 * @return html
	 */
	static function jetpack_testimonial_shortcode_html( $atts ) {
		// Default query arguments
		$defaults = array(
			'order'          => $atts['order'],
			'orderby'        => $atts['orderby'],
			'posts_per_page' => $atts['showposts'],
		);

		$args = wp_parse_args( $atts, $defaults );
		$args['post_type'] = self::CUSTOM_POST_TYPE; // Force this post type
		$query = new WP_Query( $args );

		$testimonial_index_number = 0;

		// If we have testimonials, create the html
		if ( $query->have_posts() ) {

			ob_start(); ?>
			<div class="jetpack-testimonial-shortcode column-<?php echo esc_attr( $atts['columns'] ); ?>">
				<?php  // open .jetpack-testimonial-shortcode

				// Construct the loop...
				while ( $query->have_posts() ) {
					$query->the_post();
					$post_id = get_the_ID();
					?>
					<div class="testimonial-entry <?php echo esc_attr( self::get_testimonial_class( $testimonial_index_number, $atts['columns'] ) ); ?>">
						<?php
						// The content
						if ( false != $atts['display_content'] ): ?>
							<div class="testimonial-entry-content"><?php the_excerpt(); ?></div>
						<?php endif; ?>

						<span class="testimonial-entry-title">&#8213; <a href="<?php echo esc_url( get_permalink() ); ?>" title="<?php echo esc_attr( the_title_attribute( ) ); ?>"><?php the_title(); ?></a></span>
						<?php
						// Featured image
						if ( false != $atts['image'] ):
							echo self::get_testimonial_thumbnail_link( $post_id );
						endif;
						?>
					</div><!-- close .testimonial-entry -->
					<?php
					$testimonial_index_number++;
				} // end of while loop

				wp_reset_postdata();
				?>
			</div><!-- close .jetpack-testimonial-shortcode -->
		<?php
		} else { ?>
			<p><em><?php _e( 'Your Testimonial Archive currently has no entries. You can start creating them on your dashboard.', 'jetpack' ); ?></p></em>
		<?php
		}
		$html = ob_get_clean();

		// Return the HTML block
		return $html;
	}

	/**
	 * Individual testimonial class
	 *
	 * @return string
	 */
	static function get_testimonial_class( $testimonial_index_number, $columns ) {
		$class = array();

		$class[] = 'testimonial-entry-column-'.$columns;

		if( $columns > 1) {
			if ( ( $testimonial_index_number % 2 ) == 0 ) {
				$class[] = 'testimonial-entry-mobile-first-item-row';
			} else {
				$class[] = 'testimonial-entry-mobile-last-item-row';
			}
		}

		// add first and last classes to first and last items in a row
		if ( ( $testimonial_index_number % $columns ) == 0 ) {
			$class[] = 'testimonial-entry-first-item-row';
		} elseif ( ( $testimonial_index_number % $columns ) == ( $columns - 1 ) ) {
			$class[] = 'testimonial-entry-last-item-row';
		}


		/**
		 * Filter the class applied to testimonial div in the testimonial
		 *
		 * @param string $class class name of the div.
		 * @param int $testimonial_index_number iterator count the number of columns up starting from 0.
		 * @param int $columns number of columns to display the content in.
		 *
		 */
		return apply_filters( 'testimonial-entry-post-class', implode( " ", $class ) , $testimonial_index_number, $columns );
	}

	/**
	 * Display the featured image if it's available
	 *
	 * @return html
	 */
	static function get_testimonial_thumbnail_link( $post_id ) {
		if ( has_post_thumbnail( $post_id ) ) {
			return '<a class="testimonial-featured-image" href="' . esc_url( get_permalink( $post_id ) ) . '">' . get_the_post_thumbnail( $post_id, apply_filters( 'jetpack_testimonial_thumbnail_size', 'thumbnail' ) ) . '</a>';
		}
	}
}

function jetpack_testimonial_custom_control_classes() {
	class Jetpack_Testimonial_Title_Control extends WP_Customize_Control {
		public static function sanitize_content( $value ) {
			if ( '' != $value )
				$value = trim( convert_chars( wptexturize( $value ) ) );

			return $value;
		}
	}

	class Jetpack_Testimonial_Textarea_Control extends WP_Customize_Control {
		public $type = 'textarea';

		public function render_content() {
			?>
			<label>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<textarea rows="5" style="width:100%;" <?php $this->link(); ?>><?php echo esc_textarea( $this->value() ); ?></textarea>
			</label>
			<?php
		}

		public static function sanitize_content( $value ) {
			if ( ! empty( $value ) )
				$value = apply_filters( 'the_content', $value );

			$value = preg_replace( '@<div id="jp-post-flair"([^>]+)?>(.+)?</div>@is', '', $value ); // Strip WPCOM and Jetpack post flair if included in content

			return $value;
		}
	}

	/**
	 * Need to extend WP_Customize_Image_Control to return attachment ID instead of url
	 */
	class Jetpack_Testimonial_Image_Control extends WP_Customize_Image_Control {
		public $context = 'custom_image';

		public function __construct( $manager, $id, $args ) {
			$this->get_url = array( $this, 'get_img_url' );
			parent::__construct( $manager, $id, $args );
		}

		public static function get_img_url( $attachment_id = 0 ) {
			if ( is_numeric( $attachment_id ) && wp_attachment_is_image( $attachment_id ) )
				list( $image, $x, $y ) = wp_get_attachment_image_src( $attachment_id );

			return ! empty( $image ) ? $image : $attachment_id;
		}

		public static function attachment_guid_to_id( $value ) {

			if ( is_numeric( $value ) || empty( $value ) )
				return $value;

			$matches = get_posts( array( 'post_type' => 'attachment', 'guid' => $value ) );

			if ( empty( $matches ) )
				return false;

			return $matches[0]->ID; // this is the match we want
		}
	}
}

add_action( 'init', array( 'Jetpack_Testimonial', 'init' ) );

// Check on plugin activation if theme supports CPT
register_activation_hook( __FILE__,                         array( 'Jetpack_Testimonial', 'activation_post_type_support' ) );
add_action( 'jetpack_activate_module_custom-content-types', array( 'Jetpack_Testimonial', 'activation_post_type_support' ) );
