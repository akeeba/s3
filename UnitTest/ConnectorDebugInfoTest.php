<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\UnitTest;

use Akeeba\S3\Configuration;
use Akeeba\S3\Connector;
use Akeeba\S3\Input;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The "Debug info" dump of the S3 error body in exception messages.
 *
 * Applications show exception messages to people. The dump is only added when the configuration asks for
 * it, and never contains the signed request Amazon echoes back on a SignatureDoesNotMatch error.
 */
#[CoversClass(Connector::class)]
class ConnectorDebugInfoTest extends TestCase
{
	/**
	 * What Amazon S3 answers to a request signed with the wrong secret key.
	 */
	private const SIGNATURE_ERROR = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Error><Code>SignatureDoesNotMatch</Code><Message>The request signature we calculated does not match the signature you provided. Check your key and signing method.</Message><AWSAccessKeyId>AKIAEXAMPLE</AWSAccessKeyId><StringToSign>AWS4-HMAC-SHA256 STRING-TO-SIGN-SECRET</StringToSign><SignatureProvided>SIGNATURE-PROVIDED-SECRET</SignatureProvided><StringToSignBytes>41 57 53 34</StringToSignBytes><CanonicalRequest>PUT /key x-amz-security-token:SESSION-TOKEN-SECRET</CanonicalRequest><CanonicalRequestBytes>50 55 54</CanonicalRequestBytes><RequestId>REQ123</RequestId><HostId>HOST456</HostId></Error>
XML;

	private const SIGNED_REQUEST_PARTS = [
		'StringToSign', 'STRING-TO-SIGN-SECRET', 'SignatureProvided', 'SIGNATURE-PROVIDED-SECRET',
		'CanonicalRequest', 'SESSION-TOKEN-SECRET', '41 57 53 34', '50 55 54',
	];

	protected function tearDown(): void
	{
		CurlRecorder::stop();

		parent::tearDown();
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provideOperations(): array
	{
		// getObject reports any non-200 answer by its HTTP status, not the S3 error code
		return [
			'putObject'      => ['putObject', 'SignatureDoesNotMatch'],
			'getObject'      => ['getObject', '[403]'],
			'startMultipart' => ['startMultipart', 'SignatureDoesNotMatch'],
		];
	}

	#[DataProvider('provideOperations')]
	public function testTheErrorBodyIsNotDumpedByDefault(string $operation, string $error): void
	{
		$message = $this->failingCall($operation, false);

		$this->assertStringContainsString($error, $message, 'The error is still identified.');
		$this->assertStringNotContainsString('Debug info', $message);
		$this->assertStringNotContainsString('REQ123', $message);

		foreach (self::SIGNED_REQUEST_PARTS as $part)
		{
			$this->assertStringNotContainsString($part, $message);
		}
	}

	#[DataProvider('provideOperations')]
	public function testTheDebugDumpNeverContainsTheSignedRequest(string $operation, string $error): void
	{
		$message = $this->failingCall($operation, true);

		$this->assertStringContainsString($error, $message);
		$this->assertStringContainsString('Debug info', $message);
		$this->assertStringContainsString('REQ123', $message, 'The rest of the error body is dumped.');
		$this->assertStringContainsString('AKIAEXAMPLE', $message);

		foreach (self::SIGNED_REQUEST_PARTS as $part)
		{
			$this->assertStringNotContainsString($part, $message);
		}
	}

	public function testDebugIsOffByDefault(): void
	{
		$this->assertFalse((new Configuration('AKIAEXAMPLE', 'secret'))->getDebug());
	}

	private function failingCall(string $operation, bool $debug): string
	{
		$config = new Configuration('AKIAEXAMPLE', 'secret', 'v4', 'us-east-1');
		$config->setDebug($debug);

		$connector = new Connector($config);
		$data      = 'data';
		$input     = Input::createFromData($data);

		CurlRecorder::start();
		CurlRecorder::respond(403, ['Content-Type' => 'application/xml'], self::SIGNATURE_ERROR);

		try
		{
			switch ($operation)
			{
				case 'putObject':
					$connector->putObject($input, 'mybucket', 'key.txt');
					break;

				case 'getObject':
					$connector->getObject('mybucket', 'key.txt');
					break;

				case 'startMultipart':
					$connector->startMultipart($input, 'mybucket', 'key.txt');
					break;
			}
		}
		catch (\RuntimeException $e)
		{
			return $e->getMessage();
		}
		finally
		{
			CurlRecorder::stop();
		}

		$this->fail("$operation did not fail.");
	}
}
