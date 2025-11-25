<?php
/**
 * Utilidades de administración para el plugin
 * "WC - APG City".
 *
 * Contiene enlaces del listado de plugins, enlace de Ajustes,
 * recuperación de valoración desde WordPress.org y carga de estilos
 * en la administración.
 *
 * @package   WC_APG_City
 */

// Igual no deberías poder abrirme.
defined( 'ABSPATH' ) || exit;

/**
 * Datos estáticos del plugin usados en la administración.
 *
 * @var array{
 *   plugin:string,
 *   plugin_uri:string,
 *   donacion:string,
 *   soporte:string,
 *   plugin_url:string,
 *   ajustes:string,
 *   puntuacion:string
 * }
 */
$apg_city = [	
	'plugin' 		=> 'WC - APG City', 
	'plugin_uri' 	=> 'wc-apg-city', 
	'donacion' 		=> 'https://artprojectgroup.es/tienda/donacion',
	'soporte' 		=> 'https://artprojectgroup.es/tienda/soporte-tecnico',
	'plugin_url' 	=> 'https://artprojectgroup.es/plugins-para-woocommerce/wc-apg-city', 
	'ajustes' 		=> 'admin.php?page=wc-apg-city', 
	'puntuacion' 	=> 'https://wordpress.org/support/view/plugin-reviews/wc-apg-city'
];

/**
 * Añade enlaces personalizados (donación, soporte, redes, rating, etc.)
 * en la fila del plugin dentro de "Plugins" (admin).
 *
 * Hook: `plugin_row_meta`.
 *
 * @global array $apg_city
 *
 * @param string[] $enlaces Lista existente de enlaces.
 * @param string   $archivo Ruta del archivo principal del plugin mostrado.
 * @return string[] Enlaces con los adicionales del plugin si aplica.
 */
function apg_city_enlaces( $enlaces, $archivo ) {
	global $apg_city;

	if ( $archivo == DIRECCION_apg_city ) {
		$plugin		= apg_city_plugin( $apg_city[ 'plugin_uri' ] );
		$enlaces[]	= '<a href="' . $apg_city[ 'donacion' ] . '" target="_blank" title="' . esc_attr__( 'Make a donation by ', 'wc-apg-city' ) . 'APG"><span class="genericon genericon-cart"></span></a>';
		$enlaces[]	= '<a href="'. $apg_city[ 'plugin_url' ] . '" target="_blank" title="' . $apg_city[ 'plugin' ] . '"><strong class="artprojectgroup">APG</strong></a>';
		$enlaces[]	= '<a href="https://www.facebook.com/artprojectgroup" title="' . esc_attr__( 'Follow us on ', 'wc-apg-city' ) . 'Facebook" target="_blank"><span class="genericon genericon-facebook-alt"></span></a> <a href="https://twitter.com/artprojectgroup" title="' . esc_attr__( 'Follow us on ', 'wc-apg-city' ) . 'Twitter" target="_blank"><span class="genericon genericon-twitter"></span></a> <a href="https://es.linkedin.com/in/artprojectgroup" title="' . esc_attr__( 'Follow us on ', 'wc-apg-city' ) . 'LinkedIn" target="_blank"><span class="genericon genericon-linkedin"></span></a>';
		$enlaces[]	= '<a href="https://profiles.wordpress.org/artprojectgroup/" title="' . esc_attr__( 'More plugins on ', 'wc-apg-city' ) . 'WordPress" target="_blank"><span class="genericon genericon-wordpress"></span></a>';
		$enlaces[]	= '<a href="mailto:info@artprojectgroup.es" title="' . esc_attr__( 'Contact with us by ', 'wc-apg-city' ) . 'e-mail"><span class="genericon genericon-mail"></span></a>';
		$enlaces[]	= apg_city_plugin( $apg_city[ 'plugin_uri' ] );
	}
	
	return $enlaces;
}
add_filter( 'plugin_row_meta', 'apg_city_enlaces', 10, 2 );

/**
 * Añade los enlaces "Ajustes" y "Soporte" en la fila de acciones del plugin.
 *
 * Hook: `plugin_action_links_{plugin_basename}`.
 *
 * @global array $apg_city
 *
 * @param string[] $enlaces Enlaces actuales de acción del plugin.
 * @return string[] Enlaces actualizados con Ajustes y Soporte al principio.
 */
function apg_city_enlace_de_ajustes( $enlaces ) { 
	global $apg_city;

	$enlaces_de_ajustes = [
		'<a href="' . $apg_city[ 'ajustes' ] . '" title="' . esc_attr__( 'Settings of ', 'wc-apg-city' ) . $apg_city[ 'plugin' ] .'">' . esc_attr__( 'Settings', 'wc-apg-city' ) . '</a>', 
		'<a href="' . $apg_city[ 'soporte' ] . '" title="' . esc_attr__( 'Support of ', 'wc-apg-city' ) . $apg_city[ 'plugin' ] .'">' . esc_attr__( 'Support', 'wc-apg-city' ) . '</a>'
	];
	foreach ( $enlaces_de_ajustes as $enlace_de_ajustes ) {
		array_unshift( $enlaces, $enlace_de_ajustes );
	}
	
	return $enlaces; 
}

/**
 * Basename del plugin usado para construir el hook de acción.
 *
 * @var string
 */
$plugin = DIRECCION_apg_city; 
add_filter( "plugin_action_links_$plugin", 'apg_city_enlace_de_ajustes' );

/**
 * Recupera información del plugin desde la API de WordPress.org y
 * devuelve el HTML de las estrellas de valoración enlazadas.
 *
 * Usa un transient para cachear la respuesta 24h.
 *
 * @global array $apg_city
 *
 * @param string $nombre Slug del plugin en WordPress.org.
 * @return string HTML con las estrellas de valoración (o texto alternativo si falla).
 */
function apg_city_plugin( $nombre ) {
	global $apg_city;

	$respuesta	= get_transient( 'apg_city_plugin' );
	if ( false === $respuesta ) {
		$respuesta = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' . $nombre  );
		set_transient( 'apg_city_plugin', $respuesta, 24 * HOUR_IN_SECONDS );
	}
	if ( ! is_wp_error( $respuesta ) ) {
		$plugin = json_decode( wp_remote_retrieve_body( $respuesta ) );
	} else {
        // translators: %s is the plugin name (e.g., WC – APG City)
        return '<a title="' . sprintf( esc_attr__( 'Please, rate %s:', 'wc-apg-city' ), $apg_city[ 'plugin' ] ) . '" href="' . $apg_city[ 'puntuacion' ] . '?rate=5#postform" class="estrellas">' . esc_attr__( 'Unknown rating', 'wc-apg-city' ) . '</a>';
	}

    $rating = [
	   'rating'		=> ( isset( $plugin->rating ) ) ? $plugin->rating : 0,
	   'type'		=> 'percent',
	   'number'		=> ( isset( $plugin->num_ratings ) ) ? $plugin->num_ratings : 0,
	];
	ob_start();
	wp_star_rating( $rating );
	$estrellas = ob_get_contents();
	ob_end_clean();

    // translators: %s is the plugin name (e.g., WC – APG Campo City)
	return '<a title="' . sprintf( esc_attr__( 'Please, rate %s:', 'wc-apg-city' ), $apg_city[ 'plugin' ] ) . '" href="' . $apg_city[ 'puntuacion' ] . '?rate=5#postform" class="estrellas">' . $estrellas . '</a>';
}	

/**
 * Registra/encola estilos y JS necesarios en el admin en las pantallas relevantes.
 *
 * Hook: `admin_enqueue_scripts`.
 *
 * @param string $hook Hook de pantalla actual en el admin (por ejemplo, 'woocommerce_page_wc-admin').
 * @return void
 */
function apg_city_estilo() {
    if ( isset( $_SERVER[ 'REQUEST_URI' ] ) ) {
        $request_uri = sanitize_text_field( wp_unslash( $_SERVER[ 'REQUEST_URI' ] ) );
        if ( strpos( $request_uri, 'wc-apg-city' ) !== false || strpos( $request_uri, 'plugins.php' ) !== false ) {
            // Carga/registro de la hoja de estilo del plugin con firma correcta (deps, ver, media)
			wp_register_style( 'apg_city_hoja_de_estilo', plugins_url( 'assets/css/style.css', DIRECCION_apg_city ), [], VERSION_apg_city, 'all' );
			if ( ! wp_style_is( 'apg_city_hoja_de_estilo', 'enqueued' ) ) {
				wp_enqueue_style( 'apg_city_hoja_de_estilo' );
			}
		}
	}
}
add_action( 'admin_enqueue_scripts', 'apg_city_estilo' );
