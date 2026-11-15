<?php

/**
 * Version002013Date20261114000000
 *
 * Creates the announcement tables (engagement-announcements task 1).
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
 *
 * @spec openspec/changes/engagement-announcements/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds `launchpad_announcements` and `launchpad_ann_follows`.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) {@see AnnouncementTablesBuilder} is a
 *     stateless schema builder, called the way the other table builders are.
 *
 * @spec openspec/changes/engagement-announcements/tasks.md#task-1
 */
class Version002013Date20261114000000 extends SimpleMigrationStep {
	/**
	 * Create the tables.
	 *
	 * @param IOutput $output        The output.
	 * @param Closure $schemaClosure Returns the schema.
	 * @param array   $options       The options.
	 *
	 * @return ISchemaWrapper|null
	 *
	 * @spec openspec/changes/engagement-announcements/tasks.md#task-1
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();

		AnnouncementTablesBuilder::create(schema: $schema);

		return $schema;
	}//end changeSchema()
}//end class
