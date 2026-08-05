<?php

/**
 * Version001003Date20260204120000
 *
 * Migration to add tile configuration fields to widget placements.
 *
 * @category  Migration
 * @package   OCA\LaunchPad\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001003Date20260204120000 extends SimpleMigrationStep
{
    /**
     * Add tile configuration columns to widget_placements table.
     *
     * @param IOutput $output        Migration output handler.
     * @param Closure $schemaClosure The schema closure returns an ISchemaWrapper.
     * @param array   $options       Migration options.
     *
     * @return ISchemaWrapper|null The modified schema wrapper or null.
     */
    public function changeSchema(
        IOutput $output,
        Closure $schemaClosure,
        array $options
    ): ?ISchemaWrapper {
        // Get the schema wrapper.
        $schema = $schemaClosure();

        if ($schema->hasTable('launchpad_widget_placements') === false) {
            return $schema;
        }

        // Add tile configuration fields to widget_placements table. Each
        // column is added only when absent, so the step stays idempotent
        // on an instance that has already run it.
        $table = $schema->getTable('launchpad_widget_placements');
        foreach ($this->tileColumns() as $name => $spec) {
            if ($table->hasColumn($name) === false) {
                $table->addColumn($name, $spec['type'], $spec['options']);
            }
        }

        return $schema;
    }//end changeSchema()

    /**
     * The tile-configuration columns this step adds to
     * `launchpad_widget_placements`, keyed by column name.
     *
     * Declarative table extracted from {@see self::changeSchema()}: the
     * eight columns previously expanded to eight near-identical
     * add-if-missing blocks. Array order is the physical column order.
     *
     * @return array<string, array{type: string, options: array<string, mixed>}> The column specs.
     */
    private function tileColumns(): array
    {
        return [
            'tile_type'             => [
                'type'    => Types::STRING,
                'options' => [
                    'notnull' => false,
                    'length'  => 20,
                    'default' => null,
                    'comment' => 'Type of tile: custom (null for regular widgets).',
                ],
            ],
            'tile_title'            => [
                'type'    => Types::STRING,
                'options' => [
                    'notnull' => false,
                    'length'  => 255,
                    'default' => null,
                    'comment' => 'Title for custom tiles.',
                ],
            ],
            'tile_icon'             => [
                'type'    => Types::STRING,
                'options' => [
                    'notnull' => false,
                    'length'  => 2000,
                    'default' => null,
                    'comment' => 'Icon class, URL, emoji, or SVG path data for tiles.',
                ],
            ],
            'tile_icon_type'        => [
                'type'    => Types::STRING,
                'options' => [
                    'notnull' => false,
                    'length'  => 20,
                    'default' => null,
                    'comment' => 'Type of icon: class, url, emoji, or svg.',
                ],
            ],
            'tile_background_color' => [
                'type'    => Types::STRING,
                'options' => [
                    'notnull' => false,
                    'length'  => 7,
                    'default' => null,
                    'comment' => 'Hex color code for tile background.',
                ],
            ],
            'tile_text_color'       => [
                'type'    => Types::STRING,
                'options' => [
                    'notnull' => false,
                    'length'  => 7,
                    'default' => null,
                    'comment' => 'Hex color code for tile text.',
                ],
            ],
            'tile_link_type'        => [
                'type'    => Types::STRING,
                'options' => [
                    'notnull' => false,
                    'length'  => 20,
                    'default' => null,
                    'comment' => 'Type of link: app or url.',
                ],
            ],
            'tile_link_value'       => [
                'type'    => Types::STRING,
                'options' => [
                    'notnull' => false,
                    'length'  => 1000,
                    'default' => null,
                    'comment' => 'App ID or URL for tile links.',
                ],
            ],
        ];
    }//end tileColumns()
}//end class
