<?php

/**
 * IframeServiceTest
 *
 * Covers REQ-IFRAME-002 (save-time allow-list validation, fail-closed on
 * an empty/missing list, host-removed-after-configuration) and
 * REQ-IFRAME-004 (forbidden sandbox token rejected, accessible title
 * required, sandbox sanitisation strips forbidden/unknown tokens).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\IframeService;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class IframeServiceTest extends TestCase
{

    private IframeService $service;

    private IAppConfig $appConfig;

    protected function setUp(): void
    {
        $this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
        $this->service    = new IframeService(appConfig: $this->appConfig);
    }//end setUp()

    private function allowHosts(array $hosts): void
    {
        $this->appConfig->method('getValueString')->willReturn(json_encode($hosts));
    }//end allowHosts()

    private function validConfig(array $overrides = []): array
    {
        return array_merge(
            [
                'url'     => 'https://status.example.com/board',
                'title'   => 'Status',
                'height'  => 400,
                'aspect'  => 'none',
                'sandbox' => ['allow-scripts', 'allow-same-origin'],
            ],
            $overrides
        );
    }//end validConfig()

    // -------------------------------------------------------------
    // REQ-IFRAME-002: allow-list, fail-closed.
    // -------------------------------------------------------------

    public function testEmptyAllowListDeniesEveryHost(): void
    {
        $this->appConfig->method('getValueString')->willReturn('');

        $errors = $this->service->validateConfig(config: $this->validConfig());

        $this->assertContains('host_not_allowed', $errors);
    }//end testEmptyAllowListDeniesEveryHost()

    public function testMissingAllowListKeyDeniesEveryHost(): void
    {
        // Default IAppConfig mock returns '' for an unset key — matches
        // the FAIL-CLOSED contract with no explicit stub.
        $errors = $this->service->validateConfig(config: $this->validConfig());

        $this->assertContains('host_not_allowed', $errors);
    }//end testMissingAllowListKeyDeniesEveryHost()

    public function testAllowedHostPassesValidation(): void
    {
        $this->allowHosts(hosts: ['status.example.com']);

        $errors = $this->service->validateConfig(config: $this->validConfig());

        $this->assertSame([], $errors);
    }//end testAllowedHostPassesValidation()

    public function testDisallowedHostIsRejected(): void
    {
        $this->allowHosts(hosts: ['status.example.com']);

        $errors = $this->service->validateConfig(config: $this->validConfig(overrides: [
            'url' => 'https://evil.example.net/',
        ]));

        $this->assertContains('host_not_allowed', $errors);
    }//end testDisallowedHostIsRejected()

    public function testHostMatchIsCaseInsensitive(): void
    {
        $this->allowHosts(hosts: ['Status.Example.com']);

        $errors = $this->service->validateConfig(config: $this->validConfig());

        $this->assertSame([], $errors);
    }//end testHostMatchIsCaseInsensitive()

    public function testIsHostAllowedReflectsCurrentAllowList(): void
    {
        $this->allowHosts(hosts: ['status.example.com']);

        $this->assertTrue($this->service->isHostAllowed(url: 'https://status.example.com/board'));
        $this->assertFalse($this->service->isHostAllowed(url: 'https://evil.example.net/'));
    }//end testIsHostAllowedReflectsCurrentAllowList()

    public function testHostRemovedFromAllowListIsNoLongerAllowed(): void
    {
        // Simulates "Host removed after configuration is refused at
        // render" — the same getAllowedHosts() call the CSP listener and
        // render-time re-check both use.
        $this->appConfig->method('getValueString')->willReturn('');

        $this->assertFalse($this->service->isHostAllowed(url: 'https://status.example.com/board'));
    }//end testHostRemovedFromAllowListIsNoLongerAllowed()

    public function testGetAllowedHostsReturnsEmptyArrayOnMalformedJson(): void
    {
        $this->appConfig->method('getValueString')->willReturn('not json');

        $this->assertSame([], $this->service->getAllowedHosts());
    }//end testGetAllowedHostsReturnsEmptyArrayOnMalformedJson()

    public function testGetAllowedHostsDeduplicates(): void
    {
        $this->allowHosts(hosts: ['status.example.com', 'status.example.com']);

        $this->assertSame(['status.example.com'], $this->service->getAllowedHosts());
    }//end testGetAllowedHostsDeduplicates()

    // -------------------------------------------------------------
    // REQ-IFRAME-002 / REQ-IFRAME-004: general field validation.
    // -------------------------------------------------------------

    public function testMissingUrlIsRejected(): void
    {
        $this->allowHosts(hosts: ['status.example.com']);

        $errors = $this->service->validateConfig(config: $this->validConfig(overrides: ['url' => '']));

        $this->assertContains('url_required', $errors);
    }//end testMissingUrlIsRejected()

    public function testInvalidSchemeIsRejected(): void
    {
        $this->allowHosts(hosts: ['status.example.com']);

        $errors = $this->service->validateConfig(config: $this->validConfig(overrides: [
            'url' => 'ftp://status.example.com/board',
        ]));

        $this->assertContains('invalid_url', $errors);
    }//end testInvalidSchemeIsRejected()

    public function testMissingTitleIsRejected(): void
    {
        $this->allowHosts(hosts: ['status.example.com']);

        $errors = $this->service->validateConfig(config: $this->validConfig(overrides: ['title' => '']));

        $this->assertContains('title_required', $errors);
    }//end testMissingTitleIsRejected()

    public function testForbiddenSandboxTokenIsRejected(): void
    {
        $this->allowHosts(hosts: ['status.example.com']);

        $errors = $this->service->validateConfig(config: $this->validConfig(overrides: [
            'sandbox' => ['allow-scripts', 'allow-top-navigation'],
        ]));

        $this->assertContains('forbidden_sandbox_token', $errors);
    }//end testForbiddenSandboxTokenIsRejected()

    public function testForbiddenSandboxTokenVariantIsRejected(): void
    {
        $this->allowHosts(hosts: ['status.example.com']);

        $errors = $this->service->validateConfig(config: $this->validConfig(overrides: [
            'sandbox' => ['allow-top-navigation-by-user-activation'],
        ]));

        $this->assertContains('forbidden_sandbox_token', $errors);
    }//end testForbiddenSandboxTokenVariantIsRejected()

    // -------------------------------------------------------------
    // Sandbox token sanitisation.
    // -------------------------------------------------------------

    public function testSanitiseSandboxTokensStripsForbiddenAndUnknownTokens(): void
    {
        $clean = $this->service->sanitiseSandboxTokens(tokens: [
            'allow-scripts',
            'allow-top-navigation',
            'not-a-real-token',
            'allow-forms',
        ]);

        $this->assertSame(['allow-scripts', 'allow-forms'], $clean);
    }//end testSanitiseSandboxTokensStripsForbiddenAndUnknownTokens()

    public function testSanitiseSandboxTokensReturnsEmptyArrayForNonArrayInput(): void
    {
        $this->assertSame([], $this->service->sanitiseSandboxTokens(tokens: null));
        $this->assertSame([], $this->service->sanitiseSandboxTokens(tokens: 'allow-scripts'));
    }//end testSanitiseSandboxTokensReturnsEmptyArrayForNonArrayInput()
}//end class
