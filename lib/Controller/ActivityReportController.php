<?php

/**
 * ActivityReportController
 *
 * A person's activity report, its CSV export and the administrator's switch (REQ-DWMS-005, REQ-DWMS-006).
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
use OCA\LaunchPad\Service\ActivityReport\ActivityReportingPolicy;
use OCA\LaunchPad\Service\ActivityReport\ActivityReportRefusedException;
use OCA\LaunchPad\Service\ActivityReport\ActivityReportService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;

/**
 * Activity reports and their switch.
 *
 * The caller is read from the session. Whose activity may be read is the
 * service's decision: anybody their own, an administrator another person's
 * once activity reporting is on.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-005-activity-reporting-names-its-purpose
 */
class ActivityReportController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest                $request      The request.
	 * @param ActivityReportService   $reports      Builds and gates the reports.
	 * @param ActivityReportingPolicy $policy       The administrator's switch.
	 * @param IGroupManager           $groupManager Whether the caller is an administrator.
	 * @param string|null             $userId       The signed-in user.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.4
	 */
	public function __construct(
		IRequest $request,
		private readonly ActivityReportService $reports,
		private readonly ActivityReportingPolicy $policy,
		private readonly IGroupManager $groupManager,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET a person's activity over a period: `user` (default the caller), `from`, `to`.
	 *
	 * @param string $user Whose activity; empty for the caller's own.
	 * @param string $from The first day (YYYY-MM-DD).
	 * @param string $to   The last day (YYYY-MM-DD).
	 *
	 * @return JSONResponse The report, or 400, 401 or 403 with the reason.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.5
	 */
	#[NoAdminRequired]
	public function report(string $user='', string $from='', string $to=''): JSONResponse {
		if ($this->userId === null) {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			return new JSONResponse($this->build(user: $user, from: $from, to: $to));
		} catch (ActivityReportRefusedException $refused) {
			return $this->refusal(refused: $refused);
		}
	}//end report()

	/**
	 * GET the same report as CSV, with the same numbers.
	 *
	 * @param string $user Whose activity; empty for the caller's own.
	 * @param string $from The first day (YYYY-MM-DD).
	 * @param string $to   The last day (YYYY-MM-DD).
	 *
	 * @return DataDisplayResponse|JSONResponse The CSV, or 400, 401 or 403 with the reason.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.6
	 */
	#[NoAdminRequired]
	public function export(string $user='', string $from='', string $to=''): DataDisplayResponse|JSONResponse {
		if ($this->userId === null) {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			$report = $this->build(user: $user, from: $from, to: $to);
		} catch (ActivityReportRefusedException $refused) {
			return $this->refusal(refused: $refused);
		}

		$name     = sprintf('activity-%s-%s-%s.csv', preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $report['subject']), $report['from'], $report['to']);
		$response = new DataDisplayResponse(data: ActivityReportService::csv(report: $report), statusCode: Http::STATUS_OK);
		// After construction: DataDisplayResponse sets an inline disposition of its own.
		$response->addHeader(name: 'Content-Type', value: 'text/csv; charset=utf-8');
		$response->addHeader(name: 'Content-Disposition', value: 'attachment; filename="' . $name . '"');
		return $response;
	}//end export()

	/**
	 * GET whether activity reporting on named people is on, and its purpose.
	 *
	 * @return JSONResponse {enabled, purpose}.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.1
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function policy(): JSONResponse {
		return new JSONResponse(['enabled' => $this->policy->isEnabled(), 'purpose' => $this->policy->purpose()]);
	}//end policy()

	/**
	 * PUT the switch: `enabled`, and the `purpose` it serves (required to turn it on).
	 *
	 * @param bool        $enabled Whether to turn it on.
	 * @param string|null $purpose The purpose; omitted keeps the recorded one.
	 *
	 * @return JSONResponse {enabled, purpose}, or 400 naming what is missing.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.1
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updatePolicy(bool $enabled=false, ?string $purpose=null): JSONResponse {
		try {
			return new JSONResponse($this->policy->configure(enabled: $enabled, purpose: $purpose));
		} catch (InvalidArgumentException $invalid) {
			return $this->failure(message: $invalid->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}
	}//end updatePolicy()

	/**
	 * The report for the caller.
	 *
	 * @param string $user Whose activity.
	 * @param string $from The first day.
	 * @param string $to   The last day.
	 *
	 * @return array<string,mixed> The report.
	 *
	 * @throws ActivityReportRefusedException When the caller may not have it.
	 */
	private function build(string $user, string $from, string $to): array {
		$reader = (string) $this->userId;

		return $this->reports->report(
			readerId: $reader,
			readerIsAdmin: $this->groupManager->isAdmin($reader),
			subjectId: $user,
			from: $from,
			to: $to
		);
	}//end build()

	/**
	 * The answer to a refused report: its status, its sentence, and whether reporting is off.
	 *
	 * @param ActivityReportRefusedException $refused The refusal.
	 *
	 * @return JSONResponse The answer.
	 */
	private function refusal(ActivityReportRefusedException $refused): JSONResponse {
		return new JSONResponse(
			['error' => $refused->getMessage(), 'activityReportingEnabled' => $this->policy->isEnabled()],
			$refused->statusCode
		);
	}//end refusal()
}//end class
