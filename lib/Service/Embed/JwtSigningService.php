<?php

/**
 * JwtSigningService
 *
 * The JWT lifecycle of embed tokens (embedded-analytics task 3): RS256 and
 * ES256 key pairs, signing with the active key under its `kid`, verifying
 * against the key the `kid` selects, and rotating keys so tokens signed by
 * a retiring key stay valid until they expire.
 *
 * A key is a plain record, the shape of a `signing_key` object: `kid`,
 * `algorithm`, `status` (active, retiring, revoked), `publicKeyPem`,
 * `privateKeyPem`, `publicKeyJwk`, `createdAt` and `retiredAt`. Storing it
 * (and encrypting the private key at rest) is the token store's job, not
 * this service's.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Embed
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

namespace OCA\LaunchPad\Service\Embed;

use InvalidArgumentException;
use JsonException;
use OCA\LaunchPad\Exception\InvalidTokenException;
use OCA\LaunchPad\Exception\TokenExpiredException;
use RuntimeException;

/**
 * Signs and verifies embed JWTs (RFC 7515/7519) with rotating asymmetric keys.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
 */
final class JwtSigningService {

	/**
	 * Constructor.
	 *
	 * @param JoseEncoding $jose The JWS byte encodings.
	 */
	public function __construct(
		private readonly JoseEncoding $jose = new JoseEncoding(),
	) {
	}//end __construct()

	/**
	 * RSASSA-PKCS1-v1_5 with SHA-256.
	 *
	 * @var string
	 */
	public const RS256 = 'RS256';

	/**
	 * ECDSA on P-256 with SHA-256.
	 *
	 * @var string
	 */
	public const ES256 = 'ES256';

	/**
	 * The key signs new tokens and verifies.
	 *
	 * @var string
	 */
	public const STATUS_ACTIVE = 'active';

	/**
	 * The key no longer signs, but still verifies the tokens it signed.
	 *
	 * @var string
	 */
	public const STATUS_RETIRING = 'retiring';

	/**
	 * The key neither signs nor verifies.
	 *
	 * @var string
	 */
	public const STATUS_REVOKED = 'revoked';

	/**
	 * The smallest RSA modulus accepted, in bits.
	 *
	 * @var integer
	 */
	public const MIN_RSA_BITS = 2048;

	/**
	 * A new key pair for an algorithm, active.
	 *
	 * @param string      $algorithm RS256 or ES256.
	 * @param string|null $kid       The key id; a random one when null.
	 * @param int         $now       The moment of creation (unix seconds).
	 *
	 * @return array<string, mixed> The key record: kid, algorithm, status, publicKeyPem,
	 *                              privateKeyPem, publicKeyJwk, createdAt, retiredAt.
	 *
	 * @throws InvalidArgumentException When the algorithm is not supported.
	 * @throws RuntimeException         When OpenSSL cannot make the key.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-3
	 */
	public function generateKeyPair(string $algorithm, ?string $kid, int $now): array {
		$options = match ($algorithm) {
			self::RS256 => ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => self::MIN_RSA_BITS],
			self::ES256 => ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'],
			default => throw new InvalidArgumentException(message: sprintf('Unsupported signing algorithm "%s"; use RS256 or ES256.', $algorithm)),
		};

		$key = openssl_pkey_new($options);
		if ($key === false || openssl_pkey_export($key, $privatePem) === false) {
			throw new RuntimeException(message: 'The signing key pair could not be generated.');
		}

		$kid ??= strtolower($algorithm) . '-' . bin2hex(random_bytes(8));
		$details = openssl_pkey_get_details($key);

		return [
			'kid' => $kid,
			'algorithm' => $algorithm,
			'status' => self::STATUS_ACTIVE,
			'publicKeyPem' => (string)$details['key'],
			'privateKeyPem' => (string)$privatePem,
			'publicKeyJwk' => $this->jwk(details: $details, algorithm: $algorithm, kid: $kid),
			'createdAt' => gmdate('Y-m-d\TH:i:s\Z', $now),
			'retiredAt' => null,
		];
	}//end generateKeyPair()

	/**
	 * The key that signs new tokens: the newest active one.
	 *
	 * @param array<int, array<string, mixed>> $keys The known keys.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws RuntimeException When no key is active.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-3
	 */
	public function activeKey(array $keys): array {
		$active = null;
		foreach ($keys as $key) {
			if (($key['status'] ?? null) === self::STATUS_ACTIVE
				&& ($active === null || (string)($key['createdAt'] ?? '') >= (string)($active['createdAt'] ?? ''))
			) {
				$active = $key;
			}
		}

		if ($active === null) {
			throw new RuntimeException(message: 'There is no active signing key.');
		}

		return $active;
	}//end activeKey()

	/**
	 * Sign claims with the active key, naming it in the `kid` header.
	 *
	 * @param array<string, mixed>             $claims The claims.
	 * @param array<int, array<string, mixed>> $keys   The known keys.
	 *
	 * @return string The compact JWS.
	 *
	 * @throws RuntimeException When there is no active key or signing fails.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-3
	 */
	public function sign(array $claims, array $keys): string {
		$key = $this->activeKey(keys: $keys);
		$algorithm = (string)$key['algorithm'];
		$header = ['alg' => $algorithm, 'typ' => 'JWT', 'kid' => (string)$key['kid']];
		$input = $this->encode(data: $header) . '.' . $this->encode(data: $claims);

		$private = openssl_pkey_get_private((string)($key['privateKeyPem'] ?? ''));
		if ($private === false || openssl_sign($input, $signature, $private, OPENSSL_ALGO_SHA256) === false) {
			throw new RuntimeException(message: 'The token could not be signed.');
		}

		if ($algorithm === self::ES256) {
			$signature = $this->jose->derToRaw(der: $signature);
		}

		return $input . '.' . $this->jose->base64Url(bytes: $signature);
	}//end sign()

	/**
	 * The claims of a token whose signature verifies against the key its `kid` names, inside its validity period.
	 *
	 * The key must be active or retiring and must be of the algorithm the
	 * header names; `none` and a symmetric algorithm are never accepted.
	 *
	 * @param string                           $jwt  The compact JWS.
	 * @param array<int, array<string, mixed>> $keys The known keys.
	 * @param int                              $now  The current moment (unix seconds).
	 *
	 * @return array<string, mixed> The claims.
	 *
	 * @throws InvalidTokenException When the token is malformed, names no usable key, or its signature does not verify.
	 * @throws TokenExpiredException When `exp` has passed or `nbf` is still to come.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	public function verify(string $jwt, array $keys, int $now): array {
		$parts = explode('.', $jwt);
		if (count($parts) !== 3) {
			throw new InvalidTokenException(message: 'The token is not a signed JWT.');
		}

		$header = $this->decode(segment: $parts[0]);
		$claims = $this->decode(segment: $parts[1]);
		$key = $this->verifyingKey(header: $header, keys: $keys);

		$signature = $this->jose->base64UrlDecode(segment: $parts[2]);
		if ($key['algorithm'] === self::ES256) {
			$signature = $this->jose->rawToDer(raw: $signature);
		}

		$public = openssl_pkey_get_public((string)($key['publicKeyPem'] ?? ''));
		if ($public === false || openssl_verify($parts[0] . '.' . $parts[1], $signature, $public, OPENSSL_ALGO_SHA256) !== 1) {
			throw new InvalidTokenException(message: 'The token signature does not verify.');
		}

		$this->assertValidAt(claims: $claims, now: $now);

		return $claims;
	}//end verify()

	/**
	 * Rotate: every active key becomes retiring, and a new active key is added.
	 *
	 * Tokens signed by a retiring key keep verifying until they expire; new
	 * tokens are signed with the new key.
	 *
	 * @param array<int, array<string, mixed>> $keys      The known keys.
	 * @param string                           $algorithm The new key's algorithm.
	 * @param int                              $now       The moment of rotation (unix seconds).
	 *
	 * @return array<int, array<string, mixed>> The keys after rotation, the new one last.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-3
	 */
	public function rotate(array $keys, string $algorithm, int $now): array {
		$rotated = [];
		foreach ($keys as $key) {
			if (($key['status'] ?? null) === self::STATUS_ACTIVE) {
				$key['status'] = self::STATUS_RETIRING;
				$key['retiredAt'] = gmdate('Y-m-d\TH:i:s\Z', $now);
			}

			$rotated[] = $key;
		}

		$rotated[] = $this->generateKeyPair(algorithm: $algorithm, kid: null, now: $now);

		return $rotated;
	}//end rotate()

	/**
	 * The key a token header selects, refused unless it may verify.
	 *
	 * @param array<string, mixed>             $header The token header.
	 * @param array<int, array<string, mixed>> $keys   The known keys.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidTokenException When no usable key matches.
	 */
	private function verifyingKey(array $header, array $keys): array {
		$kid = $header['kid'] ?? null;
		$alg = $header['alg'] ?? null;
		if (is_string($kid) === false || in_array($alg, [self::RS256, self::ES256], true) === false) {
			throw new InvalidTokenException(message: 'The token names no supported key.');
		}

		foreach ($keys as $key) {
			if (($key['kid'] ?? null) === $kid
				&& ($key['algorithm'] ?? null) === $alg
				&& in_array($key['status'] ?? null, [self::STATUS_ACTIVE, self::STATUS_RETIRING], true) === true
			) {
				return $key;
			}
		}

		throw new InvalidTokenException(message: 'The token names no usable key.');
	}//end verifyingKey()

	/**
	 * Refuse a token outside its `nbf`..`exp` window; a token without `exp` does not expire.
	 *
	 * @param array<string, mixed> $claims The claims.
	 * @param int                  $now    The current moment (unix seconds).
	 *
	 * @return void
	 *
	 * @throws TokenExpiredException When outside the window.
	 */
	private function assertValidAt(array $claims, int $now): void {
		if (isset($claims['exp']) === true && is_numeric($claims['exp']) === true && (int)$claims['exp'] <= $now) {
			throw new TokenExpiredException(message: 'The token has expired.');
		}

		if (isset($claims['nbf']) === true && is_numeric($claims['nbf']) === true && (int)$claims['nbf'] > $now) {
			throw new TokenExpiredException(message: 'The token is not valid yet.');
		}
	}//end assertValidAt()

	/**
	 * The public JWK (RFC 7517) of a key.
	 *
	 * @param array<string, mixed> $details   openssl_pkey_get_details() of the key.
	 * @param string               $algorithm Its algorithm.
	 * @param string               $kid       Its key id.
	 *
	 * @return array<string, string>
	 */
	private function jwk(array $details, string $algorithm, string $kid): array {
		if ($algorithm === self::RS256) {
			return [
				'kty' => 'RSA',
				'use' => 'sig',
				'alg' => $algorithm,
				'kid' => $kid,
				'n' => $this->jose->base64Url(bytes: (string)$details['rsa']['n']),
				'e' => $this->jose->base64Url(bytes: (string)$details['rsa']['e']),
			];
		}

		return [
			'kty' => 'EC',
			'use' => 'sig',
			'alg' => $algorithm,
			'kid' => $kid,
			'crv' => 'P-256',
			'x' => $this->jose->base64Url(bytes: str_pad((string)$details['ec']['x'], JoseEncoding::ES256_PART, "\0", STR_PAD_LEFT)),
			'y' => $this->jose->base64Url(bytes: str_pad((string)$details['ec']['y'], JoseEncoding::ES256_PART, "\0", STR_PAD_LEFT)),
		];
	}//end jwk()

	/**
	 * A JSON segment, base64url-encoded.
	 *
	 * @param array<string, mixed> $data The data.
	 *
	 * @return string
	 */
	private function encode(array $data): string {
		return $this->jose->base64Url(bytes: json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
	}//end encode()

	/**
	 * A base64url-encoded JSON object segment, decoded.
	 *
	 * @param string $segment The segment.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidTokenException When it is not a JSON object.
	 */
	private function decode(string $segment): array {
		try {
			$data = json_decode($this->jose->base64UrlDecode(segment: $segment), true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new InvalidTokenException(message: 'The token is not a signed JWT.');
		}

		if (is_array($data) === false) {
			throw new InvalidTokenException(message: 'The token is not a signed JWT.');
		}

		return $data;
	}//end decode()
}//end class
