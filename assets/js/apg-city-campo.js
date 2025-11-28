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

var usuarioGeonames = '';
if ( typeof geonames_user !== 'undefined' && geonames_user ) {
    usuarioGeonames = Array.isArray( geonames_user ) ? geonames_user[0] : geonames_user;
}

var apgCitySettings = ( typeof apg_city_lookup_settings !== 'undefined' ) ? apg_city_lookup_settings : {};
var apg_city_ajax_url = '';
if ( apgCitySettings.ajax_url ) {
    apg_city_ajax_url = apgCitySettings.ajax_url;
} else if ( typeof ruta_ajax !== 'undefined' && ruta_ajax ) {
    apg_city_ajax_url = Array.isArray( ruta_ajax ) ? ruta_ajax[0] : ruta_ajax;
}
var apg_city_nonce = apgCitySettings.nonce || '';
var apg_city_has_local = !!apgCitySettings.has_local;
var apg_city_fallback = '';
if ( apgCitySettings.fallback ) {
    apg_city_fallback = apgCitySettings.fallback;
} else if ( typeof funcion !== 'undefined' && funcion ) {
    apg_city_fallback = funcion;
}
if ( Array.isArray( apg_city_fallback ) ) {
    apg_city_fallback = apg_city_fallback[0];
}

//Pinta los resultados en el select usando el formato GeoNames/local
var apg_city_apply_postalcodes = function (formulario, postalcodes) {
    if ( jQuery('#' + formulario + '_city').is('input') ) { //Carga un campo select
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

    if ( ! postalcodes || postalcodes.length === 0 ) {
        return false;
    }

    if (bloqueo) {
        jQuery('#' + formulario + '_state').attr("readonly", true); 
    }

    if (postalcodes.length > 1) { //Es un código postal con múltiples localidades
        jQuery.each(postalcodes, function (key, value) {
            jQuery('#' + formulario + '_city').append(
                jQuery("<option></option>").attr("value", postalcodes[key].placeName).text(postalcodes[key].placeName)
            );
        });
    } else { //Es un código postal único
        jQuery('#' + formulario + '_city').append(
            jQuery("<option></option>").attr("value", postalcodes[0].placeName).text(postalcodes[0].placeName)
        );
    }
    //Actualiza los campos select
    jQuery('#' + formulario + '_city option[value="' + postalcodes[0].placeName + '"]').attr('selected', 'selected').trigger("change");
    if (postalcodes.length > 1) {
        if (jQuery('#s2id_' + formulario + '_city').length) {
            jQuery('#s2id_' + formulario + '_city').data('select2').open();
        } else {
            jQuery('#' + formulario + '_city').data('select2').open();
        }
    }
    //Provincia
    var provincia = (jQuery.isNumeric(postalcodes[0].adminCode2)) ? postalcodes[0].adminCode1 : postalcodes[0].adminCode2;
    const paises  = { //Países especiales
        "AT": "adminName1", //Austria
        "FR": "adminName2", //Francia
        "PT": "adminName1", //Portugal
    };
    if ( paises[ postalcodes[0].countryCode ] ) {
        provincia = postalcodes[0][ paises[ postalcodes[0].countryCode ] ];
        //Ajustes personalizados
        if ( provincia == 'Azores' ) {
            provincia = 'Açores';
        }
        jQuery('#' + formulario + "_state option:contains('" + provincia + "')").filter(function(i){
            return jQuery(this).text() === provincia;
        }).attr('selected', 'selected').trigger("change");
    } else {
        jQuery('#' + formulario + '_state').val(provincia).attr('selected', 'selected').trigger("change");                        
    }

    return true;
}

//Función que chequea el código postal en GeoNames
var comprueba_geonames = function (formulario, google = false) {
    if ( ! usuarioGeonames ) {
        if ( google == true ) {
            carga_campo(formulario, true);
        } else {
            comprueba_google(formulario, true);
        }
        return;
    }
    //Bloquea los campos
    jQuery('#' + formulario + '_city_field,#' + formulario + '_state_field').block({
        message: null,
        overlayCSS: {
            background: "#fff",
            opacity: .6
        }
    });
    jQuery.ajax({
        url: apg_city_ajax_url,
        type: "POST",
        cache: false,
        dataType: "json",
        data: {
            action: "apg_city_api_lookup",
            nonce: apg_city_nonce,
            api: "geonames",
            postcode: jQuery('#' + formulario + '_postcode').val(),
            country: jQuery('#' + formulario + '_country').val(),
        },
        success: function (response) {
            var rows = (response && response.success && response.data) ? response.data.postalcodes : [];
            var success = apg_city_apply_postalcodes( formulario, rows );
            if ( ! success ) {
                if (google == true) {
                    carga_campo(formulario, true); //Carga un campo input estándar
                } else {
                    comprueba_google(formulario, true); //Prueba con Google Maps
                }
            }
        },
        error: function() {
            if (google == true) {
                carga_campo(formulario, true); //Carga un campo input estándar
            } else {
                comprueba_google(formulario, true); //Prueba con Google Maps
            }
        }
    });
}

//Función que gestiona la consulta local y decide cuándo pasar a la API externa
var apg_city_trigger_fallback = function (formulario) {
    jQuery('#' + formulario + '_city_field,#' + formulario + '_state_field').unblock();

    if ( apg_city_fallback === 'comprueba_geonames' && typeof comprueba_geonames === 'function' ) {
        comprueba_geonames(formulario);
    } else if ( apg_city_fallback === 'comprueba_google' && typeof comprueba_google === 'function' ) {
        comprueba_google(formulario);
    } else {
        carga_campo(formulario, true);
    }
}

var comprueba_local = function (formulario) {
    if ( ! apg_city_has_local || ! apg_city_ajax_url ) {
        apg_city_trigger_fallback(formulario);
        return;
    }
    //Bloquea los campos
    jQuery('#' + formulario + '_city_field,#' + formulario + '_state_field').block({
        message: null,
        overlayCSS: {
            background: "#fff",
            opacity: .6
        }
    });
    jQuery.ajax({
        url: apg_city_ajax_url,
        type: "POST",
        cache: false,
        dataType: "json",
        data: {
            action: "apg_city_lookup",
            nonce: apg_city_nonce,
            postcode: jQuery('#' + formulario + '_postcode').val(),
            country: jQuery('#' + formulario + '_country').val(),
        },
        success: function (response) {
            var rows = (response && response.success && response.data) ? response.data.postalcodes : [];
            var success = apg_city_apply_postalcodes(formulario, rows);
            if ( ! success ) {
                apg_city_trigger_fallback(formulario);
            }
        },
        error: function () {
            apg_city_trigger_fallback(formulario);
        }
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
    jQuery.ajax({
        url: apg_city_ajax_url,
        type: "POST",
        cache: false,
        dataType: "json",
        data: {
            action: "apg_city_api_lookup",
            nonce: apg_city_nonce,
            api: "google",
            postcode: jQuery('#' + formulario + '_postcode').val(),
            country: jQuery('#' + formulario + '_country').val(),
            lang: jQuery('html')[0].lang
        },
        success: function (response) {
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

            if (response && response.success && response.data && response.data.postalcodes) { //Obtiene resultados
                var data = { postalcodes: response.data.postalcodes, country: response.data.country };
                //Bloquea el campo provincia
                if (bloqueo) {
                    jQuery('#' + formulario + '_state').attr("readonly", true); 
                }

                if ( data.postalcodes.length > 0 ) {
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
                    //Actualiza el campo select
                    jQuery('#' + formulario + '_city option[value="' + data.postalcodes[0].placeName + '"]').attr('selected', 'selected').trigger("change");
                    if (data.postalcodes.length > 1) {
                        if (jQuery('#s2id_' + formulario + '_city').length) {
                            jQuery('#s2id_' + formulario + '_city').data('select2').open();
                        } else {
                            jQuery('#' + formulario + '_city').data('select2').open();
                        }
                    }
                    var nombre = data.postalcodes[0].adminCode2 ? data.postalcodes[0].adminCode2 : data.postalcodes[0].adminCode1;
                    const paises  = { //Países especiales
                        "AT": "adminName1", //Austria
                        "FR": "adminName2", //Francia
                        "PT": "adminName1", //Portugal
                    };
                    var pais = data.postalcodes[0].countryCode;
                    if ( paises[ pais ] && data.postalcodes[0][ paises[ pais ] ] ) {
                        nombre = data.postalcodes[0][ paises[ pais ] ];
                        jQuery('#' + formulario + "_state option:contains('" + nombre + "')").filter(function(i){
                            return jQuery(this).text() === nombre;
                        }).attr('selected', 'selected').trigger("change");
                    } else {
                        jQuery('#' + formulario + '_state').val(nombre).attr('selected', 'selected').trigger("change");                        
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
        error: function() {
            if (geonames == true) {
                carga_campo(formulario, true);
            } else {
                comprueba_geonames(formulario, true);
            }
        }
    });
}

//Orquesta el flujo: primero datos locales, luego APIs externas
var apg_city_lookup = function (formulario) {
    if ( apg_city_has_local ) {
        comprueba_local(formulario);
    } else {
        if ( apg_city_fallback === 'comprueba_geonames' ) {
            comprueba_geonames(formulario);
        } else if ( apg_city_fallback === 'comprueba_google' ) {
            comprueba_google(formulario);
        } else {
            carga_campo(formulario, true);
        }
    }
}

//Inicializa las funciones
jQuery( document ).ready( function() {
	//Actualiza los dos formularios
	if ( jQuery( '#billing_country' ).val() && jQuery( '#billing_postcode' ).val() ) {
		apg_city_lookup( 'billing' );
	}
	if ( jQuery( '#shipping_country' ).val() && jQuery( '#shipping_postcode' ).val() ) {
		apg_city_lookup( 'shipping' );
	}

    //Actualiza el formulario de facturación
	jQuery( '#billing_postcode, #billing_country' ).on( 'change', function() {
		if ( jQuery( '#billing_country' ).val() && jQuery( '#billing_postcode' ).val() ) {
			apg_city_lookup( 'billing' );
		}
    } );
	
	//Actualiza el formulario de envío
	jQuery( '#shipping_postcode, #shipping_country' ).on( 'change', function() {
		if ( jQuery( '#shipping_country' ).val() && jQuery( '#shipping_postcode' ).val() ) {
			apg_city_lookup( 'shipping' );
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
