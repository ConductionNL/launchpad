<?php

/**
 * Stand-ins for OpenRegister's save option values, for tests that build them without OpenRegister installed.
 *
 * Declared only when the real classes are absent; the constructors take the
 * same named arguments the published contract does.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-3.1
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Kernel\Contracts\Value {

	if (class_exists(ObjectFindTarget::class, false) === false) {
		final class ObjectFindTarget {
			public function __construct(
				public readonly string|int|null $register=null,
				public readonly string|int|null $schema=null,
			) {
			}
		}
	}

	if (class_exists(ObjectSaveOptions::class, false) === false) {
		final class ObjectSaveOptions {
			public function __construct(
				public readonly ?ObjectFindTarget $target=null,
				public readonly ?string $uuid=null,
			) {
			}
		}
	}
}
