<?php

/**
 * Tests for WidgetProposalController: signed-in users only, a need is
 * required, no assistant answers 503, and the service's proposal is passed on.
 *
 * @category  Tests
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-045
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Controller;

use OCA\LaunchPad\Controller\SessionLog;
use OCA\LaunchPad\Controller\WidgetProposalController;
use OCA\LaunchPad\Service\WidgetProposalService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * @spec openspec/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-045
 */
#[Small]
class WidgetProposalControllerTest extends TestCase {

	/**
	 * The request.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest&MockObject $request;

	/**
	 * The proposal service.
	 *
	 * @var WidgetProposalService&MockObject
	 */
	private WidgetProposalService&MockObject $proposals;

	/**
	 * The user session.
	 *
	 * @var IUserSession&MockObject
	 */
	private IUserSession&MockObject $userSession;

	/**
	 * Set up the mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->request     = $this->createMock(originalClassName: IRequest::class);
		$this->proposals   = $this->createMock(originalClassName: WidgetProposalService::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
	}//end setUp()

	/**
	 * The controller, signed in as pieter or anonymous.
	 *
	 * @param boolean $signedIn Whether a user is signed in.
	 *
	 * @return WidgetProposalController
	 */
	private function controller(bool $signedIn=true): WidgetProposalController {
		$user = null;
		if ($signedIn === true) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn('pieter');
		}

		$this->userSession->method('getUser')->willReturn($user);

		$session = new SessionLog(userSession: $this->userSession, logger: $this->createMock(originalClassName: LoggerInterface::class));

		return new WidgetProposalController(request: $this->request, proposals: $this->proposals, session: $session);
	}//end controller()

	/**
	 * Anonymous callers get 401 on both endpoints and nothing is asked.
	 *
	 * @return void
	 */
	public function testAnonymousIsRefused(): void {
		$this->proposals->expects($this->never())->method('isAvailable');
		$this->proposals->expects($this->never())->method('propose');
		$controller = $this->controller(signedIn: false);
		$this->assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $controller->assistant()->getStatus());
		$this->assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $controller->propose()->getStatus());
	}//end testAnonymousIsRefused()

	/**
	 * The availability endpoint answers the service's verdict for the user.
	 *
	 * @return void
	 */
	public function testAvailabilityIsTheServicesVerdict(): void {
		$this->proposals->expects($this->once())->method('isAvailable')->with('pieter')->willReturn(true);
		$response = $this->controller()->assistant();
		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: ['available' => true], actual: $response->getData());
	}//end testAvailabilityIsTheServicesVerdict()

	/**
	 * A missing or empty need is a 400.
	 *
	 * @return void
	 */
	public function testANeedIsRequired(): void {
		$this->request->method('getParam')->willReturnMap([['need', null, '  '], ['cards', null, []]]);
		$this->proposals->expects($this->never())->method('propose');
		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $this->controller()->propose()->getStatus());
	}//end testANeedIsRequired()

	/**
	 * Without an assistant a proposal is a 503 and no task runs.
	 *
	 * @return void
	 */
	public function testNoAssistantIsUnavailable(): void {
		$this->request->method('getParam')->willReturnMap([['need', null, 'notes'], ['cards', null, []]]);
		$this->proposals->method('isAvailable')->willReturn(false);
		$this->proposals->expects($this->never())->method('propose');
		$this->assertSame(expected: Http::STATUS_SERVICE_UNAVAILABLE, actual: $this->controller()->propose()->getStatus());
	}//end testNoAssistantIsUnavailable()

	/**
	 * The need and the cards go to the service, its proposal comes back; cards that are not a list become none.
	 *
	 * @return void
	 */
	public function testTheProposalIsPassedOn(): void {
		$cards = [['key' => 'widget:notes', 'name' => 'Notes', 'description' => '']];
		$this->request->method('getParam')->willReturnMap([['need', null, 'my notes'], ['cards', null, $cards]]);
		$this->proposals->method('isAvailable')->willReturn(true);
		$proposal = ['key' => 'widget:notes', 'name' => 'Notes', 'reason' => 'Your notes.'];
		$this->proposals->expects($this->once())->method('propose')->with('pieter', 'my notes', $cards)->willReturn($proposal);

		$response = $this->controller()->propose();
		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		$this->assertSame(expected: ['proposal' => $proposal], actual: $response->getData());
	}//end testTheProposalIsPassedOn()

	/**
	 * Cards that are not an array are sent as none.
	 *
	 * @return void
	 */
	public function testCardsThatAreNotAListAreNone(): void {
		$this->request->method('getParam')->willReturnMap([['need', null, 'my notes'], ['cards', null, 'oops']]);
		$this->proposals->method('isAvailable')->willReturn(true);
		$this->proposals->expects($this->once())->method('propose')->with('pieter', 'my notes', [])->willReturn(null);
		$this->assertSame(expected: ['proposal' => null], actual: $this->controller()->propose()->getData());
	}//end testCardsThatAreNotAListAreNone()

	/**
	 * A proposal runs an assistant task, so it is rate limited per user.
	 *
	 * @return void
	 */
	public function testAProposalIsRateLimited(): void {
		$attributes = (new ReflectionMethod(objectOrMethod: WidgetProposalController::class, method: 'propose'))->getAttributes(name: UserRateLimit::class);
		$this->assertCount(expectedCount: 1, haystack: $attributes);
	}//end testAProposalIsRateLimited()
}//end class
