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
use OCA\LaunchPad\Service\StoreInstallFlow;
use OCA\LaunchPad\Service\StoreInstalls;
use OCA\LaunchPad\Service\StorePublishService;
use OCA\LaunchPad\Service\StoreService;
use OCA\LaunchPad\Service\StoreUpdateService;
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
	 * Which item and version an install created which dashboard (REQ-STORE-011).
	 *
	 * @var StoreInstalls|null
	 */
	private readonly ?StoreInstalls $installs;

	/**
	 * Brings an installed item to a newer version in place (REQ-STORE-012).
	 *
	 * @var StoreUpdateService|null
	 */
	private readonly ?StoreUpdateService $updates;

	/**
	 * Constructor.
	 *
	 * @param IRequest     $request      The HTTP request.
	 * @param StoreService $storeService Discovery delegation and install.
	 * @param IUserSession $userSession  The current session.
	 * @param StoreInstallFlow|null $flow The install record (REQ-STORE-011) and the in-place update (REQ-STORE-012).
	 * @param StorePublishService|null $publications Publishes a dashboard to the register (REQ-STORE-013).
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private readonly StoreService $storeService,
		private readonly IUserSession $userSession,
		?StoreInstallFlow $flow = null,
		private readonly ?StorePublishService $publications = null,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
		$this->installs = $flow?->installs;
		$this->updates = $flow?->updates;
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
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-010-the-marketplace-shows-dashboard-templates-as-cards
	 */
	#[NoAdminRequired]
	public function search(): JSONResponse {
		if ($this->userSession->getUser() === null) {
			return new JSONResponse(
				data: ['outcome' => 'unauthenticated', 'cards' => []],
				statusCode: Http::STATUS_UNAUTHORIZED
			);
		}

		$result = $this->storeService->search(
			query: $this->stringParam(name: 'q'),
			kind: $this->stringParam(name: 'kind')
		);

		// REQ-STORE-011: Installed (with the dashboard to open), Update or Available.
		$cards = ($this->installs?->annotate(cards: $result['cards']) ?? $result['cards']);
		// REQ-STORE-013: what this instance published reads "Own publication".
		$cards = ($this->publications?->annotate(cards: $cards) ?? $cards);

		return new JSONResponse(
			data: [
				'outcome' => $result['outcome'],
				'cards' => $cards,
				'kinds' => [],
				'builtIn' => [],
				// REQ-STORE-010: the register banner (name and address, never the token).
				'registry' => $this->storeService->registryBanner(),
				'checkedAt' => ($result['checkedAt'] ?? null),
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
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
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

		// REQ-STORE-012: the store page's Update action posts here. An item
		// installed before is updated in place, never installed a second time.
		if ($this->updates !== null && isset($this->installs?->all()[$slug]) === true) {
			return $this->update(slug: $slug);
		}

		$result = $this->storeService->install(slug: $slug, userId: $user->getUID());
		$created = ($result['created'] ?? []);
		if ($result['success'] === true && $created !== []) {
			$this->installs?->record(slug: $slug, version: (string)($result['version'] ?? ''), dashboard: (array)$created[0]);
		}

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
	 * Bring an installed register item to the register's newer version, in place.
	 *
	 * Administrative, like install (ADR-005): anyone else is refused with 403
	 * by the attribute before this body runs. The dashboard keeps its id,
	 * shares and the widgets people added themselves.
	 *
	 * @param string $slug The remote item slug.
	 *
	 * @return JSONResponse 200 with the update report, 404 when the item is not installed here,
	 *     or 400 when the update was refused.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(string $slug): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null || $this->updates === null) {
			return new JSONResponse(
				data: ['success' => false, 'message' => 'Sign in as an administrator to update a template.'],
				statusCode: Http::STATUS_UNAUTHORIZED
			);
		}

		$result = $this->updates->update(slug: $slug, userId: $user->getUID());

		$status = Http::STATUS_OK;
		if (($result['notInstalled'] ?? false) === true) {
			$status = Http::STATUS_NOT_FOUND;
		} else if ($result['success'] === false) {
			$status = Http::STATUS_BAD_REQUEST;
		}

		return new JSONResponse(data: $result, statusCode: $status);
	}//end update()

	/**
	 * The dashboards an administrator may publish, for the publish dialog.
	 *
	 * Administrative (REQ-STORE-013): anyone else is refused with 403 by the
	 * attribute. Only the administrator's own dashboards and the admin
	 * templates are offered, never another person's.
	 *
	 * @return JSONResponse 200 with `{objects: [{schema, id, title}]}`.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function publishable(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null || $this->publications === null) {
			return new JSONResponse(data: ['objects' => []], statusCode: Http::STATUS_OK);
		}

		return new JSONResponse(
			data: ['objects' => $this->publications->publishable(userId: $user->getUID())],
			statusCode: Http::STATUS_OK
		);
	}//end publishable()

	/**
	 * Publish one dashboard to the connected register.
	 *
	 * Administrative (REQ-STORE-013): anyone else is refused with 403 by the
	 * attribute before this body runs. The body is `{item, objects}` as the
	 * publish dialog sends it. A request this server refuses is a 400, a
	 * version that is not higher a 409; the register's own answer is passed
	 * through unchanged as the outcome (REQ-STORE-003).
	 *
	 * @return JSONResponse `{outcome, slug, message?}`.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function publish(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null || $this->publications === null) {
			return new JSONResponse(
				data: ['outcome' => 'unauthenticated', 'slug' => '', 'message' => 'Sign in as an administrator to publish.'],
				statusCode: Http::STATUS_UNAUTHORIZED
			);
		}

		$item = $this->request->getParam('item');
		$objects = $this->request->getParam('objects');
		$result = $this->publications->publish(
			item: (is_array($item) === true ? $item : []),
			objects: (is_array($objects) === true ? array_values($objects) : []),
			userId: $user->getUID()
		);

		$status = Http::STATUS_OK;
		if ($result['outcome'] === StorePublishService::OUTCOME_INVALID_REQUEST) {
			$status = Http::STATUS_BAD_REQUEST;
		} else if ($result['outcome'] === StorePublishService::OUTCOME_VERSION_NOT_HIGHER) {
			$status = Http::STATUS_CONFLICT;
		}

		return new JSONResponse(data: $result, statusCode: $status);
	}//end publish()

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
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-010-the-marketplace-shows-dashboard-templates-as-cards
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function updateConfig(): JSONResponse {
		$this->storeService->updateRegistryConfig(
			registryUrl: $this->stringParam(name: 'registryUrl'),
			registryToken: $this->stringParam(name: 'registryToken'),
			registryRegister: $this->stringParam(name: 'registryRegister'),
			registryName: $this->stringParam(name: 'registryName')
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
		if (is_string($value) === false) {
			return null;
		}

		return $value;
	}//end stringParam()
}//end class
