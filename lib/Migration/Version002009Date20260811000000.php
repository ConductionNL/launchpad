<?php

/**
 * Version002009Date20260811000000
 *
 * Drops the `content` (LONGTEXT) and `locale` (VARCHAR(16)) columns from
 * `launchpad_dashboards`. Both were added by Version002001Date20260603000000
 * for the groupfolder-storage-backend capability, which is now WITHDRAWN
 * (launchpad#87).
 *
 * Dropping them loses no data. The only code path that ever wrote to `content`
 * was `DbContentStorage::write()`, reachable only through
 * `DashboardContentStorageFactory::getStorage()`, reachable only through the
 * three `DashboardService` facade methods — and those had zero callers, so the
 * columns were NULL on every row of every install. `locale` was read only by
 * the GroupFolder backend, which was unreachable for the same reason.
 *
 * Version002001Date20260603000000 and
 * `DashboardTableBuilder::addContentStorageColumns()` are deliberately left in
 * place: a shipped migration is part of the version ledger, and deleting it
 * would desynchronise instances that have already run it. This migration drops
 * what that one added.
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
 * @spec openspec/changes/retire-groupfolder-storage-backend/tasks.md#task-3
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Drop the withdrawn content-storage columns from launchpad_dashboards.
 *
 * @spec openspec/changes/retire-groupfolder-storage-backend/tasks.md#task-3
 */
class Version002009Date20260811000000 extends SimpleMigrationStep
{
    /**
     * Alter the database schema to drop the content storage columns.
     *
     * Each column is dropped only when present, so the step is idempotent and
     * safe on an install that never ran Version002001Date20260603000000.
     *
     * @param IOutput $output        Migration output.
     * @param Closure $schemaClosure Schema closure (provides ISchemaWrapper).
     * @param array   $options       Migration options (unused).
     *
     * @return ISchemaWrapper|null The modified schema, or null when unchanged.
     *
     * @spec openspec/changes/retire-groupfolder-storage-backend/tasks.md#task-3
     */
    public function changeSchema(
        IOutput $output,
        Closure $schemaClosure,
        array $options
    ): ?ISchemaWrapper {
        // @var ISchemaWrapper $schema.
        $schema = $schemaClosure();

        if ($schema->hasTable('launchpad_dashboards') === false) {
            return null;
        }

        $table   = $schema->getTable('launchpad_dashboards');
        $changed = false;

        foreach (['content', 'locale'] as $column) {
            if ($table->hasColumn($column) === true) {
                $table->dropColumn($column);
                $changed = true;
            }
        }

        if ($changed === false) {
            return null;
        }

        return $schema;
    }//end changeSchema()
}//end class
