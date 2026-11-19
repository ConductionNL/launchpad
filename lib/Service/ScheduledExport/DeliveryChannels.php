<?php

/**
 * DeliveryChannels
 *
 * The Nextcloud services the delivery channels use, kept together to keep
 * the dispatcher's constructor short.
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

use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\Mail\IMailer;

/**
 * Mailer, HTTP client, Files and app config for the dispatcher.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-5
 */
final class DeliveryChannels {

	/**
	 * Constructor.
	 *
	 * @param IMailer        $mailer     The email channel.
	 * @param IClientService $http       The webhook channel.
	 * @param IRootFolder    $rootFolder The Files channel.
	 * @param IAppConfig     $appConfig  The outbound host allow-list.
	 */
	public function __construct(
		public readonly IMailer $mailer,
		public readonly IClientService $http,
		public readonly IRootFolder $rootFolder,
		public readonly IAppConfig $appConfig,
	) {
	}//end __construct()
}//end class
