<?php

/**
 * EmbedController unit tests (REQ-EMB-002/003/007/008, tasks 9, 26, 28).
 *
 * @category  Test
 * @package   Unit\Controller
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

namespace Unit\Controller;

use OCA\LaunchPad\Controller\EmbedController;
use OCA\LaunchPad\Entity\EmbedToken;
use OCA\LaunchPad\Exception\EmbedRefusalException;
use OCA\LaunchPad\Service\Embed\EmbedGate;
use OCA\LaunchPad\Service\Embed\EmbedRenderService;
use OCA\LaunchPad\Service\Embed\EmbedRequestContext;
use OCA\LaunchPad\Service\Embed\EmbedTokenService;
use OCA\LaunchPad\Service\Embed\JoseEncoding;
use OCA\LaunchPad\Service\Embed\RateLimitService;
use OCA\LaunchPad\Service\Embed\ScopeEnforcementService;
use OCA\LaunchPad\Service\Embed\UsageTelemetryService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(EmbedController::class)]
#[UsesClass(EmbedGate::class)]
#[UsesClass(EmbedRefusalException::class)]
#[UsesClass(EmbedRequestContext::class)]
#[UsesClass(EmbedToken::class)]
#[UsesClass(RateLimitService::class)]
#[UsesClass(ScopeEnforcementService::class)]
class EmbedControllerTest extends TestCase {

	private EmbedRenderService&MockObject $renderer;

	private UsageTelemetryService&MockObject $telemetry;

	private EmbedRequestContext $context;

	/**
	 * @var array<string, mixed>
	 */
	private array $buckets = [];

	private int $now = 1779460200;

	protected function setUp(): void {
		$this->renderer  = $this->createMock(EmbedRenderService::class);
		$this->telemetry = $this->createMock(UsageTelemetryService::class);
		$this->context   = new EmbedRequestContext();
	}//end setUp()

	public function testTheNamedWidgetRendersWithLimitHeadersAndAPageView(): void {
		$this->admit(token: self::token());
		$page = new TemplateResponse('launchpad', 'embed');
		$this->renderer->expects($this->once())->method('page')->with('widget', '42', $this->context->token())->willReturn($page);
		$this->telemetry->expects($this->once())->method('record')->with(
			'token-1',
			'pageView',
			'',
			'https://www.zeist.nl/woo/',
			'Mozilla/5.0',
			'small',
			200,
			$this->greaterThanOrEqual(0)
		);

		$response = $this->controller(request: $this->request(
			headers: ['Referer' => 'https://www.zeist.nl/woo/', 'Sec-Fetch-Site' => 'cross-site', 'User-Agent' => 'Mozilla/5.0'],
			params: ['viewport' => 'small']
		))->render(subjectType: 'widget', subjectId: '42');

		$this->assertSame($page, $response);
		$headers = self::headersOf($response);
		$this->assertSame('60', $headers['RateLimit-Limit']);
		$this->assertSame('9', $headers['RateLimit-Remaining']);
		$this->assertSame('1', $headers['RateLimit-Reset']);
	}//end testTheNamedWidgetRendersWithLimitHeadersAndAPageView()

	public function testAnotherSubjectIsRefusedWithoutRenderingAnything(): void {
		$this->admit(token: self::token());
		$this->renderer->expects($this->never())->method('page');
		$this->telemetry->expects($this->never())->method('record');

		$refusal = $this->refusalOf(fn () => $this->controller(request: $this->request())->render(subjectType: 'widget', subjectId: '43'));
		$this->assertSame(403, $refusal->status);
		$this->assertSame('scope_mismatch', $refusal->body['error']);

		$refusal = $this->refusalOf(fn () => $this->controller(request: $this->request())->data(subjectType: 'dashboard', subjectId: '42'));
		$this->assertSame('scope_mismatch', $refusal->body['error']);
	}//end testAnotherSubjectIsRefusedWithoutRenderingAnything()

	public function testWithoutAnAdmittedTokenNothingRenders(): void {
		$refusal = $this->refusalOf(fn () => $this->controller(request: $this->request())->render(subjectType: 'widget', subjectId: '42'));

		$this->assertSame(403, $refusal->status);
	}//end testWithoutAnAdmittedTokenNothingRenders()

	public function testAFramingPageOffTheHostOriginsIsRefused(): void {
		$this->admit(token: self::token());
		$this->renderer->expects($this->never())->method('page');

		$refusal = $this->refusalOf(fn () => $this->controller(request: $this->request(
			headers: ['Referer' => 'https://evil.example.com/phishing', 'Sec-Fetch-Site' => 'cross-site']
		))->render(subjectType: 'widget', subjectId: '42'));

		$this->assertSame('origin_mismatch', $refusal->body['error']);
	}//end testAFramingPageOffTheHostOriginsIsRefused()

	public function testASpentBucketIsA429WithRetryAfter(): void {
		$this->admit(token: self::token(policy: ['requestsPerMinute' => 60, 'burstSize' => 2]));
		$this->renderer->method('page')->willReturn(new TemplateResponse('launchpad', 'embed'));
		$controller = $this->controller(request: $this->request());
		$controller->render(subjectType: 'widget', subjectId: '42');
		$controller->render(subjectType: 'widget', subjectId: '42');

		$refusal = $this->refusalOf(fn () => $controller->render(subjectType: 'widget', subjectId: '42'));

		$this->assertSame(429, $refusal->status);
		$this->assertSame('rate_limit_exceeded', $refusal->body['error']);
		$this->assertSame(1, $refusal->body['retryAfter']);
		$this->assertSame(
			['Retry-After' => '1', 'RateLimit-Limit' => '60', 'RateLimit-Remaining' => '0', 'RateLimit-Reset' => '1'],
			$refusal->headers
		);
	}//end testASpentBucketIsA429WithRetryAfter()

	public function testTheDataOfTheSubjectKeepsOnlyArrayFilters(): void {
		$this->admit(token: self::token());
		$this->renderer->expects($this->exactly(2))->method('render')
			->willReturnCallback(static fn (string $type, string $id, EmbedToken $token, array $filters): array => ['subjectType' => $type, 'filters' => $filters]);
		$this->telemetry->expects($this->exactly(2))->method('record')
			->with('token-1', 'widgetRendered', $this->anything(), $this->anything(), $this->anything(), null, 200, $this->anything());

		$withFilters = $this->controller(request: $this->request(params: ['filters' => ['status' => 'open']]))->data(subjectType: 'widget', subjectId: '42');
		$withJunk    = $this->controller(request: $this->request(params: ['filters' => 'status=open']))->data(subjectType: 'widget', subjectId: '42');

		$this->assertInstanceOf(JSONResponse::class, $withFilters);
		$this->assertSame(['subjectType' => 'widget', 'filters' => ['status' => 'open']], $withFilters->getData());
		$this->assertSame(['subjectType' => 'widget', 'filters' => []], $withJunk->getData());
	}//end testTheDataOfTheSubjectKeepsOnlyArrayFilters()

	public function testASubjectThatNoLongerExistsIsA404(): void {
		$this->admit(token: self::token());
		$this->renderer->method('render')->willReturn(null);
		$this->telemetry->expects($this->once())->method('record')
			->with('token-1', 'widgetRendered', $this->anything(), $this->anything(), $this->anything(), null, 404, $this->anything());

		$response = $this->controller(request: $this->request())->data(subjectType: 'widget', subjectId: '42');

		$this->assertSame(404, $response->getStatus());
		$this->assertSame(['error' => 'not_found', 'message' => 'The embedded resource no longer exists'], $response->getData());
	}//end testASubjectThatNoLongerExistsIsA404()

	private function admit(EmbedToken $token): void {
		$this->context->admit(token: $token, form: 'query', startedAt: (microtime(true) * 1000));
	}//end admit()

	private function controller(IRequest $request): EmbedController {
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturnCallback(fn (string $key): mixed => $this->buckets[$key] ?? null);
		$cache->method('set')->willReturnCallback(function (string $key, mixed $value): bool {
			$this->buckets[$key] = $value;
			return true;
		});
		$caches = $this->createMock(ICacheFactory::class);
		$caches->method('createDistributed')->willReturn($cache);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn (): int => $this->now);

		return new EmbedController(
			$request,
			$this->context,
			new EmbedGate($this->createMock(EmbedTokenService::class), new ScopeEnforcementService(), new JoseEncoding()),
			$this->renderer,
			new RateLimitService($caches),
			$this->telemetry,
			$time
		);
	}//end controller()

	/**
	 * @param array<string, string> $headers
	 * @param array<string, mixed>  $params
	 */
	private function request(array $headers=[], array $params=[]): IRequest {
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturnCallback(static fn (string $name): string => $headers[$name] ?? '');
		$request->method('getParam')->willReturnCallback(static fn (string $name, mixed $default=null): mixed => $params[$name] ?? $default);
		return $request;
	}//end request()

	/**
	 * @param array<string, int>|null $policy
	 */
	private static function token(?array $policy=['requestsPerMinute' => 60, 'burstSize' => 10]): EmbedToken {
		return EmbedToken::fromObject([
			'id' => 'token-1',
			'subject' => ['type' => 'widget', 'id' => '42'],
			'scope' => ['mode' => 'read'],
			'hostOrigins' => ['https://www.zeist.nl'],
			'rateLimitPolicy' => $policy,
		]);
	}//end token()

	/**
	 * @return array<string, string>
	 */
	private static function headersOf(Response $response): array {
		return (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
	}//end headersOf()

	private function refusalOf(callable $call): EmbedRefusalException {
		try {
			$call();
		} catch (EmbedRefusalException $refusal) {
			return $refusal;
		}

		$this->fail('Expected a refusal.');
	}//end refusalOf()
}//end class
