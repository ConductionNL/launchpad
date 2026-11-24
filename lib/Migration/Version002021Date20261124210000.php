<?php

/**
 * Version002021Date20261124210000
 *
 * Adds `requires_confirmation` and `confirm_before` to
 * `launchpad_announcements`: whether an announcement asks its audience to
 * confirm they have read it, and the date to confirm before (REQ-ANN-007).
 * Existing announcements keep 0 and NULL, so they ask nothing and no row is
 * rewritten.
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
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
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
 * Adds the read confirmation columns to the announcements.
 *
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
class Version002021Date20261124210000 extends SimpleMigrationStep {

	/**
	 * Add the two columns, each once.
	 *
	 * @param IOutput $output        The output.
	 * @param Closure $schemaClosure Returns the schema.
	 * @param array   $options       The options.
	 *
	 * @return ISchemaWrapper|null The changed schema, or null when there is nothing to change.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) $output and $options are required by SimpleMigrationStep.
	 *
	 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();

		if ($schema->hasTable(AnnouncementTablesBuilder::ANNOUNCEMENTS) === false) {
			return null;
		}

		$table   = $schema->getTable(AnnouncementTablesBuilder::ANNOUNCEMENTS);
		$changed = false;
		if ($table->hasColumn('requires_confirmation') === false) {
			$table->addColumn(
				'requires_confirmation',
				Types::SMALLINT,
				[
					'notnull' => true,
					'default' => 0,
					'comment' => '1 asks the audience to confirm they have read the announcement.',
				]
			);
			$changed = true;
		}

		if ($table->hasColumn('confirm_before') === false) {
			$table->addColumn(
				'confirm_before',
				Types::DATETIME,
				[
					'notnull' => false,
					'comment' => 'The date to confirm before; NULL for no deadline.',
				]
			);
			$changed = true;
		}

		if ($changed === false) {
			return null;
		}

		return $schema;
	}//end changeSchema()
}//end class
