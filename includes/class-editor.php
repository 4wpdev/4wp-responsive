<?php
namespace ForWP\Responsive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Editor {
	/**
	 * Step 1: Register editor hooks.
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_assets' ] );
		// Step 1.2: Load hide-override inside editor iframe (WP 6.3+ canvas is iframed; enqueue_block_editor_assets does not inject there).
		add_action( 'enqueue_block_assets', [ __CLASS__, 'enqueue_iframe_override' ], 20 );
	}

	/**
	 * Step 2: Enqueue editor scripts and styles.
	 */
	public static function enqueue_assets() {
		// Step 2.1: Enqueue the editor script if the build file exists.
		$script_path = FORWP_RESPONSIVE_PLUGIN_DIR . 'build/index.js';
		$script_url  = set_url_scheme( FORWP_RESPONSIVE_PLUGIN_URL . 'build/index.js' );

		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'forwp-responsive-editor',
				$script_url,
				[ 'wp-element', 'wp-hooks', 'wp-components', 'wp-block-editor', 'wp-compose', 'wp-i18n', 'wp-data', 'wp-blocks' ],
				filemtime( $script_path ),
				true
			);

			// Step 2.2: Provide breakpoint and spacing data to the editor UI.
			wp_localize_script(
				'forwp-responsive-editor',
				'forwpResponsive',
				[
					'breakpoints'      => Plugin::get_breakpoints(),
					'spacingSizes'     => Plugin::get_spacing_sizes(),
					'spacingFallback'  => Plugin::uses_fallback_spacing_sizes(),
					'fontSizes'        => Plugin::get_font_sizes(),
					'fontFallback'     => Plugin::uses_fallback_font_sizes(),
				]
			);
		}

		// Step 2.3: Enqueue the generated stylesheet for accurate editor previews.
		$css_asset = Plugin::get_css_asset();
		if ( ! empty( $css_asset['url'] ) && $css_asset['exists'] ) {
			wp_enqueue_style(
				'forwp-responsive-utilities',
				$css_asset['url'],
				[],
				filemtime( $css_asset['path'] )
			);
		}

		// Step 2.3.1: In editor only, override hide classes so blocks stay visible (semi-transparent) instead of display:none.
		$editor_hide_override = 'body.wp-admin .editor-styles-wrapper .has-forwp-hide-mobile,'
			. 'body.wp-admin .editor-styles-wrapper .has-forwp-hide-tablet,'
			. 'body.wp-admin .editor-styles-wrapper .has-forwp-hide-desktop,'
			. 'body.wp-admin .block-editor-block-list__block.has-forwp-hide-mobile,'
			. 'body.wp-admin .block-editor-block-list__block.has-forwp-hide-tablet,'
			. 'body.wp-admin .block-editor-block-list__block.has-forwp-hide-desktop'
			. '{display:revert !important;visibility:visible !important;opacity:0.35 !important;pointer-events:auto !important;}';
		wp_add_inline_style( 'forwp-responsive-utilities', $editor_hide_override );

		// Step 2.4: Dotted slider by default; show number + unit when user clicks customization.
		$ui_deps = ( ! empty( $css_asset['url'] ) && $css_asset['exists'] ) ? [ 'forwp-responsive-utilities' ] : [];
		wp_register_style( 'forwp-responsive-editor-ui', false, $ui_deps, FORWP_RESPONSIVE_VERSION );
		wp_enqueue_style( 'forwp-responsive-editor-ui' );
		wp_add_inline_style(
			'forwp-responsive-editor-ui',
			/* In editor: override hide classes so blocks stay visible (semi-transparent). */
			$editor_hide_override . ' '
			. '.forwp-responsive-panel .components-range-control__wrapper{grid-template-columns:1fr !important;}'
			. '.forwp-responsive-panel .components-range-control__slider{margin:0 !important;}'
			/* Dotted (dashed) slider track */
			. '.forwp-responsive-panel .components-range-control__slider::-webkit-slider-runnable-track,'
			. '.forwp-responsive-panel .spacing-sizes-control__custom-value-range::-webkit-slider-runnable-track{'
			. 'height:6px;border-radius:3px;'
			. 'background:repeating-linear-gradient(90deg,#c3c4c7 0,#c3c4c7 4px,transparent 4px,transparent 8px) !important;}'
			. '.forwp-responsive-panel .components-range-control__slider::-moz-range-track,'
			. '.forwp-responsive-panel .spacing-sizes-control__custom-value-range::-moz-range-track{'
			. 'height:6px;border-radius:3px;'
			. 'background:repeating-linear-gradient(90deg,#c3c4c7 0,#c3c4c7 4px,transparent 4px,transparent 8px) !important;}'
		);
	}

	/**
	 * Enqueue style so it loads inside the editor iframe (enqueue_block_assets runs for iframe).
	 * Only in admin; selector uses .editor-styles-wrapper so it applies in iframe canvas.
	 */
	public static function enqueue_iframe_override() {
		if ( ! is_admin() ) {
			return;
		}
		$override = '.editor-styles-wrapper .has-forwp-hide-mobile,'
			. '.editor-styles-wrapper .has-forwp-hide-tablet,'
			. '.editor-styles-wrapper .has-forwp-hide-desktop,'
			. '.editor-styles-wrapper .block-editor-block-list__block.has-forwp-hide-mobile,'
			. '.editor-styles-wrapper .block-editor-block-list__block.has-forwp-hide-tablet,'
			. '.editor-styles-wrapper .block-editor-block-list__block.has-forwp-hide-desktop'
			. '{display:revert !important;visibility:visible !important;opacity:0.35 !important;pointer-events:auto !important;}';
		wp_register_style( 'forwp-responsive-iframe-hide-override', false, [] );
		wp_enqueue_style( 'forwp-responsive-iframe-hide-override' );
		wp_add_inline_style( 'forwp-responsive-iframe-hide-override', $override );
	}
}

