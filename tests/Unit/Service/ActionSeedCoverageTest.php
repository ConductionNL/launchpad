<?php

/**
 * ActionSeedCoverageTest
 *
 * ADR-023 gate: every action key declared in `lib/actions.seed.json`
 * must have at least one `requireAction(..., 'key')` call wired in a
 * PHP controller file. This prevents seed entries from accumulating
 * without a corresponding enforcement point.
 *
 * Wave-3 C2 - migrated analytics.* and metadata-admin.* to
 * requireAction; deleted seed-only resource.* phantoms.
 * Wave-12 WF - added reverse-direction check: every requireAction() key
 * used in controllers must exist in actions.seed.json.
 *
 * @category  Test
 * @package   OCA\MyDash\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * Asserts bidirectional coverage between actions.seed.json and the
 * requireAction() calls in the controller layer:
 *   1. Every seed key must have a requireAction() call (forward check).
 *   2. Every requireAction() key in controllers must exist in the seed
 *      (reverse check - prevents a new endpoint from going ungated).
 */
class ActionSeedCoverageTest extends TestCase
{

    /**
     * Path to the seed file relative to the project root.
     */
    private const SEED_FILE = __DIR__.'/../../../lib/actions.seed.json';

    /**
     * Directory containing the PHP controllers.
     */
    private const CONTROLLER_DIR = __DIR__.'/../../../lib/Controller';

    /**
     * Build the combined PHP source from all controller files.
     *
     * @return string All controller PHP source concatenated.
     */
    private function buildCombinedControllerSource(): string
    {
        $controllerDir = realpath(self::CONTROLLER_DIR);
        $this->assertNotFalse($controllerDir, 'Controller directory not found');

        $phpFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllerDir)
        );

        $combined = '';
        foreach ($phpFiles as $file) {
            if ($file->isFile() === false || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content !== false) {
                $combined .= $content;
            }
        }

        return $combined;
    }//end buildCombinedControllerSource()

    /**
     * Load and parse actions.seed.json, returning the action keys.
     *
     * @return string[] The list of seed action keys.
     */
    private function loadSeedKeys(): array
    {
        $seedPath = realpath(self::SEED_FILE);
        $this->assertNotFalse($seedPath, 'actions.seed.json not found');

        $json = file_get_contents($seedPath);
        $this->assertNotFalse($json, 'Failed to read actions.seed.json');

        $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded['actions'] ?? null, 'actions.seed.json missing "actions" key');

        $keys = array_keys($decoded['actions']);
        $this->assertNotEmpty($keys, 'Seed has no actions - check seed file');

        return $keys;
    }//end loadSeedKeys()

    /**
     * FORWARD: every seed key must appear in at least one requireAction()
     * call inside the Controller directory.
     *
     * @return void
     */
    public function testAllSeedKeysHaveRequireActionWiring(): void
    {
        $seedKeys       = $this->loadSeedKeys();
        $combinedSource = $this->buildCombinedControllerSource();

        $missing = [];
        foreach ($seedKeys as $key) {
            // Match requireAction($user, 'key') - single or double quotes.
            $pattern = '/requireAction\s*\([^,]+,\s*[\'"]' . preg_quote($key, '/') . '[\'"]\s*\)/';
            if (preg_match($pattern, $combinedSource) !== 1) {
                $missing[] = $key;
            }
        }

        $this->assertEmpty(
            $missing,
            sprintf(
                "The following seed action keys have no requireAction() call in any controller:\n  - %s\n\n"
                . "Either wire them up or remove them from actions.seed.json.",
                implode("\n  - ", $missing)
            )
        );
    }//end testAllSeedKeysHaveRequireActionWiring()

    /**
     * REVERSE: every requireAction() key used in controllers must exist
     * in actions.seed.json. Prevents a new ungated endpoint from slipping
     * past the ADR-023 gate silently (wave-12 WF fix).
     *
     * @return void
     */
    public function testAllRequireActionKeysExistInSeed(): void
    {
        $seedKeys       = array_flip($this->loadSeedKeys());
        $combinedSource = $this->buildCombinedControllerSource();

        // Extract every key string passed to requireAction($user, '<key>').
        preg_match_all(
            '/requireAction\s*\([^,]+,\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            $combinedSource,
            $matches
        );

        $usedKeys = array_unique($matches[1] ?? []);
        $orphaned = [];
        foreach ($usedKeys as $usedKey) {
            if (array_key_exists($usedKey, $seedKeys) === false) {
                $orphaned[] = $usedKey;
            }
        }

        $this->assertEmpty(
            $orphaned,
            sprintf(
                "The following requireAction() keys used in controllers are missing from actions.seed.json:\n  - %s\n\n"
                . "Add them to actions.seed.json with appropriate default roles, or fix the action key.",
                implode("\n  - ", $orphaned)
            )
        );
    }//end testAllRequireActionKeysExistInSeed()
}//end class
