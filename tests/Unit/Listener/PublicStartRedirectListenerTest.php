<?php

/**
 * PublicStartRedirectListenerTest
 *
 * Covers design D2 of sharing-public-start-page: the redirect script is added
 * to the login page only for a signed-out GET without `redirect_url`, `direct`
 * or `clear`, and only while the start page can be shown; every other request
 * keeps the login form, and a failing check never breaks the login page.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-signed-out-visitors-land-on-the-start-page-req-psp-002
 */

declare(strict_types=1);

namespace Unit\Listener;

use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Listener\PublicStartRedirectListener;
use OCA\LaunchPad\Service\PublicStartPage;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * When the login page sends a visitor to the start page, and when it does not.
 */
#[CoversClass(PublicStartRedirectListener::class)]
#[UsesClass(PublicShare::class)]
class PublicStartRedirectListenerTest extends TestCase {

	/**
	 * How many times the redirect script was added.
	 *
	 * @var integer
	 */
	private int $added = 0;

	/**
	 * The listener over a request, a session and a start page, recording the script instead of adding it.
	 *
	 * @param string               $method   The request method.
	 * @param array<string, mixed> $params   The request parameters.
	 * @param bool                 $loggedIn Whether somebody is signed in.
	 * @param PublicShare|null     $active   The start page's link, or null when it is off.
	 * @param LoggerInterface|null $logger   The logger.
	 *
	 * @return PublicStartRedirectListener
	 */
	private function listener(
		string $method = 'GET',
		array $params = [],
		bool $loggedIn = false,
		?PublicShare $active = null,
		?LoggerInterface $logger = null,
	): PublicStartRedirectListener {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getMethod')->willReturn($method);
		$request->method('getParam')->willReturnCallback(static fn (string $key): mixed => $params[$key] ?? null);

		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('isLoggedIn')->willReturn($loggedIn);

		$startPage = $this->createMock(originalClassName: PublicStartPage::class);
		$startPage->method('active')->willReturn($active);

		$added = &$this->added;
		return new class($request, $session, $startPage, $logger ?? $this->createMock(originalClassName: LoggerInterface::class), $added) extends PublicStartRedirectListener {
			/**
			 * The caller's counter.
			 *
			 * @var integer
			 */
			private int $counter;

			/**
			 * Constructor.
			 *
			 * @param IRequest        $request     The request.
			 * @param IUserSession    $userSession The session.
			 * @param PublicStartPage $startPage   The start page.
			 * @param LoggerInterface $logger      The logger.
			 * @param integer         $counter     The caller's counter, by reference.
			 */
			public function __construct(IRequest $request, IUserSession $userSession, PublicStartPage $startPage, LoggerInterface $logger, int &$counter) {
				parent::__construct(request: $request, userSession: $userSession, startPage: $startPage, logger: $logger);
				$this->counter = &$counter;
			}

			/**
			 * Count instead of adding the script.
			 *
			 * @return void
			 */
			protected function addRedirectScript(): void {
				$this->counter++;
			}
		};
	}//end listener()

	/**
	 * The start page's link.
	 *
	 * @return PublicShare
	 */
	private static function link(): PublicShare {
		$share = new PublicShare();
		$share->setToken('abc123');
		return $share;
	}//end link()

	/**
	 * The login event.
	 *
	 * @return BeforeLoginTemplateRenderedEvent
	 */
	private function loginEvent(): BeforeLoginTemplateRenderedEvent {
		return $this->getMockBuilder(className: BeforeLoginTemplateRenderedEvent::class)->disableOriginalConstructor()->getMock();
	}//end loginEvent()

	/**
	 * A signed-out visitor on the plain login page, with the start page on, is sent there.
	 *
	 * @return void
	 */
	public function testThePlainLoginPageSendsAVisitorToTheStartPage(): void {
		$listener = $this->listener(active: self::link());

		$listener->handle(event: $this->loginEvent());

		self::assertSame(1, $this->added);
		self::assertTrue($listener->sendsToStartPage());
	}//end testThePlainLoginPageSendsAVisitorToTheStartPage()

	/**
	 * The login form stays for a deep link, for `direct`, for `clear`, even when empty.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function formParameters(): array {
		return [
			'deep link'        => [['redirect_url' => '/apps/files/']],
			'empty deep link'  => [['redirect_url' => '']],
			'sign in button'   => [['direct' => '1']],
			'clear'            => [['clear' => '1']],
		];
	}//end formParameters()

	/**
	 * A login page with a form parameter keeps the login form.
	 *
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @return void
	 */
	#[DataProvider('formParameters')]
	public function testALoginPageWithAFormParameterKeepsTheForm(array $params): void {
		$this->listener(params: $params, active: self::link())->handle(event: $this->loginEvent());

		self::assertSame(0, $this->added);
	}//end testALoginPageWithAFormParameterKeepsTheForm()

	/**
	 * A POST (the sign-in itself) is never redirected.
	 *
	 * @return void
	 */
	public function testAPostKeepsTheForm(): void {
		$this->listener(method: 'POST', active: self::link())->handle(event: $this->loginEvent());

		self::assertSame(0, $this->added);
	}//end testAPostKeepsTheForm()

	/**
	 * Somebody already signed in is not sent to the start page.
	 *
	 * @return void
	 */
	public function testASignedInUserKeepsTheForm(): void {
		$this->listener(loggedIn: true, active: self::link())->handle(event: $this->loginEvent());

		self::assertSame(0, $this->added);
	}//end testASignedInUserKeepsTheForm()

	/**
	 * With the start page off, or its link gone bad, the login form stays.
	 *
	 * @return void
	 */
	public function testAStartPageThatIsOffKeepsTheForm(): void {
		$listener = $this->listener(active: null);

		$listener->handle(event: $this->loginEvent());

		self::assertSame(0, $this->added);
		self::assertFalse($listener->sendsToStartPage());
	}//end testAStartPageThatIsOffKeepsTheForm()

	/**
	 * Another event is ignored.
	 *
	 * @return void
	 */
	public function testAnotherEventIsIgnored(): void {
		$this->listener(active: self::link())->handle(event: new Event());

		self::assertSame(0, $this->added);
	}//end testAnotherEventIsIgnored()

	/**
	 * A failing check is logged and the login form shows.
	 *
	 * @return void
	 */
	public function testAFailingCheckIsLoggedAndTheFormShows(): void {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getMethod')->willReturn('GET');
		$request->method('getParam')->willReturn(null);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$startPage = $this->createMock(originalClassName: PublicStartPage::class);
		$startPage->method('active')->willThrowException(new RuntimeException('database gone'));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::once())->method('warning')->with(
			self::stringContains('start page check failed'),
			self::callback(static fn (array $context): bool => $context['exception'] === 'database gone')
		);

		$listener = new PublicStartRedirectListener(request: $request, userSession: $session, startPage: $startPage, logger: $logger);
		$listener->handle(event: $this->loginEvent());

		self::assertSame(0, $this->added);
	}//end testAFailingCheckIsLoggedAndTheFormShows()
}//end class
