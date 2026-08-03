<?php

/**
 * MetricsController
 *
 * Declarative Prometheus metrics endpoint backed by the OpenRegister AppHost
 * observability engine (ADR-040). The Prometheus 0.0.4 exposition format, the
 * implicit `launchpad_info` / `launchpad_up` metrics and the declarative
 * `tableCount` metrics read from the `observability.metrics` block of
 * `src/manifest.json` are all owned by that engine. This class declares
 * `index()` with `#[NoCSRFRequired]` (and deliberately WITHOUT
 * `#[NoAdminRequired]`, so Nextcloud keeps the endpoint admin-only) and defers
 * to it. The collaborators are injected by the factory in
 * {@see \OCA\LaunchPad\AppInfo\Application::registerObservability()}.
 *
 * WHY THIS NO LONGER EXTENDS OpenRegister's GenericMetricsController.
 *
 * It used to, and that single `extends` made EVERY route in this app return 500
 * on any instance without OpenRegister installed — not just `/api/metrics`.
 * Nextcloud's router calls `new ReflectionClass()` on every controller while
 * scanning for attribute routes, which loads the class, which loads its parent.
 * A missing parent is a fatal, and it happens during route matching, before any
 * request reaches any controller.
 *
 * That defeated a deliberate mitigation: `registerObservability()` references
 * the OpenRegister classes only as STRINGS inside lazy factory closures,
 * specifically so no `OCA\OpenRegister\…` symbol is touched until a request
 * resolves the controller. A lazy DI registration cannot make a class-level
 * `extends` lazy — inheritance is resolved by the autoloader, not the container.
 * So the app documented graceful degradation and instead died whole.
 *
 * The collaborators are therefore held as untyped `object`s rather than as
 * `ManifestLoader` / `MetricsEngine`: a constructor parameter TYPE is also a
 * compile-time reference to a class that may not exist. `null` means
 * OpenRegister is unavailable, and the endpoint reports that instead of taking
 * the rest of the app down with it.
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
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IRequest;
use Throwable;

/**
 * Admin-only declarative Prometheus metrics endpoint backed by the AppHost engine.
 *
 * No `#[NoAdminRequired]` — the absence of that attribute means Nextcloud
 * requires an admin session (the ADR-006 metrics posture).
 *
 * @spec openspec/changes/adopt-apphost/specs/prometheus-metrics/spec.md — Requirement: Metrics Endpoint (REQ-PROM-001)
 */
class MetricsController extends Controller
{
    /**
     * Prometheus text exposition content type.
     *
     * Inlined rather than read from `PrometheusRenderer::CONTENT_TYPE`, because
     * referencing that constant is itself a compile-time dependency on a class
     * that may not be installed — the same trap as the old `extends`.
     *
     * @var string
     */
    public const CONTENT_TYPE = 'text/plain; version=0.0.4; charset=utf-8';

    /**
     * Constructor.
     *
     * @param string      $appName        This leaf's app id (`launchpad`), which the
     *                                    engine uses to locate the manifest and to
     *                                    prefix the emitted metrics.
     * @param IRequest    $request        The HTTP request.
     * @param object|null $manifestLoader OpenRegister's ManifestLoader, or null when
     *                                    OpenRegister is unavailable. Untyped on
     *                                    purpose — see the class docblock.
     * @param object|null $engine         OpenRegister's MetricsEngine, or null.
     */
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly ?object $manifestLoader=null,
        private readonly ?object $engine=null,
    ) {
        parent::__construct(appName: $appName, request: $request);
    }//end __construct()

    /**
     * GET /api/metrics — declarative Prometheus metrics (admin-only, ADR-006).
     *
     * @return TextPlainResponse Prometheus text exposition 0.0.4, or a plain 503
     *                           body when the engine is unavailable.
     *
     * @spec openspec/changes/adopt-apphost/specs/prometheus-metrics/spec.md — Requirement: Metrics Endpoint (REQ-PROM-001)
     */
    #[NoCSRFRequired]
    public function index(): TextPlainResponse
    {
        if ($this->manifestLoader === null || $this->engine === null) {
            return new TextPlainResponse(
                '# OpenRegister AppHost observability engine unavailable'."\n",
                Http::STATUS_SERVICE_UNAVAILABLE
            );
        }

        try {
            $manifest = $this->manifestLoader->load(appId: $this->appName);
            $body     = $this->engine->render(manifest: $manifest);
        } catch (Throwable $e) {
            return new TextPlainResponse(
                '# metrics unavailable: '.$e->getMessage()."\n",
                Http::STATUS_SERVICE_UNAVAILABLE
            );
        }

        $response = new TextPlainResponse($body);
        $response->addHeader('Content-Type', self::CONTENT_TYPE);

        return $response;
    }//end index()
}//end class
