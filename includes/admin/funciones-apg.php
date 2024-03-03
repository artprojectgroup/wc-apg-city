<?php
//Igual no deberías poder abrirme
defined( 'ABSPATH' ) || exit;

//Definimos las variables
$apg_city = [	
	'plugin' 		=> 'WC - APG City', 
	'plugin_uri' 	=> 'wc-apg-city', 
	'donacion' 		=> 'https://artprojectgroup.es/tienda/donacion',
	'soporte' 		=> 'https://artprojectgroup.es/tienda/soporte-tecnico',
	'plugin_url' 	=> 'https://artprojectgroup.es/plugins-para-woocommerce/wc-apg-city', 
	'ajustes' 		=> 'admin.php?page=wc-apg-city', 
	'puntuacion' 	=> 'https://wordpress.org/support/view/plugin-reviews/wc-apg-city'
];

//Carga el idioma
load_plugin_textdomain( 'wc-apg-city', null, dirname( DIRECCION_apg_city ) . '/languages' );

//Enlaces adicionales personalizados
function apg_city_enlaces( $enlaces, $archivo ) {
	global $apg_city;

	if ( $archivo == DIRECCION_apg_city ) {
		$plugin = apg_city_plugin( $apg_city[ 'plugin_uri' ] );
		$enlaces[] = '<a href="' . $apg_city[ 'donacion' ] . '" target="_blank" title="' . __( 'Make a donation by ', 'wc-apg-city' ) . 'APG"><span class="genericon genericon-cart"></span></a>';
		$enlaces[] = '<a href="'. $apg_city[ 'plugin_url' ] . '" target="_blank" title="' . $apg_city[ 'plugin' ] . '"><strong class="artprojectgroup">APG</strong></a>';
		$enlaces[] = '<a href="https://www.facebook.com/artprojectgroup" title="' . __( 'Follow us on ', 'wc-apg-city' ) . 'Facebook" target="_blank"><span class="genericon genericon-facebook-alt"></span></a> <a href="https://twitter.com/artprojectgroup" title="' . __( 'Follow us on ', 'wc-apg-city' ) . 'Twitter" target="_blank"><span class="genericon genericon-twitter"></span></a> <a href="https://es.linkedin.com/in/artprojectgroup" title="' . __( 'Follow us on ', 'wc-apg-city' ) . 'LinkedIn" target="_blank"><span class="genericon genericon-linkedin"></span></a>';
		$enlaces[] = '<a href="https://profiles.wordpress.org/artprojectgroup/" title="' . __( 'More plugins on ', 'wc-apg-city' ) . 'WordPress" target="_blank"><span class="genericon genericon-wordpress"></span></a>';
		$enlaces[] = '<a href="mailto:info@artprojectgroup.es" title="' . __( 'Contact with us by ', 'wc-apg-city' ) . 'e-mail"><span class="genericon genericon-mail"></span></a> <a href="skype:artprojectgroup" title="' . __( 'Contact with us by ', 'wc-apg-city' ) . 'Skype"><span class="genericon genericon-skype"></span></a>';
		$enlaces[] = apg_city_plugin( $apg_city[ 'plugin_uri' ] );
	}
	
	return $enlaces;
}
add_filter( 'plugin_row_meta', 'apg_city_enlaces', 10, 2 );

//Añade el botón de configuración
function apg_city_enlace_de_ajustes( $enlaces ) { 
	global $apg_city;

	$enlaces_de_ajustes = [
		'<a href="' . $apg_city[ 'ajustes' ] . '" title="' . __( 'Settings of ', 'wc-apg-city' ) . $apg_city[ 'plugin' ] .'">' . __( 'Settings', 'wc-apg-city' ) . '</a>', 
		'<a href="' . $apg_city[ 'soporte' ] . '" title="' . __( 'Support of ', 'wc-apg-city' ) . $apg_city[ 'plugin' ] .'">' . __( 'Support', 'wc-apg-city' ) . '</a>'
	];
	foreach ( $enlaces_de_ajustes as $enlace_de_ajustes ) {
		array_unshift( $enlaces, $enlace_de_ajustes );
	}
	
	return $enlaces; 
}
$plugin = DIRECCION_apg_city; 
add_filter( "plugin_action_links_$plugin", 'apg_city_enlace_de_ajustes' );

//Obtiene toda la información sobre el plugin
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
	   return '<a title="' . sprintf( __( 'Please, rate %s:', 'wc-apg-city' ), $apg_city[ 'plugin' ] ) . '" href="' . $apg_city[ 'puntuacion' ] . '?rate=5#postform" class="estrellas">' . __( 'Unknown rating', 'wc-apg-city' ) . '</a>';
	}

    $rating = [
	   'rating'		=> $plugin->rating,
	   'type'		=> 'percent',
	   'number'		=> $plugin->num_ratings,
	];
	ob_start();
	wp_star_rating( $rating );
	$estrellas = ob_get_contents();
	ob_end_clean();

	return '<a title="' . sprintf( __( 'Please, rate %s:', 'wc-apg-city' ), $apg_city[ 'plugin' ] ) . '" href="' . $apg_city[ 'puntuacion' ] . '?rate=5#postform" class="estrellas">' . $estrellas . '</a>';
}

//Hoja de estilo
function apg_city_estilo() {
	if ( strpos( $_SERVER[ 'REQUEST_URI' ], 'wc-apg-city' ) !== false || strpos( $_SERVER[ 'REQUEST_URI' ], 'plugins.php' ) !== false ) {
		wp_register_style( 'apg_city_hoja_de_estilo', plugins_url( 'assets/css/style.css', DIRECCION_apg_city ) );
		wp_enqueue_style( 'apg_city_hoja_de_estilo' );
	}
}
add_action( 'admin_enqueue_scripts', 'apg_city_estilo' );
