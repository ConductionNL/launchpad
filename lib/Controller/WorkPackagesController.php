<?php

/**
 * WorkPackagesController
 *
 * The data of the work packages widget: the signed-in person's open
 * OpenProject work packages, read through the official OpenProject
 * integration.
 *
 * @category Controller
 * @package  OCA\LaunchPad\Controller
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\WorkPackages\WorkPackagesService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Open work packages for the signed-in person.
 *
 * The person is taken from the session and OpenProject is asked with their
 * own token, so OpenProject decides which work packages they may see.
 */
class WorkPackagesController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest            $request      The request.
	 * @param WorkPackagesService $workPackages The OpenProject reader.
	 * @param string|null         $userId       The signed-in user.
	 */
	public function __construct(
		IRequest $request,
		private readonly WorkPackagesService $workPackages,
		private readonly ?string $userId,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * GET the open work packages: the person's own (`scope=mine`) or a
	 * project's (`scope=project&project=<identifier>`), due date first.
	 *
	 * @param string $scope   `mine` or `project`.
	 * @param string $project The project identifier for scope `project`.
	 * @param int    $limit   How many to list.
	 *
	 * @return JSONResponse {state, workPackages, connectUrl?}, or 401.
	 *
	 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md#requirement-the-widget-lists-open-work-packages-due-date-first-req-wp-001
	 */
	#[NoAdminRequired]
	public function index(string $scope='mine', string $project='', int $limit=4): JSONResponse {
		if ($this->userId === null) {
			return $this->failure(message: 'not_logged_in', statusCode: Http::STATUS_UNAUTHORIZED);
		}

		$knownScope = 'mine';
		if ($scope === 'project') {
			$knownScope = 'project';
		}

		return new JSONResponse(
			$this->workPackages->openWorkPackages(
				userId: $this->userId,
				scope: $knownScope,
				project: $project,
				limit: $limit
			)
		);
	}//end index()
}//end class
