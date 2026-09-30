<?php

/**
 * OfficeNetworkLiveTest
 *
 * The office network check with Nextcloud's own IP factory (IPLib), which
 * the unit test replaces with a CIDR double (REQ-TIA-001). Runs where CI
 * boots a live Nextcloud; skipped in a plain clone.
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

use OCP\Security\Ip\IFactory;
use OCP\Server;

/**
 * @SuppressWarnings(PHPMD.StaticAccess) Server::get() is the only way in from a test.
 */
class OfficeNetworkLiveTest extends RealDatabaseTestCase {
	/**
	 * The ranges the service stores parse and match with the server's factory.
	 *
	 * @return void
	 */
	public function testTheServerFactoryAgreesWithTheRangesWeStore(): void {
		$factory = Server::get(IFactory::class);

		$this->assertTrue($factory->rangeFromString('10.0.0.0/8')->contains($factory->addressFromString('10.20.30.40')));
		$this->assertFalse($factory->rangeFromString('192.168.12.0/24')->contains($factory->addressFromString('192.168.13.1')));
		$this->assertTrue($factory->rangeFromString('2001:db8:12::/48')->contains($factory->addressFromString('2001:db8:12:ff::1')));
	}//end testTheServerFactoryAgreesWithTheRangesWeStore()
}//end class
