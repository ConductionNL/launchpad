<?php

/**
 * WidgetPlacementMapperAnnouncementKeyTest
 *
 * REQ-ACK-009: a placement that asks for confirmation gets a stable
 * announcement key on every save, and a key once given is kept.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-a-placement-that-asks-for-confirmation-has-a-key-req-ack-009
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use PHPUnit\Framework\TestCase;

/**
 * The announcement key of a placement that asks for confirmation.
 *
 * @covers \OCA\LaunchPad\Db\WidgetPlacementMapper
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses   \OCA\LaunchPad\Service\UuidV4
 */
class WidgetPlacementMapperAnnouncementKeyTest extends TestCase {

	/**
	 * A UUID v4 in its canonical form.
	 *
	 * @var string
	 */
	private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

	/**
	 * A placement asking for confirmation or not, with an optional key.
	 *
	 * @param int         $requires Whether it asks for confirmation (0/1).
	 * @param string|null $key      Its announcement key.
	 *
	 * @return WidgetPlacement The placement.
	 */
	private function placement(int $requires, ?string $key = null): WidgetPlacement {
		$placement = new WidgetPlacement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setRequiresAcknowledgement($requires);
		if ($key !== null) {
			$placement->setAnnouncementKey($key);
		}

		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $placement;
	}//end placement()

	/**
	 * A placement that asks for confirmation gets a key, once.
	 *
	 * @return void
	 */
	public function testAPlacementThatAsksForConfirmationGetsAKeyOnce(): void {
		$placement = $this->placement(requires: 1);

		$this->assertTrue(condition: WidgetPlacementMapper::ensureAnnouncementKey(placement: $placement));
		$key = (string)$placement->getAnnouncementKey();
		$this->assertMatchesRegularExpression(pattern: self::UUID, string: $key);

		// A later save keeps it, so the readers' confirmations stay with it.
		$this->assertFalse(condition: WidgetPlacementMapper::ensureAnnouncementKey(placement: $placement));
		$this->assertSame(expected: $key, actual: $placement->getAnnouncementKey());
	}//end testAPlacementThatAsksForConfirmationGetsAKeyOnce()

	/**
	 * A stored key and an empty key are told apart; no confirmation, no key.
	 *
	 * @return void
	 */
	public function testOnlyAMissingKeyIsGiven(): void {
		$kept = $this->placement(requires: 1, key: 'shared-key');
		$this->assertFalse(condition: WidgetPlacementMapper::ensureAnnouncementKey(placement: $kept));
		$this->assertSame(expected: 'shared-key', actual: $kept->getAnnouncementKey());

		$empty = $this->placement(requires: 1, key: '');
		$this->assertTrue(condition: WidgetPlacementMapper::ensureAnnouncementKey(placement: $empty));
		$this->assertMatchesRegularExpression(pattern: self::UUID, string: (string)$empty->getAnnouncementKey());

		$plain = $this->placement(requires: 0);
		$this->assertFalse(condition: WidgetPlacementMapper::ensureAnnouncementKey(placement: $plain));
		$this->assertNull(actual: $plain->getAnnouncementKey());
	}//end testOnlyAMissingKeyIsGiven()

	/**
	 * Insert and update give the key before writing, and send it with the placement.
	 *
	 * @return void
	 */
	public function testInsertAndUpdateGiveTheKeyAndTheWireCarriesIt(): void {
		$recorder = new QueryRecorder($this);
		$mapper   = new WidgetPlacementMapper($recorder->connection());

		$inserted = $mapper->insert(entity: $this->placement(requires: 1));
		$this->assertMatchesRegularExpression(pattern: self::UUID, string: (string)$inserted->getAnnouncementKey());
		$this->assertSame(expected: $inserted->getAnnouncementKey(), actual: $inserted->jsonSerialize()['announcementKey']);

		$existing = $this->placement(requires: 1);
		$existing->setId(4);
		$updated = $mapper->update(entity: $existing);
		$this->assertMatchesRegularExpression(pattern: self::UUID, string: (string)$updated->getAnnouncementKey());

		$plain = $mapper->insert(entity: $this->placement(requires: 0));
		$this->assertNull(actual: $plain->getAnnouncementKey());
	}//end testInsertAndUpdateGiveTheKeyAndTheWireCarriesIt()

	/**
	 * The repair query finds the placements that ask for confirmation without a key.
	 *
	 * @return void
	 */
	public function testFindsThePlacementsThatAskForConfirmationWithoutAKey(): void {
		$recorder       = new QueryRecorder($this);
		$recorder->rows = [['id' => '9', 'requires_acknowledgement' => '1']];
		$found          = (new WidgetPlacementMapper($recorder->connection()))->findAcknowledged(which: WidgetPlacementMapper::UNKEYED);

		$this->assertCount(expectedCount: 1, haystack: $found);
		$this->assertSame(expected: 9, actual: $found[0]->getId());
		$calls = implode(separator: "\n", array: $recorder->calls);
		$this->assertStringContainsString(needle: 'qb1.expr.eq("requires_acknowledgement", ":p1", null)', haystack: $calls);
		$this->assertStringContainsString(needle: 'qb1.expr.isNull("announcement_key")', haystack: $calls);
		$this->assertStringContainsString(needle: 'qb1.expr.eq("announcement_key", ":p2", null)', haystack: $calls);
	}//end testFindsThePlacementsThatAskForConfirmationWithoutAKey()
}//end class
