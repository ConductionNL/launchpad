<?php

/**
 * HeadlessRendererTest
 *
 * The PDF/PNG capture (REQ-SCH-003): refused without a configured
 * browser, the browser's argument list per format, and the bytes the
 * browser wrote.
 *
 * @category  Test
 * @package   Unit\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\ScheduledExport;

use OCA\LaunchPad\Service\ScheduledExport\HeadlessRenderer;
use OCA\LaunchPad\Service\ScheduledExport\RenderException;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\HeadlessRenderer
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-22
 */
class HeadlessRendererTest extends TestCase {

	/**
	 * A renderer whose configured browser is the given path.
	 *
	 * @param string $binary The path.
	 *
	 * @return HeadlessRenderer
	 */
	private function renderer(string $binary): HeadlessRenderer {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->with('launchpad', 'export_chromium_binary', '')->willReturn($binary);
		return new HeadlessRenderer($config);
	}//end renderer()

	/**
	 * Without a browser a PDF is refused with the reason.
	 *
	 * @return void
	 */
	public function testWithoutABrowserIsRefused(): void {
		$this->expectException(RenderException::class);
		$this->expectExceptionMessage('No headless browser is configured for PDF and PNG exports');
		$this->renderer(binary: '')->capture(url: 'https://nc/x', format: 'pdf');
	}//end testWithoutABrowserIsRefused()

	/**
	 * A PNG asks for the viewport and the DPI as a scale factor; a PDF prints without header and footer.
	 *
	 * @return void
	 */
	public function testCommandPerFormat(): void {
		$renderer = $this->renderer(binary: '/bin/true');
		$png      = $renderer->command(binary: '/c', request: ['url' => 'https://nc/p', 'format' => 'png', 'options' => ['width' => 1280, 'height' => 720, 'dpi' => 192], 'out' => '/tmp/o']);
		$this->assertSame(['/c', '--headless', '--no-sandbox', '--disable-gpu', '--hide-scrollbars', '--timeout=120000', '--window-size=1280,720', '--force-device-scale-factor=2', '--screenshot=/tmp/o', 'https://nc/p'], $png);

		$pdf = $renderer->command(binary: '/c', request: ['url' => 'https://nc/p', 'format' => 'pdf', 'out' => '/tmp/o']);
		$this->assertSame(['--no-pdf-header-footer', '--print-to-pdf=/tmp/o', 'https://nc/p'], array_slice($pdf, -3));
	}//end testCommandPerFormat()

	/**
	 * The bytes the browser wrote are returned; a browser that writes nothing fails.
	 *
	 * @return void
	 */
	public function testCaptureReturnsTheBrowsersBytes(): void {
		$script = tempnam(sys_get_temp_dir(), 'lpb');
		file_put_contents($script, "#!/bin/sh\nfor a in \"\$@\"; do case \"\$a\" in --print-to-pdf=*) printf '%%PDF-1.7' > \"\${a#--print-to-pdf=}\";; esac; done\n");
		chmod($script, 0700);

		try {
			$this->assertSame('%PDF-1.7', $this->renderer(binary: $script)->capture(url: 'https://nc/p', format: 'pdf'));

			$this->expectException(RenderException::class);
			$this->expectExceptionMessage('The headless browser failed to render the PNG');
			$this->renderer(binary: $script)->capture(url: 'https://nc/p', format: 'png');
		} finally {
			unlink($script);
		}
	}//end testCaptureReturnsTheBrowsersBytes()
}//end class
