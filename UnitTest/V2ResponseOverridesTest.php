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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * v2 pre-signed URLs must sign the response-* overrides, or S3 answers 403 SignatureDoesNotMatch.
 */
#[CoversClass(Connector::class)]
class V2ResponseOverridesTest extends TestCase
{
	public function testResponseOverridesAreSigned(): void
	{
		$config = new Configuration('AKIAEXAMPLE', 'secret', 'v2', 'us-east-1');
		$config->setEndpoint('storage.example.com:9000');
		$config->setUseLegacyPathStyle(true);

		$connector = new Connector($config);
		$query     = http_build_query([
			'response-content-type'        => 'application/octet-stream',
			'response-content-disposition' => 'attachment; filename="a b.jpa"',
		]);
		$url       = $connector->getAuthenticatedURL('my-bucket', 'a b.jpa?' . $query, 10, false);

		parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

		// Sub-resources are sorted by name and their values are not URL-encoded
		$stringToSign = "GET\n\n\n" . $params['Expires'] . "\n/my-bucket/a%20b.jpa"
			. '?response-content-disposition=attachment; filename="a b.jpa"'
			. '&response-content-type=application/octet-stream';
		$expected     = base64_encode(hash_hmac('sha1', $stringToSign, 'secret', true));

		$this->assertSame($expected, $params['Signature']);
	}
}
