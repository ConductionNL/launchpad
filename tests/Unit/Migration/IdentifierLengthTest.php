<?php

/**
 * IdentifierLengthTest
 *
 * Pins Nextcloud's 30-character limit on database identifiers.
 *
 * This exists because two identifiers had already breached it —
 * `acknowledgement_content_version` (31) and `launchpad_kiosk_creator_revoked`
 * (31) — which made a FRESH INSTALL of the app impossible while every existing
 * install kept working. Postgres allows 63 characters, so the database never
 * complained; the limit is enforced by Nextcloud's own schema validation, and
 * only at install/upgrade time.
 *
 * That combination is the reason a test is worth having: the failure is invisible
 * to everyone who already has the app, invisible to unit tests, and fatal to
 * every new install. It stayed hidden for months behind a `composer test:all`
 * that ended in `|| echo '…skipping'` and so always exited 0.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Migration;

use PHPUnit\Framework\TestCase;

/**
 * Static analysis of migration sources for over-long database identifiers.
 */
class IdentifierLengthTest extends TestCase
{
    /**
     * Nextcloud's hard limit on database identifier length.
     *
     * @var int
     */
    private const MAX_LENGTH = 30;

    /**
     * Absolute path to the migration directory.
     *
     * @return string
     */
    private function migrationDir(): string
    {
        return dirname(__DIR__, 3).'/lib/Migration';
    }

    /**
     * Every column name a migration creates must fit the limit.
     *
     * Deliberately a source scan rather than a schema assertion: building the
     * real schema needs a Nextcloud runtime, which is precisely the environment
     * this suite does not have — and the whole point is to catch the breach
     * BEFORE it reaches the install that would fail on it.
     *
     * @return void
     */
    public function testNoMigrationCreatesAnOverLongColumnName(): void
    {
        $offenders = [];

        foreach (glob($this->migrationDir().'/*.php') as $file) {
            $source = file_get_contents($file);
            preg_match_all(
                '/(?:addColumn|changeColumn)\(\s*\n?\s*\'([a-z0-9_]+)\'/',
                $source,
                $matches
            );

            foreach ($matches[1] as $column) {
                if (strlen($column) > self::MAX_LENGTH) {
                    $offenders[] = sprintf(
                        '%s: %s (%d chars)',
                        basename($file),
                        $column,
                        strlen($column)
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Nextcloud rejects database identifiers longer than 30 characters at install time, "
            ."so these make a fresh install of the app fail:\n".implode("\n", $offenders)
        );
    }

    /**
     * Every index name a migration creates must fit the limit.
     *
     * @return void
     */
    public function testNoMigrationCreatesAnOverLongIndexName(): void
    {
        $offenders = [];

        foreach (glob($this->migrationDir().'/*.php') as $file) {
            $source = file_get_contents($file);
            preg_match_all(
                '/add(?:Unique)?Index\([^)]*?\'([a-z0-9_]+)\'/s',
                $source,
                $matches
            );

            foreach ($matches[1] as $index) {
                if (strlen($index) > self::MAX_LENGTH) {
                    $offenders[] = sprintf(
                        '%s: %s (%d chars)',
                        basename($file),
                        $index,
                        strlen($index)
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Over-long index names fail the same install-time validation:\n".implode("\n", $offenders)
        );
    }

    /**
     * The scanners must actually match something.
     *
     * Without this, a regex that silently matches nothing would make both tests
     * above pass forever — the exact "an absence claim is what a wrong lookup
     * manufactures for free" failure. So assert the scan finds a healthy number
     * of real identifiers before trusting that none of them is too long.
     *
     * @return void
     */
    public function testTheScannersActuallyFindIdentifiers(): void
    {
        $columns = 0;
        $indexes = 0;

        foreach (glob($this->migrationDir().'/*.php') as $file) {
            $source = file_get_contents($file);

            preg_match_all('/(?:addColumn|changeColumn)\(\s*\n?\s*\'([a-z0-9_]+)\'/', $source, $c);
            $columns += count($c[1]);

            preg_match_all('/add(?:Unique)?Index\([^)]*?\'([a-z0-9_]+)\'/s', $source, $i);
            $indexes += count($i[1]);
        }

        $this->assertGreaterThan(50, $columns, 'The column scanner matched almost nothing — the regex is broken.');
        $this->assertGreaterThan(5, $indexes, 'The index scanner matched almost nothing — the regex is broken.');
    }

    /**
     * The entity's own property-derived column names must fit too.
     *
     * A column can breach the limit without any migration naming it: QBMapper
     * derives the column from the property, so `acknowledgementContentVersion`
     * becomes a 31-character column on its own. WidgetPlacement aliases that one
     * to a short column; this checks no OTHER property has grown past the limit
     * unnoticed.
     *
     * @return void
     */
    public function testNoEntityPropertyDerivesAnOverLongColumn(): void
    {
        $entityDir = dirname(__DIR__, 3).'/lib/Db';
        $offenders = [];
        $scanned   = 0;

        foreach (glob($entityDir.'/*.php') as $file) {
            $source = file_get_contents($file);

            // Aliased properties are exempt: the alias IS the column.
            $aliased = [];
            if (preg_match('/COLUMN_ALIASES\s*=\s*\[(.*?)\];/s', $source, $block) === 1) {
                preg_match_all('/\'([A-Za-z0-9_]+)\'\s*=>\s*\'([a-z0-9_]+)\'/', $block[1], $pairs);
                $aliased = array_combine($pairs[1], $pairs[2]);
            }

            preg_match_all('/addType\(fieldName:\s*\'([A-Za-z0-9_]+)\'/', $source, $matches);
            foreach ($matches[1] as $property) {
                $scanned++;
                if (isset($aliased[$property]) === true) {
                    continue;
                }

                $column = strtolower(preg_replace('/(?<!^)(?=[A-Z])/', '_', $property));
                if (strlen($column) > self::MAX_LENGTH) {
                    $offenders[] = sprintf(
                        '%s: %s -> %s (%d chars)',
                        basename($file),
                        $property,
                        $column,
                        strlen($column)
                    );
                }
            }
        }

        // Control, same reasoning as above: prove the scan saw real properties.
        $this->assertGreaterThan(50, $scanned, 'The entity scanner matched almost nothing — the regex is broken.');
        $this->assertSame(
            [],
            $offenders,
            "These entity properties derive a column name Nextcloud will reject:\n".implode("\n", $offenders)
        );
    }
}//end class
