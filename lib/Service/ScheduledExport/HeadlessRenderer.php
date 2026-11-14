<?php

/**
 * HeadlessRenderer
 *
 * The PDF and PNG half of a render (REQ-SCH-003): the subject's page is
 * printed or captured by a headless Chromium. The browser is an operator
 * choice, configured as the app value `export_chromium_binary`; without it
 * PDF and PNG targets fail with a clear reason while CSV and XLSX still
 * render. Rendering happens in the background job, one at a time (design,
 * Risks).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

use OCP\IAppConfig;

/**
 * Prints or captures a page with headless Chromium.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-003-multi-format-render-from-a-single-fire
 */
class HeadlessRenderer {

	public const CONFIG_BINARY = 'export_chromium_binary';

	private const TIMEOUT_SECONDS = 120;

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig Holds the browser path.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}//end __construct()

	/**
	 * The bytes of a page as PDF or PNG.
	 *
	 * @param string               $url     The page.
	 * @param string               $format  `pdf` or `png`.
	 * @param array<string, mixed> $options PNG: `width`, `height`, `dpi`.
	 *
	 * @return string
	 *
	 * @throws RenderException When no browser is configured or the browser fails.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-003-multi-format-render-from-a-single-fire
	 */
	public function capture(string $url, string $format, array $options = []): string {
		$binary = $this->appConfig->getValueString('launchpad', self::CONFIG_BINARY, '');
		if ($binary === '' || is_executable($binary) === false) {
			throw new RenderException('No headless browser is configured for PDF and PNG exports');
		}

		$out = tempnam(sys_get_temp_dir(), 'lpr');
		if ($out === false) {
			throw new RenderException('The render could not be written');
		}

		$command = $this->command(binary: $binary, request: ['url' => $url, 'format' => $format, 'options' => $options, 'out' => $out]);
		$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		if (is_resource($process) === false) {
			throw new RenderException('The headless browser could not be started');
		}

		stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);
		$exit  = proc_close($process);
		$bytes = (string)@file_get_contents($out);
		@unlink($out);

		if ($exit !== 0 || $bytes === '') {
			throw new RenderException('The headless browser failed to render the ' . strtoupper($format));
		}

		return $bytes;
	}//end capture()

	/**
	 * The browser command line for one capture, as an argument list (no shell).
	 *
	 * @param string               $binary  The browser.
	 * @param array<string, mixed> $request `url`, `format`, `options` and the `out` file.
	 *
	 * @return list<string>
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-003-multi-format-render-from-a-single-fire
	 */
	public function command(string $binary, array $request): array {
		$options = (array)($request['options'] ?? []);
		$command = [$binary, '--headless', '--no-sandbox', '--disable-gpu', '--hide-scrollbars', '--timeout=' . (self::TIMEOUT_SECONDS * 1000)];

		if ($request['format'] === 'png') {
			$width     = max(320, (int)($options['width'] ?? 1920));
			$height    = max(240, (int)($options['height'] ?? 1080));
			$scale     = max(1, (int)($options['dpi'] ?? 96)) / 96;
			$command[] = '--window-size=' . $width . ',' . $height;
			$command[] = '--force-device-scale-factor=' . rtrim(rtrim(number_format($scale, 2, '.', ''), '0'), '.');
			$command[] = '--screenshot=' . $request['out'];
		} else {
			$command[] = '--no-pdf-header-footer';
			$command[] = '--print-to-pdf=' . $request['out'];
		}

		$command[] = (string)$request['url'];
		return $command;
	}//end command()
}//end class
