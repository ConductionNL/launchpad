<?php

/**
 * WidgetApiSources
 *
 * The widget, news, calendar and placement services of the widget API.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
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

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\WidgetPlacementService;
use OCA\LaunchPad\Service\WidgetService;

/**
 * The widget, news, calendar and placement services of the widget API.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class WidgetApiSources {
	/**
	 * Constructor.
	 *
	 * @param WidgetService          $widgetService The widget service.
	 * @param NewsWidgetService      $newsWidgetService The news widget service.
	 * @param CalendarWidgetService  $calendarService The calendar widget service (REQ-CAL-003).
	 * @param WidgetPlacementService $placementService Placement-payload validators (REQ-CONT-006).
	 */
	public function __construct(
		public readonly WidgetService $widgetService,
		public readonly NewsWidgetService $newsWidgetService,
		public readonly CalendarWidgetService $calendarService,
		public readonly WidgetPlacementService $placementService,
	) {
	}//end __construct()
}//end class
