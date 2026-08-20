<?php

/**
 * WidgetServiceUpdateValidationTest
 *
 * Regression coverage for save-time content validation on the placement
 * UPDATE path. Once `content` became an extracted placement field, the
 * update route became a content-write path and MUST run the same
 * menu-content validation (REQ-MENU-002) as the create path
 * ({@see WidgetService::addWidget()}); otherwise a user with style
 * permission could PUT malformed / over-nested menu content the create
 * path rejects.
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
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\MenuService;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\WidgetFormatter;
use OCA\LaunchPad\Service\WidgetItemLoader;
use OCA\LaunchPad\Service\WidgetService;
use OCP\Dashboard\IManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for {@see WidgetService::updatePlacement()} content validation.
 */
class WidgetServiceUpdateValidationTest extends TestCase {

	/** @var PlacementService&MockObject */
	private $placementService;

	private WidgetService $service;

	/**
	 * Build the SUT with a real MenuService so the depth / field rules
	 * actually execute; the other collaborators are irrelevant to the
	 * update path and are mocked.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->placementService = $this->createMock(PlacementService::class);

		$this->service = new WidgetService(
			dashboardManager: $this->createMock(IManager::class),
			placementService: $this->placementService,
			widgetFormatter: $this->createMock(WidgetFormatter::class),
			widgetItemLoader: $this->createMock(WidgetItemLoader::class),
			userSession: $this->createMock(IUserSession::class),
			menuService: new MenuService(),
		);
	}//end setUp()

	/**
	 * A menu placement updated with over-nested items (4 levels) MUST be
	 * rejected — the same way the create path rejects it — and MUST NOT
	 * reach the persistence layer.
	 *
	 * @return void
	 */
	public function testUpdateRejectsOverNestedMenuContent(): void {
		$placement = new WidgetPlacement();
		$placement->setWidgetId('menu');

		$this->placementService
			->method('getPlacement')
			->with(placementId: 7)
			->willReturn($placement);

		// Persistence MUST NOT be touched when validation fails.
		$this->placementService->expects($this->never())->method('updatePlacement');

		// items nested 4 deep -> exceeds MenuService::MAX_DEPTH (3).
		$content = [
			'items' => [
				['children' => [
					['children' => [
						['children' => [
							['label' => 'too deep'],
						]],
					]],
				]],
			],
		];

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Menu items can nest at most 3 levels deep');

		$this->service->updatePlacement(
			placementId: 7,
			data: ['content' => $content]
		);
	}//end testUpdateRejectsOverNestedMenuContent()

	/**
	 * A menu placement updated with well-formed content (within the depth
	 * cap) MUST pass validation and delegate to the persistence layer.
	 *
	 * @return void
	 */
	public function testUpdateAcceptsValidMenuContent(): void {
		$placement = new WidgetPlacement();
		$placement->setWidgetId('menu');

		$this->placementService
			->method('getPlacement')
			->with(placementId: 7)
			->willReturn($placement);

		$updated = new WidgetPlacement();
		$this->placementService
			->expects($this->once())
			->method('updatePlacement')
			->willReturn($updated);

		$content = [
			'items' => [
				['label' => 'Top', 'children' => [['label' => 'Child']]],
			],
		];

		$result = $this->service->updatePlacement(
			placementId: 7,
			data: ['content' => $content]
		);

		$this->assertSame($updated, $result);
	}//end testUpdateAcceptsValidMenuContent()

	/**
	 * A non-menu widget skips content validation entirely (the validator
	 * is a no-op for non-`menu` types) and delegates straight through —
	 * even with content that would be "over-nested" for a menu.
	 *
	 * @return void
	 */
	public function testUpdateSkipsValidationForNonMenuWidget(): void {
		$placement = new WidgetPlacement();
		$placement->setWidgetId('text');

		$this->placementService
			->method('getPlacement')
			->with(placementId: 9)
			->willReturn($placement);

		$updated = new WidgetPlacement();
		$this->placementService
			->expects($this->once())
			->method('updatePlacement')
			->willReturn($updated);

		$result = $this->service->updatePlacement(
			placementId: 9,
			data: ['content' => ['items' => [['children' => [['children' => [['children' => [[]]]]]]]]]]
		);

		$this->assertSame($updated, $result);
	}//end testUpdateSkipsValidationForNonMenuWidget()

	/**
	 * An update without a `content` payload MUST NOT fetch the placement
	 * for validation — it delegates directly to the persistence layer.
	 *
	 * @return void
	 */
	public function testUpdateWithoutContentSkipsContentValidation(): void {
		$this->placementService->expects($this->never())->method('getPlacement');

		$updated = new WidgetPlacement();
		$this->placementService
			->expects($this->once())
			->method('updatePlacement')
			->willReturn($updated);

		$result = $this->service->updatePlacement(
			placementId: 7,
			data: ['customTitle' => 'Renamed']
		);

		$this->assertSame($updated, $result);
	}//end testUpdateWithoutContentSkipsContentValidation()
}//end class
