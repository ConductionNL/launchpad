<?php

/**
 * ReadingSources
 *
 * Where a remote reading widget gets its data: installed apps, the container, the HTTP client and
 * the cache.
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
 *
 * @spec openspec/specs/widgets/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\App\IAppManager;
use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;
use Psr\Container\ContainerInterface;

/**
 * Where a remote reading widget gets its data: installed apps, the container, the HTTP client and
 * the cache.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class ReadingSources {
	/**
	 * Constructor.
	 *
	 * @param IAppManager        $appManager Detects whether `weather_status` is enabled.
	 * @param ContainerInterface $container App container used to optionally resolve `weather_status`'s forecast
	 *                                      service (REQ-WEATHER-002 provider reuse).
	 * @param IClientService     $clientService HTTP client factory for the fallback provider fetch.
	 * @param ICacheFactory      $cacheFactory Backing factory for the distributed reading cache.
	 */
	public function __construct(
		public readonly IAppManager $appManager,
		public readonly ContainerInterface $container,
		public readonly IClientService $clientService,
		public readonly ICacheFactory $cacheFactory,
	) {
	}//end __construct()
}//end class
