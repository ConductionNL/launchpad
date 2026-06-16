<?php

/**
 * MetricsController
 *
 * Thin leaf subclass of the OpenRegister AppHost GenericMetricsController
 * (ADR-040). The Prometheus 0.0.4 exposition format, the implicit
 * `launchpad_info` / `launchpad_up` metrics and the declarative `tableCount`
 * metrics read from the `observability.metrics` block of `src/manifest.json` are
 * all owned by the engine. This class re-declares `index()` with `#[NoCSRFRequired]`
 * (and deliberately WITHOUT `#[NoAdminRequired]`, so Nextcloud keeps the endpoint
 * admin-only) and defers to the engine. The engine collaborators are injected by
 * the factory in {@see \OCA\LaunchPad\AppInfo\Application::registerObservability()}.
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

use OCA\OpenRegister\AppHost\Controller\GenericMetricsController;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TextPlainResponse;

/**
 * Admin-only declarative Prometheus metrics endpoint backed by the AppHost engine.
 *
 * No `#[NoAdminRequired]` — the absence of that attribute means Nextcloud
 * requires an admin session (the ADR-006 metrics posture).
 *
 * @spec openspec/changes/adopt-apphost/specs/prometheus-metrics/spec.md — Requirement: Metrics Endpoint (REQ-PROM-001)
 */
class MetricsController extends GenericMetricsController
{
    /**
     * GET /api/metrics — declarative Prometheus metrics (admin-only, ADR-006).
     *
     * @return TextPlainResponse Prometheus text exposition 0.0.4.
     *
     * @spec openspec/changes/adopt-apphost/specs/prometheus-metrics/spec.md — Requirement: Metrics Endpoint (REQ-PROM-001)
     */
    #[NoCSRFRequired]
    public function index(): TextPlainResponse
    {
        return parent::index();
    }//end index()
}//end class
