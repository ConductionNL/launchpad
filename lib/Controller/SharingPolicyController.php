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
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md
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
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
 */
class SharingPolicyController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest             $request        The request.
	 * @param AdminSettingsService $settings       Stores the two settings.
	 * @param ResponseHelper       $responseHelper Builds the JSON response envelopes.
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
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
	 * @param string|null $sharingAllowed          `everyone`, `groups` or `none`.
	 * @param bool|null   $publicLinksAllowed      Whether public links may be created.
	 * @param bool|null   $kioskScreenLinksAllowed Whether a Sharing tab may make a screen link (REQ-KIOSK-007).
	 *
	 * @return JSONResponse The update confirmation, or 400 for an unknown choice.
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
	 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-2
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(
		string|null $sharingAllowed = null,
		bool|null $publicLinksAllowed = null,
		bool|null $kioskScreenLinksAllowed = null,
	): JSONResponse {
		try {
			$this->settings->updateSettings(
				settings: [
					'sharingAllowed'          => $sharingAllowed,
					'publicLinksAllowed'      => $publicLinksAllowed,
					'kioskScreenLinksAllowed' => $kioskScreenLinksAllowed,
				]
			);

			return $this->responseHelper->success(data: ['status' => 'ok']);
		} catch (InvalidArgumentException $e) {
			return $this->responseHelper->invalidArgument(message: $e->getMessage());
		} catch (\Exception $e) {
			return $this->responseHelper->error(exception: $e);
		}
	}//end update()

	/**
	 * Set where a leaver's dashboards go (REQ-SHARE-016): their manager or
	 * not, and the person otherwise ('' for nobody).
	 *
	 * @param bool|null $manager     Whether a leaver's manager comes first; null reads as no.
	 * @param string    $fallbackUid The person otherwise; '' for nobody.
	 *
	 * @return JSONResponse The update confirmation.
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateLeaverHandover(?bool $manager = null, string $fallbackUid = ''): JSONResponse {
		try {
			$this->settings->updateSettings(
				settings: ['leaverHandover' => ['manager' => $manager === true, 'fallbackUid' => $fallbackUid]]
			);

			return $this->responseHelper->success(data: ['status' => 'ok']);
		} catch (\Exception $e) {
			return $this->responseHelper->error(exception: $e);
		}
	}//end updateLeaverHandover()
}//end class
