<?php

/**
 * Version002018Date20261124000000
 *
 * Adds `rule_match` to `launchpad_widget_placements`: whether all include
 * rules of a widget must match (`all`) or one is enough (`any`). Existing
 * placements read `any`, the behaviour they always had, so no row is
 * rewritten and reverting leaves no data behind.
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
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
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
 * Adds the rule_match column to the widget placements.
 *
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
 */
class Version002018Date20261124000000 extends SimpleMigrationStep {

	/**
	 * Add the `rule_match` string column, default `any`, once.
	 *
	 * @param IOutput $output        The output.
	 * @param Closure $schemaClosure Returns the schema.
	 * @param array   $options       The options.
	 *
	 * @return ISchemaWrapper|null The changed schema, or null when there is nothing to change.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) $output and $options are required by SimpleMigrationStep.
	 *
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
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
		if ($table->hasColumn('rule_match') === true) {
			return null;
		}

		$table->addColumn(
			'rule_match',
			Types::STRING,
			[
				'notnull' => false,
				'length'  => 8,
				'default' => 'any',
				'comment' => 'any: one include rule must match; all: every include rule must match. NULL reads as any.',
			]
		);

		return $schema;
	}//end changeSchema()
}//end class
