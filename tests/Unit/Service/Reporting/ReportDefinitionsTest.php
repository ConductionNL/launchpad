<?php

/**
 * ReportDefinitions Test: the shipped file passes the shape, and each broken definition is refused naming the field.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Reporting
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace Unit\Service\Reporting;

use InvalidArgumentException;
use OCA\LaunchPad\Service\Reporting\ReportDefinitions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReportDefinitionsTest extends TestCase {

	public function testTheShippedDefinitionsPassAndNameWhatTheyRead(): void {
		$definitions = new ReportDefinitions();
		$ids = array_column($definitions->all(), 'id');

		$this->assertSame(['open-cases-by-handler', 'cases-per-area', 'dashboards-by-type'], $ids);
		$area = $definitions->find(id: 'cases-per-area');
		$this->assertSame('area', $area['aggregation']['kind']);
		$this->assertSame(['registers' => ['dossiq'], 'schemas' => ['dossiq/case']], $area['requires']);
		$this->assertSame([['name' => 'from', 'type' => 'date', 'default' => null], ['name' => 'until', 'type' => 'date', 'default' => null]], $area['parameters']);
		$this->assertNull($definitions->find(id: 'no-such-report'));
	}//end testTheShippedDefinitionsPassAndNameWhatTheyRead()

	/**
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function brokenDefinitions(): array {
		$good = [
			'id' => 'r', 'title' => 'T', 'description' => 'D', 'source' => ['register' => 'reg', 'schema' => 'sch'],
			'aggregation' => ['kind' => 'count-by', 'property' => 'p'], 'parameters' => [],
		];

		return [
			'not an object'      => ['text', 'must be an object'],
			'no id'              => [array_diff_key($good, ['id' => true]), 'id must be a non-empty string'],
			'no source'          => [array_diff_key($good, ['source' => true]), 'source must name a register and a schema'],
			'blank schema'       => [['source' => ['register' => 'reg', 'schema' => ' ']] + $good, 'schema must be a non-empty string'],
			'unknown kind'       => [['aggregation' => ['kind' => 'sum', 'property' => 'p']] + $good, 'aggregation.kind must be one of count-by, area'],
			'no property'        => [['aggregation' => ['kind' => 'area']] + $good, 'property must be a non-empty string'],
			'parameters as map'  => [['parameters' => ['a' => 1]] + $good, 'parameters must be a list'],
			'untyped parameter'  => [['parameters' => [['name' => 'x']]] + $good, 'every parameter needs a type'],
			'filters not object' => [['filters' => 'x'] + $good, 'filters must be an object'],
		];
	}//end brokenDefinitions()

	#[DataProvider('brokenDefinitions')]
	public function testABrokenDefinitionIsRefusedNamingTheField(mixed $definition, string $message): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);

		(new ReportDefinitions())->check(definition: $definition);
	}//end testABrokenDefinitionIsRefusedNamingTheField()

	public function testAMissingFileAndADuplicateIdAreRefused(): void {
		$file = tempnam(sys_get_temp_dir(), 'reports');
		$one = ['id' => 'r', 'title' => 'T', 'description' => 'D', 'source' => ['register' => 'a', 'schema' => 'b'], 'aggregation' => ['kind' => 'area', 'property' => 'p']];
		file_put_contents($file, json_encode(['reports' => [$one, $one]]));

		try {
			(new ReportDefinitions(file: $file))->all();
			$this->fail('a duplicate id must be refused');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('Report r is defined twice.', $e->getMessage());
		} finally {
			unlink($file);
		}

		$this->expectExceptionMessage('The report definitions file is missing or not valid JSON.');
		(new ReportDefinitions(file: $file))->all();
	}//end testAMissingFileAndADuplicateIdAreRefused()
}//end class
