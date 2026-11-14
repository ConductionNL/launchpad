<?php

/**
 * CspService unit tests (REQ-EMB-003).
 *
 * @category  Test
 * @package   Unit\Service\Embed
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */

declare(strict_types=1);

namespace Unit\Service\Embed;

use OCA\LaunchPad\Service\Embed\CspService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * frame-ancestors from the token's host origins.
 */
#[CoversClass(CspService::class)]
class CspServiceTest extends TestCase {

	/**
	 * One or more origins become the frame-ancestors list, normalised and once each.
	 *
	 * @return void
	 */
	public function testTheHeaderListsTheHostOrigins(): void {
		$csp = new CspService();

		self::assertSame('frame-ancestors https://www.zeist.nl', $csp->generateCspHeader(['https://www.zeist.nl']));
		self::assertSame(
			'frame-ancestors https://intranet.zeist.nl https://www.zeist.nl',
			$csp->generateCspHeader(['https://intranet.zeist.nl', 'https://WWW.zeist.nl/', 'https://www.zeist.nl', 'javascript:alert(1)'])
		);
		self::assertSame("frame-ancestors 'none'", $csp->generateCspHeader(['*', 'not an origin', 42]), 'nothing valid frames nothing');
	}//end testTheHeaderListsTheHostOrigins()

	/**
	 * A wildcard is refused with the spec's message; so is an empty list or a path.
	 *
	 * @return void
	 */
	public function testWildcardsAndMalformedOriginsAreRefused(): void {
		$csp = new CspService();

		$wildcard = $csp->validateOrigins(['https://www.zeist.nl', '*']);
		self::assertSame('wildcard_not_permitted', $wildcard['details']['hostOrigins']);
		self::assertSame('Wildcard origin \'*\' is not permitted. Specify explicit origins, e.g., ["https://www.example.com"]', $wildcard['message']);
		self::assertSame('wildcard_not_permitted', $csp->validateOrigins(['https://*.zeist.nl'])['details']['hostOrigins']);
		self::assertSame('origin_required', $csp->validateOrigins([])['details']['hostOrigins']);
		self::assertSame('origin_required', $csp->validateOrigins('https://www.zeist.nl')['details']['hostOrigins']);
		self::assertSame('invalid_origin', $csp->validateOrigins(['https://www.zeist.nl/embed'])['details']['hostOrigins']);
		self::assertNull($csp->validateOrigins(['https://www.zeist.nl', 'http://localhost:8080']));
	}//end testWildcardsAndMalformedOriginsAreRefused()

	/**
	 * An origin is a scheme, a host and an optional port.
	 *
	 * @return void
	 */
	public function testOriginFormat(): void {
		$csp = new CspService();

		foreach (['https://www.zeist.nl', 'http://localhost:8080', 'https://a-b.example.org/'] as $good) {
			self::assertTrue($csp->validateOriginFormat($good), $good);
		}

		foreach (['www.zeist.nl', 'ftp://zeist.nl', 'https://zeist.nl/path', 'https://user@zeist.nl', 'https://-bad.nl', 'https://zeist.nl:123456'] as $bad) {
			self::assertFalse($csp->validateOriginFormat($bad), $bad);
		}
	}//end testOriginFormat()

}//end class
