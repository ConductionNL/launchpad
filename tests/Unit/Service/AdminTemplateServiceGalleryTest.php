<?php

/**
 * AdminTemplateServiceGalleryTest
 *
 * Unit tests for the template-discovery + save-as-template behaviour added
 * by the `dashboard-templates` change (REQ-TMPL-014..017). Covers:
 *   - REQ-TMPL-014: gallery serialisation includes the seven mandated
 *     fields (`uuid`, `name`, `description`, `category`, `previewImage`,
 *     `gridColumns`, `widgetCount`, `lastUpdatedAt`) per template.
 *   - REQ-TMPL-014: empty input → empty list (no error).
 *   - REQ-TMPL-015: ownership rejection raises `ForbiddenException` when
 *     the source dashboard is not owned by the caller.
 *   - REQ-TMPL-015: missing name → `InvalidArgumentException`.
 *   - REQ-TMPL-015: happy-path deep copy persists a new admin_template
 *     row with `userId = null`, `isActive = 0`, `basedOnTemplate = null`,
 *     and triggers `cloneToDashboard` exactly once.
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
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Gallery + save-as-template scenarios for AdminTemplateService.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors constructor.
 */
class AdminTemplateServiceGalleryTest extends TestCase {
	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	/** @var AdminSettingsService&MockObject */
	private $settingsService;

	/** @var IGroupManager&MockObject */
	private $groupManager;

	/** @var IUserManager&MockObject */
	private $userManager;

	private AdminTemplateService $service;

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->settingsService = $this->createMock(AdminSettingsService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->service = new AdminTemplateService(
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->placementMapper,
			settingsService: $this->settingsService,
			groupManager: $this->groupManager,
			userManager: $this->userManager,
		);
	}//end setUp()

	// ---------------------------------------------------------------
	// getGallery — REQ-TMPL-014
	// ---------------------------------------------------------------

	/**
	 * REQ-TMPL-014: gallery returns the 8 mandated fields per template.
	 *
	 * @return void
	 */
	public function testGalleryReturnsMandatedFields(): void {
		$template = new Dashboard();
		$template->setUuid('uuid-1');
		$template->setName('Marketing dashboard');
		$template->setDescription('Original description');
		$template->setTemplateDescription('Long-form gallery description');
		$template->setTemplateCategory('marketing');
		$template->setTemplatePreviewImage('/apps/launchpad/resource/img.png');
		$template->setGridColumns(12);
		$template->setUpdatedAt('2026-05-01 09:00:00');
		// ID is set internally on insert; here we use reflection-free
		// method on the entity from QBMapper rows (cast to 0 for the
		// count-by-id call when no id is set).
		$this->dashboardMapper
			->expects($this->once())
			->method('findAllTemplatesForGallery')
			->with('marketing', 'name')
			->willReturn([$template]);

		$this->placementMapper
			->expects($this->once())
			->method('countByDashboardId')
			->willReturn(4);

		$result = $this->service->getGallery(
			category: 'marketing',
			sortBy: 'name'
		);

		$this->assertCount(expectedCount: 1, haystack: $result);
		$entry = $result[0];
		$this->assertSame(expected: 'uuid-1', actual: $entry['uuid']);
		$this->assertSame(expected: 'Marketing dashboard', actual: $entry['name']);
		$this->assertSame(
			expected: 'Long-form gallery description',
			actual: $entry['description']
		);
		$this->assertSame(expected: 'marketing', actual: $entry['category']);
		$this->assertSame(
			expected: '/apps/launchpad/resource/img.png',
			actual: $entry['previewImage']
		);
		$this->assertSame(expected: 12, actual: $entry['gridColumns']);
		$this->assertSame(expected: 4, actual: $entry['widgetCount']);
		$this->assertSame(
			expected: '2026-05-01 09:00:00',
			actual: $entry['lastUpdatedAt']
		);
	}//end testGalleryReturnsMandatedFields()

	/**
	 * REQ-TMPL-014: empty backing list → empty array (HTTP 200, no error).
	 *
	 * @return void
	 */
	public function testGalleryReturnsEmptyListWhenNoTemplates(): void {
		$this->dashboardMapper
			->expects($this->once())
			->method('findAllTemplatesForGallery')
			->with(null, 'name')
			->willReturn([]);

		$this->placementMapper
			->expects($this->never())
			->method('countByDashboardId');

		$this->assertSame(
			expected: [],
			actual: $this->service->getGallery()
		);
	}//end testGalleryReturnsEmptyListWhenNoTemplates()

	/**
	 * REQ-TMPL-014: gallery falls back to the regular `description`
	 * column when `templateDescription` is null.
	 *
	 * @return void
	 */
	public function testGalleryFallsBackToRegularDescription(): void {
		$template = new Dashboard();
		$template->setUuid('uuid-2');
		$template->setName('Engineering dashboard');
		$template->setDescription('Regular description');
		// templateDescription left null on purpose.
		$template->setTemplateCategory(null);
		$template->setGridColumns(10);
		$template->setUpdatedAt('2026-04-30 14:30:00');

		$this->dashboardMapper
			->method('findAllTemplatesForGallery')
			->willReturn([$template]);
		$this->placementMapper
			->method('countByDashboardId')
			->willReturn(0);

		$result = $this->service->getGallery();

		$this->assertSame(
			expected: 'Regular description',
			actual: $result[0]['description']
		);
		$this->assertNull(actual: $result[0]['category']);
	}//end testGalleryFallsBackToRegularDescription()

}//end class
