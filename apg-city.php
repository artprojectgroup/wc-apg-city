<?php
/*
Plugin Name: WC - APG City
Requires Plugins: woocommerce
Version: 2.1.1
Plugin URI: https://wordpress.org/plugins/wc-apg-city/
Description: Adds automatic city detection from postcode to WooCommerce.
Author URI: https://artprojectgroup.es/
Author: Art Project Group
License: GNU General Public License v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 5.1
Requires PHP: 7.4
Tested up to: 7.2
WC requires at least: 5.6
WC tested up to: 11.1.0

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
define( 'VERSION_apg_city', '2.1.1' );

/**
 * Devuelve los ajustes del plugin con todas las claves presentes.
 *
 * get_option() devuelve false mientras no se hayan guardado los ajustes, y
 * varias funciones leían las claves directamente, lo que provocaba avisos de
 * PHP y un placeholder vacío en el campo de ciudad de una instalación nueva.
 *
 * @return array<string,mixed> Ajustes normalizados.
 */
function apg_city_get_settings() {
	$defaults = [
		'api'           => 'geonames',
		'key'           => '',
		'geonames_user' => '',
		'predeterminado' => __( 'Select city name', 'wc-apg-city' ),
		'carga'         => __( "My city isn't on the list", 'wc-apg-city' ),
		'bloqueo'       => 0,
		'bloqueo_color' => '#eeeeee',
	];

	$settings = get_option( 'apg_city_settings', [] );

	if ( ! is_array( $settings ) ) {
		$settings = [];
	}

	$settings = wp_parse_args( $settings, $defaults );

	// Los textos visibles no pueden quedar vacíos: el select se quedaría sin etiqueta.
	if ( '' === trim( (string) $settings['predeterminado'] ) ) {
		$settings['predeterminado'] = $defaults['predeterminado'];
	}
	if ( '' === trim( (string) $settings['carga'] ) ) {
		$settings['carga'] = $defaults['carga'];
	}

	$color                      = sanitize_hex_color( (string) $settings['bloqueo_color'] );
	$settings['bloqueo_color']  = $color ? $color : $defaults['bloqueo_color'];

	return $settings;
}

/**
 * Indica si la petición actual pinta la tienda de cara al cliente.
 *
 * Las peticiones AJAX del checkout clásico llegan por admin-ajax.php, así que
 * is_admin() es cierto en ellas y hay que dejarlas pasar. El Store API de los
 * bloques va por REST, donde is_admin() ya es falso.
 *
 * @return bool
 */
function apg_city_es_contexto_publico() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}

	$ruta = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';

	if ( '' !== $ruta && 0 === strpos( ltrim( $ruta, '/' ), 'wc-analytics/' ) ) {
		return false;
	}

	return true;
}

// Funciones generales de APG.
include_once 'includes/admin/funciones-apg.php';
// Gestión local GeoNames.
include_once 'includes/geonames-local.php';
// Compatibilidad con Checkout Blocks.
include_once 'includes/bloques.php';

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
include_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) || is_network_only_plugin( 'woocommerce/woocommerce.php' ) ) {
	// Añade compatibilidad con HPOS.
	add_action(
		'before_woocommerce_init',
		function () {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
	);

	/**
	 * Renderiza la pestaña de ajustes del plugin en el área de administración.
	 *
	 * @return void
	 */
	function apg_city_tab() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wc-apg-city' ) );
		}

		include 'includes/formulario.php';
	}

	/**
	 * Añade la página de ajustes de WC - APG City al menú de WooCommerce.
	 *
	 * @return void
	 */
	function apg_city_admin_menu() {
		add_submenu_page( 'woocommerce', __( 'APG City', 'wc-apg-city' ), __( 'City field', 'wc-apg-city' ), 'manage_woocommerce', 'wc-apg-city', 'apg_city_tab' );
	}
	add_action( 'admin_menu', 'apg_city_admin_menu', 15 );

	/**
	 * Registra las opciones del plugin para la API de ajustes de WordPress.
	 *
	 * @param array<string,mixed> $settings Ajustes a sanear.
	 *
	 * @return array<string,mixed>
	 */
	function apg_city_sanitize_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$actuales           = apg_city_get_settings();
		$sanitized          = [];
		$default_lock_color = '#eeeeee';

		$sanitized['api'] = ( isset( $settings['api'] ) && in_array( $settings['api'], [ 'geonames', 'google' ], true ) ) ? $settings['api'] : 'geonames';

		$sanitized['key']            = isset( $settings['key'] ) ? sanitize_text_field( $settings['key'] ) : '';
		$sanitized['geonames_user']  = isset( $settings['geonames_user'] ) ? sanitize_text_field( $settings['geonames_user'] ) : '';
		$sanitized['predeterminado'] = isset( $settings['predeterminado'] ) ? sanitize_text_field( $settings['predeterminado'] ) : '';
		$sanitized['carga']          = isset( $settings['carga'] ) ? sanitize_text_field( $settings['carga'] ) : '';

		$sanitized['bloqueo'] = ( isset( $settings['bloqueo'] ) && '1' === (string) $settings['bloqueo'] ) ? 1 : 0;

		// El color se guarda siempre: si solo se conservase con el bloqueo activo,
		// desactivarlo y volver a activarlo perdía el color elegido.
		$color = isset( $settings['bloqueo_color'] ) ? sanitize_hex_color( $settings['bloqueo_color'] ) : '';
		if ( ! $color ) {
			$color = isset( $actuales['bloqueo_color'] ) ? sanitize_hex_color( $actuales['bloqueo_color'] ) : '';
		}
		$sanitized['bloqueo_color'] = $color ? $color : $default_lock_color;

		return $sanitized;
	}

	/**
	 * Registra la opción del plugin en la API de ajustes.
	 *
	 * @return void
	 */
	function apg_city_registra_opciones() {
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
	 * Permite guardar los ajustes a quien puede verlos.
	 *
	 * options.php exige manage_options por defecto, así que un Gestor de tienda
	 * veía la pantalla y recibía un error de permisos al guardar.
	 *
	 * @return string Capacidad requerida para guardar el grupo de ajustes.
	 */
	function apg_city_capacidad_de_ajustes() {
		return 'manage_woocommerce';
	}
	add_filter( 'option_page_capability_apg_city_settings_group', 'apg_city_capacidad_de_ajustes' );

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
		// El campo solo tiene sentido donde se carga el JavaScript que lo rellena:
		// checkout, carrito y Mi cuenta. El plugin nunca se pensó para el panel de
		// administración, así que allí se deja el campo de texto original. Se
		// excluyen también las rutas REST de wc-analytics, que alimentan el panel
		// de React con estos mismos datos de localización.
		if ( ! apg_city_es_contexto_publico() ) {
			return $campos;
		}

		$apg_city_settings = apg_city_get_settings();

		$clases    = ( isset( $campos['city']['class'] ) && is_array( $campos['city']['class'] ) ) ? $campos['city']['class'] : [];
		$prioridad = isset( $campos['city']['priority'] ) ? $campos['city']['priority'] : 70;

		$campos['city'] = [
			'label'         => __( 'Town / City', 'wc-apg-city' ),
			'placeholder'   => $apg_city_settings['predeterminado'],
			'required'      => true,
			'clear'         => in_array( 'form-row-last', $clases, true ) ? 'true' : 'false',
			'type'          => 'select',
			'class'         => $clases,
			'input_class'   => [
				'state_select',
			],
			'options'       => [
				''            => $apg_city_settings['predeterminado'],
				'carga_campo' => $apg_city_settings['carga'],
			],
			'autocomplete'  => 'address-level2',
			'priority'      => $prioridad,
		];

		if ( '1' === (string) $apg_city_settings['bloqueo'] ) { // Bloquea los campos.
			$campos['city']['custom_attributes'] = [ 'readonly' => 'readonly' ];
			if ( isset( $campos['state'] ) ) {
				$campos['state']['custom_attributes'] = [ 'readonly' => 'readonly' ];
			}
		}

		return $campos;
	}

	/**
	 * Encola y localiza el JavaScript necesario para el checkout y la página de cuenta.
	 *
	 * También añade estilos para simular la propiedad readonly en select2 cuando procede.
	 * Se engancha a wp_enqueue_scripts con prioridad 20 para que el CSS del bloqueo
	 * viaje en la cabecera y los campos no parpadeen.
	 *
	 * @return void
	 */
	function apg_city_codigo_javascript_en_checkout() {
		if ( ! is_checkout() && ! is_account_page() ) {
			return;
		}

		// En un checkout con bloques los ids son billing-city, no billing_city:
		// este script no tendría nada que hacer y solo añadiría peso.
		if ( wp_script_is( 'apg-city-blocks', 'enqueued' ) || wp_script_is( 'apg-city-blocks', 'registered' ) ) {
			return;
		}

		$apg_city_settings = apg_city_get_settings();

		// Comprueba la API.
		$google_api     = sanitize_text_field( (string) $apg_city_settings['key'] );
		$geonames_user  = sanitize_text_field( (string) $apg_city_settings['geonames_user'] );
		$script         = '';
		$has_local_data = apg_city_local_data_available();

		if ( 'google' === $apg_city_settings['api'] && $google_api ) {
			$script = 'google';
		} elseif ( 'geonames' === $apg_city_settings['api'] && $geonames_user ) {
			$script = 'geonames';
		}

		if ( '' === $script && ! $has_local_data ) { // No hay API seleccionada o incompleta y tampoco datos locales.
			return;
		}

		$bloqueo_color = $apg_city_settings['bloqueo_color'];
		$bloqueo       = ( '1' === (string) $apg_city_settings['bloqueo'] );

		wp_register_script( 'apg_city_campo', plugins_url( 'assets/js/apg-city-campo.js', __FILE__ ), [ 'jquery' ], VERSION_apg_city, true );
		wp_register_style( 'apg_city_front_style', plugins_url( 'assets/css/apg-city-classic.css', __FILE__ ), [], VERSION_apg_city );

		// Un único objeto de configuración: las claves de API no se envían al
		// navegador, la consulta la hace siempre el servidor vía AJAX.
		wp_localize_script(
			'apg_city_campo',
			'apg_city_lookup_settings',
			[
				'ajax_url'             => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'apg_city_lookup' ),
				'has_local'            => $has_local_data,
				'fallback'             => $script,
				'bloqueo'              => $bloqueo,
				'texto_predeterminado' => $apg_city_settings['predeterminado'],
				'texto_carga_campo'    => $apg_city_settings['carga'],
			]
		);

		// Carga los scripts.
		wp_enqueue_script( 'apg_city_campo' );
		if ( $bloqueo ) {
			wp_enqueue_style( 'apg_city_front_style' );
			// sanitize_hex_color() deja solo #rrggbb: es la validación válida en contexto CSS.
			wp_add_inline_style( 'apg_city_front_style', ':root{--apg-city-locked-bg:' . sanitize_hex_color( $bloqueo_color ) . ';}' );
		}
	}
	add_filter( 'woocommerce_default_address_fields', 'apg_city_campos_de_direccion' );
	add_action( 'wp_enqueue_scripts', 'apg_city_codigo_javascript_en_checkout', 20 );

	/**
	 * Valida el campo de ciudad para evitar fallos cuando no se ejecuta JavaScript.
	 *
	 * @return void
	 */
	function apg_city_validacion_de_campo() {
		// woocommerce_checkout_process ya verifica el nonce, pero se comprueba
		// aquí también para que la lectura de $_POST sea verificable por sí misma.
		if ( ! isset( $_POST['woocommerce-process-checkout-nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'woocommerce-process_checkout' ) ) {
			return;
		}

		$billing_city  = isset( $_POST['billing_city'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_city'] ) ) : '';
		$shipping_city = isset( $_POST['shipping_city'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_city'] ) ) : '';

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

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error is-dismissible" id="wc-apg-city"><h3>' . esc_html( $apg_city['plugin'] ) . '</h3><h4>' . esc_html__( 'This plugin requires WooCommerce to be active in order to run!', 'wc-apg-city' ) . '</h4></div>';
	deactivate_plugins( DIRECCION_apg_city );
}

/**
 * Limpia las opciones y transients del plugin al desinstalarlo.
 *
 * @return void
 */
function apg_city_desinstalar() {
	if ( is_multisite() ) {
		// register_uninstall_hook() solo se ejecuta una vez: sin este recorrido,
		// la tabla y las opciones se quedaban en todos los demás sitios de la red.
		$sitios = get_sites(
			[
				'fields' => 'ids',
				'number' => 0,
			]
		);

		foreach ( $sitios as $sitio ) {
			switch_to_blog( (int) $sitio );
			apg_city_desinstalar_sitio();
			restore_current_blog();
		}

		return;
	}

	apg_city_desinstalar_sitio();
}

/**
 * Limpia las opciones, transients, archivos y tabla del sitio actual.
 *
 * @return void
 */
function apg_city_desinstalar_sitio() {
	delete_transient( 'apg_city_plugin' );
	delete_option( 'apg_city_settings' );
	delete_option( 'apg_city_last_import' );
	delete_option( 'apg_city_rows' );
	delete_option( 'apg_city_last_hash' );
	delete_transient( 'apg_city_seed_scheduled' );
	apg_city_clear_import_state();
	apg_city_delete_working_directory();
	apg_city_delete_lookup_cache();
	apg_city_unschedule_updates();

	if ( apg_city_table_exists( true ) ) {
		global $wpdb;
		$table_name = esc_sql( apg_city_get_table_name() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Drop table on uninstall.
		$wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );
	}
}
