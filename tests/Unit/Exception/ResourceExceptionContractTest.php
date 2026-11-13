<?php

/**
 * ResourceExceptionContractTest
 *
 * Pins the error contract of every typed exception the controllers turn into
 * an HTTP error envelope: the machine-readable error code, the HTTP status
 * and the default (user-facing) message. Changing any of these changes what
 * an API client sees, so each one is asserted per class rather than left to
 * incidental coverage from controller tests.
 *
 * Also covers the carrier exceptions that hold structured data for the
 * response body: QuotaExceededException, LockConflictException and
 * MetadataFieldHasValuesException.
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

use OCA\LaunchPad\Db\DashboardLock;
use OCA\LaunchPad\Exception\LockConflictException;
use OCA\LaunchPad\Exception\MetadataFieldHasValuesException;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Exception\ResourceException;
use OCA\LaunchPad\Exception\CorruptImageException;
use OCA\LaunchPad\Exception\DuplicateRoleAssignmentException;
use OCA\LaunchPad\Exception\FileAlreadyExistsException;
use OCA\LaunchPad\Exception\FileMissingException;
use OCA\LaunchPad\Exception\FileTooLargeException;
use OCA\LaunchPad\Exception\FileTypeNotAllowedException;
use OCA\LaunchPad\Exception\FolderNotFoundException;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Exception\InvalidDataUrlException;
use OCA\LaunchPad\Exception\InvalidDirectoryException;
use OCA\LaunchPad\Exception\InvalidFilenameException;
use OCA\LaunchPad\Exception\InvalidImageFormatException;
use OCA\LaunchPad\Exception\InvalidRoleAssignmentException;
use OCA\LaunchPad\Exception\InvalidSvgException;
use OCA\LaunchPad\Exception\MimeMismatchException;
use OCA\LaunchPad\Exception\NoAccessException;
use OCA\LaunchPad\Exception\PersonalDashboardsDisabledException;
use OCA\LaunchPad\Exception\PlaylistNotFoundException;
use OCA\LaunchPad\Exception\ShareExpiredException;
use OCA\LaunchPad\Exception\ShareNotFoundException;
use OCA\LaunchPad\Exception\SharePasswordRequiredException;
use OCA\LaunchPad\Exception\ShareReadOnlyException;
use OCA\LaunchPad\Exception\StorageFailureException;
use OCA\LaunchPad\Exception\UnsupportedMediaTypeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Exception\ResourceException
 * @covers \OCA\LaunchPad\Exception\QuotaExceededException
 * @covers \OCA\LaunchPad\Exception\LockConflictException
 * @covers \OCA\LaunchPad\Exception\MetadataFieldHasValuesException
 * @covers \OCA\LaunchPad\Exception\CorruptImageException
 * @covers \OCA\LaunchPad\Exception\DuplicateRoleAssignmentException
 * @covers \OCA\LaunchPad\Exception\FileAlreadyExistsException
 * @covers \OCA\LaunchPad\Exception\FileMissingException
 * @covers \OCA\LaunchPad\Exception\FileTooLargeException
 * @covers \OCA\LaunchPad\Exception\FileTypeNotAllowedException
 * @covers \OCA\LaunchPad\Exception\FolderNotFoundException
 * @covers \OCA\LaunchPad\Exception\ForbiddenException
 * @covers \OCA\LaunchPad\Exception\InvalidDataUrlException
 * @covers \OCA\LaunchPad\Exception\InvalidDirectoryException
 * @covers \OCA\LaunchPad\Exception\InvalidFilenameException
 * @covers \OCA\LaunchPad\Exception\InvalidImageFormatException
 * @covers \OCA\LaunchPad\Exception\InvalidRoleAssignmentException
 * @covers \OCA\LaunchPad\Exception\InvalidSvgException
 * @covers \OCA\LaunchPad\Exception\MimeMismatchException
 * @covers \OCA\LaunchPad\Exception\NoAccessException
 * @covers \OCA\LaunchPad\Exception\PersonalDashboardsDisabledException
 * @covers \OCA\LaunchPad\Exception\PlaylistNotFoundException
 * @covers \OCA\LaunchPad\Exception\ShareExpiredException
 * @covers \OCA\LaunchPad\Exception\ShareNotFoundException
 * @covers \OCA\LaunchPad\Exception\SharePasswordRequiredException
 * @covers \OCA\LaunchPad\Exception\ShareReadOnlyException
 * @covers \OCA\LaunchPad\Exception\StorageFailureException
 * @covers \OCA\LaunchPad\Exception\UnsupportedMediaTypeException
 * @uses   \OCA\LaunchPad\Db\DashboardLock
 */
class ResourceExceptionContractTest extends TestCase {
	/**
	 * Every concrete ResourceException with its code, status and default message.
	 *
	 * @return array<string, array{0: class-string<ResourceException>, 1: string, 2: int, 3: string}>
	 */
	public static function contracts(): array {
		return [
			'CorruptImageException' => [CorruptImageException::class, 'corrupt_image', 400, 'Image content appears to be corrupt'],
			'DuplicateRoleAssignmentException' => [DuplicateRoleAssignmentException::class, 'duplicate_role_assignment', 409, 'That target already has the requested role assigned'],
			'FileAlreadyExistsException' => [FileAlreadyExistsException::class, 'file_exists', 409, 'A file with this name already exists'],
			'FileMissingException' => [FileMissingException::class, 'no_file', 400, 'No file was uploaded'],
			'FileTooLargeException' => [FileTooLargeException::class, 'file_too_large', 400, 'Maximum size is 5MB'],
			'FileTypeNotAllowedException' => [FileTypeNotAllowedException::class, 'file_type_not_allowed', 400, 'File type not allowed'],
			'FolderNotFoundException' => [FolderNotFoundException::class, 'folder_not_found', 404, 'The configured folder no longer exists'],
			'ForbiddenException' => [ForbiddenException::class, 'forbidden', 403, 'Admin privileges required'],
			'InvalidDataUrlException' => [InvalidDataUrlException::class, 'invalid_data_url', 400, 'Body must contain a base64 data URL'],
			'InvalidDirectoryException' => [InvalidDirectoryException::class, 'invalid_directory', 400, 'Invalid directory'],
			'InvalidFilenameException' => [InvalidFilenameException::class, 'invalid_filename', 400, 'Invalid filename'],
			'InvalidImageFormatException' => [InvalidImageFormatException::class, 'invalid_image_format', 400, 'Allowed image formats: jpeg, jpg, png, gif, svg, webp'],
			'InvalidRoleAssignmentException' => [InvalidRoleAssignmentException::class, 'invalid_role_assignment', 400, 'Role assignment payload is invalid'],
			'InvalidSvgException' => [InvalidSvgException::class, 'invalid_svg', 400, 'SVG could not be parsed or contained no allowed content'],
			'MimeMismatchException' => [MimeMismatchException::class, 'mime_mismatch', 400, 'Declared image type does not match file content'],
			'NoAccessException' => [NoAccessException::class, 'no_access', 403, 'You don\'t have access to this folder'],
			'PersonalDashboardsDisabledException' => [PersonalDashboardsDisabledException::class, 'personal_dashboards_disabled', 403, 'Personal dashboards are not enabled by your administrator'],
			'PlaylistNotFoundException' => [PlaylistNotFoundException::class, 'playlist_not_found', 404, 'Not found'],
			'ShareExpiredException' => [ShareExpiredException::class, 'share_expired', 404, 'Share not found'],
			'ShareNotFoundException' => [ShareNotFoundException::class, 'share_not_found', 404, 'Share not found'],
			'SharePasswordRequiredException' => [SharePasswordRequiredException::class, 'share_password_required', 401, 'Password required'],
			'ShareReadOnlyException' => [ShareReadOnlyException::class, 'cannot_modify_public_share', 403, 'Cannot modify dashboard via public share'],
			'StorageFailureException' => [StorageFailureException::class, 'storage_failure', 500, 'Failed to store resource'],
			'UnsupportedMediaTypeException' => [UnsupportedMediaTypeException::class, 'unsupported_media_type', 415, 'Use JSON body with base64 field'],
		];
	}//end contracts()

	/**
	 * The default instance carries the documented code, status and message.
	 *
	 * @param class-string<ResourceException> $class   The exception class.
	 * @param string                          $code    Expected error code.
	 * @param int                             $status  Expected HTTP status.
	 * @param string                          $message Expected default message.
	 *
	 * @return void
	 */
	#[DataProvider('contracts')]
	public function testDefaultContract(string $class, string $code, int $status, string $message): void {
		$exception = new $class();

		$this->assertInstanceOf(ResourceException::class, $exception);
		$this->assertSame($code, $exception->getErrorCode());
		$this->assertSame($status, $exception->getHttpStatus());
		$this->assertSame($message, $exception->getMessage());
		$this->assertSame($message, $exception->getDisplayMessage());
	}

	/**
	 * A custom message replaces the default but keeps code and status.
	 *
	 * @param class-string<ResourceException> $class  The exception class.
	 * @param string                          $code   Expected error code.
	 * @param int                             $status Expected HTTP status.
	 *
	 * @return void
	 */
	#[DataProvider('contracts')]
	public function testCustomMessageKeepsCodeAndStatus(string $class, string $code, int $status): void {
		$exception = new $class('Custom detail');

		$this->assertSame('Custom detail', $exception->getDisplayMessage());
		$this->assertSame($code, $exception->getErrorCode());
		$this->assertSame($status, $exception->getHttpStatus());
	}

	public function testErrorCodesAreUniqueExceptWhereAShareIsDeliberatelyIndistinguishable(): void {
		$codes = array_column(self::contracts(), 1);

		$this->assertSame(count($codes), count(array_unique($codes)));

		// An expired share answers exactly like a missing one to the caller —
		// same status and message — so a token's past existence is not revealed.
		$expired = new ShareExpiredException();
		$missing = new ShareNotFoundException();
		$this->assertSame($missing->getHttpStatus(), $expired->getHttpStatus());
		$this->assertSame($missing->getMessage(), $expired->getMessage());
	}

	public function testQuotaExceededCarriesItsNumbersIntoTheResponseBody(): void {
		$exception = new QuotaExceededException(
			quota: QuotaExceededException::QUOTA_WIDGETS,
			limit: 20,
			current: 21
		);

		$this->assertSame('widgets', $exception->getQuota());
		$this->assertSame(20, $exception->getLimit());
		$this->assertSame(21, $exception->getCurrent());
		$this->assertSame('Quota limit reached', $exception->getMessage());
		$this->assertSame(409, $exception->getHttpStatus());
		$this->assertSame(
			['error' => 'quota_exceeded', 'quota' => 'widgets', 'limit' => 20, 'current' => 21],
			$exception->toResponseBody()
		);
	}

	public function testQuotaExceededTakesACustomMessage(): void {
		$exception = new QuotaExceededException(
			quota: QuotaExceededException::QUOTA_DASHBOARDS,
			limit: 5,
			current: 5,
			message: 'You already have 5 dashboards'
		);

		$this->assertSame('You already have 5 dashboards', $exception->getDisplayMessage());
		$this->assertSame('dashboards', $exception->toResponseBody()['quota']);
	}

	public function testLockConflictExposesTheHeldLock(): void {
		$lock = new DashboardLock();
		$exception = new LockConflictException('Locked by bob', $lock);

		$this->assertSame($lock, $exception->getExistingLock());
		$this->assertSame('Locked by bob', $exception->getMessage());
		$this->assertSame('lock_conflict', LockConflictException::ERROR_CODE);
	}

	public function testMetadataFieldHasValuesNamesTheCountAndTheCascadeEscape(): void {
		$exception = new MetadataFieldHasValuesException(valueCount: 3);

		$this->assertSame(3, $exception->getValueCount());
		$this->assertSame(
			'Metadata field has 3 values. Use ?cascade=true to delete them.',
			$exception->getMessage()
		);
		$this->assertSame('metadata_field_has_values', MetadataFieldHasValuesException::ERROR_CODE);
	}
}//end class
