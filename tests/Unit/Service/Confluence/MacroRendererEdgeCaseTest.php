<?php

/**
 * MacroRendererEdgeCaseTest
 *
 * Pins the renderer's handling of blank input and of images given by URL or by nothing.
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

use OCA\LaunchPad\Service\Confluence\MacroRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Edge cases of the Confluence macro renderer.
 *
 * @covers \OCA\LaunchPad\Service\Confluence\MacroRenderer
 */
class MacroRendererEdgeCaseTest extends TestCase {

	/**
	 * Blank input is returned unchanged, whitespace included.
	 *
	 * @return void
	 */
	public function testBlankInputIsReturnedUnchanged(): void {
		$this->assertSame("  \n", (new MacroRenderer())->render(html: "  \n"));
	}//end testBlankInputIsReturnedUnchanged()

	/**
	 * An image by URL becomes an img with an escaped src and empty alt.
	 *
	 * @return void
	 */
	public function testAnImageByUrlBecomesAnImg(): void {
		$html = '<ac:image><ri:url ri:value="https://e.test/a.png?x=1&y=&quot;2&quot;"/></ac:image>';

		$this->assertSame(
			'<img src="https://e.test/a.png?x=1&amp;y=&amp;quot;2&amp;quot;" alt="">',
			(new MacroRenderer())->render(html: $html)
		);
	}//end testAnImageByUrlBecomesAnImg()

	/**
	 * An image without an attachment or URL is dropped.
	 *
	 * @return void
	 */
	public function testAnImageWithoutSourceIsDropped(): void {
		$this->assertSame(
			'<p>before</p><p>after</p>',
			(new MacroRenderer())->render(html: '<p>before</p><ac:image ac:width="10"></ac:image><p>after</p>')
		);
	}//end testAnImageWithoutSourceIsDropped()
}//end class
