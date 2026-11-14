<?php

/**
 * AdminCatalogueController
 *
 * The app catalogue for administrators (launcher-app-catalogue): keep the
 * approved entries (REQ-ACAT-001), decide on requests (REQ-ACAT-004) and
 * name the catalogue manager group. Every method carries
 * `#[AuthorizedAdminSetting]` and checks its ADR-023 action (design D4).
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
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;

/**
 * App catalogue endpoints for administrators.
 *
 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-4
 */
class AdminCatalogueController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest         $request      HTTP request.
	 * @param CatalogueService $catalogue    The catalogue.
	 * @param ActionCaller     $caller       The session and the action check.
	 * @param IGroupManager    $groupManager Admin check.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-4
	 */
	public function __construct(
		IRequest $request,
		private readonly CatalogueService $catalogue,
		private readonly ActionCaller $caller,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Every entry, the open requests and the catalogue manager group.
	 *
	 * @return JSONResponse `{apps, requests, managerGroup}`, 401 or 403.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-keep-a-catalogue-of-approved-apps-req-acat-001
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function index(): JSONResponse {
		$guard = $this->adminGuard(check: fn (IUser $caller) => $this->caller->actionAuth->requireAction($caller, 'catalogue.manage'));
		if ($guard !== null) {
			return $guard;
		}

		return new JSONResponse(
			data: [
				'apps' => $this->catalogue->listAll(),
				'requests' => $this->catalogue->openRequests(),
				'managerGroup' => $this->catalogue->getManagerGroup(),
			]
		);
	}//end index()

	/**
	 * Add an entry.
	 *
	 * @return JSONResponse `{app}` (201), 400, 401 or 403.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-keep-a-catalogue-of-approved-apps-req-acat-001
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function create(): JSONResponse {
		$guard = $this->adminGuard(check: fn (IUser $caller) => $this->caller->actionAuth->requireAction($caller, 'catalogue.manage'));
		if ($guard !== null) {
			return $guard;
		}

		try {
			$app = $this->catalogue->create(input: $this->entryInput());
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(data: ['app' => $app], statusCode: Http::STATUS_CREATED);
	}//end create()

	/**
	 * Change an entry.
	 *
	 * @param string $uuid The entry.
	 *
	 * @return JSONResponse `{app}`, 400, 401, 403 or 404.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-keep-a-catalogue-of-approved-apps-req-acat-001
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(string $uuid): JSONResponse {
		$guard = $this->adminGuard(check: fn (IUser $caller) => $this->caller->actionAuth->requireAction($caller, 'catalogue.manage'));
		if ($guard !== null) {
			return $guard;
		}

		try {
			$app = $this->catalogue->update(uuid: $uuid, input: $this->entryInput());
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException) {
			return self::notFound();
		}

		return new JSONResponse(data: ['app' => $app]);
	}//end update()

	/**
	 * Remove an entry.
	 *
	 * @param string $uuid The entry.
	 *
	 * @return JSONResponse `{deleted: true}`, 401, 403 or 404.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-keep-a-catalogue-of-approved-apps-req-acat-001
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function destroy(string $uuid): JSONResponse {
		$guard = $this->adminGuard(check: fn (IUser $caller) => $this->caller->actionAuth->requireAction($caller, 'catalogue.manage'));
		if ($guard !== null) {
			return $guard;
		}

		try {
			$this->catalogue->delete(uuid: $uuid);
		} catch (DoesNotExistException) {
			return self::notFound();
		}

		return new JSONResponse(data: ['deleted' => true]);
	}//end destroy()

	/**
	 * Approve a request with the entry the administrator completed.
	 *
	 * @param string $uuid The request.
	 *
	 * @return JSONResponse `{request, app}`, 400, 401, 403, 404 or 409 (code `request_not_open`).
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-decide-on-requests-and-the-requester-hears-back-req-acat-004
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function approve(string $uuid): JSONResponse {
		$guard = $this->adminGuard(check: fn (IUser $caller) => $this->caller->actionAuth->requireAction($caller, 'catalogue.decide'));
		if ($guard !== null) {
			return $guard;
		}

		try {
			$result = $this->catalogue->approve(
				requestUuid: $uuid,
				adminId: $this->callerId(),
				entry: $this->entryInput()
			);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		} catch (DomainException $e) {
			return self::notOpen(code: $e->getMessage());
		} catch (DoesNotExistException) {
			return self::notFound();
		}

		return new JSONResponse(data: $result);
	}//end approve()

	/**
	 * Decline a request with a note.
	 *
	 * @param string      $uuid The request.
	 * @param string|null $note Why; required.
	 *
	 * @return JSONResponse `{request}`, 400, 401, 403, 404 or 409 (code `request_not_open`).
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-decide-on-requests-and-the-requester-hears-back-req-acat-004
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function decline(string $uuid, ?string $note = null): JSONResponse {
		$guard = $this->adminGuard(check: fn (IUser $caller) => $this->caller->actionAuth->requireAction($caller, 'catalogue.decide'));
		if ($guard !== null) {
			return $guard;
		}

		try {
			$request = $this->catalogue->decline(requestUuid: $uuid, adminId: $this->callerId(), note: (string)$note);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		} catch (DomainException $e) {
			return self::notOpen(code: $e->getMessage());
		} catch (DoesNotExistException) {
			return self::notFound();
		}

		return new JSONResponse(data: ['request' => $request]);
	}//end decline()

	/**
	 * Name the group whose members also hear about requests.
	 *
	 * @param string|null $managerGroup The group id; '' or null for none.
	 *
	 * @return JSONResponse `{managerGroup}`, 400, 401 or 403.
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-5
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function setManagerGroup(?string $managerGroup = null): JSONResponse {
		$guard = $this->adminGuard(check: fn (IUser $caller) => $this->caller->actionAuth->requireAction($caller, 'catalogue.manage'));
		if ($guard !== null) {
			return $guard;
		}

		try {
			$stored = $this->catalogue->setManagerGroup(groupId: (string)$managerGroup);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(data: ['managerGroup' => $stored]);
	}//end setManagerGroup()

	/**
	 * The entry fields of the request body.
	 *
	 * @return array<string, mixed>
	 */
	private function entryInput(): array {
		$input = [];
		foreach (['name', 'description', 'icon', 'iconType', 'linkType', 'linkValue', 'targetGroups', 'sortOrder'] as $key) {
			$value = $this->request->getParam(key: $key);
			if ($value !== null) {
				$input[$key] = $value;
			}
		}

		return $input;
	}//end entryInput()

	/**
	 * The signed-in administrator's id.
	 *
	 * @return string
	 */
	private function callerId(): string {
		return (string)$this->caller->userSession->getUser()?->getUID();
	}//end callerId()

	/**
	 * Signed-in administrator allowed to call the action.
	 *
	 * @param callable $check ADR-023 action check; throws OCSForbiddenException when refused.
	 *
	 * @return JSONResponse|null An error response, or null when allowed.
	 */
	private function adminGuard(callable $check): ?JSONResponse {
		$user = $this->caller->userSession->getUser();
		if ($user !== null && $this->groupManager->isAdmin(userId: $user->getUID()) === false) {
			return new JSONResponse(data: ['error' => 'Admin required'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return $this->guard(user: $user, check: $check);
	}//end adminGuard()

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
	 * The 404 response.
	 *
	 * @return JSONResponse
	 */
	private static function notFound(): JSONResponse {
		return new JSONResponse(data: ['error' => 'Not found'], statusCode: Http::STATUS_NOT_FOUND);
	}//end notFound()

	/**
	 * The 409 response for a request that was already decided.
	 *
	 * @param string $code The error code.
	 *
	 * @return JSONResponse
	 */
	private static function notOpen(string $code): JSONResponse {
		return new JSONResponse(
			data: ['error' => 'This request was already decided.', 'code' => $code],
			statusCode: Http::STATUS_CONFLICT
		);
	}//end notOpen()
}//end class
