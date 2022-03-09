//Función que cambia el campo select por un campo input
var carga_campo = function (formulario) {
    //Elimina select2 o selectWoo
    if (jQuery('#' + formulario + '_city').data('selectWoo')) {
        jQuery('#' + formulario + '_city').selectWoo('destroy');
    } else if (jQuery('#' + formulario + '_city').data('select2')) {
        jQuery('#' + formulario + '_city').select2('destroy');
    }
    jQuery('#' + formulario + '_city').replaceWith('<input class="input-text " name="' + formulario + '_city" id="' + formulario + '_city" autocomplete="address-level2" type="text" placeholder="" />');
}

//Función que comprueba el valor seleccionado para cambiar el campo select
var comprueba_campo = function (formulario) {
    if (jQuery('#' + formulario + '_city').val() == 'carga_campo') {
        carga_campo(formulario);
    }
}

//Función que chequea el código postal en GeoNames
var comprueba_geonames = function (formulario, google = false) {
    if (!jQuery('#' + formulario + '_city').is('input')) {
        jQuery.ajax({ //my ajax request
            url: "https://www.geonames.org/postalCodeLookupJSON?postalcode=" + jQuery('#' + formulario + '_postcode').val() + "&country=" + jQuery('#' + formulario + '_country').val(),
            type: "GET",
            cache: false,
            dataType: "JSONP",
            crossDomain: true,
            success: function (data) {
                console.log(data);
                jQuery('#' + formulario + '_city').empty();
                jQuery('#' + formulario + '_city').append(
                    jQuery("<option></option>").attr("value", "").text(texto_predeterminado)
                );
                jQuery('#' + formulario + '_city').append(
                    jQuery("<option></option>").attr("value", "carga_campo").text(texto_carga_campo)
                );

                if (data.postalcodes.length > 0) { //Obtiene resultados
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
                    var provincia = (jQuery.isNumeric(data.postalcodes[0].adminCode2)) ? data.postalcodes[0].adminCode1 : data.postalcodes[0].adminCode2;
                    jQuery('#' + formulario + '_city option[value="' + data.postalcodes[0].placeName + '"]').attr('selected', 'selected').trigger("change");
                    if (data.postalcodes.length > 1) {
                        if (jQuery('#s2id_' + formulario + '_city').length) {
                            jQuery('#s2id_' + formulario + '_city').data('select2').open();
                        } else {
                            jQuery('#' + formulario + '_city').data('select2').open();
                        }
                    }
                    jQuery('#' + formulario + '_state').val(provincia).attr('selected', 'selected').trigger("change");
                } else { //No obtiene resultados con GeoNames
                    if (google == true) {
                        carga_campo(formulario); //Carga un campo input estándar
                    } else {
                        comprueba_google(formulario, true); //Prueba con Google Maps
                    }
                }
            },
        });
    }
}

//Función que chequea el código postal en Google Maps
var comprueba_google = function (formulario, geonames = false) {
    if (!jQuery('#' + formulario + '_city').is('input')) {
        jQuery.ajax({ //my ajax request
            url: "https://maps.googleapis.com/maps/api/geocode/json?components=country:" + jQuery('#' + formulario + '_country').val() + "|postal_code:" + jQuery('#' + formulario + '_postcode').val() + "&key=" + google_api,
            type: "GET",
            cache: false,
            dataType: "JSONP",
            crossDomain: true,
            success: function (data) {
                ///Limpia y mete la opción inicial
                jQuery('#' + formulario + '_city').empty();
                jQuery('#' + formulario + '_city').append(
                    jQuery("<option></option>").attr("value", "").text(texto_predeterminado)
                );
                jQuery('#' + formulario + '_city').append(
                    jQuery("<option></option>").attr("value", "carga_campo").text(texto_carga_campo)
                );

                if (data.status !== 'ZERO_RESULTS') { //Obtiene resultados
                    //Controla el orden de los campos
                    for (var i = 0; i < data.results[0].address_components.length; i++) {
                        if (jQuery.inArray("locality", data.results[0].address_components[i].types) !== -1) {
                            var ciudad = i;
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
                        jQuery('#' + formulario + '_state').val(nombre).attr('selected', 'selected').trigger("change");
                    } else { //No existe ninguna ciudad
                        if (geonames == true) {
                            carga_campo(formulario); //Carga un campo input estándar
                        } else {
                            comprueba_geonames(formulario, true); //Prueba con GeoNames
                        }
                    }
                } else { //No obtiene resultados con Google Maps
                    if (geonames == true) {
                        carga_campo(formulario); //Carga un campo input estándar
                    } else {
                        comprueba_geonames(formulario, true); //Prueba con GeoNames
                    }
                }
            },
        });
    }
}


jQuery(document).ready(function () {
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
});
