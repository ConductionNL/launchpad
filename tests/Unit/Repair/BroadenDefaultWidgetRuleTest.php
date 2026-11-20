<?php

/**
 * BroadenDefaultWidgetRule test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Db\RoleFeaturePermission;
use OCA\LaunchPad\Db\RoleFeaturePermissionMapper;
use OCA\LaunchPad\Repair\BroadenDefaultWidgetRule;
use OCA\LaunchPad\Support\WidgetTypeList;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Migration\IOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The shipped `default` widget rule is widened once, an edited one is kept.
 *
 * @covers \OCA\LaunchPad\Repair\BroadenDefaultWidgetRule
 */
class BroadenDefaultWidgetRuleTest extends TestCase {

	/**
	 * The widget rule store.
	 *
	 * @var RoleFeaturePermissionMapper&MockObject
	 */
	private RoleFeaturePermissionMapper&MockObject $mapper;

	/**
	 * The repair output.
	 *
	 * @var IOutput&MockObject
	 */
	private IOutput&MockObject $output;

	/**
	 * Fresh doubles per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->mapper = $this->createMock(originalClassName: RoleFeaturePermissionMapper::class);
		$this->output = $this->createMock(originalClassName: IOutput::class);
	}//end setUp()

	/**
	 * The step under test.
	 *
	 * @return BroadenDefaultWidgetRule
	 */
	private function step(): BroadenDefaultWidgetRule {
		return new BroadenDefaultWidgetRule($this->mapper, $this->createMock(originalClassName: LoggerInterface::class));
	}//end step()

	/**
	 * A `default` rule with the given lists.
	 *
	 * @param array<int, string> $allowed The allowed widgets.
	 * @param array<int, string> $denied  The denied widgets.
	 *
	 * @return RoleFeaturePermission
	 */
	private static function rule(array $allowed, array $denied=[]): RoleFeaturePermission {
		$rule = new RoleFeaturePermission();
		$rule->setGroupId(RoleFeaturePermission::GROUP_DEFAULT);
		$rule->setAllowedWidgets(json_encode($allowed));
		$rule->setDeniedWidgets(json_encode($denied));
		return $rule;
	}//end rule()

	/**
	 * The step has a name.
	 *
	 * @return void
	 */
	public function testName(): void {
		$this->assertStringContainsString(needle: 'default widget rule', haystack: $this->step()->getName());
	}//end testName()

	/**
	 * The rule older versions shipped, in any order, gains every LaunchPad widget.
	 *
	 * @return void
	 */
	public function testTheShippedRuleIsWidened(): void {
		$rule = self::rule(allowed: ['recommendations', 'activity']);
		$this->mapper->method('findByGroupId')->with(RoleFeaturePermission::GROUP_DEFAULT)->willReturn($rule);
		$this->mapper->expects($this->once())->method('update')->with($this->identicalTo(value: $rule))->willReturnArgument(0);

		$this->step()->run($this->output);

		$allowed = $rule->getAllowedWidgetsDecoded();
		$this->assertSame(expected: (new WidgetTypeList())->defaultRuleWidgets(), actual: $allowed);
		$this->assertContains(needle: 'text', haystack: $allowed);
		$this->assertContains(needle: 'activity', haystack: $allowed);
		$this->assertNotNull(actual: $rule->getUpdatedAt());
	}//end testTheShippedRuleIsWidened()

	/**
	 * A rule the administrator edited is kept: an extra widget or a denial.
	 *
	 * @return array<string, array{0: array<int, string>, 1: array<int, string>}>
	 */
	public static function editedRules(): array {
		return [
			'an extra widget' => [['activity', 'recommendations', 'notes'], []],
			'a removed widget' => [['activity'], []],
			'a denial' => [['activity', 'recommendations'], ['activity']],
		];
	}//end editedRules()

	/**
	 * An edited rule is not touched.
	 *
	 * @param array<int, string> $allowed The allowed widgets.
	 * @param array<int, string> $denied  The denied widgets.
	 *
	 * @return void
	 */
	#[DataProvider('editedRules')]
	public function testAnEditedRuleIsKept(array $allowed, array $denied): void {
		$this->mapper->method('findByGroupId')->willReturn(self::rule(allowed: $allowed, denied: $denied));
		$this->mapper->expects($this->never())->method('update');
		$this->output->expects($this->once())->method('info')->with($this->stringContains(string: 'keeps it'));

		$this->step()->run($this->output);
	}//end testAnEditedRuleIsKept()

	/**
	 * Without a `default` rule there is nothing to widen.
	 *
	 * @return void
	 */
	public function testNoRuleIsANoOp(): void {
		$this->mapper->method('findByGroupId')->willThrowException(new DoesNotExistException('none'));
		$this->mapper->expects($this->never())->method('update');
		$this->output->expects($this->once())->method('info')->with($this->stringContains(string: 'nothing to widen'));

		$this->step()->run($this->output);
	}//end testNoRuleIsANoOp()

	/**
	 * A read or write failure is a warning, never a failed upgrade.
	 *
	 * @return void
	 */
	public function testFailuresAreWarnings(): void {
		$this->mapper->method('findByGroupId')->willThrowException(new RuntimeException('db gone'));
		$this->output->expects($this->once())->method('warning')->with($this->stringContains(string: 'db gone'));
		$this->step()->run($this->output);

		$mapper = $this->createMock(originalClassName: RoleFeaturePermissionMapper::class);
		$mapper->method('findByGroupId')->willReturn(self::rule(allowed: ['activity', 'recommendations']));
		$mapper->method('update')->willThrowException(new RuntimeException('read-only'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with($this->stringContains(string: 'read-only'));
		(new BroadenDefaultWidgetRule($mapper, $this->createMock(originalClassName: LoggerInterface::class)))->run($output);
	}//end testFailuresAreWarnings()
}//end class
