//Función que cambia el campo select por un campo input
var carga_campo = function (formulario, bloquea = false ) {
    //Elimina select2 o selectWoo
    if (jQuery('#' + formulario + '_city').data('selectWoo')) {
        jQuery('#' + formulario + '_city').selectWoo('destroy');
    } else if (jQuery('#' + formulario + '_city').data('select2')) {
        jQuery('#' + formulario + '_city').select2('destroy');
    }
    //Desbloquea los campos
    jQuery('#' + formulario + '_city_field,#' + formulario + '_state_field').unblock();
    //Cambia el campo
    jQuery('#' + formulario + '_city').replaceWith('<input class="input-text " name="' + formulario + '_city" id="' + formulario + '_city" autocomplete="address-level2" type="text" placeholder="" />');
    //Desbloquea el campo ciudad
    if ( bloquea ) {
        jQuery('#' + formulario + '_state').attr("readonly", false); 
    }
}

//Función que cambia el campo input por un capo select
var carga_select = function(formulario) {
    //Desbloquea los campos
    jQuery('#' + formulario + '_city_field,#' + formulario + '_state_field').unblock();
    //Cambia el campo
    var texto = ( bloqueo ) ? ' readonly="readonly"' : '';
    jQuery('#' + formulario + '_city').replaceWith('<select name="' + formulario + '_city" id="' + formulario + '_city" class="select state_select"' + texto + ' autocomplete="address-level2" data-allow_clear="true" data-placeholder="' + texto_predeterminado +'"><option value="">' + texto_predeterminado +'</option><option value="carga_campo">' + texto_carga_campo + '</option></select>');
    jQuery('#' + formulario + '_city').selectWoo();
}

//Función que comprueba el valor seleccionado para cambiar el campo select
var comprueba_campo = function (formulario) {
    if (jQuery('#' + formulario + '_city').val() == 'carga_campo') {
        carga_campo(formulario);
    }
}

//Función que chequea el código postal en GeoNames
var comprueba_geonames = function (formulario, google = false) {
    //Bloquea los campos
    jQuery('#' + formulario + '_city_field,#' + formulario + '_state_field').block({ 
        message: null,
        overlayCSS: {
            background: "#fff",
            opacity: .6
        }
    });
    //Consulta en GeoNames
    jQuery.ajax({
        url: "https://www.geonames.org/postalCodeLookupJSON?postalcode=" + jQuery('#' + formulario + '_postcode').val() + "&country=" + jQuery('#' + formulario + '_country').val(),
        type: "GET",
        cache: false,
        dataType: "JSONP",
        crossDomain: true,
        success: function (data) {
            if (jQuery('#' + formulario + '_city').is('input')) { //Carga un campo select
                carga_select(formulario);
            }
            jQuery('#' + formulario + '_city').empty();
            jQuery('#' + formulario + '_city').append(
                jQuery("<option></option>").attr("value", "").text(texto_predeterminado)
            );
            jQuery('#' + formulario + '_city').append(
                jQuery("<option></option>").attr("value", "carga_campo").text(texto_carga_campo)
            );
            //Desbloquea los campos
            jQuery('#' + formulario + '_city_field,#' + formulario + '_state_field').unblock();                    

            if (data.postalcodes.length > 0) { //Obtiene resultados
                console.log( data);
                //Bloquea el campo provincia
                if (bloqueo) {
                    jQuery('#' + formulario + '_state').attr("readonly", true); 
                }
                if (data.postalcodes.length > 1) { //Es un código postal con múltiples localidades
                    jQuery.each(data.postalcodes, function (key, value) {
                        jQuery('#' + formulario + '_city').append(
                            jQuery("<option></option>").attr("value", data.postalcodes[key].placeName).text(data.postalcodes[key].placeName)
                        );
                    });
                } else { //Es un código postal único
                    jQuery('#' + formulario + '_city').append(
                        jQuery("<option></option>").attr("value", data.postalcodes[0].placeName).text(data.postalcodes[0].placeName)
                    );
                }
                //Actualiza los campos select
                jQuery('#' + formulario + '_city option[value="' + data.postalcodes[0].placeName + '"]').attr('selected', 'selected').trigger("change");
                if (data.postalcodes.length > 1) {
                    if (jQuery('#s2id_' + formulario + '_city').length) {
                        jQuery('#s2id_' + formulario + '_city').data('select2').open();
                    } else {
                        jQuery('#' + formulario + '_city').data('select2').open();
                    }
                }
                //Provincia
                var provincia = (jQuery.isNumeric(data.postalcodes[0].adminCode2)) ? data.postalcodes[0].adminCode1 : data.postalcodes[0].adminCode2;
                const paises  = { //Países especiales
                    "AT": "adminName1", //Austria
                    "FR": "adminName2", //Francia
                    "PT": "adminName1", //Portugal
                };
                if ( paises[ data.postalcodes[0].countryCode ] ) {
                    provincia = data.postalcodes[0][ paises[ data.postalcodes[0].countryCode ] ];
                    if ( provincia == 'Azores' ) {
                        provincia = 'Açores';
                    }
                    jQuery('#' + formulario + "_state option:contains('" + provincia + "')").filter(function(i){
                        return jQuery(this).text() === provincia;
                    }).attr('selected', 'selected').trigger("change");
                } else {
                    jQuery('#' + formulario + '_state').val(provincia).attr('selected', 'selected').trigger("change");                        
                }
            } else { //No obtiene resultados con GeoNames
                if (google == true) {
                    carga_campo(formulario, true); //Carga un campo input estándar
                } else {
                    comprueba_google(formulario, true); //Prueba con Google Maps
                }
            }
        },
    });
}

//Función que chequea el código postal en Google Maps
var comprueba_google = function (formulario, geonames = false) {
    //Bloquea los campos
    jQuery('#' + formulario + '_city_field,#' + formulario + '_state_field').block({ 
        message: null,
        overlayCSS: {
            background: "#fff",
            opacity: .6
        }
    });
    //Consulta en Google
    jQuery.ajax({
        url: "https://maps.googleapis.com/maps/api/geocode/json?components=country:" + jQuery('#' + formulario + '_country').val() + "|postal_code:" + jQuery('#' + formulario + '_postcode').val() + "&key=" + google_api + "&language=" + jQuery('html')[0].lang,
        type: "GET",
        cache: false,
        dataType: "JSON",
        crossDomain: true,
        success: function (data) {
            if (jQuery('#' + formulario + '_city').is('input')) { //Carga un campo select
                carga_select(formulario);
            }
            ///Limpia y mete la opción inicial
            jQuery('#' + formulario + '_city').empty();
            jQuery('#' + formulario + '_city').append(
                jQuery("<option></option>").attr("value", "").text(texto_predeterminado)
            );
            jQuery('#' + formulario + '_city').append(
                jQuery("<option></option>").attr("value", "carga_campo").text(texto_carga_campo)
            );
            //Desbloquea los campos
            jQuery('#' + formulario + '_city_field,#' + formulario + '_state_field').unblock();                    

            if (data.status !== 'ZERO_RESULTS') { //Obtiene resultados
                console.log( data );
                //Bloquea el campo provincia
                if (bloqueo) {
                    jQuery('#' + formulario + '_state').attr("readonly", true); 
                }
                //Controla el orden de los campos
                for (var i = 0; i < data.results[0].address_components.length; i++) {
                    if (jQuery.inArray("locality", data.results[0].address_components[i].types) !== -1) {
                        var ciudad = i;
                    }
                    
                    if (jQuery.inArray("country", data.results[0].address_components[i].types) !== -1) {
                        var pais = data.results[0].address_components[i].short_name;
                    }

                    if (jQuery.inArray("administrative_area_level_2", data.results[0].address_components[i].types) !== -1) {
                        var provincia = i;
                    }
                    if (typeof (provincia) == "undefined") {
                        if (jQuery.inArray("administrative_area_level_1", data.results[0].address_components[i].types) !== -1) {
                            var provincia = i;
                        }
                    }
                }

                if (typeof (ciudad) != "undefined") { //Existe ciudad
                    if (data.results[0].postcode_localities) { //Es un código postal con múltiples localidades
                        jQuery.each(data.results[0].postcode_localities, function (key, value) {
                            jQuery('#' + formulario + '_city').append(
                                jQuery("<option></option>").attr("value", value).text(value)
                            );
                        });
                    } else { //Es un código postal único
                        jQuery('#' + formulario + '_city').append(
                            jQuery("<option></option>").attr("value", data.results[0].address_components[ciudad].long_name).text(data.results[0].address_components[ciudad].long_name)
                        );
                    }
                    //Actualiza el campo select
                    jQuery('#' + formulario + '_city option[value="' + data.results[0].address_components[ciudad].long_name + '"]').attr('selected', 'selected').trigger("change");
                    if (data.results[0].postcode_localities) {
                        if (jQuery('#s2id_' + formulario + '_city').length) {
                            jQuery('#s2id_' + formulario + '_city').data('select2').open();
                        } else {
                            jQuery('#' + formulario + '_city').data('select2').open();
                        }
                    }
                    var nombre = (data.results[0].address_components[provincia].short_name) ? data.results[0].address_components[provincia].short_name : jQuery('#' + formulario + '_state').find("option:contains('" + data.results[0].address_components[provincia].long_name + "')").val();
                    const paises  = { //Países especiales
                        "AT": "adminName1", //Austria
                        "FR": "adminName2", //Francia
                        "PT": "adminName1", //Portugal
                    };
                    if ( paises[ pais ] ) {
                        jQuery('#' + formulario + "_state option:contains('" + nombre + "')").filter(function(i){
                            return jQuery(this).text() === nombre;
                        }).attr('selected', 'selected').trigger("change");
                    } else {
                        jQuery('#' + formulario + '_state').val(provincia).attr('selected', 'selected').trigger("change");                        
                    }
                } else { //No existe ninguna ciudad
                    if (geonames == true) {
                        carga_campo(formulario, true); //Carga un campo input estándar
                    } else {
                        comprueba_geonames(formulario, true); //Prueba con GeoNames
                    }
                }
            } else { //No obtiene resultados con Google Maps
                if (geonames == true) {
                    carga_campo(formulario, true); //Carga un campo input estándar
                } else {
                    comprueba_geonames(formulario, true); //Prueba con GeoNames
                }
            }
        },
    });
}

//Inicializa las funciones
jQuery( document ).ready( function() {
	//Actualiza los dos formularios
	if ( jQuery( '#billing_country' ).val() && jQuery( '#billing_postcode' ).val() ) {
		window[funcion]( 'billing' );
	}
	if ( jQuery( '#shipping_country' ).val() && jQuery( '#shipping_postcode' ).val() ) {
		window[funcion]( 'shipping' );
	}

    //Actualiza el formulario de facturación
	jQuery( '#billing_postcode, #billing_country' ).on( 'change', function() {
		if ( jQuery( '#billing_country' ).val() && jQuery( '#billing_postcode' ).val() ) {
			window[funcion]( 'billing' );
		}
    } );
	
	//Actualiza el formulario de envío
	jQuery( '#shipping_postcode, #shipping_country' ).on( 'change', function() {
		if ( jQuery( '#shipping_country' ).val() && jQuery( '#shipping_postcode' ).val() ) {
			window[funcion]( 'shipping' );
		}
    } );
    
    //Comprueba el formulario de facturación
    jQuery('#billing_city').on('change', function () {
        if (jQuery('#billing_city').val()) {
            comprueba_campo('billing');
        }
    });

    //Comprueba el formulario de envío
    jQuery('#shipping_city').on('change', function () {
        if (jQuery('#shipping_city').val()) {
            comprueba_campo('shipping');
        }
    });
    
    jQuery(document.body).on('country_to_state_changed', function(){
        //Bloquea los campos
        if ( bloqueo && ! jQuery('#billing_city').is('input') ) {
            jQuery('#billing_state').attr('readonly', true);
        }
        if ( bloqueo && ! jQuery('#shipping_city').is('input') ) {
            jQuery('#shipping_state').attr('readonly', true);
        }
    });
});
