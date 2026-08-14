<?php

/**
 * HealthController
 *
 * Declarative health endpoint backed by the OpenRegister AppHost observability
 * engine (ADR-040). All rendering logic — the ADR-006
 * `{status, app, version, checks}` shape and the declarative checks read from the
 * `observability.health` block of `src/manifest.json` — is owned by that engine.
 * This class declares `index()` with the explicit public auth posture
 * (`#[PublicPage]` + `#[NoCSRFRequired]`) so a session-less monitoring probe can
 * reach `/api/health`, then defers to it. The collaborators are injected by the
 * factory in {@see \OCA\LaunchPad\AppInfo\Application::registerObservability()}.
 *
 * WHY THIS NO LONGER EXTENDS OpenRegister's GenericHealthController — see the
 * long explanation in {@see MetricsController}. In short: Nextcloud's router
 * reflects every controller class while scanning attribute routes, so a missing
 * PARENT class is a fatal during route matching and took every route in this app
 * down with it, defeating the lazy string-only DI registration that was written
 * specifically to avoid that. A health endpoint reporting "engine unavailable" is
 * the correct degraded behaviour; a 500 on every route is not.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Throwable;

/**
 * Public, declarative health endpoint backed by the AppHost engine.
 *
 * @spec openspec/changes/adopt-apphost/specs/prometheus-metrics/spec.md — Requirement: Health Check Endpoint (REQ-PROM-007)
 */
class HealthController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param string $appName This leaf's app id (`launchpad`).
	 * @param IRequest $request The HTTP request.
	 * @param object|null $manifestLoader OpenRegister's ManifestLoader, or null when
	 *                                    OpenRegister is unavailable. Untyped on
	 *                                    purpose: a parameter TYPE is also a
	 *                                    compile-time reference to a class that may
	 *                                    not exist.
	 * @param object|null $executor OpenRegister's HealthCheckExecutor, or null.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ?object $manifestLoader = null,
		private readonly ?object $executor = null,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * GET /api/health — declarative health check (ADR-006), public.
	 *
	 * Reports `status: unavailable` with HTTP 503 when the engine is absent,
	 * which is a meaningful answer for a monitoring probe: the app is reachable,
	 * its declarative health engine is not.
	 *
	 * @return JSONResponse `{status, app, version, checks}`.
	 *
	 * @spec openspec/changes/adopt-apphost/specs/prometheus-metrics/spec.md — Requirement: Health Check Endpoint (REQ-PROM-007)
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	// Liveness probe — no credential, so a ceiling and no counter.
	#[AnonRateLimit(limit: 120, period: 60)]
	public function index(): JSONResponse {
		$appId = $this->appName;

		if ($this->manifestLoader === null || $this->executor === null) {
			return new JSONResponse(
				[
					'status' => 'unavailable',
					'app' => $appId,
					'error' => 'OpenRegister AppHost observability engine unavailable',
					'checks' => [],
				],
				Http::STATUS_SERVICE_UNAVAILABLE
			);
		}

		try {
			$manifest = $this->manifestLoader->load(appId: $appId);
			$result = $this->executor->execute(manifest: $manifest);

			$response = new JSONResponse(
				[
					'status' => $result->status,
					'app' => $appId,
					'version' => $this->manifestLoader->appVersion(appId: $appId),
					'checks' => $result->checks,
				],
				$result->httpStatusCode
			);

			if ($manifest->cors === true) {
				$response->addHeader('Access-Control-Allow-Origin', '*');
				$response->addHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
			}

			return $response;
		} catch (Throwable $e) {
			return new JSONResponse(
				[
					'status' => 'unavailable',
					'app' => $appId,
					'error' => $e->getMessage(),
					'checks' => [],
				],
				Http::STATUS_SERVICE_UNAVAILABLE
			);
		}//end try
	}//end index()
}//end class
