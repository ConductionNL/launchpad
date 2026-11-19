<?php

/**
 * StorageReportController
 *
 * The administrator's storage report and its CSV (REQ-DWMS-010).
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

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\StorageReport\StorageReportRefusedException;
use OCA\LaunchPad\Service\StorageReport\StorageReportService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Storage use per workspace and service, administrators only.
 *
 * The route attribute keeps others out, and the service refuses anybody who
 * is not an administrator again, so the 403 does not rest on one check.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-010-storage-use-per-workspace-and-service-over-time
 */
class StorageReportController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest             $request The request.
	 * @param StorageReportService $storage Builds the report.
	 * @param string|null          $userId  The signed-in user.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.4
	 */
	public function __construct(
		IRequest $request,
		private readonly StorageReportService $storage,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET the storage report.
	 *
	 * @return JSONResponse The report, or 403.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.1
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function report(): JSONResponse {
		try {
			return new JSONResponse($this->storage->report(readerId: (string) $this->userId));
		} catch (StorageReportRefusedException $refused) {
			return $this->failure(message: $refused->getMessage(), statusCode: $refused->statusCode);
		}
	}//end report()

	/**
	 * GET the storage report as CSV.
	 *
	 * @return DataDisplayResponse|JSONResponse The CSV, or 403.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-8.4
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function export(): DataDisplayResponse|JSONResponse {
		try {
			$report = $this->storage->report(readerId: (string) $this->userId);
		} catch (StorageReportRefusedException $refused) {
			return $this->failure(message: $refused->getMessage(), statusCode: $refused->statusCode);
		}

		$response = new DataDisplayResponse(data: StorageReportService::csv(report: $report), statusCode: Http::STATUS_OK);
		$response->addHeader(name: 'Content-Type', value: 'text/csv; charset=utf-8');
		$response->addHeader(name: 'Content-Disposition', value: 'attachment; filename="storage-' . $report['date'] . '.csv"');
		return $response;
	}//end export()
}//end class
