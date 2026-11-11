<?php

/**
 * UuidV4GoldenTest
 *
 * Characterisation test pinning the shape of the random v4 UUIDs the
 * template, placement, import and demo-showcase services mint: 36
 * lower-case hex characters in 8-4-4-4-12 groups, version nibble 4 and
 * variant bits 10, unique per call. They all mint through UuidV4 now.
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

use OCA\LaunchPad\Service\DemoShowcasesService;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\PlacementUpdater;
use OCA\LaunchPad\Service\TemplateService;
use OCA\LaunchPad\Service\UuidV4;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class UuidV4GoldenTest extends TestCase {
	private const SHAPE = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

	public function testMintsDistinctV4Uuids(): void {
		$seen = [];
		for ($i = 0; $i < 200; $i++) {
			$uuid = UuidV4::generate();
			$this->assertMatchesRegularExpression(self::SHAPE, $uuid);
			$seen[$uuid] = true;
		}

		$this->assertCount(200, $seen);
	}

	/**
	 * The four services that minted their own copy now mint through UuidV4.
	 */
	public function testTheServicesNoLongerCarryTheirOwnCopy(): void {
		foreach ([TemplateService::class, PlacementUpdater::class, ImportService::class, DemoShowcasesService::class] as $class) {
			$reflection = new ReflectionClass($class);
			$this->assertFalse($reflection->hasMethod('generateUuid'), $class);
			$this->assertFalse($reflection->hasMethod('generateUuidV4'), $class);
		}
	}
}
