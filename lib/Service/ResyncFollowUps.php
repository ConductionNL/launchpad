<?php

/**
 * ResyncFollowUps
 *
 * What a template resync does to a copy after it changed it: drop the
 * personal-layer entries of removed placements, and write the copy's
 * version by the template ("Template {name}", "Updated from the template").
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
 * @spec openspec/changes/version-change-notes/specs/dashboard-versioning/spec.md#requirement-a-template-update-is-noted-as-such-req-versui-005
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCP\IL10N;

/**
 * The follow-ups of a resync on one changed copy.
 *
 * @spec openspec/changes/version-change-notes/specs/dashboard-versioning/spec.md#requirement-a-template-update-is-noted-as-such-req-versui-005
 */
class ResyncFollowUps {

	/**
	 * Longest author a version row stores, in characters.
	 *
	 * @var integer
	 */
	public const MAX_AUTHOR_LENGTH = 64;

	/**
	 * Constructor
	 *
	 * @param PersonalLayerService         $personalLayers Drops personal-layer entries for removed placements
	 *                                                     (REQ-DWMS-001).
	 * @param IL10N                        $l10n           The app's translations for the author and the note.
	 * @param DashboardVersionService|null $versions       Writes the version a changed copy gets; null writes none.
	 *
	 * @spec openspec/changes/version-change-notes/specs/dashboard-versioning/spec.md#requirement-a-template-update-is-noted-as-such-req-versui-005
	 */
	public function __construct(
		public readonly PersonalLayerService $personalLayers,
		private readonly IL10N $l10n,
		private readonly ?DashboardVersionService $versions=null,
	) {
	}//end __construct()

	/**
	 * Write the version a re-synced copy gets: its author is the template
	 * and its note "Updated from the template", instead of the administrator
	 * who ran the resync and a list of widget changes.
	 *
	 * @param Dashboard $copy         The copy that took the change.
	 * @param string    $templateName The template's name.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/version-change-notes/specs/dashboard-versioning/spec.md#requirement-a-template-update-is-noted-as-such-req-versui-005
	 */
	public function recordTemplateVersion(Dashboard $copy, string $templateName): void {
		if ($this->versions === null) {
			return;
		}

		$this->versions->captureSnapshot(
			dashboard: $copy,
			createdBy: $this->templateAuthor(templateName: $templateName),
			options: ['note' => $this->l10n->t('Updated from the template'), 'explicit' => true]
		);
	}//end recordTemplateVersion()

	/**
	 * The author a template-written version carries, cut to the length the
	 * version table stores.
	 *
	 * @param string $templateName The template's name.
	 *
	 * @return string "Template {name}".
	 *
	 * @spec openspec/changes/version-change-notes/specs/dashboard-versioning/spec.md#requirement-a-template-update-is-noted-as-such-req-versui-005
	 */
	public function templateAuthor(string $templateName): string {
		$author = $this->l10n->t('Template %s', [trim($templateName)]);

		return rtrim(mb_substr($author, 0, self::MAX_AUTHOR_LENGTH));
	}//end templateAuthor()
}//end class
