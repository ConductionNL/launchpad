<?php

/**
 * Action Authorization Service
 *
 * Implements the ADR-023 action-level authorization pattern: each controller
 * method declares an action name (e.g. "item.publish") and delegates the
 * authorization decision to this service, which resolves the action against
 * an admin-configured matrix stored in IAppConfig.
 *
 * This service is the canonical place to enforce action RBAC. Per ADR-023:
 *   - Data RBAC (who can read/write which objects) is OpenRegister's job.
 *   - Action RBAC (who can invoke which controller method) is this service.
 *   - Admin-only operations (editing the matrix itself, app config, backup/
 *     restore, integrations, credentials) bypass this service and use
 *     #[AuthorizedAdminSetting(LaunchPadAdmin::class)] at the route layer.
 *
 * Controllers call `requireAction` which throws OCSForbiddenException when
 * the caller's groups don't intersect the matrix entry for the action.
 *
 * Admin users always pass. Administrative actions (analytics, the tile
 * catalogue, metadata field definitions, conditional rules, publication
 * workflow, version history, org navigation) default to ["admin"] only.
 * The ordinary end-user surface — listing/viewing dashboards, per-user
 * preferences, and creating/editing the dashboards a user may already
 * touch — ships with the {@see self::GROUP_ALL_USERS} sentinel so a fresh
 * install is usable by non-admins; every one of those actions is still
 * gated per object by PermissionService. The admin narrows or broadens
 * either way via the settings UI.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/architecture/adr-023-action-authorization.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;

/**
 * Action-level authorization service.
 *
 * Enforces ADR-023 action RBAC: controllers call requireAction with a
 * dot-separated action name; this service checks the admin-configured
 * action-to-group mapping stored in IAppConfig.
 *
 * @spec openspec/architecture/adr-023-action-authorization.md
 */
class ActionAuthService {
	private const CONFIG_KEY = 'actions';

	/**
	 * Sentinel entry meaning "every authenticated user may perform this
	 * action".
	 *
	 * Nextcloud has no real group that contains every account, so a matrix
	 * that can only name groups cannot express "ordinary users may list
	 * their dashboards" — which is why the shipped default locked out every
	 * non-admin on a fresh install. This sentinel closes that gap.
	 *
	 * The `@` prefix is deliberate: Nextcloud group IDs created through the
	 * UI or the provisioning API never start with it, so the sentinel can
	 * never be shadowed by (or shadow) a real group.
	 *
	 * Object-level authorization is unaffected — every action that mutates
	 * a dashboard still goes through PermissionService, which checks
	 * ownership / share level per object.
	 *
	 * @var string
	 */
	public const GROUP_ALL_USERS = '@all';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig IAppConfig for reading/writing the matrix.
	 * @param IGroupManager $groupManager Group manager for admin checks only.
	 * @param AdminTemplateService $adminTemplateService Template service whose getUserGroupIdsFor()
	 *                                                   is the single-source-of-truth group-IDs
	 *                                                   accessor (REQ-TMPL-013).
	 */
	public function __construct(
		private IAppConfig $appConfig,
		private IGroupManager $groupManager,
		private AdminTemplateService $adminTemplateService,
	) {
	}//end __construct()

	/**
	 * Require that the user may perform the named action.
	 *
	 * Admin users always pass (break-glass). Non-admins pass only when any
	 * of their groups intersects the matrix entry for the action.
	 *
	 * @param IUser $user The authenticated user.
	 * @param string $action Dot-separated action name (e.g. "item.publish").
	 *
	 * @return void
	 *
	 * @throws OCSForbiddenException When the user's groups don't match the action's allowed groups.
	 *
	 * @spec openspec/architecture/adr-023-action-authorization.md
	 */
	public function requireAction(IUser $user, string $action): void {
		// Admin always passes — break-glass for ops / debugging.
		if ($this->groupManager->isAdmin($user->getUID()) === true) {
			return;
		}

		$allowedGroups = $this->getAllowedGroups(action: $action);

		// The "@all" sentinel grants the action to every authenticated
		// user, which no real Nextcloud group can express. Checked BEFORE
		// the admin-only short-circuit below so an entry of
		// ["admin", "@all"] reads as "everyone", not "admin only".
		if (in_array(self::GROUP_ALL_USERS, $allowedGroups, true) === true) {
			return;
		}

		// An "admin"-only entry means non-admins never pass (admin already
		// returned above). Empty entry means nobody is allowed.
		if (count($allowedGroups) === 0 || $allowedGroups === ['admin']) {
			throw new OCSForbiddenException(
				"Action '{$action}' requires admin rights"
			);
		}

		// Delegate to the single-source-of-truth wrapper (REQ-TMPL-013).
		$userGroups = $this->adminTemplateService->getUserGroupIdsFor($user->getUID());

		// Exclude "admin" and the "@all" sentinel from the matrix entry
		// before intersection — both were already resolved above and neither
		// is a real group membership to match against.
		$nonAdminAllowed = array_values(
			array_diff($allowedGroups, ['admin', self::GROUP_ALL_USERS])
		);

		if (count(array_intersect($userGroups, $nonAdminAllowed)) === 0) {
			throw new OCSForbiddenException(
				"Action '{$action}' not allowed for your groups"
			);
		}

	}//end requireAction()

	/**
	 * Get the list of groups allowed to perform the action.
	 *
	 * Returns the matrix entry for the action, or ["admin"] as the safe
	 * default when the action is not in the matrix.
	 *
	 * @param string $action Dot-separated action name.
	 *
	 * @return array<int, string>
	 *
	 * @spec openspec/architecture/adr-023-action-authorization.md
	 */
	public function getAllowedGroups(string $action): array {
		$matrix = $this->getMatrix();
		return $matrix[$action] ?? ['admin'];
	}//end getAllowedGroups()

	/**
	 * Get the full action-to-groups matrix.
	 *
	 * Reads the JSON-encoded matrix from IAppConfig. Missing or malformed
	 * config returns an empty array (default-deny — admin-only for every
	 * action since getAllowedGroups falls back to ["admin"]).
	 *
	 * @return array<string, array<int, string>>
	 *
	 * @spec openspec/architecture/adr-023-action-authorization.md
	 */
	public function getMatrix(): array {
		$json = $this->appConfig->getValueString(Application::APP_ID, self::CONFIG_KEY, '{}');

		try {
			$decoded = json_decode($json, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			return [];
		}

		if (is_array($decoded) === false) {
			return [];
		}

		// Normalize: discard any non-array values + any non-string group entries.
		$matrix = [];
		foreach ($decoded as $action => $groups) {
			if (is_string($action) === false || is_array($groups) === false) {
				continue;
			}

			$clean = [];
			foreach ($groups as $g) {
				if (is_string($g) === true && $g !== '') {
					$clean[] = $g;
				}
			}

			$matrix[$action] = array_values(array_unique($clean));
		}

		return $matrix;
	}//end getMatrix()

	/**
	 * Set the full action-to-groups matrix.
	 *
	 * Caller MUST enforce admin-only before invoking (this method does not
	 * gate writes — it's called from an admin-only settings endpoint).
	 *
	 * @param array<string, array<int, string>> $matrix The new matrix.
	 *
	 * @return void
	 *
	 * @throws \JsonException When the matrix cannot be encoded.
	 *
	 * @spec openspec/architecture/adr-023-action-authorization.md
	 */
	public function setMatrix(array $matrix): void {
		// Normalize on write — same shape as getMatrix returns.
		$normalized = [];
		foreach ($matrix as $action => $groups) {
			if (is_string($action) === false || is_array($groups) === false) {
				continue;
			}

			$clean = [];
			foreach ($groups as $g) {
				if (is_string($g) === true && $g !== '') {
					$clean[] = $g;
				}
			}

			$normalized[$action] = array_values(array_unique($clean));
		}

		$json = json_encode($normalized, flags: JSON_THROW_ON_ERROR);
		$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_KEY, $json);

	}//end setMatrix()
}//end class
