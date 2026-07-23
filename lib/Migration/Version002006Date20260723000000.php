<?php

/**
 * Version002006Date20260723000000
 *
 * Migration for the `admin-template-resync` capability. Adds the
 * `template_placement_id` column to `oc_launchpad_widget_placements` — the
 * origin key that lets {@see \OCA\LaunchPad\Service\TemplateResyncService}
 * tell a template-origin placement (traceable to the source template's
 * blueprint placement) apart from a placement the user personally added to
 * their copy after provisioning. Null means "no known template origin"
 * (either a pre-existing copy provisioned before this column existed, or a
 * genuinely user-added placement) — both are treated identically by the
 * re-sync diff engine (preserved under `merge`, dropped under `overwrite`).
 * REQ-RESYNC-003 / REQ-RESYNC-004.
 *
 * Zero-impact: the column defaults to null on every existing row, so
 * pre-existing placements simply behave as "user-added" until the next
 * template distribution or re-sync stamps a fresh origin key. No data is
 * rewritten by this migration.
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
 * Add the additive `template_placement_id` origin-tracking column
 * (REQ-RESYNC-003, REQ-RESYNC-004).
 *
 * @spec openspec/specs/admin-templates/spec.md
 */
class Version002006Date20260723000000 extends SimpleMigrationStep
{
    /**
     * Add the `template_placement_id` column to the placements table.
     *
     * @param IOutput $output        The migration output handler.
     * @param Closure $schemaClosure Schema closure returning an ISchemaWrapper.
     * @param array   $options       The migration options.
     *
     * @return ISchemaWrapper|null The modified schema or null.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @spec                                          openspec/specs/admin-templates/spec.md
     */
    public function changeSchema(
        IOutput $output,
        Closure $schemaClosure,
        array $options
    ): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('launchpad_widget_placements') === true) {
            $placements = $schema->getTable('launchpad_widget_placements');

            if ($placements->hasColumn('template_placement_id') === false) {
                $placements->addColumn(
                    'template_placement_id',
                    Types::INTEGER,
                    [
                        'notnull'  => false,
                        'unsigned' => true,
                    ]
                );
            }
        }

        return $schema;
    }//end changeSchema()
}//end class
