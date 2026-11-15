<?php

/**
 * MapperCountAndStampGoldenTest
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Db;

use OCA\LaunchPad\Db\AcknowledgementMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardReactionMapper;
use OCA\LaunchPad\Db\DashboardTranslationMapper;
use OCA\LaunchPad\Db\KioskPlaylistMapper;
use OCA\LaunchPad\Db\MetadataFieldMapper;
use OCA\LaunchPad\Db\PublicShareMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IFunctionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins what the count and timestamp-writing mapper methods return for each
 * row a COUNT query can yield, how many cursors they close, and the shape
 * of every bound value (a `*_at` stamp shows as its format). The fixture
 * was recorded with LAUNCHPAD_GOLDEN_RECORD=1.
 *
 * @covers \OCA\LaunchPad\Db\AcknowledgementMapper
 * @covers \OCA\LaunchPad\Db\DashboardMapper
 * @covers \OCA\LaunchPad\Db\DashboardReactionMapper
 * @covers \OCA\LaunchPad\Db\DashboardTranslationMapper
 * @covers \OCA\LaunchPad\Db\KioskPlaylistMapper
 * @covers \OCA\LaunchPad\Db\MetadataFieldMapper
 * @covers \OCA\LaunchPad\Db\PublicShareMapper
 * @covers \OCA\LaunchPad\Db\WidgetPlacementMapper
 * @covers \OCA\LaunchPad\Db\ColumnMatchQueries
 */
class MapperCountAndStampGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/mapper-count-and-stamp.json';

	/**
	 * Rows a COUNT query can come back with, by case name.
	 */
	private const ROWS = [
		'three' => ['cnt' => '3'],
		'zero' => ['cnt' => '0'],
		'null count' => ['cnt' => null],
		'no cnt column' => ['other' => '5'],
		'no row' => false,
	];

	/**
	 * The mapper calls under test: [class, method, arguments].
	 */
	private const CALLS = [
		'countByGroup' => [DashboardMapper::class, 'countByGroup', ['g1']],
		'countPersonalByUserId' => [DashboardMapper::class, 'countPersonalByUserId', ['alice']],
		'countChildrenByParent' => [DashboardMapper::class, 'countChildrenByParent', ['p-1']],
		'deactivateAllForUser' => [DashboardMapper::class, 'deactivateAllForUser', ['alice']],
		'setActive' => [DashboardMapper::class, 'setActive', [7, 'alice']],
		'clearGroupDefaults' => [DashboardMapper::class, 'clearGroupDefaults', ['g1', 'u-1']],
		'clearGroupDefaults no except' => [DashboardMapper::class, 'clearGroupDefaults', ['g1']],
		'setGroupDefaultUuid' => [DashboardMapper::class, 'setGroupDefaultUuid', ['g1', 'u-1']],
		'clearDefaultTemplates' => [DashboardMapper::class, 'clearDefaultTemplates', []],
		'countReactorsByEmoji' => [DashboardReactionMapper::class, 'countReactorsByEmoji', ['d-1', 'x']],
		'existsFor' => [AcknowledgementMapper::class, 'existsFor', ['a-1', 'alice', 2]],
		'countValuesForField' => [MetadataFieldMapper::class, 'countValuesForField', [4]],
		'countByDashboardUuid' => [DashboardTranslationMapper::class, 'countByDashboardUuid', ['d-1']],
		'countByDashboardId' => [WidgetPlacementMapper::class, 'countByDashboardId', [9]],
		'share softRevoke' => [PublicShareMapper::class, 'softRevoke', [5]],
		'share incrementViewCount seen' => [PublicShareMapper::class, 'incrementViewCount', [5, 'tok', '10.0.0.1']],
		'share incrementViewCount new' => [PublicShareMapper::class, 'incrementViewCount', [6, 'tok', '10.0.0.2']],
		'share revokeByDashboardUuid' => [PublicShareMapper::class, 'revokeByDashboardUuid', ['d-1']],
		'kiosk softRevoke' => [KioskPlaylistMapper::class, 'softRevoke', [5]],
	];

	/**
	 * Every call with every count row.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function cases(): array {
		$cases = [];
		foreach (array_keys(self::CALLS) as $call) {
			foreach (array_keys(self::ROWS) as $row) {
				$cases[$call . ' / ' . $row] = [$call, $row];
			}
		}

		return $cases;
	}

	/**
	 * The call answers, closes and binds what it did when recorded.
	 *
	 * @param string $call The call case.
	 * @param string $row  The row case.
	 *
	 * @return void
	 */
	#[DataProvider('cases')]
	public function testCallMatchesTheRecording(string $call, string $row): void {
		$log = ['bound' => [], 'closed' => 0, 'fetched' => 0];
		[$class, $method, $args] = self::CALLS[$call];
		$mapper = $this->mapper(class: $class, db: $this->connection(row: self::ROWS[$row], log: $log));
		$actual = ['returned' => $mapper->$method(...$args)] + $log;

		$key = $call . ' / ' . $row;
		if (getenv('LAUNCHPAD_GOLDEN_RECORD') === '1') {
			$recorded = is_file(self::FIXTURE) ? json_decode((string)file_get_contents(self::FIXTURE), true) : [];
			$recorded[$key] = $actual;
			ksort($recorded);
			file_put_contents(self::FIXTURE, json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true)[$key] ?? null;
		$this->assertNotNull($expected, 'no recording for ' . $key);
		$this->assertSame($expected, $actual);
	}

	/**
	 * Build the mapper on the recording connection.
	 *
	 * @param string        $class The mapper class.
	 * @param IDBConnection $db    The connection.
	 *
	 * @return object The mapper.
	 */
	private function mapper(string $class, IDBConnection $db): object {
		if ($class === PublicShareMapper::class) {
			$cache = $this->createMock(ICache::class);
			$cache->method('hasKey')->willReturnCallback(static fn (string $key): bool => str_ends_with($key, md5('10.0.0.1')));
			$factory = $this->createMock(ICacheFactory::class);
			$factory->method('createDistributed')->willReturn($cache);
			$factory->method('createLocal')->willReturn($cache);
			return new PublicShareMapper($db, $factory, $this->createMock(IURLGenerator::class));
		}

		if ($class === KioskPlaylistMapper::class) {
			return new KioskPlaylistMapper($db, $this->createMock(IURLGenerator::class));
		}

		return new $class($db);
	}

	/**
	 * A connection whose builders log bound values and whose results
	 * yield one row once.
	 *
	 * @param array<string, mixed>|false $row The row.
	 * @param array<string, mixed>       $log The log, written by reference.
	 *
	 * @return IDBConnection The connection double.
	 */
	private function connection(array|false $row, array &$log): IDBConnection {
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturnCallback(function () use ($row, &$log): IQueryBuilder {
			return $this->builder(row: $row, log: $log);
		});

		return $db;
	}

	/**
	 * A fluent query builder double.
	 *
	 * @param array<string, mixed>|false $row The row its query yields.
	 * @param array<string, mixed>       $log The log, written by reference.
	 *
	 * @return IQueryBuilder The builder double.
	 */
	private function builder(array|false $row, array &$log): IQueryBuilder {
		$qb = $this->createMock(IQueryBuilder::class);
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method($this->anything())->willReturn('expr');
		$func = $this->createMock(IFunctionBuilder::class);
		$func->method($this->anything())->willReturn($this->createMock(IQueryFunction::class));
		$qb->method('expr')->willReturn($expr);
		$qb->method('func')->willReturn($func);
		$qb->method('createFunction')->willReturn($this->createMock(IQueryFunction::class));
		$qb->method('createNamedParameter')->willReturnCallback(static function (mixed $value, mixed $type = IQueryBuilder::PARAM_STR) use (&$log): string {
			$shown = $value;
			if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
				$shown = 'stamp Y-m-d H:i:s';
			} elseif ($value instanceof \DateTimeInterface) {
				$shown = 'DateTime';
			}

			$log['bound'][] = [$shown, $type];
			return ':p' . count($log['bound']);
		});
		$qb->method('executeStatement')->willReturn(2);
		$qb->method('executeQuery')->willReturnCallback(function () use ($row, &$log): IResult {
			$result = $this->createMock(IResult::class);
			$rows = [$row];
			$next = static function () use (&$rows, &$log): array|false {
				$log['fetched']++;
				return array_shift($rows) ?? false;
			};
			$result->method('fetch')->willReturnCallback($next);
			$result->method('fetchAssociative')->willReturnCallback($next);
			$result->method('closeCursor')->willReturnCallback(static function () use (&$log): bool {
				$log['closed']++;
				return true;
			});
			return $result;
		});
		foreach (['select', 'from', 'where', 'andWhere', 'update', 'set', 'delete', 'setMaxResults', 'orderBy'] as $fluent) {
			$qb->method($fluent)->willReturnSelf();
		}

		return $qb;
	}
}
