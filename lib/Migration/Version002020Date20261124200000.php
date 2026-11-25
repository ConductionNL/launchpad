<?php

/**
 * Version002020Date20261124200000
 *
 * Adds `synced_layout` to `launchpad_widget_placements`: the grid position
 * and size the template gave a copy's placement when it was last provisioned
 * or re-synced. A re-sync with `merge` compares it with the placement's
 * current layout to tell whether the owner moved or resized the widget
 * (REQ-RESYNC-007). The column is nullable; existing rows keep NULL, which
 * reads as "not moved", so no row is rewritten and reverting leaves no data
 * behind.
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
 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
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
 * Adds the synced_layout column to the widget placements.
 *
 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
 */
class Version002020Date20261124200000 extends SimpleMigrationStep {

	/**
	 * Add the nullable `synced_layout` string column, once.
	 *
	 * @param IOutput $output        The output.
	 * @param Closure $schemaClosure Returns the schema.
	 * @param array   $options       The options.
	 *
	 * @return ISchemaWrapper|null The changed schema, or null when there is nothing to change.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) $output and $options are required by SimpleMigrationStep.
	 *
	 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-007-merge-leaves-a-widget-where-the-person-put-it
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();

		if ($schema->hasTable('launchpad_widget_placements') === false) {
			return null;
		}

		$table = $schema->getTable('launchpad_widget_placements');
		if ($table->hasColumn('synced_layout') === true) {
			return null;
		}

		$table->addColumn(
			'synced_layout',
			Types::STRING,
			[
				'notnull' => false,
				'default' => null,
				'length'  => 64,
				'comment' => 'gridX,gridY,gridWidth,gridHeight the template last gave this placement; NULL when unknown.',
			]
		);

		return $schema;
	}//end changeSchema()
}//end class
