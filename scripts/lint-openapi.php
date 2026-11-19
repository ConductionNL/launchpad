<?php
/**
 * lint-openapi.php
 *
 * `composer lint:openapi` (governance-rest-api-reference D3): fails when a
 * versioned API route in appinfo/routes.php has no operation in openapi.json,
 * when an operation has no x-launchpad-stability, or when an operation is
 * documented for a route that does not exist. Prints one line per problem.
 *
 * @category  Tooling
 * @package   OCA\LaunchPad\Scripts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/governance-rest-api-reference/specs/rest-api-reference/spec.md
 */

declare(strict_types=1);

use OCA\LaunchPad\Scripts\OpenApiRouteCoverage;

$root = dirname(__DIR__);
require_once $root.'/vendor/autoload.php';
require_once __DIR__.'/OpenApiRouteCoverage.php';

$document = json_decode((string) file_get_contents($root.'/openapi.json'), true);
if (is_array($document) === false) {
	fwrite(STDERR, "lint:openapi: openapi.json is missing or not valid JSON\n");
	exit(1);
}

$routes   = require $root.'/appinfo/routes.php';
$problems = OpenApiRouteCoverage::problems(routes: $routes, document: $document);
if ($problems !== []) {
	fwrite(STDERR, implode("\n", $problems)."\n");
	fwrite(STDERR, sprintf("lint:openapi: %d problem(s)\n", count($problems)));
	exit(1);
}

echo sprintf("lint:openapi: %d operations, every API route documented\n", count(OpenApiRouteCoverage::operations(document: $document)));
