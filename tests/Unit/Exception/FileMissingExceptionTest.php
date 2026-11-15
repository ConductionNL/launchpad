<?php

/**
 * FileMissingExceptionTest
 *
 * Unit tests for FileMissingException: the error code, HTTP status and
 * message a missing upload answers with.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Exception
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Exception;

use OCA\LaunchPad\Exception\FileMissingException;
use OCA\LaunchPad\Exception\ResourceException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FileMissingException.
 */
#[CoversClass(FileMissingException::class)]
#[UsesClass(ResourceException::class)]
class FileMissingExceptionTest extends TestCase {
	/**
	 * Without a message it answers 400 `no_file` with the default text.
	 *
	 * @return void
	 */
	public function testDefaults(): void {
		$exception = new FileMissingException();

		$this->assertInstanceOf(ResourceException::class, $exception);
		$this->assertSame('no_file', $exception->getErrorCode());
		$this->assertSame(400, $exception->getHttpStatus());
		$this->assertSame('No file was uploaded', $exception->getMessage());
		$this->assertSame('No file was uploaded', $exception->getDisplayMessage());
	}//end testDefaults()

	/**
	 * A given message is shown; code and status stay.
	 *
	 * @return void
	 */
	public function testCustomMessage(): void {
		$exception = new FileMissingException(message: 'Pick a file first');

		$this->assertSame('Pick a file first', $exception->getDisplayMessage());
		$this->assertSame('no_file', $exception->getErrorCode());
		$this->assertSame(400, $exception->getHttpStatus());
	}//end testCustomMessage()
}//end class
