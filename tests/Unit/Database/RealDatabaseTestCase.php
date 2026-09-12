<?php

/**
 * RealDatabaseTestCase
 *
 * Base for tests that write through LaunchPad's real services into a real
 * database and then read the STORED row back with SQL.
 *
 * 🔴 WHY THESE EXIST. #612 shipped an importer that never set `created_at`.
 * Every insert failed with SQLSTATE 23502 and was reported as "skipped", and
 * the unit tests stayed green because they mock the mapper, so no INSERT ever
 * reached a table with a NOT NULL constraint. A mocked test checks what the
 * code ASKS for. These check what the database ACCEPTED.
 *
 * WHERE THEY RUN. They need a live Nextcloud. phpunit.xml sets
 * PHPUNIT_USE_NC_BOOTSTRAP=1, so tests/bootstrap.php boots the real server
 * whenever this tree sits inside an installed instance, which is what CI's
 * PHPUnit job is. Anywhere else, such as a plain clone, they skip and say why.
 *
 * Every row a test creates is registered with `cleanup()`, and tearDown runs
 * those whether or not the test passed.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Database
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Database;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Server;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @SuppressWarnings(PHPMD.StaticAccess) Server::get() is the only way in from a test.
 */
abstract class RealDatabaseTestCase extends TestCase {
	/**
	 * Cleanup callbacks, run in reverse order in tearDown.
	 *
	 * @var array<int, callable>
	 */
	private array $cleanups = [];

	/**
	 * Skip unless a live Nextcloud, and so a live database, is bootstrapped.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if (class_exists(class: '\OC', autoload: false) === false) {
			$this->markTestSkipped(
				message: 'Needs a live Nextcloud database. CI boots one (phpunit.xml sets '
				. 'PHPUNIT_USE_NC_BOOTSTRAP=1); a plain clone does not.'
			);
		}
	}//end setUp()

	/**
	 * Run every registered cleanup, newest first, even after a failure.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (array_reverse($this->cleanups) as $cleanup) {
			try {
				$cleanup();
			} catch (Throwable) {
				// A cleanup that fails must not hide the test's own verdict.
			}
		}

		$this->cleanups = [];
		parent::tearDown();
	}//end tearDown()

	/**
	 * Register a cleanup to run in tearDown.
	 *
	 * @param callable $cleanup The cleanup.
	 *
	 * @return void
	 */
	protected function cleanup(callable $cleanup): void {
		$this->cleanups[] = $cleanup;
	}//end cleanup()

	/**
	 * A unique id for this test's rows.
	 *
	 * @param string $prefix A readable prefix.
	 *
	 * @return string The id.
	 */
	protected function uniqueId(string $prefix): string {
		return $prefix . '-' . bin2hex(random_bytes(4));
	}//end uniqueId()

	/**
	 * Provision a real Nextcloud account, removed again in tearDown.
	 *
	 * @param string $prefix A readable prefix.
	 *
	 * @return string The new user's id.
	 */
	protected function makeUser(string $prefix): string {
		$uid = $this->uniqueId(prefix: $prefix);
		$user = Server::get(IUserManager::class)->createUser($uid, 'Db-' . bin2hex(random_bytes(6)) . 'A1!');
		$this->assertNotFalse($user, 'CONTROL: the test account could be created');
		$this->cleanup(static function () use ($uid): void {
			Server::get(IUserManager::class)->get($uid)?->delete();
		});

		return $uid;
	}//end makeUser()

	/**
	 * Read the stored rows of a table matching one column, straight from SQL.
	 *
	 * @param string     $table  Table name without prefix.
	 * @param string     $column Column to match.
	 * @param int|string $value  Value to match.
	 *
	 * @return array<int, array<string, mixed>> The rows.
	 */
	protected function storedRows(string $table, string $column, int|string $value): array {
		$qb = Server::get(IDBConnection::class)->getQueryBuilder();
		$type = IQueryBuilder::PARAM_STR;
		if (is_int($value) === true) {
			$type = IQueryBuilder::PARAM_INT;
		}

		$qb->select('*')
			->from($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, $type)));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}//end storedRows()

	/**
	 * Delete the rows of a table matching one column.
	 *
	 * @param string     $table  Table name without prefix.
	 * @param string     $column Column to match.
	 * @param int|string $value  Value to match.
	 *
	 * @return void
	 */
	protected function deleteRows(string $table, string $column, int|string $value): void {
		$qb = Server::get(IDBConnection::class)->getQueryBuilder();
		$type = IQueryBuilder::PARAM_STR;
		if (is_int($value) === true) {
			$type = IQueryBuilder::PARAM_INT;
		}

		$qb->delete($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, $type)));
		$qb->executeStatement();
	}//end deleteRows()

	/**
	 * Assert a stored row carries a value in every named column.
	 *
	 * @param array<string, mixed> $row     The stored row.
	 * @param array<int, string>   $columns Columns that must be non-empty.
	 *
	 * @return void
	 */
	protected function assertColumnsFilled(array $row, array $columns): void {
		foreach ($columns as $column) {
			$this->assertArrayHasKey($column, $row, 'column ' . $column . ' is absent from the stored row');
			$this->assertNotNull($row[$column], 'column ' . $column . ' was stored as NULL');
			$this->assertNotSame('', (string)$row[$column], 'column ' . $column . ' was stored empty');
		}
	}//end assertColumnsFilled()
}//end class
