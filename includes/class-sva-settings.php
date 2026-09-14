<?php
/**
 * Registers the analytics settings screen.
 *
 * @package SimpleVisitorAnalytics
 */

class SVA_Settings {

	const PAGE_SLUG = 'sva-settings';
	const OPTION    = 'sva_settings';

	/**
	 * Register settings, submenu, and data deletion handler.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_sva_delete_data', array( $this, 'delete_data' ) );
		add_action( 'admin_post_sva_update_geoip', array( $this, 'update_geoip' ) );
	}

	/**
	 * Add Settings beneath the Analytics menu.
	 *
	 * @return void
	 */
	public function add_submenu() {
		add_submenu_page(
			SVA_Admin::MENU_SLUG,
			__( 'Analytics Settings', 'simple-visitor-analytics' ),
			__( 'Settings', 'simple-visitor-analytics' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the settings group and all fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'sva_settings_group',
			self::OPTION,
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);

		add_settings_section(
			'sva_privacy_section',
			__( 'Privacy and retention', 'simple-visitor-analytics' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field( 'exclude_logged_in', __( 'Exclude admins and logged-in users', 'simple-visitor-analytics' ), array( $this, 'checkbox_field' ), self::PAGE_SLUG, 'sva_privacy_section', array( 'label_for' => 'exclude_logged_in' ) );
		add_settings_field( 'exclude_ips', __( 'Exclude specific IPs', 'simple-visitor-analytics' ), array( $this, 'textarea_field' ), self::PAGE_SLUG, 'sva_privacy_section', array( 'label_for' => 'exclude_ips', 'description' => __( 'One IP address per line.', 'simple-visitor-analytics' ) ) );
		add_settings_field( 'exclude_pages', __( 'Exclude specific pages or post types', 'simple-visitor-analytics' ), array( $this, 'textarea_field' ), self::PAGE_SLUG, 'sva_privacy_section', array( 'label_for' => 'exclude_pages', 'description' => __( 'Enter one URL fragment or post type per line.', 'simple-visitor-analytics' ) ) );
		add_settings_field( 'retention_days', __( 'Raw data retention', 'simple-visitor-analytics' ), array( $this, 'retention_field' ), self::PAGE_SLUG, 'sva_privacy_section' );
		add_settings_field( 'respect_dnt', __( 'Respect Do Not Track', 'simple-visitor-analytics' ), array( $this, 'checkbox_field' ), self::PAGE_SLUG, 'sva_privacy_section', array( 'label_for' => 'respect_dnt' ) );
		add_settings_field( 'delete_data_on_uninstall', __( 'Delete data on uninstall', 'simple-visitor-analytics' ), array( $this, 'checkbox_field' ), self::PAGE_SLUG, 'sva_privacy_section', array( 'label_for' => 'delete_data_on_uninstall' ) );
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage analytics settings.', 'simple-visitor-analytics' ) );
		}

		$settings_deleted = isset( $_GET['sva_deleted'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['sva_deleted'] ) );
		$geoip_status     = isset( $_GET['sva_geoip'] ) ? sanitize_text_field( wp_unslash( $_GET['sva_geoip'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Analytics Settings', 'simple-visitor-analytics' ); ?></h1>
			<?php if ( $settings_deleted ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Analytics hit and aggregate data has been deleted. Your settings were kept.', 'simple-visitor-analytics' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'updated' === $geoip_status ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The local GeoIP database was updated.', 'simple-visitor-analytics' ); ?></p></div>
			<?php elseif ( 'error' === $geoip_status ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The GeoIP database could not be updated. Upload a valid .mmdb file.', 'simple-visitor-analytics' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'sva_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Country data', 'simple-visitor-analytics' ); ?></h2>
			<p><?php esc_html_e( 'Country lookup uses a local MaxMind GeoLite2-Country database. No database is downloaded automatically and no external request is made for a pageview.', 'simple-visitor-analytics' ); ?></p>
			<p><?php esc_html_e( 'GeoLite2 data is provided by MaxMind and is available under the GeoLite2 End User License Agreement. MaxMind attribution is required when using this database.', 'simple-visitor-analytics' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="sva_update_geoip">
				<?php wp_nonce_field( 'sva_update_geoip_database', 'sva_geoip_nonce' ); ?>
				<label for="sva_geoip_database"><strong><?php esc_html_e( 'GeoLite2-Country .mmdb file', 'simple-visitor-analytics' ); ?></strong></label>
				<input type="file" id="sva_geoip_database" name="sva_geoip_database" accept=".mmdb,application/octet-stream" required>
				<?php submit_button( __( 'Update GeoIP Database', 'simple-visitor-analytics' ), 'secondary', 'submit', false ); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Delete all analytics data', 'simple-visitor-analytics' ); ?></h2>
			<p><?php esc_html_e( 'This removes all raw hits and daily aggregates but keeps your settings.', 'simple-visitor-analytics' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Delete all analytics data now?', 'simple-visitor-analytics' ) ); ?>');">
				<input type="hidden" name="action" value="sva_delete_data">
				<?php wp_nonce_field( 'sva_delete_analytics_data', 'sva_delete_nonce' ); ?>
				<?php submit_button( __( 'Delete all analytics data', 'simple-visitor-analytics' ), 'delete', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Submitted settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$retention = isset( $input['retention_days'] ) ? sanitize_text_field( wp_unslash( $input['retention_days'] ) ) : 90;
		$allowed_retention = array( '30', '90', '180', '365', 'forever' );

		return array(
			'exclude_logged_in'        => ! empty( $input['exclude_logged_in'] ),
			'exclude_ips'              => $this->sanitize_lines( isset( $input['exclude_ips'] ) ? $input['exclude_ips'] : '', true ),
			'exclude_pages'            => $this->sanitize_lines( isset( $input['exclude_pages'] ) ? $input['exclude_pages'] : '', false ),
			'retention_days'           => in_array( $retention, $allowed_retention, true ) ? ( 'forever' === $retention ? $retention : absint( $retention ) ) : 90,
			'respect_dnt'              => ! empty( $input['respect_dnt'] ),
			'delete_data_on_uninstall' => ! empty( $input['delete_data_on_uninstall'] ),
		);
	}

	/**
	 * Delete hits and aggregates while preserving sva_settings.
	 *
	 * @return void
	 */
	public function delete_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete analytics data.', 'simple-visitor-analytics' ) );
		}

		check_admin_referer( 'sva_delete_analytics_data', 'sva_delete_nonce' );
		global $wpdb;
		$tables = array( $wpdb->prefix . 'sva_hits', $wpdb->prefix . 'sva_stats_daily' );

		foreach ( $tables as $table ) {
			$wpdb->query( "DELETE FROM {$table}" );
		}

		$url = add_query_arg( array( 'page' => self::PAGE_SLUG, 'sva_deleted' => '1' ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Install a manually uploaded local GeoIP database.
	 *
	 * @return void
	 */
	public function update_geoip() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to update the GeoIP database.', 'simple-visitor-analytics' ) );
		}

		check_admin_referer( 'sva_update_geoip_database', 'sva_geoip_nonce' );
		$file   = isset( $_FILES['sva_geoip_database'] ) ? $_FILES['sva_geoip_database'] : array();
		$result = SVA_GeoIP::install_uploaded_database( $file );
		$status = is_wp_error( $result ) ? 'error' : 'updated';
		$url    = add_query_arg( array( 'page' => self::PAGE_SLUG, 'sva_geoip' => $status ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	public function checkbox_field( $args ) {
		$settings = get_option( self::OPTION, array() );
		$key      = $args['label_for'];
		$checked  = ! empty( $settings[ $key ] );
		printf( '<label><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s> %4$s</label>', esc_attr( $key ), esc_attr( self::OPTION ), checked( $checked, true, false ), esc_html__( 'Enabled', 'simple-visitor-analytics' ) );
	}

	public function textarea_field( $args ) {
		$settings = get_option( self::OPTION, array() );
		$key      = $args['label_for'];
		$value    = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? implode( "\n", $settings[ $key ] ) : '';
		printf( '<textarea class="large-text code" rows="5" id="%1$s" name="%2$s[%1$s]">%3$s</textarea>', esc_attr( $key ), esc_attr( self::OPTION ), esc_textarea( $value ) );
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	public function retention_field() {
		$settings = get_option( self::OPTION, array() );
		$current  = isset( $settings['retention_days'] ) ? (string) $settings['retention_days'] : '90';
		$options  = array( '30' => '30 days', '90' => '90 days', '180' => '180 days', '365' => '365 days', 'forever' => 'Forever' );
		echo '<select id="retention_days" name="' . esc_attr( self::OPTION ) . '[retention_days]">';
		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	private function sanitize_lines( $value, $ips ) {
		$lines = preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( wp_unslash( (string) $value ) ) );
		$lines = array_filter( array_map( 'trim', $lines ) );
		if ( $ips ) {
			$lines = array_filter( $lines, function ( $line ) {
				return (bool) filter_var( $line, FILTER_VALIDATE_IP );
			} );
		}
		return array_values( array_unique( array_map( 'sanitize_text_field', $lines ) ) );
	}
}
