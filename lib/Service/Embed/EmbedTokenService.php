<?php

/**
 * EmbedTokenService
 *
 * The lifecycle of embed tokens (REQ-EMB-001, REQ-EMB-002, REQ-EMB-009):
 * issue a signed JWT once and keep only its hash, show metadata without it,
 * validate a presented JWT (signature, expiry, the stored hash, revocation),
 * revoke and un-revoke, re-issue. Input is validated before anything is
 * stored, so a refused create leaves no row.
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

use DateTime;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Entity\EmbedToken;
use OCA\LaunchPad\Exception\InvalidTokenException;
use OCA\LaunchPad\Exception\TokenExpiredException;
use OCA\LaunchPad\Exception\TokenRevokedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Issues, shows, validates and revokes embed tokens.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-001--token-issuance-returns-jwt-exactly-once
 */
class EmbedTokenService {

	/**
	 * The one-time warning that accompanies a new JWT.
	 *
	 * @var string
	 */
	public const WARNING = 'keepThisSafe: This JWT is shown only once. Save it now. If lost, you must re-issue the token.';

	/**
	 * The issuer claim of every embed token.
	 *
	 * @var string
	 */
	public const ISSUER = 'launchpad';

	/**
	 * The notification subject sent to a revoked token's creator.
	 *
	 * @var string
	 */
	public const REVOKED_SUBJECT = 'embed_token_revoked';

	/**
	 * Constructor.
	 *
	 * @param EmbedObjectStore  $store         The register objects.
	 * @param SigningKeyService $keys          The signing keys.
	 * @param JwtSigningService $jwt           Signs and verifies.
	 * @param CspService        $csp           Validates host origins.
	 * @param RevocationCache   $revocations   The 60-second revocation cache.
	 * @param ITimeFactory      $time          The clock.
	 * @param IManager          $notifications Tells a creator their token was revoked.
	 * @param LoggerInterface   $logger        Logs a notification that could not be sent.
	 */
	public function __construct(
		private readonly EmbedObjectStore $store,
		private readonly SigningKeyService $keys,
		private readonly JwtSigningService $jwt,
		private readonly CspService $csp,
		private readonly RevocationCache $revocations,
		private readonly ITimeFactory $time,
		private readonly IManager $notifications,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Issue a token: validate, store, sign, store the hash, return the JWT once.
	 *
	 * @param array<string, mixed> $input  The create payload.
	 * @param string               $userId The issuing admin.
	 *
	 * @return array<string, mixed> {id, name, jwt, warning, createdAt}, or a refusal
	 *                              {status: error, error, message, fieldErrors}.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-001--token-issuance-returns-jwt-exactly-once
	 */
	public function createToken(array $input, string $userId): array {
		$refusal = $this->refusalFor(input: $input);
		if ($refusal !== null) {
			return $refusal;
		}

		$now   = $this->nowIso();
		$token = EmbedToken::fromObject(
			object: [
				'name'            => trim((string)$input['name']),
				'description'     => (string)($input['description'] ?? ''),
				'subject'         => self::subjectOf(subject: $input['subject']),
				'scope'           => self::scopeOf(scope: $input['scope'] ?? []),
				'hostOrigins'     => array_values($input['hostOrigins']),
				'tenantThemeId'   => $input['tenantThemeId'] ?? null,
				'tokenExpiresAt'  => $input['tokenExpiresAt'] ?? null,
				'rateLimitPolicy' => $input['rateLimitPolicy'] ?? null,
				'createdBy'       => $userId,
				'createdAt'       => $now,
			]
		);

		return $this->issue(token: $token);
	}//end createToken()

	/**
	 * A token's metadata (no JWT, no hash) with its actions, or null.
	 *
	 * @param string $id The token id.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-001--token-issuance-returns-jwt-exactly-once
	 */
	public function getToken(string $id): ?array {
		$token = $this->load(id: $id);
		if ($token === null) {
			return null;
		}

		return $token->metadata() + ['actions' => self::actionsOf(token: $token)];
	}//end getToken()

	/**
	 * Every token's metadata, newest first.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-4
	 */
	public function listTokens(): array {
		$tokens = array_map(
			static fn (array $object): array => EmbedToken::fromObject(object: $object)->metadata(),
			$this->store->findBy(schema: EmbedObjectStore::TOKENS)
		);
		usort($tokens, static fn (array $a, array $b): int => strcmp((string)$b['createdAt'], (string)$a['createdAt']));

		return $tokens;
	}//end listTokens()

	/**
	 * Update what may change on a token: name, description, rate limit.
	 * Subject, scope and host origins are signed into the JWT and never change;
	 * any other field in the input is ignored.
	 *
	 * @param string               $id    The token id.
	 * @param array<string, mixed> $input {name?, description?, rateLimitPolicy?}.
	 *
	 * @return array<string, mixed>|null The updated metadata, a refusal, or null when there is no such token.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-32
	 */
	public function updateToken(string $id, array $input): ?array {
		$token = $this->load(id: $id);
		if ($token === null) {
			return null;
		}

		$object = $token->toObject();
		if (array_key_exists('name', $input) === true) {
			if (trim((string)$input['name']) === '') {
				return self::refusal(error: 'invalid_name', message: 'Give the token a name.', field: 'name');
			}

			$object['name'] = trim((string)$input['name']);
		}

		if (array_key_exists('description', $input) === true) {
			$object['description'] = (string)$input['description'];
		}

		if (array_key_exists('rateLimitPolicy', $input) === true) {
			$refusal = self::policyRefusal(policy: $input['rateLimitPolicy']);
			if ($refusal !== null) {
				return $refusal;
			}

			$object['rateLimitPolicy'] = $input['rateLimitPolicy'];
		}

		$object['updatedAt'] = $this->nowIso();
		$updated = EmbedToken::fromObject(object: $this->store->save(schema: EmbedObjectStore::TOKENS, object: $object));

		return $updated->metadata() + ['actions' => self::actionsOf(token: $updated)];
	}//end updateToken()

	/**
	 * The stored token a presented JWT stands for, when it holds.
	 *
	 * @param string $jwt The presented JWT.
	 *
	 * @return EmbedToken
	 *
	 * @throws InvalidTokenException  When the signature, the claims or the stored hash do not match.
	 * @throws TokenExpiredException  When the token has expired.
	 * @throws TokenRevokedException  When the token is revoked.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	public function validateToken(string $jwt): EmbedToken {
		$claims = $this->jwt->verify(jwt: $jwt, keys: $this->keys->keys());
		if (($claims['iss'] ?? null) !== self::ISSUER || is_string($claims['jti'] ?? null) === false) {
			throw new InvalidTokenException(message: 'The token was not issued by LaunchPad.');
		}

		$token = $this->load(id: $claims['jti']);
		// The hash comparison runs whether or not the row exists, so a missing
		// row and a wrong token cost the same.
		$stored = ($token->tokenHash ?? str_repeat('0', 64));
		if (hash_equals($stored, hash('sha256', $jwt)) === false || $token === null) {
			throw new InvalidTokenException(message: 'The token is not a current LaunchPad token.');
		}

		if ($this->revocations->isRevoked(tokenId: $token->id, lookup: static fn (): bool => $token->isRevoked()) === true) {
			throw new TokenRevokedException(message: 'The token has been revoked.');
		}

		if ($token->isExpiredAt(now: $this->time->getTime()) === true) {
			throw new TokenExpiredException(message: 'The token has expired.');
		}

		return $token;
	}//end validateToken()

	/**
	 * Revoke a token and tell its creator.
	 *
	 * @param string      $id     The token id.
	 * @param string      $reason One of EmbedToken::REASONS.
	 * @param string|null $notes  Free text.
	 * @param string      $userId The revoking admin.
	 *
	 * @return array<string, mixed>|null The updated metadata, a refusal for a bad reason, null when there is no such token.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-009--token-revocation-takes-effect-within-60-seconds
	 */
	public function revokeToken(string $id, string $reason, ?string $notes, string $userId): ?array {
		if (in_array($reason, EmbedToken::REASONS, true) === false) {
			return self::refusal(error: 'invalid_reason', message: 'Choose a revocation reason: ' . implode(', ', EmbedToken::REASONS) . '.', field: 'reason');
		}

		$token = $this->load(id: $id);
		if ($token === null) {
			return null;
		}

		$revoked = $token->revoked(at: $this->nowIso(), reason: $reason, notes: $notes, by: $userId);
		$this->store->save(schema: EmbedObjectStore::TOKENS, object: $revoked->toObject());
		$this->revocations->markRevoked(tokenId: $token->id);
		$this->notifyCreator(token: $revoked);

		return $revoked->metadata() + ['actions' => self::actionsOf(token: $revoked)];
	}//end revokeToken()

	/**
	 * Clear a token's revocation.
	 *
	 * @param string $id The token id.
	 *
	 * @return array<string, mixed>|null The updated metadata, null when there is no such token.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-009--token-revocation-takes-effect-within-60-seconds
	 */
	public function unrevokeToken(string $id): ?array {
		$token = $this->load(id: $id);
		if ($token === null) {
			return null;
		}

		$restored = $token->unrevoked(at: $this->nowIso());
		$this->store->save(schema: EmbedObjectStore::TOKENS, object: $restored->toObject());
		$this->revocations->purge(tokenId: $token->id);

		return $restored->metadata() + ['actions' => self::actionsOf(token: $restored)];
	}//end unrevokeToken()

	/**
	 * Re-issue: revoke the token as `re-issued` and issue a new one like it.
	 *
	 * @param string $id     The token id.
	 * @param string $userId The admin.
	 *
	 * @return array<string, mixed>|null The new token's create response, null when there is no such token.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-001--token-issuance-returns-jwt-exactly-once
	 */
	public function reissueToken(string $id, string $userId): ?array {
		$token = $this->load(id: $id);
		if ($token === null) {
			return null;
		}

		$now = $this->nowIso();
		if ($token->isRevoked() === false) {
			$this->store->save(schema: EmbedObjectStore::TOKENS, object: $token->revoked(at: $now, reason: 're-issued', notes: null, by: $userId)->toObject());
			$this->revocations->markRevoked(tokenId: $token->id);
		}

		$fresh = $token->toObject();
		foreach (['id', 'tokenHash', 'keyId', 'revokedAt', 'revocationReason', 'revocationNotes', 'revokedBy', 'updatedAt'] as $field) {
			unset($fresh[$field]);
		}

		return $this->issue(token: EmbedToken::fromObject(object: ['createdBy' => $userId, 'createdAt' => $now] + $fresh));
	}//end reissueToken()

	/**
	 * Drop a token's cached revocation answer, so every worker reads the store.
	 *
	 * @param string $id The token id.
	 *
	 * @return string|null The purge time, null when there is no such token.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-009--token-revocation-takes-effect-within-60-seconds
	 */
	public function purgeCache(string $id): ?string {
		if ($this->load(id: $id) === null) {
			return null;
		}

		$this->revocations->purge(tokenId: $id);

		return $this->nowIso();
	}//end purgeCache()

	/**
	 * Store a token, sign its JWT under its id, store the hash.
	 *
	 * @param EmbedToken $token The token, not stored yet.
	 *
	 * @return array<string, mixed> {id, name, jwt, warning, createdAt}.
	 */
	private function issue(EmbedToken $token): array {
		$keys   = $this->keys->keysWithActive();
		$active = $this->jwt->activeKey(keys: $keys);
		$stored = EmbedToken::fromObject(object: $this->store->save(schema: EmbedObjectStore::TOKENS, object: $token->toObject()));

		$claims = [
			'iss'         => self::ISSUER,
			'jti'         => $stored->id,
			'sub'         => (string)($stored->subject['type'] ?? '') . ':' . (string)($stored->subject['id'] ?? ''),
			'scope'       => $stored->scope,
			'hostOrigins' => $stored->hostOrigins,
			'iat'         => $this->time->getTime(),
		];
		if ($stored->tokenExpiresAt !== null) {
			$claims['exp'] = (int)strtotime($stored->tokenExpiresAt);
		}

		$jwt = $this->jwt->sign(claims: $claims, keys: $keys);
		$this->store->save(
			schema: EmbedObjectStore::TOKENS,
			object: ['tokenHash' => hash('sha256', $jwt), 'keyId' => (string)$active['kid']] + $stored->toObject()
		);

		return [
			'id'        => $stored->id,
			'name'      => $stored->name,
			'jwt'       => $jwt,
			'warning'   => self::WARNING,
			'createdAt' => $stored->createdAt,
		];
	}//end issue()

	/**
	 * Why a create payload is refused, or null when it is acceptable.
	 *
	 * @param array<string, mixed> $input The create payload.
	 *
	 * @return array<string, mixed>|null
	 */
	private function refusalFor(array $input): ?array {
		if (trim((string)($input['name'] ?? '')) === '') {
			return self::refusal(error: 'invalid_name', message: 'Give the token a name.', field: 'name');
		}

		$subject = ($input['subject'] ?? null);
		if (is_array($subject) === false || in_array($subject['type'] ?? null, ['widget', 'dashboard'], true) === false
			|| is_string($subject['id'] ?? null) === false || $subject['id'] === ''
		) {
			return self::refusal(error: 'invalid_subject', message: 'The subject is a widget or a dashboard with an id.', field: 'subject');
		}

		$scopeRefusal = self::scopeRefusal(scope: $input['scope'] ?? []);
		if ($scopeRefusal !== null) {
			return $scopeRefusal;
		}

		$originRefusal = $this->originRefusal(origins: $input['hostOrigins'] ?? null);
		if ($originRefusal !== null) {
			return $originRefusal;
		}

		return self::policyRefusal(policy: $input['rateLimitPolicy'] ?? null)
			?? self::expiryRefusal(expiresAt: $input['tokenExpiresAt'] ?? null, now: $this->time->getTime());
	}//end refusalFor()

	/**
	 * Why a scope is refused, or null.
	 *
	 * @param mixed $scope The scope.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function scopeRefusal(mixed $scope): ?array {
		if (is_array($scope) === false
			|| in_array($scope['mode'] ?? EmbedToken::MODE_READ, [EmbedToken::MODE_READ, EmbedToken::MODE_INTERACT], true) === false
		) {
			return self::refusal(error: 'invalid_scope', message: 'The scope mode is read or read-with-interactions.', field: 'scope.mode');
		}

		foreach ((array)($scope['allowedActions'] ?? []) as $action) {
			if (in_array($action, EmbedToken::ACTIONS, true) === false) {
				return self::refusal(
					error: 'invalid_scope',
					message: 'Allowed actions are ' . implode(', ', EmbedToken::ACTIONS) . '.',
					field: 'scope.allowedActions'
				);
			}
		}

		return null;
	}//end scopeRefusal()

	/**
	 * Why the host origins are refused, naming the first bad one, or null.
	 *
	 * @param mixed $origins The host origins.
	 *
	 * @return array<string, mixed>|null
	 */
	private function originRefusal(mixed $origins): ?array {
		$verdict = $this->csp->validateOrigins(origins: $origins);
		if ($verdict === null) {
			return null;
		}

		$index = 0;
		$listed = [];
		if (is_array($origins) === true) {
			$listed = array_values($origins);
		}

		foreach ($listed as $index => $origin) {
			if (is_string($origin) === false || str_contains($origin, '*') === true || $this->csp->validateOriginFormat(origin: $origin) === false) {
				break;
			}
		}

		$message = (string)($verdict['message'] ?? 'Invalid host origins.');
		if ($listed !== []) {
			$problem = 'not an origin';
			if (is_string($listed[$index] ?? null) === true && str_contains((string)$listed[$index], '://') === false) {
				$problem = 'missing URI scheme';
			}

			$message = 'Invalid origin format at index ' . $index . ': ' . $problem . '. Expected format: https://domain.com';
			if (($verdict['details']['hostOrigins'] ?? '') === 'wildcard_not_permitted') {
				$message = (string)$verdict['message'];
			}
		}

		return self::refusal(error: 'invalid_hostOrigins', message: $message, field: 'hostOrigins[' . $index . ']');
	}//end originRefusal()

	/**
	 * Why a rate-limit policy is refused, or null (null policy = the default).
	 *
	 * @param mixed $policy The policy.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function policyRefusal(mixed $policy): ?array {
		if ($policy === null) {
			return null;
		}

		$valid = is_array($policy) === true
			&& is_int($policy['requestsPerMinute'] ?? null) === true && $policy['requestsPerMinute'] >= 1
			&& is_int($policy['burstSize'] ?? null) === true && $policy['burstSize'] >= 1;

		if ($valid === true) {
			return null;
		}

		return self::refusal(
			error: 'invalid_rateLimitPolicy',
			message: 'requestsPerMinute and burstSize are whole numbers of at least 1.',
			field: 'rateLimitPolicy'
		);
	}//end policyRefusal()

	/**
	 * Why an expiry is refused, or null (null = never expires).
	 *
	 * @param mixed $expiresAt The expiry.
	 * @param int   $now       Unix time.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function expiryRefusal(mixed $expiresAt, int $now): ?array {
		if ($expiresAt === null) {
			return null;
		}

		$time = false;
		if (is_string($expiresAt) === true) {
			$time = strtotime($expiresAt);
		}

		if ($time === false || $time <= $now) {
			return self::refusal(
				error: 'invalid_tokenExpiresAt',
				message: 'The expiry is a date and time in the future, or empty for no expiry.',
				field: 'tokenExpiresAt'
			);
		}

		return null;
	}//end expiryRefusal()

	/**
	 * The stored subject: type and id, and the filter context when one is given.
	 *
	 * @param array<string, mixed> $subject The validated subject.
	 *
	 * @return array<string, mixed>
	 */
	private static function subjectOf(array $subject): array {
		$stored = ['type' => $subject['type'], 'id' => $subject['id']];
		if (is_array($subject['filterContext'] ?? null) === true) {
			$stored['filterContext'] = $subject['filterContext'];
		}

		return $stored;
	}//end subjectOf()

	/**
	 * The stored scope, with its defaults filled in.
	 *
	 * @param array<string, mixed> $scope The validated scope.
	 *
	 * @return array<string, mixed>
	 */
	private static function scopeOf(array $scope): array {
		return [
			'mode'           => (string)($scope['mode'] ?? EmbedToken::MODE_READ),
			'allowedFilters' => array_values(array_filter((array)($scope['allowedFilters'] ?? []), is_string(...))),
			'allowedActions' => array_values((array)($scope['allowedActions'] ?? [])),
		];
	}//end scopeOf()

	/**
	 * The actions an admin can take on a token.
	 *
	 * @param EmbedToken $token The token.
	 *
	 * @return list<array<string, string>>
	 */
	private static function actionsOf(EmbedToken $token): array {
		$actions = [['id' => 're-issue', 'label' => 'Re-issue token', 'description' => 'Revoke this token and generate a new one']];
		if ($token->isRevoked() === true) {
			$actions[] = ['id' => 'unrevoke', 'label' => 'Un-revoke token', 'description' => 'Accept this token again'];
			$actions[] = ['id' => 'purge-cache', 'label' => 'Purge cache', 'description' => 'Make every render worker re-read this token now'];

			return $actions;
		}

		$actions[] = ['id' => 'revoke', 'label' => 'Revoke token', 'description' => 'Stop this token from rendering'];

		return $actions;
	}//end actionsOf()

	/**
	 * A stored token, or null.
	 *
	 * @param string $id The token id.
	 *
	 * @return EmbedToken|null
	 */
	private function load(string $id): ?EmbedToken {
		$object = $this->store->find(schema: EmbedObjectStore::TOKENS, id: $id);

		if ($object === null) {
			return null;
		}

		return EmbedToken::fromObject(object: $object);
	}//end load()

	/**
	 * Tell the token's creator it was revoked; a failure is logged, never thrown.
	 *
	 * @param EmbedToken $token The revoked token.
	 *
	 * @return void
	 */
	private function notifyCreator(EmbedToken $token): void {
		if ($token->createdBy === '') {
			return;
		}

		try {
			$notification = $this->notifications->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($token->createdBy)
				->setDateTime(new DateTime((string)$token->revokedAt))
				->setObject('embed_token', $token->id)
				->setSubject(
					self::REVOKED_SUBJECT,
					[$token->name, (string)$token->revokedBy, (string)$token->revocationReason, (string)$token->revocationNotes, (string)$token->revokedAt]
				);
			$this->notifications->notify($notification);
		} catch (Throwable $failure) {
			$this->logger->warning('[EmbedTokenService] The revocation notice could not be sent: ' . $failure->getMessage(), ['token' => $token->id]);
		}
	}//end notifyCreator()

	/**
	 * Now, as ISO 8601 in UTC.
	 *
	 * @return string
	 */
	private function nowIso(): string {
		return gmdate('Y-m-d\TH:i:s\Z', $this->time->getTime());
	}//end nowIso()

	/**
	 * A refusal in the REQ-EMB-001 1.3 shape.
	 *
	 * @param string $error   The error code.
	 * @param string $message The message.
	 * @param string $field   The field at fault.
	 *
	 * @return array<string, mixed>
	 */
	private static function refusal(string $error, string $message, string $field): array {
		return ['status' => 'error', 'error' => $error, 'message' => $message, 'fieldErrors' => [$field => $error]];
	}//end refusal()
}//end class
