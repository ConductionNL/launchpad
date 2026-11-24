<?php

/**
 * Version002019Date20261124180000
 *
 * Adds `conversation_enabled` to `launchpad_dashboards`: whether a shared
 * dashboard shows its Talk conversation beside it (REQ-LEAF-006). The
 * column is nullable; existing dashboards keep NULL, which reads as shown,
 * so no row is rewritten and reverting leaves no data behind.
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
 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
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
 * Adds the conversation_enabled column to the dashboards.
 *
 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
 */
class Version002019Date20261124180000 extends SimpleMigrationStep {

	/**
	 * Add the nullable `conversation_enabled` small integer column, once.
	 *
	 * @param IOutput $output        The output.
	 * @param Closure $schemaClosure Returns the schema.
	 * @param array   $options       The options.
	 *
	 * @return ISchemaWrapper|null The changed schema, or null when there is nothing to change.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) $output and $options are required by SimpleMigrationStep.
	 *
	 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
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
		if ($table->hasColumn('conversation_enabled') === true) {
			return null;
		}

		$table->addColumn(
			'conversation_enabled',
			Types::SMALLINT,
			[
				'notnull' => false,
				'default' => null,
				'comment' => '0 hides the Talk conversation beside a shared dashboard; NULL or 1 shows it.',
			]
		);

		return $schema;
	}//end changeSchema()
}//end class
