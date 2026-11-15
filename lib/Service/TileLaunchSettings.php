<?php

/**
 * TileLaunchSettings
 *
 * The administrator's lists behind the program and single sign-on tile types:
 * the URL schemes a program tile may use, and the identity-provider launch
 * templates a single sign-on tile picks from.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-program-on-the-users-computer-req-tlt-001
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * Validates and normalises the two tile-launch admin settings (design D1, D4).
 *
 * A write is refused whole, naming the offending entry, rather than stored
 * with the bad entry dropped: an administrator who typed a scheme or a
 * template should learn it was not taken. A stored value that no longer
 * validates reads as empty, so nothing unsafe ever reaches a tile.
 *
 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-program-on-the-users-computer-req-tlt-001
 */
class TileLaunchSettings {

	/**
	 * Schemes a program tile never uses, whatever an administrator lists:
	 * they run script, read the local disk or carry inline content.
	 */
	public const FORBIDDEN_SCHEMES = ['javascript', 'data', 'file', 'vbscript', 'about', 'blob'];

	/**
	 * The placeholder a launch template must carry.
	 */
	public const APP_ID_PLACEHOLDER = '{appId}';

	/**
	 * The most entries either list may hold.
	 */
	private const MAX_ENTRIES = 50;

	/**
	 * The allowed schemes: lower-case RFC 3986 scheme names, none forbidden, no duplicates.
	 *
	 * @param mixed $input The submitted list.
	 *
	 * @return list<string>
	 *
	 * @throws InvalidArgumentException Naming the scheme refused.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-program-on-the-users-computer-req-tlt-001
	 */
	public function schemes(mixed $input): array {
		if (is_array($input) === false || count($input) > self::MAX_ENTRIES) {
			throw new InvalidArgumentException(message: 'tileAllowedSchemes must be a list of at most ' . self::MAX_ENTRIES . ' schemes.');
		}

		$schemes = [];
		foreach ($input as $scheme) {
			if (is_string($scheme) === false) {
				$scheme = '';
			}

			$scheme = rtrim(trim($scheme), ':');
			if (preg_match('/^[a-z][a-z0-9+.-]{0,31}$/', $scheme) !== 1) {
				throw new InvalidArgumentException(message: 'The scheme "' . $scheme . '" is not a lower-case URL scheme.');
			}

			if (in_array($scheme, self::FORBIDDEN_SCHEMES, true) === true) {
				throw new InvalidArgumentException(message: 'The scheme "' . $scheme . '" is never allowed on a tile.');
			}

			$schemes[$scheme] = $scheme;
		}

		return array_values($schemes);
	}//end schemes()

	/**
	 * The launch templates: each a unique key, a name, and an https address with `{appId}`.
	 *
	 * @param mixed $input The submitted list of `{key, name, urlTemplate}`.
	 *
	 * @return list<array{key: string, name: string, urlTemplate: string}>
	 *
	 * @throws InvalidArgumentException Naming the template refused.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-single-sign-on-into-a-company-app-req-tlt-003
	 */
	public function templates(mixed $input): array {
		if (is_array($input) === false || count($input) > self::MAX_ENTRIES) {
			throw new InvalidArgumentException(message: 'ssoLaunchTemplates must be a list of at most ' . self::MAX_ENTRIES . ' templates.');
		}

		$templates = [];
		foreach ($input as $entry) {
			$template = $this->template(entry: $entry);
			if (isset($templates[$template['key']]) === true) {
				throw new InvalidArgumentException(message: 'The launch template key "' . $template['key'] . '" is used twice.');
			}

			$templates[$template['key']] = $template;
		}

		return array_values($templates);
	}//end templates()

	/**
	 * A stored value read back: the validated list, or empty when it no longer validates.
	 *
	 * @param string $setting `schemes` or `templates`.
	 * @param mixed  $stored  The stored value.
	 *
	 * @return list<mixed>
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-program-on-the-users-computer-req-tlt-001
	 */
	public function stored(string $setting, mixed $stored): array {
		if ($stored === null) {
			return [];
		}

		try {
			if ($setting === 'schemes') {
				return $this->schemes(input: $stored);
			}

			return $this->templates(input: $stored);
		} catch (InvalidArgumentException $e) {
			return [];
		}
	}//end stored()

	/**
	 * One launch template, checked.
	 *
	 * @param mixed $entry The submitted template.
	 *
	 * @return array{key: string, name: string, urlTemplate: string}
	 *
	 * @throws InvalidArgumentException Naming what is wrong.
	 */
	private function template(mixed $entry): array {
		if (is_array($entry) === false) {
			throw new InvalidArgumentException(message: 'A launch template must have a key, a name and an address.');
		}

		$key = trim((string)($entry['key'] ?? ''));
		$name = trim((string)($entry['name'] ?? ''));
		$url = trim((string)($entry['urlTemplate'] ?? ''));
		if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $key) !== 1) {
			throw new InvalidArgumentException(message: 'The launch template key "' . $key . '" must be lower-case letters, digits and hyphens.');
		}

		if ($name === '' || mb_strlen($name) > 100) {
			throw new InvalidArgumentException(message: 'The launch template "' . $key . '" needs a name of at most 100 characters.');
		}

		if ($this->isLaunchAddress(url: $url) === false) {
			throw new InvalidArgumentException(
				message: 'The launch template "' . $key . '" must be an https address containing ' . self::APP_ID_PLACEHOLDER . ' once.'
			);
		}

		return ['key' => $key, 'name' => $name, 'urlTemplate' => $url];
	}//end template()

	/**
	 * Whether an address is https, names a host, carries `{appId}` exactly once and no other placeholder.
	 *
	 * @param string $url The template address.
	 *
	 * @return bool
	 */
	private function isLaunchAddress(string $url): bool {
		if (substr_count($url, self::APP_ID_PLACEHOLDER) !== 1 || strlen($url) > 2048) {
			return false;
		}

		$resolved = str_replace(self::APP_ID_PLACEHOLDER, 'app', $url);
		if (str_contains($resolved, '{') === true || str_contains($resolved, '}') === true) {
			return false;
		}

		$parts = parse_url($resolved);
		if (is_array($parts) === false || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
			return false;
		}

		$host = (string)($parts['host'] ?? '');
		return $host !== '' && isset($parts['user']) === false && isset($parts['pass']) === false
			&& filter_var('https://' . $host, FILTER_VALIDATE_URL) !== false;
	}//end isLaunchAddress()
}//end class
