<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * PublicWidgetController: a live widget's data through a public share or a
 * kiosk playlist token (REQ-PSHR-011).
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
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\PublicWidgetController
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

	/** @var LoggerInterface&MockObject */
	private $logger;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->request->method('getRemoteAddress')->willReturn('10.0.0.1');
		$this->reads = $this->createMock(PublicWidgetReadService::class);
		$this->shares = $this->createMock(PublicShareService::class);
		$this->kiosk = $this->createMock(KioskService::class);
		$this->shareContext = $this->createMock(PublicShareContext::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function controller(): PublicWidgetController {
		return new PublicWidgetController(
			request: $this->request,
			reads: $this->reads,
			shares: $this->shares,
			kiosk: $this->kiosk,
			shareContext: $this->shareContext,
			logger: $this->logger,
		);
	}

	public function testAShareReadsThroughItsDashboardWithTheHeaderPassword(): void {
		$this->request->method('getHeader')->with('X-Share-Password')->willReturn('geheim');
		$this->shares->expects($this->once())->method('sharedDashboardUuid')
			->with('tok', '10.0.0.1', 'geheim')
			->willReturn('today');
		$this->shareContext->expects($this->once())->method('markBearer')->with('tok');
		$this->reads->expects($this->once())->method('read')
			->with(['today'], 4)
			->willReturn(['widget' => 'weather', 'data' => ['tempValue' => 12]]);

		$response = $this->controller()->share(token: 'tok', placementId: 4);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['widget' => 'weather', 'data' => ['tempValue' => 12]], $response->getData());
	}

	public function testAPasswordProtectedShareWithoutThePasswordAnswers401(): void {
		$this->request->method('getHeader')->willReturn('');
		$this->shares->method('sharedDashboardUuid')->with('tok', '10.0.0.1', null)
			->willThrowException(new SharePasswordRequiredException());
		$this->reads->expects($this->never())->method('read');

		$response = $this->controller()->share(token: 'tok', placementId: 4);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['passwordRequired' => true], $response->getData());
	}

	public function testAnUnknownShareTokenIsThrottled(): void {
		$this->request->method('getHeader')->willReturn('');
		$this->shares->method('sharedDashboardUuid')->willThrowException(new ShareNotFoundException());

		$response = $this->controller()->share(token: 'bogus', placementId: 4);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertTrue($response->isThrottled());
	}

	public function testAKioskReadsThroughItsPlaylistsDashboards(): void {
		$this->kiosk->method('playlistDashboardUuids')->with('tok')->willReturn(['today', 'menu']);
		$this->reads->expects($this->once())->method('read')
			->with(['today', 'menu'], 6)
			->willReturn(['widget' => 'announcements', 'data' => ['announcements' => []]]);

		$response = $this->controller()->kiosk(token: 'tok', placementId: 6);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('announcements', $response->getData()['widget']);
	}

	public function testAnUnknownPlaylistTokenIsThrottled(): void {
		$this->kiosk->method('playlistDashboardUuids')->willThrowException(new PlaylistNotFoundException());

		$response = $this->controller()->kiosk(token: 'bogus', placementId: 6);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertTrue($response->isThrottled());
	}

	public function testAPlacementNotOnTheDashboardAnswers404WithoutThrottling(): void {
		$this->kiosk->method('playlistDashboardUuids')->willReturn(['today']);
		$this->reads->method('read')->willThrowException(new DoesNotExistException('not shared'));

		$response = $this->controller()->kiosk(token: 'tok', placementId: 99);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertFalse($response->isThrottled());
	}

	public function testAFailedWeatherReadingAnswers502(): void {
		$this->kiosk->method('playlistDashboardUuids')->willReturn(['today']);
		$this->reads->method('read')->willReturn(['widget' => 'weather', 'data' => ['error' => 'provider_unreachable']]);

		$response = $this->controller()->kiosk(token: 'tok', placementId: 4);

		$this->assertSame(Http::STATUS_BAD_GATEWAY, $response->getStatus());
		$this->assertSame('provider_unreachable', $response->getData()['error']);
	}

	public function testAFailedLiveDataReadingAnswers404(): void {
		$this->kiosk->method('playlistDashboardUuids')->willReturn(['today']);
		$this->reads->method('read')->willReturn(['widget' => 'livetile', 'data' => ['error' => 'not_configured']]);

		$response = $this->controller()->kiosk(token: 'tok', placementId: 5);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testAnUnexpectedFailureIsLoggedAndAnswers500(): void {
		$this->kiosk->method('playlistDashboardUuids')->willReturn(['today']);
		$this->reads->method('read')->willThrowException(new RuntimeException('database down'));
		$this->logger->expects($this->once())->method('error');

		$response = $this->controller()->kiosk(token: 'tok', placementId: 5);

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}
}
