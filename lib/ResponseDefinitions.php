<?php

/**
 * ResponseDefinitions
 *
 * The JSON shapes LaunchPad's public API answers with, as psalm types.
 * Controllers import them in `@psalm-return`; scripts/generate-openapi.php
 * turns each into a schema of openapi.json.
 *
 * @category  Api
 * @package   OCA\LaunchPad
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

namespace OCA\LaunchPad;

/**
 * Holds the psalm response types; never instantiated. *
 * @psalm-type LaunchPadTile = array{
 *     id: int,
 *     userId: string,
 *     title: string,
 *     icon: string,
 *     iconType: string,
 *     backgroundColor: string,
 *     textColor: string,
 *     linkType: string,
 *     linkValue: string,
 *     createdAt: string,
 *     updatedAt: string,
 * }
 *
 * @spec openspec/changes/governance-rest-api-reference/tasks.md#task-2
 */
final class ResponseDefinitions {
}//end class
