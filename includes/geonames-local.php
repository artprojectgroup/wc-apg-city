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
 * Número máximo de consultas a APIs externas por IP y ventana de tiempo.
 *
 * @var int
 */
define( 'APG_CITY_API_RATE_LIMIT', 40 );

/**
 * Duración de la ventana del límite de consultas, en segundos.
 *
 * @var int
 */
define( 'APG_CITY_API_RATE_WINDOW', 5 * MINUTE_IN_SECONDS );

/**
 * Número máximo de consultas a la base de datos local por IP y ventana.
 *
 * @var int
 */
define( 'APG_CITY_LOCAL_RATE_LIMIT', 200 );

/**
 * Normaliza y valida un código de país ISO 3166-1 alfa-2.
 *
 * @param string $country Código de país recibido.
 *
 * @return string Código válido en mayúsculas o cadena vacía.
 */
function apg_city_validate_country( $country ) {
	$country = strtoupper( trim( (string) $country ) );

	return preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '';
}

/**
 * Normaliza y valida un código postal.
 *
 * Acepta el formato más amplio en uso (alfanumérico con espacios y guiones,
 * de 2 a 12 caracteres) para no excluir países como Reino Unido, Irlanda o
 * Brasil, y rechaza cualquier otra cosa antes de llegar a la base de datos,
 * a la API externa o a la clave del transient.
 *
 * @param string $postcode Código postal recibido.
 *
 * @return string Código válido en mayúsculas o cadena vacía.
 */
function apg_city_validate_postcode( $postcode ) {
	$postcode = strtoupper( trim( (string) $postcode ) );
	$postcode = preg_replace( '/\s+/', ' ', $postcode );

	return preg_match( '/^[A-Z0-9][A-Z0-9 \-]{1,11}$/', $postcode ) ? $postcode : '';
}

/**
 * Devuelve la IP del visitante saneada.
 *
 * @return string IP validada o cadena vacía.
 */
function apg_city_get_remote_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip = filter_var( $ip, FILTER_VALIDATE_IP );

	return $ip ? $ip : '';
}

/**
 * Limita las consultas a APIs externas por IP.
 *
 * El endpoint es accesible sin autenticar y gasta cuota de la clave de Google
 * o del usuario de GeoNames de la tienda, así que se acota el número de
 * peticiones que una misma IP puede provocar.
 *
 * @return bool True si la petición está dentro del límite.
 */
function apg_city_check_rate_limit( $bucket = 'api', $limite = APG_CITY_API_RATE_LIMIT ) {
	$ip = apg_city_get_remote_ip();

	if ( ! $ip ) {
		return true;
	}

	/**
	 * Filtra el número máximo de consultas por IP y ventana.
	 *
	 * Detrás de una CDN o un proxy inverso todas las visitas comparten
	 * REMOTE_ADDR, así que una tienda en esa situación necesita subirlo.
	 *
	 * @param int    $limite Tope de consultas.
	 * @param string $bucket Contador afectado: 'api' o 'local'.
	 */
	$limite = (int) apply_filters( 'apg_city_rate_limit', $limite, $bucket );

	if ( $limite <= 0 ) {
		return true;
	}

	$key  = 'apg_city_rl_' . $bucket . '_' . md5( $ip );
	$hits = (int) get_transient( $key );

	if ( $hits >= $limite ) {
		return false;
	}

	set_transient( $key, $hits + 1, APG_CITY_API_RATE_WINDOW );

	return true;
}

/**
 * Construye la clave de cache de una consulta a la API externa.
 *
 * Se usa un hash para que la clave nunca supere la longitud máxima admitida
 * por la tabla de opciones.
 *
 * @param string $api      API consultada.
 * @param string $country  Código de país.
 * @param string $postcode Código postal.
 *
 * @return string Clave del transient.
 */
function apg_city_get_cache_key( $api, $country, $postcode ) {
	return 'apg_city_api_' . md5( $api . '|' . $country . '|' . $postcode );
}

/**
 * Elimina el directorio de trabajo del importador.
 *
 * @return void
 */
function apg_city_delete_working_directory() {
	$upload_dir = wp_upload_dir();

	if ( ! empty( $upload_dir['error'] ) ) {
		return;
	}

	$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'apg-city';

	if ( ! is_dir( $target_dir ) ) {
		return;
	}

	foreach ( [ 'allCountries.txt', 'readme.txt', 'index.html', '.htaccess' ] as $archivo ) {
		$ruta = trailingslashit( $target_dir ) . $archivo;
		if ( file_exists( $ruta ) ) {
			wp_delete_file( $ruta );
		}
	}

	if ( ! apg_city_init_filesystem() ) {
		return;
	}

	global $wp_filesystem;

	if ( $wp_filesystem ) {
		// rmdir() de WP_Filesystem en lugar de la función nativa: sin el segundo
		// argumento no borra nada si quedara algún archivo dentro.
		$wp_filesystem->rmdir( $target_dir );
	}
}

/**
 * Borra los transients de cache de consultas.
 *
 * @return void
 */
function apg_city_delete_lookup_cache() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Limpieza puntual en la desinstalación.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_apg_city_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_apg_city_' ) . '%'
		)
	);
}

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
 * @param bool $refrescar Fuerza una nueva comprobación en lugar de reutilizar la memoizada.
 *
 * @return bool
 */
function apg_city_table_exists( $refrescar = false ) {
	global $wpdb;

	static $existe = null;

	// La comprobación se repetía en cada carga del checkout y en cada consulta AJAX.
	if ( null !== $existe && ! $refrescar ) {
		return $existe;
	}

	$table = apg_city_get_table_name();

	// esc_like(): el guion bajo del prefijo es un comodín de LIKE y podía
	// devolver el nombre de otra tabla, dando un falso negativo.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SHOW TABLES no admite cache de objetos; el resultado se memoiza en la propia petición.
	$found_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

	$existe = ( $found_table === $table );

	return $existe;
}

/**
 * Comprueba si hay datos locales disponibles para usar.
 *
 * @return bool
 */
function apg_city_local_data_available() {
	static $disponible = null;

	if ( null !== $disponible ) {
		return $disponible;
	}

	if ( ! apg_city_table_exists() ) {
		$disponible = false;

		return $disponible;
	}

	global $wpdb;

	$table = esc_sql( apg_city_get_table_name() );

	// No basta con leer apg_city_rows: si la tabla se vacía por fuera (una
	// restauración, una migración, un reinicio de la base de datos) la opción
	// se queda diciendo que hay millones de filas. Con ese desajuste el
	// checkout gastaba una consulta AJAX inútil en cada búsqueda y, peor, el
	// cron mensual descargaba el volcado, veía que el hash coincidía y se
	// saltaba la importación, dejando la tabla vacía para siempre.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Sondeo O(1) sobre la tabla propia, memoizado en la petición; sin datos de la petición en la consulta.
	$tiene_filas = (bool) $wpdb->get_var( "SELECT id FROM `$table` LIMIT 1" );

	$contadas = (int) get_option( 'apg_city_rows', 0 );

	// Reajusta la opción cuando ha quedado obsoleta, para que el cron vuelva a importar.
	if ( ! $tiene_filas && $contadas > 0 ) {
		update_option( 'apg_city_rows', 0, false );
	}

	$disponible = $tiene_filas;

	return $disponible;
}

/**
 * Añade un intervalo semanal al cron de WordPress.
 *
 * @param array<string,mixed> $schedules Intervalos de cron registrados.
 *
 * @return array<string,mixed>
 */
function apg_city_cron_schedules( $schedules ) {
	if ( ! isset( $schedules['monthly'] ) ) {
		$schedules['monthly'] = [
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

	apg_city_table_exists( true );
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

	if ( isset( $state['file'] ) && is_string( $state['file'] ) && file_exists( $state['file'] ) ) {
		wp_delete_file( $state['file'] );
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
 * Impide el acceso público al directorio de trabajo del importador.
 *
 * El volcado de GeoNames se descarga dentro de uploads, que es accesible por
 * HTTP; se deja un index.html vacío y un .htaccess que deniega el acceso.
 *
 * @param string $target_dir Directorio de trabajo.
 *
 * @return void
 */
function apg_city_protect_working_directory( $target_dir ) {
	if ( ! apg_city_init_filesystem() ) {
		return;
	}

	global $wp_filesystem;

	if ( ! $wp_filesystem ) {
		return;
	}

	$index = trailingslashit( $target_dir ) . 'index.html';
	if ( ! $wp_filesystem->exists( $index ) ) {
		$wp_filesystem->put_contents( $index, '', FS_CHMOD_FILE );
	}

	$htaccess = trailingslashit( $target_dir ) . '.htaccess';
	if ( ! $wp_filesystem->exists( $htaccess ) ) {
		// Se cubren Apache 2.4 y 2.2. En nginx no se leen los .htaccess, pero el
		// volcado se borra en cuanto termina la importación.
		$reglas = "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";
		$wp_filesystem->put_contents( $htaccess, $reglas, FS_CHMOD_FILE );
	}
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

	if ( ! empty( $upload_dir['error'] ) ) {
		return null;
	}

	$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'apg-city';

	$txt_file = trailingslashit( $target_dir ) . 'allCountries.txt';

	if ( ! wp_mkdir_p( $target_dir ) ) {
		return null;
	}

	// Se protege siempre, no solo al descargar: una instalación que ya tenía el
	// volcado de una versión anterior se quedaba sin index.html ni .htaccess.
	apg_city_protect_working_directory( $target_dir );

	if ( ! file_exists( $txt_file ) ) {
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
 * Número de columnas que ocupa cada fila en el lote de inserción.
 *
 * @var int
 */
define( 'APG_CITY_COLUMNAS', 12 );

/**
 * Inserta un lote de códigos postales.
 *
 * El número de filas se deduce de los propios valores, de modo que no puede
 * discrepar de ellos y generar una consulta con marcadores de más. El nombre de
 * tabla y la lista de marcadores se construyen aquí a partir de esc_sql() y de
 * una plantilla literal, así que la consulta se puede verificar leyendo esta
 * función sola; los valores viajan siempre por prepare().
 *
 * @param array<int,mixed> $values Valores en el orden de las columnas, una tanda por fila.
 *
 * @return int Número de filas enviadas.
 */
function apg_city_insert_batch( $values ) {
	global $wpdb;

	if ( ! is_array( $values ) ) {
		return 0;
	}

	$filas = absint( count( $values ) / APG_CITY_COLUMNAS );

	if ( ! $filas || count( $values ) !== $filas * APG_CITY_COLUMNAS ) {
		return 0;
	}

	// El escapado va en su propio paso, con el nombre ya en una variable: las
	// herramientas de análisis no pueden seguir el valor a través de una llamada
	// a función, y con esta forma sí verifican que pasa por esc_sql().
	$nombre = apg_city_get_table_name();
	$table  = esc_sql( $nombre );

	// Un grupo de marcadores por fila, a partir de una plantilla literal.
	$grupos = implode( ',', array_fill( 0, $filas, '(%s,%s,%s,%s,%s,%s,%s,%s,%s,%f,%f,%d)' ) );

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- El número de filas del lote es variable, así que la lista de marcadores no puede ser literal; los valores van todos por prepare() y el nombre de tabla por esc_sql() unas líneas más arriba. Inserción masiva: no hay cache aplicable.
	$wpdb->query( $wpdb->prepare( 'INSERT INTO `' . $table . '` (country_code, postal_code, place_name, admin_name1, admin_code1, admin_name2, admin_code2, admin_name3, admin_code3, latitude, longitude, accuracy) VALUES ' . $grupos . ' ON DUPLICATE KEY UPDATE admin_name1=VALUES(admin_name1), admin_code1=VALUES(admin_code1), admin_name2=VALUES(admin_name2), admin_code2=VALUES(admin_code2), admin_name3=VALUES(admin_name3), admin_code3=VALUES(admin_code3), latitude=VALUES(latitude), longitude=VALUES(longitude), accuracy=VALUES(accuracy)', $values ) );

	return $filas;
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

	if ( empty( $state['file'] ) || ! file_exists( $state['file'] ) ) {
		return $result;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	$handle = fopen( $state['file'], 'r' );

	if ( ! $handle ) {
		return $result;
	}

	if ( isset( $state['offset'] ) && $state['offset'] > 0 ) {
		fseek( $handle, (int) $state['offset'] );
	}

	$values         = [];
	$filas_lote     = 0;
	$batch_size     = 300;
	$rows_this_run  = 0;
	$lines_this_run = 0;

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fgets
	// El corte va por líneas leídas, no por filas insertadas: si todas las líneas
	// de un lote se descartaran, $rows_this_run seguiría a 0 y el bucle se
	// tragaría el archivo entero en una sola ejecución.
	while ( $lines_this_run < APG_CITY_IMPORT_CHUNK && ( $line = fgets( $handle ) ) !== false ) {
		++$lines_this_run;

		$parts = explode( "\t", trim( $line ) );

		if ( count( $parts ) < 12 ) {
			continue;
		}

		++$filas_lote;

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

		if ( $filas_lote >= $batch_size ) {
			$rows_this_run += apg_city_insert_batch( $values );
			$values         = [];
			$filas_lote     = 0;
		}
	}

	$rows_this_run += apg_city_insert_batch( $values );

	$state['offset'] = ftell( $handle );

	$at_end = feof( $handle );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	fclose( $handle );

	// Se mira si se han leído líneas, no si se han insertado filas: un lote
	// entero de líneas descartadas no significa que el archivo se haya acabado.
	if ( $at_end || 0 === $lines_this_run ) {
		$result['finished'] = true;
	}

	$result['state'] = $state;

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

	// Una importación a medias tiene que continuar aunque el archivo no haya
	// cambiado: si no, el atajo por hash la daba por terminada en el primer lote.
	$reanudando = ! empty( $state['offset'] );

	if ( empty( $state ) || empty( $state['file'] ) || ! file_exists( $state['file'] ) ) {
		$state = apg_city_prepare_import_file();
		if ( empty( $state ) ) {
			delete_transient( 'apg_city_import_lock' );
			return;
		}
	}

	if ( empty( $state['hash'] ) && ! empty( $state['file'] ) && file_exists( $state['file'] ) ) {
		$state['hash'] = md5_file( $state['file'] );
	}

	$last_hash = get_option( 'apg_city_last_hash' );

	// "Mismo archivo" no significa "ya importado": la tabla puede haberse vaciado
	// o haberse quedado a medias. Solo se salta la importación si el número de
	// filas alcanza el que se registró al terminar la última vez.
	global $wpdb;
	$nombre_tabla  = esc_sql( apg_city_get_table_name() );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Recuento de la tabla propia, una vez por ejecución del cron.
	$filas_reales  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$nombre_tabla`" );
	$filas_previas = (int) get_option( 'apg_city_rows', 0 );
	$esta_completa = ( $filas_previas > 0 && $filas_reales >= $filas_previas );

	if ( ! $reanudando && $last_hash && ! empty( $state['hash'] ) && $last_hash === $state['hash'] && $esta_completa ) {
		apg_city_clear_import_state();
		update_option( 'apg_city_last_import', time() );
		delete_transient( 'apg_city_seed_scheduled' );
		delete_transient( 'apg_city_import_lock' );
		return;
	}

	$result = apg_city_process_import_chunk( $state );

	if ( $result['finished'] ) {
		if ( isset( $result['state']['file'] ) && file_exists( $result['state']['file'] ) ) {
			wp_delete_file( $result['state']['file'] );
		}
		apg_city_clear_import_state();
		update_option( 'apg_city_last_import', time() );
		update_option( 'apg_city_last_hash', isset( $result['state']['hash'] ) ? $result['state']['hash'] : '' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Recuento de la tabla propia del plugin; sin datos de la petición en la consulta.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$nombre_tabla`" );
		update_option( 'apg_city_rows', $count );
		delete_transient( 'apg_city_seed_scheduled' );
	} else {
		apg_city_set_import_state( $result['state'] );
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

	$api      = isset( $_POST['api'] ) ? sanitize_key( wp_unslash( $_POST['api'] ) ) : '';
	$postcode = isset( $_POST['postcode'] ) ? apg_city_validate_postcode( sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) ) : '';
	$country  = isset( $_POST['country'] ) ? apg_city_validate_country( sanitize_text_field( wp_unslash( $_POST['country'] ) ) ) : '';
	$lang     = isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lang'] ) ) : '';

	// El idioma solo viaja a Google: se acota a un código de idioma válido.
	$lang = preg_match( '/^[a-zA-Z]{2}(-[a-zA-Z]{2,4})?$/', $lang ) ? $lang : 'en';

	if ( ! in_array( $api, [ 'geonames', 'google' ], true ) ) {
		wp_send_json_error( [ 'message' => __( 'Unknown API.', 'wc-apg-city' ) ] );
	}

	if ( '' === $postcode || '' === $country ) {
		wp_send_json_error(
			[
				'message' => __( 'Missing parameters.', 'wc-apg-city' ),
			]
		);
	}

	$cache_key = apg_city_get_cache_key( $api, $country, $postcode );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		if ( empty( $cached ) ) { // Resultado negativo cacheado: evita repetir la consulta externa.
			wp_send_json_error( [ 'message' => __( 'No results found.', 'wc-apg-city' ) ] );
		}

		wp_send_json_success(
			[
				'postalcodes' => $cached,
				'country'     => $country,
			]
		);
	}

	// Solo se aplica el límite cuando la consulta va a salir de verdad a Internet.
	if ( ! apg_city_check_rate_limit() ) {
		wp_send_json_error( [ 'message' => __( 'Too many lookups, please try again in a few minutes.', 'wc-apg-city' ) ], 429 );
	}

	$settings = apg_city_get_settings();
	$rows     = [];

	if ( 'geonames' === $api ) {
		$username = sanitize_text_field( (string) $settings['geonames_user'] );
		if ( ! $username ) {
			wp_send_json_error(
				[
					'message' => __( 'GeoNames username missing.', 'wc-apg-city' ),
				]
			);
		}
		$url      = add_query_arg(
			[
				'postalcode' => $postcode,
				'country'    => $country,
				'username'   => $username,
			],
			'https://www.geonames.org/postalCodeLookupJSON'
		);
		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $response ) ) {
			// El detalle del error se queda en el registro: no se expone al visitante.
			wp_send_json_error( [ 'message' => __( 'Postal code lookup is not available right now.', 'wc-apg-city' ) ] );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// GeoNames devuelve {"postalcodes":[...]} cuando responde de verdad y
		// {"status":{...}} cuando falla: solo lo primero es cacheable en negativo.
		$respuesta_valida = ( isset( $body['postalcodes'] ) && is_array( $body['postalcodes'] ) );

		if ( $respuesta_valida ) {
			foreach ( $body['postalcodes'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$rows[] = [
					'countryCode' => isset( $row['countryCode'] ) ? sanitize_text_field( (string) $row['countryCode'] ) : $country,
					'postalCode'  => isset( $row['postalCode'] ) ? sanitize_text_field( (string) $row['postalCode'] ) : $postcode,
					'placeName'   => isset( $row['placeName'] ) ? sanitize_text_field( (string) $row['placeName'] ) : '',
					'adminName1'  => isset( $row['adminName1'] ) ? sanitize_text_field( (string) $row['adminName1'] ) : '',
					'adminCode1'  => isset( $row['adminCode1'] ) ? sanitize_text_field( (string) $row['adminCode1'] ) : '',
					'adminName2'  => isset( $row['adminName2'] ) ? sanitize_text_field( (string) $row['adminName2'] ) : '',
					'adminCode2'  => isset( $row['adminCode2'] ) ? sanitize_text_field( (string) $row['adminCode2'] ) : '',
					'adminName3'  => isset( $row['adminName3'] ) ? sanitize_text_field( (string) $row['adminName3'] ) : '',
					'adminCode3'  => isset( $row['adminCode3'] ) ? sanitize_text_field( (string) $row['adminCode3'] ) : '',
					'lat'         => isset( $row['lat'] ) ? sanitize_text_field( (string) $row['lat'] ) : '',
					'lng'         => isset( $row['lng'] ) ? sanitize_text_field( (string) $row['lng'] ) : '',
					'accuracy'    => isset( $row['accuracy'] ) ? sanitize_text_field( (string) $row['accuracy'] ) : '',
				];
			}
		}
	} else {
		$api_key = sanitize_text_field( (string) $settings['key'] );
		if ( ! $api_key ) {
			wp_send_json_error(
				[
					'message' => __( 'Google API key missing.', 'wc-apg-city' ),
				]
			);
		}
		$url      = add_query_arg(
			[
				'components' => 'country:' . $country . '|postal_code:' . $postcode,
				'key'        => $api_key,
				'language'   => $lang,
			],
			'https://maps.googleapis.com/maps/api/geocode/json'
		);
		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => __( 'Postal code lookup is not available right now.', 'wc-apg-city' ) ] );
		}
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$estado = isset( $body['status'] ) ? $body['status'] : '';

		// OK y ZERO_RESULTS son respuestas reales. OVER_QUERY_LIMIT, REQUEST_DENIED
		// y compañía son fallos: no se cachean como "sin resultados".
		$respuesta_valida = in_array( $estado, [ 'OK', 'ZERO_RESULTS' ], true );

		if ( 'OK' === $estado && ! empty( $body['results'][0] ) ) {
			$result = $body['results'][0];
			$city   = '';
			$state  = '';
			$pais   = '';

			$componentes = ( isset( $result['address_components'] ) && is_array( $result['address_components'] ) ) ? $result['address_components'] : [];

			foreach ( $componentes as $component ) {
				if ( ! isset( $component['types'] ) || ! is_array( $component['types'] ) ) {
					continue;
				}
				$largo = isset( $component['long_name'] ) ? sanitize_text_field( (string) $component['long_name'] ) : '';
				$corto = isset( $component['short_name'] ) ? sanitize_text_field( (string) $component['short_name'] ) : '';

				if ( in_array( 'locality', $component['types'], true ) || in_array( 'postal_town', $component['types'], true ) ) {
					$city = $largo;
				}
				if ( in_array( 'administrative_area_level_2', $component['types'], true ) && ! $state ) {
					$state = $corto;
				}
				if ( in_array( 'administrative_area_level_1', $component['types'], true ) && ! $state ) {
					$state = $corto;
				}
				if ( in_array( 'country', $component['types'], true ) ) {
					$pais = $corto;
				}
			}

			$localidades = ( isset( $result['postcode_localities'] ) && is_array( $result['postcode_localities'] ) ) ? $result['postcode_localities'] : [];

			if ( ! empty( $localidades ) ) {
				foreach ( $localidades as $loc ) {
					$rows[] = [
						'countryCode' => $pais ? $pais : $country,
						'postalCode'  => $postcode,
						'placeName'   => sanitize_text_field( (string) $loc ),
						'adminName1'  => '',
						'adminCode1'  => '',
						'adminName2'  => '',
						'adminCode2'  => $state,
						'adminName3'  => '',
						'adminCode3'  => '',
						'lat'         => '',
						'lng'         => '',
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
					'lat'         => '',
					'lng'         => '',
					'accuracy'    => '',
				];
			}
		}
	}

	if ( empty( $rows ) ) {
		if ( $respuesta_valida ) {
			// Cachea el resultado vacío, pero poco tiempo: un código postal nuevo
			// no debe quedar sin resolver durante un año.
			set_transient( $cache_key, [], DAY_IN_SECONDS );
		}

		wp_send_json_error( [ 'message' => __( 'No results found.', 'wc-apg-city' ) ] );
	}

	set_transient( $cache_key, $rows, MONTH_IN_SECONDS );

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

	$postcode = isset( $_POST['postcode'] ) ? apg_city_validate_postcode( sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) ) : '';
	$country  = isset( $_POST['country'] ) ? apg_city_validate_country( sanitize_text_field( wp_unslash( $_POST['country'] ) ) ) : '';

	if ( '' === $postcode || '' === $country || ! apg_city_table_exists() ) {
		wp_send_json_error(
			[
				'message' => __( 'Postal code lookup is not available right now.', 'wc-apg-city' ),
			]
		);
	}

	// Cache en el objeto, no en transients: un visitante anónimo podía llenar
	// wp_options con una fila por cada código postal que se inventara. Sin cache
	// persistente esto es memoria de la propia petición, y la consulta va por
	// índice, así que el coste es asumible.
	$cache_key = 'local_' . md5( $country . '|' . $postcode );
	$results   = wp_cache_get( $cache_key, 'apg_city' );

	if ( false === $results ) {
		if ( ! apg_city_check_rate_limit( 'local', APG_CITY_LOCAL_RATE_LIMIT ) ) {
			wp_send_json_error( [ 'message' => __( 'Too many lookups, please try again in a few minutes.', 'wc-apg-city' ) ], 429 );
		}

		$results = apg_city_query_local( $postcode, $country );
		wp_cache_set( $cache_key, $results, 'apg_city', HOUR_IN_SECONDS );
	}

	if ( empty( $results ) ) {
		wp_send_json_error(
			[
				'message' => __( 'No local matches found.', 'wc-apg-city' ),
			]
		);
	}

	wp_send_json_success(
		[
			'postalcodes' => array_map( 'apg_city_map_row', $results ),
		]
	);
}

/**
 * Consulta la tabla local de códigos postales.
 *
 * @param string $postcode Código postal validado.
 * @param string $country  Código de país validado.
 *
 * @return array<int,array<string,mixed>> Filas encontradas.
 */
function apg_city_query_local( $postcode, $country ) {
	global $wpdb;

	$table = esc_sql( apg_city_get_table_name() );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabla propia del plugin; el resultado se cachea en apg_city_ajax_lookup().
	$results = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Nombre de tabla propio, pasado por esc_sql(); los valores van con marcadores.
			"SELECT country_code, postal_code, place_name, admin_name1, admin_code1, admin_name2, admin_code2, admin_name3, admin_code3, latitude, longitude, accuracy FROM `$table` WHERE postal_code = %s AND country_code = %s",
			$postcode,
			$country
		),
		ARRAY_A
	);

	return is_array( $results ) ? $results : [];
}

/**
 * Normaliza una fila de la tabla local al formato que espera el JavaScript.
 *
 * @param array<string,mixed> $row Fila de la base de datos.
 *
 * @return array<string,mixed> Fila normalizada.
 */
function apg_city_map_row( $row ) {
	return [
		'countryCode' => isset( $row['country_code'] ) ? $row['country_code'] : '',
		'postalCode'  => isset( $row['postal_code'] ) ? $row['postal_code'] : '',
		'placeName'   => isset( $row['place_name'] ) ? $row['place_name'] : '',
		'adminName1'  => isset( $row['admin_name1'] ) ? $row['admin_name1'] : '',
		'adminCode1'  => isset( $row['admin_code1'] ) ? $row['admin_code1'] : '',
		'adminName2'  => isset( $row['admin_name2'] ) ? $row['admin_name2'] : '',
		'adminCode2'  => isset( $row['admin_code2'] ) ? $row['admin_code2'] : '',
		'adminName3'  => isset( $row['admin_name3'] ) ? $row['admin_name3'] : '',
		'adminCode3'  => isset( $row['admin_code3'] ) ? $row['admin_code3'] : '',
		'lat'         => isset( $row['latitude'] ) ? $row['latitude'] : '',
		'lng'         => isset( $row['longitude'] ) ? $row['longitude'] : '',
		'accuracy'    => isset( $row['accuracy'] ) ? $row['accuracy'] : '',
	];
}
