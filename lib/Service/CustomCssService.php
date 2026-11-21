<?php

/**
 * CustomCssService
 *
 * Stores administrator CSS for all of LaunchPad and for one dashboard, always
 * cleaned and scoped first (REQ-THM-003).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardMapper;

/**
 * The global CSS is the `custom_css` admin setting; a dashboard's CSS is its
 * `custom_css` column. Only the cleaned form is ever stored. Callers check
 * that the user is an administrator.
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
 */
class CustomCssService {

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settings   The admin settings.
	 * @param DashboardMapper    $dashboards The dashboards.
	 * @param CustomCssSanitiser $sanitiser  Cleans and scopes the CSS.
	 */
	public function __construct(
		private readonly AdminSettingMapper $settings,
		private readonly DashboardMapper $dashboards,
		private readonly CustomCssSanitiser $sanitiser,
	) {
	}//end __construct()

	/**
	 * The stored CSS for all of LaunchPad, or '' when there is none.
	 *
	 * @return string The cleaned CSS.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	public function getGlobalCss(): string {
		$value = $this->settings->getValue(key: AdminSetting::KEY_CUSTOM_CSS, default: '');
		if (is_string($value) === false) {
			return '';
		}

		return $value;
	}//end getGlobalCss()

	/**
	 * Clean, scope and store the CSS for all of LaunchPad.
	 *
	 * @param string $css The CSS as the administrator entered it; '' clears it.
	 *
	 * @return array{customCss: string, removed: list<string>} What was stored and what was dropped.
	 *
	 * @throws InvalidArgumentException When the CSS is larger than 20 KB.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	public function setGlobalCss(string $css): array {
		$result = $this->sanitiser->sanitise(css: $css, scope: CustomCssSanitiser::GLOBAL_SCOPE);
		$this->settings->setSetting(key: AdminSetting::KEY_CUSTOM_CSS, value: $result['css']);

		return ['customCss' => $result['css'], 'removed' => $result['removed']];
	}//end setGlobalCss()

	/**
	 * Clean, scope and store the CSS for one dashboard.
	 *
	 * @param string $uuid The dashboard's UUID.
	 * @param string $css  The CSS as the administrator entered it; '' clears it.
	 *
	 * @return array{customCss: string, removed: list<string>} What was stored and what was dropped.
	 *
	 * @throws InvalidArgumentException When the CSS is larger than 20 KB.
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When there is no such dashboard.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	public function setDashboardCss(string $uuid, string $css): array {
		$dashboard = $this->dashboards->findByUuid(uuid: $uuid);
		$result    = $this->sanitiser->sanitise(
			css: $css,
			scope: CustomCssSanitiser::dashboardScope(uuid: (string)$dashboard->getUuid())
		);

		$stored = null;
		if ($result['css'] !== '') {
			$stored = $result['css'];
		}

		$dashboard->setCustomCss($stored);
		$dashboard->setUpdatedAt((new DateTime())->format(format: 'Y-m-d H:i:s'));
		$this->dashboards->update(entity: $dashboard);

		return ['customCss' => $result['css'], 'removed' => $result['removed']];
	}//end setDashboardCss()
}//end class
