<?php
/**
 * Ripcord is an easy to use XML-RPC library for PHP. 
 * @package Ripcord
 * @author Auke van Slooten <auke@muze.nl>
 * @copyright Copyright (C) 2010, Muze <www.muze.nl>
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version Ripcord 0.3 - PHP 5.0
 */
 
require_once(dirname(__FILE__).'/ripcord.php');
 
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
	 * @see Ripcord_Server::setOutputOption()
	 */
	private $outputOptions = array(
		"output_type" => "xml",
		"verbosity" => "pretty",
		"escaping" => array("markup"),
		"version" => "auto",
		"encoding" => "utf-8"
	);

	/**
	 * Creates a new instance of the Ripcord server.
	 * @param mixed $services. Optional. An object or array of objects. The public methods in these objects will be exposed
	 * through the RPC server. If the services array has non-numeric keys, the key for each object will define its namespace.
	 * @param array $options. Optional. Allows you to override the default server settings. Accepted key names are:
	 * - 'documentor': allows you to specify an alternative HTML documentor class, or if set to false, no HTML documentor.
	 * - 'name'      : The name of the server, used by the default HTML documentor.
	 * - 'css'       : An url of a css file to link to in the HTML documentation.
	 * - 'wsdl'      : The wsdl 1.0 description of this service (only usefull if you run the 'soap 1.1' version, or the 'auto' version
	 * - 'wsdl2'     : The wsdl 2.0 description of this service
	 * In addition you can set any of the outputOptions for the xmlrpc server.
	 * @see Ripcord_Server::setOutputOption()
	 * @throws Ripcord_InvalidArgumentException (ripcord::unknownServiceType) when passed an incorrect service
	 * @throws Ripcord_Exception (ripcord::xmlrpcNotInstalled) when the xmlrpc extension in not available.
	 */
	function __construct($services = null, $options = null) 
	{
		if ( !function_exists( 'xmlrpc_server_create' ) )
		{
			throw new Ripcord_Exception('PHP XMLRPC library is not installed', ripcord::xmlrpcNotInstalled );
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
			ripcord::load('Ripcord_Documentor'); // no autoload needed this way
			$this->documentor = new Ripcord_Documentor( $docOptions );
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
	 * @throws Ripcord_InvalidArgumentException (ripcord::unknownServiceType) when passed an incorrect service
	 */
	public function addService($service, $serviceName = 0) 
	{
		if ($serviceName && !is_numeric($serviceName)) 
		{
			$serviceName .= '.';
		} else {
			$serviceName = '';
		}
		if (is_object($service)) {
			$reflection = new ReflectionObject($service);
		} else if (is_string($service) && class_exists($service)) {
			$reflection = new ReflectionClass($service);
		} else {
			throw new Ripcord_InvalidArgumentException('Unknown service type '.$serviceName, ripcord::unknownServiceType );
		}
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
	 * @throws Ripcord_InvalidArgumentException (ripcord::cannotRecurse) when passed a recursive multiCall
 	 * @throws Ripcord_Exception (ripcord::methodNotFound) when the requested method isn't available.
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
					throw new Ripcord_InvalidArgumentException( 'Cannot recurse system.multiCall', ripcord::cannotRecurse );
				}
				// system methods are handled internally by the xmlrpc server, so we've got to create a makebelieve request, 
				// there is no other way because of a badly designed API 
				$req = xmlrpc_encode_request( $method, $args, $this->outputOptions );
				$result = xmlrpc_server_call_method( $this->xmlrpc, $req, null, $this->outputOptions);
				return xmlrpc_decode( $result );
			} else {
				throw new Ripcord_Exception( 'Method '.$method.' not found.', ripcord::methodNotFound );
			}
		}
	}

	/**
	 * Allows you to set specific output options of the server after construction.
	 * @param string $option The name of the option
	 * @param mixed $value The value of the option
	 * The options are:
	 * - output_type: Return data as either php native data or xml encoded. Can be either 'php' or 'xml'. 'xml' is the default.
	 * - verbosity: Determines the compactness of generated xml. Can be either 'no_white_space', 'newlines_only' or 'pretty'. 
	 *   'pretty' is the default.
	 * - escaping: Determines how/whether to escape certain characters. 1 or more values are allowed. If multiple, they need
	 *   to be specified as a sub-array. Options are: 'cdata', 'non-ascii', 'non-print' and 'markup'. Default is 'non-ascii',
	 *   'non-print' and 'markup'.
	 * - version: Version of the xml vocabulary to use. Currently, three are supported: 'xmlrpc', 'soap 1.1' and 'simple'. The
	 *   keyword 'auto' is also recognized and tells the server to respond in whichever version the request cam in. 'auto' is
	 *   the default.
	 * - encoding: The character encoding that the data is in. Can be any supported character encoding. Default is 'utf-8'.
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

?>