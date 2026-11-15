<?php

/**
 * ProfileFieldListing
 *
 * The widget's profile-field definitions and the viewer's visibility
 * check, applied to the grouped values of the listed people.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use Closure;

/**
 * Lists the profile fields one viewer may see, per person.
 *
 * Built by {@see ProfileFieldService::visibleFieldsFor()} for one listing;
 * never shared.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
final class ProfileFieldListing {
	/**
	 * Constructor.
	 *
	 * @param array<int, array<string, mixed>> $definitions The widget's field definitions.
	 * @param Closure                          $canSee      `(string $ownerId, array $definition): bool`.
	 */
	public function __construct(
		private readonly array $definitions,
		private readonly Closure $canSee,
	) {
	}//end __construct()

	/**
	 * The visible fields of every person that has at least one.
	 *
	 * @param array<string, array<string, string[]>> $grouped Values by person, then field key.
	 *
	 * @return array<string, list<array{key: string, label: string, type: string, values: string[]}>>
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function fieldsByPerson(array $grouped): array {
		$result = [];
		foreach ($grouped as $userId => $byKey) {
			$fields = $this->fieldsOf(ownerId: (string)$userId, byKey: $byKey);
			if ($fields !== []) {
				$result[$userId] = $fields;
			}
		}

		return $result;
	}//end fieldsByPerson()

	/**
	 * One person's fields that have values and that the viewer may see,
	 * in definition order.
	 *
	 * @param string                  $ownerId The person.
	 * @param array<string, string[]> $byKey   Their values by field key.
	 *
	 * @return list<array{key: string, label: string, type: string, values: string[]}>
	 */
	private function fieldsOf(string $ownerId, array $byKey): array {
		$fields = [];
		foreach ($this->definitions as $definition) {
			$values = $byKey[$definition['key']] ?? [];
			if ($values === [] || ($this->canSee)($ownerId, $definition) === false) {
				continue;
			}

			$fields[] = ['key' => $definition['key'], 'label' => $definition['label'], 'type' => $definition['type'], 'values' => $values];
		}

		return $fields;
	}//end fieldsOf()
}//end class
