<?php

/**
 * ProfileValuesDatabaseTest
 *
 * Profile values against a real database (REQ-PEX-003): a saved tag is stored
 * lower-cased for the search, and the LIKE search finds it on part of the word.
 * The unit tests use an in-memory mapper; this one proves the SQL.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Database
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Database;

use OCA\LaunchPad\Db\ProfileValueMapper;
use OCP\Server;

/**
 * @SuppressWarnings(PHPMD.StaticAccess) Server::get() is the only way in from a test.
 */
class ProfileValuesDatabaseTest extends RealDatabaseTestCase {
	/**
	 * A tag is stored per row and found on part of the word, case-insensitively.
	 *
	 * @return void
	 */
	public function testATagIsStoredAndFoundOnPartOfTheWord(): void {
		$owner = $this->makeUser(prefix: 'db-profile');
		$this->cleanup(function () use ($owner): void {
			$this->deleteRows('launchpad_profile_values', 'user_id', $owner);
		});

		$mapper = Server::get(ProfileValueMapper::class);
		$mapper->replaceValues(userId: $owner, fieldKey: 'expertise', values: ['Subsidies', 'Omgevingswet'], source: 'self');

		$rows = $this->storedRows('launchpad_profile_values', 'user_id', $owner);
		$this->assertCount(2, $rows);
		$this->assertColumnsFilled($rows[0], ['user_id', 'field_key', 'value', 'value_search', 'source', 'updated_at']);

		$found = array_map(static fn ($row): string => $row->getUserId(), $mapper->search(needle: 'subsidie'));
		$this->assertContains($owner, $found);

		$mapper->replaceValues(userId: $owner, fieldKey: 'expertise', values: ['Omgevingswet'], source: 'self');
		$this->assertCount(1, $this->storedRows('launchpad_profile_values', 'user_id', $owner), 'a save replaces the old tags');
	}//end testATagIsStoredAndFoundOnPartOfTheWord()
}//end class
