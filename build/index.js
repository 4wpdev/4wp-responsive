(function () {
	'use strict';

	// Step 0: Ensure core Gutenberg packages are available before running.
	if (
		!window.wp ||
		!window.wp.hooks ||
		!window.wp.blockEditor ||
		!window.wp.element ||
		!window.wp.components ||
		!window.wp.i18n ||
		!window.wp.data
	) {
		return;
	}

	// Step 1: Pull WordPress packages from the global `wp` namespace.
	var addFilter = window.wp.hooks.addFilter;
	var __ = window.wp.i18n.__;
	var el = window.wp.element.createElement;
	var Fragment = window.wp.element.Fragment;
	var useState = window.wp.element.useState;
	var useEffect = window.wp.element.useEffect;
	var InspectorControls = window.wp.blockEditor.InspectorControls;
	var SpacingSizesControl = window.wp.blockEditor.__experimentalSpacingSizesControl;
	var PanelBody = window.wp.components.PanelBody;
	var TabPanel = window.wp.components.TabPanel;
	var SelectControl = window.wp.components.SelectControl;
	var TextControl = window.wp.components.TextControl;
	var ToggleControl = window.wp.components.ToggleControl;
	var ButtonGroup = window.wp.components.ButtonGroup;
	var Button = window.wp.components.Button;
	var select = window.wp.data.select;
	var subscribe = window.wp.data.subscribe;

	// Step 2: Read plugin data injected via wp_localize_script.
	var settings = window.forwpResponsive || {};
	var spacingSizes = Array.isArray(settings.spacingSizes) ? settings.spacingSizes : [];
	var fontSizes = Array.isArray(settings.fontSizes) ? settings.fontSizes : [];
	var breakpoints = settings.breakpoints || {};
	var presetSlugs = spacingSizes.map(function (size) {
		return size && size.slug ? String(size.slug) : '';
	}).filter(Boolean);
	var fontSlugs = fontSizes.map(function (size) {
		return size && size.slug ? String(size.slug) : '';
	}).filter(Boolean);

	// Step 3: Prepare device tabs for the inspector UI.
	var devices = [
		{ key: 'Desktop', slug: 'desktop', label: __('Desktop', '4wp-responsive') },
		{ key: 'Tablet', slug: 'tablet', label: __('Tablet', '4wp-responsive') },
		{ key: 'Mobile', slug: 'mobile', label: __('Mobile', '4wp-responsive') }
	];

	// Step 4: Build dropdown options from spacing presets.
	var spacingOptions = [{ label: __('Default', '4wp-responsive'), value: '' }];
	spacingSizes.forEach(function (size) {
		if (!size || !size.slug) {
			return;
		}
		spacingOptions.push({
			label: size.name || size.slug,
			value: size.slug
		});
	});

	// Step 4.1: Convert stored slugs to preset values expected by SpacingSizesControl.
	function slugToPresetValue(slug) {
		if (!slug) {
			return '';
		}
		if (presetSlugs.indexOf(String(slug)) !== -1) {
			return 'var:preset|spacing|' + slug;
		}
		return String(slug);
	}

	// Step 4.2: Convert SpacingSizesControl values back to stored slugs.
	function presetValueToSlug(value) {
		if (!value || typeof value !== 'string') {
			return '';
		}
		if (value.indexOf('var:preset|spacing|') === 0) {
			return value.split('|').pop();
		}
		if (value.indexOf('var(--wp--preset--spacing--') === 0) {
			return value.replace('var(--wp--preset--spacing--', '').replace(')', '');
		}
		// Step 4.3: Preserve custom values as-is for CSS variable handling.
		return value;
	}

	// Step 4.6: Convert stored font slugs to picker values.
	function fontSlugToPickerValue(slug) {
		if (!slug) {
			return '';
		}
		if (fontSlugs.indexOf(String(slug)) !== -1) {
			return 'var:preset|font-size|' + slug;
		}
		return String(slug);
	}

	// Step 4.7: Convert picker values back to stored font slugs or custom values.
	function fontPickerValueToSlug(value) {
		if (!value || typeof value !== 'string') {
			return '';
		}
		if (value.indexOf('var:preset|font-size|') === 0) {
			return value.split('|').pop();
		}
		if (value.indexOf('var(--wp--preset--font-size--') === 0) {
			return value.replace('var(--wp--preset--font-size--', '').replace(')', '');
		}
		return value;
	}

	// Step 4.4: Detect the active preview device in the editor.
	function getPreviewDeviceType() {
		var device = 'Desktop';
		var editPost = select && select('core/edit-post');
		var editSite = select && select('core/edit-site');

		if (editPost) {
			if (typeof editPost.__experimentalGetPreviewDeviceType === 'function') {
				device = editPost.__experimentalGetPreviewDeviceType();
			} else if (typeof editPost.getPreviewDeviceType === 'function') {
				device = editPost.getPreviewDeviceType();
			}
		} else if (editSite) {
			if (typeof editSite.__experimentalGetPreviewDeviceType === 'function') {
				device = editSite.__experimentalGetPreviewDeviceType();
			} else if (typeof editSite.getPreviewDeviceType === 'function') {
				device = editSite.getPreviewDeviceType();
			}
		}

		// Step 4.4.1: Fallback to DOM class detection when the data store is unavailable.
		if (!device && typeof document !== 'undefined') {
			if (
				document.querySelector('.editor-styles-wrapper.is-mobile-preview') ||
				document.querySelector('body.is-mobile-preview') ||
				document.querySelector('html.is-mobile-preview')
			) {
				device = 'Mobile';
			} else if (
				document.querySelector('.editor-styles-wrapper.is-tablet-preview') ||
				document.querySelector('body.is-tablet-preview') ||
				document.querySelector('html.is-tablet-preview')
			) {
				device = 'Tablet';
			} else if (
				document.querySelector('.editor-styles-wrapper.is-desktop-preview') ||
				document.querySelector('body.is-desktop-preview') ||
				document.querySelector('html.is-desktop-preview')
			) {
				device = 'Desktop';
			}
		}

		return (device || 'Desktop').toLowerCase();
	}

	// Step 4.5: Render a simple divider line for section separation.
	function renderDivider() {
		return el('div', {
			style: {
				borderTop: '1px solid #e0e0e0',
				margin: '8px 0'
			}
		});
	}

	// Step 5: Ensure the attributes exist in block registration on the client.
	addFilter('blocks.registerBlockType', 'forwp-responsive/add-attributes', function (settings) {
		if (!settings.attributes) {
			settings.attributes = {};
		}

		// Step 5.1: Generate attributes for each spacing side and breakpoint.
		['Padding', 'Margin'].forEach(function (type) {
			['Top', 'Right', 'Bottom', 'Left'].forEach(function (side) {
				['Mobile', 'Tablet', 'Desktop'].forEach(function (device) {
					var key = 'responsive' + type + side + device;
					if (!settings.attributes[key]) {
						settings.attributes[key] = { type: 'string', default: '' };
					}
				});
			});
		});

		// Step 5.2: Add visibility attributes for show/hide per breakpoint.
		['Mobile', 'Tablet', 'Desktop'].forEach(function (device) {
			var hideKey = 'responsiveHide' + device;
			var showKey = 'responsiveShow' + device;
			if (!settings.attributes[hideKey]) {
				settings.attributes[hideKey] = { type: 'boolean', default: false };
			}
			if (!settings.attributes[showKey]) {
				settings.attributes[showKey] = { type: 'boolean', default: false };
			}
		});

		// Step 5.3: Add text alignment attributes per breakpoint.
		['Mobile', 'Tablet', 'Desktop'].forEach(function (device) {
			var alignKey = 'responsiveTextAlign' + device;
			if (!settings.attributes[alignKey]) {
				settings.attributes[alignKey] = { type: 'string', default: '' };
			}
		});

		// Step 5.4: Add reverse order (flex direction) per breakpoint for Columns/Group.
		['Mobile', 'Tablet', 'Desktop'].forEach(function (device) {
			var reverseKey = 'responsiveReverse' + device;
			if (!settings.attributes[reverseKey]) {
				settings.attributes[reverseKey] = { type: 'boolean', default: false };
			}
		});

		// Step 5.5: Add border radius (per corner) and min height per breakpoint.
		var radiusCorners = ['TopLeft', 'TopRight', 'BottomRight', 'BottomLeft'];
		['Mobile', 'Tablet', 'Desktop'].forEach(function (device) {
			radiusCorners.forEach(function (corner) {
				var brKey = 'responsiveBorderRadius' + corner + device;
				if (!settings.attributes[brKey]) {
					settings.attributes[brKey] = { type: 'string', default: '' };
				}
			});
			var mhKey = 'responsiveMinHeight' + device;
			if (!settings.attributes[mhKey]) {
				settings.attributes[mhKey] = { type: 'string', default: '' };
			}
		});

		return settings;
	});

	// Step 6: Map responsive attributes to utility class names and inline variables.
	function getResponsiveClassesAndStyles(attributes) {
		var classes = [];
		var styles = {};

		['Padding', 'Margin'].forEach(function (type) {
			['Top', 'Right', 'Bottom', 'Left'].forEach(function (side) {
				['Mobile', 'Tablet', 'Desktop'].forEach(function (device) {
					var key = 'responsive' + type + side + device;
					var value = attributes && attributes[key] ? String(attributes[key]).trim() : '';
					if (!value) {
						return;
					}
					var slug = value.toLowerCase().replace(/[^a-z0-9_-]/g, '');
					if (presetSlugs.indexOf(slug) !== -1) {
						classes.push(
							'has-forwp-' +
								type.toLowerCase() +
								'-' +
								side.toLowerCase() +
								'-' +
								device.toLowerCase() +
								'-' +
								slug
						);
						return;
					}

					classes.push(
						'has-forwp-' +
							type.toLowerCase() +
							'-' +
							side.toLowerCase() +
							'-' +
							device.toLowerCase() +
							'-custom'
					);

					var numericMatch = value.match(/-?\d+(\.\d+)?/);
					if (numericMatch && numericMatch[0]) {
						var unit = value.replace(numericMatch[0], '').trim();
						var finalValue = numericMatch[0] + (unit ? unit : 'px');
						styles[
							'--forwp-' +
								type.toLowerCase() +
								'-' +
								side.toLowerCase() +
								'-' +
								device.toLowerCase()
						] = finalValue;
					}
				});
			});
		});

		// Step 6.2: Map responsive font-size values.
		['Mobile', 'Tablet', 'Desktop'].forEach(function (device) {
			var key = 'responsiveFontSize' + device;
			var value = attributes && attributes[key] ? String(attributes[key]).trim() : '';
			if (!value) {
				return;
			}

			var slug = value.toLowerCase().replace(/[^a-z0-9_-]/g, '');
			if (fontSlugs.indexOf(slug) !== -1) {
				classes.push('has-forwp-font-size-' + device.toLowerCase() + '-' + slug);
				return;
			}

			classes.push('has-forwp-font-size-' + device.toLowerCase() + '-custom');
			var numericMatch = value.match(/-?\d+(\.\d+)?/);
			if (numericMatch && numericMatch[0]) {
				var unit = value.replace(numericMatch[0], '').trim();
				var finalValue = numericMatch[0] + (unit ? unit : 'px');
				styles['--forwp-font-size-' + device.toLowerCase()] = finalValue;
			}
		});

		// Step 6.3: Map responsive text alignment classes.
		['Mobile', 'Tablet', 'Desktop'].forEach(function (device) {
			var key = 'responsiveTextAlign' + device;
			var hideKey = 'responsiveHide' + device;
			if (attributes && attributes[hideKey]) {
				return;
			}
			var value = attributes && attributes[key] ? String(attributes[key]).trim() : '';
			var slug = value.toLowerCase();
			if (['left', 'center', 'right', 'justify'].indexOf(slug) !== -1) {
				classes.push('has-forwp-text-align-' + device.toLowerCase() + '-' + slug);
			}
		});

		// Step 6.1: Add visibility utility classes based on attributes.
		if (attributes) {
			if (attributes.responsiveHideMobile) classes.push('has-forwp-hide-mobile');
			if (attributes.responsiveHideTablet) classes.push('has-forwp-hide-tablet');
			if (attributes.responsiveHideDesktop) classes.push('has-forwp-hide-desktop');
			if (attributes.responsiveShowMobile) classes.push('has-forwp-show-mobile');
			if (attributes.responsiveShowTablet) classes.push('has-forwp-show-tablet');
			if (attributes.responsiveShowDesktop) classes.push('has-forwp-show-desktop');
			if (attributes.responsiveReverseMobile) classes.push('has-forwp-reverse-mobile');
			if (attributes.responsiveReverseTablet) classes.push('has-forwp-reverse-tablet');
			if (attributes.responsiveReverseDesktop) classes.push('has-forwp-reverse-desktop');
		}

		// Step 6.5: Responsive border radius per corner (top-left, top-right, bottom-right, bottom-left).
		var radiusCorners = [
			{ key: 'TopLeft', slug: 'top-left' },
			{ key: 'TopRight', slug: 'top-right' },
			{ key: 'BottomRight', slug: 'bottom-right' },
			{ key: 'BottomLeft', slug: 'bottom-left' }
		];
		['Mobile', 'Tablet', 'Desktop'].forEach(function (device) {
			radiusCorners.forEach(function (c) {
				var key = 'responsiveBorderRadius' + c.key + device;
				var value = attributes && attributes[key] ? String(attributes[key]).trim() : '';
				if (!value) return;
				var deviceSlug = device.toLowerCase();
				classes.push('has-forwp-border-radius-' + c.slug + '-' + deviceSlug);
				var sanitized = /^-?\d*\.?\d+$/.test(value) ? value + 'px' : value;
				styles['--forwp-border-radius-' + c.slug + '-' + deviceSlug] = sanitized;
			});
		});

		// Step 6.6: Responsive min height (Cover, Group, etc.).
		['Mobile', 'Tablet', 'Desktop'].forEach(function (device) {
			var key = 'responsiveMinHeight' + device;
			var value = attributes && attributes[key] ? String(attributes[key]).trim() : '';
			if (!value) return;
			var deviceSlug = device.toLowerCase();
			classes.push('has-forwp-min-height-' + deviceSlug);
			var sanitized = /^-?\d*\.?\d+$/.test(value) ? value + 'px' : value;
			styles['--forwp-min-height-' + deviceSlug] = sanitized;
		});

		return {
			classes: classes,
			styles: styles
		};
	}

	// Step 6.4: Resolve the active preview text alignment for the editor.
	function getPreviewTextAlign(attributes) {
		var device = getPreviewDeviceType();
		var keyMap = {
			mobile: 'responsiveTextAlignMobile',
			tablet: 'responsiveTextAlignTablet',
			desktop: 'responsiveTextAlignDesktop'
		};
		var hideMap = {
			mobile: 'responsiveHideMobile',
			tablet: 'responsiveHideTablet',
			desktop: 'responsiveHideDesktop'
		};
		var key = keyMap[device];
		var hideKey = hideMap[device];
		if (!key || !attributes || attributes[hideKey]) {
			return '';
		}
		var value = attributes[key] ? String(attributes[key]).trim().toLowerCase() : '';
		if (['left', 'center', 'right', 'justify'].indexOf(value) === -1) {
			return '';
		}
		return value;
	}

	// Step 7: Add the inspector controls for responsive spacing.
	addFilter('editor.BlockEdit', 'forwp-responsive/add-controls', function (BlockEdit) {
		return function (props) {
			if (!props.attributes || !props.setAttributes) {
				return el(BlockEdit, props);
			}

			var activeDevice = useState(getPreviewDeviceType());
			var deviceState = activeDevice[0];
			var setDeviceState = activeDevice[1];

			useEffect(function () {
				var updateDevice = function () {
					var nextDevice = getPreviewDeviceType();
					setDeviceState(function (current) {
						return current === nextDevice ? current : nextDevice;
					});
				};

				updateDevice();
				var unsubscribe = subscribe(updateDevice);
				return function () {
					if (unsubscribe) {
						unsubscribe();
					}
				};
			}, []);

			var tabs = devices.map(function (device) {
				return { name: device.slug, title: device.label, className: '4wp-responsive-tab' };
			});
			var activeTabName = ['desktop', 'tablet', 'mobile'].indexOf(deviceState) !== -1 ? deviceState : 'desktop';

			function getCoreSpacing(type, side) {
				var style = props.attributes.style || {};
				var spacing = style.spacing || {};
				var map = spacing[type === 'Padding' ? 'padding' : 'margin'];
				if (!map || typeof map !== 'object') return '';
				var key = side.toLowerCase();
				var val = map[key];
				return val ? String(val).trim() : '';
			}

			function renderSpacingGroup(type, deviceKey) {
				var header = type === 'Padding' ? __('Padding', '4wp-responsive') : __('Margin', '4wp-responsive');
				var sideKeys = ['Top', 'Right', 'Bottom', 'Left'];
				function valueFor(side) {
					var attrKey = 'responsive' + type + side + deviceKey;
					var ours = props.attributes[attrKey];
					if (ours !== undefined && ours !== null && String(ours).trim() !== '') {
						return slugToPresetValue(ours);
					}
					var core = getCoreSpacing(type, side);
					return core ? (core.indexOf('var:') === 0 || core.indexOf('var(') === 0 ? core : core) : '';
				}
				var values = {
					top: valueFor('Top'),
					right: valueFor('Right'),
					bottom: valueFor('Bottom'),
					left: valueFor('Left')
				};

				// Step 7.1: Use Gutenberg spacing control when available for native UX.
				if (SpacingSizesControl) {
					return el(
						'div',
						{ className: '4wp-responsive-group' },
						el(SpacingSizesControl, {
							label: header,
							values: values,
							sides: ['top', 'right', 'bottom', 'left'],
							showSideInLabel: true,
							onChange: function (nextValues) {
								var update = {};
								sideKeys.forEach(function (side) {
									var key = 'responsive' + type + side + deviceKey;
									update[key] = presetValueToSlug(nextValues[side.toLowerCase()]);
								});
								props.setAttributes(update);
							},
							useSelect: true
						})
					);
				}

				// Step 7.2: Fallback to selects when spacing control is not available.
				return el(
					'div',
					{ className: '4wp-responsive-group' },
					el('p', { className: '4wp-responsive-group-title' }, header),
					sideKeys.map(function (side) {
						var attrKey = 'responsive' + type + side + deviceKey;
						return el(SelectControl, {
							key: attrKey,
							label: __(side, '4wp-responsive'),
							value: props.attributes[attrKey] || '',
							options: spacingOptions,
							onChange: function (value) {
								var update = {};
								update[attrKey] = value;
								props.setAttributes(update);
							}
						});
					})
				);
			}

			function getCoreFontSize() {
				var style = props.attributes.style || {};
				var typo = style.typography || {};
				return typo.fontSize ? String(typo.fontSize).trim() : '';
			}

			// Step 7.2: Render font-size controls when supported by the block.
			function renderFontSizeGroup(deviceKey) {
				var blockType = window.wp.blocks && window.wp.blocks.getBlockType ? window.wp.blocks.getBlockType(props.name) : null;
				var supportsFontSize = window.wp.blocks && window.wp.blocks.hasBlockSupport
					? window.wp.blocks.hasBlockSupport(props.name, 'typography.fontSize')
					: true;

				if (blockType && blockType.supports && blockType.supports.typography && blockType.supports.typography.fontSize === false) {
					supportsFontSize = false;
				}

				if (!supportsFontSize || !window.wp.blockEditor.FontSizePicker) {
					return null;
				}

				var attrKey = 'responsiveFontSize' + deviceKey;
				var rawValue = props.attributes[attrKey];
				var coreFontSize = getCoreFontSize();
				var effectiveRaw = (rawValue !== undefined && rawValue !== null && String(rawValue).trim() !== '') ? String(rawValue).trim() : coreFontSize;
				var rawSlug = effectiveRaw ? String(effectiveRaw).toLowerCase().replace(/[^a-z0-9_.-]/g, '') : '';
				var isPreset = rawSlug && fontSlugs.indexOf(rawSlug) !== -1;
				var value = fontSlugToPickerValue(rawValue || coreFontSize);

				function setFontSize(val) {
					var update = {};
					update[attrKey] = val ? String(val).trim() : '';
					props.setAttributes(update);
				}

				var customValue = !isPreset && rawValue ? String(rawValue).trim() : '';
				var customPlaceholder = coreFontSize ? __('Default: %s', '4wp-responsive').replace('%s', coreFontSize) : 'e.g. 18px, 1.2rem';

				return el(
					'div',
					{ className: '4wp-responsive-group' },
					el(window.wp.blockEditor.FontSizePicker, {
						fontSizes: fontSizes,
						value: value,
						disableCustomFontSizes: false,
						fallbackFontSize: coreFontSize || undefined,
						onChange: function (nextValue) {
							setFontSize(fontPickerValueToSlug(nextValue));
						}
					}),
					TextControl ? el(TextControl, {
						label: __('Custom size', '4wp-responsive'),
						value: customValue,
						placeholder: customPlaceholder,
						onChange: function (next) {
							setFontSize(next || '');
						}
					}) : null,
					el('p', { style: { fontSize: '11px', color: '#757575', margin: '4px 0 0' } }, __('Default comes from block / theme.json', '4wp-responsive'))
				);
			}

			// Step 7.3: Render responsive text alignment controls.
			function renderTextAlignGroup(deviceKey) {
				var attrKey = 'responsiveTextAlign' + deviceKey;
				var currentValue = props.attributes[attrKey] || '';
				var options = [
					{ value: 'left', label: __('Left', '4wp-responsive') },
					{ value: 'center', label: __('Center', '4wp-responsive') },
					{ value: 'right', label: __('Right', '4wp-responsive') },
					{ value: 'justify', label: __('Justify', '4wp-responsive') }
				];
				return el(
					'div',
					{ className: '4wp-responsive-group' },
					el('p', { className: '4wp-responsive-group-title' }, __('Alignment', '4wp-responsive')),
					el(
						ButtonGroup,
						null,
						options.map(function (option) {
							return el(Button, {
								key: option.value,
								isPressed: currentValue === option.value,
								variant: currentValue === option.value ? 'primary' : 'secondary',
								onClick: function () {
									var update = {};
									update[attrKey] = currentValue === option.value ? '' : option.value;
									props.setAttributes(update);
								}
							}, option.label);
						})
					)
				);
			}

			// Step 7.4: Render reverse order toggle per device (Columns/Group: Column 2 | Column 1 on mobile).
			function renderReverseOrderGroup(deviceKey) {
				var reverseKey = 'responsiveReverse' + deviceKey;
				return el(
					'div',
					{ className: '4wp-responsive-group' },
					el('p', { className: '4wp-responsive-group-title' }, __('Layout', '4wp-responsive')),
					el(ToggleControl, {
						label: __('Reverse order on this device', '4wp-responsive'),
						help: __('Use for Columns/Group: show right column above left when stacked (e.g. on mobile).', '4wp-responsive'),
						checked: !!props.attributes[reverseKey],
						onChange: function (value) {
							var update = {};
							update[reverseKey] = value;
							props.setAttributes(update);
						}
					})
				);
			}

			// Step 7.5: Render border radius per corner per device (like default block border radius).
			function renderBorderRadiusGroup(deviceKey) {
				var corners = [
					{ key: 'TopLeft', label: __('Top left', '4wp-responsive') },
					{ key: 'TopRight', label: __('Top right', '4wp-responsive') },
					{ key: 'BottomRight', label: __('Bottom right', '4wp-responsive') },
					{ key: 'BottomLeft', label: __('Bottom left', '4wp-responsive') }
				];
				return el(
					'div',
					{ className: '4wp-responsive-group' },
					el('p', { className: '4wp-responsive-group-title' }, __('Border radius', '4wp-responsive')),
					corners.map(function (c) {
						var attrKey = 'responsiveBorderRadius' + c.key + deviceKey;
						var value = (props.attributes[attrKey] || '').trim();
						return TextControl ? el(TextControl, {
							key: attrKey,
							label: c.label,
							value: value,
							placeholder: 'e.g. 8px, 0',
							onChange: function (next) {
								props.setAttributes({ [attrKey]: (next && next.trim()) || '' });
							}
						}) : null;
					})
				);
			}

			// Step 7.6: Render min height per device (only for blocks that support min height, e.g. Cover, Group).
			function renderMinHeightGroup(deviceKey) {
				var hasMinHeight = window.wp.blocks && window.wp.blocks.hasBlockSupport && window.wp.blocks.hasBlockSupport(props.name, 'dimensions', 'minHeight');
				if (!hasMinHeight) return null;
				var attrKey = 'responsiveMinHeight' + deviceKey;
				var value = (props.attributes[attrKey] || '').trim();
				return el(
					'div',
					{ className: '4wp-responsive-group' },
					el('p', { className: '4wp-responsive-group-title' }, __('Min height', '4wp-responsive')),
					TextControl ? el(TextControl, {
						label: __('Min height', '4wp-responsive'),
						value: value,
						placeholder: 'e.g. 300px, 50vh',
						onChange: function (next) {
							props.setAttributes({ [attrKey]: (next && next.trim()) || '' });
						}
					}) : null
				);
			}

			// Step 7.1: Render visibility controls per device.
			function renderVisibilityGroup(deviceKey) {
				var hideKey = 'responsiveHide' + deviceKey;
				var showKey = 'responsiveShow' + deviceKey;
				var deviceKeys = ['Mobile', 'Tablet', 'Desktop'];

				return el(
					'div',
					{ className: '4wp-responsive-group' },
					el('p', { className: '4wp-responsive-group-title' }, __('Visibility', '4wp-responsive')),
					el(ToggleControl, {
						label: __('Hide on this device', '4wp-responsive'),
						checked: !!props.attributes[hideKey],
						onChange: function (value) {
							var update = {};
							update[hideKey] = value;
							if (value) {
								update[showKey] = false;
							}
							props.setAttributes(update);
						}
					}),
					el(ToggleControl, {
						label: __('Show only on this device', '4wp-responsive'),
						checked: !!props.attributes[showKey],
						onChange: function (value) {
							var update = {};
							update[showKey] = value;
							if (value) {
								update[hideKey] = false;
								deviceKeys.forEach(function (key) {
									if (key !== deviceKey) {
										update['responsiveShow' + key] = false;
									}
								});
							}
							props.setAttributes(update);
						}
					})
				);
			}

			return el(
				Fragment,
				null,
				el(BlockEdit, props),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __('Responsive Spacing', '4wp-responsive'),
							initialOpen: activeTabName !== 'desktop',
							key: 'forwp-panel-' + activeTabName
						},
						el(
							'div',
							{ className: 'forwp-responsive-panel' },
							el(TabPanel, { className: '4wp-responsive-tabs', tabs: tabs, initialTabName: activeTabName, key: 'forwp-tabs-' + activeTabName }, function (tab) {
								var device = devices.filter(function (item) {
									return item.slug === tab.name;
								})[0];
								if (!device) {
									return null;
								}

								return el(
									'div',
									{ className: '4wp-responsive-tab-panel' },
									el('p', { className: '4wp-responsive-breakpoint', style: { fontSize: '11px', color: '#757575', margin: '4px 0 8px' } }, __('Screen size', '4wp-responsive') + ': ' + formatBreakpoint(device.slug)),
									renderDivider(),
									renderSpacingGroup('Padding', device.key),
									renderDivider(),
									renderSpacingGroup('Margin', device.key),
									renderDivider(),
									renderFontSizeGroup(device.key),
									renderDivider(),
									renderTextAlignGroup(device.key),
									renderDivider(),
									renderReverseOrderGroup(device.key),
									renderDivider(),
									renderBorderRadiusGroup(device.key),
									renderDivider(),
									renderMinHeightGroup(device.key),
									renderDivider(),
									renderVisibilityGroup(device.key)
								);
							})
						)
					)
				)
			);
		};
	});

	// Step 8: Add responsive classes and style vars in the editor preview.
	addFilter('editor.BlockListBlock', 'forwp-responsive/add-classes', function (BlockListBlock) {
		return function (props) {
			var result = getResponsiveClassesAndStyles(props.attributes);
			var classes = result.classes || [];
			var styles = result.styles || {};
			var previewAlign = getPreviewTextAlign(props.attributes);
			var previewDevice = getPreviewDeviceType();
			var dimForDevice = false;
			if (props.attributes) {
				if (previewDevice === 'mobile' && props.attributes.responsiveHideMobile) {
					dimForDevice = true;
				}
				if (previewDevice === 'tablet' && props.attributes.responsiveHideTablet) {
					dimForDevice = true;
				}
				if (previewDevice === 'desktop' && props.attributes.responsiveHideDesktop) {
					dimForDevice = true;
				}

				if (previewDevice === 'mobile' && (props.attributes.responsiveShowTablet || props.attributes.responsiveShowDesktop)) {
					dimForDevice = true;
				}
				if (previewDevice === 'tablet' && (props.attributes.responsiveShowMobile || props.attributes.responsiveShowDesktop)) {
					dimForDevice = true;
				}
				if (previewDevice === 'desktop' && (props.attributes.responsiveShowMobile || props.attributes.responsiveShowTablet)) {
					dimForDevice = true;
				}
			}

			if (!classes.length && !Object.keys(styles).length && !dimForDevice) {
				return el(BlockListBlock, props);
			}
			var className = [props.className, classes.join(' ')].filter(Boolean).join(' ');
			var inlineAlignStyle = previewAlign ? { textAlign: previewAlign } : {};
			var classesStr = classes.join(' ');
			var stylesStr = Object.keys(styles).join(' ');
			var spacingProps = ['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft'];
			var stripKeys = [];
			spacingProps.forEach(function (camel) {
				var css = camel.replace(/([A-Z])/g, '-$1').toLowerCase();
				if (classesStr.indexOf('has-forwp-' + css + '-') !== -1 || stylesStr.indexOf('--forwp-' + css + '-') !== -1) {
					stripKeys.push(camel);
				}
			});
			if (classesStr.indexOf('has-forwp-border-radius-') !== -1 || stylesStr.indexOf('--forwp-border-radius-') !== -1) {
				stripKeys.push('borderTopLeftRadius', 'borderTopRightRadius', 'borderBottomRightRadius', 'borderBottomLeftRadius');
			}
			if (classesStr.indexOf('has-forwp-min-height-') !== -1 || stylesStr.indexOf('--forwp-min-height-') !== -1) {
				stripKeys.push('minHeight');
			}
			var existingStyle = Object.assign({}, (props.wrapperProps && props.wrapperProps.style) || {}, props.style || {});
			stripKeys.forEach(function (key) {
				delete existingStyle[key];
			});
			var wrapperProps = Object.assign({}, props.wrapperProps || {}, {
				style: Object.assign(
					{},
					existingStyle,
					styles,
					dimForDevice ? { opacity: 0.35 } : {},
					inlineAlignStyle
				)
			});
			var blockStyle = Object.assign({}, props.style || {});
			stripKeys.forEach(function (key) {
				delete blockStyle[key];
			});
			Object.assign(blockStyle, inlineAlignStyle);
			return el(BlockListBlock, Object.assign({}, props, {
				className: className,
				style: blockStyle,
				wrapperProps: wrapperProps
			}));
		};
	});

	// Step 9: Show breakpoint info in the UI for context.
	function formatBreakpoint(deviceSlug) {
		var device = breakpoints[deviceSlug] || {};
		if (deviceSlug === 'mobile' && device.max) {
			return '\u2264' + device.max + 'px';
		}
		if (deviceSlug === 'tablet' && device.min && device.max) {
			return device.min + '-' + device.max + 'px';
		}
		if (deviceSlug === 'desktop' && device.min) {
			return '\u2265' + device.min + 'px';
		}
		return '';
	}
})();

