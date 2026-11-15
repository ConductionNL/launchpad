<?php

/**
 * FilesWidgetUploadTarget
 *
 * The folder a files-widget upload writes into and the placement's
 * normalised MIME type filter the upload is screened against.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/files-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\Files\Folder;

/**
 * The folder a files-widget upload writes into and its MIME filter.
 *
 * @spec openspec/specs/files-widget/spec.md
 */
final class FilesWidgetUploadTarget {
	/**
	 * Constructor.
	 *
	 * @param Folder       $folder     The writable destination folder.
	 * @param list<string> $mimeFilter Normalised MIME filter; `[]` means allow all.
	 */
	public function __construct(
		public readonly Folder $folder,
		public readonly array $mimeFilter,
	) {
	}//end __construct()
}//end class
