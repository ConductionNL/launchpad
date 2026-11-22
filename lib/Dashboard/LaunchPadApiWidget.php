<?php

/**
 * LaunchPadApiWidget
 *
 * What LaunchPad's Nextcloud dashboard widgets share: an icon from the
 * app's img folder, a link to LaunchPad, and no script to load because
 * the dashboard renders their items itself.
 *
 * @category  Dashboard
 * @package   OCA\LaunchPad\Dashboard
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

namespace OCA\LaunchPad\Dashboard;

use OCA\LaunchPad\AppInfo\Application;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IIconWidget;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Base of the LaunchPad widgets on Nextcloud's dashboard.
 *
 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
 * @spec openspec/specs/links-widget/spec.md#requirement-req-lnks-001-widget-registration
 */
abstract class LaunchPadApiWidget implements IAPIWidgetV2, IIconWidget {
	/**
	 * Constructor.
	 *
	 * @param IL10N         $l10n         Translates the title and empty states.
	 * @param IURLGenerator $urlGenerator Builds the icon, app and item URLs.
	 */
	public function __construct(
		protected readonly IL10N $l10n,
		protected readonly IURLGenerator $urlGenerator,
	) {
	}//end __construct()

	/**
	 * The icon's file name in the app's img folder.
	 *
	 * @return string
	 */
	abstract protected function iconFile(): string;

	/**
	 * No icon class: the widget ships an icon URL.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function getIconClass(): string {
		return '';
	}//end getIconClass()

	/**
	 * The widget's icon as an absolute URL.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			url: $this->urlGenerator->imagePath(appName: Application::APP_ID, file: $this->iconFile())
		);
	}//end getIconUrl()

	/**
	 * LaunchPad, where the widget's sources are configured.
	 *
	 * @return string|null
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function getUrl(): ?string {
		return $this->urlGenerator->linkToRouteAbsolute(routeName: Application::APP_ID . '.page.index');
	}//end getUrl()

	/**
	 * Nothing to load: the dashboard renders the items itself.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function load(): void {
	}//end load()
}//end class
