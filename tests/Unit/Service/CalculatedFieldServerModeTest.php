<?php

/**
 * CalculatedFieldServerModeTest
 *
 * Server-mode calculated fields on OpenRegister (REQ-CALC-004, REQ-CALC-007):
 * saving a widget writes one read-only calculated property per server-mode
 * field on its source schema, a changed expression replaces it, a field set
 * back to client mode or a deleted widget removes it, and a field
 * OpenRegister cannot evaluate, or a schema it cannot write, downgrades to
 * client mode with a warning (widget-calculated-fields, tasks 26-28, 39).
 *
 * @category  Test
 * @package   Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-39
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldAstChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCalculationTranslator;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleDetector;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldEvaluator;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldMaterialiser;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldServerFields;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldSources;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldTypeChecker;
use OCA\LaunchPad\Service\CalculatedFieldService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Materialisation of server-mode fields on the source schema.
 */
#[CoversClass(CalculatedFieldService::class)]
#[CoversClass(CalculatedFieldMaterialiser::class)]
#[CoversClass(CalculatedFieldServerFields::class)]
#[UsesClass(CalculatedFieldCalculationTranslator::class)]
#[UsesClass(CalculatedFieldAstChecker::class)]
#[UsesClass(CalculatedFieldTypeChecker::class)]
#[UsesClass(CalculatedFieldCycleDetector::class)]
#[UsesClass(CalculatedFieldEvaluator::class)]
#[UsesClass(CalculatedFieldSources::class)]
class CalculatedFieldServerModeTest extends TestCase {

	/**
	 * The fake OpenRegister schema mapper.
	 *
	 * @var object
	 */
	private object $mapper;

	/**
	 * Set up a schema `orders` with one property of its own.
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
			public array $schemas = ['orders' => ['revenue' => ['type' => 'number']]];

			/**
			 * Every schema id written.
			 *
			 * @var list<string>
			 */
			public array $updates = [];

			/**
			 * Whether update() refuses.
			 *
			 * @var bool
			 */
			public bool $refuse = false;

			/**
			 * Whether find() refuses, as OpenRegister does for a schema the caller may not read.
			 *
			 * @var bool
			 */
			public bool $refuseRead = false;

			/**
			 * The access flags of every find() call.
			 *
			 * @var list<array{rbac: bool, multitenancy: bool}>
			 */
			public array $finds = [];

			/**
			 * Find a schema.
			 *
			 * @param string|int $id            The id.
			 * @param bool       $_rbac         Whether RBAC applies.
			 * @param bool       $_multitenancy Whether multitenancy applies.
			 *
			 * @return object
			 */
			public function find(string|int $id, bool $_rbac=false, bool $_multitenancy=false): object {
				$this->finds[] = ['rbac' => $_rbac, 'multitenancy' => $_multitenancy];
				if ($this->refuseRead === true) {
					throw new RuntimeException('Access denied');
				}

				if (isset($this->schemas[(string) $id]) === false) {
					throw new RuntimeException('not found');
				}

				return new class ((string) $id, $this->schemas[(string) $id]) {
					/**
					 * Constructor.
					 *
					 * @param string               $id         The id.
					 * @param array<string,mixed>  $properties The properties.
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
				$this->updates[] = $schema->id;
				return $schema;
			}
		};
	}//end setUp()

	/**
	 * The service with a materialiser over the fake mapper, or without OpenRegister.
	 *
	 * @param bool $available Whether OpenRegister resolves.
	 *
	 * @return CalculatedFieldService
	 */
	private function service(bool $available=true): CalculatedFieldService {
		$container = $this->createMock(ContainerInterface::class);
		if ($available === true) {
			$container->method('get')->with('OCA\\OpenRegister\\Db\\SchemaMapper')->willReturn($this->mapper);
		} else {
			$container->method('get')->willThrowException(new RuntimeException('no OpenRegister'));
		}

		return new CalculatedFieldService(
			astChecker: new CalculatedFieldAstChecker(),
			typeChecker: new CalculatedFieldTypeChecker(),
			cycleDetector: new CalculatedFieldCycleDetector(),
			evaluator: new CalculatedFieldEvaluator(),
			materialiser: new CalculatedFieldMaterialiser(translator: new CalculatedFieldCalculationTranslator(), container: $container)
		);
	}//end service()

	/**
	 * A field.
	 *
	 * @param string              $name The name.
	 * @param string              $mode The evaluation mode.
	 * @param array<string,mixed> $ast  The AST.
	 * @param string              $type The return type.
	 *
	 * @return array<string,mixed>
	 */
	private static function field(string $name, string $mode, array $ast, string $type='number'): array {
		return [
			'name'               => $name,
			'label'              => ucfirst($name),
			'expression'         => $name.' expression',
			'compiledExpression' => $ast,
			'returnType'         => $type,
			'evaluationMode'     => $mode,
		];
	}//end field()

	/**
	 * `revenue - cost`.
	 *
	 * @return array<string,mixed>
	 */
	private static function profit(): array {
		return [
			'type'  => 'binary',
			'op'    => '-',
			'left'  => ['type' => 'fieldRef', 'source' => 'self', 'path' => 'revenue'],
			'right' => ['type' => 'fieldRef', 'source' => 'self', 'path' => 'cost'],
		];
	}//end profit()

	/**
	 * Plan, then materialise, as a widget save does.
	 *
	 * @param CalculatedFieldService    $service  The service.
	 * @param list<array<string,mixed>> $fields   The fields.
	 * @param array<string,mixed>       $previous The widget before the save.
	 * @param string                    $source   The widget's source.
	 *
	 * @return array{fields: list<array<string,mixed>>, warnings: list<array<string,string>>, changed: bool, planWarnings: list<array<string,string>>}
	 */
	private function save(CalculatedFieldService $service, array $fields, array $previous=[], string $source='reg/orders'): array {
		$plan    = $service->planServerFields(id: '7', fields: $fields);
		$written = $service->materializeForOpenRegister(
			widget: ['id' => '7', 'calculatedFields' => $plan['fields'], 'source' => $source],
			previous: $previous
		);
		$written['planWarnings'] = $plan['warnings'];
		return $written;
	}//end save()

	/**
	 * A server-mode field becomes a read-only calculated property, its id populated.
	 *
	 * @return void
	 */
	public function testAServerFieldIsCreatedOnTheSourceSchema(): void {
		$result = $this->save(service: $this->service(), fields: [self::field('profit', 'server', self::profit())]);

		$this->assertSame('launchpad_7__profit', $result['fields'][0]['serverComputedFieldId']);
		$this->assertSame('server', $result['fields'][0]['evaluationMode']);
		$this->assertFalse($result['changed']);
		$this->assertSame([], $result['warnings']);
		$this->assertSame(['orders'], $this->mapper->updates);
		$this->assertSame(['type' => 'number'], $this->mapper->schemas['orders']['revenue']);
		$this->assertSame(
			[
				'type'        => 'number',
				'title'       => 'Profit',
				'description' => 'Calculated by LaunchPad widget 7: profit expression',
				'readOnly'    => true,
				'calculation' => ['type' => 'number', 'expression' => ['-' => [['prop' => 'revenue'], ['prop' => 'cost']]]],
			],
			$this->mapper->schemas['orders']['launchpad_7__profit']
		);
	}//end testAServerFieldIsCreatedOnTheSourceSchema()

	/**
	 * A client field is not materialised, and a `both` field is.
	 *
	 * @return void
	 */
	public function testOnlyServerAndBothFieldsAreMaterialised(): void {
		$result = $this->save(
			service: $this->service(),
			fields: [self::field('a', 'client', self::profit()), self::field('b', 'both', self::profit())]
		);

		$this->assertNull($result['fields'][0]['serverComputedFieldId']);
		$this->assertSame('launchpad_7__b', $result['fields'][1]['serverComputedFieldId']);
		$this->assertArrayNotHasKey('launchpad_7__a', $this->mapper->schemas['orders']);
		$this->assertArrayHasKey('launchpad_7__b', $this->mapper->schemas['orders']);
	}//end testOnlyServerAndBothFieldsAreMaterialised()

	/**
	 * A changed expression replaces the property; an unchanged save writes nothing.
	 *
	 * @return void
	 */
	public function testAChangedExpressionUpdatesTheProperty(): void {
		$service = $this->service();
		$first   = $this->save(service: $service, fields: [self::field('profit', 'server', self::profit())]);
		$previous = ['source' => 'reg/orders', 'calculatedFields' => $first['fields']];

		$this->save(service: $service, fields: $first['fields'], previous: $previous);
		$this->assertSame(['orders'], $this->mapper->updates);

		$scaled = ['type' => 'binary', 'op' => '*', 'left' => self::profit(), 'right' => ['type' => 'literal', 'valueType' => 'number', 'value' => 0.9]];
		$this->save(service: $service, fields: [self::field('profit', 'server', $scaled)], previous: $previous);

		$this->assertSame(['orders', 'orders'], $this->mapper->updates);
		$this->assertSame(
			['*' => [['-' => [['prop' => 'revenue'], ['prop' => 'cost']]], ['lit' => 0.9]]],
			$this->mapper->schemas['orders']['launchpad_7__profit']['calculation']['expression']
		);
	}//end testAChangedExpressionUpdatesTheProperty()

	/**
	 * A field set back to client mode leaves the schema; other widgets' properties stay.
	 *
	 * @return void
	 */
	public function testAFieldBackToClientIsRemoved(): void {
		$this->mapper->schemas['orders']['launchpad_8__other'] = ['type' => 'number'];
		$service  = $this->service();
		$first    = $this->save(service: $service, fields: [self::field('profit', 'server', self::profit())]);

		$result = $this->save(
			service: $service,
			fields: [self::field('profit', 'client', self::profit())],
			previous: ['source' => 'reg/orders', 'calculatedFields' => $first['fields']]
		);

		$this->assertNull($result['fields'][0]['serverComputedFieldId']);
		$this->assertArrayNotHasKey('launchpad_7__profit', $this->mapper->schemas['orders']);
		$this->assertArrayHasKey('launchpad_8__other', $this->mapper->schemas['orders']);
		$this->assertArrayHasKey('revenue', $this->mapper->schemas['orders']);
	}//end testAFieldBackToClientIsRemoved()

	/**
	 * A widget without server fields, before or now, never touches OpenRegister.
	 *
	 * @return void
	 */
	public function testClientOnlyWidgetsWriteNothing(): void {
		$this->save(service: $this->service(), fields: [self::field('profit', 'client', self::profit())]);
		$this->assertSame([], $this->mapper->updates);
	}//end testClientOnlyWidgetsWriteNothing()

	/**
	 * A source change moves the properties to the new schema.
	 *
	 * @return void
	 */
	public function testASourceChangeMovesTheProperties(): void {
		$this->mapper->schemas['invoices'] = [];
		$service = $this->service();
		$first   = $this->save(service: $service, fields: [self::field('profit', 'server', self::profit())]);

		$this->save(
			service: $service,
			fields: $first['fields'],
			previous: ['source' => 'reg/orders', 'calculatedFields' => $first['fields']],
			source: 'reg/invoices'
		);

		$this->assertArrayNotHasKey('launchpad_7__profit', $this->mapper->schemas['orders']);
		$this->assertArrayHasKey('launchpad_7__profit', $this->mapper->schemas['invoices']);
	}//end testASourceChangeMovesTheProperties()

	/**
	 * Dependencies are declared before the fields that read them, and read by key.
	 *
	 * @return void
	 */
	public function testDependentFieldsAreOrderedAndReadByKey(): void {
		$margin = [
			'type'  => 'binary',
			'op'    => '/',
			'left'  => ['type' => 'calculatedRef', 'name' => 'profit'],
			'right' => ['type' => 'fieldRef', 'source' => 'self', 'path' => 'revenue'],
		];
		$this->save(
			service: $this->service(),
			fields: [self::field('margin', 'server', $margin), self::field('profit', 'server', self::profit())]
		);

		$keys = array_keys($this->mapper->schemas['orders']);
		$this->assertSame(['revenue', 'launchpad_7__profit', 'launchpad_7__margin'], $keys);
		$this->assertSame(
			['/' => [['prop' => 'launchpad_7__profit'], ['prop' => 'revenue']]],
			$this->mapper->schemas['orders']['launchpad_7__margin']['calculation']['expression']
		);
	}//end testDependentFieldsAreOrderedAndReadByKey()

	/**
	 * An expression OpenRegister cannot evaluate downgrades, and so does a field reading it.
	 *
	 * @return void
	 */
	public function testAnUnsupportedExpressionDowngradesWithItsDependents(): void {
		$upper = ['type' => 'call', 'name' => 'upper', 'args' => [['type' => 'fieldRef', 'source' => 'self', 'path' => 'name']]];
		$reads = ['type' => 'call', 'name' => 'coalesce', 'args' => [['type' => 'calculatedRef', 'name' => 'shout'], ['type' => 'literal', 'valueType' => 'string', 'value' => '-']]];
		$result = $this->save(
			service: $this->service(),
			fields: [
				self::field('shout', 'server', $upper, 'string'),
				self::field('label', 'both', $reads, 'string'),
				self::field('profit', 'server', self::profit()),
			]
		);

		$this->assertSame(['client', 'client', 'server'], array_column($result['fields'], 'evaluationMode'));
		$this->assertSame(['shout', 'label'], array_column($result['planWarnings'], 'field'));
		$this->assertSame('downgraded_evaluation_mode', $result['planWarnings'][1]['warning']);
		$this->assertStringContainsString("from 'both' to 'client'", $result['planWarnings'][1]['reason']);
		$this->assertSame(['revenue', 'launchpad_7__profit'], array_keys($this->mapper->schemas['orders']));
	}//end testAnUnsupportedExpressionDowngradesWithItsDependents()

	/**
	 * A return type OpenRegister cannot declare downgrades.
	 *
	 * @return void
	 */
	public function testAnUndeclarableTypeDowngrades(): void {
		$result = $this->save(service: $this->service(), fields: [self::field('span', 'server', self::profit(), 'duration')]);
		$this->assertSame('client', $result['fields'][0]['evaluationMode']);
		$this->assertSame([], $this->mapper->updates);
	}//end testAnUndeclarableTypeDowngrades()

	/**
	 * A schema OpenRegister refuses to write downgrades every server field.
	 *
	 * @return void
	 */
	public function testARefusedSchemaWriteDowngrades(): void {
		$this->mapper->refuse = true;
		$result = $this->save(service: $this->service(), fields: [self::field('profit', 'server', self::profit())]);

		$this->assertTrue($result['changed']);
		$this->assertSame('client', $result['fields'][0]['evaluationMode']);
		$this->assertNull($result['fields'][0]['serverComputedFieldId']);
		$this->assertSame('profit', $result['warnings'][0]['field']);
		$this->assertStringContainsString('could not be updated', $result['warnings'][0]['reason']);
	}//end testARefusedSchemaWriteDowngrades()

	/**
	 * The schema is read with OpenRegister's access checks on, so the write
	 * runs only for a caller OpenRegister lets read and update the schema.
	 *
	 * @return void
	 */
	public function testTheSchemaIsReadWithAccessChecksOn(): void {
		$this->save(service: $this->service(), fields: [self::field('profit', 'server', self::profit())]);

		$this->assertNotSame([], $this->mapper->finds);
		foreach ($this->mapper->finds as $flags) {
			$this->assertSame(['rbac' => true, 'multitenancy' => true], $flags);
		}

		$this->assertSame(['orders'], $this->mapper->updates);
	}//end testTheSchemaIsReadWithAccessChecksOn()

	/**
	 * A schema the caller may not read is never written; the field stays client-side.
	 *
	 * @return void
	 */
	public function testARefusedSchemaReadWritesNothing(): void {
		$this->mapper->refuseRead = true;
		$result = $this->save(service: $this->service(), fields: [self::field('profit', 'server', self::profit())]);

		$this->assertSame([], $this->mapper->updates);
		$this->assertSame(['revenue' => ['type' => 'number']], $this->mapper->schemas['orders']);
		$this->assertSame('client', $result['fields'][0]['evaluationMode']);
		$this->assertNull($result['fields'][0]['serverComputedFieldId']);
	}//end testARefusedSchemaReadWritesNothing()

	/**
	 * Without OpenRegister a server field is planned as a client field.
	 *
	 * @return void
	 */
	public function testWithoutOpenRegisterTheFieldIsPlannedClient(): void {
		$plan = $this->service(available: false)->planServerFields(id: '7', fields: [self::field('profit', 'server', self::profit())]);
		$this->assertSame('client', $plan['fields'][0]['evaluationMode']);
		$this->assertStringContainsString('OpenRegister is unavailable', $plan['warnings'][0]['reason']);
	}//end testWithoutOpenRegisterTheFieldIsPlannedClient()

	/**
	 * Deleting a widget removes every property it owns and nothing else.
	 *
	 * @return void
	 */
	public function testCleanupRemovesTheWidgetsProperties(): void {
		$service = $this->service();
		$this->save(service: $service, fields: [self::field('a', 'server', self::profit()), self::field('b', 'server', self::profit())]);
		$this->mapper->schemas['orders']['launchpad_70__x'] = ['type' => 'number'];

		$this->assertSame([], $service->cleanupServerFields(widget: ['id' => '7', 'source' => 'reg/orders']));
		$this->assertSame(['revenue', 'launchpad_70__x'], array_keys($this->mapper->schemas['orders']));
	}//end testCleanupRemovesTheWidgetsProperties()

	/**
	 * A cleanup OpenRegister refuses is reported, not thrown.
	 *
	 * @return void
	 */
	public function testARefusedCleanupWarns(): void {
		$service = $this->service();
		$this->save(service: $service, fields: [self::field('a', 'server', self::profit())]);
		$this->mapper->refuse = true;

		$warnings = $service->cleanupServerFields(widget: ['id' => '7', 'source' => 'reg/orders']);
		$this->assertSame('server_fields_not_removed', $warnings[0]['warning']);
		$this->assertSame([], $service->cleanupServerFields(widget: ['id' => '7', 'source' => '']));
	}//end testARefusedCleanupWarns()

	/**
	 * Whether stored fields hold a materialised field.
	 *
	 * @return void
	 */
	public function testHasServerFields(): void {
		$service = $this->service();
		$this->assertTrue($service->hasServerFields(fields: [['serverComputedFieldId' => 'launchpad_7__a']]));
		$this->assertFalse($service->hasServerFields(fields: [['serverComputedFieldId' => null], 'x']));
		$this->assertFalse($service->hasServerFields(fields: 'x'));
	}//end testHasServerFields()

	/**
	 * Keys are derived from the widget and field and made key-safe.
	 *
	 * @return void
	 */
	public function testKeys(): void {
		$this->assertSame('launchpad_7__net_revenue', CalculatedFieldMaterialiser::fieldKey(widgetId: '7', name: 'net-revenue'));
		$this->assertSame('orders', (new CalculatedFieldServerFields())->schemaOf(sourceKey: 'reg/orders'));
		$this->assertSame('', (new CalculatedFieldServerFields())->schemaOf(sourceKey: ''));
	}//end testKeys()
}//end class
