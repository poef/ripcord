<?php
/**
 * Ripcord is an easy to use XML-RPC library for PHP. 
 * @package Ripcord
 * @author Auke van Slooten <auke@muze.nl>
 * @copyright Copyright (C) 2010, Muze <www.muze.nl>
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version Ripcord 0.3 - PHP 5.0
 */

/**
 * This interface defines the minimum methods any documentor needs to implement.
 */
interface Ripcord_Documentor_Interface 
{
	public function __construct( $options = null );
	public function setMethodData( $methods );
	public function handle( $rpcServer );
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
	 * @return string XML string with the introspection data.
	 */
	function getIntrospectionXML() {
		$xml = "<?xml version='1.0' ?><introspection version='1.0'><methodList>";
		if ( isset($this->methods) && is_array( $this->methods ) )
		{
			foreach ($this->methods as $method => $methodData )
			{
				if ( is_array( $methodData['call'] ) )
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

?>