<?php

/**
 * ActionAuthBaselineTest
 *
 * Unit tests for the ADR-023 non-admin baseline in
 * {@see \OCA\LaunchPad\Service\ActionAuthService}.
 *
 * REGRESSION GUARD: the shipped `lib/actions.seed.json` used to map EVERY
 * declared action to `["admin"]`, and nothing ever broadened it. A non-admin
 * hit `Action 'dashboard.list' requires admin rights` on the very first AJAX
 * call, so a fresh install was admin-only until someone hand-edited the
 * matrix — including the "Create your first dashboard" CTA the empty state
 * shows to exactly those users.
 *
 * These tests read the REAL shipped seed file, so they fail the moment the
 * baseline is reverted or an administrative action is accidentally
 * broadened.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the shipped action-authorization baseline.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ActionAuthBaselineTest extends TestCase
{
    private const SEED_PATH = __DIR__.'/../../../lib/actions.seed.json';

    /**
     * Administrative actions that MUST stay admin-only. Broadening any of
     * these is a privilege escalation, not a usability fix.
     *
     * @var array<int, string>
     */
    private const ADMIN_ONLY_ACTIONS = [
        'admin.get-my-role',
        'admin-org-navigation.get-org-navigation',
        'admin-org-navigation.get-position',
        'analytics.top-dashboards',
        'analytics.dashboard-detail',
        'analytics.instance-summary',
        'analytics.export-csv',
        'tile-analytics.top-tiles',
        'tile-analytics.dashboard-breakdown',
        'tile-analytics.export-csv',
        'dashboard.publish',
        'dashboard.unpublish',
        'dashboard.schedule',
        'dashboard-lock.force-release',
        'dashboard-metadata.set-metadata',
        'dashboard-translation.create',
        'dashboard-translation.update',
        'dashboard-translation.destroy',
        'dashboard-translation.set-primary',
        'dashboard-version.list-versions',
        'dashboard-version.fetch-version',
        'dashboard-version.create-version',
        'dashboard-version.restore-version',
        'metadata-admin.list-fields',
        'metadata-admin.create-field',
        'metadata-admin.get-field',
        'metadata-admin.update-field',
        'metadata-admin.delete-field',
        'people-widget.get-users',
        'rule.get-rules',
        'rule.add-rule',
        'rule.update-rule',
        'rule.delete-rule',
        'tile.create',
        'tile.update',
        'tile.destroy',
    ];

    /**
     * The ordinary end-user surface a fresh install MUST make usable.
     *
     * @var array<int, string>
     */
    private const BASELINE_ACTIONS = [
        'dashboard.list',
        'dashboard.visible',
        'dashboard.get-active',
        'dashboard.show',
        'dashboard.create',
        'dashboard.update',
        'dashboard.delete',
        'dashboard.tree',
        'dashboard.activate',
        'dashboard.set-active-dashboard',
        'widget.list-available',
        'widget.get-items',
        'widget.add-widget',
        'widget.update-placement',
        'widget.remove-placement',
        'manifest.index',
        'template.gallery',
        'tile.index',
    ];

    /** @var IAppConfig&MockObject */
    private $appConfig;

    /** @var IGroupManager&MockObject */
    private $groupManager;

    /** @var AdminTemplateService&MockObject */
    private $adminTemplateService;

    /**
     * Set up fresh mocks per test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->appConfig            = $this->createMock(IAppConfig::class);
        $this->groupManager         = $this->createMock(IGroupManager::class);
        $this->adminTemplateService = $this->createMock(AdminTemplateService::class);
    }//end setUp()

    /**
     * Read the shipped seed's `actions` map.
     *
     * @return array<string, array<int, string>>
     */
    private function seedActions(): array
    {
        $raw = file_get_contents(self::SEED_PATH);
        $this->assertIsString($raw, 'lib/actions.seed.json must be readable.');

        $parsed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($parsed['actions'] ?? null);

        return $parsed['actions'];
    }//end seedActions()

    /**
     * Build the service with the given matrix already stored.
     *
     * @param array<string, array<int, string>> $matrix The stored matrix.
     *
     * @return ActionAuthService
     */
    private function serviceWithMatrix(array $matrix): ActionAuthService
    {
        $this->appConfig
            ->method('getValueString')
            ->willReturn(json_encode($matrix, JSON_THROW_ON_ERROR));

        return new ActionAuthService(
            appConfig: $this->appConfig,
            groupManager: $this->groupManager,
            adminTemplateService: $this->adminTemplateService,
        );
    }//end serviceWithMatrix()

    /**
     * A non-admin user with no group memberships at all.
     *
     * @return IUser&MockObject
     */
    private function ordinaryUser(): IUser
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('recipient');
        $this->groupManager->method('isAdmin')->with('recipient')->willReturn(false);
        $this->adminTemplateService->method('getUserGroupIdsFor')->willReturn([]);

        return $user;
    }//end ordinaryUser()

    /**
     * The whole defect in one assertion: with the SHIPPED seed loaded, an
     * ordinary non-admin user with zero groups may list dashboards.
     *
     * Pre-fix the seed mapped `dashboard.list` to `["admin"]` and this threw
     * `Action 'dashboard.list' requires admin rights`.
     *
     * @return void
     */
    public function testShippedSeedLetsAnOrdinaryUserListDashboards(): void
    {
        $service = $this->serviceWithMatrix($this->seedActions());

        $service->requireAction($this->ordinaryUser(), 'dashboard.list');

        $this->addToAssertionCount(1);
    }//end testShippedSeedLetsAnOrdinaryUserListDashboards()

    /**
     * The empty state offers "Create your first dashboard" to exactly the
     * users who could not call it. The shipped seed must make that CTA work.
     *
     * @return void
     */
    public function testShippedSeedLetsAnOrdinaryUserCreateADashboard(): void
    {
        $service = $this->serviceWithMatrix($this->seedActions());

        $service->requireAction($this->ordinaryUser(), 'dashboard.create');

        $this->addToAssertionCount(1);
    }//end testShippedSeedLetsAnOrdinaryUserCreateADashboard()

    /**
     * Every action on the ordinary end-user surface passes for a non-admin
     * under the shipped seed.
     *
     * @return void
     */
    public function testShippedSeedCoversTheWholeOrdinaryUserSurface(): void
    {
        $service = $this->serviceWithMatrix($this->seedActions());
        $user    = $this->ordinaryUser();

        foreach (self::BASELINE_ACTIONS as $action) {
            $service->requireAction($user, $action);
        }

        $this->addToAssertionCount(count(self::BASELINE_ACTIONS));
    }//end testShippedSeedCoversTheWholeOrdinaryUserSurface()

    /**
     * The other half of the decision: administrative actions stay
     * admin-only. This is what stops the fix from being "open everything up".
     *
     * @return void
     */
    public function testShippedSeedKeepsAdministrativeActionsAdminOnly(): void
    {
        $seed = $this->seedActions();

        foreach (self::ADMIN_ONLY_ACTIONS as $action) {
            $this->assertArrayHasKey($action, $seed, "Seed is missing {$action}.");
            $this->assertSame(
                ['admin'],
                $seed[$action],
                "Administrative action {$action} must stay admin-only."
            );
        }
    }//end testShippedSeedKeepsAdministrativeActionsAdminOnly()

    /**
     * An administrative action is still refused for a non-admin at runtime.
     *
     * @return void
     */
    public function testAdministrativeActionIsStillRefusedForNonAdmins(): void
    {
        $service = $this->serviceWithMatrix($this->seedActions());

        $this->expectException(OCSForbiddenException::class);
        $this->expectExceptionMessage(
            "Action 'analytics.instance-summary' requires admin rights"
        );

        $service->requireAction($this->ordinaryUser(), 'analytics.instance-summary');
    }//end testAdministrativeActionIsStillRefusedForNonAdmins()

    /**
     * The `@all` sentinel means "every authenticated user" — it is not
     * matched against real group memberships (there is no Nextcloud group
     * that contains every account).
     *
     * @return void
     */
    public function testAllUsersSentinelGrantsAccessWithoutAnyGroupMembership(): void
    {
        $service = $this->serviceWithMatrix(
            ['some.action' => ['admin', ActionAuthService::GROUP_ALL_USERS]]
        );

        $service->requireAction($this->ordinaryUser(), 'some.action');

        $this->addToAssertionCount(1);
    }//end testAllUsersSentinelGrantsAccessWithoutAnyGroupMembership()

    /**
     * Default-deny is untouched: an action absent from the matrix, and an
     * action explicitly narrowed to `["admin"]`, both still refuse.
     *
     * @return void
     */
    public function testDefaultDenyIsUnchanged(): void
    {
        $service = $this->serviceWithMatrix(['known.action' => ['admin']]);
        $user    = $this->ordinaryUser();

        $threw = 0;
        foreach (['known.action', 'entirely.unknown'] as $action) {
            try {
                $service->requireAction($user, $action);
            } catch (OCSForbiddenException) {
                $threw++;
            }
        }

        $this->assertSame(2, $threw, 'Both actions must still be refused.');
    }//end testDefaultDenyIsUnchanged()

    /**
     * An admin who deliberately narrows a baseline action back down to
     * `["admin"]` is obeyed — the sentinel is data, not a hardcoded bypass.
     *
     * @return void
     */
    public function testAdminCanNarrowABaselineActionBackDown(): void
    {
        $service = $this->serviceWithMatrix(['dashboard.list' => ['admin']]);

        $this->expectException(OCSForbiddenException::class);

        $service->requireAction($this->ordinaryUser(), 'dashboard.list');
    }//end testAdminCanNarrowABaselineActionBackDown()

    /**
     * Every action the seed declares is either admin-only or carries the
     * sentinel — no half-configured entries, and the seed stays the single
     * source of truth the repair step reads.
     *
     * @return void
     */
    public function testSeedEntriesAlwaysIncludeAdmin(): void
    {
        foreach ($this->seedActions() as $action => $groups) {
            $this->assertContains(
                'admin',
                $groups,
                "Seed entry {$action} must keep admin in the list."
            );
        }
    }//end testSeedEntriesAlwaysIncludeAdmin()
}//end class
