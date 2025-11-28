<?php
/**
 * Gestión local de códigos postales GeoNames.
 *
 * Encapsula la creación de tabla, importador por lotes,
 * cron y endpoint de consulta local.
 *
 * @package WC_APG_City
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL de descarga de códigos postales GeoNames.
 *
 * @var string
 */
define( 'APG_CITY_POSTCODES_URL', 'https://download.geonames.org/export/zip/allCountries.zip' );

/**
 * Límite de líneas que se importan por lote para no agotar recursos.
 *
 * @var int
 */
define( 'APG_CITY_IMPORT_CHUNK', 20000 );

/**
 * Hook usado para la programación de actualización semanal.
 *
 * @var string
 */
define( 'APG_CITY_CRON_HOOK', 'apg_city_update_postcodes_event' );

/**
 * Nombre de la tabla para almacenar códigos postales.
 *
 * @return string
 */
function apg_city_get_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'apg_city_postcodes';
}

/**
 * Comprueba si la tabla de códigos postales existe.
 *
 * @return bool
 */
function apg_city_table_exists() {
	global $wpdb;

	$table = esc_sql( apg_city_get_table_name() );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct check to avoid extra overhead.
	$found_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

	return $found_table === $table;
}

/**
 * Comprueba si hay datos locales disponibles para usar.
 *
 * @return bool
 */
function apg_city_local_data_available() {
	$has_rows = (int) get_option( 'apg_city_rows', 0 );

	return $has_rows > 0 && apg_city_table_exists();
}

/**
 * Añade un intervalo semanal al cron de WordPress.
 *
 * @param array<string,mixed> $schedules Intervalos de cron registrados.
 *
 * @return array<string,mixed>
 */
function apg_city_cron_schedules( $schedules ) {
	if ( ! isset( $schedules[ 'monthly' ] ) ) {
		$schedules[ 'monthly' ] = [
			'interval' => MONTH_IN_SECONDS,
			'display'  => __( 'Once Monthly', 'wc-apg-city' ),
		];
	}

	return $schedules;
}

/**
 * Crea/actualiza la tabla de códigos postales.
 *
 * @return void
 */
function apg_city_create_table() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name      = apg_city_get_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		country_code varchar(2) NOT NULL,
		postal_code varchar(20) NOT NULL,
		place_name varchar(180) NOT NULL,
		admin_name1 varchar(100) DEFAULT '' NOT NULL,
		admin_code1 varchar(20) DEFAULT '' NOT NULL,
		admin_name2 varchar(100) DEFAULT '' NOT NULL,
		admin_code2 varchar(20) DEFAULT '' NOT NULL,
		admin_name3 varchar(100) DEFAULT '' NOT NULL,
		admin_code3 varchar(20) DEFAULT '' NOT NULL,
		latitude decimal(10,7) NOT NULL DEFAULT 0,
		longitude decimal(10,7) NOT NULL DEFAULT 0,
		accuracy tinyint(2) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		UNIQUE KEY idx_country_postal_place (country_code, postal_code, place_name),
		KEY country_postal (country_code, postal_code),
		KEY postal_code (postal_code)
	) $charset_collate;";

	dbDelta( $sql );
}

/**
 * Recupera el estado del importador.
 *
 * @return array<string,mixed>
 */
function apg_city_get_import_state() {
	$state = get_option( 'apg_city_import_state', [] );

	return is_array( $state ) ? $state : [];
}

/**
 * Guarda el estado del importador.
 *
 * @param array<string,mixed> $state Estado a guardar.
 *
 * @return void
 */
function apg_city_set_import_state( $state ) {
	update_option( 'apg_city_import_state', $state, false );
}

/**
 * Limpia estado y archivos temporales del importador.
 *
 * @return void
 */
function apg_city_clear_import_state() {
	$state = apg_city_get_import_state();

	if ( isset( $state[ 'file' ] ) && is_string( $state[ 'file' ] ) && file_exists( $state[ 'file' ] ) ) {
		wp_delete_file( $state[ 'file' ] );
	}

	delete_option( 'apg_city_import_state' );
	delete_transient( 'apg_city_import_lock' );
}

/**
 * Prepara el sistema de ficheros de WordPress para operaciones de escritura.
 *
 * @return bool
 */
function apg_city_init_filesystem() {
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	$initialized = WP_Filesystem();

	return (bool) $initialized;
}

/**
 * Prepara el archivo de trabajo descargándolo y descomprimiéndolo.
 *
 * @return array<string,mixed>|null Estado inicial o null en caso de error.
 */
function apg_city_prepare_import_file() {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	wp_raise_memory_limit( 'admin' );

	$upload_dir = wp_upload_dir();
	$target_dir = trailingslashit( $upload_dir[ 'basedir' ] ) . 'apg-city';

	$txt_file = trailingslashit( $target_dir ) . 'allCountries.txt';

	if ( ! file_exists( $txt_file ) ) {
		if ( ! wp_mkdir_p( $target_dir ) ) {
			return null;
		}

		$temp_file = download_url( APG_CITY_POSTCODES_URL, 300 );

		if ( is_wp_error( $temp_file ) ) {
			return null;
		}

		if ( ! apg_city_init_filesystem() ) {
			wp_delete_file( $temp_file );
			return null;
		}

		$unzipped = unzip_file( $temp_file, $target_dir );

		if ( is_wp_error( $unzipped ) ) {
			// Fallback a ZipArchive directo para entornos sin FS credentials.
			if ( class_exists( 'ZipArchive' ) ) {
				$zip = new ZipArchive();
				if ( true === $zip->open( $temp_file ) ) {
					$zip->extractTo( $target_dir );
					$zip->close();
					wp_delete_file( $temp_file );
				} else {
					wp_delete_file( $temp_file );
					return null;
				}
			} else {
				wp_delete_file( $temp_file );
				return null;
			}
		} else {
			wp_delete_file( $temp_file );
		}
	}

	if ( ! file_exists( $txt_file ) ) {
		return null;
	}

	$hash = md5_file( $txt_file );

	return [
		'file'      => $txt_file,
		'offset'    => 0,
		'rows'      => 0,
		'hash'      => $hash,
		'started'   => time(),
	];
}

/**
 * Procesa un bloque de líneas del archivo local.
 *
 * @param array<string,mixed> $state Estado actual del importador.
 *
 * @return array{finished:bool,state:array<string,mixed>} Resultado.
 */
function apg_city_process_import_chunk( $state ) {
	global $wpdb;

	$result = [
		'finished' => false,
		'state'    => $state,
	];

	if ( empty( $state[ 'file' ] ) || ! file_exists( $state[ 'file' ] ) ) {
		return $result;
	}

	$table_name = esc_sql( apg_city_get_table_name() );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	$handle = fopen( $state[ 'file' ], 'r' );

	if ( ! $handle ) {
		return $result;
	}

	if ( isset( $state[ 'offset' ] ) && $state[ 'offset' ] > 0 ) {
		fseek( $handle, (int) $state[ 'offset' ] );
	}

	$placeholders = [];
	$values       = [];
	$batch_size   = 300;
	$rows_this_run = 0;

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fgets
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	while ( $rows_this_run < APG_CITY_IMPORT_CHUNK && ( $line = fgets( $handle ) ) !== false ) {
		$parts = explode( "\t", trim( $line ) );

		if ( count( $parts ) < 12 ) {
			continue;
		}

		$placeholders[] = "(%s,%s,%s,%s,%s,%s,%s,%s,%s,%f,%f,%d)";

		$values[] = $parts[0]; // country_code.
		$values[] = $parts[1]; // postal_code.
		$values[] = $parts[2]; // place_name.
		$values[] = $parts[3]; // admin_name1.
		$values[] = $parts[4]; // admin_code1.
		$values[] = $parts[5]; // admin_name2.
		$values[] = $parts[6]; // admin_code2.
		$values[] = $parts[7]; // admin_name3.
		$values[] = $parts[8]; // admin_code3.
		$values[] = (float) $parts[9]; // latitude.
		$values[] = (float) $parts[10]; // longitude.
		$values[] = (int) $parts[11]; // accuracy.

		if ( count( $placeholders ) >= $batch_size ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders prepared below.
			$query = $wpdb->prepare(
				"INSERT INTO `$table_name` (country_code, postal_code, place_name, admin_name1, admin_code1, admin_name2, admin_code2, admin_name3, admin_code3, latitude, longitude, accuracy) VALUES " . implode( ',', $placeholders ) . " ON DUPLICATE KEY UPDATE admin_name1=VALUES(admin_name1), admin_code1=VALUES(admin_code1), admin_name2=VALUES(admin_name2), admin_code2=VALUES(admin_code2), admin_name3=VALUES(admin_name3), admin_code3=VALUES(admin_code3), latitude=VALUES(latitude), longitude=VALUES(longitude), accuracy=VALUES(accuracy)",
				$values
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query prepared above.
			$wpdb->query( $query );
			$rows_this_run += count( $placeholders );
			$placeholders = [];
			$values       = [];
		}
	}

	if ( ! empty( $placeholders ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders prepared below.
		$query = $wpdb->prepare(
			"INSERT INTO `$table_name` (country_code, postal_code, place_name, admin_name1, admin_code1, admin_name2, admin_code2, admin_name3, admin_code3, latitude, longitude, accuracy) VALUES " . implode( ',', $placeholders ) . " ON DUPLICATE KEY UPDATE admin_name1=VALUES(admin_name1), admin_code1=VALUES(admin_code1), admin_name2=VALUES(admin_name2), admin_code2=VALUES(admin_code2), admin_name3=VALUES(admin_name3), admin_code3=VALUES(admin_code3), latitude=VALUES(latitude), longitude=VALUES(longitude), accuracy=VALUES(accuracy)",
			$values
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query prepared above.
		$wpdb->query( $query );
		$rows_this_run += count( $placeholders );
	}
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	$state[ 'offset' ] = ftell( $handle );

	$at_end = feof( $handle );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	fclose( $handle );

	if ( $at_end || $rows_this_run === 0 ) {
		$result[ 'finished' ] = true;
	}

	$result[ 'state' ] = $state;

	return $result;
}

/**
 * Importa/continúa la importación de códigos postales GeoNames en lotes.
 *
 * @return void
 */
function apg_city_refresh_data() {
	if ( get_transient( 'apg_city_import_lock' ) ) {
		return;
	}

	set_transient( 'apg_city_import_lock', 1, 10 * MINUTE_IN_SECONDS );

	apg_city_create_table();

	$state = apg_city_get_import_state();

	if ( empty( $state ) || empty( $state[ 'file' ] ) || ! file_exists( $state[ 'file' ] ) ) {
		$state = apg_city_prepare_import_file();
		if ( empty( $state ) ) {
			delete_transient( 'apg_city_import_lock' );
			return;
		}
	}

	if ( empty( $state[ 'hash' ] ) && ! empty( $state[ 'file' ] ) && file_exists( $state[ 'file' ] ) ) {
		$state[ 'hash' ] = md5_file( $state[ 'file' ] );
	}

	$last_hash = get_option( 'apg_city_last_hash' );

	if ( $last_hash && ! empty( $state[ 'hash' ] ) && $last_hash === $state[ 'hash' ] && apg_city_local_data_available() ) {
		apg_city_clear_import_state();
		update_option( 'apg_city_last_import', time() );
		delete_transient( 'apg_city_seed_scheduled' );
		delete_transient( 'apg_city_import_lock' );
		return;
	}

	$result = apg_city_process_import_chunk( $state );

	if ( $result[ 'finished' ] ) {
		if ( isset( $result[ 'state' ][ 'file' ] ) && file_exists( $result[ 'state' ][ 'file' ] ) ) {
			wp_delete_file( $result[ 'state' ][ 'file' ] );
		}
		apg_city_clear_import_state();
		update_option( 'apg_city_last_import', time() );
		update_option( 'apg_city_last_hash', isset( $result[ 'state' ][ 'hash' ] ) ? $result[ 'state' ][ 'hash' ] : '' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- count for status.
		global $wpdb;
		$table_name = esc_sql( apg_city_get_table_name() );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name ok.
		$count      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table_name`" );
		update_option( 'apg_city_rows', $count );
		delete_transient( 'apg_city_seed_scheduled' );
	} else {
		apg_city_set_import_state( $result[ 'state' ] );
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, APG_CITY_CRON_HOOK );
	}

	delete_transient( 'apg_city_import_lock' );
}

/**
 * Consulta APIs externas (GeoNames/Google) con cache en transient.
 *
 * @return void
 */
function apg_city_api_lookup() {
	check_ajax_referer( 'apg_city_lookup', 'nonce' );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$api      = isset( $_POST[ 'api' ] ) ? sanitize_key( wp_unslash( $_POST[ 'api' ] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$postcode = isset( $_POST[ 'postcode' ] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST[ 'postcode' ] ) ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$country  = isset( $_POST[ 'country' ] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST[ 'country' ] ) ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$lang     = isset( $_POST[ 'lang' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'lang' ] ) ) : '';

	if ( empty( $api ) || empty( $postcode ) || empty( $country ) ) {
		wp_send_json_error(
			[
				'message' => __( 'Missing parameters.', 'wc-apg-city' ),
			]
		);
	}

	$cache_key = 'apg_city_api_' . $api . '_' . $country . '_' . $postcode;
	$cached    = get_transient( $cache_key );

	if ( $cached ) {
		wp_send_json_success(
			[
				'postalcodes' => $cached,
				'country'     => $country,
			]
		);
	}

	$settings = get_option( 'apg_city_settings', [] );

	if ( 'geonames' === $api ) {
		$username = isset( $settings[ 'geonames_user' ] ) ? sanitize_text_field( $settings[ 'geonames_user' ] ) : '';
		if ( ! $username ) {
			wp_send_json_error(
				[
					'message' => __( 'GeoNames username missing.', 'wc-apg-city' ),
				]
			);
		}
		$url      = 'https://www.geonames.org/postalCodeLookupJSON?postalcode=' . rawurlencode( $postcode ) . '&country=' . rawurlencode( $country ) . '&username=' . rawurlencode( $username );
		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => $response->get_error_message() ] );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$rows = [];
		if ( isset( $body[ 'postalcodes' ] ) && is_array( $body[ 'postalcodes' ] ) ) {
			foreach ( $body[ 'postalcodes' ] as $row ) {
				$rows[] = [
					'countryCode' => isset( $row[ 'countryCode' ] ) ? $row[ 'countryCode' ] : $country,
					'postalCode'  => isset( $row[ 'postalCode' ] ) ? $row[ 'postalCode' ] : $postcode,
					'placeName'   => isset( $row[ 'placeName' ] ) ? $row[ 'placeName' ] : '',
					'adminName1'  => isset( $row[ 'adminName1' ] ) ? $row[ 'adminName1' ] : '',
					'adminCode1'  => isset( $row[ 'adminCode1' ] ) ? $row[ 'adminCode1' ] : '',
					'adminName2'  => isset( $row[ 'adminName2' ] ) ? $row[ 'adminName2' ] : '',
					'adminCode2'  => isset( $row[ 'adminCode2' ] ) ? $row[ 'adminCode2' ] : '',
					'adminName3'  => isset( $row[ 'adminName3' ] ) ? $row[ 'adminName3' ] : '',
					'adminCode3'  => isset( $row[ 'adminCode3' ] ) ? $row[ 'adminCode3' ] : '',
					'latitude'    => isset( $row[ 'lat' ] ) ? $row[ 'lat' ] : '',
					'longitude'   => isset( $row[ 'lng' ] ) ? $row[ 'lng' ] : '',
					'accuracy'    => isset( $row[ 'accuracy' ] ) ? $row[ 'accuracy' ] : '',
				];
			}
		}
	} elseif ( 'google' === $api ) {
		$api_key = isset( $settings[ 'key' ] ) ? sanitize_text_field( $settings[ 'key' ] ) : '';
		if ( ! $api_key ) {
			wp_send_json_error(
				[
					'message' => __( 'Google API key missing.', 'wc-apg-city' ),
				]
			);
		}
		$url      = add_query_arg(
			[
				'components' => 'country:' . rawurlencode( $country ) . '|postal_code:' . rawurlencode( $postcode ),
				'key'        => rawurlencode( $api_key ),
				'language'   => rawurlencode( $lang ? $lang : 'en' ),
			],
			'https://maps.googleapis.com/maps/api/geocode/json'
		);
		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => $response->get_error_message() ] );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$rows = [];

		if ( isset( $body[ 'status' ] ) && 'OK' === $body[ 'status' ] && ! empty( $body[ 'results' ] ) ) {
			$result = $body[ 'results' ][0];
			$city   = '';
			$state  = '';
			$pais   = '';

			foreach ( $result[ 'address_components' ] as $component ) {
				if ( in_array( 'locality', $component['types'], true ) || in_array( 'postal_town', $component['types'], true ) ) {
					$city = $component['long_name'];
				}
				if ( in_array( 'administrative_area_level_2', $component['types'], true ) && ! $state ) {
					$state = $component['short_name'];
				}
				if ( in_array( 'administrative_area_level_1', $component['types'], true ) && ! $state ) {
					$state = $component['short_name'];
				}
				if ( in_array( 'country', $component['types'], true ) ) {
					$pais = $component['short_name'];
				}
			}

			if ( isset( $result[ 'postcode_localities' ] ) && is_array( $result[ 'postcode_localities' ] ) && count( $result[ 'postcode_localities' ] ) > 0 ) {
				foreach ( $result[ 'postcode_localities' ] as $loc ) {
					$rows[] = [
						'countryCode' => $pais ? $pais : $country,
						'postalCode'  => $postcode,
						'placeName'   => $loc,
						'adminName1'  => '',
						'adminCode1'  => '',
						'adminName2'  => '',
						'adminCode2'  => $state,
						'adminName3'  => '',
						'adminCode3'  => '',
						'latitude'    => '',
						'longitude'   => '',
						'accuracy'    => '',
					];
				}
			} elseif ( $city ) {
				$rows[] = [
					'countryCode' => $pais ? $pais : $country,
					'postalCode'  => $postcode,
					'placeName'   => $city,
					'adminName1'  => '',
					'adminCode1'  => '',
					'adminName2'  => '',
					'adminCode2'  => $state,
					'adminName3'  => '',
					'adminCode3'  => '',
					'latitude'    => '',
					'longitude'   => '',
					'accuracy'    => '',
				];
			}
		}
	} else {
		wp_send_json_error( [ 'message' => __( 'Unknown API.', 'wc-apg-city' ) ] );
	}

	if ( empty( $rows ) ) {
		wp_send_json_error( [ 'message' => __( 'No results found.', 'wc-apg-city' ) ] );
	}

	set_transient( $cache_key, $rows, YEAR_IN_SECONDS );

	wp_send_json_success(
		[
			'postalcodes' => $rows,
			'country'     => $country,
		]
	);
}

/**
 * Programa el cron semanal y el primer llenado.
 *
 * @return void
 */
function apg_city_schedule_updates() {
	$scheduled = wp_get_scheduled_event( APG_CITY_CRON_HOOK );

	if ( ! $scheduled ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'monthly', APG_CITY_CRON_HOOK );
	}

	if ( ! apg_city_local_data_available() && false === get_transient( 'apg_city_seed_scheduled' ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, APG_CITY_CRON_HOOK );
		set_transient( 'apg_city_seed_scheduled', 1, HOUR_IN_SECONDS );
	}
}

/**
 * Limpia tareas programadas.
 *
 * @return void
 */
function apg_city_unschedule_updates() {
	wp_clear_scheduled_hook( APG_CITY_CRON_HOOK );
}

/**
 * Hook de activación.
 *
 * @return void
 */
function apg_city_activate() {
	apg_city_create_table();
}

/**
 * Responde a la consulta AJAX usando la base de datos local.
 *
 * @return void
 */
function apg_city_ajax_lookup() {
	check_ajax_referer( 'apg_city_lookup', 'nonce' );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$postcode = isset( $_POST[ 'postcode' ] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST[ 'postcode' ] ) ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$country  = isset( $_POST[ 'country' ] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST[ 'country' ] ) ) ) : '';

	if ( empty( $postcode ) || empty( $country ) || ! apg_city_table_exists() ) {
		wp_send_json_error(
			[
				'message' => __( 'Postal code lookup is not available right now.', 'wc-apg-city' ),
			]
		);
	}

	global $wpdb;

	$table   = esc_sql( apg_city_get_table_name() );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name interpolated intentionally; placeholders prepared below.
	$query = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name enclosed intentionally.
		"SELECT country_code, postal_code, place_name, admin_name1, admin_code1, admin_name2, admin_code2, admin_name3, admin_code3, latitude, longitude, accuracy FROM `$table` WHERE postal_code = %s AND country_code = %s",
		$postcode,
		$country
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Targeted read for AJAX response; prepared above.
	$results = $wpdb->get_results( $query, ARRAY_A );

	if ( empty( $results ) ) {
		wp_send_json_error(
			[
				'message' => __( 'No local matches found.', 'wc-apg-city' ),
			]
		);
	}

	$data = [
		'postalcodes' => array_map(
			static function ( $row ) {
				return [
					'countryCode' => $row[ 'country_code' ],
					'postalCode'  => $row[ 'postal_code' ],
					'placeName'   => $row[ 'place_name' ],
					'adminName1'  => $row[ 'admin_name1' ],
					'adminCode1'  => $row[ 'admin_code1' ],
					'adminName2'  => $row[ 'admin_name2' ],
					'adminCode2'  => $row[ 'admin_code2' ],
					'adminName3'  => $row[ 'admin_name3' ],
					'adminCode3'  => $row[ 'admin_code3' ],
					'lat'         => $row[ 'latitude' ],
					'lng'         => $row[ 'longitude' ],
					'accuracy'    => $row[ 'accuracy' ],
				];
			},
			$results
		),
	];

	wp_send_json_success( $data );
}
