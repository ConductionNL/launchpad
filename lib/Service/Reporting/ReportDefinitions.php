<?php

/**
 * ReportDefinitions
 *
 * The report definitions launchpad ships (REQ-DWMS-003): read from
 * lib/Settings/reports.json, checked against one shape, and handed out as
 * plain arrays. Nothing writes them: a definition changes with the app.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\Reporting
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
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\Reporting;

use InvalidArgumentException;

/**
 * Loads and checks the shipped report definitions.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */
class ReportDefinitions {

	/**
	 * The aggregations a definition may run.
	 */
	public const KINDS = ['count-by', 'area'];

	/**
	 * The parameter types a definition may declare.
	 */
	public const PARAMETER_TYPES = ['date', 'string'];

	/**
	 * The shipped file.
	 */
	public const FILE = __DIR__ . '/../../Settings/reports.json';

	/**
	 * The checked definitions, keyed by id, once read.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $definitions = null;

	/**
	 * Constructor.
	 *
	 * @param string $file The definitions file; the shipped one unless a test passes another.
	 */
	public function __construct(
		private readonly string $file=self::FILE,
	) {
	}//end __construct()

	/**
	 * Every definition, in file order.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @throws InvalidArgumentException When the file or a definition is malformed.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-003-reports-the-product-ships
	 */
	public function all(): array {
		return array_values($this->load());
	}//end all()

	/**
	 * One definition, or null for an unknown id.
	 *
	 * @param string $id The report id.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-003-reports-the-product-ships
	 */
	public function find(string $id): ?array {
		return ($this->load()[$id] ?? null);
	}//end find()

	/**
	 * Check one definition against the shape and return it normalised.
	 *
	 * @param mixed $definition The raw definition.
	 *
	 * @return array<string, mixed> The definition: id, title, description, source {register, schema}, filters,
	 *                              aggregation, parameters [{name, type, default}], rendering and requires
	 *                              {registers, schemas}.
	 *
	 * @throws InvalidArgumentException Naming the field that is wrong.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-003-reports-the-product-ships
	 */
	public function check(mixed $definition): array {
		if (is_array($definition) === false) {
			throw new InvalidArgumentException('A report definition must be an object.');
		}

		$id = $this->text(definition: $definition, key: 'id', label: 'report');
		$source = ($definition['source'] ?? null);
		if (is_array($source) === false) {
			throw new InvalidArgumentException("Report {$id}: source must name a register and a schema.");
		}

		$register = $this->text(definition: $source, key: 'register', label: $id);
		$schema = $this->text(definition: $source, key: 'schema', label: $id);
		$aggregation = ($definition['aggregation'] ?? null);
		if (is_array($aggregation) === false || in_array(($aggregation['kind'] ?? null), self::KINDS, true) === false) {
			throw new InvalidArgumentException("Report {$id}: aggregation.kind must be one of " . implode(', ', self::KINDS) . '.');
		}

		$this->text(definition: $aggregation, key: 'property', label: $id);

		return [
			'id'          => $id,
			'title'       => $this->text(definition: $definition, key: 'title', label: $id),
			'description' => $this->text(definition: $definition, key: 'description', label: $id),
			'source'      => ['register' => $register, 'schema' => $schema],
			'filters'     => $this->listOrMap(value: ($definition['filters'] ?? []), label: "Report {$id}: filters"),
			'aggregation' => $aggregation,
			'parameters'  => $this->parameters(raw: ($definition['parameters'] ?? []), id: $id),
			'rendering'   => $this->listOrMap(value: ($definition['rendering'] ?? []), label: "Report {$id}: rendering"),
			'requires'    => ['registers' => [$register], 'schemas' => [$register . '/' . $schema]],
		];
	}//end check()

	/**
	 * Read and check the file once.
	 *
	 * @return array<string, array<string, mixed>>
	 *
	 * @throws InvalidArgumentException When the file or a definition is malformed.
	 */
	private function load(): array {
		if ($this->definitions !== null) {
			return $this->definitions;
		}

		$raw = @file_get_contents($this->file);
		$decoded = json_decode((string)$raw, true);
		if (is_array($decoded) === false || is_array(($decoded['reports'] ?? null)) === false) {
			throw new InvalidArgumentException('The report definitions file is missing or not valid JSON.');
		}

		$definitions = [];
		foreach ($decoded['reports'] as $definition) {
			$checked = $this->check(definition: $definition);
			if (isset($definitions[$checked['id']]) === true) {
				throw new InvalidArgumentException("Report {$checked['id']} is defined twice.");
			}

			$definitions[$checked['id']] = $checked;
		}

		$this->definitions = $definitions;
		return $definitions;
	}//end load()

	/**
	 * The declared parameters, checked.
	 *
	 * @param mixed  $raw The raw list.
	 * @param string $id  The report id.
	 *
	 * @return list<array{name: string, type: string, default: mixed}>
	 *
	 * @throws InvalidArgumentException Naming the parameter that is wrong.
	 */
	private function parameters(mixed $raw, string $id): array {
		if (is_array($raw) === false || array_is_list($raw) === false) {
			throw new InvalidArgumentException("Report {$id}: parameters must be a list.");
		}

		$parameters = [];
		foreach ($raw as $parameter) {
			if (is_array($parameter) === false || in_array(($parameter['type'] ?? null), self::PARAMETER_TYPES, true) === false) {
				throw new InvalidArgumentException("Report {$id}: every parameter needs a type of " . implode(', ', self::PARAMETER_TYPES) . '.');
			}

			$parameters[] = [
				'name'    => $this->text(definition: $parameter, key: 'name', label: $id),
				'type'    => $parameter['type'],
				'default' => ($parameter['default'] ?? null),
			];
		}

		return $parameters;
	}//end parameters()

	/**
	 * A required non-empty string field.
	 *
	 * @param array<string, mixed> $definition The object holding it.
	 * @param string               $key        The field.
	 * @param string               $label      The report, for the message.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When it is missing or empty.
	 */
	private function text(array $definition, string $key, string $label): string {
		$value = ($definition[$key] ?? null);
		if (is_string($value) === false || trim($value) === '') {
			throw new InvalidArgumentException("Report {$label}: {$key} must be a non-empty string.");
		}

		return trim($value);
	}//end text()

	/**
	 * An object-shaped field, empty when absent.
	 *
	 * @param mixed  $value The value.
	 * @param string $label The field, for the message.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException When it is not an object.
	 */
	private function listOrMap(mixed $value, string $label): array {
		if (is_array($value) === false) {
			throw new InvalidArgumentException("{$label} must be an object.");
		}

		return $value;
	}//end listOrMap()
}//end class
