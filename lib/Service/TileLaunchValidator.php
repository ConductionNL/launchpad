<?php

/**
 * TileLaunchValidator
 *
 * Checks a tile's link type, address and launch settings before anything is
 * written (launcher-tile-launch-types, REQ-TLT-001 to REQ-TLT-003). A tile
 * opens a Nextcloud app, a web address, a program on the user's computer, a
 * remote desktop or a single sign-on app. Script-like schemes are refused
 * for every type, because the browser follows a tile's address as a link.
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

/**
 * Link type validation for tiles.
 *
 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
 */
class TileLaunchValidator {
	/**
	 * Every link type a tile may have.
	 *
	 * @var string[]
	 */
	public const LINK_TYPES = ['app', 'url', 'program', 'remote-desktop', 'sso'];

	/**
	 * A host name or IPv4 address, at most 253 characters.
	 *
	 * @var string
	 */
	private const HOST_PATTERN = '/^(?=.{1,253}$)[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/D';

	/**
	 * A published program: an alias such as `||Belastingen` or a Windows path,
	 * without control characters.
	 *
	 * @var string
	 */
	private const REMOTE_APP_PATTERN = '/^(\|\|)?[A-Za-z0-9 ._\\\\:$()-]{1,260}$/D';

	/**
	 * An app's identifier at the identity provider.
	 *
	 * @var string
	 */
	private const APP_ID_PATTERN = '/^[A-Za-z0-9._:-]{1,128}$/D';

	/**
	 * Constructor.
	 *
	 * @param TileLaunchSettingsService $settings The administrator's launch settings.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function __construct(
		private readonly TileLaunchSettingsService $settings,
	) {
	}//end __construct()

	/**
	 * Check a tile's link type and address. A remote desktop or single
	 * sign-on tile may be created without its settings; they follow in the
	 * tile's content (see assertValidContent()).
	 *
	 * @param string|null $type  The link type; empty means `app`.
	 * @param string|null $value The address or app id.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the type is unknown or the address is not allowed.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function assertValidLink(?string $type, ?string $value): void {
		$type = self::normaliseType(type: $type);
		if (in_array(needle: $type, haystack: self::LINK_TYPES, strict: true) === false) {
			throw new InvalidArgumentException(message: 'Unknown tile link type');
		}

		$scheme = self::schemeOf(value: (string)$value);
		if ($scheme !== null && in_array(needle: $scheme, haystack: TileLaunchSettingsService::FORBIDDEN_SCHEMES, strict: true) === true) {
			throw new InvalidArgumentException(message: 'A tile cannot open this kind of address');
		}

		if ($type === 'program' && ($scheme === null || in_array(needle: $scheme, haystack: $this->settings->getAllowedSchemes(), strict: true) === false)) {
			throw new InvalidArgumentException(message: 'An administrator has not allowed this program address');
		}
	}//end assertValidLink()

	/**
	 * Check a whole tile: its link and, for a remote desktop or single
	 * sign-on tile, the settings in its content.
	 *
	 * @param string|null $type    The link type.
	 * @param string|null $value   The address or app id.
	 * @param array       $content The tile's content.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When anything is not allowed.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function assertValidTile(?string $type, ?string $value, array $content): void {
		$this->assertValidLink(type: $type, value: $value);

		$type = self::normaliseType(type: $type);
		if ($type === 'remote-desktop') {
			$this->assertValidRemote(remote: ($content['remote'] ?? null));
		}

		if ($type === 'sso') {
			$this->assertValidSso(sso: ($content['sso'] ?? null));
		}
	}//end assertValidTile()

	/**
	 * Check a remote desktop: a web gateway (https) or an RDP connection.
	 *
	 * @param mixed $remote The tile's `content.remote`.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the remote desktop is not valid.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function assertValidRemote(mixed $remote): void {
		if (is_array(value: $remote) === false) {
			throw new InvalidArgumentException(message: 'A remote desktop tile needs a gateway address or a connection');
		}

		$mode = ($remote['mode'] ?? null);
		if ($mode === 'gateway') {
			self::assertHttpsAddress(value: ($remote['url'] ?? null));
			return;
		}

		if ($mode !== 'rdp') {
			throw new InvalidArgumentException(message: 'A remote desktop is a gateway address or an RDP connection');
		}

		self::assertPattern(value: ($remote['host'] ?? null), pattern: self::HOST_PATTERN, message: 'The computer name is not valid');
		self::assertOptionalPattern(value: ($remote['gateway'] ?? null), pattern: self::HOST_PATTERN, message: 'The gateway name is not valid');
		self::assertOptionalPattern(value: ($remote['remoteApp'] ?? null), pattern: self::REMOTE_APP_PATTERN, message: 'The program name is not valid');

		$port = ($remote['port'] ?? null);
		if ($port !== null && $port !== '' && (filter_var(value: $port, filter: FILTER_VALIDATE_INT, options: ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false)) {
			throw new InvalidArgumentException(message: 'The port must be a number from 1 to 65535');
		}
	}//end assertValidRemote()

	/**
	 * Check a single sign-on tile: a known template and a plain app id.
	 *
	 * @param mixed $sso The tile's `content.sso`.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the template is unknown or the app id is not valid.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function assertValidSso(mixed $sso): void {
		if (is_array(value: $sso) === false || is_string(value: ($sso['template'] ?? null)) === false) {
			throw new InvalidArgumentException(message: 'A single sign-on tile needs a launch template');
		}

		self::assertPattern(value: ($sso['appId'] ?? null), pattern: self::APP_ID_PATTERN, message: 'The app id is not valid');
		if ($this->settings->resolveSsoUrl(templateKey: $sso['template'], appId: $sso['appId']) === null) {
			throw new InvalidArgumentException(message: 'This launch template does not exist');
		}
	}//end assertValidSso()

	/**
	 * The lower-case scheme of an address as a browser reads it (control
	 * characters and spaces dropped), or null when it has none.
	 *
	 * @param string $value The address.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public static function schemeOf(string $value): ?string {
		$compact = (string)preg_replace(pattern: '/[\x00-\x20\x7F]+/', replacement: '', subject: $value);
		if (preg_match(pattern: '/^([A-Za-z][A-Za-z0-9+.-]*):/', subject: $compact, matches: $match) !== 1) {
			return null;
		}

		return strtolower(string: $match[1]);
	}//end schemeOf()

	/**
	 * An empty type is an app tile, as when it is created without one.
	 *
	 * @param string|null $type The submitted type.
	 *
	 * @return string
	 */
	private static function normaliseType(?string $type): string {
		if ($type === null || $type === '') {
			return 'app';
		}

		return $type;
	}//end normaliseType()

	/**
	 * An https address with a host.
	 *
	 * @param mixed $value The candidate.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When it is not.
	 */
	private static function assertHttpsAddress(mixed $value): void {
		if (is_string(value: $value) === false || strlen(string: $value) > 2048 || preg_match(pattern: '/[\x00-\x20\x7F]/', subject: $value) === 1
			|| self::schemeOf(value: $value) !== 'https' || (string)parse_url(url: $value, component: PHP_URL_HOST) === ''
		) {
			throw new InvalidArgumentException(message: 'The gateway address must be an https address');
		}
	}//end assertHttpsAddress()

	/**
	 * A string matching a pattern.
	 *
	 * @param mixed  $value   The candidate.
	 * @param string $pattern The pattern.
	 * @param string $message The refusal.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When it does not match.
	 */
	private static function assertPattern(mixed $value, string $pattern, string $message): void {
		if (is_string(value: $value) === false || preg_match(pattern: $pattern, subject: $value) !== 1) {
			throw new InvalidArgumentException(message: $message);
		}
	}//end assertPattern()

	/**
	 * Empty, or a string matching a pattern.
	 *
	 * @param mixed  $value   The candidate.
	 * @param string $pattern The pattern.
	 * @param string $message The refusal.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When it is set and does not match.
	 */
	private static function assertOptionalPattern(mixed $value, string $pattern, string $message): void {
		if ($value === null || $value === '') {
			return;
		}

		self::assertPattern(value: $value, pattern: $pattern, message: $message);
	}//end assertOptionalPattern()
}//end class
