<?php

/**
 * JwtSigningService and JoseEncoding edge cases (embedded-analytics task 3).
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

use OCA\LaunchPad\Exception\InvalidTokenException;
use OCA\LaunchPad\Exception\TokenExpiredException;
use OCA\LaunchPad\Service\Embed\JoseEncoding;
use OCA\LaunchPad\Service\Embed\JwtSigningService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The paths the main suite does not reach: long-form DER lengths, a signature
 * of the wrong size, token segments that are base64url but not a JSON object,
 * a key whose private part cannot be read, and the system clock fallback.
 */
#[CoversClass(JwtSigningService::class)]
#[CoversClass(JoseEncoding::class)]
#[UsesClass(InvalidTokenException::class)]
#[UsesClass(TokenExpiredException::class)]
class JwtSigningEdgeCasesTest extends TestCase {

	/**
	 * A DER signature with a long-form sequence length reads as R||S.
	 *
	 * @return void
	 */
	public function testALongFormDerLengthIsSkipped(): void {
		$r = str_repeat("\x11", 32);
		$s = str_repeat("\x22", 32);
		$body = "\x02\x20" . $r . "\x02\x20" . $s;
		$der = "\x30\x81" . chr(strlen($body)) . $body;

		$this->assertSame($r . $s, (new JoseEncoding())->derToRaw(der: $der));
	}

	/**
	 * A short-form DER signature with a padded R and a short S reads as 64 bytes.
	 *
	 * @return void
	 */
	public function testShortFormPartsArePaddedToThirtyTwoBytes(): void {
		$r = "\x80" . str_repeat("\x01", 31);
		$s = str_repeat("\x02", 30);
		$body = "\x02\x21\x00" . $r . "\x02\x1e" . $s;
		$der = "\x30" . chr(strlen($body)) . $body;

		$this->assertSame($r . "\0\0" . $s, (new JoseEncoding())->derToRaw(der: $der));
	}

	/**
	 * A JOSE signature that is not 64 bytes is refused.
	 *
	 * @return void
	 */
	public function testARawSignatureOfTheWrongSizeIsRefused(): void {
		$this->expectException(InvalidTokenException::class);
		$this->expectExceptionMessage('The token signature does not verify.');

		(new JoseEncoding())->rawToDer(raw: str_repeat("\x01", 63));
	}

	/**
	 * A header segment that decodes to text that is not JSON is refused.
	 *
	 * @return void
	 */
	public function testAHeaderThatIsNotJsonIsRefused(): void {
		$jose = new JoseEncoding();
		$jwt = $jose->base64Url(bytes: 'not json') . '.' . $jose->base64Url(bytes: '{}') . '.' . $jose->base64Url(bytes: 'sig');

		$this->expectException(InvalidTokenException::class);
		$this->expectExceptionMessage('The token is not a signed JWT.');

		(new JwtSigningService())->verify(jwt: $jwt, keys: []);
	}

	/**
	 * A claims segment that is JSON but not an object is refused.
	 *
	 * @return void
	 */
	public function testClaimsThatAreNotAnObjectAreRefused(): void {
		$jose = new JoseEncoding();
		$jwt = $jose->base64Url(bytes: '{"alg":"RS256","kid":"k"}') . '.' . $jose->base64Url(bytes: '123') . '.' . $jose->base64Url(bytes: 'sig');

		$this->expectException(InvalidTokenException::class);
		$this->expectExceptionMessage('The token is not a signed JWT.');

		(new JwtSigningService())->verify(jwt: $jwt, keys: []);
	}

	/**
	 * An active key whose private part cannot be read does not sign.
	 *
	 * @return void
	 */
	public function testAKeyWithAnUnreadablePrivatePartDoesNotSign(): void {
		$key = ['kid' => 'broken', 'algorithm' => 'RS256', 'status' => 'active', 'privateKeyPem' => 'not a key'];

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('The token could not be signed.');

		(new JwtSigningService())->sign(claims: ['sub' => 'widget:w-1'], keys: [$key]);
	}

	/**
	 * Without an injected clock the service checks expiry against the system time.
	 *
	 * @return void
	 */
	public function testWithoutAClockTheSystemTimeDecides(): void {
		$service = new JwtSigningService();
		$key = $service->generateKeyPair(algorithm: 'ES256', kid: 'ec-1');

		$current = ['sub' => 'widget:w-1', 'nbf' => time() - 60, 'exp' => time() + 3600];
		$this->assertSame($current, $service->verify(jwt: $service->sign(claims: $current, keys: [$key]), keys: [$key]));

		$expired = ['sub' => 'widget:w-1', 'exp' => time() - 3600];
		$this->expectException(TokenExpiredException::class);
		$service->verify(jwt: $service->sign(claims: $expired, keys: [$key]), keys: [$key]);
	}
}
