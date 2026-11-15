<?php

/**
 * StatusReportController
 *
 * Prefill and keep a status report on a body of work (REQ-DWMS-004).
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
use OCA\LaunchPad\Service\StatusReport\StatusReportRefusedException;
use OCA\LaunchPad\Service\StatusReport\StatusReportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Status reports for the signed-in user.
 *
 * The objects are read through OpenRegister as the caller, so a report
 * counts only what the caller may see; the author is the caller.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-004-a-dated-status-report-with-a-health-rating
 */
class StatusReportController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest            $request The request.
	 * @param StatusReportService $reports Prefills and keeps.
	 * @param string|null         $userId  The signed-in user.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.2
	 */
	public function __construct(
		IRequest $request,
		private readonly StatusReportService $reports,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * POST a body of work; answers the prefilled report, not kept.
	 *
	 * @param string             $register       The register.
	 * @param string             $schema         The schema.
	 * @param string             $title          The name of the body of work.
	 * @param string             $deadline       Its deadline (YYYY-MM-DD), if any.
	 * @param array<string,mixed> $filters       Property filters on its objects.
	 * @param string             $statusProperty The property holding an item's status.
	 * @param list<string>       $closedValues   The statuses that mean closed.
	 * @param string             $dueProperty    The property holding an item's due date.
	 *
	 * @return JSONResponse The draft, or 400, 401 or 409.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.2
	 */
	#[NoAdminRequired]
	public function draft(
		string $register='',
		string $schema='',
		string $title='',
		string $deadline='',
		array $filters=[],
		string $statusProperty='',
		array $closedValues=[],
		string $dueProperty='',
	): JSONResponse {
		if ($this->userId === null) {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		$subject = compact('register', 'schema', 'title', 'deadline', 'filters', 'statusProperty', 'closedValues', 'dueProperty');
		try {
			return new JSONResponse($this->reports->draft(subject: $subject));
		} catch (StatusReportRefusedException $refused) {
			return $this->failure(message: $refused->getMessage(), statusCode: $refused->statusCode);
		}
	}//end draft()

	/**
	 * POST a body of work with the person's rating and summary; keeps the report.
	 *
	 * @param string             $register       The register.
	 * @param string             $schema         The schema.
	 * @param string             $title          The name of the body of work.
	 * @param string             $deadline       Its deadline (YYYY-MM-DD), if any.
	 * @param array<string,mixed> $filters       Property filters on its objects.
	 * @param string             $statusProperty The property holding an item's status.
	 * @param list<string>       $closedValues   The statuses that mean closed.
	 * @param string             $dueProperty    The property holding an item's due date.
	 * @param string|null        $health         The rating chosen; omitted keeps the computed one.
	 * @param string|null        $summary        The summary written; omitted keeps the computed one.
	 *
	 * @return JSONResponse 201 with the kept report, or 400, 401, 409 or 503.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3
	 */
	#[NoAdminRequired]
	public function keep(
		string $register='',
		string $schema='',
		string $title='',
		string $deadline='',
		array $filters=[],
		string $statusProperty='',
		array $closedValues=[],
		string $dueProperty='',
		?string $health=null,
		?string $summary=null,
	): JSONResponse {
		if ($this->userId === null) {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		$subject = compact('register', 'schema', 'title', 'deadline', 'filters', 'statusProperty', 'closedValues', 'dueProperty');
		try {
			$kept = $this->reports->keep(userId: $this->userId, subject: $subject, health: $health, summary: $summary);
		} catch (StatusReportRefusedException $refused) {
			return $this->failure(message: $refused->getMessage(), statusCode: $refused->statusCode);
		}

		return new JSONResponse($kept, Http::STATUS_CREATED);
	}//end keep()
}//end class
