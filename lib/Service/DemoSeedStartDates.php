<?php

/**
 * DemoSeedStartDates
 *
 * The start dates of the demo seed's new colleagues (REQ-PEX-005,
 * REQ-PPL-014): a date profile field marked as the start date, and each
 * listed person's start date as days from the day the seed is loaded, so
 * the "Nieuwe collega's" widget always has someone who starts next week.
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
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Seeds the start date field and the start dates of the demo people.
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-a-profile-field-can-hold-a-start-date-req-pex-005
 */
class DemoSeedStartDates {
	/**
	 * Constructor.
	 *
	 * @param ProfileFieldService $fields The profile field definitions and values.
	 */
	public function __construct(
		private readonly ProfileFieldService $fields,
	) {
	}//end __construct()

	/**
	 * Ensure a start date field and write the listed start dates. A field
	 * already marked as the start date is used as it is; otherwise the
	 * seed's field is added next to the existing definitions. Values of a
	 * field people do not fill in themselves (LDAP, Nextcloud) are left to
	 * their source.
	 *
	 * @param array<string, mixed> $definition `{field: {key, label}, days: {uid: offset}}`.
	 * @param string               $today      The load day, `YYYY-MM-DD`.
	 *
	 * @return array{field: string, people: array<string, string>} The field key and each person's date; empty without a definition.
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-a-profile-field-can-hold-a-start-date-req-pex-005
	 */
	public function seed(array $definition, string $today): array {
		$report = ['field' => '', 'people' => []];
		$days   = (array)($definition['days'] ?? []);
		if ($days === []) {
			return $report;
		}

		$report['field'] = $this->ensureField(field: (array)($definition['field'] ?? []));
		if ($report['field'] === '') {
			return $report;
		}

		$base = strtotime(datetime: $today.' 00:00:00 UTC');
		foreach ($days as $userId => $offset) {
			$date = gmdate(format: 'Y-m-d', timestamp: $base + ((int)$offset * 86400));
			$this->fields->saveOwnValues(userId: (string)$userId, values: [$report['field'] => $date]);
			$report['people'][(string)$userId] = $date;
		}

		return $report;
	}//end seed()

	/**
	 * The key of the start date field: the one already marked, else the
	 * seed's field, added as a date field people fill in themselves.
	 *
	 * @param array<string, mixed> $field `{key, label}`.
	 *
	 * @return string The key, or '' when the seed names no field and none is marked.
	 */
	private function ensureField(array $field): string {
		$definitions = $this->fields->getDefinitions();
		foreach ($definitions as $existing) {
			if (($existing['startDate'] ?? false) === true) {
				return (string)$existing['key'];
			}
		}

		$key = (string)($field['key'] ?? '');
		if ($key === '') {
			return '';
		}

		$definitions = array_values(
			array: array_filter(
				array: $definitions,
				callback: static fn (array $existing): bool => ($existing['key'] ?? '') !== $key
			)
		);
		$definitions[] = [
			'key'           => $key,
			'label'         => (string)($field['label'] ?? $key),
			'type'          => 'date',
			'source'        => 'self',
			'searchable'    => false,
			'shownInWidget' => false,
			'startDate'     => true,
		];
		$this->fields->saveDefinitions(raw: $definitions);

		return $key;
	}//end ensureField()
}//end class
