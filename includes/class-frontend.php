<?php
namespace ForWP\Responsive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Frontend {
	/**
	 * Step 1: Register frontend hooks.
	 */
	public static function init() {
		// Step 1.1: Enqueue generated utilities on the front end.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

		// Step 1.2: Add responsive utility classes during block rendering.
		add_filter( 'render_block', [ __CLASS__, 'add_responsive_classes_to_block' ], 10, 2 );
	}

	/**
	 * Step 2: Enqueue frontend assets.
	 */
	public static function enqueue_assets() {
		// Step 2.1: Load the generated CSS file if it exists.
		$css_asset = Plugin::get_css_asset();
		if ( ! empty( $css_asset['url'] ) && $css_asset['exists'] ) {
			wp_enqueue_style(
				'forwp-responsive-utilities',
				$css_asset['url'],
				[],
				filemtime( $css_asset['path'] )
			);
		}

		// Step 2.2: Inject a frontend-only fix to guarantee hide rules apply.
		$hide_fix = '.has-forwp-hide-mobile,.has-forwp-hide-tablet,.has-forwp-hide-desktop{display:none !important;}';
		if ( ! is_admin() ) {
			wp_add_inline_style( 'forwp-responsive-utilities', $hide_fix );
		}
	}

	/**
	 * Step 3: Add responsive classes to block output.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string
	 */
	public static function add_responsive_classes_to_block( $block_content, $block ) {
		if ( empty( $block['attrs'] ) || empty( $block_content ) ) {
			return $block_content;
		}

		$result = self::get_responsive_classes_and_styles( $block['attrs'] );
		if ( empty( $result['classes'] ) && empty( $result['styles'] ) ) {
			return $block_content;
		}

		// Step 3.1: Inject utility classes into the first block wrapper element.
		$class_string = ! empty( $result['classes'] ) ? implode( ' ', $result['classes'] ) : '';
		$style_string = ! empty( $result['styles'] ) ? self::build_style_attribute( $result['styles'] ) : '';

		return preg_replace_callback(
			'/<([a-z]+)([^>]*)>/i',
			static function ( $matches ) use ( $class_string, $style_string ) {
				$tag_name  = $matches[1];
				$tag_attrs = $matches[2];

				if ( $class_string && preg_match( '/class=["\']([^"\']*)["\']/i', $tag_attrs, $class_matches ) ) {
					$existing_classes = $class_matches[1];
					$new_classes      = trim( $existing_classes . ' ' . $class_string );
					$tag_attrs        = preg_replace(
						'/class=["\']' . preg_quote( $existing_classes, '/' ) . '["\']/i',
						'class="' . esc_attr( $new_classes ) . '"',
						$tag_attrs
					);
				} elseif ( $class_string ) {
					$tag_attrs = ( '' !== trim( $tag_attrs ) ? $tag_attrs . ' ' : '' ) . 'class="' . esc_attr( $class_string ) . '"';
				}

				if ( $style_string ) {
					if ( preg_match( '/style=["\']([^"\']*)["\']/i', $tag_attrs, $style_matches ) ) {
						$existing_style = trim( $style_matches[1] );
						$merged_style   = trim( $existing_style . ' ' . $style_string );
						$tag_attrs      = preg_replace(
							'/style=["\']' . preg_quote( $existing_style, '/' ) . '["\']/i',
							'style="' . esc_attr( $merged_style ) . '"',
							$tag_attrs
						);
					} else {
						$tag_attrs = ( '' !== trim( $tag_attrs ) ? $tag_attrs . ' ' : '' ) . 'style="' . esc_attr( $style_string ) . '"';
					}
				}

				return '<' . $tag_name . ( '' !== trim( $tag_attrs ) ? ' ' . trim( $tag_attrs ) : '' ) . '>';
			},
			$block_content,
			1
		);
	}

	/**
	 * Step 4: Map responsive attributes to utility class names and style variables.
	 *
	 * @param array $attributes Block attributes.
	 * @return array{classes:string[],styles:array}
	 */
	private static function get_responsive_classes_and_styles( $attributes ) {
		$types   = [ 'Padding' => 'padding', 'Margin' => 'margin' ];
		$sides   = [ 'Top' => 'top', 'Right' => 'right', 'Bottom' => 'bottom', 'Left' => 'left' ];
		$devices = [ 'Mobile' => 'mobile', 'Tablet' => 'tablet', 'Desktop' => 'desktop' ];
		$classes = [];
		$styles  = [];

		// Step 4.1: Build a lookup of preset slugs.
		$preset_slugs = array_map(
			static function ( $size ) {
				return $size['slug'] ?? '';
			},
			Plugin::get_spacing_sizes()
		);
		$preset_slugs = array_filter( $preset_slugs );
		$font_slugs = array_map(
			static function ( $size ) {
				return $size['slug'] ?? '';
			},
			Plugin::get_font_sizes()
		);
		$font_slugs = array_filter( $font_slugs );
		$text_align_values = [ 'left', 'center', 'right', 'justify' ];

		// Step 4.2: Translate each attribute into a CSS class or variable when a value is provided.
		foreach ( $types as $type_key => $type_slug ) {
			foreach ( $sides as $side_key => $side_slug ) {
				foreach ( $devices as $device_key => $device_slug ) {
					$attr_key = 'responsive' . $type_key . $side_key . $device_key;
					if ( empty( $attributes[ $attr_key ] ) ) {
						continue;
					}

					$raw_value = trim( (string) $attributes[ $attr_key ] );
					if ( '' === $raw_value ) {
						continue;
					}

					$size_slug = sanitize_key( $raw_value );
					if ( in_array( $size_slug, $preset_slugs, true ) ) {
						$classes[] = 'has-forwp-' . $type_slug . '-' . $side_slug . '-' . $device_slug . '-' . $size_slug;
					} else {
						$classes[] = 'has-forwp-' . $type_slug . '-' . $side_slug . '-' . $device_slug . '-custom';
						$styles[]  = '--forwp-' . $type_slug . '-' . $side_slug . '-' . $device_slug . ':' . self::sanitize_custom_value( $raw_value );
					}
				}
			}
		}

		// Step 4.4: Apply responsive font-size classes and custom vars.
		foreach ( $devices as $device_key => $device_slug ) {
			$attr_key = 'responsiveFontSize' . $device_key;
			if ( empty( $attributes[ $attr_key ] ) ) {
				continue;
			}

			$raw_value = trim( (string) $attributes[ $attr_key ] );
			if ( '' === $raw_value ) {
				continue;
			}

			$size_slug = sanitize_key( $raw_value );
			if ( in_array( $size_slug, $font_slugs, true ) ) {
				$classes[] = 'has-forwp-font-size-' . $device_slug . '-' . $size_slug;
			} else {
				$classes[] = 'has-forwp-font-size-' . $device_slug . '-custom';
				$styles[]  = '--forwp-font-size-' . $device_slug . ':' . self::sanitize_custom_value( $raw_value );
			}
		}

		// Step 4.5: Apply responsive text alignment classes.
		foreach ( $devices as $device_key => $device_slug ) {
			$attr_key = 'responsiveTextAlign' . $device_key;
			$hide_key = 'responsiveHide' . $device_key;
			if ( ! empty( $attributes[ $hide_key ] ) ) {
				continue;
			}
			if ( empty( $attributes[ $attr_key ] ) ) {
				continue;
			}

			$align = sanitize_key( $attributes[ $attr_key ] );
			if ( in_array( $align, $text_align_values, true ) ) {
				$classes[] = 'has-forwp-text-align-' . $device_slug . '-' . $align;
			}
		}

		// Step 4.3: Translate visibility attributes into utility classes.
		$visibility_map = [
			'responsiveHideMobile'   => 'has-forwp-hide-mobile',
			'responsiveHideTablet'   => 'has-forwp-hide-tablet',
			'responsiveHideDesktop'  => 'has-forwp-hide-desktop',
			'responsiveShowMobile'   => 'has-forwp-show-mobile',
			'responsiveShowTablet'   => 'has-forwp-show-tablet',
			'responsiveShowDesktop'  => 'has-forwp-show-desktop',
		];

		foreach ( $visibility_map as $attr_key => $class_name ) {
			if ( ! empty( $attributes[ $attr_key ] ) ) {
				$classes[] = $class_name;
			}
		}

		return [
			'classes' => $classes,
			'styles'  => $styles,
		];
	}

	/**
	 * Step 5: Build a style attribute string from CSS variable fragments.
	 *
	 * @param array $styles CSS variable fragments.
	 * @return string
	 */
	private static function build_style_attribute( $styles ) {
		$styles = array_filter( array_map( 'trim', $styles ) );
		if ( empty( $styles ) ) {
			return '';
		}

		return implode( ';', $styles ) . ';';
	}

	/**
	 * Step 6: Sanitize custom spacing values and ensure a unit.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function sanitize_custom_value( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		// Step 6.1: If the value is numeric, default to px.
		if ( is_numeric( $value ) ) {
			return $value . 'px';
		}

		// Step 6.2: Allow basic units and percentages.
		if ( preg_match( '/^-?\d*\.?\d+(px|%|em|rem|vh|vw)$/i', $value ) ) {
			return $value;
		}

		return '';
	}
}

