<?php

/**
 * ResourceTools
 *
 * The resource service, the upload parser and the log of the resource API.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\ResourceService;
use Psr\Log\LoggerInterface;

/**
 * The resource service, the upload parser and the log of the resource API.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
class ResourceTools {
	/**
	 * Constructor.
	 *
	 * @param ResourceService             $resourceService The upload pipeline.
	 * @param ResourceUploadRequestParser $parser Parses request body.
	 * @param LoggerInterface             $logger PSR logger.
	 */
	public function __construct(
		public readonly ResourceService $resourceService,
		public readonly ResourceUploadRequestParser $parser,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
