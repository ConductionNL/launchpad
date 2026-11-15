<?php

/**
 * TranslationVariantDraft
 *
 * The language code and initial content of a translation variant to
 * create, as posted to the translation API (REQ-DASH-040).
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/dashboard-language-content/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

/**
 * A translation variant to create.
 *
 * @spec openspec/specs/dashboard-language-content/spec.md
 */
final class TranslationVariantDraft {
	/**
	 * Constructor.
	 *
	 * @param string                    $languageCode The variant's language code.
	 * @param array<string, string|null> $content      Name, description, widget tree JSON and copy-from language.
	 */
	public function __construct(
		public readonly string $languageCode,
		public readonly array $content,
	) {
	}//end __construct()
}//end class
