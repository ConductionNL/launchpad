<?php

/**
 * AdminCustomCssController
 *
 * REST surface for administrator CSS (REQ-THM-003):
 *
 *  - `GET /api/admin/custom-css` — the stored CSS for all of LaunchPad.
 *  - `PUT /api/admin/custom-css` — clean, scope and store it.
 *  - `PUT /api/admin/custom-css/dashboards/{uuid}` — the same for one dashboard.
 *
 * Every route is administrator-only. A save answers with what was stored and
 * what the sanitiser dropped.
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
use OCA\LaunchPad\Service\CustomCssService;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Administrator CSS editor REST surface.
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
 */
class AdminCustomCssController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest         $request The inbound request.
	 * @param CustomCssService $css     Stores the cleaned CSS.
	 * @param RequestGuard     $guard   The administrator check.
	 */
	public function __construct(
		IRequest $request,
		private readonly CustomCssService $css,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Read the stored CSS for all of LaunchPad.
	 *
	 * @return JSONResponse `{customCss}`, or 401/403 for a non-administrator.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function show(): JSONResponse {
		$refusal = $this->guard->refusal();
		if ($refusal !== null) {
			return $refusal;
		}

		return ResponseHelper::success(data: ['customCss' => $this->css->getGlobalCss()]);
	}//end show()

	/**
	 * Clean, scope and store the CSS for all of LaunchPad.
	 *
	 * @param mixed $customCss The CSS; '' clears it.
	 *
	 * @return JSONResponse `{customCss, removed}`, 400 for a value that is no
	 *                      string or is over 20 KB, 401/403 for a non-administrator.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(mixed $customCss = null): JSONResponse {
		$refusal = $this->guard->refusal();
		if ($refusal !== null) {
			return $refusal;
		}

		if (is_string($customCss) === false) {
			return $this->failure(message: 'customCss must be a string', statusCode: Http::STATUS_BAD_REQUEST);
		}

		try {
			return ResponseHelper::success(data: $this->css->setGlobalCss(css: $customCss));
		} catch (InvalidArgumentException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}
	}//end update()

	/**
	 * Clean, scope and store the CSS for one dashboard.
	 *
	 * @param string $uuid      The dashboard's UUID.
	 * @param mixed  $customCss The CSS; '' clears it.
	 *
	 * @return JSONResponse `{customCss, removed}`, 400 for a value that is no
	 *                      string or is over 20 KB, 404 for an unknown
	 *                      dashboard, 401/403 for a non-administrator.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateDashboard(string $uuid, mixed $customCss = null): JSONResponse {
		$refusal = $this->guard->refusal();
		if ($refusal !== null) {
			return $refusal;
		}

		if (is_string($customCss) === false) {
			return $this->failure(message: 'customCss must be a string', statusCode: Http::STATUS_BAD_REQUEST);
		}

		try {
			return ResponseHelper::success(data: $this->css->setDashboardCss(uuid: $uuid, css: $customCss));
		} catch (DoesNotExistException) {
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}
	}//end updateDashboard()
}//end class
