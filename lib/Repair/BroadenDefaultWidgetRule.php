<?php

/**
 * BroadenDefaultWidgetRule Repair Step
 *
 * Instances installed before REQ-RFP-011 carry a `default` widget rule that
 * allows only the activity and recommendations widgets, so every user without
 * a group rule was refused LaunchPad's own widgets. This step widens that rule
 * to what a fresh install seeds, but only while it still holds exactly the
 * shipped allow-list and no denials: a rule an administrator edited is kept.
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
 * Widen the untouched shipped `default` widget rule to LaunchPad's own widgets.
 *
 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
 */
class BroadenDefaultWidgetRule implements IRepairStep {
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
		return 'Let every user add LaunchPad widgets under the shipped default widget rule';
	}//end getName()

	/**
	 * Run the step. A failure is a warning, never a failed upgrade.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
	 */
	public function run(IOutput $output): void {
		try {
			$rule = $this->permMapper->findByGroupId(groupId: RoleFeaturePermission::GROUP_DEFAULT);
		} catch (DoesNotExistException) {
			$output->info(message: 'There is no default widget rule, so there is nothing to widen.');
			return;
		} catch (Throwable $e) {
			$this->logger->warning(message: '[BroadenDefaultWidgetRule] could not read the default widget rule: ' . $e->getMessage());
			$output->warning(message: 'The default widget rule was not widened: ' . $e->getMessage());
			return;
		}

		if ($this->isShippedRule(rule: $rule) === false) {
			$output->info(message: 'An administrator changed the default widget rule, so LaunchPad keeps it.');
			return;
		}

		try {
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$rule->setAllowedWidgets(json_encode(value: (new WidgetTypeList())->defaultRuleWidgets()));
			$rule->setUpdatedAt((new DateTime())->format(format: 'c'));
			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$this->permMapper->update(entity: $rule);
		} catch (Throwable $e) {
			$this->logger->warning(message: '[BroadenDefaultWidgetRule] could not widen the default widget rule: ' . $e->getMessage());
			$output->warning(message: 'The default widget rule was not widened: ' . $e->getMessage());
			return;
		}

		$output->info(message: 'The default widget rule now lets every user add LaunchPad widgets.');
	}//end run()

	/**
	 * Whether the rule still holds exactly what older versions shipped.
	 *
	 * @param RoleFeaturePermission $rule The `default` rule.
	 *
	 * @return bool True when it allows only the shipped widgets and denies none.
	 */
	private function isShippedRule(RoleFeaturePermission $rule): bool {
		$allowed = $rule->getAllowedWidgetsDecoded();
		sort($allowed);
		$shipped = WidgetTypeList::DEFAULT_RULE_DASHBOARD_WIDGETS;
		sort($shipped);

		return $allowed === $shipped && $rule->getDeniedWidgetsDecoded() === [];
	}//end isShippedRule()
}//end class
