<?php

/**
 * TenantThemeApiController
 *
 * The admin API of tenant themes (REQ-EMB-005): list with how many tokens
 * use each theme, create, update and delete. Admin-only: the admin-setting
 * attribute plus the request guard. Validation lives in TenantThemeService.
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
use OCA\LaunchPad\Service\Embed\TenantThemeService;
use OCA\LaunchPad\Service\RequestGuard;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use RuntimeException;

/**
 * Admin endpoints for tenant themes.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
 */
class TenantThemeApiController extends Controller {

	/**
	 * The fields a create or update reads from the request.
	 *
	 * @var list<string>
	 */
	private const FIELDS = ['name', 'cssVariables', 'logoUrl', 'customCss'];

	/**
	 * Constructor.
	 *
	 * @param IRequest           $request The request.
	 * @param TenantThemeService $themes  The themes.
	 * @param RequestGuard       $guard   The admin check.
	 */
	public function __construct(
		IRequest $request,
		private readonly TenantThemeService $themes,
		private readonly RequestGuard $guard,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Every theme, with the number of tokens using it.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function index(): JSONResponse {
		return $this->asAdmin(action: fn (): JSONResponse => new JSONResponse(data: ['results' => $this->themes->listThemes()]));
	}//end index()

	/**
	 * Create a theme.
	 *
	 * @return JSONResponse 201 with the theme, 400 with a refusal.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function create(): JSONResponse {
		return $this->asAdmin(
			action: fn (string $admin): JSONResponse => self::outcome(
				data: $this->themes->createTheme(input: $this->input(), userId: $admin),
				success: Http::STATUS_CREATED
			)
		);
	}//end create()

	/**
	 * Replace a theme; tokens using it follow at once.
	 *
	 * @param string $id The theme id.
	 *
	 * @return JSONResponse 200, 400 with a refusal, 404 for an unknown theme.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function update(string $id): JSONResponse {
		return $this->asAdmin(
			action: function () use ($id): JSONResponse {
				$result = $this->themes->updateTheme(id: $id, input: $this->input());
				if ($result === null) {
					return self::notFound();
				}

				return self::outcome(data: $result, success: Http::STATUS_OK);
			}
		);
	}//end update()

	/**
	 * Delete a theme; one that tokens use only with `force`.
	 *
	 * @param string $id    The theme id.
	 * @param bool   $force Delete even when tokens use it.
	 *
	 * @return JSONResponse 200, 409 while tokens use it, 404 for an unknown theme.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
	 */
	#[AuthorizedAdminSetting(LaunchPadAdmin::class)]
	public function destroy(string $id, bool $force=false): JSONResponse {
		return $this->asAdmin(
			action: function () use ($id, $force): JSONResponse {
				$result = $this->themes->deleteTheme(id: $id, force: $force);
				if ($result === null) {
					return self::notFound();
				}

				if (($result['status'] ?? null) === 'error') {
					return new JSONResponse(data: $result, statusCode: Http::STATUS_CONFLICT);
				}

				return new JSONResponse(data: $result);
			}
		);
	}//end destroy()

	/**
	 * Run an action as the signed-in admin; 401/403 otherwise, 503 without OpenRegister.
	 *
	 * @param callable $action fn(string $admin): JSONResponse.
	 *
	 * @return JSONResponse
	 */
	private function asAdmin(callable $action): JSONResponse {
		$admin = $this->guard->adminId();
		if ($admin instanceof JSONResponse) {
			return $admin;
		}

		try {
			return $action($admin);
		} catch (RuntimeException $failure) {
			return new JSONResponse(data: ['error' => 'unavailable', 'message' => $failure->getMessage()], statusCode: Http::STATUS_SERVICE_UNAVAILABLE);
		}
	}//end asAdmin()

	/**
	 * The theme fields that are present in the request.
	 *
	 * @return array<string, mixed>
	 */
	private function input(): array {
		$params = $this->request->getParams();
		$input  = [];
		foreach (self::FIELDS as $field) {
			if (array_key_exists($field, $params) === true) {
				$input[$field] = $params[$field];
			}
		}

		return $input;
	}//end input()

	/**
	 * 400 for a refusal, the success status otherwise.
	 *
	 * @param array<string, mixed> $data    The result.
	 * @param int                  $success The success status.
	 *
	 * @return JSONResponse
	 */
	private static function outcome(array $data, int $success): JSONResponse {
		if (($data['status'] ?? null) === 'error') {
			return new JSONResponse(data: $data, statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(data: $data, statusCode: $success);
	}//end outcome()

	/**
	 * The 404 of an unknown theme.
	 *
	 * @return JSONResponse
	 */
	private static function notFound(): JSONResponse {
		return new JSONResponse(data: ['error' => 'not_found', 'message' => 'There is no such tenant theme.'], statusCode: Http::STATUS_NOT_FOUND);
	}//end notFound()
}//end class
