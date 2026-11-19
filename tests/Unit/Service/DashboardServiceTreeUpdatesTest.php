<?php

/**
 * DashboardServiceTreeUpdatesTest
 *
 * Pins the hierarchy patch of `DashboardService::applyTreeUpdates`:
 * re-parenting with the cycle/depth guard, the slug format and
 * per-parent uniqueness guards, and the verbatim sort order.
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

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardBuilders;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardOutlets;
use OCA\LaunchPad\Service\DashboardPlatform;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardStores;
use OCA\LaunchPad\Service\DashboardStructure;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * The tree keys of a dashboard update, case by case.
 */
class DashboardServiceTreeUpdatesTest extends TestCase {

	/**
	 * Guard calls in order.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private array $calls = [];

	/**
	 * Re-parenting casts a non-string parent, guards it, and the slug check uses the new parent.
	 *
	 * @return void
	 */
	public function testReparentAndRenameInOnePatch(): void {
		$dashboard = $this->apply(data: ['parentUuid' => 42, 'slug' => 'kid', 'sortOrder' => '7']);

		$this->assertSame(
			[['validateParent', 'me', '42'], ['validateSlugUnique', '42', 'kid', 'me']],
			$this->calls
		);
		$this->assertSame(['42', 'kid', 7], [$dashboard->getParentUuid(), $dashboard->getSlug(), $dashboard->getSortOrder()]);
	}//end testReparentAndRenameInOnePatch()

	/**
	 * A slug alone is checked against the current parent; a null parent is kept null.
	 *
	 * @return void
	 */
	public function testSlugAloneUsesTheCurrentParentAndNullParentStaysNull(): void {
		$dashboard = $this->apply(data: ['slug' => 'new']);
		$this->assertSame([['validateSlugUnique', 'old-parent', 'new', 'me']], $this->calls);
		$this->assertSame('new', $dashboard->getSlug());

		$this->calls = [];
		$dashboard   = $this->apply(data: ['parentUuid' => null]);
		$this->assertSame([['validateParent', 'me', null]], $this->calls);
		$this->assertNull($dashboard->getParentUuid());
		$this->assertSame('old-slug', $dashboard->getSlug());
	}//end testSlugAloneUsesTheCurrentParentAndNullParentStaysNull()

	/**
	 * An empty or null slug clears it without any guard; a number is cast.
	 *
	 * @return void
	 */
	public function testEmptyAndNullSlugSkipTheGuards(): void {
		$this->assertSame('', $this->apply(data: ['slug' => ''])->getSlug());
		$this->assertNull($this->apply(data: ['slug' => null])->getSlug());
		$this->assertSame([], $this->calls);
		$this->assertSame('12', $this->apply(data: ['slug' => 12])->getSlug());
		$this->assertSame([['validateSlugUnique', 'old-parent', '12', 'me']], $this->calls);
	}//end testEmptyAndNullSlugSkipTheGuards()

	/**
	 * An invalid slug is refused before the uniqueness guard; nothing else is touched.
	 *
	 * @return void
	 */
	public function testInvalidSlugIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Slug must match [a-z0-9_-]+ and be at most 128 characters');
		try {
			$this->apply(data: ['slug' => 'Not Valid!']);
		} finally {
			$this->assertSame([], $this->calls);
		}
	}//end testInvalidSlugIsRefused()

	/**
	 * Without tree keys nothing changes.
	 *
	 * @return void
	 */
	public function testNoTreeKeysChangeNothing(): void {
		$dashboard = $this->apply(data: ['name' => 'x']);
		$this->assertSame([], $this->calls);
		$this->assertSame(['old-parent', 'old-slug', 3], [$dashboard->getParentUuid(), $dashboard->getSlug(), $dashboard->getSortOrder()]);
	}//end testNoTreeKeysChangeNothing()

	/**
	 * Run the private tree patch on a fresh dashboard.
	 *
	 * @param array<string, mixed> $data The update payload.
	 *
	 * @return Dashboard The patched dashboard.
	 */
	private function apply(array $data): Dashboard {
		$tree = $this->createMock(DashboardTreeService::class);
		$tree->method('validateParent')->willReturnCallback(
			function (?string $movingUuid, ?string $newParentUuid): void {
				$this->calls[] = ['validateParent', $movingUuid, $newParentUuid];
			}
		);
		$tree->method('validateSlugUnique')->willReturnCallback(
			function (?string $parentUuid, string $slug, ?string $excludeUuid = null): void {
				$this->calls[] = ['validateSlugUnique', $parentUuid, $slug, $excludeUuid];
			}
		);

		$service = new DashboardService(
			stores: new DashboardStores(
				dashboardMapper: $this->createMock(DashboardMapper::class),
				placementMapper: $this->createMock(WidgetPlacementMapper::class),
				settingMapper: $this->createMock(AdminSettingMapper::class)
			),
			builders: new DashboardBuilders(
				templateService: $this->createMock(TemplateService::class),
				dashboardFactory: new DashboardFactory(),
				dashResolver: $this->createMock(DashboardResolver::class),
				adminTemplateService: $this->createMock(AdminTemplateService::class)
			),
			structure: new DashboardStructure(
				treeService: $tree,
				personalLayers: $this->createMock(PersonalLayerService::class),
				footerService: $this->createMock(FooterService::class)
			),
			platform: new DashboardPlatform(
				groupManager: $this->createMock(IGroupManager::class),
				db: $this->createMock(IDBConnection::class),
				config: $this->createMock(IConfig::class),
				l10nFactory: $this->createMock(IFactory::class)
			),
			outlets: new DashboardOutlets(logger: $this->createMock(LoggerInterface::class))
		);

		$dashboard = new Dashboard();
		$dashboard->setUuid('me');
		$dashboard->setParentUuid('old-parent');
		$dashboard->setSlug('old-slug');
		$dashboard->setSortOrder(3);
		(new ReflectionMethod(DashboardService::class, 'applyTreeUpdates'))->invoke($service, $dashboard, $data);

		return $dashboard;
	}//end apply()
}//end class
