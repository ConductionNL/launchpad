<?php

/**
 * VisibilityPreviewControllerViewAsTest
 *
 * "View as" in the rules dialog (REQ-CVUI-006): the preview evaluates the
 * rules as a chosen person, with that person's own groups resolved on the
 * server, through the same pipeline the dashboard renders with, in the
 * placement's match mode (REQ-VIS-014).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */

declare(strict_types=1);

namespace Unit\Controller;

use Exception;
use OCA\LaunchPad\Controller\VisibilityPreviewController;
use OCA\LaunchPad\Db\ConditionalRuleMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ConditionalService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RuleEvaluatorService;
use OCA\LaunchPad\Service\UserAttributeResolver;
use OCA\LaunchPad\Service\VisibilityChecker;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Preview as a person.
 *
 * @covers \OCA\LaunchPad\Controller\VisibilityPreviewController
 */
class VisibilityPreviewControllerViewAsTest extends TestCase {

	/**
	 * Who may edit which placement.
	 *
	 * @var PermissionService&MockObject
	 */
	private $permissions;

	/**
	 * Which users exist.
	 *
	 * @var IUserManager&MockObject
	 */
	private $users;

	/**
	 * The preview controller over the real evaluation pipeline, called by
	 * alice. Sanne is in KCC, alice in no group.
	 *
	 * @return VisibilityPreviewController
	 */
	private function controller(): VisibilityPreviewController {
		$templates = $this->createMock(AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnMap([['sanne', ['KCC']], ['alice', []]]);
		$evaluator = new RuleEvaluatorService(
			adminTemplateService: $templates,
			attrResolver: $this->createMock(UserAttributeResolver::class)
		);
		$service = new ConditionalService(
			ruleMapper: $this->createMock(ConditionalRuleMapper::class),
			ruleEvaluator: $evaluator,
			visibilityChecker: new VisibilityChecker(ruleEvaluator: $evaluator),
			placementMapper: $this->createMock(WidgetPlacementMapper::class),
			dashboardMapper: $this->createMock(DashboardMapper::class),
		);
		$this->permissions = $this->createMock(PermissionService::class);
		$this->users = $this->createMock(IUserManager::class);
		$this->users->method('userExists')->willReturnCallback(static fn (string $uid): bool => $uid === 'sanne');

		return new VisibilityPreviewController(
			request: $this->createMock(IRequest::class),
			conditionalService: $service,
			userId: 'alice',
			permissionService: $this->permissions,
			userManager: $this->users,
		);
	}//end controller()

	/**
	 * Group KCC and Monday to Friday 08:00 to 17:00, as the board's example.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function rules(): array {
		return [
			['id' => 1, 'ruleType' => 'group', 'ruleConfig' => ['groups' => ['KCC']], 'isInclude' => true],
			[
				'id' => 2,
				'ruleType' => 'time',
				'ruleConfig' => ['startTime' => '08:00', 'endTime' => '17:00', 'days' => ['mon', 'tue', 'wed', 'thu', 'fri']],
				'isInclude' => true,
			],
		];
	}//end rules()

	/**
	 * Sanne, in KCC, sees the widget on Tuesday 13 October 10:00 when all
	 * rules must match; both rules are named as the reason.
	 *
	 * @return void
	 */
	public function testSanneSeesTheWidget(): void {
		$controller = $this->controller();
		$this->permissions->expects(self::once())->method('verifyPlacementOwnership')
			->with('alice', 10)->willReturn(new WidgetPlacement());

		$response = $controller->preview(
			rules: self::rules(),
			context: ['userId' => 'sanne', 'datetime' => '2026-10-13 10:00'],
			match: 'all',
			placementId: 10
		);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(['visible' => true, 'matchedIncludeRuleIds' => [1, 2], 'matchedExcludeRuleIds' => []], $response->getData());
	}//end testSanneSeesTheWidget()

	/**
	 * On Saturday the time rule fails: hidden when all must match, shown
	 * when one is enough.
	 *
	 * @return void
	 */
	public function testTheMatchModeDecidesOnSaturday(): void {
		$controller = $this->controller();
		$this->permissions->method('verifyPlacementOwnership')->willReturn(new WidgetPlacement());
		$context = ['userId' => 'sanne', 'datetime' => '2026-10-17 10:00'];

		$all = $controller->preview(rules: self::rules(), context: $context, match: 'all', placementId: 10)->getData();
		$any = $controller->preview(rules: self::rules(), context: $context, match: 'any', placementId: 10)->getData();

		self::assertFalse($all['visible']);
		self::assertTrue($any['visible']);
		self::assertSame([1], $any['matchedIncludeRuleIds']);
	}//end testTheMatchModeDecidesOnSaturday()

	/**
	 * Viewing as yourself uses your own groups and asks no permission.
	 *
	 * @return void
	 */
	public function testViewingAsYourselfUsesYourGroups(): void {
		$controller = $this->controller();
		$this->permissions->expects(self::never())->method('verifyPlacementOwnership');

		$response = $controller->preview(rules: self::rules(), context: ['userId' => 'alice', 'groups' => ['KCC'], 'datetime' => '2026-10-13 10:00']);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame([2], $response->getData()['matchedIncludeRuleIds']);
	}//end testViewingAsYourselfUsesYourGroups()

	/**
	 * Viewing as someone else needs a placement whose rules you may edit.
	 *
	 * @return void
	 */
	public function testViewingAsSomeoneElseNeedsTheRulesEditor(): void {
		$controller = $this->controller();
		$this->permissions->method('verifyPlacementOwnership')->willThrowException(new Exception('denied'));

		$withoutPlacement = $controller->preview(rules: self::rules(), context: ['userId' => 'sanne']);
		$notOwner = $controller->preview(rules: self::rules(), context: ['userId' => 'sanne'], placementId: 10);

		self::assertSame(Http::STATUS_FORBIDDEN, $withoutPlacement->getStatus());
		self::assertSame(Http::STATUS_FORBIDDEN, $notOwner->getStatus());
	}//end testViewingAsSomeoneElseNeedsTheRulesEditor()

	/**
	 * An unknown person or an unknown match mode is refused.
	 *
	 * @return void
	 */
	public function testUnknownPersonOrModeIsRefused(): void {
		$controller = $this->controller();
		$this->permissions->method('verifyPlacementOwnership')->willReturn(new WidgetPlacement());

		$nobody = $controller->preview(rules: [], context: ['userId' => 'nobody'], placementId: 10);
		$mode = $controller->preview(rules: [], context: [], match: 'most');

		self::assertSame(Http::STATUS_BAD_REQUEST, $nobody->getStatus());
		self::assertSame(['error' => 'Unknown person to view as'], $nobody->getData());
		self::assertSame(Http::STATUS_BAD_REQUEST, $mode->getStatus());
		self::assertSame(['error' => 'match must be any or all'], $mode->getData());
	}//end testUnknownPersonOrModeIsRefused()
}//end class
