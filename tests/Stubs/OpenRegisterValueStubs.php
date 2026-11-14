<?php

/**
 * OpenRegister request value-object stubs for unit tests.
 *
 * `ExportObjectStore` builds OpenRegister's ObjectService option objects by
 * class NAME, so LaunchPad runs without OpenRegister installed. Unit tests
 * still need the symbols to instantiate. The `class_exists` guards make this
 * a no-op where the real OpenRegister is loaded.
 *
 * The constructor parameters used here mirror
 * `openregister/lib/Kernel/Contracts/Value/` (ObjectFindOptions,
 * ObjectFindTarget, ObjectSaveOptions, ObjectDeletion) as of 2026-11-14.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Kernel\Contracts\Value;

if (class_exists(ObjectFindTarget::class) === false) {
	/**
	 * Stub: the register and schema an operation targets.
	 */
	final class ObjectFindTarget {
		/**
		 * @param string|int|null $register The register.
		 * @param string|int|null $schema   The schema.
		 */
		public function __construct(
			public readonly string|int|null $register = null,
			public readonly string|int|null $schema = null,
		) {
		}
	}
}

if (class_exists(ObjectFindOptions::class) === false) {
	/**
	 * Stub: the options of a find.
	 */
	final class ObjectFindOptions {
		/**
		 * @param string|int|null $register The register.
		 * @param string|int|null $schema   The schema.
		 */
		public function __construct(
			public readonly string|int|null $register = null,
			public readonly string|int|null $schema = null,
		) {
		}
	}
}

if (class_exists(ObjectSaveOptions::class) === false) {
	/**
	 * Stub: the options of a save.
	 */
	final class ObjectSaveOptions {
		/**
		 * @param ObjectFindTarget $target The register and schema.
		 * @param string|null      $uuid   The object to update.
		 */
		public function __construct(
			public readonly ObjectFindTarget $target = new ObjectFindTarget(),
			public readonly ?string $uuid = null,
		) {
		}
	}
}

if (class_exists(ObjectDeletion::class) === false) {
	/**
	 * Stub: what a delete removes.
	 */
	final class ObjectDeletion {
		/**
		 * @param string $uuid The object.
		 */
		public function __construct(
			public readonly string $uuid,
		) {
		}
	}
}
