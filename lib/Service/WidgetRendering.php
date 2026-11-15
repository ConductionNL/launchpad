<?php

/**
 * WidgetRendering
 *
 * The widget formatter and the item loader of the widget service.
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
 *
 * @spec openspec/specs/widgets/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The widget formatter and the item loader of the widget service.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class WidgetRendering {
	/**
	 * Constructor.
	 *
	 * @param WidgetFormatter  $widgetFormatter Widget formatter service.
	 * @param WidgetItemLoader $widgetItemLoader Widget item loader service.
	 */
	public function __construct(
		public readonly WidgetFormatter $widgetFormatter,
		public readonly WidgetItemLoader $widgetItemLoader,
	) {
	}//end __construct()
}//end class
