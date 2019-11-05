<?php

namespace tester;

require_once __DIR__  . '/../vendor/autoload.php';

// require_once( __DIR__ . '/../strictmode.php');
\strictmode\initializer::init();

class notice extends test_base {

    public function runtests() {
        $this->test1();
    }
    
    protected function test1() {
        
        $code = null;
        $msg = null;
        try {
            echo HELLO;
        }
        catch( \Exception $e ) {
            $code = $e->getCode();
            $msg  = md5($e->getMessage());
        }
        $this->eq( $code, 8, 'exception code' );
        $this->eq( $msg, md5("Use of undefined constant HELLO - assumed 'HELLO'"), 'exception message md5' );
    }

}
