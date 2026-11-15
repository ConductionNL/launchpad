<?php

/**
 * TileLinkValidator
 *
 * Checks the three tile link types that start something other than a web
 * page: a program on the user's computer (`program`), a remote desktop or a
 * published program (`remote-desktop`) and a single sign-on app (`sso`).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * Validates a launch tile's address and content on the server (design D1).
 *
 * Every check returns the link value to store, so a tile never keeps an
 * address the server did not produce or accept. A refusal throws, which the
 * placement API answers with HTTP 400 before anything is saved.
 *
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-2
 */
class TileLinkValidator {

	public const TYPE_PROGRAM = 'program';
	public const TYPE_REMOTE = 'remote-desktop';
	public const TYPE_SSO = 'sso';

	/**
	 * The link types this validator owns.
	 */
	public const LAUNCH_TYPES = [self::TYPE_PROGRAM, self::TYPE_REMOTE, self::TYPE_SSO];

	/**
	 * The port an RDP connection uses when the tile names none.
	 */
	public const DEFAULT_RDP_PORT = 3389;

	/**
	 * A DNS host name: labels of letters, digits and inner hyphens.
	 */
	private const HOST_PATTERN = '/^(?=.{1,253}$)[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/';

	/**
	 * A published program (RemoteApp) name such as `||Belastingen`.
	 */
	private const REMOTE_APP_PATTERN = '/^(?:\|\|)?[A-Za-z0-9][A-Za-z0-9 ._()-]{0,127}$/';

	/**
	 * An identity provider's identifier of an app.
	 */
	private const APP_ID_PATTERN = '/^[A-Za-z0-9._:-]{1,128}$/';

	/**
	 * The longest address a tile keeps.
	 */
	private const MAX_ADDRESS = 2048;

	/**
	 * Constructor.
	 *
	 * @param AdminSettingsService $settings Holds the allowed schemes and the launch templates.
	 */
	public function __construct(
		private readonly AdminSettingsService $settings,
	) {
	}//end __construct()

	/**
	 * Whether a link type is one this validator owns.
	 *
	 * @param string|null $type The tile's link type.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-2
	 */
	public static function isLaunchType(?string $type): bool {
		return in_array($type, self::LAUNCH_TYPES, true);
	}//end isLaunchType()

	/**
	 * Check a launch tile and return the link value to store.
	 *
	 * @param string               $type    The link type, one of LAUNCH_TYPES.
	 * @param string               $value   The address the client sent.
	 * @param array<string, mixed> $content The placement's content (`remote`, `sso`).
	 *
	 * @return string The link value to store.
	 *
	 * @throws InvalidArgumentException When the tile may not be saved like this.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-2
	 */
	public function check(string $type, string $value, array $content): string {
		return match ($type) {
			self::TYPE_PROGRAM => $this->program(address: $value),
			self::TYPE_REMOTE => $this->remote(content: $content)['url'],
			self::TYPE_SSO => $this->sso(content: $content),
			default => throw new InvalidArgumentException(message: 'The link type "' . $type . '" is not a launch type.'),
		};
	}//end check()

	/**
	 * The checked RDP connection of a remote-desktop tile in RDP mode.
	 *
	 * @param array<string, mixed> $content The placement's content.
	 *
	 * @return array{host: string, port: int, remoteApp: string, gateway: string}
	 *
	 * @throws InvalidArgumentException When the tile holds no valid RDP connection.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-remote-desktop-or-a-published-program-req-tlt-002
	 */
	public function rdpConnection(array $content): array {
		$remote = $this->remote(content: $content);
		if ($remote['mode'] !== 'rdp') {
			throw new InvalidArgumentException(message: 'This remote desktop tile opens a web gateway, not an RDP connection.');
		}

		return [
			'host' => $remote['host'],
			'port' => $remote['port'],
			'remoteApp' => $remote['remoteApp'],
			'gateway' => $remote['gateway'],
		];
	}//end rdpConnection()

	/**
	 * A program address: a scheme the administrator allowed, never a forbidden one.
	 *
	 * @param string $address The address.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When the scheme is not allowed.
	 */
	private function program(string $address): string {
		$address = trim($address);
		if ($address === '' || strlen($address) > self::MAX_ADDRESS || preg_match('/[\x00-\x20\x7f]/', $address) === 1
			|| preg_match('/^([A-Za-z][A-Za-z0-9+.-]{0,31}):/', $address, $match) !== 1
		) {
			throw new InvalidArgumentException(message: 'A program tile needs an address that starts with a scheme, such as ms-word:.');
		}

		$scheme = strtolower($match[1]);
		if (in_array($scheme, TileLaunchSettings::FORBIDDEN_SCHEMES, true) === true) {
			throw new InvalidArgumentException(message: 'The scheme "' . $scheme . '" is never allowed on a tile.');
		}

		if (in_array($scheme, $this->allowedSchemes(), true) === false) {
			throw new InvalidArgumentException(message: 'The scheme "' . $scheme . '" is not on the allowed list.');
		}

		return $address;
	}//end program()

	/**
	 * The schemes the administrator allowed.
	 *
	 * @return array<int, string>
	 */
	private function allowedSchemes(): array {
		return (array)($this->settings->getSettings()['tileAllowedSchemes'] ?? []);
	}//end allowedSchemes()

	/**
	 * The checked `content.remote` of a remote-desktop tile.
	 *
	 * @param array<string, mixed> $content The placement's content.
	 *
	 * @return array{mode: string, url: string, host: string, port: int, remoteApp: string, gateway: string}
	 *
	 * @throws InvalidArgumentException When the remote settings are not valid.
	 */
	private function remote(array $content): array {
		$remote = ($content['remote'] ?? null);
		if (is_array($remote) === false) {
			throw new InvalidArgumentException(message: 'A remote desktop tile needs a gateway address or an RDP connection.');
		}

		$checked = ['mode' => '', 'url' => '', 'host' => '', 'port' => self::DEFAULT_RDP_PORT, 'remoteApp' => '', 'gateway' => ''];
		if (($remote['mode'] ?? null) === 'gateway') {
			$checked['mode'] = 'gateway';
			$checked['url'] = $this->httpsAddress(url: trim((string)($remote['url'] ?? '')));
			return $checked;
		}

		if (($remote['mode'] ?? null) !== 'rdp') {
			throw new InvalidArgumentException(message: 'A remote desktop tile opens a gateway or an RDP connection.');
		}

		$checked['mode'] = 'rdp';
		$checked['host'] = $this->host(host: ($remote['host'] ?? ''), what: 'host');
		$checked['port'] = $this->port(port: ($remote['port'] ?? null));
		$checked['remoteApp'] = $this->remoteApp(name: ($remote['remoteApp'] ?? ''));
		$checked['gateway'] = $this->optionalHost(host: ($remote['gateway'] ?? ''));
		return $checked;
	}//end remote()

	/**
	 * An https address without credentials.
	 *
	 * @param string $url The address.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When it is not one.
	 */
	private function httpsAddress(string $url): string {
		$parts = parse_url($url);
		$valid = is_array($parts) === true && strlen($url) <= self::MAX_ADDRESS
			&& strtolower((string)($parts['scheme'] ?? '')) === 'https'
			&& preg_match(self::HOST_PATTERN, (string)($parts['host'] ?? '')) === 1
			&& isset($parts['user']) === false && isset($parts['pass']) === false
			&& preg_match('/[\x00-\x20\x7f]/', $url) !== 1;
		if ($valid === false) {
			throw new InvalidArgumentException(message: 'A gateway address must be an https address without a user name or password.');
		}

		return $url;
	}//end httpsAddress()

	/**
	 * A host name.
	 *
	 * @param mixed  $host The value.
	 * @param string $what The field, for the message.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When it is not a host name.
	 */
	private function host(mixed $host, string $what): string {
		if (is_string($host) === false || preg_match(self::HOST_PATTERN, $host) !== 1) {
			throw new InvalidArgumentException(message: 'The RDP ' . $what . ' must be a host name.');
		}

		return $host;
	}//end host()

	/**
	 * An optional host name; empty when not given.
	 *
	 * @param mixed $host The value.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When it is given and not a host name.
	 */
	private function optionalHost(mixed $host): string {
		if ($host === null || $host === '') {
			return '';
		}

		return $this->host(host: $host, what: 'gateway');
	}//end optionalHost()

	/**
	 * A TCP port; the RDP default when not given.
	 *
	 * @param mixed $port The value.
	 *
	 * @return int
	 *
	 * @throws InvalidArgumentException When it is not a port.
	 */
	private function port(mixed $port): int {
		if ($port === null || $port === '') {
			return self::DEFAULT_RDP_PORT;
		}

		if (is_int($port) === false && (is_string($port) === false || preg_match('/^\d{1,5}$/', $port) !== 1)) {
			throw new InvalidArgumentException(message: 'The RDP port must be a number from 1 to 65535.');
		}

		$port = (int)$port;
		if ($port < 1 || $port > 65535) {
			throw new InvalidArgumentException(message: 'The RDP port must be a number from 1 to 65535.');
		}

		return $port;
	}//end port()

	/**
	 * An optional published program name.
	 *
	 * @param mixed $name The value.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When it is given and not a program name.
	 */
	private function remoteApp(mixed $name): string {
		if ($name === null || $name === '') {
			return '';
		}

		if (is_string($name) === false || preg_match(self::REMOTE_APP_PATTERN, $name) !== 1) {
			throw new InvalidArgumentException(message: 'The published program must be a program name such as ||Belastingen.');
		}

		return $name;
	}//end remoteApp()

	/**
	 * The address of a single sign-on tile: its template with the encoded app id.
	 *
	 * @param array<string, mixed> $content The placement's content.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When the template or app id is not valid.
	 */
	private function sso(array $content): string {
		$sso = ($content['sso'] ?? null);
		if (is_array($sso) === false) {
			$sso = [];
		}

		$key = ($sso['template'] ?? null);
		$appId = ($sso['appId'] ?? null);
		if (is_string($appId) === false || preg_match(self::APP_ID_PATTERN, $appId) !== 1) {
			throw new InvalidArgumentException(message: 'A single sign-on tile needs an app id of letters, digits and . _ : -.');
		}

		foreach ($this->templates() as $template) {
			if (is_array($template) === true && ($template['key'] ?? null) === $key) {
				return str_replace(TileLaunchSettings::APP_ID_PLACEHOLDER, rawurlencode($appId), (string)$template['urlTemplate']);
			}
		}

		throw new InvalidArgumentException(message: 'A single sign-on tile must name one of the launch templates.');
	}//end sso()

	/**
	 * The launch templates the administrator defined.
	 *
	 * @return array<int, mixed>
	 */
	private function templates(): array {
		return (array)($this->settings->getSettings()['ssoLaunchTemplates'] ?? []);
	}//end templates()
}//end class
