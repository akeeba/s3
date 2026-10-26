<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\IntegrationTest;

/**
 * Reads the S3 test environments from `.env.test` (and the process environment) in the repository root.
 *
 * An environment is a group of variables sharing a prefix, e.g. `C2_ACCESS_KEY`, `C2_SECRET_KEY`, `C2_BUCKET`… The
 * prefix is whatever precedes `_ACCESS_KEY`; every such variable defines an environment. Real environment variables
 * override the file, so CI can inject credentials without writing a file.
 */
final class Environment
{
	/** Name of the variable telling the test cases which environment to run against. */
	public const SELECTOR = 'S3_TEST_ENV';

	/** Settings and their defaults. A null default means the setting is required. */
	private const SETTINGS = [
		'ACCESS_KEY'               => null,
		'SECRET_KEY'               => null,
		'BUCKET'                   => null,
		'REGION'                   => '',
		'SIGNATURE'                => 'v4',
		'ENDPOINT'                 => '',
		'DUALSTACK'                => 'false',
		'PATH_ACCESS'              => 'false',
		'SSL'                      => 'true',
		'VIRTUAL_HOSTS'            => 'true',
		'ALTERNATE_DATE_FORMAT'    => 'false',
		'USE_HTTP_DATE_HEADER'     => 'false',
		'PRE_SIGNED_BUCKET_IN_URL' => 'false',
		// full | no-delete | write-only. See IntegrationTestCase::REQUIRED_PERMISSIONS.
		'PERMISSIONS'              => 'full',
		'CREATE_2100_FILES'        => 'true',
		// all, or a comma-separated list of the storage classes the service accepts, e.g. REDUCED_REDUNDANCY
		'STORAGE_CLASSES'          => 'all',
		'SKIP'                     => 'false',
		// true if the environment is the Docker Compose stack of IntegrationTest/docker; the runner starts it when needed
		'CONTAINER'                => 'false',
	];

	/** @var array<string,string>|null */
	private static $variables;

	public static function filePath(): string
	{
		return dirname(__DIR__) . '/.env.test';
	}

	/**
	 * Names (prefixes) of all defined environments which are not marked as skipped.
	 *
	 * @return  string[]
	 */
	public static function discover(): array
	{
		$names = [];

		foreach (array_keys(self::variables()) as $key)
		{
			// Requiring _BUCKET too keeps unrelated variables such as AWS_ACCESS_KEY from looking like environments
			if (preg_match('/^([A-Z0-9]+(?:_[A-Z0-9]+)*)_ACCESS_KEY$/', $key, $matches)
				&& isset(self::variables()[$matches[1] . '_BUCKET']))
			{
				$names[] = $matches[1];
			}
		}

		sort($names);

		return array_values(
			array_filter($names, function (string $name): bool {
				return !self::toBool(self::variables()[$name . '_SKIP'] ?? 'false');
			})
		);
	}

	/**
	 * The environment this process should run against: the one named by S3_TEST_ENV, or the only/`DEFAULT` one.
	 */
	public static function selected(): ?string
	{
		$requested = self::variables()[self::SELECTOR] ?? '';

		if ($requested !== '')
		{
			return strtoupper($requested);
		}

		$names = self::discover();

		if (count($names) === 1)
		{
			return $names[0];
		}

		return in_array('DEFAULT', $names, true) ? 'DEFAULT' : null;
	}

	/**
	 * Returns the options of an environment, as the shape the tests and IntegrationTestCase::createConnector() expect.
	 *
	 * @return  array<string,mixed>
	 * @throws  \RuntimeException  When a required setting is missing
	 */
	public static function options(string $name): array
	{
		$raw = [];

		foreach (self::SETTINGS as $setting => $default)
		{
			$value = self::variables()[$name . '_' . $setting] ?? $default;

			if ($value === null || ($value === '' && $default === null))
			{
				throw new \RuntimeException(sprintf('Environment %s: %s_%s is not set in .env.test', $name, $name, $setting));
			}

			$raw[$setting] = $value;
		}

		return [
			'name'                      => $name,
			'access'                    => $raw['ACCESS_KEY'],
			'secret'                    => $raw['SECRET_KEY'],
			'bucket'                    => $raw['BUCKET'],
			'region'                    => $raw['REGION'],
			'signature'                 => $raw['SIGNATURE'],
			'endpoint'                  => $raw['ENDPOINT'] === '' ? null : $raw['ENDPOINT'],
			'dualstack'                 => self::toBool($raw['DUALSTACK']),
			'path_access'               => self::toBool($raw['PATH_ACCESS']),
			'ssl'                       => self::toBool($raw['SSL']),
			'virtualHosts'              => self::toBool($raw['VIRTUAL_HOSTS']),
			'alternateDateHeaderFormat' => self::toBool($raw['ALTERNATE_DATE_FORMAT']),
			'useHTTPDateHeader'         => self::toBool($raw['USE_HTTP_DATE_HEADER']),
			'preSignedBucketInURL'      => self::toBool($raw['PRE_SIGNED_BUCKET_IN_URL']),
			'permissions'               => $raw['PERMISSIONS'],
			'create2100Files'           => self::toBool($raw['CREATE_2100_FILES']),
			'storageClasses'            => strtolower(trim($raw['STORAGE_CLASSES'])) === 'all'
				? null
				: array_values(array_filter(array_map('trim', explode(',', strtoupper($raw['STORAGE_CLASSES']))))),
			'container'                 => self::toBool($raw['CONTAINER']),
		];
	}

	/**
	 * A raw variable from .env.test or the process environment, or null.
	 */
	public static function get(string $key): ?string
	{
		return self::variables()[$key] ?? null;
	}

	/**
	 * Forget the cached variables (the .env.test file is read once per process).
	 */
	public static function reset(): void
	{
		self::$variables = null;
	}

	/**
	 * Parse the contents of a dotenv file: KEY=VALUE lines, `#` comments, optional `export `, optional quotes.
	 *
	 * @return  array<string,string>
	 */
	public static function parse(string $contents): array
	{
		$ret = [];

		foreach (preg_split('/\R/', $contents) as $line)
		{
			$line = trim($line);

			if ($line === '' || $line[0] === '#')
			{
				continue;
			}

			if (strpos($line, 'export ') === 0)
			{
				$line = ltrim(substr($line, 7));
			}

			$pos = strpos($line, '=');

			if ($pos === false)
			{
				continue;
			}

			$key   = trim(substr($line, 0, $pos));
			$value = trim(substr($line, $pos + 1));

			if (preg_match('/^"(.*)"$/', $value, $m) || preg_match("/^'(.*)'$/", $value, $m))
			{
				$value = $m[1];
			}
			else
			{
				// Unquoted: strip a trailing comment
				$value = trim(preg_replace('/\s+#.*$/', '', $value));
			}

			$ret[$key] = $value;
		}

		return $ret;
	}

	private static function variables(): array
	{
		if (self::$variables !== null)
		{
			return self::$variables;
		}

		$fromFile = is_readable(self::filePath()) ? self::parse((string) file_get_contents(self::filePath())) : [];
		$fromEnv  = array_filter(getenv(), 'is_string');

		self::$variables = array_merge($fromFile, $fromEnv);

		return self::$variables;
	}

	private static function toBool(string $value): bool
	{
		return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
	}
}
