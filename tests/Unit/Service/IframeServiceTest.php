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
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[Small]
class IframeServiceTest extends TestCase {

	private IframeService $service;

	private IAppConfig $appConfig;

	private IClientService $clientService;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->clientService = $this->createMock(originalClassName: IClientService::class);
		$this->service = new IframeService(
			appConfig: $this->appConfig,
			clientService: $this->clientService,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);
	}//end setUp()

	private function allowHosts(array $hosts): void {
		$this->appConfig->method('getValueString')->willReturn(json_encode($hosts));
	}//end allowHosts()

	/**
	 * Stub the HTTP client so a framable-check GET returns the given headers.
	 *
	 * @param array<string,string> $headers Response headers by name.
	 *
	 * @return void
	 */
	private function stubResponseHeaders(array $headers): void {
		$response = $this->createMock(originalClassName: IResponse::class);
		$response->method('getHeader')->willReturnCallback(
			static function (string $name) use ($headers): string {
				foreach ($headers as $k => $v) {
					if (strcasecmp($k, $name) === 0) {
						return $v;
					}
				}

				return '';
			}
		);
		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturn($response);
		$this->clientService->method('newClient')->willReturn($client);
	}//end stubResponseHeaders()

	public function testFramableTrueWhenNoFramingHeaders(): void {
		$this->allowHosts(['example.com']);
		$this->stubResponseHeaders([]);

		$result = $this->service->checkFramable(url: 'https://example.com/page');

		$this->assertTrue($result['framable']);
		$this->assertSame('ok', $result['reason']);
	}//end testFramableTrueWhenNoFramingHeaders()

	public function testFramableFalseOnXFrameOptionsDeny(): void {
		$this->allowHosts(['github.com']);
		$this->stubResponseHeaders(['X-Frame-Options' => 'deny']);

		$result = $this->service->checkFramable(url: 'https://github.com/');

		$this->assertFalse($result['framable']);
		$this->assertSame('x_frame_options', $result['reason']);
	}//end testFramableFalseOnXFrameOptionsDeny()

	public function testFramableFalseOnXFrameOptionsSameorigin(): void {
		$this->allowHosts(['example.com']);
		$this->stubResponseHeaders(['X-Frame-Options' => 'SAMEORIGIN']);

		$result = $this->service->checkFramable(url: 'https://example.com/');

		$this->assertFalse($result['framable']);
		$this->assertSame('x_frame_options', $result['reason']);
	}//end testFramableFalseOnXFrameOptionsSameorigin()

	public function testFramableFalseOnCspFrameAncestorsNone(): void {
		$this->allowHosts(['github.com']);
		$this->stubResponseHeaders(
			['Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; img-src 'self'"]
		);

		$result = $this->service->checkFramable(url: 'https://github.com/');

		$this->assertFalse($result['framable']);
		$this->assertSame('frame_ancestors', $result['reason']);
	}//end testFramableFalseOnCspFrameAncestorsNone()

	public function testFramableFalseWhenHostNotAllowed_NoFetch(): void {
		$this->allowHosts(['example.com']);
		// A non-allow-listed host must be refused WITHOUT any HTTP call.
		$this->clientService->expects($this->never())->method('newClient');

		$result = $this->service->checkFramable(url: 'https://evil.test/x');

		$this->assertFalse($result['framable']);
		$this->assertSame('host_not_allowed', $result['reason']);
	}//end testFramableFalseWhenHostNotAllowed_NoFetch()

	public function testFramableFalseWhenTargetUnreachable(): void {
		$this->allowHosts(['example.com']);
		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willThrowException(new RuntimeException('timeout'));
		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->checkFramable(url: 'https://example.com/');

		// Fail-closed: a target we cannot verify is treated as un-framable,
		// not gambled on a blank frame.
		$this->assertFalse($result['framable']);
		$this->assertSame('unreachable', $result['reason']);
	}//end testFramableFalseWhenTargetUnreachable()

	private function validConfig(array $overrides = []): array {
		return array_merge(
			[
				'url' => 'https://status.example.com/board',
				'title' => 'Status',
				'height' => 400,
				'aspect' => 'none',
				'sandbox' => ['allow-scripts', 'allow-same-origin'],
			],
			$overrides
		);
	}//end validConfig()

	// -------------------------------------------------------------
	// REQ-IFRAME-002: allow-list, fail-closed.
	// -------------------------------------------------------------

	public function testEmptyAllowListDeniesEveryHost(): void {
		$this->appConfig->method('getValueString')->willReturn('');

		$errors = $this->service->validateConfig(config: $this->validConfig());

		$this->assertContains('host_not_allowed', $errors);
	}//end testEmptyAllowListDeniesEveryHost()

	public function testMissingAllowListKeyDeniesEveryHost(): void {
		// Default IAppConfig mock returns '' for an unset key — matches
		// the FAIL-CLOSED contract with no explicit stub.
		$errors = $this->service->validateConfig(config: $this->validConfig());

		$this->assertContains('host_not_allowed', $errors);
	}//end testMissingAllowListKeyDeniesEveryHost()

	public function testAllowedHostPassesValidation(): void {
		$this->allowHosts(hosts: ['status.example.com']);

		$errors = $this->service->validateConfig(config: $this->validConfig());

		$this->assertSame([], $errors);
	}//end testAllowedHostPassesValidation()

	public function testDisallowedHostIsRejected(): void {
		$this->allowHosts(hosts: ['status.example.com']);

		$errors = $this->service->validateConfig(config: $this->validConfig(overrides: [
			'url' => 'https://evil.example.net/',
		]));

		$this->assertContains('host_not_allowed', $errors);
	}//end testDisallowedHostIsRejected()

	public function testHostMatchIsCaseInsensitive(): void {
		$this->allowHosts(hosts: ['Status.Example.com']);

		$errors = $this->service->validateConfig(config: $this->validConfig());

		$this->assertSame([], $errors);
	}//end testHostMatchIsCaseInsensitive()

	public function testIsHostAllowedReflectsCurrentAllowList(): void {
		$this->allowHosts(hosts: ['status.example.com']);

		$this->assertTrue($this->service->isHostAllowed(url: 'https://status.example.com/board'));
		$this->assertFalse($this->service->isHostAllowed(url: 'https://evil.example.net/'));
	}//end testIsHostAllowedReflectsCurrentAllowList()

	public function testHostRemovedFromAllowListIsNoLongerAllowed(): void {
		// Simulates "Host removed after configuration is refused at
		// render" — the same getAllowedHosts() call the CSP listener and
		// render-time re-check both use.
		$this->appConfig->method('getValueString')->willReturn('');

		$this->assertFalse($this->service->isHostAllowed(url: 'https://status.example.com/board'));
	}//end testHostRemovedFromAllowListIsNoLongerAllowed()

	public function testGetAllowedHostsReturnsEmptyArrayOnMalformedJson(): void {
		$this->appConfig->method('getValueString')->willReturn('not json');

		$this->assertSame([], $this->service->getAllowedHosts());
	}//end testGetAllowedHostsReturnsEmptyArrayOnMalformedJson()

	public function testGetAllowedHostsDeduplicates(): void {
		$this->allowHosts(hosts: ['status.example.com', 'status.example.com']);

		$this->assertSame(['status.example.com'], $this->service->getAllowedHosts());
	}//end testGetAllowedHostsDeduplicates()

	// -------------------------------------------------------------
	// REQ-IFRAME-002 / REQ-IFRAME-004: general field validation.
	// -------------------------------------------------------------

	public function testMissingUrlIsRejected(): void {
		$this->allowHosts(hosts: ['status.example.com']);

		$errors = $this->service->validateConfig(config: $this->validConfig(overrides: ['url' => '']));

		$this->assertContains('url_required', $errors);
	}//end testMissingUrlIsRejected()

	public function testInvalidSchemeIsRejected(): void {
		$this->allowHosts(hosts: ['status.example.com']);

		$errors = $this->service->validateConfig(config: $this->validConfig(overrides: [
			'url' => 'ftp://status.example.com/board',
		]));

		$this->assertContains('invalid_url', $errors);
	}//end testInvalidSchemeIsRejected()

	public function testMissingTitleIsRejected(): void {
		$this->allowHosts(hosts: ['status.example.com']);

		$errors = $this->service->validateConfig(config: $this->validConfig(overrides: ['title' => '']));

		$this->assertContains('title_required', $errors);
	}//end testMissingTitleIsRejected()

	public function testForbiddenSandboxTokenIsRejected(): void {
		$this->allowHosts(hosts: ['status.example.com']);

		$errors = $this->service->validateConfig(config: $this->validConfig(overrides: [
			'sandbox' => ['allow-scripts', 'allow-top-navigation'],
		]));

		$this->assertContains('forbidden_sandbox_token', $errors);
	}//end testForbiddenSandboxTokenIsRejected()

	public function testForbiddenSandboxTokenVariantIsRejected(): void {
		$this->allowHosts(hosts: ['status.example.com']);

		$errors = $this->service->validateConfig(config: $this->validConfig(overrides: [
			'sandbox' => ['allow-top-navigation-by-user-activation'],
		]));

		$this->assertContains('forbidden_sandbox_token', $errors);
	}//end testForbiddenSandboxTokenVariantIsRejected()

	// -------------------------------------------------------------
	// Sandbox token sanitisation.
	// -------------------------------------------------------------

	public function testSanitiseSandboxTokensStripsForbiddenAndUnknownTokens(): void {
		$clean = $this->service->sanitiseSandboxTokens(tokens: [
			'allow-scripts',
			'allow-top-navigation',
			'not-a-real-token',
			'allow-forms',
		]);

		$this->assertSame(['allow-scripts', 'allow-forms'], $clean);
	}//end testSanitiseSandboxTokensStripsForbiddenAndUnknownTokens()

	public function testSanitiseSandboxTokensReturnsEmptyArrayForNonArrayInput(): void {
		$this->assertSame([], $this->service->sanitiseSandboxTokens(tokens: null));
		$this->assertSame([], $this->service->sanitiseSandboxTokens(tokens: 'allow-scripts'));
	}//end testSanitiseSandboxTokensReturnsEmptyArrayForNonArrayInput()
}//end class
