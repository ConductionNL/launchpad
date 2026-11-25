<?php

/**
 * An in-memory stand-in for OpenRegister's object service, with the calls
 * the embed store makes: setRegister/setSchema, saveObject (create without
 * an id, update with one), find, deleteObject and searchObjectsBySlug with equality filters.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\Embed\Support;

use RuntimeException;

class InMemoryObjectService {

	/**
	 * Objects per "register/schema", by id.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	public array $objects = [];

	/**
	 * How each search named its register and schema: 'target' or 'named slugs'.
	 *
	 * @var list<string>
	 */
	public array $searchShapes = [];

	/**
	 * Every call, as "method register/schema".
	 *
	 * @var list<string>
	 */
	public array $calls = [];

	/**
	 * Whether saveObject explodes.
	 *
	 * @var bool
	 */
	public bool $failSaves = false;

	/**
	 * Every saved payload per "register/schema", in order, as it arrived.
	 *
	 * @var array<string, list<array<string, mixed>>>
	 */
	public array $saved = [];

	private string $register = '';

	private string $schema = '';

	private int $sequence = 0;

	public function setRegister(string|int $register): static {
		$this->register = (string)$register;
		return $this;
	}

	public function setSchema(string|int $schema): static {
		$this->schema = (string)$schema;
		return $this;
	}

	/**
	 * @param array<string, mixed> $object
	 */
	public function saveObject(array $object, mixed $options=null, bool $_rbac=true, bool $_multitenancy=true): object {
		$this->calls[] = 'save ' . $this->bucket() . ($_rbac === false && $_multitenancy === false ? ' system' : ' user');
		if ($this->failSaves === true) {
			throw new RuntimeException('database down');
		}

		$this->saved[$this->bucket()][] = $object;

		$id = (string)($object['id'] ?? '');
		if ($id === '') {
			$id = 'obj-' . (++$this->sequence);
		}

		unset($object['id']);
		$this->objects[$this->bucket()][$id] = $object;

		return $this->entity(id: $id, object: $object);
	}

	public function find(int|string $id, mixed $options=null, bool $_rbac=true, bool $_multitenancy=true): ?object {
		$this->calls[] = 'find ' . $this->bucket();
		$object = ($this->objects[$this->bucket()][(string)$id] ?? null);

		return $object === null ? null : $this->entity(id: (string)$id, object: $object);
	}

	public function deleteObject(object $deletion, bool $_rbac=true, bool $_multitenancy=true, bool $_retentionSweep=false): bool {
		$this->calls[] = 'delete ' . $this->bucket() . ($_rbac === false && $_multitenancy === false ? ' system' : ' user');
		if (isset($this->objects[$this->bucket()][$deletion->uuid]) === false) {
			return false;
		}

		unset($this->objects[$this->bucket()][$deletion->uuid]);

		return true;
	}

	/**
	 * @param array<string, mixed> $filters
	 *
	 * @return array<mixed>
	 */
	/**
	 * Takes the slugs either way OpenRegister has taken them: one `target`
	 * value (register, schema) or, in older releases, two named strings.
	 *
	 * @param object|null          $target        The register and schema, in the newer shape.
	 * @param array<string, mixed> $filters       Field => value.
	 * @param bool                 $_rbac         Unused.
	 * @param bool                 $_multitenancy Unused.
	 * @param string|null          $registerSlug  The register slug, in the older shape.
	 * @param string|null          $schemaSlug    The schema slug, in the older shape.
	 *
	 * @return array<string, mixed>
	 */
	public function searchObjectsBySlug(
		?object $target=null,
		array $filters=[],
		bool $_rbac=true,
		bool $_multitenancy=true,
		?string $registerSlug=null,
		?string $schemaSlug=null
	): array {
		$shape = 'named slugs';
		if ($target !== null) {
			$shape = 'target';
			$registerSlug = (string)$target->register;
			$schemaSlug = (string)$target->schema;
		}

		$this->calls[] = 'search ' . $registerSlug . '/' . $schemaSlug;
		$this->searchShapes[] = $shape;
		$rows = [];
		foreach ($this->objects[$registerSlug . '/' . $schemaSlug] ?? [] as $id => $object) {
			foreach ($filters as $field => $value) {
				if (($object[$field] ?? null) !== $value) {
					continue 2;
				}
			}

			$rows[] = ['@self' => ['id' => $id]] + $object;
		}

		return ['results' => $rows];
	}

	private function bucket(): string {
		return $this->register . '/' . $this->schema;
	}

	/**
	 * @param array<string, mixed> $object
	 */
	private function entity(string $id, array $object): object {
		return new class ($id, $object) {
			/**
			 * @param array<string, mixed> $object
			 */
			public function __construct(private readonly string $id, private readonly array $object) {
			}

			public function getUuid(): string {
				return $this->id;
			}

			/**
			 * @return array<string, mixed>
			 */
			public function getObject(): array {
				return $this->object;
			}
		};
	}
}
