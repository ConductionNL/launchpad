<?php

/**
 * CspListenerTest
 *
 * Covers REQ-IFRAME-003: allow-listed hosts are added to the app's
 * `frame-src` CSP directive, non-allow-listed hosts (and wildcards) are
 * never added, an empty allow-list contributes nothing, and foreign event
 * types / a service failure are both handled without throwing.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Listener;

use OCA\LaunchPad\Listener\CspListener;
use OCA\LaunchPad\Service\IframeService;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[Small]
class CspListenerTest extends TestCase
{

    private IframeService $iframeService;

    private LoggerInterface $logger;

    private CspListener $listener;

    protected function setUp(): void
    {
        $this->iframeService = $this->createMock(originalClassName: IframeService::class);
        $this->logger         = $this->createMock(originalClassName: LoggerInterface::class);
        $this->listener       = new CspListener(
            iframeService: $this->iframeService,
            logger: $this->logger,
        );
    }//end setUp()

    /**
     * Builds a mock `AddContentSecurityPolicyEvent` (constructor disabled —
     * PHPUnit mocks for a concrete class extend it, so the
     * `instanceof AddContentSecurityPolicyEvent` guard in
     * {@see CspListener::handle()} still passes) whose `addPolicy()` calls
     * are captured (by reference, into the caller-owned `$captured`
     * array) instead of routed through the real (internal, non-OCP)
     * `ContentSecurityPolicyManager`.
     *
     * @param array<int,EmptyContentSecurityPolicy> $captured Caller-owned capture sink, passed by reference.
     *
     * @return AddContentSecurityPolicyEvent
     */
    private function makeEvent(array &$captured): AddContentSecurityPolicyEvent
    {
        $event = $this->getMockBuilder(AddContentSecurityPolicyEvent::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addPolicy'])
            ->getMock();

        $event->method('addPolicy')->willReturnCallback(
            static function (EmptyContentSecurityPolicy $csp) use (&$captured): void {
                $captured[] = $csp;
            }
        );

        return $event;
    }//end makeEvent()

    public function testAllowListedHostIsAddedToFrameSrc(): void
    {
        $this->iframeService->method('getAllowedHosts')->willReturn(['status.example.com']);
        $captured = [];
        $event    = $this->makeEvent(captured: $captured);

        $this->listener->handle(event: $event);

        $this->assertCount(1, $captured);
        $this->assertStringContainsString('frame-src https://status.example.com', $captured[0]->buildPolicy());
    }//end testAllowListedHostIsAddedToFrameSrc()

    public function testNonAllowListedHostsAreNeverAdded(): void
    {
        $this->iframeService->method('getAllowedHosts')->willReturn(['status.example.com']);
        $captured = [];
        $event    = $this->makeEvent(captured: $captured);

        $this->listener->handle(event: $event);

        $policy = $captured[0]->buildPolicy();
        $this->assertStringNotContainsString('evil.example.net', $policy);
    }//end testNonAllowListedHostsAreNeverAdded()

    public function testNeverAddsAWildcardToFrameSrc(): void
    {
        $this->iframeService->method('getAllowedHosts')->willReturn(['status.example.com', 'intranet.example.nl']);
        $captured = [];
        $event    = $this->makeEvent(captured: $captured);

        $this->listener->handle(event: $event);

        $frameSrcSegment = $this->extractFrameSrc(policy: $captured[0]->buildPolicy());
        $this->assertStringNotContainsString('*', $frameSrcSegment);
    }//end testNeverAddsAWildcardToFrameSrc()

    public function testEmptyAllowListContributesNoPolicy(): void
    {
        $this->iframeService->method('getAllowedHosts')->willReturn([]);
        $captured = [];
        $event    = $this->makeEvent(captured: $captured);

        $this->listener->handle(event: $event);

        $this->assertCount(0, $captured);
    }//end testEmptyAllowListContributesNoPolicy()

    public function testMultipleAllowListedHostsAreAllAdded(): void
    {
        $this->iframeService->method('getAllowedHosts')->willReturn(['status.example.com', 'intranet.example.nl']);
        $captured = [];
        $event    = $this->makeEvent(captured: $captured);

        $this->listener->handle(event: $event);

        $policy = $captured[0]->buildPolicy();
        $this->assertStringContainsString('https://status.example.com', $policy);
        $this->assertStringContainsString('https://intranet.example.nl', $policy);
    }//end testMultipleAllowListedHostsAreAllAdded()

    public function testForeignEventTypesAreIgnored(): void
    {
        $this->iframeService->expects($this->never())->method('getAllowedHosts');

        $this->listener->handle(event: $this->createMock(originalClassName: Event::class));

        $this->addToAssertionCount(1);
    }//end testForeignEventTypesAreIgnored()

    public function testServiceFailureIsLoggedAndNeverThrows(): void
    {
        $this->iframeService->method('getAllowedHosts')->willThrowException(new RuntimeException('boom'));
        $this->logger->expects($this->once())->method('warning');
        $captured = [];
        $event    = $this->makeEvent(captured: $captured);

        $this->listener->handle(event: $event);

        $this->addToAssertionCount(1);
    }//end testServiceFailureIsLoggedAndNeverThrows()

    private function extractFrameSrc(string $policy): string
    {
        if (preg_match(pattern: '/frame-src [^;]*/', subject: $policy, matches: $matches) === 1) {
            return $matches[0];
        }

        return '';
    }//end extractFrameSrc()
}//end class
