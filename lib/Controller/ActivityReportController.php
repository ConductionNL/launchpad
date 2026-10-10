<?php

/**
 * ActivityReportController
 *
 * The HTTP face of activity reporting (REQ-DWMS-005, REQ-DWMS-006) and the
 * colleague activity widget (REQ-DWMS-007). Every gate lives in the
 * services; this controller maps their refusals onto status codes. A
 * refusal for a switched-off capability says so, so the screen can tell the
 * administrator how to turn it on.
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
use OCA\LaunchPad\Service\ActivityReportService;
use OCA\LaunchPad\Service\ColleagueActivityService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;

/**
 * Activity reports and the colleague activity feed.
 */
class ActivityReportController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request The request.
	 * @param ActivityReportService $reports Activity reporting.
	 * @param ColleagueActivityService $colleagues Colleague activity.
	 * @param IGroupManager $groups Answers who is an administrator.
	 * @param string|null $userId The caller, from the session.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function __construct(
		IRequest $request,
		private readonly ActivityReportService $reports,
		private readonly ColleagueActivityService $colleagues,
		private readonly IGroupManager $groups,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `GET /api/admin/activity-reporting`: the switch and its purpose.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function policy(): JSONResponse {
		if ($this->isAdmin() === false) {
			return new JSONResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse($this->reports->getPolicy());
	}//end policy()

	/**
	 * `PUT /api/admin/activity-reporting`: turn it on with a purpose, or off.
	 *
	 * @param bool $enabled Whether to turn it on.
	 * @param string $purpose Why; required to turn it on.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updatePolicy(bool $enabled = false, string $purpose = ''): JSONResponse {
		if ($this->isAdmin() === false) {
			return new JSONResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}

		try {
			return new JSONResponse($this->reports->setPolicy(enabled: $enabled, purpose: $purpose));
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}//end updatePolicy()

	/**
	 * `GET /api/activity-report`: one person's activity for a period.
	 *
	 * @param string $userId Whose activity; empty means the caller's own.
	 * @param string $from First day, Y-m-d.
	 * @param string $until Last day, Y-m-d.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	#[NoAdminRequired]
	public function report(string $userId = '', string $from = '', string $until = ''): JSONResponse {
		try {
			$result = $this->reports->report(
				readerId: (string)$this->userId,
				subjectId: $this->subject(userId: $userId),
				from: $from,
				until: $until
			);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		if (isset($result['error']) === true) {
			return new JSONResponse($result, Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse($result);
	}//end report()

	/**
	 * `GET /api/activity-report/export`: the same report as CSV.
	 *
	 * @param string $userId Whose activity; empty means the caller's own.
	 * @param string $from First day, Y-m-d.
	 * @param string $until Last day, Y-m-d.
	 *
	 * @return DataDownloadResponse|JSONResponse
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	#[NoAdminRequired]
	public function export(string $userId = '', string $from = '', string $until = ''): DataDownloadResponse|JSONResponse {
		try {
			$result = $this->reports->export(
				readerId: (string)$this->userId,
				subjectId: $this->subject(userId: $userId),
				from: $from,
				until: $until
			);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		if (isset($result['error']) === true) {
			return new JSONResponse($result, Http::STATUS_FORBIDDEN);
		}

		return new DataDownloadResponse((string)$result['csv'], (string)$result['filename'], 'text/csv');
	}//end export()

	/**
	 * `GET /api/colleague-activity`: recent colleague activity for the caller.
	 *
	 * @param int $limit How many entries at most.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	#[NoAdminRequired]
	public function colleagues(int $limit = 20): JSONResponse {
		return new JSONResponse($this->colleagues->recent(readerId: (string)$this->userId, limit: $limit));
	}//end colleagues()

	/**
	 * Whether the caller is an administrator.
	 *
	 * @return bool
	 */
	private function isAdmin(): bool {
		return ($this->userId !== null && $this->groups->isAdmin($this->userId) === true);
	}//end isAdmin()

	/**
	 * The person a report is about: the named one, or the caller.
	 *
	 * @param string $userId The named person, possibly empty.
	 *
	 * @return string
	 */
	private function subject(string $userId): string {
		if (trim($userId) === '') {
			return (string)$this->userId;
		}

		return trim($userId);
	}//end subject()
}//end class
