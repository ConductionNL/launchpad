<?php

/**
 * KioskService
 *
 * Business logic for kiosk playlists — named, ordered lists of dashboards
 * rendered chrome-less on wall displays and addressed by a single token URL.
 *
 * A playlist token is an anonymous read grant over every referenced
 * dashboard, so creation and update validate owner-or-admin permission per
 * entry (reusing the dashboard-public-share authorization rule). The public
 * render path re-checks dashboard existence and SKIPS (does not error) any
 * entry whose dashboard has since been deleted.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-3
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Db\KioskPlaylistMapper;
use OCA\LaunchPad\Exception\PlaylistNotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

/**
 * Service for kiosk-playlist lifecycle management and public render.
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-3
 */
class KioskService {

	/**
	 * Minimum dwell time per entry, in seconds.
	 *
	 * @var integer
	 */
	public const DWELL_MIN = 10;

	/**
	 * Maximum dwell time per entry, in seconds (24 hours).
	 *
	 * @var integer
	 */
	public const DWELL_MAX = 86400;

	/**
	 * Minimum in-place refresh interval, in seconds.
	 *
	 * @var integer
	 */
	public const REFRESH_MIN = 30;

	/**
	 * Maximum in-place refresh interval, in seconds (24 hours).
	 *
	 * @var integer
	 */
	public const REFRESH_MAX = 86400;

	/**
	 * Default in-place refresh interval, in seconds.
	 *
	 * @var integer
	 */
	public const REFRESH_DEFAULT = 300;

	/**
	 * Constructor.
	 *
	 * @param KioskPlaylistMapper $playlistMapper Mapper for kiosk playlists.
	 * @param DashboardMapper $dashMapper Dashboard mapper for existence/ownership.
	 * @param PublicShareService $shareService Reused owner-or-admin authorization rule.
	 * @param IGroupManager $groupManager NC group manager for admin scoping.
	 * @param ISecureRandom $secureRandom CSPRNG for token generation.
	 * @param LoggerInterface $logger PSR-3 logger.
	 */
	public function __construct(
		private readonly KioskPlaylistMapper $playlistMapper,
		private readonly DashboardMapper $dashMapper,
		private readonly PublicShareService $shareService,
		private readonly IGroupManager $groupManager,
		private readonly ISecureRandom $secureRandom,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Create a new kiosk playlist.
	 *
	 * Validates owner-or-admin permission for every referenced dashboard
	 * (REQ-KIOSK-002): a single unauthorized dashboard rejects the whole
	 * request. Dwell and refresh values are clamped before storage.
	 *
	 * @param string $name Playlist name.
	 * @param array $entries Raw entries [{dashboardUuid, dwellSeconds}, ...].
	 * @param int $refresh Requested refresh interval in seconds.
	 * @param string $callerId User ID of the creating user.
	 *
	 * @return KioskPlaylist The new playlist with URL populated.
	 *
	 * @throws OCSForbiddenException When the caller may not share a referenced dashboard.
	 *
	 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-3
	 */
	public function createPlaylist(
		string $name,
		array $entries,
		int $refresh,
		string $callerId,
	): KioskPlaylist {
		$normalised = $this->validateAndNormaliseEntries(
			entries: $entries,
			callerId: $callerId
		);

		$playlist = new KioskPlaylist();
		// phpcs:disable CustomSn.Functions.NamedParameters -- Entity magic __call breaks with named args.
		$playlist->setName($name);
		$playlist->setToken(
			$this->secureRandom->generate(
				length: 64,
				characters: ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS
			)
		);
		$playlist->setEntries((string)json_encode($normalised));
		$playlist->setRefreshSeconds($this->clampRefresh(refresh: $refresh));
		$playlist->setCreatedBy($callerId);
		$playlist->setCreatedAt((new DateTime())->format('Y-m-d H:i:s'));
		// phpcs:enable CustomSn.Functions.NamedParameters

		$saved = $this->playlistMapper->insert(entity: $playlist);

		$this->logger->debug(
			message: sprintf('launchpad: kiosk playlist created by %s', $callerId),
			context: ['app' => 'launchpad']
		);

		return $saved;
	}//end createPlaylist()

	/**
	 * Update an existing kiosk playlist.
	 *
	 * Re-validates owner-or-admin permission for every referenced dashboard
	 * (REQ-KIOSK-002). On any unauthorized entry the playlist is left
	 * unchanged and OCSForbiddenException is thrown.
	 *
	 * @param int $id Playlist primary key.
	 * @param string $name New playlist name.
	 * @param array $entries Raw entries [{dashboardUuid, dwellSeconds}, ...].
	 * @param int $refresh Requested refresh interval in seconds.
	 * @param string $callerId Caller user ID.
	 *
	 * @return KioskPlaylist The updated playlist with URL populated.
	 *
	 * @throws PlaylistNotFoundException When the playlist does not exist or is revoked.
	 * @throws OCSForbiddenException When the caller may not edit it or share a dashboard.
	 *
	 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-3
	 */
	public function updatePlaylist(
		int $id,
		string $name,
		array $entries,
		int $refresh,
		string $callerId,
	): KioskPlaylist {
		$playlist = $this->resolveOwnedPlaylist(id: $id, callerId: $callerId);

		$normalised = $this->validateAndNormaliseEntries(
			entries: $entries,
			callerId: $callerId
		);

		// phpcs:disable CustomSn.Functions.NamedParameters
		$playlist->setName($name);
		$playlist->setEntries((string)json_encode($normalised));
		$playlist->setRefreshSeconds($this->clampRefresh(refresh: $refresh));
		// phpcs:enable CustomSn.Functions.NamedParameters

		$saved = $this->playlistMapper->update(entity: $playlist);
		return $this->playlistMapper->findById(id: (int)$saved->getId());
	}//end updatePlaylist()

	/**
	 * List playlists visible to the caller.
	 *
	 * Regular users see their own active playlists; admins see all active
	 * playlists (REQ-KIOSK-002).
	 *
	 * @param string $callerId Caller user ID.
	 *
	 * @return KioskPlaylist[]
	 *
	 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-3
	 */
	public function listPlaylists(string $callerId): array {
		if ($this->groupManager->isAdmin(userId: $callerId) === true) {
			return $this->playlistMapper->findAllActive();
		}

		return $this->playlistMapper->findByCreator(createdBy: $callerId);
	}//end listPlaylists()

	/**
	 * Soft-revoke a playlist.
	 *
	 * Owner-or-admin only. Idempotent — revoking an already-revoked playlist
	 * is a no-op once the caller is authorized.
	 *
	 * @param int $id Playlist primary key.
	 * @param string $callerId Caller user ID.
	 *
	 * @return void
	 *
	 * @throws PlaylistNotFoundException When the playlist does not exist or is revoked.
	 * @throws OCSForbiddenException When the caller is neither owner nor admin.
	 *
	 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-3
	 */
	public function revokePlaylist(int $id, string $callerId): void {
		$playlist = $this->resolveOwnedPlaylist(id: $id, callerId: $callerId);
		$this->playlistMapper->softRevoke(id: (int)$playlist->getId());
	}//end revokePlaylist()

	/**
	 * Render a playlist for anonymous wall-display access.
	 *
	 * Returns the playlist descriptor plus the read-only render payload for
	 * each entry whose dashboard still exists. Entries referencing a deleted
	 * dashboard are omitted (no error, no placeholder slot) per
	 * REQ-KIOSK-003. Unknown or revoked tokens throw PlaylistNotFoundException
	 * (HTTP 404) with no existence leak.
	 *
	 * @param string $token The playlist token.
	 *
	 * @return array{playlist: array, entries: array<int, array>}
	 *
	 * @throws PlaylistNotFoundException When the token is unknown or revoked.
	 *
	 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-3
	 */
	public function renderPlaylist(string $token): array {
		try {
			$playlist = $this->playlistMapper->findByToken(token: $token);
		} catch (DoesNotExistException) {
			throw new PlaylistNotFoundException();
		}

		$rendered = [];
		foreach ($playlist->getEntriesArray() as $entry) {
			try {
				$dashboard = $this->dashMapper->findByUuid(
					uuid: $entry['dashboardUuid']
				);
			} catch (DoesNotExistException) {
				// Dashboard deleted since the playlist was created — skip it.
				continue;
			}

			$rendered[] = [
				'dwellSeconds' => $entry['dwellSeconds'],
				'dashboard' => $this->publicDashboardPayload(dashboard: $dashboard),
			];
		}

		$descriptor = $playlist->jsonSerialize();
		// The token IS the public credential already in the URL; the
		// createdBy attribution is not needed by an anonymous renderer.
		unset($descriptor['createdBy']);

		return [
			'playlist' => $descriptor,
			'entries' => $rendered,
		];
	}//end renderPlaylist()

	/**
	 * Validate and normalise raw playlist entries.
	 *
	 * Each entry MUST reference an existing dashboard the caller may share
	 * (owner-or-admin); a single failure rejects the whole request. Dwell
	 * times are clamped to [DWELL_MIN, DWELL_MAX].
	 *
	 * @param array $entries Raw entries.
	 * @param string $callerId Caller user ID.
	 *
	 * @return array<int, array{dashboardUuid: string, dwellSeconds: int}>
	 *
	 * @throws OCSForbiddenException When a referenced dashboard is missing or unauthorized.
	 */
	private function validateAndNormaliseEntries(array $entries, string $callerId): array {
		$normalised = [];

		foreach ($entries as $entry) {
			$uuid = '';
			if (is_array($entry) === true && isset($entry['dashboardUuid']) === true) {
				$uuid = (string)$entry['dashboardUuid'];
			}

			if ($uuid === '') {
				throw new OCSForbiddenException('Invalid playlist entry');
			}

			try {
				$dashboard = $this->dashMapper->findByUuid(uuid: $uuid);
			} catch (DoesNotExistException) {
				// Treat a missing dashboard as a permission failure to avoid
				// leaking which UUIDs exist (matches public-share 404/403 model).
				throw new OCSForbiddenException('Not authorized');
			}

			// Reuse the dashboard-public-share owner-or-admin rule: the
			// playlist token grants anonymous read, so the caller must be
			// allowed to share each dashboard (REQ-PSHR-001).
			$this->shareService->authorizeShareMutation(
				dashboard: $dashboard,
				userId: $callerId
			);

			$dwell = self::DWELL_MIN;
			if (is_array($entry) === true && isset($entry['dwellSeconds']) === true) {
				$dwell = $this->clampDwell(dwell: (int)$entry['dwellSeconds']);
			}

			$normalised[] = [
				'dashboardUuid' => $uuid,
				'dwellSeconds' => $dwell,
			];
		}//end foreach

		return $normalised;
	}//end validateAndNormaliseEntries()

	/**
	 * Resolve a playlist the caller owns (or is admin of).
	 *
	 * @param int $id Playlist primary key.
	 * @param string $callerId Caller user ID.
	 *
	 * @return KioskPlaylist
	 *
	 * @throws PlaylistNotFoundException When the playlist does not exist or is revoked.
	 * @throws OCSForbiddenException When the caller is neither owner nor admin.
	 */
	private function resolveOwnedPlaylist(int $id, string $callerId): KioskPlaylist {
		try {
			$playlist = $this->playlistMapper->findById(id: $id);
		} catch (DoesNotExistException) {
			throw new PlaylistNotFoundException();
		}

		$isOwner = ($playlist->getCreatedBy() === $callerId);
		$isAdmin = $this->groupManager->isAdmin(userId: $callerId);
		if ($isOwner === false && $isAdmin === false) {
			throw new OCSForbiddenException('Not authorized');
		}

		return $playlist;
	}//end resolveOwnedPlaylist()

	/**
	 * Build the read-only public dashboard payload for an anonymous renderer.
	 *
	 * Mirrors the public-share render shape: identity and presentation
	 * metadata only, no owner attribution.
	 *
	 * @param Dashboard $dashboard The dashboard to expose.
	 *
	 * @return array
	 */
	private function publicDashboardPayload(Dashboard $dashboard): array {
		$payload = $dashboard->jsonSerialize();
		unset($payload['userId'], $payload['user_id']);
		return $payload;
	}//end publicDashboardPayload()

	/**
	 * Clamp a dwell value to [DWELL_MIN, DWELL_MAX].
	 *
	 * @param int $dwell Requested dwell seconds.
	 *
	 * @return int Clamped dwell seconds.
	 */
	private function clampDwell(int $dwell): int {
		return max(self::DWELL_MIN, min(self::DWELL_MAX, $dwell));
	}//end clampDwell()

	/**
	 * Clamp a refresh value to [REFRESH_MIN, REFRESH_MAX].
	 *
	 * @param int $refresh Requested refresh seconds.
	 *
	 * @return int Clamped refresh seconds.
	 */
	private function clampRefresh(int $refresh): int {
		if ($refresh <= 0) {
			return self::REFRESH_DEFAULT;
		}

		return max(self::REFRESH_MIN, min(self::REFRESH_MAX, $refresh));
	}//end clampRefresh()
}//end class
