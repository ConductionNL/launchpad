<?php

/**
 * AdminPageController
 *
 * Renders Launchpad's administration as a page of Launchpad itself at
 * `/apps/launchpad/admin/{tab}`, in the Launchpad shell without Nextcloud's
 * settings menu (REQ-ADM-030). The reader is checked on the server before
 * anything renders: a Nextcloud administrator or a delegated Launchpad
 * administrator gets the page, anybody else a 403 that carries none of it.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Settings\AdminFormState;
use OCA\LaunchPad\Settings\AdminPageAccess;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

/**
 * The administration page.
 *
 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
 */
class AdminPageController extends Controller {
	/**
	 * The tab a page without a (known) tab opens on.
	 */
	public const DEFAULT_TAB = 'general';

	/**
	 * The tabs of the administration, as `AdminSettings.vue` declares them:
	 * the twelve the Beheer boards draw (REQ-ADM-032).
	 */
	public const TABS = [
		'general',
		'templates',
		'operations',
		'roles-permissions',
		'versioning-audit',
		'sharing',
		'kiosk',
		'org-navigation',
		'demo-data',
		'group-dashboards',
		'detail-fields',
		'profile-fields',
	];

	/**
	 * Former tabs that are now a section of a drawn tab, and the tab that
	 * holds each (REQ-ADM-032). An old address opens the host tab on that
	 * section; `src/utils/adminPage.js` knows the same map.
	 */
	public const FOLDED_TABS = [
		'embeds'           => 'general',
		'search-shortcuts' => 'general',
		'communities'      => 'group-dashboards',
		'app-catalogue'    => 'operations',
	];

	/**
	 * Query parameters an old address carries on to its host tab: the embed
	 * form's pre-selection (`embedType`, `embedId`, `embedDashboard`).
	 */
	private const KEPT_QUERY = ['embedType', 'embedId', 'embedDashboard'];

	/**
	 * Constructor.
	 *
	 * @param IRequest        $request      The request.
	 * @param AdminPageAccess $access       Whether the reader is a (delegated) administrator.
	 * @param AdminFormState  $formState    The initial state of the administration.
	 * @param IURLGenerator   $urlGenerator Builds the address of a tab.
	 *
	 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
	 */
	public function __construct(
		IRequest $request,
		private readonly AdminPageAccess $access,
		private readonly AdminFormState $formState,
		private readonly IURLGenerator $urlGenerator,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The administration page on one tab.
	 *
	 * A reader who is no (delegated) administrator gets Nextcloud's 403 page
	 * and no initial state. An unknown tab is sent to the General tab.
	 *
	 * @param string $tab The tab the address names.
	 *
	 * @return TemplateResponse|RedirectResponse The page, the 403, or the redirect to General.
	 *
	 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
	 * @spec openspec/specs/admin-settings/spec.md#requirement-the-administration-shows-the-twelve-drawn-tabs-req-adm-032
	 */
	#[NoCSRFRequired]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function show(string $tab = self::DEFAULT_TAB): TemplateResponse|RedirectResponse {
		if ($this->access->allows() === false) {
			$forbidden = new TemplateResponse(
				appName: 'core',
				templateName: '403',
				params: [],
				renderAs: TemplateResponse::RENDER_AS_GUEST
			);
			$forbidden->setStatus(status: Http::STATUS_FORBIDDEN);
			return $forbidden;
		}

		if (array_key_exists(key: $tab, array: self::FOLDED_TABS) === true) {
			return $this->redirectToSection(section: $tab);
		}

		if (in_array(needle: $tab, haystack: self::TABS, strict: true) === false) {
			return new RedirectResponse(
				redirectURL: $this->urlGenerator->linkToRoute(
					routeName: Application::APP_ID . '.adminPage.show',
					arguments: ['tab' => self::DEFAULT_TAB]
				)
			);
		}

		$this->formState->provide();
		$this->addAssets();

		return new TemplateResponse(appName: Application::APP_ID, templateName: 'index');
	}//end show()

	/**
	 * Send an old tab address to the tab that holds it now, naming the
	 * section, with the embed form's pre-selection kept.
	 *
	 * @param string $section A key of FOLDED_TABS.
	 *
	 * @return RedirectResponse The redirect to the host tab.
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-the-administration-shows-the-twelve-drawn-tabs-req-adm-032
	 */
	private function redirectToSection(string $section): RedirectResponse {
		$arguments = [
			'tab'     => self::FOLDED_TABS[$section],
			'section' => $section,
		];
		foreach (self::KEPT_QUERY as $name) {
			$value = $this->request->getParam(key: $name);
			if (is_string(value: $value) === true && $value !== '') {
				$arguments[$name] = $value;
			}
		}

		return new RedirectResponse(
			redirectURL: $this->urlGenerator->linkToRoute(
				routeName: Application::APP_ID . '.adminPage.show',
				arguments: $arguments
			)
		);
	}//end redirectToSection()

	/**
	 * Load the Launchpad shell's script and style.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) Nextcloud registers page assets only through OCP\Util.
	 *
	 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
	 */
	protected function addAssets(): void {
		Util::addScript(application: Application::APP_ID, file: 'launchpad-main');
		Util::addStyle(application: Application::APP_ID, file: 'launchpad');
	}//end addAssets()
}//end class
