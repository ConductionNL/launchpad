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
use OCA\LaunchPad\Support\WidgetTypeList;
use OCP\AppFramework\Db\DoesNotExistException;
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
	 * The Nextcloud dashboard widgets each shipped group rule allows and
	 * denies. The seeded rule allows these plus every LaunchPad widget type;
	 * older versions seeded only these, and BroadenShippedGroupWidgetRules
	 * widens a rule that still holds exactly them.
	 *
	 * @var array<string, array{allowed: list<string>, denied: list<string>}>
	 */
	public const SHIPPED_GROUP_RULES = [
		'medewerkers' => [
			'allowed' => ['activity', 'recommendations', 'notes', 'calendar'],
			'denied' => [],
		],
		'managers' => [
			'allowed' => ['activity', 'recommendations', 'notes', 'calendar', 'analytics_dashboard', 'user_status'],
			'denied' => [],
		],
		'ict-beheer' => [
			'allowed' => ['activity', 'recommendations', 'notes', 'calendar', 'system_monitor', 'user_status'],
			'denied' => [],
		],
		'hrm' => [
			'allowed' => ['activity', 'recommendations', 'notes', 'calendar', 'user_status'],
			'denied' => ['system_monitor'],
		],
	];

	/**
	 * Default role-feature permissions, one per group.
	 */
	private const PERMISSION_SEEDS = [
		[
			'groupId' => 'medewerkers',
			'name' => 'Medewerker widget-rechten',
			'description' => 'Standaard widget-toegang voor alle medewerkers van de gemeente',
			'allowedWidgets' => self::SHIPPED_GROUP_RULES['medewerkers']['allowed'],
			'deniedWidgets' => self::SHIPPED_GROUP_RULES['medewerkers']['denied'],
			'priorityWeights' => ['activity' => 10, 'recommendations' => 8, 'calendar' => 6, 'notes' => 4],
		],
		[
			'groupId' => 'managers',
			'name' => 'Manager widget-rechten',
			'description' => 'Uitgebreide widget-toegang voor teamleiders en afdelingshoofden',
			'allowedWidgets' => self::SHIPPED_GROUP_RULES['managers']['allowed'],
			'deniedWidgets' => self::SHIPPED_GROUP_RULES['managers']['denied'],
			'priorityWeights' => ['analytics_dashboard' => 10, 'activity' => 8, 'user_status' => 6, 'calendar' => 5],
		],
		[
			'groupId' => 'ict-beheer',
			'name' => 'ICT-beheer widget-rechten',
			'description' => 'Widget-toegang voor de ICT-beheerdersgroep met systeemoverzicht',
			'allowedWidgets' => self::SHIPPED_GROUP_RULES['ict-beheer']['allowed'],
			'deniedWidgets' => self::SHIPPED_GROUP_RULES['ict-beheer']['denied'],
			'priorityWeights' => ['system_monitor' => 10, 'activity' => 8, 'user_status' => 7],
		],
		[
			'groupId' => 'hrm',
			'name' => 'HRM widget-rechten',
			'description' => 'Widget-toegang voor de afdeling Human Resource Management',
			'allowedWidgets' => self::SHIPPED_GROUP_RULES['hrm']['allowed'],
			'deniedWidgets' => self::SHIPPED_GROUP_RULES['hrm']['denied'],
			'priorityWeights' => ['user_status' => 10, 'calendar' => 9, 'activity' => 7],
		],
		[
			'groupId' => RoleFeaturePermission::GROUP_DEFAULT,
			'name' => 'Standaard widget-rechten',
			'description' => 'Basistoegang voor gebruikers zonder specifieke groepsindeling',
			'allowedWidgets' => WidgetTypeList::DEFAULT_RULE_DASHBOARD_WIDGETS,
			'deniedWidgets' => [],
			'priorityWeights' => ['recommendations' => 10, 'activity' => 8],
		],
	];

	/**
	 * Default widget layout per group.
	 */
	private const LAYOUT_DEFAULT_SEEDS = [
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
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
	 */
	private function seedPermissions(IOutput $output): void {
		$now = (new DateTime())->format(format: 'c');
		foreach (self::PERMISSION_SEEDS as $seed) {
			$entity = new RoleFeaturePermission();
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$entity->setGroupId($seed['groupId']);
			$entity->setName($seed['name']);
			$entity->setDescription($seed['description']);
			$allowed = (new WidgetTypeList())->withOwnTypes(widgetIds: $seed['allowedWidgets']);

			$entity->setAllowedWidgets(json_encode(value: $allowed));
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
	 * Seed the five default RoleLayoutDefault rows. A row whose group and
	 * widget already have a layout default is left as it is: the pair is
	 * unique (`launchpad_rld_group_widget`), so inserting it again would
	 * abort the step on an instance whose rules were removed but whose
	 * layout defaults were kept.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/role-based-content/tasks.md#task-2
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
	 */
	private function seedLayoutDefaults(IOutput $output): void {
		$now = (new DateTime())->format(format: 'c');
		foreach (self::LAYOUT_DEFAULT_SEEDS as $seed) {
			if ($this->hasLayoutDefault(groupId: $seed['groupId'], widgetId: $seed['widgetId']) === true) {
				$output->info(message: 'RoleLayoutDefault already present: ' . $seed['groupId'] . '/' . $seed['widgetId']);
				continue;
			}

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

	/**
	 * Whether a layout default exists for the group and widget.
	 *
	 * @param string $groupId  The group.
	 * @param string $widgetId The widget.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
	 */
	private function hasLayoutDefault(string $groupId, string $widgetId): bool {
		try {
			$this->defMapper->findByGroupAndWidget(groupId: $groupId, widgetId: $widgetId);
		} catch (DoesNotExistException) {
			return false;
		}

		return true;
	}//end hasLayoutDefault()
}//end class
