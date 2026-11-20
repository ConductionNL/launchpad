<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The read-only MCP declaration on the Dashboard schema, as the register
 * import hands it to OpenRegister.
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-17
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Repair\ImportLaunchpadRegister;
use OCP\App\IAppManager;
use OCP\Migration\IOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * Runs the real repair step over the shipped descriptor and fragments.
 */
#[CoversClass(ImportLaunchpadRegister::class)]
class DashboardMcpOverlayTest extends TestCase {

	/**
	 * The Dashboard schema's MCP declaration and the import version.
	 *
	 * @return array{0: array<string, mixed>, 1: string}
	 */
	private function imported(): array {
		$importer = new class {
			/**
			 * @var list<array{0: array<string, mixed>, 1: string}>
			 */
			public array $runs = [];

			/**
			 * Keep what the import hands over.
			 *
			 * @param string               $appId   The app.
			 * @param array<string, mixed> $data    The descriptor.
			 * @param string               $version The version.
			 * @param bool                 $force   Whether to force.
			 *
			 * @return void
			 */
			public function importFromApp(string $appId, array $data, string $version, bool $force): void {
				$this->runs[] = [$data, $version];
			}
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($importer);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getAppPath')->willReturn(dirname(__DIR__, 3));

		(new ImportLaunchpadRegister(container: $container, appManager: $apps, logger: new NullLogger()))
			->run(output: $this->createMock(originalClassName: IOutput::class));

		[$data, $version] = $importer->runs[0];
		return [$data['components']['schemas']['Dashboard']['configuration']['x-openregister-mcp'], $version];
	}//end imported()

	/**
	 * Only search and get are declared, both read-only, under a new import version.
	 *
	 * @return void
	 */
	public function testTheDashboardSchemaDeclaresOnlyReadTools(): void {
		[$mcp, $version] = $this->imported();

		$this->assertSame(expected: '1.0.7', actual: $version);
		$this->assertTrue(condition: $mcp['enabled']);
		$this->assertSame(expected: ['search', 'get'], actual: array_keys($mcp['tools']));
		foreach ($mcp['tools'] as $tool) {
			$this->assertSame(expected: 'read', actual: $tool['scope']);
			$this->assertTrue(condition: $tool['readOnlyHint']);
		}

		$this->assertSame(expected: ['title', 'slug', 'type', 'groupId'], actual: $mcp['tools']['search']['filters']);
	}//end testTheDashboardSchemaDeclaresOnlyReadTools()
}//end class
