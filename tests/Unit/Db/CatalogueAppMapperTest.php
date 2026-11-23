<?php

/**
 * CatalogueAppMapperTest
 *
 * Unit tests for CatalogueAppMapper: the catalogue in its sort order, then by name and id, and the lookup by uuid.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\CatalogueAppMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\CatalogueAppMapper
 * @uses   \OCA\LaunchPad\Db\CatalogueApp
 */
class CatalogueAppMapperTest extends TestCase {

	/**
	 * The catalogue is ordered by sort order, then name, then id, with no filter.
	 *
	 * @return void
	 */
	public function testFindAllOrderedSortsBySortOrderNameAndId(): void {
		$recorder       = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 2, 'name' => 'Agenda'], ['id' => 1, 'name' => 'Zaken']];

		$apps = (new CatalogueAppMapper(db: $recorder->connection()))->findAllOrdered();

		$this->assertSame(expected: ['Agenda', 'Zaken'], actual: array_map(static fn ($app): string => $app->getName(), $apps));
		$this->assertSame(
			expected: [
				'qb1.from("launchpad_catalogue_apps", null)',
				'qb1.orderBy("sort_order", null)',
				'qb1.addOrderBy("name", null)',
				'qb1.addOrderBy("id", null)',
			],
			actual: array_values(preg_grep(pattern: '/\.(from|orderBy|addOrderBy|where)\(/', array: $recorder->calls))
		);
	}//end testFindAllOrderedSortsBySortOrderNameAndId()

	/**
	 * The lookup by uuid binds the uuid and throws when nothing matches.
	 *
	 * @return void
	 */
	public function testFindByUuid(): void {
		$recorder       = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 7, 'uuid' => 'app-7']];

		$this->assertSame(expected: 7, actual: (new CatalogueAppMapper(db: $recorder->connection()))->findByUuid(uuid: 'app-7')->getId());
		$this->assertContains(needle: 'qb1.where("eq(\"uuid\", \":p1\", null)")', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.createNamedParameter("app-7", 2, null)', haystack: $recorder->calls);

		$this->expectException(exception: DoesNotExistException::class);
		(new CatalogueAppMapper(db: (new QueryRecorder(test: $this))->connection()))->findByUuid(uuid: 'missing');
	}//end testFindByUuid()
}//end class
