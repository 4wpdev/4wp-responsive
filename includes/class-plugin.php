<?php
namespace ForWP\Responsive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	/**
	 * Cache for computed spacing sizes.
	 *
	 * @var array|null
	 */
	private static $spacing_sizes_cache = null;

	/**
	 * Flag indicating whether fallback spacing sizes are used.
	 *
	 * @var bool
	 */
	private static $spacing_sizes_fallback = false;

	/**
	 * Cache for computed font sizes.
	 *
	 * @var array|null
	 */
	private static $font_sizes_cache = null;

	/**
	 * Flag indicating whether fallback font sizes are used.
	 *
	 * @var bool
	 */
	private static $font_sizes_fallback = false;

	/**
	 * Supported text align values.
	 *
	 * @var string[]
	 */
	private static $text_align_values = [ 'left', 'center', 'right', 'justify' ];

	/**
	 * Cache for computed breakpoints.
	 *
	 * @var array|null
	 */
	private static $breakpoints_cache = null;

	/**
	 * Step 1: Bootstrap plugin components and hooks.
	 */
	public static function init() {
		// Step 1.1: Load component classes.
		require_once __DIR__ . '/class-admin.php';
		require_once __DIR__ . '/class-editor.php';
		require_once __DIR__ . '/class-frontend.php';

		// Step 1.2: Initialize components.
		Admin::init();
		Editor::init();
		Frontend::init();

		// Step 1.3: Register responsive attributes for all blocks.
		add_filter( 'register_block_type_args', [ __CLASS__, 'add_responsive_attributes' ], 10, 2 );
	}

	/**
	 * Step 2: Add responsive spacing attributes to all blocks.
	 *
	 * @param array  $args       Block arguments.
	 * @param string $block_name Block name.
	 * @return array
	 */
	public static function add_responsive_attributes( $args, $block_name ) {
		if ( ! isset( $args['attributes'] ) ) {
			$args['attributes'] = [];
		}

		// Step 2.1: Generate attribute definitions for padding and margin per breakpoint.
		foreach ( self::get_spacing_attribute_keys() as $attribute_key ) {
			$args['attributes'][ $attribute_key ] = [
				'type'    => 'string',
				'default' => '',
			];
		}

		// Step 2.2: Generate attribute definitions for visibility controls.
		foreach ( self::get_visibility_attribute_keys() as $attribute_key ) {
			$args['attributes'][ $attribute_key ] = [
				'type'    => 'boolean',
				'default' => false,
			];
		}

		// Step 2.3: Generate attribute definitions for responsive font sizes.
		foreach ( self::get_typography_attribute_keys() as $attribute_key ) {
			$args['attributes'][ $attribute_key ] = [
				'type'    => 'string',
				'default' => '',
			];
		}

		// Step 2.4: Generate attribute definitions for responsive text alignment.
		foreach ( self::get_alignment_attribute_keys() as $attribute_key ) {
			$args['attributes'][ $attribute_key ] = [
				'type'    => 'string',
				'default' => '',
			];
		}

		return $args;
	}

	/**
	 * Step 3: Build the list of responsive spacing attribute keys.
	 *
	 * @return string[]
	 */
	public static function get_spacing_attribute_keys() {
		$types   = [ 'Padding', 'Margin' ];
		$sides   = [ 'Top', 'Right', 'Bottom', 'Left' ];
		$devices = [ 'Mobile', 'Tablet', 'Desktop' ];
		$keys    = [];

		// Step 3.1: Compose each attribute key consistently across the codebase.
		foreach ( $types as $type ) {
			foreach ( $sides as $side ) {
				foreach ( $devices as $device ) {
					$keys[] = 'responsive' . $type . $side . $device;
				}
			}
		}

		return $keys;
	}

	/**
	 * Step 3.2: Build the list of responsive visibility attribute keys.
	 *
	 * @return string[]
	 */
	public static function get_visibility_attribute_keys() {
		$devices = [ 'Mobile', 'Tablet', 'Desktop' ];
		$keys    = [];

		// Step 3.2.1: Compose hide/show keys for each breakpoint.
		foreach ( $devices as $device ) {
			$keys[] = 'responsiveHide' . $device;
			$keys[] = 'responsiveShow' . $device;
		}

		return $keys;
	}

	/**
	 * Step 3.3: Build the list of responsive typography attribute keys.
	 *
	 * @return string[]
	 */
	public static function get_typography_attribute_keys() {
		$devices = [ 'Mobile', 'Tablet', 'Desktop' ];
		$keys    = [];

		// Step 3.3.1: Compose font-size keys for each breakpoint.
		foreach ( $devices as $device ) {
			$keys[] = 'responsiveFontSize' . $device;
		}

		return $keys;
	}

	/**
	 * Step 3.4: Build the list of responsive text alignment attribute keys.
	 *
	 * @return string[]
	 */
	public static function get_alignment_attribute_keys() {
		$devices = [ 'Mobile', 'Tablet', 'Desktop' ];
		$keys    = [];

		// Step 3.4.1: Compose text-align keys for each breakpoint.
		foreach ( $devices as $device ) {
			$keys[] = 'responsiveTextAlign' . $device;
		}

		return $keys;
	}

	/**
	 * Step 4: Provide spacing preset sizes from theme.json (or fallback).
	 *
	 * @return array[] Each item contains 'slug' and 'name'.
	 */
	public static function get_spacing_sizes() {
		if ( null !== self::$spacing_sizes_cache ) {
			return self::$spacing_sizes_cache;
		}

		// Step 4.1: Pull spacing sizes from global settings (theme.json merged data).
		$sizes = wp_get_global_settings( [ 'spacing', 'spacingSizes' ] );

		// Step 4.1.1: Fall back to the active theme.json when global settings are empty.
		if ( ! is_array( $sizes ) || empty( $sizes ) ) {
			$theme_json_path = wp_get_theme()->get_file_path( 'theme.json' );
			if ( $theme_json_path && file_exists( $theme_json_path ) ) {
				$theme_json = wp_json_file_decode( $theme_json_path, [ 'associative' => true ] );
				if ( is_array( $theme_json ) ) {
					$sizes = $theme_json['settings']['spacing']['spacingSizes'] ?? [];
				}
			}
		}

		if ( ! is_array( $sizes ) || empty( $sizes ) ) {
			// Step 4.2: Provide a minimal fallback scale when theme.json is missing sizes.
			self::$spacing_sizes_fallback = true;
			$sizes                         = [
				[ 'slug' => 'xs', 'name' => __( 'XS', '4wp-responsive' ), 'size' => '4px' ],
				[ 'slug' => 'sm', 'name' => __( 'SM', '4wp-responsive' ), 'size' => '8px' ],
				[ 'slug' => 'md', 'name' => __( 'MD', '4wp-responsive' ), 'size' => '16px' ],
				[ 'slug' => 'lg', 'name' => __( 'LG', '4wp-responsive' ), 'size' => '24px' ],
				[ 'slug' => 'xl', 'name' => __( 'XL', '4wp-responsive' ), 'size' => '32px' ],
				[ 'slug' => '2xl', 'name' => __( '2XL', '4wp-responsive' ), 'size' => '40px' ],
			];
		}

		// Step 4.3: Flatten "default/theme" grouped formats when present.
		if ( is_array( $sizes ) && ( isset( $sizes['default'] ) || isset( $sizes['theme'] ) ) ) {
			$grouped_sizes = [];
			if ( ! empty( $sizes['default'] ) && is_array( $sizes['default'] ) ) {
				$grouped_sizes = array_merge( $grouped_sizes, $sizes['default'] );
			}
			if ( ! empty( $sizes['theme'] ) && is_array( $sizes['theme'] ) ) {
				$grouped_sizes = array_merge( $grouped_sizes, $sizes['theme'] );
			}
			if ( ! empty( $grouped_sizes ) ) {
				$sizes = $grouped_sizes;
			}
		}

		// Step 4.4: Normalize the array to a slug/name structure.
		$normalized = [];
		foreach ( $sizes as $key => $size ) {
			// Step 4.3.1: Cast objects to arrays for consistent access.
			if ( is_object( $size ) ) {
				$size = (array) $size;
			}

			// Step 4.3.2: Handle associative formats where the slug is the key.
			$slug = '';
			$name = '';
			$value = null;
			if ( is_array( $size ) ) {
				$slug  = $size['slug'] ?? ( is_string( $key ) ? $key : '' );
				$name  = $size['name'] ?? $slug;
				$value = $size['size'] ?? $size['value'] ?? null;
			} elseif ( is_string( $key ) ) {
				$slug  = $key;
				$name  = $key;
				$value = is_string( $size ) ? $size : null;
			}

			if ( '' === $slug ) {
				continue;
			}
			$normalized[] = [
				'slug' => sanitize_key( $slug ),
				'name' => $name,
				'size' => $value,
			];
		}

		// Step 4.5: If normalization failed, fall back to a default scale.
		if ( empty( $normalized ) ) {
			self::$spacing_sizes_fallback = true;
			$normalized                   = [
				[ 'slug' => 'xs', 'name' => __( 'XS', '4wp-responsive' ), 'size' => '4px' ],
				[ 'slug' => 'sm', 'name' => __( 'SM', '4wp-responsive' ), 'size' => '8px' ],
				[ 'slug' => 'md', 'name' => __( 'MD', '4wp-responsive' ), 'size' => '16px' ],
				[ 'slug' => 'lg', 'name' => __( 'LG', '4wp-responsive' ), 'size' => '24px' ],
				[ 'slug' => 'xl', 'name' => __( 'XL', '4wp-responsive' ), 'size' => '32px' ],
				[ 'slug' => '2xl', 'name' => __( '2XL', '4wp-responsive' ), 'size' => '40px' ],
			];
		}

		self::$spacing_sizes_cache = $normalized;
		return self::$spacing_sizes_cache;
	}

	/**
	 * Step 4.6: Provide font-size preset values from theme.json (or fallback).
	 *
	 * @return array[] Each item contains 'slug' and 'name'.
	 */
	public static function get_font_sizes() {
		if ( null !== self::$font_sizes_cache ) {
			return self::$font_sizes_cache;
		}

		// Step 4.6.1: Pull font sizes from global settings (theme.json merged data).
		$sizes = wp_get_global_settings( [ 'typography', 'fontSizes' ] );

		// Step 4.6.2: Fall back to the active theme.json when global settings are empty.
		if ( ! is_array( $sizes ) || empty( $sizes ) ) {
			$theme_json_path = wp_get_theme()->get_file_path( 'theme.json' );
			if ( $theme_json_path && file_exists( $theme_json_path ) ) {
				$theme_json = wp_json_file_decode( $theme_json_path, [ 'associative' => true ] );
				if ( is_array( $theme_json ) ) {
					$sizes = $theme_json['settings']['typography']['fontSizes'] ?? [];
				}
			}
		}

		// Step 4.6.3: Normalize the array to a slug/name structure.
		$normalized = [];
		foreach ( $sizes as $key => $size ) {
			if ( is_object( $size ) ) {
				$size = (array) $size;
			}

			$slug  = '';
			$name  = '';
			$value = null;
			if ( is_array( $size ) ) {
				$slug  = $size['slug'] ?? ( is_string( $key ) ? $key : '' );
				$name  = $size['name'] ?? $slug;
				$value = $size['size'] ?? $size['value'] ?? null;
			} elseif ( is_string( $key ) ) {
				$slug  = $key;
				$name  = $key;
				$value = is_string( $size ) ? $size : null;
			}

			if ( '' === $slug ) {
				continue;
			}
			$normalized[] = [
				'slug' => sanitize_key( $slug ),
				'name' => $name,
				'size' => $value,
			];
		}

		// Step 4.6.4: If normalization failed, mark fallback.
		if ( empty( $normalized ) ) {
			self::$font_sizes_fallback = true;
		}

		self::$font_sizes_cache = $normalized;
		return self::$font_sizes_cache;
	}

	/**
	 * Step 4.7: Check whether fallback font sizes are in use.
	 *
	 * @return bool
	 */
	public static function uses_fallback_font_sizes() {
		self::get_font_sizes();
		return self::$font_sizes_fallback;
	}

	/**
	 * Step 5: Check whether fallback spacing sizes are in use.
	 *
	 * @return bool
	 */
	public static function uses_fallback_spacing_sizes() {
		// Step 5.1: Trigger spacing cache population if it hasn't run yet.
		self::get_spacing_sizes();
		return self::$spacing_sizes_fallback;
	}

	/**
	 * Step 6: Provide breakpoints with theme.json-first precedence.
	 *
	 * @return array
	 */
	public static function get_breakpoints() {
		if ( null !== self::$breakpoints_cache ) {
			return self::$breakpoints_cache;
		}

		// Step 6.1: Start with defaults that match a common WP baseline.
		$defaults = [
			'mobile'  => [ 'max' => 599, 'label' => __( 'Mobile', '4wp-responsive' ) ],
			'tablet'  => [ 'min' => 600, 'max' => 1023, 'label' => __( 'Tablet', '4wp-responsive' ) ],
			'desktop' => [ 'min' => 1024, 'label' => __( 'Desktop', '4wp-responsive' ) ],
		];

		// Step 6.2: Pull custom breakpoints from theme.json if provided.
		$theme_breakpoints = wp_get_global_settings( [ 'custom', 'breakpoints' ] );

		// Step 6.3: Fall back to saved settings when theme.json does not define breakpoints.
		$saved_breakpoints = get_option( '4wp_responsive_breakpoints', [] );

		$breakpoints = is_array( $theme_breakpoints ) && ! empty( $theme_breakpoints )
			? $theme_breakpoints
			: $saved_breakpoints;

		if ( ! is_array( $breakpoints ) || empty( $breakpoints ) ) {
			$breakpoints = $defaults;
		} else {
			// Step 6.4: Merge provided data with defaults to ensure required keys exist.
			$breakpoints = array_replace_recursive( $defaults, $breakpoints );
		}

		// Step 6.5: Normalize and sanitize values.
		foreach ( [ 'mobile', 'tablet', 'desktop' ] as $device ) {
			$breakpoints[ $device ]['min']   = isset( $breakpoints[ $device ]['min'] )
				? absint( $breakpoints[ $device ]['min'] )
				: ( $defaults[ $device ]['min'] ?? 0 );
			$breakpoints[ $device ]['max']   = isset( $breakpoints[ $device ]['max'] )
				? absint( $breakpoints[ $device ]['max'] )
				: ( $defaults[ $device ]['max'] ?? 0 );
			$breakpoints[ $device ]['label'] = sanitize_text_field(
				$breakpoints[ $device ]['label'] ?? $defaults[ $device ]['label']
			);
		}

		self::$breakpoints_cache = apply_filters( '4wp_responsive_breakpoints', $breakpoints );
		return self::$breakpoints_cache;
	}

	/**
	 * Step 6.6: Read breakpoints defined in the active theme.json.
	 *
	 * @return array
	 */
	public static function get_theme_json_breakpoints() {
		$theme_json_path = wp_get_theme()->get_file_path( 'theme.json' );
		if ( ! $theme_json_path || ! file_exists( $theme_json_path ) ) {
			return [];
		}

		$theme_json = wp_json_file_decode( $theme_json_path, [ 'associative' => true ] );
		if ( ! is_array( $theme_json ) ) {
			return [];
		}

		$breakpoints = $theme_json['settings']['custom']['breakpoints'] ?? [];
		return is_array( $breakpoints ) ? $breakpoints : [];
	}

	/**
	 * Step 6.7: Check if breakpoints are defined in theme.json.
	 *
	 * @return bool
	 */
	public static function has_theme_json_breakpoints() {
		return ! empty( self::get_theme_json_breakpoints() );
	}

	/**
	 * Step 7: Resolve the CSS asset location and ensure it is generated.
	 *
	 * @return array{path:string,url:string,exists:bool}
	 */
	public static function get_css_asset() {
		// Step 7.1: Resolve the configured storage directory for the stylesheet.
		$directory = self::get_css_storage_directory();
		if ( '' === $directory ) {
			return [ 'path' => '', 'url' => '', 'exists' => false ];
		}

		// Step 7.2: Generate a hash based on the data that affects the stylesheet.
		$hash_data = [
			'breakpoints' => self::get_breakpoints(),
			'sizes'       => self::get_spacing_sizes(),
			'fallback'    => self::uses_fallback_spacing_sizes(),
			'fontSizes'   => self::get_font_sizes(),
			'fontFallback' => self::uses_fallback_font_sizes(),
			'prefix'      => 'forwp',
			'cssVersion'  => defined( 'FORWP_RESPONSIVE_VERSION' ) ? FORWP_RESPONSIVE_VERSION : 'dev',
		];
		$hash      = md5( wp_json_encode( $hash_data ) );
		$filename  = 'forwp-responsive-' . $hash . '.css';
		$path      = trailingslashit( $directory ) . $filename;
		$stored    = get_option( '4wp_responsive_css_hash', '' );
		$previous  = get_option( '4wp_responsive_css_filename', '' );

		// Step 7.3: Regenerate the CSS file when the hash changes or the file is missing.
		if ( $hash !== $stored || ! file_exists( $path ) ) {
			self::write_css_file( $path, self::build_responsive_css( $hash_data['breakpoints'], $hash_data['sizes'] ) );
			update_option( '4wp_responsive_css_hash', $hash, false );
			update_option( '4wp_responsive_css_filename', $filename, false );

			// Step 7.4: Remove the previous CSS file to keep the directory clean.
			if ( $previous && $previous !== $filename ) {
				$old_path = trailingslashit( $directory ) . $previous;
				if ( file_exists( $old_path ) ) {
					@unlink( $old_path );
				}
			}
		}

		// Step 7.5: Resolve a public URL for the stored file.
		$url = self::get_css_url_from_path( $path );

		return [
			'path'   => $path,
			'url'    => $url,
			'exists' => file_exists( $path ),
		];
	}

	/**
	 * Step 8: Build the responsive utility stylesheet content.
	 *
	 * @param array $breakpoints Breakpoint settings.
	 * @param array $sizes       Spacing sizes.
	 * @return string
	 */
	private static function build_responsive_css( $breakpoints, $sizes ) {
		// Step 8.1: Normalize size entries for safer generation.
		$normalized_sizes = [];
		foreach ( $sizes as $size ) {
			if ( empty( $size['slug'] ) ) {
				continue;
			}
			$normalized_sizes[] = [
				'slug' => sanitize_key( $size['slug'] ),
				'size' => $size['size'] ?? null,
			];
		}

		// Step 8.2: Prepare media queries for each device breakpoint.
		$media_queries = [
			'mobile'  => '(max-width: ' . absint( $breakpoints['mobile']['max'] ?? 599 ) . 'px)',
			'tablet'  => '(min-width: ' . absint( $breakpoints['tablet']['min'] ?? 600 ) . 'px) and (max-width: ' . absint( $breakpoints['tablet']['max'] ?? 1023 ) . 'px)',
			'desktop' => '(min-width: ' . absint( $breakpoints['desktop']['min'] ?? 1024 ) . 'px)',
		];

		$css = "/* 4WP Responsive utility classes */\n";

		// Step 8.3: Generate spacing utilities for each device.
		foreach ( $media_queries as $device => $query ) {
			$css .= '@media ' . $query . " {\n";
			$css .= self::build_spacing_rules( $device, $normalized_sizes );
			$css .= "}\n\n";
		}

		// Step 8.3.1: Generate editor preview spacing utilities (no media query).
		$css .= self::build_preview_spacing_rules( $normalized_sizes );

		// Step 8.4: Append visibility utilities for show/hide per device.
		$css .= self::build_visibility_rules( $breakpoints );

		// Step 8.5: Append typography utilities for font-size per device.
		$css .= self::build_typography_rules( self::get_font_sizes(), $breakpoints );

		// Step 8.5.1: Generate editor preview typography utilities (no media query).
		$css .= self::build_preview_typography_rules( self::get_font_sizes() );

		// Step 8.6: Append text alignment utilities for front and editor preview.
		$css .= self::build_text_align_rules( $breakpoints );

		return $css;
	}

	/**
	 * Step 9: Build spacing rules for a specific device.
	 *
	 * @param string $device Device key.
	 * @param array  $sizes  Normalized size array.
	 * @return string
	 */
	private static function build_spacing_rules( $device, $sizes ) {
		// Step 9.1: Define the mapping between attributes and CSS properties.
		$types = [
			'padding' => [ 'top', 'right', 'bottom', 'left' ],
			'margin'  => [ 'top', 'right', 'bottom', 'left' ],
		];

		$css = '';
		$preview_prefixes = self::get_preview_prefixes( $device );
		// Step 9.2: Add custom value rules that read from CSS variables.
		foreach ( $types as $type => $sides ) {
			foreach ( $sides as $side ) {
				$selector = '.has-forwp-' . $type . '-' . $side . '-' . $device . '-custom';
				$selectors = array_merge( [ $selector ], self::prefix_selectors( $selector, $preview_prefixes ) );
				$css .= sprintf(
					'%1$s{ %2$s-%3$s:var(--forwp-%2$s-%3$s-%4$s); }' . "\n",
					implode( ',', $selectors ),
					$type,
					$side,
					$device
				);
			}
		}

		// Step 9.3: Add preset-based rules for each spacing size.
		foreach ( $sizes as $size ) {
			$slug  = $size['slug'];
			$value = self::uses_fallback_spacing_sizes() && ! empty( $size['size'] )
				? $size['size']
				: 'var(--wp--preset--spacing--' . $slug . ')';

			foreach ( $types as $type => $sides ) {
				foreach ( $sides as $side ) {
					$selector = '.has-forwp-' . $type . '-' . $side . '-' . $device . '-' . $slug;
					$selectors = array_merge( [ $selector ], self::prefix_selectors( $selector, $preview_prefixes ) );
					$css .= sprintf(
						'%1$s{ %2$s-%3$s:%4$s; }' . "\n",
						implode( ',', $selectors ),
						$type,
						$side,
						$value
					);
				}
			}
		}

		return $css;
	}

	/**
	 * Step 9.1.1: Build spacing rules for editor preview classes (no media query).
	 *
	 * @param array $sizes Normalized size array.
	 * @return string
	 */
	private static function build_preview_spacing_rules( $sizes ) {
		$devices = [ 'mobile', 'tablet', 'desktop' ];
		$types   = [
			'padding' => [ 'top', 'right', 'bottom', 'left' ],
			'margin'  => [ 'top', 'right', 'bottom', 'left' ],
		];
		$css = '';

		foreach ( $devices as $device ) {
			$preview_prefixes = self::get_preview_prefixes( $device );
			foreach ( $types as $type => $sides ) {
				foreach ( $sides as $side ) {
					// Custom value selector.
					$selector  = '.has-forwp-' . $type . '-' . $side . '-' . $device . '-custom';
					$selectors = self::prefix_selectors( $selector, $preview_prefixes );
					$css      .= implode( ',', $selectors ) . '{ ' . $type . '-' . $side . ':var(--forwp-' . $type . '-' . $side . '-' . $device . '); }' . "\n";

					// Preset value selectors.
					foreach ( $sizes as $size ) {
						if ( empty( $size['slug'] ) ) {
							continue;
						}
						$slug      = $size['slug'];
						$selector  = '.has-forwp-' . $type . '-' . $side . '-' . $device . '-' . $slug;
						$selectors = self::prefix_selectors( $selector, $preview_prefixes );
						$css      .= implode( ',', $selectors ) . '{ ' . $type . '-' . $side . ':var(--wp--preset--spacing--' . $slug . '); }' . "\n";
					}
				}
			}
		}

		return $css;
	}

	/**
	 * Step 9.2: Build visibility rules for show/hide per breakpoint.
	 *
	 * @param array $breakpoints Breakpoint settings.
	 * @return string
	 */
	private static function build_visibility_rules( $breakpoints ) {
		$mobile_max  = absint( $breakpoints['mobile']['max'] ?? 599 );
		$tablet_min  = absint( $breakpoints['tablet']['min'] ?? 600 );
		$tablet_max  = absint( $breakpoints['tablet']['max'] ?? 1023 );
		$desktop_min = absint( $breakpoints['desktop']['min'] ?? 1024 );

		$css  = "/* Visibility utilities */\n";
		$css .= "@media (max-width: {$mobile_max}px) {\n";
		$css .= ".has-forwp-hide-mobile{display:none !important;}\n";
		$css .= "}\n";
		$css .= "@media (min-width: {$tablet_min}px) and (max-width: {$tablet_max}px) {\n";
		$css .= ".has-forwp-hide-tablet{display:none !important;}\n";
		$css .= "}\n";
		$css .= "@media (min-width: {$desktop_min}px) {\n";
		$css .= ".has-forwp-hide-desktop{display:none !important;}\n";
		$css .= "}\n";

		$css .= "@media (min-width: {$tablet_min}px) {\n";
		$css .= ".has-forwp-show-mobile{display:none !important;}\n";
		$css .= "}\n";
		$css .= "@media (max-width: {$mobile_max}px), (min-width: {$desktop_min}px) {\n";
		$css .= ".has-forwp-show-tablet{display:none !important;}\n";
		$css .= "}\n";
		$css .= "@media (max-width: {$tablet_max}px) {\n";
		$css .= ".has-forwp-show-desktop{display:none !important;}\n";
		$css .= "}\n\n";

		// Step 9.2.1: In editor previews, dim instead of hiding blocks.
		foreach ( [ 'mobile', 'tablet', 'desktop' ] as $device ) {
			$preview_prefixes = self::get_preview_prefixes( $device );
			$selector = '.has-forwp-hide-' . $device;
			$selectors = self::prefix_selectors( $selector, $preview_prefixes );
			$css .= implode( ',', $selectors ) . '{opacity:0.35;}\n';
		}

		// Step 9.2.2: Dim "show only" blocks on non-matching preview devices.
		$dim_map = [
			'mobile'  => [ 'tablet', 'desktop' ],
			'tablet'  => [ 'mobile', 'desktop' ],
			'desktop' => [ 'mobile', 'tablet' ],
		];
		foreach ( $dim_map as $preview_device => $hidden_devices ) {
			$preview_prefixes = self::get_preview_prefixes( $preview_device );
			foreach ( $hidden_devices as $hidden_device ) {
				$selector  = '.has-forwp-show-' . $hidden_device;
				$selectors = self::prefix_selectors( $selector, $preview_prefixes );
				$css      .= implode( ',', $selectors ) . '{opacity:0.35;}\n';
			}
		}

		$css .= "\n";

		return $css;
	}

	/**
	 * Step 9.3: Build typography rules for font sizes per breakpoint.
	 *
	 * @param array $font_sizes Font size presets.
	 * @param array $breakpoints Breakpoint settings.
	 * @return string
	 */
	private static function build_typography_rules( $font_sizes, $breakpoints ) {
		$mobile_max  = absint( $breakpoints['mobile']['max'] ?? 599 );
		$tablet_min  = absint( $breakpoints['tablet']['min'] ?? 600 );
		$tablet_max  = absint( $breakpoints['tablet']['max'] ?? 1023 );
		$desktop_min = absint( $breakpoints['desktop']['min'] ?? 1024 );

		$media_queries = [
			'mobile'  => '(max-width: ' . $mobile_max . 'px)',
			'tablet'  => '(min-width: ' . $tablet_min . 'px) and (max-width: ' . $tablet_max . 'px)',
			'desktop' => '(min-width: ' . $desktop_min . 'px)',
		];

		$css = "/* Typography utilities */\n";

		foreach ( $media_queries as $device => $query ) {
			$preview_prefixes = self::get_preview_prefixes( $device );
			$css .= '@media ' . $query . " {\n";
			$selector = '.has-forwp-font-size-' . $device . '-custom';
			$selectors = array_merge( [ $selector ], self::prefix_selectors( $selector, $preview_prefixes ) );
			$css .= implode( ',', $selectors ) . '{ font-size:var(--forwp-font-size-' . $device . '); }' . "\n";
			foreach ( $font_sizes as $size ) {
				if ( empty( $size['slug'] ) ) {
					continue;
				}
				$slug = $size['slug'];
				$selector = '.has-forwp-font-size-' . $device . '-' . $slug;
				$selectors = array_merge( [ $selector ], self::prefix_selectors( $selector, $preview_prefixes ) );
				$css .= implode( ',', $selectors ) . '{ font-size:var(--wp--preset--font-size--' . $slug . '); }' . "\n";
			}
			$css .= "}\n";
		}

		return $css;
	}

	/**
	 * Step 9.3.1: Build typography rules for editor preview classes (no media query).
	 *
	 * @param array $font_sizes Font size presets.
	 * @return string
	 */
	private static function build_preview_typography_rules( $font_sizes ) {
		$devices = [ 'mobile', 'tablet', 'desktop' ];
		$css     = '';

		foreach ( $devices as $device ) {
			$preview_prefixes = self::get_preview_prefixes( $device );

			// Custom value selector.
			$selector  = '.has-forwp-font-size-' . $device . '-custom';
			$selectors = self::prefix_selectors( $selector, $preview_prefixes );
			$css      .= implode( ',', $selectors ) . '{ font-size:var(--forwp-font-size-' . $device . '); }' . "\n";

			foreach ( $font_sizes as $size ) {
				if ( empty( $size['slug'] ) ) {
					continue;
				}
				$slug      = $size['slug'];
				$selector  = '.has-forwp-font-size-' . $device . '-' . $slug;
				$selectors = self::prefix_selectors( $selector, $preview_prefixes );
				$css      .= implode( ',', $selectors ) . '{ font-size:var(--wp--preset--font-size--' . $slug . '); }' . "\n";
			}
		}

		return $css;
	}

	/**
	 * Step 9.6: Build text alignment rules for breakpoints and editor preview.
	 *
	 * @param array $breakpoints Breakpoint settings.
	 * @return string
	 */
	private static function build_text_align_rules( $breakpoints ) {
		$mobile_max  = absint( $breakpoints['mobile']['max'] ?? 599 );
		$tablet_min  = absint( $breakpoints['tablet']['min'] ?? 600 );
		$tablet_max  = absint( $breakpoints['tablet']['max'] ?? 1023 );
		$desktop_min = absint( $breakpoints['desktop']['min'] ?? 1024 );

		$media_queries = [
			'mobile'  => '(max-width: ' . $mobile_max . 'px)',
			'tablet'  => '(min-width: ' . $tablet_min . 'px) and (max-width: ' . $tablet_max . 'px)',
			'desktop' => '(min-width: ' . $desktop_min . 'px)',
		];

		$css = "/* Text alignment utilities */\n";
		foreach ( $media_queries as $device => $query ) {
			$css .= '@media ' . $query . " {\n";
			foreach ( self::$text_align_values as $align ) {
				$css .= '.has-forwp-text-align-' . $device . '-' . $align . '{ text-align:' . $align . ' !important; }' . "\n";
				$css .= '.has-forwp-text-align-' . $device . '-' . $align . ' > *{ text-align:inherit !important; }' . "\n";
			}
			$css .= "}\n";
		}

		// Step 9.6.1: Add editor preview selectors without media queries.
		foreach ( [ 'mobile', 'tablet', 'desktop' ] as $device ) {
			$preview_prefixes = self::get_preview_prefixes( $device );
			foreach ( self::$text_align_values as $align ) {
				$selector  = '.has-forwp-text-align-' . $device . '-' . $align;
				$selectors = self::prefix_selectors( $selector, $preview_prefixes );
				$css      .= implode( ',', $selectors ) . '{ text-align:' . $align . ' !important; }' . "\n";
				$child_selectors = self::prefix_selectors( $selector . ' > *', $preview_prefixes );
				$css      .= implode( ',', $child_selectors ) . '{ text-align:inherit !important; }' . "\n";
			}
		}

		return $css;
	}

	/**
	 * Step 9.4: Build preview prefixes for editor device classes.
	 *
	 * @param string $device Device key.
	 * @return string[]
	 */
	private static function get_preview_prefixes( $device ) {
		$class = 'is-' . $device . '-preview';
		return [
			'.' . $class . ' ',
			'body.' . $class . ' ',
			'html.' . $class . ' ',
			'.editor-styles-wrapper.' . $class . ' ',
			'.' . $class . ' .editor-styles-wrapper ',
			'body.' . $class . ' .editor-styles-wrapper ',
		];
	}

	/**
	 * Step 9.5: Prefix selectors with editor preview classes.
	 *
	 * @param string   $selector Base selector.
	 * @param string[] $prefixes Prefixes.
	 * @return string[]
	 */
	private static function prefix_selectors( $selector, $prefixes ) {
		$results = [];
		foreach ( $prefixes as $prefix ) {
			$results[] = $prefix . $selector;
		}
		return $results;
	}

	/**
	 * Step 10: Write the generated CSS to disk.
	 *
	 * @param string $path Target file path.
	 * @param string $css  Stylesheet contents.
	 * @return void
	 */
	private static function write_css_file( $path, $css ) {
		// Step 10.1: Ensure the target directory exists.
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}

		// Step 10.2: Attempt to write the file only when the directory is writable.
		if ( is_writable( $directory ) ) {
			file_put_contents( $path, $css );
		}
	}

	/**
	 * Step 11: Resolve the default storage path for generated CSS.
	 *
	 * @return string
	 */
	public static function get_default_css_storage_path() {
		// Step 11.1: Use the uploads directory by default.
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return '';
		}

		return wp_normalize_path( trailingslashit( $uploads['basedir'] ) . '4wp-responsive' );
	}

	/**
	 * Step 12: Resolve the storage directory from settings or defaults.
	 *
	 * @return string
	 */
	private static function get_css_storage_directory() {
		// Step 12.1: Respect custom admin setting when provided.
		$custom_path = get_option( '4wp_responsive_css_storage_path', '' );
		if ( $custom_path ) {
			return wp_normalize_path( untrailingslashit( $custom_path ) );
		}

		// Step 12.2: Fall back to the uploads directory.
		return self::get_default_css_storage_path();
	}

	/**
	 * Step 13: Resolve a public URL for a stored CSS file.
	 *
	 * @param string $path Absolute file path.
	 * @return string
	 */
	private static function get_css_url_from_path( $path ) {
		// Step 13.1: Allow consumers to override URL resolution.
		$url_override = apply_filters( '4wp_responsive_css_base_url', '' );
		if ( $url_override ) {
			return set_url_scheme( trailingslashit( $url_override ) . basename( $path ) );
		}

		// Step 13.2: Map uploads directory paths to the uploads URL.
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] ) ) {
			$normalized = wp_normalize_path( $path );
			$basedir    = wp_normalize_path( $uploads['basedir'] );
			if ( 0 === strpos( $normalized, $basedir ) ) {
				$relative = ltrim( substr( $normalized, strlen( $basedir ) ), '/' );
				return set_url_scheme( trailingslashit( $uploads['baseurl'] ) . $relative );
			}
		}

		// Step 13.3: Map plugin directory paths to the plugin URL.
		$plugin_dir = wp_normalize_path( FORWP_RESPONSIVE_PLUGIN_DIR );
		if ( 0 === strpos( wp_normalize_path( $path ), $plugin_dir ) ) {
			$relative = ltrim( substr( wp_normalize_path( $path ), strlen( $plugin_dir ) ), '/' );
			return set_url_scheme( trailingslashit( FORWP_RESPONSIVE_PLUGIN_URL ) . $relative );
		}

		// Step 13.4: Attempt to map paths under WP_CONTENT_DIR to content_url.
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$content_dir = wp_normalize_path( WP_CONTENT_DIR );
			$normalized  = wp_normalize_path( $path );
			if ( 0 === strpos( $normalized, $content_dir ) ) {
				$relative = ltrim( substr( $normalized, strlen( $content_dir ) ), '/' );
				return set_url_scheme( trailingslashit( content_url() ) . $relative );
			}
		}

		return '';
	}
}

