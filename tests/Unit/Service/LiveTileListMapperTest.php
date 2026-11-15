<?php

/**
 * LiveTileListMapperTest
 * Covers REQ-LTC-001 (display, mapping and limit validation), REQ-LTC-002
 * (only mapped fields, cut to the limit) and REQ-LTC-003 (only https links).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\LiveTileListMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
#[CoversClass(LiveTileListMapper::class)]
class LiveTileListMapperTest extends TestCase {

	/**
	 * A path evaluator with the service's `$.a.b[0]` rules.
	 *
	 * @return callable
	 */
	private function extract(): callable {
		return static function (mixed $item, string $expr): mixed {
			preg_match_all('/\.([A-Za-z0-9_]+)|\[(\d+)\]/', substr($expr, 1), $tokens, PREG_SET_ORDER);
			foreach ($tokens as $token) {
				$key = (($token[2] ?? '') !== '') ? (int)$token[2] : $token[1];
				if (is_array($item) === false || array_key_exists($key, $item) === false) {
					return null;
				}

				$item = $item[$key];
			}

			return $item;
		};
	}//end extract()

	/**
	 * The TOPdesk incident mapping of the spec.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function incidentMapping(): array {
		return [
			['label' => 'Incident', 'path' => 'briefDescription', 'role' => 'title'],
			['label' => 'Open', 'path' => 'url', 'role' => 'link'],
			['label' => 'Reported', 'path' => '$.callDate', 'role' => 'date'],
		];
	}//end incidentMapping()

	public function testAnOldConfigHasNoErrorsAndShowsAValue(): void {
		$mapper = new LiveTileListMapper();

		$this->assertSame([], $mapper->configErrors(config: ['sourceMode' => 'url', 'valueExpr' => '$.open']));
		$this->assertSame('value', $mapper->displayOf(config: ['valueExpr' => '$.open']));
	}//end testAnOldConfigHasNoErrorsAndShowsAValue()

	public function testEachRuleIsChecked(): void {
		$mapper = new LiveTileListMapper();
		$field = ['label' => 'Title', 'path' => 'name', 'role' => 'title'];

		$this->assertSame(['invalid_display'], $mapper->configErrors(config: ['display' => 'chart']));
		$this->assertSame(['mapping_required'], $mapper->configErrors(config: ['display' => 'list']));
		$this->assertSame(['invalid_mapping'], $mapper->configErrors(config: ['display' => 'table', 'mapping' => array_fill(0, 7, $field)]));
		$this->assertSame(['invalid_mapping'], $mapper->configErrors(config: ['display' => 'list', 'mapping' => [['label' => 'X', 'path' => 'a', 'role' => 'image']]]));
		$this->assertSame(['invalid_mapping'], $mapper->configErrors(config: ['display' => 'list', 'mapping' => [['label' => 'X', 'path' => 'a b', 'role' => 'field']]]));
		$this->assertSame(['invalid_limit'], $mapper->configErrors(config: ['display' => 'list', 'limit' => 51, 'mapping' => [$field]]));
		$this->assertSame(['invalid_limit'], $mapper->configErrors(config: ['limit' => 0]));
		$this->assertSame([], $mapper->configErrors(config: ['display' => 'list', 'limit' => '5', 'mapping' => array_fill(0, 6, $field)]));
	}//end testEachRuleIsChecked()

	public function testAListKeepsOnlyMappedFieldsCutToTheLimit(): void {
		$incidents = [];
		for ($i = 1; $i <= 7; $i++) {
			$incidents[] = ['briefDescription' => 'Printer '.$i, 'url' => 'https://topdesk.example/i/'.$i, 'callDate' => '2026-11-0'.$i, 'callerEmail' => 'p'.$i.'@example.nl'];
		}

		$mapped = (new LiveTileListMapper())->map(
			node: $incidents,
			config: ['display' => 'list', 'limit' => 5, 'mapping' => $this->incidentMapping()],
			extract: $this->extract()
		);

		$this->assertCount(5, $mapped['items']);
		$this->assertSame(
			[
				['label' => 'Incident', 'role' => 'title', 'value' => 'Printer 1'],
				['label' => 'Open', 'role' => 'link', 'value' => 'https://topdesk.example/i/1'],
				['label' => 'Reported', 'role' => 'date', 'value' => '2026-11-01'],
			],
			$mapped['items'][0]
		);
		$this->assertStringNotContainsString('callerEmail', (string)json_encode($mapped));
		$this->assertStringNotContainsString('@example.nl', (string)json_encode($mapped));
	}//end testAListKeepsOnlyMappedFieldsCutToTheLimit()

	public function testALinkThatIsNotHttpsIsDropped(): void {
		$mapped = (new LiveTileListMapper())->map(
			node: [['briefDescription' => 'X', 'url' => 'javascript:alert(1)'], ['briefDescription' => 'Y', 'url' => 'http://plain.example/']],
			config: ['display' => 'list', 'mapping' => $this->incidentMapping()],
			extract: $this->extract()
		);

		$this->assertNull($mapped['items'][0][1]['value']);
		$this->assertNull($mapped['items'][1][1]['value']);
		$this->assertSame('X', $mapped['items'][0][0]['value']);
	}//end testALinkThatIsNotHttpsIsDropped()

	public function testKeyValueReadsPairsFromAnObjectAndNestedValuesBecomeNull(): void {
		$mapped = (new LiveTileListMapper())->map(
			node: ['open' => 4, 'team' => ['name' => 'Servicedesk'], 'secret' => 'x'],
			config: ['display' => 'keyvalue', 'mapping' => [
				['label' => 'Open', 'path' => 'open', 'role' => 'field'],
				['label' => 'Team', 'path' => 'team.name', 'role' => 'field'],
				['label' => 'Raw', 'path' => 'team', 'role' => 'field'],
			]],
			extract: $this->extract()
		);

		$this->assertSame(
			[
				['label' => 'Open', 'role' => 'field', 'value' => 4],
				['label' => 'Team', 'role' => 'field', 'value' => 'Servicedesk'],
				['label' => 'Raw', 'role' => 'field', 'value' => null],
			],
			$mapped['pairs']
		);
	}//end testKeyValueReadsPairsFromAnObjectAndNestedValuesBecomeNull()

	public function testANodeThatDoesNotFitTheDisplayIsRefused(): void {
		$mapper = new LiveTileListMapper();
		$config = ['display' => 'list', 'mapping' => $this->incidentMapping()];

		$this->assertNull($mapper->map(node: ['briefDescription' => 'not a list'], config: $config, extract: $this->extract()));
		$this->assertNull($mapper->map(node: 42, config: $config, extract: $this->extract()));
		$this->assertNull($mapper->map(node: [['a' => 1]], config: ['display' => 'keyvalue'] + $config, extract: $this->extract()));
		$this->assertCount(10, $mapper->map(node: array_fill(0, 30, []), config: $config, extract: $this->extract())['items']);
	}//end testANodeThatDoesNotFitTheDisplayIsRefused()
}//end class
