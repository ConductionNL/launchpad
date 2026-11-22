<?php

/**
 * Unit tests for DashboardWidgetRegistry: the widget list without load().
 *
 * @category Test
 * @package  Unit\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\DashboardWidgetRegistry;
use OCA\LaunchPad\Service\WidgetFormatter;
use OCA\LaunchPad\Service\WidgetItemLoader;
use OCA\LaunchPad\Service\WidgetPlatform;
use OCA\LaunchPad\Service\WidgetRendering;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\App\IAppManager;
use OCP\Dashboard\IConditionalWidget;
use OCP\Dashboard\IManager;
use OCP\Dashboard\IWidget;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * A widget that records whether anything loaded it.
 */
class RecordingWidget implements IWidget {
	public int $loads = 0;

	public function __construct(
		private readonly string $id,
	) {
	}

	public function getId(): string {
		return $this->id;
	}

	public function getTitle(): string {
		return ucfirst($this->id);
	}

	public function getOrder(): int {
		return 0;
	}

	public function getIconClass(): string {
		return '';
	}

	public function getUrl(): ?string {
		return null;
	}

	public function load(): void {
		$this->loads++;
	}
}

/**
 * A widget that is switched off.
 */
class SwitchedOffWidget extends RecordingWidget implements IConditionalWidget {
	public function isEnabled(): bool {
		return false;
	}
}

/**
 * A dashboard manager with the shape of Nextcloud's: pending registrations
 * that getWidgets() builds and loads.
 */
class PendingManager implements IManager {
	/** @var list<array{class: string, appId: string}> */
	private array $lazyWidgets = [];

	public int $getWidgetsCalls = 0;

	/** @var array<string, IWidget> */
	public array $loaded = [];

	public function lazyRegisterWidget(string $widgetClass, string $appId): void {
		$this->lazyWidgets[] = ['class' => $widgetClass, 'appId' => $appId];
	}

	public function getWidgets(): array {
		$this->getWidgetsCalls++;
		return $this->loaded;
	}

	public function markLoaded(): void {
		$this->lazyWidgets = [];
	}
}

/**
 * @covers \OCA\LaunchPad\Service\DashboardWidgetRegistry
 * @covers \OCA\LaunchPad\Service\WidgetService
 */
class DashboardWidgetRegistryTest extends TestCase {
	/** @var array<string, IWidget> */
	private array $built = [];

	private function container(): ContainerInterface {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(function (string $class): object {
			if (isset($this->built[$class]) === false) {
				throw new RuntimeException('unknown ' . $class);
			}
			return $this->built[$class];
		});
		return $container;
	}

	private function apps(string ...$disabled): IAppManager {
		$apps = $this->createMock(IAppManager::class);
		$apps->method('isEnabledForUser')->willReturnCallback(
			static fn (string $appId): bool => in_array($appId, $disabled, true) === false
		);
		return $apps;
	}

	public function testPendingWidgetsAreBuiltAndNeverLoaded(): void {
		$activity = new RecordingWidget('activity');
		$notes = new RecordingWidget('notes');
		$this->built = ['Activity' => $activity, 'Notes' => $notes];
		$manager = new PendingManager();
		$manager->lazyRegisterWidget('Activity', 'activity');
		$manager->lazyRegisterWidget('Notes', 'notes');

		$widgets = (new DashboardWidgetRegistry($manager, $this->apps(), $this->container()))->widgetsWithoutLoading();

		$this->assertSame([$activity, $notes], $widgets);
		$this->assertSame(0, $activity->loads + $notes->loads, 'no widget may be loaded');
		$this->assertSame(0, $manager->getWidgetsCalls, 'getWidgets() loads every widget');
	}

	public function testDisabledAppsSwitchedOffAndUnbuildableWidgetsAreLeftOut(): void {
		$this->built = [
			'Kept' => new RecordingWidget('kept'),
			'Off' => new SwitchedOffWidget('off'),
			'Disabled' => new RecordingWidget('disabled'),
			'NotAWidget' => new \stdClass(),
		];
		$manager = new PendingManager();
		foreach (['Kept' => 'kept', 'Off' => 'off', 'Disabled' => 'disabled', 'NotAWidget' => 'x', 'Missing' => 'y'] as $class => $app) {
			$manager->lazyRegisterWidget($class, $app);
		}
		$manager->lazyRegisterWidget('Kept', 'kept');

		$widgets = (new DashboardWidgetRegistry($manager, $this->apps('disabled'), $this->container()))->widgetsWithoutLoading();

		$this->assertSame(['kept'], array_map(static fn (IWidget $w): string => $w->getId(), $widgets));
	}

	public function testAnAlreadyLoadedRegistryIsReturnedAsItIs(): void {
		$manager = new PendingManager();
		$manager->loaded = ['activity' => new RecordingWidget('activity')];
		$manager->markLoaded();

		$widgets = (new DashboardWidgetRegistry($manager, $this->apps(), $this->container()))->widgetsWithoutLoading();

		$this->assertSame([$manager->loaded['activity']], $widgets);
	}

	public function testAManagerWithoutPendingRegistrationsGivesNull(): void {
		$manager = $this->createMock(IManager::class);
		$manager->expects($this->never())->method('getWidgets');

		$this->assertNull((new DashboardWidgetRegistry($manager, $this->apps(), $this->container()))->widgetsWithoutLoading());
	}

	public function testTheServiceDescribesTheCatalogueOrNull(): void {
		$activity = new RecordingWidget('activity');
		$this->built = ['Activity' => $activity];
		$manager = new PendingManager();
		$manager->lazyRegisterWidget('Activity', 'activity');
		$session = $this->createMock(IUserSession::class);
		$service = new WidgetService(
			platform: new WidgetPlatform(
				dashboardManager: $manager,
				userSession: $session,
				registry: new DashboardWidgetRegistry($manager, $this->apps(), $this->container())
			),
			placementService: $this->createMock(PlacementService::class),
			rendering: new WidgetRendering(widgetFormatter: new WidgetFormatter(), widgetItemLoader: $this->createMock(WidgetItemLoader::class))
		);

		$catalogue = $service->getWidgetCatalogue();

		$this->assertSame('activity', $catalogue[0]['id']);
		$this->assertSame('Activity', $catalogue[0]['title']);
		$this->assertSame(0, $activity->loads);

		$without = new WidgetService(
			platform: new WidgetPlatform(dashboardManager: $manager, userSession: $session),
			placementService: $this->createMock(PlacementService::class),
			rendering: new WidgetRendering(widgetFormatter: new WidgetFormatter(), widgetItemLoader: $this->createMock(WidgetItemLoader::class))
		);
		$this->assertNull($without->getWidgetCatalogue());
	}
}
