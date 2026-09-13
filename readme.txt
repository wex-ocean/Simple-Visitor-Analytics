=== Simple Visitor Analytics ===
Contributors: simplevisitoranalytics
Tags: analytics, statistics, privacy, visitor tracking, dashboard
Requires at least: 5.8
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-friendly, lightweight visitor analytics for WordPress.

== Description ==

Simple Visitor Analytics provides lightweight, privacy-friendly website analytics without cookies, cross-site tracking, or raw IP storage.

Analytics data is stored locally in your WordPress database. Raw hits are short-lived and aggregated into daily statistics. The default raw data retention period is 90 days and can be configured in the plugin settings.

== Features ==

* No cookies or persistent visitor identifiers.
* No raw IP addresses are stored.
* Local database storage with no external services.
* Daily aggregated statistics for fast dashboard queries.
* Configurable retention and privacy exclusions.
* Data is kept on uninstall unless deletion is explicitly enabled.

== Installation ==

1. Upload the `simple-visitor-analytics` directory to `/wp-content/plugins/`.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Configure analytics and privacy settings from the plugin settings page.

== Frequently Asked Questions ==

= Does the plugin use cookies? =

No. The plugin does not use cookies or persistent visitor identifiers.

= Is an IP address stored? =

No. Raw IP addresses are not stored. Visitor uniqueness uses a short-lived, privacy-focused hash.

= What happens when I uninstall the plugin? =

Data is retained by default. Enable the delete-data-on-uninstall setting before uninstalling if you want the plugin to remove its tables and settings.

== Privacy ==

This plugin stores analytics data in the site's own WordPress database. Site owners should review their configuration and privacy policy to ensure they meet applicable legal requirements.

== Changelog ==

= 1.0.0 =
* Initial release.
