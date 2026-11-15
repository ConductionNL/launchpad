<?php

/**
 * PeopleWidgetService list guard characterisation test
 *
 * Pins the exact messages `listUsers()` refuses a request with, and that
 * the page window is checked before the search text, so the guards can
 * move into named steps without changing what a caller is told.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\PeopleQuery;
use OCA\LaunchPad\Service\PeopleWidgetService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\Accounts\IAccountManager;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class PeopleWidgetListGuardsTest extends TestCase {
	/**
	 * Refused requests and the message they are refused with.
	 *
	 * @return array<string,array{0:string,1:int,2:int,3:string|null,4:string}>
	 */
	public static function refusals(): array {
		return [
			'limit zero'        => ['displayName', 0, 0, null, 'limit must be between 1 and 100'],
			'limit over max'    => ['displayName', 101, 0, null, 'limit must be between 1 and 100'],
			'window before sort' => ['recent-activity', 0, -1, 's', 'limit must be between 1 and 100'],
			'offset negative'   => ['recent-activity', 10, -1, 's', 'offset must be >= 0'],
			'sort before query' => ['recent-activity', 10, 0, 's', 'sortBy=recent-activity is not yet implemented'],
			'query too short'   => ['displayName', 10, 0, ' é ', 'q must be at least 2 characters'],
		];
	}

	/**
	 * @dataProvider refusals
	 */
	public function testRefusalMessage(string $sortBy, int $limit, int $offset, ?string $search, string $message): void {
		$userManager = $this->createMock(originalClassName: IUserManager::class);
		$userManager->expects($this->never())->method('search');
		$userManager->expects($this->never())->method('searchDisplayName');
		$service = new PeopleWidgetService(
			$userManager,
			$this->createMock(originalClassName: IGroupManager::class),
			$this->createMock(originalClassName: IAccountManager::class),
			$this->createMock(originalClassName: IURLGenerator::class),
			$this->createMock(originalClassName: AdminTemplateService::class),
			$this->createMock(originalClassName: ProfileFieldService::class),
		);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);

		$service->listUsers(
			new PeopleQuery(
				filters: [],
				sortBy: $sortBy,
				search: $search,
				page: PeopleQuery::window(excludeDisabled: true, showBirthdays: true, limit: $limit, offset: $offset)
			),
			'viewer'
		);
	}
}//end class
