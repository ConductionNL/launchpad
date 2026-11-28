<?php

/**
 * CspListener
 *
 * Contributes every admin-allow-listed `iframe` host to LaunchPad's own
 * `frame-src` Content-Security-Policy directive so Nextcloud's instance CSP
 * does not itself block an otherwise-permitted embed (REQ-IFRAME-003), and
 * every allow-listed external image host to `img-src` (REQ-IMG-006).
 *
 * This is deliberately the ONLY side of the CSP the app touches: the
 * event fires for every Nextcloud response, so the policy is only added to
 * requests under `/apps/launchpad` (never loosening the instance policy for
 * unrelated apps), and it never adds a wildcard —
 * only the exact hosts read from {@see IframeService::getAllowedHosts()}. A
 * target site's OWN `X-Frame-Options: DENY` / `frame-ancestors 'none'` is
 * outside this policy entirely and cannot be overridden by it (that side is
 * handled client-side by the widget's graceful-degradation fallback, see
 * `src/components/Widgets/Renderers/IframeWidget.vue`).
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\Service\IframeService;
use OCA\LaunchPad\Service\ImageHostService;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Adds allow-listed `iframe` embed hosts to the app-scoped `frame-src` CSP
 * directive (REQ-IFRAME-003) and allow-listed image hosts to `img-src`
 * (REQ-IMG-006).
 *
 * @implements IEventListener<AddContentSecurityPolicyEvent>
 */
class CspListener implements IEventListener {
	/**
	 * Constructor.
	 *
	 * @param IframeService $iframeService The allow-list source of truth.
	 * @param ImageHostService $imageHosts The image host allow-list.
	 * @param LoggerInterface $logger PSR-3 logger for log-and-continue
	 *                                failure handling — a CSP
	 *                                contribution failure must never
	 *                                break page rendering.
	 * @param IRequest $request The request whose response the policy is for.
	 */
	public function __construct(
		private readonly IframeService $iframeService,
		private readonly ImageHostService $imageHosts,
		private readonly LoggerInterface $logger,
		private readonly IRequest $request,
	) {
	}//end __construct()

	/**
	 * Handle the AddContentSecurityPolicyEvent.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/iframe-embed-widget/spec.md
	 * @spec openspec/specs/image-widget/spec.md#requirement-req-img-006-external-image-hosts-are-allow-listed-by-an-administrator
	 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
	 */
	public function handle(Event $event): void {
		if (($event instanceof AddContentSecurityPolicyEvent) === false || $this->isLaunchPadRequest() === false) {
			return;
		}

		try {
			$frameHosts = $this->iframeService->getAllowedHosts();
			$imageHosts = $this->imageHosts->getPermittedHosts();
			if ($frameHosts === [] && $imageHosts === []) {
				// FAIL-CLOSED — no configured host means nothing is added,
				// matching the save/render-time allow-list behaviour
				// (REQ-IFRAME-002, REQ-IFRAME-003, REQ-IMG-006).
				return;
			}

			// Exact hosts, HTTPS only, never a wildcard (REQ-IFRAME-003
			// "the app MUST NOT add a wildcard (*) to frame-src").
			$policy = new EmptyContentSecurityPolicy();
			foreach ($frameHosts as $host) {
				$policy->addAllowedFrameDomain(domain: 'https://' . $host);
			}

			foreach ($imageHosts as $host) {
				$policy->addAllowedImageDomain(domain: 'https://' . $host);
			}

			$event->addPolicy(csp: $policy);
		} catch (Throwable $exception) {
			$this->logger->warning(
				message: 'launchpad CspListener: failed to contribute the allow-lists to the CSP',
				context: ['app' => 'launchpad', 'exception' => $exception->getMessage()]
			);
		}//end try
	}//end handle()

	/**
	 * Whether the current request is for a LaunchPad page or endpoint.
	 *
	 * @return bool True under `/apps/launchpad`.
	 *
	 * @spec openspec/specs/iframe-embed-widget/spec.md
	 */
	private function isLaunchPadRequest(): bool {
		try {
			$path = $this->request->getPathInfo();
		} catch (Throwable $exception) {
			return false;
		}

		return is_string(value: $path) === true
			&& ($path === '/apps/launchpad' || str_starts_with(haystack: $path, needle: '/apps/launchpad/') === true);
	}//end isLaunchPadRequest()
}//end class
