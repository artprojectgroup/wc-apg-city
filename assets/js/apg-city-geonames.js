jQuery( document ).ready( function() {
	//Actualiza los dos formularios
	if ( jQuery( '#billing_country' ).val() && jQuery( '#billing_postcode' ).val() ) {
		comprueba_geonames( 'billing' );
	}
	if ( jQuery( '#shipping_country' ).val() && jQuery( '#shipping_postcode' ).val() ) {
		comprueba_geonames( 'shipping' );
	}

	//Actualiza el formulario de facturación
	jQuery( '#billing_postcode' ).live( 'change', function() {
		if ( jQuery( '#billing_country' ).val() && jQuery( '#billing_postcode' ).val() ) {
			comprueba_geonames( 'billing' );
		}
	} );
	
	//Actualiza el formulario de envío
	jQuery( '#shipping_postcode' ).live( 'change', function() {
		if ( jQuery( '#shipping_country' ).val() && jQuery( '#shipping_postcode' ).val() ) {
			comprueba_geonames( 'shipping' );
		}
	} );
} );
