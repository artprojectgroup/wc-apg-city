/* global jQuery */
( function( $ ) {
	// Muestra u oculta las filas según la API seleccionada.
    var $api = $( '#apg_city_settings\\[api\\]' );
    var $bloqueo = $( '#apg_city_settings\\[bloqueo\\]' );
    var $bloqueoColor = $( '#apg_city_settings\\[bloqueo_color\\]' );
    var $bloqueoColorText = $( '#apg_city_settings\\[bloqueo_color_text\\]' );
    var toggleRows = function( value ) {
        if ( value === 'google' ) {
            $( '.api' ).show();
            $( '.geonames' ).hide();
        } else {
            $( '.api' ).hide();
            $( '.geonames' ).show();
        }
    };
	// Muestra u oculta las opciones de color de bloqueo.
    var toggleBloqueoColor = function( checked ) {
        $( '.bloqueo-color' ).toggle( !! checked );
    };
	// Sincroniza los campos de color.
    var syncColorInputs = function( value, fromText ) {
        var hex = ( value || '' ).trim();
        if ( ! hex ) {
            return;
        }
        if ( fromText && hex.charAt(0) !== '#' ) {
            hex = '#' + hex;
        }
        var match = hex.match( /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/ );
        if ( ! match ) {
            return;
        }
        if ( match[1].length === 3 ) { // Expande formato #rgb a #rrggbb.
            hex = '#' + match[1].split( '' ).map( function( c ) { return c + c; } ).join( '' );
        }
        $bloqueoColor.val( hex );
        $bloqueoColorText.val( hex );
    };

    toggleRows( $api.val() );
    toggleBloqueoColor( $bloqueo.is( ':checked' ) );
    syncColorInputs( $bloqueoColor.val() );

    $api.on( 'change', function() {
        toggleRows( this.value );
    } );
    $bloqueo.on( 'change', function() {
        toggleBloqueoColor( this.checked );
    } );
    $bloqueoColor.on( 'change', function() {
        syncColorInputs( this.value );
    } );
    $bloqueoColorText.on( 'change keyup', function() {
        syncColorInputs( this.value, true );
    } );
} )( jQuery );
