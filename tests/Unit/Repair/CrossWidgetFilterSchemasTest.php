<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The saved-view and filter-event schemas of drill-down-cross-widget-filter,
 * as the register import hands them to OpenRegister.
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-009-saved-views-persist-named-filter-combinations
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

	public function testBothSchemasJoinTheRegisterUnderANewVersion(): void {
		[$data, $version] = $this->imported;

		$this->assertSame('1.0.4', $version, 'existing installs only take new schemas on a version change');
		$this->assertSame(
			['dashboard', 'scheduled_export', 'render_target', 'recipient', 'scheduled_export_run', 'status_report', 'saved_view', 'filter_event'],
			$data['components']['registers']['launchpad']['schemas']
		);
		foreach (['saved_view', 'filter_event'] as $slug) {
			$this->assertSame($slug, $data['components']['schemas'][$slug]['slug']);
			$this->assertSame('1.0.0', $data['components']['schemas'][$slug]['version']);
			foreach ($data['components']['schemas'][$slug]['properties'] as $name => $property) {
				$this->assertNotSame('', $property['title'] ?? '', $slug . '.' . $name . ' has a title');
				$this->assertNotSame('', $property['description'] ?? '', $slug . '.' . $name . ' has a description');
			}
		}
	}

	public function testTheSpecsSavedViewsAreValidAndAViewNeedsItsDashboardNameAndFilters(): void {
		$view = [
			'dashboardId' => 'd1a2b3c4-5678-90ab-cdef-1234567890ab',
			'name' => 'Open bezwaren Zeist > 6w',
			'description' => 'All open complaints in Zeist older than 6 weeks',
			'filters' => [
				['dimension' => 'gemeenteCode', 'operator' => 'eq', 'value' => 'GM0355', 'source' => 'savedView'],
				['dimension' => 'status', 'operator' => 'eq', 'value' => 'open', 'source' => 'savedView'],
				['dimension' => 'ageInDays', 'operator' => 'gte', 'value' => 42, 'source' => 'savedView'],
				['dimension' => 'period.quarter', 'operator' => 'between', 'value' => ['2026-Q1', '2026-Q2'], 'source' => 'savedView', 'pinned' => true],
			],
			'isShared' => true,
			'sharedWith' => ['team-bezwaar'],
			'createdBy' => 'u-analyst-01',
			'createdAt' => '2026-05-15T10:00:00Z',
		];
		$this->assertTrue($this->valid($view, 'saved_view'));

		foreach (['dashboardId', 'name', 'filters', 'createdBy'] as $field) {
			$without = $view;
			unset($without[$field]);
			$this->assertFalse($this->valid($without, 'saved_view'), 'a view without ' . $field . ' is refused');
		}

		$this->assertFalse($this->valid(['name' => ''] + $view, 'saved_view'), 'an empty name is refused');
		$this->assertFalse($this->valid(['filters' => [['dimension' => 'status', 'operator' => 'like', 'value' => 'o']]] + $view, 'saved_view'));
		$this->assertFalse($this->valid(['filters' => [['dimension' => 'status', 'operator' => 'eq', 'source' => 'guess']]] + $view, 'saved_view'));
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
