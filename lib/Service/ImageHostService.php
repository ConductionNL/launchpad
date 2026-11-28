<?php

/**
 * ImageHostService
 *
 * The administrator's allow-list of external hosts image widgets may load
 * pictures from (REQ-IMG-006). The list is a JSON array of hostnames under
 * `image_allowed_hosts` (IAppConfig), the same shape as
 * `iframe_allowed_hosts`, and it is FAIL-CLOSED: an empty or missing list
 * admits no external image host. {@see \OCA\LaunchPad\Listener\CspListener}
 * adds each host to the `img-src` directive of LaunchPad pages.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCP\IAppConfig;

/**
 * Reads and stores the external image host allow-list.
 *
 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
 */
class ImageHostService {

	/**
	 * IAppConfig key: JSON array of hostnames image widgets may load from.
	 *
	 * @var string
	 */
	public const CONFIG_KEY_ALLOWED_HOSTS = 'image_allowed_hosts';

	/**
	 * A bare hostname with an optional port: no scheme, path or wildcard.
	 *
	 * @var string
	 */
	private const HOST_PATTERN = '~^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?::[0-9]{1,5})?$~i';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig Holds the allow-list.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}//end __construct()

	/**
	 * The allow-listed hosts; empty when none are configured.
	 *
	 * @return string[]
	 *
	 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
	 */
	public function getAllowedHosts(): array {
		return (new HostAllowList(appConfig: $this->appConfig, configKey: self::CONFIG_KEY_ALLOWED_HOSTS))->hosts();
	}//end getAllowedHosts()

	/**
	 * The hosts images may load from: this list and the allowed websites
	 * with purpose embed (REQ-ASET-021). The admin editor reads
	 * {@see self::getAllowedHosts()}; the CSP reads this.
	 *
	 * @return string[]
	 *
	 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	public function getPermittedHosts(): array {
		return (new HostAllowList(
			appConfig: $this->appConfig,
			configKey: self::CONFIG_KEY_ALLOWED_HOSTS,
			purpose: AllowedSitesService::PURPOSE_EMBED
		))->hosts();
	}//end getPermittedHosts()

	/**
	 * Replace the allow-list. Every entry must be a bare hostname, optionally
	 * with a port; entries are lower-cased, blanks dropped and duplicates merged.
	 *
	 * @param mixed $hosts The submitted list.
	 *
	 * @return string[] The stored list.
	 *
	 * @throws InvalidArgumentException When the list is not an array of hostnames.
	 *
	 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
	 */
	public function setAllowedHosts(mixed $hosts): array {
		if (is_array(value: $hosts) === false) {
			throw new InvalidArgumentException(message: 'The allowed image hosts must be a list of host names.');
		}

		$clean = [];
		foreach ($hosts as $host) {
			if (is_string(value: $host) === false) {
				throw new InvalidArgumentException(message: 'The allowed image hosts must be a list of host names.');
			}

			$host = strtolower(string: trim(string: $host));
			if ($host === '') {
				continue;
			}

			if (preg_match(pattern: self::HOST_PATTERN, subject: $host) !== 1) {
				throw new InvalidArgumentException(
					message: sprintf('"%s" is not a host name. Enter a host such as images.example.com, without https:// or a path.', $host)
				);
			}

			$clean[] = $host;
		}

		$clean = array_values(array: array_unique(array: $clean));
		$this->appConfig->setValueString(
			app: Application::APP_ID,
			key: self::CONFIG_KEY_ALLOWED_HOSTS,
			value: json_encode(value: $clean, flags: JSON_THROW_ON_ERROR)
		);

		return $clean;
	}//end setAllowedHosts()
}//end class
