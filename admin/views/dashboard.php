<?php
/**
 * Analytics dashboard view.
 *
 * @package SimpleVisitorAnalytics
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap sva-dashboard">
	<h1><?php esc_html_e( 'Analytics', 'simple-visitor-analytics' ); ?></h1>

	<div class="sva-toolbar">
		<label for="sva-period"><?php esc_html_e( 'Period', 'simple-visitor-analytics' ); ?></label>
		<select id="sva-period">
			<option value="daily"><?php esc_html_e( 'Daily', 'simple-visitor-analytics' ); ?></option>
			<option value="weekly"><?php esc_html_e( 'Weekly', 'simple-visitor-analytics' ); ?></option>
			<option value="monthly"><?php esc_html_e( 'Monthly', 'simple-visitor-analytics' ); ?></option>
			<option value="custom"><?php esc_html_e( 'Custom range', 'simple-visitor-analytics' ); ?></option>
		</select>
		<span id="sva-custom-range" hidden>
			<label class="screen-reader-text" for="sva-start-date"><?php esc_html_e( 'Start date', 'simple-visitor-analytics' ); ?></label>
			<input type="date" id="sva-start-date">
			<label class="screen-reader-text" for="sva-end-date"><?php esc_html_e( 'End date', 'simple-visitor-analytics' ); ?></label>
			<input type="date" id="sva-end-date">
		</span>
	</div>

	<div class="sva-cards">
		<div class="sva-card"><span><?php esc_html_e( 'Pageviews', 'simple-visitor-analytics' ); ?></span><strong id="sva-pageviews">0</strong></div>
		<div class="sva-card"><span><?php esc_html_e( 'Uniques', 'simple-visitor-analytics' ); ?></span><strong id="sva-uniques">0</strong></div>
		<div class="sva-card"><span><?php esc_html_e( 'Average pages / session', 'simple-visitor-analytics' ); ?></span><strong id="sva-average">0</strong></div>
	</div>

	<section class="sva-panel sva-chart-panel">
		<h2><?php esc_html_e( 'Traffic over time', 'simple-visitor-analytics' ); ?></h2>
		<div class="sva-chart-wrap"><canvas id="sva-line-chart"></canvas></div>
	</section>

	<div class="sva-grid">
		<section class="sva-panel">
			<h2><?php esc_html_e( 'Top pages', 'simple-visitor-analytics' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Page', 'simple-visitor-analytics' ); ?></th><th><?php esc_html_e( 'Pageviews', 'simple-visitor-analytics' ); ?></th><th><?php esc_html_e( 'Uniques', 'simple-visitor-analytics' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $data['top_pages'] as $page ) : ?>
					<tr><td><?php echo esc_html( $page['title'] ); ?></td><td><?php echo esc_html( number_format_i18n( $page['pageviews'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $page['uniques'] ) ); ?></td></tr>
				<?php endforeach; ?>
				<?php if ( empty( $data['top_pages'] ) ) : ?><tr><td colspan="3"><?php esc_html_e( 'No aggregated data yet.', 'simple-visitor-analytics' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</section>
		<section class="sva-panel">
			<h2><?php esc_html_e( 'Traffic sources', 'simple-visitor-analytics' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Referrer', 'simple-visitor-analytics' ); ?></th><th><?php esc_html_e( 'Visits', 'simple-visitor-analytics' ); ?></th></tr></thead><tbody>
				<?php arsort( $data['referrers'] ); foreach ( array_slice( $data['referrers'], 0, 10, true ) as $referrer => $count ) : ?><tr><td><?php echo esc_html( $referrer ); ?></td><td><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr><?php endforeach; ?>
				<?php if ( empty( $data['referrers'] ) ) : ?><tr><td colspan="2"><?php esc_html_e( 'No aggregated data yet.', 'simple-visitor-analytics' ); ?></td></tr><?php endif; ?>
			</tbody></table>
		</section>
	</div>

	<div class="sva-breakdowns">
		<section class="sva-panel"><h2><?php esc_html_e( 'Devices', 'simple-visitor-analytics' ); ?></h2><div class="sva-small-chart"><canvas id="sva-device-chart"></canvas></div></section>
		<section class="sva-panel"><h2><?php esc_html_e( 'Browsers', 'simple-visitor-analytics' ); ?></h2><div class="sva-small-chart"><canvas id="sva-browser-chart"></canvas></div></section>
		<section class="sva-panel"><h2><?php esc_html_e( 'Operating systems', 'simple-visitor-analytics' ); ?></h2><div class="sva-small-chart"><canvas id="sva-os-chart"></canvas></div></section>
	</div>

	<section class="sva-panel">
		<h2><?php esc_html_e( 'Countries', 'simple-visitor-analytics' ); ?></h2>
		<div class="sva-country-list">
			<?php arsort( $data['countries'] ); foreach ( array_slice( $data['countries'], 0, 20, true ) as $country => $count ) : ?><span><strong><?php echo esc_html( $country ); ?></strong> <?php echo esc_html( number_format_i18n( $count ) ); ?></span><?php endforeach; ?>
			<?php if ( empty( $data['countries'] ) ) : ?><span><?php esc_html_e( 'No aggregated data yet.', 'simple-visitor-analytics' ); ?></span><?php endif; ?>
		</div>
	</section>
</div>
