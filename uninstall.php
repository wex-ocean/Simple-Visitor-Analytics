<?php
/**
 * Remove plugin data when the site owner has opted in.
 *
 * @package SimpleVisitorAnalytics
 * @license GPLv2
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'sva_settings', array() );

if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'sva_hits',
	$wpdb->prefix . 'sva_urls',
	$wpdb->prefix . 'sva_stats_daily',
);

foreach ( $tables as $table ) {
	// Table names are built only from the trusted WordPress prefix and fixed suffixes.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

delete_option( 'sva_settings' );
