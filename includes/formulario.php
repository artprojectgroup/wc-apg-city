<?php
//Igual no deberías poder abrirme
defined( 'ABSPATH' ) || exit;

global $apg_city_settings, $apg_city;

if ( isset( $_GET[ 'settings-updated' ] ) && ( ! isset( $apg_city_settings[ 'key' ] ) || empty( $apg_city_settings[ 'key' ] ) && ( isset( $apg_city_settings[ 'api' ] ) && $apg_city_settings[ 'api' ] != 'geonames') ) ) {
	echo "<div class='notice notice-error is-dismissible' id='wc-apg-city'><p>" . __( 'Google Maps API Key is a required field.', 'wc-apg-city' ) . "</p></div>";
    $apg_city_settings[ 'api' ] = 'geonames';
    update_option( 'apg_city_settings', $apg_city_settings );
    $apg_city_settings = get_option( 'apg_city_settings' );
}

settings_errors(); 

//Variables
$tab = 1;
?>

<div class="wrap woocommerce">
	<h2>
		<?php _e( 'WC - APG City Options.', 'wc-apg-city' ); ?>
	</h2>
	<h3><a href="<?php echo $apg_city[ 'plugin_url' ]; ?>" title="Art Project Group"><?php echo $apg_city[ 'plugin' ]; ?></a></h3>
	<p>
		<?php _e( 'Add to WooCommerce an automatic city name generated from postcode.', 'wc-apg-city' ); ?>
	</p>
	<?php include( 'cuadro-informacion.php' ); ?>
	<form method="post" action="options.php">
		<?php settings_fields( 'apg_city_settings_group' ); ?>
		<div class="cabecera"> <a href="<?php echo $apg_city[ 'plugin_url' ]; ?>" title="<?php echo $apg_city[ 'plugin' ]; ?>" target="_blank"><img src="<?php echo plugins_url( '../assets/images/cabecera.jpg', __FILE__ ); ?>" class="imagen" alt="<?php echo $apg_city[ 'plugin' ]; ?>" /></a> </div>
		<table class="form-table apg-table">
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[api]">
						<?php _e( 'Select a public API', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php _e( 'Select which API would you want to use', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp">
					<select class="wc-enhanced-select" id="apg_city_settings[api]" name="apg_city_settings[api]" tabindex="<?php echo $tab++; ?>">
						<option value="geonames" <?php echo ( isset( $apg_city_settings[ 'api' ] ) && $apg_city_settings[ 'api' ] == "geonames" ? ' selected="selected"' : '' ); ?>>GeoNames</option>
						<option value="google" <?php echo ( isset( $apg_city_settings[ 'api' ] ) && $apg_city_settings[ 'api' ] == "google" ? ' selected="selected"' : '' ); ?>>Google Maps</option>
					</select>
				</td>
			</tr>
			<tr valign="top" class="api">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[key]">
						<?php _e( 'Google Maps API Key', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php _e( 'Add your own Google Maps API Key.', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp forminp-text">
					<input type="text" id="apg_city_settings[key]" name="apg_city_settings[key]" tabindex="<?php echo $tab++; ?>" value="<?php echo ( isset( $apg_city_settings[ 'key' ] ) ? $apg_city_settings[ 'key' ] : '' ); ?>"/>
					<p class="description"><?php echo sprintf( __( 'Get your own API Key from %s.', 'wc-apg-city' ), '<a href="https://console.developers.google.com/flows/enableapi?apiid=maps_backend,geocoding_backend,directions_backend,distance_matrix_backend,elevation_backend,places_backend&reusekey=true&hl=' . strtoupper( substr( get_bloginfo ( 'language' ), 0, 2 ) ) . '" target="_blank">Google API Console</a>' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[predeterminado]">
						<?php _e( 'Default option', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php _e( 'Type your own default option text for the select field.', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp"><input id="apg_city_settings[predeterminado]" name="apg_city_settings[predeterminado]" type="text" value="<?php echo ( isset( $apg_city_settings[ 'predeterminado'] ) && ! empty( $apg_city_settings[ 'predeterminado'] ) ? esc_attr( $apg_city_settings[ 'predeterminado'] ) : __( 'Select city name', 'wc-apg-city' ) ); ?>" tabindex="<?php echo $tab++; ?>" placeholder="<?php _e( 'Please enter a default option text for the select field.', 'wc-apg-city' ); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[carga]">
						<?php _e( 'Option to switch', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php _e( 'Type your own text for the option to switch to input text.', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp"><input id="apg_city_settings[carga]" name="apg_city_settings[carga]" type="text" value="<?php echo ( isset( $apg_city_settings[ 'carga'] ) && ! empty( $apg_city_settings[ 'carga'] ) ? esc_attr( $apg_city_settings[ 'carga'] ) : __( 'My city isn\'t on the list', 'wc-apg-city' ) ); ?>" tabindex="<?php echo $tab++; ?>" placeholder="<?php _e( 'Please enter a text for the option to switch to input text.', 'wc-apg-city' ); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[bloqueo]">
						<?php _e( 'Block fields', 'wc-apg-city' ); ?>
						<span class="woocommerce-help-tip" data-tip="<?php _e( 'Mark it to lock the city and state fields so that they cannot be modified.', 'wc-apg-city' ); ?>"></span>
					</label>
				</th>
				<td class="forminp"><input id="apg_city_settings[bloqueo]" name="apg_city_settings[bloqueo]" type="checkbox" value="1" <?php checked( isset( $apg_city_settings[ 'bloqueo' ] ) ? $apg_city_settings[ 'bloqueo' ] : '', 1 ); ?> tabindex="<?php echo $tab++; ?>" /></td>
			</tr>
        </table>
		<?php submit_button(); ?>
	</form>
</div>
<script>
//Oculta el campo API
if ( jQuery('select').val() == 'geonames' ) {
    jQuery( '.api' ).hide();        
}    
jQuery('select').on('change', function() {
    if (this.value == 'google') {
        jQuery( '.api' ).show();        
    } else {
        jQuery( '.api' ).hide();        
    }
});  
</script>
