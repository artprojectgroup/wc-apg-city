jQuery( document ).ready( function() {
	//Función que chequea el código postal en Google Maps
	var comprueba_geonames = function( formulario ) {
		jQuery.getJSON( "http://www.geonames.org/postalCodeLookupJSON?postalcode=" + jQuery( '#' + formulario + '_postcode' ).val() + "&country=" + jQuery( '#' + formulario + '_country' ).val(), function( data ) {
			//Limpiamos y metemos la opción inicial
			jQuery( '#' + formulario + '_city' ).empty();
			jQuery( '#' + formulario + '_city' ).append( 
				jQuery( "<option></option>" ).attr( "value", "" ).text( "Select city name" )
			);

			if ( data.postalcodes.length > 0 ) {
				if ( data.postalcodes.length > 1 ) { //Es un código postal con múltiples localidades
					jQuery.each( data.postalcodes, function( key, value ) { 
						jQuery( '#' + formulario + '_city' ).append( 
							jQuery( "<option></option>" ).attr( "value", data.postalcodes[key].placeName ).text( data.postalcodes[key].placeName )
						);
					} );
				} else { //Es un código postal único
					jQuery( '#' + formulario + '_city' ).append( 
						jQuery( "<option></option>" ).attr( "value", data.postalcodes[0].placeName ).text( data.postalcodes[0].placeName )
					);
				}
				//Actualizamos los campos select
				jQuery( '#' + formulario + '_city option[value="' + data.postalcodes[0].placeName + '"]' ).attr( 'selected', 'selected' ).trigger( "change" );
				jQuery( '#' + formulario + '_state option[value=' + data.postalcodes[0].adminCode2 + ']' ).attr( 'selected', 'selected' ).trigger( "change" );
			}		
		} );
	}
	
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
