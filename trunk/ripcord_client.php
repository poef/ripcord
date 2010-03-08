<?php
/**
 * Ripcord is an easy to use XML-RPC library for PHP. 
 * @package Ripcord
 * @author Auke van Slooten <auke@muze.nl>
 * @copyright Copyright (C) 2010, Muze <www.muze.nl>
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version Ripcord 0.1 - PHP 5.0
 */

require_once(dirname(__FILE__).'/ripcord.php');

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
			throw new Ripcord_Exception_Remote($result['faultString'], $result['faultCode']);
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