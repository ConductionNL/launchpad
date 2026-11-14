<?php

/**
 * CatalogueController
 *
 * The app catalogue for every signed-in user (launcher-app-catalogue): the
 * entries the user's groups may see (REQ-ACAT-002) and a request for an
 * application that is missing (REQ-ACAT-003).
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use DomainException;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\CatalogueService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;

/**
 * App catalogue endpoints for users.
 *
 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-4
 */
class CatalogueController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest         $request   HTTP request.
	 * @param CatalogueService $catalogue The catalogue.
	 * @param ActionCaller     $caller    The session and the action check.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-4
	 */
	public function __construct(
		IRequest $request,
		private readonly CatalogueService $catalogue,
		private readonly ActionCaller $caller,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The entries the caller's groups may see. The filter runs here, on the
	 * server; the client never filters for security (design D2).
	 *
	 * @return JSONResponse `{apps: [...]}`, 401 or 403.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-add-catalogue-apps-as-tiles-from-the-add-flow-req-acat-002
	 */
	#[NoAdminRequired]
	public function index(): JSONResponse {
		$user  = $this->caller->userSession->getUser();
		$guard = $this->guard(user: $user, check: fn (IUser $caller) => $this->caller->actionAuth->requireAction($caller, 'catalogue.list'));
		if ($guard !== null || $user === null) {
			return ($guard ?? new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED));
		}

		return new JSONResponse(data: ['apps' => $this->catalogue->listFor(userId: $user->getUID())]);
	}//end index()

	/**
	 * Ask for an application the catalogue lacks.
	 *
	 * @param string|null $appName The application.
	 * @param string|null $purpose What the caller needs it for.
	 *
	 * @return JSONResponse `{request: {...}}` (201), 400, 401, 403 or 429 with code `request_limit`.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-can-ask-for-a-missing-app-req-acat-003
	 */
	#[NoAdminRequired]
	public function request(?string $appName = null, ?string $purpose = null): JSONResponse {
		$user  = $this->caller->userSession->getUser();
		$guard = $this->guard(user: $user, check: fn (IUser $caller) => $this->caller->actionAuth->requireAction($caller, 'catalogue.request'));
		if ($guard !== null || $user === null) {
			return ($guard ?? new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED));
		}

		try {
			$stored = $this->catalogue->request(
				userId: $user->getUID(),
				appName: (string)$appName,
				purpose: (string)$purpose
			);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		} catch (DomainException $e) {
			return new JSONResponse(
				data: [
					'error' => 'You have ' . CatalogueService::MAX_OPEN_REQUESTS . ' open requests. Wait for an answer first.',
					'code' => $e->getMessage(),
				],
				statusCode: Http::STATUS_TOO_MANY_REQUESTS
			);
		}

		return new JSONResponse(data: ['request' => $stored], statusCode: Http::STATUS_CREATED);
	}//end request()

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
}//end class
