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

use OCA\LaunchPad\Kernel\Contracts\CalendarWidgetServiceInterface;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldAstChecker;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleDetector;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldEvaluator;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldTypeChecker;
use OCA\LaunchPad\Service\CalculatedFieldService;
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
	 * Save-time validation of calculated fields (widget-calculated-fields, task 24).
	 *
	 * @var CalculatedFieldService
	 */
	public readonly CalculatedFieldService $calculatedFields;

	/**
	 * Constructor.
	 *
	 * @param WidgetService          $widgetService The widget service.
	 * @param NewsWidgetService      $newsWidgetService The news widget service.
	 * @param CalendarWidgetServiceInterface  $calendarService The calendar widget service (REQ-CAL-003).
	 * @param WidgetPlacementService $placementService Placement-payload validators (REQ-CONT-006).
	 * @param CalculatedFieldService|null $calculatedFields Calculated-field validation; the stateless
	 *                                                      default when not injected.
	 */
	public function __construct(
		public readonly WidgetService $widgetService,
		public readonly NewsWidgetService $newsWidgetService,
		public readonly CalendarWidgetServiceInterface $calendarService,
		public readonly WidgetPlacementService $placementService,
		?CalculatedFieldService $calculatedFields=null,
	) {
		$this->calculatedFields = $calculatedFields ?? new CalculatedFieldService(
			astChecker: new CalculatedFieldAstChecker(),
			typeChecker: new CalculatedFieldTypeChecker(),
			cycleDetector: new CalculatedFieldCycleDetector(),
			evaluator: new CalculatedFieldEvaluator()
		);
	}//end __construct()
}//end class
