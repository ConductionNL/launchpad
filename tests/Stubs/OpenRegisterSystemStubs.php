<?php

/**
 * OpenRegister system-scope and validation stubs for unit tests.
 *
 * `ExportObjectStore::saveAsSystem()` enters OpenRegister's
 * `SystemOperationContext` by class NAME, and `ScheduledExportController`
 * recognises OpenRegister's `ValidationException` by class NAME, so LaunchPad
 * runs without OpenRegister installed. The `class_exists` guards make this a
 * no-op where the real OpenRegister is loaded.
 *
 * The members used here mirror
 * `openregister/lib/Service/SystemOperationContext.php` (run, isActive) and
 * `openregister/lib/Exception/ValidationException.php` as of 2026-11-20.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-8
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Service {

	if (class_exists(SystemOperationContext::class) === false) {
		/**
		 * Stub: a scope in which code-initiated writes may stamp readOnly properties.
		 */
		final class SystemOperationContext {

			/**
			 * @var integer Nesting depth of active scopes.
			 */
			private static int $depth = 0;

			/**
			 * Run a callable inside the scope.
			 *
			 * @param callable $operation The operation.
			 *
			 * @return mixed What it returns.
			 */
			public static function run(callable $operation) {
				self::$depth++;
				try {
					return $operation();
				} finally {
					self::$depth--;
				}
			}

			/**
			 * Whether a scope is active.
			 *
			 * @return bool
			 */
			public static function isActive(): bool {
				return self::$depth > 0;
			}
		}
	}
}

namespace OCA\OpenRegister\Exception {

	if (class_exists(ValidationException::class) === false) {
		/**
		 * Stub: OpenRegister's refusal of an object that breaks its schema.
		 */
		class ValidationException extends \Exception {
		}
	}
}
