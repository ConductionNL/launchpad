<?php

/**
 * Unit tests for the regeneration half of `composer lint:openapi` (governance-rest-api-reference D3).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Scripts
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

namespace OCA\LaunchPad\Tests\Unit\Scripts;

use OCA\LaunchPad\Scripts\OpenApiDocument;
use OCA\LaunchPad\Scripts\OpenApiDrift;
use OCA\LaunchPad\Scripts\OpenApiGenerator;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3).'/scripts/OpenApiDocument.php';
require_once dirname(__DIR__, 3).'/scripts/OpenApiDrift.php';

/**
 * The committed document must be exactly what the generator writes.
 *
 * @coversNothing
 */
class OpenApiDriftTest extends TestCase {

	/**
	 * An identical document passes.
	 *
	 * @return void
	 */
	public function testIdenticalDocumentsPass(): void {
		self::assertSame([], OpenApiDrift::problems(committed: "{}\n", generated: "{}\n"));
	}//end testIdenticalDocumentsPass()

	/**
	 * A document that was not regenerated fails, naming the operation that changed.
	 *
	 * @return void
	 */
	public function testStaleDocumentFailsNamingTheOperation(): void {
		$fresh = ['openapi' => '3.1.0', 'paths' => ['/gadgets' => ['get' => ['summary' => 'New'], 'post' => ['summary' => 'Same']]]];
		$stale = ['openapi' => '3.1.0', 'paths' => ['/gadgets' => ['get' => ['summary' => 'Old'], 'post' => ['summary' => 'Same']]]];

		$problems = OpenApiDrift::problems(committed: OpenApiGenerator::encode(document: $stale), generated: OpenApiGenerator::encode(document: $fresh));

		self::assertCount(2, $problems);
		self::assertStringContainsString('composer openapi:generate', $problems[0]);
		self::assertSame('  changed: GET /gadgets', $problems[1]);
	}//end testStaleDocumentFailsNamingTheOperation()

	/**
	 * A changed section outside the paths is named too, and long lists are cut.
	 *
	 * @return void
	 */
	public function testChangedSectionsAndLongLists(): void {
		$paths = [];
		for ($i = 0; $i < 12; $i++) {
			$paths['/p'.$i] = ['get' => ['summary' => 'a']];
		}

		$changed = OpenApiDrift::changedParts(committed: ['info' => ['title' => 'A'], 'paths' => $paths], generated: ['info' => ['title' => 'B'], 'paths' => []]);
		self::assertSame('info', $changed[0]);
		self::assertCount(13, $changed);

		$problems = OpenApiDrift::problems(
			committed: OpenApiGenerator::encode(document: ['paths' => $paths]),
			generated: OpenApiGenerator::encode(document: ['paths' => []])
		);
		self::assertSame('  ... and 2 more', end($problems));
	}//end testChangedSectionsAndLongLists()

	/**
	 * The committed openapi.json is current, OpenAPI 3.1, and uses no 3.0-only `nullable`.
	 *
	 * @return void
	 */
	public function testTheCommittedDocumentIsCurrent(): void {
		$root      = dirname(__DIR__, 3);
		$committed = (string) file_get_contents($root.'/openapi.json');

		self::assertSame([], OpenApiDrift::problems(committed: $committed, generated: OpenApiDocument::encodedFor(root: $root)));
		self::assertSame('3.1.0', json_decode($committed, true)['openapi']);
		self::assertStringNotContainsString('"nullable"', $committed);
	}//end testTheCommittedDocumentIsCurrent()

	/**
	 * Empty JSON objects survive decoding, so the overlay writes `{}` back, not `[]`.
	 *
	 * @return void
	 */
	public function testDecodedKeepsEmptyObjects(): void {
		$decoded = OpenApiDocument::decoded(value: json_decode('{"a": {}, "b": [], "c": {"d": 1}}', false));

		self::assertSame('{"a":{},"b":[],"c":{"d":1}}', json_encode($decoded));
	}//end testDecodedKeepsEmptyObjects()
}//end class
