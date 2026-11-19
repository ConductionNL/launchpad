<?php

/**
 * UserResponses
 *
 * The caller's user id and the response builder, which most controllers take
 * together.
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
 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

/**
 * The caller's user id and the response builder.
 */
class UserResponses {
	/**
	 * Constructor.
	 *
	 * @param string|null    $userId         The signed-in user, or null.
	 * @param ResponseHelper $responseHelper Builds the JSON response envelopes.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	public function __construct(
		public readonly ?string $userId,
		public readonly ResponseHelper $responseHelper,
	) {
	}//end __construct()
}//end class
