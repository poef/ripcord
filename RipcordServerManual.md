# Introduction #

The Ripcord RPC Server is very easy to use, but still allows you to extensively configure it.

# Details #

A very simple Ripcord RPC server looks like this:

```
<?php
    require_once('ripcord.php');
    class myTest {
        /**
         * This method will return 'Bar'.
         */
        public function Foo() {
            return 'Bar';
        }
    }
    $test = new MyTest();
    $server = ripcord::server( $test );
    $server->run();
?>
```

This will automatically implement a XML-RPC, SOAP 1.1 and Simple RPC server. In addition it will auto document itself if you browse to its URL. The method Foo will be listed, including the description in the doc comments above it. Warning: if you use a cache like APC you may lose the descriptions, since APC removes comments.

If you need to support namespaced methods, you can do something like this:

```
<?php

    $test = new MyTest();
    $test2 = new MySecondTest();
    $server = ripcord::server( array( 
        'test' => $test,
        'test2' => $test
    ) );
    $server->run();

?>
```

This will add the public methods from MyTest to the rpc server within the namespace 'test', and the methods from MySecondTest in the namespace 'test2'. So the ripcord server would now publish, among others, the 'test.Foo' method.

You do not need to pass objects to the ripcord server factory, you can also directly pass methods or functions, as long as they are in a format which matches the criteria for PHP's is\_callable function. In addition you can also pass the name of a PHP class as an argument.

## Automatic Documentation ##

The Ripcord server by default autodocuments itself. You can either disable this or override the default autodocumentor. Disabling the autodocument feature is done by setting the documentor parameter to false:
```
  $server = ripcord::server( $services, null, false );
```

Overriding it with your own class is done like this:
```
  class myDocumentor extends Ripcord_Documentor {
    ....
  }
  $server = ripcord::server( $services, null, new myDocumentor() );
```