## Random Feature Comparison ##

|                                 | XML\_RPC2  | Ripcord |
|:--------------------------------|:-----------|:--------|
| Built-in Caching                | yes        | no      |
| Requires xmlrpc Extension       | no         | yes     |
| Requires CURL Extension         | yes        | no      |
| Part of PEAR                    | yes        | no      |
| Overridable Transport Layer     | no         | yes     |
| Overridable Autodocumentor      | no         | yes     |
| Supports XML-RPC Introspection  | no         | yes     |
| Supports Native Multicall       | no         | yes     |

Introspection refers to [XML-RPC Introspection](http://xmlrpc-c.sourceforge.net/introspection.html).

## Why not use PEAR XML\_RPC2? ##

For most purposes you are probably just as well served with the PEAR XML\_RPC2 class. However Ripcord has a few differences which make it more flexible and simple in use.

### Auto generated service documentation ###

Ripcord automatically generates a webpage with documentation on which methods the
server provides and how to call them. This documentation is generated using PHP's introspection capabilities and will use information from phpDocumentor styled docComments as well. Ripcord allows you to simply override this with your own documentor class or just change the css, title, header and footer text.

### Dependency Injection ###

Ripcord allows you to inject the objects it depends on. The server has an autodocument feature, just like XML\_RPC2, but you can substitute your own documentor object for the default one. This allows you to generate your own auto documentation in whatever form you wish.

The client can use different transport objects to do the work of sending the request and receiving the answer. Default it comes with two; one based on streams and one based on CURL. With dependency injection not only can you substitute your own, you can also create a transport object seperately and modify its settings before passing it on to the Ripcord client. The XML\_RPC2 client has one transport backend, using CURL, and doesn't allow you to change any setting other than those it exposes through the client options.

### Flexibility ###

Ripcord is flexible not just because of its use of dependency injection, but also because of other design choices.

The XML\_RPC2 server accepts a class or object as its 'callHandler', but it is limited to one, with one prefix. Ripcord allows you to create an XML RPC server with many different namespaces (or prefixes).

Finally Ripcord supports the system methods of the xmlrpc extension. Meaning you automatically have things like system.listMethods and system.multiCall in your server for free.

### Simplicity ###

The Ripcord client has a very simple way to access namespaced (or prefixed) methods. You simply use the name of the namespace as a property of the client. So calling 'system.listMethods' becomes as easy as
```
  $client->system->listMethods();
```

The Ripcord client has another simple trick, it supports the system.multiCall method many xml-rpc servers implement in a very simple and readable syntax:
```
  $client->system->multiCall()->start();
  $client->doOneCall()->bind($resultOne),
  $client->doAnotherCall()->bind($resultTwo)
  $client->system->multiCall()->execute();
```

## When XML\_RPC2 is better ##

If you really need a caching mechanism for your xml-rpc client, but do not want to write your own caching wrapper. XML\_RPC2 comes with a caching client built-in.

Ripcord doesn't have an alternative backend when you cannot use PHP's xmlrpc extension. XML\_RPC2 does have a PHP only solution for that.

## Conclusion ##

Ripcord is designed to be simple to use and simple to comprehend and extend. Every class does only one thing and tries to do it well. It doesn't try to be everything for everybody. But it does allow you access to everything it uses, so the simple things are easy and the difficult things are still possible.