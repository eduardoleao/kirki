<?php
/**
 * Initializes Kirki
 *
 * @package     Kirki
 * @category    Core
 * @author      Aristeides Stathopoulos
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       1.0
 */

/**
 * Initialize Kirki
 */
class Kirki_Init {

	/**
	 * The class constructor.
	 */
	public function __construct() {

		global $wp_customize;
		if ( $wp_customize ) {
			$this->set_url();
			add_action( 'after_setup_theme', array( $this, 'set_url' ) );
		}
		add_action( 'wp_loaded', array( $this, 'add_to_customizer' ), 1 );

		new Kirki_Values();
	}

	/**
	 * Properly set the Kirki URL for assets.
	 * Determines if Kirki is installed as a plugin, in a child theme, or a parent theme
	 * and then does some calculations to get the proper URL for its CSS & JS assets.
	 */
	public function set_url() {

		Kirki::$path = wp_normalize_path( dirname( KIRKI_PLUGIN_FILE ) );

		// Works in most cases.
		// Serves as a fallback in case all other checks fail.
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$content_dir = wp_normalize_path( WP_CONTENT_DIR );
			if ( false !== strpos( Kirki::$path, $content_dir ) ) {
				$relative_path = str_replace( $content_dir, '', Kirki::$path );
				Kirki::$url = content_url( $relative_path );
			}
		}

		// If Kirki is installed as a plugin, use that for the URL.
		if ( Kirki_Util::is_plugin() ) {
			Kirki::$url = plugin_dir_url( KIRKI_PLUGIN_FILE );
		}

		// Get the path to the theme.
		$theme_path = wp_normalize_path( get_template_directory() );

		// Is Kirki included in the theme?
		if ( false !== strpos( Kirki::$path, $theme_path ) ) {
			Kirki::$url = get_template_directory_uri() . str_replace( $theme_path, '', Kirki::$path );
		}

		// Is there a child-theme?
		$child_theme_path = wp_normalize_path( get_stylesheet_directory_uri() );
		if ( $child_theme_path !== $theme_path ) {
			// Is Kirki included in a child theme?
			if ( false !== strpos( Kirki::$path, $child_theme_path ) ) {
				Kirki::$url = get_template_directory_uri() . str_replace( $child_theme_path, '', Kirki::$path );
			}
		}

		// Apply the kirki/config filter.
		$config = apply_filters( 'kirki/config', array() );
		if ( isset( $config['url_path'] ) ) {
			Kirki::$url = $config['url_path'];
		}

		// Escapes the URL.
		Kirki::$url = esc_url_raw( Kirki::$url );
		// Make sure the right protocol is used.
		Kirki::$url = set_url_scheme( Kirki::$url );
	}

	/**
	 * Helper function that adds the fields, sections and panels to the customizer.
	 *
	 * @return void
	 */
	public function add_to_customizer() {
		$this->fields_from_filters();
		add_action( 'customize_register', array( $this, 'register_section_types' ) );
		add_action( 'customize_register', array( $this, 'add_panels' ), 97 );
		add_action( 'customize_register', array( $this, 'add_sections' ), 98 );
		add_action( 'customize_register', array( $this, 'add_fields' ), 99 );
	}

	/**
	 * Register section types
	 *
	 * @return void
	 */
	public function register_section_types() {
		global $wp_customize;

		$section_types = apply_filters( 'kirki/section_types', array() );
		foreach ( $section_types as $section_type ) {
			$wp_customize->register_section_type( $section_type );
		}
	}

	/**
	 * Register our panels to the WordPress Customizer.
	 *
	 * @access public
	 */
	public function add_panels() {
		if ( ! empty( Kirki::$panels ) ) {
			foreach ( Kirki::$panels as $panel_args ) {
				// Extra checks for nested panels.
				if ( isset( $panel_args['panel'] ) ) {
					if ( isset( Kirki::$panels[ $panel_args['panel'] ] ) ) {
						// Set the type to nested.
						$panel_args['type'] = 'kirki-nested';
					}
				}

				new Kirki_Panel( $panel_args );
			}
		}
	}

	/**
	 * Register our sections to the WordPress Customizer.
	 *
	 * @var object The WordPress Customizer object
	 * @return void
	 */
	public function add_sections() {
		if ( ! empty( Kirki::$sections ) ) {
			foreach ( Kirki::$sections as $section_args ) {
				// Extra checks for nested sections.
				if ( isset( $section_args['section'] ) ) {
					if ( isset( Kirki::$sections[ $section_args['section'] ] ) ) {
						// Set the type to nested.
						$section_args['type'] = 'kirki-nested';
						// We need to check if the parent section is nested inside a panel.
						$parent_section = Kirki::$sections[ $section_args['section'] ];
						if ( isset( $parent_section['panel'] ) ) {
							$section_args['panel'] = $parent_section['panel'];
						}
					}
				}
				new Kirki_Section( $section_args );
			}
		}
	}

	/**
	 * Create the settings and controls from the $fields array and register them.
	 *
	 * @var object The WordPress Customizer object.
	 * @return void
	 */
	public function add_fields() {

		global $wp_customize;
		foreach ( Kirki::$fields as $args ) {

			// Create the settings.
			new Kirki_Settings( $args );

			// Check if we're on the customizer.
			// If we are, then we will create the controls, add the scripts needed for the customizer
			// and any other tweaks that this field may require.
			if ( $wp_customize ) {

				// Create the control.
				new Kirki_Control( $args );

			}
		}
	}

	/**
	 * Process fields added using the 'kirki/fields' and 'kirki/controls' filter.
	 * These filters are no longer used, this is simply for backwards-compatibility.
	 *
	 * @access private
	 * @since 2.0.0
	 */
	private function fields_from_filters() {

		$fields = apply_filters( 'kirki/controls', array() );
		$fields = apply_filters( 'kirki/fields', $fields );

		if ( ! empty( $fields ) ) {
			foreach ( $fields as $field ) {
				Kirki::add_field( 'global', $field );
			}
		}
	}

	/**
	 * Alias for the is_plugin static method in the Kirki_Util class.
	 * This is here for backwards-compatibility purposes.
	 *
	 * @static
	 * @access public
	 * @since 3.0.0
	 * @return bool
	 */
	public static function is_plugin() {
		// Return result using the Kirki_Util class.
		return Kirki_Util::is_plugin();
	}

	/**
	 * Alias for the get_variables static method in the Kirki_Util class.
	 * This is here for backwards-compatibility purposes.
	 *
	 * @static
	 * @access public
	 * @since 2.0.0
	 * @return array Formatted as array( 'variable-name' => value ).
	 */
	public static function get_variables() {
		// Log error for developers.
		_doing_it_wrong( __METHOD__, esc_attr__( 'We detected you\'re using Kirki_Init::get_variables(). Please use Kirki_Util::get_variables() instead.', 'kirki' ), '3.0.10' );
		// Return result using the Kirki_Util class.
		return Kirki_Util::get_variables();
	}

}
