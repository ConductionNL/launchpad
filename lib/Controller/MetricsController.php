<?php

/**
 * MetricsController
 *
 * Thin leaf subclass of the OpenRegister AppHost GenericMetricsController
 * (ADR-040). The admin-only posture, the Prometheus 0.0.4 exposition format,
 * the implicit `launchpad_info` / `launchpad_up` metrics, and the declarative
 * `tableCount` metrics read from the `observability.metrics` block of
 * `src/manifest.json` are all owned by the engine. This class exists only so the
 * unchanged `metrics#index` route resolves to a controller in the
 * `OCA\LaunchPad\Controller` namespace; the engine collaborators are injected by
 * the factory in {@see \OCA\LaunchPad\AppInfo\Application}.
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

/**
 * Admin-only declarative Prometheus metrics endpoint backed by the AppHost engine.
 *
 * @spec openspec/changes/adopt-apphost/specs/prometheus-metrics/spec.md — Requirement: Metrics Endpoint (REQ-PROM-001)
 */
class MetricsController extends GenericMetricsController
{
}//end class
