<?php

/**
 * CatalogueAppTest
 *
 * Unit tests for the CatalogueApp entity: every link type an administrator
 * can choose is written on insert.
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

use OCA\LaunchPad\Db\CatalogueApp;
use PHPUnit\Framework\TestCase;

/**
 * The link type column has no database default, so the entity must mark it
 * for insert whichever value is set.
 *
 * @covers \OCA\LaunchPad\Db\CatalogueApp
 */
class CatalogueAppTest extends TestCase {

	/**
	 * Setting either link type on a new entry marks the field for insert.
	 *
	 * @return void
	 */
	public function testEveryLinkTypeIsWrittenOnInsert(): void {
		foreach (['url', 'app'] as $linkType) {
			$app = new CatalogueApp();
			$app->setLinkType($linkType);

			self::assertArrayHasKey(key: 'linkType', array: $app->getUpdatedFields(), message: $linkType);
			self::assertSame(expected: $linkType, actual: $app->getLinkType());
		}
	}//end testEveryLinkTypeIsWrittenOnInsert()
}//end class
