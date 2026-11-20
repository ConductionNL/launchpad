<?php

/**
 * SigningKeyService
 *
 * The signing keys of embed tokens, stored as `signing_key` objects with the
 * private key encrypted by Nextcloud's ICrypto (the instance secret). It
 * hands JwtSigningService decrypted key records, creates the first RS256 key
 * when none is active, and stores a rotation.
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

use OCA\LaunchPad\Entity\SigningKey;
use OCP\Security\ICrypto;
use Throwable;

/**
 * Loads, creates and rotates the stored signing keys.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-4
 */
class SigningKeyService {

	/**
	 * Constructor.
	 *
	 * @param EmbedObjectStore  $store  The register objects.
	 * @param JwtSigningService $jwt    Generates and rotates key pairs.
	 * @param ICrypto           $crypto Encrypts the private keys at rest.
	 */
	public function __construct(
		private readonly EmbedObjectStore $store,
		private readonly JwtSigningService $jwt,
		private readonly ICrypto $crypto,
	) {
	}//end __construct()

	/**
	 * Every stored key as a key record. A private key that cannot be
	 * decrypted reads as '' — the key still verifies, it cannot sign.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-4
	 */
	public function keys(): array {
		$records = [];
		foreach ($this->store->findBy(schema: EmbedObjectStore::KEYS) as $object) {
			$records[] = SigningKey::toRecord(object: $object, privateKeyPem: $this->decrypt(value: (string)($object['privateKeyEncrypted'] ?? '')));
		}

		return $records;
	}//end keys()

	/**
	 * The keys, with a new active RS256 key stored first when none is active.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-4
	 */
	public function keysWithActive(): array {
		$keys = $this->keys();
		foreach ($keys as $key) {
			if (($key['status'] ?? null) === JwtSigningService::STATUS_ACTIVE && (string)($key['privateKeyPem'] ?? '') !== '') {
				return $keys;
			}
		}

		$keys[] = $this->persist(record: $this->jwt->generateKeyPair(algorithm: JwtSigningService::RS256));

		return $keys;
	}//end keysWithActive()

	/**
	 * Rotate: every active key turns retiring and a new active key is stored.
	 *
	 * @param string $algorithm RS256 or ES256.
	 *
	 * @return list<array<string, mixed>> The keys after rotation.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-4
	 */
	public function rotate(string $algorithm): array {
		$before  = $this->keys();
		$rotated = [];
		foreach ($this->jwt->rotate(keys: $before, algorithm: $algorithm) as $index => $record) {
			if (isset($before[$index]) === true && $before[$index] === $record) {
				$rotated[] = $record;
				continue;
			}

			$rotated[] = $this->persist(record: $record);
		}

		return $rotated;
	}//end rotate()

	/**
	 * Store a key record, its private key encrypted.
	 *
	 * @param array<string, mixed> $record The key record.
	 *
	 * @return array<string, mixed> The record with its stored id.
	 */
	private function persist(array $record): array {
		$encrypted = $this->crypto->encrypt((string)($record['privateKeyPem'] ?? ''));
		$stored    = $this->store->save(schema: EmbedObjectStore::KEYS, object: SigningKey::toObject(record: $record, privateKeyEncrypted: $encrypted));

		return ['id' => (string)$stored['id']] + $record;
	}//end persist()

	/**
	 * A decrypted private key, '' when it cannot be decrypted.
	 *
	 * @param string $value The encrypted value.
	 *
	 * @return string
	 */
	private function decrypt(string $value): string {
		if ($value === '') {
			return '';
		}

		try {
			return $this->crypto->decrypt($value);
		} catch (Throwable) {
			return '';
		}
	}//end decrypt()
}//end class
