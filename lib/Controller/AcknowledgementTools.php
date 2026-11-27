<?php

/**
 * AcknowledgementTools
 *
 * The acknowledgement service, who manages announcements, and the log of the acknowledgement API.
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
 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Kernel\Contracts\AcknowledgementServiceInterface;
use OCA\LaunchPad\Service\AnnouncementManagers;
use OCA\LaunchPad\Service\ReadReceiptPeople;
use Psr\Log\LoggerInterface;

/**
 * The acknowledgement and role services and the log of the acknowledgement API.
 *
 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
 */
class AcknowledgementTools {
	/**
	 * Constructor.
	 *
	 * @param AcknowledgementServiceInterface $acknowledgements The service.
	 * @param AnnouncementManagers   $managers    Who holds the announcements setting (REQ-ACK-004).
	 * @param LoggerInterface        $logger PSR logger.
	 * @param ReadReceiptPeople|null $people The names and departments on a report (REQ-ACK-010).
	 */
	public function __construct(
		public readonly AcknowledgementServiceInterface $acknowledgements,
		public readonly AnnouncementManagers $managers,
		public readonly LoggerInterface $logger,
		public readonly ?ReadReceiptPeople $people = null,
	) {
	}//end __construct()
}//end class
