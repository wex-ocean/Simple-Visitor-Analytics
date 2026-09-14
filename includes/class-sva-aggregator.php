<?php
/**
 * Aggregates raw hits and prunes expired data.
 *
 * @package SimpleVisitorAnalytics
 * @license GPLv2
 */

class SVA_Aggregator {

	const HOURLY_HOOK = 'sva_hourly_aggregate';
	const DAILY_HOOK  = 'sva_daily_cleanup';

	/**
	 * Register cron callbacks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( self::HOURLY_HOOK, array( $this, 'aggregate' ) );
		add_action( self::DAILY_HOOK, array( $this, 'cleanup' ) );
	}

	/**
	 * Schedule aggregation and cleanup if they are not already scheduled.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOURLY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOURLY_HOOK );
		}

		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::DAILY_HOOK );
		}
	}

	/**
	 * Unschedule all plugin cron events.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOURLY_HOOK );
		wp_clear_scheduled_hook( self::DAILY_HOOK );
	}

	/**
	 * Rebuild retained daily aggregates, then prune expired raw hits.
	 *
	 * Rebuilding exact aggregates makes repeated hourly runs idempotent while
	 * raw rows remain inside the retention window.
	 *
	 * @return void
	 */
	public function aggregate() {
		global $wpdb;

		$hits_table  = $wpdb->prefix . 'sva_hits';
		$stats_table = $wpdb->prefix . 'sva_stats_daily';
		$cutoff      = $this->retention_cutoff();
		$where       = '';
		$values      = array();

		if ( false !== $cutoff ) {
			$where   = ' WHERE created_at >= %s';
			$values[] = $cutoff;
		} else {
			$where    = ' WHERE 1 = %d';
			$values[] = 1;
		}

		$sql = "SELECT url_id, visitor_hash, referrer, device, browser, os, country, created_at
			FROM {$hits_table}{$where} ORDER BY created_at ASC";
		$hits = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		$groups = array();

		foreach ( $hits as $hit ) {
			$date = gmdate( 'Y-m-d', strtotime( $hit->created_at ) );
			$key  = $date . ':' . absint( $hit->url_id );

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'date'      => $date,
					'url_id'    => absint( $hit->url_id ),
					'pageviews' => 0,
					'uniques'   => array(),
					'device'    => array(),
					'browser'   => array(),
					'os'        => array(),
					'referrer'  => array(),
					'country'   => array(),
				);
			}

			$group =& $groups[ $key ];
			$group['pageviews']++;
			$group['uniques'][ $hit->visitor_hash ] = true;
			$this->increment( $group['device'], $hit->device ? $hit->device : 'unknown' );
			$this->increment( $group['browser'], $hit->browser ? $hit->browser : 'unknown' );
			$this->increment( $group['os'], $hit->os ? $hit->os : 'unknown' );
			$this->increment( $group['referrer'], $hit->referrer ? $hit->referrer : 'direct' );
			$this->increment( $group['country'], $hit->country ? $hit->country : 'unknown' );
			unset( $group );
		}

		foreach ( $groups as $group ) {
			$device_breakdown = array(
				'device'  => $group['device'],
				'browser' => $group['browser'],
				'os'      => $group['os'],
			);
			$sql = "INSERT INTO {$stats_table}
				(date, url_id, pageviews, uniques, device_breakdown, referrer_breakdown, country_breakdown)
				VALUES (%s, %d, %d, %d, %s, %s, %s)
				ON DUPLICATE KEY UPDATE
				pageviews = VALUES(pageviews), uniques = VALUES(uniques),
				device_breakdown = VALUES(device_breakdown),
				referrer_breakdown = VALUES(referrer_breakdown),
				country_breakdown = VALUES(country_breakdown)";
			$wpdb->query(
				$wpdb->prepare(
					$sql,
					$group['date'],
					$group['url_id'],
					$group['pageviews'],
					count( $group['uniques'] ),
					wp_json_encode( $device_breakdown ),
					wp_json_encode( $group['referrer'] ),
					wp_json_encode( $group['country'] )
				)
			);
		}

		$this->delete_expired_hits( $cutoff );
	}

	/**
	 * Delete all raw hits beyond the configured retention window.
	 *
	 * @return void
	 */
	public function cleanup() {
		$this->delete_expired_hits( $this->retention_cutoff() );
	}

	/**
	 * Delete raw hits older than a cutoff date.
	 *
	 * @param string|false $cutoff UTC cutoff or false for unlimited retention.
	 * @return void
	 */
	private function delete_expired_hits( $cutoff ) {
		if ( false === $cutoff ) {
			return;
		}

		global $wpdb;
		$hits_table = $wpdb->prefix . 'sva_hits';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$hits_table} WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * Calculate the configured raw-hit retention cutoff.
	 *
	 * @return string|false
	 */
	private function retention_cutoff() {
		$settings = get_option( 'sva_settings', array() );
		$days     = isset( $settings['retention_days'] ) ? $settings['retention_days'] : 90;

		if ( 'forever' === $days || 0 === (int) $days ) {
			return false;
		}

		$days = max( 1, absint( $days ) );
		return gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
	}

	/**
	 * Increment a named breakdown bucket.
	 *
	 * @param array  $breakdown Breakdown values.
	 * @param string $key       Bucket name.
	 * @return void
	 */
	private function increment( &$breakdown, $key ) {
		if ( ! isset( $breakdown[ $key ] ) ) {
			$breakdown[ $key ] = 0;
		}
		$breakdown[ $key ]++;
	}
}