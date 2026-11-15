<?php

/**
 * Unit tests for DashboardTranslationApiController::resolved().
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\ActionCaller;
use OCA\LaunchPad\Controller\DashboardTranslationApiController;
use OCA\LaunchPad\Controller\TranslationSources;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardTranslation;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DashboardTranslationService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Which language variant a dashboard is shown in, and what the response tells the client.
 *
 * @covers \OCA\LaunchPad\Controller\DashboardTranslationApiController
 *
 * @uses \OCA\LaunchPad\Controller\ActionCaller
 * @uses \OCA\LaunchPad\Controller\ResponseHelper
 * @uses \OCA\LaunchPad\Controller\TranslationSources
 * @uses \OCA\LaunchPad\Db\Dashboard
 * @uses \OCA\LaunchPad\Db\DashboardTranslation
 * @uses \OCA\LaunchPad\Db\DashboardTranslationMapper
 */
class DashboardTranslationApiControllerResolvedTest extends TestCase {

	/**
	 * The request double.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest $request;

	/**
	 * The dashboard mapper double.
	 *
	 * @var DashboardMapper&MockObject
	 */
	private DashboardMapper $dashboards;

	/**
	 * The translation service double.
	 *
	 * @var DashboardTranslationService&MockObject
	 */
	private DashboardTranslationService $translations;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->dashboards = $this->createMock(DashboardMapper::class);
		$this->translations = $this->createMock(DashboardTranslationService::class);
		$dashboard = new Dashboard();
		$dashboard->setId(7);
		$dashboard->setUuid('uuid-a');
		$dashboard->setName('Intranet');
		$this->dashboards->method('findByUuid')->willReturnCallback(
			static function (string $uuid) use ($dashboard): Dashboard {
				if ($uuid !== 'uuid-a') {
					throw new DoesNotExistException('none');
				}

				return $dashboard;
			}
		);
	}//end setUp()

	/**
	 * The controller under test, for a signed-in user allowed every action.
	 *
	 * @return DashboardTranslationApiController
	 */
	private function controller(): DashboardTranslationApiController {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new DashboardTranslationApiController(
			request: $this->request,
			translations: new TranslationSources(dashboardMapper: $this->dashboards, translationService: $this->translations),
			caller: new ActionCaller(actionAuth: $this->createMock(ActionAuthService::class), userSession: $session),
			userId: 'alice',
		);
	}//end controller()

	/**
	 * A translation variant in the given language.
	 *
	 * @param string $code The language code.
	 *
	 * @return DashboardTranslation
	 */
	private static function variant(string $code): DashboardTranslation {
		$variant = new DashboardTranslation();
		$variant->setDashboardUuid('uuid-a');
		$variant->setLanguageCode($code);

		return $variant;
	}//end variant()

	/**
	 * An unknown dashboard is a 404.
	 *
	 * @return void
	 */
	public function testAnUnknownDashboardIsNotFound(): void {
		self::assertSame(Http::STATUS_NOT_FOUND, $this->controller()->resolved('uuid-x')->getStatus());
	}//end testAnUnknownDashboardIsNotFound()

	/**
	 * Without a language the locale variant is shown with its languages.
	 *
	 * @return void
	 */
	public function testWithoutALanguageTheLocaleVariantIsShown(): void {
		$this->request->method('getParam')->with('lang')->willReturn(null);
		$this->translations->expects(self::once())->method('resolveForLocale')->with('uuid-a', '')
			->willReturn(['translation' => self::variant('nl'), 'isFallback' => false]);
		$this->translations->method('listAvailableLanguages')->with('uuid-a')->willReturn(['nl', 'en']);
		$this->translations->expects(self::never())->method('materialiseLegacyVariant');

		$data = $this->controller()->resolved('uuid-a')->getData();

		self::assertSame('nl', $data['currentLanguage']);
		self::assertSame(['nl', 'en'], $data['availableLanguages']);
		self::assertFalse($data['isFallback']);
		self::assertSame('Intranet', $data['dashboard']['name']);
	}//end testWithoutALanguageTheLocaleVariantIsShown()

	/**
	 * A dashboard without variants is shown through its materialised legacy variant,
	 * whose language is the only one offered.
	 *
	 * @return void
	 */
	public function testADashboardWithoutVariantsFallsBackToItsLegacyVariant(): void {
		$this->request->method('getParam')->willReturn('');
		$this->translations->method('resolveForLocale')->willReturn(null);
		$this->translations->expects(self::once())->method('materialiseLegacyVariant')->willReturn(self::variant('de'));
		$this->translations->method('listAvailableLanguages')->willReturn([]);

		$data = $this->controller()->resolved('uuid-a')->getData();

		self::assertSame('de', $data['currentLanguage']);
		self::assertSame(['de'], $data['availableLanguages']);
		self::assertTrue($data['isFallback']);
	}//end testADashboardWithoutVariantsFallsBackToItsLegacyVariant()

	/**
	 * A legacy variant without a language offers no languages.
	 *
	 * @return void
	 */
	public function testALegacyVariantWithoutALanguageOffersNone(): void {
		$this->translations->method('resolveForLocale')->willReturn(null);
		$this->translations->method('materialiseLegacyVariant')->willReturn(self::variant(''));
		$this->translations->method('listAvailableLanguages')->willReturn([]);

		self::assertSame([], $this->controller()->resolved('uuid-a')->getData()['availableLanguages']);
	}//end testALegacyVariantWithoutALanguageOffersNone()

	/**
	 * An explicit language must match exactly; a nearer variant is not substituted.
	 *
	 * @return void
	 */
	public function testAnExplicitLanguageMustMatchExactly(): void {
		$this->request->method('getParam')->with('lang')->willReturn('fr');
		$this->translations->method('resolveForLocale')->with('uuid-a', 'fr')
			->willReturn(['translation' => self::variant('nl'), 'isFallback' => true]);

		$response = $this->controller()->resolved('uuid-a');

		self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}//end testAnExplicitLanguageMustMatchExactly()
}//end class
