<?php

/**
 * AdminWidgetRulesController
 *
 * Admin-only overview of conditional visibility rules across all dashboards.
 *
 * @category  Controller
 * @package   OCA\MyDash\Controller
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

namespace OCA\MyDash\Controller;

use OCA\MyDash\AppInfo\Application;
use OCA\MyDash\Service\ConditionalService;
use OCA\MyDash\Settings\MyDashAdmin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Surfaces the per-widget conditional-visibility overview for the admin
 * Beheer ▸ Versioning & Audit tab (conditional-visibility spec).
 *
 * @spec openspec/specs/conditional-visibility/spec.md
 */
class AdminWidgetRulesController extends Controller
{
    /**
     * Constructor
     *
     * @param IRequest           $request            The request.
     * @param ConditionalService $conditionalService The conditional service.
     */
    public function __construct(
        IRequest $request,
        private readonly ConditionalService $conditionalService,
    ) {
        parent::__construct(
            appName: Application::APP_ID,
            request: $request
        );
    }//end __construct()

    /**
     * List every widget placement that carries at least one conditional rule.
     *
     * Admin-only — the overview discloses every user's dashboard names and
     * widget types, so it is gated with `#[AuthorizedAdminSetting]` like the
     * rest of the Beheer surface (ADR-005).
     *
     * @return JSONResponse The overview rows (placement + dashboard + counts).
     *
     * @spec openspec/specs/conditional-visibility/spec.md
     */
    #[AuthorizedAdminSetting(MyDashAdmin::class)]
    public function index(): JSONResponse
    {
        try {
            return ResponseHelper::success(
                data: $this->conditionalService->listAllRules()
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(exception: $e);
        }
    }//end index()
}//end class
