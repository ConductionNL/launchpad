<?php

/**
 * TileLaunchSettingsService
 *
 * The administrator's settings for tiles that start something outside the
 * browser (launcher-tile-launch-types): the address schemes a program tile
 * may use (REQ-TLT-001) and the identity-provider launch templates a single
 * sign-on tile picks from (REQ-TLT-003). The scheme list is empty until an
 * administrator fills it, and script-like schemes are never allowed.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;

/**
 * Allowed program schemes and single sign-on launch templates.
 *
 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
 */
class TileLaunchSettingsService {
	/**
	 * Schemes no tile may ever use, whatever an administrator lists.
	 *
	 * @var string[]
	 */
	public const FORBIDDEN_SCHEMES = ['javascript', 'data', 'file', 'vbscript', 'about', 'blob'];

	/**
	 * Most schemes an administrator may list.
	 *
	 * @var int
	 */
	public const MAX_SCHEMES = 50;

	/**
	 * Most launch templates an administrator may list.
	 *
	 * @var int
	 */
	public const MAX_TEMPLATES = 20;

	/**
	 * The placeholder a template replaces with the tile's app id.
	 *
	 * @var string
	 */
	public const APP_ID_PLACEHOLDER = '{appId}';

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settingMapper Admin settings store.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function __construct(
		private readonly AdminSettingMapper $settingMapper,
	) {
	}//end __construct()

	/**
	 * The schemes program tiles may use; a stored entry that is forbidden or
	 * malformed is left out.
	 *
	 * @return string[]
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function getAllowedSchemes(): array {
		$raw = $this->settingMapper->getValue(key: AdminSettingKey::TILE_ALLOWED_SCHEMES->value, default: []);
		if (is_array(value: $raw) === false) {
			return [];
		}

		$schemes = [];
		foreach ($raw as $scheme) {
			if (is_string(value: $scheme) === true && self::isAllowableScheme(scheme: $scheme) === true) {
				$schemes[] = $scheme;
			}
		}

		return $schemes;
	}//end getAllowedSchemes()

	/**
	 * Validate and store the allowed schemes.
	 *
	 * @param array $raw The schemes, any case, with or without a colon.
	 *
	 * @return string[] The stored schemes.
	 *
	 * @throws InvalidArgumentException When a scheme is forbidden or malformed.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function saveAllowedSchemes(array $raw): array {
		return $this->saveAll(schemes: $raw, templates: null)['allowedSchemes'];
	}//end saveAllowedSchemes()

	/**
	 * The launch templates, each `{key, name, urlTemplate}`.
	 *
	 * @return array<int, array{key: string, name: string, urlTemplate: string}>
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function getSsoTemplates(): array {
		$raw = $this->settingMapper->getValue(key: AdminSettingKey::SSO_LAUNCH_TEMPLATES->value, default: []);
		if (is_array(value: $raw) === false) {
			return [];
		}

		$templates = [];
		foreach ($raw as $template) {
			if (is_array(value: $template) === true
				&& is_string(value: ($template['key'] ?? null)) === true
				&& is_string(value: ($template['name'] ?? null)) === true
				&& self::isValidUrlTemplate(value: ($template['urlTemplate'] ?? null)) === true
			) {
				$templates[] = ['key' => $template['key'], 'name' => $template['name'], 'urlTemplate' => $template['urlTemplate']];
			}
		}

		return $templates;
	}//end getSsoTemplates()

	/**
	 * Validate and store the launch templates.
	 *
	 * @param array $raw The templates, each `{name, urlTemplate, key?}`.
	 *
	 * @return array<int, array{key: string, name: string, urlTemplate: string}> The stored templates.
	 *
	 * @throws InvalidArgumentException When a template is invalid.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function saveSsoTemplates(array $raw): array {
		return $this->saveAll(schemes: null, templates: $raw)['ssoTemplates'];
	}//end saveSsoTemplates()

	/**
	 * Validate both lists first, then store the ones given, so a refused
	 * template leaves the schemes as they were. Null leaves a list unchanged.
	 *
	 * @param array|null $schemes   The allowed schemes, or null.
	 * @param array|null $templates The launch templates, or null.
	 *
	 * @return array{allowedSchemes: string[], ssoTemplates: array<int, array{key: string, name: string, urlTemplate: string}>}
	 *
	 * @throws InvalidArgumentException When an entry is invalid.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function saveAll(?array $schemes, ?array $templates): array {
		$cleanSchemes = null;
		if ($schemes !== null) {
			$cleanSchemes = self::normaliseSchemes(raw: $schemes);
		}

		$cleanTemplates = null;
		if ($templates !== null) {
			$cleanTemplates = self::normaliseTemplates(raw: $templates);
		}

		if ($cleanSchemes !== null) {
			$this->settingMapper->setSetting(key: AdminSettingKey::TILE_ALLOWED_SCHEMES->value, value: $cleanSchemes);
		}

		if ($cleanTemplates !== null) {
			$this->settingMapper->setSetting(key: AdminSettingKey::SSO_LAUNCH_TEMPLATES->value, value: $cleanTemplates);
		}

		return [
			'allowedSchemes' => ($cleanSchemes ?? $this->getAllowedSchemes()),
			'ssoTemplates' => ($cleanTemplates ?? $this->getSsoTemplates()),
		];
	}//end saveAll()

	/**
	 * The address a single sign-on tile opens: the template with the
	 * URL-encoded app id, or null when the template does not exist.
	 *
	 * @param string $templateKey The template's key.
	 * @param string $appId       The app's identifier at the identity provider.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function resolveSsoUrl(string $templateKey, string $appId): ?string {
		foreach ($this->getSsoTemplates() as $template) {
			if ($template['key'] === $templateKey) {
				return str_replace(search: self::APP_ID_PLACEHOLDER, replace: rawurlencode(string: $appId), subject: $template['urlTemplate']);
			}
		}

		return null;
	}//end resolveSsoUrl()

	/**
	 * Whether a scheme is well formed and not forbidden.
	 *
	 * @param string $scheme The lower-case scheme without colon.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public static function isAllowableScheme(string $scheme): bool {
		return preg_match(pattern: '/^[a-z][a-z0-9+.-]{0,31}$/D', subject: $scheme) === 1
			&& in_array(needle: $scheme, haystack: self::FORBIDDEN_SCHEMES, strict: true) === false;
	}//end isAllowableScheme()

	/**
	 * Clean and check a scheme list.
	 *
	 * @param array $raw The submitted schemes.
	 *
	 * @return string[]
	 *
	 * @throws InvalidArgumentException When a scheme is forbidden or malformed.
	 */
	private static function normaliseSchemes(array $raw): array {
		$schemes = [];
		foreach ($raw as $scheme) {
			$scheme = rtrim(string: strtolower(string: trim(string: (string)$scheme)), characters: ':');
			if ($scheme === '') {
				continue;
			}

			if (self::isAllowableScheme(scheme: $scheme) === false) {
				throw new InvalidArgumentException(message: 'This scheme cannot be allowed: ' . mb_substr(string: $scheme, start: 0, length: 32));
			}

			$schemes[$scheme] = $scheme;
		}

		if (count(value: $schemes) > self::MAX_SCHEMES) {
			throw new InvalidArgumentException(message: 'At most ' . self::MAX_SCHEMES . ' schemes');
		}

		return array_values(array: $schemes);
	}//end normaliseSchemes()

	/**
	 * Clean and check a template list; a template without a key gets one
	 * from its name.
	 *
	 * @param array $raw The submitted templates.
	 *
	 * @return array<int, array{key: string, name: string, urlTemplate: string}>
	 *
	 * @throws InvalidArgumentException When a template is invalid or a key repeats.
	 */
	private static function normaliseTemplates(array $raw): array {
		if (count(value: $raw) > self::MAX_TEMPLATES) {
			throw new InvalidArgumentException(message: 'At most ' . self::MAX_TEMPLATES . ' launch templates');
		}

		$templates = [];
		foreach ($raw as $template) {
			if (is_array(value: $template) === false) {
				throw new InvalidArgumentException(message: 'A launch template needs a name and an address');
			}

			$name = trim(string: (string)($template['name'] ?? ''));
			$url  = trim(string: (string)($template['urlTemplate'] ?? ''));
			$key  = self::slug(value: (string)($template['key'] ?? $name));
			if ($name === '' || $key === '' || mb_strlen(string: $name) > 100) {
				throw new InvalidArgumentException(message: 'A launch template needs a name of at most 100 characters');
			}

			if (self::isValidUrlTemplate(value: $url) === false) {
				throw new InvalidArgumentException(message: 'A launch template address must be an https address with {appId} after the host');
			}

			if (isset($templates[$key]) === true) {
				throw new InvalidArgumentException(message: 'Two launch templates have the same name: ' . $name);
			}

			$templates[$key] = ['key' => $key, 'name' => $name, 'urlTemplate' => $url];
		}//end foreach

		return array_values(array: $templates);
	}//end normaliseTemplates()

	/**
	 * An https address with a host, holding `{appId}` after the host.
	 *
	 * @param mixed $value The candidate.
	 *
	 * @return bool
	 */
	private static function isValidUrlTemplate(mixed $value): bool {
		if (is_string(value: $value) === false || strlen(string: $value) > 2048 || str_contains(haystack: $value, needle: self::APP_ID_PLACEHOLDER) === false) {
			return false;
		}

		$probe  = str_replace(search: self::APP_ID_PLACEHOLDER, replace: 'appid', subject: $value);
		$scheme = strtolower(string: (string)parse_url(url: $probe, component: PHP_URL_SCHEME));
		$host   = (string)parse_url(url: $value, component: PHP_URL_HOST);

		return $scheme === 'https' && $host !== '' && str_contains(haystack: $host, needle: '{') === false
			&& preg_match(pattern: '/[\x00-\x20\x7F]/', subject: $value) === 0;
	}//end isValidUrlTemplate()

	/**
	 * A lower-case key from a name: letters and digits joined by dashes.
	 *
	 * @param string $value The name or key.
	 *
	 * @return string
	 */
	private static function slug(string $value): string {
		$slug = strtolower(string: (string)preg_replace(pattern: '/[^A-Za-z0-9]+/', replacement: '-', subject: $value));

		return substr(string: trim(string: $slug, characters: '-'), offset: 0, length: 64);
	}//end slug()
}//end class
