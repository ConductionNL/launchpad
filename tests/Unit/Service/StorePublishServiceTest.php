<?php

/**
 * StorePublishServiceTest
 *
 * Unit tests for {@see \OCA\LaunchPad\Service\StorePublishService}: an
 * administrator publishes one dashboard to the connected register
 * (REQ-STORE-013). The export names no person, a slug published before needs
 * a higher version, another person's dashboard is never published and this
 * instance's own publications read "Own publication".
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\StoreOwnPublications;
use OCA\LaunchPad\Service\StorePublishService;
use OCA\LaunchPad\Service\StoreService;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the constructor.
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) One test per declared scenario.
 *
 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
 */
class StorePublishServiceTest extends TestCase {
	/** @var StoreService&MockObject */
	private $store;

	/** @var DashboardMapper&MockObject */
	private $dashboards;

	/** @var ExportService&MockObject */
	private $export;

	/** @var IAppConfig&MockObject */
	private $appConfig;

	/** @var array<string, string> */
	private array $configValues = [];

	/**
	 * The items the store was asked to publish.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $sent = [];

	/**
	 * Build the collaborators: the administrator owns "Raads- en collegewerk".
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->store = $this->createMock(originalClassName: StoreService::class);
		$this->store->method('isAvailable')->willReturn(true);
		$this->store->method('publish')->willReturnCallback(
			function (array $item): array {
				$this->sent[] = $item;
				return ['outcome' => 'ok', 'slug' => (string)$item['slug']];
			}
		);

		$own = $this->dashboard(uuid: 'uuid-own', name: 'Raads- en collegewerk');
		$template = $this->dashboard(uuid: 'uuid-template', name: 'Intranet');
		$this->dashboards = $this->createMock(originalClassName: DashboardMapper::class);
		$this->dashboards->method('findByUserId')->willReturnCallback(
			static fn (string $userId): array => array_filter([$own], static fn (): bool => $userId === 'admin')
		);
		$this->dashboards->method('findAdminTemplates')->willReturn([$template, $own]);

		$this->export = $this->createMock(originalClassName: ExportService::class);
		$this->export->method('serializeDashboard')->willReturn(
			[
				'uuid' => 'uuid-own',
				'name' => 'Raads- en collegewerk',
				'userId' => 'admin',
				'groupId' => 'raad',
				'targetGroups' => ['raad'],
				'basedOnTemplate' => 4,
				'isActive' => true,
				'isDefault' => false,
				'gridColumns' => 12,
				'widgets' => [
					['widgetId' => 'text', 'content' => ['text' => 'Agenda'], 'templatePlacementId' => 9, 'createdAt' => 'x', 'updatedAt' => 'y'],
				],
				'metadataFieldAssignments' => [],
			]
		);

		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->appConfig->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => ($this->configValues[$key] ?? $default)
		);
		$this->appConfig->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->configValues[$key] = $value;
				return true;
			}
		);
	}//end setUp()

	/**
	 * A dashboard with a uuid and a name.
	 *
	 * @param string $uuid The uuid.
	 * @param string $name The name.
	 *
	 * @return Dashboard
	 */
	private function dashboard(string $uuid, string $name): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid($uuid);
		$dashboard->setName($name);
		return $dashboard;
	}//end dashboard()

	/**
	 * The service under test.
	 *
	 * @return StorePublishService
	 */
	private function service(): StorePublishService {
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		return new StorePublishService(
			store: $this->store,
			dashboards: $this->dashboards,
			export: $this->export,
			own: new StoreOwnPublications(appConfig: $this->appConfig, l10n: $l10n)
		);
	}//end service()

	/**
	 * The item the publish dialog sends.
	 *
	 * @param string $version The version.
	 *
	 * @return array<string, string>
	 */
	private function item(string $version = '0.9.0'): array {
		return [
			'slug' => 'raads-en-collegewerk',
			'title' => 'Raads- en collegewerk',
			'description' => 'Agenda, besluiten en moties.',
			'category' => 'Management',
			'version' => $version,
			'publisher' => 'Gemeente Tilburg',
		];
	}//end item()

	/**
	 * The administrator publishes Raads- en collegewerk 0.9.0 and the register gets the export.
	 *
	 * @return void
	 */
	public function testTheAdministratorPublishesADashboard(): void {
		$result = $this->service()->publish(
			item: $this->item(),
			objects: [['schema' => 'dashboard', 'id' => 'uuid-own']],
			userId: 'admin'
		);

		$this->assertSame(expected: ['outcome' => 'ok', 'slug' => 'raads-en-collegewerk'], actual: $result);
		$this->assertCount(expectedCount: 1, haystack: $this->sent);
		$sent = $this->sent[0];
		$this->assertSame(expected: '0.9.0', actual: $sent['version']);
		$this->assertSame(expected: 'Management', actual: $sent['category']);
		$this->assertSame(expected: 1, actual: $sent['widgets']);
		$this->assertSame(expected: 'uuid-own', actual: $sent['dashboards'][0]['uuid']);
	}//end testTheAdministratorPublishesADashboard()

	/**
	 * The published export names no owner, group or local row.
	 *
	 * @return void
	 */
	public function testThePublishedExportNamesNoPerson(): void {
		$this->service()->publish(item: $this->item(), objects: [['schema' => 'dashboard', 'id' => 'uuid-own']], userId: 'admin');

		$dashboard = $this->sent[0]['dashboards'][0];
		foreach (StorePublishService::PRIVATE_DASHBOARD_FIELDS as $field) {
			$this->assertArrayNotHasKey(key: $field, array: $dashboard);
		}

		$this->assertSame(expected: [['widgetId' => 'text', 'content' => ['text' => 'Agenda']]], actual: $dashboard['widgets']);
		$this->assertStringNotContainsString(needle: 'admin', haystack: (string)json_encode(value: $this->sent[0]));
	}//end testThePublishedExportNamesNoPerson()

	/**
	 * The same version twice is refused with a message asking for a higher version.
	 *
	 * @return void
	 */
	public function testTheSameVersionTwiceIsRefused(): void {
		$this->store->method('resolveItem')->willReturn(['version' => '0.9.0', 'dashboards' => []]);

		$result = $this->service()->publish(item: $this->item(), objects: [['schema' => 'dashboard', 'id' => 'uuid-own']], userId: 'admin');

		$this->assertSame(expected: StorePublishService::OUTCOME_VERSION_NOT_HIGHER, actual: $result['outcome']);
		$this->assertStringContainsString(needle: 'higher version', haystack: $result['message']);
		$this->assertSame(expected: [], actual: $this->sent);
	}//end testTheSameVersionTwiceIsRefused()

	/**
	 * A higher version than the published one goes through.
	 *
	 * @return void
	 */
	public function testAHigherVersionIsPublished(): void {
		$this->store->method('resolveItem')->willReturn(['version' => '0.9.0', 'dashboards' => []]);

		$result = $this->service()->publish(
			item: $this->item(version: '0.10.0'),
			objects: [['schema' => 'dashboard', 'id' => 'uuid-own']],
			userId: 'admin'
		);

		$this->assertSame(expected: 'ok', actual: $result['outcome']);
	}//end testAHigherVersionIsPublished()

	/**
	 * Another person's dashboard is not publishable, and neither is a request without exactly one dashboard.
	 *
	 * @return void
	 */
	public function testOnlyOneOwnDashboardIsPublishable(): void {
		$service = $this->service();

		$foreign = $service->publish(item: $this->item(), objects: [['schema' => 'dashboard', 'id' => 'uuid-of-pieter']], userId: 'admin');
		$none = $service->publish(item: $this->item(), objects: [], userId: 'admin');
		$two = $service->publish(
			item: $this->item(),
			objects: [['schema' => 'dashboard', 'id' => 'uuid-own'], ['schema' => 'dashboard', 'id' => 'uuid-template']],
			userId: 'admin'
		);

		foreach ([$foreign, $none, $two] as $result) {
			$this->assertSame(expected: StorePublishService::OUTCOME_INVALID_REQUEST, actual: $result['outcome']);
		}

		$this->assertSame(expected: [], actual: $this->sent);
	}//end testOnlyOneOwnDashboardIsPublishable()

	/**
	 * A version that is not three numbers, or no title, is refused before the register is asked.
	 *
	 * @return void
	 */
	public function testAMalformedItemIsRefused(): void {
		$service = $this->service();
		$objects = [['schema' => 'dashboard', 'id' => 'uuid-own']];

		$version = $service->publish(item: $this->item(version: 'latest'), objects: $objects, userId: 'admin');
		$title = $service->publish(item: ['slug' => '', 'title' => ' ', 'version' => '1.0.0'], objects: $objects, userId: 'admin');

		$this->assertSame(expected: StorePublishService::OUTCOME_INVALID_REQUEST, actual: $version['outcome']);
		$this->assertSame(expected: StorePublishService::OUTCOME_INVALID_REQUEST, actual: $title['outcome']);
		$this->assertSame(expected: [], actual: $this->sent);
	}//end testAMalformedItemIsRefused()

	/**
	 * The dialog offers the administrator's own dashboards and the admin templates, once each.
	 *
	 * @return void
	 */
	public function testThePublishableListIsOwnAndTemplatesOnce(): void {
		$objects = $this->service()->publishable(userId: 'admin');

		$this->assertSame(
			expected: [
				['schema' => 'dashboard', 'id' => 'uuid-own', 'title' => 'Raads- en collegewerk'],
				['schema' => 'dashboard', 'id' => 'uuid-template', 'title' => 'Intranet'],
			],
			actual: $objects
		);
	}//end testThePublishableListIsOwnAndTemplatesOnce()

	/**
	 * After a publish the card reads "Own publication"; other cards are left alone.
	 *
	 * @return void
	 */
	public function testAnOwnPublicationIsMarkedOnItsCard(): void {
		$service = $this->service();
		$service->publish(item: $this->item(), objects: [['schema' => 'dashboard', 'id' => 'uuid-own']], userId: 'admin');

		$cards = $service->annotate(cards: [['slug' => 'raads-en-collegewerk'], ['slug' => 'intranet']]);

		$this->assertSame(expected: 'Own publication', actual: $cards[0]['typeName']);
		$this->assertTrue(condition: $cards[0]['ownPublication']);
		$this->assertSame(expected: ['slug' => 'intranet'], actual: $cards[1]);
	}//end testAnOwnPublicationIsMarkedOnItsCard()

	/**
	 * A refused publish is not remembered as this instance's own.
	 *
	 * @return void
	 */
	public function testARefusedPublishIsNotRemembered(): void {
		$store = $this->createMock(originalClassName: StoreService::class);
		$store->method('isAvailable')->willReturn(true);
		$store->method('publish')->willReturn(['outcome' => 'store_rejected', 'slug' => '']);
		$this->store = $store;
		$service = $this->service();

		$service->publish(item: $this->item(), objects: [['schema' => 'dashboard', 'id' => 'uuid-own']], userId: 'admin');

		$this->assertSame(
			expected: [['slug' => 'raads-en-collegewerk']],
			actual: $service->annotate(cards: [['slug' => 'raads-en-collegewerk']])
		);
	}//end testARefusedPublishIsNotRemembered()

	/**
	 * Without OpenRegister nothing is sent and the outcome is not_configured.
	 *
	 * @return void
	 */
	public function testWithoutTheEngineThePublishIsNotConfigured(): void {
		$store = $this->createMock(originalClassName: StoreService::class);
		$store->method('isAvailable')->willReturn(false);
		$store->expects($this->never())->method('publish');
		$this->store = $store;

		$result = $this->service()->publish(item: $this->item(), objects: [['schema' => 'dashboard', 'id' => 'uuid-own']], userId: 'admin');

		$this->assertSame(expected: ['outcome' => 'not_configured', 'slug' => ''], actual: $result);
	}//end testWithoutTheEngineThePublishIsNotConfigured()
}//end class
