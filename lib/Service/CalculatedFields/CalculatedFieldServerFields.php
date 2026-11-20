<?php

/**
 * CalculatedFieldServerFields
 *
 * The save and delete flow of a widget's server-mode calculated fields
 * (REQ-CALC-004): before the widget is persisted, decide which fields will be
 * materialised; after, write them to the widget's OpenRegister source schema,
 * moving them when the source changed; on delete, remove them. A schema
 * OpenRegister refuses to write downgrades the fields to client mode instead
 * of failing the save.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\CalculatedFields
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-27
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\CalculatedFields;

use Throwable;

/**
 * Plans, writes and removes the OpenRegister side of server-mode fields.
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-27
 */
class CalculatedFieldServerFields {

	/**
	 * Constructor.
	 *
	 * @param CalculatedFieldMaterialiser|null $materialiser The materialiser; null keeps every field client-side.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-27
	 */
	public function __construct(
		private readonly ?CalculatedFieldMaterialiser $materialiser=null,
	) {
	}//end __construct()

	/**
	 * Decide, before a widget is persisted, which of its server-mode fields
	 * will be materialised and set their `serverComputedFieldId`. A field
	 * OpenRegister cannot evaluate alike is downgraded to client mode with a
	 * warning; without OpenRegister every server-mode field is.
	 *
	 * @param string                    $id     The widget id.
	 * @param list<array<string,mixed>> $fields The validated fields.
	 *
	 * @return array{fields: list<array<string,mixed>>, warnings: list<array<string,string>>}
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
	 */
	public function plan(string $id, array $fields): array {
		if ($this->materialiser === null) {
			return ['fields' => $fields, 'warnings' => []];
		}

		$plan = $this->materialiser->plan(widgetId: $id, fields: $fields);
		if ($plan['properties'] !== [] && $this->materialiser->isAvailable() === false) {
			$down = $this->materialiser->downgradeAll(fields: $plan['fields'], reason: 'OpenRegister is unavailable');
			return ['fields' => $down['fields'], 'warnings' => array_merge($plan['warnings'], $down['warnings'])];
		}

		return ['fields' => $plan['fields'], 'warnings' => $plan['warnings']];
	}//end plan()

	/**
	 * Write a persisted widget's server-mode fields to its source schema,
	 * replacing the properties the widget owned there, and remove them from
	 * the schema it was backed by before when its source changed.
	 *
	 * When the schema cannot be written (OpenRegister refuses the current
	 * user, or the schema is gone) nothing is materialised: every server-mode
	 * field is answered as a client field with a warning, for the caller to
	 * persist.
	 *
	 * @param string                    $id       The widget id.
	 * @param list<array<string,mixed>> $fields   The fields, from plan().
	 * @param string                    $source   The widget's `register/schema`.
	 * @param array<string,mixed>       $previous The widget before this save: `source` and `calculatedFields`.
	 *
	 * @return array{fields: list<array<string,mixed>>, warnings: list<array<string,string>>, changed: bool}
	 *         The fields as they now stand, warnings, and whether they differ from the ones given.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-004-server-side-evaluation-via-openregister-computed-fields
	 */
	public function materialise(string $id, array $fields, string $source, array $previous=[]): array {
		if ($this->materialiser === null || $this->materialiser->isAvailable() === false) {
			return ['fields' => $fields, 'warnings' => [], 'changed' => false];
		}

		$schema    = $this->schemaOf(sourceKey: $source);
		$oldSource = (string) ($previous['source'] ?? '');
		$hadServer = $this->hasServerFields(fields: ($previous['calculatedFields'] ?? []));
		$warnings  = $this->leaveOldSchema(id: $id, oldSource: $oldSource, schema: $schema, hadServer: $hadServer);

		$plan  = $this->materialiser->plan(widgetId: $id, fields: $fields);
		$stale = ($hadServer === true && $this->schemaOf(sourceKey: $oldSource) === $schema);
		if ($schema === '' || ($plan['properties'] === [] && $stale === false)) {
			return ['fields' => $fields, 'warnings' => $warnings, 'changed' => false];
		}

		try {
			$this->materialiser->write(schemaId: $schema, widgetId: $id, properties: $plan['properties']);
		} catch (Throwable) {
			$down = $this->materialiser->downgradeAll(fields: $plan['fields'], reason: 'the source schema could not be updated');
			return [
				'fields'   => $down['fields'],
				'warnings' => array_merge($warnings, $down['warnings']),
				'changed'  => $down['warnings'] !== [],
			];
		}

		return ['fields' => $plan['fields'], 'warnings' => $warnings, 'changed' => $plan['fields'] !== $fields];
	}//end materialise()

	/**
	 * Remove a widget's properties from the schema it was backed by before,
	 * when it had server fields and its source moved to another schema.
	 *
	 * @param string $id        The widget id.
	 * @param string $oldSource Its previous `register/schema`.
	 * @param string $schema    Its schema now.
	 * @param bool   $hadServer Whether it had materialised fields.
	 *
	 * @return list<array<string,string>> A warning when they could not be removed.
	 */
	private function leaveOldSchema(string $id, string $oldSource, string $schema, bool $hadServer): array {
		$oldSchema = $this->schemaOf(sourceKey: $oldSource);
		if ($hadServer === false || $oldSchema === '' || $oldSchema === $schema) {
			return [];
		}

		return $this->cleanup(id: $id, source: $oldSource);
	}//end leaveOldSchema()

	/**
	 * Remove every property a widget owns on its source schema.
	 *
	 * @param string $id     The widget id.
	 * @param string $source Its `register/schema`.
	 *
	 * @return list<array<string,string>> A warning when the properties could not be removed.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-28
	 */
	public function cleanup(string $id, string $source): array {
		$schema = $this->schemaOf(sourceKey: $source);
		if ($this->materialiser === null || $schema === '' || $this->materialiser->isAvailable() === false) {
			return [];
		}

		try {
			$this->materialiser->remove(schemaId: $schema, widgetId: $id);
		} catch (Throwable) {
			return [
				[
					'warning' => 'server_fields_not_removed',
					'field'   => '',
					'reason'  => 'the calculated properties on the source schema could not be removed',
				],
			];
		}

		return [];
	}//end cleanup()

	/**
	 * Whether any of the stored fields was materialised on OpenRegister.
	 *
	 * @param mixed $fields The stored fields.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-28
	 */
	public function hasServerFields(mixed $fields): bool {
		if (is_array($fields) === false) {
			return false;
		}

		foreach ($fields as $field) {
			if (is_array($field) === true && is_string($field['serverComputedFieldId'] ?? null) === true && $field['serverComputedFieldId'] !== '') {
				return true;
			}
		}

		return false;
	}//end hasServerFields()

	/**
	 * The schema part of a `register/schema` source key.
	 *
	 * @param string $sourceKey The source key.
	 *
	 * @return string The schema, or '' when there is none.
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-26
	 */
	public function schemaOf(string $sourceKey): string {
		$slash = strpos($sourceKey, '/');
		if ($slash === false) {
			return '';
		}

		return substr($sourceKey, ($slash + 1));
	}//end schemaOf()
}//end class
