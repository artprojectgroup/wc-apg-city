( function ( $ ) {
	const settings = window.apg_city_blocks_settings || {};
	if ( ! settings.ajax_url || ! settings.nonce ) {
		return;
	}

	const setNativeValue = function( el, value ) {
		if ( ! el ) {
			return;
		}
		const proto = Object.getPrototypeOf( el );
		const desc = Object.getOwnPropertyDescriptor( proto, 'value' );
		if ( desc && desc.set ) {
			desc.set.call( el, value );
		} else {
			el.value = value;
		}
	};

	const destroyCitySelect = function( type ) {
		const cityId  = type === 'shipping' ? 'shipping-city' : 'billing-city';
		const $input  = $( '#' + cityId );
		const $select = $( '#' + cityId + '-apg' );
		const $label  = $( 'label[for="' + cityId + '-apg' + '"]' );
		const $wrapperSelect = $select.closest( '.wc-blocks-components-select' );
		const $wrapper = $input.closest( '.wc-block-components-address-form__city' );

		if ( $select.length ) {
			$select.off( 'change' );
			$wrapperSelect.remove();
		}
		if ( $label.length ) {
			$label.removeClass( 'wc-blocks-components-select__label' );
			$label.attr( 'for', cityId );
			$label.insertAfter( $input );
		}
		if ( $wrapper.length ) {
			$wrapper.removeClass( 'wc-block-components-state-input' ).addClass( 'wc-block-components-text-input' );
			$wrapper.removeAttr( 'style' );
		}
		$input.show().prop( 'readonly', false ).css( { display: '', zoom: '' } ).removeAttr( 'style' );
	};

	const ensureCitySelect = function( type ) {
		const cityId  = type === 'shipping' ? 'shipping-city' : 'billing-city';
		const $input  = $( '#' + cityId );
		if ( ! $input.length ) {
			return null;
		}
		const $wrapper = $input.closest( '.wc-block-components-text-input' );
		if ( $wrapper.length ) {
			$wrapper.removeAttr( 'style' );
		}
		const $label   = $( 'label[for="' + cityId + '"]' );
		let $select    = $( '#' + cityId + '-apg' );

		if ( ! $select.length ) {
			if ( $wrapper.length ) {
				$wrapper.removeClass( 'wc-block-components-text-input' ).addClass( 'wc-block-components-state-input' );
				$wrapper.removeAttr( 'style' );
			}
			$select = $( '<select />', {
				id: cityId + '-apg',
				class: 'wc-blocks-components-select__select ' + ( $input.attr( 'class' ) || '' ),
				'data-type': type,
				autocomplete: 'address-level2'
			} );

			const $container = $( '<div />', { class: 'wc-blocks-components-select' } );
			const $inner     = $( '<div />', { class: 'wc-blocks-components-select__container' } );

			if ( $label.length ) {
				$label
					.attr( 'for', cityId + '-apg' )
					.addClass( 'wc-blocks-components-select__label' );
				$inner.append( $label );
			}

			$inner.append( $select );
			const svgNS = 'http://www.w3.org/2000/svg';
			const svgEl = document.createElementNS( svgNS, 'svg' );
			svgEl.setAttribute( 'viewBox', '0 0 24 24' );
			svgEl.setAttribute( 'width', '24' );
			svgEl.setAttribute( 'height', '24' );
			svgEl.setAttribute( 'class', 'wc-blocks-components-select__expand' );
			svgEl.setAttribute( 'aria-hidden', 'true' );
			svgEl.setAttribute( 'focusable', 'false' );
			const pathEl = document.createElementNS( svgNS, 'path' );
			pathEl.setAttribute( 'd', 'M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z' );
			svgEl.appendChild( pathEl );
			$inner.append( $( svgEl ) );
			$container.append( $inner );

			$input.hide();
			if ( $wrapper.length ) {
				$wrapper.append( $container );
			} else {
				$select.insertAfter( $input );
			}

			$select.on( 'change', function() {
				if ( this.value === 'carga_campo' ) {
					const lastVal = $select.data( 'lastCity' ) || '';
					if ( lastVal ) {
						setNativeValue( $input[0], lastVal );
					}
					destroyCitySelect( type );
					return;
				}
				$select.data( 'lastCity', this.value );
				setNativeValue( $input[0], this.value );
				try {
					$input[0].dispatchEvent( new Event( 'input', { bubbles: true } ) );
					$input[0].dispatchEvent( new Event( 'change', { bubbles: true } ) );
				} catch ( e ) {}
			} );
		}

		return $select;
	};

	const applyPostalData = function( type, postalcodes ) {
		postalcodes = postalcodes || [];
		const cityId = type === 'shipping' ? 'shipping-city' : 'billing-city';
		const $input = $( '#' + cityId );
		const select = ensureCitySelect( type );
		if ( ! select ) {
			return;
		}

		select.empty();

		select.append( $( '<option />', { value: '', text: settings.texto_predeterminado || '', disabled: true } ) );
		select.append( $( '<option />', { value: 'carga_campo', text: settings.texto_carga_campo || '' } ) );

		if ( postalcodes.length ) {
			postalcodes.forEach( function( row ) {
				const opt = $( '<option />', {
					value: row.placeName,
					text: row.placeName
				} );
				if ( row.adminCode2 || row.adminCode1 ) {
					opt.attr( 'data-state', row.adminCode2 || row.adminCode1 );
				}
				select.append( opt );
			} );
		} else {
			// No hay resultados: revertir a input.
			destroyCitySelect( type );
			return;
		}

		const firstVal = postalcodes[0] ? postalcodes[0].placeName : '';
		select.val( firstVal );
		$input.val( firstVal );
		select.trigger( 'change' );
	};

	const lookupLocal = function( type, postcode, country, cb, onFail ) {
		const data = {
			action: 'apg_city_lookup',
			nonce: settings.nonce,
			postcode: postcode,
			country: country,
		};
		$.ajax( {
			url: settings.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: data,
			success: function( response ) {
				if ( response && response.success && response.data && response.data.postalcodes ) {
					cb( response.data.postalcodes );
				} else {
					onFail();
				}
			},
			error: onFail
		} );
	};

	const lookupApi = function( type, postcode, country, cb ) {
		const data = {
			action: 'apg_city_api_lookup',
			nonce: settings.nonce,
			api: settings.fallback || 'google',
			postcode: postcode,
			country: country,
			lang: document.documentElement.lang || 'en'
		};
		$.ajax( {
			url: settings.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: data,
			success: function( response ) {
				if ( response && response.success && response.data && response.data.postalcodes ) {
					cb( response.data.postalcodes );
				} else {
					cb( [] );
				}
			}
		} );
	};

	const handleChange = function() {
		const id = this.id.indexOf( 'shipping-' ) === 0 ? 'shipping' : 'billing';
		const postcode = $( this ).val();
		const country = $( '#' + id + '-country' ).val();

		if ( ! postcode || ! country ) {
			return;
		}
		var $blockTarget  = $( '.wc-block-components-address-form__city, .wc-block-components-address-form__state' );
		if ( $blockTarget.length && $.fn.block ) {
			$blockTarget.block( { message: null, overlayCSS: { background: '#fff', opacity: 0.6 } } );
		}

		if ( settings.has_local ) {
			lookupLocal( id, postcode, country, function( postalcodes ) {
				applyPostalData( id, postalcodes );
				if ( $blockTarget && $blockTarget.length && $.fn.unblock ) {
					$blockTarget.unblock();
					$blockTarget.removeAttr( 'style' );
				}
			}, function() {
				lookupApi( id, postcode, country, function( postalcodes ) {
					applyPostalData( id, postalcodes );
					if ( $blockTarget && $blockTarget.length && $.fn.unblock ) {
						$blockTarget.unblock();
						$blockTarget.removeAttr( 'style' );
					}
				} );
			} );
		} else {
			lookupApi( id, postcode, country, function( postalcodes ) {
				applyPostalData( id, postalcodes );
				if ( $blockTarget && $blockTarget.length && $.fn.unblock ) {
					$blockTarget.unblock();
					$blockTarget.removeAttr( 'style' );
				}
			} );
		}
	};

	$( document ).on( 'change', '#shipping-postcode, #billing-postcode', handleChange );

	// Estado inicial: convierte city en select y aplica bloqueo si procede.
	$( document ).ready( function() {
		[ 'billing', 'shipping' ].forEach( function( type ) {
			const select = ensureCitySelect( type );
			if ( select ) {
				// Carga opciones de un posible valor previo.
				var currentVal = $( '#' + ( type === 'shipping' ? 'shipping-city' : 'billing-city' ) ).val() || '';
				select.empty();
				select.append( $( '<option />', { value: '', text: settings.texto_predeterminado || '', disabled: true } ) );
				select.append( $( '<option />', { value: 'carga_campo', text: settings.texto_carga_campo || '' } ) );
				if ( currentVal ) {
					select.append( $( '<option />', { value: currentVal, text: currentVal, selected: 'selected' } ) );
				}
				select.trigger( 'change' );
				if ( settings.bloqueo ) {
					select.prop( 'disabled', true );
				}
			}
		} );
		if ( settings.bloqueo ) {
			$( '#billing-state, #shipping-state' ).prop( 'disabled', true );
		}
	} );
} )( jQuery );
