<?php

/**
 * MergeAllowedSitesTest
 *
 * REQ-ASET-021: on the first upgrade the per-feature host lists are merged
 * into the allowed websites list with the purpose they had, once.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Repair\MergeAllowedSites;
use OCA\LaunchPad\Service\AllowedSitesService;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class MergeAllowedSitesTest extends TestCase {

	/**
	 * An app config backed by an in-memory map.
	 *
	 * @param array<string, string> $values The stored values.
	 *
	 * @return IAppConfig
	 */
	private function configWith(array &$values): IAppConfig {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use (&$values): string {
				return $values[$key] ?? $default;
			}
		);
		$config->method('setValueString')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$values): bool {
				$values[$key] = $value;
				return true;
			}
		);

		return $config;
	}//end configWith()

	/**
	 * Run the step over the given config.
	 *
	 * @param IAppConfig $config The config.
	 * @param IOutput    $output The output.
	 *
	 * @return void
	 */
	private function runStep(IAppConfig $config, IOutput $output): void {
		$step = new MergeAllowedSites(
			appConfig: $config,
			allowedSites: new AllowedSitesService(appConfig: $config),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$this->assertNotSame(expected: '', actual: $step->getName());
		$step->run(output: $output);
	}//end runStep()

	/**
	 * Existing lists survive the upgrade with their purpose.
	 *
	 * @return void
	 */
	public function testExistingListsSurviveTheUpgradeWithTheirPurpose(): void {
		$values = [
			'livetile_allowed_hosts'            => '["kcc-api.zuiddrecht.nl"]',
			'iframe_allowed_hosts'              => '["app.powerbi.com","www.zuiddrecht.nl"]',
			'image_allowed_hosts'               => '["images.example.com:8443"]',
			'healthping_allowed_hosts'          => '["www.zuiddrecht.nl","not a host"]',
			'calendar_widget_allowed_ics_hosts' => '{not json',
		];
		$config = $this->configWith(values: $values);
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')->with('5 host(s) merged into the allowed websites list.');

		$this->runStep(config: $config, output: $output);

		$this->assertSame(
			expected: [
				['host' => 'app.powerbi.com', 'embed' => true, 'fetch' => false],
				['host' => 'www.zuiddrecht.nl', 'embed' => true, 'fetch' => true],
				['host' => 'images.example.com', 'embed' => true, 'fetch' => false],
				['host' => 'kcc-api.zuiddrecht.nl', 'embed' => false, 'fetch' => true],
			],
			actual: (new AllowedSitesService(appConfig: $config))->getSites()
		);
		$this->assertSame(expected: '1', actual: $values[MergeAllowedSites::DONE_KEY]);
		// The per-feature lists are kept as they are.
		$this->assertSame(expected: '["kcc-api.zuiddrecht.nl"]', actual: $values['livetile_allowed_hosts']);
	}//end testExistingListsSurviveTheUpgradeWithTheirPurpose()

	/**
	 * Runs only once.
	 *
	 * @return void
	 */
	public function testRunsOnlyOnce(): void {
		$values = [MergeAllowedSites::DONE_KEY => '1', 'livetile_allowed_hosts' => '["kcc-api.zuiddrecht.nl"]'];
		$config = $this->configWith(values: $values);

		$this->runStep(config: $config, output: $this->createMock(originalClassName: IOutput::class));

		$this->assertArrayNotHasKey(key: 'allowed_sites', array: $values);
	}//end testRunsOnlyOnce()

	/**
	 * A failure is a warning and leaves the step to run again.
	 *
	 * @return void
	 */
	public function testAFailureIsAWarningAndLeavesTheStepToRunAgain(): void {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => match ($key) {
				'iframe_allowed_hosts' => '["app.powerbi.com"]',
				default => $default,
			}
		);
		$config->method('setValueString')->willThrowException(new RuntimeException('database gone'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')
			->with('The host lists were not merged into the allowed websites: database gone');
		$output->expects($this->never())->method('info');

		$this->runStep(config: $config, output: $output);
	}//end testAFailureIsAWarningAndLeavesTheStepToRunAgain()
}//end class
