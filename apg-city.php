<?php
/*
Plugin Name: WC - APG City
Requires Plugins: woocommerce
Version: 2.0.3
Plugin URI: https://wordpress.org/plugins/wc-apg-city/
Description: Adds automatic city detection from postcode to WooCommerce.
Author URI: https://artprojectgroup.es/
Author: Art Project Group
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.0
Tested up to: 6.9
WC requires at least: 5.6
WC tested up to: 10.4.0

Text Domain: wc-apg-city
Domain Path: /languages

@package WC_APG_City
@category Core
@author Art Project Group
*/

// Igual no deberías poder abrirme.
defined( 'ABSPATH' ) || exit;

/**
 * Constante con la ruta base del plugin.
 * @var string
 */
define( 'DIRECCION_apg_city', plugin_basename( __FILE__ ) );

/**
 * Constante con la versión actual del plugin.
 * @var string
 */
define( 'VERSION_apg_city', '2.0.3' );

// Funciones generales de APG.
include_once( 'includes/admin/funciones-apg.php' );
// Gestión local GeoNames.
include_once( 'includes/geonames-local.php' );
// Compatibilidad con Checkout Blocks.
include_once( 'includes/bloques.php' );

$apg_city_settings = get_option( 'apg_city_settings' );

// Hooks de la capa local GeoNames.
add_filter( 'cron_schedules', 'apg_city_cron_schedules' );
add_action( APG_CITY_CRON_HOOK, 'apg_city_refresh_data' );
add_action( 'init', 'apg_city_schedule_updates' );
register_activation_hook( __FILE__, 'apg_city_activate' );
register_deactivation_hook( __FILE__, 'apg_city_unschedule_updates' );
register_uninstall_hook( __FILE__, 'apg_city_desinstalar' );
add_action( 'wp_ajax_apg_city_lookup', 'apg_city_ajax_lookup' );
add_action( 'wp_ajax_nopriv_apg_city_lookup', 'apg_city_ajax_lookup' );
add_action( 'wp_ajax_apg_city_api_lookup', 'apg_city_api_lookup' );
add_action( 'wp_ajax_nopriv_apg_city_api_lookup', 'apg_city_api_lookup' );

// ¿Está activo WooCommerce?
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) || is_network_only_plugin( 'woocommerce/woocommerce.php' ) ) {
    // Añade compatibilidad con HPOS.
    add_action( 'before_woocommerce_init', function() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    } );
    
    /**
	 * Renderiza la pestaña de ajustes del plugin en el área de administración.
	 *
	 * @return void
	 */
	function apg_city_tab() {
		include( 'includes/formulario.php' );
	}

	/**
	 * Añade la página de ajustes de WC - APG City al menú de WooCommerce.
	 *
	 * @return void
	 */
	function apg_city_admin_menu() {
		add_submenu_page( 'woocommerce', __( 'APG City', 'wc-apg-city' ),  __( 'City field', 'wc-apg-city' ) , 'manage_woocommerce', 'wc-apg-city', 'apg_city_tab' );
	}
	add_action( 'admin_menu', 'apg_city_admin_menu', 15 );

	/**
	 * Registra las opciones del plugin para la API de ajustes de WordPress.
	 *
	 * @return void
	 */
	function apg_city_sanitize_settings( $settings ) {
		$sanitized = [];
		$default_lock_color = '#eeeeee';

		if ( isset( $settings[ 'api' ] ) ) {
			$sanitized[ 'api' ] = in_array( $settings[ 'api' ], [ 'geonames', 'google' ], true ) ? $settings[ 'api' ] : 'geonames';
		}

		if ( isset( $settings[ 'key' ] ) ) {
			$sanitized[ 'key' ] = sanitize_text_field( $settings[ 'key' ] );
		}

		if ( isset( $settings[ 'geonames_user' ] ) ) {
			$sanitized[ 'geonames_user' ] = sanitize_text_field( $settings[ 'geonames_user' ] );
		}

		if ( isset( $settings[ 'predeterminado' ] ) ) {
			$sanitized[ 'predeterminado' ] = sanitize_text_field( $settings[ 'predeterminado' ] );
		}

		if ( isset( $settings[ 'carga' ] ) ) {
			$sanitized[ 'carga' ] = sanitize_text_field( $settings[ 'carga' ] );
		}

		$sanitized[ 'bloqueo' ] = ( isset( $settings[ 'bloqueo' ] ) && '1' === (string) $settings[ 'bloqueo' ] ) ? 1 : 0;
		if ( $sanitized[ 'bloqueo' ] && isset( $settings[ 'bloqueo_color' ] ) ) {
			$color = sanitize_hex_color( $settings[ 'bloqueo_color' ] );
			$sanitized[ 'bloqueo_color' ] = $color ? $color : $default_lock_color;
		}

		return $sanitized;
	}

	function apg_city_registra_opciones() {
		global $apg_city_settings;

		register_setting(
			'apg_city_settings_group',
			'apg_city_settings',
			[
				'type'              => 'array',
				'sanitize_callback' => 'apg_city_sanitize_settings',
				'show_in_rest'      => false,
			]
		);
	}
	add_action( 'admin_init', 'apg_city_registra_opciones' );

	/**
	 * Añade la pantalla personalizada del plugin a los IDs de pantallas de WooCommerce.
	 *
	 * @param string[] $woocommerce_screen_ids IDs de pantalla de WooCommerce.
	 *
	 * @return string[] IDs de pantalla de WooCommerce actualizados.
	 */
	function apg_city_screen_id( $woocommerce_screen_ids ) {
		$woocommerce_screen_ids[] = 'woocommerce_page_wc-apg-city';

		return $woocommerce_screen_ids;
	}
	add_filter( 'woocommerce_screen_ids', 'apg_city_screen_id' );
	
	/**
	 * Modifica el campo de ciudad en los campos de dirección de WooCommerce.
	 *
	 * @param array $campos Campos de dirección por defecto.
	 *
	 * @return array Campos de dirección modificados.
	 */
	function apg_city_campos_de_direccion( $campos ) {
		global $apg_city_settings;
        
		$campos[ 'city' ]	= [
			'label'         => __( 'Town / City', 'wc-apg-city' ),
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
        
        if ( isset( $apg_city_settings[ 'bloqueo' ] ) && $apg_city_settings[ 'bloqueo' ] == "1" ) { // Bloquea los campos.
            $campos[ 'city' ][ 'custom_attributes' ] = [ 'readonly' => 'readonly' ];            
            $campos[ 'state' ][ 'custom_attributes' ] = [ 'readonly' => 'readonly' ];            
        }

		return $campos;
	}
	
	/**
	 * Encola y localiza el JavaScript necesario para el checkout y la página de cuenta.
	 *
	 * También añade estilos para simular la propiedad readonly en select2 cuando procede.
	 *
	 * @return void
	 */
	function apg_city_codigo_javascript_en_checkout() {
		if ( is_checkout() || is_account_page() ) {
			global $apg_city_settings;
			
			// Comprueba la API.
            $google_api     = ( isset( $apg_city_settings[ 'key' ] ) && ! empty( $apg_city_settings[ 'key' ] ) ) ? sanitize_text_field( $apg_city_settings[ 'key' ] ) : '';
            $geonames_user  = ( isset( $apg_city_settings[ 'geonames_user' ] ) && ! empty( $apg_city_settings[ 'geonames_user' ] ) ) ? sanitize_text_field( $apg_city_settings[ 'geonames_user' ] ) : '';
            $script         = '';
			$has_local_data = apg_city_local_data_available();
            if ( isset( $apg_city_settings[ 'api' ] ) ) {
                if ( 'google' === $apg_city_settings[ 'api' ] && $google_api ) {
                    $script = 'comprueba_google';
                } elseif ( 'geonames' === $apg_city_settings[ 'api' ] && $geonames_user ) {
                    $script = 'comprueba_geonames';
                }
            }
			if ( empty( $script ) && ! $has_local_data ) { // No hay API seleccionada o incompleta y tampoco datos locales.
				return;
            }
            // Variables.
			$bloqueo_color = '#eeeeee';
			if ( isset( $apg_city_settings[ 'bloqueo_color' ] ) ) {
				$color = sanitize_hex_color( $apg_city_settings[ 'bloqueo_color' ] );
				if ( $color ) {
					$bloqueo_color = $color;
				}
			}
			wp_register_script( 'apg_city_campo', plugins_url( 'assets/js/apg-city-campo.js', __FILE__ ), [ 'select2' ], VERSION_apg_city, 'all' );
			wp_register_style( 'apg_city_front_style', plugins_url( 'assets/css/apg-cigy-classic.css', __FILE__ ), [], VERSION_apg_city );
            $bloqueo = ( isset( $apg_city_settings[ 'bloqueo' ] ) && $apg_city_settings[ 'bloqueo' ] == "1" ) ? true : false;
			wp_localize_script( 'apg_city_campo', 'funcion', [ $script ] );
			wp_localize_script( 'apg_city_campo', 'bloqueo', [ $bloqueo ] );
			wp_localize_script( 'apg_city_campo', 'texto_predeterminado', [ $apg_city_settings[ 'predeterminado' ] ] );
			wp_localize_script( 'apg_city_campo', 'texto_carga_campo', [ $apg_city_settings[ 'carga' ] ] );
			wp_localize_script( 'apg_city_campo', 'ruta_ajax', [ admin_url( 'admin-ajax.php' ) ] );
			wp_localize_script( 'apg_city_campo', 'google_api', [ $google_api ] );
			wp_localize_script( 'apg_city_campo', 'geonames_user', [ $geonames_user ] );
			wp_localize_script(
				'apg_city_campo',
				'apg_city_lookup_settings',
				[
					'ajax_url'  => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'apg_city_lookup' ),
					'has_local' => $has_local_data,
					'fallback'  => $script,
				]
			);
            // Carga los scripts.
			wp_enqueue_script( 'apg_city_campo' );
			if ( $bloqueo && ! wp_script_is( 'apg-city-blocks', 'enqueued' ) ) {
				wp_enqueue_style( 'apg_city_front_style' );
				wp_add_inline_style( 'apg_city_front_style', ':root{--apg-city-locked-bg:' . esc_attr( $bloqueo_color ) . ';}' );
			}
		}
	}
	$apg_city_user_agent = '';
	if ( ! empty( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) {
		$apg_city_user_agent = sanitize_text_field( wp_unslash( $_SERVER[ 'HTTP_USER_AGENT' ] ) );
	}

	if ( ! empty( $apg_city_user_agent ) ) {
		$apg_city_version_ie = ( preg_match( '/Trident\/(.*)/', $apg_city_user_agent, $apg_city_navegador ) ) ? intval( $apg_city_navegador[1] ) + 4 : 11;
		if ( $apg_city_version_ie >= 11 ) { // No funciona en Microsoft Internet Explorer 10 o anterior.
			add_filter( 'woocommerce_default_address_fields', 'apg_city_campos_de_direccion' );
			add_action( 'wp_footer', 'apg_city_codigo_javascript_en_checkout' );
		}
	}
	
	/**
	 * Valida el campo de ciudad para evitar fallos cuando no se ejecuta JavaScript.
	 *
	 * @return void
	 */
	function apg_city_validacion_de_campo() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$billing_city  = isset( $_POST[ 'billing_city' ] ) ? wc_clean( wp_unslash( $_POST[ 'billing_city' ] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$shipping_city = isset( $_POST[ 'shipping_city' ] ) ? wc_clean( wp_unslash( $_POST[ 'shipping_city' ] ) ) : '';

		if ( 'carga_campo' === $billing_city || 'carga_campo' === $shipping_city ) {
			$campo = ( 'carga_campo' === $billing_city )
				? __( 'Please enter a valid <strong>billing Town / City</strong>. JavaScript is required.', 'wc-apg-city' )
				: __( 'Please enter a valid <strong>shipping Town / City</strong>. JavaScript is required.', 'wc-apg-city' );
			wc_add_notice( $campo, 'error' );
		}
	}
	add_action( 'woocommerce_checkout_process', 'apg_city_validacion_de_campo' );
} else {
	add_action( 'admin_notices', 'apg_city_requiere_wc' );
}

/**
 * Muestra un aviso en el área de administración si WooCommerce no está activo.
 *
 * Desactiva el plugin al no cumplirse la dependencia.
 *
 * @return void
 */
function apg_city_requiere_wc() {
	global $apg_city;

	echo '<div class="notice notice-error is-dismissible" id="wc-apg-city"><h3>' . esc_html( $apg_city[ 'plugin' ] ) . '</h3><h4>' . esc_html__( 'This plugin requires WooCommerce to be active in order to run!', 'wc-apg-city' ) . '</h4></div>';
	deactivate_plugins( DIRECCION_apg_city );
}

/**
 * Limpia las opciones y transients del plugin al desinstalarlo.
 *
 * @return void
 */
function apg_city_desinstalar() {
	delete_transient( 'apg_city_plugin' );
	delete_option( 'apg_city_settings' );
	delete_option( 'apg_city_last_import' );
	delete_option( 'apg_city_rows' );
	delete_option( 'apg_city_last_hash' );
	delete_transient( 'apg_city_seed_scheduled' );
	apg_city_clear_import_state();
	apg_city_unschedule_updates();

	if ( apg_city_table_exists() ) {
		global $wpdb;
		$table_name = esc_sql( apg_city_get_table_name() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Drop table on uninstall.
		$wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );
	}
}
register_uninstall_hook( __FILE__, 'apg_city_desinstalar' );
