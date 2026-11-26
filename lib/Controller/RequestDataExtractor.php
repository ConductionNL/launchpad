<?php

/**
 * RequestDataExtractor
 *
 * Helper for extracting typed data from controller requests.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-05-24-retrofit-infrastructure-helpers/tasks.md#task-3
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCP\IRequest;

/**
 * Helper for extracting typed data from controller requests.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class RequestDataExtractor {
	/**
	 * The tile fields read from the request: field => [parameter, default].
	 *
	 * @var array<string, array{0: string, 1: string|null}>
	 */
	private const TILE_PARAMS = [
		'title'    => ['title', 'New Tile'],
		'icon'     => ['icon', 'icon-link'],
		'iconType' => ['iconType', 'class'],
		'bgColor'  => ['bgColor', '#0082c9'],
		'txtColor' => ['textColor', '#ffffff'],
		'linkType' => ['linkType', 'app'],
		'linkVal'  => ['linkValue', ''],
		// Launcher-tile-open-behaviour D1: absent means "follow the dashboard".
		'linkTarget' => ['linkTarget', null],
	];

	/**
	 * The tile grid coordinates read from the request, as integers defaulting to 0.
	 *
	 * @var list<string>
	 */
	private const TILE_GRID_PARAMS = ['gridX', 'gridY'];

	/**
	 * The tile size read from the request when the caller gives one (a positive
	 * whole number of cells); left out otherwise, so the placement keeps its
	 * default size.
	 *
	 * @var list<string>
	 */
	private const TILE_SIZE_PARAMS = ['gridWidth', 'gridHeight'];

	/**
	 * The placement fields a placement update may carry.
	 *
	 * @var list<string>
	 */
	private const PLACEMENT_FIELDS = [
		// The widget version the change was made on (building-edit-a-dashboard-together, task 1.2).
		'version',
		'gridX',
		'gridY',
		'gridWidth',
		'gridHeight',
		'isVisible',
		'showTitle',
		'customTitle',
		'customIcon',
		'styleConfig',
		'content',
		// The widget's calculated fields (widget-calculated-fields, task 21).
		'calculatedFields',
		'tileTitle',
		'tileIcon',
		'tileIconType',
		'tileBackgroundColor',
		'tileTextColor',
		'tileLinkType',
		'tileLinkValue',
		'requiresAcknowledgement',
		'acknowledgementPrompt',
		'acknowledgementDeadline',
		'reacknowledgeOnChange',
		'acknowledgementContentVersion',
		// Whether all or one include rule must match (REQ-VIS-014).
		'ruleMatch',
	];

	/**
	 * Extract tile data from the request parameters.
	 *
	 * @param IRequest $request The request.
	 *
	 * @return array The tile configuration data.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-infrastructure-helpers/tasks.md#task-3
	 * @spec openspec/specs/widgets/spec.md#requirement-app-tiles-in-the-picker-req-wdg-042
	 */
	public static function extractTileData(IRequest $request): array {
		$data = [];
		foreach (self::TILE_PARAMS as $field => $param) {
			$data[$field] = $request->getParam(
				key: $param[0],
				default: $param[1]
			);
		}

		foreach (self::TILE_GRID_PARAMS as $field) {
			$data[$field] = (int)$request->getParam(
				key: $field,
				default: 0
			);
		}

		// REQ-WDG-042: an app tile from the picker asks for one cell; without
		// this the size was dropped and every tile landed as 2x2.
		foreach (self::TILE_SIZE_PARAMS as $field) {
			$size = $request->getParam(key: $field, default: null);
			if (is_numeric($size) === true && (int)$size > 0) {
				$data[$field] = (int)$size;
			}
		}

		return $data;
	}//end extractTileData()

	/**
	 * Extract placement update data from the request.
	 *
	 * @param IRequest $request The request.
	 *
	 * @return array The non-null placement field values.
	 *
	 * @spec openspec/changes/archive/2026-05-24-retrofit-infrastructure-helpers/tasks.md#task-3
	 */
	public static function extractPlacementData(IRequest $request): array {
		$data = [];
		foreach (self::PLACEMENT_FIELDS as $field) {
			$value = $request->getParam(key: $field);
			if ($value !== null) {
				$data[$field] = $value;
			}
		}

		return $data;
	}//end extractPlacementData()
}//end class
