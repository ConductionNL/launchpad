<?php

/**
 * MetadataApiCaller
 *
 * The metadata sources with the session and the ADR-023 action check of the dashboard metadata API.
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
 * @spec openspec/specs/dashboard-metadata-fields/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\ActionAuthService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUser;
use OCP\IUserSession;

/**
 * The metadata sources with the session and the ADR-023 action check of the dashboard metadata API.
 *
 * @spec openspec/specs/dashboard-metadata-fields/spec.md
 */
class MetadataApiCaller {
	/**
	 * Constructor.
	 *
	 * @param MetadataSources   $sources The metadata service and dashboard mapper.
	 * @param ActionAuthService $actionAuth ADR-023 action authorization.
	 * @param IUserSession      $userSession User session (IUser resolution).
	 */
	public function __construct(
		public readonly MetadataSources $sources,
		public readonly ActionAuthService $actionAuth,
		public readonly IUserSession $userSession,
	) {
	}//end __construct()

	/**
	 * The signed-in user when they may perform the action, else the 401/403 refusal.
	 *
	 * @param string|null $userId The controller's user ID.
	 * @param string      $action The action identifier to require.
	 *
	 * @return IUser|JSONResponse The user, or the refusal.
	 *
	 * @see ActionCaller::authorise()
	 *
	 * @spec openspec/specs/dashboard-metadata-fields/spec.md
	 */
	public function authorise(string|null $userId, string $action): IUser|JSONResponse {
		$caller = new ActionCaller(actionAuth: $this->actionAuth, userSession: $this->userSession);
		return $caller->authorise(userId: $userId, action: $action);
	}//end authorise()
}//end class
