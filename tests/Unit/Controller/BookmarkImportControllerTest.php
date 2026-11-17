<?php

/**
 * BookmarkImportControllerTest
 *
 * `POST /api/dashboard/{dashboardId}/tiles/import`: 401 without a session, the
 * action check and the dashboard's add right before anything else, 400 for a
 * malformed request, 409 when the import does not fit the quota, 201 with the
 * created placements and the skipped bookmarks.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\BookmarkImportController;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\BookmarkImportPlan;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Controller\BookmarkImportController
 * @uses   \OCA\LaunchPad\Controller\RequestActor
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Service\BookmarkImportPlan
 * @uses   \OCA\LaunchPad\Exception\QuotaExceededException
 */
class BookmarkImportControllerTest extends TestCase {
	/** @var IRequest&MockObject */
	private $request;

	/** @var WidgetService&MockObject */
	private $widgetService;

	/** @var PermissionService&MockObject */
	private $permissions;

	/** @var IUserSession&MockObject */
	private $session;

	/** @var ActionAuthService&MockObject */
	private $actionAuth;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->widgetService = $this->createMock(WidgetService::class);
		$this->permissions = $this->createMock(PermissionService::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->actionAuth = $this->createMock(ActionAuthService::class);
	}//end setUp()

	/**
	 * The controller for a signed-in Pieter, posting the given body.
	 *
	 * @param array<string, mixed> $body The posted folders and bookmarks.
	 *
	 * @return BookmarkImportController
	 */
	private function controller(array $body): BookmarkImportController {
		$this->session->method('getUser')->willReturn($this->createMock(IUser::class));
		$this->request->method('getParam')->willReturnCallback(static fn (string $key, $default = null) => ($body[$key] ?? $default));
		return new BookmarkImportController($this->request, $this->widgetService, $this->permissions, new RequestActor($this->session, $this->actionAuth), 'pieter');
	}//end controller()

	/**
	 * One folder and a bookmarklet to import.
	 *
	 * @return array<string, mixed>
	 */
	private static function body(): array {
		return [
			'folders' => [['name' => 'Werk', 'bookmarks' => [['title' => 'Wiki', 'url' => 'https://wiki.example']]]],
			'bookmarks' => [['title' => 'Run', 'url' => 'javascript:alert(1)']],
		];
	}//end body()

	/**
	 * No session: 401, nothing checked or created.
	 *
	 * @return void
	 */
	public function testWithoutASessionTheImportIs401(): void {
		$this->session->method('getUser')->willReturn(null);
		$this->widgetService->expects(self::never())->method('importBookmarks');
		$controller = new BookmarkImportController($this->request, $this->widgetService, $this->permissions, new RequestActor($this->session, $this->actionAuth), null);

		self::assertSame(401, $controller->import(dashboardId: 3)->getStatus());
	}//end testWithoutASessionTheImportIs401()

	/**
	 * The import action is checked by its own name, and a refusal stops it.
	 *
	 * @return void
	 */
	public function testTheImportActionIsRequired(): void {
		$this->actionAuth->expects(self::once())->method('requireAction')
			->with(self::isInstanceOf(IUser::class), 'widget.import-bookmarks')
			->willThrowException(new OCSForbiddenException('no'));
		$this->widgetService->expects(self::never())->method('importBookmarks');

		$this->expectException(OCSForbiddenException::class);
		$this->controller(self::body())->import(dashboardId: 3);
	}//end testTheImportActionIsRequired()

	/**
	 * A user who may not add to the dashboard gets 403 and nothing is created
	 * (the view-only dashboard of REQ-BMI-001).
	 *
	 * @return void
	 */
	public function testAViewOnlyDashboardIs403(): void {
		$this->permissions->expects(self::once())->method('canAddWidget')->with('pieter', 3)->willReturn(false);
		$this->widgetService->expects(self::never())->method('importBookmarks');

		self::assertSame(403, $this->controller(self::body())->import(dashboardId: 3)->getStatus());
	}//end testAViewOnlyDashboardIs403()

	/**
	 * A malformed request is 400 with the reason.
	 *
	 * @return void
	 */
	public function testAMalformedRequestIs400(): void {
		$this->permissions->method('canAddWidget')->willReturn(true);
		$this->widgetService->expects(self::never())->method('importBookmarks');

		$response = $this->controller(['folders' => 'x'])->import(dashboardId: 3);
		self::assertSame(400, $response->getStatus());
		self::assertStringContainsString('must be a list', json_encode($response->getData()));
	}//end testAMalformedRequestIs400()

	/**
	 * An import that does not fit the quota is 409 with the quota body
	 * (REQ-BMI-003).
	 *
	 * @return void
	 */
	public function testAnImportOverTheQuotaIs409(): void {
		$this->permissions->method('canAddWidget')->willReturn(true);
		$this->widgetService->method('importBookmarks')->willThrowException(new QuotaExceededException(quota: QuotaExceededException::QUOTA_WIDGETS, limit: 20, current: 18));

		$response = $this->controller(self::body())->import(dashboardId: 3);
		self::assertSame(409, $response->getStatus());
		self::assertSame(20, $response->getData()['limit']);
		self::assertSame(18, $response->getData()['current']);
	}//end testAnImportOverTheQuotaIs409()

	/**
	 * A good import is 201 with the created placements and the skipped
	 * bookmarklet and its reason (REQ-BMI-002).
	 *
	 * @return void
	 */
	public function testAnImportIs201WithWhatWasCreatedAndSkipped(): void {
		$this->permissions->method('canAddWidget')->willReturn(true);
		$placement = new WidgetPlacement();
		$placement->setWidgetId('container');
		$this->widgetService->expects(self::once())->method('importBookmarks')
			->with(3, self::callback(static fn (BookmarkImportPlan $plan): bool => $plan->placementCount() === 1))
			->willReturn([$placement]);

		$response = $this->controller(self::body())->import(dashboardId: 3);

		self::assertSame(201, $response->getStatus());
		$data = $response->getData();
		self::assertCount(1, $data['placements']);
		self::assertSame('container', $data['placements'][0]['widgetId']);
		self::assertSame([['title' => 'Run', 'url' => 'javascript:alert(1)', 'reason' => 'only web addresses can become tiles']], $data['skipped']);
	}//end testAnImportIs201WithWhatWasCreatedAndSkipped()
}//end class
