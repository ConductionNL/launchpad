<?php

/**
 * KeyContainerChildren unit tests (REQ-WDG-050).
 *
 * @category  Test
 * @package   Unit\Repair
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

namespace Unit\Repair;

use OCA\LaunchPad\Repair\KeyContainerChildren;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The repair step keys the children of stored wells and tabs widgets.
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
class KeyContainerChildrenTest extends TestCase {

	/**
	 * The content written per placement id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $written = [];

	/**
	 * Only the widgets with a child without a key are written, with keys.
	 *
	 * @return void
	 */
	public function testKeysOnlyTheWidgetsWithAChildWithoutAKey(): void {
		$rows   = [
			['id' => 12, 'content' => json_encode(value: ['placements' => [['type' => 'tile'], ['type' => 'tile', 'id' => 4]]])],
			['id' => 13, 'content' => json_encode(value: ['placements' => [['type' => 'tile', 'uuid' => 'a']]])],
			['id' => 14, 'content' => json_encode(value: ['tabs' => [['id' => 't', 'placements' => [['type' => 'clock']]]]])],
			['id' => 15, 'content' => 'not json'],
		];
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')->with('2 well(s) or tabs widget(s) got keys for their widgets.');

		$step = new KeyContainerChildren(db: $this->db(rows: $rows), logger: $this->createMock(originalClassName: LoggerInterface::class));
		$this->assertSame(expected: 'Give every widget inside a well or tabs widget a key of its own', actual: $step->getName());
		$step->run(output: $output);

		$this->assertSame(expected: [12, 14], actual: array_keys($this->written));
		$this->assertSame(expected: '12-0', actual: $this->written[12]['placements'][0]['uuid']);
		$this->assertSame(expected: 4, actual: $this->written[12]['placements'][1]['id']);
		$this->assertMatchesRegularExpression(pattern: '/^[0-9a-f]{16}$/', string: $this->written[14]['tabs'][0]['placements'][0]['uuid']);
	}//end testKeysOnlyTheWidgetsWithAChildWithoutAKey()

	/**
	 * A failure is a warning, never a failed upgrade.
	 *
	 * @return void
	 */
	public function testAFailureIsAWarningNotAFailedUpgrade(): void {
		$db = $this->createMock(originalClassName: IDBConnection::class);
		$db->method('getQueryBuilder')->willThrowException(new RuntimeException(message: 'db down'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with('The widgets inside wells were not given keys: db down');

		(new KeyContainerChildren(db: $db, logger: $this->createMock(originalClassName: LoggerInterface::class)))->run(output: $output);
	}//end testAFailureIsAWarningNotAFailedUpgrade()

	/**
	 * A database whose select answers the rows and whose updates are recorded.
	 *
	 * @param array<int, array<string, mixed>> $rows The stored wells and tabs widgets.
	 *
	 * @return IDBConnection
	 */
	private function db(array $rows): IDBConnection {
		$db = $this->createMock(originalClassName: IDBConnection::class);
		$db->method('getQueryBuilder')->willReturnCallback(function () use ($rows): IQueryBuilder {
			$params = [];
			$set    = [];
			$expr   = $this->createMock(originalClassName: IExpressionBuilder::class);
			$expr->method('eq')->willReturnCallback(static fn (string $x, string $y): string => $y);
			$expr->method('in')->willReturn('widget_id IN');

			$qb = $this->createMock(originalClassName: IQueryBuilder::class);
			$qb->method('expr')->willReturn($expr);
			$qb->method('createNamedParameter')->willReturnCallback(static function (mixed $value) use (&$params): string {
				$name          = ':p' . count($params);
				$params[$name] = $value;
				return $name;
			});
			$qb->method('select')->willReturnSelf();
			$qb->method('from')->willReturnSelf();
			$qb->method('update')->willReturnSelf();
			$qb->method('set')->willReturnCallback(static function (string $key, string $value) use ($qb, &$set, &$params): IQueryBuilder {
				$set[$key] = $params[$value];
				return $qb;
			});
			$qb->method('where')->willReturnCallback(function (string $condition) use ($qb, &$set, &$params): IQueryBuilder {
				if (isset($params[$condition]) === true) {
					$set['id'] = $params[$condition];
				}

				return $qb;
			});
			$qb->method('executeStatement')->willReturnCallback(function () use (&$set): int {
				$this->written[$set['id']] = json_decode(json: $set['content'], associative: true);
				return 1;
			});
			$result = $this->createMock(originalClassName: IResult::class);
			$result->method('fetch')->willReturnOnConsecutiveCalls(...[...$rows, false]);
			$qb->method('executeQuery')->willReturn($result);

			return $qb;
		});

		return $db;
	}//end db()
}//end class
