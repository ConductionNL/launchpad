<?php

/**
 * GrantTabsWhereWellsAreAllowedTest
 *
 * A rule that allows wells gets the Tabs widget; one without wells, or one
 * that denies Tabs, is kept; running twice changes nothing more
 * (layout-widgets-replace-multi-tile D1).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Db\RoleFeaturePermission;
use OCA\LaunchPad\Db\RoleFeaturePermissionMapper;
use OCA\LaunchPad\Repair\GrantTabsWhereWellsAreAllowed;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tabs follows the well in the widget rules.
 */
class GrantTabsWhereWellsAreAllowedTest extends TestCase {

	/**
	 * A stored widget rule.
	 *
	 * @param list<string> $allowed The allowed widgets.
	 * @param list<string> $denied  The denied widgets.
	 *
	 * @return RoleFeaturePermission The rule.
	 */
	private function rule(array $allowed, array $denied=[]): RoleFeaturePermission {
		$rule = new RoleFeaturePermission();
		$rule->setAllowedWidgets(json_encode(value: $allowed));
		$rule->setDeniedWidgets(json_encode(value: $denied));
		return $rule;
	}//end rule()

	/**
	 * Only the rule with wells and without a Tabs denial changes, once.
	 *
	 * @return void
	 */
	public function testTabsFollowsTheWell(): void {
		$withWells = $this->rule(allowed: ['activity', 'text', 'container']);
		$noWells   = $this->rule(allowed: ['activity', 'text']);
		$denies    = $this->rule(allowed: ['container'], denied: ['tabs']);
		$mapper    = $this->createMock(originalClassName: RoleFeaturePermissionMapper::class);
		$mapper->method('findAll')->willReturn([$withWells, $noWells, $denies]);
		$updated = [];
		$mapper->method('update')->willReturnCallback(
			function (RoleFeaturePermission $rule) use (&$updated): RoleFeaturePermission {
				$updated[] = $rule;
				return $rule;
			}
		);
		$step = new GrantTabsWhereWellsAreAllowed(
			permMapper: $mapper,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$step->run(output: $this->createMock(originalClassName: IOutput::class));
		$step->run(output: $this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: [$withWells], actual: $updated);
		$this->assertSame(expected: ['activity', 'text', 'container', 'tabs'], actual: $withWells->getAllowedWidgetsDecoded());
		$this->assertSame(expected: ['activity', 'text'], actual: $noWells->getAllowedWidgetsDecoded());
		$this->assertSame(expected: ['container'], actual: $denies->getAllowedWidgetsDecoded());
	}//end testTabsFollowsTheWell()

	/**
	 * A store that cannot be read is a warning, not a failed upgrade.
	 *
	 * @return void
	 */
	public function testAnUnreadableStoreIsAWarning(): void {
		$mapper = $this->createMock(originalClassName: RoleFeaturePermissionMapper::class);
		$mapper->method('findAll')->willThrowException(new \RuntimeException('db down'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning');

		(new GrantTabsWhereWellsAreAllowed(
			permMapper: $mapper,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		))->run(output: $output);
	}//end testAnUnreadableStoreIsAWarning()
}//end class
