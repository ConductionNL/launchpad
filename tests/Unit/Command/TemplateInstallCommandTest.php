<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Command;

use InvalidArgumentException;
use OCA\LaunchPad\Command\TemplateInstallCommand;
use OCA\LaunchPad\Exception\TemplateNotInstalledException;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\ShippedTemplateService;
use OCA\LaunchPad\Service\ShippedTemplateUpdateService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `launchpad:template:install` (REQ-CLI-012): what it passes on, what it
 * prints and how it exits. The install itself is ShippedTemplateServiceTest.
 */
class TemplateInstallCommandTest extends TestCase {
	/** @var ShippedTemplateService&MockObject */
	private $templates;

	/** @var ShippedTemplateUpdateService&MockObject */
	private $updates;

	protected function setUp(): void {
		parent::setUp();
		$this->templates = $this->createMock(ShippedTemplateService::class);
		$this->updates = $this->createMock(ShippedTemplateUpdateService::class);
	}

	private function command(bool $personalDashboards = true): TemplateInstallCommand {
		return new TemplateInstallCommand($this->templates, $this->settings($personalDashboards), $this->updates);
	}

	/**
	 * @param array<string, mixed> $overrides Result fields to override.
	 *
	 * @return array<string, mixed>
	 */
	private static function updateResult(array $overrides = []): array {
		return $overrides + [
			'templateId' => 'mijn-werkdag',
			'uuid' => 'uuid-1',
			'id' => 3,
			'name' => 'Mijn werkdag',
			'installedVersion' => 2,
			'version' => 3,
			'upToDate' => false,
			'dryRun' => false,
			'applied' => true,
			'added' => ['Mijn tickets (object-list)'],
			'removed' => ['Oud (text)'],
			'changed' => [['widget' => 'Mijn zaken (object-list)', 'fields' => ['position', 'settings']]],
			'unchanged' => 3,
			'copies' => 4,
			'resync' => ['async' => false, 'affectedCount' => 4, 'totalCopies' => 4],
		];
	}

	/**
	 * @param array<string, mixed> $overrides Result fields to override.
	 *
	 * @return array<string, mixed>
	 */
	private static function installResult(array $overrides = []): array {
		return $overrides + [
			'templateId' => 'mijn-werkdag',
			'uuid' => 'uuid-1',
			'id' => 3,
			'version' => 1,
			'alreadyInstalled' => false,
			'targetGroups' => [],
			'isDefault' => false,
			'missingWidgets' => [],
			'registers' => ['dossiq', 'pipelinq'],
		];
	}

	private function settings(bool $personalDashboards): AdminSettingsService {
		$settings = $this->createMock(AdminSettingsService::class);
		$settings->method('getSettings')->willReturn(['allowUserDashboards' => $personalDashboards]);
		return $settings;
	}

	public function testInstallsForAGroup(): void {
		$this->templates->expects(self::once())->method('install')
			->with('mijn-werkdag', ['medewerkers', 'bestuur'], true, false)
			->willReturn(self::installResult(['targetGroups' => ['medewerkers', 'bestuur'], 'isDefault' => true]));

		$tester = new CommandTester($this->command());
		$exit = $tester->execute([
			'id' => 'mijn-werkdag',
			'--group' => ['medewerkers', 'bestuur'],
			'--default' => true,
		]);

		self::assertSame(0, $exit);
		$display = $tester->getDisplay();
		self::assertStringContainsString('Installed template mijn-werkdag version 1 (UUID: uuid-1).', $display);
		self::assertStringContainsString('Groups: medewerkers, bestuur. Default for everyone: yes.', $display);
		self::assertStringContainsString('Its lists read these registers: dossiq, pipelinq. A list whose register is not on this instance is hidden for members.', $display);
		self::assertStringNotContainsString('Personal dashboards are off', $display);
	}

	public function testASecondRunSaysSoAndPassesForce(): void {
		$this->templates->expects(self::once())->method('install')
			->with('mijn-werkdag', [], false, true)
			->willReturn(self::installResult(['alreadyInstalled' => true, 'missingWidgets' => ['decidesk']]));

		$tester = new CommandTester($this->command(false));
		$exit = $tester->execute(['id' => 'mijn-werkdag', '--force' => true]);

		self::assertSame(0, $exit);
		$display = $tester->getDisplay();
		self::assertStringContainsString('Already installed: template mijn-werkdag', $display);
		self::assertStringContainsString('Groups: (none). Default for everyone: no.', $display);
		self::assertStringContainsString('decidesk', $display);
		self::assertStringContainsString('Personal dashboards are off. Members will see this template read-only', $display);
	}

	public function testAnUnknownGroupExitsOne(): void {
		$this->templates->method('install')
			->willThrowException(new InvalidArgumentException('Unknown group: medewerkerz'));

		$tester = new CommandTester($this->command());

		self::assertSame(1, $tester->execute(['id' => 'mijn-werkdag', '--group' => ['medewerkerz']]));
		self::assertStringContainsString('Unknown group: medewerkerz', $tester->getDisplay());
	}

	public function testAnUnknownIdExitsOne(): void {
		$this->templates->method('install')->willThrowException(
			new InvalidArgumentException('Unknown template: nope. Shipped templates: mijn-werkdag')
		);

		$tester = new CommandTester($this->command());

		self::assertSame(1, $tester->execute(['id' => 'nope']));
		self::assertStringContainsString('Shipped templates: mijn-werkdag', $tester->getDisplay());
	}

	public function testAFailedInstallExitsOne(): void {
		$this->templates->method('install')->willThrowException(new RuntimeException('disk full'));

		$tester = new CommandTester($this->command());

		self::assertSame(1, $tester->execute(['id' => 'mijn-werkdag']));
		self::assertStringContainsString('Installation failed: disk full', $tester->getDisplay());
	}

	/**
	 * REQ-CLI-012: `--update` prints every widget it adds, removes and changes.
	 */
	public function testUpdatePrintsWhatChanges(): void {
		$this->templates->expects(self::never())->method('install');
		$this->updates->expects(self::once())->method('update')
			->with('mijn-werkdag', false)
			->willReturn(self::updateResult());

		$tester = new CommandTester($this->command());
		$exit = $tester->execute(['id' => 'mijn-werkdag', '--update' => true]);

		self::assertSame(0, $exit);
		$display = $tester->getDisplay();
		self::assertStringContainsString('Updated template mijn-werkdag from version 2 to version 3 (UUID: uuid-1).', $display);
		self::assertStringContainsString('+ added:   Mijn tickets (object-list)', $display);
		self::assertStringContainsString('- removed: Oud (text)', $display);
		self::assertStringContainsString('~ changed: Mijn zaken (object-list) (position, settings)', $display);
		self::assertStringContainsString('= unchanged: 3 widgets', $display);
		self::assertStringContainsString('Members with a copy of this template: 4. Copies changed: 4.', $display);
	}

	/**
	 * REQ-CLI-012: `--dry-run` is passed on and the output says nothing was written.
	 */
	public function testADryRunSaysNothingWasWritten(): void {
		$this->updates->expects(self::once())->method('update')
			->with('mijn-werkdag', true)
			->willReturn(self::updateResult(['dryRun' => true, 'applied' => false, 'resync' => null]));

		$tester = new CommandTester($this->command());
		$exit = $tester->execute(['id' => 'mijn-werkdag', '--update' => true, '--dry-run' => true]);

		self::assertSame(0, $exit);
		$display = $tester->getDisplay();
		self::assertStringContainsString('Dry run, nothing written. Would update template mijn-werkdag from version 2 to version 3', $display);
		self::assertStringContainsString('+ added:   Mijn tickets (object-list)', $display);
		self::assertStringContainsString('Their copies would follow.', $display);
		self::assertStringNotContainsString('Copies changed', $display);
	}

	public function testAnUpToDateTemplateSaysSoAndExitsZero(): void {
		$this->updates->method('update')->willReturn(
			self::updateResult(['upToDate' => true, 'installedVersion' => 3, 'applied' => false, 'resync' => null])
		);

		$tester = new CommandTester($this->command());

		self::assertSame(0, $tester->execute(['id' => 'mijn-werkdag', '--update' => true]));
		self::assertStringContainsString('is at version 3, the version LaunchPad ships. Nothing changed.', $tester->getDisplay());
		self::assertStringNotContainsString('added', $tester->getDisplay());
	}

	public function testUpdatingATemplateThatIsNotInstalledExitsOne(): void {
		$this->updates->method('update')->willThrowException(
			new TemplateNotInstalledException('Template mijn-werkdag is not installed here, so there is nothing to update.')
		);

		$tester = new CommandTester($this->command());

		self::assertSame(1, $tester->execute(['id' => 'mijn-werkdag', '--update' => true]));
		self::assertStringContainsString('is not installed here', $tester->getDisplay());
	}

	/**
	 * REQ-CLI-012: an update keeps the groups and the default flag, so the
	 * options that change them are refused before anything runs.
	 */
	public function testUpdateRefusesTheOptionsThatChangeWhoGetsTheTemplate(): void {
		$this->updates->expects(self::never())->method('update');
		$this->templates->expects(self::never())->method('install');

		foreach ([['--group' => ['medewerkers']], ['--default' => true], ['--force' => true]] as $extra) {
			$tester = new CommandTester($this->command());
			self::assertSame(1, $tester->execute(['id' => 'mijn-werkdag', '--update' => true] + $extra));
			self::assertStringContainsString('cannot be combined with', $tester->getDisplay());
		}
	}

	public function testDryRunWithoutUpdateExitsOne(): void {
		$this->templates->expects(self::never())->method('install');

		$tester = new CommandTester($this->command());

		self::assertSame(1, $tester->execute(['id' => 'mijn-werkdag', '--dry-run' => true]));
		self::assertStringContainsString('--dry-run only works together with --update', $tester->getDisplay());
	}
}
