<?php
/**
 * Integrates Simple Visitor Analytics with WordPress privacy tools.
 *
 * @package SimpleVisitorAnalytics
 * @license GPLv2
 */

class SVA_Privacy {

	/**
	 * Register privacy exporter, eraser, and policy hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
	}

	/**
	 * Register the plugin in Tools > Export Personal Data.
	 *
	 * Analytics records contain no identity-linked personal data. The exporter
	 * is still registered so WordPress can document that this plugin was checked.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['simple-visitor-analytics'] = array(
			'exporter_friendly_name' => __( 'Simple Visitor Analytics', 'simple-visitor-analytics' ),
			'callback'              => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Register the plugin in Tools > Erase Personal Data.
	 *
	 * Visitor hashes are daily salted and not tied to an account or other
	 * identity, so there is no individual record to erase by email address.
	 * The callback nevertheless completes the standard WordPress eraser flow.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['simple-visitor-analytics'] = array(
			'eraser_friendly_name' => __( 'Simple Visitor Analytics', 'simple-visitor-analytics' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Return the standard completed exporter response.
	 *
	 * @param string $email_address Request email address.
	 * @param int    $page          Export page number.
	 * @return array
	 */
	public function export_personal_data( $email_address, $page = 1 ) {
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	/**
	 * Return the standard completed eraser response.
	 *
	 * No analytics rows are removed here because the stored hash cannot be
	 * reliably matched to the supplied email address. Site-wide deletion is
	 * available separately in the plugin settings and requires administrator
	 * confirmation.
	 *
	 * @param string $email_address Request email address.
	 * @return array
	 */
	public function erase_personal_data( $email_address ) {
		return array(
			'messages'      => array( __( 'Simple Visitor Analytics does not store personal data linked to an individual email address.', 'simple-visitor-analytics' ) ),
			'items_removed' => false,
			'items_retained' => false,
			'done'          => true,
		);
	}

	/**
	 * Add a privacy policy suggestion in Tools > Privacy.
	 *
	 * @return void
	 */
	public function add_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . esc_html__( 'Simple Visitor Analytics collects privacy-focused, aggregate website usage data. It does not use cookies or persistent visitor identifiers, and it does not store raw IP addresses.', 'simple-visitor-analytics' ) . '</p>';
		$content .= '<p>' . esc_html__( 'For daily uniqueness estimates, the plugin hashes an anonymized IP address (the last IPv4 octet is removed), the browser user agent, a site salt, and the current date. The daily salt means the resulting visitor hash is rotated and is not intended to identify an individual or track visitors across sites.', 'simple-visitor-analytics' ) . '</p>';
		$content .= '<p>' . esc_html__( 'When country information is available, only a country code is retained. No city-level location data is stored. Analytics data is stored in this site\'s own WordPress database and is subject to the site owner\'s configured retention period.', 'simple-visitor-analytics' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Simple Visitor Analytics', 'simple-visitor-analytics' ), wp_kses_post( $content ) );
	}
}
