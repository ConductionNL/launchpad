<?php

/**
 * LaunchPad LaunchPadToolProvider
 *
 * LaunchPad's MCP tool provider: a dispatcher over a constant tool catalogue,
 * routing each call to a domain handler that validates, authorises through
 * McpDashboardGate and calls the service layer (mcp-tool-surface D1, D4).
 * OpenRegister finds it by its class name, `OCA\<namespace>\Mcp\<namespace>ToolProvider`
 * with the namespace from info.xml, so the class name follows `LaunchPad`.
 * Without OpenRegister nothing resolves it and the app boots as before.
 *
 * Every descriptor carries hermiq's grant annotations: `scope` (read, create,
 * update, delete), `reach` (self, user, instance, external) and `readOnlyHint`.
 *
 * Deliberately not tools (REQ-MCP-007): view and tile-click recording (agents
 * must not fabricate analytics), acknowledgement writes (evidence of a human
 * act), binary uploads, editing locks, the setup wizard, imports, cleanup, demo
 * showcases, bulk operations, export and import, and settings surfaces.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category Mcp
 * @package  OCA\LaunchPad\Mcp
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Mcp;

use OCA\LaunchPad\Exception\McpToolRefusedException;
use OCA\OpenRegister\Mcp\IMcpToolProvider;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;

/**
 * The MCP tool provider for LaunchPad.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
class LaunchPadToolProvider implements IMcpToolProvider {

	public const APP_ID = 'launchpad';

	private const NO_ARGUMENTS = ['type' => 'object', 'properties' => [], 'required' => []];

	private const REACTION_ARGUMENTS = [
		'type' => 'object',
		'properties' => [
			'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
			'emoji' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 32],
		],
		'required' => ['dashboardUuid', 'emoji'],
	];

	/**
	 * The argument that names a write tool's target, in order of preference.
	 *
	 * @var list<string>
	 */
	private const TARGET_KEYS = ['dashboardUuid', 'dashboardId', 'placementId', 'shareId', 'playlistId', 'templateId', 'groupId'];

	private const GROUP_DASHBOARD_ARGUMENT = [
		'type' => 'object',
		'properties' => [
			'groupId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
			'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
		],
		'required' => ['groupId', 'dashboardUuid'],
	];

	private const DASHBOARD_FIELDS = [
		'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
		'description' => ['type' => 'string', 'maxLength' => 2000],
		'icon' => ['type' => 'string'],
		'parentUuid' => ['type' => 'string'],
		'slug' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
		'sortOrder' => ['type' => 'integer'],
	];

	private const PLACEMENT_ID_ARGUMENT = [
		'type' => 'object',
		'properties' => ['placementId' => ['type' => 'integer', 'minimum' => 1]],
		'required' => ['placementId'],
	];

	private const DASHBOARD_UUID_ARGUMENT = [
		'type' => 'object',
		'properties' => ['dashboardUuid' => ['type' => 'string', 'format' => 'uuid']],
		'required' => ['dashboardUuid'],
	];

	/**
	 * The tool catalogue. A plain constant so tests assert against it directly.
	 *
	 * @var list<array<string, mixed>>
	 */
	public const TOOL_DESCRIPTORS = [
		[
			'id' => 'launchpad.listDashboards',
			'name' => 'List dashboards',
			'description' => 'List the dashboards you can see: your own, your groups\' and the ones shared with you.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.getDashboard',
			'name' => 'Get dashboard',
			'description' => 'Fetch one dashboard you can see, with its widget placements and your permission level.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::DASHBOARD_UUID_ARGUMENT,
		],
		[
			'id' => 'launchpad.getActiveDashboard',
			'name' => 'Get active dashboard',
			'description' => 'Fetch the dashboard the app opens on for you, with its widget placements and your permission level.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.listGroupDashboards',
			'name' => 'List group dashboards',
			'description' => 'List the shared dashboards of a group you are a member of.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => ['groupId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255]],
				'required' => ['groupId'],
			],
		],
		[
			'id' => 'launchpad.listWidgetTypes',
			'name' => 'List widget types',
			'description' => 'List the widget types your role may place on a dashboard.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.listTiles',
			'name' => 'List tiles',
			'description' => 'List your own tiles: the links and apps you can place on a dashboard.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.getDashboardTree',
			'name' => 'Get dashboard tree',
			'description' => 'The parent and child structure of the dashboards you can see.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.listShares',
			'name' => 'List shares',
			'description' => 'List the user and group shares of a dashboard you own.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => ['dashboardId' => ['type' => 'integer', 'minimum' => 1]],
				'required' => ['dashboardId'],
			],
		],
		[
			'id' => 'launchpad.listVersions',
			'name' => 'List versions',
			'description' => 'List the saved versions of a dashboard you own or administer.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::DASHBOARD_UUID_ARGUMENT,
		],
		[
			'id' => 'launchpad.listTemplates',
			'name' => 'List templates',
			'description' => 'List the dashboard templates you can start a dashboard from, optionally of one category.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'category' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'sort' => ['type' => 'string', 'enum' => ['name', 'updatedAt']],
				],
				'required' => [],
			],
		],
		[
			'id' => 'launchpad.getDashboardMetadata',
			'name' => 'Get dashboard metadata',
			'description' => 'Fetch the metadata values of a dashboard you can see.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::DASHBOARD_UUID_ARGUMENT,
		],
		[
			'id' => 'launchpad.getReactions',
			'name' => 'Get reactions',
			'description' => 'Fetch the reaction counts of a dashboard and the reactions you gave.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::DASHBOARD_UUID_ARGUMENT,
		],
		[
			'id' => 'launchpad.listPendingAcknowledgements',
			'name' => 'List pending acknowledgements',
			'description' => 'List the dashboards that ask you to acknowledge them and that you have not acknowledged yet.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.listKioskPlaylists',
			'name' => 'List kiosk playlists',
			'description' => 'List the kiosk playlists you may see: your own, or every active one for an administrator.',
			'scope' => 'read', 'reach' => 'self', 'readOnlyHint' => true,
			'inputSchema' => self::NO_ARGUMENTS,
		],
		[
			'id' => 'launchpad.createDashboardVersion',
			'name' => 'Create dashboard version',
			'description' => 'Save the current state of a dashboard you own as a version, with an optional note.',
			'scope' => 'create', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
					'note' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
				],
				'required' => ['dashboardUuid'],
			],
		],
		[
			'id' => 'launchpad.restoreDashboardVersion',
			'name' => 'Restore dashboard version',
			'description' => 'Restore a dashboard you own to one of its saved versions.',
			'scope' => 'update', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
					'versionNumber' => ['type' => 'integer', 'minimum' => 1],
				],
				'required' => ['dashboardUuid', 'versionNumber'],
			],
		],
		[
			'id' => 'launchpad.setDashboardMetadata',
			'name' => 'Set dashboard metadata',
			'description' => 'Set metadata values on a dashboard whose metadata you may edit.',
			'scope' => 'update', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
					'metadata' => ['type' => 'object'],
				],
				'required' => ['dashboardUuid', 'metadata'],
			],
		],
		[
			'id' => 'launchpad.addReaction',
			'name' => 'Add reaction',
			'description' => 'Add your emoji reaction to a dashboard you can see.',
			'scope' => 'create', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => self::REACTION_ARGUMENTS,
		],
		[
			'id' => 'launchpad.removeReaction',
			'name' => 'Remove reaction',
			'description' => 'Remove your emoji reaction from a dashboard.',
			'scope' => 'delete', 'reach' => 'self', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => self::REACTION_ARGUMENTS,
		],
		[
			'id' => 'launchpad.addWidget',
			'name' => 'Add widget',
			'description' => 'Place a widget on a dashboard you may add widgets to; find the widgetId with launchpad.listWidgetTypes.',
			'scope' => 'create', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardId' => ['type' => 'integer', 'minimum' => 1],
					'widgetId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'gridX' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
					'gridY' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
					'gridWidth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
					'gridHeight' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
					'content' => ['type' => 'object'],
				],
				'required' => ['dashboardId', 'widgetId'],
			],
		],
		[
			'id' => 'launchpad.updateWidgetPlacement',
			'name' => 'Update widget placement',
			'description' => 'Move, resize, show, hide, retitle, restyle or change the content of a widget you may change.',
			'scope' => 'update', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'placementId' => ['type' => 'integer', 'minimum' => 1],
					'gridX' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
					'gridY' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
					'gridWidth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
					'gridHeight' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
					'version' => ['type' => 'integer'],
					'isVisible' => ['type' => 'boolean'],
					'showTitle' => ['type' => 'boolean'],
					'customTitle' => ['type' => 'string'],
					'customIcon' => ['type' => 'string'],
					'styleConfig' => ['type' => 'object'],
					'content' => ['type' => 'object'],
				],
				'required' => ['placementId'],
			],
		],
		[
			'id' => 'launchpad.removeWidget',
			'name' => 'Remove widget',
			'description' => 'Remove a widget from a dashboard you may remove widgets from.',
			'scope' => 'delete', 'reach' => 'self', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => self::PLACEMENT_ID_ARGUMENT,
		],
		[
			'id' => 'launchpad.addTileToDashboard',
			'name' => 'Add tile to dashboard',
			'description' => 'Add a tile (a title and a link to an app or a web address) to a dashboard you may add widgets to.',
			'scope' => 'create', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardId' => ['type' => 'integer', 'minimum' => 1],
					'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'linkType' => ['type' => 'string', 'enum' => ['app', 'url']],
					'linkValue' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2048],
					'icon' => ['type' => 'string'],
					'iconType' => ['type' => 'string'],
					'backgroundColor' => ['type' => 'string'],
					'textColor' => ['type' => 'string'],
					'gridX' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
					'gridY' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
				],
				'required' => ['dashboardId', 'title', 'linkType', 'linkValue'],
			],
		],
		[
			'id' => 'launchpad.createTile',
			'name' => 'Create tile',
			'description' => 'Create a tile, a widget of type tile with a title and a link, on a dashboard you may add widgets to.',
			'scope' => 'create', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardId' => ['type' => 'integer', 'minimum' => 1],
					'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'linkType' => ['type' => 'string', 'enum' => ['app', 'url']],
					'linkValue' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2048],
					'icon' => ['type' => 'string'],
					'iconType' => ['type' => 'string'],
					'backgroundColor' => ['type' => 'string'],
					'textColor' => ['type' => 'string'],
					'gridX' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
					'gridY' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
					'gridWidth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
					'gridHeight' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
				],
				'required' => ['dashboardId', 'title', 'linkType', 'linkValue'],
			],
		],
		[
			'id' => 'launchpad.updateTile',
			'name' => 'Update tile',
			'description' => 'Change the title, link, icon, colours or position of a tile you may change.',
			'scope' => 'update', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'placementId' => ['type' => 'integer', 'minimum' => 1],
					'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'linkType' => ['type' => 'string', 'enum' => ['app', 'url']],
					'linkValue' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2048],
					'icon' => ['type' => 'string'],
					'iconType' => ['type' => 'string'],
					'backgroundColor' => ['type' => 'string'],
					'textColor' => ['type' => 'string'],
					'gridX' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
					'gridY' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
					'gridWidth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
					'gridHeight' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
					'version' => ['type' => 'integer'],
				],
				'required' => ['placementId'],
			],
		],
		[
			'id' => 'launchpad.deleteTile',
			'name' => 'Delete tile',
			'description' => 'Delete a tile from a dashboard you may remove widgets from.',
			'scope' => 'delete', 'reach' => 'self', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => self::PLACEMENT_ID_ARGUMENT,
		],
		[
			'id' => 'launchpad.createDashboard',
			'name' => 'Create dashboard',
			'description' => 'Create a personal dashboard, seeded with the default widgets.',
			'scope' => 'create', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => self::DASHBOARD_FIELDS,
				'required' => ['name'],
			],
		],
		[
			'id' => 'launchpad.updateDashboard',
			'name' => 'Update dashboard',
			'description' => 'Rename, describe, move or re-slug a dashboard whose details you may edit.',
			'scope' => 'update', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => ['dashboardId' => ['type' => 'integer', 'minimum' => 1]] + self::DASHBOARD_FIELDS,
				'required' => ['dashboardId'],
			],
		],
		[
			'id' => 'launchpad.deleteDashboard',
			'name' => 'Delete dashboard',
			'description' => 'Delete a dashboard you own; set cascade to delete its child dashboards too.',
			'scope' => 'delete', 'reach' => 'self', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardId' => ['type' => 'integer', 'minimum' => 1],
					'cascade' => ['type' => 'boolean'],
				],
				'required' => ['dashboardId'],
			],
		],
		[
			'id' => 'launchpad.switchActiveDashboard',
			'name' => 'Switch active dashboard',
			'description' => 'Make a dashboard the one LaunchPad opens on for you.',
			'scope' => 'update', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => self::DASHBOARD_UUID_ARGUMENT,
		],
		[
			'id' => 'launchpad.setDefaultDashboard',
			'name' => 'Set default dashboard',
			'description' => 'Make a dashboard your default.',
			'scope' => 'update', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => self::DASHBOARD_UUID_ARGUMENT,
		],
		[
			'id' => 'launchpad.forkDashboard',
			'name' => 'Fork dashboard',
			'description' => 'Copy a dashboard you can see, or a gallery template, as your own dashboard.',
			'scope' => 'create', 'reach' => 'self', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
					'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
				],
				'required' => ['dashboardUuid'],
			],
		],
		[
			'id' => 'launchpad.shareDashboard',
			'name' => 'Share dashboard',
			'description' => 'Share a dashboard you own with a user or a group, at a permission level.',
			'scope' => 'create', 'reach' => 'user', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardId' => ['type' => 'integer', 'minimum' => 1],
					'shareType' => ['type' => 'string', 'enum' => ['user', 'group']],
					'shareWith' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'permissionLevel' => ['type' => 'string', 'enum' => ['view_only', 'add_only', 'full']],
				],
				'required' => ['dashboardId', 'shareType', 'shareWith', 'permissionLevel'],
			],
		],
		[
			'id' => 'launchpad.replaceShares',
			'name' => 'Replace shares',
			'description' => 'Replace every share of a dashboard you own with the given list.',
			'scope' => 'update', 'reach' => 'user', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardId' => ['type' => 'integer', 'minimum' => 1],
					'shares' => ['type' => 'array', 'items' => ['type' => 'object']],
				],
				'required' => ['dashboardId', 'shares'],
			],
		],
		[
			'id' => 'launchpad.unshareDashboard',
			'name' => 'Unshare dashboard',
			'description' => 'Remove one share of a dashboard you own.',
			'scope' => 'delete', 'reach' => 'user', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'shareId' => ['type' => 'integer', 'minimum' => 1],
				],
				'required' => ['shareId'],
			],
		],
		[
			'id' => 'launchpad.setPublicationState',
			'name' => 'Set publication state',
			'description' => 'Publish, unpublish or schedule a dashboard you own or administer.',
			'scope' => 'update', 'reach' => 'user', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
					'state' => ['type' => 'string', 'enum' => ['published', 'draft', 'scheduled']],
					'publishAt' => ['type' => 'string'],
					'unpublishAt' => ['type' => 'string'],
				],
				'required' => ['dashboardUuid', 'state'],
			],
		],
		[
			'id' => 'launchpad.createPublicShareLink',
			'name' => 'Create public share link',
			'description' => 'Mint an anonymous public link to a dashboard you own, optionally with a password and an expiry date.',
			'scope' => 'create', 'reach' => 'external', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
					'password' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'expiresAt' => ['type' => 'string'],
				],
				'required' => ['dashboardUuid'],
			],
		],
		[
			'id' => 'launchpad.revokePublicShareLink',
			'name' => 'Revoke public share link',
			'description' => 'Revoke a public link of a dashboard you own.',
			'scope' => 'delete', 'reach' => 'external', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
					'shareId' => ['type' => 'integer', 'minimum' => 1],
				],
				'required' => ['dashboardUuid', 'shareId'],
			],
		],
		[
			'id' => 'launchpad.createKioskPlaylist',
			'name' => 'Create kiosk playlist',
			'description' => 'Create a kiosk playlist: an anonymous full-screen rotation of dashboards.',
			'scope' => 'create', 'reach' => 'external', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'entries' => ['type' => 'array', 'items' => ['type' => 'object']],
					'refreshSeconds' => ['type' => 'integer'],
				],
				'required' => ['name', 'entries'],
			],
		],
		[
			'id' => 'launchpad.updateKioskPlaylist',
			'name' => 'Update kiosk playlist',
			'description' => 'Change a kiosk playlist you own.',
			'scope' => 'update', 'reach' => 'external', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'playlistId' => ['type' => 'integer', 'minimum' => 1],
					'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'entries' => ['type' => 'array', 'items' => ['type' => 'object']],
					'refreshSeconds' => ['type' => 'integer'],
				],
				'required' => ['playlistId', 'name', 'entries'],
			],
		],
		[
			'id' => 'launchpad.deleteKioskPlaylist',
			'name' => 'Delete kiosk playlist',
			'description' => 'Revoke a kiosk playlist you own; its kiosk link stops working.',
			'scope' => 'delete', 'reach' => 'external', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'playlistId' => ['type' => 'integer', 'minimum' => 1],
				],
				'required' => ['playlistId'],
			],
		],
		[
			'id' => 'launchpad.createGroupDashboard',
			'name' => 'Create group dashboard',
			'description' => 'Create a dashboard shared with a group. Administrators only.',
			'scope' => 'create', 'reach' => 'instance', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'groupId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'description' => ['type' => 'string'],
				],
				'required' => ['groupId', 'name'],
			],
		],
		[
			'id' => 'launchpad.updateGroupDashboard',
			'name' => 'Update group dashboard',
			'description' => 'Change a group dashboard. Administrators only.',
			'scope' => 'update', 'reach' => 'instance', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'groupId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'dashboardUuid' => ['type' => 'string', 'format' => 'uuid'],
					'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'description' => ['type' => 'string'],
					'gridColumns' => ['type' => 'integer'],
					'placements' => ['type' => 'array'],
				],
				'required' => ['groupId', 'dashboardUuid'],
			],
		],
		[
			'id' => 'launchpad.deleteGroupDashboard',
			'name' => 'Delete group dashboard',
			'description' => 'Delete a group dashboard. Administrators only.',
			'scope' => 'delete', 'reach' => 'instance', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => self::GROUP_DASHBOARD_ARGUMENT,
		],
		[
			'id' => 'launchpad.setGroupDefault',
			'name' => 'Set group default',
			'description' => 'Make a group dashboard the default of its group. Administrators only.',
			'scope' => 'update', 'reach' => 'instance', 'readOnlyHint' => false,
			'inputSchema' => self::GROUP_DASHBOARD_ARGUMENT,
		],
		[
			'id' => 'launchpad.createTemplate',
			'name' => 'Create template',
			'description' => 'Create an admin dashboard template. Administrators only.',
			'scope' => 'create', 'reach' => 'instance', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'description' => ['type' => 'string'],
					'targetGroups' => ['type' => 'array', 'items' => ['type' => 'string']],
					'permissionLevel' => ['type' => 'string', 'enum' => ['view_only', 'add_only', 'full']],
					'isDefault' => ['type' => 'boolean'],
				],
				'required' => ['name'],
			],
		],
		[
			'id' => 'launchpad.updateTemplate',
			'name' => 'Update template',
			'description' => 'Change an admin dashboard template. Administrators only.',
			'scope' => 'update', 'reach' => 'instance', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'templateId' => ['type' => 'integer', 'minimum' => 1],
					'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
					'description' => ['type' => 'string'],
					'targetGroups' => ['type' => 'array', 'items' => ['type' => 'string']],
					'permissionLevel' => ['type' => 'string', 'enum' => ['view_only', 'add_only', 'full']],
					'isDefault' => ['type' => 'boolean'],
					'gridColumns' => ['type' => 'integer'],
				],
				'required' => ['templateId'],
			],
		],
		[
			'id' => 'launchpad.deleteTemplate',
			'name' => 'Delete template',
			'description' => 'Delete an admin dashboard template. Administrators only.',
			'scope' => 'delete', 'reach' => 'instance', 'readOnlyHint' => false, 'destructiveHint' => true,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'templateId' => ['type' => 'integer', 'minimum' => 1],
				],
				'required' => ['templateId'],
			],
		],
		[
			'id' => 'launchpad.resyncTemplate',
			'name' => 'Resync template',
			'description' => 'Bring the dashboards made from a template in line with it; a dry run, the default, only reports. Administrators only.',
			'scope' => 'update', 'reach' => 'instance', 'readOnlyHint' => false,
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'templateId' => ['type' => 'integer', 'minimum' => 1],
					'strategy' => ['type' => 'string', 'enum' => ['overwrite', 'merge']],
					'dryRun' => ['type' => 'boolean'],
				],
				'required' => ['templateId', 'strategy'],
			],
		],
	];

	/**
	 * The handler class and method of each tool. The handler is resolved from
	 * the container only when its tool runs.
	 *
	 * @var array<string, array{0: class-string, 1: string}>
	 */
	public const TOOL_HANDLERS = [
		'launchpad.listDashboards' => [McpDashboardTools::class, 'listDashboards'],
		'launchpad.getDashboard' => [McpDashboardTools::class, 'getDashboard'],
		'launchpad.getActiveDashboard' => [McpDashboardTools::class, 'getActiveDashboard'],
		'launchpad.listGroupDashboards' => [McpDashboardTools::class, 'listGroupDashboards'],
		'launchpad.listWidgetTypes' => [McpDashboardTools::class, 'listWidgetTypes'],
		'launchpad.listTiles' => [McpCatalogueTools::class, 'listTiles'],
		'launchpad.getDashboardTree' => [McpCatalogueTools::class, 'getDashboardTree'],
		'launchpad.listShares' => [McpSharingTools::class, 'listShares'],
		'launchpad.listVersions' => [McpHistoryTools::class, 'listVersions'],
		'launchpad.listTemplates' => [McpCatalogueTools::class, 'listTemplates'],
		'launchpad.getDashboardMetadata' => [McpMetadataTools::class, 'getDashboardMetadata'],
		'launchpad.getReactions' => [McpEngagementTools::class, 'getReactions'],
		'launchpad.listPendingAcknowledgements' => [McpEngagementTools::class, 'listPendingAcknowledgements'],
		'launchpad.listKioskPlaylists' => [McpExternalTools::class, 'listKioskPlaylists'],
		'launchpad.createDashboardVersion' => [McpHistoryTools::class, 'createDashboardVersion'],
		'launchpad.restoreDashboardVersion' => [McpHistoryTools::class, 'restoreDashboardVersion'],
		'launchpad.setDashboardMetadata' => [McpMetadataTools::class, 'setDashboardMetadata'],
		'launchpad.addReaction' => [McpEngagementTools::class, 'addReaction'],
		'launchpad.removeReaction' => [McpEngagementTools::class, 'removeReaction'],
		'launchpad.addWidget' => [McpWidgetTools::class, 'addWidget'],
		'launchpad.updateWidgetPlacement' => [McpWidgetTools::class, 'updateWidgetPlacement'],
		'launchpad.removeWidget' => [McpWidgetTools::class, 'removeWidget'],
		'launchpad.addTileToDashboard' => [McpWidgetTools::class, 'addTileToDashboard'],
		'launchpad.createTile' => [McpWidgetTools::class, 'createTile'],
		'launchpad.updateTile' => [McpWidgetTools::class, 'updateTile'],
		'launchpad.deleteTile' => [McpWidgetTools::class, 'deleteTile'],
		'launchpad.createDashboard' => [McpDashboardWriteTools::class, 'createDashboard'],
		'launchpad.updateDashboard' => [McpDashboardWriteTools::class, 'updateDashboard'],
		'launchpad.deleteDashboard' => [McpDashboardWriteTools::class, 'deleteDashboard'],
		'launchpad.switchActiveDashboard' => [McpDashboardWriteTools::class, 'switchActiveDashboard'],
		'launchpad.setDefaultDashboard' => [McpDashboardWriteTools::class, 'setDefaultDashboard'],
		'launchpad.forkDashboard' => [McpDashboardWriteTools::class, 'forkDashboard'],
		'launchpad.shareDashboard' => [McpSharingTools::class, 'shareDashboard'],
		'launchpad.replaceShares' => [McpSharingTools::class, 'replaceShares'],
		'launchpad.unshareDashboard' => [McpSharingTools::class, 'unshareDashboard'],
		'launchpad.setPublicationState' => [McpDashboardWriteTools::class, 'setPublicationState'],
		'launchpad.createPublicShareLink' => [McpExternalTools::class, 'createPublicShareLink'],
		'launchpad.revokePublicShareLink' => [McpExternalTools::class, 'revokePublicShareLink'],
		'launchpad.createKioskPlaylist' => [McpExternalTools::class, 'createKioskPlaylist'],
		'launchpad.updateKioskPlaylist' => [McpExternalTools::class, 'updateKioskPlaylist'],
		'launchpad.deleteKioskPlaylist' => [McpExternalTools::class, 'deleteKioskPlaylist'],
		'launchpad.createGroupDashboard' => [McpInstanceTools::class, 'createGroupDashboard'],
		'launchpad.updateGroupDashboard' => [McpInstanceTools::class, 'updateGroupDashboard'],
		'launchpad.deleteGroupDashboard' => [McpInstanceTools::class, 'deleteGroupDashboard'],
		'launchpad.setGroupDefault' => [McpInstanceTools::class, 'setGroupDefault'],
		'launchpad.createTemplate' => [McpInstanceTools::class, 'createTemplate'],
		'launchpad.updateTemplate' => [McpInstanceTools::class, 'updateTemplate'],
		'launchpad.deleteTemplate' => [McpInstanceTools::class, 'deleteTemplate'],
		'launchpad.resyncTemplate' => [McpInstanceTools::class, 'resyncTemplate'],
	];

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Resolves the handler of the tool that runs.
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 */
	public function __construct(
		private readonly ContainerInterface $container,
	) {
	}//end __construct()

	/**
	 * The app id OpenRegister lists the tools under.
	 *
	 * @return string The id.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function getAppId(): string {
		return self::APP_ID;
	}//end getAppId()

	/**
	 * The tool catalogue.
	 *
	 * @return list<array<string, mixed>> The descriptors.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function getTools(): array {
		return self::TOOL_DESCRIPTORS;
	}//end getTools()

	/**
	 * Run one tool.
	 *
	 * @param string               $toolId    The tool id.
	 * @param array<string, mixed> $arguments The arguments.
	 *
	 * @return array<string, mixed> The result.
	 *
	 * @throws McpToolRefusedException When the tool refuses: an unknown tool, no
	 *                                 permission, a missing target or invalid input.
	 *                                 OpenRegister answers it with `isError: true`.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-7
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-29
	 */
	public function invokeTool(string $toolId, array $arguments): array {
		$handler = (self::TOOL_HANDLERS[$toolId] ?? null);
		if ($handler === null) {
			throw new McpToolRefusedException(
				result: [
					'isError' => true,
					'error' => 'unknown_tool',
					'message' => "Unknown tool id '{$toolId}'. Available tools: "
						. implode(', ', array_column(self::TOOL_DESCRIPTORS, 'id')) . '.',
				]
			);
		}

		[$class, $method] = $handler;
		$result = $this->container->get($class)->$method($arguments);
		if ((array_column(self::TOOL_DESCRIPTORS, 'scope', 'id')[$toolId] ?? 'read') !== 'read') {
			$result += ['audit' => $this->auditRecord(toolId: $toolId, arguments: $arguments) + ['outcome' => self::outcomeOf(result: $result)]];
		}

		if (($result['isError'] ?? false) === true) {
			throw new McpToolRefusedException(result: $result);
		}

		return $result;
	}//end invokeTool()

	/**
	 * What hermiq's audit trail records about a write tool call: the tool, its
	 * target and the caller (REQ-MCP-006). The outcome is added beside it.
	 *
	 * @param string               $toolId    The tool id.
	 * @param array<string, mixed> $arguments The arguments.
	 *
	 * @return array{tool: string, target: mixed, caller: string}
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-14
	 */
	private function auditRecord(string $toolId, array $arguments): array {
		return [
			'tool' => $toolId,
			'target' => (array_values(array_intersect_key($arguments, array_flip(self::TARGET_KEYS)))[0] ?? null),
			'caller' => (string)($this->container->get(IUserSession::class)->getUser()?->getUID() ?? ''),
		];
	}//end auditRecord()

	/**
	 * The outcome of a tool call: `done`, or the refusal's error code.
	 *
	 * @param array<string, mixed> $result The handler's answer.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-14
	 */
	private static function outcomeOf(array $result): string {
		if (($result['isError'] ?? false) === true) {
			return (string)($result['error'] ?? 'failed');
		}

		return 'done';
	}//end outcomeOf()
}//end class
