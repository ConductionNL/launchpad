<?php

/**
 * Version002013Date20261005190000
 *
 * Adds `launchpad_announcements` (news items and notices with targeting,
 * a publish window and a notified marker) and `launchpad_ann_follows`
 * (which categories a person follows) for engagement-announcements,
 * REQ-ANN-001..005. The follows table carries a short name because
 * Nextcloud refuses table names over 27 characters.
 *
 * @category Migration
 * @package  OCA\LaunchPad\Migration
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create the announcement tables.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
 */
class Version002013Date20261005190000 extends SimpleMigrationStep {
	/**
	 * Create both tables when they are not already there.
	 *
	 * @param IOutput $output Migration output.
	 * @param Closure $schemaClosure Schema closure (provides ISchemaWrapper).
	 * @param array $options Migration options (unused).
	 *
	 * @return ISchemaWrapper|null The modified schema, or null when unchanged.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Signature fixed by SimpleMigrationStep.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();
		$changed = false;

		if ($schema->hasTable('launchpad_announcements') === false) {
			$this->createAnnouncements(schema: $schema);
			$changed = true;
		}

		if ($schema->hasTable('launchpad_ann_follows') === false) {
			$table = $schema->createTable('launchpad_ann_follows');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('category', Types::STRING, ['notnull' => true, 'length' => 128]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'category'], 'launchpad_annf_user_cat');
			$table->addIndex(['category'], 'launchpad_annf_cat');
			$changed = true;
		}

		if ($changed === false) {
			return null;
		}

		return $schema;
	}//end changeSchema()

	/**
	 * Create the announcements table.
	 *
	 * @param ISchemaWrapper $schema The schema.
	 *
	 * @return void
	 */
	private function createAnnouncements(ISchemaWrapper $schema): void {
		$table = $schema->createTable('launchpad_announcements');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->addColumn('uuid', Types::STRING, ['notnull' => true, 'length' => 36]);
		$table->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 16, 'comment' => 'news or notice']);
		$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('body', Types::TEXT, ['notnull' => false]);
		$table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 128]);
		$table->addColumn('level', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'info', 'comment' => 'info or warning (notices)']);
		$table->addColumn('dismissible', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
		$table->addColumn('target_groups', Types::TEXT, ['notnull' => false, 'comment' => 'JSON list of group ids; empty means everyone']);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'draft', 'comment' => 'draft or published']);
		$table->addColumn('publish_at', Types::DATETIME, ['notnull' => false]);
		$table->addColumn('expires_at', Types::DATETIME, ['notnull' => false]);
		$table->addColumn('allow_comments', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
		$table->addColumn('author_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('notified_at', Types::DATETIME, ['notnull' => false]);
		$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
		$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['uuid'], 'launchpad_ann_uuid');
		$table->addIndex(['status', 'publish_at'], 'launchpad_ann_window');
	}//end createAnnouncements()
}//end class
