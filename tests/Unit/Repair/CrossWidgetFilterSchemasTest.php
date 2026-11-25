<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The filter-event schema of drill-down-cross-widget-filter, as the register
 * import hands it to OpenRegister.
 *
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-010-telemetry-of-filter-usage-is-opt-in-and-aggregated
 */

namespace Unit\Repair;

use OCA\LaunchPad\Repair\ImportLaunchpadRegister;
use OCP\App\IAppManager;
use OCP\Migration\IOutput;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs the real repair step over the shipped descriptor and fragments, and
 * checks the spec's seed rows against the schemas it imports.
 */
#[CoversClass(ImportLaunchpadRegister::class)]
class CrossWidgetFilterSchemasTest extends TestCase {

	/**
	 * What the import handed to OpenRegister: the descriptor and its version.
	 *
	 * @var array{0: array<string, mixed>, 1: string}|null
	 */
	private ?array $imported = null;

	protected function setUp(): void {
		$importer = new class {
			/** @var list<array{0: array, 1: string}> */
			public array $runs = [];

			public function importFromApp(string $appId, array $data, string $version, bool $force): void {
				$this->runs[] = [$data, $version];
			}
		};
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($importer);
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getAppPath')->willReturn(dirname(__DIR__, 3));

		(new ImportLaunchpadRegister(container: $container, appManager: $apps, logger: $this->createMock(LoggerInterface::class)))
			->run($this->createMock(IOutput::class));

		$this->assertCount(1, $importer->runs);
		$this->imported = $importer->runs[0];
	}

	/**
	 * A schema of the imported descriptor, as JSON Schema text.
	 *
	 * @param string $slug The schema.
	 *
	 * @return string
	 */
	private function schema(string $slug): string {
		return (string)json_encode($this->imported[0]['components']['schemas'][$slug]);
	}

	/**
	 * Whether a row validates against a schema.
	 *
	 * @param array<string, mixed> $row  The row.
	 * @param string               $slug The schema.
	 *
	 * @return bool
	 */
	private function valid(array $row, string $slug): bool {
		return (new Validator())->validate(json_decode((string)json_encode($row)), $this->schema($slug))->isValid();
	}

	/**
	 * The register lists filter_event and no longer ships a saved_view schema.
	 *
	 * @return void
	 */
	public function testTheFilterEventSchemaJoinsTheRegisterAndNoSavedViewSchemaShips(): void {
		[$data, $version] = $this->imported;

		$this->assertSame(expected: '1.0.8', actual: $version, message: 'existing installs only take new schemas on a version change');
		$this->assertSame(
			expected: [
				'dashboard', 'scheduled_export', 'render_target', 'recipient', 'scheduled_export_run', 'status_report',
				'filter_event', 'embed_token', 'tenant_theme', 'embed_usage_event', 'signing_key',
			],
			actual: $data['components']['registers']['launchpad']['schemas']
		);
		$this->assertArrayNotHasKey(key: 'saved_view', array: $data['components']['schemas']);
		$schema = $data['components']['schemas']['filter_event'];
		$this->assertSame(expected: 'filter_event', actual: $schema['slug']);
		$this->assertSame(expected: '1.0.0', actual: $schema['version']);
		foreach ($schema['properties'] as $name => $property) {
			$this->assertNotSame(expected: '', actual: $property['title'] ?? '', message: 'filter_event.' . $name . ' has a title');
			$this->assertNotSame(expected: '', actual: $property['description'] ?? '', message: 'filter_event.' . $name . ' has a description');
		}
	}

	public function testAFilterEventHoldsDimensionsAndOperatorsButNeverAValue(): void {
		$event = [
			'dashboardId' => 'd1a2b3c4-5678-90ab-cdef-1234567890ab',
			'userId' => 'u-caseworker-01',
			'widgetId' => 'w-chart-gemeenten',
			'eventType' => 'apply',
			'filterDelta' => [['dimension' => 'gemeenteCode', 'operator' => 'eq', 'source' => 'widgetClick', 'sourceWidgetId' => 'w-chart-gemeenten']],
			'timestamp' => '2026-05-22T14:35:00Z',
		];
		$this->assertTrue($this->valid($event, 'filter_event'));
		$this->assertTrue($this->valid(['eventType' => 'reset', 'filterDelta' => []] + $event, 'filter_event'));

		$withValue = $event;
		$withValue['filterDelta'][0]['value'] = 'GM0355';
		$this->assertFalse($this->valid($withValue, 'filter_event'), 'a filter value is never stored in telemetry');
		$this->assertFalse($this->valid(['eventType' => 'click'] + $event, 'filter_event'));
		$this->assertArrayNotHasKey('value', $this->imported[0]['components']['schemas']['filter_event']['properties']['filterDelta']['items']['properties']);
	}
}
