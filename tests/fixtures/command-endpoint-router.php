<?php
// Router für den PHP-Testserver in MessageDecoratorCommandTest: liefert je Pfad eine feste Antwort
header( 'Content-Type: application/json' );
switch( parse_url( $_SERVER['REQUEST_URI'] , PHP_URL_PATH ) ){
    case '/ok':
        echo json_encode( [ 'content' => 'Offene Meldungen: 3' ] );
        break;
    case '/unauthorized':
        http_response_code( 401 );
        echo json_encode( [ 'message' => 'Invalid credentials.' ] );
        break;
    case '/no-content':
        echo json_encode( [ 'message' => 'Report unknown' ] );
        break;
    default:
        http_response_code( 404 );
}
