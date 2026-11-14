<?php

/**
 * AnnouncementTablesBuilder
 *
 * Builder for the two announcement tables (engagement-announcements design,
 * "Data"): `launchpad_announcements`, one row per news item or notice, and
 * `launchpad_ann_follows`, one row per (user, category) a reader follows.
 * Likes and comments are not stored here: they live in Nextcloud's comments
 * service (design D2). Dismissed notices live in the user's preferences.
 *
 * @category  Migration
 * @package   OCA\LaunchPad\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/engagement-announcements/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;

/**
 * Creates the announcement and follow tables when they do not exist.
 *
 * @spec openspec/changes/engagement-announcements/tasks.md#task-1
 */
class AnnouncementTablesBuilder {

	/**
	 * The announcements table.
	 */
	public const ANNOUNCEMENTS = 'launchpad_announcements';

	/**
	 * The follows table.
	 */
	public const FOLLOWS = 'launchpad_ann_follows';

	/**
	 * Create both tables; an existing table is left as it is.
	 *
	 * @param ISchemaWrapper $schema The schema.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/engagement-announcements/tasks.md#task-1
	 */
	public static function create(ISchemaWrapper $schema): void {
		if ($schema->hasTable(self::ANNOUNCEMENTS) === false) {
			self::createAnnouncements(schema: $schema);
		}

		if ($schema->hasTable(self::FOLLOWS) === false) {
			self::createFollows(schema: $schema);
		}
	}//end create()

	/**
	 * The announcements table.
	 *
	 * @param ISchemaWrapper $schema The schema.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/engagement-announcements/tasks.md#task-1
	 */
	private static function createAnnouncements(ISchemaWrapper $schema): void {
		$table = $schema->createTable(self::ANNOUNCEMENTS);
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->addColumn('uuid', Types::STRING, ['notnull' => true, 'length' => 36]);
		$table->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 16, 'comment' => 'news or notice.']);
		$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('body', Types::TEXT, ['notnull' => false, 'comment' => 'Markdown, sanitised on render.']);
		$table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('level', Types::STRING, ['notnull' => false, 'length' => 16, 'comment' => 'info or warning; notices only.']);
		$table->addColumn('dismissible', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('target_groups', Types::TEXT, ['notnull' => false, 'comment' => 'JSON list of group ids; empty means everyone.']);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'draft', 'comment' => 'draft or published.']);
		$table->addColumn('publish_at', Types::DATETIME, ['notnull' => false]);
		$table->addColumn('expires_at', Types::DATETIME, ['notnull' => false, 'comment' => 'Required for a notice.']);
		$table->addColumn('allow_comments', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('author_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('notified_at', Types::DATETIME, ['notnull' => false, 'comment' => 'When followers were told; the notify job skips it.']);
		$table->addColumn('created_at', Types::DATETIME, ['notnull' => false]);
		$table->addColumn('updated_at', Types::DATETIME, ['notnull' => false]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['uuid'], 'launchpad_ann_uuid');
		$table->addIndex(['status', 'publish_at'], 'launchpad_ann_status_pub');
		$table->addIndex(['category'], 'launchpad_ann_category');
	}//end createAnnouncements()

	/**
	 * The follows table.
	 *
	 * @param ISchemaWrapper $schema The schema.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/engagement-announcements/tasks.md#task-1
	 */
	private static function createFollows(ISchemaWrapper $schema): void {
		$table = $schema->createTable(self::FOLLOWS);
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('category', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('created_at', Types::DATETIME, ['notnull' => false]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id', 'category'], 'launchpad_ann_follow_uc');
		$table->addIndex(['category'], 'launchpad_ann_follow_cat');
	}//end createFollows()
}//end class
