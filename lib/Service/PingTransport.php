<?php

/**
 * PingTransport
 *
 * The HTTP client and the cache factory of the health ping service.
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
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;

/**
 * The HTTP client and the cache factory of the health ping service.
 *
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md
 */
class PingTransport {
	/**
	 * Constructor.
	 *
	 * @param IClientService $clientService HTTP client factory for the health-check request.
	 * @param ICacheFactory  $cacheFactory Backing factory for the distributed badge cache.
	 */
	public function __construct(
		public readonly IClientService $clientService,
		public readonly ICacheFactory $cacheFactory,
	) {
	}//end __construct()
}//end class
