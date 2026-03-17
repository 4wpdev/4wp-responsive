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
		// Hide/show rules are in the generated CSS with correct media queries (mobile/tablet/desktop). Do not add a global display:none here.
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

		// Step 3.1: Inject utility classes into the block root (first tag with wp-block-* or first block-level tag).
		$class_string = ! empty( $result['classes'] ) ? implode( ' ', $result['classes'] ) : '';
		$style_string = ! empty( $result['styles'] ) ? self::build_style_attribute( $result['styles'] ) : '';

		$inject = static function ( $matches ) use ( $class_string, $style_string, $result ) {
			$tag_name  = $matches[1];
			$tag_attrs = $matches[2];
			// Skip script, style, template.
			if ( in_array( strtolower( $tag_name ), [ 'script', 'style', 'template' ], true ) ) {
				return $matches[0];
			}
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
					// Remove default padding/margin from inline style so our responsive rules (with !important) are not overridden by concatenation.
					$existing_style = self::strip_responsive_overridden_properties( $existing_style, $result );
					$merged_style   = trim( ( $existing_style ? $existing_style . '; ' : '' ) . $style_string );
					$tag_attrs      = preg_replace(
						'/style=["\']' . preg_quote( $style_matches[1], '/' ) . '["\']/i',
						'style="' . esc_attr( $merged_style ) . '"',
						$tag_attrs
					);
				} else {
					$tag_attrs = ( '' !== trim( $tag_attrs ) ? $tag_attrs . ' ' : '' ) . 'style="' . esc_attr( $style_string ) . '"';
				}
			}
			return '<' . $tag_name . ( '' !== trim( $tag_attrs ) ? ' ' . trim( $tag_attrs ) : '' ) . '>';
		};

		// Prefer first tag that has wp-block- in class (block root); otherwise first block-level tag.
		if ( preg_match( '/<([a-z][a-z0-9]*)([^>]*class=["\'][^"\']*wp-block-[^"\']*["\'][^>]*)>/i', $block_content, $m ) ) {
			return preg_replace_callback(
				'/<([a-z][a-z0-9]*)([^>]*class=["\'][^"\']*wp-block-[^"\']*["\'][^>]*)>/i',
				$inject,
				$block_content,
				1
			);
		}
		return preg_replace_callback(
			'/<([a-z][a-z0-9]*)([^>]*)>/i',
			$inject,
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

		// Step 4.3.1: Reverse order (flex column-reverse) per device for Columns/Group.
		$reverse_map = [
			'responsiveReverseMobile'  => 'has-forwp-reverse-mobile',
			'responsiveReverseTablet'   => 'has-forwp-reverse-tablet',
			'responsiveReverseDesktop' => 'has-forwp-reverse-desktop',
		];
		foreach ( $reverse_map as $attr_key => $class_name ) {
			if ( ! empty( $attributes[ $attr_key ] ) ) {
				$classes[] = $class_name;
			}
		}

		// Step 4.5a: Responsive border radius per corner (top-left, top-right, bottom-right, bottom-left).
		$radius_corners = [
			'TopLeft'     => 'top-left',
			'TopRight'    => 'top-right',
			'BottomRight' => 'bottom-right',
			'BottomLeft'  => 'bottom-left',
		];
		foreach ( $devices as $device_key => $device_slug ) {
			foreach ( $radius_corners as $corner_key => $corner_slug ) {
				$attr_key = 'responsiveBorderRadius' . $corner_key . $device_key;
				if ( empty( $attributes[ $attr_key ] ) ) {
					continue;
				}
				$raw_value = trim( (string) $attributes[ $attr_key ] );
				if ( '' === $raw_value ) {
					continue;
				}
				$classes[] = 'has-forwp-border-radius-' . $corner_slug . '-' . $device_slug;
				$styles[]  = '--forwp-border-radius-' . $corner_slug . '-' . $device_slug . ':' . self::sanitize_length_value( $raw_value );
			}
		}

		// Step 4.5b: Responsive min height (Cover, Group, etc.).
		foreach ( $devices as $device_key => $device_slug ) {
			$attr_key = 'responsiveMinHeight' . $device_key;
			if ( empty( $attributes[ $attr_key ] ) ) {
				continue;
			}
			$raw_value = trim( (string) $attributes[ $attr_key ] );
			if ( '' === $raw_value ) {
				continue;
			}
			$classes[] = 'has-forwp-min-height-' . $device_slug;
			$styles[]  = '--forwp-min-height-' . $device_slug . ':' . self::sanitize_length_value( $raw_value );
		}

		// Step 4.5c: Responsive max height (cover, image).
		foreach ( $devices as $device_key => $device_slug ) {
			$attr_key = 'responsiveMaxHeight' . $device_key;
			if ( empty( $attributes[ $attr_key ] ) ) {
				continue;
			}
			$raw_value = trim( (string) $attributes[ $attr_key ] );
			if ( '' === $raw_value ) {
				continue;
			}
			$classes[] = 'has-forwp-max-height-' . $device_slug;
			$styles[]  = '--forwp-max-height-' . $device_slug . ':' . self::sanitize_length_value( $raw_value );
		}

		// Step 4.5d: Responsive line-height (headings, paragraph, blocks with typography).
		foreach ( $devices as $device_key => $device_slug ) {
			$attr_key = 'responsiveLineHeight' . $device_key;
			if ( empty( $attributes[ $attr_key ] ) ) {
				continue;
			}
			$raw_value = trim( (string) $attributes[ $attr_key ] );
			if ( '' === $raw_value ) {
				continue;
			}
			$classes[] = 'has-forwp-line-height-' . $device_slug . '-custom';
			$styles[]  = '--forwp-line-height-' . $device_slug . ':' . self::sanitize_line_height_value( $raw_value );
		}

		return [
			'classes' => $classes,
			'styles'  => $styles,
		];
	}

	/**
	 * Remove from inline style only properties that we fully control per viewport via CSS vars.
	 * Do NOT strip padding/margin: block default (desktop) stays in inline style; our responsive
	 * rules override only in the matching media query, so desktop values are preserved.
	 *
	 * @param string $existing_style Inline style string.
	 * @param array  $result        Result from get_responsive_classes_and_styles (classes, styles).
	 * @return string Filtered style string.
	 */
	private static function strip_responsive_overridden_properties( $existing_style, $result ) {
		$classes   = isset( $result['classes'] ) ? implode( ' ', $result['classes'] ) : '';
		$styles    = isset( $result['styles'] ) ? implode( ' ', $result['styles'] ) : '';
		$to_strip  = [];
		$props     = [];
		// Do not strip min-height/max-height: core value stays in inline for desktop; responsive overrides only in media queries.
		if ( strpos( $classes, 'has-forwp-line-height-' ) !== false || strpos( $styles, '--forwp-line-height-' ) !== false ) {
			$props[] = 'line-height';
		}
		foreach ( $props as $prop ) {
			$to_strip[] = $prop;
		}
		// Strip per-corner border radius when we have any responsive border radius override.
		if ( strpos( $classes, 'has-forwp-border-radius-' ) !== false || strpos( $styles, '--forwp-border-radius-' ) !== false ) {
			$to_strip[] = 'border-top-left-radius';
			$to_strip[] = 'border-top-right-radius';
			$to_strip[] = 'border-bottom-right-radius';
			$to_strip[] = 'border-bottom-left-radius';
		}
		if ( empty( $to_strip ) ) {
			return $existing_style;
		}
		foreach ( $to_strip as $prop ) {
			$existing_style = preg_replace( '/\s*' . preg_quote( $prop, '/' ) . '\s*:\s*[^;]+;?\s*/i', ' ', $existing_style );
		}
		return trim( preg_replace( '/\s+/', ' ', $existing_style ), " \t\n\r;" );
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

	/**
	 * Sanitize length value for border-radius and min-height (same rules as spacing).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function sanitize_length_value( $value ) {
		return self::sanitize_custom_value( $value );
	}

	/**
	 * Sanitize line-height value (unitless or with unit: px, em, rem, %).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function sanitize_line_height_value( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( is_numeric( $value ) ) {
			return $value;
		}
		if ( preg_match( '/^-?\d*\.?\d+(px|%|em|rem)?$/i', $value ) ) {
			return $value;
		}
		return '';
	}
}

