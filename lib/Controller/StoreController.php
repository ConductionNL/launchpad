<?php

/**
 * StoreController
 *
 * The store endpoints `CnStorePage` calls. LaunchPad declares a
 * `type: "store"` page in `src/manifest.json`, which renders that shared
 * component, which fetches `/api/store/items` on mount and posts to
 * `/api/store/items/{slug}/install` on an install click.
 *
 * 🔴 THIS CLASS IS ALSO WHAT KEEPS THE ENGINE'S ALIAS OFF LAUNCHPAD.
 * `OCA\OpenRegister\AppHost\Bootstrap::aliasStoreController()` binds the
 * engine's `GenericStoreController` at `Controller\StoreController` UNLESS the
 * leaf defines that class itself. LaunchPad must define it: the engine installs
 * by writing OpenRegister objects or applying a configuration bundle, and a
 * LaunchPad dashboard is neither — it lives in LaunchPad's own tables behind
 * `DashboardMapper`. Deleting this class would not fall back to a working
 * store, it would fall back to a store that can never install a dashboard.
 *
 * Implements the `dashboard-store` capability (REQ-STORE-001, REQ-STORE-004).
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\StoreService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Browse a dashboard registry and install one of its templates.
 *
 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md
 */
class StoreController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest     $request      The HTTP request.
	 * @param StoreService $storeService Discovery delegation and install.
	 * @param IUserSession $userSession  The current session.
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private readonly StoreService $storeService,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(
			appName: Application::APP_ID,
			request: $request
		);
	}//end __construct()

	/**
	 * Search the configured registry.
	 *
	 * Signed-in rather than admin: browsing is how a user finds out what an
	 * administrator could install for them, and the response carries only
	 * normalised cards. The engine drops every remote property outside the
	 * descriptor's card map, so the registry URL and token never ride back.
	 *
	 * The guard is in the body rather than only in the attribute so an
	 * anonymous caller gets an explicit 401 instead of a login redirect into a
	 * fetch that expected JSON.
	 *
	 * @return JSONResponse 200 with `{outcome, cards}`, or 401 for anonymous.
	 *
	 * @no-admin-idor-exempt Addresses no object of this instance. The query and
	 *   kind filter are forwarded to an EXTERNAL registry and the response is
	 *   normalised cards, so there is no local identifier to guess.
	 *
	 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-004-installing-must-require-an-administrator
	 */
	#[NoAdminRequired]
	public function search(): JSONResponse {
		if ($this->userSession->getUser() === null) {
			return new JSONResponse(
				data: ['outcome' => 'unauthenticated', 'cards' => []],
				statusCode: Http::STATUS_UNAUTHORIZED
			);
		}

		$query = $this->request->getParam('q');
		$kind = $this->request->getParam('kind');

		$result = $this->storeService->search(
			query: (is_string($query) === true) ? $query : null,
			kind: (is_string($kind) === true) ? $kind : null
		);

		return new JSONResponse(
			data: [
				'outcome' => $result['outcome'],
				'cards' => $result['cards'],
				'kinds' => [],
				'builtIn' => [],
			],
			statusCode: Http::STATUS_OK
		);
	}//end search()

	/**
	 * Install one registry template into this instance.
	 *
	 * Administrative. An install writes dashboards from a third-party server
	 * into the instance, so it is gated exactly like the rest of LaunchPad's
	 * admin surface (ADR-005).
	 *
	 * @param string $slug The remote item slug.
	 *
	 * @return JSONResponse 200 with a per-component report, or 400 when the install was refused.
	 *
	 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-004-installing-must-require-an-administrator
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function install(string $slug): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(
				data: ['success' => false, 'message' => 'Sign in to install a template.', 'components' => []],
				statusCode: Http::STATUS_UNAUTHORIZED
			);
		}

		$result = $this->storeService->install(slug: $slug, userId: $user->getUID());

		// A refusal that installed nothing is a 400 so the page shows the
		// message rather than an empty success. A PARTIAL install is a 200: the
		// dashboards that arrived are real, and the report names the rest.
		$status = Http::STATUS_OK;
		if ($result['success'] === false) {
			$status = Http::STATUS_BAD_REQUEST;
		}

		return new JSONResponse(data: $result, statusCode: $status);
	}//end install()

	/**
	 * Read the registry connection.
	 *
	 * The token is reported as a boolean and never as a value.
	 *
	 * @return JSONResponse 200 with `{registryUrl, registryRegister, tokenConfigured}`.
	 *
	 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-007-the-registry-token-must-not-be-readable-back
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function getConfig(): JSONResponse {
		return new JSONResponse(
			data: ($this->storeService->getRegistryConfig() + ['available' => $this->storeService->isAvailable()]),
			statusCode: Http::STATUS_OK
		);
	}//end getConfig()

	/**
	 * Write the registry connection.
	 *
	 * An omitted key leaves the stored value alone, so a form can change the
	 * URL without resubmitting the token. An empty string clears the key.
	 *
	 * @return JSONResponse 200 with the redacted connection.
	 *
	 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-007-the-registry-token-must-not-be-readable-back
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateConfig(): JSONResponse {
		$this->storeService->updateRegistryConfig(
			registryUrl: $this->stringParam(name: 'registryUrl'),
			registryToken: $this->stringParam(name: 'registryToken'),
			registryRegister: $this->stringParam(name: 'registryRegister')
		);

		return new JSONResponse(
			data: $this->storeService->getRegistryConfig(),
			statusCode: Http::STATUS_OK
		);
	}//end updateConfig()

	/**
	 * Read one request parameter as a string, or null when it was not sent.
	 *
	 * The null is load-bearing: it is what distinguishes "leave this key alone"
	 * from "clear this key", and the empty string means the second.
	 *
	 * @param string $name The parameter name.
	 *
	 * @return string|null The value, or null when absent or not a string.
	 */
	private function stringParam(string $name): ?string {
		$value = $this->request->getParam($name);

		return (is_string($value) === true) ? $value : null;
	}//end stringParam()
}//end class
