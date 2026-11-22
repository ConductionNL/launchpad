<?php

/**
 * DemoSeedFiles
 *
 * Creates the shared team folder of a demo seed (for example Vergunningen
 * in Marieke's files, shared with the group vergunningen) with its example
 * files, so the Files widgets of the demo dashboards have content. Existing
 * files and an existing share are kept.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Seed the demo team folder and its group share.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedFiles {
	/**
	 * Constructor.
	 *
	 * @param IRootFolder     $rootFolder   The user folders.
	 * @param IShareManager   $shareManager Creates the group share.
	 * @param LoggerInterface $logger       Logs a folder that could not be created.
	 */
	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly IShareManager $shareManager,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Ensure the team folder when the seed has one. A failure is logged and
	 * reported: the dashboards still load without the folder.
	 *
	 * @param mixed $definition The seed's `files` entry, or null.
	 *
	 * @return array<string, mixed> What this run added, `{skipped: true}`, or `{error}`.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function ensureSafely(mixed $definition): array {
		if (is_array($definition) === false) {
			return ['skipped' => true];
		}

		try {
			return $this->ensure(definition: $definition);
		} catch (Throwable $e) {
			$this->logger->warning(
				message: 'Demo seed could not create the team folder: ' . $e->getMessage(),
				context: ['exception' => $e]
			);
			return ['error' => $e->getMessage()];
		}
	}//end ensureSafely()

	/**
	 * Ensure the folder, its files and its group share exist.
	 *
	 * @param array<string, mixed> $definition `{owner, folder, shareWithGroup, names}`.
	 *
	 * @return array{createdFiles: int, shared: bool} What this run added.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function ensure(array $definition): array {
		$owner      = (string)$definition['owner'];
		$folderName = (string)$definition['folder'];
		$home       = $this->rootFolder->getUserFolder(userId: $owner);

		$folder = $this->folderIn(home: $home, name: $folderName);
		$createdFiles = 0;
		foreach ((array)($definition['names'] ?? []) as $name) {
			if ($folder->nodeExists(path: (string)$name) === true) {
				continue;
			}

			$folder->newFile(path: (string)$name, content: '');
			$createdFiles++;
		}

		$group  = (string)($definition['shareWithGroup'] ?? '');
		$shared = false;
		if ($group !== '') {
			$shared = $this->shareWithGroup(folder: $folder, owner: $owner, group: $group);
		}

		return ['createdFiles' => $createdFiles, 'shared' => $shared];
	}//end ensure()

	/**
	 * The named folder in a home folder, created when absent.
	 *
	 * @param Folder $home The home folder.
	 * @param string $name The folder name.
	 *
	 * @return Folder The folder.
	 */
	private function folderIn(Folder $home, string $name): Folder {
		if ($home->nodeExists(path: $name) === true) {
			$node = $home->get(path: $name);
			if ($node instanceof Folder) {
				return $node;
			}
		}

		return $home->newFolder(path: $name);
	}//end folderIn()

	/**
	 * Share the folder with a group unless that share exists.
	 *
	 * @param Folder $folder The folder.
	 * @param string $owner  The owner.
	 * @param string $group  The group.
	 *
	 * @return bool True when this run created the share.
	 */
	private function shareWithGroup(Folder $folder, string $owner, string $group): bool {
		$existing = $this->shareManager->getSharesBy(
			userId: $owner,
			shareType: IShare::TYPE_GROUP,
			path: $folder
		);
		foreach ($existing as $share) {
			if ($share->getSharedWith() === $group) {
				return false;
			}
		}

		$share = $this->shareManager->newShare();
		$share->setNode(node: $folder);
		$share->setShareType(shareType: IShare::TYPE_GROUP);
		$share->setSharedWith(sharedWith: $group);
		$share->setSharedBy(sharedBy: $owner);
		$share->setShareOwner(shareOwner: $owner);
		$share->setPermissions(permissions: Constants::PERMISSION_ALL);
		$this->shareManager->createShare(share: $share);

		return true;
	}//end shareWithGroup()
}//end class
