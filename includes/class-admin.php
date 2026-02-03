<?php
namespace ForWP\Responsive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {
	/**
	 * Step 1: Register admin hooks.
	 */
	public static function init() {
		// Step 1.1: Register settings used as a fallback when theme.json does not define breakpoints.
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );

		// Step 1.2: Register the settings page for storage configuration.
		add_action( 'admin_menu', [ __CLASS__, 'register_settings_page' ] );
	}

	/**
	 * Step 2: Register plugin settings with sanitization.
	 */
	public static function register_settings() {
		// Step 2.1: Store breakpoints in a single option for simple retrieval.
		register_setting(
			'4wp_responsive_settings',
			'4wp_responsive_breakpoints',
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize_breakpoints' ],
				'default'           => [],
			]
		);

		// Step 2.2: Store CSS path overrides for non-standard architectures.
		register_setting(
			'4wp_responsive_settings',
			'4wp_responsive_css_storage_path',
			[
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_storage_path' ],
				'default'           => '',
			]
		);
	}

	/**
	 * Step 3: Sanitize breakpoint values before saving.
	 *
	 * @param array $input Raw input array.
	 * @return array
	 */
	public static function sanitize_breakpoints( $input ) {
		// Step 3.1: Normalize the expected device keys.
		$devices   = [ 'mobile', 'tablet', 'desktop' ];
		$sanitized = [];

		foreach ( $devices as $device ) {
			if ( ! isset( $input[ $device ] ) || ! is_array( $input[ $device ] ) ) {
				continue;
			}

			// Step 3.2: Sanitize each value for safe storage.
			$sanitized[ $device ] = [
				'min'   => isset( $input[ $device ]['min'] ) ? absint( $input[ $device ]['min'] ) : 0,
				'max'   => isset( $input[ $device ]['max'] ) ? absint( $input[ $device ]['max'] ) : 0,
				'label' => sanitize_text_field( $input[ $device ]['label'] ?? '' ),
			];
		}

		return $sanitized;
	}

	/**
	 * Step 4: Register a settings page under Settings.
	 */
	public static function register_settings_page() {
		// Step 4.1: Add a simple settings page under "Settings" for administrators.
		add_options_page(
			__( '4WP Responsive', '4wp-responsive' ),
			__( '4WP Responsive', '4wp-responsive' ),
			'manage_options',
			'4wp-responsive',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	/**
	 * Step 5: Render the settings page.
	 */
	public static function render_settings_page() {
		// Step 5.1: Load the current value to display.
		$current_path = get_option( '4wp_responsive_css_storage_path', '' );
		$active_breakpoints = Plugin::get_breakpoints();
		$theme_breakpoints  = Plugin::get_theme_json_breakpoints();
		$has_theme_breakpoints = ! empty( $theme_breakpoints );
		$saved_breakpoints  = get_option( '4wp_responsive_breakpoints', [] );
		$has_saved_breakpoints = ! empty( $saved_breakpoints );
		$breakpoint_source = $has_theme_breakpoints
			? __( 'theme.json (settings.custom.breakpoints)', '4wp-responsive' )
			: ( $has_saved_breakpoints ? __( 'Plugin settings', '4wp-responsive' ) : __( 'Default values', '4wp-responsive' ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( '4wp_responsive_settings' ); ?>

				<h2><?php esc_html_e( 'Breakpoints', '4wp-responsive' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'These breakpoints are used by the editor tabs and responsive CSS.', '4wp-responsive' ); ?>
					<?php echo ' ' . esc_html__( 'Source:', '4wp-responsive' ) . ' ' . esc_html( $breakpoint_source ); ?>
				</p>

				<table class="widefat striped" style="max-width: 640px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Device', '4wp-responsive' ); ?></th>
							<th><?php esc_html_e( 'Min width', '4wp-responsive' ); ?></th>
							<th><?php esc_html_e( 'Max width', '4wp-responsive' ); ?></th>
							<th><?php esc_html_e( 'Label', '4wp-responsive' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( [ 'mobile', 'tablet', 'desktop' ] as $device ) : ?>
							<tr>
								<td><?php echo esc_html( ucfirst( $device ) ); ?></td>
								<td><?php echo esc_html( $active_breakpoints[ $device ]['min'] ?? '—' ); ?></td>
								<td><?php echo esc_html( $active_breakpoints[ $device ]['max'] ?? '—' ); ?></td>
								<td><?php echo esc_html( $active_breakpoints[ $device ]['label'] ?? '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p class="description" style="margin-top: 8px;">
					<?php esc_html_e( 'Edit breakpoints in the active theme.json under settings.custom.breakpoints.', '4wp-responsive' ); ?>
				</p>

				<?php if ( ! $has_theme_breakpoints ) : ?>
					<h3 style="margin-top: 24px;"><?php esc_html_e( 'Custom breakpoints (used when theme.json is missing)', '4wp-responsive' ); ?></h3>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Mobile', '4wp-responsive' ); ?></th>
							<td>
								<label>
									<?php esc_html_e( 'Max', '4wp-responsive' ); ?>
									<input type="number" class="small-text" name="4wp_responsive_breakpoints[mobile][max]" value="<?php echo esc_attr( $saved_breakpoints['mobile']['max'] ?? 599 ); ?>" />
								</label>
								<label style="margin-left: 12px;">
									<?php esc_html_e( 'Label', '4wp-responsive' ); ?>
									<input type="text" class="regular-text" name="4wp_responsive_breakpoints[mobile][label]" value="<?php echo esc_attr( $saved_breakpoints['mobile']['label'] ?? __( 'Mobile', '4wp-responsive' ) ); ?>" />
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Tablet', '4wp-responsive' ); ?></th>
							<td>
								<label>
									<?php esc_html_e( 'Min', '4wp-responsive' ); ?>
									<input type="number" class="small-text" name="4wp_responsive_breakpoints[tablet][min]" value="<?php echo esc_attr( $saved_breakpoints['tablet']['min'] ?? 600 ); ?>" />
								</label>
								<label style="margin-left: 12px;">
									<?php esc_html_e( 'Max', '4wp-responsive' ); ?>
									<input type="number" class="small-text" name="4wp_responsive_breakpoints[tablet][max]" value="<?php echo esc_attr( $saved_breakpoints['tablet']['max'] ?? 1023 ); ?>" />
								</label>
								<label style="margin-left: 12px;">
									<?php esc_html_e( 'Label', '4wp-responsive' ); ?>
									<input type="text" class="regular-text" name="4wp_responsive_breakpoints[tablet][label]" value="<?php echo esc_attr( $saved_breakpoints['tablet']['label'] ?? __( 'Tablet', '4wp-responsive' ) ); ?>" />
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Desktop', '4wp-responsive' ); ?></th>
							<td>
								<label>
									<?php esc_html_e( 'Min', '4wp-responsive' ); ?>
									<input type="number" class="small-text" name="4wp_responsive_breakpoints[desktop][min]" value="<?php echo esc_attr( $saved_breakpoints['desktop']['min'] ?? 1024 ); ?>" />
								</label>
								<label style="margin-left: 12px;">
									<?php esc_html_e( 'Label', '4wp-responsive' ); ?>
									<input type="text" class="regular-text" name="4wp_responsive_breakpoints[desktop][label]" value="<?php echo esc_attr( $saved_breakpoints['desktop']['label'] ?? __( 'Desktop', '4wp-responsive' ) ); ?>" />
								</label>
							</td>
						</tr>
					</table>
				<?php else : ?>
					<p class="description" style="margin-top: 8px;">
						<?php esc_html_e( 'Custom breakpoints are disabled because the active theme defines them in theme.json.', '4wp-responsive' ); ?>
					</p>
				<?php endif; ?>

				<hr style="margin: 24px 0;" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="4wp-responsive-css-storage-path">
								<?php esc_html_e( 'CSS storage path', '4wp-responsive' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								class="regular-text"
								id="4wp-responsive-css-storage-path"
								name="4wp_responsive_css_storage_path"
								value="<?php echo esc_attr( $current_path ); ?>"
								placeholder="<?php echo esc_attr( Plugin::get_default_css_storage_path() ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Leave empty to use the default uploads path. Provide an absolute path to support custom architectures.', '4wp-responsive' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Step 6: Sanitize the custom storage path.
	 *
	 * @param string $input Raw input.
	 * @return string
	 */
	public static function sanitize_storage_path( $input ) {
		// Step 6.1: Normalize whitespace and slashes.
		$path = trim( wp_unslash( $input ) );
		if ( '' === $path ) {
			return '';
		}

		// Step 6.2: Normalize to a safe absolute path string.
		return wp_normalize_path( $path );
	}
}

