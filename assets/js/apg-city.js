jQuery( document ).ready( function() {
	//Función que chequea el código postal en Google Maps
	var comprueba_google = function( formulario ) {
		jQuery.getJSON( "http://maps.googleapis.com/maps/api/geocode/json?components=postal_code:" + jQuery( '#' + formulario + '_country' ).val() + jQuery( '#' + formulario + '_postcode' ).val(), function( data ) {
			//Limpiamos y metemos la opción inicial
			jQuery( '#' + formulario + '_city' ).empty();
			jQuery( '#' + formulario + '_city' ).append( 
				jQuery( "<option></option>" ).attr( "value", "" ).text( "Select city name" )
			);
			
			if ( data.status !== 'ZERO_RESULTS' ) {
				if ( data.results[0].postcode_localities ) { //Es un código postal con múltiples localidades
					jQuery.each( data.results[0].postcode_localities, function( key, value ) { 
						jQuery( '#' + formulario + '_city' ).append( 
							jQuery( "<option></option>" ).attr( "value", value ).text( value )
						);
					} );
					//Actualizamos el campo select
					jQuery( '#' + formulario + '_city option[value="' + data.results[0].address_components[1].long_name + '"]' ).attr( 'selected', 'selected' ).trigger( "change" );
				} else { //Es un código postal único
					jQuery( '#' + formulario + '_city' ).append( 
						jQuery( "<option></option>" ).attr( "value", data.results[0].address_components[1].long_name ).text( data.results[0].address_components[1].long_name )
					);
					//Actualizamos el campo select
					jQuery( '#' + formulario + '_city option[value="' + data.results[0].address_components[1].long_name + '"]' ).attr( 'selected', 'selected' ).trigger( "change" );
				}
			
				jQuery( '#' + formulario + '_state option:contains(' + data.results[0].address_components[3].long_name + ')' ).attr( 'selected', 'selected' ).trigger( "change" );
			}		
		} );
	}
	
	//Actualiza los dos formularios
	if ( jQuery( '#billing_country' ).val() && jQuery( '#billing_postcode' ).val() ) {
		comprueba_google( 'billing' );
	}
	if ( jQuery( '#shipping_country' ).val() && jQuery( '#shipping_postcode' ).val() ) {
		comprueba_google( 'shipping' );
	}

	//Actualiza el formulario de facturación
	jQuery( '#billing_postcode' ).live( 'change', function() {
		if ( jQuery( '#billing_country' ).val() && jQuery( '#billing_postcode' ).val() ) {
			comprueba_google( 'billing' );
		}
	} );
	
	//Actualiza el formulario de envío
	jQuery( '#shipping_postcode' ).live( 'change', function() {
		if ( jQuery( '#shipping_country' ).val() && jQuery( '#shipping_postcode' ).val() ) {
			comprueba_google( 'shipping' );
		}
	} );
} );
