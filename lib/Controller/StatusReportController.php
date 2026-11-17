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
	 * The request fields that describe a body of work, with their defaults.
	 *
	 * `register`, `schema`, `title`, `deadline` (YYYY-MM-DD), `statusProperty`
	 * and `dueProperty` are strings; `filters` (property filters on its
	 * objects) and `closedValues` (the statuses that mean closed) are lists.
	 */
	private const SUBJECT_FIELDS = [
		'register'       => '',
		'schema'         => '',
		'title'          => '',
		'deadline'       => '',
		'filters'        => [],
		'statusProperty' => '',
		'closedValues'   => [],
		'dueProperty'    => '',
	];

	/**
	 * POST a body of work (the SUBJECT_FIELDS); answers the prefilled report, not kept.
	 *
	 * @return JSONResponse The draft, or 400, 401 or 409.
	 *
	 * @no-admin-idor-exempt No stored object is addressed and nothing is kept; the objects counted are read through
	 *   OpenRegister's object search as the caller, so the caller's own read rights scope them.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.2
	 */
	#[NoAdminRequired]
	public function draft(): JSONResponse {
		return $this->answer(work: fn (string $userId): JSONResponse => new JSONResponse($this->reports->draft(subject: $this->subject())));
	}//end draft()

	/**
	 * POST a body of work (the SUBJECT_FIELDS) with the person's `health` rating and
	 * `summary`, each omitted to keep the computed one; keeps the report.
	 *
	 * @return JSONResponse 201 with the kept report, or 400, 401, 409 or 503.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.3
	 */
	#[NoAdminRequired]
	public function keep(): JSONResponse {
		return $this->answer(
			work: fn (string $userId): JSONResponse => new JSONResponse(
				$this->reports->keep(
					userId: $userId,
					subject: $this->subject(),
					health: $this->optionalString(key: 'health'),
					summary: $this->optionalString(key: 'summary')
				),
				Http::STATUS_CREATED
			)
		);
	}//end keep()

	/**
	 * Run the work for the signed-in user; 401 without one, a refusal answers its own status.
	 *
	 * @param callable(string): JSONResponse $work The work.
	 *
	 * @return JSONResponse
	 */
	private function answer(callable $work): JSONResponse {
		if ($this->userId === null) {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			return $work($this->userId);
		} catch (StatusReportRefusedException $refused) {
			return $this->failure(message: $refused->getMessage(), statusCode: $refused->statusCode);
		}
	}//end answer()

	/**
	 * The body of work as sent: every SUBJECT_FIELDS key, a string or a list as its default is.
	 *
	 * @return array<string, mixed>
	 */
	private function subject(): array {
		$subject = [];
		foreach (self::SUBJECT_FIELDS as $key => $default) {
			$value         = $this->request->getParam($key, $default);
			$subject[$key] = $default;
			if (is_array($default) === true && is_array($value) === true) {
				$subject[$key] = $value;
			} else if (is_array($default) === false && is_scalar($value) === true) {
				$subject[$key] = (string) $value;
			}
		}

		return $subject;
	}//end subject()

	/**
	 * A request field as a string, or null when it is absent or not a string.
	 *
	 * @param string $key The field.
	 *
	 * @return string|null
	 */
	private function optionalString(string $key): ?string {
		$value = $this->request->getParam($key);
		if (is_string($value) === false) {
			return null;
		}

		return $value;
	}//end optionalString()
}//end class
