<?php

/**
 * SummaryMailJob
 *
 * Hourly: send the summary emails that are due (personal-settings
 * REQ-PSET-002). The decision who is due lives in SummaryMailService.
 *
 * @category  BackgroundJob
 * @package   OCA\LaunchPad\BackgroundJob
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

namespace OCA\LaunchPad\BackgroundJob;

use DateTimeImmutable;
use OCA\LaunchPad\Service\SummaryMailService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Every hour, hands the clock to {@see SummaryMailService::sendDue()}, which
 * decides per person whether their 07:30 has come. A send that fails is
 * logged by the sender and tried again on the next run.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter) — $argument required by TimedJob interface.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */
class SummaryMailJob extends TimedJob {

	/**
	 * Seconds between runs.
	 *
	 * @var int
	 */
	public const INTERVAL_SECONDS = 3600;

	/**
	 * Constructor.
	 *
	 * @param ITimeFactory       $time    The clock.
	 * @param SummaryMailService $summary Sends the due summaries.
	 */
	public function __construct(ITimeFactory $time, private readonly SummaryMailService $summary) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: self::INTERVAL_SECONDS);
	}//end __construct()

	/**
	 * Send the summaries that are due at this moment.
	 *
	 * @param mixed $argument Unused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	#[\Override]
	protected function run($argument): void {
		$this->summary->sendDue(now: new DateTimeImmutable('@'.$this->time->getTime()));
	}//end run()
}//end class
