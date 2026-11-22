<?php

/**
 * BroadenShippedGroupWidgetRules Repair Step
 *
 * The group widget rules older versions seeded (medewerkers, managers,
 * ict-beheer, hrm) list only Nextcloud dashboard widgets. A rule lists every
 * widget its group may use, and a widget outside it is hidden on every
 * dashboard the member opens, so those members lost LaunchPad's own text,
 * tile and header widgets everywhere. This step widens such a rule to what a
 * fresh install seeds, but only while it still holds exactly what was
 * shipped: a rule an administrator edited is kept.
 *
 * @category Repair
 * @package  OCA\LaunchPad\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use DateTime;
use OCA\LaunchPad\Db\RoleFeaturePermission;
use OCA\LaunchPad\Db\RoleFeaturePermissionMapper;
use OCA\LaunchPad\Support\WidgetTypeList;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Widen the untouched shipped group widget rules to LaunchPad's own widgets.
 *
 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
 */
class BroadenShippedGroupWidgetRules implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param RoleFeaturePermissionMapper $permMapper The widget rule store.
	 * @param LoggerInterface             $logger     PSR-3 logger.
	 */
	public function __construct(
		private readonly RoleFeaturePermissionMapper $permMapper,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
	 */
	public function getName(): string {
		return 'Let the members of the shipped group widget rules keep LaunchPad widgets';
	}//end getName()

	/**
	 * Run the step, one shipped group at a time. A failure is a warning,
	 * never a failed upgrade.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
	 */
	public function run(IOutput $output): void {
		foreach (SeedRolePermissions::SHIPPED_GROUP_RULES as $groupId => $shipped) {
			try {
				$this->broaden(groupId: $groupId, shipped: $shipped, output: $output);
			} catch (Throwable $e) {
				$this->logger->warning(message: '[BroadenShippedGroupWidgetRules] could not widen the ' . $groupId . ' widget rule: ' . $e->getMessage());
				$output->warning(message: 'The ' . $groupId . ' widget rule was not widened: ' . $e->getMessage());
			}
		}
	}//end run()

	/**
	 * Widen one group's rule when it still holds exactly what was shipped.
	 *
	 * @param string                                             $groupId The group.
	 * @param array{allowed: list<string>, denied: list<string>} $shipped What older versions seeded for it.
	 * @param IOutput                                            $output  Migration output stream.
	 *
	 * @return void
	 */
	private function broaden(string $groupId, array $shipped, IOutput $output): void {
		try {
			$rule = $this->permMapper->findByGroupId(groupId: $groupId);
		} catch (DoesNotExistException) {
			return;
		}

		if (self::holdsExactly(rule: $rule, shipped: $shipped) === false) {
			$output->info(message: 'An administrator changed the ' . $groupId . ' widget rule, so LaunchPad keeps it.');
			return;
		}

		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$rule->setAllowedWidgets(json_encode(value: (new WidgetTypeList())->withOwnTypes(widgetIds: $shipped['allowed'])));
		$rule->setUpdatedAt((new DateTime())->format(format: 'c'));
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->permMapper->update(entity: $rule);
		$output->info(message: 'The ' . $groupId . ' widget rule now lets its members use LaunchPad widgets.');
	}//end broaden()

	/**
	 * Whether the rule allows and denies exactly the shipped widgets, in any order.
	 *
	 * @param RoleFeaturePermission                              $rule    The rule.
	 * @param array{allowed: list<string>, denied: list<string>} $shipped The shipped lists.
	 *
	 * @return bool True when nothing was changed.
	 */
	private static function holdsExactly(RoleFeaturePermission $rule, array $shipped): bool {
		$allowed = $rule->getAllowedWidgetsDecoded();
		$denied = $rule->getDeniedWidgetsDecoded();
		$shippedAllowed = $shipped['allowed'];
		$shippedDenied = $shipped['denied'];
		sort($allowed);
		sort($denied);
		sort($shippedAllowed);
		sort($shippedDenied);

		return $allowed === $shippedAllowed && $denied === $shippedDenied;
	}//end holdsExactly()
}//end class
