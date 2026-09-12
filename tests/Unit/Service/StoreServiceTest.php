<?php

/**
 * StoreServiceTest
 *
 * Unit tests for {@see \OCA\LaunchPad\Service\StoreService} covering the
 * `dashboard-store` capability — REQ-STORE-002 (degraded discovery),
 * REQ-STORE-003 (outcome pass-through), REQ-STORE-005 (reuse of
 * ImportService), REQ-STORE-006 (additive install), REQ-STORE-007 (token
 * redaction).
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
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\StoreService;
use OCA\OpenRegister\AppHost\Service\GenericStoreService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use ZipArchive;

require_once __DIR__ . '/../../Stubs/OpenRegisterStubs.php';

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the constructor.
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) One test per declared scenario.
 */
class StoreServiceTest extends TestCase {
	/**
	 * A resolved registry item carrying one dashboard.
	 *
	 * @var array<string, mixed>
	 */
	private const ITEM = [
		'slug' => 'sales-overview',
		'title' => 'Sales overview',
		'dashboards' => [
			['uuid' => 'aaaa-bbbb', 'name' => 'Sales overview', 'gridColumns' => 4],
		],
	];

	/** @var ImportService&MockObject */
	private $importService;

	/** @var IAppConfig&MockObject */
	private $appConfig;

	/** @var array<string, string> */
	private array $configValues = [];

	/**
	 * Build the collaborators every test shares.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->importService = $this->createMock(ImportService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);

		$this->appConfig->method('getValueString')->willReturnCallback(
			function (string $app, string $key, string $default = ''): string {
				return ($this->configValues[$key] ?? $default);
			}
		);
		$this->appConfig->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->configValues[$key] = $value;
				return true;
			}
		);
	}//end setUp()

	/**
	 * REQ-STORE-005: a template installed from a registry arrives with its
	 * widgets configured.
	 *
	 * Every other install test here mocks the importer, which proves the
	 * payload reaches it and nothing about what it does with the payload. This
	 * one runs the REAL `ImportService` behind the store. That matters because
	 * the importer used to drop each widget's `content`, so a store install
	 * placed an object-list that knew no register and a text widget with no
	 * text, and reported success.
	 *
	 * @return void
	 */
	public function testAStoreInstallArrivesWithItsWidgetsConfigured(): void {
		$dashboards = $this->createMock(originalClassName: DashboardMapper::class);
		$dashboards->method('findByUuid')->willThrowException(exception: new DoesNotExistException(msg: 'no'));
		$persisted = new Dashboard();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$persisted->setId(31);
		$dashboards->method('insert')->willReturn($persisted);

		$placed = [];
		$placements = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$placements->method('insert')->willReturnCallback(
			static function (WidgetPlacement $placement) use (&$placed): WidgetPlacement {
				$placed[] = $placement;
				return $placement;
			}
		);

		$importer = new ImportService(
			dashboardMapper: $dashboards,
			placementMapper: $placements,
			db: $this->createMock(originalClassName: IDBConnection::class),
			logger: new NullLogger(),
		);

		$discovery = $this->createMock(GenericStoreService::class);
		$discovery->method('resolve')->willReturn([
			'slug' => 'case-desk',
			'title' => 'Case desk',
			'dashboards' => [[
				'uuid' => 'store-dash',
				'name' => 'Case desk',
				'widgets' => [
					['widgetId' => 'object-list', 'content' => ['register' => 'dossiq', 'schema' => 'case']],
					['widgetId' => 'text', 'content' => ['text' => 'Start here']],
				],
			]],
		]);

		$store = new StoreService(
			discovery: $discovery,
			importService: $importer,
			appConfig: $this->appConfig,
			logger: new NullLogger()
		);

		$result = $store->install(slug: 'case-desk', userId: 'alice');

		$this->assertTrue($result['success'], $result['message']);
		$this->assertCount(2, $placed);
		$this->assertSame(['register' => 'dossiq', 'schema' => 'case'], $placed[0]->getContentArray());
		$this->assertSame(['text' => 'Start here'], $placed[1]->getContentArray());
	}//end testAStoreInstallArrivesWithItsWidgetsConfigured()

	/**
	 * Build the service under test.
	 *
	 * @param object|null $discovery The discovery client, or null.
	 *
	 * @return StoreService
	 */
	private function service(?object $discovery): StoreService {
		return new StoreService(
			discovery: $discovery,
			importService: $this->importService,
			appConfig: $this->appConfig,
			logger: new NullLogger()
		);
	}//end service()

	/**
	 * REQ-STORE-002: an absent OpenRegister degrades to `not_configured`.
	 *
	 * @return void
	 */
	public function testSearchWithoutTheEngineReportsNotConfigured(): void {
		$result = $this->service(discovery: null)->search();

		$this->assertSame('not_configured', $result['outcome']);
		$this->assertSame([], $result['cards']);
	}//end testSearchWithoutTheEngineReportsNotConfigured()

	/**
	 * REQ-STORE-002: `isAvailable()` reports the engine's presence.
	 *
	 * @return void
	 */
	public function testAvailabilityFollowsTheInjectedClient(): void {
		$this->assertFalse($this->service(discovery: null)->isAvailable());
		$this->assertTrue(
			$this->service(discovery: $this->createMock(GenericStoreService::class))->isAvailable()
		);
	}//end testAvailabilityFollowsTheInjectedClient()

	/**
	 * REQ-STORE-003: an invalid response is NOT reported as unreachable.
	 *
	 * The two outcomes need different fixes, so collapsing them would send an
	 * administrator to check the network for a configuration mistake.
	 *
	 * @return void
	 */
	public function testInvalidResponseIsNotCollapsedIntoUnreachable(): void {
		$discovery = $this->createMock(GenericStoreService::class);
		$discovery->method('search')->willReturn(
			['outcome' => 'store_invalid_response', 'cards' => []]
		);

		$result = $this->service(discovery: $discovery)->search();

		$this->assertSame('store_invalid_response', $result['outcome']);
	}//end testInvalidResponseIsNotCollapsedIntoUnreachable()

	/**
	 * REQ-STORE-003: cards come back as the engine normalised them.
	 *
	 * @return void
	 */
	public function testCardsArePassedThroughUnchanged(): void {
		$cards = [['slug' => 'sales-overview', 'title' => 'Sales overview', 'kind' => '']];
		$discovery = $this->createMock(GenericStoreService::class);
		$discovery->method('search')->willReturn(['outcome' => 'ok', 'cards' => $cards]);

		$result = $this->service(discovery: $discovery)->search(query: 'sales');

		$this->assertSame('ok', $result['outcome']);
		$this->assertSame($cards, $result['cards']);
	}//end testCardsArePassedThroughUnchanged()

	/**
	 * REQ-STORE-002: no engine means no import, not a half-run install.
	 *
	 * @return void
	 */
	public function testInstallWithoutTheEngineNeverImports(): void {
		$this->importService->expects($this->never())->method('import');

		$result = $this->service(discovery: null)->install(slug: 'sales-overview', userId: 'alice');

		$this->assertFalse($result['success']);
		$this->assertSame([], $result['components']);
	}//end testInstallWithoutTheEngineNeverImports()

	/**
	 * REQ-STORE-005: an unresolved slug is refused before any import.
	 *
	 * @return void
	 */
	public function testUnresolvedSlugIsRefused(): void {
		$discovery = $this->createMock(GenericStoreService::class);
		$discovery->method('resolve')->willReturn(null);
		$this->importService->expects($this->never())->method('import');

		$result = $this->service(discovery: $discovery)->install(slug: 'nope', userId: 'alice');

		$this->assertFalse($result['success']);
	}//end testUnresolvedSlugIsRefused()

	/**
	 * REQ-STORE-005: an item carrying no dashboard never reaches the importer.
	 *
	 * Importing an empty archive would report a cheerful "installed 0" instead
	 * of naming the registry's mistake.
	 *
	 * @return void
	 */
	public function testItemWithoutDashboardsIsRefusedBeforeImport(): void {
		$discovery = $this->createMock(GenericStoreService::class);
		$discovery->method('resolve')->willReturn(['slug' => 'empty', 'dashboards' => []]);
		$this->importService->expects($this->never())->method('import');

		$result = $this->service(discovery: $discovery)->install(slug: 'empty', userId: 'alice');

		$this->assertFalse($result['success']);
	}//end testItemWithoutDashboardsIsRefusedBeforeImport()

	/**
	 * REQ-STORE-005: the payload reaches the EXISTING importer, as a ZIP whose
	 * shape `ImportService::validateZipStructure()` accepts.
	 *
	 * @return void
	 */
	public function testThePayloadIsMaterialisedAsALaunchpadExportArchive(): void {
		$seen = [];
		$this->importService->expects($this->once())->method('import')->willReturnCallback(
			function (string $zipPath, bool $preserveUuids, string $currentUserId) use (&$seen): array {
				$zip = new ZipArchive();
				$this->assertTrue($zip->open($zipPath) === true, 'the archive must open');
				$seen['manifest'] = json_decode((string)$zip->getFromName('manifest.json'), true);
				$seen['dashboard'] = $zip->getFromName('dashboards/aaaa-bbbb.json');
				$zip->close();

				return ['status' => 'ok', 'importedDashboardCount' => 1, 'skippedDashboardCount' => 0, 'errors' => [], 'manifest' => []];
			}
		);

		$result = $this->serviceResolving(item: self::ITEM)->install(slug: 'sales-overview', userId: 'alice');

		$this->assertTrue($result['success']);
		$this->assertSame(ImportService::SCHEMA_VERSION, $seen['manifest']['schemaVersion']);
		$this->assertSame('dashboard', $seen['manifest']['scope']);
		$this->assertNotFalse($seen['dashboard'], 'the dashboard entry must be present');
	}//end testThePayloadIsMaterialisedAsALaunchpadExportArchive()

	/**
	 * REQ-STORE-006: a store install creates, it never replaces.
	 *
	 * A foreign template carries the publishing instance's UUIDs, one of which
	 * can name a live local dashboard.
	 *
	 * @return void
	 */
	public function testInstallIsAdditiveRatherThanReplacing(): void {
		$this->importService->expects($this->once())->method('import')->with(
			$this->anything(),
			$this->isFalse(),
			'alice'
		)->willReturn(
			['status' => 'ok', 'importedDashboardCount' => 1, 'skippedDashboardCount' => 0, 'errors' => [], 'manifest' => []]
		);

		$this->serviceResolving(item: self::ITEM)->install(slug: 'sales-overview', userId: 'alice');
	}//end testInstallIsAdditiveRatherThanReplacing()

	/**
	 * REQ-STORE-005: a failing import leaves no archive behind.
	 *
	 * @return void
	 */
	public function testTheTemporaryArchiveIsRemovedWhenTheImportThrows(): void {
		$path = null;
		$this->importService->method('import')->willReturnCallback(
			function (string $zipPath) use (&$path): array {
				$path = $zipPath;
				throw new RuntimeException(message: 'boom');
			}
		);

		$result = $this->serviceResolving(item: self::ITEM)->install(slug: 'sales-overview', userId: 'alice');

		$this->assertFalse($result['success']);
		$this->assertNotNull($path);
		$this->assertFileDoesNotExist($path);
	}//end testTheTemporaryArchiveIsRemovedWhenTheImportThrows()

	/**
	 * REQ-STORE-005: a succeeding import leaves no archive behind either.
	 *
	 * @return void
	 */
	public function testTheTemporaryArchiveIsRemovedOnSuccess(): void {
		$path = null;
		$this->importService->method('import')->willReturnCallback(
			function (string $zipPath) use (&$path): array {
				$path = $zipPath;
				return ['status' => 'ok', 'importedDashboardCount' => 1, 'skippedDashboardCount' => 0, 'errors' => [], 'manifest' => []];
			}
		);

		$this->serviceResolving(item: self::ITEM)->install(slug: 'sales-overview', userId: 'alice');

		$this->assertNotNull($path);
		$this->assertFileDoesNotExist($path);
	}//end testTheTemporaryArchiveIsRemovedOnSuccess()

	/**
	 * REQ-STORE-005: a partly-imported item names what did not arrive.
	 *
	 * @return void
	 */
	public function testAPartialImportNamesTheDashboardThatDidNotArrive(): void {
		$item = self::ITEM;
		$item['dashboards'][] = ['uuid' => 'cccc-dddd', 'name' => 'Refused board'];

		$this->importService->method('import')->willReturn(
			['status' => 'ok', 'importedDashboardCount' => 1, 'skippedDashboardCount' => 1, 'errors' => [], 'manifest' => []]
		);

		$result = $this->serviceResolving(item: $item)->install(slug: 'sales-overview', userId: 'alice');

		$this->assertTrue($result['success']);
		$this->assertSame('installed', $result['components'][0]['status']);
		$this->assertSame('refused', $result['components'][1]['status']);
		$this->assertSame('Refused board', $result['components'][1]['schema']);
	}//end testAPartialImportNamesTheDashboardThatDidNotArrive()

	/**
	 * REQ-STORE-005: a JSON-encoded dashboards property is accepted too.
	 *
	 * @return void
	 */
	public function testAJsonEncodedDashboardsPropertyIsAccepted(): void {
		$item = ['slug' => 'sales-overview', 'dashboards' => json_encode(self::ITEM['dashboards'])];
		$this->importService->expects($this->once())->method('import')->willReturn(
			['status' => 'ok', 'importedDashboardCount' => 1, 'skippedDashboardCount' => 0, 'errors' => [], 'manifest' => []]
		);

		$result = $this->serviceResolving(item: $item)->install(slug: 'sales-overview', userId: 'alice');

		$this->assertTrue($result['success']);
	}//end testAJsonEncodedDashboardsPropertyIsAccepted()

	/**
	 * REQ-STORE-007: the token is reported, never returned.
	 *
	 * @return void
	 */
	public function testTheRegistryTokenIsNeverReadBack(): void {
		$this->configValues = [
			StoreService::CONFIG_URL => 'https://registry.example.org/',
			StoreService::CONFIG_TOKEN => 'super-secret-token',
			StoreService::CONFIG_REGISTER => 'launchpad',
		];

		$config = $this->service(discovery: null)->getRegistryConfig();

		$this->assertTrue($config['tokenConfigured']);
		$this->assertSame('https://registry.example.org/', $config['registryUrl']);
		$this->assertNotContains('super-secret-token', $config);
		$this->assertArrayNotHasKey('registryToken', $config);
	}//end testTheRegistryTokenIsNeverReadBack()

	/**
	 * REQ-STORE-007: an omitted key is left alone, an empty one clears.
	 *
	 * @return void
	 */
	public function testAnOmittedKeyIsLeftAloneAndAnEmptyOneClears(): void {
		$this->configValues = [StoreService::CONFIG_TOKEN => 'super-secret-token'];
		$service = $this->service(discovery: null);

		$service->updateRegistryConfig(registryUrl: 'https://registry.example.org/');
		$this->assertTrue($service->getRegistryConfig()['tokenConfigured'], 'an omitted token must survive');

		$service->updateRegistryConfig(registryToken: '');
		$this->assertFalse($service->getRegistryConfig()['tokenConfigured'], 'an empty token must clear');
	}//end testAnOmittedKeyIsLeftAloneAndAnEmptyOneClears()

	/**
	 * A service whose discovery client resolves to the given item.
	 *
	 * @param array<string, mixed> $item The resolved registry item.
	 *
	 * @return StoreService
	 */
	private function serviceResolving(array $item): StoreService {
		$discovery = $this->createMock(GenericStoreService::class);
		$discovery->method('resolve')->willReturn($item);

		return $this->service(discovery: $discovery);
	}//end serviceResolving()
}//end class
