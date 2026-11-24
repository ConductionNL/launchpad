<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * PublicWidgetController and PublicWidgetTokens: a live widget's data
 * through a public share or a kiosk playlist token (REQ-PSHR-011).
 *
 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Controller;

use OCA\LaunchPad\Controller\PublicWidgetController;
use OCA\LaunchPad\Exception\PlaylistNotFoundException;
use OCA\LaunchPad\Exception\ShareNotFoundException;
use OCA\LaunchPad\Exception\SharePasswordRequiredException;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\PublicShareService;
use OCA\LaunchPad\Service\PublicWidgetReadService;
use OCA\LaunchPad\Service\PublicWidgetTokens;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Controller\PublicWidgetController
 * @covers \OCA\LaunchPad\Service\PublicWidgetTokens
 */
class PublicWidgetControllerTest extends TestCase {

	/** @var IRequest&MockObject */
	private $request;

	/** @var PublicWidgetReadService&MockObject */
	private $reads;

	/** @var PublicShareService&MockObject */
	private $shares;

	/** @var KioskService&MockObject */
	private $kiosk;

	/** @var PublicShareContext&MockObject */
	private $shareContext;

	/**
	 * Mocks for the request, the reads and the two token kinds.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->request->method('getRemoteAddress')->willReturn(value: '10.0.0.1');
		$this->reads = $this->createMock(originalClassName: PublicWidgetReadService::class);
		$this->shares = $this->createMock(originalClassName: PublicShareService::class);
		$this->kiosk = $this->createMock(originalClassName: KioskService::class);
		$this->shareContext = $this->createMock(originalClassName: PublicShareContext::class);
	}//end setUp()

	/**
	 * The controller over the real token opener.
	 *
	 * @return PublicWidgetController
	 */
	private function controller(): PublicWidgetController {
		return new PublicWidgetController(
			request: $this->request,
			reads: $this->reads,
			tokens: new PublicWidgetTokens(shares: $this->shares, kiosk: $this->kiosk, shareContext: $this->shareContext),
		);
	}//end controller()

	/**
	 * A share reads through its dashboard, with the password from the header, as a read-only bearer.
	 *
	 * @return void
	 */
	public function testAShareReadsThroughItsDashboardWithTheHeaderPassword(): void {
		$this->request->method('getHeader')->with('X-Share-Password')->willReturn(value: 'geheim');
		$this->shares->expects($this->once())->method('sharedDashboardId')
			->with('tok', '10.0.0.1', 'geheim')
			->willReturn(value: 7);
		$this->shareContext->expects($this->once())->method('markBearer')->with('tok');
		$this->reads->expects($this->once())->method('read')
			->with([7], 4)
			->willReturn(value: ['widget' => 'weather', 'data' => ['tempValue' => 12]]);

		$response = $this->controller()->share(token: 'tok', placementId: 4);

		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: ['widget' => 'weather', 'data' => ['tempValue' => 12]], actual: $response->getData());
	}//end testAShareReadsThroughItsDashboardWithTheHeaderPassword()

	/**
	 * A password-protected share without the password answers 401 and reads nothing.
	 *
	 * @return void
	 */
	public function testAPasswordProtectedShareWithoutThePasswordAnswers401(): void {
		$this->request->method('getHeader')->willReturn(value: '');
		$this->shares->method('sharedDashboardId')->with('tok', '10.0.0.1', null)
			->willThrowException(exception: new SharePasswordRequiredException());
		$this->reads->expects($this->never())->method('read');

		$response = $this->controller()->share(token: 'tok', placementId: 4);

		$this->assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $response->getStatus());
		$this->assertSame(expected: ['passwordRequired' => true], actual: $response->getData());
	}//end testAPasswordProtectedShareWithoutThePasswordAnswers401()

	/**
	 * An unknown share token answers 404 and is throttled.
	 *
	 * @return void
	 */
	public function testAnUnknownShareTokenIsThrottled(): void {
		$this->request->method('getHeader')->willReturn(value: '');
		$this->shares->method('sharedDashboardId')->willThrowException(exception: new ShareNotFoundException());
		$this->shareContext->expects($this->never())->method('markBearer');

		$response = $this->controller()->share(token: 'bogus', placementId: 4);

		$this->assertSame(expected: Http::STATUS_NOT_FOUND, actual: $response->getStatus());
		$this->assertTrue(condition: $response->isThrottled());
	}//end testAnUnknownShareTokenIsThrottled()

	/**
	 * A share whose dashboard was deleted answers 404 and is throttled.
	 *
	 * @return void
	 */
	public function testAShareOfADeletedDashboardIsThrottled(): void {
		$this->request->method('getHeader')->willReturn(value: '');
		$this->shares->method('sharedDashboardId')->willThrowException(exception: new DoesNotExistException(msg: 'deleted'));

		$response = $this->controller()->share(token: 'tok', placementId: 4);

		$this->assertSame(expected: Http::STATUS_NOT_FOUND, actual: $response->getStatus());
		$this->assertTrue(condition: $response->isThrottled());
	}//end testAShareOfADeletedDashboardIsThrottled()

	/**
	 * A kiosk screen reads through the dashboards of its playlist.
	 *
	 * @return void
	 */
	public function testAKioskReadsThroughItsPlaylistsDashboards(): void {
		$this->kiosk->method('playlistDashboardIds')->with('tok')->willReturn(value: [7, 3]);
		$this->shareContext->expects($this->once())->method('markBearer')->with('tok');
		$this->reads->expects($this->once())->method('read')
			->with([7, 3], 6)
			->willReturn(value: ['widget' => 'announcements', 'data' => ['announcements' => []]]);

		$response = $this->controller()->kiosk(token: 'tok', placementId: 6);

		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: 'announcements', actual: $response->getData()['widget']);
	}//end testAKioskReadsThroughItsPlaylistsDashboards()

	/**
	 * An unknown playlist token answers 404 and is throttled.
	 *
	 * @return void
	 */
	public function testAnUnknownPlaylistTokenIsThrottled(): void {
		$this->kiosk->method('playlistDashboardIds')->willThrowException(exception: new PlaylistNotFoundException());

		$response = $this->controller()->kiosk(token: 'bogus', placementId: 6);

		$this->assertSame(expected: Http::STATUS_NOT_FOUND, actual: $response->getStatus());
		$this->assertTrue(condition: $response->isThrottled());
	}//end testAnUnknownPlaylistTokenIsThrottled()

	/**
	 * A placement that is not on the dashboard answers 404, without throttling a valid token.
	 *
	 * @return void
	 */
	public function testAPlacementNotOnTheDashboardAnswers404WithoutThrottling(): void {
		$this->kiosk->method('playlistDashboardIds')->willReturn(value: [7]);
		$this->reads->method('read')->willThrowException(exception: new DoesNotExistException(msg: 'not shared'));

		$response = $this->controller()->kiosk(token: 'tok', placementId: 99);

		$this->assertSame(expected: Http::STATUS_NOT_FOUND, actual: $response->getStatus());
		$this->assertFalse(condition: $response->isThrottled());
	}//end testAPlacementNotOnTheDashboardAnswers404WithoutThrottling()

	/**
	 * A weather reading that failed answers 502, as on the dashboard.
	 *
	 * @return void
	 */
	public function testAFailedWeatherReadingAnswers502(): void {
		$this->kiosk->method('playlistDashboardIds')->willReturn(value: [7]);
		$this->reads->method('read')->willReturn(value: ['widget' => 'weather', 'data' => ['error' => 'provider_unreachable']]);

		$response = $this->controller()->kiosk(token: 'tok', placementId: 4);

		$this->assertSame(expected: Http::STATUS_BAD_GATEWAY, actual: $response->getStatus());
		$this->assertSame(expected: 'provider_unreachable', actual: $response->getData()['error']);
	}//end testAFailedWeatherReadingAnswers502()

	/**
	 * A live data value that failed answers 404, as on the dashboard.
	 *
	 * @return void
	 */
	public function testAFailedLiveDataReadingAnswers404(): void {
		$this->kiosk->method('playlistDashboardIds')->willReturn(value: [7]);
		$this->reads->method('read')->willReturn(value: ['widget' => 'livetile', 'data' => ['error' => 'not_configured']]);

		$response = $this->controller()->kiosk(token: 'tok', placementId: 5);

		$this->assertSame(expected: Http::STATUS_NOT_FOUND, actual: $response->getStatus());
	}//end testAFailedLiveDataReadingAnswers404()
}//end class
