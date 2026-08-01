<?php

/**
 * IframeController
 *
 * HTTP entry point for the `iframe` dashboard widget's save-time allow-list
 * validation. Exposes:
 *
 * - `POST /api/iframe/validate-url` — validates a candidate `{url, title,
 *   height, aspect, sandbox}` config before the author saves the placement
 *   (REQ-IFRAME-002 "rejected at save time" — host allow-list, fail-closed).
 *
 * There is no per-placement data-fetch endpoint: the iframe is embedded
 * directly by the browser (its `src` is the target URL) — LaunchPad never
 * proxies the target's content, it only gates which hosts may be embedded
 * at all (allow-list) and which hosts the app's own CSP permits
 * ({@see \OCA\LaunchPad\Listener\CspListener}).
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
use OCA\LaunchPad\Service\IframeService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Controller for the `iframe` widget's allow-list validation.
 *
 * @spec openspec/specs/iframe-embed-widget/spec.md
 */
class IframeController extends Controller
{
    /**
     * Constructor.
     *
     * @param IRequest      $request       HTTP request.
     * @param IframeService $iframeService Allow-list validation + sandbox sanitisation.
     * @param IUserSession  $userSession   Session accessor.
     */
    public function __construct(
        IRequest $request,
        private readonly IframeService $iframeService,
        private readonly IUserSession $userSession,
    ) {
        parent::__construct(
            appName: Application::APP_ID,
            request: $request
        );
    }//end __construct()

    /**
     * `POST /api/iframe/validate-url`
     *
     * Validates a candidate iframe config before the author saves the
     * placement (REQ-IFRAME-002 "rejected at save time" — host allow-list,
     * fail-closed). Requires only an authenticated caller — the allow-list
     * itself is admin-controlled, not per-user.
     *
     * @return JSONResponse `{valid: bool, errors: string[]}`.
     *
     * @spec openspec/specs/iframe-embed-widget/spec.md
     */
    #[NoAdminRequired]
    public function validateUrl(): JSONResponse
    {
        if ($this->resolveUserId() === null) {
            return new JSONResponse(
                data: ['status' => 'error', 'error' => 'unauthorized'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        $config = $this->request->getParam(key: 'config');
        if (is_array(value: $config) === false) {
            $config = [];
        }

        $errors = $this->iframeService->validateConfig(config: $config);

        return new JSONResponse(
            data: ['valid' => ($errors === []), 'errors' => $errors],
            statusCode: Http::STATUS_OK
        );
    }//end validateUrl()

    /**
     * `POST /api/iframe/framable`
     *
     * Server-side check of whether a URL may actually be framed
     * (REQ-IFRAME-003 "graceful degradation"). The browser cannot tell an
     * `X-Frame-Options: DENY` / `frame-ancestors 'none'` refusal apart from a
     * normal cross-origin embed, so the widget calls this on mount and shows
     * the fallback card up front when the target refuses framing, instead of
     * a permanently blank frame. Allow-list fail-closed; never leaks the
     * target's response body.
     *
     * @return JSONResponse `{framable: bool, reason: string}`.
     *
     * @spec openspec/specs/iframe-embed-widget/spec.md
     */
    #[NoAdminRequired]
    public function checkFramable(): JSONResponse
    {
        if ($this->resolveUserId() === null) {
            return new JSONResponse(
                data: ['status' => 'error', 'error' => 'unauthorized'],
                statusCode: Http::STATUS_UNAUTHORIZED
            );
        }

        $url = trim(string: (string) $this->request->getParam(key: 'url', default: ''));
        if ($url === '') {
            return new JSONResponse(
                data: ['framable' => false, 'reason' => 'url_required'],
                statusCode: Http::STATUS_OK
            );
        }

        return new JSONResponse(
            data: $this->iframeService->checkFramable(url: $url),
            statusCode: Http::STATUS_OK
        );
    }//end checkFramable()

    /**
     * Resolve the active user's UID, or `null` for anonymous.
     *
     * @return string|null
     */
    private function resolveUserId(): ?string
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return null;
        }

        return $user->getUID();
    }//end resolveUserId()
}//end class
