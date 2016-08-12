<?php
/*
Plugin Name: WC - APG City
Version: 0.2
Plugin URI: https://wordpress.org/plugins/wc-apg-city/
Description: Add to WooCommerce an automatic city name generated from postcode.
Author URI: http://www.artprojectgroup.es/
Author: Art Project Group
Requires at least: 3.8
Tested up to: 4.6

Text Domain: apg_city
Domain Path: /i18n/languages

@package WC - APG City
@category Core
@author Art Project Group
*/

//Igual no deberías poder abrirme
if ( !defined( 'ABSPATH' ) ) {
    exit();
}

//Definimos constantes
define( 'DIRECCION_apg_city', plugin_basename( __FILE__ ) );

//Definimos las variables
$apg_city = array(	
	'plugin' 		=> 'WC - APG City', 
	'plugin_uri' 	=> 'wc-apg-city', 
	'donacion' 		=> 'http://www.artprojectgroup.es/tienda/donacion',
	'soporte' 		=> 'http://www.wcprojectgroup.es/tienda/ticket-de-soporte',
	'plugin_url' 	=> 'http://www.artprojectgroup.es/plugins-para-wordpress/plugins-para-woocommerce/wc-apg-city', 
	'puntuacion' 	=> 'https://wordpress.org/support/view/plugin-reviews/wc-apg-city'
);
$envios_adicionales = $limpieza = NULL;

//Carga el idioma
load_plugin_textdomain( 'apg_city', null, dirname( DIRECCION_apg_city ) . '/i18n/languages' );

//Enlaces adicionales personalizados
function apg_city_enlaces( $enlaces, $archivo ) {
	global $apg_city;

	if ( $archivo == DIRECCION_apg_city ) {
		$plugin = apg_city_plugin( $apg_city['plugin_uri'] );
		$enlaces[] = '<a href="' . $apg_city['donacion'] . '" target="_blank" title="' . __( 'Make a donation by ', 'apg_city' ) . 'APG"><span class="genericon genericon-cart"></span></a>';
		$enlaces[] = '<a href="'. $apg_city['plugin_url'] . '" target="_blank" title="' . $apg_city['plugin'] . '"><strong class="artprojectgroup">APG</strong></a>';
		$enlaces[] = '<a href="https://www.facebook.com/artprojectgroup" title="' . __( 'Follow us on ', 'apg_city' ) . 'Facebook" target="_blank"><span class="genericon genericon-facebook-alt"></span></a> <a href="https://twitter.com/artprojectgroup" title="' . __( 'Follow us on ', 'apg_city' ) . 'Twitter" target="_blank"><span class="genericon genericon-twitter"></span></a> <a href="https://plus.google.com/+ArtProjectGroupES" title="' . __( 'Follow us on ', 'apg_city' ) . 'Google+" target="_blank"><span class="genericon genericon-googleplus-alt"></span></a> <a href="http://es.linkedin.com/in/artprojectgroup" title="' . __( 'Follow us on ', 'apg_city' ) . 'LinkedIn" target="_blank"><span class="genericon genericon-linkedin"></span></a>';
		$enlaces[] = '<a href="https://profiles.wordpress.org/artprojectgroup/" title="' . __( 'More plugins on ', 'apg_city' ) . 'WordPress" target="_blank"><span class="genericon genericon-wordpress"></span></a>';
		$enlaces[] = '<a href="mailto:info@artprojectgroup.es" title="' . __( 'Contact with us by ', 'apg_city' ) . 'e-mail"><span class="genericon genericon-mail"></span></a> <a href="skype:artprojectgroup" title="' . __( 'Contact with us by ', 'apg_city' ) . 'Skype"><span class="genericon genericon-skype"></span></a>';
		$enlaces[] = apg_city_plugin( $apg_city['plugin_uri'] );
	}
	
	return $enlaces;
}
add_filter( 'plugin_row_meta', 'apg_city_enlaces', 10, 2 );

//Añade el botón de configuración
function apg_city_enlace_de_ajustes( $enlaces ) { 
	global $apg_city;

	$enlaces_de_ajustes = array(
		'<a href="' . $apg_city['soporte'] . '" title="' . __( 'Support of ', 'apg_city' ) . $apg_city['plugin'] .'">' . __( 'Support', 'apg_city' ) . '</a>'
	);
	foreach ( $enlaces_de_ajustes as $enlace_de_ajustes ) {
		array_unshift( $enlaces, $enlace_de_ajustes );
	}
	
	return $enlaces; 
}
$plugin = DIRECCION_apg_city; 
add_filter( "plugin_action_links_$plugin", 'apg_city_enlace_de_ajustes' );

//¿Está activo WooCommerce?
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	function apg_city_campos_de_direccion( $campos ) {
		$campos['city']['custom_attributes']		= array( 
			'readonly'	=> 'readonly'
		);
		$campos['city']							= array(
			'label'         => __( 'Town / City', 'woocommerce' ),
			'placeholder'   => _x( 'Select city name', 'placeholder', 'apg_city' ),
			'required'		=> true,
			'clear'       	=> false,
			'type'        	=> 'select',
			'class'       	=> array(
				'form-row-wide'
			),
			'input_class'	=> array(
				'state_select'
			),
			'options'		=> array(
				'' => __( 'Select city name', 'apg_city' ),
			),
			'readonly'	=> 'readonly'
        );

		//Reordenamos los campos
		$campos_nuevos['country']		= $campos['country'];
		$campos_nuevos['first_name']		= $campos['first_name'];
		$campos_nuevos['last_name']		= $campos['last_name'];
		$campos_nuevos['company']		= $campos['company'];
		$campos_nuevos['address_1']		= $campos['address_1'];
		$campos_nuevos['address_2']		= $campos['address_2'];
		$campos_nuevos['postcode']		= $campos['postcode'];
		$campos_nuevos['city']			= $campos['city'];
		$campos_nuevos['state']			= $campos['state'];
		if ( isset( $campos['email'] ) ) {
			$campos_nuevos['email'] = $campos['email'];
		}
		if ( isset( $campos['phone'] ) ) {
			$campos_nuevos['phone'] = $campos['phone'];
		}

		return $campos_nuevos;
	}
	add_filter( 'woocommerce_default_address_fields', 'apg_city_campos_de_direccion' );
	
	//Añade código JavaScript a en checkout
	function codigo_javascript_en_checkout() {
		if ( is_checkout() ) {
			wp_register_script( 'apg_city', plugins_url( 'assets/js/apg-city.js', __FILE__ ) );
			wp_enqueue_script( 'apg_city' );
		}
	}
	add_action( 'wp_footer', 'codigo_javascript_en_checkout' );
} else {
	add_action( 'admin_notices', 'apg_city_requiere_wc' );
}

//Muestra el mensaje de activación de WooCommerce y desactiva el plugin
function apg_city_requiere_wc() {
	global $apg_city;
		
	echo '<div class="error fade" id="message"><h3>' . $apg_city['plugin'] . '</h3><h4>' . __( "This plugin require WooCommerce active to run!", 'apg_city' ) . '</h4></div>';
	deactivate_plugins( DIRECCION_apg_city );
}

//Obtiene toda la información sobre el plugin
function apg_city_plugin( $nombre ) {
	global $apg_city;

	$argumentos = ( object ) array( 
		'slug' => $nombre 
	);
	$consulta = array( 
		'action' => 'plugin_information', 
		'timeout' => 15, 
		'request' => serialize( $argumentos )
	);
	$respuesta = get_transient( 'apg_city_plugin' );
	if ( false === $respuesta ) {
		$respuesta = wp_remote_post( 'http://api.wordpress.org/plugins/info/1.0/', array( 
			'body' => $consulta)
		);
		set_transient( 'apg_city_plugin', $respuesta, 24 * HOUR_IN_SECONDS );
	}
	if ( !is_wp_error( $respuesta ) ) {
		$plugin = get_object_vars( unserialize( $respuesta['body'] ) );
	} else {
		$plugin['rating'] = 100;
	}
	
	$rating = array(
	   'rating'	=> $plugin['rating'],
	   'type'	=> 'percent',
	   'number'	=> $plugin['num_ratings'],
	);
	ob_start();
	wp_star_rating( $rating );
	$estrellas = ob_get_contents();
	ob_end_clean();

	return '<a title="' . sprintf( __( 'Please, rate %s:', 'apg_city' ), $apg_city['plugin'] ) . '" href="' . $apg_city['puntuacion'] . '?rate=5#postform" class="estrellas">' . $estrellas . '</a>';
}

//Carga la hoja de estilo
function apg_city_muestra_mensaje() {
	wp_register_style( 'apg_city_hoja_de_estilo', plugins_url( 'assets/fonts/stylesheet.css', __FILE__ ) );
	wp_enqueue_style( 'apg_city_hoja_de_estilo' );
}
add_action( 'admin_init', 'apg_city_muestra_mensaje' );

//Eliminamos todo rastro del plugin al desinstalarlo
function apg_city_desinstalar() {
	delete_transient( 'apg_city_plugin' );
}
register_uninstall_hook( __FILE__, 'apg_city_desinstalar' );
?>
