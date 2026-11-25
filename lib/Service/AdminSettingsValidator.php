<?php

/**
 * AdminSettingsValidator
 *
 * The checks PUT /api/admin/settings makes before anything is stored
 * (REQ-ASET-002): the default permission level, the default grid column
 * count and the raw value of every boolean setting.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-update-admin-settings-req-aset-002
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCP\IRequest;

/**
 * Validates admin settings before they are written (REQ-ASET-002).
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-update-admin-settings-req-aset-002
 */
final class AdminSettingsValidator {

	/**
	 * The default permission levels an admin may choose.
	 *
	 * @var list<string>
	 */
	private const PERMISSION_LEVELS = [
		Dashboard::PERMISSION_VIEW_ONLY,
		Dashboard::PERMISSION_ADD_ONLY,
		Dashboard::PERMISSION_FULL,
	];

	/**
	 * The fewest default grid columns.
	 */
	private const MIN_GRID_COLUMNS = 1;

	/**
	 * The most default grid columns.
	 */
	private const MAX_GRID_COLUMNS = 24;

	/**
	 * The boolean settings the update takes, under each spelling.
	 *
	 * @var list<string>
	 */
	private const BOOLEAN_SETTING_PARAMS = [
		'allowUserDash',
		'allowMultiDash',
		'allowUserDashboards',
		'allowMultipleDashboards',
		'legacyWidgetBridgeEnabled',
		'filterTelemetryEnabled',
		'defaultAppEnabled',
	];

	/**
	 * The raw values that read as a boolean.
	 *
	 * @var list<bool|int|string>
	 */
	private const BOOLEAN_SPELLINGS = [true, false, 0, 1, '0', '1', 'true', 'false'];

	/**
	 * Refuse a default permission level outside view_only, add_only and full, and a
	 * default grid column count outside 1 to 24. A null value means "not given".
	 *
	 * @param array<string, mixed> $settings The settings by input name.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When one of the two values is invalid.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-update-admin-settings-req-aset-002
	 */
	public function assertValidCoreSettings(array $settings): void {
		$level = $settings['defaultPermLevel'] ?? null;
		if ($level !== null && in_array(needle: $level, haystack: self::PERMISSION_LEVELS, strict: true) === false) {
			throw new InvalidArgumentException(
				message: 'defaultPermLevel must be one of '.implode(separator: ', ', array: self::PERMISSION_LEVELS)
			);
		}

		$columns = $settings['defaultGridCols'] ?? null;
		if ($columns !== null
			&& (is_int($columns) === false || $columns < self::MIN_GRID_COLUMNS || $columns > self::MAX_GRID_COLUMNS)
		) {
			throw new InvalidArgumentException(
				message: 'defaultGridCols must be a whole number from '.self::MIN_GRID_COLUMNS.' to '.self::MAX_GRID_COLUMNS
			);
		}
	}//end assertValidCoreSettings()

	/**
	 * The first boolean setting in the request whose raw value is not a boolean, or
	 * null when all are. Nextcloud casts a bool controller parameter before the
	 * controller runs ("maybe" arrives as true), so the raw value is read here.
	 *
	 * @param IRequest $request The request.
	 *
	 * @return string|null The parameter name, or null.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-update-admin-settings-req-aset-002
	 */
	public function firstNonBooleanParam(IRequest $request): ?string {
		foreach (self::BOOLEAN_SETTING_PARAMS as $name) {
			$raw = $request->getParam(key: $name);
			if ($raw !== null && in_array(needle: $raw, haystack: self::BOOLEAN_SPELLINGS, strict: true) === false) {
				return $name;
			}
		}

		return null;
	}//end firstNonBooleanParam()
}//end class
