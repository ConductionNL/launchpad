<?php

/**
 * Version002003Date20260620000000
 *
 * Drops the launchpad_feed_tokens table. The per-user RSS/Atom outbound-feed
 * feature (dashboard-rss-feeds) has been retired — its controller, services,
 * entity, mapper and routes are removed — so the backing table is no longer
 * used. The inbound news-widget feed cache (launchpad_feed_cache) is a
 * separate, still-live feature and is intentionally left untouched.
 *
 * @category  Migration
 * @package   OCA\LaunchPad\Migration
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Drops the now-unused launchpad_feed_tokens table (outbound RSS feature retired).
 */
class Version002003Date20260620000000 extends SimpleMigrationStep {
	/**
	 * Drop launchpad_feed_tokens if present.
	 *
	 * @param IOutput $output Migration output handler.
	 * @param Closure $schemaClosure Returns an ISchemaWrapper.
	 * @param array $options Migration options.
	 *
	 * @return ISchemaWrapper|null
	 */
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('launchpad_feed_tokens') === true) {
			$schema->dropTable('launchpad_feed_tokens');
		}

		return $schema;
	}//end changeSchema()
}//end class
