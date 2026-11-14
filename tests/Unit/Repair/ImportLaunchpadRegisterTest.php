<?php

/**
 * ImportLaunchpadRegisterTest
 *
 * Pins what the register import repair step does and says on every path:
 * OpenRegister missing, descriptor missing, descriptor not JSON, a
 * successful import, and a failing import.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Repair\ImportLaunchpadRegister;
use OCP\App\IAppManager;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class ImportLaunchpadRegisterTest extends TestCase {

	private string $root = '';

	/** @var list<string> */
	private array $log = [];

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/lp-register-' . getmypid();
		@mkdir($this->root . '/lib/Settings', 0777, true);
	}

	protected function tearDown(): void {
		@unlink($this->root . '/lib/Settings/launchpad_register.json');
		foreach (glob($this->root . '/lib/Settings/register.d/*.json') ?: [] as $fragment) {
			@unlink($fragment);
		}

		@rmdir($this->root . '/lib/Settings/register.d');
	}

	/**
	 * Run the step with an importer (or none) and record what it says.
	 *
	 * @param object|null $importer The configuration service stand-in.
	 *
	 * @return list<string>
	 */
	private function runStep(?object $importer): array {
		$this->log = [];
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(function (string $id) use ($importer): object {
			$this->log[] = 'get ' . $id;
			if ($importer === null) {
				throw new \RuntimeException('no openregister');
			}

			return $importer;
		});
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getAppPath')->willReturn($this->root);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(function (string $message): void {
			$this->log[] = 'log ' . $message;
		});
		$output = $this->createMock(IOutput::class);
		foreach (['info', 'warning'] as $level) {
			$output->method($level)->willReturnCallback(function (string $message) use ($level): void {
				$this->log[] = $level . ' ' . str_replace($this->root, '<root>', $message);
			});
		}

		(new ImportLaunchpadRegister(container: $container, appManager: $apps, logger: $logger))->run($output);
		return $this->log;
	}

	private function importer(bool $failing): object {
		return new class ($failing) {
			/** @var list<string> */
			public array $calls = [];

			/** @var list<array> */
			public array $imported = [];

			public function __construct(private bool $failing) {
			}

			public function importFromApp(string $appId, array $data, string $version, bool $force): void {
				$this->calls[] = $appId . ' ' . json_encode($data) . ' ' . $version . ' ' . var_export($force, true);
				$this->imported[] = $data;
				if ($this->failing === true) {
					throw new \RuntimeException('bad schema');
				}
			}
		};
	}

	public function testEveryPath(): void {
		$this->assertSame(
			['get OCA\OpenRegister\Service\ConfigurationService', 'info LaunchPad: OpenRegister not available — skipping register import.'],
			$this->runStep(null)
		);
		$this->assertSame(
			['get OCA\OpenRegister\Service\ConfigurationService', 'warning LaunchPad register descriptor not found: <root>/lib/Settings/launchpad_register.json'],
			$this->runStep($this->importer(false))
		);

		file_put_contents($this->root . '/lib/Settings/launchpad_register.json', 'not json');
		$this->assertSame(
			['get OCA\OpenRegister\Service\ConfigurationService', 'warning LaunchPad register descriptor is not valid JSON: <root>/lib/Settings/launchpad_register.json'],
			$this->runStep($this->importer(false))
		);

		file_put_contents($this->root . '/lib/Settings/launchpad_register.json', '{"a":1}');
		$importer = $this->importer(false);
		$this->assertSame(
			['get OCA\OpenRegister\Service\ConfigurationService', 'info LaunchPad register imported (launchpad register + dashboard schema)'],
			$this->runStep($importer)
		);
		$this->assertSame(['launchpad {"a":1} 1.0.2 false'], $importer->calls);

		$this->assertSame(
			['get OCA\OpenRegister\Service\ConfigurationService', 'log [ImportLaunchpadRegister] import failed: bad schema', 'warning LaunchPad register import skipped: bad schema'],
			$this->runStep($this->importer(true))
		);
	}

	/**
	 * register.d fragments merge onto the descriptor in name order: objects key
	 * by key, lists joined; a broken fragment is skipped with a warning.
	 *
	 * @spec openspec/changes/openregister-leaf-integrations/specs/dashboard-talk-leaf/spec.md#requirement-req-leaf-001-talk-leaf-declared-on-the-dashboard-schema-via-registerd-overlay
	 */
	public function testFragmentsMergeOntoTheDescriptor(): void {
		@mkdir($this->root . '/lib/Settings/register.d', 0777, true);
		file_put_contents(
			$this->root . '/lib/Settings/launchpad_register.json',
			'{"components":{"schemas":{"Dashboard":{"slug":"dashboard","required":["name"]}}}}'
		);
		file_put_contents($this->root . '/lib/Settings/register.d/b-leaf.json', '{"components":{"schemas":{"Dashboard":{"configuration":{"linkedTypes":["talk"]}}}}}');
		file_put_contents($this->root . '/lib/Settings/register.d/a-required.json', '{"components":{"schemas":{"Dashboard":{"required":["owner"]}}}}');
		file_put_contents($this->root . '/lib/Settings/register.d/c-broken.json', '{ nope');
		$importer = $this->importer(false);

		$log = $this->runStep($importer);

		$this->assertContains('warning LaunchPad register fragment is not valid JSON, skipped: c-broken.json', $log);
		$this->assertSame(
			['launchpad {"components":{"schemas":{"Dashboard":{"slug":"dashboard","required":["name","owner"],"configuration":{"linkedTypes":["talk"]}}}}} 1.0.2 false'],
			$importer->calls
		);
	}

	/**
	 * The shipped descriptor with the shipped fragments gives the Dashboard
	 * schema the talk leaf, and leaves the rest of the schema as the base file has it.
	 *
	 * @spec openspec/changes/openregister-leaf-integrations/specs/dashboard-talk-leaf/spec.md#requirement-req-leaf-001-talk-leaf-declared-on-the-dashboard-schema-via-registerd-overlay
	 */
	public function testTheShippedDashboardSchemaDeclaresTheTalkLeaf(): void {
		$this->root = dirname(__DIR__, 3);
		$importer   = $this->importer(false);
		$this->runStep($importer);
		$this->root = sys_get_temp_dir() . '/lp-register-' . getmypid();

		$this->assertCount(1, $importer->imported);
		$data = $importer->imported[0];
		$base = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/launchpad_register.json'), true);

		$this->assertSame(['talk'], $data['components']['schemas']['Dashboard']['configuration']['linkedTypes']);
		$this->assertArrayNotHasKey('configuration', $base['components']['schemas']['Dashboard'], 'the base file stays a domain statement');
		unset($data['components']['schemas']['Dashboard']['configuration']);
		$this->assertSame($base['components']['schemas']['Dashboard'], $data['components']['schemas']['Dashboard']);
	}
}
