<?php
/**
 * Formulario de ajustes del plugin WC - APG City.
 *
 * Muestra el panel de configuración dentro de WooCommerce 
 * para seleccionar API, claves y opciones relacionadas.
 *
 * @package WC_APG_City
 * @global array<string,mixed> $apg_city_settings Ajustes del plugin.
 * @global array<string,string> $apg_city          Datos de información del plugin.
 */

// Igual no deberías poder abrirme.
defined( 'ABSPATH' ) || exit;

global $apg_city_settings, $apg_city;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Options page is already protected by WordPress nonces.
if ( isset( $_GET[ 'settings-updated' ] ) ) {
	if ( ( ! isset( $apg_city_settings[ 'key' ] ) || '' === $apg_city_settings[ 'key' ] ) && isset( $apg_city_settings[ 'api' ] ) && 'geonames' !== $apg_city_settings[ 'api' ] ) {
		echo '<div class="notice notice-error is-dismissible" id="wc-apg-city"><p>' . esc_html__( 'Google Maps API Key is a required field.', 'wc-apg-city' ) . '</p></div>';
		$apg_city_settings[ 'api' ] = 'geonames';
		update_option( 'apg_city_settings', $apg_city_settings );
		$apg_city_settings = get_option( 'apg_city_settings' );
	}

	if ( ( ! isset( $apg_city_settings[ 'geonames_user' ] ) || '' === $apg_city_settings[ 'geonames_user' ] ) && isset( $apg_city_settings[ 'api' ] ) && 'geonames' === $apg_city_settings[ 'api' ] ) {
		echo '<div class="notice notice-error is-dismissible" id="wc-apg-city"><p>' . esc_html__( 'GeoNames username is a required field.', 'wc-apg-city' ) . '</p></div>';
	}
}

settings_errors(); 

// Variables.
$tab = 1;
?>

<div class="wrap woocommerce">
	<h2>
		<?php esc_html_e( 'WC - APG City Options.', 'wc-apg-city' ); ?>
	</h2>
	<h3><a href="<?php echo esc_url( $apg_city[ 'plugin_url' ] ); ?>" title="Art Project Group"><?php echo esc_html( $apg_city[ 'plugin' ] ); ?></a></h3>
	<p>
		<?php esc_html_e( 'Add to WooCommerce an automatic city name generated from postcode.', 'wc-apg-city' ); ?>
	</p>
	<?php include( 'cuadro-informacion.php' ); ?>
	<form method="post" action="options.php">
		<?php settings_fields( 'apg_city_settings_group' ); ?>
		<div class="cabecera"> <a href="<?php echo esc_url( $apg_city[ 'plugin_url' ] ); ?>" title="<?php echo esc_attr( $apg_city[ 'plugin' ] ); ?>" target="_blank"><img src="<?php echo esc_url( plugins_url( '../assets/images/cabecera.jpg', __FILE__ ) ); ?>" class="imagen" alt="<?php echo esc_attr( $apg_city[ 'plugin' ] ); ?>" /></a> </div>
		<table class="form-table apg-table">
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[api]">
						<?php esc_html_e( 'Select a public API', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Select which API would you want to use', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp">
					<select class="wc-enhanced-select" id="apg_city_settings[api]" name="apg_city_settings[api]" tabindex="<?php echo esc_attr( $tab++ ); ?>">
						<option value="geonames" <?php echo ( isset( $apg_city_settings[ 'api' ] ) && $apg_city_settings[ 'api' ] == "geonames" ? ' selected="selected"' : '' ); ?>>GeoNames</option>
						<option value="google" <?php echo ( isset( $apg_city_settings[ 'api' ] ) && $apg_city_settings[ 'api' ] == "google" ? ' selected="selected"' : '' ); ?>>Google Maps</option>
					</select>
				</td>
			</tr>
			<tr valign="top" class="api">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[key]">
						<?php esc_html_e( 'Google Maps API Key', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Add your own Google Maps API Key.', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp forminp-text">
					<input type="text" id="apg_city_settings[key]" name="apg_city_settings[key]" tabindex="<?php echo esc_attr( $tab++ ); ?>" value="<?php echo isset( $apg_city_settings[ 'key' ] ) ? esc_attr( $apg_city_settings[ 'key' ] ) : ''; ?>"/>
					<p class="description">
						<?php
						// translators: %s is a link to Google API Console.
						echo wp_kses_post( sprintf( __( 'Get your own API Key from %s.', 'wc-apg-city' ), '<a href="https://console.developers.google.com/flows/enableapi?apiid=maps_backend,geocoding_backend,directions_backend,distance_matrix_backend,elevation_backend,places_backend&reusekey=true&hl=' . strtoupper( substr( get_bloginfo( 'language' ), 0, 2 ) ) . '" target="_blank">Google API Console</a>' ) );
						?>
					</p>
				</td>
			</tr>
			<tr valign="top" class="geonames">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[geonames_user]">
						<?php esc_html_e( 'GeoNames username', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Add the username that you registered in GeoNames.', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp forminp-text">
					<input type="text" id="apg_city_settings[geonames_user]" name="apg_city_settings[geonames_user]" tabindex="<?php echo esc_attr( $tab++ ); ?>" value="<?php echo ( isset( $apg_city_settings[ 'geonames_user' ] ) ? esc_attr( $apg_city_settings[ 'geonames_user' ] ) : '' ); ?>"/>
					<p class="description">
						<?php
						// translators: %s is a link to GeoNames website.
						echo wp_kses_post( sprintf( __( 'Create your own username in %s.', 'wc-apg-city' ), '<a href="https://www.geonames.org/login" target="_blank">GeoNames</a>' ) );
						?>
					</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[predeterminado]">
						<?php esc_html_e( 'Default option', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Type your own default option text for the select field.', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp"><input id="apg_city_settings[predeterminado]" name="apg_city_settings[predeterminado]" type="text" value="<?php echo ( isset( $apg_city_settings[ 'predeterminado' ] ) && ! empty( $apg_city_settings[ 'predeterminado' ] ) ? esc_attr( $apg_city_settings[ 'predeterminado' ] ) : esc_attr__( 'Select city name', 'wc-apg-city' ) ); ?>" tabindex="<?php echo esc_attr( $tab++ ); ?>" placeholder="<?php esc_attr_e( 'Please enter a default option text for the select field.', 'wc-apg-city' ); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[carga]">
						<?php esc_html_e( 'Option to switch', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Type your own text for the option to switch to input text.', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp"><input id="apg_city_settings[carga]" name="apg_city_settings[carga]" type="text" value="<?php echo ( isset( $apg_city_settings[ 'carga' ] ) && ! empty( $apg_city_settings[ 'carga' ] ) ? esc_attr( $apg_city_settings[ 'carga' ] ) : esc_attr__( 'My city isn\'t on the list', 'wc-apg-city' ) ); ?>" tabindex="<?php echo esc_attr( $tab++ ); ?>" placeholder="<?php esc_attr_e( 'Please enter a text for the option to switch to input text.', 'wc-apg-city' ); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[bloqueo]">
						<?php esc_html_e( 'Block fields', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Mark it to lock the city and state fields so that they cannot be modified.', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp"><input id="apg_city_settings[bloqueo]" name="apg_city_settings[bloqueo]" type="checkbox" value="1" <?php checked( isset( $apg_city_settings[ 'bloqueo' ] ) ? $apg_city_settings[ 'bloqueo' ] : '', 1 ); ?> tabindex="<?php echo esc_attr( $tab++ ); ?>" /></td>
			</tr>
			<tr valign="top" class="bloqueo-color">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[bloqueo_color]">
						<?php esc_html_e( 'Locked fields background', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Select the background color used when the city and state fields are locked.', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp forminp-text bloqueo-color-inputs">
					<input type="text" id="apg_city_settings[bloqueo_color_text]" name="apg_city_settings[bloqueo_color_text]" value="<?php echo esc_attr( isset( $apg_city_settings[ 'bloqueo_color' ] ) ? $apg_city_settings[ 'bloqueo_color' ] : '#eeeeee' ); ?>" tabindex="<?php echo esc_attr( $tab++ ); ?>" placeholder="#rrggbb" class="regular-text" />
					<input type="color" id="apg_city_settings[bloqueo_color]" name="apg_city_settings[bloqueo_color]" value="<?php echo esc_attr( isset( $apg_city_settings[ 'bloqueo_color' ] ) ? $apg_city_settings[ 'bloqueo_color' ] : '#eeeeee' ); ?>" tabindex="<?php echo esc_attr( $tab++ ); ?>" />
					<p class="description"><?php esc_html_e( 'Enter the HEX/RGB value (e.g. #eeeeee) or pick a color.', 'wc-apg-city' ); ?></p>
				</td>
			</tr>
        </table>
		<?php submit_button(); ?>
	</form>
</div>
<script>
( function( $ ) {
	// Muestra u oculta las filas según la API seleccionada.
    var $api = $( '#apg_city_settings\\[api\\]' );
    var $bloqueo = $( '#apg_city_settings\\[bloqueo\\]' );
    var $bloqueoColor = $( '#apg_city_settings\\[bloqueo_color\\]' );
    var $bloqueoColorText = $( '#apg_city_settings\\[bloqueo_color_text\\]' );
    var toggleRows = function( value ) {
        if ( value === 'google' ) {
            $( '.api' ).show();
            $( '.geonames' ).hide();
        } else {
            $( '.api' ).hide();
            $( '.geonames' ).show();
        }
    };
	// Muestra u oculta las opciones de color de bloqueo.
    var toggleBloqueoColor = function( checked ) {
        $( '.bloqueo-color' ).toggle( !! checked );
    };
	// Sincroniza los campos de color.
    var syncColorInputs = function( value, fromText ) {
        var hex = ( value || '' ).trim();
        if ( ! hex ) {
            return;
        }
        if ( fromText && hex.charAt(0) !== '#' ) {
            hex = '#' + hex;
        }
        var match = hex.match( /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/ );
        if ( ! match ) {
            return;
        }
        if ( match[1].length === 3 ) { // Expande formato #rgb a #rrggbb.
            hex = '#' + match[1].split( '' ).map( function( c ) { return c + c; } ).join( '' );
        }
        $bloqueoColor.val( hex );
        $bloqueoColorText.val( hex );
    };

    toggleRows( $api.val() );
    toggleBloqueoColor( $bloqueo.is( ':checked' ) );
    syncColorInputs( $bloqueoColor.val() );

    $api.on( 'change', function() {
        toggleRows( this.value );
    } );
    $bloqueo.on( 'change', function() {
        toggleBloqueoColor( this.checked );
    } );
    $bloqueoColor.on( 'change', function() {
        syncColorInputs( this.value );
    } );
    $bloqueoColorText.on( 'change keyup', function() {
        syncColorInputs( this.value, true );
    } );
} )( jQuery );
</script>
