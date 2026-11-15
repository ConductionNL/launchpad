<?php

/**
 * DashboardCleanupListener
 *
 * Base for the listeners that remove a deleted dashboard's dependent rows
 * (REQ-CSC-003). It logs the outcome and isolates failures (REQ-CSC-006).
 *
 * @category  Listener
 * @package   OCA\LaunchPad\Listener
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

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs one cleanup per DashboardDeletedEvent and logs it: the count at
 * debug level, a failure at warning level without rethrowing, so peer
 * listeners still run.
 *
 * @implements IEventListener<DashboardDeletedEvent>
 */
abstract class DashboardCleanupListener implements IEventListener {
	/**
	 * The debug message for a finished cleanup; `%d` is the count.
	 */
	protected const CLEANED = 'deleted %d rows';

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger PSR-3 logger for log-and-continue
	 *                                failure handling (REQ-CSC-006).
	 */
	public function __construct(
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle the DashboardDeletedEvent.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-cascade-events/spec.md
	 */
	public function handle(Event $event): void {
		$name = substr(string: strrchr(haystack: static::class, needle: '\\'), offset: 1);
		if (($event instanceof DashboardDeletedEvent) === false) {
			return;
		}

		$uuid = $event->getDashboardUuid();

		try {
			$count = $this->cleanUp(event: $event);
			$this->logger->debug(
				message: sprintf('launchpad %s: ' . static::CLEANED . ' for dashboard %s', $name, $count, $uuid),
				context: ['app' => 'launchpad']
			);
		} catch (Throwable $t) {
			$this->logger->warning(
				message: sprintf('launchpad %s: failed for dashboard %s: %s', $name, $uuid, $t->getMessage()),
				context: ['app' => 'launchpad']
			);
		}
	}//end handle()

	/**
	 * Remove the deleted dashboard's rows.
	 *
	 * @param DashboardDeletedEvent $event The event.
	 *
	 * @return int The number of rows removed.
	 *
	 * @spec openspec/specs/dashboard-cascade-events/spec.md
	 */
	abstract protected function cleanUp(DashboardDeletedEvent $event): int;
}//end class
