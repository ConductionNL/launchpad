<?php

/**
 * Unit tests for the marketplace install record of REQ-STORE-011: which
 * register item and version an install created which dashboard, the card
 * state it gives (Installed with the dashboard to open, Update, Available),
 * the record written by an install and dropped with its dashboard.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTimeImmutable;
use OCA\LaunchPad\Controller\StoreController;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\StoreInstallsListener;
use OCA\LaunchPad\Service\StoreInstalls;
use OCA\LaunchPad\Service\StoreService;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Installed, Update or Available, from the install record.
 */
class StoreInstallsTest extends TestCase {

	/** @var string The stored map. */
	private string $stored = '{}';

	/**
	 * The install record over the stored map.
	 *
	 * @return StoreInstalls The record.
	 */
	private function installs(): StoreInstalls {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(fn (): string => $this->stored);
		$config->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->stored = $value;
				return true;
			}
		);

		return new StoreInstalls(appConfig: $config);
	}//end installs()

	/**
	 * An install is recorded by slug; a bad slug or id is not, and junk reads as nothing.
	 *
	 * @return void
	 */
	public function testAnInstallIsRecordedBySlug(): void {
		$installs = $this->installs();
		$installs->record(slug: 'intranet-gemeente', version: '2.3.0', dashboardId: 41);
		$installs->record(slug: '', version: '1.0.0', dashboardId: 42);
		$installs->record(slug: 'empty', version: '1.0.0', dashboardId: 0);

		$this->assertSame(
			expected: ['intranet-gemeente' => ['version' => '2.3.0', 'dashboardId' => 41]],
			actual: $installs->all()
		);

		$this->stored = 'not json';
		$this->assertSame(expected: [], actual: $this->installs()->all());
	}//end testAnInstallIsRecordedBySlug()

	/**
	 * Scenarios "Intranet gemeente is installed" and "A newer version in the register".
	 *
	 * @return void
	 */
	public function testTheCardsCarryTheirInstallState(): void {
		$installs = $this->installs();
		$installs->record(slug: 'intranet-gemeente', version: '2.3.0', dashboardId: 41);
		$installs->record(slug: 'managementinfo', version: '1.0.0', dashboardId: 52);

		$cards = $installs->annotate(cards: [
			['slug' => 'intranet-gemeente', 'version' => '2.3.0'],
			['slug' => 'managementinfo', 'version' => '1.1.0'],
			['slug' => 'wijkteam', 'version' => '0.9.0'],
		]);

		$this->assertSame(expected: 'installed', actual: $cards[0]['status']);
		$this->assertTrue(condition: $cards[0]['installed']);
		$this->assertFalse(condition: $cards[0]['updateAvailable']);
		$this->assertSame(expected: 41, actual: $cards[0]['installedDashboardId']);

		$this->assertSame(expected: 'update', actual: $cards[1]['status']);
		$this->assertTrue(condition: $cards[1]['updateAvailable']);
		$this->assertSame(expected: '1.0.0', actual: $cards[1]['installedVersion']);

		$this->assertSame(expected: 'available', actual: $cards[2]['status']);
		$this->assertFalse(condition: $cards[2]['installed']);
		$this->assertArrayNotHasKey(key: 'installedDashboardId', array: $cards[2]);
	}//end testTheCardsCarryTheirInstallState()

	/**
	 * A deleted dashboard drops its install, so the item reads Available again.
	 *
	 * @return void
	 */
	public function testADeletedDashboardIsForgotten(): void {
		$installs = $this->installs();
		$installs->record(slug: 'intranet-gemeente', version: '2.3.0', dashboardId: 41);
		$installs->record(slug: 'managementinfo', version: '1.0.0', dashboardId: 52);

		$listener = new StoreInstallsListener(installs: $installs, logger: $this->createMock(originalClassName: LoggerInterface::class));
		$listener->handle(
			event: new DashboardDeletedEvent(
				dashboardUuid: 'uuid-41',
				ownerUserId: 'admin',
				type: 'user',
				deletedAt: new DateTimeImmutable(),
				dashboardId: 41
			)
		);

		$this->assertSame(expected: ['managementinfo'], actual: array_keys($installs->all()));
		$this->assertSame(expected: 0, actual: $installs->forget(dashboardId: 99));
	}//end testADeletedDashboardIsForgotten()

	/**
	 * The controller records a successful install with its first dashboard,
	 * records nothing for a refusal, and annotates the search cards.
	 *
	 * @return void
	 */
	public function testTheControllerRecordsAndAnnotates(): void {
		$installs = $this->installs();
		$service = $this->createMock(originalClassName: StoreService::class);
		$service->method('install')->willReturnOnConsecutiveCalls(
			['success' => true, 'message' => 'Installed 2 of 2.', 'components' => [], 'version' => '2.3.0', 'dashboardIds' => [41, 42]],
			['success' => false, 'message' => 'That template could not be found in the registry.', 'components' => []],
		);
		$service->method('search')->willReturn(
			['outcome' => 'ok', 'cards' => [['slug' => 'intranet-gemeente', 'version' => '2.3.0']]]
		);
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('admin');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$controller = new StoreController(
			request: $this->createMock(originalClassName: IRequest::class),
			storeService: $service,
			userSession: $session,
			installs: $installs
		);

		$controller->install(slug: 'intranet-gemeente');
		$controller->install(slug: 'missing');
		$this->assertSame(
			expected: ['intranet-gemeente' => ['version' => '2.3.0', 'dashboardId' => 41]],
			actual: $installs->all()
		);

		$card = $controller->search()->getData()['cards'][0];
		$this->assertSame(expected: 'installed', actual: $card['status']);
		$this->assertSame(expected: 41, actual: $card['installedDashboardId']);
	}//end testTheControllerRecordsAndAnnotates()

}//end class
