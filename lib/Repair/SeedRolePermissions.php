<?php

/**
 * SeedRolePermissions Repair Step
 *
 * Inserts example RoleFeaturePermission and RoleLayoutDefault rows on a
 * fresh install when neither table contains any rows yet. Re-running this
 * step on an instance that already has data is a no-op (idempotent).
 *
 * Seed objects are derived from the `design.md` Seed Data section for the
 * `role-based-content` change.
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
 * @spec openspec/changes/role-based-content/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use DateTime;
use OCA\LaunchPad\Db\RoleFeaturePermission;
use OCA\LaunchPad\Db\RoleFeaturePermissionMapper;
use OCA\LaunchPad\Db\RoleLayoutDefault;
use OCA\LaunchPad\Db\RoleLayoutDefaultMapper;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Seed role-feature-permission and role-layout-default rows on first install.
 *
 * @spec openspec/changes/role-based-content/tasks.md#task-2
 */
class SeedRolePermissions implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param RoleFeaturePermissionMapper $permMapper Permission mapper.
	 * @param RoleLayoutDefaultMapper $defMapper Layout-default mapper.
	 * @param LoggerInterface $logger PSR-3 logger.
	 */
	public function __construct(
		private readonly RoleFeaturePermissionMapper $permMapper,
		private readonly RoleLayoutDefaultMapper $defMapper,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/role-based-content/tasks.md#task-2
	 */
	public function getName(): string {
		return 'Seed default role-feature permissions and role-layout defaults';
	}//end getName()

	/**
	 * Run the seeding step.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/role-based-content/tasks.md#task-2
	 */
	public function run(IOutput $output): void {
		$existing = $this->permMapper->findAll();
		if (count(value: $existing) > 0) {
			$this->logger->debug(
				message: 'SeedRolePermissions: rows already present, skipping seed.'
			);
			$output->info(message: 'Role-feature permissions already seeded — skipping.');
			return;
		}

		$this->seedPermissions(output: $output);
		$this->seedLayoutDefaults(output: $output);
		$this->logger->info(
			message: 'SeedRolePermissions: seeded default role-feature permissions and layout defaults.'
		);
		$output->info(message: 'Role-feature permissions and layout defaults seeded successfully.');
	}//end run()

	/**
	 * Seed the five default RoleFeaturePermission rows.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/role-based-content/tasks.md#task-2
	 */
	private function seedPermissions(IOutput $output): void {
		$seeds = [
			[
				'groupId' => 'medewerkers',
				'name' => 'Medewerker widget-rechten',
				'description' => 'Standaard widget-toegang voor alle medewerkers van de gemeente',
				'allowedWidgets' => ['activity', 'recommendations', 'notes', 'calendar'],
				'deniedWidgets' => [],
				'priorityWeights' => ['activity' => 10, 'recommendations' => 8, 'calendar' => 6, 'notes' => 4],
			],
			[
				'groupId' => 'managers',
				'name' => 'Manager widget-rechten',
				'description' => 'Uitgebreide widget-toegang voor teamleiders en afdelingshoofden',
				'allowedWidgets' => ['activity', 'recommendations', 'notes', 'calendar', 'analytics_dashboard', 'user_status'],
				'deniedWidgets' => [],
				'priorityWeights' => ['analytics_dashboard' => 10, 'activity' => 8, 'user_status' => 6, 'calendar' => 5],
			],
			[
				'groupId' => 'ict-beheer',
				'name' => 'ICT-beheer widget-rechten',
				'description' => 'Widget-toegang voor de ICT-beheerdersgroep met systeemoverzicht',
				'allowedWidgets' => ['activity', 'recommendations', 'notes', 'calendar', 'system_monitor', 'user_status'],
				'deniedWidgets' => [],
				'priorityWeights' => ['system_monitor' => 10, 'activity' => 8, 'user_status' => 7],
			],
			[
				'groupId' => 'hrm',
				'name' => 'HRM widget-rechten',
				'description' => 'Widget-toegang voor de afdeling Human Resource Management',
				'allowedWidgets' => ['activity', 'recommendations', 'notes', 'calendar', 'user_status'],
				'deniedWidgets' => ['system_monitor'],
				'priorityWeights' => ['user_status' => 10, 'calendar' => 9, 'activity' => 7],
			],
			[
				'groupId' => 'default',
				'name' => 'Standaard widget-rechten',
				'description' => 'Basistoegang voor gebruikers zonder specifieke groepsindeling',
				'allowedWidgets' => ['activity', 'recommendations'],
				'deniedWidgets' => [],
				'priorityWeights' => ['recommendations' => 10, 'activity' => 8],
			],
		];

		$now = (new DateTime())->format(format: 'c');
		foreach ($seeds as $seed) {
			$entity = new RoleFeaturePermission();
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$entity->setGroupId($seed['groupId']);
			$entity->setName($seed['name']);
			$entity->setDescription($seed['description']);
			$entity->setAllowedWidgets(json_encode(value: $seed['allowedWidgets']));
			$entity->setDeniedWidgets(json_encode(value: $seed['deniedWidgets']));
			$entity->setPriorityWeights(json_encode(value: $seed['priorityWeights']));
			$entity->setCreatedAt($now);
			$entity->setUpdatedAt($now);
			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$this->permMapper->insert(entity: $entity);
			$output->info(message: 'Seeded RoleFeaturePermission for group: ' . $seed['groupId']);
		}//end foreach
	}//end seedPermissions()

	/**
	 * Seed the five default RoleLayoutDefault rows.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/role-based-content/tasks.md#task-2
	 */
	private function seedLayoutDefaults(IOutput $output): void {
		$seeds = [
			[
				'groupId' => 'medewerkers',
				'widgetId' => 'activity',
				'name' => 'Medewerker — activiteiten',
				'gridX' => 0,
				'gridY' => 0,
				'gridWidth' => 6,
				'gridHeight' => 5,
				'sortOrder' => 1,
				'isCompulsory' => 0,
				'description' => 'Activiteitenoverzicht linksboven voor medewerkers',
			],
			[
				'groupId' => 'medewerkers',
				'widgetId' => 'recommendations',
				'name' => 'Medewerker — aanbevelingen',
				'gridX' => 6,
				'gridY' => 0,
				'gridWidth' => 6,
				'gridHeight' => 5,
				'sortOrder' => 2,
				'isCompulsory' => 0,
				'description' => 'Persoonlijke aanbevelingen rechtsboven voor medewerkers',
			],
			[
				'groupId' => 'managers',
				'widgetId' => 'analytics_dashboard',
				'name' => 'Manager — analysedashboard',
				'gridX' => 0,
				'gridY' => 0,
				'gridWidth' => 8,
				'gridHeight' => 6,
				'sortOrder' => 1,
				'isCompulsory' => 1,
				'description' => 'Verplicht analysedashboard als eerste widget voor managers',
			],
			[
				'groupId' => 'managers',
				'widgetId' => 'activity',
				'name' => 'Manager — activiteiten',
				'gridX' => 8,
				'gridY' => 0,
				'gridWidth' => 4,
				'gridHeight' => 6,
				'sortOrder' => 2,
				'isCompulsory' => 0,
				'description' => 'Teamactiviteiten rechtsbovenhoek voor managers',
			],
			[
				'groupId' => 'ict-beheer',
				'widgetId' => 'system_monitor',
				'name' => 'ICT-beheer — systeemmonitor',
				'gridX' => 0,
				'gridY' => 0,
				'gridWidth' => 12,
				'gridHeight' => 4,
				'sortOrder' => 1,
				'isCompulsory' => 1,
				'description' => 'Verplicht systeemoverzicht als prominente balk voor ICT-beheer',
			],
		];

		$now = (new DateTime())->format(format: 'c');
		foreach ($seeds as $seed) {
			$entity = new RoleLayoutDefault();
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$entity->setGroupId($seed['groupId']);
			$entity->setWidgetId($seed['widgetId']);
			$entity->setName($seed['name']);
			$entity->setGridX($seed['gridX']);
			$entity->setGridY($seed['gridY']);
			$entity->setGridWidth($seed['gridWidth']);
			$entity->setGridHeight($seed['gridHeight']);
			$entity->setSortOrder($seed['sortOrder']);
			$entity->setIsCompulsory($seed['isCompulsory']);
			$entity->setDescription($seed['description']);
			$entity->setCreatedAt($now);
			$entity->setUpdatedAt($now);
			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$this->defMapper->insert(entity: $entity);
			$output->info(message: 'Seeded RoleLayoutDefault: ' . $seed['groupId'] . '/' . $seed['widgetId']);
		}//end foreach
	}//end seedLayoutDefaults()
}//end class
