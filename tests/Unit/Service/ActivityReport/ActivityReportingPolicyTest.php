<?php

/**
 * ActivityReportingPolicy Test
 *
 * REQ-DWMS-005: off by default, and only on with a purpose.
 *
 * @category  Test
 * @package   Unit\Service\ActivityReport
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

namespace Unit\Service\ActivityReport;

use InvalidArgumentException;
use OCA\LaunchPad\Service\ActivityReport\ActivityReportingPolicy;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityReportingPolicy::class)]
class ActivityReportingPolicyTest extends TestCase {

	/** @var array<string,mixed> */
	private array $stored = [];

	private function policy(): ActivityReportingPolicy {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueBool')->willReturnCallback(fn (string $app, string $key, bool $default = false): bool => $this->stored[$key] ?? $default);
		$config->method('getValueString')->willReturnCallback(fn (string $app, string $key, string $default = ''): string => $this->stored[$key] ?? $default);
		$config->method('setValueBool')->willReturnCallback(function (string $app, string $key, bool $value): bool {
			$this->assertSame('launchpad', $app);
			$this->stored[$key] = $value;
			return true;
		});
		$config->method('setValueString')->willReturnCallback(function (string $app, string $key, string $value): bool {
			$this->stored[$key] = $value;
			return true;
		});

		return new ActivityReportingPolicy($config);
	}

	public function testAFreshInstanceHasItOff(): void {
		$policy = $this->policy();

		$this->assertFalse($policy->isEnabled());
		$this->assertSame('', $policy->purpose());
	}

	public function testItTurnsOnWithAPurposeAndOffKeepingIt(): void {
		$policy = $this->policy();

		$this->assertSame(['enabled' => true, 'purpose' => 'workload balancing'], $policy->configure(true, '  workload balancing '));
		$this->assertTrue($policy->isEnabled());
		$this->assertSame(['enabled' => false, 'purpose' => 'workload balancing'], $policy->configure(false));
		$this->assertFalse($policy->isEnabled());
		$this->assertSame(['enabled' => true, 'purpose' => 'workload balancing'], $policy->configure(true));
	}

	public function testItDoesNotTurnOnWithoutAPurpose(): void {
		$policy = $this->policy();

		try {
			$policy->configure(true, '   ');
			$this->fail('it turned on without a purpose');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('Activity reporting can only be turned on with a purpose.', $e->getMessage());
		}

		$this->assertSame([], $this->stored, 'nothing was stored');
	}

	public function testASwitchStoredOnWithoutAPurposeReadsAsOff(): void {
		$this->stored = [ActivityReportingPolicy::KEY_ENABLED => true];

		$this->assertFalse($this->policy()->isEnabled());
	}

	public function testAnOverlongPurposeIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);

		$this->policy()->configure(true, str_repeat('x', ActivityReportingPolicy::MAX_PURPOSE_LENGTH + 1));
	}
}
