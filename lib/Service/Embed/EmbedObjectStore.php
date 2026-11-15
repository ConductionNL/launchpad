<?php

/**
 * EmbedObjectStore
 *
 * Reads and writes the embed objects (`embed_token`, `tenant_theme`,
 * `embed_usage_event`, `signing_key`) in the launchpad register through
 * OpenRegister's published object service. OpenRegister is optional: the
 * service is looked up by name when first needed, so an instance without it
 * boots, and every call then fails with one clear exception.
 *
 * Access is decided by LaunchPad before a call reaches the store (token
 * management is admin-only, the render route checks the token), so the store
 * reads and writes as the system: no register RBAC, no multitenancy.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Embed
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\Embed;

use JsonSerializable;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

/**
 * The launchpad register's embed objects, as plain arrays with an `id`.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
 */
class EmbedObjectStore {

	/**
	 * The register every embed schema lives in.
	 *
	 * @var string
	 */
	public const REGISTER = 'launchpad';

	/**
	 * Embed tokens.
	 *
	 * @var string
	 */
	public const TOKENS = 'embed_token';

	/**
	 * Tenant themes.
	 *
	 * @var string
	 */
	public const THEMES = 'tenant_theme';

	/**
	 * Usage events.
	 *
	 * @var string
	 */
	public const USAGE = 'embed_usage_event';

	/**
	 * Signing keys.
	 *
	 * @var string
	 */
	public const KEYS = 'signing_key';

	/**
	 * OpenRegister's object service, by name (it may not be installed).
	 *
	 * @var string
	 */
	private const SERVICE = 'OCA\\OpenRegister\\Service\\ObjectService';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves OpenRegister's object service.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
	) {
	}//end __construct()

	/**
	 * Save an object: created when it has no `id`, updated when it has one.
	 *
	 * @param string               $schema The schema slug.
	 * @param array<string, mixed> $object The object.
	 *
	 * @return array<string, mixed> The stored object with its `id`.
	 *
	 * @throws RuntimeException When OpenRegister is unavailable or the save fails.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public function save(string $schema, array $object): array {
		$service = $this->service(schema: $schema);
		try {
			$saved = $service->saveObject(object: $object, _rbac: false, _multitenancy: false);
		} catch (Throwable $failure) {
			throw new RuntimeException(message: 'The ' . $schema . ' object could not be saved.', previous: $failure);
		}

		return self::toArray(entity: $saved) ?? throw new RuntimeException(message: 'The ' . $schema . ' object could not be saved.');
	}//end save()

	/**
	 * One object by id, or null when there is none.
	 *
	 * @param string $schema The schema slug.
	 * @param string $id     The object id.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @throws RuntimeException When OpenRegister is unavailable.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public function find(string $schema, string $id): ?array {
		if ($id === '') {
			return null;
		}

		$service = $this->service(schema: $schema);
		try {
			$entity = $service->find(id: $id, _rbac: false, _multitenancy: false);
		} catch (Throwable) {
			return null;
		}

		return self::toArray(entity: $entity);
	}//end find()

	/**
	 * Every object of a schema whose fields equal the filters.
	 *
	 * @param string               $schema  The schema slug.
	 * @param array<string, mixed> $filters Field => value.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @throws RuntimeException When OpenRegister is unavailable.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-2
	 */
	public function findBy(string $schema, array $filters=[]): array {
		$service = $this->service(schema: $schema);
		$rows    = $service->searchObjectsBySlug(
			registerSlug: self::REGISTER,
			schemaSlug: $schema,
			filters: $filters,
			_rbac: false,
			_multitenancy: false
		);
		if (is_array($rows) === false) {
			return [];
		}

		if (isset($rows['results']) === true && is_array($rows['results']) === true) {
			$rows = $rows['results'];
		}

		$objects = [];
		foreach ($rows as $row) {
			$object = self::toArray(entity: $row);
			if ($object !== null) {
				$objects[] = $object;
			}
		}

		return $objects;
	}//end findBy()

	/**
	 * The object service, pointed at the launchpad register and a schema.
	 *
	 * @param string $schema The schema slug.
	 *
	 * @return \OCA\OpenRegister\Contract\ObjectServiceInterface
	 *
	 * @throws RuntimeException When OpenRegister is unavailable.
	 */
	private function service(string $schema): object {
		try {
			$service = $this->container->get(self::SERVICE);
		} catch (Throwable $failure) {
			throw new RuntimeException(message: 'Embeds need OpenRegister, which is not available.', previous: $failure);
		}

		if (is_object($service) === false) {
			throw new RuntimeException(message: 'Embeds need OpenRegister, which is not available.');
		}

		return $service->setRegister(register: self::REGISTER)->setSchema(schema: $schema);
	}//end service()

	/**
	 * An entity or array as a plain object with its `id`; null for anything else.
	 *
	 * @param mixed $entity What the object service returned.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function toArray(mixed $entity): ?array {
		$id = null;
		if (is_object($entity) === true && method_exists($entity, 'getObject') === true) {
			if (method_exists($entity, 'getUuid') === true) {
				$id = $entity->getUuid();
			}

			$entity = $entity->getObject();
		} else if ($entity instanceof JsonSerializable) {
			$entity = $entity->jsonSerialize();
		}

		if (is_array($entity) === false) {
			return null;
		}

		$id = ($id ?? $entity['id'] ?? $entity['@self']['id'] ?? $entity['uuid'] ?? null);
		if (is_string($id) === false || $id === '') {
			return null;
		}

		unset($entity['@self']);
		$entity['id'] = $id;

		return $entity;
	}//end toArray()
}//end class
