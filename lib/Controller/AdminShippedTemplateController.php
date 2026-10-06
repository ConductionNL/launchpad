<?php

/**
 * AdminShippedTemplateController
 *
 * Admin endpoints for the templates LaunchPad ships with: list them, and add
 * one as an admin template. Which groups get it is then set on the Templates
 * page, as for any other template.
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

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Exception\TemplateNotInstalledException;
use OCA\LaunchPad\Service\ShippedTemplateService;
use OCA\LaunchPad\Service\ShippedTemplateUpdateService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * List, install and update shipped templates.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 *      {@see ResponseHelper} is an all-static envelope builder with no state.
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 *      `$force` and `$dryRun` are request parameters bound by the framework.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *      One controller for the three shipped-template endpoints. The types it
 *      names are the framework's (request, session, response, attribute) plus
 *      the two services and the one exception it turns into a 409.
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
 */
class AdminShippedTemplateController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest               $request      The request.
	 * @param ShippedTemplateService       $templates    Shipped template service.
	 * @param ShippedTemplateUpdateService $updates      Updates an installed template in place.
	 * @param IUserSession           $userSession  The user session.
	 * @param IGroupManager          $groupManager The group manager.
	 * @param LoggerInterface        $logger       Logger.
	 */
	public function __construct(
		IRequest $request,
		private readonly ShippedTemplateService $templates,
		private readonly ShippedTemplateUpdateService $updates,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(
			appName: Application::APP_ID,
			request: $request
		);
	}//end __construct()

	/**
	 * The signed-in administrator's id, or a refusal.
	 *
	 * @return string|JSONResponse The user id, or the 401/403 to return.
	 */
	private function requireAdmin(): string|JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(
				data: ['error' => 'Not authenticated'],
				statusCode: Http::STATUS_UNAUTHORIZED
			);
		}

		if ($this->groupManager->isAdmin(userId: $user->getUID()) === false) {
			return new JSONResponse(
				data: ['error' => 'Admin required'],
				statusCode: Http::STATUS_FORBIDDEN
			);
		}

		return $user->getUID();
	}//end requireAdmin()

	/**
	 * List the shipped templates with whether each is installed.
	 *
	 * @return JSONResponse The templates.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function index(): JSONResponse {
		$admin = $this->requireAdmin();
		if ($admin instanceof JSONResponse) {
			return $admin;
		}

		return ResponseHelper::success(data: $this->templates->listTemplates());
	}//end index()

	/**
	 * Add a shipped template as an admin template.
	 *
	 * @param string $id    The shipped template id.
	 * @param bool   $force Add a fresh copy even when installed.
	 *
	 * @return JSONResponse The installed template, 201 when it was added.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function install(string $id, bool $force = false): JSONResponse {
		$admin = $this->requireAdmin();
		if ($admin instanceof JSONResponse) {
			return $admin;
		}

		try {
			$result = $this->templates->install(templateId: $id, force: $force, userId: $admin);
		} catch (InvalidArgumentException) {
			return new JSONResponse(
				data: ['error' => 'Template not found'],
				statusCode: Http::STATUS_NOT_FOUND
			);
		} catch (Throwable $e) {
			$this->logger->error(
				message: 'Shipped template install failed',
				context: ['templateId' => $id, 'exception' => $e]
			);
			return new JSONResponse(
				data: ['error' => 'Template installation failed'],
				statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}

		$statusCode = Http::STATUS_CREATED;
		if ($result['alreadyInstalled'] === true) {
			$statusCode = Http::STATUS_OK;
		}

		return new JSONResponse(data: $result, statusCode: $statusCode);
	}//end install()

	/**
	 * Update an installed shipped template to the version LaunchPad ships
	 * now, in place, and bring the members' copies along. With `dryRun` it
	 * only says what would change.
	 *
	 * @param string $id     The shipped template id.
	 * @param bool   $dryRun Report the changes, write nothing.
	 *
	 * @return JSONResponse The widgets added, removed and changed; 404 for an
	 *                      unknown id, 409 when the template is not installed.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(string $id, bool $dryRun = false): JSONResponse {
		$admin = $this->requireAdmin();
		if ($admin instanceof JSONResponse) {
			return $admin;
		}

		try {
			$result = $this->updates->update(templateId: $id, dryRun: $dryRun, userId: $admin);
		} catch (InvalidArgumentException) {
			return new JSONResponse(
				data: ['error' => 'Template not found'],
				statusCode: Http::STATUS_NOT_FOUND
			);
		} catch (TemplateNotInstalledException) {
			return new JSONResponse(
				data: ['error' => 'Template not installed'],
				statusCode: Http::STATUS_CONFLICT
			);
		} catch (Throwable $e) {
			$this->logger->error(
				message: 'Shipped template update failed',
				context: ['templateId' => $id, 'exception' => $e]
			);
			return new JSONResponse(
				data: ['error' => 'Template update failed'],
				statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}//end try

		return new JSONResponse(data: $result, statusCode: Http::STATUS_OK);
	}//end update()
}//end class
