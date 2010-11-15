Readme                      Ripcord: Easy XML-RPC Client and Server for PHP 5
=============================================================================

Ripcord is a very easy to use XML-RPC library for PHP. It provides client, 
server and auto documentation features for XML-RPC but also SimpleRPC and
simplified SOAP (1.1). It uses PHP's xmlrpc library and it needs at least PHP 5.

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


Extending Ripcord
=================

Ripcord is also very simple to extend. All functionality can be changed
through dependency injection. The client by default uses the PHP Streams API
to connect to a server, but can simply be reconfigured to use CURL. You
can provide your own configuration or even a completely new transport method
by simply injecting a new transport object into the client.

Any server created with Ripcord is auto documenting by default. Simply browse
to the URL of your RPC server and you will see a list of all methods including
any inline documentation for that method, if you use docblock style comments, e.g.:

/**
 * This will show up with your method description.
 */
function yourMethod() {
}

The auto documentor is again easily extended to use your own styles or extensions
and you can simply inject a completely different documentor object into the server
if you want. Or skip it altogether.


Documentation
=============

The full API documentation is included in the docs/ directory.

