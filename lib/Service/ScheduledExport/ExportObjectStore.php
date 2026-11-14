<?php

/**
 * ExportObjectStore
 *
 * The one place scheduled exports touch OpenRegister (design D3, Reuse
 * Analysis): exports and their run-rows are objects of the `launchpad`
 * register, read and written through OpenRegister's ObjectService, never a
 * LaunchPad table. OpenRegister is a soft dependency, so the service is
 * resolved lazily and its absence reads as "no objects".
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

/**
 * Object CRUD on the `launchpad` register for the scheduled-export schemas.
 *
 * Every call takes `$system`: the background runner and janitor run without
 * a session user, and OpenRegister denies an anonymous caller, so they read
 * and write with RBAC off. Request paths keep it on and check visibility in
 * {@see ScheduledExportService} as well.
 *
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) RBAC on is the default every request path gets.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */
class ExportObjectStore {

	public const REGISTER = 'launchpad';

	public const SCHEMA_EXPORT = 'scheduled_export';

	public const SCHEMA_RUN = 'scheduled_export_run';

	private const OBJECT_SERVICE = 'OCA\OpenRegister\Service\ObjectService';

	private const VALUE_NS = 'OCA\OpenRegister\Kernel\Contracts\Value\\';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's ObjectService lazily.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
	) {
	}//end __construct()

	/**
	 * Every object of a schema matching the filters.
	 *
	 * @param string               $schema  The schema slug.
	 * @param array<string, mixed> $filters Property filters; metadata filters nested under `@self`.
	 * @param bool                 $system  Read with RBAC off (background jobs).
	 *
	 * @return list<array<string, mixed>> The objects, each with its `id` and `owner`.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function findAll(string $schema, array $filters = [], bool $system = false): array {
		$service = $this->objectService();
		if ($service === null) {
			return [];
		}

		$filters['register'] = self::REGISTER;
		$filters['schema']   = $schema;
		$rows = $service->findAll(config: ['filters' => $filters, 'limit' => 1000], _rbac: $system === false);
		if (is_array($rows) === false) {
			return [];
		}

		return array_values(array_filter(array_map(fn ($row): array => $this->normalise(item: $row), $rows)));
	}//end findAll()

	/**
	 * One object by uuid, or null when it does not exist or may not be read.
	 *
	 * @param string $schema The schema slug.
	 * @param string $uuid   The object uuid.
	 * @param bool   $system Read with RBAC off (background jobs).
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function find(string $schema, string $uuid, bool $system = false): ?array {
		$service = $this->objectService();
		if ($service === null || $uuid === '') {
			return null;
		}

		$options = $this->value(name: 'ObjectFindOptions', args: ['register' => self::REGISTER, 'schema' => $schema]);
		try {
			$row = $service->find(id: $uuid, options: $options, _rbac: $system === false);
		} catch (Throwable) {
			return null;
		}

		$data = $this->normalise(item: $row);
		if ($data === []) {
			return null;
		}

		return $data;
	}//end find()

	/**
	 * Create or update an object.
	 *
	 * @param string               $schema The schema slug.
	 * @param array<string, mixed> $data   The object's properties; an `id` updates that object.
	 * @param bool                 $system Write with RBAC off (background jobs).
	 *
	 * @return array<string, mixed> The saved object.
	 *
	 * @throws RuntimeException When OpenRegister is not available.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function save(string $schema, array $data, bool $system = false): array {
		$service = $this->objectService();
		if ($service === null) {
			throw new RuntimeException('OpenRegister is not available');
		}

		$uuid = null;
		if (is_string($data['id'] ?? null) === true && $data['id'] !== '') {
			$uuid = $data['id'];
		}

		unset($data['id'], $data['owner'], $data['@self']);

		$target  = $this->value(name: 'ObjectFindTarget', args: ['register' => self::REGISTER, 'schema' => $schema]);
		$options = $this->value(name: 'ObjectSaveOptions', args: ['target' => $target, 'uuid' => $uuid]);
		$saved   = $service->saveObject(object: $data, options: $options, _rbac: $system === false);

		return $this->normalise(item: $saved);
	}//end save()

	/**
	 * Soft-delete an object (OpenRegister's standard delete).
	 *
	 * @param string $uuid   The object uuid.
	 * @param bool   $system Delete with RBAC off (the janitor).
	 *
	 * @return bool Whether it was deleted.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function delete(string $uuid, bool $system = false): bool {
		$service = $this->objectService();
		if ($service === null) {
			return false;
		}

		$deletion = $this->value(name: 'ObjectDeletion', args: ['uuid' => $uuid]);
		return $service->deleteObject(deletion: $deletion, _rbac: $system === false) === true;
	}//end delete()

	/**
	 * A plain array from an ObjectService result, with `id` and `owner` lifted
	 * out of `@self`.
	 *
	 * @param mixed $item An ObjectEntity, a serialisable object or an array.
	 *
	 * @return array<string, mixed> The data, or [] for anything else.
	 */
	private function normalise(mixed $item): array {
		if (is_object($item) === true && method_exists($item, 'jsonSerialize') === true) {
			$item = $item->jsonSerialize();
		}

		if (is_array($item) === false) {
			return [];
		}

		$self = [];
		if (is_array($item['@self'] ?? null) === true) {
			$self = $item['@self'];
		}

		$item['id']    = $item['id'] ?? $self['id'] ?? $self['uuid'] ?? null;
		$item['owner'] = $self['owner'] ?? $item['owner'] ?? null;
		unset($item['@self']);

		return $item;
	}//end normalise()

	/**
	 * One of OpenRegister's request value objects, built by name so the
	 * soft dependency stays soft.
	 *
	 * @param string               $name The class name under the value namespace.
	 * @param array<string, mixed> $args Its named constructor arguments.
	 *
	 * @return object
	 */
	private function value(string $name, array $args): object {
		$class = self::VALUE_NS . $name;
		return new $class(...$args);
	}//end value()

	/**
	 * OpenRegister's ObjectService, or null when OpenRegister is not enabled.
	 *
	 * @return object|null
	 */
	private function objectService(): ?object {
		try {
			$service = $this->container->get(self::OBJECT_SERVICE);
		} catch (Throwable) {
			return null;
		}

		if (is_object($service) === false) {
			return null;
		}

		return $service;
	}//end objectService()
}//end class
