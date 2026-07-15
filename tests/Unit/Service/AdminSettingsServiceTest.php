<?php

/**
 * AdminSettingsService Test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

class AdminSettingsServiceTest extends TestCase
{

    private AdminSettingsService $service;

    private AdminSettingMapper $settingMapper;

    protected function setUp(): void
    {
        $this->settingMapper = $this->createMock(AdminSettingMapper::class);
        $this->service       = new AdminSettingsService(
            settingMapper: $this->settingMapper,
        );
    }//end setUp()

    public function testGetSettingsReturnsDefaults(): void
    {
        $this->settingMapper->method('getAllAsArray')->willReturn([]);

        $settings = $this->service->getSettings();

        $this->assertSame(Dashboard::PERMISSION_ADD_ONLY, $settings['defaultPermissionLevel']);
        // REQ-ASET-003 (extended): allow_user_dashboards defaults to false
        // when no row is present — admins MUST opt in.
        $this->assertFalse($settings['allowUserDashboards']);
        $this->assertTrue($settings['allowMultipleDashboards']);
        $this->assertSame(12, $settings['defaultGridColumns']);
        $this->assertSame(
            ['txt', 'md', 'docx', 'xlsx', 'csv', 'odt'],
            $settings['linkCreateFileExtensions']
        );
    }//end testGetSettingsReturnsDefaults()

    public function testGetSettingsReturnsStoredValues(): void
    {
        $this->settingMapper->method('getAllAsArray')->willReturn(
                [
                    AdminSetting::KEY_DEFAULT_PERMISSION_LEVEL  => 'view_only',
                    AdminSetting::KEY_ALLOW_USER_DASHBOARDS     => false,
                    AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS => false,
                    AdminSetting::KEY_DEFAULT_GRID_COLUMNS      => 6,
                ]
                );

        $settings = $this->service->getSettings();

        $this->assertSame('view_only', $settings['defaultPermissionLevel']);
        $this->assertFalse($settings['allowUserDashboards']);
        $this->assertFalse($settings['allowMultipleDashboards']);
        $this->assertSame(6, $settings['defaultGridColumns']);
    }//end testGetSettingsReturnsStoredValues()

    public function testGetSettingsPartialOverride(): void
    {
        $this->settingMapper->method('getAllAsArray')->willReturn(
                [
                    AdminSetting::KEY_ALLOW_USER_DASHBOARDS => false,
                ]
                );

        $settings = $this->service->getSettings();

        $this->assertSame(Dashboard::PERMISSION_ADD_ONLY, $settings['defaultPermissionLevel']);
        $this->assertFalse($settings['allowUserDashboards']);
        $this->assertTrue($settings['allowMultipleDashboards']);
        $this->assertSame(12, $settings['defaultGridColumns']);
    }//end testGetSettingsPartialOverride()

    public function testUpdateSettingsCallsMapperForEachProvided(): void
    {
        $this->settingMapper->expects($this->exactly(2))
            ->method('setSetting');

        $this->service->updateSettings(
            defaultPermLevel: 'full',
            allowUserDash: false,
        );
    }//end testUpdateSettingsCallsMapperForEachProvided()

    public function testUpdateSettingsSkipsNullValues(): void
    {
        $this->settingMapper->expects($this->once())
            ->method('setSetting');

        $this->service->updateSettings(
            defaultGridCols: 8,
        );
    }//end testUpdateSettingsSkipsNullValues()

    public function testUpdateSettingsWithNoValues(): void
    {
        $this->settingMapper->expects($this->never())
            ->method('setSetting');

        $this->service->updateSettings();
    }//end testUpdateSettingsWithNoValues()

    public function testGetSettingsReturnsCamelCaseKeys(): void
    {
        $this->settingMapper->method('getAllAsArray')->willReturn([]);

        $settings = $this->service->getSettings();

        $this->assertArrayHasKey('defaultPermissionLevel', $settings);
        $this->assertArrayHasKey('allowUserDashboards', $settings);
        $this->assertArrayHasKey('allowMultipleDashboards', $settings);
        $this->assertArrayHasKey('defaultGridColumns', $settings);
        $this->assertArrayHasKey('linkCreateFileExtensions', $settings);
        // REQ-GFSB-006: content storage backend key added.
        $this->assertArrayHasKey('launchpad.content_storage', $settings);
        // dashboard-sharing + legacy-widget-bridge spec keys.
        $this->assertArrayHasKey('defaultSharePermissionLevel', $settings);
        $this->assertArrayHasKey('forcedShareGroups', $settings);
        $this->assertArrayHasKey('legacyWidgetBridgeEnabled', $settings);
        // dashboard-quota-limits REQ-QUOTA-001 — two numeric quota keys.
        $this->assertArrayHasKey('maxDashboardsPerUser', $settings);
        $this->assertArrayHasKey('maxWidgetsPerDashboard', $settings);
        $this->assertCount(11, $settings);
    }//end testGetSettingsReturnsCamelCaseKeys()

    // ----- dashboard-quota-limits REQ-QUOTA-001 -----

    public function testGetSettingsQuotaDefaultsUnlimited(): void
    {
        // REQ-QUOTA-001 — fresh / upgraded instance defaults both quotas
        // to 0 (unlimited) so behaviour is invariant on upgrade.
        $this->settingMapper->method('getAllAsArray')->willReturn([]);

        $settings = $this->service->getSettings();

        $this->assertSame(0, $settings['maxDashboardsPerUser']);
        $this->assertSame(0, $settings['maxWidgetsPerDashboard']);
    }//end testGetSettingsQuotaDefaultsUnlimited()

    public function testGetSettingsReturnsStoredQuotaValues(): void
    {
        // REQ-QUOTA-001 — admin-set values round-trip through getSettings.
        $this->settingMapper->method('getAllAsArray')->willReturn(
                [
                    AdminSetting::KEY_MAX_DASHBOARDS_PER_USER   => 5,
                    AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD => 40,
                ]
                );

        $settings = $this->service->getSettings();

        $this->assertSame(5, $settings['maxDashboardsPerUser']);
        $this->assertSame(40, $settings['maxWidgetsPerDashboard']);
    }//end testGetSettingsReturnsStoredQuotaValues()

    public function testGetSettingsClampsCorruptStoredQuota(): void
    {
        // REQ-QUOTA-001 — a hand-edited negative / out-of-range / garbage
        // row is clamped defensively on read.
        $this->settingMapper->method('getAllAsArray')->willReturn(
                [
                    AdminSetting::KEY_MAX_DASHBOARDS_PER_USER   => -8,
                    AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD => 99999,
                ]
                );

        $settings = $this->service->getSettings();

        $this->assertSame(0, $settings['maxDashboardsPerUser']);
        $this->assertSame(10000, $settings['maxWidgetsPerDashboard']);
    }//end testGetSettingsClampsCorruptStoredQuota()

    public function testUpdateSettingsPersistsClampedDashboardQuota(): void
    {
        // REQ-QUOTA-001 / REQ-ASET-014 — a negative value clamps to 0.
        $this->settingMapper->expects($this->once())
            ->method('setSetting')
            ->with(
                AdminSetting::KEY_MAX_DASHBOARDS_PER_USER,
                0
            );

        $this->service->updateSettings(maxDashboardsPerUser: -3);
    }//end testUpdateSettingsPersistsClampedDashboardQuota()

    public function testUpdateSettingsPersistsClampedWidgetQuota(): void
    {
        // REQ-QUOTA-001 — an over-range value clamps down to 10000.
        $this->settingMapper->expects($this->once())
            ->method('setSetting')
            ->with(
                AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD,
                10000
            );

        $this->service->updateSettings(maxWidgetsPerDashboard: 50000);
    }//end testUpdateSettingsPersistsClampedWidgetQuota()

    public function testUpdateSettingsPersistsBothQuotaValues(): void
    {
        // REQ-QUOTA-001 — both keys persisted when supplied.
        $this->settingMapper->expects($this->exactly(2))
            ->method('setSetting');

        $this->service->updateSettings(
            maxDashboardsPerUser: 5,
            maxWidgetsPerDashboard: 40
        );
    }//end testUpdateSettingsPersistsBothQuotaValues()

    public function testClampQuotaCoercesNonNumericToZero(): void
    {
        // REQ-QUOTA-001 — non-numeric input collapses to 0 (unlimited).
        $this->assertSame(0, $this->service->clampQuota('not-a-number'));
        $this->assertSame(0, $this->service->clampQuota(null));
        $this->assertSame(7, $this->service->clampQuota('7'));
        $this->assertSame(10000, $this->service->clampQuota(10001));
    }//end testClampQuotaCoercesNonNumericToZero()

    public function testGetSettingsSharingAndBridgeDefaults(): void
    {
        $this->settingMapper->method('getAllAsArray')->willReturn([]);

        $settings = $this->service->getSettings();

        // Default share permission mirrors the dashboard default.
        $this->assertSame(Dashboard::PERMISSION_ADD_ONLY, $settings['defaultSharePermissionLevel']);
        $this->assertSame([], $settings['forcedShareGroups']);
        // Bridge defaults ON so existing placements keep rendering.
        $this->assertTrue($settings['legacyWidgetBridgeEnabled']);
    }//end testGetSettingsSharingAndBridgeDefaults()

    public function testGetSettingsSharingAndBridgeStoredValues(): void
    {
        $this->settingMapper->method('getAllAsArray')->willReturn(
                [
                    AdminSetting::KEY_DEFAULT_SHARE_PERMISSION_LEVEL => 'full',
                    AdminSetting::KEY_FORCED_SHARE_GROUPS            => ['marketing', 'sales'],
                    AdminSetting::KEY_LEGACY_WIDGET_BRIDGE_ENABLED   => false,
                ]
                );

        $settings = $this->service->getSettings();

        $this->assertSame('full', $settings['defaultSharePermissionLevel']);
        $this->assertSame(['marketing', 'sales'], $settings['forcedShareGroups']);
        $this->assertFalse($settings['legacyWidgetBridgeEnabled']);
    }//end testGetSettingsSharingAndBridgeStoredValues()

    public function testUpdateSettingsPersistsForcedShareGroupsDeduplicated(): void
    {
        $this->settingMapper->expects($this->once())
            ->method('setSetting')
            ->with(
                AdminSetting::KEY_FORCED_SHARE_GROUPS,
                ['marketing', 'sales']
            );

        $this->service->updateSettings(
            forcedShareGroups: [' marketing ', 'sales', 'marketing', '', 42]
        );
    }//end testUpdateSettingsPersistsForcedShareGroupsDeduplicated()

    public function testUpdateSettingsPersistsBridgeToggle(): void
    {
        $this->settingMapper->expects($this->once())
            ->method('setSetting')
            ->with(
                AdminSetting::KEY_LEGACY_WIDGET_BRIDGE_ENABLED,
                false
            );

        $this->service->updateSettings(
            legacyWidgetBridgeEnabled: false
        );
    }//end testUpdateSettingsPersistsBridgeToggle()

    public function testUpdateSettingsRejectsInvalidSharePermissionLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->updateSettings(
            defaultSharePermissionLevel: 'not-a-level'
        );
    }//end testUpdateSettingsRejectsInvalidSharePermissionLevel()

    public function testUpdateSettingsPersistsLinkCreateFileExtensions(): void
    {
        $this->settingMapper->expects($this->once())
            ->method('setSetting')
            ->with(
                AdminSetting::KEY_LINK_CREATE_FILE_EXTENSIONS,
                ['txt', 'docx']
            );

        $this->service->updateSettings(
            linkCreateFileExts: ['txt', '.docx', 'BAD/PATH', '']
        );
    }//end testUpdateSettingsPersistsLinkCreateFileExtensions()

    public function testUpdateSettingsLinkExtensionsFallsBackToDefaultsWhenEmpty(): void
    {
        $this->settingMapper->expects($this->once())
            ->method('setSetting')
            ->with(
                AdminSetting::KEY_LINK_CREATE_FILE_EXTENSIONS,
                ['txt', 'md', 'docx', 'xlsx', 'csv', 'odt']
            );

        $this->service->updateSettings(linkCreateFileExts: []);
    }//end testUpdateSettingsLinkExtensionsFallsBackToDefaultsWhenEmpty()

    public function testGetSettingsReturnsStoredLinkCreateFileExtensions(): void
    {
        $this->settingMapper->method('getAllAsArray')->willReturn(
                [
                    AdminSetting::KEY_LINK_CREATE_FILE_EXTENSIONS => ['txt', 'md'],
                ]
                );

        $settings = $this->service->getSettings();

        $this->assertSame(['txt', 'md'], $settings['linkCreateFileExtensions']);
    }//end testGetSettingsReturnsStoredLinkCreateFileExtensions()

    // ----- REQ-ASET-012: getGroupOrder / setGroupOrder -----
    public function testGetGroupOrderReturnsEmptyWhenRowAbsent(): void
    {
        // REQ-ASET-012 — defensive read: row missing → []
        $this->settingMapper
            ->method('getValue')
            ->with(AdminSetting::KEY_GROUP_ORDER, null)
            ->willReturn(null);

        $this->assertSame([], $this->service->getGroupOrder());
    }//end testGetGroupOrderReturnsEmptyWhenRowAbsent()

    public function testGetGroupOrderReturnsEmptyOnCorruptValue(): void
    {
        // REQ-ASET-012 — corrupt JSON resolves to []. The mapper's
        // `getValue` returns whatever `json_decode` produced; a string
        // (or any non-array) is treated as corrupt by the service.
        $this->settingMapper
            ->method('getValue')
            ->with(AdminSetting::KEY_GROUP_ORDER, null)
            ->willReturn('{not-json');

        $this->assertSame([], $this->service->getGroupOrder());
    }//end testGetGroupOrderReturnsEmptyOnCorruptValue()

    public function testGetGroupOrderFiltersNonStringEntries(): void
    {
        // Hand-edited DB rows could carry mixed payloads — drop them.
        $this->settingMapper
            ->method('getValue')
            ->with(AdminSetting::KEY_GROUP_ORDER, null)
            ->willReturn(['engineering', 42, '', null, 'marketing']);

        $this->assertSame(
            ['engineering', 'marketing'],
            $this->service->getGroupOrder()
        );
    }//end testGetGroupOrderFiltersNonStringEntries()

    public function testGetGroupOrderPreservesOrder(): void
    {
        $this->settingMapper
            ->method('getValue')
            ->with(AdminSetting::KEY_GROUP_ORDER, null)
            ->willReturn(['zebra', 'alpha', 'marigold']);

        $this->assertSame(
            ['zebra', 'alpha', 'marigold'],
            $this->service->getGroupOrder()
        );
    }//end testGetGroupOrderPreservesOrder()

    public function testSetGroupOrderDeduplicatesPreservingOrder(): void
    {
        // REQ-ASET-014 — first occurrence wins, duplicates removed.
        $captured = null;
        $this->settingMapper
            ->expects($this->once())
            ->method('setSetting')
            ->with(
                $this->equalTo(AdminSetting::KEY_GROUP_ORDER),
                $this->callback(
                        function ($value) use (&$captured) {
                            $captured = $value;
                            return true;
                        }
                        )
            );

        $this->service->setGroupOrder(['a', 'b', 'a', 'c', 'b']);
        $this->assertSame(['a', 'b', 'c'], $captured);
    }//end testSetGroupOrderDeduplicatesPreservingOrder()

    public function testSetGroupOrderRejectsNonStringElements(): void
    {
        $this->settingMapper->expects($this->never())->method('setSetting');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->setGroupOrder(['engineering', 42, 'marketing']);
    }//end testSetGroupOrderRejectsNonStringElements()

    public function testSetGroupOrderRejectsEmptyStringElements(): void
    {
        $this->settingMapper->expects($this->never())->method('setSetting');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->setGroupOrder(['engineering', '']);
    }//end testSetGroupOrderRejectsEmptyStringElements()

    public function testSetGroupOrderEmptyArrayPersisted(): void
    {
        // REQ-ASET-012 — empty list is the documented "clear active" case.
        $captured = null;
        $this->settingMapper
            ->expects($this->once())
            ->method('setSetting')
            ->with(
                $this->equalTo(AdminSetting::KEY_GROUP_ORDER),
                $this->callback(
                        function ($value) use (&$captured) {
                            $captured = $value;
                            return true;
                        }
                        )
            );

        $this->service->setGroupOrder([]);
        $this->assertSame([], $captured);
    }//end testSetGroupOrderEmptyArrayPersisted()
}//end class
