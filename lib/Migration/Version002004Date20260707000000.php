<?php

/**
 * Version002004Date20260707000000
 *
 * Migration for the `dashboard-acknowledgements` capability. Creates the
 * local `oc_launchpad_acknowledgements` receipt table and adds the additive
 * acknowledgement fields to the existing `oc_launchpad_widget_placements`
 * table. Required by REQ-ACK-001 / REQ-ACK-003.
 *
 * Zero-impact: the new placement columns default to the "no acknowledgement
 * required" state (`requires_acknowledgement = 0`, `content_version = 1`), so
 * every existing placement renders exactly as before. The receipt table
 * starts empty. No OpenRegister install-time dependency is introduced
 * (`launchpad-adopt-or-abstractions`).
 *
 * @category  Migration
 * @package   OCA\LaunchPad\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
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
 * Create `launchpad_acknowledgements` + additive placement fields.
 * REQ-ACK-001 / REQ-ACK-003.
 *
 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
 */
class Version002004Date20260707000000 extends SimpleMigrationStep {
	/**
	 * Create the receipt table and add the acknowledgement placement columns.
	 *
	 * @param IOutput $output The migration output handler.
	 * @param Closure $schemaClosure The schema closure returning an
	 *                               ISchemaWrapper.
	 * @param array $options The migration options.
	 *
	 * @return ISchemaWrapper|null The modified schema or null.
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		$schema = $schemaClosure();

		// 1. Create `launchpad_acknowledgements` receipt table.
		$this->createAcknowledgementsTable(schema: $schema);

		// 2. Additive acknowledgement columns on the placements table.
		$this->addPlacementAcknowledgementColumns(schema: $schema);

		return $schema;
	}//end changeSchema()

	/**
	 * Create the `launchpad_acknowledgements` receipt table.
	 *
	 * No-op when the table already exists, so the step stays idempotent.
	 *
	 * @param ISchemaWrapper $schema The schema wrapper.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	private function createAcknowledgementsTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('launchpad_acknowledgements') === true) {
			return;
		}

		$table = $schema->createTable('launchpad_acknowledgements');

		$table->addColumn(
			'id',
			Types::BIGINT,
			[
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]
		);
		$table->addColumn(
			'announcement_key',
			Types::STRING,
			[
				'notnull' => true,
				'length' => 36,
			]
		);
		$table->addColumn(
			'user_id',
			Types::STRING,
			[
				'notnull' => true,
				'length' => 64,
			]
		);
		$table->addColumn(
			'content_version',
			Types::INTEGER,
			[
				'notnull' => true,
				'default' => 1,
				'unsigned' => true,
			]
		);
		$table->addColumn(
			'acknowledged_at',
			Types::DATETIME,
			['notnull' => true]
		);

		$table->setPrimaryKey(['id']);

		// Composite uniqueness — REQ-ACK-003 idempotency guarantee.
		$table->addUniqueIndex(
			['announcement_key', 'user_id', 'content_version'],
			'launchpad_ack_unique'
		);

		// Report aggregation lookups — REQ-ACK-004.
		$table->addIndex(
			['announcement_key', 'content_version'],
			'launchpad_ack_ann_ver'
		);
	}//end createAcknowledgementsTable()

	/**
	 * Add the additive acknowledgement columns to
	 * `launchpad_widget_placements`.
	 *
	 * Each column is added only when absent, so the step stays
	 * idempotent on an instance that has already run it.
	 *
	 * @param ISchemaWrapper $schema The schema wrapper.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	private function addPlacementAcknowledgementColumns(ISchemaWrapper $schema): void {
		if ($schema->hasTable('launchpad_widget_placements') === false) {
			return;
		}

		$placements = $schema->getTable('launchpad_widget_placements');

		// NOTE: 'ack_content_version', not 'acknowledgement_content_version'.
		// Nextcloud rejects any identifier longer than 30 characters during
		// app install, and the long form is 31 — so this made a FRESH
		// INSTALL of the app impossible. The entity keeps the descriptive
		// property name (and therefore the unchanged JSON key) by aliasing
		// it in WidgetPlacement::propertyToColumn(). Existing installs carry
		// the long column and are repaired by Version002008Date20260803000000.
		// One condition, not two: this step only ever runs once per instance,
		// and an instance that already ran the old version has the long
		// column plus a recorded migration, so it never re-enters here. If it
		// somehow did, Version002008Date20260803000000 handles "both columns
		// present" by dropping the long one.
		$columns = [
			'requires_acknowledgement' => [
				'type' => Types::SMALLINT,
				'options' => [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				],
			],
			'acknowledgement_prompt' => [
				'type' => Types::TEXT,
				'options' => ['notnull' => false],
			],
			'acknowledgement_deadline' => [
				'type' => Types::DATE,
				'options' => ['notnull' => false],
			],
			'reacknowledge_on_change' => [
				'type' => Types::SMALLINT,
				'options' => [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				],
			],
			'ack_content_version' => [
				'type' => Types::INTEGER,
				'options' => [
					'notnull' => true,
					'default' => 1,
					'unsigned' => true,
				],
			],
			'announcement_key' => [
				'type' => Types::STRING,
				'options' => [
					'notnull' => false,
					'length' => 36,
				],
			],
		];

		foreach ($columns as $name => $spec) {
			if ($placements->hasColumn($name) === false) {
				$placements->addColumn($name, $spec['type'], $spec['options']);
			}
		}
	}//end addPlacementAcknowledgementColumns()
}//end class
