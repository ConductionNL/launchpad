<?php

/**
 * ColumnTypeRegistry
 *
 * Registry for database column type definitions.
 *
 * @category  Db
 * @package   OCA\LaunchPad\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

/**
 * Registry for database column type definitions used by Doctrine mapper
 * hydration (`Entity::addType()`).
 *
 * **Why these types are intentionally separate from JSON-schema `type`.**
 *
 * JSON-schema types (`integer`, `boolean`, `string`) describe the *data
 * model* — the shape of a value as it would appear in an API response.
 * These constants model a *rendering choice* — how the OCP Entity layer
 * should cast a raw database string into the PHP value that matters for
 * the UI affordance (e.g. an `INTEGER` DB column that should hydrate to a
 * PHP `int`, or a `SMALLINT` flag that acts as a `bool`).
 *
 * The two namespaces overlap by coincidence (`TYPE_INTEGER` / `TYPE_BOOLEAN`
 * happen to share names with JSON-schema primitives), but:
 *   - LaunchPad column types are local constants that never leave the PHP
 *     layer.  JSON-schema types travel in API responses consumed by Vue.
 *   - A future JSON-schema `type: "dashboard-reference"` would have no
 *     Doctrine hydration analogue; conversely a DB-level `SMALLINT` flag
 *     stored as 0/1 is always `TYPE_BOOLEAN` here regardless of its
 *     JSON-schema representation.
 *
 * Do NOT merge or derive these constants from an external type vocabulary
 * (e.g. OpenRegister's JSON-schema primitives).  Keep them local and stable.
 *
 * @see  openspec/changes/launchpad-adopt-or-abstractions/design.md Q6
 * @spec openspec/changes/launchpad-adopt-or-abstractions/tasks.md#task-9
 */
class ColumnTypeRegistry
{
    /**
     * Column type for integer fields.
     *
     * @var string
     */
    public const TYPE_INTEGER = 'integer';

    /**
     * Column type for boolean fields.
     *
     * @var string
     */
    public const TYPE_BOOLEAN = 'boolean';

    /**
     * Column type for string fields.
     *
     * @var string
     */
    public const TYPE_STRING = 'string';

    /**
     * Get the column types for the dashboard entity.
     *
     * @return array The column name to type mapping.
     */
    public static function getDashboardTypes(): array
    {
        return [
            'id'              => self::TYPE_INTEGER,
            'basedOnTemplate' => self::TYPE_INTEGER,
            'gridColumns'     => self::TYPE_INTEGER,
            'isDefault'       => self::TYPE_INTEGER,
            'isActive'        => self::TYPE_INTEGER,
        ];
    }//end getDashboardTypes()

    /**
     * Get the column types for the widget placement entity.
     *
     * @return array The column name to type mapping.
     */
    public static function getWidgetPlacementTypes(): array
    {
        return [
            'id'           => self::TYPE_INTEGER,
            'dashboardId'  => self::TYPE_INTEGER,
            'gridX'        => self::TYPE_INTEGER,
            'gridY'        => self::TYPE_INTEGER,
            'gridWidth'    => self::TYPE_INTEGER,
            'gridHeight'   => self::TYPE_INTEGER,
            'isCompulsory' => self::TYPE_INTEGER,
            'isVisible'    => self::TYPE_INTEGER,
            'showTitle'    => self::TYPE_INTEGER,
            'sortOrder'    => self::TYPE_INTEGER,
        ];
    }//end getWidgetPlacementTypes()

    /**
     * Get the column types for the conditional rule entity.
     *
     * @return array The column name to type mapping.
     */
    public static function getConditionalRuleTypes(): array
    {
        return [
            'id'                => self::TYPE_INTEGER,
            'widgetPlacementId' => self::TYPE_INTEGER,
            'isInclude'         => self::TYPE_BOOLEAN,
        ];
    }//end getConditionalRuleTypes()

    /**
     * Get the column types for the admin setting entity.
     *
     * @return array The column name to type mapping.
     */
    public static function getAdminSettingTypes(): array
    {
        return [
            'id' => self::TYPE_INTEGER,
        ];
    }//end getAdminSettingTypes()

    /**
     * Get the column types for the tile entity.
     *
     * @return array The column name to type mapping.
     */
    public static function getTileTypes(): array
    {
        return [
            'id' => self::TYPE_INTEGER,
        ];
    }//end getTileTypes()
}//end class
