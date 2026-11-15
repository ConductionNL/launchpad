<?php

/**
 * ShowcaseInstallOptions
 *
 * The source language and the force flag of one showcase install
 * (REQ-DEMO-007, REQ-DEMO-009).
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
 * @spec openspec/specs/demo-data-showcases/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The source language and the force flag of one showcase install.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
final class ShowcaseInstallOptions {
	/**
	 * Constructor.
	 *
	 * @param string $lang  The source language (always `nl` in v1).
	 * @param bool   $force Whether an existing install is replaced.
	 */
	public function __construct(
		public readonly string $lang,
		public readonly bool $force,
	) {
	}//end __construct()
}//end class
