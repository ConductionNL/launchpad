<?php

/**
 * SearchBuckets
 *
 * The state of one unified-search pass of {@see LaunchPadSearchProvider}:
 * the lower-cased needle, the localizer and the three result buckets
 * (dashboard name/description, widget content, metadata values), each
 * capped at the provider's per-bucket limit.
 *
 * @category  Search
 * @package   OCA\LaunchPad\Search
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Search;

use OCP\IL10N;
use OCP\Search\SearchResultEntry;

/**
 * Result buckets of one search pass.
 *
 * @spec openspec/specs/nc-unified-search-integration/spec.md
 */
final class SearchBuckets {

	/**
	 * Dashboard name / description matches (REQ-SRCH-002).
	 *
	 * @var SearchResultEntry[]
	 */
	public array $dashboardEntries = [];

	/**
	 * Widget content matches (REQ-SRCH-003).
	 *
	 * @var SearchResultEntry[]
	 */
	public array $widgetEntries = [];

	/**
	 * Metadata value matches (REQ-SRCH-004).
	 *
	 * @var SearchResultEntry[]
	 */
	public array $metadataEntries = [];

	/**
	 * Constructor.
	 *
	 * @param string $needle The lower-cased search term.
	 * @param IL10N  $l10n   The localizer for sublines.
	 */
	public function __construct(
		public readonly string $needle,
		public readonly IL10N $l10n,
	) {
	}//end __construct()

	/**
	 * All entries, ranked: title/description, then widget body, then
	 * metadata (REQ-SRCH-006 / REQ-SRCH-011).
	 *
	 * @return SearchResultEntry[]
	 *
	 * @spec openspec/specs/nc-unified-search-integration/spec.md
	 */
	public function rankedEntries(): array {
		return array_merge($this->dashboardEntries, $this->widgetEntries, $this->metadataEntries);
	}//end rankedEntries()
}//end class
