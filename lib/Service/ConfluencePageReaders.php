<?php

/**
 * ConfluencePageReaders
 *
 * What the Confluence importer reads a page with: the archive parser, the HTML sanitizer, the macro
 * renderer and the link rewriter.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Service\Confluence\ArchiveParser;
use OCA\LaunchPad\Service\Confluence\HtmlSanitizer;
use OCA\LaunchPad\Service\Confluence\LinkRewriter;
use OCA\LaunchPad\Service\Confluence\MacroRenderer;

/**
 * What the Confluence importer reads a page with: the archive parser, the HTML sanitizer, the macro
 * renderer and the link rewriter.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class ConfluencePageReaders {
	/**
	 * Constructor.
	 *
	 * @param ArchiveParser $parser Archive reader.
	 * @param HtmlSanitizer $sanitizer HTML allow-list filter.
	 * @param MacroRenderer $macroRenderer Macro → HTML renderer.
	 * @param LinkRewriter  $linkRewriter Internal-link rewriter.
	 */
	public function __construct(
		public readonly ArchiveParser $parser,
		public readonly HtmlSanitizer $sanitizer,
		public readonly MacroRenderer $macroRenderer,
		public readonly LinkRewriter $linkRewriter,
	) {
	}//end __construct()
}//end class
