<?php

/**
 * DashboardTranslationApiController
 *
 * Controller for the per-language dashboard content variant endpoints —
 * create, update, delete, promote-primary, plus the read-side resolver
 * exposed via `GET /api/dashboards/{uuid}` with optional `?lang=` query
 * parameter. REQ-DASH-038..044 (dashboard-language-content).
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardTranslationMapper;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DashboardTranslationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Controller for dashboard translation endpoints (REQ-DASH-038..044).
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @spec                                         openspec/specs/dashboard-language-content/spec.md
 */
class DashboardTranslationApiController extends Controller
{
    /**
     * Constructor
     *
     * @param IRequest                    $request            The request.
     * @param DashboardMapper             $dashboardMapper    Dashboard mapper
     *                                                        (used for the
     *                                                        ownership check
     *                                                        before any
     *                                                        translation
     *                                                        mutation).
     * @param DashboardTranslationService $translationService Translation
     *                                                        service.
     * @param ActionAuthService           $actionAuth         ADR-023 action
     *                                                        authorization.
     * @param IUserSession                $userSession        User session
     *                                                        (IUser resolution).
     * @param string|null                 $userId             The user ID.
     */
    public function __construct(
        IRequest $request,
        private readonly DashboardMapper $dashboardMapper,
        private readonly DashboardTranslationService $translationService,
        private readonly ActionAuthService $actionAuth,
        private readonly IUserSession $userSession,
        private readonly ?string $userId,
    ) {
        parent::__construct(
            appName: Application::APP_ID,
            request: $request
        );
    }//end __construct()

    /**
     * GET /api/dashboards/{uuid}/translations — list every translation
     * variant for a dashboard. Returns 403 when the dashboard belongs
     * to another user. REQ-DASH-038.
     *
     * @param string $uuid The dashboard UUID.
     *
     * @return JSONResponse The list payload.
         *
     * @spec openspec/specs/dashboard-language-content/spec.md
 */
    #[NoAdminRequired]
    public function list(string $uuid): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($this->userId === null || $user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'dashboard-translation.list');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        $ownerCheck = $this->assertOwner(uuid: $uuid);
        if ($ownerCheck !== null) {
            return $ownerCheck;
        }

        $variants = $this->translationService->listVariants(
            dashboardUuid: $uuid
        );

        $serialized = ResponseHelper::serializeList(entities: $variants);

        return ResponseHelper::success(
            data: ['translations' => $serialized]
        );
    }//end list()

    /**
     * POST /api/dashboards/{uuid}/translations — create a new variant.
     *
     * Body: `{languageCode, name?, description?, widgetTreeJson?, copyFrom?}`.
     * Returns 201 with the created entity. Maps duplicate-language
     * conflicts to HTTP 409. REQ-DASH-040.
     *
     * @param string      $uuid           The dashboard UUID.
     * @param string|null $languageCode   The language code from the body.
     * @param string|null $name           The optional name.
     * @param string|null $description    The optional description.
     * @param string|null $widgetTreeJson The optional widget tree JSON.
     * @param string|null $copyFrom       Optional source language.
     *
     * @return JSONResponse The created variant.
         *
     * @spec openspec/specs/dashboard-language-content/spec.md
 */
    #[NoAdminRequired]
    public function create(
        string $uuid,
        ?string $languageCode=null,
        ?string $name=null,
        ?string $description=null,
        ?string $widgetTreeJson=null,
        ?string $copyFrom=null
    ): JSONResponse {
        $user = $this->userSession->getUser();
        if ($this->userId === null || $user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'dashboard-translation.create');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        $ownerCheck = $this->assertOwner(uuid: $uuid);
        if ($ownerCheck !== null) {
            return $ownerCheck;
        }

        if ($this->isBlank(value: $languageCode) === true) {
            return self::invalidArgument(
                message: DashboardTranslationService::ERR_INVALID_LANGUAGE
            );
        }

        try {
            $variant = $this->translationService->createVariant(
                dashboardUuid: $uuid,
                languageCode: (string) $languageCode,
                name: $name,
                description: $description,
                widgetTreeJson: $widgetTreeJson,
                copyFromLanguage: $copyFrom
            );

            return new JSONResponse(
                data: ['translation' => $variant->jsonSerialize()],
                statusCode: Http::STATUS_CREATED
            );
        } catch (InvalidArgumentException $e) {
            return self::invalidArgument(message: $e->getMessage());
        } catch (Exception $e) {
            return $this->mapCreateFailure(exception: $e);
        }//end try
    }//end create()

    /**
     * Test whether an optional string parameter carries no value.
     *
     * A missing body key arrives as `null` and an explicitly blank one as
     * `''`; both are rejected identically by the create endpoint.
     *
     * @param string|null $value The parameter value.
     *
     * @return bool True when the parameter carries no value.
     */
    private function isBlank(?string $value): bool
    {
        return ($value === null || $value === '');
    }//end isBlank()

    /**
     * Build the shared HTTP 400 invalid-argument envelope.
     *
     * @param string $message The validation message.
     *
     * @return JSONResponse The 400 response.
     */
    private static function invalidArgument(string $message): JSONResponse
    {
        return new JSONResponse(
            data: [
                'status'  => 'error',
                'error'   => 'invalid_argument',
                'message' => $message,
            ],
            statusCode: Http::STATUS_BAD_REQUEST
        );
    }//end invalidArgument()

    /**
     * Map a create-variant failure onto its HTTP envelope.
     *
     * A duplicate-language collision is the one domain failure with a
     * dedicated status (HTTP 409); everything else falls through to the
     * generic error envelope. REQ-DASH-040.
     *
     * @param Exception $exception The failure thrown by the service.
     *
     * @return JSONResponse The mapped response.
     */
    private function mapCreateFailure(Exception $exception): JSONResponse
    {
        if ($exception->getMessage() === DashboardTranslationService::ERR_LANGUAGE_EXISTS) {
            return new JSONResponse(
                data: [
                    'status'  => 'error',
                    'error'   => 'language_exists',
                    'message' => $exception->getMessage(),
                ],
                statusCode: Http::STATUS_CONFLICT
            );
        }

        return ResponseHelper::error(exception: $exception);
    }//end mapCreateFailure()

    /**
     * PUT /api/dashboards/{uuid}/translations/{lang} — update a variant.
     *
     * Body: `{name?, description?, widgetTreeJson?}`. Returns 200 with
     * the updated entity. REQ-DASH-041.
     *
     * @param string      $uuid           The dashboard UUID.
     * @param string      $lang           The language code from the URL.
     * @param string|null $name           Optional new name.
     * @param string|null $description    Optional new description.
     * @param string|null $widgetTreeJson Optional new widget tree JSON.
     *
     * @return JSONResponse The updated variant.
         *
     * @spec openspec/specs/dashboard-language-content/spec.md
 */
    #[NoAdminRequired]
    public function update(
        string $uuid,
        string $lang,
        ?string $name=null,
        ?string $description=null,
        ?string $widgetTreeJson=null
    ): JSONResponse {
        $user = $this->userSession->getUser();
        if ($this->userId === null || $user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'dashboard-translation.update');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        $ownerCheck = $this->assertOwner(uuid: $uuid);
        if ($ownerCheck !== null) {
            return $ownerCheck;
        }

        $patch = $this->buildPatch(
            name: $name,
            description: $description,
            widgetTreeJson: $widgetTreeJson
        );

        try {
            $variant = $this->translationService->updateVariant(
                dashboardUuid: $uuid,
                languageCode: $lang,
                patch: $patch
            );

            return ResponseHelper::success(
                data: ['translation' => $variant->jsonSerialize()]
            );
        } catch (DoesNotExistException) {
            return new JSONResponse(
                data: [
                    'status' => 'error',
                    'error'  => 'not_found',
                ],
                statusCode: Http::STATUS_NOT_FOUND
            );
        } catch (Exception $e) {
            return ResponseHelper::error(exception: $e);
        }//end try
    }//end update()

    /**
     * DELETE /api/dashboards/{uuid}/translations/{lang} — delete a
     * variant. Maps last-variant / primary-variant guards to HTTP 400.
     * REQ-DASH-042.
     *
     * @param string $uuid The dashboard UUID.
     * @param string $lang The language code from the URL.
     *
     * @return JSONResponse The status payload.
         *
     * @spec openspec/specs/dashboard-language-content/spec.md
 */
    #[NoAdminRequired]
    public function destroy(string $uuid, string $lang): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($this->userId === null || $user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'dashboard-translation.destroy');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        $ownerCheck = $this->assertOwner(uuid: $uuid);
        if ($ownerCheck !== null) {
            return $ownerCheck;
        }

        try {
            $this->translationService->deleteVariant(
                dashboardUuid: $uuid,
                languageCode: $lang
            );

            return ResponseHelper::success(data: ['status' => 'ok']);
        } catch (DoesNotExistException) {
            return new JSONResponse(
                data: [
                    'status' => 'error',
                    'error'  => 'not_found',
                ],
                statusCode: Http::STATUS_NOT_FOUND
            );
        } catch (Exception $e) {
            $errorCode = 'invalid_state';
            if ($e->getMessage() === DashboardTranslationService::ERR_LAST_VARIANT) {
                $errorCode = 'last_variant';
            } else if ($e->getMessage() === DashboardTranslationService::ERR_DELETE_PRIMARY) {
                $errorCode = 'primary_variant';
            }

            return new JSONResponse(
                data: [
                    'status'  => 'error',
                    'error'   => $errorCode,
                    'message' => $e->getMessage(),
                ],
                statusCode: Http::STATUS_BAD_REQUEST
            );
        }//end try
    }//end destroy()

    /**
     * POST /api/dashboards/{uuid}/translations/{lang}/set-primary —
     * promote a variant to primary. Idempotent. REQ-DASH-043.
     *
     * @param string $uuid The dashboard UUID.
     * @param string $lang The language code from the URL.
     *
     * @return JSONResponse The promoted variant.
         *
     * @spec openspec/specs/dashboard-language-content/spec.md
 */
    #[NoAdminRequired]
    public function setPrimary(string $uuid, string $lang): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($this->userId === null || $user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'dashboard-translation.set-primary');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        $ownerCheck = $this->assertOwner(uuid: $uuid);
        if ($ownerCheck !== null) {
            return $ownerCheck;
        }

        try {
            $variant = $this->translationService->promoteVariantToPrimary(
                dashboardUuid: $uuid,
                languageCode: $lang
            );

            return ResponseHelper::success(
                data: ['translation' => $variant->jsonSerialize()]
            );
        } catch (DoesNotExistException) {
            return new JSONResponse(
                data: [
                    'status' => 'error',
                    'error'  => 'not_found',
                ],
                statusCode: Http::STATUS_NOT_FOUND
            );
        } catch (Exception $e) {
            return ResponseHelper::error(exception: $e);
        }
    }//end setPrimary()

    /**
     * GET /api/dashboards/{uuid}/resolved — resolve the dashboard's
     * content for the viewer's locale. Optional `?lang=<code>` query
     * parameter overrides the user's Nextcloud locale; in strict mode
     * an unknown explicit lang returns 404 instead of falling back.
     * REQ-DASH-039.
     *
     * Response shape:
     *  - `dashboard`: the dashboard entity payload
     *  - `translation`: the matched translation row
     *  - `availableLanguages`: sorted list of codes
     *  - `currentLanguage`: the matched code
     *  - `isFallback`: true when the primary fallback was used
     *
     * @param string $uuid The dashboard UUID.
     *
     * @return JSONResponse The resolved payload.
         *
     * @spec openspec/specs/dashboard-language-content/spec.md
 */
    #[NoAdminRequired]
    public function resolved(string $uuid): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($this->userId === null || $user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->actionAuth->requireAction($user, 'dashboard-translation.resolved');
        } catch (OCSForbiddenException) {
            return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

        try {
            $dashboard = $this->dashboardMapper->findByUuid(uuid: $uuid);
        } catch (DoesNotExistException) {
            return new JSONResponse(
                data: [
                    'status' => 'error',
                    'error'  => 'not_found',
                ],
                statusCode: Http::STATUS_NOT_FOUND
            );
        }

        $variant = $this->resolveVariant(
            uuid: $uuid,
            dashboard: $dashboard,
            explicitLang: $this->request->getParam(key: 'lang')
        );

        // Only the strict explicit-lang path yields null; the locale path
        // always materialises a variant. REQ-DASH-039 strict scenario.
        if ($variant === null) {
            return new JSONResponse(
                data: [
                    'status' => 'error',
                    'error'  => 'language_not_available',
                ],
                statusCode: Http::STATUS_NOT_FOUND
            );
        }

        return ResponseHelper::success(
            data: [
                'dashboard'          => $dashboard->jsonSerialize(),
                'translation'        => $variant['translation']->jsonSerialize(),
                'availableLanguages' => $this->resolveAvailableLanguages(
                    uuid: $uuid,
                    variant: $variant
                ),
                'currentLanguage'    => $variant['translation']->getLanguageCode(),
                'isFallback'         => $variant['isFallback'],
            ]
        );
    }//end resolved()

    /**
     * Resolve the translation variant this request should render.
     *
     * A usable `?lang=` query parameter selects the strict exact-match
     * path (which may report "no such language" by returning null); its
     * absence — or a blank / non-string value — selects the viewer's own
     * locale, which always yields a variant. REQ-DASH-039.
     *
     * @param string    $uuid         The dashboard UUID.
     * @param Dashboard $dashboard    The dashboard entity (legacy source).
     * @param mixed     $explicitLang The raw `lang` query parameter.
     *
     * @return array{translation: mixed, isFallback: bool}|null The variant,
     *                                                          or null.
     */
    private function resolveVariant(
        string $uuid,
        Dashboard $dashboard,
        mixed $explicitLang
    ): ?array {
        if (is_string($explicitLang) === false || $explicitLang === '') {
            return $this->resolveLocaleVariant(uuid: $uuid, dashboard: $dashboard);
        }

        return $this->resolveExactVariant(uuid: $uuid, explicitLang: $explicitLang);
    }//end resolveVariant()

    /**
     * Resolve a variant that matches the requested code exactly.
     *
     * Strict mode for the explicit `?lang=` parameter — when no exact
     * match exists for the requested code the caller must return 404
     * instead of the primary-fallback envelope, so a near-miss (the
     * service's own fallback) is reported as "no match" here.
     * REQ-DASH-039 strict scenario.
     *
     * @param string $uuid         The dashboard UUID.
     * @param string $explicitLang The requested language code.
     *
     * @return array{translation: mixed, isFallback: bool}|null The exact
     *                                                          match, or
     *                                                          null.
     */
    private function resolveExactVariant(string $uuid, string $explicitLang): ?array
    {
        $variant = $this->translationService->resolveForLocale(
            dashboardUuid: $uuid,
            preferredLanguage: $explicitLang
        );

        $requested = DashboardTranslationMapper::normaliseLanguageCode(
            raw: $explicitLang
        );
        $matched   = null;
        if ($variant !== null) {
            $matched = (string) $variant['translation']->getLanguageCode();
        }

        if ($matched !== $requested) {
            return null;
        }

        return $variant;
    }//end resolveExactVariant()

    /**
     * Resolve the variant for the viewer's own locale.
     *
     * Legacy fallback — dashboards predating REQ-DASH-038 may have no
     * translation rows yet. Materialise an in-memory variant from the
     * dashboard's own fields so the response envelope shape stays
     * uniform. REQ-DASH-044.
     *
     * @param string    $uuid      The dashboard UUID.
     * @param Dashboard $dashboard The dashboard entity (legacy source).
     *
     * @return array{translation: mixed, isFallback: bool} The variant.
     */
    private function resolveLocaleVariant(string $uuid, Dashboard $dashboard): array
    {
        $variant = $this->translationService->resolveForLocale(
            dashboardUuid: $uuid,
            preferredLanguage: ''
        );

        if ($variant !== null) {
            return $variant;
        }

        return [
            'translation' => $this->translationService
                ->materialiseLegacyVariant(dashboard: $dashboard),
            'isFallback'  => true,
        ];
    }//end resolveLocaleVariant()

    /**
     * List the language codes offered for this dashboard.
     *
     * A dashboard with no stored translation rows still advertises the
     * one code the resolved variant carries, so the language switcher is
     * never empty when content is being shown.
     *
     * @param string                                      $uuid    The dashboard UUID.
     * @param array{translation: mixed, isFallback: bool} $variant The resolved variant.
     *
     * @return array<int, string> The available codes.
     */
    private function resolveAvailableLanguages(string $uuid, array $variant): array
    {
        $available = $this->translationService->listAvailableLanguages(
            dashboardUuid: $uuid
        );
        if (count($available) > 0) {
            return $available;
        }

        $code = (string) $variant['translation']->getLanguageCode();
        if ($code === '') {
            return $available;
        }

        return [$code];
    }//end resolveAvailableLanguages()

    /**
     * Look up the dashboard and verify the current user is the owner.
     *
     * Returns null when the check passes; an HTTP 403 / 404 envelope
     * when it fails. Group-shared dashboards are not addressable via
     * the personal-scope translation endpoints — they short-circuit to
     * 403 (the group-scoped translation flow lives separately).
     *
     * @param string $uuid The dashboard UUID.
     *
     * @return JSONResponse|null The error envelope or null on success.
     */
    private function assertOwner(string $uuid): ?JSONResponse
    {
        try {
            $dashboard = $this->dashboardMapper->findByUuid(uuid: $uuid);
        } catch (DoesNotExistException) {
            return new JSONResponse(
                data: [
                    'status' => 'error',
                    'error'  => 'not_found',
                ],
                statusCode: Http::STATUS_NOT_FOUND
            );
        }

        if ($dashboard->getUserId() !== $this->userId) {
            return ResponseHelper::forbidden();
        }

        return null;
    }//end assertOwner()

    /**
     * Build the patch payload from individual nullable parameters.
     *
     * `null` means "not in payload" (skip the key); anything else (incl.
     * the empty string) means "set it explicitly". The service then
     * inspects key presence with `array_key_exists`.
     *
     * @param string|null $name           The new name.
     * @param string|null $description    The new description.
     * @param string|null $widgetTreeJson The new widget tree JSON.
     *
     * @return array The patch payload.
     */
    private function buildPatch(
        ?string $name,
        ?string $description,
        ?string $widgetTreeJson
    ): array {
        $patch = [];
        if ($name !== null) {
            $patch['name'] = $name;
        }

        if ($description !== null) {
            $patch['description'] = $description;
        }

        if ($widgetTreeJson !== null) {
            $patch['widgetTreeJson'] = $widgetTreeJson;
        }

        return $patch;
    }//end buildPatch()
}//end class
