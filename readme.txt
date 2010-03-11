Readme                        Ripcord: Simple RPC Client and Server for PHP 5
=============================================================================

Ripcord is an attempt to create a very easy to use RPC library, server and 
client. It uses PHP's xmlrpc library and it needs at least PHP 5.

To create a simple xmlrpc client do something like this:

<?php
    require_once('ripcord.php');
    $client = ripcord::xmlrpcClient( 'http://www.moviemeter.nl/ws' );
    $score  = $client->film->getScore( 'e3dee9d19a8c3af7c92f9067d2945b59', 500 );
?>

See the RipcordClientManual 
<http://code.google.com/p/ripcord/wiki/RipcordClientManual> for more information.

To create a simple xmlrpc server do something like this:

<?php
    require_once('ripcord.php');
    class myTest {
        public function Foo() {
            return 'Bar';
        }
    }
    $test = new MyTest();
    $server = ripcord::server( $test );
    $server->run();
?>

See the RipcordServerManual 
<http://code.google.com/p/ripcord/wiki/RipcordServerManual> for more information.

The full API documentation is included in the docs/ directory.