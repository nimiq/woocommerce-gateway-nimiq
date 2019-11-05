#!/usr/bin/env php

<?php

require_once( __DIR__ . '/texttable_markdown.class.php' );

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
        $val = $j == 1 ? ('k' . substr(md5($buf), 0, rand(0, 7))) : $buf . rand(0, 1000);
        $row[$key] = $val;
    }
    $data[] = $row;
}

$headertypes = [ 'keys', 'firstrow'];
$headertype = $headertypes[rand(0, count( $headertypes) - 1)];
echo texttable_markdown::table( $data, $headertype );