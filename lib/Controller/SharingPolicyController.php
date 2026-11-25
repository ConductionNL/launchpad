<?php

/**
 * SharingPolicyController
 *
 * Admin-only: who may share dashboards (Everyone, Only these groups, Nobody)
 * and whether public links may be created (REQ-SHARE-015, board LpBeheerDelen).
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
 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Sets the sharing policy. Read back through GET /api/admin/settings.
 *
 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
 */
class SharingPolicyController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest             $request        The request.
	 * @param AdminSettingsService $settings       Stores the two settings.
	 * @param ResponseHelper       $responseHelper Builds the JSON response envelopes.
	 *
	 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
	 */
	public function __construct(
		IRequest $request,
		private readonly AdminSettingsService $settings,
		private readonly ResponseHelper $responseHelper,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Set who may share and whether public links may be created.
	 *
	 * A partial patch: a value left out stays as it is.
	 *
	 * @param string|null $sharingAllowed     `everyone`, `groups` or `none`.
	 * @param bool|null   $publicLinksAllowed Whether public links may be created.
	 *
	 * @return JSONResponse The update confirmation, or 400 for an unknown choice.
	 *
	 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(
		string|null $sharingAllowed = null,
		bool|null $publicLinksAllowed = null,
	): JSONResponse {
		try {
			$this->settings->updateSettings(
				settings: ['sharingAllowed' => $sharingAllowed, 'publicLinksAllowed' => $publicLinksAllowed]
			);

			return $this->responseHelper->success(data: ['status' => 'ok']);
		} catch (InvalidArgumentException $e) {
			return $this->responseHelper->invalidArgument(message: $e->getMessage());
		} catch (\Exception $e) {
			return $this->responseHelper->error(exception: $e);
		}
	}//end update()
}//end class
