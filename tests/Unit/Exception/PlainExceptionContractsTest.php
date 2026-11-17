<?php

/**
 * PlainExceptionContractsTest
 *
 * Pins the contract of the typed exceptions that carry no data of their own:
 * the stable error codes the controllers copy into the JSON error body
 * (lock, metadata), and the class hierarchy the callers rely on when they
 * catch or `instanceof`-check a whole family (embed tokens, demo showcases,
 * shipped templates). Renaming a code changes what an API client sees;
 * moving a class out of its family makes an existing catch block miss it.
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

use Exception;
use OCA\LaunchPad\Exception\EmbedTokenException;
use OCA\LaunchPad\Exception\InvalidMetadataFieldException;
use OCA\LaunchPad\Exception\InvalidTokenException;
use OCA\LaunchPad\Exception\LockForbiddenException;
use OCA\LaunchPad\Exception\LockNotFoundException;
use OCA\LaunchPad\Exception\ResourceException;
use OCA\LaunchPad\Exception\ShowcaseNotFoundException;
use OCA\LaunchPad\Exception\TemplateNotInstalledException;
use OCA\LaunchPad\Exception\TokenExpiredException;
use OCA\LaunchPad\Exception\TokenRevokedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Error codes and class families of the data-less exceptions.
 */
#[CoversClass(EmbedTokenException::class)]
#[CoversClass(InvalidTokenException::class)]
#[CoversClass(TokenExpiredException::class)]
#[CoversClass(TokenRevokedException::class)]
#[CoversClass(LockForbiddenException::class)]
#[CoversClass(LockNotFoundException::class)]
#[CoversClass(InvalidMetadataFieldException::class)]
#[CoversClass(ShowcaseNotFoundException::class)]
#[CoversClass(TemplateNotInstalledException::class)]
class PlainExceptionContractsTest extends TestCase {

	/**
	 * The error codes the lock and metadata controllers put in the body.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function errorCodeProvider(): array {
		return [
			'lock forbidden'         => [LockForbiddenException::ERROR_CODE, 'lock_forbidden'],
			'lock not found'         => [LockNotFoundException::ERROR_CODE, 'lock_not_found'],
			'invalid metadata field' => [InvalidMetadataFieldException::ERROR_CODE, 'invalid_metadata_field'],
		];
	}//end errorCodeProvider()

	/**
	 * Each code keeps its exact wire value.
	 *
	 * @param string $actual   The class constant.
	 * @param string $expected The value API clients match on.
	 *
	 * @return void
	 */
	#[DataProvider('errorCodeProvider')]
	public function testErrorCodeKeepsItsWireValue(string $actual, string $expected): void {
		$this->assertSame($expected, $actual);
	}//end testErrorCodeKeepsItsWireValue()

	/**
	 * The three token failures.
	 *
	 * @return array<string, array{class-string<EmbedTokenException>}>
	 */
	public static function tokenFailureProvider(): array {
		return [
			'invalid' => [InvalidTokenException::class],
			'expired' => [TokenExpiredException::class],
			'revoked' => [TokenRevokedException::class],
		];
	}//end tokenFailureProvider()

	/**
	 * A token failure is caught as an EmbedTokenException, keeps its message
	 * and previous exception, and is not one of the HTTP-mapped resource errors.
	 *
	 * @param string $class The token exception class.
	 *
	 * @return void
	 */
	#[DataProvider('tokenFailureProvider')]
	public function testTokenFailureBelongsToTheEmbedTokenFamily(string $class): void {
		$previous  = new Exception('decode failed');
		$exception = new $class('The token is not a signed JWT.', 7, $previous);

		try {
			throw $exception;
		} catch (EmbedTokenException $caught) {
			$this->assertSame($exception, $caught);
		}

		$this->assertInstanceOf(RuntimeException::class, $exception);
		$this->assertNotInstanceOf(ResourceException::class, $exception);
		$this->assertSame('The token is not a signed JWT.', $exception->getMessage());
		$this->assertSame(7, $exception->getCode());
		$this->assertSame($previous, $exception->getPrevious());
	}//end testTokenFailureBelongsToTheEmbedTokenFamily()

	/**
	 * The three token failures are distinct: catching one does not catch another.
	 *
	 * @return void
	 */
	public function testTokenFailuresAreDistinct(): void {
		$this->assertNotInstanceOf(TokenExpiredException::class, new InvalidTokenException());
		$this->assertNotInstanceOf(TokenRevokedException::class, new TokenExpiredException());
		$this->assertNotInstanceOf(InvalidTokenException::class, new TokenRevokedException());
	}//end testTokenFailuresAreDistinct()

	/**
	 * The catalogue errors are runtime exceptions outside the resource and
	 * token families, so their callers' instanceof checks and catch blocks
	 * are the only ones that pick them up.
	 *
	 * @return void
	 */
	public function testCatalogueErrorsStayOutsideTheOtherFamilies(): void {
		$showcase = new ShowcaseNotFoundException('Unknown showcase "x".');
		$template = new TemplateNotInstalledException('Template "y" is not installed.');

		foreach ([$showcase, $template] as $exception) {
			$this->assertInstanceOf(RuntimeException::class, $exception);
			$this->assertNotInstanceOf(ResourceException::class, $exception);
			$this->assertNotInstanceOf(EmbedTokenException::class, $exception);
		}

		$this->assertNotInstanceOf(TemplateNotInstalledException::class, $showcase);
		$this->assertNotInstanceOf(ShowcaseNotFoundException::class, $template);
		$this->assertSame('Unknown showcase "x".', $showcase->getMessage());
		$this->assertSame('Template "y" is not installed.', $template->getMessage());
	}//end testCatalogueErrorsStayOutsideTheOtherFamilies()

	/**
	 * The lock and metadata errors are checked exceptions, not runtime ones,
	 * and not resource errors: the controllers map them by hand.
	 *
	 * @return void
	 */
	public function testLockAndMetadataErrorsAreHandMappedCheckedExceptions(): void {
		foreach ([new LockForbiddenException('no'), new LockNotFoundException('gone'), new InvalidMetadataFieldException('bad')] as $exception) {
			$this->assertInstanceOf(Exception::class, $exception);
			$this->assertNotInstanceOf(RuntimeException::class, $exception);
			$this->assertNotInstanceOf(ResourceException::class, $exception);
		}
	}//end testLockAndMetadataErrorsAreHandMappedCheckedExceptions()
}//end class
