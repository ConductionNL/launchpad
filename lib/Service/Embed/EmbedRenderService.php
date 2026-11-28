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
use OCA\LaunchPad\Db\ContainerChildKeys;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Entity\EmbedToken;
use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;
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
	 * @param ConditionalServiceInterface|null $visibility Applies the widget visibility rules.
	 */
	public function __construct(
		private readonly DashboardMapper $dashboards,
		private readonly WidgetPlacementMapper $widgets,
		private readonly TenantThemeService $themes,
		private readonly ScopeEnforcementService $scopes,
		private readonly ?ConditionalServiceInterface $visibility = null,
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
		$placements = $this->widgetPlacements(widgetId: $widgetId);
		if ($placements === null) {
			return null;
		}

		return $this->envelope(token: $token, requestFilters: $requestFilters) + [
			'subjectType' => 'widget',
			'placements'  => $placements,
		];
	}//end renderWidget()

	/**
	 * The placements a widget embed shows: one widget on a dashboard, or one
	 * widget inside a well or a tabs widget (`<placement id>.<child key>`).
	 *
	 * @param string $widgetId The subject id.
	 *
	 * @return array<int, array<string, mixed>>|null Null when it does not name a widget that exists.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	private function widgetPlacements(string $widgetId): ?array {
		$child = (new ContainerChildKeys())->splitSubject(subjectId: $widgetId);
		if ($child !== null) {
			return $this->childPlacements(subject: $child);
		}

		if (ctype_digit($widgetId) === false) {
			return null;
		}

		try {
			$placement = $this->widgets->find(id: (int)$widgetId);
		} catch (DoesNotExistException) {
			return null;
		}

		return $this->forVisitor(placements: [$placement->jsonSerialize()]);
	}//end widgetPlacements()

	/**
	 * The placements an embed of one widget inside a well or a tabs widget
	 * shows (REQ-WDG-050): the child drawn as a widget of its own, only while
	 * its well shows (a well the visibility rules hide hides the child too).
	 *
	 * @param array{0: int, 1: string} $subject The well's placement id and the child's key.
	 *
	 * @return array<int, array<string, mixed>>|null Null when the well or the child no longer exists.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	private function childPlacements(array $subject): ?array {
		try {
			$parent = $this->widgets->find(id: $subject[0]);
		} catch (DoesNotExistException) {
			return null;
		}

		$child = self::typedChild(content: $parent->getContentArray(), key: $subject[1]);
		if ($child === null) {
			return null;
		}

		if ($this->forVisitor(placements: [$parent->jsonSerialize()]) === []) {
			return [];
		}

		return [self::childAsPlacement(parent: $parent->jsonSerialize(), child: $child)];
	}//end childPlacements()

	/**
	 * The child with this key when it names a widget type, else null.
	 *
	 * @param array<string, mixed> $content The well's content.
	 * @param string               $key     The child's key.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function typedChild(array $content, string $key): ?array {
		$child = (new ContainerChildKeys())->find(content: $content, key: $key);
		if (is_string($child['type'] ?? null) === false || $child['type'] === '') {
			return null;
		}

		return $child;
	}//end typedChild()

	/**
	 * A child of a well as a placement of its own: the well's id and
	 * dashboard, the child's type, content, title and look.
	 *
	 * @param array<string, mixed> $parent The well, serialised.
	 * @param array<string, mixed> $child  The child.
	 *
	 * @return array<string, mixed>
	 */
	private static function childAsPlacement(array $parent, array $child): array {
		$own = $child;
		unset($own['type'], $own['id'], $own['uuid']);

		return array_merge(
			$parent,
			[
				'widgetId'    => $child['type'],
				'content'     => (object)[],
				'styleConfig' => (object)[],
				'customTitle' => null,
				'childKey'    => (new ContainerChildKeys())->keyOf(child: $child),
			],
			$own
		);
	}//end childAsPlacement()

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
			'placements'  => $this->forVisitor(
				placements: array_map(
					static fn ($placement): array => $placement->jsonSerialize(),
					$this->widgets->findByDashboardId(dashboardId: (int)$dashboard->getId())
				)
			),
		];
	}//end renderDashboard()

	/**
	 * The placements an embed shows: an embed has no viewer who can edit, so
	 * a widget the visibility rules hide is left out (REQ-VIS-013).
	 *
	 * @param array<int, array<string, mixed>> $placements The serialised placements.
	 *
	 * @return array<int, array<string, mixed>> The placements to render.
	 */
	private function forVisitor(array $placements): array {
		return ($this->visibility?->visiblePlacements(placements: $placements, userId: '', canEdit: false) ?? $placements);
	}//end forVisitor()

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
	 * The page URL can carry the token (`?token=`), so the Referer it sends is
	 * only ever its origin, never that URL (`strict-origin`): the token does
	 * not reach access logs through the requests the page makes.
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
		$response->addHeader('Referrer-Policy', 'strict-origin');

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
