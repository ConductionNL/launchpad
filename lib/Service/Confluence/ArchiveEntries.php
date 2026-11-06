<?php

/**
 * ArchiveEntries
 *
 * What one walk over a Confluence export has collected so far: the four
 * entry buckets and the running uncompressed size.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Confluence
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/confluence-html-import/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\Confluence;

/**
 * The buckets and running size of one archive walk.
 *
 * @spec openspec/specs/confluence-html-import/spec.md
 */
class ArchiveEntries {
	/**
	 * The collected entries: the index page, the content pages by name,
	 * and the attachment and image entry names.
	 *
	 * @var array{indexHtml: string|null, htmlFiles: array<string, string>, attachments: string[], images: string[]}
	 */
	public array $collected = [
		'indexHtml' => null,
		'htmlFiles' => [],
		'attachments' => [],
		'images' => [],
	];

	/**
	 * The uncompressed bytes read so far.
	 *
	 * @var integer
	 */
	public int $totalBytes = 0;
}//end class
