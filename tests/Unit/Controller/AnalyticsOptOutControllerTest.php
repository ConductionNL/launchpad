<?php

/**
 * Unit tests for AnalyticsOptOutController: the caller reads and writes their
 * own analytics opt-out, and nobody else's.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/analytics-personal-opt-out/specs/dashboard-view-analytics/spec.md#requirement-a-person-turns-view-counting-off-in-their-own-settings-req-anlt-ui-001
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AnalyticsOptOutController;
use OCA\LaunchPad\Service\AnalyticsService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the personal analytics opt-out route.
 *
 * @covers \OCA\LaunchPad\Controller\AnalyticsOptOutController
 */
class AnalyticsOptOutControllerTest extends TestCase {

	/**
	 * Session mock.
	 *
	 * @var IUserSession&MockObject
	 */
	private IUserSession&MockObject $session;

	/**
	 * Analytics service mock.
	 *
	 * @var AnalyticsService&MockObject
	 */
	private AnalyticsService&MockObject $analytics;

	/**
	 * The controller under test.
	 *
	 * @var AnalyticsOptOutController
	 */
	private AnalyticsOptOutController $controller;

	/**
	 * Build the controller over mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->session = $this->createMock(IUserSession::class);
		$this->analytics = $this->createMock(AnalyticsService::class);
		$this->controller = new AnalyticsOptOutController($this->createMock(IRequest::class), $this->session, $this->analytics);

	}//end setUp()

	/**
	 * Sign eva in.
	 *
	 * @return void
	 */
	private function signedIn(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('eva');
		$this->session->method('getUser')->willReturn($user);

	}//end signedIn()

	/**
	 * Signed out, nothing is read or written.
	 *
	 * @return void
	 */
	public function testSignedOutIsRefused(): void {
		$this->session->method('getUser')->willReturn(null);
		$this->analytics->expects($this->never())->method('setUserOptedOut');
		$this->analytics->expects($this->never())->method('isUserOptedOut');

		$this->assertSame(401, $this->controller->show()->getStatus());
		$this->assertSame(401, $this->controller->update(optedOut: true)->getStatus());

	}//end testSignedOutIsRefused()

	/**
	 * The caller reads their own value and whether analytics is on for everyone.
	 *
	 * @return void
	 */
	public function testTheCallerReadsTheirOwnValue(): void {
		$this->signedIn();
		$this->analytics->method('isGloballyEnabled')->willReturn(false);
		$this->analytics->expects($this->once())->method('isUserOptedOut')->with('eva')->willReturn(true);

		$response = $this->controller->show();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['enabled' => false, 'optedOut' => true], $response->getData());

	}//end testTheCallerReadsTheirOwnValue()

	/**
	 * The caller turns counting off and on again, for themselves, naming themselves or nobody.
	 *
	 * @return void
	 */
	public function testTheCallerWritesTheirOwnValue(): void {
		$this->signedIn();
		$this->analytics->method('isGloballyEnabled')->willReturn(true);
		$calls = [];
		$this->analytics->expects($this->exactly(2))->method('setUserOptedOut')->willReturnCallback(
			static function (string $userId, bool $optedOut) use (&$calls): bool {
				$calls[] = [$userId, $optedOut];
				return $optedOut;
			}
		);

		$off = $this->controller->update(optedOut: true);
		$on = $this->controller->update(optedOut: false, userId: 'eva');

		$this->assertSame(['enabled' => true, 'optedOut' => true], $off->getData());
		$this->assertSame(['enabled' => true, 'optedOut' => false], $on->getData());
		$this->assertSame([['eva', true], ['eva', false]], $calls);

	}//end testTheCallerWritesTheirOwnValue()

	/**
	 * A request naming another user is refused and writes nothing.
	 *
	 * @return void
	 */
	public function testAnotherUsersValueIsRefused(): void {
		$this->signedIn();
		$this->analytics->expects($this->never())->method('setUserOptedOut');

		foreach (['admin', '', 'Eva'] as $other) {
			$response = $this->controller->update(optedOut: true, userId: $other);
			$this->assertSame(403, $response->getStatus(), $other);
		}

	}//end testAnotherUsersValueIsRefused()
}//end class
