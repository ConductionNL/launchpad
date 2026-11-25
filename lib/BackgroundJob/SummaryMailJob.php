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
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * A thin TimedJob shell over {@see SummaryMailService::sendDue()}.
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
	 * @param SummaryMailService $service Sends the due summaries.
	 * @param LoggerInterface    $logger  A failed run.
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly SummaryMailService $service,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: self::INTERVAL_SECONDS);
	}//end __construct()

	/**
	 * Send the due summaries.
	 *
	 * @param mixed $argument Unused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	protected function run($argument): void {
		try {
			$this->service->sendDue(now: DateTimeImmutable::createFromInterface($this->time->now()));
		} catch (Throwable $exception) {
			$this->logger->error('LaunchPad summary mail run failed: {message}', ['message' => $exception->getMessage()]);
		}
	}//end run()
}//end class
