<?php
/**
 * Akeeba Engine
 * The modular PHP5 site backup engine
 *
 * @copyright Copyright (c)2006-2015 Nicholas K. Dionysopoulos
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   akeebaengine
 */

namespace Akeeba\Engine\Postproc\Connector\S3v4\Signature;

// Protection against direct access
defined('AKEEBAENGINE') or die();

use Akeeba\Engine\Postproc\Connector\S3v4\Signature;

/**
 * Implements the Amazon AWS v4 signatures
 *
 * @see http://docs.aws.amazon.com/general/latest/gr/signature-version-4.html
 */
class V4 extends Signature
{
	/**
	 * Returns the authorization header for the request
	 *
	 * @return  string
	 */
	public function getAuthorizationHeader()
	{
		$verb           = strtoupper($this->request->getVerb());
		$resourcePath   = $this->request->getResource();
		$headers        = $this->request->getHeaders();
		$amzHeaders     = $this->request->getAmzHeaders();
		$parameters     = $this->request->getParameters();
		$isPresignedURL = false;

		// If the Expires query string parameter is set up we're pre-signing a download URL. The string to sign is a bit
		// different in this case; it does not include the Date, it includes the Expires.
		// See http://docs.aws.amazon.com/AmazonS3/latest/dev/RESTAuthentication.html#RESTAuthenticationQueryStringAuth
		if (isset($parameters['Expires']) && ($verb == 'GET'))
		{
			$headers['Date'] = $parameters['Expires'];
			$isPresignedURL  = true;
		}

		// ========== Step 1: Create a canonical request ==========
		// See http://docs.aws.amazon.com/general/latest/gr/sigv4-create-canonical-request.html

		$canonicalHeaders = "";
		$signedHeadersArray = array();

		// The canonical URI is the resource path
		$canonicalURI = $resourcePath;

		// If the resource path has a query yank it and parse it into the parameters array
		$questionMarkPos = strpos($canonicalURI, '?');

		if ($questionMarkPos !== false)
		{
			$canonicalURI = substr($canonicalURI, 0, $questionMarkPos);
			$queryString = @substr($canonicalURI, $questionMarkPos + 1);
			@parse_str($queryString, $extraQuery);

			if (count($extraQuery))
			{
				$parameters = array_merge($parameters, $extraQuery);
			}
		}

		// The canonical query string is the string representation of $parameters, alpha sorted by key
		ksort($parameters);
		$canonicalQueryString = http_build_query($parameters, null, null, PHP_QUERY_RFC3986);

		// Calculate the canonical headers and the signed headers
		$allHeaders = array_merge($headers, $amzHeaders);
		ksort($allHeaders);

		foreach ($allHeaders as $k => $v)
		{
			$lowercaseHeaderName = strtolower($k);
			$canonicalHeaders .= $lowercaseHeaderName . ':' . trim($v) . "\n";
			$signedHeadersArray[] = $lowercaseHeaderName;
		}

		$signedHeaders = implode(';', $signedHeadersArray);

		// Get the payload hash
		$requestPayloadHash = $this->request->getInput()->getSha256();

		// Calculate the canonical request
		$canonicalRequest = $verb . "\n" .
			$canonicalURI . "\n" .
			$canonicalQueryString . "\n" .
			$canonicalHeaders . "\n" .
			$signedHeaders . "\n" .
			$requestPayloadHash;

		$hashedCanonicalRequest = hash('sha256', $canonicalRequest);

		// ========== Step 2: Create a string to sign ==========
		// See http://docs.aws.amazon.com/general/latest/gr/sigv4-create-string-to-sign.html

		$signatureDate = new \DateTime($headers['Date']);

		$credentialScope = $signatureDate->format('Ymd') . '/' .
			$this->request->getConfiguration()->getRegion() . '/' .
			's3/aws4_request';

		$stringToSign = "AWS4-HMAC-SHA256\n" .
			$headers['Date'] . "\n" .
			$credentialScope . "\n" .
			$hashedCanonicalRequest;

		// ========== Step 3: Calculate the signature ==========
		// See http://docs.aws.amazon.com/general/latest/gr/sigv4-calculate-signature.html
		$kSigning = $this->getSigningKey($signatureDate);

		$signature = hash_hmac('sha256', $stringToSign, $kSigning, false);

		// ========== Step 4: Add the signing information to the Request ==========
		// See http://docs.aws.amazon.com/general/latest/gr/sigv4-add-signature-to-request.html

		// For presigned URLs we only return the Base64-encoded signature without the AWS format specifier and the
		// public access key.
		if ($isPresignedURL)
		{
			// TODO See http://docs.aws.amazon.com/general/latest/gr/sigv4-create-string-to-sign.html
		}

		$authorization = 'AWS4-HMAC-SHA256 Credential=' .
			$this->request->getConfiguration()->getAccess() . '/' . $credentialScope . ', ' .
			'SignedHeaders=' . $signedHeaders . ', ' .
			'Signature=' . $signature;

		return $authorization;
	}

	/**
	 * Calculate the AWS4 signing key
	 *
	 * @param   \DateTime  $signatureDate  The date the signing key is good for
	 *
	 * @return  string
	 */
	private function getSigningKey(\DateTime $signatureDate)
	{
		$kSecret  = $this->request->getConfiguration()->getSecret();
		$kDate    = hash_hmac('sha256', $signatureDate->format('Ymd'), 'AWS4' . $kSecret, true);
		$kRegion  = hash_hmac('sha256', $this->request->getConfiguration()->getRegion(), $kDate, true);
		$kService = hash_hmac('sha256', 's3', $kRegion, true);
		$kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

		return $kSigning;
	}
}