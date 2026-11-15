<?php

/**
 * ProfileFieldTools
 *
 * The admin template service and the logger of the profile field service.
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
 * @spec openspec/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use Psr\Log\LoggerInterface;

/**
 * The admin template service and the logger of the profile field service.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class ProfileFieldTools {
	/**
	 * Constructor.
	 *
	 * @param AdminTemplateService $adminTemplateService Group ids of a user.
	 * @param LoggerInterface      $logger Logger.
	 */
	public function __construct(
		public readonly AdminTemplateService $adminTemplateService,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
