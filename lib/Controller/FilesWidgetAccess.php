<?php

/**
 * FilesWidgetAccess
 *
 * The signed-in user and the configured folder of a files widget
 * placement that user may write to.
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
 * @spec openspec/specs/files-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

/**
 * A granted files widget placement.
 *
 * @spec openspec/specs/files-widget/spec.md
 */
final class FilesWidgetAccess {
	/**
	 * Constructor.
	 *
	 * @param string              $userId The signed-in user's UID.
	 * @param array<string,mixed> $config The placement's parsed folder config.
	 */
	public function __construct(
		public readonly string $userId,
		public readonly array $config,
	) {
	}//end __construct()
}//end class
