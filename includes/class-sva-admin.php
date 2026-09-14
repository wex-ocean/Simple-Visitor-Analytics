<?php
/**
 * Registers the analytics dashboard.
 *
 * @package SimpleVisitorAnalytics
 */

class SVA_Admin {

	const MENU_SLUG = 'sva-analytics';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the top-level Analytics screen.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Analytics', 'simple-visitor-analytics' ),
			__( 'Analytics', 'simple-visitor-analytics' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-chart-area',
			30
		);
	}

	/**
	 * Enqueue assets only on the Analytics screen.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'sva-dashboard', SVA_PLUGIN_URL . 'admin/css/dashboard.css', array(), SVA_VERSION );
		wp_enqueue_script( 'sva-chartjs', SVA_PLUGIN_URL . 'admin/js/chart.umd.js', array(), '4.4.7', true );
		wp_enqueue_script( 'sva-dashboard', SVA_PLUGIN_URL . 'admin/js/dashboard.js', array( 'sva-chartjs' ), SVA_VERSION, true );
		wp_localize_script( 'sva-dashboard', 'SVA_DASHBOARD_DATA', $this->get_dashboard_data() );
	}

	/**
	 * Render the dashboard view.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view analytics.', 'simple-visitor-analytics' ) );
		}

		$data = $this->get_dashboard_data();
		require SVA_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Build dashboard data from aggregated statistics only.
	 *
	 * @return array
	 */
	private function get_dashboard_data() {
		global $wpdb;

		$stats_table = $wpdb->prefix . 'sva_stats_daily';
		$urls_table  = $wpdb->prefix . 'sva_urls';
		$start       = gmdate( 'Y-m-d', strtotime( '-365 days' ) );
		$end         = gmdate( 'Y-m-d' );
		$rows        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.date, s.url_id, s.pageviews, s.uniques, s.device_breakdown, s.referrer_breakdown, s.country_breakdown, u.url, u.title
				FROM {$stats_table} AS s
				LEFT JOIN {$urls_table} AS u ON u.id = s.url_id
				WHERE s.date BETWEEN %s AND %s
				ORDER BY s.date ASC",
				$start,
				$end
			)
		);

		$data = array(
			'rows'        => array(),
			'top_pages'   => array(),
			'referrers'   => array(),
			'countries'   => array(),
			'device'      => array(),
			'browser'     => array(),
			'os'          => array(),
			'generated_at' => current_time( 'mysql' ),
			'labels'      => array(
				'pageviews' => __( 'Pageviews', 'simple-visitor-analytics' ),
				'uniques'   => __( 'Uniques', 'simple-visitor-analytics' ),
				'devices'   => __( 'Devices', 'simple-visitor-analytics' ),
				'browsers'  => __( 'Browsers', 'simple-visitor-analytics' ),
				'os'        => __( 'Operating systems', 'simple-visitor-analytics' ),
			),
		);
		$pages = array();

		foreach ( $rows as $row ) {
			$device = $this->decode_json( $row->device_breakdown );
			$referrer = $this->decode_json( $row->referrer_breakdown );
			$country = $this->decode_json( $row->country_breakdown );
			$device_counts = isset( $device['device'] ) ? $device['device'] : array();
			$browser_counts = isset( $device['browser'] ) ? $device['browser'] : array();
			$os_counts = isset( $device['os'] ) ? $device['os'] : array();

			$data['rows'][] = array(
				'date'      => $row->date,
				'pageviews' => (int) $row->pageviews,
				'uniques'   => (int) $row->uniques,
			);
			$this->merge_counts( $data['referrers'], $referrer );
			$this->merge_counts( $data['countries'], $country );
			$this->merge_counts( $data['device'], $device_counts );
			$this->merge_counts( $data['browser'], $browser_counts );
			$this->merge_counts( $data['os'], $os_counts );

			$page_key = (string) $row->url_id;
			if ( ! isset( $pages[ $page_key ] ) ) {
				$pages[ $page_key ] = array(
					'title'     => $row->title ? $row->title : $row->url,
					'url'       => $row->url,
					'pageviews' => 0,
					'uniques'   => 0,
				);
			}
			$pages[ $page_key ]['pageviews'] += (int) $row->pageviews;
			$pages[ $page_key ]['uniques'] += (int) $row->uniques;
		}

		$data['top_pages'] = array_values( $pages );
		usort(
			$data['top_pages'],
			function ( $first, $second ) {
				return $second['pageviews'] <=> $first['pageviews'];
			}
		);
		$data['top_pages'] = array_slice( $data['top_pages'], 0, 10 );

		return $data;
	}

	/**
	 * Decode a stored JSON breakdown safely.
	 *
	 * @param string $value JSON value.
	 * @return array
	 */
	private function decode_json( $value ) {
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Merge breakdown counts into a dashboard total.
	 *
	 * @param array $target Aggregate output.
	 * @param array $counts Breakdown counts.
	 * @return void
	 */
	private function merge_counts( &$target, $counts ) {
		foreach ( $counts as $label => $count ) {
			$label = sanitize_text_field( $label );
			if ( ! isset( $target[ $label ] ) ) {
				$target[ $label ] = 0;
			}
			$target[ $label ] += absint( $count );
		}
	}
}
