<?php

/**
 * Unit tests for the MCP argument validator.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */

declare(strict_types=1);

namespace Unit\Mcp;

use OCA\LaunchPad\Mcp\McpArgumentValidator;
use PHPUnit\Framework\TestCase;

/**
 * Each check refuses with a structured error and accepts a valid value.
 *
 * @covers \OCA\LaunchPad\Mcp\McpArgumentValidator
 */
class McpArgumentValidatorTest extends TestCase {

	/**
	 * A malformed or missing UUID is refused; a UUID is accepted.
	 *
	 * @return void
	 */
	public function testUuid(): void {
		$validator = new McpArgumentValidator();

		$this->assertSame('invalid_arguments', $validator->uuid(['d' => 'not-a-uuid'], 'd')['error']);
		$this->assertNotNull($validator->uuid([], 'd'));
		$this->assertNull($validator->uuid(['d' => '123e4567-e89b-42d3-a456-426614174000'], 'd'));
	}//end testUuid()

	/**
	 * An over-long or missing string is refused, an absent optional one is not.
	 *
	 * @return void
	 */
	public function testStringLength(): void {
		$validator = new McpArgumentValidator();

		$this->assertSame(
			['isError' => true, 'error' => 'invalid_arguments', 'message' => 'Argument title must be a string of 1 to 5 characters.'],
			$validator->string(['title' => 'too long'], 'title', 1, 5)
		);
		$this->assertNotNull($validator->string([], 'title', 1, 5));
		$this->assertNull($validator->string([], 'title', 1, 5, false));
		$this->assertNull($validator->string(['title' => 'ok'], 'title', 1, 5));
	}//end testStringLength()

	/**
	 * A value outside the enum is refused.
	 *
	 * @return void
	 */
	public function testEnum(): void {
		$validator = new McpArgumentValidator();

		$this->assertStringContainsString('view, edit', $validator->oneOf(['level' => 'own'], 'level', ['view', 'edit'])['message']);
		$this->assertNull($validator->oneOf(['level' => 'edit'], 'level', ['view', 'edit']));
		$this->assertNull($validator->oneOf([], 'level', ['view'], false));
	}//end testEnum()

	/**
	 * Negative grid values and non-integers are refused; values in range pass.
	 *
	 * @return void
	 */
	public function testGridBounds(): void {
		$validator = new McpArgumentValidator();

		$this->assertSame('Argument gridX must be an integer from 0 to 1000.', $validator->grid(['gridX' => -1])['message']);
		$this->assertNotNull($validator->grid(['gridWidth' => 0]));
		$this->assertNotNull($validator->grid(['gridHeight' => '3']));
		$this->assertNull($validator->grid(['gridX' => 0, 'gridY' => 4, 'gridWidth' => 6, 'gridHeight' => 3]));
	}//end testGridBounds()

	/**
	 * A positive integer id passes, zero and strings do not.
	 *
	 * @return void
	 */
	public function testId(): void {
		$validator = new McpArgumentValidator();

		$this->assertNull($validator->id(['id' => 7], 'id'));
		$this->assertNotNull($validator->id(['id' => 0], 'id'));
		$this->assertNotNull($validator->id(['id' => '7'], 'id'));
	}//end testId()
}//end class
