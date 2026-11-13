<?php

/**
 * Unit tests for UserAttributeResolver: attribute lookup and comparison operators.
 *
 * @category Test
 * @package  Unit\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\UserAttributeResolver;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\UserAttributeResolver
 */
class UserAttributeResolverTest extends TestCase {
	private IUserManager&MockObject $userManager;
	private IConfig&MockObject $config;
	private UserAttributeResolver $resolver;

	protected function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->resolver = new UserAttributeResolver(
			userManager: $this->userManager,
			config: $this->config
		);
	}

	private function givenUser(): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('alice')
			->willReturn($user);
		return $user;
	}

	public function testUnknownUserResolvesToNullWithoutReadingConfig(): void {
		$this->userManager->method('get')->willReturn(null);
		$this->config->expects($this->never())->method('getUserValue');

		$this->assertNull($this->resolver->getUserAttributeValue(userId: 'ghost', attribute: 'locale'));
	}

	public function testLocaleReadsTheCoreLangPreference(): void {
		$this->givenUser();
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('alice', 'core', 'lang', 'en')
			->willReturn('nl');

		$this->assertSame('nl', $this->resolver->getUserAttributeValue(userId: 'alice', attribute: 'locale'));
	}

	public function testEmailComesFromTheUser(): void {
		$this->givenUser()->method('getEMailAddress')->willReturn('alice@example.org');

		$this->assertSame('alice@example.org', $this->resolver->getUserAttributeValue(userId: 'alice', attribute: 'email'));
	}

	public function testDisplayNameComesFromTheUser(): void {
		$this->givenUser()->method('getDisplayName')->willReturn('Alice A.');

		$this->assertSame('Alice A.', $this->resolver->getUserAttributeValue(userId: 'alice', attribute: 'displayName'));
	}

	public function testQuotaComesFromTheUser(): void {
		$this->givenUser()->method('getQuota')->willReturn('5 GB');

		$this->assertSame('5 GB', $this->resolver->getUserAttributeValue(userId: 'alice', attribute: 'quota'));
	}

	public function testUnsupportedAttributeIsNull(): void {
		$this->givenUser();

		$this->assertNull($this->resolver->getUserAttributeValue(userId: 'alice', attribute: 'password'));
	}

	/**
	 * @dataProvider operatorCases
	 */
	public function testEvaluateOperator(string $userValue, string $operator, ?string $value, bool $expected): void {
		$this->assertSame($expected, $this->resolver->evaluateOperator(userValue: $userValue, operator: $operator, value: $value));
	}

	public static function operatorCases(): array {
		return [
			'equals match' => ['nl', 'equals', 'nl', true],
			'equals is case sensitive' => ['nl', 'equals', 'NL', false],
			'equals against null' => ['nl', 'equals', null, false],
			'not_equals differs' => ['nl', 'not_equals', 'de', true],
			'not_equals same' => ['nl', 'not_equals', 'nl', false],
			'not_equals against null' => ['nl', 'not_equals', null, true],
			'contains hit' => ['alice@example.org', 'contains', '@example', true],
			'contains miss' => ['alice@example.org', 'contains', '@other', false],
			'contains null needle matches' => ['abc', 'contains', null, true],
			'starts_with hit' => ['alice@example.org', 'starts_with', 'alice', true],
			'starts_with miss' => ['alice@example.org', 'starts_with', 'bob', false],
			'ends_with hit' => ['alice@example.org', 'ends_with', '.org', true],
			'ends_with miss' => ['alice@example.org', 'ends_with', '.nl', false],
			'unknown operator' => ['nl', 'regex', 'nl', false],
		];
	}
}
