<?php

/**
 * StoreDescriptorBuilder
 *
 * Builds the engine's StoreDescriptor for LaunchPad's dashboard store: the
 * listing (this app, the dashboard-template schema, the card map) and the
 * sharing part (no configuration types; the publish fields and group,
 * REQ-STORE-013). Split from StoreService so that service keeps to the
 * complexity budget; the behaviour is unchanged.
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use RuntimeException;

/**
 * The engine descriptor for LaunchPad's store.
 *
 * @spec openspec/specs/dashboard-store/spec.md
 */
class StoreDescriptorBuilder {
	/**
	 * Fully-qualified name of the engine's descriptor value object.
	 *
	 * Held as a string so no `OCA\OpenRegister\…` symbol is touched at load
	 * time. Instantiated only after the injected client proved non-null.
	 */
	private const DESCRIPTOR_CLASS = 'OCA\\OpenRegister\\AppHost\\Service\\StoreDescriptor';

	/**
	 * The read half of the descriptor, in OpenRegister releases that split it
	 * into a listing and a sharing part. Absent in older releases.
	 */
	private const LISTING_CLASS = 'OCA\\OpenRegister\\AppHost\\Service\\StoreListing';

	/**
	 * The share half of the descriptor, next to {@see self::LISTING_CLASS}.
	 */
	private const SHARING_CLASS = 'OCA\\OpenRegister\\AppHost\\Service\\StoreSharing';

	/**
	 * Build the engine descriptor for this app's store.
	 *
	 * Only ever called once OpenRegister's store client proved non-null, which
	 * is what guarantees the OpenRegister class is loadable here.
	 *
	 * @return object The engine's StoreDescriptor.
	 *
	 * @throws RuntimeException When OpenRegister supplied a client but not a descriptor class.
	 *
	 * @psalm-suppress MixedMethodCall Instantiated from a class string by design.
	 *
	 * @spec openspec/changes/archive/2026-11-27-store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 */
	public function build(): object {
		$class = self::DESCRIPTOR_CLASS;
		if (class_exists($class) === false) {
			throw new RuntimeException(message: 'OpenRegister store descriptor is unavailable.');
		}

		// OpenRegister now builds the descriptor from a listing and a sharing
		// part (the two classes ship together); named arguments for the old
		// flat form are an Error there.
		if (class_exists(self::LISTING_CLASS) === true) {
			$listingClass = self::LISTING_CLASS;
			$sharingClass = self::SHARING_CLASS;
			return new $class(
				listing: new $listingClass(
					appId: Application::APP_ID,
					schema: StoreService::REMOTE_SCHEMA,
					defaultRegister: StoreService::DEFAULT_REGISTER,
					cardFields: StoreService::CARD_FIELDS
				),
				sharing: new $sharingClass(
					types: [],
					publishFields: StoreService::PUBLISH_FIELDS,
					publishGroups: StoreService::PUBLISH_GROUPS
				)
			);
		}

		return new $class(
			appId: Application::APP_ID,
			schema: StoreService::REMOTE_SCHEMA,
			defaultRegister: StoreService::DEFAULT_REGISTER,
			cardFields: StoreService::CARD_FIELDS,
			types: []
		);
	}//end build()
}//end class
