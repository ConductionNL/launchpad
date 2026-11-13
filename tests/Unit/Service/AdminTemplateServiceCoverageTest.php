<?php

/**
 * AdminTemplateServiceCoverageTest
 *
 * Unit tests for the admin-template CRUD surface of AdminTemplateService
 * that the routing and gallery suites leave untouched:
 *   - `listTemplates` delegates to `findAdminTemplates`.
 *   - `getTemplateWithPlacements`, `updateTemplate` and `deleteTemplate`
 *     reject non-template rows with "Not an admin template".
 *   - `updateTemplate` applies identity, behaviour and gallery fields
 *     (explicit null clears gallery fields).
 *   - `uploadPreviewImage` (REQ-TMPL-017): unwired resource service,
 *     non-template row and happy path.
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

use Exception;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\AdminTemplateStores;
use OCA\LaunchPad\Service\ResourceService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * CRUD and preview-upload scenarios for AdminTemplateService.
 *
 * @covers \OCA\LaunchPad\Service\AdminTemplateService
 * @uses   \OCA\LaunchPad\Service\AdminTemplateStores
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors constructor.
 */
class AdminTemplateServiceCoverageTest extends TestCase {
	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	/** @var ResourceService&MockObject */
	private $resourceService;

	private AdminTemplateService $service;

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->resourceService = $this->createMock(ResourceService::class);

		$this->service = $this->buildService(resourceService: $this->resourceService);
	}//end setUp()

	/**
	 * Build the service with an optional resource service.
	 *
	 * @param ResourceService|null $resourceService The resource service or null.
	 *
	 * @return AdminTemplateService
	 */
	private function buildService(?ResourceService $resourceService): AdminTemplateService {
		return new AdminTemplateService(
			stores: new AdminTemplateStores(
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->placementMapper,
				resourceService: $resourceService
			),
			settingsService: $this->createMock(AdminSettingsService::class),
			groupManager: $this->createMock(IGroupManager::class),
			userManager: $this->createMock(IUserManager::class)
		);
	}//end buildService()

	/**
	 * Build a dashboard row of the given type.
	 *
	 * @param string $type The dashboard type.
	 *
	 * @return Dashboard
	 */
	private function makeDashboard(string $type): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(7);
		$dashboard->setUuid('tmpl-uuid-7');
		$dashboard->setName('Original');
		$dashboard->setDescription('Original description');
		$dashboard->setType($type);
		$dashboard->setGridColumns(12);
		$dashboard->setPermissionLevel(Dashboard::PERMISSION_ADD_ONLY);
		$dashboard->setIsDefault(0);
		$dashboard->setTemplateCategory('ops');
		$dashboard->setTemplateDescription('Gallery blurb');
		$dashboard->setTemplatePreviewImage('/old.png');
		$dashboard->setUpdatedAt('2020-01-01 00:00:00');
		return $dashboard;
	}//end makeDashboard()

	// ---------------------------------------------------------------
	// listTemplates / getTemplateWithPlacements
	// ---------------------------------------------------------------

	/**
	 * @return void
	 */
	public function testListTemplatesReturnsMapperResult(): void {
		$templates = [
			$this->makeDashboard(Dashboard::TYPE_ADMIN_TEMPLATE),
			$this->makeDashboard(Dashboard::TYPE_ADMIN_TEMPLATE),
		];
		$this->dashboardMapper->expects($this->once())
			->method('findAdminTemplates')
			->willReturn($templates);

		$this->assertSame($templates, $this->service->listTemplates());
	}//end testListTemplatesReturnsMapperResult()

	/**
	 * @return void
	 */
	public function testGetTemplateWithPlacementsReturnsTemplateAndPlacements(): void {
		$template = $this->makeDashboard(Dashboard::TYPE_ADMIN_TEMPLATE);
		$placements = [new WidgetPlacement(), new WidgetPlacement()];

		$this->dashboardMapper->expects($this->once())
			->method('find')
			->with(7)
			->willReturn($template);
		$this->placementMapper->expects($this->once())
			->method('findByDashboardId')
			->with(7)
			->willReturn($placements);

		$result = $this->service->getTemplateWithPlacements(id: 7);

		$this->assertSame(
			['template' => $template, 'placements' => $placements],
			$result
		);
	}//end testGetTemplateWithPlacementsReturnsTemplateAndPlacements()

	/**
	 * @return void
	 */
	public function testGetTemplateWithPlacementsRejectsNonTemplate(): void {
		$this->dashboardMapper->method('find')
			->with(7)
			->willReturn($this->makeDashboard(Dashboard::TYPE_USER));
		$this->placementMapper->expects($this->never())->method('findByDashboardId');

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Not an admin template');

		$this->service->getTemplateWithPlacements(id: 7);
	}//end testGetTemplateWithPlacementsRejectsNonTemplate()

	// ---------------------------------------------------------------
	// updateTemplate
	// ---------------------------------------------------------------

	/**
	 * @return void
	 */
	public function testUpdateTemplateRejectsNonTemplate(): void {
		$this->dashboardMapper->method('find')
			->with(7)
			->willReturn($this->makeDashboard(Dashboard::TYPE_GROUP_SHARED));
		$this->dashboardMapper->expects($this->never())->method('update');

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Not an admin template');

		$this->service->updateTemplate(id: 7, data: ['name' => 'X']);
	}//end testUpdateTemplateRejectsNonTemplate()

	/**
	 * @return void
	 */
	public function testUpdateTemplateAppliesAllFields(): void {
		$template = $this->makeDashboard(Dashboard::TYPE_ADMIN_TEMPLATE);

		$this->dashboardMapper->method('find')->with(7)->willReturn($template);
		$this->dashboardMapper->expects($this->once())->method('clearDefaultTemplates');
		$this->dashboardMapper->expects($this->once())
			->method('update')
			->with($this->identicalTo($template))
			->willReturnArgument(0);

		$result = $this->service->updateTemplate(
			id: 7,
			data: [
				'name' => 'Renamed',
				'description' => 'New description',
				'targetGroups' => ['admin', 'staff'],
				'permissionLevel' => Dashboard::PERMISSION_VIEW_ONLY,
				'isDefault' => true,
				'gridColumns' => 6,
				'templateCategory' => 'finance',
				'templateDescription' => 'New blurb',
				'templatePreviewImage' => '/new.png',
			]
		);

		$this->assertSame($template, $result);
		$this->assertSame('Renamed', $result->getName());
		$this->assertSame('New description', $result->getDescription());
		$this->assertSame(['admin', 'staff'], $result->getTargetGroupsArray());
		$this->assertSame(Dashboard::PERMISSION_VIEW_ONLY, $result->getPermissionLevel());
		$this->assertSame(1, $result->getIsDefault());
		$this->assertSame(6, $result->getGridColumns());
		$this->assertSame('finance', $result->getTemplateCategory());
		$this->assertSame('New blurb', $result->getTemplateDescription());
		$this->assertSame('/new.png', $result->getTemplatePreviewImage());
		$this->assertNotSame('2020-01-01 00:00:00', $result->getUpdatedAt());
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			(string)$result->getUpdatedAt()
		);
	}//end testUpdateTemplateAppliesAllFields()

	/**
	 * Explicit null clears gallery fields; absent / null identity fields
	 * are left alone.
	 *
	 * @return void
	 */
	public function testUpdateTemplateNullGalleryFieldsClearButIdentityNullIsIgnored(): void {
		$template = $this->makeDashboard(Dashboard::TYPE_ADMIN_TEMPLATE);

		$this->dashboardMapper->method('find')->with(7)->willReturn($template);
		$this->dashboardMapper->expects($this->never())->method('clearDefaultTemplates');
		$this->dashboardMapper->expects($this->once())
			->method('update')
			->willReturnArgument(0);

		$result = $this->service->updateTemplate(
			id: 7,
			data: [
				'description' => null,
				'isDefault' => false,
				'templateCategory' => null,
				'templateDescription' => null,
				'templatePreviewImage' => null,
			]
		);

		$this->assertSame('Original', $result->getName());
		$this->assertSame('Original description', $result->getDescription());
		$this->assertSame(Dashboard::PERMISSION_ADD_ONLY, $result->getPermissionLevel());
		$this->assertSame(12, $result->getGridColumns());
		$this->assertSame(0, $result->getIsDefault());
		$this->assertNull($result->getTemplateCategory());
		$this->assertNull($result->getTemplateDescription());
		$this->assertNull($result->getTemplatePreviewImage());
	}//end testUpdateTemplateNullGalleryFieldsClearButIdentityNullIsIgnored()

	// ---------------------------------------------------------------
	// deleteTemplate
	// ---------------------------------------------------------------

	/**
	 * @return void
	 */
	public function testDeleteTemplateRejectsNonTemplate(): void {
		$this->dashboardMapper->method('find')
			->with(7)
			->willReturn($this->makeDashboard(Dashboard::TYPE_USER));
		$this->placementMapper->expects($this->never())->method('deleteByDashboardId');
		$this->dashboardMapper->expects($this->never())->method('delete');

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Not an admin template');

		$this->service->deleteTemplate(id: 7);
	}//end testDeleteTemplateRejectsNonTemplate()

	// ---------------------------------------------------------------
	// uploadPreviewImage (REQ-TMPL-017)
	// ---------------------------------------------------------------

	/**
	 * @return void
	 */
	public function testUploadPreviewImageThrowsWhenResourceServiceUnwired(): void {
		$service = $this->buildService(resourceService: null);
		$this->dashboardMapper->expects($this->never())->method('findByUuid');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('ResourceService is not wired in');

		$service->uploadPreviewImage(
			templateUuid: 'tmpl-uuid-7',
			base64DataUrl: 'data:image/png;base64,AAAA'
		);
	}//end testUploadPreviewImageThrowsWhenResourceServiceUnwired()

	/**
	 * @return void
	 */
	public function testUploadPreviewImageRejectsNonTemplate(): void {
		$this->dashboardMapper->expects($this->once())
			->method('findByUuid')
			->with('dash-uuid')
			->willReturn($this->makeDashboard(Dashboard::TYPE_USER));
		$this->resourceService->expects($this->never())->method('upload');
		$this->dashboardMapper->expects($this->never())->method('update');

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Not an admin template');

		$this->service->uploadPreviewImage(
			templateUuid: 'dash-uuid',
			base64DataUrl: 'data:image/png;base64,AAAA'
		);
	}//end testUploadPreviewImageRejectsNonTemplate()

	/**
	 * @return void
	 */
	public function testUploadPreviewImagePersistsUploadedUrl(): void {
		$template = $this->makeDashboard(Dashboard::TYPE_ADMIN_TEMPLATE);
		$dataUrl = 'data:image/png;base64,iVBORw0KGgo=';

		$this->dashboardMapper->expects($this->once())
			->method('findByUuid')
			->with('tmpl-uuid-7')
			->willReturn($template);
		$this->resourceService->expects($this->once())
			->method('upload')
			->with($dataUrl)
			->willReturn(['url' => '/apps/launchpad/resource/abc.png', 'name' => 'abc.png']);
		$this->dashboardMapper->expects($this->once())
			->method('update')
			->with($this->callback(
				static fn (Dashboard $d): bool => $d === $template
					&& $d->getTemplatePreviewImage() === '/apps/launchpad/resource/abc.png'
			))
			->willReturnArgument(0);

		$url = $this->service->uploadPreviewImage(
			templateUuid: 'tmpl-uuid-7',
			base64DataUrl: $dataUrl
		);

		$this->assertSame('/apps/launchpad/resource/abc.png', $url);
		$this->assertNotSame('2020-01-01 00:00:00', $template->getUpdatedAt());
	}//end testUploadPreviewImagePersistsUploadedUrl()
}//end class
