<?php

/**
 * VisibilityPreviewController Test
 *
 * Covers REQ-CVUI-005 (conditional-visibility-editor spec): the preview
 * endpoint delegates to the shared evaluation service, persists nothing,
 * validates its input, and requires authentication.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use DateTime;
use OCA\LaunchPad\Controller\VisibilityPreviewController;
use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Db\ConditionalRuleMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ConditionalService;
use OCA\LaunchPad\Service\RuleEvaluatorService;
use OCA\LaunchPad\Service\UserAttributeResolver;
use OCA\LaunchPad\Service\VisibilityChecker;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VisibilityPreviewControllerTest extends TestCase
{

    /** @var IRequest&MockObject */
    private $request;

    /** @var ConditionalRuleMapper&MockObject */
    private $ruleMapper;

    /** @var AdminTemplateService&MockObject */
    private $adminTemplateService;

    /** @var UserAttributeResolver&MockObject */
    private $attrResolver;

    /** @var WidgetPlacementMapper&MockObject */
    private $placementMapper;

    /** @var DashboardMapper&MockObject */
    private $dashboardMapper;

    private ConditionalService $conditionalService;

    protected function setUp(): void
    {
        $this->request              = $this->createMock(IRequest::class);
        $this->ruleMapper           = $this->createMock(ConditionalRuleMapper::class);
        $this->adminTemplateService = $this->createMock(AdminTemplateService::class);
        $this->attrResolver         = $this->createMock(UserAttributeResolver::class);
        $this->placementMapper      = $this->createMock(WidgetPlacementMapper::class);
        $this->dashboardMapper      = $this->createMock(DashboardMapper::class);

        // REAL RuleEvaluatorService + VisibilityChecker + ConditionalService
        // — the preview path under test MUST delegate to these exact
        // instances, not a fork. Only the two live-context resolvers
        // (group membership, user attributes) and the DB mappers are
        // mocked.
        $ruleEvaluator = new RuleEvaluatorService(
            adminTemplateService: $this->adminTemplateService,
            attrResolver: $this->attrResolver,
        );
        $visibilityChecker       = new VisibilityChecker(ruleEvaluator: $ruleEvaluator);
        $this->conditionalService = new ConditionalService(
            ruleMapper: $this->ruleMapper,
            ruleEvaluator: $ruleEvaluator,
            visibilityChecker: $visibilityChecker,
            placementMapper: $this->placementMapper,
            dashboardMapper: $this->dashboardMapper,
        );
    }//end setUp()

    /**
     * Build a controller for the given user.
     *
     * @param string|null $userId The acting user ID.
     *
     * @return VisibilityPreviewController
     */
    private function makeController(?string $userId='alice'): VisibilityPreviewController
    {
        return new VisibilityPreviewController(
            request: $this->request,
            conditionalService: $this->conditionalService,
            userId: $userId,
        );
    }//end makeController()

    /**
     * REQ-CVUI-005: an unauthenticated request MUST be rejected 401 before
     * any evaluation happens.
     *
     * @return void
     */
    public function testAnonymousCallerReturns401(): void
    {
        $this->ruleMapper->expects($this->never())->method('findByPlacementId');

        $controller = $this->makeController(userId: null);
        $response   = $controller->preview(rules: [], context: []);

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }//end testAnonymousCallerReturns401()

    /**
     * REQ-CVUI-005: preview delegates to the shared evaluation service
     * (VisibilityChecker::evaluateRuleSet via ConditionalService) and
     * returns the visible/matched-ids envelope for a matching group.
     *
     * @return void
     */
    public function testPreviewShowsVisibleForMatchingAudience(): void
    {
        $this->adminTemplateService->expects($this->never())
            ->method('getUserGroupIdsFor');

        $controller = $this->makeController();
        $response   = $controller->preview(
            rules: [
                ['id' => 1, 'ruleType' => 'group', 'ruleConfig' => ['groups' => ['marketing']], 'isInclude' => true],
            ],
            context: ['groups' => ['marketing'], 'datetime' => '2026-07-23T14:30']
        );

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        $this->assertTrue($data['visible']);
        $this->assertSame([1], $data['matchedIncludeRuleIds']);
        $this->assertSame([], $data['matchedExcludeRuleIds']);
    }//end testPreviewShowsVisibleForMatchingAudience()

    /**
     * REQ-CVUI-005 scenario: non-matching audience → hidden, no include
     * rule indicated as matched.
     *
     * @return void
     */
    public function testPreviewShowsHiddenForNonMatchingAudience(): void
    {
        $controller = $this->makeController();
        $response   = $controller->preview(
            rules: [
                ['id' => 1, 'ruleType' => 'group', 'ruleConfig' => ['groups' => ['marketing']], 'isInclude' => true],
            ],
            context: ['groups' => ['engineering'], 'datetime' => '2026-07-23T14:30']
        );

        $data = $response->getData();
        $this->assertFalse($data['visible']);
        $this->assertSame([], $data['matchedIncludeRuleIds']);
    }//end testPreviewShowsHiddenForNonMatchingAudience()

    /**
     * REQ-CVUI-005 scenario: an exclude rule overrides a matching include
     * rule and is reported as the matched reason.
     *
     * @return void
     */
    public function testPreviewReflectsExcludeOverride(): void
    {
        $controller = $this->makeController();
        $response   = $controller->preview(
            rules: [
                ['id' => 1, 'ruleType' => 'group', 'ruleConfig' => ['groups' => ['marketing']], 'isInclude' => true],
                ['id' => 2, 'ruleType' => 'date', 'ruleConfig' => ['startDate' => '2026-07-01', 'endDate' => '2026-07-31'], 'isInclude' => false],
            ],
            context: ['groups' => ['marketing'], 'datetime' => '2026-07-15T10:00']
        );

        $data = $response->getData();
        $this->assertFalse($data['visible']);
        $this->assertSame([1], $data['matchedIncludeRuleIds']);
        $this->assertSame([2], $data['matchedExcludeRuleIds']);
    }//end testPreviewReflectsExcludeOverride()

    /**
     * REQ-CVUI-005: unknown ruleType MUST be rejected with HTTP 400 and
     * only the four canonical types accepted.
     *
     * @return void
     */
    public function testUnknownRuleTypeReturns400(): void
    {
        $controller = $this->makeController();
        $response   = $controller->preview(
            rules: [
                ['id' => 1, 'ruleType' => 'weather', 'ruleConfig' => [], 'isInclude' => true],
            ],
            context: ['groups' => [], 'datetime' => '2026-07-23T14:30']
        );

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }//end testUnknownRuleTypeReturns400()

    /**
     * REQ-CVUI-005: malformed ruleConfig (non-array) MUST be rejected with
     * HTTP 400.
     *
     * @return void
     */
    public function testMalformedRuleConfigReturns400(): void
    {
        $controller = $this->makeController();
        $response   = $controller->preview(
            rules: [
                ['id' => 1, 'ruleType' => 'group', 'ruleConfig' => 'not-an-array', 'isInclude' => true],
            ],
            context: []
        );

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }//end testMalformedRuleConfigReturns400()

    /**
     * REQ-CVUI-005: the response body is EXACTLY {visible,
     * matchedIncludeRuleIds, matchedExcludeRuleIds} — nothing else, and no
     * DB mapper method (insert/update/delete/find) is ever invoked, proving
     * the preview persists nothing.
     *
     * @return void
     */
    public function testPreviewPersistsNothing(): void
    {
        $this->ruleMapper->expects($this->never())->method('insert');
        $this->ruleMapper->expects($this->never())->method('update');
        $this->ruleMapper->expects($this->never())->method('delete');
        $this->ruleMapper->expects($this->never())->method('findByPlacementId');
        $this->ruleMapper->expects($this->never())->method('find');

        $controller = $this->makeController();
        $response   = $controller->preview(
            rules: [
                ['id' => 1, 'ruleType' => 'group', 'ruleConfig' => ['groups' => ['marketing']], 'isInclude' => true],
            ],
            context: ['groups' => ['marketing'], 'datetime' => '2026-07-23T14:30']
        );

        $data = $response->getData();
        $this->assertSame(
            ['visible', 'matchedIncludeRuleIds', 'matchedExcludeRuleIds'],
            array_keys($data)
        );
    }//end testPreviewPersistsNothing()

    /**
     * REQ-CVUI-005 scenario: "Preview verdict matches render-time verdict
     * for identical inputs" — build the SAME rule (matching a group the
     * "live" user is a member of per the mocked AdminTemplateService) and
     * assert the render-time path (ConditionalService::checkRulesForPlacement,
     * going through ConditionalRuleMapper) and the preview path
     * (ConditionalService::previewRules, given the same groups explicitly)
     * agree — because both route through the same VisibilityChecker
     * instance, not because the test independently re-derives the answer.
     *
     * @return void
     */
    public function testPreviewVerdictMatchesRenderTimeVerdictForIdenticalInputs(): void
    {
        $rule = new ConditionalRule();
        $rule->setId(7);
        $rule->setWidgetPlacementId(10);
        $rule->setRuleType('group');
        $rule->setRuleConfigArray(['groups' => ['marketing']]);
        $rule->setIsInclude(true);

        // Render-time: ConditionalRuleMapper returns the stored rule, and
        // the live user's group membership (resolved via
        // AdminTemplateService — REQ-TMPL-013) is ['marketing'].
        $this->ruleMapper->method('findByPlacementId')->willReturn([$rule]);
        $this->adminTemplateService->method('getUserGroupIdsFor')
            ->with('alice')
            ->willReturn(['marketing']);

        $renderTimeVisible = $this->conditionalService->checkRulesForPlacement(
            placementId: 10,
            userId: 'alice'
        );

        // Preview: identical rule content, identical groups supplied
        // explicitly as the preview context instead of resolved from the
        // live session.
        $controller = $this->makeController();
        $response   = $controller->preview(
            rules: [
                ['id' => 7, 'ruleType' => 'group', 'ruleConfig' => ['groups' => ['marketing']], 'isInclude' => true],
            ],
            context: ['groups' => ['marketing'], 'datetime' => '2026-07-23T14:30']
        );
        $previewVisible = $response->getData()['visible'];

        $this->assertTrue($renderTimeVisible);
        $this->assertSame($renderTimeVisible, $previewVisible);
    }//end testPreviewVerdictMatchesRenderTimeVerdictForIdenticalInputs()
}//end class
