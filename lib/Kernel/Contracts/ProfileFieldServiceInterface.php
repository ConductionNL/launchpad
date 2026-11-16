<?php

/**
 * Contract of ProfileFieldService
 *
 * The public surface of ProfileFieldService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Kernel\Contracts;

use OCP\IUser;

/**
 * Contract of ProfileFieldService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ProfileFieldServiceInterface {
	/**
	 * The stored field definitions, cleaned.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function getDefinitions(): array;

	/**
	 * Validate and store the field definitions (REQ-PEX-001).
	 *
	 * @param array $raw Definitions as sent by the admin screen.
	 *
	 * @return array<int, array<string, mixed>> The stored definitions.
	 *
	 * @throws \InvalidArgumentException When a definition is invalid.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function saveDefinitions(array $raw): array;

	/**
	 * A person's own fields with their values, for the personal settings (REQ-PEX-002).
	 *
	 * @param string $userId The person.
	 *
	 * @return array<int, array<string, mixed>> Definitions with `values` and `readOnly`.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function getOwnFields(string $userId): array;

	/**
	 * Save a person's own values (REQ-PEX-002). LDAP fields are read-only
	 * and ignored; unknown keys are rejected.
	 *
	 * @param string $userId The person.
	 * @param array $values Map of field key to a string (text) or a list of strings (tags).
	 *
	 * @return array<int, array<string, mixed>> The fields after saving.
	 *
	 * @throws \InvalidArgumentException When a key or value is invalid.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function saveOwnValues(string $userId, array $values): array;

	/**
	 * Mirror a person's searchable standard fields with their scope (REQ-PEX-004).
	 *
	 * @param IUser $user The person.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function mirrorStandardFields(IUser $user): void;

	/**
	 * Read a person's LDAP-sourced fields (REQ-PEX-001). Does nothing when
	 * `user_ldap` is not available or the account does not come from LDAP.
	 *
	 * @param IUser $user The person.
	 *
	 * @return int Number of fields synced.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function syncLdapFields(IUser $user): int;

	/**
	 * Remove every value of a deleted person.
	 *
	 * @param string $userId The person.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function deleteUser(string $userId): void;
}//end interface
