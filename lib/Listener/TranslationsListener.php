<?php

/**
 * TranslationsListener
 *
 * Cleans up `oc_launchpad_dash_translations` rows when a dashboard
 * is soft-deleted. Stub registered as part of the cascade-events
 * scaffolding; the live implementation is owned by the
 * dashboard-language-content follow-up. REQ-CSC-003.
 *
 * @category  Listener
 * @package   OCA\LaunchPad\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\Db\DashboardTranslationMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * Deletes dashboard translation rows for the deleted dashboard.
 * C4 fix (REQ-CSC-003).
 *
 */
class TranslationsListener extends DashboardCleanupListener {
	protected const CLEANED = 'deleted %d translation rows';

	/**
	 * Constructor.
	 *
	 * @param DashboardTranslationMapper $translationMapper Translation mapper.
	 * @param LoggerInterface $logger PSR-3 logger for
	 *                                log-and-continue
	 *                                failure handling per
	 *                                REQ-CSC-006.
	 */
	public function __construct(
		private readonly DashboardTranslationMapper $translationMapper,
		LoggerInterface $logger,
	) {
		parent::__construct(logger: $logger);
	}//end __construct()

	/**
	 * Remove the deleted dashboard's rows.
	 *
	 * @param DashboardDeletedEvent $event The event.
	 *
	 * @return int The number of rows removed.
	 */
	protected function cleanUp(DashboardDeletedEvent $event): int {
		return $this->translationMapper->deleteByDashboardUuid(
			dashboardUuid: $event->getDashboardUuid()
		);
	}//end cleanUp()
}//end class
