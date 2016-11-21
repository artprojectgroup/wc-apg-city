jQuery( document ).ready( function() {
	//Función que chequea el código postal en Google Maps
	var comprueba_geonames = function( formulario ) {
		var datos = {
			'action'		: 'apg_city_geonames',
			'codigo_postal'	: jQuery( '#' + formulario + '_postcode' ).val(),
			'pais'			: jQuery( '#' + formulario + '_country' ).val()
		};
		jQuery.getJSON( ruta_ajax, datos,  function( data ) {
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
				var provincia = ( jQuery.isNumeric( data.postalcodes[0].adminCode2 ) ) ? data.postalcodes[0].adminCode1 : data.postalcodes[0].adminCode2;
				jQuery( '#' + formulario + '_city option[value="' + data.postalcodes[0].placeName + '"]' ).attr( 'selected', 'selected' ).trigger( "change" );
				if ( data.postalcodes.length > 1 ) {
					jQuery( '#s2id_' + formulario + '_city' ).data('select2').open();
				}
				jQuery( '#' + formulario + '_state option[value=' + provincia + ']' ).attr( 'selected', 'selected' ).trigger( "change" );
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
