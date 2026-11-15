<?php

/**
 * OfficeFileRequest
 *
 * What a "New ..." office tile asks for: the file creator, the file name
 * without extension and the folder (integrations-office-tiles, REQ-OFT-002).
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The creator, name and folder of one file an office tile creates.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
 */
final class OfficeFileRequest {

	/**
	 * Constructor
	 *
	 * @param string $creatorId The creator, as the pack names it (`<app>:<extension>`).
	 * @param string $name      The file name without extension.
	 * @param string $folder    The folder, created when missing (default /Documents).
	 *
	 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
	 */
	public function __construct(
		public readonly string $creatorId,
		public readonly string $name,
		public readonly string $folder = OfficeTilesService::DEFAULT_FOLDER,
	) {
	}//end __construct()
}//end class
