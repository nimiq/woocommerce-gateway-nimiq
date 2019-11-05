# strictmode-php

A tiny php include to make php code more strict and catch problems sooner.

This library catches all PHP notices, warnings, and errors and turns them
into PHP exceptions.

It also catches all uncaught exceptions and prints a stack trace.  It can
also send an email alert for uncaught exceptions.

The net result is that minor problems are caught early in development and it
forces the developer to write much cleaner code.

The email alerts are useful for production environments, to notify site
operators of any problems.

strictmode.php is a single file that can be included with require_once or can be
included via autoload when using composer.


# Let's see an example.

app code:

This uses require_once(), see below for autoload example.

```
<?php
require_once( __DIR__ . '/../strictmode.php');
define( 'strictmode\ALERTS_MAILTO', 'yourmail@yourdomain.com' );
...

echo HELLO;   // this will generate a PHP notice.
```

output:

```
Uncaught Exception. code: 8, message: Use of undefined constant HELLO - assumed 'HELLO'
/home/danda/dev/strictmode-php/t.php : 7

Stack Trace:
#0 /home/danda/dev/strictmode-php/t.php(7): strictmode\_global_error_handler(8, 'Use of undefine...', '/home/danda/dev...', 7, Array)
#1 {main}

INFO: alert sent to yourmail@yourdomain.com


Now exiting.  Please report this problem to the software author
```


For comparison, here is normal php output without strictmode.php

```
PHP Notice:  Use of undefined constant HELLO - assumed 'HELLO' in /home/danda/dev/strictmode-php/t.php on line 7
HELLO
```

# including via composer / autoload

```
<?php
require_once __DIR__  . '/../vendor/autoload.php';
define( 'strictmode\ALERTS_MAILTO', 'yourmail@yourdomain.com' );
\strictmode\initializer::init();

// your app code...
```


# Configuring email alerts

## set alert address

```
define( 'strictmode\ALERTS_MAILTO', 'yourmail@yourdomain.com' );
```

## disable warning if ALERTS_MAILTO not set

```
define( 'strictmode\ALERTS_DISABLE', 1 );
```


# Installation and Running.

Install strictmode-php into your own project using a composer require in your
project's composer.json, eg:

```
    $ cd yourproject
    $ composer require dan-da/strictmode-php
```

Then run composer install.


# Todos

* add more test cases
