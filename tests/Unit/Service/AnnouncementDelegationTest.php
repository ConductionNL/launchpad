<?php

/**
 * AnnouncementDelegationTest
 *
 * The groups delegated the announcements setting (D1): sorted by name,
 * unavailable without the settings app or when reading fails.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-3
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\AnnouncementDelegation;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for AnnouncementDelegation.
 *
 * @covers \OCA\LaunchPad\Service\AnnouncementDelegation
 */
class AnnouncementDelegationTest extends TestCase {

	/**
	 * The service over a container that has, or lacks, the settings app's service.
	 *
	 * @param object|null $service The delegation service, null when absent.
	 *
	 * @return AnnouncementDelegation
	 */
	private function delegation(?object $service): AnnouncementDelegation {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('has')->willReturn($service !== null);
		$container->method('get')->willReturn($service);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('get')->willReturnCallback(function (string $groupId): ?IGroup {
			if ($groupId === 'gone') {
				return null;
			}

			$group = $this->createMock(originalClassName: IGroup::class);
			$group->method('getDisplayName')->willReturn(ucfirst($groupId));
			return $group;
		});

		return new AnnouncementDelegation(
			container: $container,
			groupManager: $groups,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end delegation()

	/**
	 * The delegated groups, by name; a removed group keeps its id.
	 *
	 * @return void
	 */
	public function testTheDelegatedGroupsByName(): void {
		$entry   = static fn (string $groupId): object => new class ($groupId) {
			/**
			 * Constructor.
			 *
			 * @param string $groupId The group.
			 */
			public function __construct(private readonly string $groupId) {
			}

			/**
			 * The group.
			 *
			 * @return string
			 */
			public function getGroupId(): string {
				return $this->groupId;
			}
		};
		$service = new class ([$entry('redactie'), $entry('gone'), $entry('communicatie')]) {
			/**
			 * Constructor.
			 *
			 * @param array $entries The stored delegations.
			 */
			public function __construct(private readonly array $entries) {
			}

			/**
			 * The delegations of one setting class.
			 *
			 * @param string $class The setting class.
			 *
			 * @return array
			 */
			public function findExistingGroupsForClass(string $class): array {
				return $class === LaunchPadAnnouncementsAdmin::class ? $this->entries : [];
			}
		};

		$this->assertSame(
			expected: [
				'available' => true,
				'groups'    => [
					['id' => 'communicatie', 'displayName' => 'Communicatie'],
					['id' => 'gone', 'displayName' => 'gone'],
					['id' => 'redactie', 'displayName' => 'Redactie'],
				],
			],
			actual: $this->delegation(service: $service)->groups()
		);
	}//end testTheDelegatedGroupsByName()

	/**
	 * Without the settings app, or when reading fails, the list is unavailable.
	 *
	 * @return void
	 */
	public function testUnavailable(): void {
		$this->assertSame(expected: ['available' => false, 'groups' => []], actual: $this->delegation(service: null)->groups());

		$broken = new class {
			/**
			 * Always fails.
			 *
			 * @return array
			 *
			 * @throws RuntimeException Always.
			 */
			public function findExistingGroupsForClass(): array {
				throw new RuntimeException('db down');
			}
		};
		$this->assertSame(expected: ['available' => false, 'groups' => []], actual: $this->delegation(service: $broken)->groups());
	}//end testUnavailable()
}//end class
