<?php
/*
Plugin Name: WC - APG City
Version: 1.0.2
Plugin URI: https://wordpress.org/plugins/wc-apg-city/
Description: Add to WooCommerce an automatic city name generated from postcode.
Author URI: https://artprojectgroup.es/
Author: Art Project Group
Requires at least: 3.8
Tested up to: 5.5
WC requires at least: 2.1
WC tested up to: 4.2

Text Domain: wc-apg-city
Domain Path: /languages

@package WC - APG City
@category Core
@author Art Project Group
*/

//Igual no deberías poder abrirme
defined( 'ABSPATH' ) || exit;

//Definimos constantes
define( 'DIRECCION_apg_city', plugin_basename( __FILE__ ) );

//Funciones generales de APG
include_once( 'includes/admin/funciones-apg.php' );

$apg_city_settings = get_option( 'apg_city_settings' );

//¿Está activo WooCommerce?
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) || is_network_only_plugin( 'woocommerce/woocommerce.php' ) ) {
	//Pinta el formulario de configuración
	function apg_city_tab() {
		include( 'includes/formulario.php' );
	}

	//Añade en el menú a WooCommerce
	function apg_city_admin_menu() {
		add_submenu_page( 'woocommerce', __( 'APG City', 'wc-apg-city' ),  __( 'City field', 'wc-apg-city' ) , 'manage_woocommerce', 'wc-apg-city', 'apg_city_tab' );
	}
	add_action( 'admin_menu', 'apg_city_admin_menu', 15 );

	//Registra las opciones
	function apg_city_registra_opciones() {
		register_setting( 'apg_city_settings_group', 'apg_city_settings' );
	}
	add_action( 'admin_init', 'apg_city_registra_opciones' );

	//Carga los scripts y CSS de WooCommerce
	function apg_city_screen_id( $woocommerce_screen_ids ) {
		$woocommerce_screen_ids[] = 'woocommerce_page_wc-apg-city';

		return $woocommerce_screen_ids;
	}
	add_filter( 'woocommerce_screen_ids', 'apg_city_screen_id' );
	
	//Modifica el campo Localidad
	function apg_city_campos_de_direccion( $campos ) {
		$campos['city']	= [
			'label'         => __( 'Town / City', 'woocommerce' ),
			'placeholder'   => _x( 'Select city name', 'placeholder', 'wc-apg-city' ),
			'required'		=> true,
			'clear'       	=> ( in_array( 'form-row-last', $campos['city']['class'] ) ) ? "true" : "false",
			'type'        	=> 'select',
			'class'       	=> $campos['city']['class'],
			'input_class'	=> [
				'state_select'
			],
			'options'		=> [
				''				=> __( 'Select city name', 'wc-apg-city' ),
				'carga_campo'	=> __( 'My city isn\'t on the list', 'wc-apg-city' ),
			],
			'readonly'		=> 'readonly',
			'autocomplete'	=> 'address-level2',
			'priority'      => $campos['city']['priority'],
        ];

		return $campos;
	}
	
	//Añade código JavaScript al checkout
	function apg_city_codigo_javascript_en_checkout() {
		if ( is_checkout() || is_account_page() ) {
			global $apg_city_settings;
			
			wp_register_script( 'apg_city_campo', plugins_url( 'assets/js/apg-city-campo.js', __FILE__ ), [ 'select2' ] );
			wp_enqueue_script( 'apg_city_campo' );
			if ( isset( $apg_city_settings['api'] ) && $apg_city_settings['api'] == "google" ) {
				wp_register_script( 'apg_city', plugins_url( 'assets/js/apg-city-google.js', __FILE__ ), [ 'select2' ] );
			} else {
				wp_register_script( 'apg_city', plugins_url( 'assets/js/apg-city-geonames.js', __FILE__ ), [ 'select2' ] );
			}
			wp_localize_script( 'apg_city', 'texto_predeterminado', __( 'Select city name', 'wc-apg-city' ) );
			wp_localize_script( 'apg_city', 'texto_carga_campo', __( 'My city isn\'t on the list', 'wc-apg-city' ) );
			wp_localize_script( 'apg_city', 'ruta_ajax', admin_url( 'admin-ajax.php' ) );
            $usuario = wp_get_current_user();
			wp_localize_script( 'apg_city', 'billing_city', get_user_meta( $usuario->ID, 'billing_city', true ) );
			wp_localize_script( 'apg_city', 'shipping_city', get_user_meta( $usuario->ID, 'shipping_city', true ) );
            if ( isset( $apg_city_settings[ 'key'] ) && !empty( $apg_city_settings[ 'key'] ) ) {
                wp_localize_script( 'apg_city', 'google_api', $apg_city_settings[ 'key'] );
            }
			wp_enqueue_script( 'apg_city' );
		}
	}
	$version = ( preg_match( '/Trident\/(.*)/', $_SERVER['HTTP_USER_AGENT'], $navegador ) ) ? intval( $matches[1] ) + 4 : 11;
	if ( $version >= 11 ) { //No funciona en Microsoft Internet Explorer 11 o anterior
		add_filter( 'woocommerce_default_address_fields', 'apg_city_campos_de_direccion' );
		add_action( 'wp_footer', 'apg_city_codigo_javascript_en_checkout' );
	}
	
	//Valida el campo ciudad para evitar fallos de JavaScript
	function apg_city_validacion_de_campo() {
		if ( $_POST['billing_city'] == 'carga_campo' || $_POST['shipping_city'] == 'carga_campo' ) {
			$campo = ( $_POST['billing_city'] == 'carga_campo' ) ? __( 'Please enter a valid <strong>billing Town / City</strong>. JavaScript is required.', 'wc-apg-city' ) : __( 'Please enter a valid <strong>shipping Town / City</strong>. JavaScript is required.', 'wc-apg-city' );
			wc_add_notice( $campo, 'error' );
		}
	}
	add_action( 'woocommerce_checkout_process', 'apg_city_validacion_de_campo' );

	//Obtiene los resultados de la API de Geonames
	function apg_city_geonames() {
		$respuesta = wp_remote_get( "https://www.geonames.org/postalCodeLookupJSON?postalcode=" . $_REQUEST['codigo_postal'] . "&country=" . $_REQUEST['pais'] );

		if ( !is_wp_error( $respuesta ) && is_array( $respuesta ) ) {
			echo $respuesta['body'];
		}

		wp_die();
	}
	add_action( 'wp_ajax_nopriv_apg_city_geonames', 'apg_city_geonames' );
	add_action( 'wp_ajax_apg_city_geonames', 'apg_city_geonames' );
} else {
	add_action( 'admin_notices', 'apg_city_requiere_wc' );
}

//Muestra el mensaje de activación de WooCommerce y desactiva el plugin
function apg_city_requiere_wc() {
	global $apg_city;
		
	echo '<div class="error fade" id="message"><h3>' . $apg_city['plugin'] . '</h3><h4>' . __( 'This plugin require WooCommerce active to run!', 'wc-apg-city' ) . '</h4></div>';
	deactivate_plugins( DIRECCION_apg_city );
}

//Muestra el mensaje de actualización
function apg_city_actualizacion() {
	global $apg_city;
	
	echo '<div class="notice notice-error is-dismissible" id="message"><h3>' . $apg_city['plugin'] . '</h3><h4>' . sprintf( __( "Please, update your %s. Google Maps API Key now is required.", 'wc-apg-city' ), '<a href="' . $apg_city['ajustes'] . '" title="' . __( 'Settings', 'wc-apg-city' ) . '">' . __( 'settings', 'wc-apg-city' ) . '</a>' ) . '</h4></div>';
}

//Eliminamos todo rastro del plugin al desinstalarlo
function apg_city_desinstalar() {
	delete_transient( 'apg_city_plugin' );
	delete_option( 'apg_city_settings' );
}
register_uninstall_hook( __FILE__, 'apg_city_desinstalar' );
