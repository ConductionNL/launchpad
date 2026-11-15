<?php

/**
 * PublicStartRedirectListener
 *
 * Sends a signed-out visitor who opens the plain login page to the public
 * start page (sharing-public-start-page, design D2). It adds one small script,
 * `launchpad-start-redirect`, to the login page, and only when every condition
 * holds: the start page is on and its link can be shown, the request is a GET,
 * it carries no `redirect_url`, no `direct` and no `clear` parameter, and
 * nobody is signed in. A deep link keeps its `redirect_url` and therefore its
 * login form; `direct=1` is how the start page's "Sign in" button and single
 * sign-on apps reach the form without being sent back. Without JavaScript the
 * login form stays usable.
 *
 * @category  Listener
 * @package   OCA\LaunchPad\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-signed-out-visitors-land-on-the-start-page-req-psp-002
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\PublicStartPage;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Adds the start-page redirect to the plain login page.
 *
 * @implements IEventListener<Event>
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-signed-out-visitors-land-on-the-start-page-req-psp-002
 */
class PublicStartRedirectListener implements IEventListener {

	/**
	 * The script file, without the `.js` extension.
	 *
	 * @var string
	 */
	public const SCRIPT = 'launchpad-start-redirect';

	/**
	 * Login-page parameters that mean "show the login form", whatever they hold.
	 *
	 * @var list<string>
	 */
	private const FORM_PARAMETERS = ['redirect_url', 'direct', 'clear'];

	/**
	 * Constructor.
	 *
	 * @param IRequest        $request     The login page request.
	 * @param IUserSession    $userSession Whether somebody is signed in.
	 * @param PublicStartPage $startPage   Whether the start page is on and can be shown.
	 * @param LoggerInterface $logger      PSR-3 logger; a failure never breaks the login page.
	 */
	public function __construct(
		private readonly IRequest $request,
		private readonly IUserSession $userSession,
		private readonly PublicStartPage $startPage,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle the BeforeLoginTemplateRenderedEvent.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-signed-out-visitors-land-on-the-start-page-req-psp-002
	 */
	public function handle(Event $event): void {
		if (($event instanceof BeforeLoginTemplateRenderedEvent) === false) {
			return;
		}

		try {
			if ($this->sendsToStartPage() === true) {
				$this->addRedirectScript();
			}
		} catch (Throwable $exception) {
			$this->logger->warning(
				message: 'launchpad PublicStartRedirectListener: the start page check failed; the login form shows',
				context: ['app' => Application::APP_ID, 'exception' => $exception->getMessage()]
			);
		}
	}//end handle()

	/**
	 * Whether this login page request goes to the start page (design D2).
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-signed-out-visitors-land-on-the-start-page-req-psp-002
	 */
	public function sendsToStartPage(): bool {
		if (strtoupper((string)$this->request->getMethod()) !== 'GET') {
			return false;
		}

		foreach (self::FORM_PARAMETERS as $parameter) {
			if ($this->request->getParam($parameter) !== null) {
				return false;
			}
		}

		if ($this->userSession->isLoggedIn() === true) {
			return false;
		}

		return $this->startPage->active() !== null;
	}//end sendsToStartPage()

	/**
	 * Add the redirect script to the login page.
	 *
	 * @return void
	 */
	protected function addRedirectScript(): void {
		Util::addScript(application: Application::APP_ID, file: self::SCRIPT);
	}//end addRedirectScript()
}//end class
