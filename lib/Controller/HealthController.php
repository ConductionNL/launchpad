<?php

/**
 * HealthController
 *
 * Thin leaf subclass of the OpenRegister AppHost GenericHealthController
 * (ADR-040). All rendering logic — the ADR-006 `{status, app, version, checks}`
 * shape and the declarative checks read from the `observability.health` block of
 * `src/manifest.json` — is owned by the engine. This class re-declares `index()`
 * with the explicit public auth posture (`#[PublicPage]` + `#[NoCSRFRequired]`)
 * so a session-less monitoring probe can reach `/api/health`, then defers to the
 * engine. The engine collaborators are injected by the factory in
 * {@see \OCA\LaunchPad\AppInfo\Application::registerObservability()}.
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

use OCA\OpenRegister\AppHost\Controller\GenericHealthController;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;

/**
 * Public, declarative health endpoint backed by the AppHost engine.
 *
 * @spec openspec/changes/adopt-apphost/specs/prometheus-metrics/spec.md — Requirement: Health Check Endpoint (REQ-PROM-007)
 */
class HealthController extends GenericHealthController
{
    /**
     * GET /api/health — declarative health check (ADR-006), public.
     *
     * @return JSONResponse `{status, app, version, checks}`.
     *
     * @spec openspec/changes/adopt-apphost/specs/prometheus-metrics/spec.md — Requirement: Health Check Endpoint (REQ-PROM-007)
     */
    #[PublicPage]
    #[NoCSRFRequired]
    public function index(): JSONResponse
    {
        return parent::index();
    }//end index()
}//end class
