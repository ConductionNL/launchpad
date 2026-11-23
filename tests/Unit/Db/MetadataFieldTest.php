<?php

/**
 * MetadataFieldTest
 *
 * Unit tests for the MetadataField entity: every field type an administrator
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

use OCA\LaunchPad\Db\MetadataField;
use PHPUnit\Framework\TestCase;

/**
 * The type column has no database default, so the entity must mark it for
 * insert whichever value is set.
 *
 * @covers \OCA\LaunchPad\Db\MetadataField
 */
class MetadataFieldTest extends TestCase {

	/**
	 * Setting any field type on a new field marks it for insert.
	 *
	 * @return void
	 */
	public function testEveryFieldTypeIsWrittenOnInsert(): void {
		foreach (MetadataField::VALID_TYPES as $type) {
			$field = new MetadataField();
			$field->setType($type);

			self::assertArrayHasKey(key: 'type', array: $field->getUpdatedFields(), message: $type);
			self::assertSame(expected: $type, actual: $field->getType());
		}
	}//end testEveryFieldTypeIsWrittenOnInsert()
}//end class
