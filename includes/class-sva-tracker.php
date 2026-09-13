<?php
/**
 * Registers the frontend beacon and records pageview hits.
 *
 * @package SimpleVisitorAnalytics
 */

class SVA_Tracker {

	/**
	 * Register tracker hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker' ) );
	}

	/**
	 * Register the public pageview route, protected by a WordPress REST nonce.
	 *
	 * @return void
	 */
	public function register_route() {
		register_rest_route(
			'sva/v1',
			'/hit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'record_hit' ),
				'permission_callback' => array( $this, 'verify_request' ),
				'args'                => array(
					'url'      => array( 'required' => true, 'sanitize_callback' => 'esc_url_raw' ),
					'title'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'referrer' => array( 'sanitize_callback' => 'esc_url_raw' ),
				),
			)
		);
	}

	/**
	 * Verify the REST nonce sent by the frontend beacon.
	 *
	 * @return true|WP_Error
	 */
	public function verify_request() {
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'sva_invalid_nonce', __( 'Invalid analytics request.', 'simple-visitor-analytics' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Enqueue the beacon for eligible frontend visitors.
	 *
	 * @return void
	 */
	public function enqueue_tracker() {
		if ( is_admin() || $this->should_exclude_request() ) {
			return;
		}

		wp_enqueue_script( 'sva-tracker', SVA_PLUGIN_URL . 'assets/tracker.js', array(), SVA_VERSION, true );
		wp_add_inline_script(
			'sva-tracker',
			'window.SVA_TRACKER=' . wp_json_encode(
				array(
					'endpoint' => esc_url_raw( rest_url( 'sva/v1/hit' ) ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Store a sanitized pageview and its URL record.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function record_hit( WP_REST_Request $request ) {
		if ( $this->should_exclude_request() ) {
			return new WP_REST_Response( array( 'recorded' => false ), 202 );
		}

		$url = esc_url_raw( $request->get_param( 'url' ) );
		if ( ! $url ) {
			return new WP_Error( 'sva_invalid_url', __( 'Invalid page URL.', 'simple-visitor-analytics' ), array( 'status' => 400 ) );
		}

		$title      = sanitize_text_field( $request->get_param( 'title' ) );
		$referrer   = esc_url_raw( $request->get_param( 'referrer' ) );
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		global $wpdb;
		$urls_table = $wpdb->prefix . 'sva_urls';
		$hits_table = $wpdb->prefix . 'sva_hits';
		$url_id     = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$urls_table} WHERE url = %s LIMIT 1", $url ) );

		if ( ! $url_id ) {
			$wpdb->insert(
				$urls_table,
				array( 'url' => $url, 'title' => $title ),
				array( '%s', '%s' )
			);
			$url_id = $wpdb->insert_id;
		}

		$wpdb->insert(
			$hits_table,
			array(
				'url_id'       => absint( $url_id ),
				'visitor_hash' => $this->visitor_hash( $user_agent ),
				'referrer'     => $referrer,
				'device'       => $this->detect_device( $user_agent ),
				'browser'      => $this->detect_browser( $user_agent ),
				'os'            => $this->detect_os( $user_agent ),
				'country'      => '',
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return new WP_REST_Response( array( 'recorded' => true ), 201 );
	}

	/**
	 * Apply privacy and settings exclusions to the current request.
	 *
	 * @return bool
	 */
	private function should_exclude_request() {
		$settings = get_option( 'sva_settings', array() );

		if ( ! empty( $settings['exclude_logged_in'] ) && is_user_logged_in() ) {
			return true;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$ip = $this->remote_ip();
		foreach ( (array) ( isset( $settings['exclude_ips'] ) ? $settings['exclude_ips'] : array() ) as $excluded_ip ) {
			if ( $ip && $ip === sanitize_text_field( $excluded_ip ) ) {
				return true;
			}
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		foreach ( (array) ( isset( $settings['exclude_pages'] ) ? $settings['exclude_pages'] : array() ) as $excluded_page ) {
			$excluded_page = sanitize_text_field( $excluded_page );
			if ( $excluded_page && false !== strpos( $request_uri, $excluded_page ) ) {
				return true;
			}
		}

		return ! empty( $settings['respect_dnt'] ) && ! empty( $_SERVER['HTTP_DNT'] ) && '1' === $_SERVER['HTTP_DNT'];
	}

	/**
	 * Return the request IP only for comparison; it is never persisted.
	 *
	 * @return string
	 */
	private function remote_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Hash an anonymized IP, user agent, and rotating daily site salt.
	 *
	 * @param string $user_agent User agent.
	 * @return string
	 */
	private function visitor_hash( $user_agent ) {
		$ip = $this->remote_ip();
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0';
			$ip       = implode( '.', $parts );
		} elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$ip = preg_replace( '/:[^:]+(:[^:]+){2}$/', '::0:0:0', $ip );
		}

		return hash( 'sha256', $ip . '|' . $user_agent . '|' . wp_salt( 'auth' ) . '|' . gmdate( 'Y-m-d' ) );
	}

	private function detect_device( $user_agent ) {
		return preg_match( '/mobile|android|iphone|ipad/i', $user_agent ) ? 'mobile' : 'desktop';
	}

	private function detect_browser( $user_agent ) {
		if ( preg_match( '/(edge|edg)\/?([\d\.]+)/i', $user_agent ) ) {
			return 'Edge';
		}
		if ( preg_match( '/chrome|crios/i', $user_agent ) ) {
			return 'Chrome';
		}
		if ( preg_match( '/firefox|fxios/i', $user_agent ) ) {
			return 'Firefox';
		}
		if ( preg_match( '/safari/i', $user_agent ) ) {
			return 'Safari';
		}
		return 'Other';
	}

	private function detect_os( $user_agent ) {
		if ( preg_match( '/windows/i', $user_agent ) ) {
			return 'Windows';
		}
		if ( preg_match( '/android/i', $user_agent ) ) {
			return 'Android';
		}
		if ( preg_match( '/iphone|ipad|mac os/i', $user_agent ) ) {
			return 'Apple';
		}
		if ( preg_match( '/linux/i', $user_agent ) ) {
			return 'Linux';
		}
		return 'Other';
	}
}