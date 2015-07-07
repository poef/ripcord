# Introduction #

The Ripcord RPC client has a very simple API, but it allows you to extensively configure how it works.

# Details #

A very basic example:

```
<?php
    require_once('ripcord.php');
    $client = ripcord::client( 'http://www.moviemeter.nl/ws' );
    $score = $client->film->getScore( 'e3dee9d19a8c3af7c92f9067d2945b59', 500 );
?>
```

This creates a new client for the moviemeter.nl webservice and accesses the rpc method 'film.getScore' on it. The namespace 'film' is automatically mapped to $client->film.

Some XML-RPC servers support 'system.multiCall'. This allows you to call multiple methods using only one HTTP request. The ripcord client supports this with the following syntax:

```
<?php

    $client->system->multiCall()->start();
    ripcord::bind($score, 
        $client->film->getScore('e3dee9d19a8c3af7c92f9067d2945b59', 500) );
    ripcord::bind($methods, $client->system->listMethods() );
    $client->system->multiCall()->execute();

?>
```

In the above example both 'film.getScore' and 'system.listMethods' are called. The results are returned in $score and $methods.

An alternative syntax, without binding variables to the results automatically is:

```
<?php

    $client->system->multiCall()->start();
    $client->film->getScore('e3dee9d19a8c3af7c92f9067d2945b59', 500 );
    $client->system->listMethods();
    $result = $client->system->multiCall()->execute();

?>
```

After which $result is an array containing the results of each call, in order.

## Using a different transport layer ##

The Ripcord RPC Client uses PHP streams to connect to the remote server. In some cases this may not work, e.g. when your PHP has been configured not to allow fopen to access remote urls. In that case you may want to use CURL instead. Ripcorde provides an alternative transport class which uses CURL. You use it like this:

```
<?php
    
    $transport = new Ripcord_Transport_CURL();
    $client = ripcord::xmlrpcClient( $url, null, $transport );

?>
```

Any options can be passed through the constructor of a Ripcord\_Transport class as an array.

You can also use this method to add custom headers or cookies to the rpc request, e.g.:

```
<?php

    $transport = new Ripcord_Transport_Stream( array(
        'header' => "Cookie: foo=bar\r\n"
    ) );
    $client = ripcord::xmlrpcClient( $url, null, $transport );

?>
```

One usefull thing is to change the default timeout to something less than the default. Usually you will not want your script to hang for 30 seconds if it cannot connect to the XML-RPC server (should work as of PHP 5.2.1):

```
<?php

    $transport = new Ripcord_Transport_Stream( array(
        'timeout' => 2 // in seconds.
    ) );
    $client = ripcord::xmlrpcClient( $url, null, $transport );

?>
```

## Exceptions and Faults ##

The Ripcord client by default returns XMLRPC faults as a Fault array. You can check if a result is a fault like this:

```
<?php

    $result = $client->nonExistingMethod();
    if (ripcord::isFault($result)) {
        $errorMessage = $result['faultMessage'];
        $errorCode = $result['faultCode'];
    }

?>
```

But checking each and every one of your RPC calls can be a pain, so with a simple switch the ripcord client will throw an exception whenever a RPC fault is received:

```
<?php

    $client->_throwExceptions = true;
    try {
        $result = $client->nonExistingMethod();

    } catch( Ripcord_Exception $e ) {
        $errorMessage = $e->getMessage();
        $errorCode = $e->getCode();
    }

?>
```

If you cannot get a correct response from an XML-RPC server, you can check the exact XML-RPC request and response like this:

```
<?php

    $result = $client->someMethod();
    echo '<pre>' . $client->_request . '</pre>';
    echo '<pre>' . $client->_response . '</pre>';

?>
```