<?php

/**
 * SeedRolePermissions golden test
 *
 * Pins the rows the role seed repair step inserts and the lines it writes,
 * on a fresh install and when rows exist, against a recorded fixture.
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
use OCA\LaunchPad\Db\RoleLayoutDefault;
use OCA\LaunchPad\Db\RoleLayoutDefaultMapper;
use OCA\LaunchPad\Repair\SeedRolePermissions;
use OCP\AppFramework\Db\Entity;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The seeded rows, case by case.
 *
 * @covers \OCA\LaunchPad\Repair\SeedRolePermissions
 * @uses   \OCA\LaunchPad\Db\RoleFeaturePermission
 * @uses   \OCA\LaunchPad\Db\RoleLayoutDefault
 */
class SeedRolePermissionsGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/seed-role-permissions.json';

	/**
	 * Both cases match the recorded fixture.
	 *
	 * @return void
	 */
	public function testSeedMatchesTheRecordedFixture(): void {
		$actual = [
			'fresh' => $this->seed(existing: []),
			'already seeded' => $this->seed(existing: [new RoleFeaturePermission()]),
		];

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
			$this->markTestIncomplete(message: 'Fixture recorded.');
		}

		$this->assertSame(expected: json_decode((string)file_get_contents(self::FIXTURE), true), actual: $actual);
	}

	/**
	 * Run the step and record the inserted rows and written lines.
	 *
	 * @param array<int, RoleFeaturePermission> $existing Rows already present.
	 *
	 * @return array<int, mixed>
	 */
	private function seed(array $existing): array {
		$log = [];
		$insert = static function (Entity $entity) use (&$log): Entity {
			$row = $entity->jsonSerialize();
			$log[] = 'insert ' . get_class($entity) . ' ' . json_encode(array_diff_key($row, ['createdAt' => 1, 'updatedAt' => 1]))
				. ' stamped ' . json_encode($row['createdAt'] !== null && $row['createdAt'] === $row['updatedAt']);
			return $entity;
		};
		$perms = $this->getMockBuilder(className: RoleFeaturePermissionMapper::class)->disableOriginalConstructor()->getMock();
		$perms->method('findAll')->willReturn($existing);
		$perms->method('insert')->willReturnCallback($insert);
		$defaults = $this->getMockBuilder(className: RoleLayoutDefaultMapper::class)->disableOriginalConstructor()->getMock();
		$defaults->method('insert')->willReturnCallback($insert);
		$output = $this->getMockBuilder(className: IOutput::class)->getMock();
		$output->method('info')->willReturnCallback(static function (string $message) use (&$log): void {
			$log[] = 'output ' . $message;
		});
		$logger = $this->getMockBuilder(className: LoggerInterface::class)->getMock();
		foreach (['debug', 'info'] as $level) {
			$logger->method($level)->willReturnCallback(static function (string $message) use ($level, &$log): void {
				$log[] = 'log ' . $level . ' ' . $message;
			});
		}

		(new SeedRolePermissions(permMapper: $perms, defMapper: $defaults, logger: $logger))->run(output: $output);

		return $log;
	}
}
