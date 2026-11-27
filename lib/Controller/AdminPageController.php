<?php

/**
 * AdminPageController
 *
 * The former address of Launchpad's administration,
 * `/apps/launchpad/admin/{tab}`. The admin page lives in Nextcloud's
 * settings framework now (`/settings/admin/launchpad`, REQ-ADM-032); this
 * route only answers with a redirect (303) to that page on the named tab,
 * so bookmarks and documentation links keep working (REQ-ADM-033). A former
 * tab that is now a section opens its host tab at that section; anything
 * unknown opens General. The route stays an administrator's route, as it
 * was; the settings framework checks the page itself.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-old-admin-route-redirects-req-adm-033
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Settings\AdminTabs;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * Redirects the former administration address to the admin settings page.
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-old-admin-route-redirects-req-adm-033
 */
class AdminPageController extends Controller {
	/**
	 * Query parameters a link into the Embeds section carries (the widget or
	 * dashboard an embed token is for); kept on the redirect.
	 */
	private const KEPT_QUERY = ['embedType', 'embedId', 'embedDashboard'];

	/**
	 * Constructor.
	 *
	 * @param IRequest      $request      The request.
	 * @param IURLGenerator $urlGenerator Builds the settings page's address.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-old-admin-route-redirects-req-adm-033
	 */
	public function __construct(
		IRequest $request,
		private readonly IURLGenerator $urlGenerator,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Answer `/apps/launchpad/admin/{tab}` with a 303 to
	 * `/settings/admin/launchpad#{tab}`.
	 *
	 * @param string $tab The tab the former address names.
	 *
	 * @return RedirectResponse The redirect.
	 *
	 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-old-admin-route-redirects-req-adm-033
	 */
	#[NoCSRFRequired]
	#[AuthorizedAdminSetting(settings: LaunchPadAdmin::class)]
	public function show(string $tab=AdminTabs::DEFAULT_TAB): RedirectResponse {
		$query = [];
		foreach (self::KEPT_QUERY as $name) {
			$value = $this->request->getParam(key: $name);
			if (is_string(value: $value) === true && $value !== '') {
				$query[$name] = $value;
			}
		}

		$url = $this->urlGenerator->linkToRoute(
			routeName: 'settings.AdminSettings.index',
			arguments: ['section' => Application::APP_ID]
		);
		if ($query !== []) {
			$url .= '?'.http_build_query(data: $query);
		}

		$response = new RedirectResponse(redirectURL: $url.'#'.AdminTabs::fragment(tab: $tab));
		$response->setStatus(status: Http::STATUS_SEE_OTHER);
		return $response;
	}//end show()
}//end class
