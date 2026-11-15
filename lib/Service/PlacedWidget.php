<?php

/**
 * PlacedWidget
 *
 * A widget placement together with the dashboard it sits on, as the
 * placement-level permission checks read them.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
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

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * A placement and its dashboard, both known to exist.
 *
 * @spec openspec/specs/permissions/spec.md
 */
final class PlacedWidget {
	/**
	 * Constructor
	 *
	 * @param WidgetPlacement $placement The placement.
	 * @param Dashboard       $dashboard The dashboard it sits on.
	 */
	public function __construct(
		public readonly WidgetPlacement $placement,
		public readonly Dashboard $dashboard,
	) {
	}//end __construct()
}//end class
