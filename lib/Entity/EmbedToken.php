<?php

/**
 * EmbedToken
 *
 * One `embed_token` object of the launchpad register: what a token renders,
 * what it may do, where it may be framed, and whether it still holds. The
 * token itself is never part of it, only its sha256 hash.
 *
 * @category  Entity
 * @package   OCA\LaunchPad\Entity
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

namespace OCA\LaunchPad\Entity;

/**
 * An embed token record.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
 */
final class EmbedToken {

	/**
	 * Read-only scope: GET and HEAD only.
	 *
	 * @var string
	 */
	public const MODE_READ = 'read';

	/**
	 * Read with the interactions the scope lists.
	 *
	 * @var string
	 */
	public const MODE_INTERACT = 'read-with-interactions';

	/**
	 * The actions a scope may allow.
	 *
	 * @var list<string>
	 */
	public const ACTIONS = ['interact', 'export', 'drillDown'];

	/**
	 * The reasons a token may be revoked for.
	 *
	 * @var list<string>
	 */
	public const REASONS = ['compromised', 'no_longer_needed', 'host_decommissioned', 're-issued', 'other'];

	/**
	 * Constructor.
	 *
	 * @param string                    $id               The object id ('' before the first save).
	 * @param string                    $name             The name.
	 * @param string                    $description      The description.
	 * @param string                    $tokenHash        sha256 of the token, hex.
	 * @param array<string, mixed>      $subject          {type, id, filterContext?}.
	 * @param array<string, mixed>      $scope            {mode, allowedFilters, allowedActions}.
	 * @param list<string>              $hostOrigins      The origins that may frame it.
	 * @param string|null               $tenantThemeId    The theme, if any.
	 * @param string|null               $tokenExpiresAt   ISO 8601, null for no expiry.
	 * @param array<string, int>|null   $rateLimitPolicy  {requestsPerMinute, burstSize}.
	 * @param string                    $keyId            The kid it was signed with.
	 * @param string                    $createdBy        The admin who issued it.
	 * @param string                    $createdAt        ISO 8601.
	 * @param string|null               $revokedAt        ISO 8601 once revoked.
	 * @param string|null               $revocationReason One of REASONS once revoked.
	 * @param string|null               $revocationNotes  Free text once revoked.
	 * @param string|null               $revokedBy        The admin who revoked it.
	 * @param string|null               $updatedAt        ISO 8601 of the last change.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly string $description,
		public readonly string $tokenHash,
		public readonly array $subject,
		public readonly array $scope,
		public readonly array $hostOrigins,
		public readonly ?string $tenantThemeId,
		public readonly ?string $tokenExpiresAt,
		public readonly ?array $rateLimitPolicy,
		public readonly string $keyId,
		public readonly string $createdBy,
		public readonly string $createdAt,
		public readonly ?string $revokedAt=null,
		public readonly ?string $revocationReason=null,
		public readonly ?string $revocationNotes=null,
		public readonly ?string $revokedBy=null,
		public readonly ?string $updatedAt=null,
	) {
	}//end __construct()

	/**
	 * A record from a stored object; missing or malformed fields read as empty.
	 *
	 * @param array<string, mixed> $object The stored object.
	 *
	 * @return self
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public static function fromObject(array $object): self {
		$policy = null;
		if (is_array($object['rateLimitPolicy'] ?? null) === true) {
			$policy = [
				'requestsPerMinute' => (int)($object['rateLimitPolicy']['requestsPerMinute'] ?? 0),
				'burstSize'         => (int)($object['rateLimitPolicy']['burstSize'] ?? 0),
			];
		}

		$subject = [];
		if (is_array($object['subject'] ?? null) === true) {
			$subject = $object['subject'];
		}

		$scope = [];
		if (is_array($object['scope'] ?? null) === true) {
			$scope = $object['scope'];
		}

		$origins = array_values(array_filter((array)($object['hostOrigins'] ?? []), is_string(...)));

		return new self(
			id: (string)($object['id'] ?? ''),
			name: (string)($object['name'] ?? ''),
			description: (string)($object['description'] ?? ''),
			tokenHash: (string)($object['tokenHash'] ?? ''),
			subject: $subject,
			scope: $scope,
			hostOrigins: $origins,
			tenantThemeId: self::optional(value: $object['tenantThemeId'] ?? null),
			tokenExpiresAt: self::optional(value: $object['tokenExpiresAt'] ?? null),
			rateLimitPolicy: $policy,
			keyId: (string)($object['keyId'] ?? ''),
			createdBy: (string)($object['createdBy'] ?? ''),
			createdAt: (string)($object['createdAt'] ?? ''),
			revokedAt: self::optional(value: $object['revokedAt'] ?? null),
			revocationReason: self::optional(value: $object['revocationReason'] ?? null),
			revocationNotes: self::optional(value: $object['revocationNotes'] ?? null),
			revokedBy: self::optional(value: $object['revokedBy'] ?? null),
			updatedAt: self::optional(value: $object['updatedAt'] ?? null),
		);
	}//end fromObject()

	/**
	 * The object to store, with the hash; `id` only once it has one.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public function toObject(): array {
		$object = [
			'name'             => $this->name,
			'description'      => $this->description,
			'tokenHash'        => $this->tokenHash,
			'subject'          => $this->subject,
			'scope'            => $this->scope,
			'hostOrigins'      => $this->hostOrigins,
			'tenantThemeId'    => $this->tenantThemeId,
			'tokenExpiresAt'   => $this->tokenExpiresAt,
			'rateLimitPolicy'  => $this->rateLimitPolicy,
			'keyId'            => $this->keyId,
			'createdBy'        => $this->createdBy,
			'createdAt'        => $this->createdAt,
			'revokedAt'        => $this->revokedAt,
			'revocationReason' => $this->revocationReason,
			'revocationNotes'  => $this->revocationNotes,
			'revokedBy'        => $this->revokedBy,
			'updatedAt'        => $this->updatedAt,
		];
		if ($this->id !== '') {
			$object = ['id' => $this->id] + $object;
		}

		return $object;
	}//end toObject()

	/**
	 * What an admin sees: everything but the hash (REQ-EMB-001 1.2).
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-001--token-issuance-returns-jwt-exactly-once
	 */
	public function metadata(): array {
		$metadata = $this->toObject();
		unset($metadata['tokenHash']);
		$metadata['id'] = $this->id;

		return $metadata;
	}//end metadata()

	/**
	 * Whether the token has been revoked.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public function isRevoked(): bool {
		return $this->revokedAt !== null;
	}//end isRevoked()

	/**
	 * Whether the token has expired at a moment.
	 *
	 * @param int $now Unix time.
	 *
	 * @return bool False for a token without expiry or with an unreadable one.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public function isExpiredAt(int $now): bool {
		if ($this->tokenExpiresAt === null) {
			return false;
		}

		$expires = strtotime($this->tokenExpiresAt);

		return $expires !== false && $expires <= $now;
	}//end isExpiredAt()

	/**
	 * Whether the token renders this subject.
	 *
	 * @param string $type `widget` or `dashboard`.
	 * @param string $id   The subject id.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	public function grants(string $type, string $id): bool {
		return $id !== ''
			&& (string)($this->subject['type'] ?? '') === $type
			&& hash_equals((string)($this->subject['id'] ?? ''), $id);
	}//end grants()

	/**
	 * A copy revoked now, for a reason.
	 *
	 * @param string      $at     ISO 8601.
	 * @param string      $reason One of REASONS.
	 * @param string|null $notes  Free text.
	 * @param string      $by     The admin.
	 *
	 * @return self
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public function revoked(string $at, string $reason, ?string $notes, string $by): self {
		return $this->with(revokedAt: $at, revocationReason: $reason, revocationNotes: $notes, revokedBy: $by, updatedAt: $at);
	}//end revoked()

	/**
	 * A copy with the revocation cleared.
	 *
	 * @param string $at ISO 8601.
	 *
	 * @return self
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public function unrevoked(string $at): self {
		return $this->with(revokedAt: null, revocationReason: null, revocationNotes: null, revokedBy: null, updatedAt: $at);
	}//end unrevoked()

	/**
	 * A copy with other revocation fields.
	 *
	 * @param string|null $revokedAt        ISO 8601.
	 * @param string|null $revocationReason The reason.
	 * @param string|null $revocationNotes  The notes.
	 * @param string|null $revokedBy        The admin.
	 * @param string      $updatedAt        ISO 8601.
	 *
	 * @return self
	 */
	private function with(?string $revokedAt, ?string $revocationReason, ?string $revocationNotes, ?string $revokedBy, string $updatedAt): self {
		return new self(
			id: $this->id,
			name: $this->name,
			description: $this->description,
			tokenHash: $this->tokenHash,
			subject: $this->subject,
			scope: $this->scope,
			hostOrigins: $this->hostOrigins,
			tenantThemeId: $this->tenantThemeId,
			tokenExpiresAt: $this->tokenExpiresAt,
			rateLimitPolicy: $this->rateLimitPolicy,
			keyId: $this->keyId,
			createdBy: $this->createdBy,
			createdAt: $this->createdAt,
			revokedAt: $revokedAt,
			revocationReason: $revocationReason,
			revocationNotes: $revocationNotes,
			revokedBy: $revokedBy,
			updatedAt: $updatedAt,
		);
	}//end with()

	/**
	 * A non-empty string, or null.
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return string|null
	 */
	private static function optional(mixed $value): ?string {
		if (is_string($value) === false || $value === '') {
			return null;
		}

		return $value;
	}//end optional()
}//end class
