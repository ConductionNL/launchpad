<?php

/**
 * EmbedGate unit tests (REQ-EMB-002/003/004).
 *
 * @category  Test
 * @package   Unit\Service\Embed
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

namespace Unit\Service\Embed;

use OCA\LaunchPad\Entity\EmbedToken;
use OCA\LaunchPad\Exception\EmbedRefusalException;
use OCA\LaunchPad\Exception\InvalidTokenException;
use OCA\LaunchPad\Exception\TokenExpiredException;
use OCA\LaunchPad\Exception\TokenRevokedException;
use OCA\LaunchPad\Service\Embed\EmbedGate;
use OCA\LaunchPad\Service\Embed\EmbedTokenService;
use OCA\LaunchPad\Service\Embed\JoseEncoding;
use OCA\LaunchPad\Service\Embed\ScopeEnforcementService;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmbedGate::class)]
#[CoversClass(EmbedRefusalException::class)]
#[UsesClass(EmbedToken::class)]
#[UsesClass(JoseEncoding::class)]
#[UsesClass(ScopeEnforcementService::class)]
class EmbedGateTest extends TestCase {

	private EmbedTokenService&MockObject $tokens;

	protected function setUp(): void {
		$this->tokens = $this->createMock(EmbedTokenService::class);
	}//end setUp()

	public function testTheBearerHeaderWinsAndTheQueryCountsOnlyWhereAllowed(): void {
		$gate = $this->gate();

		$this->assertSame(
			['jwt' => 'a.b.c', 'form' => EmbedGate::FORM_BEARER],
			$gate->credentials(request: $this->request(headers: ['Authorization' => 'Bearer a.b.c'], params: ['token' => 'x.y.z']), allowQuery: true)
		);
		$this->assertSame(
			['jwt' => 'x.y.z', 'form' => EmbedGate::FORM_QUERY],
			$gate->credentials(request: $this->request(params: ['token' => ' x.y.z ']), allowQuery: true)
		);
		$this->assertNull($gate->credentials(request: $this->request(params: ['token' => 'x.y.z']), allowQuery: false));
		$this->assertNull($gate->credentials(request: $this->request(headers: ['Authorization' => 'Basic dXNlcjpwYXNz']), allowQuery: true));
		$this->assertNull($gate->credentials(request: $this->request(params: ['token' => ['x']]), allowQuery: true));
	}//end testTheBearerHeaderWinsAndTheQueryCountsOnlyWhereAllowed()

	public function testOnlyABearerJwtIssuedByLaunchPadMarksAnEmbedRequest(): void {
		$gate = $this->gate();

		$this->assertTrue($gate->carriesEmbedToken(request: $this->bearer(claims: ['iss' => 'launchpad', 'jti' => 't-1'])));
		$this->assertFalse($gate->carriesEmbedToken(request: $this->bearer(claims: ['iss' => 'someone-else'])));
		$this->assertFalse($gate->carriesEmbedToken(request: $this->request(headers: ['Authorization' => 'Bearer an-app-password'])));
		$this->assertFalse($gate->carriesEmbedToken(request: $this->request(headers: ['Authorization' => 'Bearer a.%%%.c'])));
		$this->assertFalse($gate->carriesEmbedToken(request: $this->request(params: ['token' => self::jwt(['iss' => 'launchpad'])])));
	}//end testOnlyABearerJwtIssuedByLaunchPadMarksAnEmbedRequest()

	public function testAValidTokenIsAdmittedWithItsForm(): void {
		$token = self::token();
		$this->tokens->expects($this->once())->method('validateToken')->with('x.y.z')->willReturn($token);

		$admitted = $this->gate()->authenticate(request: $this->request(params: ['token' => 'x.y.z']), allowQuery: true);

		$this->assertSame(['token' => $token, 'form' => 'query'], $admitted);
	}//end testAValidTokenIsAdmittedWithItsForm()

	public function testAMissingTokenIsA401NamingBothForms(): void {
		$this->tokens->expects($this->never())->method('validateToken');

		$refusal = $this->refusalOf(fn () => $this->gate()->authenticate(request: $this->request(), allowQuery: true));

		$this->assertSame(401, $refusal->status);
		$this->assertSame(
			['error' => 'missing_token', 'message' => 'No token provided. Include ?token=<jwt> or use the Authorization: Bearer <jwt> header'],
			$refusal->body
		);
		$this->assertSame('', $refusal->tokenId);
	}//end testAMissingTokenIsA401NamingBothForms()

	public function testAnExpiredTokenIsA401WithTheChallengeAndItsGenuineId(): void {
		$jwt = self::jwt(['iss' => 'launchpad', 'jti' => 'token-7']);
		$this->tokens->method('validateToken')->willThrowException(new TokenExpiredException('expired'));

		$refusal = $this->refusalOf(fn () => $this->gate()->authenticate(request: $this->request(params: ['token' => $jwt]), allowQuery: true));

		$this->assertSame(401, $refusal->status);
		$this->assertSame(['error' => 'token_expired', 'message' => 'The provided token has expired'], $refusal->body);
		$this->assertSame(['WWW-Authenticate' => 'Bearer error="invalid_token", error_description="The token has expired"'], $refusal->headers);
		$this->assertSame('token-7', $refusal->tokenId);
	}//end testAnExpiredTokenIsA401WithTheChallengeAndItsGenuineId()

	public function testARevokedTokenIsA401(): void {
		$jwt = self::jwt(['iss' => 'launchpad', 'jti' => 'token-8']);
		$this->tokens->method('validateToken')->willThrowException(new TokenRevokedException('revoked'));

		$refusal = $this->refusalOf(fn () => $this->gate()->authenticate(request: $this->bearerJwt(jwt: $jwt), allowQuery: false));

		$this->assertSame(['error' => 'token_revoked', 'message' => 'The provided token has been revoked'], $refusal->body);
		$this->assertSame('token-8', $refusal->tokenId);
	}//end testARevokedTokenIsA401()

	public function testAnInvalidTokenIsA401WithoutAnId(): void {
		$jwt = self::jwt(['iss' => 'launchpad', 'jti' => 'forged']);
		$this->tokens->method('validateToken')->willThrowException(new InvalidTokenException('bad signature'));

		$refusal = $this->refusalOf(fn () => $this->gate()->authenticate(request: $this->request(params: ['token' => $jwt]), allowQuery: true));

		$this->assertSame(401, $refusal->status);
		$this->assertSame(['error' => 'invalid_token', 'message' => 'The provided token is invalid or malformed'], $refusal->body);
		$this->assertSame('', $refusal->tokenId);
	}//end testAnInvalidTokenIsA401WithoutAnId()

	public function testTheScopeDecidesWritesAndLetsReadsThrough(): void {
		$gate = $this->gate();
		$read = self::token(scope: ['mode' => 'read']);
		$interact = self::token(scope: ['mode' => 'read-with-interactions', 'allowedActions' => ['interact', 'drillDown']]);

		$this->assertNull($gate->scopeRefusal(method: 'GET', path: '/api/dashboards/D', token: $read));
		$this->assertNull($gate->scopeRefusal(method: 'head', path: '/api/dashboards/D', token: $read));
		$this->assertSame('read_only_token', $gate->scopeRefusal(method: 'DELETE', path: '/api/dashboards/D/widgets/w1', token: $read)?->body['error']);
		$this->assertNull($gate->scopeRefusal(method: 'POST', path: '/api/dashboards/D/filters', token: $interact));

		$refusal = $gate->scopeRefusal(method: 'POST', path: '/api/dashboards/D/export', token: $interact);
		$this->assertSame(403, $refusal?->status);
		$this->assertSame(
			['error' => 'action_not_in_scope', 'message' => 'The action \'export\' is not permitted by this token. Allowed actions: interact, drillDown'],
			$refusal?->body
		);
	}//end testTheScopeDecidesWritesAndLetsReadsThrough()

	public function testAFramingPageOutsideTheHostOriginsIsRefused(): void {
		$gate = $this->gate();
		$token = self::token();
		$evil = ['Referer' => 'https://evil.example.com/page', 'Sec-Fetch-Site' => 'cross-site'];

		$refusal = $gate->originRefusal(request: $this->request(headers: $evil), token: $token, form: 'query');
		$this->assertSame(403, $refusal?->status);
		$this->assertSame(['error' => 'origin_mismatch', 'message' => 'This iframe cannot be embedded on this origin'], $refusal?->body);

		// A Bearer token is checked against the Referer whatever Sec-Fetch-Site says (3.6).
		$this->assertNotNull($gate->originRefusal(request: $this->request(headers: ['Referer' => 'https://evil.example.com/']), token: $token, form: 'bearer'));
		$this->assertNull($gate->originRefusal(request: $this->request(headers: ['Referer' => 'https://evil.example.com/']), token: $token, form: 'query'));
		$this->assertNull($gate->originRefusal(request: $this->request(headers: ['Referer' => 'https://WWW.Zeist.nl/woo/', 'Sec-Fetch-Site' => 'cross-site']), token: $token, form: 'query'));
		$this->assertNull($gate->originRefusal(request: $this->request(headers: ['Sec-Fetch-Site' => 'same-site']), token: $token, form: 'bearer'));
		// The embed page loading its own subject is same-origin: its Referer is LaunchPad itself.
		$this->assertNull($gate->originRefusal(request: $this->request(headers: ['Referer' => 'https://cloud.example.nl/apps/launchpad/embed/widget/42', 'Sec-Fetch-Site' => 'same-origin']), token: $token, form: 'bearer'));
		$this->assertNotNull($gate->originRefusal(request: $this->request(headers: ['Referer' => 'https://www.zeist.nl:8443/', 'Sec-Fetch-Site' => 'cross-site']), token: $token, form: 'query'));
	}//end testAFramingPageOutsideTheHostOriginsIsRefused()

	public function testASubjectMismatchDoesNotSayWhetherTheSubjectExists(): void {
		$refusal = $this->gate()->subjectMismatch();

		$this->assertSame(403, $refusal->status);
		$this->assertSame(['error' => 'scope_mismatch', 'message' => 'The provided token does not grant access to this resource'], $refusal->body);
		$this->assertSame('The provided token does not grant access to this resource', $refusal->getMessage());
	}//end testASubjectMismatchDoesNotSayWhetherTheSubjectExists()

	private function gate(): EmbedGate {
		return new EmbedGate($this->tokens, new ScopeEnforcementService(), new JoseEncoding());
	}//end gate()

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
	 * @param array<string, mixed> $claims
	 */
	private function bearer(array $claims): IRequest {
		return $this->bearerJwt(jwt: self::jwt($claims));
	}//end bearer()

	private function bearerJwt(string $jwt): IRequest {
		return $this->request(headers: ['Authorization' => 'Bearer ' . $jwt]);
	}//end bearerJwt()

	/**
	 * An unsigned JWT-shaped string with these claims.
	 *
	 * @param array<string, mixed> $claims
	 */
	private static function jwt(array $claims): string {
		$jose = new JoseEncoding();
		return $jose->base64Url((string)json_encode(['alg' => 'RS256', 'kid' => 'k1'])) . '.' . $jose->base64Url((string)json_encode($claims)) . '.c2ln';
	}//end jwt()

	/**
	 * @param array<string, mixed> $scope
	 */
	private static function token(array $scope=['mode' => 'read']): EmbedToken {
		return EmbedToken::fromObject([
			'id' => 'token-1',
			'name' => 'Zeist WOO',
			'subject' => ['type' => 'widget', 'id' => '42'],
			'scope' => $scope,
			'hostOrigins' => ['https://www.zeist.nl'],
		]);
	}//end token()

	private function refusalOf(callable $call): EmbedRefusalException {
		try {
			$call();
		} catch (EmbedRefusalException $refusal) {
			return $refusal;
		}

		$this->fail('Expected a refusal.');
	}//end refusalOf()
}//end class
