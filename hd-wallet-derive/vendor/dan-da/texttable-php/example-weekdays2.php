#!/usr/bin/env php

<?php

require_once( __DIR__ . '/texttable.class.php' );

$data = [];

$timestamp = strtotime('next Sunday');
$days = array();
for ($i = 0; $i < 7; $i++) {
    $data[] = ['Day' => strftime('%A', $timestamp),
               'Abbrev' => strftime('%a', $timestamp),
               'Initial' => strftime('%A', $timestamp)[0],
               'Position' => $i ];
$timestamp = strtotime('+1 day', $timestamp);
    
}

echo "  [  Table with header from first row keys. similar to db result set. ]\n";
echo texttable::table( $data );
