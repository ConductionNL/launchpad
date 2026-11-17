<?php

/**
 * ScheduledExportStore
 *
 * The one place the scheduled-export services reach OpenRegister: the
 * `scheduled_export` and `scheduled_export_run` objects of the `launchpad`
 * register are read, saved and soft-deleted through OpenRegister's
 * ObjectService (design D3, ADR-001), never through an own table.
 *
 * Calls use OpenRegister main's object API (ObjectFindOptions,
 * ObjectSaveOptions, ObjectDeletion). Every row comes back as the object's
 * data with its `id` and `@self.owner`, whatever shape OpenRegister answered
 * in. With `$rbac` true OpenRegister applies the signed-in user's access
 * rules; the background runner passes false because it runs with no session.
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
 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use JsonSerializable;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectDeletion;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindOptions;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindTarget;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectSaveOptions;
use Psr\Container\ContainerInterface;

/**
 * OpenRegister reads and writes for scheduled exports and their runs.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
 */
class ScheduledExportStore {

	public const REGISTER = 'launchpad';

	public const SCHEMA_EXPORT = 'scheduled_export';

	public const SCHEMA_RUN = 'scheduled_export_run';

	private const OBJECT_SERVICE = 'OCA\OpenRegister\Service\ObjectService';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Loads OpenRegister's ObjectService lazily, so LaunchPad boots without it.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
	) {
	}//end __construct()

	/**
	 * The objects of a schema matching equality filters.
	 *
	 * @param string               $schema  The schema slug.
	 * @param array<string, mixed> $filters Equality filters on the object's properties.
	 * @param bool                 $rbac    Apply the signed-in user's access rules.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function list(string $schema, array $filters=[], bool $rbac=true): array {
		$found = $this->objectService()->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: $schema,
			filters: $filters,
			_rbac: $rbac
		);
		if (is_array($found) === false) {
			return [];
		}

		$rows = [];
		foreach ($found as $item) {
			$row = self::normalise(item: $item);
			if ($row !== null) {
				$rows[] = $row;
			}
		}

		return $rows;
	}//end list()

	/**
	 * One object by uuid, or null when it does not exist or is not visible.
	 *
	 * @param string $schema The schema slug.
	 * @param string $uuid   The object uuid.
	 * @param bool   $rbac   Apply the signed-in user's access rules.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function find(string $schema, string $uuid, bool $rbac=true): ?array {
		$found = $this->objectService()->find(
			id: $uuid,
			options: new ObjectFindOptions(register: self::REGISTER, schema: $schema),
			_rbac: $rbac
		);

		return self::normalise(item: $found);
	}//end find()

	/**
	 * Create an object, or update the one with the given uuid.
	 *
	 * @param string               $schema The schema slug.
	 * @param array<string, mixed> $data   The object's properties.
	 * @param string|null          $uuid   The uuid to update, null to create.
	 * @param bool                 $rbac   Apply the signed-in user's access rules.
	 *
	 * @return array<string, mixed> The saved object.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function save(string $schema, array $data, ?string $uuid=null, bool $rbac=true): array {
		unset($data['id'], $data['@self']);
		$saved = $this->objectService()->saveObject(
			object: $data,
			options: new ObjectSaveOptions(
				target: new ObjectFindTarget(register: self::REGISTER, schema: $schema),
				uuid: $uuid
			),
			_rbac: $rbac
		);

		return self::normalise(item: $saved) ?? $data;
	}//end save()

	/**
	 * Soft-delete an object (OpenRegister's standard delete).
	 *
	 * @param string $schema The schema slug.
	 * @param string $uuid   The object uuid.
	 * @param bool   $rbac   Apply the signed-in user's access rules.
	 *
	 * @return bool Whether OpenRegister deleted it.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-3
	 */
	public function delete(string $schema, string $uuid, bool $rbac=true): bool {
		return $this->objectService()->deleteObject(
			deletion: new ObjectDeletion(
				uuid: $uuid,
				target: new ObjectFindTarget(register: self::REGISTER, schema: $schema)
			),
			_rbac: $rbac
		);
	}//end delete()

	/**
	 * OpenRegister's ObjectService.
	 *
	 * @return mixed
	 */
	private function objectService(): mixed {
		return $this->container->get(self::OBJECT_SERVICE);
	}//end objectService()

	/**
	 * An OpenRegister answer as the object's data with `id` and `@self.owner`.
	 *
	 * @param mixed $item An object entity, a serialised row, or nothing.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function normalise(mixed $item): ?array {
		if (is_object($item) === true && method_exists($item, 'getObject') === true) {
			$data = (array)$item->getObject();
			$self = (array)($data['@self'] ?? []);
			$self['id']    = $item->getUuid();
			$self['owner'] = $item->getOwner();
			$data['@self'] = $self;
			$data['id']    = $item->getUuid();
			return $data;
		}

		if ($item instanceof JsonSerializable) {
			$item = $item->jsonSerialize();
		}

		if (is_array($item) === false) {
			return null;
		}

		$self = (array)($item['@self'] ?? []);
		$id   = $item['id'] ?? $self['id'] ?? $item['uuid'] ?? null;
		if ($id !== null) {
			$item['id'] = (string)$id;
		}

		return $item;
	}//end normalise()
}//end class
