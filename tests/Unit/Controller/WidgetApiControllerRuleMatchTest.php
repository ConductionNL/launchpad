<?php

/**
 * WidgetApiControllerRuleMatchTest
 *
 * A placement update accepts `ruleMatch` only as `any` or `all`, and only
 * from someone who may edit the placement's rules (REQ-VIS-014).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
 */

declare(strict_types=1);

namespace Unit\Controller;

use Exception;
use OCA\LaunchPad\Controller\ActorResponses;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Controller\ResponseHelper;
use OCA\LaunchPad\Controller\UserResponses;
use OCA\LaunchPad\Controller\WidgetApiAccess;
use OCA\LaunchPad\Controller\WidgetApiController;
use OCA\LaunchPad\Controller\WidgetApiPolicy;
use OCA\LaunchPad\Controller\WidgetApiSources;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetPlacementService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The rule match mode on the placement update path.
 *
 * @covers \OCA\LaunchPad\Controller\WidgetApiController
 */
class WidgetApiControllerRuleMatchTest extends TestCase {

	/**
	 * The widget service double.
	 *
	 * @var WidgetService&MockObject
	 */
	private $widgetService;

	/**
	 * The permission service double.
	 *
	 * @var PermissionService&MockObject
	 */
	private $permissions;

	/**
	 * The controller for an update carrying the given parameters.
	 *
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @return WidgetApiController
	 */
	private function controller(array $params): WidgetApiController {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(
			static fn (string $key, mixed $default = null): mixed => ($params[$key] ?? $default)
		);
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('jan');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$this->widgetService = $this->createMock(originalClassName: WidgetService::class);
		$this->permissions = $this->createMock(originalClassName: PermissionService::class);
		$this->permissions->method('canStyleWidget')->willReturn(true);

		return new WidgetApiController(
			request: $request,
			sources: new WidgetApiSources(
				widgetService: $this->widgetService,
				newsWidgetService: $this->createMock(originalClassName: NewsWidgetService::class),
				calendarService: $this->createMock(originalClassName: CalendarWidgetService::class),
				placementService: $this->createMock(originalClassName: WidgetPlacementService::class)
			),
			access: new WidgetApiAccess(
				permissionService: $this->permissions,
				policy: new WidgetApiPolicy(
					roleFeaturePerm: $this->createMock(originalClassName: RoleFeaturePermissionService::class),
					logger: $this->createMock(originalClassName: LoggerInterface::class)
				)
			),
			context: new ActorResponses(
				actor: new RequestActor(userSession: $session, actionAuth: $this->createMock(originalClassName: ActionAuthService::class)),
				responses: new UserResponses(userId: 'jan', responseHelper: new ResponseHelper(logger: new NullLogger()))
			)
		);
	}//end controller()

	/**
	 * A mode other than any or all is refused with 400 and nothing is saved.
	 *
	 * @return void
	 */
	public function testAnUnknownModeIsRefused(): void {
		$controller = $this->controller(params: ['ruleMatch' => 'some']);
		$this->widgetService->expects(self::never())->method('updatePlacement');

		$response = $controller->updatePlacement(placementId: 10);

		self::assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
		self::assertSame(expected: 'ruleMatch must be any or all', actual: $response->getData()['message']);
	}//end testAnUnknownModeIsRefused()

	/**
	 * Someone who may not edit the rules may not change how they combine.
	 *
	 * @return void
	 */
	public function testOnlyTheRulesEditorMayChangeTheMode(): void {
		$controller = $this->controller(params: ['ruleMatch' => 'all']);
		$this->permissions->method('verifyPlacementOwnership')->willThrowException(new Exception('denied'));
		$this->widgetService->expects(self::never())->method('updatePlacement');

		self::assertSame(expected: Http::STATUS_FORBIDDEN, actual: $controller->updatePlacement(placementId: 10)->getStatus());
	}//end testOnlyTheRulesEditorMayChangeTheMode()

	/**
	 * The owner's `all` reaches the update and comes back on the placement.
	 *
	 * @return void
	 */
	public function testTheOwnersModeIsSaved(): void {
		$controller = $this->controller(params: ['ruleMatch' => 'all']);
		$this->permissions->expects(self::once())->method('verifyPlacementOwnership')
			->with('jan', 10)->willReturn(new WidgetPlacement());
		$saved = new WidgetPlacement();
		$saved->setRuleMatch('all');
		$this->widgetService->expects(self::once())->method('updatePlacement')
			->with(10, ['ruleMatch' => 'all'])->willReturn($saved);

		$response = $controller->updatePlacement(placementId: 10);

		self::assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
		self::assertSame(expected: 'all', actual: $response->getData()['ruleMatch']);
	}//end testTheOwnersModeIsSaved()

	/**
	 * An update without a mode does not ask who may edit the rules.
	 *
	 * @return void
	 */
	public function testAnUpdateWithoutAModeIsNotChecked(): void {
		$controller = $this->controller(params: ['gridX' => 2]);
		$this->permissions->expects(self::never())->method('verifyPlacementOwnership');
		$this->widgetService->method('updatePlacement')->willReturn(new WidgetPlacement());

		self::assertSame(expected: Http::STATUS_OK, actual: $controller->updatePlacement(placementId: 10)->getStatus());
	}//end testAnUpdateWithoutAModeIsNotChecked()
}//end class
