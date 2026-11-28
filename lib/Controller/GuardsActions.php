<?php

/**
 * GuardsActions
 *
 * The access pre-check controllers share before they act: a signed-in caller
 * the ADR-023 action check allows, and for administrator endpoints an
 * administrator. Answers the refusal as a response, or null to go on.
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
 * @spec openspec/changes/archive/2026-11-19-launcher-app-catalogue/tasks.md#task-4
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IUser;

/**
 * Signed-in, allowed and, where asked, administrator.
 *
 * @spec openspec/changes/archive/2026-11-19-launcher-app-catalogue/tasks.md#task-4
 */
trait GuardsActions {

	/**
	 * Signed-in and allowed to call the action.
	 *
	 * @param IUser|null $user  The caller.
	 * @param callable   $check ADR-023 action check; throws OCSForbiddenException when refused.
	 *
	 * @return JSONResponse|null An error response, or null when allowed.
	 */
	private function guard(?IUser $user, callable $check): ?JSONResponse {
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			$check($user);
		} catch (OCSForbiddenException) {
			return new JSONResponse(data: ['error' => 'Forbidden'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return null;
	}//end guard()

	/**
	 * A signed-in administrator allowed to call the action. A signed-in
	 * caller who is no administrator is refused before the action check runs.
	 *
	 * @param IUser|null    $user         The caller.
	 * @param IGroupManager $groupManager Answers whether the caller is an administrator.
	 * @param callable      $check        ADR-023 action check; throws OCSForbiddenException when refused.
	 *
	 * @return JSONResponse|null An error response, or null when allowed.
	 */
	private function guardAdmin(?IUser $user, IGroupManager $groupManager, callable $check): ?JSONResponse {
		if ($user !== null && $groupManager->isAdmin(userId: $user->getUID()) === false) {
			return new JSONResponse(data: ['error' => 'Admin required'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return $this->guard(user: $user, check: $check);
	}//end guardAdmin()
}//end trait
