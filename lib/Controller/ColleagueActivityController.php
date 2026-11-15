<?php

/**
 * ColleagueActivityController
 *
 * The data of the colleague activity widget (REQ-DWMS-007).
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
use OCA\LaunchPad\Service\ColleagueActivity\ColleagueActivityService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Recent colleague activity for the signed-in reader.
 *
 * The reader is taken from the session; no parameter names another person,
 * so nobody reads somebody else's stream through it.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-007-recent-colleague-activity-filtered-by-what-you-may-see
 */
class ColleagueActivityController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest                 $request  The request.
	 * @param ColleagueActivityService $activity The filtered activity.
	 * @param string|null              $userId   The signed-in user.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1
	 */
	public function __construct(
		IRequest $request,
		private readonly ColleagueActivityService $activity,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET the recent activity of other people the caller may see: `limit` entries over `days` days.
	 *
	 * @param int $limit How many entries to list.
	 * @param int $days  How many days back.
	 *
	 * @return JSONResponse {available, days, entries, total, counts, actors}, or 401.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1
	 */
	#[NoAdminRequired]
	public function index(int $limit=ColleagueActivityService::DEFAULT_LIMIT, int $days=ColleagueActivityService::DEFAULT_DAYS): JSONResponse {
		if ($this->userId === null) {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse($this->activity->recent(readerId: $this->userId, limit: $limit, days: $days));
	}//end index()
}//end class
