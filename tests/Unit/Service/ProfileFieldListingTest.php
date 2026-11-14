<?php

/**
 * ProfileFieldListingTest
 *
 * Unit tests for ProfileFieldListing: per-person visible fields in
 * definition order, skipping empty and hidden fields and people left with
 * none.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\ProfileFieldListing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ProfileFieldListing.
 */
#[CoversClass(ProfileFieldListing::class)]
class ProfileFieldListingTest extends TestCase {
	/**
	 * The widget's field definitions.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function definitions(): array {
		return [
			['key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'visibility' => 'public'],
			['key' => 'room', 'label' => 'Room', 'type' => 'text', 'visibility' => 'private'],
			['key' => 'skills', 'label' => 'Skills', 'type' => 'tags', 'visibility' => 'public'],
		];
	}//end definitions()

	/**
	 * Fields come in definition order, only with values and when visible.
	 *
	 * @return void
	 */
	public function testVisibleFieldsInDefinitionOrder(): void {
		$listing = new ProfileFieldListing(
			definitions: self::definitions(),
			canSee: static fn (string $ownerId, array $definition): bool => $definition['visibility'] === 'public',
		);

		$result = $listing->fieldsByPerson(
			grouped: [
				'alice' => ['skills' => ['php', 'vue'], 'room' => ['1.01'], 'phone' => ['555']],
			]
		);

		$this->assertSame(
			[
				'alice' => [
					['key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'values' => ['555']],
					['key' => 'skills', 'label' => 'Skills', 'type' => 'tags', 'values' => ['php', 'vue']],
				],
			],
			$result
		);
	}//end testVisibleFieldsInDefinitionOrder()

	/**
	 * A person with no visible, non-empty field is left out.
	 *
	 * @return void
	 */
	public function testPersonWithoutVisibleFieldsIsOmitted(): void {
		$listing = new ProfileFieldListing(
			definitions: self::definitions(),
			canSee: static fn (string $ownerId, array $definition): bool => $ownerId === 'bob',
		);

		$result = $listing->fieldsByPerson(
			grouped: [
				'alice' => ['phone' => ['1']],
				'bob' => ['phone' => [], 'room' => ['2.02']],
				'carol' => [],
			]
		);

		$this->assertSame(
			['bob' => [['key' => 'room', 'label' => 'Room', 'type' => 'text', 'values' => ['2.02']]]],
			$result
		);
	}//end testPersonWithoutVisibleFieldsIsOmitted()

	/**
	 * The visibility check gets the owner id as a string and the definition,
	 * and is not asked about fields without values.
	 *
	 * @return void
	 */
	public function testVisibilityCheckArguments(): void {
		$calls = [];
		$listing = new ProfileFieldListing(
			definitions: self::definitions(),
			canSee: static function (string $ownerId, array $definition) use (&$calls): bool {
				$calls[] = [$ownerId, $definition['key']];
				return true;
			},
		);

		$listing->fieldsByPerson(grouped: [42 => ['room' => ['x']]]);

		$this->assertSame([['42', 'room']], $calls);
	}//end testVisibilityCheckArguments()
}//end class
