<?php

/**
 * FeedRefreshSources
 *
 * What a feed refresh reads and keeps: the HTTP client, the URL check, the feed cache and config.
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

use OCA\LaunchPad\Db\FeedCacheMapper;
use OCP\Http\Client\IClientService;
use OCP\IConfig;

/**
 * What a feed refresh reads and keeps: the HTTP client, the URL check, the feed cache and config.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class FeedRefreshSources {
	/**
	 * Constructor.
	 *
	 * @param IClientService     $clientService The HTTP client factory.
	 * @param UrlSafetyValidator $urlValidator SSRF guard (H3).
	 * @param FeedCacheMapper    $cacheMapper The feed-cache mapper.
	 * @param IConfig            $config The system config reader.
	 */
	public function __construct(
		public readonly IClientService $clientService,
		public readonly UrlSafetyValidator $urlValidator,
		public readonly FeedCacheMapper $cacheMapper,
		public readonly IConfig $config,
	) {
	}//end __construct()
}//end class
