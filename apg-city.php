<?php
/*
Plugin Name: WC - APG City
Version: 1.3.0.2
Plugin URI: https://wordpress.org/plugins/wc-apg-city/
Description: Add to WooCommerce an automatic city name generated from postcode.
Author URI: https://artprojectgroup.es/
Author: Art Project Group
Requires at least: 5.0
Tested up to: 6.6
WC requires at least: 5.6
WC tested up to: 9.1

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
    //Añade compatibilidad con HPOS
    add_action( 'before_woocommerce_init', function() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    } );
    
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
		global $apg_city_settings;
        
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
		global $apg_city_settings;
        
		$campos[ 'city' ]	= [
			'label'         => __( 'Town / City', 'woocommerce' ),
			'placeholder'   => $apg_city_settings[ 'predeterminado' ],
			'required'		=> true,
			'clear'       	=> ( in_array( 'form-row-last', $campos[ 'city' ][ 'class' ] ) ) ? "true" : "false",
			'type'        	=> 'select',
			'class'       	=> $campos[ 'city' ][ 'class' ],
			'input_class'	=> [
				'state_select'
			],
			'options'		=> [
				''				=> $apg_city_settings[ 'predeterminado' ],
				'carga_campo'	=> $apg_city_settings[ 'carga' ],
			],
			'readonly'		=> 'readonly',
			'autocomplete'	=> 'address-level2',
			'priority'      => $campos[ 'city' ][ 'priority' ],
        ];
        
        if ( isset( $apg_city_settings[ 'bloqueo' ] ) && $apg_city_settings[ 'bloqueo' ] == "1" ) { //Bloquea los campos
            $campos[ 'city' ][ 'custom_attributes' ] = [ 'readonly' => 'readonly' ];            
            $campos[ 'state' ][ 'custom_attributes' ] = [ 'readonly' => 'readonly' ];            
        }

		return $campos;
	}
	
	//Añade código JavaScript al checkout
	function apg_city_codigo_javascript_en_checkout() {
		if ( is_checkout() || is_account_page() ) {
			global $apg_city_settings;
			
            //Comprueba la API
            $google_api = ( isset( $apg_city_settings[ 'key' ] ) && ! empty( $apg_city_settings[ 'key' ] ) ) ? $apg_city_settings[ 'key' ] : '';
            //Variables
			wp_register_script( 'apg_city_campo', plugins_url( 'assets/js/apg-city-campo.js', __FILE__ ), [ 'select2' ] );
            $script     = ( isset( $apg_city_settings[ 'api' ] ) && $apg_city_settings[ 'api' ] == "google" && $google_api ) ? 'comprueba_google' : 'comprueba_geonames';
            $bloqueo    = ( isset( $apg_city_settings[ 'bloqueo' ] ) && $apg_city_settings[ 'bloqueo' ] == "1" ) ? true : false;
            wp_localize_script( 'apg_city_campo', 'funcion', [ $script ] );
            wp_localize_script( 'apg_city_campo', 'bloqueo', [ $bloqueo ] );
			wp_localize_script( 'apg_city_campo', 'texto_predeterminado', [ $apg_city_settings[ 'predeterminado' ] ] );
			wp_localize_script( 'apg_city_campo', 'texto_carga_campo', [ $apg_city_settings[ 'carga' ] ] );
			wp_localize_script( 'apg_city_campo', 'ruta_ajax', [ admin_url( 'admin-ajax.php' ) ] );
            wp_localize_script( 'apg_city_campo', 'google_api', [ $google_api ] );
            //Carga los script
			wp_enqueue_script( 'apg_city_campo' );

            if ( isset( $apg_city_settings[ 'bloqueo' ] ) && $apg_city_settings[ 'bloqueo' ] == "1" ) { //Bloquea los campos
?>
<style>
select[readonly].select2-hidden-accessible + .select2-container {
	pointer-events: none;
	touch-action: none;
}
select[readonly].select2-hidden-accessible + .select2-container .select2-selection {
	background: #eee;
	box-shadow: none;
}
select[readonly].select2-hidden-accessible + .select2-container .select2-selection__arrow, select[readonly].select2-hidden-accessible + .select2-container .select2-selection__clear {
	display: none;
}
</style>
<?php
            }
		}
	}
    if ( ! empty( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) {
        $version = ( preg_match( '/Trident\/(.*)/', $_SERVER[ 'HTTP_USER_AGENT' ], $navegador ) ) ? intval( $navegador[1] ) + 4 : 11;
        if ( $version >= 11 ) { //No funciona en Microsoft Internet Explorer 11 o anterior
            add_filter( 'woocommerce_default_address_fields', 'apg_city_campos_de_direccion' );
            add_action( 'wp_footer', 'apg_city_codigo_javascript_en_checkout' );
        }
    }
	
	//Valida el campo ciudad para evitar fallos de JavaScript
	function apg_city_validacion_de_campo() {
		if ( $_POST[ 'billing_city' ] == 'carga_campo' || $_POST[ 'shipping_city' ] == 'carga_campo' ) {
			$campo = ( $_POST[ 'billing_city' ] == 'carga_campo' ) ? __( 'Please enter a valid <strong>billing Town / City</strong>. JavaScript is required.', 'wc-apg-city' ) : __( 'Please enter a valid <strong>shipping Town / City</strong>. JavaScript is required.', 'wc-apg-city' );
			wc_add_notice( $campo, 'error' );
		}
	}
	add_action( 'woocommerce_checkout_process', 'apg_city_validacion_de_campo' );
} else {
	add_action( 'admin_notices', 'apg_city_requiere_wc' );
}

//Muestra el mensaje de activación de WooCommerce y desactiva el plugin
function apg_city_requiere_wc() {
	global $apg_city;
		
	echo '<div class="notice notice-error is-dismissible" id="wc-apg-city"><h3>' . $apg_city[ 'plugin' ] . '</h3><h4>' . __( 'This plugin require WooCommerce active to run!', 'wc-apg-city' ) . '</h4></div>';
	deactivate_plugins( DIRECCION_apg_city );
}

//Eliminamos todo rastro del plugin al desinstalarlo
function apg_city_desinstalar() {
	delete_transient( 'apg_city_plugin' );
	delete_option( 'apg_city_settings' );
}
register_uninstall_hook( __FILE__, 'apg_city_desinstalar' );
