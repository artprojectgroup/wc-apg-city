jQuery( document ).ready( function() {
	//Actualiza los dos formularios
	if ( jQuery( '#billing_country' ).val() && jQuery( '#billing_postcode' ).val() ) {
        jQuery( '#billing_city' ).append( 
		  jQuery( "<option></option>" ).attr( "value", billing_city ).text( billing_city ).attr( 'selected', true )
		);
	}
	if ( jQuery( '#shipping_country' ).val() && jQuery( '#shipping_postcode' ).val() ) {
        jQuery( '#shipping_city' ).append( 
		  jQuery( "<option></option>" ).attr( "value", shipping_city ).text( shipping_city ).attr( 'selected', true )
		);
	}

	//Actualiza el formulario de facturación
	jQuery( '#billing_postcode, #billing_country' ).live( 'change', function() {
		if ( jQuery( '#billing_country' ).val() && jQuery( '#billing_postcode' ).val() ) {
			comprueba_google( 'billing' );
		}
	} );
	
	//Actualiza el formulario de envío
	jQuery( '#shipping_postcode, #shipping_country' ).live( 'change', function() {
		if ( jQuery( '#shipping_country' ).val() && jQuery( '#shipping_postcode' ).val() ) {
			comprueba_google( 'shipping' );
		}
	} );
} );
