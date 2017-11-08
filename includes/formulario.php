<?php global $apg_city_settings, $apg_city;

if ( isset( $_GET[ 'settings-updated' ] ) && ( !isset( $apg_city_settings['key'] ) || empty( $apg_city_settings['key'] ) ) ) {
	echo "<div class='notice notice-error is-dismissible'><p>" . __( 'Google Maps API Key is a required field.', 'wc-apg-city' ) . "</p></div>";
}
?>

<div class="wrap woocommerce">
	<h2>
		<?php _e( 'APG City Options.', 'wc-apg-city' ); ?>
	</h2>
	<?php 
	settings_errors(); 
	$tab = 1;
	?>
	<h3><a href="<?php echo $apg_city['plugin_url']; ?>" title="Art Project Group"><?php echo $apg_city['plugin']; ?></a></h3>
	<p>
		<?php _e( 'Add to WooCommerce an automatic city name generated from postcode.', 'wc-apg-city' ); ?>
	</p>
	<?php include( 'cuadro-informacion.php' ); ?>
	<form method="post" action="options.php">
		<?php settings_fields( 'apg_city_settings_group' ); ?>
		<div class="cabecera"> <a href="<?php echo $apg_city['plugin_url']; ?>" title="<?php echo $apg_city['plugin']; ?>" target="_blank"><img src="<?php echo plugins_url( '../assets/images/cabecera.jpg', __FILE__ ); ?>" class="imagen" alt="<?php echo $apg_city['plugin']; ?>" /></a> </div>
		<table class="form-table apg-table">
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[api]">
						<?php _e( 'Select a public API', 'wc-apg-city' ); ?>
					</label>
					<span class="woocommerce-help-tip" data-tip="<?php _e( 'Select which API would you want to use', 'wc-apg-city' ); ?>"></span> </th>
				<td class="forminp">
					<select class="wc-enhanced-select" id="apg_city_settings[api]" name="apg_city_settings[api]" tabindex="<?php echo $tab++; ?>">
						<option value="geonames" <?php echo ( isset( $apg_city_settings[ 'api'] ) && $apg_city_settings[ 'api'] == "geonames" ? ' selected="selected"' : '' ); ?>>GeoNames</option>
						<option value="google" <?php echo ( isset( $apg_city_settings[ 'api'] ) && $apg_city_settings[ 'api'] == "google" ? ' selected="selected"' : '' ); ?>>Google Maps</option>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_city_settings[key]">
						<?php _e( 'Google Maps API Key', 'wc-apg-city' ); ?>
					</label>
					<span class="woocommerce-help-tip" data-tip="<?php _e( 'Add your own Google Maps API Key.', 'wc-apg-city' ); ?>"></span> </th>
				<td class="forminp forminp-text">
					<input type="text" id="apg_city_settings[key]" name="apg_city_settings[key]" tabindex="<?php echo $tab++; ?>" value="<?php echo ( isset( $apg_city_settings[ 'key'] ) ? $apg_city_settings[ 'key'] : '' ); ?>" />
				</td>
			</tr>
		</table>
		<p class="submit">
			<input class="button-primary" type="submit" value="<?php _e( 'Save Changes', 'wc-apg-city' ); ?>" name="submit" id="submit" tabindex="<?php echo $tab++; ?>"/>
		</p>
	</form>
</div>