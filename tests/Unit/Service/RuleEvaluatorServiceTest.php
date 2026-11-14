<?php

/**
 * RuleEvaluatorService Test
 *
 * Pins the four visibility rule types: group membership (live and
 * preview override), the time window with its day filter, the date
 * window, and the attribute rule's delegation to the resolver. An
 * unknown rule type never matches.
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
use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\RuleEvaluatorService;
use OCA\LaunchPad\Service\UserAttributeResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RuleEvaluatorServiceTest extends TestCase {
	/**
	 * Service.
	 *
	 * @var RuleEvaluatorService
	 */
	private RuleEvaluatorService $service;

	/**
	 * Admin template service.
	 *
	 * @var AdminTemplateService
	 */
	private AdminTemplateService&MockObject $adminTemplateService;

	/**
	 * Attr resolver.
	 *
	 * @var UserAttributeResolver
	 */
	private UserAttributeResolver&MockObject $attrResolver;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->adminTemplateService = $this->createMock(originalClassName: AdminTemplateService::class);
		$this->attrResolver = $this->createMock(originalClassName: UserAttributeResolver::class);
		$this->service = new RuleEvaluatorService(
			adminTemplateService: $this->adminTemplateService,
			attrResolver: $this->attrResolver,
		);
	}//end setUp()

	/**
	 * Rule.
	 *
	 * @param string $type Type.
	 * @param array $config Config.
	 * @return ConditionalRule
	 */
	private function rule(string $type, array $config): ConditionalRule {
		$rule = new ConditionalRule();
		$rule->setRuleType($type);
		$rule->setRuleConfigArray($config);
		return $rule;
	}//end rule()

	/**
	 * Group rule matches when the user shares agroup.
	 *
	 * @return void
	 */
	public function testGroupRuleMatchesWhenTheUserSharesAGroup(): void {
		$this->adminTemplateService->method('getUserGroupIdsFor')->with('alice')
			->willReturn(value: ['staff', 'editors']);

		$this->assertTrue(
			condition: $this->service->evaluateRule(
				rule: $this->rule(type: ConditionalRule::TYPE_GROUP, config: ['groups' => ['editors']]),
				userId: 'alice'
			)
		);
	}//end testGroupRuleMatchesWhenTheUserSharesAGroup()

	/**
	 * Group rule does not match adisjoint user.
	 *
	 * @return void
	 */
	public function testGroupRuleDoesNotMatchADisjointUser(): void {
		$this->adminTemplateService->method('getUserGroupIdsFor')->willReturn(value: ['staff']);

		$this->assertFalse(
			condition: $this->service->evaluateRule(
				rule: $this->rule(type: ConditionalRule::TYPE_GROUP, config: ['groups' => ['admin']]),
				userId: 'alice'
			)
		);
	}//end testGroupRuleDoesNotMatchADisjointUser()

	/**
	 * Group rule without target groups never matches.
	 *
	 * @return void
	 */
	public function testGroupRuleWithoutTargetGroupsNeverMatches(): void {
		$this->adminTemplateService->expects($this->never())->method('getUserGroupIdsFor');

		$this->assertFalse(
			condition: $this->service->evaluateRule(
				rule: $this->rule(type: ConditionalRule::TYPE_GROUP, config: []),
				userId: 'alice'
			)
		);
	}//end testGroupRuleWithoutTargetGroupsNeverMatches()

	/**
	 * Group rule uses the preview override instead of the live groups.
	 *
	 * @return void
	 */
	public function testGroupRuleUsesThePreviewOverrideInsteadOfTheLiveGroups(): void {
		$this->adminTemplateService->expects($this->never())->method('getUserGroupIdsFor');
		$rule = $this->rule(type: ConditionalRule::TYPE_GROUP, config: ['groups' => ['admin']]);

		$this->assertTrue(condition: $this->service->evaluateRule(rule: $rule, userId: 'alice', groupsOverride: ['admin']));
		// An empty override is a user in no groups, not "use the live groups".
		$this->assertFalse(condition: $this->service->evaluateRule(rule: $rule, userId: 'alice', groupsOverride: []));
	}//end testGroupRuleUsesThePreviewOverrideInsteadOfTheLiveGroups()

	/**
	 * @return array<string, array{0: string, 1: array<string, mixed>, 2: bool}>
	 */
	public static function timeCases(): array {
		// 2026-11-11 is a Wednesday.
		return [
			'inside the window'         => ['2026-11-11 10:30', ['startTime' => '09:00', 'endTime' => '17:00'], true],
			'before the window'         => ['2026-11-11 08:59', ['startTime' => '09:00', 'endTime' => '17:00'], false],
			'after the window'          => ['2026-11-11 17:01', ['startTime' => '09:00', 'endTime' => '17:00'], false],
			'on the end minute'         => ['2026-11-11 17:00', ['startTime' => '09:00', 'endTime' => '17:00'], true],
			'listed day'                => ['2026-11-11 12:00', ['days' => ['mon', 'wed']], true],
			'unlisted day'              => ['2026-11-11 12:00', ['days' => ['sat', 'sun']], false],
			'no config is the whole day' => ['2026-11-11 23:59', [], true],
		];
	}//end timeCases()

	/**
	 * @dataProvider timeCases
	 * @param string $now Now.
	 * @param array $config Config.
	 * @param bool $expected Expected.
	 * @return void
	 */
	public function testTimeRule(string $now, array $config, bool $expected): void {
		$this->assertSame(
			expected: $expected,
			actual: $this->service->evaluateRule(
				rule: $this->rule(type: ConditionalRule::TYPE_TIME, config: $config),
				userId: 'alice',
				nowOverride: new DateTimeImmutable($now)
			)
		);
	}//end testTimeRule()

	/**
	 * @return array<string, array{0: string, 1: array<string, mixed>, 2: bool}>
	 */
	public static function dateCases(): array {
		$window = ['startDate' => '2026-11-01', 'endDate' => '2026-11-30'];
		return [
			'inside'         => ['2026-11-11', $window, true],
			'first day'      => ['2026-11-01', $window, true],
			'last day'       => ['2026-11-30', $window, true],
			'before'         => ['2026-10-31', $window, false],
			'after'          => ['2026-12-01', $window, false],
			'open start'     => ['2020-01-01', ['endDate' => '2026-11-30'], true],
			'open end'       => ['2099-01-01', ['startDate' => '2026-11-01'], true],
		];
	}//end dateCases()

	/**
	 * @dataProvider dateCases
	 * @param string $now Now.
	 * @param array $config Config.
	 * @param bool $expected Expected.
	 * @return void
	 */
	public function testDateRule(string $now, array $config, bool $expected): void {
		$this->assertSame(
			expected: $expected,
			actual: $this->service->evaluateRule(
				rule: $this->rule(type: ConditionalRule::TYPE_DATE, config: $config),
				userId: 'alice',
				nowOverride: new DateTimeImmutable($now . ' 12:00')
			)
		);
	}//end testDateRule()

	/**
	 * Attribute rule delegates the comparison to the resolver.
	 *
	 * @return void
	 */
	public function testAttributeRuleDelegatesTheComparisonToTheResolver(): void {
		$this->attrResolver->method('getUserAttributeValue')->with('alice', 'locale')
			->willReturn(value: 'nl');
		$this->attrResolver->expects($this->once())->method('evaluateOperator')
			->with('nl', 'contains', 'n')->willReturn(value: true);

		$this->assertTrue(
			condition: $this->service->evaluateRule(
				rule: $this->rule(
					type: ConditionalRule::TYPE_ATTRIBUTE,
					config: ['attribute' => 'locale', 'operator' => 'contains', 'value' => 'n']
				),
				userId: 'alice'
			)
		);
	}//end testAttributeRuleDelegatesTheComparisonToTheResolver()

	/**
	 * Attribute rule defaults to equals.
	 *
	 * @return void
	 */
	public function testAttributeRuleDefaultsToEquals(): void {
		$this->attrResolver->method('getUserAttributeValue')->willReturn(value: 'nl');
		$this->attrResolver->expects($this->once())->method('evaluateOperator')
			->with('nl', 'equals', 'nl')->willReturn(value: true);

		$this->assertTrue(
			condition: $this->service->evaluateRule(
				rule: $this->rule(type: ConditionalRule::TYPE_ATTRIBUTE, config: ['attribute' => 'locale', 'value' => 'nl']),
				userId: 'alice'
			)
		);
	}//end testAttributeRuleDefaultsToEquals()

	/**
	 * Attribute rule without an attribute never matches.
	 *
	 * @return void
	 */
	public function testAttributeRuleWithoutAnAttributeNeverMatches(): void {
		$this->attrResolver->expects($this->never())->method('getUserAttributeValue');

		$this->assertFalse(
			condition: $this->service->evaluateRule(
				rule: $this->rule(type: ConditionalRule::TYPE_ATTRIBUTE, config: ['value' => 'nl']),
				userId: 'alice'
			)
		);
	}//end testAttributeRuleWithoutAnAttributeNeverMatches()

	/**
	 * Attribute rule does not match auser without the attribute.
	 *
	 * @return void
	 */
	public function testAttributeRuleDoesNotMatchAUserWithoutTheAttribute(): void {
		$this->attrResolver->method('getUserAttributeValue')->willReturn(value: null);
		$this->attrResolver->expects($this->never())->method('evaluateOperator');

		$this->assertFalse(
			condition: $this->service->evaluateRule(
				rule: $this->rule(type: ConditionalRule::TYPE_ATTRIBUTE, config: ['attribute' => 'department']),
				userId: 'alice'
			)
		);
	}//end testAttributeRuleDoesNotMatchAUserWithoutTheAttribute()

	/**
	 * Unknown rule type never matches.
	 *
	 * @return void
	 */
	public function testUnknownRuleTypeNeverMatches(): void {
		$this->assertFalse(
			condition: $this->service->evaluateRule(
				rule: $this->rule(type: 'moon-phase', config: []),
				userId: 'alice'
			)
		);
	}//end testUnknownRuleTypeNeverMatches()
}//end class
