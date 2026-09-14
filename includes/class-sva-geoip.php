<?php
/**
 * Resolves country codes from a local MaxMind GeoLite2-Country database.
 *
 * @package SimpleVisitorAnalytics
 * @license GPLv2
 */

class SVA_GeoIP {

	const DATABASE_PATH = 'data/GeoLite2-Country.mmdb';

	/**
	 * @var MaxMind\Db\Reader|null
	 */
	private $reader;

	/**
	 * Open the local database when the optional MaxMind reader is available.
	 *
	 * No network request is made here or during pageview tracking.
	 */
	public function __construct() {
		$autoload = SVA_PLUGIN_DIR . 'vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}

		$database = SVA_PLUGIN_DIR . self::DATABASE_PATH;
		if ( ! file_exists( $database ) || ! class_exists( 'MaxMind\\Db\\Reader' ) ) {
			return;
		}

		try {
			$this->reader = new MaxMind\Db\Reader( $database );
		} catch ( Exception $exception ) {
			$this->reader = null;
		}
	}

	/**
	 * Resolve a country ISO code from an IP address.
	 *
	 * @param string $ip Validated request IP.
	 * @return string|null Two-letter country code, or null when unavailable.
	 */
	public function lookup_country( $ip ) {
		if ( ! $this->reader || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return null;
		}

		try {
			$record = $this->reader->get( $ip );
			$code   = isset( $record['country']['iso_code'] ) ? strtoupper( $record['country']['iso_code'] ) : '';
			return preg_match( '/^[A-Z]{2}$/', $code ) ? $code : null;
		} catch ( Exception $exception ) {
			return null;
		}
	}

	/**
	 * Return the configured database path when present.
	 *
	 * @return string|null
	 */
	public static function database_path() {
		$path = SVA_PLUGIN_DIR . self::DATABASE_PATH;
		return file_exists( $path ) ? $path : null;
	}

	/**
	 * Store a manually uploaded MaxMind database.
	 *
	 * This accepts only a local administrator upload. The plugin never silently
	 * downloads or contacts MaxMind or another external service.
	 *
	 * @param array $file Uploaded file data.
	 * @return true|WP_Error
	 */
	public static function install_uploaded_database( $file ) {
		if ( empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'sva_geoip_upload', __( 'The GeoIP database upload failed.', 'simple-visitor-analytics' ) );
		}

		$filename = sanitize_file_name( $file['name'] );
		if ( 'mmdb' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'sva_geoip_type', __( 'Please upload a .mmdb MaxMind database file.', 'simple-visitor-analytics' ) );
		}

		if ( ! is_uploaded_file( $file['tmp_name'] ) || filesize( $file['tmp_name'] ) < 1024 ) {
			return new WP_Error( 'sva_geoip_invalid', __( 'The uploaded GeoIP database is invalid.', 'simple-visitor-analytics' ) );
		}

		$directory = dirname( SVA_PLUGIN_DIR . self::DATABASE_PATH );
		if ( ! wp_mkdir_p( $directory ) ) {
			return new WP_Error( 'sva_geoip_directory', __( 'The GeoIP database directory could not be created.', 'simple-visitor-analytics' ) );
		}

		$destination = SVA_PLUGIN_DIR . self::DATABASE_PATH;
		if ( ! move_uploaded_file( $file['tmp_name'], $destination ) ) {
			return new WP_Error( 'sva_geoip_move', __( 'The GeoIP database could not be stored.', 'simple-visitor-analytics' ) );
		}

		return true;
	}
}
