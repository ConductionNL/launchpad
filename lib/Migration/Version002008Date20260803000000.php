<?php

/**
 * Version002008Date20260803000000
 *
 * Repairs two identifiers that exceed Nextcloud's 30-character limit and
 * therefore made a FRESH INSTALL of this app impossible:
 *
 *   oc_launchpad_widget_placements.acknowledgement_content_version  (31)
 *   launchpad_kiosk_creator_revoked                                 (31, index)
 *
 * Postgres itself allows 63 characters, which is why existing installs carry
 * both names and work fine — the limit is enforced by Nextcloud's own schema
 * validation, and only at install/upgrade time. So the defect was invisible on
 * every instance that already had the app, and fatal on every instance that did
 * not. It surfaced only once `composer test:all` was made able to fail: the
 * script previously ended in `|| echo '…skipping'`, so CI reported success while
 * `occ app:enable` was aborting with "Column name … is too long".
 *
 * The two creating migrations (`Version002002Date20260614000000` and
 * `Version002004Date20260707000000`) now emit the short names, so a fresh
 * install never creates the long ones. This step exists for instances that
 * already ran the old versions.
 *
 * WHY THE COLUMN IS RENAMED IN postSchemaChange() AND THE INDEX IN
 * changeSchema(). The long column must be GONE, not merely shadowed by a new
 * one — Nextcloud re-validates the whole schema on upgrade, so leaving it would
 * simply move the failure to the next `occ upgrade`. An add/copy/drop across two
 * migrations would leave it present in between. A single portable
 * `ALTER TABLE … RENAME COLUMN` avoids that entirely, and every database
 * Nextcloud 34 supports (MySQL 8.0+, MariaDB 10.6+, PostgreSQL 12+,
 * SQLite 3.25+) implements that exact syntax. Index renaming, by contrast, is
 * NOT portable (`ALTER INDEX … RENAME TO` on Postgres versus
 * `ALTER TABLE … RENAME INDEX` on MySQL), so the index goes through the schema
 * wrapper's drop/add, which Doctrine renders per platform.
 *
 * Both halves are guarded on the presence of the long name, so the step is
 * idempotent and a no-op on an instance installed after the fix.
 *
 * The entity keeps the descriptive `acknowledgementContentVersion` property —
 * and therefore the unchanged JSON key — by aliasing it in
 * {@see \OCA\LaunchPad\Db\WidgetPlacement::propertyToColumn()}. No API consumer
 * or frontend change is required.
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
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Throwable;

/**
 * Shorten the two over-long identifiers that blocked fresh installs.
 */
class Version002008Date20260803000000 extends SimpleMigrationStep {
	/**
	 * The over-long column (31 characters).
	 *
	 * @var string
	 */
	private const OLD_COLUMN = 'acknowledgement_content_version';

	/**
	 * Replacement column name (19 characters).
	 *
	 * @var string
	 */
	private const NEW_COLUMN = 'ack_content_version';

	/**
	 * The over-long index (31 characters).
	 *
	 * @var string
	 */
	private const OLD_INDEX = 'launchpad_kiosk_creator_revoked';

	/**
	 * Replacement index name (24 characters).
	 *
	 * @var string
	 */
	private const NEW_INDEX = 'lp_kiosk_creator_revoked';

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $connection Used for the portable RENAME COLUMN, which
	 *                                  ISchemaWrapper cannot express.
	 * @param IConfig $config Resolves the `dbtableprefix`, mirroring
	 *                        Version002005Date20260710000000's raw-DDL
	 *                        rename.
	 */
	public function __construct(
		private readonly IDBConnection $connection,
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * Replace the over-long index. The column is handled post-schema.
	 *
	 * @param IOutput $output The migration output handler.
	 * @param Closure $schemaClosure Returns an ISchemaWrapper.
	 * @param array $options The migration options.
	 *
	 * @return ISchemaWrapper|null The modified schema, or null when unchanged.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/*
		 * @var ISchemaWrapper $schema
		 */

		$schema = $schemaClosure();
		$changed = false;

		if ($schema->hasTable('launchpad_kiosk_playlists') === true) {
			$playlists = $schema->getTable('launchpad_kiosk_playlists');

			if ($playlists->hasIndex(self::OLD_INDEX) === true) {
				$playlists->dropIndex(self::OLD_INDEX);
				$changed = true;
				$output->info('launchpad: dropped over-long index ' . self::OLD_INDEX);
			}

			if ($playlists->hasIndex(self::NEW_INDEX) === false) {
				$playlists->addIndex(['created_by', 'revoked_at'], self::NEW_INDEX);
				$changed = true;
				$output->info('launchpad: added index ' . self::NEW_INDEX);
			}
		}//end if

		if ($changed === false) {
			return null;
		}

		return $schema;
	}//end changeSchema()

	/**
	 * Rename the over-long column in place.
	 *
	 * Runs post-schema so the rename is a single statement against the settled
	 * schema rather than an add/copy/drop straddling two migrations.
	 *
	 * A failure here is logged and swallowed rather than aborting the upgrade:
	 * on an instance where the column is already short there is nothing to do,
	 * and an unexpected platform quirk should not brick the app's upgrade path.
	 * The next `occ upgrade` retries, and the install-blocking case (a fresh
	 * install) never reaches this step because the creating migration now emits
	 * the short name directly.
	 *
	 * @param IOutput $output The migration output handler.
	 * @param Closure $schemaClosure Returns an ISchemaWrapper.
	 * @param array $options The migration options.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$prefix = $this->config->getSystemValueString('dbtableprefix', 'oc_');
		$table = $prefix . 'launchpad_widget_placements';

		try {
			$schemaManager = $this->connection->createSchema();
			if ($schemaManager->hasTable('launchpad_widget_placements') === false) {
				return;
			}

			$placements = $schemaManager->getTable('launchpad_widget_placements');
			if ($placements->hasColumn(self::OLD_COLUMN) === false) {
				// Already short, or the table predates the column. Nothing to do.
				return;
			}

			if ($placements->hasColumn(self::NEW_COLUMN) === true) {
				// Both present: an earlier partial run. Drop the long one so the
				// schema validates, keeping the short column's values.
				$this->connection->executeStatement(
					'ALTER TABLE ' . $table . ' DROP COLUMN ' . self::OLD_COLUMN
				);
				$output->info('launchpad: dropped leftover ' . self::OLD_COLUMN);
				return;
			}

			$this->connection->executeStatement(
				'ALTER TABLE ' . $table . ' RENAME COLUMN ' . self::OLD_COLUMN . ' TO ' . self::NEW_COLUMN
			);
			$output->info('launchpad: renamed ' . self::OLD_COLUMN . ' to ' . self::NEW_COLUMN);
		} catch (Throwable $e) {
			$output->warning(
				'launchpad: could not rename ' . self::OLD_COLUMN . ' (' . $e->getMessage() . '). '
				. 'The app still works; re-run occ upgrade to retry.'
			);
		}//end try
	}//end postSchemaChange()
}//end class
