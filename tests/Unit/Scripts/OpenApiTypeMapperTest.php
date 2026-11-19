<?php

/**
 * Unit tests for the PHPDoc-to-schema mapping of the OpenAPI generator.
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

use OCA\LaunchPad\Scripts\OpenApiTypeMapper;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3).'/scripts/OpenApiTypeMapper.php';

/**
 * PHPDoc types become OpenAPI 3.1 schemas.
 *
 * @coversNothing
 */
class OpenApiTypeMapperTest extends TestCase {

	/**
	 * The mapper under test, knowing one alias and two status constants.
	 *
	 * @return OpenApiTypeMapper
	 */
	private function mapper(): OpenApiTypeMapper {
		return new OpenApiTypeMapper(aliases: ['Gadget' => true], statusCodes: ['STATUS_OK' => 200, 'STATUS_GONE' => 410]);
	}//end mapper()

	/**
	 * Scalars, nullable scalars and unknown names.
	 *
	 * @return void
	 */
	public function testScalarsAndNullables(): void {
		$mapper = $this->mapper();
		self::assertSame(['type' => 'integer'], $mapper->schemaOf(type: 'int'));
		self::assertSame(['type' => 'boolean'], $mapper->schemaOf(type: 'true'));
		self::assertSame(['type' => ['string', 'null']], $mapper->schemaOf(type: '?string'));
		self::assertSame(['type' => ['integer', 'null']], $mapper->schemaOf(type: 'int|null'));
		self::assertSame([], $mapper->schemaOf(type: 'mixed'));
		self::assertSame([], $mapper->schemaOf(type: 'Some\Entity'));
		self::assertSame([], $mapper->schemaOf(type: 'array{'));
	}//end testScalarsAndNullables()

	/**
	 * Lists are arrays, string-keyed arrays are maps, aliases are references.
	 *
	 * @return void
	 */
	public function testCollectionsAndAliases(): void {
		$mapper = $this->mapper();
		self::assertEquals(['type' => 'array', 'items' => (object) ['$ref' => '#/components/schemas/Gadget']], $mapper->schemaOf(type: 'list<Gadget>'));
		self::assertEquals(['type' => 'array', 'items' => (object) ['type' => 'integer']], $mapper->schemaOf(type: 'int[]'));
		self::assertEquals(['type' => 'object', 'additionalProperties' => (object) []], $mapper->schemaOf(type: 'array<string, mixed>'));
		self::assertEquals(['type' => 'array', 'items' => (object) ['type' => 'string']], $mapper->schemaOf(type: 'array<int, string>'));
		self::assertEquals(
			['anyOf' => [['$ref' => '#/components/schemas/Gadget'], ['type' => 'null']]],
			$mapper->schemaOf(type: 'Gadget|null')
		);
	}//end testCollectionsAndAliases()

	/**
	 * A shape lists its keys; optional keys are not required; `...` leaves it open.
	 *
	 * @return void
	 */
	public function testShapes(): void {
		$mapper = $this->mapper();
		self::assertEquals(
			[
				'type'                 => 'object',
				'properties'           => ['id' => (object) ['type' => 'integer'], 'note' => (object) ['type' => ['string', 'null']]],
				'required'             => ['id'],
				'additionalProperties' => false,
			],
			$mapper->schemaOf(type: 'array{id: int, note?: string|null}')
		);
		self::assertTrue($mapper->schemaOf(type: 'array{id: int, ...}')['additionalProperties']);
		self::assertEquals(['type' => 'string', 'const' => 'gone'], $mapper->schemaOf(type: "'gone'"));
	}//end testShapes()

	/**
	 * A status argument is read from its constant or its number.
	 *
	 * @return void
	 */
	public function testStatusOf(): void {
		$mapper = $this->mapper();
		self::assertSame(410, $mapper->statusOf(node: $mapper->parse(type: 'Http::STATUS_GONE')));
		self::assertSame(204, $mapper->statusOf(node: $mapper->parse(type: '204')));
		self::assertNull($mapper->statusOf(node: $mapper->parse(type: 'Http::STATUS_*')));
		self::assertNull($mapper->statusOf(node: $mapper->parse(type: 'int')));
	}//end testStatusOf()
}//end class
