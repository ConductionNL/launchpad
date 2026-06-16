<?php

/**
 * AdminSettingKey Enum Test
 *
 * Verifies that every AdminSettingKey case has the expected string value
 * and that the BC aliases in AdminSetting match the enum values exactly.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Db
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/launchpad-adopt-or-abstractions/tasks.md#task-10
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingKey;
use PHPUnit\Framework\TestCase;

class AdminSettingKeyTest extends TestCase
{
    /**
     * Every enum case must resolve to a non-empty string value.
     *
     * @return void
     */
    public function testAllCasesHaveNonEmptyStringValue(): void
    {
        $cases = AdminSettingKey::cases();
        $this->assertNotEmpty(actual: $cases, message: 'AdminSettingKey must have at least one case.');

        foreach ($cases as $case) {
            $this->assertIsString(actual: $case->value);
            $this->assertNotSame(expected: '', actual: $case->value, message: "Enum case {$case->name} has an empty value.");
        }
    }//end testAllCasesHaveNonEmptyStringValue()

    /**
     * Enum values must be unique (no two keys collide in the DB table).
     *
     * @return void
     */
    public function testEnumValuesAreUnique(): void
    {
        $values = array_map(static fn(AdminSettingKey $k) => $k->value, AdminSettingKey::cases());
        $this->assertSame(expected: count($values), actual: count(array_unique($values)), message: 'AdminSettingKey enum values are not unique.');
    }//end testEnumValuesAreUnique()

    /**
     * AdminSetting BC aliases must match enum values exactly so that
     * existing call-sites do not inadvertently use a different key.
     *
     * @param string          $constant The BC alias string constant from AdminSetting.
     * @param AdminSettingKey $enumCase The corresponding AdminSettingKey enum case.
     *
     * @return void
     *
     * @dataProvider bcAliasProvider
     */
    public function testBcAliasMatchesEnumValue(string $constant, AdminSettingKey $enumCase): void
    {
        $this->assertSame(expected: $enumCase->value, actual: $constant, message: "AdminSetting::{$enumCase->name} alias does not match enum value.");
    }//end testBcAliasMatchesEnumValue()

    /**
     * Data provider: maps each AdminSetting BC alias to its AdminSettingKey enum case.
     *
     * @return array<string, array{string, AdminSettingKey}>
     */
    public static function bcAliasProvider(): array
    {
        return [
            'DEFAULT_PERMISSION_LEVEL'    => [AdminSetting::KEY_DEFAULT_PERMISSION_LEVEL,      AdminSettingKey::DEFAULT_PERMISSION_LEVEL],
            'ALLOW_USER_DASHBOARDS'       => [AdminSetting::KEY_ALLOW_USER_DASHBOARDS,         AdminSettingKey::ALLOW_USER_DASHBOARDS],
            'ALLOW_MULTIPLE_DASHBOARDS'   => [AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS,     AdminSettingKey::ALLOW_MULTIPLE_DASHBOARDS],
            'DEFAULT_GRID_COLUMNS'        => [AdminSetting::KEY_DEFAULT_GRID_COLUMNS,          AdminSettingKey::DEFAULT_GRID_COLUMNS],
            'GROUP_ORDER'                 => [AdminSetting::KEY_GROUP_ORDER,                   AdminSettingKey::GROUP_ORDER],
            'LINK_CREATE_FILE_EXTENSIONS' => [AdminSetting::KEY_LINK_CREATE_FILE_EXTENSIONS,   AdminSettingKey::LINK_CREATE_FILE_EXTENSIONS],
            'COMMENTS_ENABLED_DEFAULT'    => [AdminSetting::KEY_COMMENTS_ENABLED_DEFAULT,      AdminSettingKey::COMMENTS_ENABLED_DEFAULT],
            'FOOTER_ENABLED'              => [AdminSetting::KEY_FOOTER_ENABLED,                AdminSettingKey::FOOTER_ENABLED],
            'FOOTER_HTML'                 => [AdminSetting::KEY_FOOTER_HTML,                   AdminSettingKey::FOOTER_HTML],
            'FOOTER_CONFIG'               => [AdminSetting::KEY_FOOTER_CONFIG,                 AdminSettingKey::FOOTER_CONFIG],
            'FOOTER_BACKGROUND_COLOR'     => [AdminSetting::KEY_FOOTER_BACKGROUND_COLOR,       AdminSettingKey::FOOTER_BACKGROUND_COLOR],
            'FOOTER_TEXT_COLOR'           => [AdminSetting::KEY_FOOTER_TEXT_COLOR,             AdminSettingKey::FOOTER_TEXT_COLOR],
            'SETUP_WIZARD_COMPLETE'       => [AdminSetting::KEY_SETUP_WIZARD_COMPLETE,         AdminSettingKey::SETUP_WIZARD_COMPLETE],
            'CONTENT_STORAGE'             => [AdminSetting::KEY_CONTENT_STORAGE,               AdminSettingKey::CONTENT_STORAGE],
        ];
    }//end bcAliasProvider()

    /**
     * AdminSettingKey::from() must succeed for every BC alias value.
     *
     * @return void
     */
    public function testFromWorksForEveryAlias(): void
    {
        $aliases = [
            AdminSetting::KEY_DEFAULT_PERMISSION_LEVEL,
            AdminSetting::KEY_ALLOW_USER_DASHBOARDS,
            AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS,
            AdminSetting::KEY_DEFAULT_GRID_COLUMNS,
            AdminSetting::KEY_GROUP_ORDER,
            AdminSetting::KEY_LINK_CREATE_FILE_EXTENSIONS,
            AdminSetting::KEY_COMMENTS_ENABLED_DEFAULT,
            AdminSetting::KEY_FOOTER_ENABLED,
            AdminSetting::KEY_FOOTER_HTML,
            AdminSetting::KEY_FOOTER_CONFIG,
            AdminSetting::KEY_FOOTER_BACKGROUND_COLOR,
            AdminSetting::KEY_FOOTER_TEXT_COLOR,
            AdminSetting::KEY_SETUP_WIZARD_COMPLETE,
            AdminSetting::KEY_CONTENT_STORAGE,
        ];

        foreach ($aliases as $alias) {
            $case = AdminSettingKey::from($alias);
            $this->assertInstanceOf(expected: AdminSettingKey::class, actual: $case);
            $this->assertSame(expected: $alias, actual: $case->value);
        }
    }//end testFromWorksForEveryAlias()
}//end class
