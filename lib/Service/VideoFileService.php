<?php

/**
 * VideoFileService
 *
 * Resolves the Nextcloud file of a `video` widget placement
 * (`sourceType: 'nc-file'`) for the user viewing it (REQ-VID-006): a video
 * the viewer can read gets a streaming address in the viewer's own WebDAV
 * space; a deleted file, a non-video and a file the viewer cannot read are
 * told apart, and none of them carries an address.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use Throwable;

/**
 * The file behind a video placement, as the viewer may play it.
 *
 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-006-check-file-access-control-for-nextcloud-files
 */
class VideoFileService {
	/**
	 * The file plays: the result carries `streamingUrl`.
	 */
	public const OK = 'ok';

	/**
	 * The placement is no Nextcloud-file video.
	 */
	public const NO_FILE = 'no_file';

	/**
	 * The file no longer exists (deleted, or only in a trash bin).
	 */
	public const NOT_FOUND = 'not_found';

	/**
	 * The file exists but is not a video.
	 */
	public const NOT_VIDEO = 'not_video';

	/**
	 * The file exists but the viewer cannot read it.
	 */
	public const NOT_ACCESSIBLE = 'not_accessible';

	/**
	 * Constructor.
	 *
	 * @param IRootFolder           $rootFolder The filesystem root.
	 * @param WidgetPlacementMapper $placements The placement store.
	 * @param IURLGenerator         $urls       Builds the WebDAV address.
	 * @param IUserMountCache       $mounts     Says whose storages hold a file id.
	 */
	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly WidgetPlacementMapper $placements,
		private readonly IURLGenerator $urls,
		private readonly IUserMountCache $mounts,
	) {
	}//end __construct()

	/**
	 * Resolve a placement's file for a viewer who may see the placement.
	 *
	 * @param string $viewerId    The viewing user.
	 * @param int    $placementId The placement.
	 *
	 * @return array{status: string, streamingUrl?: string}
	 *
	 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-006-check-file-access-control-for-nextcloud-files
	 */
	public function resolve(string $viewerId, int $placementId): array {
		$fileId = $this->fileIdOf(placementId: $placementId);
		if ($fileId === null) {
			return ['status' => self::NO_FILE];
		}

		$node = $this->viewerNode(viewerId: $viewerId, fileId: $fileId);
		if ($node === null) {
			return ['status' => $this->absentStatus(fileId: $fileId)];
		}

		if ($node->isReadable() === false) {
			return ['status' => self::NOT_ACCESSIBLE];
		}

		if (str_starts_with(haystack: $node->getMimetype(), needle: 'video/') === false) {
			return ['status' => self::NOT_VIDEO];
		}

		return ['status' => self::OK, 'streamingUrl' => $this->streamingUrl(viewerId: $viewerId, node: $node)];
	}//end resolve()

	/**
	 * The file id of a Nextcloud-file video placement, or null.
	 *
	 * @param int $placementId The placement.
	 *
	 * @return int|null
	 */
	private function fileIdOf(int $placementId): ?int {
		try {
			$placement = $this->placements->find(id: $placementId);
		} catch (DoesNotExistException) {
			return null;
		}

		$content = $placement->getContentArray();
		$fileId  = ($content['fileId'] ?? null);
		if ($placement->getWidgetId() !== 'video'
			|| ($content['sourceType'] ?? null) !== 'nc-file'
			|| is_numeric($fileId) === false
			|| (int)$fileId <= 0
		) {
			return null;
		}

		return (int)$fileId;
	}//end fileIdOf()

	/**
	 * The file as it appears in the viewer's own files, or null.
	 *
	 * @param string $viewerId The viewing user.
	 * @param int    $fileId   The file id.
	 *
	 * @return File|null
	 */
	private function viewerNode(string $viewerId, int $fileId): ?File {
		try {
			$node = $this->rootFolder->getUserFolder(userId: $viewerId)->getFirstNodeById(id: $fileId);
		} catch (Throwable) {
			return null;
		}

		if ($node instanceof File) {
			return $node;
		}

		return null;
	}//end viewerNode()

	/**
	 * Why a file is missing from the viewer's files: it lives in someone's
	 * files (not accessible), or nowhere but a trash bin or nowhere at all
	 * (not found).
	 *
	 * Asked per user whose storage holds the file id: a lookup from the
	 * filesystem root only sees storages already set up in this request,
	 * which another owner's never is.
	 *
	 * @param int $fileId The file id.
	 *
	 * @return string NOT_ACCESSIBLE or NOT_FOUND.
	 */
	private function absentStatus(int $fileId): string {
		foreach ($this->mounts->getMountsForFileId(fileId: $fileId) as $mount) {
			try {
				$node = $this->rootFolder->getUserFolder(userId: $mount->getUser()->getUID())->getFirstNodeById(id: $fileId);
			} catch (Throwable) {
				$node = null;
			}

			if ($node !== null) {
				return self::NOT_ACCESSIBLE;
			}
		}

		return self::NOT_FOUND;
	}//end absentStatus()

	/**
	 * The file's WebDAV address in the viewer's own space, which the
	 * viewer's session may read.
	 *
	 * @param string $viewerId The viewing user.
	 * @param File   $node     The file, as the viewer sees it.
	 *
	 * @return string
	 */
	private function streamingUrl(string $viewerId, File $node): string {
		$relative = $this->rootFolder->getUserFolder(userId: $viewerId)->getRelativePath(path: $node->getPath());
		$segments = array_map(
			callback: 'rawurlencode',
			array: array_values(array_filter(explode(separator: '/', string: (string)$relative), static fn (string $segment): bool => $segment !== ''))
		);

		return $this->urls->getWebroot().'/remote.php/dav/files/'.rawurlencode($viewerId).'/'.implode(separator: '/', array: $segments);
	}//end streamingUrl()
}//end class
