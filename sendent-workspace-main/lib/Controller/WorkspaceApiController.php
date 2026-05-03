<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sendent B.V.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SendentWorkspace\Controller;

use OCA\SendentWorkspace\Service\WorkspaceService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IManager;
use OCP\Dashboard\IWidget;
use OCP\Dashboard\Model\WidgetItem;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\Files\IRootFolder;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\AppFramework\Http\StreamResponse;

class WorkspaceApiController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IManager $dashboardManager,
		private IConfig $config,
		private ?string $userId,
		private WorkspaceService $service,
		private IGroupManager $groupManager,
		private IRootFolder $rootFolder,
		private IAppData $appData,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get workspace layout for a specific group
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/layout/{groupId}')]
	public function getLayout(string $groupId = 'default'): DataResponse {
		try {
			$layout = $this->service->getWorkspaceLayout($groupId);
			return new DataResponse([
				'status' => 'success',
				'layout' => $layout,
				'groupId' => $groupId,
			]);
		} catch (\Exception $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Save workspace layout for a specific group (admin only)
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/layout/{groupId}')]
	public function saveLayout(string $groupId, array $layout): DataResponse {
		// Check if user is admin
		if (!$this->groupManager->isAdmin($this->userId)) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Unauthorized',
			], Http::STATUS_FORBIDDEN);
		}

		// Validate group ID
		if ($groupId !== 'default' && !$this->isValidGroupId($groupId)) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Invalid group ID',
			], Http::STATUS_BAD_REQUEST);
		}

		// Validate layout structure
		foreach ($layout as $widget) {
			if (!is_array($widget)
				|| !isset($widget['id'], $widget['type'], $widget['x'], $widget['y'], $widget['w'], $widget['h'])
			) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'Invalid layout structure: each widget must have id, type, x, y, w, h',
				], Http::STATUS_BAD_REQUEST);
			}
		}

		try {
			$this->service->saveWorkspaceLayout($groupId, $layout);
			return new DataResponse([
				'status' => 'success',
				'message' => 'Layout saved successfully',
			]);
		} catch (\Exception $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get configured groups and their order
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/groups')]
	public function getGroups(): DataResponse {
		try {
			// Get all groups
			$allGroups = $this->groupManager->search('');
			$allGroupIds = array_map(fn($group) => $group->getGID(), $allGroups);

			// Get configured active groups
			$activeGroups = json_decode(
				$this->config->getAppValue('sendentworkspace', 'group_order', '[]'),
				true
			) ?? [];

			// Inactive groups are all groups not in active list
			$inactiveGroups = array_diff($allGroupIds, $activeGroups);

			return new DataResponse([
				'status' => 'success',
				'active' => $activeGroups,
				'inactive' => array_values($inactiveGroups),
			]);
		} catch (\Exception $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update groups order (admin only)
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/groups')]
	public function updateGroups(array $groups): DataResponse {
		// Check if user is admin
		if (!$this->groupManager->isAdmin($this->userId)) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Unauthorized',
			], Http::STATUS_FORBIDDEN);
		}

		try {
			$this->config->setAppValue('sendentworkspace', 'group_order', json_encode($groups));
			return new DataResponse([
				'status' => 'success',
				'message' => 'Groups order updated successfully',
			]);
		} catch (\Exception $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	// ─── Multi-dashboard endpoints ───

	/**
	 * Get all dashboards for a group
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/dashboards/{groupId}')]
	public function getGroupDashboards(string $groupId): DataResponse {
		try {
			$data = $this->service->getGroupDashboards($groupId);
			return new DataResponse([
				'status' => 'success',
				'dashboards' => array_map(fn($d) => [
					'id' => $d['id'],
					'name' => $d['name'],
					'icon' => $d['icon'] ?? 'ViewDashboard',
					'createdAt' => $d['createdAt'] ?? null,
					'updatedAt' => $d['updatedAt'] ?? null,
				], $data['dashboards'] ?? []),
				'defaultDashboardId' => $data['defaultDashboardId'] ?? null,
			]);
		} catch (\Exception $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single dashboard's full data (including layout)
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/dashboards/{groupId}/{dashboardId}')]
	public function getGroupDashboard(string $groupId, string $dashboardId): DataResponse {
		$dash = $this->service->getGroupDashboard($groupId, $dashboardId);
		if ($dash === null) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Dashboard not found',
			], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse([
			'status' => 'success',
			'dashboard' => $dash,
		]);
	}

	/**
	 * Create a new dashboard in a group (admin only)
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/dashboards/{groupId}')]
	public function createGroupDashboard(string $groupId, string $name, array $layout = [], string $icon = 'ViewDashboard'): DataResponse {
		if (!$this->groupManager->isAdmin($this->userId)) {
			return new DataResponse(['status' => 'error', 'message' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		try {
			$dash = $this->service->createGroupDashboard($groupId, $name, $layout, $icon);
			return new DataResponse(['status' => 'success', 'dashboard' => $dash]);
		} catch (\Exception $e) {
			return new DataResponse(['status' => 'error', 'message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a dashboard (admin only)
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/dashboards/{groupId}/{dashboardId}')]
	public function updateGroupDashboard(string $groupId, string $dashboardId, ?string $name = null, ?array $layout = null, ?string $icon = null): DataResponse {
		if (!$this->groupManager->isAdmin($this->userId)) {
			return new DataResponse(['status' => 'error', 'message' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		$updates = [];
		if ($name !== null) {
			$updates['name'] = $name;
		}
		if ($icon !== null) {
			$updates['icon'] = $icon;
		}
		if ($layout !== null) {
			$updates['layout'] = $layout;
		}
		try {
			$this->service->saveGroupDashboard($groupId, $dashboardId, $updates);
			return new DataResponse(['status' => 'success', 'message' => 'Dashboard updated']);
		} catch (\Exception $e) {
			return new DataResponse(['status' => 'error', 'message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a dashboard from a group (admin only)
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/dashboards/{groupId}/{dashboardId}')]
	public function deleteGroupDashboard(string $groupId, string $dashboardId): DataResponse {
		if (!$this->groupManager->isAdmin($this->userId)) {
			return new DataResponse(['status' => 'error', 'message' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		$deleted = $this->service->deleteGroupDashboard($groupId, $dashboardId);
		if (!$deleted) {
			return new DataResponse(['status' => 'error', 'message' => 'Cannot delete the only dashboard'], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(['status' => 'success', 'message' => 'Dashboard deleted']);
	}

	/**
	 * Set the default dashboard for a group (admin only)
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/dashboards/{groupId}/default')]
	public function setDefaultDashboard(string $groupId, string $dashboardId): DataResponse {
		if (!$this->groupManager->isAdmin($this->userId)) {
			return new DataResponse(['status' => 'error', 'message' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		try {
			$this->service->setDefaultDashboard($groupId, $dashboardId);
			return new DataResponse(['status' => 'success', 'message' => 'Default dashboard set']);
		} catch (\Exception $e) {
			return new DataResponse(['status' => 'error', 'message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	// ─── User dashboard endpoints ───

	/**
	 * Get the current user's dashboards
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/user-dashboards')]
	public function getUserDashboards(): DataResponse {
		$data = $this->service->getUserDashboards($this->userId);
		return new DataResponse([
			'status' => 'success',
			'dashboards' => $data['dashboards'] ?? [],
			'allowUserDashboards' => $this->service->getAllowUserDashboards(),
		]);
	}

	/**
	 * Create a new user dashboard
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/user-dashboards')]
	public function createUserDashboard(string $name, array $layout = []): DataResponse {
		if (!$this->service->getAllowUserDashboards()) {
			return new DataResponse(['status' => 'error', 'message' => 'User dashboards are not enabled'], Http::STATUS_FORBIDDEN);
		}
		try {
			$dash = $this->service->createUserDashboard($this->userId, $name, $layout);
			return new DataResponse(['status' => 'success', 'dashboard' => $dash]);
		} catch (\Exception $e) {
			return new DataResponse(['status' => 'error', 'message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a user dashboard
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/user-dashboards/{dashboardId}')]
	public function updateUserDashboard(string $dashboardId, ?string $name = null, ?array $layout = null): DataResponse {
		$updates = [];
		if ($name !== null) {
			$updates['name'] = $name;
		}
		if ($layout !== null) {
			$updates['layout'] = $layout;
		}
		$updated = $this->service->updateUserDashboard($this->userId, $dashboardId, $updates);
		if (!$updated) {
			return new DataResponse(['status' => 'error', 'message' => 'Dashboard not found'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse(['status' => 'success', 'message' => 'Dashboard updated']);
	}

	/**
	 * Delete a user dashboard
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/user-dashboards/{dashboardId}')]
	public function deleteUserDashboard(string $dashboardId): DataResponse {
		$deleted = $this->service->deleteUserDashboard($this->userId, $dashboardId);
		if (!$deleted) {
			return new DataResponse(['status' => 'error', 'message' => 'Dashboard not found'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse(['status' => 'success', 'message' => 'Dashboard deleted']);
	}

	// ─── Active dashboard + Settings ───

	/**
	 * Set the user's active dashboard preference
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/active-dashboard')]
	public function setActiveDashboard(string $dashboardId): DataResponse {
		$this->service->setActiveDashboard($this->userId, $dashboardId);
		return new DataResponse(['status' => 'success']);
	}

	/**
	 * Get app settings (admin only)
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/settings')]
	public function getSettings(): DataResponse {
		if (!$this->groupManager->isAdmin($this->userId)) {
			return new DataResponse(['status' => 'error', 'message' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse([
			'status' => 'success',
			'allowUserDashboards' => $this->service->getAllowUserDashboards(),
		]);
	}

	/**
	 * Update app settings (admin only)
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/settings')]
	public function updateSettings(bool $allow_user_dashboards): DataResponse {
		if (!$this->groupManager->isAdmin($this->userId)) {
			return new DataResponse(['status' => 'error', 'message' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		$this->service->setAllowUserDashboards($allow_user_dashboards);
		return new DataResponse(['status' => 'success', 'message' => 'Settings updated']);
	}

	/**
	 * Get the items for the Nextcloud widgets
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/widget-items')]
	public function getWidgetItems(array $sinceIds = [], int $limit = 7, array $widgets = []): DataResponse {
		$items = [];
		$widgetMeta = [];
		$requestedWidgets = $this->getShownWidgets($widgets);

		foreach ($requestedWidgets as $widget) {
			// Get widget metadata
			if ($widget instanceof IIconWidget) {
				$widgetMeta[$widget->getId()] = [
					'iconUrl' => $widget->getIconUrl(),
				];
			}

			// Get widget items — try v2 API first (NC 27+), then v1
			$widgetItemsList = null;
			if ($widget instanceof IAPIWidgetV2) {
				$widgetItemsList = $widget->getItemsV2(
					$this->userId,
					$sinceIds[$widget->getId()] ?? null,
					$limit
				)->getItems();
			} elseif ($widget instanceof IAPIWidget) {
				$widgetItemsList = $widget->getItems(
					$this->userId,
					$sinceIds[$widget->getId()] ?? null,
					$limit
				);
			}

			if ($widgetItemsList !== null) {
				$items[$widget->getId()] = array_map(static function (WidgetItem $item) {
					return [
						'subtitle' => $item->getSubtitle(),
						'title' => $item->getTitle(),
						'link' => $item->getLink(),
						'iconUrl' => $item->getIconUrl(),
						'overlayIconUrl' => $item->getOverlayIconUrl(),
						'sinceId' => $item->getSinceId(),
					];
				}, $widgetItemsList);
			}
		}

		return new DataResponse([
			'items' => $items,
			'meta' => $widgetMeta,
		]);
	}

	/**
	 * Create a file via DAV and return URL to open it
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/create-file')]
	public function createFile(string $filename, string $dir = '/', string $content = ''): DataResponse {
		try {
			// Validate filename: reject path traversal and dangerous characters
			if (strlen($filename) === 0 || strlen($filename) > 255
				|| strpos($filename, '..') !== false
				|| strpos($filename, '/') !== false
				|| strpos($filename, '\\') !== false
				|| strpos($filename, "\0") !== false
				|| !preg_match('/^[a-zA-Z0-9_\-. ]+$/', $filename)
			) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'Invalid filename',
				], Http::STATUS_BAD_REQUEST);
			}

			// Validate directory: reject path traversal
			if (strpos($dir, '..') !== false || strpos($dir, "\0") !== false) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'Invalid directory path',
				], Http::STATUS_BAD_REQUEST);
			}

			$userFolder = $this->rootFolder->getUserFolder($this->userId);

			$targetFolder = $userFolder;
			if ($dir !== '/' && $dir !== '') {
				if ($userFolder->nodeExists($dir)) {
					$targetFolder = $userFolder->get($dir);
				} else {
					$targetFolder = $userFolder->newFolder($dir);
				}
			}

			if ($targetFolder->nodeExists($filename)) {
				$file = $targetFolder->get($filename);
				$file->putContent($content);
			} else {
				$file = $targetFolder->newFile($filename);
				$file->putContent($content);
			}

			$fileId = $file->getId();
			$url = \OC::$server->getURLGenerator()->linkToRouteAbsolute(
				'files.view.index',
				['openfile' => $fileId]
			);

			return new DataResponse([
				'status' => 'success',
				'fileId' => $fileId,
				'url' => $url,
			]);
		} catch (\Exception $e) {
			return new DataResponse([
				'status' => 'error',
				'message' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Upload an image resource (admin only) - accepts base64 encoded images
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/upload-resource')]
	public function uploadResource(): DataResponse {
		// Check if user is admin
		if (!$this->groupManager->isAdmin($this->userId)) {
			return new DataResponse([
				'error' => 'Unauthorized',
			], Http::STATUS_FORBIDDEN);
		}

		try {
			// Get JSON input
			$input = file_get_contents('php://input');
			$data = json_decode($input, true);

			if (!isset($data['base64']) || empty($data['base64'])) {
				return new DataResponse([
					'error' => 'No file data provided',
				], Http::STATUS_BAD_REQUEST);
			}

			$base64 = $data['base64'];

			// Extract mime type and data from data URL
			if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
				$imageType = $matches[1];
				$base64 = substr($base64, strpos($base64, ',') + 1);
			} else {
				return new DataResponse([
					'error' => 'Invalid image format',
				], Http::STATUS_BAD_REQUEST);
			}

			$imageData = base64_decode($base64);

			if ($imageData === false) {
				return new DataResponse([
					'error' => 'Failed to decode image data',
				], Http::STATUS_BAD_REQUEST);
			}

			// Validate image type
			$allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'svg', 'webp'];
			if (!in_array(strtolower($imageType), $allowedTypes)) {
				return new DataResponse([
					'error' => 'Invalid file type. Only images are allowed.',
				], Http::STATUS_BAD_REQUEST);
			}

			// Verify actual file content for non-SVG images
			if (strtolower($imageType) !== 'svg' && strtolower($imageType) !== 'svg+xml') {
				$imageInfo = @getimagesizefromstring($imageData);
				if ($imageInfo === false) {
					return new DataResponse([
						'error' => 'Invalid image data. The file does not appear to be a valid image.',
					], Http::STATUS_BAD_REQUEST);
				}

				// Verify MIME type matches declared type
				$detectedMime = $imageInfo['mime'] ?? '';
				$expectedMimes = [
					'jpeg' => ['image/jpeg'],
					'jpg' => ['image/jpeg'],
					'png' => ['image/png'],
					'gif' => ['image/gif'],
					'webp' => ['image/webp'],
				];

				if (isset($expectedMimes[strtolower($imageType)])) {
					if (!in_array($detectedMime, $expectedMimes[strtolower($imageType)])) {
						return new DataResponse([
							'error' => 'File type mismatch. The file content does not match the declared type.',
						], Http::STATUS_BAD_REQUEST);
					}
				}
			}

			// Sanitize SVG files using DOM-based whitelist approach
			if (strtolower($imageType) === 'svg' || strtolower($imageType) === 'svg+xml') {
				$imageData = $this->sanitizeSvg($imageData);
				if ($imageData === null) {
					return new DataResponse([
						'error' => 'Invalid or malicious SVG content',
					], Http::STATUS_BAD_REQUEST);
				}
				$imageType = 'svg';
			}

			// Validate file size (limit to 5MB)
			if (strlen($imageData) > 5 * 1024 * 1024) {
				return new DataResponse([
					'error' => 'File too large. Maximum size is 5MB.',
				], Http::STATUS_BAD_REQUEST);
			}

			// Generate unique filename
			$filename = uniqid('resource_', true) . '.' . $imageType;

			// Store in app data
			try {
				$folder = $this->appData->getFolder('resources');
			} catch (NotFoundException $e) {
				$folder = $this->appData->newFolder('resources');
			}

			$newFile = $folder->newFile($filename);
			$newFile->putContent($imageData);

			return new DataResponse([
				'url' => '/apps/sendentworkspace/resource/' . $filename,
			]);
		} catch (\Exception $e) {
			return new DataResponse([
				'error' => $e->getMessage(),
				'message' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * List all uploaded resources
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/resources')]
	public function listResources(): DataResponse {
		try {
			try {
				$folder = $this->appData->getFolder('resources');
			} catch (NotFoundException $e) {
				// No resources folder yet
				return new DataResponse([
					'resources' => [],
				]);
			}

			$resources = [];
			foreach ($folder->getDirectoryListing() as $file) {
				$resources[] = [
					'name' => $file->getName(),
					'url' => '/apps/sendentworkspace/resource/' . $file->getName(),
				];
			}

			return new DataResponse([
				'resources' => $resources,
			]);
		} catch (\Exception $e) {
			return new DataResponse([
				'error' => $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Serve a resource file
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/resource/{filename}')]
	public function getResource(string $filename): StreamResponse {
		try {
			$folder = $this->appData->getFolder('resources');
			$file = $folder->getFile($filename);

			// Create a stream from the file content
			$stream = fopen('php://memory', 'r+');
			fwrite($stream, $file->getContent());
			rewind($stream);

			$response = new StreamResponse($stream);

			// Set appropriate content type based on extension
			$extension = pathinfo($filename, PATHINFO_EXTENSION);
			$contentTypes = [
				'jpg' => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png' => 'image/png',
				'gif' => 'image/gif',
				'svg' => 'image/svg+xml',
				'webp' => 'image/webp',
			];

			$contentType = $contentTypes[$extension] ?? 'application/octet-stream';
			$response->addHeader('Content-Type', $contentType);
			$response->addHeader('Cache-Control', 'public, max-age=31536000');

			return $response;
		} catch (\Exception $e) {
			$response = new StreamResponse(fopen('php://memory', 'r'));
			$response->setStatus(Http::STATUS_NOT_FOUND);
			return $response;
		}
	}

	/**
	 * Validate a group ID for safe use as a config key
	 */
	private function isValidGroupId(string $groupId): bool {
		return strlen($groupId) > 0
			&& strlen($groupId) <= 64
			&& preg_match('/^[a-zA-Z0-9_\- ]+$/', $groupId);
	}

	/**
	 * Sanitize SVG content using DOM-based whitelist approach
	 */
	private function sanitizeSvg(string $svgData): ?string {
		$allowedElements = [
			'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line',
			'polyline', 'polygon', 'text', 'tspan', 'defs', 'clippath',
			'use', 'image', 'style', 'lineargradient', 'radialgradient',
			'stop', 'mask', 'pattern', 'symbol', 'title', 'desc',
		];

		$allowedAttributes = [
			'id', 'class', 'style', 'd', 'x', 'y', 'x1', 'y1', 'x2', 'y2',
			'cx', 'cy', 'r', 'rx', 'ry', 'width', 'height', 'viewbox',
			'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
			'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'fill-opacity',
			'opacity', 'transform', 'points', 'font-size', 'font-family',
			'font-weight', 'text-anchor', 'dominant-baseline', 'dx', 'dy',
			'clip-path', 'mask', 'filter', 'gradientunits', 'gradienttransform',
			'offset', 'stop-color', 'stop-opacity', 'patternunits',
			'preserveaspectratio', 'xmlns', 'xmlns:xlink', 'version',
			'href', 'xlink:href',
		];

		libxml_use_internal_errors(true);
		$dom = new \DOMDocument();
		if (!$dom->loadXML($svgData, LIBXML_NONET | LIBXML_NOENT)) {
			libxml_clear_errors();
			return null;
		}
		libxml_clear_errors();

		$this->sanitizeSvgNode($dom->documentElement, $allowedElements, $allowedAttributes);

		$result = $dom->saveXML($dom->documentElement);
		return $result !== false ? $result : null;
	}

	/**
	 * Recursively sanitize SVG DOM nodes
	 */
	private function sanitizeSvgNode(\DOMNode $node, array $allowedElements, array $allowedAttributes): void {
		if ($node->nodeType !== XML_ELEMENT_NODE) {
			return;
		}

		$nodeName = strtolower($node->nodeName);

		// Remove disallowed elements (script, foreignObject, etc.)
		if (!in_array($nodeName, $allowedElements)) {
			$node->parentNode->removeChild($node);
			return;
		}

		// Remove disallowed attributes (on* event handlers, javascript: URLs)
		$attributesToRemove = [];
		foreach ($node->attributes as $attr) {
			$attrName = strtolower($attr->name);
			$attrValue = strtolower(trim($attr->value));

			// Remove on* event handlers
			if (strpos($attrName, 'on') === 0) {
				$attributesToRemove[] = $attr->name;
				continue;
			}

			// Remove non-whitelisted attributes
			if (!in_array($attrName, $allowedAttributes)) {
				$attributesToRemove[] = $attr->name;
				continue;
			}

			// Remove javascript: and data:text/html URLs from href attributes
			if (in_array($attrName, ['href', 'xlink:href'])) {
				if (preg_match('/^\s*(javascript|data\s*:)/i', $attrValue)) {
					$attributesToRemove[] = $attr->name;
					continue;
				}
			}

			// Remove javascript: from style attributes
			if ($attrName === 'style' && preg_match('/expression\s*\(|javascript\s*:|url\s*\(\s*["\']?\s*data\s*:/i', $attrValue)) {
				$attributesToRemove[] = $attr->name;
			}
		}

		foreach ($attributesToRemove as $attrName) {
			$node->removeAttribute($attrName);
		}

		// Recursively process children (iterate backwards to handle removals)
		$children = [];
		foreach ($node->childNodes as $child) {
			$children[] = $child;
		}
		foreach ($children as $child) {
			$this->sanitizeSvgNode($child, $allowedElements, $allowedAttributes);
		}
	}

	/**
	 * @param string[] $widgetIds Limit widgets to given ids
	 * @return IWidget[]
	 */
	private function getShownWidgets(array $widgetIds): array {
		if (empty($widgetIds)) {
			return [];
		}

		return array_filter(
			$this->dashboardManager->getWidgets(),
			static function (IWidget $widget) use ($widgetIds) {
				return in_array($widget->getId(), $widgetIds);
			},
		);
	}
}
