<?php

/**
 * EmbedRenderService
 *
 * Renders an embed's subject for its token (embedded-analytics task 10): the
 * widget or dashboard read through the same mappers and serialisation the
 * public share uses (no fork), with the token's scope, the pinned filter
 * context and the tenant theme's :root block attached, and the page that
 * frames it served without navigation chrome under the token's CSP.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Embed
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

namespace OCA\LaunchPad\Service\Embed;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Entity\EmbedToken;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;

/**
 * Builds what an embed shows.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
 */
class EmbedRenderService {

	/**
	 * Constructor.
	 *
	 * @param DashboardMapper         $dashboards The dashboards.
	 * @param WidgetPlacementMapper   $widgets    The widget placements.
	 * @param TenantThemeService      $themes     The tenant themes.
	 * @param ScopeEnforcementService $scopes     The scope decisions.
	 */
	public function __construct(
		private readonly DashboardMapper $dashboards,
		private readonly WidgetPlacementMapper $widgets,
		private readonly TenantThemeService $themes,
		private readonly ScopeEnforcementService $scopes,
	) {
	}//end __construct()

	/**
	 * One widget for its token.
	 *
	 * @param string               $widgetId       The placement id.
	 * @param EmbedToken           $token          The token, which names this widget.
	 * @param array<string, mixed> $requestFilters The filters the embed asks for.
	 *
	 * @return array<string, mixed>|null Null when the widget no longer exists.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	public function renderWidget(string $widgetId, EmbedToken $token, array $requestFilters=[]): ?array {
		if (ctype_digit($widgetId) === false) {
			return null;
		}

		try {
			$placement = $this->widgets->find(id: (int)$widgetId);
		} catch (DoesNotExistException) {
			return null;
		}

		return $this->envelope(token: $token, requestFilters: $requestFilters) + [
			'subjectType' => 'widget',
			'placements'  => [$placement->jsonSerialize()],
		];
	}//end renderWidget()

	/**
	 * One dashboard, with its widgets, for its token.
	 *
	 * @param string               $dashboardId    The dashboard uuid.
	 * @param EmbedToken           $token          The token, which names this dashboard.
	 * @param array<string, mixed> $requestFilters The filters the embed asks for.
	 *
	 * @return array<string, mixed>|null Null when the dashboard no longer exists.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	public function renderDashboard(string $dashboardId, EmbedToken $token, array $requestFilters=[]): ?array {
		try {
			$dashboard = $this->dashboards->findByUuid(uuid: $dashboardId);
		} catch (DoesNotExistException) {
			return null;
		}

		return $this->envelope(token: $token, requestFilters: $requestFilters) + [
			'subjectType' => 'dashboard',
			'dashboard'   => $dashboard->jsonSerialize(),
			'placements'  => array_map(
				static fn ($placement): array => $placement->jsonSerialize(),
				$this->widgets->findByDashboardId(dashboardId: (int)$dashboard->getId())
			),
		];
	}//end renderDashboard()

	/**
	 * The subject named by type, or null for an unknown type or a missing subject.
	 *
	 * @param string               $subjectType    `widget` or `dashboard`.
	 * @param string               $subjectId      Its id.
	 * @param EmbedToken           $token          The token.
	 * @param array<string, mixed> $requestFilters The filters the embed asks for.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	public function render(string $subjectType, string $subjectId, EmbedToken $token, array $requestFilters=[]): ?array {
		return match ($subjectType) {
			'widget' => $this->renderWidget(widgetId: $subjectId, token: $token, requestFilters: $requestFilters),
			'dashboard' => $this->renderDashboard(dashboardId: $subjectId, token: $token, requestFilters: $requestFilters),
			default => null,
		};
	}//end render()

	/**
	 * The page that frames an embed: the bare base layout (no navigation),
	 * the tenant theme's :root block, and the token's host origins as the only
	 * frame ancestors. X-Frame-Options stays as the legacy fallback (3.5).
	 *
	 * @param string     $subjectType `widget` or `dashboard`.
	 * @param string     $subjectId   Its id.
	 * @param EmbedToken $token       The token.
	 *
	 * @return TemplateResponse
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-003--csp-frame-ancestors-enforced-from-token-hostorigins
	 */
	public function page(string $subjectType, string $subjectId, EmbedToken $token): TemplateResponse {
		$response = new TemplateResponse(
			appName: Application::APP_ID,
			templateName: 'embed',
			params: [
				'subjectType' => $subjectType,
				'subjectId'   => $subjectId,
				'themeCss'    => $this->themes->rootCss(themeId: $token->tenantThemeId),
			],
			renderAs: TemplateResponse::RENDER_AS_BASE
		);

		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain(domain: 'data:');
		foreach ($token->hostOrigins as $origin) {
			$csp->addAllowedFrameAncestorDomain(domain: (string)$origin);
		}

		$response->setContentSecurityPolicy(csp: $csp);
		$response->addHeader('X-Frame-Options', 'SAMEORIGIN');
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->addHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

		return $response;
	}//end page()

	/**
	 * What every embed payload carries: the scope the frontend honours, the
	 * filters in force (pinned context over the allowed request filters) and
	 * the theme block.
	 *
	 * @param EmbedToken           $token          The token.
	 * @param array<string, mixed> $requestFilters The filters the embed asks for.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
	 */
	private function envelope(EmbedToken $token, array $requestFilters): array {
		$filterContext = $token->subject['filterContext'] ?? [];
		if (is_array($filterContext) === false) {
			$filterContext = [];
		}

		return [
			'scope'    => [
				'mode'           => (string)($token->scope['mode'] ?? ScopeEnforcementService::MODE_READ),
				'allowedActions' => array_values((array)($token->scope['allowedActions'] ?? [])),
				'allowedFilters' => array_values((array)($token->scope['allowedFilters'] ?? [])),
			],
			'filters'  => $this->scopes->restrictFilters(requestFilters: $requestFilters, scope: $token->scope, filterContext: $filterContext),
			'themeCss' => $this->themes->rootCss(themeId: $token->tenantThemeId),
		];
	}//end envelope()

}//end class
