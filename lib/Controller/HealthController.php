<?php

/**
 * HealthController
 *
 * Thin leaf subclass of the OpenRegister AppHost GenericHealthController
 * (ADR-040). All behaviour — the public `#[PublicPage]` posture, the ADR-006
 * `{status, app, version, checks}` shape, and the declarative checks read from
 * the `observability.health` block of `src/manifest.json` — is owned by the
 * engine. This class exists only so the unchanged `health#index` route resolves
 * to a controller in the `OCA\LaunchPad\Controller` namespace; the engine
 * collaborators are injected by the factory in {@see \OCA\LaunchPad\AppInfo\Application}.
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

/**
 * Public, declarative health endpoint backed by the AppHost engine.
 *
 * @spec openspec/changes/adopt-apphost/specs/prometheus-metrics/spec.md — Requirement: Health Check Endpoint (REQ-PROM-007)
 */
class HealthController extends GenericHealthController
{
}//end class
