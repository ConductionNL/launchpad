<?php

/**
 * Version002014Date20261114100000
 *
 * Adds `default_link_target` to `launchpad_dashboards`: how a tile on the
 * dashboard opens when the tile has no choice of its own (`same-tab`,
 * `new-tab` or `side-panel`). Nullable, so every existing dashboard keeps
 * the rule from before the setting existed (launcher-tile-open-behaviour D2).
 *
 * @category Migration
 * @package  OCA\LaunchPad\Migration
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add the dashboard's default tile link target.
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */
class Version002014Date20261114100000 extends SimpleMigrationStep {
	/**
	 * Add the nullable column when it is not already there.
	 *
	 * @param IOutput $output        Migration output.
	 * @param Closure $schemaClosure Schema closure (provides ISchemaWrapper).
	 * @param array   $options       Migration options (unused).
	 *
	 * @return ISchemaWrapper|null The modified schema, or null when unchanged.
	 *
	 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();

		if ($schema->hasTable('launchpad_dashboards') === false) {
			return null;
		}

		$table = $schema->getTable('launchpad_dashboards');
		if ($table->hasColumn('default_link_target') === true) {
			return null;
		}

		$table->addColumn(
			'default_link_target',
			Types::STRING,
			[
				'notnull' => false,
				'length' => 16,
				'default' => null,
				'comment' => 'same-tab, new-tab or side-panel; NULL follows the link type.',
			]
		);

		return $schema;
	}//end changeSchema()
}//end class
