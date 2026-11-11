<?php

/**
 * Dashboard list command golden test
 *
 * Pins what `launchpad:dashboard:list` prints and returns: refused
 * filters, the user, group and instance-wide scopes, the status filter,
 * the JSON envelope, the table, the empty message and `--quiet`.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Command;

use OCA\LaunchPad\Command\DashboardListCommand;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\CommandService;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The printed output of the dashboard list command, case by case.
 */
class DashboardListCommandGoldenTest extends TestCase {

	/** @var list<string> */
	private array $log = [];

	private static function dashboard(string $uuid, string $status, ?string $owner = null): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid($uuid);
		$dashboard->setName('A dashboard with a very long name ' . $uuid);
		$dashboard->setPublicationStatus($status);
		$dashboard->setUserId($owner);
		return $dashboard;
	}

	/**
	 * @param array<string, mixed> $input The input.
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private function runList(array $input): array {
		$this->log = [];
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findByUserId')->willReturnCallback(function (string $userId): array {
			$this->log[] = 'byUser ' . $userId;
			return ['k' => self::dashboard('u1', 'draft', $userId)];
		});
		$mapper->method('findByGroup')->willReturnCallback(function (string $groupId): array {
			$this->log[] = 'byGroup ' . $groupId;
			return [];
		});
		$mapper->method('findAdminTemplates')->willReturnCallback(function (): array {
			$this->log[] = 'templates';
			return [self::dashboard('t1', 'published')];
		});
		$mapper->method('findByParent')->willReturnCallback(function (?string $parentUuid): array {
			$this->log[] = 'roots';
			return [self::dashboard('r1', 'draft', 'bob'), self::dashboard('', 'published')];
		});
		$mapper->method('findDescendants')->willReturnCallback(function (string $ancestorUuid): array {
			$this->log[] = 'descendants ' . $ancestorUuid;
			return [self::dashboard('c1', 'published', 'bob')];
		});
		$users = $this->createMock(IUserManager::class);
		$users->method('userExists')->willReturnCallback(fn (string $uid): bool => $uid === 'alice');

		$tester = new CommandTester(new DashboardListCommand(
			new CommandService(new NullLogger()),
			$this->createMock(IUserSession::class),
			$mapper,
			$users
		));
		$code = $tester->execute($input);
		return [$code, $tester->getDisplay(), $this->log];
	}

	public function testRefusedFilters(): void {
		[$code, $out, $log] = $this->runList(['--status' => 'gone', '--user' => 'nobody', '--json' => true]);
		$this->assertSame(CommandService::EXIT_INVALID_ARGS, $code);
		$this->assertStringContainsString('"message":"Invalid --status value: gone"', $out);
		$this->assertStringContainsString('"context":{"allowed":["draft","published","scheduled"]}', $out);
		$this->assertSame([], $log);

		[$code, $out] = $this->runList(['--user' => 'nobody', '--json' => true]);
		$this->assertSame(CommandService::EXIT_NOT_FOUND, $code);
		$this->assertStringContainsString('"message":"User not found: nobody"', $out);
		$this->assertStringContainsString('"context":{"userId":"nobody"}', $out);
	}

	public function testScopesAndOutput(): void {
		$header = sprintf('%-36s  %-20s  %-14s  %-12s  %s', 'UUID', 'NAME', 'TYPE', 'STATUS', 'OWNER');
		$this->assertSame(
			[0, $header . "\n"
				. sprintf('%-36s  %-20s  %-14s  %-12s  %s', 't1', 'A dashboard with a..', 'user', 'published', '') . "\n"
				. sprintf('%-36s  %-20s  %-14s  %-12s  %s', 'c1', 'A dashboard with a..', 'user', 'published', 'bob') . "\n"
				. sprintf('%-36s  %-20s  %-14s  %-12s  %s', '', 'A dashboard with a..', 'user', 'published', '') . "\n",
				['templates', 'roots', 'descendants r1']],
			$this->runList(['--status' => 'published'])
		);
		$this->assertSame([0, "No dashboards match the supplied filters.\n", ['byGroup staff']], $this->runList(['--group' => 'staff']));
		$this->assertSame([0, '', ['byUser alice']], $this->runList(['--user' => 'alice', '--quiet' => true]));

		[$code, $out, $log] = $this->runList(['--user' => 'alice', '--group' => 'staff', '--json' => true]);
		$this->assertSame([0, ['byUser alice']], [$code, $log]);
		$this->assertStringContainsString(
			'"data":{"dashboards":[{"uuid":"u1","name":"A dashboard with a very long name u1","type":"user","owner":"alice","group":"","publicationStatus":"draft"}],"count":1}',
			$out
		);
	}
}
