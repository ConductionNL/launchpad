<?php

/**
 * AdminSettingsController
 *
 * HTTP entry point for admin-only group-priority order management
 * (REQ-ASET-012, REQ-ASET-013, REQ-ASET-014).
 *
 * Exposes two endpoints:
 *  - `GET  /api/admin/groups` returning `{active, inactive, allKnown}`
 *    so the admin UI can render both columns in one round-trip and
 *    surface stale (deleted) group IDs in the active list.
 *  - `POST /api/admin/groups` body `{groups: string[]}` replacing the
 *    persisted setting wholesale (no merge — UI sends the full ordered
 *    list after every drag).
 *
 * It also carries the administrator CSS endpoints (REQ-THM-003):
 * `GET`/`PUT /api/admin/custom-css` for all of LaunchPad and
 * `PUT /api/admin/custom-css/dashboards/{uuid}` for one dashboard; a save
 * answers with what was stored and what the sanitiser dropped.
 *
 * Both endpoints are admin-only via `#[AuthorizedAdminSetting]` (with
 * `assertAdmin()`'s `IGroupManager::isAdmin` check kept as defense in
 * depth) because even the GET reveals every group on the system
 * (privacy concern).
 *
 * It also serves the search shortcut list of the admin settings
 * (`GET` and `PUT /api/admin/search-shortcuts`, tile-quick-search),
 * admin-only and behind the ADR-023 action check.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Kernel\Contracts\SearchShortcutServiceInterface;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\CustomCssService;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;

/**
 * Admin-only controller for the group-priority order, search shortcut and
 * administrator CSS settings.
 */
class AdminSettingsController extends Controller {
	use RespondsWithErrors;

	/**
	 * Constructor.
	 *
	 * @param IRequest $request The HTTP request.
	 * @param AdminSettingsService $settingsService Persisted-settings service.
	 * @param IGroupManager $groupManager Group manager (admin check + listing).
	 * @param RequestGuard $guard The session and administrator check.
	 * @param SearchShortcutServiceInterface $shortcuts The search shortcut list.
	 * @param CustomCssService $css Stores the cleaned administrator CSS.
	 */
	public function __construct(
		IRequest $request,
		private readonly AdminSettingsService $settingsService,
		private readonly IGroupManager $groupManager,
		private readonly RequestGuard $guard,
		private readonly SearchShortcutServiceInterface $shortcuts,
		private readonly CustomCssService $css,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Handle `GET /api/admin/groups` — REQ-ASET-013.
	 *
	 * Returns the disjoint exhaustive split `{active, inactive, allKnown}`:
	 *  - `active` — the persisted `group_order` list, in admin-chosen
	 *    order. Stale IDs (no longer in Nextcloud) remain so admin can
	 *    see and remove them.
	 *  - `inactive` — every Nextcloud group ID NOT in `active`, sorted
	 *    by displayName (case-insensitive).
	 *  - `allKnown` — full `{id, displayName}` list for the UI to render
	 *    display names without a second round-trip. Stale IDs MUST NOT
	 *    appear here (no display name available).
	 *
	 * @return JSONResponse Either the success payload or HTTP 403 when
	 *                      the caller is not an administrator.
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function listGroups(): JSONResponse {
		$forbidden = $this->assertAdmin();
		if ($forbidden !== null) {
			return $forbidden;
		}

		$allKnown = $this->knownGroups();
		$active = $this->settingsService->getGroupOrder();

		return ResponseHelper::success(
			data: [
				'active' => $active,
				'inactive' => self::inactiveGroups(allKnown: $allKnown, active: $active),
				'allKnown' => $allKnown,
			]
		);
	}//end listGroups()

	/**
	 * Every Nextcloud group as `{id, displayName}`.
	 *
	 * @return list<array{id: string, displayName: string}> The groups.
	 */
	private function knownGroups(): array {
		$allKnown = [];
		foreach ($this->groupManager->search(search: '') as $group) {
			$allKnown[] = [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			];
		}

		return $allKnown;
	}//end knownGroups()

	/**
	 * The known group ids that are not in the active order, sorted by
	 * display name (case-insensitive).
	 *
	 * `inactive` = allKnown - active (stale active IDs MUST NOT appear in
	 * inactive — REQ-ASET-013 disjoint scenario).
	 *
	 * @param list<array{id: string, displayName: string}> $allKnown Every group.
	 * @param list<string>                                 $active   The active group order.
	 *
	 * @return list<string> The inactive group ids.
	 */
	private static function inactiveGroups(array $allKnown, array $active): array {
		$activeSet = array_flip(array: $active);
		$inactive = [];
		$displayNameById = [];
		foreach ($allKnown as $row) {
			$displayNameById[$row['id']] = $row['displayName'];
			if (array_key_exists(key: $row['id'], array: $activeSet) === false) {
				$inactive[] = $row['id'];
			}
		}

		usort(
			array: $inactive,
			callback: static function (string $aId, string $bId) use ($displayNameById): int {
				$aName = strtolower(string: $displayNameById[$aId] ?? $aId);
				$bName = strtolower(string: $displayNameById[$bId] ?? $bId);
				return strcmp(string1: $aName, string2: $bName);
			}
		);

		return $inactive;
	}//end inactiveGroups()

	/**
	 * Handle `POST /api/admin/groups` — REQ-ASET-012, REQ-ASET-014.
	 *
	 * Body: `{"groups": ["id", ...]}`. Replaces the persisted
	 * `group_order` setting wholesale. Validation:
	 *  - `groups` MUST be present and an array.
	 *  - Every element MUST be a non-empty string.
	 *  - Duplicate IDs are deduplicated (first occurrence kept).
	 *  - Unknown (not currently in Nextcloud) IDs are tolerated — they
	 *    remain in the persisted setting per REQ-ASET-014.
	 *
	 * @param mixed $groups The raw `groups` payload from the request body.
	 *
	 * @return JSONResponse HTTP 200 with `{status: 'ok'}` on success,
	 *                      400 on validation failure, 403 for non-admins.
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateGroupOrder(mixed $groups = null): JSONResponse {
		$forbidden = $this->assertAdmin();
		if ($forbidden !== null) {
			return $forbidden;
		}

		if (is_array($groups) === false) {
			return $this->failure(message: 'groups must be an array', statusCode: Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->settingsService->setGroupOrder(groupIds: $groups);
		} catch (InvalidArgumentException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}

		return ResponseHelper::success(
			data: [
				'status' => 'ok',
				'groupOrder' => $this->settingsService->getGroupOrder(),
			]
		);
	}//end updateGroupOrder()

	/**
	 * The shortcut list (administrators).
	 *
	 * @return JSONResponse `{shortcuts: [...]}`; 401 without a user, 403 for a
	 *                      non-administrator or a denied ADR-023 action.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function listSearchShortcuts(): JSONResponse {
		$refused = ($this->guard->refusal() ?? $this->guard->actionRefusal(action: 'search-shortcut.list'));
		if ($refused !== null) {
			return $refused;
		}

		return new JSONResponse(data: ['shortcuts' => $this->shortcuts->getShortcuts()]);
	}//end listSearchShortcuts()

	/**
	 * Replace the shortcut list (administrators).
	 *
	 * @param array|null $shortcuts The new list.
	 *
	 * @return JSONResponse `{shortcuts: [...]}`, 400, 401 or 403.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function saveSearchShortcuts(?array $shortcuts = null): JSONResponse {
		$refused = ($this->guard->refusal() ?? $this->guard->actionRefusal(action: 'search-shortcut.save'));
		if ($refused !== null) {
			return $refused;
		}

		try {
			$saved = $this->shortcuts->saveShortcuts(raw: ($shortcuts ?? []));
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(data: ['shortcuts' => $saved]);
	}//end saveSearchShortcuts()

	/**
	 * Read the stored CSS for all of LaunchPad.
	 *
	 * @return JSONResponse `{customCss}`, or 401/403 for a non-administrator.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function showCustomCss(): JSONResponse {
		$refusal = $this->guard->refusal();
		if ($refusal !== null) {
			return $refusal;
		}

		return ResponseHelper::success(data: ['customCss' => $this->css->getGlobalCss()]);
	}//end showCustomCss()

	/**
	 * Clean, scope and store the CSS for all of LaunchPad.
	 *
	 * @param mixed $customCss The CSS; '' clears it.
	 *
	 * @return JSONResponse `{customCss, removed}`, 400 for a value that is no
	 *                      string or is over 20 KB, 401/403 for a non-administrator.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateCustomCss(mixed $customCss = null): JSONResponse {
		$refusal = $this->guard->refusal();
		if ($refusal !== null) {
			return $refusal;
		}

		if (is_string($customCss) === false) {
			return $this->failure(message: 'customCss must be a string', statusCode: Http::STATUS_BAD_REQUEST);
		}

		try {
			return ResponseHelper::success(data: $this->css->setGlobalCss(css: $customCss));
		} catch (InvalidArgumentException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}
	}//end updateCustomCss()

	/**
	 * Clean, scope and store the CSS for one dashboard.
	 *
	 * @param string $uuid      The dashboard's UUID.
	 * @param mixed  $customCss The CSS; '' clears it.
	 *
	 * @return JSONResponse `{customCss, removed}`, 400 for a value that is no
	 *                      string or is over 20 KB, 404 for an unknown
	 *                      dashboard, 401/403 for a non-administrator.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateDashboardCustomCss(string $uuid, mixed $customCss = null): JSONResponse {
		$refusal = $this->guard->refusal();
		if ($refusal !== null) {
			return $refusal;
		}

		if (is_string($customCss) === false) {
			return $this->failure(message: 'customCss must be a string', statusCode: Http::STATUS_BAD_REQUEST);
		}

		try {
			return ResponseHelper::success(data: $this->css->setDashboardCss(uuid: $uuid, css: $customCss));
		} catch (DoesNotExistException) {
			return $this->failure(message: 'Dashboard not found', statusCode: Http::STATUS_NOT_FOUND);
		} catch (InvalidArgumentException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}
	}//end updateDashboardCustomCss()

	/**
	 * Assert that the active session belongs to an administrator.
	 *
	 * Both endpoints are admin-only because the inactive list reveals
	 * every group on the system (REQ-ASET-014). The base controller
	 * routing already requires authentication; this guard only adds the
	 * admin check on top.
	 *
	 * @return JSONResponse|null `null` when the caller is an admin, or
	 *                           a 403 response that the calling action
	 *                           should return verbatim.
	 */
	private function assertAdmin(): ?JSONResponse {
		$refusal = $this->guard->refusal();
		if ($refusal === null || $refusal->getStatus() === Http::STATUS_UNAUTHORIZED) {
			return $refusal;
		}

		return ResponseHelper::forbidden();
	}//end assertAdmin()
}//end class
