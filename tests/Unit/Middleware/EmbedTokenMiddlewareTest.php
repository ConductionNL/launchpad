<?php

/**
 * EmbedTokenMiddleware unit tests (REQ-EMB-002/004, task 6).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Middleware
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

namespace OCA\LaunchPad\Tests\Unit\Middleware;

use OCA\LaunchPad\Controller\EmbedController;
use OCA\LaunchPad\Entity\EmbedToken;
use OCA\LaunchPad\Exception\EmbedRefusalException;
use OCA\LaunchPad\Exception\TokenExpiredException;
use OCA\LaunchPad\Middleware\EmbedTokenMiddleware;
use OCA\LaunchPad\Service\Embed\EmbedGate;
use OCA\LaunchPad\Service\Embed\EmbedRequestContext;
use OCA\LaunchPad\Service\Embed\EmbedTokenService;
use OCA\LaunchPad\Service\Embed\JoseEncoding;
use OCA\LaunchPad\Service\Embed\ScopeEnforcementService;
use OCA\LaunchPad\Service\Embed\UsageTelemetryService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use OCP\AppFramework\Http\Response;

#[CoversClass(EmbedTokenMiddleware::class)]
#[CoversClass(EmbedRequestContext::class)]
#[UsesClass(EmbedGate::class)]
#[UsesClass(EmbedRefusalException::class)]
#[UsesClass(EmbedToken::class)]
#[UsesClass(JoseEncoding::class)]
#[UsesClass(ScopeEnforcementService::class)]
class EmbedTokenMiddlewareTest extends TestCase {

	private EmbedTokenService&MockObject $tokens;

	private UsageTelemetryService&MockObject $telemetry;

	private EmbedRequestContext $context;

	protected function setUp(): void {
		$this->tokens    = $this->createMock(EmbedTokenService::class);
		$this->telemetry = $this->createMock(UsageTelemetryService::class);
		$this->context   = new EmbedRequestContext();
	}//end setUp()

	public function testARequestWithoutAnEmbedTokenIsLeftAlone(): void {
		$this->tokens->expects($this->never())->method('validateToken');

		$this->middleware(request: $this->request(method: 'POST', path: '/api/dashboards/D', headers: ['Authorization' => 'Bearer an-app-password']))
			->beforeController($this->createMock(Controller::class), 'update');

		$this->assertNull($this->context->token());
	}//end testARequestWithoutAnEmbedTokenIsLeftAlone()

	public function testTheRenderRouteAdmitsAQueryToken(): void {
		$token = self::token(scope: ['mode' => 'read']);
		$this->tokens->expects($this->once())->method('validateToken')->with('x.y.z')->willReturn($token);

		$this->middleware(request: $this->request(method: 'GET', path: '/embed/widget/42', params: ['token' => 'x.y.z']))
			->beforeController($this->createMock(EmbedController::class), 'render');

		$this->assertSame($token, $this->context->token());
		$this->assertSame('query', $this->context->form());
		$this->assertGreaterThanOrEqual(0, $this->context->latencyMs(now: (microtime(true) * 1000)));
	}//end testTheRenderRouteAdmitsAQueryToken()

	public function testTheRenderRouteRefusesARequestWithoutAToken(): void {
		$this->expectException(EmbedRefusalException::class);
		$this->expectExceptionCode(401);

		$this->middleware(request: $this->request(method: 'GET', path: '/embed/widget/42'))
			->beforeController($this->createMock(EmbedController::class), 'render');
	}//end testTheRenderRouteRefusesARequestWithoutAToken()

	public function testAReadTokenCannotWriteAnywhereEvenWhenTheUiIsBypassed(): void {
		$this->tokens->method('validateToken')->willReturn(self::token(scope: ['mode' => 'read']));
		$middleware = $this->middleware(request: $this->request(method: 'POST', path: '/api/dashboards/D/widgets/new', headers: ['Authorization' => 'Bearer ' . self::jwt()]));

		try {
			$middleware->beforeController($this->createMock(Controller::class), 'create');
			$this->fail('A write under a read token must be refused.');
		} catch (EmbedRefusalException $refusal) {
			$this->assertSame(403, $refusal->status);
			$this->assertSame('read_only_token', $refusal->body['error']);
		}

		$this->assertSame('bearer', $this->context->form());
	}//end testAReadTokenCannotWriteAnywhereEvenWhenTheUiIsBypassed()

	public function testAReadTokenMayStillRead(): void {
		$this->tokens->method('validateToken')->willReturn(self::token(scope: ['mode' => 'read']));

		$this->middleware(request: $this->request(method: 'GET', path: '/api/dashboards/D', headers: ['Authorization' => 'Bearer ' . self::jwt()]))
			->beforeController($this->createMock(Controller::class), 'show');

		$this->assertSame('token-1', $this->context->token()?->id);
	}//end testAReadTokenMayStillRead()

	public function testARefusalOnTheRenderRouteIsAnsweredAndWrittenAsAPageView(): void {
		$this->tokens->method('validateToken')->willThrowException(new TokenExpiredException('expired'));
		$request    = $this->request(
			method: 'GET',
			path: '/embed/widget/42',
			headers: ['Origin' => 'https://www.zeist.nl', 'User-Agent' => 'Firefox/125.0'],
			params: ['token' => self::jwt(), 'viewport' => '1280x800']
		);
		$middleware = $this->middleware(request: $request);
		$controller = $this->createMock(EmbedController::class);

		try {
			$middleware->beforeController($controller, 'render');
			$this->fail('An expired token must be refused.');
		} catch (EmbedRefusalException $refusal) {
			$this->telemetry->expects($this->once())->method('record')->with(
				'token-1',
				'pageView',
				'https://www.zeist.nl',
				'',
				'Firefox/125.0',
				'1280x800',
				401,
				$this->greaterThanOrEqual(0)
			);

			$response = $middleware->afterException($controller, 'render', $refusal);
		}

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(401, $response->getStatus());
		$this->assertSame(['error' => 'token_expired', 'message' => 'The provided token has expired'], $response->getData());
		$this->assertSame('Bearer error="invalid_token", error_description="The token has expired"', self::headersOf($response)['WWW-Authenticate']);
	}//end testARefusalOnTheRenderRouteIsAnsweredAndWrittenAsAPageView()

	public function testARefusalWithoutAKnownTokenOrOffTheRenderRouteWritesNoEvent(): void {
		$this->telemetry->expects($this->never())->method('record');
		$middleware = $this->middleware(request: $this->request(method: 'GET', path: '/embed/widget/42'));

		$anonymous = $middleware->afterException(
			$this->createMock(EmbedController::class),
			'render',
			new EmbedRefusalException(status: 401, body: ['error' => 'missing_token'])
		);
		$api = $middleware->afterException(
			$this->createMock(Controller::class),
			'create',
			new EmbedRefusalException(status: 403, body: ['error' => 'read_only_token'], tokenId: 'token-1')
		);

		$this->assertSame(401, $anonymous->getStatus());
		$this->assertSame(403, $api->getStatus());
		$this->assertSame(['error' => 'read_only_token'], $api->getData());
	}//end testARefusalWithoutAKnownTokenOrOffTheRenderRouteWritesNoEvent()

	public function testAnyOtherExceptionIsNotOurs(): void {
		$failure = new RuntimeException('boom');

		$this->expectExceptionObject($failure);

		$this->middleware(request: $this->request(method: 'GET', path: '/embed/widget/42'))
			->afterException($this->createMock(EmbedController::class), 'render', $failure);
	}//end testAnyOtherExceptionIsNotOurs()

	private function middleware(IRequest $request): EmbedTokenMiddleware {
		return new EmbedTokenMiddleware(
			$request,
			new EmbedGate($this->tokens, new ScopeEnforcementService(), new JoseEncoding()),
			$this->context,
			$this->telemetry
		);
	}//end middleware()

	/**
	 * @param array<string, string> $headers
	 * @param array<string, mixed>  $params
	 */
	private function request(string $method, string $path, array $headers=[], array $params=[]): IRequest {
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn($method);
		$request->method('getPathInfo')->willReturn($path);
		$request->method('getHeader')->willReturnCallback(static fn (string $name): string => $headers[$name] ?? '');
		$request->method('getParam')->willReturnCallback(static fn (string $name, mixed $default=null): mixed => $params[$name] ?? $default);
		return $request;
	}//end request()

	/**
	 * The headers set on a response, without Response::getHeaders(), which needs a running server.
	 *
	 * @return array<string, string>
	 */
	private static function headersOf(Response $response): array {
		return (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
	}//end headersOf()

	private static function jwt(): string {
		$jose = new JoseEncoding();
		return $jose->base64Url('{"alg":"RS256","kid":"k1"}') . '.' . $jose->base64Url('{"iss":"launchpad","jti":"token-1"}') . '.c2ln';
	}//end jwt()

	/**
	 * @param array<string, mixed> $scope
	 */
	private static function token(array $scope): EmbedToken {
		return EmbedToken::fromObject([
			'id' => 'token-1',
			'subject' => ['type' => 'widget', 'id' => '42'],
			'scope' => $scope,
			'hostOrigins' => ['https://www.zeist.nl'],
		]);
	}//end token()
}//end class
