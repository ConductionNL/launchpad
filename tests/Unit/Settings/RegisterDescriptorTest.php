<?php

/**
 * RegisterDescriptorTest
 *
 * Guards `lib/Settings/launchpad_register.json` against the two defects that
 * between them meant the LaunchPad register was NEVER provisioned on a fresh
 * install — so `GET /api/manifest` returned an empty page list and every
 * dashboard-object write failed with "Register not found".
 *
 * 1. The descriptor declared `components.schemas` but NO `components.registers`
 *    block, so there was nothing to create the register even once something
 *    imported the file.
 *
 * 2. `groupId` declared a UNION type, `["string", "null"]`. OpenRegister's
 *    property validator accepts scalar types only, so the whole Dashboard schema
 *    was rejected — and the register was then created WITHOUT a reference to it,
 *    which OpenRegister logs and carries on from. The visible symptom was a
 *    misleading "Schema not found: 'dashboard'" at the far end, on object write.
 *
 * Both are invisible to every other kind of test: the descriptor is data, the
 * import runs only during provisioning, and the failure is a logged warning
 * rather than an exception.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Settings;

use PHPUnit\Framework\TestCase;

/**
 * Static validation of the OpenRegister descriptor this app ships.
 */
class RegisterDescriptorTest extends TestCase
{
    /**
     * Property types OpenRegister's PropertyValidatorHandler accepts.
     *
     * Mirrors its `$validTypes`. A union (array) type is rejected outright.
     *
     * @var array<int, string>
     */
    private const VALID_TYPES = [
        'string',
        'number',
        'integer',
        'boolean',
        'array',
        'object',
        'null',
        'file',
        'geo',
        'color',
        'recurrence',
    ];

    /**
     * The decoded descriptor.
     *
     * @return array<string, mixed>
     */
    private function descriptor(): array
    {
        $path = dirname(__DIR__, 3).'/lib/Settings/launchpad_register.json';
        $this->assertFileExists($path, 'the register descriptor must ship with the app');

        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, 'the register descriptor must be valid JSON');

        return $data;
    }

    /**
     * The descriptor must declare the register, not only its schemas.
     *
     * @return void
     */
    public function testTheRegisterIsDeclared(): void
    {
        $d = $this->descriptor();

        $this->assertArrayHasKey(
            'registers',
            $d['components'] ?? [],
            'without components.registers nothing creates the register, and every '
            .'OpenRegister-backed feature fails with "Register not found"',
        );

        $register = $d['components']['registers']['launchpad'] ?? null;
        $this->assertIsArray($register, 'the `launchpad` register must be declared');
        $this->assertSame('launchpad', $register['slug'] ?? null);

        // The register's schema references are matched by SLUG (OpenRegister keys
        // its import-time schemasMap on the schema's slug, not on the map key —
        // here the map key is `Dashboard` while the slug is `dashboard`).
        $slugs = [];
        foreach (($d['components']['schemas'] ?? []) as $schema) {
            if (isset($schema['slug']) === true) {
                $slugs[] = $schema['slug'];
            }
        }

        $this->assertNotEmpty($slugs, 'control: the descriptor must declare at least one schema slug');

        foreach (($register['schemas'] ?? []) as $ref) {
            $this->assertContains(
                $ref,
                $slugs,
                "the register references schema '$ref', which no declared schema's `slug` provides",
            );
        }
    }

    /**
     * Every property type must be a scalar OpenRegister accepts.
     *
     * @return void
     */
    public function testNoPropertyUsesAnUnsupportedType(): void
    {
        $d = $this->descriptor();
        $offenders = [];
        $checked = 0;

        foreach (($d['components']['schemas'] ?? []) as $schemaKey => $schema) {
            foreach (($schema['properties'] ?? []) as $name => $property) {
                if (array_key_exists('type', $property) === false) {
                    continue;
                }

                $checked++;
                $type = $property['type'];

                if (is_array($type) === true) {
                    $offenders[] = sprintf(
                        '%s.%s: union type %s — OpenRegister accepts scalar types only; '
                        .'model optionality by leaving the property out of `required`',
                        $schemaKey,
                        $name,
                        json_encode($type),
                    );
                    continue;
                }

                if (in_array($type, self::VALID_TYPES, true) === false) {
                    $offenders[] = sprintf('%s.%s: unsupported type %s', $schemaKey, $name, json_encode($type));
                }
            }
        }

        // Control: if the walk found no typed properties at all, the assertion
        // below would pass while inspecting nothing.
        $this->assertGreaterThan(5, $checked, 'the property walk found almost nothing — it is not reaching the schema');

        $this->assertSame(
            [],
            $offenders,
            "OpenRegister rejects the whole schema on an invalid property type, then creates the register "
            ."WITHOUT a reference to it and merely logs a warning — so this surfaces much later as a "
            ."misleading \"Schema not found\":\n".implode("\n", $offenders),
        );
    }
}//end class
