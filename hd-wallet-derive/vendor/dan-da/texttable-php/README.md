# texttable-php

A handy PHP class for printing fixed-width text tables.

There is also a class for printing human-friendly markdown tables.

Let's see a couple examples, shall we?

## Example Price History Report ( from [bitprices](https://github.com/dan-da/bitprices) )

```
+------------+------------------+-----------+-------------+----------------+---------------+----------+
| Date       | BTC Amount       | USD Price | USD Amount  | USD Amount Now | USD Gain      | Type     |
+------------+------------------+-----------+-------------+----------------+---------------+----------+
| 2011-11-16 |  500000.00000000 |      2.46 |  1230000.00 |   189905000.00 |  188675000.00 | purchase |
| 2011-11-16 | -500000.00000000 |      2.46 | -1230000.00 |  -189905000.00 | -188675000.00 | sale     |
| 2013-11-26 |       0.00011000 |    913.95 |        0.10 |           0.04 |         -0.06 | purchase |
| 2013-11-26 |      -0.00011000 |    913.95 |       -0.10 |          -0.04 |          0.06 | sale     |
| 2014-11-21 |       0.00010000 |    351.95 |        0.04 |           0.04 |          0.00 | purchase |
| 2014-12-09 |       0.00889387 |    353.67 |        3.15 |           3.38 |          0.23 | purchase |
| 2015-06-05 |       0.44520000 |    226.01 |      100.62 |         169.09 |         68.47 | purchase |
| 2015-06-07 |       0.44917576 |    226.02 |      101.52 |         170.60 |         69.08 | purchase |
| 2015-10-17 |       0.00010000 |    270.17 |        0.03 |           0.04 |          0.01 | purchase |
| 2015-11-05 |       0.00010000 |    400.78 |        0.04 |           0.04 |          0.00 | purchase |
| Totals:    |       0.90356963 |           |      205.40 |         343.19 |        137.79 |          |
+------------+------------------+-----------+-------------+----------------+---------------+----------+
```

## Days of the week.  From ./example-weekdays2.php

```
+-----------+--------+---------+----------+
| Day       | Abbrev | Initial | Position |
+-----------+--------+---------+----------+
| Sunday    | Sun    | S       |        0 |
| Monday    | Mon    | M       |        1 |
| Tuesday   | Tue    | T       |        2 |
| Wednesday | Wed    | W       |        3 |
| Thursday  | Thu    | T       |        4 |
| Friday    | Fri    | F       |        5 |
| Saturday  | Sat    | S       |        6 |
+-----------+--------+---------+----------+
```


# Usage.

## You can install with composer.

```
    $ cd yourproject
    $ composer require dan-da/texttable-php
```

include in your code via:

```
require_once 'path/to/vendor/autoload.php';
```


## Or just drop into your project.

There are no dependencies!

Simply include texttable.class.php from any PHP file and use it!

Here's a trivial example that prints info about the days of the week:


```
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
```
