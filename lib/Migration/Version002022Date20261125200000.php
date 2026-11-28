<?php

/**
 * Version002022Date20261125200000
 *
 * Widens `launchpad_acknowledgements.announcement_key` from 36 to 64
 * characters. A read confirmation stores `announcement-<uuid>` (49
 * characters, AnnouncementReadConfirmations::key()), which a 36-character
 * column refused on MariaDB, MySQL and PostgreSQL: "I have read this"
 * answered 500 there. Existing rows keep their values.
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
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Widens the acknowledgement key so a read confirmation's key fits.
 *
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
class Version002022Date20261125200000 extends SimpleMigrationStep {

	/**
	 * The acknowledgements table.
	 *
	 * @var string
	 */
	public const TABLE = 'launchpad_acknowledgements';

	/**
	 * The key length after this step: room for `announcement-` and a uuid.
	 *
	 * @var int
	 */
	public const KEY_LENGTH = 64;

	/**
	 * Widen the column once; a column already this wide is left alone.
	 *
	 * @param IOutput $output        The output.
	 * @param Closure $schemaClosure Returns the schema.
	 * @param array   $options       The options.
	 *
	 * @return ISchemaWrapper|null The changed schema, or null when there is nothing to change.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) $output and $options are required by SimpleMigrationStep.
	 *
	 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
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

		$table = $schema->getTable(self::TABLE);
		if ($table->hasColumn('announcement_key') === false
			|| ($table->getColumn('announcement_key')->getLength() ?? 0) >= self::KEY_LENGTH
		) {
			return null;
		}

		$table->modifyColumn('announcement_key', ['length' => self::KEY_LENGTH]);

		return $schema;
	}//end changeSchema()
}//end class
