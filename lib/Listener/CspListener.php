<?php

/**
 * CspListener
 *
 * Contributes every admin-allow-listed `iframe` host to LaunchPad's own
 * `frame-src` Content-Security-Policy directive so Nextcloud's instance CSP
 * does not itself block an otherwise-permitted embed (REQ-IFRAME-003).
 *
 * This is deliberately the ONLY side of the CSP the app touches: the
 * `IContentSecurityPolicy` returned here is merged only into LaunchPad's own
 * responses (an `AddContentSecurityPolicyEvent` listener never loosens the
 * global instance policy for unrelated apps), and it never adds a wildcard —
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
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Adds allow-listed `iframe` embed hosts to the app-scoped `frame-src` CSP
 * directive. REQ-IFRAME-003.
 *
 * @implements IEventListener<AddContentSecurityPolicyEvent>
 */
class CspListener implements IEventListener
{
    /**
     * Constructor.
     *
     * @param IframeService   $iframeService The allow-list source of truth.
     * @param LoggerInterface $logger        PSR-3 logger for log-and-continue
     *                                       failure handling — a CSP
     *                                       contribution failure must never
     *                                       break page rendering.
     */
    public function __construct(
        private readonly IframeService $iframeService,
        private readonly LoggerInterface $logger,
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
     */
    public function handle(Event $event): void
    {
        if (($event instanceof AddContentSecurityPolicyEvent) === false) {
            return;
        }

        try {
            $hosts = $this->iframeService->getAllowedHosts();
            if ($hosts === []) {
                // FAIL-CLOSED — no configured host means nothing is added
                // to frame-src, matching the save/render-time allow-list
                // behaviour (REQ-IFRAME-002, REQ-IFRAME-003).
                return;
            }

            $policy = new EmptyContentSecurityPolicy();
            foreach ($hosts as $host) {
                // Exact host, HTTPS only, never a wildcard
                // (REQ-IFRAME-003 "the app MUST NOT add a wildcard (*) to
                // frame-src").
                $policy->addAllowedFrameDomain(domain: 'https://'.$host);
            }

            $event->addPolicy(csp: $policy);
        } catch (Throwable $exception) {
            $this->logger->warning(
                message: 'launchpad CspListener: failed to contribute iframe allow-list to frame-src',
                context: ['app' => 'launchpad', 'exception' => $exception->getMessage()]
            );
        }//end try
    }//end handle()
}//end class
