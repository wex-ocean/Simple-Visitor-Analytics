<?php
/**
 * Fired during plugin deactivation.
 *
 * @package SimpleVisitorAnalytics
 * @license GPLv2
 */

class SVA_Deactivator {

	/**
	 * Deactivate the plugin without deleting analytics data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		SVA_Aggregator::unschedule();
	}
}
