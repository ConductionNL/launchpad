<?php

/**
 * OfficeTilesService
 *
 * The ready-made office tile pack (integrations-office-tiles): a "New ..."
 * tile for each file creator the server's office apps register, and tiles
 * for Files, Mail, Calendar, Contacts and Talk when those apps are enabled
 * for the person (REQ-OFT-001). A "New ..." tile creates the file from the
 * office app's own blank template under a free name, never overwriting
 * (REQ-OFT-002).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Exception\InvalidDirectoryException;
use OCA\LaunchPad\Exception\InvalidFilenameException;
use OCA\LaunchPad\Exception\ResourceException;
use OCA\LaunchPad\Exception\StorageFailureException;
use OCP\App\IAppManager;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Template\ITemplateManager;
use OCP\Files\Template\TemplateFileCreator;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Throwable;

/**
 * Builds the office tile pack and creates files from office templates.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) The template, app, user, file and URL services are each one platform call.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-the-add-flow-offers-an-office-tile-pack-built-from-what-the-server-has-req-oft-001
 */
class OfficeTilesService {

	/**
	 * The apps the pack links to when they are enabled, in pack order: app id => tile title.
	 *
	 * @var array<string, string>
	 */
	public const APP_TILES = [
		'files' => 'Files',
		'mail' => 'Mail',
		'calendar' => 'Calendar',
		'contacts' => 'Contacts',
		'spreed' => 'Talk',
	];

	/**
	 * The folder a new file goes to when the person names none.
	 *
	 * @var string
	 */
	public const DEFAULT_FOLDER = '/Documents';

	/**
	 * How many numbered names are tried before giving up.
	 *
	 * @var int
	 */
	private const MAX_FREE_NAME_ATTEMPTS = 1000;

	/**
	 * Constructor.
	 *
	 * @param ITemplateManager $templateManager The file creators office apps register.
	 * @param IAppManager      $appManager      Which apps are enabled for a person.
	 * @param IUserManager     $userManager     Resolves the person.
	 * @param IRootFolder      $rootFolder      The person's files.
	 * @param IURLGenerator    $urlGenerator    Builds the editor link.
	 */
	public function __construct(
		private readonly ITemplateManager $templateManager,
		private readonly IAppManager $appManager,
		private readonly IUserManager $userManager,
		private readonly IRootFolder $rootFolder,
		private readonly IURLGenerator $urlGenerator,
	) {
	}//end __construct()

	/**
	 * The tiles of the pack for one person: a create tile per file creator,
	 * then a tile per enabled app. An app the person cannot open is left out.
	 *
	 * @param string $userId The person.
	 *
	 * @return array<int, array<string, string>> Tiles: `{title, linkType, linkValue, icon, iconType}`.
	 *
	 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-the-add-flow-offers-an-office-tile-pack-built-from-what-the-server-has-req-oft-001
	 */
	public function packFor(string $userId): array {
		$tiles = [];
		foreach ($this->creators() as $creatorId => $creator) {
			$tiles[] = [
				'title' => $this->labelOf(creator: $creator),
				'linkType' => 'create',
				'linkValue' => $creatorId,
				'icon' => 'icon-filetype-file',
				'iconType' => 'class',
			];
		}

		$user = $this->userManager->get($userId);
		foreach (self::APP_TILES as $appId => $title) {
			if ($this->appManager->isEnabledForUser($appId, $user) === false) {
				continue;
			}

			$tiles[] = [
				'title' => $title,
				'linkType' => 'app',
				'linkValue' => $appId,
				'icon' => 'icon-' . $appId,
				'iconType' => 'class',
			];
		}

		return $tiles;
	}//end packFor()

	/**
	 * Create a file from a creator's blank template under a free name.
	 *
	 * @param string $userId    The person.
	 * @param string $creatorId The creator, as the pack names it (`<app>:<extension>`).
	 * @param string $name      The file name without extension.
	 * @param string $folder    The folder, created when missing (default /Documents).
	 *
	 * @return array{fileId: int, path: string, url: string} The new file.
	 *
	 * @throws InvalidFilenameException  When the name or the creator is not acceptable (400).
	 * @throws InvalidDirectoryException When the folder escapes the person's files (400).
	 * @throws StorageFailureException   When the file cannot be created.
	 *
	 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
	 */
	public function create(string $userId, string $creatorId, string $name, string $folder = self::DEFAULT_FOLDER): array {
		$creator = ($this->creators()[$creatorId] ?? null);
		if ($creator === null) {
			throw new InvalidFilenameException(message: 'Unknown file creator');
		}

		$name = trim($name);
		$this->assertValidName(name: $name);
		$folderPath = $this->normaliseFolder(folder: $folder);

		try {
			$target = $this->folderFor(userId: $userId, path: $folderPath);
			$fileName = $this->freeName(folder: $target, name: $name, extension: $this->extensionOf(creator: $creator));
			$path = rtrim($folderPath, '/') . '/' . $fileName;
			$created = $this->templateManager->createFromTemplate($path, '', 'user');
		} catch (ResourceException $exception) {
			throw $exception;
		} catch (Throwable $exception) {
			throw new StorageFailureException(message: 'Failed to create the file');
		}

		$fileId = (int)($created['fileid'] ?? 0);

		return [
			'fileId' => $fileId,
			'path' => $path,
			'url' => $this->urlGenerator->linkToRoute('files.view.index', ['openfile' => $fileId]),
		];
	}//end create()

	/**
	 * The registered file creators, keyed by `<app>:<extension>`.
	 *
	 * @return array<string, TemplateFileCreator>
	 */
	private function creators(): array {
		$creators = [];
		foreach ($this->templateManager->listCreators() as $creator) {
			if ($creator instanceof TemplateFileCreator === false) {
				continue;
			}

			$extension = $this->extensionOf(creator: $creator);
			if ($extension === '') {
				continue;
			}

			$creators[$creator->getAppId() . ':' . $extension] = $creator;
		}

		return $creators;
	}//end creators()

	/**
	 * A creator's file extension, with its leading dot, lowercase.
	 *
	 * @param TemplateFileCreator $creator The creator.
	 *
	 * @return string The extension, such as `.docx`, or '' when it has none usable.
	 */
	private function extensionOf(TemplateFileCreator $creator): string {
		$extension = strtolower(ltrim((string)($creator->jsonSerialize()['extension'] ?? ''), '.'));
		if (preg_match('/^[a-z0-9]{1,10}$/', $extension) !== 1) {
			return '';
		}

		return '.' . $extension;
	}//end extensionOf()

	/**
	 * A creator's own label, such as "New document".
	 *
	 * @param TemplateFileCreator $creator The creator.
	 *
	 * @return string The label.
	 */
	private function labelOf(TemplateFileCreator $creator): string {
		return (string)($creator->jsonSerialize()['label'] ?? '');
	}//end labelOf()

	/**
	 * Refuse a name the file rules of REQ-LBN-004 refuse.
	 *
	 * @param string $name The name without extension.
	 *
	 * @return void
	 *
	 * @throws InvalidFilenameException When the name is empty, too long or has forbidden characters.
	 */
	private function assertValidName(string $name): void {
		if ($name === '' || strlen($name) > 200 || str_contains($name, '..') === true) {
			throw new InvalidFilenameException();
		}

		if (preg_match(FileService::FILENAME_PATTERN, $name) !== 1) {
			throw new InvalidFilenameException();
		}
	}//end assertValidName()

	/**
	 * The folder as an absolute path inside the person's files.
	 *
	 * @param string $folder The requested folder.
	 *
	 * @return string The path, starting with `/`.
	 *
	 * @throws InvalidDirectoryException When it contains `..`, a backslash or a null byte.
	 */
	private function normaliseFolder(string $folder): string {
		$folder = trim($folder);
		if ($folder === '') {
			return self::DEFAULT_FOLDER;
		}

		foreach (['..', "\0", '\\'] as $forbidden) {
			if (str_contains($folder, $forbidden) === true) {
				throw new InvalidDirectoryException();
			}
		}

		return '/' . trim($folder, '/');
	}//end normaliseFolder()

	/**
	 * The person's folder at a path, created when missing.
	 *
	 * @param string $userId The person.
	 * @param string $path   The folder path.
	 *
	 * @return Folder The folder.
	 *
	 * @throws InvalidDirectoryException When the path is a file.
	 */
	private function folderFor(string $userId, string $path): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		if ($path === '/') {
			return $userFolder;
		}

		$relative = ltrim($path, '/');
		if ($userFolder->nodeExists($relative) === false) {
			return $userFolder->newFolder($relative);
		}

		$node = $userFolder->get($relative);
		if ($node instanceof Folder === false) {
			throw new InvalidDirectoryException();
		}

		return $node;
	}//end folderFor()

	/**
	 * A file name that does not exist yet: `Name.ext`, else `Name (2).ext`, `Name (3).ext`, ...
	 *
	 * @param Folder $folder    The folder.
	 * @param string $name      The name without extension.
	 * @param string $extension The extension with its dot.
	 *
	 * @return string The free file name.
	 *
	 * @throws StorageFailureException When no free name is found.
	 *
	 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
	 */
	public function freeName(Folder $folder, string $name, string $extension): string {
		$candidate = $name . $extension;
		for ($number = 2; $folder->nodeExists($candidate) === true; $number++) {
			if ($number > self::MAX_FREE_NAME_ATTEMPTS) {
				throw new StorageFailureException(message: 'No free file name');
			}

			$candidate = $name . ' (' . $number . ')' . $extension;
		}

		return $candidate;
	}//end freeName()
}//end class
