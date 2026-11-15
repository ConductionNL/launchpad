<?php

/**
 * LinkRewriterEdgeCaseTest
 *
 * Pins which links the rewriter leaves alone: empty input, blank, anchor and root-relative hrefs.
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

use OCA\LaunchPad\Service\Confluence\LinkRewriter;
use PHPUnit\Framework\TestCase;

/**
 * Edge cases of the Confluence link rewriter.
 *
 * @covers \OCA\LaunchPad\Service\Confluence\LinkRewriter
 */
class LinkRewriterEdgeCaseTest extends TestCase {

	/**
	 * Empty input returns empty html and no warnings.
	 *
	 * @return void
	 */
	public function testEmptyInput(): void {
		$this->assertSame(
			['html' => '', 'warnings' => []],
			(new LinkRewriter())->rewrite(html: '', pageIdToUuid: ['page' => 'u-1'])
		);
	}//end testEmptyInput()

	/**
	 * Blank, anchor and root-relative hrefs stay as they are and raise no
	 * warning, even when their name matches a known page; a known page link
	 * next to them is still rewritten.
	 *
	 * @return void
	 */
	public function testBlankAnchorAndRootRelativeLinksAreLeftAlone(): void {
		$html = '<a href=" ">blank</a><a href="#page.html">anchor</a>'
			. '<a href="/space/page.html">root</a><a href="page.html" class="x">page</a>';

		$result = (new LinkRewriter())->rewrite(html: $html, pageIdToUuid: ['page' => 'u-1']);

		$this->assertSame(
			'<a href=" ">blank</a><a href="#page.html">anchor</a>'
			. '<a href="/space/page.html">root</a><a href="/apps/launchpad/dashboard/u-1" class="x">page</a>',
			$result['html']
		);
		$this->assertSame([], $result['warnings']);
	}//end testBlankAnchorAndRootRelativeLinksAreLeftAlone()
}//end class
