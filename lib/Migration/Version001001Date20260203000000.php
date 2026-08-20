<?php

/**
 * Version001001Date20260203000000
 *
 * Migration to add custom tiles feature.
 *
 * @category  Migration
 * @package   OCA\LaunchPad\Migration
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001001Date20260203000000 extends SimpleMigrationStep {
	/**
	 * Create the tiles table.
	 *
	 * @param IOutput $output The migration output handler.
	 * @param Closure $schemaClosure The schema closure returns an ISchemaWrapper.
	 * @param array $options The migration options.
	 *
	 * @return ISchemaWrapper|null The modified schema or null.
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// Get the schema wrapper.
		$schema = $schemaClosure();

		// Create launchpad_tiles table.
		if ($schema->hasTable('launchpad_tiles') === false) {
			$table = $schema->createTable('launchpad_tiles');

			foreach ($this->tileTableColumns() as $name => $spec) {
				$table->addColumn($name, $spec['type'], $spec['options']);
			}

			$table->setPrimaryKey(['id']);
			$table->addIndex(
				['user_id'],
				'launchpad_tiles_user'
			);
		}

		return $schema;
	}//end changeSchema()

	/**
	 * The column set of the `launchpad_tiles` table, keyed by column
	 * name.
	 *
	 * Declarative table extracted from {@see self::changeSchema()}.
	 * Array order is the physical column order of the created table.
	 *
	 * @return array<string, array{type: string, options: array<string, mixed>}> The column specs.
	 */
	private function tileTableColumns(): array {
		return [
			'id' => [
				'type' => Types::BIGINT,
				'options' => [
					'autoincrement' => true,
					'notnull' => true,
					'unsigned' => true,
				],
			],
			'user_id' => [
				'type' => Types::STRING,
				'options' => [
					'notnull' => true,
					'length' => 64,
				],
			],
			'title' => [
				'type' => Types::STRING,
				'options' => [
					'notnull' => true,
					'length' => 255,
				],
			],
			'icon' => [
				'type' => Types::STRING,
				'options' => [
					'notnull' => true,
					'length' => 2000,
					'comment' => 'Icon class, URL to icon image, or SVG path data.',
				],
			],
			'icon_type' => [
				'type' => Types::STRING,
				'options' => [
					'notnull' => true,
					'length' => 20,
					'default' => 'class',
					'comment' => 'Type of icon: class, url, or emoji.',
				],
			],
			'background_color' => [
				'type' => Types::STRING,
				'options' => [
					'notnull' => true,
					'length' => 7,
					'default' => '#0082c9',
					'comment' => 'Hex color code for tile background.',
				],
			],
			'text_color' => [
				'type' => Types::STRING,
				'options' => [
					'notnull' => true,
					'length' => 7,
					'default' => '#ffffff',
					'comment' => 'Hex color code for text.',
				],
			],
			'link_type' => [
				'type' => Types::STRING,
				'options' => [
					'notnull' => true,
					'length' => 20,
					'comment' => 'Type of link: app or url.',
				],
			],
			'link_value' => [
				'type' => Types::STRING,
				'options' => [
					'notnull' => true,
					'length' => 1000,
					'comment' => 'App ID or URL.',
				],
			],
			'created_at' => [
				'type' => Types::DATETIME,
				'options' => [
					'notnull' => true,
				],
			],
			'updated_at' => [
				'type' => Types::DATETIME,
				'options' => [
					'notnull' => true,
				],
			],
		];
	}//end tileTableColumns()
}//end class
