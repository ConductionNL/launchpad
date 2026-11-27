<?php

/**
 * Version002024Date20261127160000
 *
 * Adds `launchpad_ann_receipts`, one row per (announcement, reader): when it
 * was first seen and when and how it was confirmed, the check or the x
 * (announcements-managed-in-settings D3), and `reminded_at` on
 * `launchpad_announcements` for the once-a-day reminder (REQ-ANN-009).
 * After the schema change it carries the read confirmations stored before
 * (acknowledgements under the key `announcement-<uuid>`) over as confirmed
 * via the check, seen at the same moment. Run again, it adds nothing twice.
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
 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-6
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Seen and confirmed receipts of announcements.
 *
 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-6
 */
class Version002024Date20261127160000 extends SimpleMigrationStep {

	/**
	 * The receipts table.
	 */
	public const TABLE = 'launchpad_ann_receipts';

	/**
	 * The announcements table.
	 */
	public const ANNOUNCEMENTS = 'launchpad_announcements';

	/**
	 * The table the old read confirmations live in.
	 */
	public const ACKNOWLEDGEMENTS = 'launchpad_acknowledgements';

	/**
	 * The key prefix of an old announcement read confirmation.
	 */
	public const KEY_PREFIX = 'announcement-';

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $db The database, for the carry-over.
	 */
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}//end __construct()

	/**
	 * Create the receipts table and the reminder column.
	 *
	 * @param IOutput $output        The output.
	 * @param Closure $schemaClosure Returns the schema.
	 * @param array   $options       The options.
	 *
	 * @return ISchemaWrapper|null
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) The signature is Nextcloud's.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-6
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();

		if ($schema->hasTable(self::TABLE) === false) {
			$table = $schema->createTable(self::TABLE);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('announcement_uuid', Types::STRING, ['notnull' => true, 'length' => 36]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('seen_at', Types::DATETIME, ['notnull' => false]);
			$table->addColumn('confirmed_at', Types::DATETIME, ['notnull' => false]);
			$table->addColumn('confirmed_via', Types::STRING, ['notnull' => false, 'length' => 8, 'comment' => 'check or x.']);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['announcement_uuid', 'user_id'], 'launchpad_ann_rcpt_uc');
			$table->addIndex(['user_id'], 'launchpad_ann_rcpt_user');
		}

		if ($schema->hasTable(self::ANNOUNCEMENTS) === true) {
			$announcements = $schema->getTable(self::ANNOUNCEMENTS);
			if ($announcements->hasColumn('reminded_at') === false) {
				$announcements->addColumn('reminded_at', Types::DATETIME, ['notnull' => false]);
			}
		}

		return $schema;
	}//end changeSchema()

	/**
	 * Carry the old read confirmations over as confirmed via the check.
	 *
	 * @param IOutput $output        The output.
	 * @param Closure $schemaClosure Returns the schema.
	 * @param array   $options       The options.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) The signature is Nextcloud's.
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-6
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if ($this->db->tableExists(self::ACKNOWLEDGEMENTS) === false || $this->db->tableExists(self::TABLE) === false) {
			return;
		}

		$existing = [];
		$cursor   = $this->db->getQueryBuilder()->select('announcement_uuid', 'user_id')->from(self::TABLE)->executeQuery();
		while (($row = $cursor->fetch()) !== false) {
			$existing[$row['announcement_uuid'].'|'.$row['user_id']] = true;
		}

		$cursor->closeCursor();

		$qb = $this->db->getQueryBuilder();
		$qb->select('announcement_key', 'user_id', 'acknowledged_at')
			->from(self::ACKNOWLEDGEMENTS)
			->where($qb->expr()->like('announcement_key', $qb->createNamedParameter(self::KEY_PREFIX.'%')));
		$cursor = $qb->executeQuery();
		$carried = 0;
		while (($row = $cursor->fetch()) !== false) {
			$uuid = substr((string) $row['announcement_key'], strlen(self::KEY_PREFIX));
			$pair = $uuid.'|'.$row['user_id'];
			if ($uuid === '' || isset($existing[$pair]) === true) {
				continue;
			}

			$existing[$pair] = true;
			$insert = $this->db->getQueryBuilder();
			$insert->insert(self::TABLE)->values(
				[
					'announcement_uuid' => $insert->createNamedParameter($uuid),
					'user_id'           => $insert->createNamedParameter((string) $row['user_id']),
					'seen_at'           => $insert->createNamedParameter($row['acknowledged_at']),
					'confirmed_at'      => $insert->createNamedParameter($row['acknowledged_at']),
					'confirmed_via'     => $insert->createNamedParameter('check'),
				]
			)->executeStatement();
			$carried++;
		}

		$cursor->closeCursor();
		if ($carried > 0) {
			$output->info("Carried $carried announcement read confirmations over to receipts");
		}
	}//end postSchemaChange()
}//end class
