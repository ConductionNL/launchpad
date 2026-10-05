<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Command;

use InvalidArgumentException;
use OCA\LaunchPad\Command\TemplateInstallCommand;
use OCA\LaunchPad\Service\ShippedTemplateService;
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

	protected function setUp(): void {
		parent::setUp();
		$this->templates = $this->createMock(ShippedTemplateService::class);
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
		];
	}

	public function testInstallsForAGroup(): void {
		$this->templates->expects(self::once())->method('install')
			->with('mijn-werkdag', ['medewerkers', 'bestuur'], true, false)
			->willReturn(self::installResult(['targetGroups' => ['medewerkers', 'bestuur'], 'isDefault' => true]));

		$tester = new CommandTester(new TemplateInstallCommand($this->templates));
		$exit = $tester->execute([
			'id' => 'mijn-werkdag',
			'--group' => ['medewerkers', 'bestuur'],
			'--default' => true,
		]);

		self::assertSame(0, $exit);
		$display = $tester->getDisplay();
		self::assertStringContainsString('Installed template mijn-werkdag version 1 (UUID: uuid-1).', $display);
		self::assertStringContainsString('Groups: medewerkers, bestuur. Default for everyone: yes.', $display);
		self::assertStringNotContainsString('registers', $display);
	}

	public function testASecondRunSaysSoAndPassesForce(): void {
		$this->templates->expects(self::once())->method('install')
			->with('mijn-werkdag', [], false, true)
			->willReturn(self::installResult(['alreadyInstalled' => true, 'missingWidgets' => ['decidesk']]));

		$tester = new CommandTester(new TemplateInstallCommand($this->templates));
		$exit = $tester->execute(['id' => 'mijn-werkdag', '--force' => true]);

		self::assertSame(0, $exit);
		$display = $tester->getDisplay();
		self::assertStringContainsString('Already installed: template mijn-werkdag', $display);
		self::assertStringContainsString('Groups: (none). Default for everyone: no.', $display);
		self::assertStringContainsString('decidesk', $display);
	}

	public function testAnUnknownGroupExitsOne(): void {
		$this->templates->method('install')
			->willThrowException(new InvalidArgumentException('Unknown group: medewerkerz'));

		$tester = new CommandTester(new TemplateInstallCommand($this->templates));

		self::assertSame(1, $tester->execute(['id' => 'mijn-werkdag', '--group' => ['medewerkerz']]));
		self::assertStringContainsString('Unknown group: medewerkerz', $tester->getDisplay());
	}

	public function testAnUnknownIdExitsOne(): void {
		$this->templates->method('install')->willThrowException(
			new InvalidArgumentException('Unknown template: nope. Shipped templates: mijn-werkdag')
		);

		$tester = new CommandTester(new TemplateInstallCommand($this->templates));

		self::assertSame(1, $tester->execute(['id' => 'nope']));
		self::assertStringContainsString('Shipped templates: mijn-werkdag', $tester->getDisplay());
	}

	public function testAFailedInstallExitsOne(): void {
		$this->templates->method('install')->willThrowException(new RuntimeException('disk full'));

		$tester = new CommandTester(new TemplateInstallCommand($this->templates));

		self::assertSame(1, $tester->execute(['id' => 'mijn-werkdag']));
		self::assertStringContainsString('Installation failed: disk full', $tester->getDisplay());
	}
}
