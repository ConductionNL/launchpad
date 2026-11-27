<?php

/**
 * Version002023Date20261127140000
 *
 * Adds `screen_dashboard_uuid` to `launchpad_kiosk_playlists`. A row with
 * it set is the screen link of that one dashboard, made from the dashboard's
 * Sharing tab (REQ-KIOSK-006); playlists made on the admin page's Kiosk tab
 * keep it NULL. Indexed, because the Sharing tab looks the active screen
 * link up by dashboard.
 *
 * @category Migration
 * @package  OCA\LaunchPad\Migration
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Marks a kiosk playlist as the screen link of one dashboard.
 *
 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-1
 */
class Version002023Date20261127140000 extends SimpleMigrationStep {

	/**
	 * The kiosk playlists table.
	 *
	 * @var string
	 */
	public const TABLE = 'launchpad_kiosk_playlists';

	/**
	 * The new column.
	 *
	 * @var string
	 */
	public const COLUMN = 'screen_dashboard_uuid';

	/**
	 * The index on the new column.
	 *
	 * @var string
	 */
	public const INDEX = 'lp_kiosk_screen_dash_idx';

	/**
	 * Add the column and its index, each once.
	 *
	 * @param IOutput $output        The output.
	 * @param Closure $schemaClosure Returns the schema.
	 * @param array   $options       The options.
	 *
	 * @return ISchemaWrapper|null The changed schema, or null when there is nothing to change.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) $output and $options are required by SimpleMigrationStep.
	 *
	 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-1
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();

		if ($schema->hasTable(self::TABLE) === false) {
			return null;
		}

		$table   = $schema->getTable(self::TABLE);
		$changed = false;
		if ($table->hasColumn(self::COLUMN) === false) {
			$table->addColumn(
				self::COLUMN,
				Types::STRING,
				[
					'notnull' => false,
					'length'  => 36,
					'default' => null,
					'comment' => 'The dashboard this playlist is the screen link of; NULL for an admin playlist.',
				]
			);
			$changed = true;
		}

		if ($table->hasIndex(self::INDEX) === false) {
			$table->addIndex([self::COLUMN], self::INDEX);
			$changed = true;
		}

		if ($changed === false) {
			return null;
		}

		return $schema;
	}//end changeSchema()
}//end class
