<?php

/**
 * SearchLinks
 *
 * The localisation factory and the URL generator of the search provider.
 *
 * @category  Search
 * @package   OCA\LaunchPad\Search
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/nc-unified-search-integration/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Search;

use OCP\IURLGenerator;
use OCP\L10N\IFactory;

/**
 * The localisation factory and the URL generator of the search provider.
 *
 * @spec openspec/specs/nc-unified-search-integration/spec.md
 */
class SearchLinks {
	/**
	 * Constructor.
	 *
	 * @param IFactory      $l10nFactory The L10N factory.
	 * @param IURLGenerator $urlGenerator The URL generator.
	 */
	public function __construct(
		public readonly IFactory $l10nFactory,
		public readonly IURLGenerator $urlGenerator,
	) {
	}//end __construct()
}//end class
