/* global jQuery */
( function ( $ ) {
	'use strict';

	var settings = window.apg_city_lookup_settings || {};

	var ajaxUrl  = settings.ajax_url || '';
	var nonce    = settings.nonce || '';
	var hasLocal = !! settings.has_local;
	var fallback = settings.fallback || '';
	var bloqueo  = !! settings.bloqueo;

	var textoPredeterminado = settings.texto_predeterminado || '';
	var textoCargaCampo     = settings.texto_carga_campo || '';

	// Países cuya provincia se toma de un campo distinto al habitual.
	var PAISES_ESPECIALES = {
		AT: 'adminName1', // Austria.
		FR: 'adminName2', // Francia.
		PT: 'adminName1'  // Portugal.
	};

	/**
	 * Crea una opción de select sin construir HTML por concatenación.
	 *
	 * Los textos configurables y los nombres de localidad se asignan como
	 * propiedades del nodo, de modo que nunca se interpretan como marcado.
	 */
	var creaOpcion = function ( valor, texto ) {
		return $( '<option></option>' ).attr( 'value', valor ).text( texto );
	};

	// Desbloquea los campos. blockUI lo carga WooCommerce, pero si un plugin de
	// optimización lo quita, llamar a unblock() abortaría todo el flujo.
	var desbloquea_campos = function ( formulario ) {
		if ( ! $.fn.unblock ) {
			return;
		}
		$( '#' + formulario + '_city_field,#' + formulario + '_state_field' ).unblock();
	};

	// Bloquea visualmente los campos mientras se resuelve la consulta.
	var bloquea_campos = function ( formulario ) {
		if ( ! $.fn.block ) {
			return;
		}
		$( '#' + formulario + '_city_field,#' + formulario + '_state_field' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		} );
	};

	// Función que cambia el campo select por un campo input.
	var carga_campo = function ( formulario, bloquea ) {
		var $ciudad = $( '#' + formulario + '_city' );

		// Elimina select2 o selectWoo.
		if ( $ciudad.data( 'selectWoo' ) ) {
			$ciudad.selectWoo( 'destroy' );
		} else if ( $ciudad.data( 'select2' ) ) {
			$ciudad.select2( 'destroy' );
		}

		desbloquea_campos( formulario );

		// Cambia el campo.
		var $input = $( '<input />', {
			'class': 'input-text',
			type: 'text',
			name: formulario + '_city',
			id: formulario + '_city',
			autocomplete: 'address-level2',
			placeholder: ''
		} );
		$ciudad.replaceWith( $input );

		// Desbloquea el campo provincia.
		if ( bloquea ) {
			$( '#' + formulario + '_state' ).attr( 'readonly', false );
		}
	};

	// Función que cambia el campo input por un campo select.
	var carga_select = function ( formulario ) {
		desbloquea_campos( formulario );

		var $select = $( '<select></select>', {
			name: formulario + '_city',
			id: formulario + '_city',
			'class': 'select state_select',
			autocomplete: 'address-level2'
		} );
		$select.attr( 'data-allow_clear', 'true' );
		$select.attr( 'data-placeholder', textoPredeterminado );
		if ( bloqueo ) {
			$select.attr( 'readonly', 'readonly' );
		}
		$select.append( creaOpcion( '', textoPredeterminado ) );
		$select.append( creaOpcion( 'carga_campo', textoCargaCampo ) );

		$( '#' + formulario + '_city' ).replaceWith( $select );
		if ( $.fn.selectWoo ) {
			$( '#' + formulario + '_city' ).selectWoo();
		}
	};

	// Función que comprueba el valor seleccionado para cambiar el campo select.
	var comprueba_campo = function ( formulario ) {
		if ( $( '#' + formulario + '_city' ).val() === 'carga_campo' ) {
			// Se pasa el bloqueo para que carga_campo() devuelva la provincia a
			// editable: si el cliente escribe la ciudad a mano, tiene que poder
			// corregir también la provincia.
			carga_campo( formulario, bloqueo );
		}
	};

	// Abre el desplegable cuando hay más de una localidad para el mismo código postal.
	var abre_select = function ( formulario ) {
		if ( bloqueo ) { // Con los campos bloqueados el CSS anula el puntero: abrirlo no sirve de nada.
			return;
		}

		var $s2 = $( '#s2id_' + formulario + '_city' );
		var instancia = $s2.length ? $s2.data( 'select2' ) : $( '#' + formulario + '_city' ).data( 'select2' );

		// Sin select2/selectWoo cargado el desplegable es nativo y no se puede abrir.
		if ( instancia && typeof instancia.open === 'function' ) {
			instancia.open();
		}
	};

	/**
	 * Selecciona la provincia.
	 *
	 * Se compara el texto de cada opción en JavaScript en lugar de construir un
	 * selector `:contains('...')`: los nombres con apóstrofo (Côte-d'Or,
	 * L'Hospitalet) rompían el selector y abortaban el autorrellenado.
	 */
	var aplica_provincia = function ( formulario, fila ) {
		var provincia = $.isNumeric( fila.adminCode2 ) ? fila.adminCode1 : fila.adminCode2;
		var $estado   = $( '#' + formulario + '_state' );

		if ( ! $estado.length ) {
			return;
		}

		// Se exige que el campo especial traiga valor: la respuesta de Google no
		// rellena adminName*, así que sin esta comprobación AT, FR y PT se
		// quedaban sin provincia en lugar de usar el camino normal.
		if ( PAISES_ESPECIALES[ fila.countryCode ] && fila[ PAISES_ESPECIALES[ fila.countryCode ] ] ) {
			provincia = fila[ PAISES_ESPECIALES[ fila.countryCode ] ];
			// Ajustes personalizados.
			if ( provincia === 'Azores' ) {
				provincia = 'Açores';
			}
			$estado.find( 'option' ).filter( function () {
				return $( this ).text() === provincia;
			} ).attr( 'selected', 'selected' );
			$estado.trigger( 'change' );

			return;
		}

		$estado.val( provincia ).attr( 'selected', 'selected' ).trigger( 'change' );
	};

	// Pinta los resultados en el select usando el formato GeoNames/local.
	var apg_city_apply_postalcodes = function ( formulario, postalcodes ) {
		if ( $( '#' + formulario + '_city' ).is( 'input' ) ) { // Carga un campo select.
			carga_select( formulario );
		}

		var $ciudad = $( '#' + formulario + '_city' );
		$ciudad.empty();
		$ciudad.append( creaOpcion( '', textoPredeterminado ) );
		$ciudad.append( creaOpcion( 'carga_campo', textoCargaCampo ) );

		desbloquea_campos( formulario );

		if ( ! postalcodes || ! postalcodes.length ) {
			return false;
		}

		if ( bloqueo ) {
			$( '#' + formulario + '_state' ).attr( 'readonly', true );
		}

		$.each( postalcodes, function ( indice, fila ) {
			$ciudad.append( creaOpcion( fila.placeName, fila.placeName ) );
		} );

		// Selecciona por valor en lugar de construir un selector con el nombre
		// de la localidad, que se rompía con comillas o apóstrofos.
		$ciudad.val( postalcodes[0].placeName ).trigger( 'change' );

		if ( postalcodes.length > 1 ) {
			abre_select( formulario );
		}

		aplica_provincia( formulario, postalcodes[0] );

		return true;
	};

	// Lanza una consulta AJAX al servidor.
	var consulta = function ( datos, alTerminar, alFallar ) {
		if ( ! ajaxUrl || ! nonce ) {
			alFallar();

			return;
		}

		datos.nonce = nonce;

		$.ajax( {
			url: ajaxUrl,
			type: 'POST',
			cache: false,
			dataType: 'json',
			data: datos,
			success: function ( response ) {
				var filas = ( response && response.success && response.data ) ? response.data.postalcodes : [];
				alTerminar( filas || [] );
			},
			error: alFallar
		} );
	};

	// Función que chequea el código postal en GeoNames.
	var comprueba_geonames = function ( formulario, google ) {
		bloquea_campos( formulario );

		var reintenta = function () {
			// Solo se prueba la otra API si es la configurada: si no, la petición
			// viajaría para que el servidor responda que faltan credenciales.
			if ( google === true || fallback !== 'google' ) {
				carga_campo( formulario, true ); // Carga un campo input estándar.
			} else {
				comprueba_google( formulario, true ); // Prueba con Google Maps.
			}
		};

		consulta(
			{
				action: 'apg_city_api_lookup',
				api: 'geonames',
				postcode: $( '#' + formulario + '_postcode' ).val(),
				country: $( '#' + formulario + '_country' ).val()
			},
			function ( filas ) {
				if ( ! apg_city_apply_postalcodes( formulario, filas ) ) {
					reintenta();
				}
			},
			reintenta
		);
	};

	// Función que chequea el código postal en Google Maps.
	var comprueba_google = function ( formulario, geonames ) {
		bloquea_campos( formulario );

		var reintenta = function () {
			if ( geonames === true || fallback !== 'geonames' ) {
				carga_campo( formulario, true ); // Carga un campo input estándar.
			} else {
				comprueba_geonames( formulario, true ); // Prueba con GeoNames.
			}
		};

		consulta(
			{
				action: 'apg_city_api_lookup',
				api: 'google',
				postcode: $( '#' + formulario + '_postcode' ).val(),
				country: $( '#' + formulario + '_country' ).val(),
				lang: document.documentElement.lang || 'en'
			},
			function ( filas ) {
				if ( ! apg_city_apply_postalcodes( formulario, filas ) ) {
					reintenta();
				}
			},
			reintenta
		);
	};

	// Función que gestiona la consulta local y decide cuándo pasar a la API externa.
	var apg_city_trigger_fallback = function ( formulario ) {
		desbloquea_campos( formulario );

		if ( fallback === 'geonames' ) {
			comprueba_geonames( formulario );
		} else if ( fallback === 'google' ) {
			comprueba_google( formulario );
		} else { // Sin API configurada solo queda el campo de texto libre.
			carga_campo( formulario, true );
		}
	};

	var comprueba_local = function ( formulario ) {
		bloquea_campos( formulario );

		consulta(
			{
				action: 'apg_city_lookup',
				postcode: $( '#' + formulario + '_postcode' ).val(),
				country: $( '#' + formulario + '_country' ).val()
			},
			function ( filas ) {
				if ( ! apg_city_apply_postalcodes( formulario, filas ) ) {
					apg_city_trigger_fallback( formulario );
				}
			},
			function () {
				apg_city_trigger_fallback( formulario );
			}
		);
	};

	// Orquesta el flujo: primero datos locales, luego APIs externas.
	var apg_city_lookup = function ( formulario ) {
		if ( hasLocal && ajaxUrl ) {
			comprueba_local( formulario );
		} else {
			apg_city_trigger_fallback( formulario );
		}
	};

	// Inicializa las funciones.
	// Un formulario de envío oculto ("enviar a otra dirección" desmarcado) no
	// debe provocar una consulta ni tocar campos que el cliente no ve.
	var es_visible = function ( formulario ) {
		var $postcode = $( '#' + formulario + '_postcode' );

		return !! ( $postcode.length && $postcode.is( ':visible' ) );
	};

	$( document ).ready( function () {
		var formularios = [ 'billing', 'shipping' ];

		$.each( formularios, function ( indice, formulario ) {
			// Actualiza los dos formularios.
			if ( es_visible( formulario ) && $( '#' + formulario + '_country' ).val() && $( '#' + formulario + '_postcode' ).val() ) {
				apg_city_lookup( formulario );
			}

			$( '#' + formulario + '_postcode, #' + formulario + '_country' ).on( 'change', function () {
				if ( $( '#' + formulario + '_country' ).val() && $( '#' + formulario + '_postcode' ).val() ) {
					apg_city_lookup( formulario );
				}
			} );

			// Comprueba el cambio a campo de texto libre.
			$( document ).on( 'change', '#' + formulario + '_city', function () {
				if ( $( this ).val() ) {
					comprueba_campo( formulario );
				}
			} );
		} );

		$( document.body ).on( 'country_to_state_changed', function () {
			// Bloquea los campos.
			$.each( formularios, function ( indice, formulario ) {
				if ( bloqueo && ! $( '#' + formulario + '_city' ).is( 'input' ) ) {
					$( '#' + formulario + '_state' ).attr( 'readonly', true );
				}
			} );
		} );
	} );
}( jQuery ) );
