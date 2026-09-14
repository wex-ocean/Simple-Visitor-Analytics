<?php
/**
 * Fired during plugin activation.
 *
 * @package SimpleVisitorAnalytics
 * @license GPLv2
 */

class SVA_Activator {

	/**
	 * Create plugin tables and default settings.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$hits_table      = $wpdb->prefix . 'sva_hits';
		$urls_table      = $wpdb->prefix . 'sva_urls';
		$stats_table     = $wpdb->prefix . 'sva_stats_daily';

		$hits_sql = "CREATE TABLE {$hits_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url_id bigint(20) unsigned NOT NULL,
			visitor_hash char(64) NOT NULL,
			referrer varchar(2048) NOT NULL DEFAULT '',
			device varchar(100) NOT NULL DEFAULT '',
			browser varchar(100) NOT NULL DEFAULT '',
			os varchar(100) NOT NULL DEFAULT '',
			country char(2) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY url_id (url_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$urls_sql = "CREATE TABLE {$urls_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url varchar(2048) NOT NULL,
			title text NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$stats_sql = "CREATE TABLE {$stats_table} (
			date date NOT NULL,
			url_id bigint(20) unsigned NOT NULL,
			pageviews bigint(20) unsigned NOT NULL DEFAULT 0,
			uniques bigint(20) unsigned NOT NULL DEFAULT 0,
			device_breakdown longtext NOT NULL,
			referrer_breakdown longtext NOT NULL,
			country_breakdown longtext NOT NULL,
			PRIMARY KEY  (date,url_id),
			KEY url_id (url_id),
			KEY date (date)
		) {$charset_collate};";

		dbDelta( $hits_sql );
		dbDelta( $urls_sql );
		dbDelta( $stats_sql );

		$defaults = array(
			'exclude_logged_in'       => true,
			'respect_dnt'             => false,
			'retention_days'          => 90,
			'exclude_ips'             => array(),
			'exclude_pages'           => array(),
			'delete_data_on_uninstall' => false,
		);

		if ( false === get_option( 'sva_settings', false ) ) {
			add_option( 'sva_settings', $defaults );
		}

		SVA_Aggregator::schedule();
	}
}
