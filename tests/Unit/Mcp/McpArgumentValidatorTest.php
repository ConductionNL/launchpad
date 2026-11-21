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
use OCA\LaunchPad\Exception\ForbiddenException;
use OCP\AppFramework\Db\DoesNotExistException;

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

		$this->assertSame(expected: 'invalid_arguments', actual: $validator->uuid(['d' => 'not-a-uuid'], 'd')['error']);
		$this->assertNotNull(actual: $validator->uuid([], 'd'));
		$this->assertNull(actual: $validator->uuid(['d' => '123e4567-e89b-42d3-a456-426614174000'], 'd'));
	}//end testUuid()

	/**
	 * An over-long or missing string is refused, an absent optional one is not.
	 *
	 * @return void
	 */
	public function testStringLength(): void {
		$validator = new McpArgumentValidator();

		$this->assertSame(
			expected: ['isError' => true, 'error' => 'invalid_arguments', 'message' => 'Argument title must be a string of 1 to 5 characters.'],
			actual: $validator->string(['title' => 'too long'], 'title', 1, 5)
		);
		$this->assertNotNull(actual: $validator->string([], 'title', 1, 5));
		$this->assertNull(actual: $validator->optional([], 'title', $validator->string([], 'title', 1, 5)));
		$this->assertNotNull(actual: $validator->optional(['title' => 'too long'], 'title', $validator->string(['title' => 'too long'], 'title', 1, 5)));
		$this->assertNull(actual: $validator->optional(['title' => 'ok'], 'title', $validator->string(['title' => 'ok'], 'title', 1, 5)));
		$this->assertNull(actual: $validator->string(['title' => 'ok'], 'title', 1, 5));
	}//end testStringLength()

	/**
	 * A value outside the enum is refused.
	 *
	 * @return void
	 */
	public function testEnum(): void {
		$validator = new McpArgumentValidator();

		$this->assertStringContainsString(needle: 'view, edit', haystack: $validator->oneOf(['level' => 'own'], 'level', ['view', 'edit'])['message']);
		$this->assertNull(actual: $validator->oneOf(['level' => 'edit'], 'level', ['view', 'edit']));
		$this->assertNotNull(actual: $validator->oneOf([], 'level', ['view']));
		$this->assertNull(actual: $validator->optional([], 'level', $validator->oneOf([], 'level', ['view'])));
		$this->assertNotNull(actual: $validator->optional(['level' => 'own'], 'level', $validator->oneOf(['level' => 'own'], 'level', ['view'])));
	}//end testEnum()

	/**
	 * Negative grid values and non-integers are refused; values in range pass.
	 *
	 * @return void
	 */
	public function testGridBounds(): void {
		$validator = new McpArgumentValidator();

		$this->assertSame(expected: 'Argument gridX must be an integer from 0 to 1000.', actual: $validator->grid(['gridX' => -1])['message']);
		$this->assertNotNull(actual: $validator->grid(['gridWidth' => 0]));
		$this->assertNotNull(actual: $validator->grid(['gridHeight' => '3']));
		$this->assertNull(actual: $validator->grid(['gridX' => 0, 'gridY' => 4, 'gridWidth' => 6, 'gridHeight' => 3]));
	}//end testGridBounds()

	/**
	 * A positive integer id passes, zero and strings do not.
	 *
	 * @return void
	 */
	public function testId(): void {
		$validator = new McpArgumentValidator();

		$this->assertNull(actual: $validator->positiveId(['id' => 7], 'id'));
		$this->assertNotNull(actual: $validator->positiveId(['id' => 0], 'id'));
		$this->assertNotNull(actual: $validator->positiveId(['id' => '7'], 'id'));
	}//end testId()

	/**
	 * refuse() is the error envelope, and failure() names a missing or
	 * forbidden target but keeps every other failure generic.
	 *
	 * @return void
	 */
	public function testRefuseAndFailureEnvelopes(): void {
		$validator = new McpArgumentValidator();

		$this->assertSame(
			expected: ['isError' => true, 'error' => 'forbidden', 'message' => 'No.'],
			actual: $validator->refuse(code: 'forbidden', message: 'No.')
		);
		$this->assertSame(expected: 'not_found', actual: $validator->failure(exception: new DoesNotExistException('gone'))['error']);
		$this->assertSame(expected: 'forbidden', actual: $validator->failure(exception: new ForbiddenException('not yours'))['error']);
		$this->assertSame(
			expected: ['isError' => true, 'error' => 'invalid_arguments', 'message' => 'Bad title'],
			actual: $validator->failure(exception: new \InvalidArgumentException('Bad title'))
		);
		$this->assertSame(
			expected: ['isError' => true, 'error' => 'failed', 'message' => 'The operation failed.'],
			actual: $validator->failure(exception: new \RuntimeException('SQLSTATE secret'))
		);
	}//end testRefuseAndFailureEnvelopes()
}//end class
