<?php

/**
 * FilesWidgetService
 *
 * Implements the files-widget capability: inline Nextcloud Files browser
 * with permission-aware listing, upload, and delete (REQ-FLS-001..011).
 *
 * The service operates view-time: every request is evaluated against the
 * VIEWING user's permissions. Files and folders the viewer cannot read
 * are silently absent from the response (REQ-FLS-004). Upload and delete
 * are dual-gated by both placement config (`allowUpload` / `allowDelete`)
 * AND per-viewer permission on the underlying folder (REQ-FLS-007,
 * REQ-FLS-008).
 *
 * Folder resolution prefers `fileId` over `folderPath` because the file
 * id survives renames and moves (REQ-FLS-002 design D5). When the
 * configured folder is gone the service throws a typed
 * `FolderNotFoundException` so the controller can map it to HTTP 404
 * with `{error: "folder_not_found"}` instead of leaking a 500
 * (REQ-FLS-009).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Exception\FolderNotFoundException;
use OCA\LaunchPad\Exception\NoAccessException;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IPreview;
use OCP\IURLGenerator;
use RuntimeException;
use Throwable;

/**
 * Permission-aware folder browser used by the files widget.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Filesystem + URL
 *                                                 generation are both
 *                                                 unavoidable for this
 *                                                 capability.
 * @spec                                           openspec/specs/files-widget/spec.md
 */
class FilesWidgetService {
	/**
	 * Default page size when the caller does not specify one
	 * (REQ-FLS-003 design D3).
	 *
	 * @var integer
	 */
	public const DEFAULT_LIMIT = 50;

	/**
	 * Hard ceiling on page size — even when the caller asks for more
	 * we cap here so that a malicious or buggy client cannot force
	 * the backend to read an unbounded directory.
	 *
	 * @var integer
	 */
	public const MAX_LIMIT = 200;

	/**
	 * Maximum allowed upload size per file in bytes (50 MB).
	 *
	 * Rejects individual files larger than this before they reach
	 * Nextcloud's storage layer (M3 DoS / resource-exhaustion guard).
	 *
	 * @var integer
	 */
	public const MAX_UPLOAD_BYTES = 52428800;

	/**
	 * Constructor.
	 *
	 * @param IRootFolder $rootFolder Filesystem root accessor.
	 * @param IURLGenerator $urlGenerator URL builder for thumbnails and
	 *                                    deep links into the Files app.
	 * @param IPreview $previewManager Decides which files have a
	 *                                 previewable thumbnail.
	 */
	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly IURLGenerator $urlGenerator,
		private readonly IPreview $previewManager,
	) {
	}//end __construct()

	/**
	 * List the visible contents of the placement's configured folder (or a
	 * sub-path of it) for the viewing user (REQ-FLS-003, REQ-FLS-004).
	 *
	 * @param string $userId Viewing user's UID.
	 * @param array $config Placement config (`fileId`, `folderPath`,
	 *                      `mimeTypeFilter`, `sortBy`, `sortDescending`).
	 * @param array $page Optional `currentSubPath` (sub-path relative to the
	 *                    configured folder, set when the user navigates into
	 *                    a subfolder; null for the folder itself), `limit`
	 *                    (page size) and `cursor` (opaque offset string
	 *                    supplied by the previous response).
	 *
	 * @return array{items: list<array<string,mixed>>, nextCursor: ?string}
	 *
	 * @throws FolderNotFoundException When the configured folder no
	 *                                 longer exists.
	 * @throws NoAccessException When the viewer cannot read the
	 *                           configured folder.
	 *
	 * @spec openspec/specs/files-widget/spec.md
	 */
	public function getContentsForPlacement(string $userId, array $config, array $page=[]): array {
		$page          += ['currentSubPath' => null, 'limit' => self::DEFAULT_LIMIT, 'cursor' => ''];
		$limit          = (int)$page['limit'];
		$cursor         = (string)$page['cursor'];
		$rootFolder = $this->resolveConfiguredFolder(userId: $userId, config: $config);
		$target     = $this->descendInto(root: $rootFolder, subPath: ($page['currentSubPath'] ?? '/'));

		if ($target->isReadable() === false) {
			throw new NoAccessException();
		}

		$items = $this->listVisibleItems(folder: $target, config: $config);

		return $this->paginate(items: $items, limit: $this->clampLimit(limit: $limit), cursor: $cursor);
	}//end getContentsForPlacement()

	/**
	 * The folder's children the viewer may read and the placement's MIME
	 * filter lets through (folders always pass), as metadata, sorted per
	 * the placement config.
	 *
	 * @param Folder $folder The readable folder being listed.
	 * @param array  $config Placement config.
	 *
	 * @return list<array<string,mixed>> The sorted item metadata.
	 */
	private function listVisibleItems(Folder $folder, array $config): array {
		$children = $folder->getDirectoryListing();
		$mimeFilter = $this->normaliseMimeFilter(filter: ($config['mimeTypeFilter'] ?? []));

		$items = [];
		foreach ($children as $child) {
			if ($this->isListed(node: $child, mimeFilter: $mimeFilter) === true) {
				$items[] = $this->buildFileMetadata(node: $child);
			}
		}

		return $this->sortItems(
			items: $items,
			sortBy: ($config['sortBy'] ?? 'name'),
			sortDescending: (bool)($config['sortDescending'] ?? false)
		);
	}//end listVisibleItems()

	/**
	 * Whether a child is listed: the viewer can read it, and it is a
	 * folder or a file that passes the MIME filter.
	 *
	 * @param Node         $node       The child node.
	 * @param list<string> $mimeFilter Normalised filter list.
	 *
	 * @return bool True when the child is listed.
	 */
	private function isListed(Node $node, array $mimeFilter): bool {
		return $node->isReadable() === true
			&& (($node instanceof File) === false || $this->matchesMimeFilter(node: $node, filter: $mimeFilter) === true);
	}//end isListed()

	/**
	 * Upload one or more files into the configured folder (or a
	 * sub-path of it), enforcing the dual gate of placement allow-flag
	 * AND viewer write permission on the target folder.
	 *
	 * @param string $userId Viewing user's UID.
	 * @param array $config Placement config.
	 * @param string|null $currentSubPath Sub-path inside the
	 *                                    configured folder.
	 * @param array<int, array{name:string, tmp_name:string, size:int, error:int}> $uploadedFiles Normalised
	 *                                                                                            $_FILES
	 *                                                                                            entries.
	 *
	 * @return array{uploaded: list<array<string,mixed>>, errors: list<array<string,mixed>>}
	 *
	 * @throws FolderNotFoundException When the configured folder is gone.
	 * @throws NoAccessException When upload is not allowed for
	 *                           this placement / viewer combination.
	 *
	 * @spec openspec/specs/files-widget/spec.md
	 */
	public function uploadFiles(
		string $userId,
		array $config,
		?string $currentSubPath,
		array $uploadedFiles,
	): array {
		if ((bool)($config['allowUpload'] ?? false) === false) {
			throw new NoAccessException();
		}

		$root = $this->resolveConfiguredFolder(userId: $userId, config: $config);
		$target = $this->writableFolder(root: $root, subPath: $currentSubPath);

		return $this->storeUploads(target: $this->uploadTarget(folder: $target, config: $config), uploadedFiles: $uploadedFiles);
	}//end uploadFiles()

	/**
	 * The sub-path folder an upload writes into, refused when the viewer
	 * may not write to it.
	 *
	 * @param Folder      $root    The configured root folder.
	 * @param string|null $subPath Sub-path inside the configured folder.
	 *
	 * @return Folder The writable folder.
	 *
	 * @throws FolderNotFoundException When the sub-path does not resolve.
	 * @throws NoAccessException When the folder is not writable.
	 */
	private function writableFolder(Folder $root, ?string $subPath): Folder {
		$target = $this->descendInto(root: $root, subPath: ($subPath ?? '/'));
		if ($target->isUpdateable() === false) {
			throw new NoAccessException();
		}

		return $target;
	}//end writableFolder()

	/**
	 * The upload destination with the placement's MIME filter.
	 *
	 * @param Folder $folder The writable destination folder.
	 * @param array  $config Placement config.
	 *
	 * @return FilesWidgetUploadTarget The destination.
	 */
	private function uploadTarget(Folder $folder, array $config): FilesWidgetUploadTarget {
		// M3: normalise the placement's MIME type filter so we can gate
		// uploads against it (same filter already enforced on reads).
		$mimeFilter = $this->normaliseMimeFilter(
			filter: ($config['mimeTypeFilter'] ?? [])
		);

		return new FilesWidgetUploadTarget(folder: $folder, mimeFilter: $mimeFilter);
	}//end uploadTarget()

	/**
	 * Store each uploaded entry, collecting the stored files' metadata and
	 * the per-entry errors, in upload order.
	 *
	 * @param FilesWidgetUploadTarget $target        The destination.
	 * @param array                   $uploadedFiles Normalised `$_FILES` entries.
	 *
	 * @return array{uploaded: list<array<string,mixed>>, errors: list<array<string,mixed>>}
	 */
	private function storeUploads(FilesWidgetUploadTarget $target, array $uploadedFiles): array {
		$uploaded = [];
		$errors = [];
		foreach ($uploadedFiles as $entry) {
			$outcome = $this->storeUpload(target: $target, entry: $entry);
			if (is_string($outcome) === true) {
				$errors[] = ['name' => (string)$entry['name'], 'error' => $outcome];
			} else {
				$uploaded[] = $outcome;
			}
		}

		return ['uploaded' => $uploaded, 'errors' => $errors];
	}//end storeUploads()

	/**
	 * Screen and store one uploaded entry under a collision-free name.
	 *
	 * @param FilesWidgetUploadTarget $target The destination.
	 * @param array                   $entry  One normalised `$_FILES` entry.
	 *
	 * @return array<string,mixed>|string The stored file's metadata, or the
	 *                                    client-facing error code.
	 */
	private function storeUpload(FilesWidgetUploadTarget $target, array $entry): array|string {
		$rejection = $this->rejectUpload(entry: $entry, mimeFilter: $target->mimeFilter);
		if ($rejection !== null) {
			return $rejection;
		}

		return $this->writeUpload(target: $target, entry: $entry);
	}//end storeUpload()

	/**
	 * Write a screened upload under a collision-free name.
	 *
	 * @param FilesWidgetUploadTarget $target The destination.
	 * @param array                   $entry  One screened `$_FILES` entry.
	 *
	 * @return array<string,mixed>|string The stored file's metadata, or
	 *                                    `storage_failed`.
	 */
	private function writeUpload(FilesWidgetUploadTarget $target, array $entry): array|string {
		$safeName = $this->resolveAvailableName(folder: $target->folder, desired: (string)$entry['name']);
		try {
			$file = $this->streamToNewFile(
				target: $target->folder,
				tmp: (string)$entry['tmp_name'],
				safeName: $safeName
			);
			return $this->buildFileMetadata(node: $file);
		} catch (Throwable $e) {
			return 'storage_failed';
		}
	}//end writeUpload()

	/**
	 * Screen one uploaded entry before it is written to storage.
	 *
	 * Applies, in order: the transport-level `$_FILES` error, the M3
	 * genuine-upload assertion (a crafted `tmp_name` would otherwise give
	 * local-file inclusion), the hard size cap, and the placement's MIME
	 * type filter so an upload cannot bypass a restrict-to-images config.
	 *
	 * @param array $entry One normalised `$_FILES` entry with keys
	 *                     `name`, `tmp_name`, `size` and `error`.
	 * @param array $mimeFilter Normalised MIME filter; `[]` means allow all.
	 *
	 * @return string|null Client-facing error code, or null when the
	 *                     entry may be stored.
	 *
	 * @spec openspec/specs/files-widget/spec.md
	 */
	private function rejectUpload(array $entry, array $mimeFilter): ?string {
		if ($this->isGenuineUpload(entry: $entry) === false) {
			return 'upload_failed';
		}

		// M3: reject files that exceed the hard size cap.
		if ((int)$entry['size'] > self::MAX_UPLOAD_BYTES) {
			return 'file_too_large';
		}

		return $this->rejectUploadType(tmp: (string)$entry['tmp_name'], mimeFilter: $mimeFilter);
	}//end rejectUpload()

	/**
	 * Whether an entry is a complete upload: named, without a transport
	 * error, and with a temp file that came through a real PHP upload
	 * (M3: prevents local-file inclusion via a crafted `tmp_name`).
	 *
	 * @param array $entry One normalised `$_FILES` entry.
	 *
	 * @return bool True when the entry may be screened further.
	 */
	private function isGenuineUpload(array $entry): bool {
		$tmp = (string)$entry['tmp_name'];

		return (string)$entry['name'] !== ''
			&& (int)$entry['error'] === UPLOAD_ERR_OK
			&& $tmp !== ''
			&& is_uploaded_file(filename: $tmp) === true;
	}//end isGenuineUpload()

	/**
	 * M3: honour the placement MIME type filter on writes.
	 *
	 * @param string $tmp        Path of the PHP temporary upload file.
	 * @param array  $mimeFilter Normalised MIME filter; `[]` means allow all.
	 *
	 * @return string|null `file_type_not_allowed`, or null when allowed.
	 */
	private function rejectUploadType(string $tmp, array $mimeFilter): ?string {
		if ($mimeFilter === []) {
			return null;
		}

		$detectedMime = $this->detectMimeType(path: $tmp);
		if ($this->mimeMatchesFilter(mime: $detectedMime, filter: $mimeFilter) === false) {
			return 'file_type_not_allowed';
		}

		return null;
	}//end rejectUploadType()

	/**
	 * Stream a temporary upload into a new file under `$target`.
	 *
	 * M3: streamed rather than read whole so an upload is never fully
	 * materialised in memory before reaching Nextcloud's storage layer.
	 *
	 * @param Folder $target Destination folder.
	 * @param string $tmp Path of the PHP temporary upload file.
	 * @param string $safeName Collision-free name for the new file.
	 *
	 * @return File The newly written file.
	 *
	 * @throws RuntimeException When either the source or the destination
	 *                          stream cannot be opened.
	 *
	 * @spec openspec/specs/files-widget/spec.md
	 */
	private function streamToNewFile(Folder $target, string $tmp, string $safeName): File {
		$handle = fopen(filename: $tmp, mode: 'rb');
		if ($handle === false) {
			throw new RuntimeException('Cannot open temporary file');
		}

		$file = $target->newFile(path: $safeName);
		$outHandle = $file->fopen(mode: 'w');
		if ($outHandle === false) {
			fclose($handle);
			throw new RuntimeException('Cannot open destination file');
		}

		stream_copy_to_stream(from: $handle, to: $outHandle);
		fclose($handle);
		fclose($outHandle);

		return $file;
	}//end streamToNewFile()

	/**
	 * Delete a single file inside the configured folder (or a sub-path
	 * of it). Dual-gated: placement `allowDelete` + viewer delete
	 * permission on the file.
	 *
	 * @param string $userId Viewing user's UID.
	 * @param array $config Placement config.
	 * @param integer $fileId The file ID to delete.
	 *
	 * @return array{status: string, fileId: int}
	 *
	 * @throws FolderNotFoundException When the file is not found.
	 * @throws NoAccessException When delete is not allowed.
	 *
	 * @spec openspec/specs/files-widget/spec.md
	 */
	public function deleteFile(string $userId, array $config, int $fileId): array {
		if ((bool)($config['allowDelete'] ?? false) === false) {
			throw new NoAccessException();
		}

		$root = $this->resolveConfiguredFolder(userId: $userId, config: $config);
		$node = $this->userNodeById(userId: $userId, fileId: $fileId);
		$this->assertDeletableWithin(root: $root, node: $node);
		$this->deleteNode(node: $node);

		return [
			'status' => 'success',
			'fileId' => $fileId,
		];
	}//end deleteFile()

	/**
	 * Find a node by id inside the viewer's own Files area. The id
	 * search is scoped to the user folder so we do not have to worry
	 * about cross-storage leaks.
	 *
	 * @param string  $userId Viewing user's UID.
	 * @param integer $fileId The file ID.
	 *
	 * @return Node The first match.
	 *
	 * @throws FolderNotFoundException When the lookup fails or finds nothing.
	 */
	private function userNodeById(string $userId, int $fileId): Node {
		try {
			$userFolder = $this->rootFolder->getUserFolder(userId: $userId);
			$matches = $userFolder->getById(id: $fileId);
		} catch (Throwable $e) {
			throw new FolderNotFoundException();
		}

		if (count($matches) === 0) {
			throw new FolderNotFoundException();
		}

		return $matches[0];
	}//end userNodeById()

	/**
	 * Refuse a delete of a node outside the configured root folder or one
	 * the viewer may not delete.
	 *
	 * @param Folder $root The configured root folder.
	 * @param Node   $node The node to delete.
	 *
	 * @return void
	 *
	 * @throws NoAccessException When the delete is not allowed.
	 */
	private function assertDeletableWithin(Folder $root, Node $node): void {
		// Defence-in-depth: the resolved node must live inside the
		// configured root folder. Otherwise a viewer with both
		// placements could pass an arbitrary fileId from a different
		// folder and have it deleted via this endpoint.
		if ($this->isDescendantOf(parent: $root, candidate: $node) === false) {
			throw new NoAccessException();
		}

		if ($node->isDeletable() === false) {
			throw new NoAccessException();
		}
	}//end assertDeletableWithin()

	/**
	 * Delete a node, answering any storage failure as no access.
	 *
	 * @param Node $node The node to delete.
	 *
	 * @return void
	 *
	 * @throws NoAccessException When the storage refuses the delete.
	 */
	private function deleteNode(Node $node): void {
		try {
			$node->delete();
		} catch (Throwable $e) {
			throw new NoAccessException();
		}
	}//end deleteNode()

	/**
	 * Build the normalised JSON-friendly file metadata blob the
	 * frontend renderer consumes (REQ-FLS-003).
	 *
	 * @param Node $node A file or folder node.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/files-widget/spec.md
	 */
	public function buildFileMetadata(Node $node): array {
		$isFolder = $node instanceof Folder;

		// Emit a preview URL for any file Nextcloud can render a thumbnail
		// for (images, PDFs, videos, …) — not just images. `isAvailable`
		// gates on a registered preview provider, so we never hand the client
		// a URL that 404s. Non-previewable files keep `null` and the frontend
		// falls back to a generic file icon.
		$thumbnailUrl = null;
		if ($isFolder === false && $node instanceof File
			&& $this->previewManager->isAvailable(file: $node) === true
		) {
			$thumbnailUrl = $this->urlGenerator->linkToRouteAbsolute(
				routeName: 'core.Preview.getPreviewByFileId',
				arguments: [
					'fileId' => $node->getId(),
					'x' => 256,
					'y' => 256,
					'a' => 1,
				]
			);
		}

		$permissions = $node->getPermissions();
		$canEdit = ($permissions & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE;
		$canDelete = ($permissions & Constants::PERMISSION_DELETE) === Constants::PERMISSION_DELETE;

		return [
			'fileId' => (int)$node->getId(),
			'name' => $node->getName(),
			'path' => $node->getInternalPath(),
			'mimeType' => $node->getMimetype(),
			'size' => (int)$node->getSize(),
			'modifiedAt' => $this->formatTimestamp(timestamp: $node->getMTime()),
			'isFolder' => $isFolder,
			'thumbnailUrl' => $thumbnailUrl,
			'canEdit' => $canEdit,
			'canDelete' => $canDelete,
		];
	}//end buildFileMetadata()

	/**
	 * Resolve the placement's configured folder for the given user.
	 *
	 * Prefers `fileId` (stable across renames) and falls back to
	 * `folderPath` (REQ-FLS-002 / design D5).
	 *
	 * @param string $userId Viewing user's UID.
	 * @param array $config Placement config.
	 *
	 * @return Folder
	 *
	 * @throws FolderNotFoundException When neither identifier resolves.
	 * @throws NoAccessException When the resolved folder is
	 *                           unreadable.
	 *
	 * @spec openspec/specs/files-widget/spec.md
	 */
	public function resolveConfiguredFolder(string $userId, array $config): Folder {
		try {
			$userFolder = $this->rootFolder->getUserFolder(userId: $userId);
		} catch (Throwable $e) {
			throw new FolderNotFoundException();
		}

		$node = $this->configuredNode(userFolder: $userFolder, config: $config);
		if ($node === null) {
			throw new FolderNotFoundException();
		}

		if ($node->isReadable() === false) {
			throw new NoAccessException();
		}

		return $node;
	}//end resolveConfiguredFolder()

	/**
	 * The folder a placement config names: by `fileId` first, else by
	 * `folderPath`, else — for an unconfigured placement — the home folder.
	 *
	 * @param Folder $userFolder The viewer's home folder.
	 * @param array  $config     Placement config.
	 *
	 * @return Folder|null The folder, or null when the configured one is gone.
	 */
	private function configuredNode(Folder $userFolder, array $config): ?Folder {
		$fileId = $this->extractFileId(config: $config);
		$folderPath = (string)($config['folderPath'] ?? '');

		$node = $this->resolveByFileId(userFolder: $userFolder, fileId: $fileId);
		if ($node !== null) {
			return $node;
		}

		if ($folderPath !== '') {
			return $this->resolveByPath(userFolder: $userFolder, folderPath: $folderPath);
		}

		// Unconfigured placement (neither a fileId nor a folderPath set):
		// default to the user's home folder rather than erroring, so a
		// freshly-added or seeded files widget shows something usable until a
		// folder is chosen. A configured-but-missing folder still yields null.
		return $this->homeWhenUnconfigured(userFolder: $userFolder, fileId: $fileId);
	}//end configuredNode()

	/**
	 * The home folder for a placement without a `fileId`; null for one
	 * whose `fileId` no longer resolves.
	 *
	 * @param Folder       $userFolder The viewer's home folder.
	 * @param integer|null $fileId     Configured file id, or null.
	 *
	 * @return Folder|null The home folder, or null.
	 */
	private function homeWhenUnconfigured(Folder $userFolder, ?int $fileId): ?Folder {
		if ($fileId === null) {
			return $userFolder;
		}

		return null;
	}//end homeWhenUnconfigured()

	/**
	 * Resolve the placement folder by its stable `fileId`.
	 *
	 * An id that resolves to nothing, or to a node that is not a folder,
	 * yields null so the caller can fall back to `folderPath`.
	 *
	 * @param Folder $userFolder The viewer's home folder.
	 * @param integer|null $fileId Configured file id, or null.
	 *
	 * @return Folder|null The resolved folder, or null.
	 *
	 * @spec openspec/specs/files-widget/spec.md
	 */
	private function resolveByFileId(Folder $userFolder, ?int $fileId): ?Folder {
		if ($fileId === null) {
			return null;
		}

		return $this->firstFolder(matches: $userFolder->getById(id: $fileId));
	}//end resolveByFileId()

	/**
	 * The first match when it is a folder.
	 *
	 * @param array<int, Node> $matches The id lookup's matches.
	 *
	 * @return Folder|null The folder, or null.
	 */
	private function firstFolder(array $matches): ?Folder {
		if (count($matches) > 0 && $matches[0] instanceof Folder) {
			return $matches[0];
		}

		return null;
	}//end firstFolder()

	/**
	 * Resolve the placement folder by its configured path.
	 *
	 * A path of `/` denotes the viewer's home folder itself. A missing
	 * path, or one naming a non-folder node, yields null so the caller
	 * can raise `FolderNotFoundException`.
	 *
	 * @param Folder $userFolder The viewer's home folder.
	 * @param string $folderPath Configured path, relative to the home folder.
	 *
	 * @return Folder|null The resolved folder, or null.
	 *
	 * @spec openspec/specs/files-widget/spec.md
	 */
	private function resolveByPath(Folder $userFolder, string $folderPath): ?Folder {
		$normalised = ('/' . trim(string: $folderPath, characters: '/'));
		try {
			$candidate = $userFolder;
			if ($normalised !== '/') {
				$candidate = $userFolder->get(path: $normalised);
			}

			if ($candidate instanceof Folder) {
				return $candidate;
			}
		} catch (NotFoundException $e) {
			// Caller falls through to its own not-found handling.
		}

		return null;
	}//end resolveByPath()

	/**
	 * Descend into a sub-path relative to a root folder. Each
	 * intermediate node must be a readable folder; otherwise a
	 * `FolderNotFoundException` is thrown (we do NOT distinguish
	 * between "missing" and "unreadable subfolder" — the API
	 * deliberately treats both the same so unauthorised paths are
	 * indistinguishable from invented ones, REQ-FLS-004).
	 *
	 * @param Folder $root The configured root folder.
	 * @param string $subPath The sub-path requested by the client.
	 *
	 * @return Folder
	 *
	 * @throws FolderNotFoundException When the sub-path does not
	 *                                 resolve to a readable folder.
	 */
	private function descendInto(Folder $root, string $subPath): Folder {
		$clean = ('/' . trim(string: $subPath, characters: '/'));
		if ($clean === '/') {
			return $root;
		}

		// Reject path traversal attempts.
		if (str_contains(haystack: $clean, needle: '..') === true) {
			throw new FolderNotFoundException();
		}

		$node = $this->nodeBelow(root: $root, path: ltrim(string: $clean, characters: '/'));
		if (($node instanceof Folder) === false || $node->isReadable() === false) {
			throw new FolderNotFoundException();
		}

		return $node;
	}//end descendInto()

	/**
	 * The node at a relative path below a folder; missing and not
	 * permitted paths are both answered as not found.
	 *
	 * @param Folder $root The folder to look below.
	 * @param string $path The relative path.
	 *
	 * @return Node The node.
	 *
	 * @throws FolderNotFoundException When the path is missing or not permitted.
	 */
	private function nodeBelow(Folder $root, string $path): Node {
		try {
			return $root->get(path: $path);
		} catch (NotFoundException $e) {
			throw new FolderNotFoundException();
		} catch (NotPermittedException $e) {
			throw new FolderNotFoundException();
		}
	}//end nodeBelow()

	/**
	 * Coerce the placement's `fileId` config value into a positive
	 * integer or `null`.
	 *
	 * @param array $config Placement config.
	 *
	 * @return integer|null
	 */
	private function extractFileId(array $config): ?int {
		if (isset($config['fileId']) === false) {
			return null;
		}

		$value = $config['fileId'];
		if (is_int($value) === true && $value > 0) {
			return $value;
		}

		return $this->positiveIntFromDigits(value: $value);
	}//end extractFileId()

	/**
	 * A positive integer from a digit-only string; null for anything else.
	 *
	 * @param mixed $value The candidate value.
	 *
	 * @return integer|null The integer, or null.
	 */
	private function positiveIntFromDigits(mixed $value): ?int {
		if (is_string($value) === false || ctype_digit($value) === false) {
			return null;
		}

		$coerced = (int)$value;
		if ($coerced > 0) {
			return $coerced;
		}

		return null;
	}//end positiveIntFromDigits()

	/**
	 * Normalise the MIME filter array — drops non-strings and empty
	 * entries.
	 *
	 * @param mixed $filter Raw filter value from the placement config.
	 *
	 * @return list<string>
	 */
	private function normaliseMimeFilter(mixed $filter): array {
		if (is_array($filter) === false) {
			return [];
		}

		$normalised = [];
		foreach ($filter as $entry) {
			if (is_string($entry) === false) {
				continue;
			}

			$token = strtolower(string: trim(string: $entry));
			if ($token === '') {
				continue;
			}

			$normalised[] = $token;
		}

		return $normalised;
	}//end normaliseMimeFilter()

	/**
	 * Test a node's MIME type against the configured filter list.
	 *
	 * @param Node $node The candidate node.
	 * @param list<string> $filter Normalised filter list.
	 *
	 * @return boolean
	 */
	private function matchesMimeFilter(Node $node, array $filter): bool {
		if (count($filter) === 0) {
			return true;
		}

		return $this->mimeMatchesFilter(mime: strtolower(string: (string)$node->getMimetype()), filter: $filter);
	}//end matchesMimeFilter()

	/**
	 * Detect the MIME type of a local file path using PHP's finfo
	 * extension (falling back to 'application/octet-stream').
	 *
	 * Used by uploadFiles to enforce the placement's mimeTypeFilter
	 * on writes (M3).
	 *
	 * @param string $path Absolute path to the local file.
	 *
	 * @return string The detected MIME type (lowercase).
	 */
	private function detectMimeType(string $path): string {
		if (function_exists('finfo_open') === true) {
			$finfo = finfo_open(flags: FILEINFO_MIME_TYPE);
			if ($finfo !== false) {
				$mime = finfo_file(finfo: $finfo, filename: $path);
				finfo_close(finfo: $finfo);
				if (is_string(value: $mime) === true && $mime !== '') {
					return strtolower(string: $mime);
				}
			}
		}

		return 'application/octet-stream';
	}//end detectMimeType()

	/**
	 * Test a raw MIME type string against a normalised filter list.
	 *
	 * Accepts both exact matches (`image/png`) and wildcard prefixes
	 * (`image/*`) — shared by the read-path `matchesMimeFilter` and the
	 * upload screening.
	 *
	 * @param string $mime The detected MIME type.
	 * @param list<string> $filter Normalised filter list.
	 *
	 * @return boolean True when the MIME passes the filter.
	 */
	private function mimeMatchesFilter(string $mime, array $filter): bool {
		if (count($filter) === 0) {
			return true;
		}

		foreach ($filter as $pattern) {
			if (self::mimeMatchesPattern(mime: $mime, pattern: $pattern) === true) {
				return true;
			}
		}

		return false;
	}//end mimeMatchesFilter()

	/**
	 * Test a MIME type against one filter entry: an exact type, or a
	 * `type/*` wildcard prefix.
	 *
	 * @param string $mime    The MIME type.
	 * @param string $pattern One normalised filter entry.
	 *
	 * @return bool True on a match.
	 */
	private static function mimeMatchesPattern(string $mime, string $pattern): bool {
		if (str_ends_with(haystack: $pattern, needle: '/*') === true) {
			return str_starts_with(haystack: $mime, needle: substr(string: $pattern, offset: 0, length: -1));
		}

		return $pattern === $mime;
	}//end mimeMatchesPattern()

	/**
	 * Sort an item list according to the placement config.
	 *
	 * @param list<array<string,mixed>> $items Items to sort.
	 * @param string $sortBy Sort field.
	 * @param boolean $sortDescending Direction.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function sortItems(array $items, string $sortBy, bool $sortDescending): array {
		usort(array: $items, callback: self::itemComparator(sortBy: $sortBy));

		if ($sortDescending === true) {
			$items = array_reverse(array: $items);
		}

		return array_values(array: $items);
	}//end sortItems()

	/**
	 * The comparator for a sort field: `modified`, `size` or `type`, and
	 * natural case-insensitive name order for `name` or any other value.
	 *
	 * @param string $sortBy Sort field.
	 *
	 * @return callable(array<string,mixed>, array<string,mixed>): int The comparator.
	 */
	private static function itemComparator(string $sortBy): callable {
		return match ($sortBy) {
			'modified' => static fn (array $a, array $b): int => strcmp(string1: (string)($a['modifiedAt'] ?? ''), string2: (string)($b['modifiedAt'] ?? '')),
			'size' => static fn (array $a, array $b): int => ((int)($a['size'] ?? 0)) <=> ((int)($b['size'] ?? 0)),
			'type' => static fn (array $a, array $b): int => strcmp(string1: (string)($a['mimeType'] ?? ''), string2: (string)($b['mimeType'] ?? '')),
			default => static fn (array $a, array $b): int => strnatcasecmp(string1: (string)($a['name'] ?? ''), string2: (string)($b['name'] ?? '')),
		};
	}//end itemComparator()

	/**
	 * Slice the sorted item list into a single page using an opaque
	 * offset cursor (REQ-FLS-003 design D3).
	 *
	 * @param list<array<string,mixed>> $items Items to paginate.
	 * @param integer $limit Page size.
	 * @param string $cursor Numeric offset (or empty).
	 *
	 * @return array{items: list<array<string,mixed>>, nextCursor: ?string}
	 */
	private function paginate(array $items, int $limit, string $cursor): array {
		$offset = 0;
		if ($cursor !== '' && ctype_digit($cursor) === true) {
			$offset = (int)$cursor;
		}

		if ($offset < 0) {
			$offset = 0;
		}

		$page = array_slice(array: $items, offset: $offset, length: $limit);
		$next = ($offset + count($page));
		$nextCursor = null;
		if ($next < count($items)) {
			$nextCursor = (string)$next;
		}

		return [
			'items' => array_values(array: $page),
			'nextCursor' => $nextCursor,
		];
	}//end paginate()

	/**
	 * Clamp a caller-supplied limit into the supported range.
	 *
	 * @param integer $limit Requested limit.
	 *
	 * @return integer
	 */
	private function clampLimit(int $limit): int {
		if ($limit < 1) {
			return self::DEFAULT_LIMIT;
		}

		if ($limit > self::MAX_LIMIT) {
			return self::MAX_LIMIT;
		}

		return $limit;
	}//end clampLimit()

	/**
	 * Render a Unix timestamp as ISO 8601 in UTC (REQ-FLS-003).
	 *
	 * @param integer $timestamp The mtime.
	 *
	 * @return string
	 */
	private function formatTimestamp(int $timestamp): string {
		return gmdate(format: 'Y-m-d\\TH:i:s\\Z', timestamp: $timestamp);
	}//end formatTimestamp()

	/**
	 * Find a free filename inside the target folder by appending
	 * `(1)`, `(2)` etc. when the desired name is taken (REQ-FLS-007).
	 *
	 * @param Folder $folder Target folder.
	 * @param string $desired Desired leaf name.
	 *
	 * @return string
	 */
	private function resolveAvailableName(Folder $folder, string $desired): string {
		$sanitised = self::sanitiseUploadName(desired: $desired);
		if ($folder->nodeExists(path: $sanitised) === false) {
			return $sanitised;
		}

		return $this->firstFreeNumberedName(folder: $folder, name: $sanitised);
	}//end resolveAvailableName()

	/**
	 * Replace path separators and NUL with `_`; an empty, `.` or `..`
	 * result becomes `upload`.
	 *
	 * @param string $desired Desired leaf name.
	 *
	 * @return string The safe leaf name.
	 */
	private static function sanitiseUploadName(string $desired): string {
		$sanitised = str_replace(search: ['/', '\\', "\0"], replace: '_', subject: $desired);
		if ($sanitised === '' || $sanitised === '.' || $sanitised === '..') {
			return 'upload';
		}

		return $sanitised;
	}//end sanitiseUploadName()

	/**
	 * The first of `name (1)` … `name (999)` that is free in the folder,
	 * else a `name (<unique id>)` variant.
	 *
	 * @param Folder $folder Target folder.
	 * @param string $name   The taken, sanitised name.
	 *
	 * @return string The free name.
	 */
	private function firstFreeNumberedName(Folder $folder, string $name): string {
		for ($i = 1; $i <= 999; $i++) {
			$candidate = self::numberedName(name: $name, number: (string)$i);
			if ($folder->nodeExists(path: $candidate) === false) {
				return $candidate;
			}
		}

		return self::numberedName(name: $name, number: uniqid());
	}//end firstFreeNumberedName()

	/**
	 * Insert ` (<number>)` before a name's extension; a leading-dot name
	 * or one without a dot gets it at the end.
	 *
	 * @param string $name   The sanitised name.
	 * @param string $number The number to insert.
	 *
	 * @return string The numbered name.
	 */
	private static function numberedName(string $name, string $number): string {
		$extPos = strrpos(haystack: $name, needle: '.');
		if ($extPos !== false && $extPos !== 0) {
			return substr(string: $name, offset: 0, length: $extPos) . ' (' . $number . ')' . substr(string: $name, offset: $extPos);
		}

		return $name . ' (' . $number . ')';
	}//end numberedName()

	/**
	 * Walk parents of `$candidate` and return true when `$parent` is
	 * one of them (or the same node).
	 *
	 * @param Folder $parent Putative ancestor.
	 * @param Node $candidate Node to test.
	 *
	 * @return boolean
	 */
	private function isDescendantOf(Folder $parent, Node $candidate): bool {
		$parentId = $parent->getId();
		if ($candidate->getId() === $parentId) {
			return true;
		}

		return $this->hasAncestorWithId(node: $candidate, ancestorId: $parentId);
	}//end isDescendantOf()

	/**
	 * Walk at most 32 parents up from a node looking for an id; the walk
	 * stops at the user-folder root or on a parent that cannot be read.
	 *
	 * @param Node  $node       The node to start from.
	 * @param mixed $ancestorId The id to look for.
	 *
	 * @return bool True when a parent within reach has the id.
	 */
	private function hasAncestorWithId(Node $node, mixed $ancestorId): bool {
		$cursor = $node;
		for ($depth = 0; $depth < 32; $depth++) {
			$next = $this->parentOf(node: $cursor);
			if ($next === null || $next->getId() === $ancestorId) {
				return $next !== null;
			}

			// Reached the user-folder root without hitting the ancestor.
			if (in_array(needle: $next->getPath(), haystack: ['/', ''], strict: true) === true) {
				return false;
			}

			$cursor = $next;
		}

		return false;
	}//end hasAncestorWithId()

	/**
	 * A node's parent, null when it cannot be read.
	 *
	 * @param Node $node The node.
	 *
	 * @return Node|null The parent, or null.
	 */
	private function parentOf(Node $node): ?Node {
		try {
			return $node->getParent();
		} catch (Throwable $e) {
			return null;
		}
	}//end parentOf()
}//end class
