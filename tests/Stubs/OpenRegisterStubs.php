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
 * `openregister/lib/AppHost/Service/StoreDescriptor.php` and
 * `openregister/lib/AppHost/Service/GenericStoreService.php` as of 2026-09-10.
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\AppHost\Service {
	if (class_exists(__NAMESPACE__ . '\\StoreListing', false) === false) {
		/**
		 * Stub of OpenRegister's StoreListing: where a store reads from and how a card is built.
		 */
		final class StoreListing {
			/**
			 * Constructor.
			 *
			 * @param string                $appId           App whose IAppConfig holds the registry connection.
			 * @param string                $schema          Remote schema slug.
			 * @param string                $defaultRegister Remote register used when `registry_register` is empty.
			 * @param array<string, string> $cardFields      Card field => remote property.
			 *
			 * @return void
			 */
			public function __construct(
				public readonly string $appId,
				public readonly string $schema,
				public readonly string $defaultRegister,
				public readonly array $cardFields = [
					'slug' => 'slug',
					'title' => 'title',
					'description' => 'description',
					'category' => 'category',
					'version' => 'version',
				],
			) {
			}//end __construct()
		}//end class
	}

	if (class_exists(__NAMESPACE__ . '\\StoreSharing', false) === false) {
		/**
		 * Stub of OpenRegister's StoreSharing: shareable types and the publish opt-in.
		 */
		final class StoreSharing {
			/**
			 * Constructor.
			 *
			 * @param array<int, string> $types         Shareable configuration type ids.
			 * @param array<int, string> $publishFields Remote properties a publish may send next to the slug.
			 * @param array<int, string> $publishGroups Nextcloud groups whose members may publish.
			 *
			 * @return void
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
		 * Stub mirroring the engine's immutable descriptor value object.
		 */
		final class StoreDescriptor {
			/** @var string */
			public readonly string $appId;

			/** @var string */
			public readonly string $schema;

			/** @var string */
			public readonly string $defaultRegister;

			/** @var array<string, string> */
			public readonly array $cardFields;

			/** @var array<int, string> */
			public readonly array $types;

			/** @var array<int, string> */
			public readonly array $publishFields;

			/** @var array<int, string> */
			public readonly array $publishGroups;

			/**
			 * Constructor.
			 *
			 * Mirrors OpenRegister: built from a listing part and a sharing part, read
			 * through the same flat readonly properties as before.
			 *
			 * @param StoreListing $listing Registry connection app, remote schema and register, card field map.
			 * @param StoreSharing $sharing Shareable types and the publish opt-in.
			 *
			 * @return void
			 */
			public function __construct(
				public readonly StoreListing $listing,
				public readonly StoreSharing $sharing = new StoreSharing(),
			) {
				$this->appId           = $listing->appId;
				$this->schema          = $listing->schema;
				$this->defaultRegister = $listing->defaultRegister;
				$this->cardFields      = $listing->cardFields;
				$this->types           = $sharing->types;
				$this->publishFields   = $sharing->publishFields;
				$this->publishGroups   = $sharing->publishGroups;
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
		}//end class
	}
}
