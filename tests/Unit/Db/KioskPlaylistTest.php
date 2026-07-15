<?php

/**
 * KioskPlaylistTest
 *
 * Unit tests for the KioskPlaylist entity covering entries JSON decoding,
 * malformed/null tolerance, dwellSeconds int normalisation, the jsonSerialize
 * shape (including the refreshSeconds default), and the computed-URL accessor.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-8
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\KioskPlaylist;
use PHPUnit\Framework\TestCase;

class KioskPlaylistTest extends TestCase
{

    public function testGetEntriesArrayDecodesJsonAndNormalisesDwellToInt(): void
    {
        $playlist = new KioskPlaylist();
        $playlist->setEntries(
            (string) json_encode(
                [
                    ['dashboardUuid' => 'uuid-1', 'dwellSeconds' => '30'],
                    ['dashboardUuid' => 'uuid-2', 'dwellSeconds' => 45],
                ]
            )
        );

        $entries = $playlist->getEntriesArray();

        $this->assertCount(2, $entries);
        $this->assertSame('uuid-1', $entries[0]['dashboardUuid']);
        $this->assertSame(30, $entries[0]['dwellSeconds']);
        $this->assertIsInt($entries[0]['dwellSeconds']);
        $this->assertSame(45, $entries[1]['dwellSeconds']);
    }

    public function testGetEntriesArrayReturnsEmptyOnNull(): void
    {
        $playlist = new KioskPlaylist();
        // entries left null.
        $this->assertSame([], $playlist->getEntriesArray());
    }

    public function testGetEntriesArrayReturnsEmptyOnMalformedJson(): void
    {
        $playlist = new KioskPlaylist();
        $playlist->setEntries('not-valid-json{');

        $this->assertSame([], $playlist->getEntriesArray());
    }

    public function testGetEntriesArraySkipsEntriesWithoutDashboardUuid(): void
    {
        $playlist = new KioskPlaylist();
        $playlist->setEntries(
            (string) json_encode(
                [
                    ['dwellSeconds' => 30],
                    ['dashboardUuid' => 'uuid-1', 'dwellSeconds' => 10],
                ]
            )
        );

        $entries = $playlist->getEntriesArray();

        $this->assertCount(1, $entries);
        $this->assertSame('uuid-1', $entries[0]['dashboardUuid']);
        // Missing dwellSeconds defaults to 0.
        $playlist2 = new KioskPlaylist();
        $playlist2->setEntries((string) json_encode([['dashboardUuid' => 'uuid-2']]));
        $this->assertSame(0, $playlist2->getEntriesArray()[0]['dwellSeconds']);
    }

    public function testJsonSerializeIncludesAllFields(): void
    {
        $playlist = new KioskPlaylist();
        $playlist->setId(7);
        $playlist->setName('Wall');
        $playlist->setToken('tok-123');
        $playlist->setUrl('https://example.com/apps/launchpad/kiosk/tok-123');
        $playlist->setEntries((string) json_encode([['dashboardUuid' => 'uuid-1', 'dwellSeconds' => 20]]));
        $playlist->setRefreshSeconds(120);
        $playlist->setCreatedBy('alice');
        $playlist->setCreatedAt('2026-01-01 00:00:00');
        $playlist->setRevokedAt(null);

        $json = $playlist->jsonSerialize();

        $this->assertSame(7, $json['id']);
        $this->assertSame('Wall', $json['name']);
        $this->assertSame('tok-123', $json['token']);
        $this->assertSame('https://example.com/apps/launchpad/kiosk/tok-123', $json['url']);
        $this->assertSame(120, $json['refreshSeconds']);
        $this->assertSame('alice', $json['createdBy']);
        $this->assertSame('2026-01-01 00:00:00', $json['createdAt']);
        $this->assertNull($json['revokedAt']);
        $this->assertIsArray($json['entries']);
        $this->assertCount(1, $json['entries']);
        $this->assertSame('uuid-1', $json['entries'][0]['dashboardUuid']);
    }

    public function testJsonSerializeRefreshSecondsDefaultsTo300(): void
    {
        $playlist = new KioskPlaylist();
        // refreshSeconds left null.
        $json = $playlist->jsonSerialize();

        $this->assertSame(300, $json['refreshSeconds']);
    }

    public function testSetUrlGetUrlRoundtrip(): void
    {
        $playlist = new KioskPlaylist();
        $this->assertNull($playlist->getUrl());

        $playlist->setUrl('https://example.com/apps/launchpad/kiosk/tok');
        $this->assertSame('https://example.com/apps/launchpad/kiosk/tok', $playlist->getUrl());
    }
}//end class
