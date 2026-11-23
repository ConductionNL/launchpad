<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Settings;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The embed schemas of the embedded-analytics register fragment.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-1
 */
#[CoversNothing]
class EmbeddedAnalyticsSchemasTest extends TestCase {

	/**
	 * The decoded fragment.
	 *
	 * @return array<string, mixed>
	 */
	private static function fragment(): array {
		return json_decode((string)file_get_contents(__DIR__ . '/../../../lib/Settings/register.d/embedded-analytics.json'), true, 512, JSON_THROW_ON_ERROR);
	}//end fragment()

	/**
	 * The four schemas are declared and listed on the launchpad register.
	 *
	 * @return void
	 */
	public function testTheFourSchemasAreDeclaredAndRegistered(): void {
		$fragment = self::fragment();
		$names    = ['embed_token', 'tenant_theme', 'embed_usage_event', 'signing_key'];
		foreach ($names as $name) {
			$this->assertSame($name, $fragment['components']['schemas'][$name]['slug'] ?? null, $name);
		}

		$this->assertSame($names, $fragment['components']['registers']['launchpad']['schemas']);
	}//end testTheFourSchemasAreDeclaredAndRegistered()

	/**
	 * A token stores its hash, never the token itself.
	 *
	 * @return void
	 */
	public function testATokenStoresOnlyItsHash(): void {
		$token = self::fragment()['components']['schemas']['embed_token'];

		$this->assertSame('^[0-9a-f]{64}$', $token['properties']['tokenHash']['pattern']);
		$this->assertArrayNotHasKey('jwt', $token['properties']);
		$this->assertArrayNotHasKey('token', $token['properties']);
		$this->assertSame(['read', 'read-with-interactions'], $token['properties']['scope']['properties']['mode']['enum']);
		$this->assertContains('re-issued', $token['properties']['revocationReason']['enum']);
	}//end testATokenStoresOnlyItsHash()

	/**
	 * A usage event has no field for an IP address, a full user agent or a user.
	 *
	 * @return void
	 */
	public function testAUsageEventHoldsNoPersonalData(): void {
		$properties = array_keys(self::fragment()['components']['schemas']['embed_usage_event']['properties']);

		foreach (['ip', 'ipAddress', 'remoteAddr', 'userId', 'user', 'fullUserAgent'] as $forbidden) {
			$this->assertNotContains($forbidden, $properties, $forbidden);
		}

		$this->assertContains('userAgentHash', $properties);
		$this->assertContains('viewportSize', $properties);
	}//end testAUsageEventHoldsNoPersonalData()

	/**
	 * A signing key keeps its private half only encrypted.
	 *
	 * @return void
	 */
	public function testASigningKeyKeepsThePrivateKeyEncrypted(): void {
		$key = self::fragment()['components']['schemas']['signing_key'];

		$this->assertArrayHasKey('privateKeyEncrypted', $key['properties']);
		$this->assertArrayNotHasKey('privateKey', $key['properties']);
		$this->assertSame(['RS256', 'ES256'], $key['properties']['algorithm']['enum']);
		$this->assertSame(['active', 'retiring', 'revoked'], $key['properties']['status']['enum']);
	}//end testASigningKeyKeepsThePrivateKeyEncrypted()
}//end class
