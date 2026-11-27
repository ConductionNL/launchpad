<?php

/**
 * OpenRegister AppHost store-plane stubs for unit tests.
 *
 * `StoreService` names OpenRegister's classes as STRINGS, so LaunchPad can run
 * without OpenRegister installed. Unit tests still need the two symbols to
 * exist: one to instantiate as a descriptor, one to build a test double from.
 *
 * The `class_exists` guards make this a no-op inside a Nextcloud container that
 * has the real OpenRegister loaded, so a test asserting against these stubs is
 * asserting against the real signatures there.
 *
 * The signatures mirror
 * `openregister/lib/AppHost/Service/StoreDescriptor.php` (with StoreListing and
 * StoreSharing, as of 2026-11-25) and
 * `openregister/lib/AppHost/Service/GenericStoreService.php` as of 2026-09-10.
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\AppHost\Service {
	if (class_exists(__NAMESPACE__ . '\\StoreListing', false) === false) {
		/**
		 * Stub mirroring the engine's read half of a store descriptor.
		 */
		final class StoreListing {
			/**
			 * Constructor.
			 *
			 * @param string                $appId           App holding the registry config.
			 * @param string                $schema          Remote schema slug.
			 * @param string                $defaultRegister Register used when config is empty.
			 * @param array<string, string> $cardFields      Card field to remote property.
			 */
			public function __construct(
				public readonly string $appId,
				public readonly string $schema,
				public readonly string $defaultRegister,
				public readonly array $cardFields = [],
			) {
			}//end __construct()
		}//end class
	}

	if (class_exists(__NAMESPACE__ . '\\StoreSharing', false) === false) {
		/**
		 * Stub mirroring the engine's share half of a store descriptor.
		 */
		final class StoreSharing {
			/**
			 * Constructor.
			 *
			 * @param array<int, string> $types         Shareable configuration type ids.
			 * @param array<int, string> $publishFields Fields that may leave this server.
			 * @param array<int, string> $publishGroups Groups that may publish.
			 */
			public function __construct(
				public readonly array $types = [],
				public readonly array $publishFields = [],
				public readonly array $publishGroups = [],
			) {
			}//end __construct()
		}//end class
	}

	if (class_exists(__NAMESPACE__ . '\\StoreDescriptor', false) === false) {
		/**
		 * Stub mirroring the engine's immutable descriptor value object, built
		 * from a listing and a sharing part.
		 */
		final class StoreDescriptor {
			/**
			 * App holding the registry config.
			 *
			 * @var string
			 */
			public readonly string $appId;

			/**
			 * Remote schema slug.
			 *
			 * @var string
			 */
			public readonly string $schema;

			/**
			 * Register used when config is empty.
			 *
			 * @var string
			 */
			public readonly string $defaultRegister;

			/**
			 * Card field to remote property.
			 *
			 * @var array<string, string>
			 */
			public readonly array $cardFields;

			/**
			 * Shareable configuration type ids.
			 *
			 * @var array<int, string>
			 */
			public readonly array $types;

			/**
			 * Fields that may leave this server.
			 *
			 * @var array<int, string>
			 */
			public readonly array $publishFields;

			/**
			 * Groups whose members may publish.
			 *
			 * @var array<int, string>
			 */
			public readonly array $publishGroups;

			/**
			 * Constructor.
			 *
			 * @param StoreListing $listing The read half.
			 * @param StoreSharing $sharing The share half.
			 */
			public function __construct(
				public readonly StoreListing $listing,
				public readonly StoreSharing $sharing = new StoreSharing(),
			) {
				$this->appId = $listing->appId;
				$this->schema = $listing->schema;
				$this->defaultRegister = $listing->defaultRegister;
				$this->cardFields = $listing->cardFields;
				$this->types = $sharing->types;
				$this->publishFields = $sharing->publishFields;
				$this->publishGroups = $sharing->publishGroups;
			}//end __construct()
		}//end class
	}

	if (class_exists(__NAMESPACE__ . '\\GenericStoreService', false) === false) {
		/**
		 * Stub mirroring the engine's guarded discovery client.
		 */
		class GenericStoreService {
			/**
			 * Search the remote store.
			 *
			 * @param StoreDescriptor $descriptor Store parameters.
			 * @param string|null     $query      Free-text term.
			 * @param string|null     $kind       Kind filter.
			 *
			 * @return array{outcome: string, cards: array<int, array<string, mixed>>}
			 */
			public function search(StoreDescriptor $descriptor, ?string $query = null, ?string $kind = null): array {
				return ['outcome' => 'not_configured', 'cards' => []];
			}//end search()

			/**
			 * Resolve one remote item by slug.
			 *
			 * @param StoreDescriptor $descriptor Store parameters.
			 * @param string          $slug       The item slug.
			 *
			 * @return array<string, mixed>|null
			 */
			public function resolve(StoreDescriptor $descriptor, string $slug): ?array {
				return null;
			}//end resolve()

			/**
			 * Publish one object to the remote store.
			 *
			 * @param StoreDescriptor      $descriptor Store parameters.
			 * @param array<string, mixed> $payload    The object, carrying its slug.
			 *
			 * @return array{outcome: string, slug: string}
			 */
			public function publish(StoreDescriptor $descriptor, array $payload): array {
				return ['outcome' => 'not_configured', 'slug' => ''];
			}//end publish()
		}//end class
	}
}

namespace OCA\OpenRegister\Kernel\Contracts\Value {
	if (class_exists(__NAMESPACE__ . '\\ObjectFindTarget', false) === false) {
		/**
		 * Stub mirroring the register and schema an object is saved under.
		 */
		final class ObjectFindTarget {
			/**
			 * Constructor.
			 *
			 * @param mixed $register The register (entity, slug or id).
			 * @param mixed $schema   The schema (entity, slug or id).
			 */
			public function __construct(
				public readonly mixed $register = null,
				public readonly mixed $schema = null,
			) {
			}//end __construct()
		}//end class
	}

	if (class_exists(__NAMESPACE__ . '\\ObjectSaveOptions', false) === false) {
		/**
		 * Stub mirroring the save options LaunchPad passes to ObjectService::saveObject().
		 */
		final class ObjectSaveOptions {
			/**
			 * Constructor.
			 *
			 * @param ObjectFindTarget $target The register and schema.
			 * @param string|null      $uuid   The object uuid.
			 * @param array|null       $extend Properties to extend.
			 */
			public function __construct(
				public readonly ObjectFindTarget $target = new ObjectFindTarget(),
				public readonly ?string $uuid = null,
				public readonly ?array $extend = [],
			) {
			}//end __construct()
		}//end class
	}

	if (class_exists(__NAMESPACE__ . '\\ObjectDeletion', false) === false) {
		/**
		 * Stub mirroring the delete LaunchPad passes to ObjectService::deleteObject().
		 */
		final class ObjectDeletion {
			/**
			 * Constructor.
			 *
			 * @param string           $uuid   The object uuid.
			 * @param ObjectFindTarget $target The register and schema.
			 */
			public function __construct(
				public readonly string $uuid,
				public readonly ObjectFindTarget $target = new ObjectFindTarget(),
			) {
			}//end __construct()
		}//end class
	}
}

namespace OCA\OpenRegister\Contract {
	if (class_exists(__NAMESPACE__ . '\\RegisterSchemaSlugs', false) === false) {
		/**
		 * Stub mirroring the register and schema slugs of a slug search.
		 */
		final class RegisterSchemaSlugs {
			/**
			 * Constructor.
			 *
			 * @param string $register The register slug.
			 * @param string $schema   The schema slug.
			 */
			public function __construct(
				public readonly string $register,
				public readonly string $schema,
			) {
			}//end __construct()
		}//end class
	}
}
