<?php

/**
 * TemplateController
 *
 * HTTP entry point for the dashboard-templates discovery capability
 * (REQ-TMPL-014). Exposes the read-only gallery endpoint available to every
 * logged-in user.
 *
 * The admin-only preview-image upload endpoint lives on
 * {@see AdminController::uploadTemplatePreviewImage()} so the admin gating
 * stays consistent with the rest of the `/api/admin/...` namespace.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Controller for the template gallery (REQ-TMPL-014).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TemplateController extends Controller
{
    /**
     * Constructor.
     *
     * @param IRequest             $request         The request.
     * @param AdminTemplateService $templateService The template service.
     * @param IUserSession         $userSession     The current user
     *                                              session (required for
     *                                              the owner check).
     * @param ActionAuthService    $actionAuth      ADR-023 action
     *                                              authorization.
     */
    public function __construct(
        IRequest $request,
        private readonly AdminTemplateService $templateService,
        private readonly IUserSession $userSession,
        private readonly ActionAuthService $actionAuth,
    ) {
        parent::__construct(
            appName: Application::APP_ID,
            request: $request
        );
    }//end __construct()

    /**
     * `GET /api/templates/gallery` — list all admin templates with the
     * gallery metadata (REQ-TMPL-014).
     *
     * Query parameters:
     *   - `category` (string, optional): exact-match category filter.
     *   - `sort` (string, optional): `name` (default) or `updatedAt`.
     *
     * Available to every logged-in user. Returns a `{status: 'success',
     * templates: [...]}` envelope with no widget bodies — gallery is a
     * list view, not a render.
     *
     * @param string|null $category Optional category filter.
     * @param string      $sort     Sort key (`name` or `updatedAt`).
     *
     * @return JSONResponse The gallery list envelope.
         *
     * @spec openspec/specs/admin-templates/spec.md
 */
    #[NoAdminRequired]
    public function gallery(
        ?string $category=null,
        string $sort='name'
    ): JSONResponse {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'template.gallery');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        $templates = $this->templateService->getGallery(
            category: $category,
            sortBy: $sort
        );

        return new JSONResponse(
            data: [
                'status'    => 'success',
                'templates' => $templates,
            ],
            statusCode: Http::STATUS_OK
        );
    }//end gallery()
}//end class
