<?php

/**
 * KioskPlaylist Entity
 *
 * Represents a row in launchpad_kiosk_playlists — a named, ordered list of
 * dashboards rendered chrome-less on a wall display and addressed by a
 * URL-safe token. The token grants anonymous read access to every
 * referenced dashboard, so it is a first-class access grant.
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
 *
 * @method string|null getName()
 * @method void setName(?string $name)
 * @method string|null getToken()
 * @method void setToken(?string $token)
 * @method string|null getEntries()
 * @method void setEntries(?string $entries)
 * @method int|null getRefreshSeconds()
 * @method void setRefreshSeconds(?int $refreshSeconds)
 * @method string|null getCreatedBy()
 * @method void setCreatedBy(?string $createdBy)
 * @method string|null getCreatedAt()
 * @method void setCreatedAt(?string $createdAt)
 * @method string|null getRevokedAt()
 * @method void setRevokedAt(?string $revokedAt)
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Kiosk playlist entity. jsonSerialize() decodes the entries JSON column
 * into a structured array and exposes the computed public URL.
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
 *
 * @method string|null getName()
 * @method void setName(?string $name)
 * @method string|null getToken()
 * @method void setToken(?string $token)
 * @method string|null getEntries()
 * @method void setEntries(?string $entries)
 * @method int|null getRefreshSeconds()
 * @method void setRefreshSeconds(?int $refreshSeconds)
 * @method string|null getCreatedBy()
 * @method void setCreatedBy(?string $createdBy)
 * @method string|null getCreatedAt()
 * @method void setCreatedAt(?string $createdAt)
 * @method string|null getRevokedAt()
 * @method void setRevokedAt(?string $revokedAt)
 */
class KioskPlaylist extends Entity implements JsonSerializable {

	/**
	 * Human-readable playlist name.
	 *
	 * @var string|null
	 */
	protected ?string $name = null;

	/**
	 * URL-safe 64-byte random access token.
	 *
	 * @var string|null
	 */
	protected ?string $token = null;

	/**
	 * JSON-encoded array of {dashboardUuid, dwellSeconds} entries.
	 *
	 * @var string|null
	 */
	protected ?string $entries = null;

	/**
	 * In-place widget refresh interval in seconds (clamped [30, 86400]).
	 *
	 * @var integer|null
	 */
	protected ?int $refreshSeconds = null;

	/**
	 * Nextcloud user ID who created this playlist.
	 *
	 * @var string|null
	 */
	protected ?string $createdBy = null;

	/**
	 * Creation timestamp.
	 *
	 * @var string|null
	 */
	protected ?string $createdAt = null;

	/**
	 * Soft-delete timestamp; null means the playlist is still active.
	 *
	 * @var string|null
	 */
	protected ?string $revokedAt = null;

	/**
	 * Computed public URL — populated by KioskPlaylistMapper after load, not a DB column.
	 *
	 * @var string|null
	 */
	private ?string $url = null;

	/**
	 * Constructor — registers column types.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
		$this->addType(fieldName: 'refreshSeconds', type: 'integer');
	}//end __construct()

	/**
	 * Set the computed public URL (not stored in DB).
	 *
	 * @param string $url The absolute kiosk URL.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
	 */
	public function setUrl(string $url): void {
		$this->url = $url;
	}//end setUrl()

	/**
	 * Get the computed public URL.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
	 */
	public function getUrl(): ?string {
		return $this->url;
	}//end getUrl()

	/**
	 * Decode the entries JSON column into a structured array.
	 *
	 * Each entry is normalised to {dashboardUuid: string, dwellSeconds: int}.
	 * Malformed JSON yields an empty array rather than throwing, so a corrupt
	 * row never crashes a render path.
	 *
	 * @return array<int, array{dashboardUuid: string, dwellSeconds: int}>
	 *
	 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
	 */
	public function getEntriesArray(): array {
		if ($this->entries === null || $this->entries === '') {
			return [];
		}

		$decoded = json_decode(json: $this->entries, associative: true);
		if (is_array($decoded) === false) {
			return [];
		}

		$normalised = [];
		foreach ($decoded as $entry) {
			if (is_array($entry) === false
				|| isset($entry['dashboardUuid']) === false
			) {
				continue;
			}

			$normalised[] = [
				'dashboardUuid' => (string)$entry['dashboardUuid'],
				'dwellSeconds' => (int)($entry['dwellSeconds'] ?? 0),
			];
		}

		return $normalised;
	}//end getEntriesArray()

	/**
	 * Serialize to JSON.
	 *
	 * @return array
	 *
	 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-2
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->name,
			'token' => $this->token,
			'url' => $this->url,
			'entries' => $this->getEntriesArray(),
			'refreshSeconds' => ($this->refreshSeconds ?? 300),
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'revokedAt' => $this->revokedAt,
		];
	}//end jsonSerialize()
}//end class
