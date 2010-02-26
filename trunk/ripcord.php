<?php
/**
 * Ripcord is an easy to use XML-RPC library for PHP. 
 * @package Ripcord
 * @author Auke van Slooten <auke@muze.nl>
 * @copyright Copyright (C) 2010, Muze <www.muze.nl>
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version Ripcord 0.1 - PHP 5.0
 */

/**
 * The ripcord class contains a number of useful static methods. This makes it a bit easier to create a server or client, convert types 
 * and check for errors.
 */
class ripcord
{
	/**
	 *  This method checks whether the given argument is an XML-RPC fault.
	 *  @param mixed $fault
	 *  @return bool
	 */
	public static function isFault($fault) 
	{
		return ( !isset($fault) || xmlrpc_is_fault($fault) );
	}

	/**
	 *  This method generates an XML-RPC fault with the given code and message.
	 *  @param int $code
	 *  @param string $message
	 *  @return array
	 */
	public static function fault($code, $message) 
	{
		return array('faultCode' => $code, 'faultString' => $message);
	}
	
	/**
	 * This method returns a new Ripcord server, which by default implements XML-RPC, Simple RPC and SOAP 1.1.
	 * The server will publish any methods passed through the $services argument. It can be configured through
	 * the $options argument.
	 * @param mixed $services Optional. Either an object or an array of objects. If the array has non-numeric keys, the key will be used as a namespace for the methods in the object.
	 * @param array $options Optional. An array of options to set for the Ripcord server. 
	 * @see Ripcord_Server
	 */
	public static function server($services = null, $options = null) 
	{
		return new Ripcord_Server($services, $options);
	}
	
	/**
	 * This method returns a new Ripcord client. By default this will be an XML-RPC client, but you can change this
	 * through the $options argument. 
	 * @param string $url The url of the RPC server to connect with
	 * @param array $options Optional. An array of options to set for the Ripcord client.
	 * @see Ripcord_Client
	 */
	public static function client($url, $options = null) 
	{
		return new Ripcord_Client($url, $options);
	}
	
	/**
	 * This method returns an XML-RPC datetime object from a given unix timestamp.
	 * @param int $timestamp
	 * @return object
	 */
	public static function datetime($timestamp) 
	{
		$datetime = date("Ymd\TH:i:s", $timestamp);
		xmlrpc_set_type($datetime, 'datetime');
		return $datetime;
	}

	/**
	 * This method returns a unix timestamp from a given XML-RPC datetime object.
	 * It will throw a 'Variable is not of type datetime' Ripcord_Exception (code -2)
	 * if the given argument is not of the correct type.
	 * @param object $datetime
	 * @return int
	 */
	public static function timestamp($datetime) 
	{
		if (xmlrpc_get_type($datetime)=='datetime') 
		{
			return $datetime->timestamp;
		} else {
			throw Ripcord_Exception('Variable is not of type datetime', -6);
		}
	}

	/**
	 * This method returns a new Ripcord client, configured to access a SOAP 1.1 server.
	 * @param string $url 
	 * @param array $options Optional.
	 * @see Ripcord_Client
	 */
	public static function soapClient($url, $options = null) 
	{
		$options['version'] = 'soap 1.1';
		return new Ripcord_Client($url, $options);
	}
	
	/**
	 * This method returns a new Ripcord client, configured to access an XML-RPC server.
	 * @param string $url 
	 * @param array $options Optional.
	 * @see Ripcord_Client
	 */
	public static function xmlrpcClient($url, $options = null) 
	{
		$options['version'] = 'xmlrpc';
		return new Ripcord_Client($url, $options);
	}
	
	/**
	 * This method returns a new Ripcord client, configured to access a Simple RPC server.
	 * @param string $url 
	 * @param array $options Optional.
	 * @see Ripcord_Client
	 */
	public static function simpleClient($url, $options = null) 
	{
		$options['version'] = 'simple';
		return new Ripcord_Client($url, $options);
	}
}

/**
 * This class is used for all exceptions thrown by Ripcord. Possible exceptions thrown are:
 * -1 Method {method} not found. - Thrown by the ripcord server when a requested method isn't found.
 * -2 Argument {index} is not a valid Ripcord call - Thrown by the client when passing incorrect arguments to system.multiCall.
 * -3 Cannot recurse system.multiCall  - Thrown by the ripcord server when system.multicall is called within itself.
 * -4  Could not access {url} - Thrown by the transport object when unable to access the given url.
 * -5 PHP XMLRPC library is not installed - Thrown by the ripcord server and client when the xmlrpc library is not installed.
 * -6 Variable is not of type datetime - Thrown by the ripcord datetime method.
 */
class Ripcord_Exception extends Exception { }

/**
 * This class is used for exceptions generated from xmlrpc faults returned by the server. The code and message correspond
 * to the code and message from the xmlrpc fault.
 */
class Ripcord_Server_Exception extends Ripcord_Exception { }

/**
 * This class implements the Ripcord server. It is an OO wrapper around PHP's XML-RPC methods, with some added features.
 * You can create an XML-RPC (or Simple RPC or a simple SOAP 1.1) server by defining a class with public methods and passing
 * an object (or array of objects) of this class to the constructor of Ripcord_Server. Then simply call the run() method.
 * 
 * A basic example:
 * <code>
 * <?php
 *   $myObject = new MyClass();
 *   $server = new Ripcord_Server( $myObject );
 *   $server->run();
 * ?>
 * </code>
 * 
 * An example with namespaces in the method names.
 * <code>
 * <?php
 *   $myObject = new MyClass();
 *   $myOtherObject = new MyOtherClass();
 *   $server = new Ripcord_Server( array( 'namespace1' => $myObject, 'namespace2' => $myOtherObject ) );
 *   $server->run();
 * ?>
 * </code>
 */
class Ripcord_Server 
{
	/**
	 * Contains a reference to the Ripcord documentor object.
	 * @see Ripcord_Documentor
	 */
	private $documentor = null;
	
	/**
	 * Contains a reference to the XML-RPC server created with xmlrpc_server_create.
	 */
	private $xmlrpc = null;
	
	/**
	 * Contains a list of methods set for this server. Excludes the system.* methods automatically
	 * created by PHP's xmlrpc_server_create.
	 */
	private $methods = array();

	/**
	 * Contains an array with outputOptions, used when calling methods on the xmlrpc server created with 
	 * xmlrpc_server_create. These options can be overridden through the $options parameter of the 
	 * Ripcord_Server constructor.
	 * @see Ripcord_Server::setOutputOption
	 */
	private $outputOptions = array(
		"output_type" => "xml",
		"verbosity" => "pretty",
		"escaping" => array("markup", "non-ascii", "non-print"),
		"version" => "auto",
		"encoding" => "utf-8"
	);

	/**
	 * Creates a new instance of the Ripcord server.
	 * @param mixed $services. Optional. An object or array of objects. The public methods in these objects will be exposed
	 * through the RPC server. If the services array has non-numeric keys, the key for each object will define its namespace.
	 * @param array $options. Optional. Allows you to override the default server settings. Accepted key names are:
	 * 'documentor' - allows you to specify an alternative HTML documentor class, or if set to false, no HTML documentor.
	 * 'name' - The name of the server, used by the default HTML documentor.
	 * 'css' - An url of a css file to link to in the HTML documentation.
	 * 'wsdl' - The wsdl 1.0 description of this service (only usefull if you run the 'soap 1.1' version, or the 'auto' version
	 * 'wsdl2' - The wsdl 2.0 description of this service
	 * In addition you can set any of the outputOptions for the xmlrpc server.
	 * @see Server::setOutputOption
	 */
	function __construct($services = null, $options = null) 
	{
		if ( !function_exists( 'xmlrpc_server_create' ) )
		{
			throw new Exception('PHP XMLRPC library is not installed', -5);
		}
		$this->xmlrpc = xmlrpc_server_create();
		if (isset($services)) 
		{
			if (is_array($services)) 
			{
				foreach ($services as $serviceName => $service) 
				{
					$this->addService($service, $serviceName);
				}
			} else {
				$this->addService($services);
			}
		}
		if ( isset($options['documentor']) )
		{
			$this->documentor = $options['documentor'];
		}
		else 
		{
			$doc = array('name', 'css', 'wsdl', 'wsdl2');
			$docOptions = array();
			foreach ( $doc as $key ) 
			{
				if ( isset($options[$key]) ) 
				{
					$docOptions[$key] = $options[$key];
					unset( $options[$key] );
				}
			}
			$docOptions['version'] = $options['version'] ? $options['version'] : $this->outputOptions['version'];
			$this->documentor = new Documentor( $docOptions );
		}
		xmlrpc_server_register_introspection_callback( $this->xmlrpc, array( $this->documentor, 'getIntrospectionXML') );
		if ( isset($options) ) 
		{
			$this->outputOptions = array_merge($this->outputOptions, $options);
		}
	}
	
	/**
	 * Allows you to add a service to the server after construction.
	 * @param object $service The object whose public methods must be added to the rpc server
	 * @param string $serviceName Optional. The namespace for the methods.
	 */
	public function addService($service, $serviceName = 0) 
	{
		if ($serviceName && !is_numeric($serviceName)) 
		{
			$serviceName .= '.';
		} else {
			$serviceName = '';
		}
		$reflection = new ReflectionObject($service);
		$methods = $reflection->getMethods();
		if (is_array($methods)) 
		{
			foreach($methods as $method) 
			{
				if ( substr($method->name, 0, 1) != '_'
					&& !$method->isPrivate() && !$method->isProtected()) 
				{
					$rpcMethodName = $serviceName.$method->name;
					$this->addMethod( 
						$rpcMethodName, 
						array( $service, $method->name)
					);
				}
			}
		}
	}
	
	/**
	 * Allows you to add a single method to the server after construction.
	 * @param string $name The name of the method as exposed through the rpc server
	 * @param callback $method The name of the method to call, or an array with classname or object and method name.
	 */
	public function addMethod($name, $method) 
	{
		$this->methods[$name] = array(
			'name' => $name,
			'call' => $method
		);
		xmlrpc_server_register_method($this->xmlrpc, $name, array($this, 'call') );
	}
	
	/**
	 * Runs the rpc server. Automatically handles an incoming request.
	 */
	public function run() 
	{
		$this->documentor->setMethodData( $this->methods );
		$request_xml = file_get_contents('php://input');
		if (!$request_xml) 
		{
			if ( ( $query = $_SERVER['QUERY_STRING'] ) && isset($this->wsdl[$query]) && $this->wsdl[$query] )
			{
				echo $this->wsdl[$query];
			}
			else if ( $this->documentor )
			{
				$this->documentor->handle( $this, $this->methods );
			}
			else
			{
				echo xmlrpc_encode_request(
					null,  
					ripcord::fault( -1, 'No request xml found.' ),
					$this->outputOptions
				);
			}
		}
		else 
		{
			echo $this->handle( $request_xml );
		}
	}
	
	/**
	 * Handles the given request xml
	 * @param string $request_xml The incoming request.
	 * @return string
	 */
	public function handle($request_xml) 
	{
		$params = xmlrpc_decode_request($request_xml, $method);
		if ( $method == 'system.multiCall' ) {
			// php's xml-rpc server (xmlrpc-epi) crashes on multicall, so handle it ourselves...
			if ( $params && is_array( $params ) ) 
			{
				$result = array();
				$params = $params[0];
				foreach ( $params as $param ) {
					$method = $param['methodName'];
					$args = $param['params'];
					try {
						// XML-RPC specification says that non-fault results must be in a single item array
						$result[] = array( $this->call($method, $args) );
					} catch( Exception $e) {
						$result[] = ripcord::fault( $e->getCode(), $e->getMessage() );
					}
				}
				$result = xmlrpc_encode_request( null, $result, $this->outputOptions );
			} else {
				$result = xmlrpc_encode_request( 
					null, 
					ripcord::fault( -2, 'Illegal or no params set for system.multiCall'), 
					$this->outputOptions
				);
			}
		} else {
			try {
				$result = xmlrpc_server_call_method(
					$this->xmlrpc, $request_xml, null, $this->outputOptions
				);
			} catch( Exception $e) {
				$result = xmlrpc_encode_request( 
					null, 
					ripcord::fault( $e->getCode(), $e->getMessage() ), 
					$this->outputOptions
				);
			}
		}
		return $result;
	}
	
	/**
	 * Calls a method by its rpc name. 
	 * @param string $method The rpc name of the method
	 * @param array $args The arguments to this method
	 * @return mixed
	 */
	public function call( $method, $args = null ) 
	{
		if ( $this->methods[$method] ) 
		{
			$call = $this->methods[$method]['call'];
			return call_user_func_array( $call, $args);
		} else {
			if ( substr( $method, 0, 7 ) == 'system.' ) 
			{
				if ( $method == 'system.multiCall' ) {
					throw new Ripcord_Exception( 'Cannot recurse system.multiCall', -3 );
				}
				// system methods are handled internally by the xmlrpc server, so we've got to create a makebelieve request, 
				// there is no other way because of a badly designed API 
				$req = xmlrpc_encode_request( $method, $args, $this->outputOptions );
				$result = xmlrpc_server_call_method( $this->xmlrpc, $req, null, $this->outputOptions);
				return xmlrpc_decode( $result );
			} else {
				throw new Ripcord_Exception( 'Method '.$method.' not found.', -1 );
			}
		}
	}

	/**
	 * Allows you to set specific output options of the server after construction.
	 * @param string $option The name of the option
	 * @param mixed $value The value of the option
	 * The options are:
	 * output_type: Return data as either php native data or xml encoded. Can be either 'php' or 'xml'. 'xml' is the default.
	 * verbosity: Determines the compactness of generated xml. Can be either 'no_white_space', 'newlines_only' or 'pretty'. 
	 *   'pretty' is the default.
	 * escaping: Determines how/whether to escape certain characters. 1 or more values are allowed. If multiple, they need
	 *   to be specified as a sub-array. Options are: 'cdata', 'non-ascii', 'non-print' and 'markup'. Default is 'non-ascii',
	 *   'non-print' and 'markup'.
	 * version: Version of the xml vocabulary to use. Currently, three are supported: 'xmlrpc', 'soap 1.1' and 'simple'. The
	 *   keyword 'auto' is also recognized and tells the server to respond in whichever version the request cam in. 'auto' is
	 *   the default.
	 * encoding: The character encoding that the data is in. Can be any supported character encoding. Default is 'utf-8'.
	 */
	public function setOutputOption($option, $value) 
	{
		if ( isset($this->outputOptions[$option]) ) 
		{
			$this->outputOptions[$option] = $value;
			return true;
		} else {
			return false;
		}
	}
}

/**
 * This interface defines the minimum methods any documentor needs to implement.
 */
interface Ripcord_Documentor_Interface {
	public function __construct( $options );
	public function setMethodData( $methods );
	public function handle ( $rpcServer );
	public function getIntrospectionXML();
}

/**
 * This class implements the default documentor for the ripcord server. Any request to the server
 * without a request_xml is handled by the documentor.
 */
class Ripcord_Documentor implements Ripcord_Documentor_Interface
{
	/**
	 * The name of the rpc server, used as the title and heading of the default HTML page.
	 */
	public $name     = 'Ripcord: Simple RPC Server';
	
	/**
	 * A url to an optional css file.
	 */
	public $css      = false;
	
	/**
	 * The wsdl 1.0 description.
	 */
	public $wsdl     = false;
	
	/**
	 * The wsdl 2.0 description
	 */
	public $wsdl2    = false;
	
	/**
	 * Which version of the XML vocabulary the server implements. Either 'xmlrpc', 'soap 1.1', 'simple' or 'auto'.
	 */
	public $version  = false;
	
	/**
	 * The root URL of the rpc server.
	 */
	public $root     = '';

	/**
	 * A list of method data, containing all the user supplied methods the rpc server implements.
	 */
	private $methods = null;
	
	/**
	 * The constructor for the Ripcord_Documentor class. 
	 * @param array $options. Optional. Allows you to set the public properties of this class upon construction.
	 */
	public function __construct($options = null) 
	{
		$check = array( 'name', 'css', 'wsdl', 'wsdl2', 'root', 'version' );
		foreach ( $check as $name )
		{
			if ( isset($options[$name]) ) 
			{
				$this->{$name} = $options[$name];
			}
		}
	}

	/**
	 * This method fills the list of method data with all the user supplied methods of the rpc server.
	 * @param array $methodData A list of methods with name and callback information.
	 */
	public function setMethodData( $methodData )
	{
		$this->methods = $methodData;
	}

	/**
	 * This method handles any request which isn't a valid rpc request.
	 * @param object $rpcServer A reference to the active rpc server.
	 */
	public function handle( $rpcServer ) 
	{
		$methods = $rpcServer->call('system.listMethods');
		echo '<html><head><title>' . $this->name . '</title>';
		if ( isset($rpcServer->css) ) 
		{
			echo '<link rel="stylesheet" type="text/css" href="' . $this->css . '">';
		}
		echo '</head><body>';
		echo '<h1>' . $this->name . '<h1>';
		echo '<p>';
		$showWSDL = false;
		switch ( $this->version ) 
		{
			case 'xmlrpc':
				echo 'This server implements the <a href="http://www.xmlrpc.com/spec">XML-RPC specification</a>';
			break;
			case 'simple':
				echo 'This server implements the <a href="http://sites.google.com/a/simplerpc.org/simplerpc/Home/simplerpc-specification-v09">SimpleRPC 1.0 specification</a>';
			break;
			case 'auto';
				echo 'This server implements the <a href="http://www.w3.org/TR/2000/NOTE-SOAP-20000508/">SOAP 1.1</a>, <a href="http://www.xmlrpc.com/spec">XML-RPC</a> and <a href="http://sites.google.com/a/simplerpc.org/simplerpc/Home/simplerpc-specification-v09">SimpleRPC 1.0</a> specification.';
				$showWSDL = true;
			break;
			case 'soap 1.1':
				echo 'This server implements the <a href="http://www.w3.org/TR/2000/NOTE-SOAP-20000508/">SOAP 1.1 specification</a>.';
				$showWSDL = true;
			break;
		}
		echo '</p>';
		if ( $showWSDL && ( $this->wsdl || $this->wsdl2 ) ) 
		{
			echo '<ul>';
			if ($this->wsdl) 
			{
				echo '<li><a href="' . $this->root . '?wsdl">WSDL 1.1 Description</a></li>';
			}
			if ($this->wsdl2) 
			{
				echo '<li><a href="' . $this->root . '?wsdl2">WSDL 2.0 Description</a></li>';
			}					
			echo '</ul>';
		}
		foreach ( $methods as $method ) 
		{
			$signature = $rpcServer->call( 'system.methodSignature', array( $method ) );
			echo '<h2>' . $method . '( ';
			if (is_array($signature) && isset($signature[0]) )
			{
				$types = '';
				foreach ($signature[0] as $type) 
				{
					$types .= $type.' , ';
				}
				$types = substr($types, 0, -3);
				echo $types;
			}
			echo ' )</h2>';
			$description = $rpcServer->call( 'system.methodHelp', array( $method ) );
			echo '<p>' . $description . '</p>';
		}
		echo '</body></html>';
	}

	/**
	 * This method returns an XML document in the introspection format expected by xmlrpc_server_register_introspection_callback
	 * It uses the php Reflection classes to gather information from the registered methods. Descriptions are added from phpdoc docblocks if found.
	 */
	function getIntrospectionXML() {
		$xml = "<?xml version='1.0' ?><introspection version='1.0'><methodList>";
		if ( isset($this->methods) && is_array( $this->methods ) )
		{
			foreach ($this->methods as $method => $methodData )
			{
				if ( is_array( $methodData['call'] )
				{
					$reflection = new ReflectionMethod( 
						get_class( $methodData['call'][0] ), 
						$methodData['call'][1] 
					);
				}
				else
				{
					$reflection = new ReflectionFunction( $methodData['call'] );
				}
				$description = trim( str_replace( 
					array( '/*', '*/', '*' ), 
					'', 
					$reflection->getDocComment() 
				) );
				if ($description) 
				{
					$description = '<p>'.str_replace("\n\n", '</p><p>', $description).'</p>';
				}
				$xml .= "<methodDescription name='".$method."'><purpose>".
					htmlspecialchars($description).
					"</purpose></methodDescription>";
			}
		}	
		$xml .= "</methodList></introspection>";
		return $xml;
	}
}

/**
 * This class implements a simple RPC client, for XML-RPC, (simplified) SOAP 1.1 or Simple RPC. The client abstracts 
 * the entire RPC process behind native PHP methods. Any method defined by the rpc server can be called as if it was
 * a native method of the rpc client.
 * 
 *  E.g.
 *  <code>
 *  <?php
 *    $client = new Ripcord_Client( 'http://www.moviemeter.nl/ws' );
 *    $score = $client->film->getScore( 'e3dee9d19a8c3af7c92f9067d2945b59', 500 );
 *  ?>
 *  </code>
 * 
 * The client has a simple interface for the system.multiCall method:  
 * <code>
 * <?php
 *  $client = new Ripcord_Client( 'http://ripcord.muze.nl/ripcord.php' );
 *  $client->system->multiCall(
 *     $client->system->listMethods()->bind($methods),
 *     $client->getFoo()->bind($foo)
 * ?>
 * </code>
 * 
 * The soap client can only handle the basic php types and doesn't understand xml namespaces. Use PHP's SoapClient 
 * for complex soap calls. This client cannot parse wsdl.
 *
 * @link  http://wiki.moviemeter.nl/index.php/API Moviemeter API documentation
 */
class Ripcord_Client 
{
	/**
	 * The url of the rpc server
	 */
	private $_url = '';

	/**
	 * The transport object, used to post requests.
	 */
	private $_transport = null;

	/**
	 * A list of output options, used with the xmlrpc_encode_request method.
	 * @see Ripcord_Server::setOutputOption
	 */
	private $_outputOptions = array(
		"output_type" => "xml",
		"verbosity" => "pretty",
		"escaping" => array("markup", "non-ascii", "non-print"),
		"version" => "xmlrpc",
		"encoding" => "utf-8"
	);

	/**
	 * The namespace to use when calling a method.
	 */
	private $_namespace = null;

	/**
	 * A reference to the root client object. This is so when you use namespaced sub clients, you can always
	 * find the _response and _request data in the root client.
	 */
	private $_rootClient = null;

	/**
	 * A counter to keep track of the scope of method calls. If this variable is non zero, we're in the system
	 * namespace, so calls to non-system methods must be deferred for later use with system.multiCall.
	 */
	private static $_multicall = 0;

	/**
	  * The exact response from the rpc server. For debugging purposes.
	 */
	public $_response = '';

	/**
	 * The exact request from the client. For debugging purposes.
	 */
	public $_request = '';

	/**
	 * Whether or not to throw exceptions when an xml-rpc fault is returned by the server. Default is false.
	 */
	public $_throwExceptions = false;
	
	/**
	 * The constructor for the RPC client.
	 * @param string $url The url of the rpc server
	 * @param array $options Optional. A list of outputOptions. @see Ripcord_Server::setOutputOption
	 * @param object $rootClient Optional. Used internally when using namespaces.
	 */
	public function __construct( $url, array $options = null, $rootClient = null) 
	{
		if ( !isset($rootClient) ) {
			$rootClient = $this;
		}
		$this->_rootClient = $rootClient;
		$this->_url = $url;
		if ( isset($options) ) 
		{
			if ( isset($options['namespace']) ) 
			{
				$this->_namespace = $options['namespace'];
				unset( $options['namespace'] );
			}
			if ( isset($options['transport']) ) 
			{
				$this->_transport = $options['transport'];
				unset( $options['transport'] );
			}
			$this->_outputOptions = $options;
		}
		if ( !isset($this->_transport) ) 
		{
			$this->_transport = new Ripcord_Transport_Stream();
		}
		if ( !function_exists( 'xmlrpc_encode_request' ) )
		{
			throw new Exception('PHP XMLRPC library is not installed', -5);
		}
	}

	/**
	 * This method catches any native method called on the client and calls it on the rpc server instead. It automatically
	 * parses the resulting xml and returns native php type results.
	 */
	public function __call($name, $args) 
	{
		if ( isset($this->_namespace) ) 
		{
			$name = $this->_namespace . '.' . $name;
		}

		if ( self::$_multicall && $this->_namespace === 'system' ) 
		{
			// this exits the multicall scope when simply calling a system method
			// but if you call a system method in the multicall scope, $_multicall
			// is > 1, so you stay in multicall scope, and system.multiCall will
			// add your system method to the list of methods to call later.
			self::$_multicall -= 1;
		}
		if ( self::$_multicall && ($name !== 'system.multiCall') ) 
		{
			// inside a multicall method, so return deferred calls instead
			return new Ripcord_Call( $name, $args );
		} 
		else if ( $name === 'system.multiCall' ) 
		{
			self::$_multicall = false;
			if ( is_array( $args ) && count( $args ) == 1 && is_array( $args[0] )) 
			{ 
				// multicall is called with a simple array of calls.
				$args = $args[0];
			}
			$params = array();
			$bound = array();
			foreach ( $args as $key => $arg ) 
			{
				if ( !is_a( $arg, 'Ripcord_Call' ) ) 
				{
					throw new Ripcord_Exception(
						'Argument '.$key.' is not a valid Ripcord call', -2);
				}
				$bound[$key] = $arg;
				$arg->index  = count( $params );
				$params[]    = array(
					'methodName' => $arg->method,
					'params'     => $arg->params
				);
			}
			$args = array( $params );
		}

		$request  = xmlrpc_encode_request( $name, $args, $this->_outputOptions );
		$response = $this->_transport->post( $this->_url, $request );
		$result   = xmlrpc_decode( $response );
		$this->_rootClient->_request  = $request;
		$this->_rootClient->_response = $response;
		if ( ripcord::isFault( $result ) && $this->_throwExceptions ) 
		{
			throw new Ripcord_Server_Exception($result['faultString'], $result['faultCode']);
		}
		if ( isset($bound) && is_array( $bound ) ) 
		{
			foreach ( $bound as $key => $callObject ) 
			{
				$returnValue = $result[$callObject->index];
				if ( is_array( $returnValue ) && count( $returnValue ) == 1 ) 
				{
					// XML-RPC specification says that non-fault results must be in a single item array
					$returnValue = current($returnValue);
				}
				$callObject->bound = $returnValue;
				$bound[$key] = $returnValue;
			}
			$result = $bound;
		}		
		return $result;
	}

	/**
	 * This method catches any reference to properties of the client and uses them as a namespace. The
	 * property is automatically created as a new instance of the rpc client, with the name of the property
	 * as a namespace.
	 */
	public function __get($name) 
	{
		$result = null;
		if ( !isset($this->{$name}) ) 
		{
			$result = new Ripcord_Client(
				$this->_url, 
				array_merge($this->_outputOptions, array( 
					'namespace' => $this->_namespace ? 
						$this->_namespace . '.' . $name : $name, 
					'transport' => $this->_transport)
				),
				$this->_rootClient
			);
			if ( $name === 'system' ) 
			{
				self::$_multicall+=1;
			} else {
				$this->{$name} = $result;
			}
		}
		return $result;
	}
}

/**
 *  This class is used with the Ripcord_Client when calling system.multiCall. Instead of immediately calling the method on the rpc server,
 *  a Ripcord_Call  object is created with all the information needed to call the method using the multicall parameters. The call object is
 *  returned immediately and is used as input parameter for the multiCall call. The result of the call can be bound to a php variable. This
 *  variable will be filled with the result of the call when it is available.
 */
class Ripcord_Call 
{
	/**
	 * The method to call on the rpc server
	 */
	public $method = null;
	
	/**
	 * The arguments to pass on to the method.
	 */
	public $params = null;
	
	/**
	 * The index in the multicall request array, if any.
	 */
	public $index  = null;
	
	/**
	 * A reference to the php variable to fill with the result of the call, if any.
	 */
	public $bound  = null;
	
	/**
	 * The constructor for the Ripcord_Call class.
	 * @param string $method The name of the rpc method to call
	 * @param array $params The parameters for the rpc method.
	 */
	public function __construct($method, $params) 
	{
		$this->method = $method;
		$this->params = $params;
	}

	/**
	 * This method allows you to bind a php variable to the result of this method call.
	 * When the method call's result is available, the php variable will be filled with
	 * this result.
	 */
	public function bind(&$bound) 
	{
		$this->bound =& $bound;
		return $this;
	}
}

/**
 * This interface describes the minimum interface needed for the transport object used by the
 * Ripcord_Client
 */
interface Ripcord_Transport 
{
	/**
	 * This method must post the request to the given url and return the results.
	 * @param string $url The url to post to.
	 * @param string $request The request to post.
	 * @return string
	 */
	public function post( $url, $request );
}

/**
 * This class implements the Ripcord_Transport interface using PHP streams.
 */
class  Ripcord_Transport_Stream implements Ripcord_Transport 
{
	/**
	 * A list of stream context options.
	 */
	private $options = array();
	
	/**
	 * Contains the headers sent by the server.
	 */
	public $responseHeaders = null;
	
	/**
	 * This is the constructor for the Ripcord_Transport_Stream class.
	 * @param array $contextOptions Optional. An array with stream context options.
	 */
	public function __construct( $contextOptions = null ) 
	{
		if ( isset($contextOptions) ) 
		{
			$this->options = $contextOptions;
		}
	}

	/**
	 * This method posts the request to the given url.
	 * @param string $url The url to post to.
	 * @param string $request The request to post.
	 * @return string
	 */
	public function post( $url, $request ) 
	{
		$options = array_merge( 
			$this->options, 
			array( 
				'http' => array(
					'method' => "POST",
					'header' => "Content-Type: text/xml",
					'content' => $request
				) 
			) 
		);
		$context = stream_context_create( $options );
		$result  = file_get_contents( $url, false, $context );
		$this->responseHeaders = $http_response_header;
		if ( !$result )
		{
			throw new Ripcord_Exception( 'Could not access ' . $url, -4 );
		}
		return $result;
	}
}

/**
 * This class implements the Ripcord_Transport interface using CURL.
 */
class Ripcord_Transport_CURL implements Ripcord_Transport 
{
	/**
	 * A list of CURL options.
	 */
	private $options = array();
	
	/**
	 * Contains the headers sent by the server.
	 */
	public $responseHeaders = null;

	/**
	 * This is the constructor for the Ripcord_Transport_CURL class.
	 * @param array $curlOptions A list of CURL options.
	 */
	public function __construct( $curlOptions = null ) 
	{
		if ( isset($curlOptions) )
		{
			$this->options = $curlOptions;
		}
	}

	/**
	 * This method posts the request to the given url
	 * @param string $url The url to post to.
	 * @param string $request The request to post.
	 */
	public function post( $url, $request) 
	{
		$curl = curl_init();
		curl_setopt_array( $curl, array_merge(
			array(
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_URL            => $url,
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $request,
				CURLOPT_HEADER         => true
			),
			$this->options
		) );
		$contents = curl_exec( $curl );
		$headerSize = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
		$this->responseHeaders = substr( $contents, 0, $headerSize );
		$contents = substr( $contents, $headerSize );

		if ( curl_errno( $curl ) ) 
		{
			$errorNumber = curl_errno( $curl );
			$errorMessage = curl_error( $curl );
			curl_close( $curl );
			throw new Ripcord_Exception( 'Could not access ' . $url, -4, 
				new Exception( $errorMessage, $errorNumber ) );
		}
		curl_close($curl);
		return $contents;
	}
}

?>