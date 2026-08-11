<?php

/**
 * RuleApiController getRules Contract Test
 *
 * Wire-contract coverage for `ruleApi#getRules`
 * (GET /api/widgets/{placementId}/rules).
 *
 * The endpoint is `#[NoAdminRequired]` and takes a bare integer placement id,
 * so any authenticated account can address any placement in the instance. Two
 * things therefore have to hold on the wire and both are asserted directly:
 *
 *   1. The refusal ladder — anonymous 401, ADR-023 action denial 403, and a
 *      non-owner refusal that MUST NOT be a 200 and MUST NOT have read the
 *      rules of a placement it does not own (REQ-PERM-001).
 *   2. The success envelope — `{rules: [...], isVisible: bool}`, the shape the
 *      conditional-visibility editor binds to. `isVisible` is the evaluated
 *      verdict for the *calling* user, not a stored column, so it is asserted
 *      to travel from the evaluator rather than from the rule rows.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use Exception;
use OCA\LaunchPad\Controller\RuleApiController;
use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\ConditionalService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for RuleApiController::getRules.
 */
class RuleApiControllerGetRulesTest extends TestCase
{

    /**
     * HTTP request mock.
     *
     * @var IRequest&MockObject
     */
    private $request;

    /**
     * Conditional-rule service mock.
     *
     * @var ConditionalService&MockObject
     */
    private $conditionalService;

    /**
     * Permission service mock.
     *
     * @var PermissionService&MockObject
     */
    private $permissionService;

    /**
     * ADR-023 action authorization mock.
     *
     * @var ActionAuthService&MockObject
     */
    private $actionAuth;

    /**
     * User session mock.
     *
     * @var IUserSession&MockObject
     */
    private $userSession;


    /**
     * Set up shared mocks.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request            = $this->createMock(IRequest::class);
        $this->conditionalService = $this->createMock(ConditionalService::class);
        $this->permissionService  = $this->createMock(PermissionService::class);
        $this->actionAuth         = $this->createMock(ActionAuthService::class);
        $this->userSession        = $this->createMock(IUserSession::class);

    }//end setUp()


    /**
     * Build the controller for the supplied user (NULL = anonymous).
     *
     * @param string|null $userId The acting user ID.
     *
     * @return RuleApiController
     */
    private function makeController(?string $userId): RuleApiController
    {
        $user = null;
        if ($userId !== null) {
            $user = $this->createMock(IUser::class);
            $user->method('getUID')->willReturn($userId);
        }

        $this->userSession->method('getUser')->willReturn($user);

        return new RuleApiController(
            request: $this->request,
            conditionalService: $this->conditionalService,
            permissionService: $this->permissionService,
            actionAuth: $this->actionAuth,
            userSession: $this->userSession,
            userId: $userId,
        );

    }//end makeController()


    /**
     * Build a rule fixture.
     *
     * @param string $ruleType The rule type discriminator.
     *
     * @return ConditionalRule
     */
    private function makeRule(string $ruleType='group'): ConditionalRule
    {
        $rule = new ConditionalRule();
        $rule->setId(3);
        $rule->setWidgetPlacementId(7);
        $rule->setRuleType($ruleType);
        $rule->setRuleConfigArray(['groups' => ['finance']]);
        $rule->setIsInclude(true);

        return $rule;

    }//end makeRule()


    /**
     * An anonymous caller MUST get 401 and MUST NOT reach the rule store.
     *
     * @return void
     */
    public function testGetRulesRejectsAnonymousWith401(): void
    {
        $this->conditionalService->expects($this->never())->method('getRules');
        $this->permissionService->expects($this->never())->method('verifyPlacementOwnership');

        $controller = $this->makeController(null);
        $response   = $controller->getRules(placementId: 7);

        $this->assertSame(
            expected: Http::STATUS_UNAUTHORIZED,
            actual: $response->getStatus()
        );

    }//end testGetRulesRejectsAnonymousWith401()


    /**
     * ADR-023: a caller whose role does not carry `rule.get-rules` MUST get
     * 403 before any ownership lookup happens.
     *
     * @return void
     */
    public function testGetRulesRejectsDeniedActionWith403(): void
    {
        $this->actionAuth->expects($this->once())
            ->method('requireAction')
            ->willThrowException(new OCSForbiddenException('denied'));

        $this->permissionService->expects($this->never())->method('verifyPlacementOwnership');
        $this->conditionalService->expects($this->never())->method('getRules');

        $controller = $this->makeController('alice');
        $response   = $controller->getRules(placementId: 7);

        $this->assertSame(
            expected: Http::STATUS_FORBIDDEN,
            actual: $response->getStatus()
        );

    }//end testGetRulesRejectsDeniedActionWith403()


    /**
     * REQ-PERM-001: a caller who does not own the placement MUST NOT get a
     * 200, and — the part that matters — the rules MUST NOT be read at all.
     * Ownership is verified BEFORE the store is touched, so a refusal cannot
     * be a 200 with a filtered body.
     *
     * @return void
     */
    public function testGetRulesRefusesANonOwnerWithoutReadingTheRules(): void
    {
        $this->permissionService->expects($this->once())
            ->method('verifyPlacementOwnership')
            ->with(userId: 'mallory', placementId: 7)
            ->willThrowException(new Exception('Access denied'));

        $this->conditionalService->expects($this->never())->method('getRules');
        $this->conditionalService->expects($this->never())->method('checkRulesForPlacement');

        $controller = $this->makeController('mallory');
        $response   = $controller->getRules(placementId: 7);

        $this->assertNotSame(
            expected: Http::STATUS_OK,
            actual: $response->getStatus()
        );
        // ADR-005: the refusal must not echo the internal message.
        $this->assertSame(expected: 'Operation failed', actual: $response->getData()['error']);

    }//end testGetRulesRefusesANonOwnerWithoutReadingTheRules()


    /**
     * The owner gets 200 with the serialised rule list and the evaluated
     * visibility verdict for their own account.
     *
     * @return void
     */
    public function testGetRulesReturnsTheRulesAndTheEvaluatedVisibility(): void
    {
        $this->permissionService->expects($this->once())
            ->method('verifyPlacementOwnership')
            ->with(userId: 'alice', placementId: 7)
            ->willReturn(new WidgetPlacement());

        $this->conditionalService->expects($this->once())
            ->method('getRules')
            ->with(placementId: 7)
            ->willReturn([$this->makeRule(ruleType: 'group')]);

        $this->conditionalService->expects($this->once())
            ->method('checkRulesForPlacement')
            ->with(placementId: 7, userId: 'alice')
            ->willReturn(false);

        $controller = $this->makeController('alice');
        $response   = $controller->getRules(placementId: 7);

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());

        $data = $response->getData();
        $this->assertCount(expectedCount: 1, haystack: $data['rules']);
        $this->assertSame(expected: 'group', actual: $data['rules'][0]['ruleType']);
        // isVisible comes from the evaluator, not from the rule rows: an
        // include rule the caller fails evaluates to false even though the
        // rule itself is stored as `isInclude = true`.
        $this->assertTrue(condition: $data['rules'][0]['isInclude']);
        $this->assertFalse(condition: $data['isVisible']);

    }//end testGetRulesReturnsTheRulesAndTheEvaluatedVisibility()


    /**
     * A placement with no rules is a legitimate 200 with an empty list —
     * not a 404 — and evaluates as visible.
     *
     * @return void
     */
    public function testGetRulesReturnsAnEmptyListForAnUnruledPlacement(): void
    {
        $this->permissionService->method('verifyPlacementOwnership')
            ->willReturn(new WidgetPlacement());

        $this->conditionalService->method('getRules')->willReturn([]);
        $this->conditionalService->method('checkRulesForPlacement')->willReturn(true);

        $controller = $this->makeController('alice');
        $response   = $controller->getRules(placementId: 8);

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(expected: [], actual: $response->getData()['rules']);
        $this->assertTrue(condition: $response->getData()['isVisible']);

    }//end testGetRulesReturnsAnEmptyListForAnUnruledPlacement()


}//end class
