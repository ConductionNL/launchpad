<?php

/**
 * WidgetApiControllerCalculatedFieldsTest
 *
 * A placement update that carries calculated fields is validated against the
 * dashboard before anything is saved: a field that does not check answers 400
 * with the editor's error envelope and the placement is not updated; a valid
 * one is stored with its `returnType` (widget-calculated-fields, tasks 24-25).
 *
 * @category  Test
 * @package   Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-25
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Controller\RequestDataExtractor;
use OCA\LaunchPad\Controller\ResponseHelper;
use OCA\LaunchPad\Controller\WidgetApiController;
use OCA\LaunchPad\Controller\WidgetApiPolicy;
use OCA\LaunchPad\Controller\WidgetApiSources;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldAstChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleDetector;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleError;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldEvaluator;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldTypeChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldValidationError;
use OCA\LaunchPad\Service\CalculatedFieldService;
use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetPlacementService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Save-time validation of calculated fields on the placement update endpoint.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the controller constructor.
 */
#[CoversClass(WidgetApiController::class)]
#[CoversClass(WidgetApiSources::class)]
#[UsesClass(\OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldServerFields::class)]
#[UsesClass(RequestActor::class)]
#[UsesClass(RequestDataExtractor::class)]
#[UsesClass(ResponseHelper::class)]
#[UsesClass(WidgetApiPolicy::class)]
#[UsesClass(WidgetPlacement::class)]
#[UsesClass(CalculatedFieldService::class)]
#[UsesClass(CalculatedFieldAstChecker::class)]
#[UsesClass(CalculatedFieldTypeChecker::class)]
#[UsesClass(CalculatedFieldCycleDetector::class)]
#[UsesClass(CalculatedFieldCycleError::class)]
#[UsesClass(CalculatedFieldEvaluator::class)]
#[UsesClass(\OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldSources::class)]
#[UsesClass(CalculatedFieldValidationError::class)]
class WidgetApiControllerCalculatedFieldsTest extends TestCase {

	/**
	 * The request parameters.
	 *
	 * @var array<string,mixed>
	 */
	private array $params = [];

	/**
	 * The widget service.
	 *
	 * @var WidgetService&MockObject
	 */
	private WidgetService&MockObject $widgetService;

	/**
	 * Set up the widget service with a dashboard of two placements.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->widgetService = $this->createMock(WidgetService::class);
		$this->widgetService->method('getPlacement')->willReturn($this->placement(id: 9, fields: []));
		$this->widgetService->method('getDashboardPlacements')->with(4)->willReturn(
			[
				$this->placement(id: 9, fields: []),
				$this->placement(
					id: 10,
					fields: [
						[
							'name'               => 'target',
							'compiledExpression' => ['type' => 'fieldRef', 'source' => 'widget:9', 'path' => 'revenue'],
						],
					]
				),
			]
		);
	}//end setUp()

	/**
	 * A placement of dashboard 4.
	 *
	 * @param int                       $id     The placement id.
	 * @param list<array<string,mixed>> $fields Its stored calculated fields.
	 *
	 * @return WidgetPlacement
	 */
	private function placement(int $id, array $fields): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setDashboardId(4);
		$placement->setWidgetId('w');
		$placement->setCalculatedFieldsArray(fields: $fields);

		return $placement;
	}//end placement()

	/**
	 * The controller, allowed to style the placement.
	 *
	 * @return WidgetApiController
	 */
	private function controller(): WidgetApiController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key, $default=null) => $this->params[$key] ?? $default);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($this->createMock(IUser::class));
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canStyleWidget')->willReturn(true);

		return new WidgetApiController(
			$request,
			new WidgetApiSources(
				$this->widgetService,
				$this->createMock(NewsWidgetService::class),
				$this->createMock(CalendarWidgetService::class),
				$this->createMock(WidgetPlacementService::class)
			),
			$permissions,
			new WidgetApiPolicy($this->createMock(RoleFeaturePermissionService::class), $this->createMock(LoggerInterface::class)),
			new RequestActor($session, $this->createMock(ActionAuthService::class)),
			'alice',
			responseHelper: new ResponseHelper(logger: new NullLogger()),
		);
	}//end controller()

	/**
	 * A field that does not type-check answers 400 with its envelope and saves nothing.
	 *
	 * @return void
	 */
	public function testAMistypedFieldAnswers400AndSavesNothing(): void {
		$this->params = [
			'calculatedFields' => [
				[
					'name'               => 'bad',
					'expression'         => 'revenue + "100"',
					'compiledExpression' => [
						'type'  => 'binary',
						'op'    => '+',
						'left'  => ['type' => 'literal', 'valueType' => 'number', 'value' => 1],
						'right' => ['type' => 'literal', 'valueType' => 'string', 'value' => '100'],
					],
				],
			],
		];
		$this->widgetService->expects($this->never())->method('updatePlacement');

		$response = $this->controller()->updatePlacement(placementId: 9);

		self::assertSame(400, $response->getStatus());
		self::assertSame(
			[
				'error'    => 'typeError',
				'message'  => "Cannot apply '+' to 1 (number) and \"100\" (string)",
				'operands' => ['1', '"100"'],
				'field'    => 'bad',
			],
			$response->getData()
		);
	}//end testAMistypedFieldAnswers400AndSavesNothing()

	/**
	 * An unknown function answers 400 naming it.
	 *
	 * @return void
	 */
	public function testAnUnknownFunctionAnswers400(): void {
		$this->params = [
			'calculatedFields' => [['name' => 'x', 'compiledExpression' => ['type' => 'call', 'name' => 'eval', 'args' => []]]],
		];
		$this->widgetService->expects($this->never())->method('updatePlacement');

		$response = $this->controller()->updatePlacement(placementId: 9);

		self::assertSame(400, $response->getStatus());
		self::assertSame(['unknownFunction', 'eval', 'x'], [$response->getData()['error'], $response->getData()['name'], $response->getData()['field']]);
	}//end testAnUnknownFunctionAnswers400()

	/**
	 * A field that reads a neighbour which reads it back answers 400 as a cycle.
	 *
	 * @return void
	 */
	public function testACycleWithANeighbourAnswers400(): void {
		$this->params = [
			'calculatedFields' => [
				['name' => 'calc1', 'compiledExpression' => ['type' => 'fieldRef', 'source' => 'widget:10', 'path' => 'target']],
			],
		];
		$this->widgetService->expects($this->never())->method('updatePlacement');

		$response = $this->controller()->updatePlacement(placementId: 9);

		self::assertSame(400, $response->getStatus());
		self::assertSame('cycleDetected', $response->getData()['error']);
		self::assertEqualsCanonicalizing(['9', '10'], $response->getData()['widgets']);
	}//end testACycleWithANeighbourAnswers400()

	/**
	 * Valid fields are stored with their return type, other placement data untouched.
	 *
	 * @return void
	 */
	public function testValidFieldsAreStoredWithTheirReturnType(): void {
		$this->params = [
			'customTitle'      => 'Sales',
			'calculatedFields' => [
				[
					'name'               => 'label',
					'compiledExpression' => [
						'type' => 'if',
						'cond' => ['type' => 'literal', 'valueType' => 'boolean', 'value' => true],
						'then' => ['type' => 'literal', 'valueType' => 'string', 'value' => 'High'],
						'else' => ['type' => 'literal', 'valueType' => 'null', 'value' => null],
					],
				],
			],
		];
		$saved = null;
		$this->widgetService->expects($this->once())->method('updatePlacement')->willReturnCallback(
			function (int $placementId, array $data) use (&$saved): WidgetPlacement {
				$saved = [$placementId, $data];
				return $this->placement(id: 9, fields: $data['calculatedFields']);
			}
		);

		$response = $this->controller()->updatePlacement(placementId: 9);

		self::assertSame(200, $response->getStatus());
		self::assertSame(9, $saved[0]);
		self::assertSame('Sales', $saved[1]['customTitle']);
		self::assertSame('string', $saved[1]['calculatedFields'][0]['returnType']);
	}//end testValidFieldsAreStoredWithTheirReturnType()

	/**
	 * A server-mode field that reads a widget of another register is stored as client
	 * mode and the response carries the warning (REQ-CALC-007).
	 *
	 * @return void
	 */
	public function testADowngradedFieldIsReportedInTheResponse(): void {
		$this->params = [
			'content'          => ['register' => 'sales', 'schema' => 'deals'],
			'calculatedFields' => [
				[
					'name'               => 'ratio',
					'evaluationMode'     => 'server',
					'compiledExpression' => ['type' => 'fieldRef', 'source' => 'widget:11', 'path' => 'x'],
				],
			],
		];
		$saved = [];
		$this->widgetService->method('updatePlacement')->willReturnCallback(
			function (int $placementId, array $data) use (&$saved): WidgetPlacement {
				$saved = $data;
				return $this->placement(id: 9, fields: $data['calculatedFields']);
			}
		);

		$response = $this->controller()->updatePlacement(placementId: 9);

		self::assertSame(200, $response->getStatus());
		self::assertSame('client', $saved['calculatedFields'][0]['evaluationMode']);
		self::assertSame('downgraded_evaluation_mode', $response->getData()['warnings'][0]['warning']);
		self::assertSame('ratio', $response->getData()['warnings'][0]['field']);
		self::assertArrayNotHasKey('warnings', $this->placement(id: 9, fields: [])->jsonSerialize());
	}//end testADowngradedFieldIsReportedInTheResponse()

	/**
	 * An update without calculated fields does not read the dashboard.
	 *
	 * @return void
	 */
	public function testAnUpdateWithoutCalculatedFieldsSkipsTheCheck(): void {
		$this->params = ['customTitle' => 'Sales'];
		$this->widgetService->expects($this->never())->method('getDashboardPlacements');
		$this->widgetService->expects($this->once())->method('updatePlacement')->willReturn($this->placement(id: 9, fields: []));

		self::assertSame(200, $this->controller()->updatePlacement(placementId: 9)->getStatus());
	}//end testAnUpdateWithoutCalculatedFieldsSkipsTheCheck()
}//end class
