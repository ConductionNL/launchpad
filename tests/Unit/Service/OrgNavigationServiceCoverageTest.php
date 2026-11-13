<?php

/**
 * OrgNavigationServiceCoverageTest
 *
 * Additional unit tests for the org-wide navigation editor service
 * (REQ-ONAV-001..012), covering the defensive branches not reached by
 * OrgNavigationServiceTest:
 *   - setTree(): JSON encoding failure, 5 MB size cap, folder creation.
 *   - getTree(): unsupported language fallback, unreadable / oversized /
 *     empty / non-array file bodies.
 *   - validateTree(): non-object nodes, non-array children, non-string
 *     url, groupVisibility shape errors, openInNewTab / icon types.
 *   - filterTreeByUserGroups(): empty tree short-circuit, malformed
 *     visibility treated as visible, non-string group ids skipped.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\OrgNavigationService;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Coverage-focused tests for the org-wide navigation editor service.
 *
 * @covers \OCA\LaunchPad\Service\OrgNavigationService
 */
class OrgNavigationServiceCoverageTest extends TestCase {

	/** @var IAppData&MockObject */
	private $appData;

	/** @var AdminTemplateService&MockObject */
	private $templateService;

	private OrgNavigationService $service;

	protected function setUp(): void {
		$this->appData = $this->createMock(IAppData::class);
		$this->templateService = $this->createMock(AdminTemplateService::class);

		$this->service = new OrgNavigationService(
			appData: $this->appData,
			templateService: $this->templateService,
		);

	}//end setUp()

	/**
	 * Build a deterministic UUID v4 derived from the given seed.
	 *
	 * @param string $seed The seed.
	 *
	 * @return string A canonical UUID string.
	 */
	private function uuid(string $seed): string {
		$hash = md5($seed);
		return sprintf(
			'%s-%s-4%s-8%s-%s',
			substr($hash, 0, 8),
			substr($hash, 8, 4),
			substr($hash, 12, 3),
			substr($hash, 15, 3),
			substr($hash, 18, 12)
		);

	}//end uuid()

	/**
	 * Wire appData->getFolder('org-navigation') to return a folder whose
	 * getFile($name) returns the given file.
	 *
	 * @param ISimpleFile $file The file to return.
	 * @param string $name The expected file name.
	 *
	 * @return void
	 */
	private function wireFile(ISimpleFile $file, string $name): void {
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->expects($this->once())
			->method('getFile')
			->with($name)
			->willReturn($file);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with(OrgNavigationService::FOLDER)
			->willReturn($folder);

	}//end wireFile()

	// ---- setTree --------------------------------------------------------

	public function testSetTreeRejectsTreeThatCannotBeJsonEncoded(): void {
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->expects($this->never())->method('getFile');
		$folder->expects($this->never())->method('newFile');
		$this->appData->method('getFolder')->willReturn($folder);

		// Invalid UTF-8 passes validation (non-empty string) but makes
		// json_encode return false.
		$tree = [['id' => $this->uuid('bad-utf8'), 'label' => "Bad \xB1\x31 label"]];

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Failed to encode tree to JSON');
		$this->service->setTree(tree: $tree, language: 'en');

	}//end testSetTreeRejectsTreeThatCannotBeJsonEncoded()

	public function testSetTreeRejectsPayloadLargerThanFiveMegabytes(): void {
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->expects($this->never())->method('getFile');
		$folder->expects($this->never())->method('newFile');
		$this->appData->method('getFolder')->willReturn($folder);

		$tree = [
			[
				'id' => $this->uuid('huge'),
				'label' => str_repeat('x', (OrgNavigationService::MAX_FILE_BYTES + 1)),
			],
		];

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Tree exceeds maximum file size of 5 MB');
		$this->service->setTree(tree: $tree, language: 'en');

	}//end testSetTreeRejectsPayloadLargerThanFiveMegabytes()

	public function testSetTreeCreatesFolderWhenMissingAndNormalisesUnsupportedLanguage(): void {
		$tree = [['id' => $this->uuid('one'), 'label' => 'One']];
		$expectedPayload = json_encode($tree, (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

		$folder = $this->createMock(ISimpleFolder::class);
		$folder->expects($this->once())
			->method('getFile')
			->with('nl.json')
			->willThrowException(new NotFoundException());
		$folder->expects($this->once())
			->method('newFile')
			->with('nl.json', $expectedPayload);

		$this->appData->expects($this->once())
			->method('getFolder')
			->with(OrgNavigationService::FOLDER)
			->willThrowException(new NotFoundException());
		$this->appData->expects($this->once())
			->method('newFolder')
			->with(OrgNavigationService::FOLDER)
			->willReturn($folder);

		// 'fr' is not supported → falls back to the default 'nl'.
		$this->service->setTree(tree: $tree, language: 'fr');

	}//end testSetTreeCreatesFolderWhenMissingAndNormalisesUnsupportedLanguage()

	// ---- getTree / decodeFile ------------------------------------------

	public function testGetTreeFallsBackToDefaultLanguageFileForUnsupportedLanguage(): void {
		$tree = [['id' => $this->uuid('x'), 'label' => 'X']];
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getSize')->willReturn(100);
		$file->method('getContent')->willReturn((string) json_encode($tree));
		$this->wireFile(file: $file, name: 'nl.json');

		$this->assertSame($tree, $this->service->getTree(language: '  DE '));

	}//end testGetTreeFallsBackToDefaultLanguageFileForUnsupportedLanguage()

	public function testGetTreeReturnsEmptyWhenSizeCannotBeRead(): void {
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())->method('getSize')->willThrowException(new RuntimeException('stat failed'));
		$file->expects($this->never())->method('getContent');
		$this->wireFile(file: $file, name: 'en.json');

		$this->assertSame([], $this->service->getTree(language: 'en'));

	}//end testGetTreeReturnsEmptyWhenSizeCannotBeRead()

	public function testGetTreeReturnsEmptyWhenFileExceedsMaximumSize(): void {
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())->method('getSize')->willReturn(OrgNavigationService::MAX_FILE_BYTES + 1);
		$file->expects($this->never())->method('getContent');
		$this->wireFile(file: $file, name: 'en.json');

		$this->assertSame([], $this->service->getTree(language: 'en'));

	}//end testGetTreeReturnsEmptyWhenFileExceedsMaximumSize()

	public function testGetTreeReadsFileExactlyAtMaximumSize(): void {
		$tree = [['id' => $this->uuid('edge'), 'label' => 'Edge']];
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getSize')->willReturn(OrgNavigationService::MAX_FILE_BYTES);
		$file->expects($this->once())->method('getContent')->willReturn((string) json_encode($tree));
		$this->wireFile(file: $file, name: 'en.json');

		$this->assertSame($tree, $this->service->getTree(language: 'en'));

	}//end testGetTreeReadsFileExactlyAtMaximumSize()

	public function testGetTreeReturnsEmptyWhenContentCannotBeRead(): void {
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getSize')->willReturn(10);
		$file->expects($this->once())->method('getContent')->willThrowException(new NotFoundException('gone'));
		$this->wireFile(file: $file, name: 'en.json');

		$this->assertSame([], $this->service->getTree(language: 'en'));

	}//end testGetTreeReturnsEmptyWhenContentCannotBeRead()

	public function testGetTreeReturnsEmptyForEmptyFile(): void {
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getSize')->willReturn(0);
		$file->expects($this->once())->method('getContent')->willReturn('');
		$this->wireFile(file: $file, name: 'nl.json');

		$this->assertSame([], $this->service->getTree());

	}//end testGetTreeReturnsEmptyForEmptyFile()

	public function testGetTreeReturnsEmptyWhenJsonIsNotAnArray(): void {
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getSize')->willReturn(7);
		$file->expects($this->once())->method('getContent')->willReturn('"hello"');
		$this->wireFile(file: $file, name: 'en.json');

		$this->assertSame([], $this->service->getTree(language: 'en'));

	}//end testGetTreeReturnsEmptyWhenJsonIsNotAnArray()

	// ---- validateTree ---------------------------------------------------

	/**
	 * Invalid trees and the exact message each must raise.
	 *
	 * @return array<string, array{0: array, 1: string}>
	 */
	public static function invalidTreeProvider(): array {
		$id = '0cc175b9-c0f1-4b6a-831c-399e26977266';
		return [
			'scalar node' => [
				['not-a-node'],
				'Each node must be an object',
			],
			'children is a string' => [
				[['id' => $id, 'label' => 'A', 'children' => 'nope']],
				'Node children must be an array',
			],
			'url is an int' => [
				[['id' => $id, 'label' => 'A', 'url' => 42]],
				'url must be a string when set',
			],
			'groupVisibility is a string' => [
				[['id' => $id, 'label' => 'A', 'groupVisibility' => 'admin']],
				'groupVisibility must be null or a non-empty array of strings',
			],
			'groupVisibility is empty array' => [
				[['id' => $id, 'label' => 'A', 'groupVisibility' => []]],
				'groupVisibility must be null or a non-empty array of strings',
			],
			'groupVisibility has non-string entry' => [
				[['id' => $id, 'label' => 'A', 'groupVisibility' => ['admin', 7]]],
				'groupVisibility entries must be non-empty strings',
			],
			'groupVisibility has empty-string entry' => [
				[['id' => $id, 'label' => 'A', 'groupVisibility' => ['']]],
				'groupVisibility entries must be non-empty strings',
			],
			'openInNewTab is a string' => [
				[['id' => $id, 'label' => 'A', 'openInNewTab' => 'yes']],
				'openInNewTab must be a boolean when set',
			],
			'icon is an array' => [
				[['id' => $id, 'label' => 'A', 'icon' => ['folder']]],
				'icon must be a string when set',
			],
		];

	}//end invalidTreeProvider()

	/**
	 * @dataProvider invalidTreeProvider
	 *
	 * @param array $tree The invalid tree.
	 * @param string $message The expected exception message.
	 */
	public function testValidateTreeRejectsMalformedNodes(array $tree, string $message): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		$this->service->validateTree(tree: $tree);

	}//end testValidateTreeRejectsMalformedNodes()

	public function testValidateTreeAcceptsValidOptionalFields(): void {
		$tree = [
			[
				'id' => $this->uuid('ok'),
				'label' => 'OK',
				'url' => 'https://example.org',
				'groupVisibility' => ['admin', 'staff'],
				'openInNewTab' => true,
				'icon' => 'star',
			],
		];

		// validateTree() is void; the observable contract is "no throw",
		// so persist via setTree and assert the exact write.
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())
			->method('putContent')
			->with(json_encode($tree, (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('getFile')->with('en.json')->willReturn($file);
		$this->appData->method('getFolder')->willReturn($folder);

		$this->service->setTree(tree: $tree, language: 'EN');

	}//end testValidateTreeAcceptsValidOptionalFields()

	public function testSetTreeDoesNotTouchStorageWhenValidationFails(): void {
		$this->appData->expects($this->never())->method('getFolder');
		$this->appData->expects($this->never())->method('newFolder');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('icon must be a string when set');
		$this->service->setTree(tree: [['id' => $this->uuid('i'), 'label' => 'I', 'icon' => 5]]);

	}//end testSetTreeDoesNotTouchStorageWhenValidationFails()

	// ---- filterTreeByUserGroups ----------------------------------------

	public function testFilterEmptyTreeShortCircuitsWithoutGroupLookup(): void {
		$this->templateService->expects($this->never())->method('getUserGroupIdsFor');

		$this->assertSame([], $this->service->filterTreeByUserGroups(tree: [], userId: 'alice'));

	}//end testFilterEmptyTreeShortCircuitsWithoutGroupLookup()

	public function testFilterTreatsMalformedVisibilityAsVisibleAndSkipsNonStringGroupIds(): void {
		$this->templateService->expects($this->once())
			->method('getUserGroupIdsFor')
			->with('alice')
			->willReturn(['staff', '1']);

		$malformedString = ['id' => 'a', 'label' => 'Malformed string', 'groupVisibility' => 'admin'];
		$malformedEmpty = ['id' => 'b', 'label' => 'Empty array', 'groupVisibility' => []];
		// Non-string entry is skipped, then the string 'staff' grants visibility.
		$mixed = ['id' => 'c', 'label' => 'Mixed', 'groupVisibility' => [1, 'staff']];
		// The user is in group '1' (array_flip turns it into int key 1), but
		// an int entry 1 must be skipped rather than matched → hidden.
		$onlyInts = ['id' => 'd', 'label' => 'Ints only', 'groupVisibility' => [1]];

		$result = $this->service->filterTreeByUserGroups(
			tree: [$malformedString, $malformedEmpty, $mixed, $onlyInts],
			userId: 'alice'
		);

		$this->assertSame([$malformedString, $malformedEmpty, $mixed], $result);

	}//end testFilterTreatsMalformedVisibilityAsVisibleAndSkipsNonStringGroupIds()
}//end class
