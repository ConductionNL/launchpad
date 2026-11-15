<?php

/**
 * An in-memory stand-in for OpenRegister's object service, with the calls
 * the embed store makes: setRegister/setSchema, saveObject (create without
 * an id, update with one), find and searchObjectsBySlug with equality filters.
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

	/**
	 * @param array<string, mixed> $filters
	 *
	 * @return array<mixed>
	 */
	public function searchObjectsBySlug(string $registerSlug, string $schemaSlug, array $filters=[], bool $_rbac=true, bool $_multitenancy=true): array {
		$this->calls[] = 'search ' . $registerSlug . '/' . $schemaSlug;
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
