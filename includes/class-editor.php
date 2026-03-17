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

		// Step 2.3.1: In editor, keep hide/show blocks visible; gray only when body has forwp-preview-* (set by JS) and block is hidden on that device.
		$editor_keep_visible = 'body.wp-admin .editor-styles-wrapper .has-forwp-hide-mobile,'
			. 'body.wp-admin .editor-styles-wrapper .has-forwp-hide-tablet,'
			. 'body.wp-admin .editor-styles-wrapper .has-forwp-hide-desktop,'
			. 'body.wp-admin .editor-styles-wrapper .has-forwp-show-mobile,'
			. 'body.wp-admin .editor-styles-wrapper .has-forwp-show-tablet,'
			. 'body.wp-admin .editor-styles-wrapper .has-forwp-show-desktop,'
			. 'body.wp-admin .block-editor-block-list__block.has-forwp-hide-mobile,'
			. 'body.wp-admin .block-editor-block-list__block.has-forwp-hide-tablet,'
			. 'body.wp-admin .block-editor-block-list__block.has-forwp-hide-desktop,'
			. 'body.wp-admin .block-editor-block-list__block.has-forwp-show-mobile,'
			. 'body.wp-admin .block-editor-block-list__block.has-forwp-show-tablet,'
			. 'body.wp-admin .block-editor-block-list__block.has-forwp-show-desktop'
			. '{display:revert !important;visibility:visible !important;pointer-events:auto !important;}';
		$editor_gray_mobile  = 'body.wp-admin.forwp-preview-mobile .block-editor-block-list__block.has-forwp-hide-mobile,'
			. 'body.wp-admin.forwp-preview-mobile .block-editor-block-list__block.has-forwp-show-tablet,'
			. 'body.wp-admin.forwp-preview-mobile .block-editor-block-list__block.has-forwp-show-desktop{opacity:0.35 !important;}';
		$editor_gray_tablet  = 'body.wp-admin.forwp-preview-tablet .block-editor-block-list__block.has-forwp-hide-tablet,'
			. 'body.wp-admin.forwp-preview-tablet .block-editor-block-list__block.has-forwp-show-mobile,'
			. 'body.wp-admin.forwp-preview-tablet .block-editor-block-list__block.has-forwp-show-desktop{opacity:0.35 !important;}';
		$editor_gray_desktop = 'body.wp-admin.forwp-preview-desktop .block-editor-block-list__block.has-forwp-hide-desktop,'
			. 'body.wp-admin.forwp-preview-desktop .block-editor-block-list__block.has-forwp-show-mobile,'
			. 'body.wp-admin.forwp-preview-desktop .block-editor-block-list__block.has-forwp-show-tablet{opacity:0.35 !important;}';
		wp_add_inline_style( 'forwp-responsive-utilities', $editor_keep_visible . $editor_gray_mobile . $editor_gray_tablet . $editor_gray_desktop );

		// Step 2.4: Dotted slider by default; show number + unit when user clicks customization.
		$ui_deps = ( ! empty( $css_asset['url'] ) && $css_asset['exists'] ) ? [ 'forwp-responsive-utilities' ] : [];
		wp_register_style( 'forwp-responsive-editor-ui', false, $ui_deps, FORWP_RESPONSIVE_VERSION );
		wp_enqueue_style( 'forwp-responsive-editor-ui' );
		wp_add_inline_style(
			'forwp-responsive-editor-ui',
			/* In editor: keep hide/show blocks visible; gray only when device matches (see forwp-responsive-utilities). */
			$editor_keep_visible . ' '
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
			/* Line-height panel: highlight when device matches editor view (top-right) */
			. '.forwp-typography-line-height-wrapper.forwp-lh-synced-with-editor{'
			. 'border-left:3px solid var(--wp-admin-theme-color,#0073aa) !important;'
			. 'padding-left:10px !important;'
			. 'margin-left:-2px !important;'
			. 'background:color-mix(in srgb, var(--wp-admin-theme-color,#0073aa) 8%, transparent) !important;}'
			/* Badge on block when hidden on other devices */
			. '.block-editor-block-list__block.forwp-has-visibility-badge{position:relative;}'
			. '.block-editor-block-list__block.forwp-has-visibility-badge::after{'
			. 'content:"";position:absolute;top:4px;right:4px;width:20px;height:20px;'
			. 'background:var(--wp-admin-theme-color,#0073aa);border-radius:50%;'
			. 'box-shadow:0 0 0 1px rgba(255,255,255,.8);z-index:5;'
			. 'background-image:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'white\'%3E%3Cpath d=\'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z\'/%3E%3C/svg%3E");'
			. 'background-size:12px 12px;background-repeat:no-repeat;background-position:center;}'
		);
	}

	/**
	 * Enqueue style so it loads inside the editor iframe (enqueue_block_assets runs for iframe).
	 * Gray only when the current editor device is one where the block is hidden (body class set by JS).
	 */
	public static function enqueue_iframe_override() {
		if ( ! is_admin() ) {
			return;
		}
		// Keep hidden blocks visible in editor (override display:none from frontend CSS).
		$base = '.editor-styles-wrapper .has-forwp-hide-mobile,.editor-styles-wrapper .has-forwp-hide-tablet,.editor-styles-wrapper .has-forwp-hide-desktop,'
			. '.editor-styles-wrapper .has-forwp-show-mobile,.editor-styles-wrapper .has-forwp-show-tablet,.editor-styles-wrapper .has-forwp-show-desktop,'
			. '.editor-styles-wrapper .block-editor-block-list__block.has-forwp-hide-mobile,.editor-styles-wrapper .block-editor-block-list__block.has-forwp-hide-tablet,.editor-styles-wrapper .block-editor-block-list__block.has-forwp-hide-desktop,'
			. '.editor-styles-wrapper .block-editor-block-list__block.has-forwp-show-mobile,.editor-styles-wrapper .block-editor-block-list__block.has-forwp-show-tablet,.editor-styles-wrapper .block-editor-block-list__block.has-forwp-show-desktop'
			. '{display:revert !important;visibility:visible !important;pointer-events:auto !important;}';
		$gray = '%1$s .editor-styles-wrapper .block-editor-block-list__block%2$s{opacity:0.35 !important;}';
		$selectors = [];
		// Mobile preview: gray blocks hidden on mobile or "show only" on tablet/desktop.
		$selectors[] = sprintf( $gray, 'body.forwp-preview-mobile', '.has-forwp-hide-mobile' );
		$selectors[] = sprintf( $gray, 'body.forwp-preview-mobile', '.has-forwp-show-tablet' );
		$selectors[] = sprintf( $gray, 'body.forwp-preview-mobile', '.has-forwp-show-desktop' );
		// Tablet preview.
		$selectors[] = sprintf( $gray, 'body.forwp-preview-tablet', '.has-forwp-hide-tablet' );
		$selectors[] = sprintf( $gray, 'body.forwp-preview-tablet', '.has-forwp-show-mobile' );
		$selectors[] = sprintf( $gray, 'body.forwp-preview-tablet', '.has-forwp-show-desktop' );
		// Desktop preview.
		$selectors[] = sprintf( $gray, 'body.forwp-preview-desktop', '.has-forwp-hide-desktop' );
		$selectors[] = sprintf( $gray, 'body.forwp-preview-desktop', '.has-forwp-show-mobile' );
		$selectors[] = sprintf( $gray, 'body.forwp-preview-desktop', '.has-forwp-show-tablet' );
		$badge_css = '.editor-styles-wrapper .block-editor-block-list__block.forwp-has-visibility-badge{position:relative;}'
			. '.editor-styles-wrapper .block-editor-block-list__block.forwp-has-visibility-badge::after{'
			. 'content:"";position:absolute;top:4px;right:4px;width:20px;height:20px;'
			. 'background:var(--wp-admin-theme-color,#0073aa);border-radius:50%;'
			. 'box-shadow:0 0 0 1px rgba(255,255,255,.8);z-index:5;'
			. 'background-image:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'white\'%3E%3Cpath d=\'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z\'/%3E%3C/svg%3E");'
			. 'background-size:12px 12px;background-repeat:no-repeat;background-position:center;}';
		$override = $base . "\n" . implode( "\n", $selectors ) . "\n" . $badge_css;
		wp_register_style( 'forwp-responsive-iframe-hide-override', false, [] );
		wp_enqueue_style( 'forwp-responsive-iframe-hide-override' );
		wp_add_inline_style( 'forwp-responsive-iframe-hide-override', $override );
	}
}

