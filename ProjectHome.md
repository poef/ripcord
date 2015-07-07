Ripcord is an attempt to create an RPC client and server around PHP's xmlrpc library which is as easy to use as possible.

You can create xml-rpc, (simplified) soap 1.1 and simple rpc clients with one call and then call rpc methods as if they were local methods of the client.

You can create a server with one call, passing it any number of objects whose methods it will publish and automatically document.

It is not an attempt to create a full blown SOAP client or server, it has no support for any of the more complicated options of SOAP. For that just use SoapClient.

PEAR provides an XML-RPC client and server solution which resembles Ripcord in some ways. There is a comparison in [RipcordVsXML\_RPC2](RipcordVsXML_RPC2.md).

To create a simple xmlrpc client do something like this:

```
<?php
    require_once('ripcord.php');

    $client = ripcord::xmlrpcClient( 'http://www.moviemeter.nl/ws' );

    $score = $client->film->getScore( 'e3dee9d19a8c3af7c92f9067d2945b59', 500 );
?>
```

See the RipcordClientManual for more information.

To create a simple xmlrpc server do something like this:

```
<?php
    require_once('ripcord.php');

    class myTest 
    {
        /**
         * Documentation for Foo.
         */
        public function Foo() 
        {
            return 'Bar';
        }
    }

    $server = ripcord::server( 'myTest' );

    $server->run();
?>
```

See the RipcordServerManual for more information.