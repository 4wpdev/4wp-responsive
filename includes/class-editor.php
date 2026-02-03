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
		// Step 1.1: Enqueue editor assets where block controls are rendered.
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_assets' ] );
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

		// Step 2.4: Hide numeric input fields inside our responsive panel (slider-only UI).
		wp_register_style( 'forwp-responsive-editor-ui', false, [], FORWP_RESPONSIVE_VERSION );
		wp_enqueue_style( 'forwp-responsive-editor-ui' );
		wp_add_inline_style(
			'forwp-responsive-editor-ui',
			'.forwp-responsive-panel .components-range-control__number,'
			. '.forwp-responsive-panel .components-input-control__container,'
			. '.forwp-responsive-panel input[type="number"]{display:none !important;}'
			. '.forwp-responsive-panel .components-unit-control,'
			. '.forwp-responsive-panel .components-unit-control__select,'
			. '.forwp-responsive-panel .components-unit-control__button{display:none !important;}'
			. '.forwp-responsive-panel .components-range-control__wrapper{grid-template-columns:1fr !important;}'
			. '.forwp-responsive-panel .components-range-control__slider{margin:0 !important;}'
		);
	}
}

