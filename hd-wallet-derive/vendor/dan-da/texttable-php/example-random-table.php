#!/usr/bin/env php

<?php

require_once( __DIR__ . '/texttable.class.php' );

$data = [];

srand( microtime(true));
$numcols = rand(2,6);
for( $i = 0; $i < 20; $i ++ ) {
    $row = [];
    for( $j = 0; $j < $numcols; $j++ ) {
        $buf = '';
        if( rand(0, 60) == 0) {
            $buf = rand( 0, 10000 );
        }
        $key = sprintf( 'col-%d', $j+1);
        $row[$key] = $buf . rand(0, 1000);
    }
    $data[] = $row;
}

$headertypes = [ 'keys', 'firstrow', 'none'];
$headertype = $headertypes[rand(0, count( $headertypes) - 1)];

$footertypes = [ 'keys', 'lastrow', 'none'];
$footertype = $footertypes[rand(0, count( $footertypes) - 1)];

echo texttable::table( $data, $headertype, $footertype );