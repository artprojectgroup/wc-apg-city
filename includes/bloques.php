<?php
/**
 * Integración con WooCommerce Checkout Blocks.
 *
 * @package WC_APG_City
 */

defined( 'ABSPATH' ) || exit;

/**
 * Encola los assets específicos para Checkout Blocks.
 *
 * @return void
 */
function apg_city_enqueue_blocks_assets() {
	if ( is_admin() ) {
		return;
	}
	if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Package' ) ) {
		return;
	}
	if ( function_exists( 'has_block' ) && ! has_block( 'woocommerce/checkout' ) ) {
		return;
	}

	global $apg_city_settings;

	$google_api     = ( isset( $apg_city_settings[ 'key' ] ) && ! empty( $apg_city_settings[ 'key' ] ) ) ? sanitize_text_field( $apg_city_settings[ 'key' ] ) : '';
	$geonames_user  = ( isset( $apg_city_settings[ 'geonames_user' ] ) && ! empty( $apg_city_settings[ 'geonames_user' ] ) ) ? sanitize_text_field( $apg_city_settings[ 'geonames_user' ] ) : '';
	$bloqueo        = ( isset( $apg_city_settings[ 'bloqueo' ] ) && $apg_city_settings[ 'bloqueo' ] == "1" ) ? true : false;
	$has_local_data = apg_city_local_data_available();
	$fallback       = '';
	$bloqueo_color  = '#eeeeee';

	if ( isset( $apg_city_settings[ 'api' ] ) ) {
		if ( 'google' === $apg_city_settings[ 'api' ] && $google_api ) {
			$fallback = 'google';
		} elseif ( 'geonames' === $apg_city_settings[ 'api' ] && $geonames_user ) {
			$fallback = 'geonames';
		}
	}

	if ( ! $has_local_data && '' === $fallback ) {
		return;
	}

	if ( isset( $apg_city_settings[ 'bloqueo_color' ] ) ) {
		$color = sanitize_hex_color( $apg_city_settings[ 'bloqueo_color' ] );
		if ( $color ) {
			$bloqueo_color = $color;
		}
	}

	wp_register_script(
		'apg-city-blocks',
		plugins_url( '../assets/js/apg-city-blocks.js', __FILE__ ),
		[
			'wc-blocks-checkout',
			'wc-blocks-registry',
			'wp-element',
			'wp-data',
		],
		VERSION_apg_city,
		true
	);

	wp_localize_script(
		'apg-city-blocks',
		'apg_city_blocks_settings',
		[
			'ajax_url'        => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'apg_city_lookup' ),
			'has_local'       => $has_local_data,
			'fallback'        => $fallback,
			'texto_predeterminado' => isset( $apg_city_settings[ 'predeterminado' ] ) ? $apg_city_settings[ 'predeterminado' ] : __( 'Select city name', 'wc-apg-city' ),
			'texto_carga_campo'    => isset( $apg_city_settings[ 'carga' ] ) ? $apg_city_settings[ 'carga' ] : __( "My city isn't on the list", 'wc-apg-city' ),
			'bloqueo'         => $bloqueo,
		]
	);

	wp_enqueue_script( 'apg-city-blocks' );

	wp_register_style( 'apg_city_blocks_style', plugins_url( '../assets/css/apg-city-blocks.css', __FILE__ ), [], VERSION_apg_city );
	wp_register_style( 'apg_city_blocks_position', plugins_url( '../assets/css/apg-city-blocks-position.css', __FILE__ ), [], VERSION_apg_city );

	if ( $bloqueo ) {
		wp_add_inline_style( 'apg_city_blocks_style', ':root{--apg-city-locked-bg:' . esc_attr( $bloqueo_color ) . ';}' );
		wp_enqueue_style( 'apg_city_blocks_style' );
	}
	wp_enqueue_style( 'apg_city_blocks_position' );
}
add_action( 'enqueue_block_assets', 'apg_city_enqueue_blocks_assets' );
