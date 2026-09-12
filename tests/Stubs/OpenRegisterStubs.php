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
	if (class_exists(__NAMESPACE__ . '\\StoreDescriptor', false) === false) {
		/**
		 * Stub mirroring the engine's immutable descriptor value object.
		 */
		final class StoreDescriptor {
			/**
			 * Constructor.
			 *
			 * @param string                $appId           App holding the registry config.
			 * @param string                $schema          Remote schema slug.
			 * @param string                $defaultRegister Register used when config is empty.
			 * @param array<string, string> $cardFields      Card field to remote property.
			 * @param array<int, string>    $types           Shareable configuration type ids.
			 */
			public function __construct(
				public readonly string $appId,
				public readonly string $schema,
				public readonly string $defaultRegister,
				public readonly array $cardFields = [],
				public readonly array $types = [],
			) {
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
