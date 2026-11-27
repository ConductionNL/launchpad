<?php

/**
 * RestyleSeededTiles test
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

use OCA\LaunchPad\Repair\RestyleSeededTiles;
use OCA\LaunchPad\Support\SeedTileBrands;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Untouched seeded brand tiles get the white logo on the brand colour.
 *
 * @covers \OCA\LaunchPad\Repair\RestyleSeededTiles
 * @spec openspec/specs/default-widget-bundle/spec.md#requirement-req-dwb-007-a-default-tile-is-the-brands-white-logo-on-the-brands-colour
 */
class RestyleSeededTilesTest extends TestCase {

	/**
	 * Per update statement: the SET columns and the WHERE conditions.
	 *
	 * @var array<int, array{set: array<string, mixed>, where: array<int, string>}>
	 */
	private array $statements = [];

	/**
	 * Each brand gets one UPDATE that sets the white SVG and the brand colour
	 * and only matches a preset tile of that title still carrying the old icon.
	 *
	 * @return void
	 */
	public function testEachBrandIsRestyledOnlyWhereTheOldSeedIsLeft(): void {
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('info')->with('Restyled 3 seeded brand tile(s).');

		$this->step(db: $this->db(changed: 1))->run($output);

		$this->assertCount(expectedCount: 3, haystack: $this->statements);
		foreach (array_keys(SeedTileBrands::BRANDS) as $index => $title) {
			$brand = SeedTileBrands::BRANDS[$title];
			$this->assertSame(expected: [
				'tile_icon' => '/apps/launchpad/img/'.$brand['icon'],
				'tile_icon_type' => 'url',
				'tile_background_color' => $brand['backgroundColor'],
				'tile_text_color' => '#ffffff',
			], actual: $this->statements[$index]['set']);
			$this->assertSame(expected: [
				'tile_type = preset',
				'tile_title = '.$title,
				'tile_icon LIKE '.SeedTileBrands::LEGACY_ICONS[$title],
			], actual: $this->statements[$index]['where']);
		}
	}//end testEachBrandIsRestyledOnlyWhereTheOldSeedIsLeft()

	/**
	 * The old Nextcloud seed is an exact CSS class, the PNG seeds match by file
	 * name whatever web root resolved them.
	 *
	 * @return void
	 */
	public function testTheLegacyPatternsMatchWhatOlderVersionsSeeded(): void {
		$this->assertSame(expected: 'icon-nextcloud', actual: SeedTileBrands::LEGACY_ICONS['Nextcloud']);
		$conduction = str_replace('%', '*', SeedTileBrands::LEGACY_ICONS['Conduction']);
		$this->assertTrue(condition: fnmatch($conduction, '/custom_apps/launchpad/img/conduction-logo.png'));
		$this->assertTrue(condition: fnmatch(str_replace('%', '*', SeedTileBrands::LEGACY_ICONS['Sendent']), '/apps/launchpad/img/sendent-logo.png'));
		$this->assertFalse(condition: fnmatch(str_replace('%', '*', SeedTileBrands::LEGACY_ICONS['Sendent']), '/apps/launchpad/img/sendent-white.svg'));
	}//end testTheLegacyPatternsMatchWhatOlderVersionsSeeded()

	/**
	 * A database failure is a warning, never a failed upgrade.
	 *
	 * @return void
	 */
	public function testADatabaseFailureIsAWarning(): void {
		$db = $this->createMock(originalClassName: IDBConnection::class);
		$db->method('getQueryBuilder')->willThrowException(new RuntimeException('gone'));
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning')->with('The seeded brand tiles were not restyled: gone');
		$output->expects($this->never())->method('info');

		$this->step(db: $db)->run($output);
	}//end testADatabaseFailureIsAWarning()

	/**
	 * The step with a URL generator that resolves to `/apps/<app>/img/<file>`.
	 *
	 * @param IDBConnection $db The database.
	 *
	 * @return RestyleSeededTiles
	 */
	private function step(IDBConnection $db): RestyleSeededTiles {
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('imagePath')->willReturnCallback(static fn (string $app, string $file): string => '/apps/'.$app.'/img/'.$file);

		return new RestyleSeededTiles($db, $urls, $this->createMock(originalClassName: LoggerInterface::class));
	}//end step()

	/**
	 * A database whose query builders record each UPDATE.
	 *
	 * @param int $changed Rows each statement reports as changed.
	 *
	 * @return IDBConnection
	 */
	private function db(int $changed): IDBConnection {
		$db = $this->createMock(originalClassName: IDBConnection::class);
		$db->method('getQueryBuilder')->willReturnCallback(function () use ($changed): IQueryBuilder {
			$index = count($this->statements);
			$this->statements[$index] = ['set' => [], 'where' => []];
			$params = [];

			$expr = $this->createMock(originalClassName: IExpressionBuilder::class);
			$expr->method('eq')->willReturnCallback(static function (string $x, string $y) use (&$params): string {
				return $x.' = '.$params[$y];
			});
			$expr->method('like')->willReturnCallback(static function (string $x, string $y) use (&$params): string {
				return $x.' LIKE '.$params[$y];
			});

			$qb = $this->createMock(originalClassName: IQueryBuilder::class);
			$qb->method('expr')->willReturn($expr);
			$qb->method('createNamedParameter')->willReturnCallback(static function (mixed $value) use (&$params): string {
				$name = ':p'.count($params);
				$params[$name] = $value;
				return $name;
			});
			$qb->method('update')->willReturnSelf();
			$qb->method('set')->willReturnCallback(function (string $key, string $value) use ($qb, $index, &$params): IQueryBuilder {
				$this->statements[$index]['set'][$key] = $params[$value];
				return $qb;
			});
			$record = function (string $condition) use ($qb, $index): IQueryBuilder {
				$this->statements[$index]['where'][] = $condition;
				return $qb;
			};
			$qb->method('where')->willReturnCallback($record);
			$qb->method('andWhere')->willReturnCallback($record);
			$qb->method('executeStatement')->willReturn($changed);

			return $qb;
		});

		return $db;
	}//end db()
}//end class
