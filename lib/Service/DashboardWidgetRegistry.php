<?php

/**
 * DashboardWidgetRegistry
 *
 * The Nextcloud dashboard widgets enabled for the current user, read
 * without running any widget's `load()`.
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
 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-010-initial-state-widget-allowlist
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\App\IAppManager;
use OCP\Dashboard\IConditionalWidget;
use OCP\Dashboard\IManager;
use OCP\Dashboard\IWidget;
use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionProperty;
use Throwable;

/**
 * Reads the dashboard widget registry the way `IManager::getWidgets()` does,
 * minus the `load()` call on every widget.
 *
 * `load()` is where a widget calls `Util::addScript()`, so calling
 * `getWidgets()` while rendering the workspace page injects every widget's
 * JavaScript into a page that renders no Nextcloud dashboard. The public API
 * has no other way to list the widgets, so this reads the manager's pending
 * registrations and builds each widget the way the manager would. When the
 * manager does not have that shape the answer is null and the caller falls
 * back to `GET /api/widgets`.
 *
 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-010-initial-state-widget-allowlist
 */
class DashboardWidgetRegistry {
	/**
	 * Constructor.
	 *
	 * @param IManager           $dashboardManager The dashboard widget registry.
	 * @param IAppManager        $appManager       Whether a widget's app is enabled for the user.
	 * @param ContainerInterface $container        Builds a registered widget class.
	 */
	public function __construct(
		private readonly IManager $dashboardManager,
		private readonly IAppManager $appManager,
		private readonly ContainerInterface $container,
	) {
	}//end __construct()

	/**
	 * The widgets enabled for the current user, none of them loaded.
	 *
	 * When the registry was already loaded in this request, reading it again
	 * costs nothing, so its widgets are returned as they are.
	 *
	 * @return list<IWidget>|null The widgets, or null when the registry cannot be read without loading it.
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-010-initial-state-widget-allowlist
	 */
	public function widgetsWithoutLoading(): ?array {
		$pending = $this->pendingRegistrations();
		if ($pending === null) {
			return null;
		}

		if ($pending === []) {
			return array_values(array: $this->dashboardManager->getWidgets());
		}

		$widgets = [];
		foreach ($pending as $registration) {
			$widget = $this->build(registration: $registration);
			if ($widget !== null && isset($widgets[$widget->getId()]) === false) {
				$widgets[$widget->getId()] = $widget;
			}
		}

		return array_values(array: $widgets);
	}//end widgetsWithoutLoading()

	/**
	 * The manager's registrations that have not been loaded yet.
	 *
	 * @return list<array{class: string, appId: string}>|null The registrations, or null when the manager has no such list.
	 */
	private function pendingRegistrations(): ?array {
		try {
			$property = new ReflectionProperty($this->dashboardManager, 'lazyWidgets');
		} catch (ReflectionException) {
			return null;
		}

		$value = $property->getValue($this->dashboardManager);
		if (is_array($value) === false) {
			return null;
		}

		$registrations = [];
		foreach ($value as $entry) {
			if (is_array($entry) === false || is_string($entry['class'] ?? null) === false || is_string($entry['appId'] ?? null) === false) {
				return null;
			}

			$registrations[] = ['class' => $entry['class'], 'appId' => $entry['appId']];
		}

		return $registrations;
	}//end pendingRegistrations()

	/**
	 * One registered widget, when its app is enabled for the user, it can be
	 * built and it is not switched off.
	 *
	 * @param array{class: string, appId: string} $registration The registration.
	 *
	 * @return IWidget|null The widget, or null to leave it out.
	 */
	private function build(array $registration): ?IWidget {
		try {
			if ($this->appManager->isEnabledForUser($registration['appId']) === false) {
				return null;
			}

			$widget = $this->container->get($registration['class']);
			if ($widget instanceof IWidget === false) {
				return null;
			}

			if ($widget instanceof IConditionalWidget && $widget->isEnabled() === false) {
				return null;
			}
		} catch (Throwable) {
			return null;
		}

		return $widget;
	}//end build()
}//end class
