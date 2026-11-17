<?php

/**
 * ScheduledExportArtefactStorage
 *
 * Where rendered artefacts live (design D3): in the export owner's Files
 * area under `Apps/LaunchPad/Exports/{exportId}/`, so they can be found,
 * shared and searched like any file, while the run-row in OpenRegister keeps
 * only their path and hash. The dispatcher reads them back for a retry and
 * the janitor deletes them when their retention ends.
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
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-4
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

/**
 * Artefact files of scheduled exports in the owner's Files area.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-007-retention-policy-enforced-by-janitor
 */
class ScheduledExportArtefactStorage {

	public const BASE_PATH = 'Apps/LaunchPad/Exports';

	/**
	 * Constructor.
	 *
	 * @param IRootFolder $rootFolder Nextcloud's file tree.
	 */
	public function __construct(
		private readonly IRootFolder $rootFolder,
	) {
	}//end __construct()

	/**
	 * Write an artefact and answer its path in the owner's Files area.
	 *
	 * @param string $owner    The export owner's uid.
	 * @param string $exportId The export's uuid.
	 * @param string $filename The file name, without directories.
	 * @param string $content  The bytes.
	 *
	 * @return string The path, relative to the owner's Files root.
	 *
	 * @throws InvalidArgumentException When the export id or file name would leave the export's folder.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-4
	 */
	public function write(string $owner, string $exportId, string $filename, string $content): string {
		if (self::isPlainName(name: $exportId) === false || self::isPlainName(name: $filename) === false) {
			throw new InvalidArgumentException('An artefact name must not contain a path.');
		}

		$folder = $this->ensureFolder(root: $this->rootFolder->getUserFolder($owner), path: self::BASE_PATH . '/' . $exportId);
		if ($folder->nodeExists($filename) === true) {
			$node = $folder->get($filename);
			if ($node instanceof File) {
				$node->putContent($content);
			}
		} else {
			$folder->newFile($filename, $content);
		}

		return self::BASE_PATH . '/' . $exportId . '/' . $filename;
	}//end write()

	/**
	 * Read an artefact back; null when it is gone.
	 *
	 * @param string $owner The export owner's uid.
	 * @param string $path  The path `write()` answered.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-6
	 */
	public function read(string $owner, string $path): ?string {
		if (self::isArtefactPath(path: $path) === false) {
			return null;
		}

		try {
			$node = $this->rootFolder->getUserFolder($owner)->get($path);
		} catch (NotFoundException) {
			return null;
		}

		if ($node instanceof File === false) {
			return null;
		}

		return $node->getContent();
	}//end read()

	/**
	 * Delete an artefact. A file that is already gone counts as deleted.
	 *
	 * @param string $owner The export owner's uid.
	 * @param string $path  The path `write()` answered.
	 *
	 * @return bool False when the path is not an artefact path.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-007-retention-policy-enforced-by-janitor
	 */
	public function delete(string $owner, string $path): bool {
		if (self::isArtefactPath(path: $path) === false) {
			return false;
		}

		try {
			$this->rootFolder->getUserFolder($owner)->get($path)->delete();
		} catch (NotFoundException) {
			return true;
		}

		return true;
	}//end delete()

	/**
	 * A folder under a root, created where missing.
	 *
	 * @param Folder $root The root.
	 * @param string $path Slash-separated folder names.
	 *
	 * @return Folder
	 */
	private function ensureFolder(Folder $root, string $path): Folder {
		$folder = $root;
		foreach (explode('/', $path) as $name) {
			if ($folder->nodeExists($name) === true) {
				$node = $folder->get($name);
				if ($node instanceof Folder) {
					$folder = $node;
					continue;
				}
			}

			$folder = $folder->newFolder($name);
		}

		return $folder;
	}//end ensureFolder()

	/**
	 * Whether a path is one `write()` produces: under the export folder, no
	 * `..` and no empty segments.
	 *
	 * @param string $path The path.
	 *
	 * @return bool
	 */
	private static function isArtefactPath(string $path): bool {
		$prefix = self::BASE_PATH . '/';
		if (str_starts_with($path, $prefix) === false) {
			return false;
		}

		$rest = explode('/', substr($path, strlen($prefix)));
		return count($rest) === 2
			&& self::isPlainName(name: $rest[0]) === true
			&& self::isPlainName(name: $rest[1]) === true;
	}//end isArtefactPath()

	/**
	 * Whether a name is one path segment.
	 *
	 * @param string $name The name.
	 *
	 * @return bool
	 */
	private static function isPlainName(string $name): bool {
		return $name !== '' && $name !== '.' && $name !== '..' && str_contains($name, '/') === false && str_contains($name, '\\') === false;
	}//end isPlainName()
}//end class
