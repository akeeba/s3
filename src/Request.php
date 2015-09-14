<?php
/**
 * Akeeba Engine
 * The modular PHP5 site backup engine
 *
 * @copyright Copyright (c)2006-2015 Nicholas K. Dionysopoulos
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   akeebaengine
 */

namespace Akeeba\Engine\Postproc\Connector\S3v4;

use Akeeba\Engine\Postproc\Connector\S3v4\Response\Error;

// Protection against direct access
defined('AKEEBAENGINE') or die();


class Request
{
	/**
	 * The HTTP verb to use
	 *
	 * @var  string
	 */
	private $verb = 'GET';

	/**
	 * The bucket we are using
	 *
	 * @var  string
	 */
	private $bucket = '';

	/**
	 * The object URI, relative to the bucket's root
	 *
	 * @var  string
	 */
	private $uri = '';

	/**
	 * The remote resource we are querying
	 *
	 * @var  string
	 */
	private $resource = '';

	/**
	 * Query string parameters
	 *
	 * @var  array
	 */
	private $parameters = array();

	/**
	 * Amazon-specific headers to pass to the request
	 *
	 * @var  array
	 */
	private $amzHeaders = array();

	/**
	 * Regular HTTP headers to send in the request
	 *
	 * @var  array
	 */
	private $headers = array(
		'Host' => '',
		'Date' => '',
		'Content-MD5' => '',
		'Content-Type' => ''
	);

	/**
	 * The Amazon S3 configuration object
	 *
	 * @var Configuration
	 */
	private $configuration = null;

	/**
	 * The file resource we are reading data from / writing data to
	 *
	 * @var  bool|resource
	 */
	private $fp = false;

	/**
	 * Content length for PUT and POST operations, given in bytes
	 *
	 * @var  int
	 */
	private $size = 0;

	/**
	 * The data to use for PUT or POST operations. If you want to read directly from a file use $fp instead.
	 *
	 * @var  bool
	 */
	private $data = false;

	/**
	 * The response object
	 *
	 * @var  Response
	 */
	private $response = null;

	/**
	 * The location of the CA certificate cache. It can be a file or a directory. If it's not specified, the location
	 * set in AKEEBA_CACERT_PEM will be used
	 *
	 * @var  string|null
	 */
	private $caCertLocation = null;

	/**
	 * Constructor
	 *
	 * @param   string         $verb           HTTP verb, e.g. 'POST'
	 * @param   string         $bucket         Bucket name, e.g. 'example-bucket'
	 * @param   string         $uri            Object URI
	 * @param   Configuration  $configuration  The Amazon S3 configuration object to use
	 *
	 * @return  Request
	 */
	function __construct($verb, $bucket = '', $uri = '', Configuration $configuration)
	{
		$this->verb          = $verb;
		$this->bucket        = $bucket;
		$this->uri           = '/';
		$this->configuration = $configuration;

		if (!empty($uri))
		{
			$this->uri = '/' . str_replace('%2F', '/', rawurlencode($uri));
		}

		$defaultHost = $configuration->getEndpoint();

		$this->headers['Host'] = $defaultHost;
		$this->resource        = $this->uri;

		if ($this->bucket !== '')
		{
			$this->headers['Host'] = $this->bucket . '.' . $defaultHost;
			$this->resource        = '/' . $this->bucket . $this->uri;
		}

		$this->headers['Date'] = gmdate('D, d M Y H:i:s T');

		$this->response = new Response();
	}

	/**
	 * Set a request parameter
	 *
	 * @param   string  $key    The parameter name
	 * @param   string  $value  The parameter value
	 *
	 * @return  void
	 */
	public function setParameter($key, $value)
	{
		$this->parameters[$key] = $value;
	}

	/**
	 * Set a request header
	 *
	 * @param   string  $key    The header name
	 * @param   string  $value  The header value
	 *
	 * @return  void
	 */
	public function setHeader($key, $value)
	{
		$this->headers[$key] = $value;
	}

	/**
	 * Set an x-amz-meta-* header
	 *
	 * @param   string  $key    The header name
	 * @param   string  $value  The header value
	 *
	 * @return  void
	 */
	public function setAmzHeader($key, $value)
	{
		$this->amzHeaders[$key] = $value;
	}

	/**
	 * Get the HTTP verb of this request
	 *
	 * @return  string
	 */
	public function getVerb()
	{
		return $this->verb;
	}

	/**
	 * Get the S3 bucket's name
	 *
	 * @return  string
	 */
	public function getBucket()
	{
		return $this->bucket;
	}

	/**
	 * Get the absolute URI of the resource we're accessing
	 *
	 * @return  string
	 */
	public function getResource()
	{
		return $this->resource;
	}

	/**
	 * Get the parameters array
	 *
	 * @return  array
	 */
	public function getParameters()
	{
		return $this->parameters;
	}

	/**
	 * Get the Amazon headers array
	 *
	 * @return  array
	 */
	public function getAmzHeaders()
	{
		return $this->amzHeaders;
	}

	/**
	 * Get the other headers array
	 *
	 * @return  array
	 */
	public function getHeaders()
	{
		return $this->headers;
	}

	/**
	 * Get a reference to the Amazon configuration object
	 *
	 * @return  Configuration
	 */
	public function getConfiguration()
	{
		return $this->configuration;
	}

	/**
	 * Get the file pointer resource (for PUT and POST requests)
	 *
	 * @return  bool|resource
	 */
	public function &getFp()
	{
		return $this->fp;
	}

	/**
	 * Get the length, in bytes, of the data to send in a PUT/POST request
	 *
	 * @return  int
	 */
	public function getSize()
	{
		return $this->size;
	}

	/**
	 * Get the data to send in a PUT/POST request. It will be null if the file pointer is set.
	 *
	 * @return  mixed
	 */
	public function &getData()
	{
		return $this->data;
	}

	/**
	 * Set the data resource as a file pointer
	 *
	 * @param   resource  $fp
	 */
	public function setFp($fp)
	{
		$this->fp = $fp;
		$this->data = null;
	}

	/**
	 * Set the size of the PUT/POST data, measured in bytes
	 *
	 * @param   int  $size
	 */
	public function setSize($size)
	{
		$this->size = (int) $size;
	}

	/**
	 * Set the PUT/POST data.
	 *
	 * @param  string  $data
	 */
	public function setData($data)
	{
		$this->fp = null;
		$this->data = $data;
	}

	/**
	 * Set the PUT/POST data from a variable passed as reference.
	 *
	 * @param  string  $data
	 */
	public function assignData(&$data)
	{
		$this->fp = null;
		$this->data = $data;
	}

	/**
	 * Get the certificate authority location
	 *
	 * @return  string
	 */
	public function getCaCertLocation()
	{
		if (!empty($this->caCertLocation))
		{
			return $this->caCertLocation;
		}

		if (defined('AKEEBA_CACERT_PEM'))
		{
			return AKEEBA_CACERT_PEM;
		}

		return null;
	}

	/**
	 * @param null|string $caCertLocation
	 */
	public function setCaCertLocation($caCertLocation)
	{
		if (empty($caCertLocation))
		{
			$caCertLocation = null;
		}

		if (!is_null($caCertLocation) && !is_file($caCertLocation) && !is_dir($caCertLocation))
		{
			$caCertLocation = null;
		}

		$this->caCertLocation = $caCertLocation;
	}

	/**
	 * Get the S3 response
	 *
	 * @return  Response
	 */
	public function getResponse()
	{
		$query = '';

		if (count($this->parameters))
		{
			$query = substr($this->uri, -1) !== '?' ? '?' : '&';

			ksort($this->parameters);

			foreach ($this->parameters as $var => $value)
			{
				if ($value == null || $value == '')
				{
					$query .= $var . '&';
				}
				else
				{
					// Parameters must be URL-encoded
					$query .= $var . '=' . rawurlencode($value) . '&';
				}
			}

			$query = substr($query, 0, -1);
			$this->uri .= $query;

			if (array_key_exists('acl', $this->parameters) ||
			    array_key_exists('location', $this->parameters) ||
			    array_key_exists('torrent', $this->parameters) ||
			    array_key_exists('logging', $this->parameters) ||
			    array_key_exists('uploads', $this->parameters) ||
			    array_key_exists('uploadId', $this->parameters) ||
			    array_key_exists('partNumber', $this->parameters)
			)
			{
				$this->resource .= $query;
			}
		}

		$schema = 'http://';

		if ($this->configuration->isSSL())
		{
			$schema = 'https://';
		}

		$url = $schema . $this->headers['Host'] . $this->uri;

		// Basic setup
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_USERAGENT, 'AkeebaBackupProfessional/S3PostProcessor');

		if ($this->configuration->isSSL())
		{
			// Set the CA certificate cache location
			$caCert = $this->getCaCertLocation();

			if (!empty($caCert))
			{
				if (is_dir($caCert))
				{
					@curl_setopt($curl, CURLOPT_CAPATH, $caCert);
				}
				else
				{
					@curl_setopt($curl, CURLOPT_CAINFO, $caCert);
				}
			}

			// Verify the host name in the certificate and the certificate itself
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		}

		curl_setopt($curl, CURLOPT_URL, $url);

		// Headers
		$headers = array();

		foreach ($this->amzHeaders as $header => $value)
		{
			if (strlen($value) > 0)
			{
				$headers[] = $header . ': ' . $value;
			}
		}

		foreach ($this->headers as $header => $value)
		{
			if (strlen($value) > 0)
			{
				$headers[] = $header . ': ' . $value;
			}
		}

		$signer = Signature::getSignatureObject($this, $this->configuration->getSignatureMethod());
		$headers[] = 'Authorization: ' . $signer->getAuthorizationHeader();

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($curl, CURLOPT_WRITEFUNCTION, array(&$this, '__responseWriteCallback'));
		curl_setopt($curl, CURLOPT_HEADERFUNCTION, array(&$this, '__responseHeaderCallback'));
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

		// Request types
		switch ($this->verb)
		{
			case 'GET':
				break;

			case 'PUT':
			case 'POST':
				if (!is_null($this->fp) && is_resource($this->fp))
				{
					curl_setopt($curl, CURLOPT_PUT, true);
					curl_setopt($curl, CURLOPT_INFILE, $this->fp);

					if ($this->size >= 0)
					{
						curl_setopt($curl, CURLOPT_INFILESIZE, $this->size);
					}
				}
				elseif (!is_null($this->data))
				{
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
					curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);

					if ($this->size >= 0)
					{
						curl_setopt($curl, CURLOPT_BUFFERSIZE, $this->size);
					}
				}
				else
				{
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
				}
				break;

			case 'HEAD':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
				curl_setopt($curl, CURLOPT_NOBODY, true);
				break;

			case 'DELETE':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;

			default:
				break;
		}

		// Execute, grab errors
		$this->response->resetBody();

		if (curl_exec($curl))
		{
			$this->response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		}
		else
		{
			$this->response->error = new Error(
				curl_errno($curl),
				curl_error($curl),
				$this->resource
			);
		}

		@curl_close($curl);

		// Set the body data
		$this->response->finaliseBody();

		// Clean up file resources
		if (!is_null($this->fp) && is_resource($this->fp))
		{
			fclose($this->fp);
		}

		return $this->response;
	}


	/**
	 * cURL write callback
	 *
	 * @param   resource   &$curl  cURL resource
	 * @param   string     &$data  Data
	 *
	 * @return  int  Length in bytes
	 */
	protected function  __responseWriteCallback(&$curl, &$data)
	{
		if (in_array($this->response->code, array(200, 206)) && !is_null($this->fp) && is_resource($this->fp))
		{
			return fwrite($this->fp, $data);
		}

		$this->response->addToBody($data);

		return strlen($data);
	}

	/**
	 * cURL header callback
	 *
	 * @param   resource  &$curl  cURL resource
	 * @param   string    &$data  Data
	 *
	 * @return  int  Length in bytes
	 */
	protected function  __responseHeaderCallback(&$curl, &$data)
	{
		if (($strlen = strlen($data)) <= 2)
		{
			return $strlen;
		}

		if (substr($data, 0, 4) == 'HTTP')
		{
			$this->response->code = (int)substr($data, 9, 3);

			return $strlen;
		}

		list($header, $value) = explode(': ', trim($data), 2);

		switch ($header)
		{
			case 'Last-Modified':
				$this->response->setHeader('time', strtotime($value));
				break;

			case 'Content-Length':
				$this->response->setHeader('size', (int)$value);
				break;

			case 'Content-Type':
				$this->response->setHeader('type', $value);
				break;

			case 'ETag':
				$this->response->setHeader('hash', $value{0} == '"' ? substr($value, 1, -1) : $value);
				break;

			default:
				if (preg_match('/^x-amz-meta-.*$/', $header))
				{
					$this->setHeader($header, is_numeric($value) ? (int)$value : $value);
				}
				break;
		}

		return $strlen;
	}
}