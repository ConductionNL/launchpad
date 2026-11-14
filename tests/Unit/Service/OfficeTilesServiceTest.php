<?php

/**
 * Unit tests for the office tile pack and file creation.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Exception\InvalidDirectoryException;
use OCA\LaunchPad\Exception\InvalidFilenameException;
use OCA\LaunchPad\Exception\StorageFailureException;
use OCA\LaunchPad\Service\OfficeTilesService;
use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Template\ITemplateManager;
use OCP\Files\Template\TemplateFileCreator;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The pack lists what the server has; a new file never overwrites (REQ-OFT-001, REQ-OFT-002).
 *
 * @covers \OCA\LaunchPad\Service\OfficeTilesService
 */
class OfficeTilesServiceTest extends TestCase {

	/**
	 * The template manager double.
	 *
	 * @var ITemplateManager&MockObject
	 */
	private ITemplateManager&MockObject $templateManager;

	/**
	 * The app manager double.
	 *
	 * @var IAppManager&MockObject
	 */
	private IAppManager&MockObject $appManager;

	/**
	 * The person's files.
	 *
	 * @var Folder&MockObject
	 */
	private Folder&MockObject $userFolder;

	/**
	 * Build the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->templateManager = $this->createMock(ITemplateManager::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->userFolder = $this->createMock(Folder::class);
	}//end setUp()

	/**
	 * The service over the doubles.
	 *
	 * @return OfficeTilesService
	 */
	private function service(): OfficeTilesService {
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('pieter')->willReturn($this->createMock(IUser::class));
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('pieter')->willReturn($this->userFolder);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturnCallback(
			static fn (string $route, array $arguments): string => '/apps/files/?openfile=' . $arguments['openfile']
		);

		return new OfficeTilesService($this->templateManager, $this->appManager, $userManager, $rootFolder, $urlGenerator);
	}//end service()

	/**
	 * Nextcloud Office's three creators.
	 *
	 * @return void
	 */
	private function withOfficeCreators(): void {
		$this->templateManager->method('listCreators')->willReturn([
			new TemplateFileCreator('richdocuments', 'New document', '.docx'),
			new TemplateFileCreator('richdocuments', 'New spreadsheet', '.xlsx'),
			new TemplateFileCreator('richdocuments', 'New presentation', '.pptx'),
		]);
	}//end withOfficeCreators()

	/**
	 * Enable exactly these apps for the person.
	 *
	 * @param array<int, string> $enabled The app ids.
	 *
	 * @return void
	 */
	private function withApps(array $enabled): void {
		$this->appManager->method('isEnabledForUser')->willReturnCallback(
			static fn (string $appId): bool => in_array($appId, $enabled, true)
		);
	}//end withApps()

	/**
	 * With Office, Mail and Calendar and no Contacts: three create tiles, then Files, Mail, Calendar.
	 *
	 * @return void
	 */
	public function testThePackListsCreatorsThenEnabledApps(): void {
		$this->withOfficeCreators();
		$this->withApps(['files', 'mail', 'calendar']);

		$tiles = $this->service()->packFor('pieter');

		$this->assertSame(
			['New document', 'New spreadsheet', 'New presentation', 'Files', 'Mail', 'Calendar'],
			array_column($tiles, 'title')
		);
		$this->assertSame(
			['create', 'create', 'create', 'app', 'app', 'app'],
			array_column($tiles, 'linkType')
		);
		$this->assertSame(
			['richdocuments:.docx', 'richdocuments:.xlsx', 'richdocuments:.pptx', 'files', 'mail', 'calendar'],
			array_column($tiles, 'linkValue')
		);
		$this->assertNotContains('Contacts', array_column($tiles, 'title'));
	}//end testThePackListsCreatorsThenEnabledApps()

	/**
	 * Without office apps or optional apps the pack is just what is left, never a dead tile.
	 *
	 * @return void
	 */
	public function testAServerWithoutCreatorsOrAppsGivesAnEmptyPack(): void {
		$this->templateManager->method('listCreators')->willReturn([]);
		$this->withApps([]);

		$this->assertSame([], $this->service()->packFor('pieter'));
	}//end testAServerWithoutCreatorsOrAppsGivesAnEmptyPack()

	/**
	 * A new document goes to /Documents from the blank template and opens in the editor.
	 *
	 * @return void
	 */
	public function testCreateMakesTheFileFromTheBlankTemplate(): void {
		$this->withOfficeCreators();
		$documents = $this->createMock(Folder::class);
		$documents->method('nodeExists')->willReturn(false);
		$this->userFolder->method('nodeExists')->with('Documents')->willReturn(true);
		$this->userFolder->method('get')->with('Documents')->willReturn($documents);
		$this->templateManager->expects($this->once())->method('createFromTemplate')
			->with('/Documents/Notulen.docx', '', 'user')
			->willReturn(['fileid' => 42]);

		$file = $this->service()->create('pieter', 'richdocuments:.docx', ' Notulen ');

		$this->assertSame(['fileId' => 42, 'path' => '/Documents/Notulen.docx', 'url' => '/apps/files/?openfile=42'], $file);
	}//end testCreateMakesTheFileFromTheBlankTemplate()

	/**
	 * The same name twice gives `Notulen (2).docx`; the first file is never overwritten.
	 *
	 * @return void
	 */
	public function testAnExistingNameGetsTheNextFreeNumber(): void {
		$this->withOfficeCreators();
		$documents = $this->createMock(Folder::class);
		$documents->method('nodeExists')->willReturnCallback(
			static fn (string $name): bool => in_array($name, ['Notulen.docx', 'Notulen (2).docx'], true)
		);
		$this->userFolder->method('nodeExists')->willReturn(true);
		$this->userFolder->method('get')->willReturn($documents);
		$this->templateManager->expects($this->once())->method('createFromTemplate')
			->with('/Documents/Notulen (3).docx', '', 'user')
			->willReturn(['fileid' => 7]);

		$this->assertSame('/Documents/Notulen (3).docx', $this->service()->create('pieter', 'richdocuments:.docx', 'Notulen')['path']);
	}//end testAnExistingNameGetsTheNextFreeNumber()

	/**
	 * A missing folder is created; one named in the request is used.
	 *
	 * @return void
	 */
	public function testAMissingFolderIsCreated(): void {
		$this->withOfficeCreators();
		$created = $this->createMock(Folder::class);
		$created->method('nodeExists')->willReturn(false);
		$this->userFolder->method('nodeExists')->with('Projects/2026')->willReturn(false);
		$this->userFolder->expects($this->once())->method('newFolder')->with('Projects/2026')->willReturn($created);
		$this->templateManager->method('createFromTemplate')->willReturn(['fileid' => 3]);

		$this->assertSame('/Projects/2026/Plan.xlsx', $this->service()->create('pieter', 'richdocuments:.xlsx', 'Plan', '/Projects/2026/')['path']);
	}//end testAMissingFolderIsCreated()

	/**
	 * A creator the server does not register is refused and nothing is created.
	 *
	 * @return void
	 */
	public function testAnUnknownCreatorIsRefused(): void {
		$this->withOfficeCreators();
		$this->templateManager->expects($this->never())->method('createFromTemplate');

		try {
			$this->service()->create('pieter', 'onlyoffice:.docx', 'Notulen');
			$this->fail('expected a refusal');
		} catch (InvalidFilenameException $exception) {
			$this->assertSame(400, $exception->getHttpStatus());
			$this->assertSame('Unknown file creator', $exception->getMessage());
		}
	}//end testAnUnknownCreatorIsRefused()

	/**
	 * Names the file rules refuse are refused before anything is touched.
	 *
	 * @return void
	 */
	public function testABadNameIsRefused(): void {
		$this->withOfficeCreators();
		$this->templateManager->expects($this->never())->method('createFromTemplate');

		foreach (['', '   ', '../escape', 'a/b', 'semi;colon', str_repeat('x', 201)] as $name) {
			try {
				$this->service()->create('pieter', 'richdocuments:.docx', $name);
				$this->fail('expected a refusal for ' . $name);
			} catch (InvalidFilenameException) {
				$this->addToAssertionCount(1);
			}
		}
	}//end testABadNameIsRefused()

	/**
	 * A folder that escapes the person's files is refused.
	 *
	 * @return void
	 */
	public function testAnEscapingFolderIsRefused(): void {
		$this->withOfficeCreators();
		$this->templateManager->expects($this->never())->method('createFromTemplate');

		$this->expectException(InvalidDirectoryException::class);
		$this->service()->create('pieter', 'richdocuments:.docx', 'Notulen', '/Documents/../../other');
	}//end testAnEscapingFolderIsRefused()

	/**
	 * A folder path that is a file is refused.
	 *
	 * @return void
	 */
	public function testAFolderThatIsAFileIsRefused(): void {
		$this->withOfficeCreators();
		$this->userFolder->method('nodeExists')->willReturn(true);
		$this->userFolder->method('get')->willReturn($this->createMock(File::class));

		$this->expectException(InvalidDirectoryException::class);
		$this->service()->create('pieter', 'richdocuments:.docx', 'Notulen', '/notes.txt');
	}//end testAFolderThatIsAFileIsRefused()

	/**
	 * A failure in the template manager is a storage failure, not a raw error.
	 *
	 * @return void
	 */
	public function testATemplateFailureIsAStorageFailure(): void {
		$this->withOfficeCreators();
		$documents = $this->createMock(Folder::class);
		$documents->method('nodeExists')->willReturn(false);
		$this->userFolder->method('nodeExists')->willReturn(true);
		$this->userFolder->method('get')->willReturn($documents);
		$this->templateManager->method('createFromTemplate')->willThrowException(new RuntimeException('disk full'));

		$this->expectException(StorageFailureException::class);
		$this->expectExceptionMessage('Failed to create the file');
		$this->service()->create('pieter', 'richdocuments:.docx', 'Notulen');
	}//end testATemplateFailureIsAStorageFailure()
}//end class
