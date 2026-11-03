<?php

/**
 * DashboardFactory golden test
 *
 * Pins the dashboard entity that DashboardFactory::create builds for a
 * fixed set of option combinations, and its refusals, against a recorded
 * fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\DashboardFactory;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The factory's entities, case by case.
 *
 * @covers \OCA\LaunchPad\Service\DashboardFactory
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Service\SlugGenerator
 */
class DashboardFactoryGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/dashboard-factory.json';

	/**
	 * The create() argument sets, by case name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function inputs(): array {
		return [
			'defaults'          => ['userId' => 'alice', 'name' => 'My Board'],
			'no name slug'      => ['userId' => 'alice', 'name' => '!!!'],
			'description'       => ['userId' => 'alice', 'name' => 'A', 'description' => 'About'],
			'group shared'      => ['userId' => null, 'name' => 'Team', 'type' => Dashboard::TYPE_GROUP_SHARED, 'groupId' => 'staff'],
			'group no group'    => ['userId' => null, 'name' => 'Team', 'type' => Dashboard::TYPE_GROUP_SHARED],
			'user with group'   => ['userId' => 'alice', 'name' => 'Team', 'groupId' => 'staff'],
			'admin template'    => ['userId' => null, 'name' => 'Tpl', 'type' => Dashboard::TYPE_ADMIN_TEMPLATE],
			'grid and level'    => ['userId' => 'bob', 'name' => 'Grid', 'gridColumns' => 6, 'permissionLevel' => Dashboard::PERMISSION_VIEW_ONLY],
			'tree'              => ['userId' => 'bob', 'name' => 'Child', 'parentUuid' => 'p-1', 'slug' => 'child-1', 'sortOrder' => 4],
			'empty slug'        => ['userId' => 'bob', 'name' => 'Derived Name', 'slug' => ''],
			'bad slug'          => ['userId' => 'bob', 'name' => 'X', 'slug' => 'Not A Slug'],
			'everything'        => [
				'userId'          => 'carol',
				'name'            => 'All',
				'description'     => 'd',
				'type'            => Dashboard::TYPE_USER,
				'groupId'         => null,
				'gridColumns'     => 24,
				'permissionLevel' => Dashboard::PERMISSION_ADD_ONLY,
				'parentUuid'      => 'p-2',
				'slug'            => 'all',
				'sortOrder'       => 1,
			],
		];
	}//end inputs()

	/**
	 * Call create() with one argument set.
	 *
	 * @param DashboardFactory     $factory The factory.
	 * @param array<string, mixed> $args    The case's named arguments.
	 *
	 * @return Dashboard
	 */
	private static function call(DashboardFactory $factory, array $args): Dashboard {
		return $factory->create(
			userId: $args['userId'],
			name: $args['name'],
			options: array_diff_key($args, ['userId' => true, 'name' => true])
		);
	}//end call()

	/**
	 * Every case builds the recorded entity or the recorded refusal.
	 *
	 * @return void
	 */
	public function testEntitiesMatchTheRecordedFixture(): void {
		$actual = [];
		foreach (self::inputs() as $case => $args) {
			try {
				$dashboard = self::call(factory: new DashboardFactory(), args: $args);
			} catch (Throwable $e) {
				$actual[$case] = ['refused' => [get_class($e), $e->getMessage()]];
				continue;
			}

			$row = $dashboard->jsonSerialize();
			$this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $dashboard->getUuid());
			$this->assertSame($dashboard->getCreatedAt(), $dashboard->getUpdatedAt());
			unset($row['uuid'], $row['createdAt'], $row['updatedAt']);
			$actual[$case] = $row;
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_encode(json_decode((string) file_get_contents(self::FIXTURE))), json_encode($actual));
	}//end testEntitiesMatchTheRecordedFixture()
}//end class
