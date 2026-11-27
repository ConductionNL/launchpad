<?php

/**
 * MoveAnnouncementEditorsToDelegationTest
 *
 * Pins the one-time move of the announcement editor groups to the delegated
 * announcements setting (announcements-managed-in-settings D1).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Repair\MoveAnnouncementEditorsToDelegation;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for MoveAnnouncementEditorsToDelegation.
 *
 * @covers \OCA\LaunchPad\Repair\MoveAnnouncementEditorsToDelegation
 */
class MoveAnnouncementEditorsToDelegationTest extends TestCase {

	/** @var list<array{0: string, 1: string}> Delegations written. */
	public array $created = [];

	/**
	 * The step over the given editor groups, with or without the settings app.
	 *
	 * @param mixed     $groups  The stored editor groups.
	 * @param bool      $service Whether the settings app's service is there.
	 * @param bool      $fails   Whether delegating fails.
	 * @param bool|null $cleared Set to whether the setting was emptied.
	 *
	 * @return MoveAnnouncementEditorsToDelegation
	 */
	private function step(mixed $groups, bool $service, bool $fails, ?bool &$cleared): MoveAnnouncementEditorsToDelegation {
		$cleared = false;
		$settings = $this->createMock(originalClassName: AdminSettingMapper::class);
		$settings->method('getValue')->willReturn($groups);
		$settings->method('setSetting')->willReturnCallback(function (string $key, mixed $value) use (&$cleared) {
			$cleared = ($key === 'announcement_editor_groups' && $value === []);
			return new \OCA\LaunchPad\Db\AdminSetting();
		});
		$test = $this;
		$delegation = new class($test, $fails) {
			/**
			 * @param MoveAnnouncementEditorsToDelegationTest $test  Collects the calls.
			 * @param bool                                    $fails Whether to fail.
			 */
			public function __construct(private $test, private bool $fails) {
			}

			/**
			 * @param string $groupId The group.
			 * @param string $class   The setting.
			 *
			 * @return void
			 */
			public function create(string $groupId, string $class): void {
				if ($this->fails === true) {
					throw new RuntimeException('no');
				}
				$this->test->created[] = [$groupId, $class];
			}
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('has')->willReturn($service);
		$container->method('get')->willReturn($delegation);

		return new MoveAnnouncementEditorsToDelegation($settings, $container, $this->createMock(originalClassName: LoggerInterface::class));
	}//end step()

	/**
	 * Each editor group is delegated the setting, then the setting is emptied.
	 *
	 * @return void
	 */
	public function testEditorGroupsBecomeDelegatedGroups(): void {
		$step = $this->step(groups: ['redactie', '', 'communicatie'], service: true, fails: false, cleared: $cleared);
		$step->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(
			expected: [['redactie', LaunchPadAnnouncementsAdmin::class], ['communicatie', LaunchPadAnnouncementsAdmin::class]],
			actual: $this->created
		);
		$this->assertTrue(condition: $cleared);
	}//end testEditorGroupsBecomeDelegatedGroups()

	/**
	 * Without the settings app, or when delegating fails, nothing changes.
	 *
	 * @return void
	 */
	public function testNothingChangesWithoutTheDelegationService(): void {
		$this->step(groups: ['redactie'], service: false, fails: false, cleared: $cleared)->run($this->createMock(originalClassName: IOutput::class));
		$this->assertFalse(condition: $cleared);
		$this->step(groups: ['redactie'], service: true, fails: true, cleared: $cleared)->run($this->createMock(originalClassName: IOutput::class));
		$this->assertFalse(condition: $cleared);
		$this->step(groups: [], service: true, fails: false, cleared: $cleared)->run($this->createMock(originalClassName: IOutput::class));
		$this->assertFalse(condition: $cleared);
		$this->assertSame(expected: [], actual: $this->created);
	}//end testNothingChangesWithoutTheDelegationService()
}//end class
