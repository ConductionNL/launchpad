<?php

/**
 * Version002001Date20260603000000
 *
 * Adds the `content` (LONGTEXT, nullable) and `locale` (VARCHAR(16), nullable)
 * columns to `mydash_dashboards` for the GroupFolder storage backend
 * (REQ-GFSB-002, REQ-GFSB-004). Both columns default to NULL so pre-existing
 * rows are unaffected. The `content` column holds a JSON blob when the `db`
 * backend is active; it is ignored when `groupfolder` is active. The `locale`
 * column routes GroupFolder reads/writes to locale-specific sub-paths.
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
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-6
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add content and locale columns to mydash_dashboards (REQ-GFSB-002).
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-6
 */
class Version002001Date20260603000000 extends SimpleMigrationStep
{
    /**
     * Alter the database schema to add content storage columns.
     *
     * @param IOutput $output        Migration output.
     * @param Closure $schemaClosure Schema closure (provides ISchemaWrapper).
     * @param array   $options       Migration options (unused).
     *
     * @return ISchemaWrapper|null The modified schema, or null when unchanged.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-6
     */
    public function changeSchema(
        IOutput $output,
        Closure $schemaClosure,
        array $options
    ): ?ISchemaWrapper {
        // @var ISchemaWrapper $schema.
        $schema = $schemaClosure();

        if ($schema->hasTable('mydash_dashboards') === false) {
            return null;
        }

        $table = $schema->getTable('mydash_dashboards');

        DashboardTableBuilder::addContentStorageColumns(table: $table);

        return $schema;
    }//end changeSchema()
}//end class
