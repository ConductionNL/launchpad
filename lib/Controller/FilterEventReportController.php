<?php

/**
 * FilterEventReportController
 *
 * Serves the "most-used drill-down paths" report of one dashboard
 * (drill-down-cross-widget-filter REQ-CWF-010). The owner and administrators
 * see every user's paths; another viewer sees only their own; anyone else
 * gets 404, as if the dashboard did not exist.
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
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-010-telemetry-of-filter-usage-is-opt-in-and-aggregated
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\FilterEventReportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * The drill-down path report endpoint.
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-16
 */
class FilterEventReportController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest                 $request       The request.
	 * @param FilterEventReportService $reportService Builds the report and decides its scope.
	 * @param IUserSession             $userSession   Names the caller.
	 */
	public function __construct(
		IRequest $request,
		private readonly FilterEventReportService $reportService,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The report of one dashboard.
	 *
	 * Access is decided per dashboard by the service: the owner and
	 * administrators get every user's paths, another viewer only their own.
	 *
	 * @param string $uuid  The dashboard's uuid.
	 * @param int    $limit The most paths returned.
	 *
	 * @return JSONResponse `{dashboardId, scope, events, paths: [{path, count}]}`; 401 signed out, 404 not found or not visible.
	 *
	 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-16
	 */
	#[NoAdminRequired]
	public function report(string $uuid, int $limit=20): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['message' => 'Not logged in'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			$report = $this->reportService->report(dashboardUuid: $uuid, userId: $user->getUID(), limit: $limit);
		} catch (DoesNotExistException) {
			return new JSONResponse(data: ['message' => 'Dashboard not found'], statusCode: Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse(data: $report);
	}//end report()
}//end class
