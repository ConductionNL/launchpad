<?php

/**
 * JwtSigningService unit tests (embedded-analytics tasks 3 and 31).
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
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */

declare(strict_types=1);

namespace Unit\Service\Embed;

use InvalidArgumentException;
use OCA\LaunchPad\Exception\InvalidTokenException;
use OCA\LaunchPad\Exception\TokenExpiredException;
use OCA\LaunchPad\Service\Embed\JoseEncoding;
use OCA\LaunchPad\Service\Embed\JwtSigningService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Signing with the active key, verifying by kid, and rotating keys.
 */
#[CoversClass(JwtSigningService::class)]
#[CoversClass(JoseEncoding::class)]
#[UsesClass(InvalidTokenException::class)]
#[UsesClass(TokenExpiredException::class)]
class JwtSigningServiceTest extends TestCase {

	private const NOW = 1_800_000_000;

	private JwtSigningService $service;

	/**
	 * The moment the stub clock answers.
	 *
	 * @var int
	 */
	private int $now = self::NOW;

	protected function setUp(): void {
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn (): int => $this->now);
		$this->service = new JwtSigningService(time: $time);
	}

	/**
	 * The service with its clock set to a moment.
	 *
	 * @param int $now The moment (unix seconds).
	 *
	 * @return JwtSigningService
	 */
	private function at(int $now): JwtSigningService {
		$this->now = $now;
		return $this->service;
	}

	/**
	 * The claims a widget token carries.
	 *
	 * @param array<string, mixed> $extra Claims to add.
	 *
	 * @return array<string, mixed>
	 */
	private static function claims(array $extra = []): array {
		return array_merge(['sub' => 'widget:w-1', 'jti' => 'tok-1', 'iat' => self::NOW], $extra);
	}

	/**
	 * The header of a token.
	 *
	 * @param string $jwt The token.
	 *
	 * @return array<string, mixed>
	 */
	private static function header(string $jwt): array {
		return json_decode(base64_decode(strtr(explode('.', $jwt)[0], '-_', '+/')), true);
	}

	public function testAnRs256TokenVerifiesAndCarriesTheKid(): void {
		$key = $this->at(self::NOW)->generateKeyPair(algorithm: 'RS256', kid: 'rsa-1');
		$jwt = $this->service->sign(claims: self::claims(), keys: [$key]);

		$this->assertSame(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'rsa-1'], self::header($jwt));
		$this->assertSame(self::claims(), $this->at(self::NOW)->verify(jwt: $jwt, keys: [$key]));
		$this->assertSame('RSA', $key['publicKeyJwk']['kty']);
		$this->assertSame(JwtSigningService::MIN_RSA_BITS, openssl_pkey_get_details(openssl_pkey_get_public($key['publicKeyPem']))['bits']);
	}

	public function testAnEs256TokenVerifiesWithAJoseSignature(): void {
		$key = $this->at(self::NOW)->generateKeyPair(algorithm: 'ES256', kid: 'ec-1');
		$jwt = $this->service->sign(claims: self::claims(), keys: [$key]);
		$signature = base64_decode(strtr(explode('.', $jwt)[2], '-_', '+/'));

		$this->assertSame(64, strlen($signature), 'R||S, not DER');
		$this->assertSame(self::claims(), $this->at(self::NOW)->verify(jwt: $jwt, keys: [$key]));
		$this->assertSame(['kty' => 'EC', 'crv' => 'P-256'], array_intersect_key($key['publicKeyJwk'], ['kty' => 1, 'crv' => 1]));
	}

	public function testAnUnsupportedAlgorithmIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->at(self::NOW)->generateKeyPair(algorithm: 'HS256');
	}

	public function testAWrongKeyWithTheSameKidFailsToVerify(): void {
		$signer = $this->at(self::NOW)->generateKeyPair(algorithm: 'RS256', kid: 'rsa-1');
		$impostor = $this->at(self::NOW)->generateKeyPair(algorithm: 'RS256', kid: 'rsa-1');
		$jwt = $this->service->sign(claims: self::claims(), keys: [$signer]);

		$this->expectException(InvalidTokenException::class);
		$this->at(self::NOW)->verify(jwt: $jwt, keys: [$impostor]);
	}

	public function testATamperedPayloadFailsToVerify(): void {
		$key = $this->at(self::NOW)->generateKeyPair(algorithm: 'ES256', kid: 'ec-1');
		[$head, , $signature] = explode('.', $this->service->sign(claims: self::claims(), keys: [$key]));
		$widened = rtrim(strtr(base64_encode(json_encode(self::claims(['sub' => 'dashboard:all']))), '+/', '-_'), '=');

		$this->expectException(InvalidTokenException::class);
		$this->at(self::NOW)->verify(jwt: "{$head}.{$widened}.{$signature}", keys: [$key]);
	}

	public function testAnAlgorithmTheKeyIsNotOfIsRefused(): void {
		$key = $this->at(self::NOW)->generateKeyPair(algorithm: 'RS256', kid: 'rsa-1');
		[, $payload, $signature] = explode('.', $this->service->sign(claims: self::claims(), keys: [$key]));

		foreach (['none', 'HS256', 'ES256'] as $alg) {
			$head = rtrim(strtr(base64_encode(json_encode(['alg' => $alg, 'typ' => 'JWT', 'kid' => 'rsa-1'])), '+/', '-_'), '=');
			try {
				$this->at(self::NOW)->verify(jwt: "{$head}.{$payload}.{$signature}", keys: [$key]);
				$this->fail("alg {$alg} was accepted");
			} catch (InvalidTokenException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testAMalformedTokenIsRefused(): void {
		$key = $this->at(self::NOW)->generateKeyPair(algorithm: 'RS256', kid: 'rsa-1');

		foreach (['', 'abc', 'a.b', 'a.b.c', '!!.??.**'] as $jwt) {
			try {
				$this->at(self::NOW)->verify(jwt: $jwt, keys: [$key]);
				$this->fail("'{$jwt}' was accepted");
			} catch (InvalidTokenException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testAnExpiredTokenIsRefusedAndOneWithoutExpiryIsNot(): void {
		$key = $this->at(self::NOW)->generateKeyPair(algorithm: 'RS256', kid: 'rsa-1');
		$open = $this->service->sign(claims: self::claims(), keys: [$key]);
		$expired = $this->service->sign(claims: self::claims(['exp' => self::NOW]), keys: [$key]);

		$this->assertSame('tok-1', $this->at(self::NOW + 10 ** 8)->verify(jwt: $open, keys: [$key])['jti']);
		$this->expectException(TokenExpiredException::class);
		$this->at(self::NOW)->verify(jwt: $expired, keys: [$key]);
	}

	public function testATokenBeforeItsNotBeforeIsRefused(): void {
		$key = $this->at(self::NOW)->generateKeyPair(algorithm: 'RS256', kid: 'rsa-1');
		$jwt = $this->service->sign(claims: self::claims(['nbf' => self::NOW + 60]), keys: [$key]);

		$this->expectException(TokenExpiredException::class);
		$this->at(self::NOW)->verify(jwt: $jwt, keys: [$key]);
	}

	public function testRotationKeepsOldTokensValidAndSignsNewOnesWithTheNewKey(): void {
		$old = $this->at(self::NOW)->generateKeyPair(algorithm: 'ES256', kid: 'ec-old');
		$oldToken = $this->service->sign(claims: self::claims(), keys: [$old]);

		$keys = $this->at(self::NOW + 60)->rotate(keys: [$old], algorithm: 'RS256');
		$newToken = $this->service->sign(claims: self::claims(['jti' => 'tok-2']), keys: $keys);

		$this->assertSame(['retiring', 'active'], array_column($keys, 'status'));
		$this->assertNotNull($keys[0]['retiredAt']);
		$this->assertSame('ec-old', self::header($oldToken)['kid']);
		$this->assertSame($keys[1]['kid'], self::header($newToken)['kid']);
		$this->assertSame('RS256', self::header($newToken)['alg']);
		$this->assertSame('tok-1', $this->at(self::NOW + 120)->verify(jwt: $oldToken, keys: $keys)['jti']);
		$this->assertSame('tok-2', $this->at(self::NOW + 120)->verify(jwt: $newToken, keys: $keys)['jti']);
	}

	public function testARevokedKeyNoLongerVerifies(): void {
		$key = $this->at(self::NOW)->generateKeyPair(algorithm: 'RS256', kid: 'rsa-1');
		$jwt = $this->service->sign(claims: self::claims(), keys: [$key]);
		$key['status'] = JwtSigningService::STATUS_REVOKED;

		$this->expectException(InvalidTokenException::class);
		$this->at(self::NOW)->verify(jwt: $jwt, keys: [$key]);
	}

	public function testSigningNeedsAnActiveKey(): void {
		$key = $this->at(self::NOW)->generateKeyPair(algorithm: 'RS256', kid: 'rsa-1');
		$key['status'] = JwtSigningService::STATUS_RETIRING;

		$this->expectException(RuntimeException::class);
		$this->service->sign(claims: self::claims(), keys: [$key]);
	}
}
