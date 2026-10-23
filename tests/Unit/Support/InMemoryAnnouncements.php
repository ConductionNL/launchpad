<?php

/**
 * InMemoryAnnouncements
 *
 * The real AnnouncementMapper with its storage swapped for an array, so the
 * service's targeting, window and notification logic run unchanged. The SQL
 * itself is proven by AnnouncementsDatabaseTest against a real database.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Support
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Support;

use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;

/**
 * In-memory announcements.
 */
class InMemoryAnnouncements extends AnnouncementMapper {
	/**
	 * Stored rows by id.
	 *
	 * @var array<int, Announcement>
	 */
	public array $rows = [];

	/**
	 * Next id.
	 *
	 * @var integer
	 */
	private int $nextId = 1;

	/**
	 * No database.
	 */
	public function __construct() {
	}//end __construct()

	/**
	 * Store a new row.
	 *
	 * @param Entity $entity The row.
	 *
	 * @return Entity
	 */
	public function insert(Entity $entity): Entity {
		$entity->setId($this->nextId++);
		$this->rows[$entity->getId()] = $entity;

		return $entity;
	}//end insert()

	/**
	 * Store a changed row.
	 *
	 * @param Entity $entity The row.
	 *
	 * @return Entity
	 */
	public function update(Entity $entity): Entity {
		$this->rows[$entity->getId()] = $entity;

		return $entity;
	}//end update()

	/**
	 * Remove a row.
	 *
	 * @param Entity $entity The row.
	 *
	 * @return Entity
	 */
	public function delete(Entity $entity): Entity {
		unset($this->rows[$entity->getId()]);

		return $entity;
	}//end delete()

	/**
	 * One row by uuid.
	 *
	 * @param string $uuid The uuid.
	 *
	 * @return Announcement
	 */
	public function findByUuid(string $uuid): Announcement {
		foreach ($this->rows as $row) {
			if ($row->getUuid() === $uuid) {
				return $row;
			}
		}

		throw new DoesNotExistException('none');
	}//end findByUuid()

	/**
	 * The same filter as the SQL: published, started, not ended.
	 *
	 * @param string $now UTC time.
	 *
	 * @return Announcement[]
	 */
	public function findLive(string $now): array {
		$live = array_filter(
			$this->rows,
			static fn (Announcement $row): bool => $row->getStatus() === Announcement::STATUS_PUBLISHED
				&& $row->getPublishAt() !== null && $row->getPublishAt() <= $now
				&& ($row->getExpiresAt() === null || $row->getExpiresAt() > $now)
		);
		usort($live, static fn (Announcement $a, Announcement $b): int => [$b->getPublishAt(), $b->getId()] <=> [$a->getPublishAt(), $a->getId()]);

		return $live;
	}//end findLive()

	/**
	 * Every row, newest first.
	 *
	 * @return Announcement[]
	 */
	public function findAllNewestFirst(): array {
		return array_reverse(array_values($this->rows));
	}//end findAllNewestFirst()

	/**
	 * Live rows not notified yet.
	 *
	 * @param string $now UTC time.
	 *
	 * @return Announcement[]
	 */
	public function findDueForNotification(string $now): array {
		return array_values(
			array_filter($this->findLive(now: $now), static fn (Announcement $row): bool => $row->getNotifiedAt() === null)
		);
	}//end findDueForNotification()
}//end class
