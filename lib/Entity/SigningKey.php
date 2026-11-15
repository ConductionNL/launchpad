<?php

/**
 * SigningKey
 *
 * One `signing_key` object of the launchpad register as the key record
 * JwtSigningService works with. The private key is held encrypted at rest
 * and only decrypted into the record a signing call needs.
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
 * Converts between a stored `signing_key` object and a key record.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
 */
final class SigningKey {

	/**
	 * The fields a key record and a stored key share.
	 *
	 * @var list<string>
	 */
	private const SHARED = ['kid', 'algorithm', 'status', 'publicKeyPem', 'publicKeyJwk', 'createdAt', 'retiredAt'];

	/**
	 * The stored object of a key record, its private key encrypted.
	 *
	 * @param array<string, mixed> $record              A JwtSigningService key record.
	 * @param string               $privateKeyEncrypted The encrypted private key PEM.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public static function toObject(array $record, string $privateKeyEncrypted): array {
		$object = [];
		if (isset($record['id']) === true && is_string($record['id']) === true && $record['id'] !== '') {
			$object['id'] = $record['id'];
		}

		foreach (self::SHARED as $field) {
			$object[$field] = ($record[$field] ?? null);
		}

		$object['privateKeyEncrypted'] = $privateKeyEncrypted;

		return $object;
	}//end toObject()

	/**
	 * The key record of a stored object, with the decrypted private key.
	 *
	 * @param array<string, mixed> $object        The stored object.
	 * @param string               $privateKeyPem The decrypted private key, '' when it cannot be read.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public static function toRecord(array $object, string $privateKeyPem): array {
		$record = ['id' => (string)($object['id'] ?? '')];
		foreach (self::SHARED as $field) {
			$record[$field] = ($object[$field] ?? null);
		}

		$record['privateKeyPem'] = $privateKeyPem;

		return $record;
	}//end toRecord()
}//end class
