<?php

/**
 * ReportApiController
 *
 * The report gallery, a report run and "save as dashboard" (REQ-DWMS-003).
 * Every route reads the caller from the session; a report reads its source
 * with the caller's own access, so a run shows nobody more than they may see.
 *
 * @category Controller
 * @package  OCA\LaunchPad\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\Reporting\ReportLibrary;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Lists, runs and saves the shipped reports.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */
class ReportApiController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest      $request The request.
	 * @param ReportLibrary $reports The report library.
	 * @param string|null   $userId  The signed-in user.
	 */
	public function __construct(
		IRequest $request,
		private readonly ReportLibrary $reports,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET the report gallery, each report with whether it can run here.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-003-reports-the-product-ships
	 */
	#[NoAdminRequired]
	public function index(): JSONResponse {
		if ($this->userId === null) {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(['reports' => $this->reports->gallery()]);
	}//end index()

	/**
	 * GET one report run; an unavailable report answers 200 with the missing names.
	 *
	 * @param string $reportId The report.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-003-reports-the-product-ships
	 */
	#[NoAdminRequired]
	public function run(string $reportId): JSONResponse {
		if ($this->userId === null) {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			$run = $this->reports->run(id: $reportId, parameters: $this->parameters());
		} catch (InvalidArgumentException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}

		if ($run === null) {
			return $this->failure(message: 'report_not_found', statusCode: Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse($run);
	}//end run()

	/**
	 * POST a report's result as a new dashboard owned by the caller.
	 *
	 * @param string $reportId The report.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-003-reports-the-product-ships
	 */
	#[NoAdminRequired]
	public function save(string $reportId): JSONResponse {
		if ($this->userId === null) {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			$dashboard = $this->reports->saveAsDashboard(id: $reportId, parameters: $this->parameters(), userId: $this->userId);
		} catch (InvalidArgumentException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse($dashboard->jsonSerialize(), Http::STATUS_CREATED);
	}//end save()

	/**
	 * The request's parameter values; the library keeps only the declared ones.
	 *
	 * @return array<string, mixed>
	 */
	private function parameters(): array {
		$parameters = $this->request->getParams();
		unset($parameters['reportId'], $parameters['_route']);

		return $parameters;
	}//end parameters()
}//end class
