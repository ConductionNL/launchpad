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
use OCA\LaunchPad\Service\ViewAsGuard;
use OCA\LaunchPad\Service\VisibilityChecker;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
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
	 * The request body beside `rules` and `context`: `match`, `placementId`.
	 *
	 * @var array<string, mixed>
	 */
	private array $body = [];

	/**
	 * The preview controller over the real evaluation pipeline, called by
	 * alice. Sanne is in KCC, alice in no group.
	 *
	 * @return VisibilityPreviewController
	 */
	private function controller(): VisibilityPreviewController {
		$templates = $this->createMock(originalClassName: AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnMap([['sanne', ['KCC']], ['alice', []]]);
		$evaluator = new RuleEvaluatorService(
			adminTemplateService: $templates,
			attrResolver: $this->createMock(originalClassName: UserAttributeResolver::class)
		);
		$service = new ConditionalService(
			ruleMapper: $this->createMock(originalClassName: ConditionalRuleMapper::class),
			ruleEvaluator: $evaluator,
			visibilityChecker: new VisibilityChecker(ruleEvaluator: $evaluator),
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			dashboardMapper: $this->createMock(originalClassName: DashboardMapper::class),
		);
		$this->permissions = $this->createMock(originalClassName: PermissionService::class);

		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key): mixed => ($this->body[$key] ?? null));

		return new VisibilityPreviewController(
			request: $request,
			conditionalService: $service,
			userId: 'alice',
			viewAsGuard: new ViewAsGuard(permissionService: $this->permissions, userId: 'alice'),
		);
	}//end controller()

	/**
	 * Ask a preview with the given body.
	 *
	 * @param VisibilityPreviewController $controller The controller.
	 * @param array<string, mixed>        $body       `rules`, `context`, `match`, `placementId`.
	 *
	 * @return JSONResponse The answer.
	 */
	private function ask(VisibilityPreviewController $controller, array $body): JSONResponse {
		$this->body = $body;
		return $controller->preview(rules: ($body['rules'] ?? []), context: ($body['context'] ?? []));
	}//end ask()

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

		$response = $this->ask(
			controller: $controller,
			body: [
				'rules' => self::rules(),
				'context' => ['userId' => 'sanne', 'datetime' => '2026-10-13 10:00'],
				'match' => 'all',
				'placementId' => 10,
			]
		);

		self::assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		self::assertSame(
			expected: ['visible' => true, 'matchedIncludeRuleIds' => [1, 2], 'matchedExcludeRuleIds' => []],
			actual: $response->getData()
		);
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

		$all = $this->ask(
			controller: $controller,
			body: ['rules' => self::rules(), 'context' => $context, 'match' => 'all', 'placementId' => 10]
		)->getData();
		$any = $this->ask(
			controller: $controller,
			body: ['rules' => self::rules(), 'context' => $context, 'match' => 'any', 'placementId' => 10]
		)->getData();

		self::assertFalse(condition: $all['visible']);
		self::assertTrue(condition: $any['visible']);
		self::assertSame(expected: [1], actual: $any['matchedIncludeRuleIds']);
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

		self::assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		self::assertSame(expected: [2], actual: $response->getData()['matchedIncludeRuleIds']);
	}//end testViewingAsYourselfUsesYourGroups()

	/**
	 * Viewing as someone else needs a placement whose rules you may edit.
	 *
	 * @return void
	 */
	public function testViewingAsSomeoneElseNeedsTheRulesEditor(): void {
		$controller = $this->controller();
		$this->permissions->method('verifyPlacementOwnership')->willThrowException(new Exception('denied'));

		$withoutPlacement = $this->ask(controller: $controller, body: ['rules' => self::rules(), 'context' => ['userId' => 'sanne']]);
		$notOwner = $this->ask(controller: $controller, body: ['rules' => self::rules(), 'context' => ['userId' => 'sanne'], 'placementId' => 10]);

		self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $withoutPlacement->getStatus());
		self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $notOwner->getStatus());
	}//end testViewingAsSomeoneElseNeedsTheRulesEditor()

	/**
	 * An unknown match mode is refused; an unknown person is in no group.
	 *
	 * @return void
	 */
	public function testAnUnknownModeIsRefused(): void {
		$controller = $this->controller();
		$this->permissions->method('verifyPlacementOwnership')->willReturn(new WidgetPlacement());

		$nobody = $this->ask(controller: $controller, body: ['context' => ['userId' => 'nobody'], 'placementId' => 10]);
		$mode = $this->ask(controller: $controller, body: ['match' => 'most']);

		self::assertSame(expected: Http::STATUS_OK, actual: $nobody->getStatus());
		self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $mode->getStatus());
		self::assertSame(expected: ['error' => 'match must be any or all'], actual: $mode->getData());
	}//end testAnUnknownModeIsRefused()
}//end class
