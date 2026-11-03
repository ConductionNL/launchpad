<?php

/**
 * Visibility rule set golden test
 *
 * Pins the verdict, the matched rule ids and every rule evaluation (with
 * the user and the preview overrides it received) for every rule set of up
 * to three include or exclude rules, against a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Service\RuleEvaluatorService;
use OCA\LaunchPad\Service\VisibilityChecker;
use PHPUnit\Framework\TestCase;

/**
 * Rule sets, verdict by verdict.
 *
 * @covers \OCA\LaunchPad\Service\VisibilityChecker
 * @uses   \OCA\LaunchPad\Db\ConditionalRule
 */
class VisibilityRuleSetGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/visibility-rule-set.json';

	/**
	 * Every rule set matches the recorded fixture.
	 *
	 * @return void
	 */
	public function testRuleSetsMatchTheRecordedFixture(): void {
		$kinds = ['I+', 'I-', 'E+', 'E-'];
		$sets = [[]];
		foreach ([1, 2, 3] as $size) {
			$next = [];
			foreach ($sets as $set) {
				if (count($set) === $size - 1) {
					foreach ($kinds as $kind) {
						$next[] = [...$set, $kind];
					}
				}
			}

			$sets = [...$sets, ...$next];
		}

		$overrides = [
			'live' => [null, null],
			'preview' => [['staff', 'hr'], new DateTimeImmutable('2030-02-03 04:05:06+00:00')],
			'preview no groups' => [[], null],
		];

		$actual = [];
		foreach ($sets as $set) {
			$name = '[' . implode(' ', $set) . ']';
			$actual["check $name"] = $this->evaluate(set: $set, call: ['check', 'alice', null, null]);
			foreach ($overrides as $label => [$groups, $now]) {
				$actual["set $name $label"] = $this->evaluate(set: $set, call: ['set', 'bob', $groups, $now]);
			}
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
			$this->markTestIncomplete(message: 'Fixture recorded.');
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		$this->assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $case) {
			$this->assertSame(expected: $case, actual: $actual[$name], message: $name);
		}
	}

	/**
	 * Evaluate one rule set and record the result and the evaluations.
	 *
	 * @param list<string>      $set  Rule kinds: I/E for include/exclude, +/- for match.
	 * @param array<int, mixed> $call Method, user, groups override, clock override.
	 *
	 * @return array<string, mixed>
	 */
	private function evaluate(array $set, array $call): array {
		$log = [];
		$rules = [];
		$matches = [];
		foreach ($set as $i => $kind) {
			$rule = new ConditionalRule();
			$rule->setId(10 + $i);
			$rule->setIsInclude($kind[0] === 'I');
			$rule->setRuleType('group');
			$rules[] = $rule;
			$matches[10 + $i] = $kind[1] === '+';
		}

		$evaluator = $this->getMockBuilder(className: RuleEvaluatorService::class)->disableOriginalConstructor()->getMock();
		$evaluator->method('evaluateRule')->willReturnCallback(static function (ConditionalRule $rule, string $userId, ?array $groupsOverride=null, ?DateTimeInterface $nowOverride=null) use (&$log, $matches): bool {
			$log[] = [$rule->getId(), $userId, $groupsOverride, $nowOverride?->format(DATE_ATOM)];
			return $matches[$rule->getId()];
		});

		$checker = new VisibilityChecker(ruleEvaluator: $evaluator);
		[$method, $userId, $groups, $now] = $call;
		if ($method === 'check') {
			$result = $checker->checkRules(rules: $rules, userId: $userId);
		} else {
			$result = $checker->evaluateRuleSet(rules: $rules, userId: $userId, groupsOverride: $groups, nowOverride: $now);
		}

		return ['result' => $result, 'log' => $log];
	}
}
