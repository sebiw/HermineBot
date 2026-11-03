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
    case '/todo.ics':
        header( 'Content-Type: text/calendar' );
        $done = gmdate( 'Ymd\THis\Z' , time() - 3 * 86400 );
        echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//DE\r\n"
            . "BEGIN:VTODO\r\nUID:1\r\nSUMMARY:Reifen prüfen\r\nSTATUS:NEEDS-ACTION\r\nPRIORITY:1\r\nDESCRIPTION:Vorne links\r\nEND:VTODO\r\n"
            . "BEGIN:VTODO\r\nUID:2\r\nSUMMARY:Halle fegen\r\nSTATUS:IN-PROCESS\r\nPRIORITY:0\r\nEND:VTODO\r\n"
            . "BEGIN:VTODO\r\nUID:3\r\nSUMMARY:Licht tauschen\r\nSTATUS:COMPLETED\r\nCOMPLETED:" . $done . "\r\nEND:VTODO\r\n"
            . "END:VCALENDAR\r\n";
        break;
    default:
        http_response_code( 404 );
}
