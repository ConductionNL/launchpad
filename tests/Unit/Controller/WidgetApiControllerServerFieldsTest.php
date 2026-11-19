<?php

/**
 * WidgetApiControllerServerFieldsTest
 *
 * The placement endpoints keep a widget's server-mode calculated fields on
 * its OpenRegister source schema: an update writes them once the widget is
 * persisted and saves the widget again when a field had to fall back to
 * client mode; a source change re-checks the stored fields; a removal takes
 * the widget's properties off the schema (widget-calculated-fields, tasks
 * 27 and 28).
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
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-27
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
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCalculationTranslator;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleDetector;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldEvaluator;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldMaterialiser;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldServerFields;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldSources;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldTypeChecker;
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
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Materialisation on placement update and removal.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the controller constructor.
 */
#[CoversClass(WidgetApiController::class)]
#[UsesClass(WidgetApiSources::class)]
#[UsesClass(RequestActor::class)]
#[UsesClass(RequestDataExtractor::class)]
#[UsesClass(ResponseHelper::class)]
#[UsesClass(WidgetApiPolicy::class)]
#[UsesClass(WidgetPlacement::class)]
#[UsesClass(CalculatedFieldService::class)]
#[UsesClass(CalculatedFieldMaterialiser::class)]
#[UsesClass(CalculatedFieldServerFields::class)]
#[UsesClass(CalculatedFieldCalculationTranslator::class)]
#[UsesClass(CalculatedFieldAstChecker::class)]
#[UsesClass(CalculatedFieldTypeChecker::class)]
#[UsesClass(CalculatedFieldCycleDetector::class)]
#[UsesClass(CalculatedFieldEvaluator::class)]
#[UsesClass(CalculatedFieldSources::class)]
class WidgetApiControllerServerFieldsTest extends TestCase {

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
	 * The fake OpenRegister schema mapper: `schemas` holds each schema's properties.
	 *
	 * @var object
	 */
	private object $mapper;

	/**
	 * Every placement update, in order.
	 *
	 * @var list<array<string,mixed>>
	 */
	private array $saves = [];

	/**
	 * Set up placement 9 on dashboard 4, backed by `sales/orders`.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->mapper = new class {
			/**
			 * Schema id => properties.
			 *
			 * @var array<string,array<string,mixed>>
			 */
			public array $schemas = ['orders' => ['revenue' => ['type' => 'number']], 'leads' => []];

			/**
			 * Whether update() refuses.
			 *
			 * @var bool
			 */
			public bool $refuse = false;

			/**
			 * Find a schema.
			 *
			 * @param string|int $id            The id.
			 * @param bool       $_rbac         Whether RBAC applies.
			 * @param bool       $_multitenancy Whether multitenancy applies.
			 *
			 * @return object
			 */
			public function find(string|int $id, bool $_rbac=true, bool $_multitenancy=true): object {
				unset($_rbac, $_multitenancy);
				$props = $this->schemas[(string) $id];
				return new class ((string) $id, $props) {
					/**
					 * Constructor.
					 *
					 * @param string              $id         The id.
					 * @param array<string,mixed> $properties The properties.
					 */
					public function __construct(public string $id, public array $properties) {
					}

					/**
					 * The properties.
					 *
					 * @return array<string,mixed>
					 */
					public function getProperties(): array {
						return $this->properties;
					}

					/**
					 * Replace the properties.
					 *
					 * @param array<string,mixed> $properties The properties.
					 *
					 * @return void
					 */
					public function setProperties(array $properties): void {
						$this->properties = $properties;
					}
				};
			}

			/**
			 * Write a schema.
			 *
			 * @param object $schema The schema.
			 *
			 * @return object
			 */
			public function update(object $schema): object {
				if ($this->refuse === true) {
					throw new RuntimeException('forbidden');
				}

				$this->schemas[$schema->id] = $schema->properties;
				return $schema;
			}
		};

		$this->widgetService = $this->createMock(WidgetService::class);
		$this->widgetService->method('updatePlacement')->willReturnCallback(
			function (int $placementId, array $data): WidgetPlacement {
				$this->saves[] = $data;
				return $this->placement(fields: ($data['calculatedFields'] ?? []), source: ($data['content'] ?? ['register' => 'sales', 'schema' => 'orders']));
			}
		);
	}//end setUp()

	/**
	 * Placement 9.
	 *
	 * @param list<array<string,mixed>> $fields Its stored fields.
	 * @param array<string,mixed>       $source Its content.
	 *
	 * @return WidgetPlacement
	 */
	private function placement(array $fields, array $source=['register' => 'sales', 'schema' => 'orders']): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId(9);
		$placement->setDashboardId(4);
		$placement->setWidgetId('w');
		$placement->setContentArray(content: $source);
		$placement->setCalculatedFieldsArray(fields: $fields);

		return $placement;
	}//end placement()

	/**
	 * Placement 9 as stored, alone on its dashboard.
	 *
	 * @param WidgetPlacement $placement The stored placement.
	 *
	 * @return void
	 */
	private function stored(WidgetPlacement $placement): void {
		$this->widgetService->method('getPlacement')->willReturn($placement);
		$this->widgetService->method('getDashboardPlacements')->with(4)->willReturn([$placement]);
	}//end stored()

	/**
	 * A server-mode `revenue - cost` field.
	 *
	 * @param string|null $id Its stored `serverComputedFieldId`.
	 *
	 * @return array<string,mixed>
	 */
	private static function profit(?string $id=null): array {
		$field = [
			'name'               => 'profit',
			'evaluationMode'     => 'server',
			'compiledExpression' => [
				'type'  => 'binary',
				'op'    => '-',
				'left'  => ['type' => 'fieldRef', 'source' => 'self', 'path' => 'revenue'],
				'right' => ['type' => 'fieldRef', 'source' => 'self', 'path' => 'cost'],
			],
		];
		if ($id !== null) {
			$field['returnType']            = 'number';
			$field['serverComputedFieldId'] = $id;
		}

		return $field;
	}//end profit()

	/**
	 * The controller over a real service whose OpenRegister is the fake mapper.
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
		$permissions->method('canRemoveWidget')->willReturn(true);
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($this->mapper);

		return new WidgetApiController(
			$request,
			new WidgetApiSources(
				$this->widgetService,
				$this->createMock(NewsWidgetService::class),
				$this->createMock(CalendarWidgetService::class),
				$this->createMock(WidgetPlacementService::class),
				new CalculatedFieldService(
					astChecker: new CalculatedFieldAstChecker(),
					typeChecker: new CalculatedFieldTypeChecker(),
					cycleDetector: new CalculatedFieldCycleDetector(),
					evaluator: new CalculatedFieldEvaluator(),
					materialiser: new CalculatedFieldMaterialiser(translator: new CalculatedFieldCalculationTranslator(), container: $container)
				)
			),
			$permissions,
			new WidgetApiPolicy($this->createMock(RoleFeaturePermissionService::class), $this->createMock(originalClassName: LoggerInterface::class)),
			new RequestActor($session, $this->createMock(ActionAuthService::class)),
			'alice',
			responseHelper: new ResponseHelper(logger: $this->createMock(originalClassName: LoggerInterface::class)),
		);
	}//end controller()

	/**
	 * Saving a server-mode field stores its id and creates the schema property, saving once.
	 *
	 * @return void
	 */
	public function testAnUpdateMaterialisesTheServerField(): void {
		$this->stored(placement: $this->placement(fields: []));
		$this->params = ['calculatedFields' => [self::profit()]];

		$response = $this->controller()->updatePlacement(placementId: 9);

		self::assertSame(200, $response->getStatus());
		self::assertCount(1, $this->saves);
		self::assertSame('launchpad_9__profit', $this->saves[0]['calculatedFields'][0]['serverComputedFieldId']);
		self::assertSame('launchpad_9__profit', $response->getData()['calculatedFields'][0]['serverComputedFieldId']);
		self::assertSame(
			['-' => [['prop' => 'revenue'], ['prop' => 'cost']]],
			$this->mapper->schemas['orders']['launchpad_9__profit']['calculation']['expression']
		);
		self::assertArrayNotHasKey('warnings', $response->getData());
	}//end testAnUpdateMaterialisesTheServerField()

	/**
	 * A schema OpenRegister refuses to write saves the widget again with the field in client mode.
	 *
	 * @return void
	 */
	public function testARefusedSchemaSavesTheFieldAsClient(): void {
		$this->stored(placement: $this->placement(fields: []));
		$this->mapper->refuse = true;
		$this->params = ['calculatedFields' => [self::profit()]];

		$response = $this->controller()->updatePlacement(placementId: 9);

		self::assertSame(200, $response->getStatus());
		self::assertCount(2, $this->saves);
		self::assertSame(['calculatedFields'], array_keys($this->saves[1]));
		self::assertSame('client', $this->saves[1]['calculatedFields'][0]['evaluationMode']);
		self::assertNull($this->saves[1]['calculatedFields'][0]['serverComputedFieldId']);
		self::assertSame('profit', $response->getData()['warnings'][0]['field']);
		self::assertSame('client', $response->getData()['calculatedFields'][0]['evaluationMode']);
	}//end testARefusedSchemaSavesTheFieldAsClient()

	/**
	 * A source change without new fields re-checks the stored ones and moves their properties.
	 *
	 * @return void
	 */
	public function testASourceChangeMovesTheStoredServerFields(): void {
		$this->mapper->schemas['orders']['launchpad_9__profit'] = ['type' => 'number'];
		$this->stored(placement: $this->placement(fields: [self::profit(id: 'launchpad_9__profit')]));
		$this->params = ['content' => ['register' => 'sales', 'schema' => 'leads']];

		$response = $this->controller()->updatePlacement(placementId: 9);

		self::assertSame(200, $response->getStatus());
		self::assertSame('launchpad_9__profit', $this->saves[0]['calculatedFields'][0]['serverComputedFieldId']);
		self::assertArrayNotHasKey('launchpad_9__profit', $this->mapper->schemas['orders']);
		self::assertArrayHasKey('launchpad_9__profit', $this->mapper->schemas['leads']);
	}//end testASourceChangeMovesTheStoredServerFields()

	/**
	 * Removing the widget removes its properties from the schema.
	 *
	 * @return void
	 */
	public function testARemovalCleansUpTheSchema(): void {
		$this->mapper->schemas['orders']['launchpad_9__profit'] = ['type' => 'number'];
		$this->stored(placement: $this->placement(fields: [self::profit(id: 'launchpad_9__profit')]));
		$this->widgetService->expects($this->once())->method('removePlacement')->with(9);

		$response = $this->controller()->removePlacement(placementId: 9);

		self::assertSame(200, $response->getStatus());
		self::assertSame(['revenue' => ['type' => 'number']], $this->mapper->schemas['orders']);
		self::assertArrayNotHasKey('warnings', $response->getData());
	}//end testARemovalCleansUpTheSchema()

	/**
	 * A cleanup OpenRegister refuses still removes the widget and says so.
	 *
	 * @return void
	 */
	public function testARefusedCleanupStillRemovesTheWidget(): void {
		$this->mapper->refuse = true;
		$this->mapper->schemas['orders']['launchpad_9__profit'] = ['type' => 'number'];
		$this->stored(placement: $this->placement(fields: [self::profit(id: 'launchpad_9__profit')]));
		$this->widgetService->expects($this->once())->method('removePlacement')->with(9);

		$response = $this->controller()->removePlacement(placementId: 9);

		self::assertSame(200, $response->getStatus());
		self::assertSame('server_fields_not_removed', $response->getData()['warnings'][0]['warning']);
	}//end testARefusedCleanupStillRemovesTheWidget()
}//end class
