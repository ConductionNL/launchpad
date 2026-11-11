<?php

/**
 * InitializeActionsTest
 *
 * Pins the first-install seeding of the ADR-023 action matrix: an
 * existing matrix is preserved, an empty one is seeded from
 * actions.seed.json, and a failed write is reported.
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
use OCA\LaunchPad\Repair\InitializeActions;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The matrix seeding repair step, path by path.
 */
class InitializeActionsTest extends TestCase {

	/**
	 * Calls made in the current run.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private array $calls = [];

	/**
	 * Every reachable path writes and reports as recorded.
	 *
	 * @return void
	 */
	public function testEveryPathWritesAndReports(): void {
		$seed  = json_decode((string) file_get_contents(__DIR__.'/../../../lib/actions.seed.json'), true)['actions'];
		$count = count($seed);

		$actual = [];
		foreach (['one entry' => [['a' => ['admin']], false], 'two entries' => [['a' => ['admin'], 'b' => ['admin']], false], 'empty' => [[], false], 'write fails' => [[], true]] as $name => [$existing, $fails]) {
			$this->calls = [];
			$this->step(existing: $existing, fails: $fails)->run($this->recordingOutput());
			$actual[$name] = $this->calls;
		}

		$this->assertSame(
			[
				'one entry' => [['info', 'Action matrix already has 1 entry — preserving.']],
				'two entries' => [['info', 'Action matrix already has 2 entries — preserving.']],
				'empty' => [['setMatrix', $seed], ['info', 'Seeded action matrix with '.$count.' actions (default: admin-only).']],
				'write fails' => [['setMatrix', $seed], ['warning', 'Failed to write matrix: disk full']],
			],
			$actual
		);
	}//end testEveryPathWritesAndReports()

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
	 * @param array<string, string[]> $existing The stored matrix.
	 * @param bool                    $fails    Whether the matrix write fails.
	 *
	 * @return InitializeActions
	 */
	private function step(array $existing, bool $fails): InitializeActions {
		$auth = $this->createMock(ActionAuthService::class);
		$auth->method('getMatrix')->willReturn($existing);
		$auth->method('setMatrix')->willReturnCallback(
			function (array $matrix) use ($fails): void {
				$this->calls[] = ['setMatrix', $matrix];
				if ($fails === true) {
					throw new JsonException('disk full');
				}
			}
		);

		return new InitializeActions(actionAuth: $auth, logger: $this->createMock(LoggerInterface::class));
	}//end step()
}//end class
