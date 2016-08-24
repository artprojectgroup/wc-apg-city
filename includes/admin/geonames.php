<?php
$parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
require_once( $parse_uri[0] . 'wp-load.php' );

$respuesta = wp_remote_get( "http://www.geonames.org/postalCodeLookupJSON?postalcode=" . $_REQUEST['codigo_postal'] . "&country=" . $_REQUEST['pais'] );

echo $respuesta['body'];
?>