<?php

/**
 * EmbedTokenService unit tests (REQ-EMB-001, REQ-EMB-002, REQ-EMB-009).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-001--token-issuance-returns-jwt-exactly-once
 */

declare(strict_types=1);

namespace Unit\Service\Embed;

use OCA\LaunchPad\Entity\EmbedToken;
use OCA\LaunchPad\Entity\SigningKey;
use OCA\LaunchPad\Exception\InvalidTokenException;
use OCA\LaunchPad\Exception\TokenExpiredException;
use OCA\LaunchPad\Exception\TokenRevokedException;
use OCA\LaunchPad\Service\Embed\CspService;
use OCA\LaunchPad\Service\Embed\EmbedObjectStore;
use OCA\LaunchPad\Service\Embed\EmbedTokenService;
use OCA\LaunchPad\Service\Embed\JoseEncoding;
use OCA\LaunchPad\Service\Embed\JwtSigningService;
use OCA\LaunchPad\Service\Embed\RevocationCache;
use OCA\LaunchPad\Service\Embed\SigningKeyService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Security\ICrypto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Unit\Service\Embed\Support\InMemoryObjectService;

#[CoversClass(EmbedTokenService::class)]
#[CoversClass(SigningKeyService::class)]
#[CoversClass(RevocationCache::class)]
#[CoversClass(EmbedToken::class)]
#[CoversClass(SigningKey::class)]
#[UsesClass(EmbedObjectStore::class)]
#[UsesClass(JwtSigningService::class)]
#[UsesClass(JoseEncoding::class)]
#[UsesClass(CspService::class)]
class EmbedTokenServiceTest extends TestCase {

	private const INPUT = [
		'name'            => 'Gemeente Zeist WOO',
		'description'     => 'Public WOO requests widget',
		'subject'         => ['type' => 'widget', 'id' => 'woo-widget-uuid'],
		'scope'           => ['mode' => 'read', 'allowedFilters' => ['status'], 'allowedActions' => []],
		'hostOrigins'     => ['https://intranet.zeist.nl'],
		'rateLimitPolicy' => ['requestsPerMinute' => 600, 'burstSize' => 60],
	];

	private InMemoryObjectService $objects;

	private int $now = 1779460200;

	/**
	 * @var array<string, mixed>
	 */
	private array $cache = [];

	/**
	 * @var list<INotification>
	 */
	private array $sent = [];

	/**
	 * The service over the real signing chain and an in-memory register.
	 *
	 * @return EmbedTokenService
	 */
	private function service(): EmbedTokenService {
		$this->objects ??= new InMemoryObjectService();
		$container       = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($this->objects);
		$store = new EmbedObjectStore($container);

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn (): int => $this->now);

		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(static fn (string $plain): string => 'enc:' . base64_encode($plain));
		$crypto->method('decrypt')->willReturnCallback(static fn (string $cipher): string => (string)base64_decode(substr($cipher, 4)));

		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturnCallback(fn (string $key): mixed => $this->cache[$key] ?? null);
		$cache->method('set')->willReturnCallback(function (string $key, mixed $value, int $ttl): bool {
			$this->assertSame(60, $ttl);
			$this->cache[$key] = $value;
			return true;
		});
		$cache->method('remove')->willReturnCallback(function (string $key): bool {
			unset($this->cache[$key]);
			return true;
		});
		$factory = $this->createMock(ICacheFactory::class);
		$factory->method('createDistributed')->with('launchpad-embed-revocation')->willReturn($cache);

		$notifications = $this->createMock(IManager::class);
		$notifications->method('createNotification')->willReturnCallback(function (): INotification {
			$notification = $this->createMock(INotification::class);
			$record = new \ArrayObject();
			foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject'] as $method) {
				$notification->method($method)->willReturnCallback(function (...$args) use ($notification, $record, $method): INotification {
					$record[$method] = $args;
					return $notification;
				});
			}

			$notification->method('getSubjectParameters')->willReturnCallback(static fn (): array => $record['setSubject'][1] ?? []);
			$notification->method('getUser')->willReturnCallback(static fn (): string => $record['setUser'][0] ?? '');
			$notification->method('getSubject')->willReturnCallback(static fn (): string => $record['setSubject'][0] ?? '');
			return $notification;
		});
		$notifications->method('notify')->willReturnCallback(function (INotification $notification): void {
			$this->sent[] = $notification;
		});

		$jwt = new JwtSigningService(time: $time);

		return new EmbedTokenService(
			$store,
			new SigningKeyService($store, $jwt, $crypto),
			$jwt,
			new CspService(),
			new RevocationCache($factory),
			$time,
			$notifications,
			$this->createMock(LoggerInterface::class)
		);
	}//end service()

	/**
	 * A create returns the JWT once and stores only its hash; the first create
	 * also stores an active RS256 key with its private half encrypted.
	 *
	 * @return void
	 */
	public function testCreateReturnsTheJwtOnceAndStoresItsHash(): void {
		$created = $this->service()->createToken(input: self::INPUT, userId: 'admin');

		$this->assertSame(['id', 'name', 'jwt', 'warning', 'createdAt'], array_keys($created));
		$this->assertSame('Gemeente Zeist WOO', $created['name']);
		$this->assertSame(EmbedTokenService::WARNING, $created['warning']);
		$this->assertSame('2026-05-22T14:30:00Z', $created['createdAt']);

		$stored = $this->objects->objects['launchpad/embed_token'][$created['id']];
		$this->assertSame(hash('sha256', $created['jwt']), $stored['tokenHash']);
		$this->assertSame('admin', $stored['createdBy']);
		$this->assertSame(self::INPUT['hostOrigins'], $stored['hostOrigins']);

		$keys = array_values($this->objects->objects['launchpad/signing_key']);
		$this->assertCount(1, $keys);
		$this->assertSame(['RS256', 'active'], [$keys[0]['algorithm'], $keys[0]['status']]);
		$this->assertSame($keys[0]['kid'], $stored['keyId']);
		$this->assertStringStartsWith('enc:', $keys[0]['privateKeyEncrypted']);
		$this->assertStringNotContainsString('PRIVATE KEY', $keys[0]['privateKeyEncrypted']);
		$this->assertArrayNotHasKey('privateKeyPem', $keys[0]);

		$header = json_decode((string)base64_decode(strtr(explode('.', $created['jwt'])[0], '-_', '+/')), true);
		$this->assertSame(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $keys[0]['kid']], $header);
	}//end testCreateReturnsTheJwtOnceAndStoresItsHash()

	/**
	 * Metadata never carries the JWT or the hash, and lists the actions.
	 *
	 * @return void
	 */
	public function testMetadataHasNoJwtAndNoHash(): void {
		$service = $this->service();
		$id      = $service->createToken(input: self::INPUT, userId: 'admin')['id'];
		$token   = $service->getToken(id: $id);

		$this->assertArrayNotHasKey('jwt', $token);
		$this->assertArrayNotHasKey('tokenHash', $token);
		$this->assertSame($id, $token['id']);
		$this->assertSame(['type' => 'widget', 'id' => 'woo-widget-uuid'], $token['subject']);
		$this->assertSame(['re-issue', 'revoke'], array_column($token['actions'], 'id'));
		$this->assertNull($service->getToken(id: 'nope'));
		$this->assertSame([$id], array_column($service->listTokens(), 'id'));
	}//end testMetadataHasNoJwtAndNoHash()

	/**
	 * A malformed origin is refused naming its index, and nothing is stored.
	 *
	 * @return void
	 */
	public function testAMalformedOriginIsRefusedAndNothingIsStored(): void {
		$input = ['hostOrigins' => ['https://www.example.com', 'invalid-origin-missing-scheme']] + self::INPUT;
		$this->assertSame(
			[
				'status'      => 'error',
				'error'       => 'invalid_hostOrigins',
				'message'     => 'Invalid origin format at index 1: missing URI scheme. Expected format: https://domain.com',
				'fieldErrors' => ['hostOrigins[1]' => 'invalid_hostOrigins'],
			],
			$this->service()->createToken(input: $input, userId: 'admin')
		);
		$this->assertSame([], $this->objects->objects);
	}//end testAMalformedOriginIsRefusedAndNothingIsStored()

	/**
	 * Every other bad field is refused with its own code.
	 *
	 * @return void
	 */
	public function testEveryBadFieldIsRefused(): void {
		$cases = [
			'invalid_name'            => ['name' => '  '],
			'invalid_subject'         => ['subject' => ['type' => 'page', 'id' => 'x']],
			'invalid_scope'           => ['scope' => ['mode' => 'read', 'allowedActions' => ['delete']]],
			'invalid_rateLimitPolicy' => ['rateLimitPolicy' => ['requestsPerMinute' => 0, 'burstSize' => 5]],
			'invalid_tokenExpiresAt'  => ['tokenExpiresAt' => '2026-01-01T00:00:00Z'],
		];
		foreach ($cases as $error => $change) {
			$this->assertSame($error, $this->service()->createToken(input: $change + self::INPUT, userId: 'admin')['error'] ?? null, $error);
		}

		$wildcard = $this->service()->createToken(input: ['hostOrigins' => ['*']] + self::INPUT, userId: 'admin');
		$this->assertSame(['invalid_hostOrigins', ['hostOrigins[0]' => 'invalid_hostOrigins']], [$wildcard['error'], $wildcard['fieldErrors']]);
		$this->assertStringStartsWith('Wildcard origin', $wildcard['message']);
		$this->assertSame([], $this->objects->objects);
	}//end testEveryBadFieldIsRefused()

	/**
	 * The issued JWT validates to its token; a tampered one, or one whose row
	 * is gone, does not.
	 *
	 * @return void
	 */
	public function testValidationNeedsTheSignatureAndTheStoredHash(): void {
		$service = $this->service();
		$created = $service->createToken(input: self::INPUT, userId: 'admin');

		$token = $service->validateToken(jwt: $created['jwt']);
		$this->assertSame($created['id'], $token->id);
		$this->assertTrue($token->grants(type: 'widget', id: 'woo-widget-uuid'));
		$this->assertFalse($token->grants(type: 'widget', id: 'other'));

		$parts    = explode('.', $created['jwt']);
		$parts[1] = rtrim(strtr(base64_encode((string)json_encode(['iss' => 'launchpad', 'jti' => $created['id'], 'scope' => ['mode' => 'read-with-interactions']])), '+/', '-_'), '=');
		try {
			$service->validateToken(jwt: implode('.', $parts));
			$this->fail('a tampered token validated');
		} catch (InvalidTokenException) {
			$this->addToAssertionCount(1);
		}

		unset($this->objects->objects['launchpad/embed_token'][$created['id']]);
		$this->expectException(InvalidTokenException::class);
		$service->validateToken(jwt: $created['jwt']);
	}//end testValidationNeedsTheSignatureAndTheStoredHash()

	/**
	 * Revocation is refused at once (through the cache), tells the creator,
	 * and un-revoking accepts the token again.
	 *
	 * @return void
	 */
	public function testRevocationTakesEffectAndCanBeUndone(): void {
		$service = $this->service();
		$created = $service->createToken(input: self::INPUT, userId: 'creator');
		$service->validateToken(jwt: $created['jwt']);

		$revoked = $service->revokeToken(id: $created['id'], reason: 'no_longer_needed', notes: 'In-house now', userId: 'admin');
		$this->assertSame(['2026-05-22T14:30:00Z', 'no_longer_needed', 'In-house now', 'admin'], [$revoked['revokedAt'], $revoked['revocationReason'], $revoked['revocationNotes'], $revoked['revokedBy']]);
		$this->assertSame(['re-issue', 'unrevoke', 'purge-cache'], array_column($revoked['actions'], 'id'));

		$this->assertCount(1, $this->sent);
		$this->assertSame('creator', $this->sent[0]->getUser());
		$this->assertSame('embed_token_revoked', $this->sent[0]->getSubject());
		$this->assertSame(['Gemeente Zeist WOO', 'admin', 'no_longer_needed', 'In-house now', '2026-05-22T14:30:00Z'], $this->sent[0]->getSubjectParameters());

		try {
			$service->validateToken(jwt: $created['jwt']);
			$this->fail('a revoked token validated');
		} catch (TokenRevokedException) {
			$this->addToAssertionCount(1);
		}

		$this->assertNull($service->unrevokeToken(id: 'nope'));
		$this->assertNull($service->unrevokeToken(id: $created['id'])['revokedAt']);
		$this->assertSame($created['id'], $service->validateToken(jwt: $created['jwt'])->id);
	}//end testRevocationTakesEffectAndCanBeUndone()

	/**
	 * A revocation written straight to the store is picked up once the cached
	 * answer is purged; until then the 60-second answer holds.
	 *
	 * @return void
	 */
	public function testThePurgeMakesEveryWorkerReadTheStore(): void {
		$service = $this->service();
		$created = $service->createToken(input: self::INPUT, userId: 'admin');
		$service->validateToken(jwt: $created['jwt']);

		$this->objects->objects['launchpad/embed_token'][$created['id']]['revokedAt'] = '2026-05-22T14:31:00Z';
		$this->assertSame($created['id'], $service->validateToken(jwt: $created['jwt'])->id);

		$this->assertSame('2026-05-22T14:30:00Z', $service->purgeCache(id: $created['id']));
		$this->assertNull($service->purgeCache(id: 'nope'));
		$this->expectException(TokenRevokedException::class);
		$service->validateToken(jwt: $created['jwt']);
	}//end testThePurgeMakesEveryWorkerReadTheStore()

	/**
	 * An expiring token stops validating once its expiry passes.
	 *
	 * @return void
	 */
	public function testAnExpiredTokenIsRefused(): void {
		$service = $this->service();
		$created = $service->createToken(input: ['tokenExpiresAt' => '2026-12-31T23:59:59Z'] + self::INPUT, userId: 'admin');
		$this->assertSame($created['id'], $service->validateToken(jwt: $created['jwt'])->id);

		$this->now = (int)strtotime('2027-01-01T00:00:00Z');
		$this->expectException(TokenExpiredException::class);
		$service->validateToken(jwt: $created['jwt']);
	}//end testAnExpiredTokenIsRefused()

	/**
	 * Re-issue revokes the old token as re-issued and issues a like one.
	 *
	 * @return void
	 */
	public function testReissueRevokesTheOldAndIssuesALikeToken(): void {
		$service = $this->service();
		$old     = $service->createToken(input: self::INPUT, userId: 'creator');
		$new     = $service->reissueToken(id: $old['id'], userId: 'admin');

		$this->assertNotSame($old['id'], $new['id']);
		$this->assertNotSame($old['jwt'], $new['jwt']);
		$stored = $this->objects->objects['launchpad/embed_token'];
		$this->assertSame('re-issued', $stored[$old['id']]['revocationReason']);
		$this->assertSame([self::INPUT['subject'], 'admin', null], [$stored[$new['id']]['subject'], $stored[$new['id']]['createdBy'], $stored[$new['id']]['revokedAt']]);
		$this->assertSame($new['id'], $service->validateToken(jwt: $new['jwt'])->id);
		$this->assertNull($service->reissueToken(id: 'nope', userId: 'admin'));

		$this->expectException(TokenRevokedException::class);
		$service->validateToken(jwt: $old['jwt']);
	}//end testReissueRevokesTheOldAndIssuesALikeToken()

	/**
	 * Name, description and rate limit change; subject, scope and origins do
	 * not, so the issued JWT keeps validating.
	 *
	 * @return void
	 */
	public function testAnUpdateChangesOnlyTheMutableFields(): void {
		$service = $this->service();
		$created = $service->createToken(input: self::INPUT, userId: 'admin');
		$this->now += 60;

		$updated = $service->updateToken(
			id: $created['id'],
			input: [
				'name'            => 'Zeist WOO (public)',
				'rateLimitPolicy' => ['requestsPerMinute' => 120, 'burstSize' => 10],
				'scope'           => ['mode' => 'read-with-interactions'],
				'hostOrigins'     => ['https://evil.example'],
			]
		);

		$this->assertSame('Zeist WOO (public)', $updated['name']);
		$this->assertSame('Public WOO requests widget', $updated['description']);
		$this->assertSame(['requestsPerMinute' => 120, 'burstSize' => 10], $updated['rateLimitPolicy']);
		$this->assertSame(self::INPUT['scope'], $updated['scope']);
		$this->assertSame(self::INPUT['hostOrigins'], $updated['hostOrigins']);
		$this->assertSame('2026-05-22T14:31:00Z', $updated['updatedAt']);
		$this->assertSame($created['id'], $service->validateToken(jwt: $created['jwt'])->id);

		$this->assertSame('invalid_name', $service->updateToken(id: $created['id'], input: ['name' => ''])['error']);
		$this->assertSame('invalid_rateLimitPolicy', $service->updateToken(id: $created['id'], input: ['rateLimitPolicy' => ['burstSize' => 1]])['error']);
		$this->assertNull($service->updateToken(id: 'nope', input: []));
	}//end testAnUpdateChangesOnlyTheMutableFields()

	/**
	 * An unknown reason is refused; an unknown token is null.
	 *
	 * @return void
	 */
	public function testRevocationNeedsAKnownReasonAndToken(): void {
		$service = $this->service();
		$this->assertSame('invalid_reason', $service->revokeToken(id: 'x', reason: 'bored', notes: null, userId: 'a')['error']);
		$this->assertNull($service->revokeToken(id: 'x', reason: 'compromised', notes: null, userId: 'a'));
		$this->assertSame([], $this->sent);
	}//end testRevocationNeedsAKnownReasonAndToken()
}//end class
