<?php

/**
 * NoCompileTimeOpenRegisterDependencyTest
 *
 * Pins the rule that no controller may depend on an OpenRegister class at LOAD
 * time — only lazily, through the container, by string.
 *
 * This exists because `HealthController` and `MetricsController` used to EXTEND
 * OpenRegister's generic AppHost controllers. Nextcloud's router calls
 * `new ReflectionClass()` on every controller while scanning for attribute
 * routes, which loads the class, which loads its parent. On an instance without
 * OpenRegister that missing parent was a fatal raised during route MATCHING —
 * so every single route in launchpad returned 500, not just `/api/health` and
 * `/api/metrics`.
 *
 * What makes it worth a test is that the codebase had already tried to prevent
 * exactly this: `Application::registerObservability()` names the OpenRegister
 * classes only as strings inside lazy factory closures, with a comment stating
 * that "a disabled/absent OpenRegister never fatals NC bootstrap". That was
 * false, because a lazy DI registration cannot make a class-level `extends`
 * lazy — inheritance is resolved by the autoloader, not by the container. A
 * careful mitigation plus one `extends` still equals an outage, and nothing in
 * the suite noticed.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Source scan for load-time OpenRegister references in controllers.
 */
class NoCompileTimeOpenRegisterDependencyTest extends TestCase
{
    /**
     * Absolute path to the controller directory.
     *
     * @return string
     */
    private function controllerDir(): string
    {
        return dirname(__DIR__, 3).'/lib/Controller';
    }

    /**
     * No controller may extend or implement an OpenRegister type.
     *
     * @return void
     */
    public function testNoControllerExtendsAnOpenRegisterClass(): void
    {
        $offenders = [];

        foreach (glob($this->controllerDir().'/*.php') as $file) {
            $source = file_get_contents($file);

            preg_match_all(
                '/^\s*(?:final\s+|abstract\s+)*class\s+\w+\s+(?:extends|implements)\s+([^\{]+)/m',
                $source,
                $matches
            );

            foreach ($matches[1] as $parents) {
                if (str_contains($parents, 'OpenRegister') === true) {
                    $offenders[] = basename($file).' -> '.trim($parents);
                    continue;
                }

                // A short parent name is resolved through the import list, so a
                // `use OCA\OpenRegister\…\Foo;` plus `extends Foo` is the same
                // defect wearing a different hat.
                foreach (preg_split('/\s*,\s*/', trim($parents)) as $parent) {
                    $parent  = trim($parent);
                    $pattern = '/^use\s+OCA\\\\OpenRegister\\\\[^;]*\b'.preg_quote($parent, '/').'\s*;/m';
                    if ($parent !== '' && preg_match($pattern, $source) === 1) {
                        $offenders[] = basename($file).' -> '.$parent.' (imported from OpenRegister)';
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Nextcloud's router reflects every controller class when scanning attribute routes, so a "
            ."missing PARENT class is a fatal during route matching and takes EVERY route in the app "
            ."down — not only the endpoint that needs it. Depend on OpenRegister lazily, via the "
            ."container and by string, as Application::optional() does:\n".implode("\n", $offenders)
        );
    }

    /**
     * No controller may name an OpenRegister type in a `use` import.
     *
     * An import alone is harmless at runtime (PHP does not resolve unused
     * imports), but it is the raw material for the defect above and for a
     * constructor parameter type — which IS resolved when the container
     * instantiates the controller. Keeping controllers free of the import
     * removes the whole family. Lazy container lookups pass the class name as a
     * quoted string, so they are unaffected by this rule.
     *
     * @return void
     */
    public function testNoControllerImportsAnOpenRegisterClass(): void
    {
        $offenders = [];

        foreach (glob($this->controllerDir().'/*.php') as $file) {
            $source = file_get_contents($file);
            preg_match_all('/^use\s+(OCA\\\\OpenRegister\\\\[^;]+);/m', $source, $matches);

            foreach ($matches[1] as $import) {
                $offenders[] = basename($file).': use '.$import;
            }
        }

        $this->assertSame([], $offenders, "Import OpenRegister lazily by string instead:\n".implode("\n", $offenders));
    }

    /**
     * The scanner must actually be looking at controllers.
     *
     * Without this, a wrong path or a broken regex would make both assertions
     * above pass forever while inspecting nothing — an absence claim is exactly
     * what a wrong lookup manufactures for free.
     *
     * @return void
     */
    public function testTheScannerActuallySeesControllers(): void
    {
        $files   = glob($this->controllerDir().'/*.php');
        $classes = 0;

        foreach ($files as $file) {
            $source = file_get_contents($file);
            if (preg_match('/^\s*(?:final\s+|abstract\s+)*class\s+\w+/m', $source) === 1) {
                $classes++;
            }
        }

        $this->assertGreaterThan(20, count($files), 'The controller directory glob found almost nothing.');
        $this->assertGreaterThan(20, $classes, 'The class-declaration regex matched almost nothing.');
    }
}//end class
