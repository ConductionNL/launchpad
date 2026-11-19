<?php
/**
 * generate-openapi.php
 *
 * `composer openapi:generate` (governance-rest-api-reference D1): writes
 * openapi.json (OpenAPI 3.1) from appinfo/routes.php, the controllers in
 * lib/Controller, the `@psalm-type` response shapes in
 * lib/ResponseDefinitions.php and the hand-written parts in
 * scripts/openapi-overlay.json. With `--stdout` it prints the document
 * instead; `composer lint:openapi` compares that with the committed file.
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
 * @spec openspec/changes/governance-rest-api-reference/design.md
 */

declare(strict_types=1);

use OCA\LaunchPad\Scripts\OpenApiDocument;

$root = dirname(__DIR__);
require_once $root.'/vendor/autoload.php';
require_once __DIR__.'/OpenApiDocument.php';

$encoded = OpenApiDocument::encodedFor(root: $root);
if (in_array('--stdout', $argv, true) === true) {
	echo $encoded;
	exit(0);
}

file_put_contents($root.'/openapi.json', $encoded);
echo "openapi:generate: wrote openapi.json\n";
