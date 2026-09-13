<?php

/**
 * FleetAppIdFixtures
 *
 * A class under a PRE-RENAME fleet namespace, so {@see FleetAppId::resolveClass()}
 * and {@see FleetAppId::isInstanceOf()} can be exercised against a name that
 * really is absent under the new namespace and present under the old one.
 *
 * `buildiq` / `OCA\OpenBuilt` is used on purpose: no launchpad code binds to
 * that app, so declaring a fixture in its namespace cannot collide with a real
 * class or influence any other test.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Support
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenBuilt\Fixture;

/**
 * Stand-in for a class an app shipped before its rename.
 */
class LegacyProbe {

}//end class
