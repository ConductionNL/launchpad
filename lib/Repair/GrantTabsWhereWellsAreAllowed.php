<?php

/**
 * GrantTabsWhereWellsAreAllowed Repair Step
 *
 * A widget rule lists every widget its group may add, so a widget type that
 * is new in this version is missing from every rule stored before it. The
 * Tabs widget is a well with tabs (layout-widgets-replace-multi-tile D1):
 * a rule that lets its group add wells (`container`) and does not deny Tabs
 * gets Tabs too. A rule without wells, or one that denies Tabs, is kept.
 * Idempotent.
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
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
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Let a group that may add wells add Tabs as well.
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 */
class GrantTabsWhereWellsAreAllowed implements IRepairStep {
	/**
	 * The well's widget type.
	 *
	 * @var string
	 */
	public const WELL = 'container';

	/**
	 * The Tabs widget's type.
	 *
	 * @var string
	 */
	public const TABS = 'tabs';

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
	 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
	 */
	public function getName(): string {
		return 'Let groups that may add wells add the Tabs widget';
	}//end getName()

	/**
	 * Run the step over every widget rule. A failure is a warning, never a
	 * failed upgrade.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
	 */
	public function run(IOutput $output): void {
		try {
			$rules = $this->permMapper->findAll();
		} catch (Throwable $e) {
			$this->logger->warning(message: '[GrantTabsWhereWellsAreAllowed] could not read the widget rules: ' . $e->getMessage());
			$output->warning(message: 'The widget rules were not given Tabs: ' . $e->getMessage());
			return;
		}

		$granted = 0;
		foreach ($rules as $rule) {
			if ($this->grant(rule: $rule) === true) {
				$granted++;
			}
		}

		$output->info(message: sprintf('Tabs added to %d widget rule(s) that allow wells.', $granted));
	}//end run()

	/**
	 * Add Tabs to one rule when it allows wells and neither allows nor denies Tabs.
	 *
	 * @param RoleFeaturePermission $rule The rule.
	 *
	 * @return bool Whether the rule was changed.
	 */
	private function grant(RoleFeaturePermission $rule): bool {
		$allowed = $rule->getAllowedWidgetsDecoded();
		if (in_array(needle: self::WELL, haystack: $allowed, strict: true) === false
			|| in_array(needle: self::TABS, haystack: $allowed, strict: true) === true
			|| in_array(needle: self::TABS, haystack: $rule->getDeniedWidgetsDecoded(), strict: true) === true
		) {
			return false;
		}

		try {
			$allowed[] = self::TABS;
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$rule->setAllowedWidgets(json_encode(value: array_values($allowed)));
			$rule->setUpdatedAt((new DateTime())->format(format: 'c'));
			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$this->permMapper->update(entity: $rule);
		} catch (Throwable $e) {
			$this->logger->warning(message: '[GrantTabsWhereWellsAreAllowed] could not update a widget rule: ' . $e->getMessage());
			return false;
		}

		return true;
	}//end grant()
}//end class
