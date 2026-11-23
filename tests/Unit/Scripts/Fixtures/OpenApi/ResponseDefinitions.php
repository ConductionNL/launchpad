<?php

/**
 * Response type aliases for the OpenAPI generator tests; never loaded, only read.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Scripts\Fixtures\OpenApi;

/**
 * The aliases.
 *
 * @psalm-type FixtureGadget = array{
 *     id: int,
 *     name: string,
 *     colour?: string|null,
 *     ...
 * }
 *
 * @psalm-type FixtureCount = array{count: int}
 */
final class ResponseDefinitions {
}
