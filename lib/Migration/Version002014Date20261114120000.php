<?php

/**
 * Version002014Date20261114120000
 *
 * Adds the look of a dashboard to `launchpad_dashboards`: an accent colour,
 * a background colour, a background image from LaunchPad's resource uploads
 * and the administrator's custom CSS (platform-dashboard-theme-and-css D1).
 * Every column is nullable: an empty column means "follow the theme".
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
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add the four look columns to the dashboards table.
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
 */
class Version002014Date20261114120000 extends SimpleMigrationStep {

	/**
	 * The columns this step adds, with their type and options.
	 */
	public const COLUMNS = [
		'theme_accent' => [Types::STRING, ['notnull' => false, 'length' => 7, 'comment' => 'Accent colour #rrggbb, NULL follows the theme.']],
		'theme_background_color' => [Types::STRING, ['notnull' => false, 'length' => 7, 'comment' => 'Background colour #rrggbb, NULL follows the theme.']],
		'theme_background_resource' => [Types::STRING, ['notnull' => false, 'length' => 255, 'comment' => 'Background image, a resource file name.']],
		'custom_css' => [Types::TEXT, ['notnull' => false, 'comment' => 'Administrator CSS for this dashboard, stored cleaned and scoped.']],
	];

	/**
	 * Add each column that is not there yet.
	 *
	 * @param IOutput $output        Migration output.
	 * @param Closure $schemaClosure Schema closure (provides ISchemaWrapper).
	 * @param array   $options       Migration options (unused).
	 *
	 * @return ISchemaWrapper|null The modified schema, or null when unchanged.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		// @var ISchemaWrapper $schema.
		$schema = $schemaClosure();
		if ($schema->hasTable('launchpad_dashboards') === false) {
			return null;
		}

		$table   = $schema->getTable('launchpad_dashboards');
		$changed = false;
		foreach (self::COLUMNS as $name => [$type, $columnOptions]) {
			if ($table->hasColumn($name) === true) {
				continue;
			}

			$table->addColumn($name, $type, $columnOptions);
			$changed = true;
		}

		if ($changed === false) {
			return null;
		}

		return $schema;
	}//end changeSchema()
}//end class
