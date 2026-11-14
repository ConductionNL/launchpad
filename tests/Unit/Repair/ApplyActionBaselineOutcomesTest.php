<?php

/**
 * ApplyActionBaselineOutcomesTest
 *
 * Pins what the baseline repair step writes, logs and reports for every
 * path: already applied, everything broadened, one or more admin choices
 * preserved, nothing to broaden, and a failed matrix write.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use JsonException;
use OCA\LaunchPad\Repair\ApplyActionBaseline;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * Every path of the baseline repair step, with its writes and messages.
 */
class ApplyActionBaselineOutcomesTest extends TestCase {

	/**
	 * Calls made in the current run.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private array $calls = [];

	/**
	 * Every path writes, logs and reports as recorded.
	 *
	 * @return void
	 */
	public function testEveryPathWritesAndReports(): void {
		$keys  = $this->baselineKeys();
		$admin = array_fill_keys($keys, ['admin']);
		$mixed = $admin;
		$mixed[$keys[0]] = ['admin', 'editors'];
		$custom = array_fill_keys($keys, ['admin', 'editors']);
		$oneLeft = $custom;
		unset($oneLeft[$keys[1]]);

		$runs = [
			'already applied' => [99, $admin, false],
			'all broadened' => [0, $admin, false],
			'one preserved' => [0, $mixed, false],
			'all preserved' => [0, $custom, false],
			'one missing, rest preserved' => [0, $oneLeft, false],
			'write fails' => [0, $admin, true],
		];

		$actual = [];
		foreach ($runs as $name => [$applied, $matrix, $fails]) {
			$this->calls = [];
			$this->step(applied: $applied, matrix: $matrix, fails: $fails)->run($this->recordingOutput());
			$actual[$name] = array_map(
				static fn (array $call): array => ($call[0] === 'setMatrix') ? ['setMatrix', count($call[1]), array_keys(array_filter($call[1], static fn (array $groups): bool => $groups === ['admin', 'editors']))] : $call,
				$this->calls
			);
		}

		$count = count($keys);
		$this->assertSame(
			[
				'already applied' => [['info', 'Action baseline v99 already applied — nothing to do.']],
				'all broadened' => [['setMatrix', $count, []], ['setValueInt'], ['info', $this->summary(broadened: $count, preserved: 0)]],
				'one preserved' => [['setMatrix', $count, [$keys[0]]], ['setValueInt'], ['info', $this->summary(broadened: $count - 1, preserved: 1)]],
				'all preserved' => [['setValueInt'], ['info', $this->summary(broadened: 0, preserved: $count)]],
				'one missing, rest preserved' => [['setMatrix', $count, array_values(array_diff($keys, [$keys[1]]))], ['setValueInt'], ['info', $this->summary(broadened: 1, preserved: $count - 1)]],
				'write fails' => [['setMatrix', $count, []], ['warning', 'Failed to write matrix: disk full'], ['log error', '[launchpad] ADR-023 baseline write failed: disk full']],
			],
			$actual
		);
	}//end testEveryPathWritesAndReports()

	/**
	 * The summary line the step reports.
	 *
	 * @param int $broadened Broadened actions.
	 * @param int $preserved Preserved entries.
	 *
	 * @return string
	 */
	private function summary(int $broadened, int $preserved): string {
		return sprintf(
			'Action baseline v%d applied: %d action(s) broadened to the non-admin baseline, %d admin-customized entr%s preserved.',
			2,
			$broadened,
			$preserved,
			($preserved === 1) ? 'y' : 'ies'
		);
	}//end summary()

	/**
	 * The baseline action keys a fresh instance receives.
	 *
	 * @return string[]
	 */
	private function baselineKeys(): array {
		$step     = $this->step(applied: 0, matrix: [], fails: false);
		$baseline = (new ReflectionMethod(ApplyActionBaseline::class, 'readBaseline'))->invoke($step);
		$since    = (new ReflectionMethod(ApplyActionBaseline::class, 'baselineSince'))->invoke($step, 0, $baseline);
		$this->assertGreaterThan(2, count($since));
		return array_keys($since);
	}//end baselineKeys()

	/**
	 * An output that records its messages.
	 *
	 * @return IOutput
	 */
	private function recordingOutput(): IOutput {
		$output = $this->createMock(IOutput::class);
		foreach (['info', 'warning'] as $method) {
			$output->method($method)->willReturnCallback(
				function (string $message) use ($method): void {
					$this->calls[] = [$method, $message];
				}
			);
		}

		return $output;
	}//end recordingOutput()

	/**
	 * The step over a recorded matrix store.
	 *
	 * @param int                     $applied The stored baseline version.
	 * @param array<string, string[]> $matrix  The stored matrix.
	 * @param bool                    $fails   Whether the matrix write fails.
	 *
	 * @return ApplyActionBaseline
	 */
	private function step(int $applied, array $matrix, bool $fails): ApplyActionBaseline {
		$auth = $this->createMock(ActionAuthService::class);
		$auth->method('getMatrix')->willReturn($matrix);
		$auth->method('setMatrix')->willReturnCallback(
			function (array $matrix) use ($fails): void {
				$this->calls[] = ['setMatrix', $matrix];
				if ($fails === true) {
					throw new JsonException('disk full');
				}
			}
		);

		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueInt')->willReturn($applied);
		$config->method('setValueInt')->willReturnCallback(
			function (): bool {
				$this->calls[] = ['setValueInt'];
				return true;
			}
		);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function (string $message): void {
				$this->calls[] = ['log error', $message];
			}
		);

		return new ApplyActionBaseline(actionAuth: $auth, appConfig: $config, logger: $logger);
	}//end step()
}//end class
