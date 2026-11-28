<?php

/**
 * ShippedTemplateTools
 *
 * The shipped template and update services and the log of the shipped template admin API.
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

use OCA\LaunchPad\Kernel\Contracts\ShippedTemplateServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\ShippedTemplateUpdateServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * The shipped template and update services and the log of the shipped template admin API.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
class ShippedTemplateTools {
	/**
	 * Constructor.
	 *
	 * @param ShippedTemplateServiceInterface       $templates Shipped template service.
	 * @param ShippedTemplateUpdateServiceInterface $updates Updates an installed template in place.
	 * @param LoggerInterface              $logger Logger.
	 */
	public function __construct(
		public readonly ShippedTemplateServiceInterface $templates,
		public readonly ShippedTemplateUpdateServiceInterface $updates,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
