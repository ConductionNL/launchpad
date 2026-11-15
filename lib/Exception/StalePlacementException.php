<?php

/**
 * StalePlacementException
 *
 * A widget save made on an older version of that widget: a colleague saved
 * it first. Carries the widget as it is now, so the editor sees the
 * colleague's version and can make the change again on top of it
 * (building-edit-a-dashboard-together, task 1.2).
 *
 * @category Exception
 * @package  OCA\LaunchPad\Exception
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Exception;

use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * A refused save on an older widget version.
 *
 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
 */
class StalePlacementException extends \RuntimeException {

	/**
	 * Constructor
	 *
	 * @param WidgetPlacement $current The widget as saved now.
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
	 */
	public function __construct(private readonly WidgetPlacement $current) {
		parent::__construct(message: 'A colleague changed this widget first.');
	}//end __construct()

	/**
	 * The widget as saved now.
	 *
	 * @return WidgetPlacement
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
	 */
	public function getCurrent(): WidgetPlacement {
		return $this->current;
	}//end getCurrent()
}//end class
