<?php

/**
 * HtmlSanitizerEdgeCaseTest
 *
 * Pins the sanitizer's handling of blank input and of empty URL attributes.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Confluence
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\Confluence;

use OCA\LaunchPad\Service\Confluence\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Edge cases of the Confluence HTML sanitizer.
 *
 * @covers \OCA\LaunchPad\Service\Confluence\HtmlSanitizer
 */
class HtmlSanitizerEdgeCaseTest extends TestCase {

	/**
	 * Blank input yields an empty string.
	 *
	 * @return void
	 */
	public function testBlankInputIsEmpty(): void {
		$this->assertSame('', (new HtmlSanitizer())->sanitize(html: " \n\t "));
	}//end testBlankInputIsEmpty()

	/**
	 * An empty href is kept, as it carries no scheme.
	 *
	 * @return void
	 */
	public function testAnEmptyHrefIsKept(): void {
		$this->assertSame(
			'<p><a href="" title="t">a</a></p>',
			(new HtmlSanitizer())->sanitize(html: '<p><a href="" title="t" onclick="x()">a</a></p>')
		);
	}//end testAnEmptyHrefIsKept()

	/**
	 * Unsafe schemes are removed from src even with leading whitespace,
	 * while https and relative sources stay.
	 *
	 * @return void
	 */
	public function testUnsafeSourcesAreDroppedAndSafeOnesKept(): void {
		$out = (new HtmlSanitizer())->sanitize(
			html: '<img src="  data:image/png;base64,AAA" alt="x"><img src="https://e.test/a.png"><img src="pics/b.png">'
		);

		$this->assertSame('<img alt="x"><img src="https://e.test/a.png"><img src="pics/b.png">', $out);
	}//end testUnsafeSourcesAreDroppedAndSafeOnesKept()
}//end class
