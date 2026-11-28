<?php

/**
 * Unit tests for BroadenShippedGroupWidgetRules.
 *
 * @category Test
 * @package  Unit\Repair
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Db\RoleFeaturePermission;
use OCA\LaunchPad\Db\RoleFeaturePermissionMapper;
use OCA\LaunchPad\Repair\BroadenShippedGroupWidgetRules;
use OCA\LaunchPad\Repair\SeedRolePermissions;
use OCA\LaunchPad\Support\WidgetTypeList;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * An untouched shipped group widget rule is widened, an edited one is kept.
 *
 * @covers \OCA\LaunchPad\Repair\BroadenShippedGroupWidgetRules
 * @uses \OCA\LaunchPad\Db\RoleFeaturePermission
 * @uses \OCA\LaunchPad\Support\WidgetTypeList
 */
class BroadenShippedGroupWidgetRulesTest extends TestCase {
	/**
	 * A group rule with the given lists.
	 *
	 * @param string             $groupId The group.
	 * @param array<int, string> $allowed The allowed widgets.
	 * @param array<int, string> $denied  The denied widgets.
	 *
	 * @return RoleFeaturePermission
	 */
	private static function rule(string $groupId, array $allowed, array $denied=[]): RoleFeaturePermission {
		$rule = new RoleFeaturePermission();
		$rule->setGroupId($groupId);
		$rule->setAllowedWidgets(json_encode($allowed));
		$rule->setDeniedWidgets(json_encode($denied));
		return $rule;
	}//end rule()

	/**
	 * Run the step over the given rules by group.
	 *
	 * @param array<string, RoleFeaturePermission> $rules The stored rules.
	 * @param array<int, RoleFeaturePermission>    $updated Receives the updated rules.
	 *
	 * @return void
	 */
	private function runStep(array $rules, array &$updated): void {
		$mapper = $this->createMock(originalClassName: RoleFeaturePermissionMapper::class);
		$mapper->method('findByGroupId')->willReturnCallback(static function (string $groupId) use ($rules): RoleFeaturePermission {
			if (isset($rules[$groupId]) === false) {
				throw new DoesNotExistException('none');
			}
			return $rules[$groupId];
		});
		$mapper->method('update')->willReturnCallback(static function (RoleFeaturePermission $rule) use (&$updated): RoleFeaturePermission {
			$updated[] = $rule;
			return $rule;
		});

		(new BroadenShippedGroupWidgetRules($mapper, $this->createMock(originalClassName: LoggerInterface::class)))
			->run($this->createMock(originalClassName: IOutput::class));
	}//end runStep()

	/**
	 * The hrm rule as older versions seeded it, in any order, gains every
	 * LaunchPad widget and keeps its denial; the other groups too.
	 *
	 * @return void
	 */
	public function testTheShippedRulesAreWidened(): void {
		$rules = [];
		foreach (SeedRolePermissions::SHIPPED_GROUP_RULES as $groupId => $shipped) {
			$rules[$groupId] = self::rule(groupId: $groupId, allowed: array_reverse($shipped['allowed']), denied: $shipped['denied']);
		}
		$updated = [];

		$this->runStep(rules: $rules, updated: $updated);

		$this->assertCount(4, $updated);
		$hrm = $rules['hrm'];
		$this->assertSame(
			expected: (new WidgetTypeList())->withOwnTypes(widgetIds: SeedRolePermissions::SHIPPED_GROUP_RULES['hrm']['allowed']),
			actual: $hrm->getAllowedWidgetsDecoded()
		);
		$this->assertContains(needle: 'text', haystack: $hrm->getAllowedWidgetsDecoded());
		$this->assertContains(needle: 'tile', haystack: $hrm->getAllowedWidgetsDecoded());
		$this->assertSame(expected: ['system_monitor'], actual: $hrm->getDeniedWidgetsDecoded());
		$this->assertNotNull(actual: $hrm->getUpdatedAt());
	}//end testTheShippedRulesAreWidened()

	/**
	 * An edited rule (an extra widget, a removed one or another denial) is
	 * kept, and a missing one is skipped, while the untouched ones widen.
	 *
	 * @return void
	 */
	public function testEditedAndMissingRulesAreKept(): void {
		$rules = [
			'medewerkers' => self::rule(groupId: 'medewerkers', allowed: ['activity', 'recommendations', 'notes', 'calendar', 'text']),
			'managers' => self::rule(groupId: 'managers', allowed: ['activity']),
			'hrm' => self::rule(groupId: 'hrm', allowed: SeedRolePermissions::SHIPPED_GROUP_RULES['hrm']['allowed']),
		];
		$updated = [];

		$this->runStep(rules: $rules, updated: $updated);

		$this->assertSame(expected: [], actual: $updated);
		$this->assertSame(expected: ['activity'], actual: $rules['managers']->getAllowedWidgetsDecoded());
	}//end testEditedAndMissingRulesAreKept()

	/**
	 * A failure on one group is a warning, and the next group is still widened.
	 *
	 * @return void
	 */
	public function testAFailureIsAWarningAndTheRestRuns(): void {
		$mapper = $this->createMock(originalClassName: RoleFeaturePermissionMapper::class);
		$managers = SeedRolePermissions::SHIPPED_GROUP_RULES['managers'];
		$mapper->method('findByGroupId')->willReturnCallback(static function (string $groupId) use ($managers): RoleFeaturePermission {
			if ($groupId === 'medewerkers') {
				throw new RuntimeException('db gone');
			}
			if ($groupId === 'managers') {
				return self::rule(groupId: 'managers', allowed: $managers['allowed']);
			}
			throw new DoesNotExistException('none');
		});
		$mapper->expects($this->once())->method('update')->willReturnArgument(0);
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with($this->stringContains(string: 'db gone'));

		(new BroadenShippedGroupWidgetRules($mapper, $this->createMock(originalClassName: LoggerInterface::class)))->run($output);
	}//end testAFailureIsAWarningAndTheRestRuns()
}//end class
