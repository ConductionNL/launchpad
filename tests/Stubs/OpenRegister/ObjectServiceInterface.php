<?php

/**
 * Declaration-only stub of OpenRegister's published object service contract,
 * for static analysis: OpenRegister is a sibling app, not in vendor/. Only the
 * methods LaunchPad's embed store calls are declared, with the signatures of
 * `OCA\OpenRegister\Contract\ObjectServiceInterface`.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Contract;

interface ObjectServiceInterface {
	/**
	 * @param array<string, mixed> $object
	 */
	public function saveObject(array $object, mixed $options=null, bool $_rbac=true, bool $_multitenancy=true): object;

	public function setRegister(string|int $register): static;

	public function setSchema(string|int $schema): static;

	public function find(int|string $id, mixed $options=null, bool $_rbac=true, bool $_multitenancy=true): ?object;

	/**
	 * @param array<string, mixed> $filters
	 *
	 * @return array<mixed>|int
	 */
	public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters=[], bool $_rbac=true, bool $_multitenancy=true): array|int;
}
