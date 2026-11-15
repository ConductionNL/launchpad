<?php

/**
 * FeedFetchTools
 *
 *
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

use OCA\LaunchPad\Service\Connection\ConnectionReporter;
use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;

/**
 *
 *
 * @spec openspec/specs/widgets/spec.md
 */
class FeedFetchTools {
	/**
	 * Constructor.
	 *
	 * @param IClientService          $clientService Builds the HTTP client used for synchronous on-demand feed
	 *                                               fetches.
	 * @param ICacheFactory           $cacheFactory Backing factory for the distributed cache used to hold raw
	 *                                              feed payloads.
	 * @param UrlSafetyValidator      $urlValidator Shared SSRF / allow-list guard.
	 * @param ConnectionReporter|null $connectionReporter Tells integriq what a feed fetch met, or nothing when
	 *                                                    absent.
	 */
	public function __construct(
		public readonly IClientService $clientService,
		public readonly ICacheFactory $cacheFactory,
		public readonly UrlSafetyValidator $urlValidator,
		public readonly ?ConnectionReporter $connectionReporter=null,
	) {
	}//end __construct()
}//end class
