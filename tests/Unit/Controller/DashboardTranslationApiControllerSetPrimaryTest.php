<?php

/**
 * DashboardTranslationApiController set-primary Contract Test
 *
 * Wire-contract coverage for `dashboardTranslationApi#setPrimary`
 * (POST /api/dashboards/{uuid}/translations/{lang}/set-primary) —
 * REQ-DASH-043.
 *
 * Promoting a variant to primary changes which content every viewer of a
 * dashboard falls back to, so the endpoint's guard chain is the contract:
 * anonymous → 401, ADR-023 action denial → 403, unknown dashboard → 404,
 * and a dashboard owned by somebody else → 403 with NO promotion. Each
 * refusal asserts that `promoteVariantToPrimary()` was never reached.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\DashboardTranslationApiController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardTranslation;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DashboardTranslationService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for translation-variant promotion.
 */
class DashboardTranslationApiControllerSetPrimaryTest extends TestCase {

	/**
	 * HTTP request mock.
	 *
	 * @var IRequest&MockObject
	 */
	private $request;

	/**
	 * Dashboard mapper mock (ownership lookup).
	 *
	 * @var DashboardMapper&MockObject
	 */
	private $dashboardMapper;

	/**
	 * Translation service mock.
	 *
	 * @var DashboardTranslationService&MockObject
	 */
	private $translationService;

	/**
	 * Action authorization mock.
	 *
	 * @var ActionAuthService&MockObject
	 */
	private $actionAuth;

	/**
	 * User session mock.
	 *
	 * @var IUserSession&MockObject
	 */
	private $userSession;

	/**
	 * Set up shared mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->translationService = $this->createMock(DashboardTranslationService::class);
		$this->actionAuth = $this->createMock(ActionAuthService::class);
		$this->userSession = $this->createMock(IUserSession::class);

	}//end setUp()

	/**
	 * Build the controller for the supplied user (NULL = anonymous).
	 *
	 * @param string|null $userId The acting user ID.
	 *
	 * @return DashboardTranslationApiController
	 */
	private function makeController(?string $userId): DashboardTranslationApiController {
		$user = null;
		if ($userId !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($userId);
		}

		$this->userSession->method('getUser')->willReturn($user);

		return new DashboardTranslationApiController(
			request: $this->request,
			dashboardMapper: $this->dashboardMapper,
			translationService: $this->translationService,
			actionAuth: $this->actionAuth,
			userSession: $this->userSession,
			userId: $userId,
		);

	}//end makeController()

	/**
	 * Build a dashboard fixture owned by the given user.
	 *
	 * @param string $ownerId The owning user ID.
	 *
	 * @return Dashboard
	 */
	private function makeDashboard(string $ownerId): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(7);
		$dashboard->setUuid('uuid-a');
		$dashboard->setUserId($ownerId);

		return $dashboard;
	}//end makeDashboard()

	/**
	 * An anonymous caller MUST get 401 and promote nothing.
	 *
	 * @return void
	 */
	public function testSetPrimaryRejectsAnonymousWith401(): void {
		$this->translationService->expects($this->never())
			->method('promoteVariantToPrimary');

		$controller = $this->makeController(null);
		$response = $controller->setPrimary(uuid: 'uuid-a', lang: 'nl');

		$this->assertSame(
			expected: Http::STATUS_UNAUTHORIZED,
			actual: $response->getStatus()
		);

	}//end testSetPrimaryRejectsAnonymousWith401()

	/**
	 * ADR-023: an action denial from ActionAuthService becomes a 403
	 * envelope rather than an uncaught OCS exception.
	 *
	 * @return void
	 */
	public function testSetPrimaryMapsActionDenialTo403(): void {
		$this->actionAuth->method('requireAction')
			->willThrowException(new OCSForbiddenException('denied'));

		$this->translationService->expects($this->never())
			->method('promoteVariantToPrimary');

		$controller = $this->makeController('alice');
		$response = $controller->setPrimary(uuid: 'uuid-a', lang: 'nl');

		$this->assertSame(
			expected: Http::STATUS_FORBIDDEN,
			actual: $response->getStatus()
		);

	}//end testSetPrimaryMapsActionDenialTo403()

	/**
	 * An unknown dashboard UUID is a 404 and promotes nothing.
	 *
	 * @return void
	 */
	public function testSetPrimaryReturns404ForUnknownDashboard(): void {
		$this->dashboardMapper->method('findByUuid')
			->willThrowException(new DoesNotExistException(msg: 'nope'));

		$this->translationService->expects($this->never())
			->method('promoteVariantToPrimary');

		$controller = $this->makeController('alice');
		$response = $controller->setPrimary(uuid: 'uuid-missing', lang: 'nl');

		$this->assertSame(
			expected: Http::STATUS_NOT_FOUND,
			actual: $response->getStatus()
		);

	}//end testSetPrimaryReturns404ForUnknownDashboard()

	/**
	 * A dashboard owned by somebody else MUST be refused with 403 and MUST
	 * NOT have its primary variant changed.
	 *
	 * @return void
	 */
	public function testSetPrimaryRefusesNonOwnerWith403(): void {
		$this->dashboardMapper->method('findByUuid')
			->willReturn($this->makeDashboard(ownerId: 'alice'));

		$this->translationService->expects($this->never())
			->method('promoteVariantToPrimary');

		$controller = $this->makeController('mallory');
		$response = $controller->setPrimary(uuid: 'uuid-a', lang: 'nl');

		$this->assertSame(
			expected: Http::STATUS_FORBIDDEN,
			actual: $response->getStatus()
		);

	}//end testSetPrimaryRefusesNonOwnerWith403()

	/**
	 * Happy path: the owner promotes `nl` and gets the promoted variant
	 * back with `isPrimary` set.
	 *
	 * @return void
	 */
	public function testSetPrimaryPromotesVariantForTheOwner(): void {
		$this->dashboardMapper->method('findByUuid')
			->willReturn($this->makeDashboard(ownerId: 'alice'));

		$variant = new DashboardTranslation();
		$variant->setDashboardUuid('uuid-a');
		$variant->setLanguageCode('nl');
		$variant->setIsPrimary(true);

		$this->translationService->expects($this->once())
			->method('promoteVariantToPrimary')
			->with(dashboardUuid: 'uuid-a', languageCode: 'nl')
			->willReturn($variant);

		$controller = $this->makeController('alice');
		$response = $controller->setPrimary(uuid: 'uuid-a', lang: 'nl');

		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());

		$payload = $response->getData()['translation'];
		$this->assertSame(expected: 'nl', actual: $payload['languageCode']);
		$this->assertTrue(condition: (bool)$payload['isPrimary']);

	}//end testSetPrimaryPromotesVariantForTheOwner()

	/**
	 * A language that has no variant on this dashboard is a 404, not a
	 * silently-successful no-op.
	 *
	 * @return void
	 */
	public function testSetPrimaryReturns404ForUnknownLanguage(): void {
		$this->dashboardMapper->method('findByUuid')
			->willReturn($this->makeDashboard(ownerId: 'alice'));

		$this->translationService->method('promoteVariantToPrimary')
			->willThrowException(new DoesNotExistException(msg: 'no such variant'));

		$controller = $this->makeController('alice');
		$response = $controller->setPrimary(uuid: 'uuid-a', lang: 'zz');

		$this->assertSame(
			expected: Http::STATUS_NOT_FOUND,
			actual: $response->getStatus()
		);
		$this->assertSame(expected: 'not_found', actual: $response->getData()['error']);

	}//end testSetPrimaryReturns404ForUnknownLanguage()

}//end class
