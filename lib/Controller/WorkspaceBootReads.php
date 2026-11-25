<?php

/**
 * WorkspaceBootReads
 *
 * The tiles, the pending acknowledgements and the default-dashboard pin the
 * workspace page carries in its initial state.
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
 * @spec openspec/changes/workspace-boot-reads-from-initial-state/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Kernel\Contracts\AcknowledgementServiceInterface;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\TileService;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * What the workspace view reads while it boots, besides its dashboards: the
 * answers of `GET /api/tiles`, `GET /api/acknowledgements/pending` and
 * `GET /api/dashboards/default` for the caller.
 *
 * @spec openspec/changes/workspace-boot-reads-from-initial-state/tasks.md#task-1
 */
class WorkspaceBootReads {
	/**
	 * Constructor.
	 *
	 * @param TileService                     $tileService      The caller's tiles.
	 * @param AcknowledgementServiceInterface $acknowledgements The caller's outstanding mandatory reads.
	 * @param DashboardService                $dashboardService The caller's default-dashboard pin.
	 * @param LoggerInterface                 $logger           Records a read that failed and was left out.
	 * @param RequestActor|null               $actor            The action matrix check; without it nothing is given.
	 */
	public function __construct(
		private readonly TileService $tileService,
		private readonly AcknowledgementServiceInterface $acknowledgements,
		private readonly DashboardService $dashboardService,
		private readonly LoggerInterface $logger,
		private readonly ?RequestActor $actor=null,
	) {
	}//end __construct()

	/**
	 * `{tiles, pendingAcknowledgements, defaultDashboard}` with exactly what
	 * the three endpoints answer for the caller. A part the caller may not
	 * read, or that fails, is left out, so the page asks its endpoint as
	 * before. Null when nothing is left, without a user or without the
	 * action check.
	 *
	 * @param string $userId The caller's uid.
	 *
	 * @return array<string, mixed>|null The reads, or null.
	 *
	 * @spec openspec/changes/workspace-boot-reads-from-initial-state/tasks.md#task-1
	 */
	public function firstLoad(string $userId): ?array {
		if ($userId === '' || $this->actor === null) {
			return null;
		}

		$reads = [];
		if ($this->actor->refusal(userId: $userId, action: 'tile.index') === null) {
			$reads['tiles'] = array_map(
				static fn (object $tile): mixed => $tile->jsonSerialize(),
				$this->tileService->getUserTiles(userId: $userId)
			);
		}

		if ($this->actor->refusal(userId: $userId, action: 'dashboard.get-default-dashboard') === null) {
			$reads['defaultDashboard'] = ['uuid' => $this->dashboardService->getDefaultPreference(userId: $userId)];
		}

		try {
			$reads['pendingAcknowledgements'] = $this->acknowledgements->getPending(userId: $userId);
		} catch (Throwable $e) {
			$this->logger->warning(
				'LaunchPad: pending acknowledgements left out of the page: '.$e->getMessage(),
				['app' => 'launchpad']
			);
		}

		if ($reads === []) {
			return null;
		}

		return $reads;
	}//end firstLoad()
}//end class
