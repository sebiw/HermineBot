<?php

// Für PHPStan: Konstante wie in bootstrap.php (dort startet zusätzlich die Runtime, die PHPStan nicht braucht)
if( !defined( 'BASE_PATH' ) ){
    define( 'BASE_PATH' , dirname( __DIR__ ) );
}
