<?php

/**
 * ConditionalService listAllRules Test
 *
 * Covers the admin Versioning & Audit overview aggregation
 * (conditional-visibility spec).
 *
 * @category  Test
 * @package   OCA\MyDash\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\MyDash\Db\ConditionalRule;
use OCA\MyDash\Db\ConditionalRuleMapper;
use OCA\MyDash\Db\Dashboard;
use OCA\MyDash\Db\DashboardMapper;
use OCA\MyDash\Db\WidgetPlacement;
use OCA\MyDash\Db\WidgetPlacementMapper;
use OCA\MyDash\Service\ConditionalService;
use OCA\MyDash\Service\RuleEvaluatorService;
use OCA\MyDash\Service\VisibilityChecker;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

class ConditionalServiceListAllRulesTest extends TestCase
{

    private ConditionalRuleMapper $ruleMapper;

    private WidgetPlacementMapper $placementMapper;

    private DashboardMapper $dashboardMapper;

    private ConditionalService $service;

    protected function setUp(): void
    {
        $this->ruleMapper      = $this->createMock(ConditionalRuleMapper::class);
        $this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
        $this->dashboardMapper = $this->createMock(DashboardMapper::class);

        $this->service = new ConditionalService(
            ruleMapper: $this->ruleMapper,
            ruleEvaluator: $this->createMock(RuleEvaluatorService::class),
            visibilityChecker: $this->createMock(VisibilityChecker::class),
            placementMapper: $this->placementMapper,
            dashboardMapper: $this->dashboardMapper,
        );
    }//end setUp()

    private function makeRule(int $placementId, bool $isInclude): ConditionalRule
    {
        $rule = new ConditionalRule();
        $rule->setWidgetPlacementId($placementId);
        $rule->setIsInclude($isInclude);
        return $rule;
    }//end makeRule()

    private function makePlacement(int $id, int $dashboardId, string $widgetId): WidgetPlacement
    {
        $placement = new WidgetPlacement();
        $placement->setId($id);
        $placement->setDashboardId($dashboardId);
        $placement->setWidgetId($widgetId);
        return $placement;
    }//end makePlacement()

    private function makeDashboard(int $id, string $name): Dashboard
    {
        $dashboard = new Dashboard();
        $dashboard->setId($id);
        $dashboard->setName($name);
        return $dashboard;
    }//end makeDashboard()

    public function testAggregatesRulesPerPlacementWithIncludeExcludeBreakdown(): void
    {
        $this->ruleMapper->method('findAll')->willReturn(
                [
                    $this->makeRule(10, true),
                    $this->makeRule(10, false),
                    $this->makeRule(10, true),
                    $this->makeRule(20, false),
                ]
                );

        $this->placementMapper->method('find')->willReturnCallback(
            function (int $id): WidgetPlacement {
                return match ($id) {
                    10 => $this->makePlacement(10, 1, 'weather'),
                    20 => $this->makePlacement(20, 2, 'calendar'),
                    default => throw new DoesNotExistException('missing'),
                };
            }
        );

        $this->dashboardMapper->method('find')->willReturnCallback(
            function (int $id): Dashboard {
                return match ($id) {
                    1 => $this->makeDashboard(1, 'Marketing'),
                    2 => $this->makeDashboard(2, 'Sales'),
                    default => throw new DoesNotExistException('missing'),
                };
            }
        );

        $rows = $this->service->listAllRules();

        $this->assertCount(2, $rows);

        $first = $rows[0];
        $this->assertSame(10, $first['placementId']);
        $this->assertSame(1, $first['dashboardId']);
        $this->assertSame('Marketing', $first['dashboardName']);
        $this->assertSame('weather', $first['widgetType']);
        $this->assertSame(3, $first['ruleCount']);
        $this->assertSame(2, $first['includeCount']);
        $this->assertSame(1, $first['excludeCount']);

        $second = $rows[1];
        $this->assertSame(20, $second['placementId']);
        $this->assertSame(1, $second['ruleCount']);
        $this->assertSame(0, $second['includeCount']);
        $this->assertSame(1, $second['excludeCount']);
    }//end testAggregatesRulesPerPlacementWithIncludeExcludeBreakdown()

    public function testSkipsRulesWhosePlacementNoLongerExists(): void
    {
        $this->ruleMapper->method('findAll')->willReturn(
                [
                    $this->makeRule(99, true),
                ]
                );

        $this->placementMapper->method('find')
            ->willThrowException(new DoesNotExistException('gone'));

        $rows = $this->service->listAllRules();

        $this->assertSame([], $rows);
    }//end testSkipsRulesWhosePlacementNoLongerExists()

    public function testToleratesMissingDashboardButStillListsPlacement(): void
    {
        $this->ruleMapper->method('findAll')->willReturn(
                [
                    $this->makeRule(10, true),
                ]
                );
        $this->placementMapper->method('find')
            ->willReturn($this->makePlacement(10, 7, 'weather'));
        $this->dashboardMapper->method('find')
            ->willThrowException(new DoesNotExistException('gone'));

        $rows = $this->service->listAllRules();

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['dashboardId']);
        $this->assertNull($rows[0]['dashboardName']);
        $this->assertSame('weather', $rows[0]['widgetType']);
    }//end testToleratesMissingDashboardButStillListsPlacement()
}//end class
