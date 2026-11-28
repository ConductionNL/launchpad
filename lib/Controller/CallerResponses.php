<?php

/**
 * CallerResponses
 *
 * A caller check with the caller's user id and the response builder, taken
 * as one value.
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
 * A caller check with the caller's user id and the response builder.
 */
class CallerResponses {

	/**
	 * The signed-in user, or null.
	 *
	 * @var string|null
	 */
	public readonly ?string $userId;

	/**
	 * Builds the JSON response envelopes.
	 *
	 * @var ResponseHelper
	 */
	public readonly ResponseHelper $responseHelper;

	/**
	 * Constructor.
	 *
	 * @param ActionCaller $caller The session user and the ADR-023 action check.
	 * @param UserResponses $responses The user id and the response builder.
	 *
	 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
	 */
	public function __construct(
		public readonly ActionCaller $caller,
		UserResponses $responses,
	) {
		$this->userId         = $responses->userId;
		$this->responseHelper = $responses->responseHelper;
	}//end __construct()
}//end class
