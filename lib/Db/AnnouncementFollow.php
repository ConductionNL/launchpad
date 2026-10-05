<?php

/**
 * AnnouncementFollow Entity
 *
 * One category a person follows; they get a notification when an
 * announcement in it becomes visible to them (REQ-ANN-003).
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
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

namespace OCA\LaunchPad\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Announcement follow entity.
 *
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getCategory()
 * @method void setCategory(string $category)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
 */
class AnnouncementFollow extends Entity {

	/**
	 * Follower user id.
	 *
	 * @var string
	 */
	protected string $userId = '';

	/**
	 * Followed category.
	 *
	 * @var string
	 */
	protected string $category = '';

	/**
	 * Created at.
	 *
	 * @var string
	 */
	protected string $createdAt = '';

	/**
	 * Register the column types.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function __construct() {
		$this->addType(fieldName: 'id', type: 'integer');
	}//end __construct()
}//end class
