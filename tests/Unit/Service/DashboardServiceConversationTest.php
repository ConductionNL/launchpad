<?php

/**
 * The conversation switch of a dashboard (REQ-LEAF-006).
 *
 * Ensures `updateDashboard()` stores true and false as 1 and 0, returns to
 * the default on null, keeps the value when an update does not name it, and
 * refuses any other value before saving.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\DashboardOutlets;
use OCA\LaunchPad\Service\DashboardPlatform;
use OCA\LaunchPad\Service\DashboardStructure;
use OCA\LaunchPad\Service\DashboardBuilders;
use OCA\LaunchPad\Service\DashboardStores;
use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DashboardServiceConversationTest extends TestCase {
	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var FooterService&MockObject */
	private $footerService;

	/**
	 * The service under test.
	 *
	 * @var DashboardService
	 */
	private DashboardService $service;

	/**
	 * Build the service on mocked stores.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->dashboardMapper = $this->createMock(originalClassName: DashboardMapper::class);
		$this->footerService = $this->createMock(originalClassName: FooterService::class);

		$this->service = new DashboardService(
			stores: new DashboardStores(
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
				settingMapper: $this->createMock(originalClassName: AdminSettingMapper::class)
			),
			builders: new DashboardBuilders(
				templateService: $this->createMock(originalClassName: TemplateService::class),
				dashboardFactory: new DashboardFactory(),
				dashResolver: $this->createMock(originalClassName: DashboardResolver::class),
				adminTemplateService: $this->createMock(originalClassName: AdminTemplateService::class)
			),
			structure: new DashboardStructure(
				treeService: $this->createMock(originalClassName: DashboardTreeService::class),
				personalLayers: $this->createMock(originalClassName: PersonalLayerService::class),
				footerService: $this->footerService
			),
			platform: new DashboardPlatform(
				groupManager: $this->createMock(originalClassName: IGroupManager::class),
				db: $this->createMock(originalClassName: IDBConnection::class),
				config: $this->createMock(originalClassName: IConfig::class),
				l10nFactory: $this->createMock(originalClassName: IFactory::class)
			),
			outlets: new DashboardOutlets(
				logger: $this->createMock(originalClassName: LoggerInterface::class)
			)
		);
	}

	/**
	 * A personal dashboard of alice.
	 *
	 * @return Dashboard
	 */
	private function makeDashboard(): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUserId('alice');
		$dashboard->setUuid('uuid-1');
		$dashboard->setName('Test');
		return $dashboard;
	}

	/**
	 * True stores 1, false stores 0, null returns to the default; the wire format carries it.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
	 */
	public function testTheSwitchIsStoredAndCleared(): void {
		$dashboard = $this->makeDashboard();
		$this->dashboardMapper->method('find')->willReturn($dashboard);
		$this->dashboardMapper->method('update')->willReturnArgument(0);

		$off = $this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: ['conversationEnabled' => false]);
		$this->assertSame(expected: 0, actual: $off->jsonSerialize()['conversationEnabled']);
		$this->assertSame(expected: 0, actual: $off->toViewerArray()['conversationEnabled']);

		$on = $this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: ['conversationEnabled' => true]);
		$this->assertSame(expected: 1, actual: $on->getConversationEnabled());

		$reset = $this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: ['conversationEnabled' => null]);
		$this->assertNull(actual: $reset->getConversationEnabled());
	}

	/**
	 * An update that does not name the switch leaves it alone.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
	 */
	public function testAnUpdateWithoutTheSwitchKeepsIt(): void {
		$dashboard = $this->makeDashboard();
		$dashboard->setConversationEnabled(0);
		$this->dashboardMapper->method('find')->willReturn($dashboard);
		$this->dashboardMapper->method('update')->willReturnArgument(0);

		$result = $this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: ['name' => 'Renamed']);

		$this->assertSame(expected: 0, actual: $result->getConversationEnabled());
	}

	/**
	 * Any value but a boolean, 0, 1 or null is refused and nothing is saved.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
	 */
	public function testAnotherValueIsRefused(): void {
		$dashboard = $this->makeDashboard();
		$this->dashboardMapper->method('find')->willReturn($dashboard);
		$this->dashboardMapper->expects($this->never())->method('update');

		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'conversationEnabled must be true, false or null');
		$this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: ['conversationEnabled' => 'yes']);
	}
}
