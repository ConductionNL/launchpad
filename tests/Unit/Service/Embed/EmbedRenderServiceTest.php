<?php

/**
 * EmbedRenderService unit tests (REQ-EMB-002/003/005, task 10).
 *
 * @category  Test
 * @package   Unit\Service\Embed
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

namespace Unit\Service\Embed;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Entity\EmbedToken;
use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;
use OCA\LaunchPad\Service\Embed\EmbedRenderService;
use OCA\LaunchPad\Service\Embed\ScopeEnforcementService;
use OCA\LaunchPad\Service\Embed\TenantThemeService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(EmbedRenderService::class)]
#[UsesClass(EmbedToken::class)]
#[UsesClass(\OCA\LaunchPad\Db\ContainerChildKeys::class)]
#[UsesClass(ScopeEnforcementService::class)]
#[UsesClass(\OCA\LaunchPad\Db\Dashboard::class)]
class EmbedRenderServiceTest extends TestCase {

	private DashboardMapper&MockObject $dashboards;

	private WidgetPlacementMapper&MockObject $widgets;

	private TenantThemeService&MockObject $themes;

	protected function setUp(): void {
		$this->dashboards = $this->createMock(DashboardMapper::class);
		$this->widgets    = $this->createMock(WidgetPlacementMapper::class);
		$this->themes     = $this->createMock(TenantThemeService::class);
		$this->themes->method('rootCss')->willReturnCallback(
			static fn (?string $themeId): string => ($themeId === 'theme-zeist' ? ':root{--c-primary:#004f9f;}' : '')
		);
	}//end setUp()

	public function testAWidgetComesWithItsScopeFiltersAndTheme(): void {
		$placement = $this->createMock(WidgetPlacement::class);
		$placement->method('jsonSerialize')->willReturn(['id' => 42, 'widgetType' => 'chart']);
		$this->widgets->expects($this->once())->method('find')->with(42)->willReturn($placement);

		$payload = $this->service()->render(
			subjectType: 'widget',
			subjectId: '42',
			token: self::token(subject: ['type' => 'widget', 'id' => '42', 'filterContext' => ['gemeente' => 'Amsterdam']]),
			requestFilters: ['status' => 'open', 'periode' => '2026', 'gemeente' => 'Rotterdam']
		);

		$this->assertSame([
			'scope' => ['mode' => 'read-with-interactions', 'allowedActions' => ['drillDown'], 'allowedFilters' => ['status']],
			'filters' => ['status' => 'open', 'gemeente' => 'Amsterdam'],
			'themeCss' => ':root{--c-primary:#004f9f;}',
			'subjectType' => 'widget',
			'placements' => [['id' => 42, 'widgetType' => 'chart']],
		], $payload);
	}//end testAWidgetComesWithItsScopeFiltersAndTheme()

	public function testADashboardComesWithItsWidgets(): void {
		$dashboard = $this->getMockBuilder(Dashboard::class)->onlyMethods(['jsonSerialize'])->getMock();
		$dashboard->setId(7);
		$dashboard->method('jsonSerialize')->willReturn(['uuid' => 'dash-uuid', 'name' => 'Woo']);
		$this->dashboards->expects($this->once())->method('findByUuid')->with('dash-uuid')->willReturn($dashboard);
		$first = $this->createMock(WidgetPlacement::class);
		$first->method('jsonSerialize')->willReturn(['id' => 1]);
		$second = $this->createMock(WidgetPlacement::class);
		$second->method('jsonSerialize')->willReturn(['id' => 2]);
		$this->widgets->expects($this->once())->method('findByDashboardId')->with(7)->willReturn([$first, $second]);

		$payload = $this->service()->renderDashboard(
			dashboardId: 'dash-uuid',
			token: self::token(subject: ['type' => 'dashboard', 'id' => 'dash-uuid'], scope: ['mode' => 'read'], theme: null)
		);

		$this->assertSame('dashboard', $payload['subjectType'] ?? null);
		$this->assertSame(['uuid' => 'dash-uuid', 'name' => 'Woo'], $payload['dashboard'] ?? null);
		$this->assertSame([['id' => 1], ['id' => 2]], $payload['placements'] ?? null);
		$this->assertSame(['mode' => 'read', 'allowedActions' => [], 'allowedFilters' => []], $payload['scope'] ?? null);
		$this->assertSame([], $payload['filters'] ?? null);
		$this->assertSame('', $payload['themeCss'] ?? null);
	}//end testADashboardComesWithItsWidgets()

	public function testAMissingOrUnknownSubjectIsNull(): void {
		$this->widgets->method('find')->willThrowException(new DoesNotExistException('gone'));
		$this->dashboards->method('findByUuid')->willThrowException(new DoesNotExistException('gone'));
		$service = $this->service();
		$token   = self::token();

		$this->assertNull($service->render(subjectType: 'widget', subjectId: '43', token: $token));
		$this->assertNull($service->render(subjectType: 'dashboard', subjectId: 'gone-uuid', token: $token));
		$this->assertNull($service->render(subjectType: 'report', subjectId: '42', token: $token));
		$this->assertNull($service->renderWidget(widgetId: '42 OR 1=1', token: $token));
	}//end testAMissingOrUnknownSubjectIsNull()

	/**
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function testAWidgetInsideAWellIsDrawnAsAWidgetOfItsOwn(): void {
		$well = $this->createMock(WidgetPlacement::class);
		$well->method('jsonSerialize')->willReturn(['id' => 12, 'dashboardId' => 7, 'widgetId' => 'container', 'customTitle' => 'Apps', 'styleConfig' => ['backgroundColor' => '#eee'], 'gridX' => 0]);
		$well->method('getContentArray')->willReturn([
			'placements' => [
				['type' => 'text', 'uuid' => '12-0', 'content' => ['text' => 'Eerste']],
				['type' => 'tabs', 'uuid' => 'ab12', 'content' => ['tabs' => [
					['id' => 't1', 'placements' => [['type' => 'clock', 'uuid' => 'cd34', 'content' => ['zone' => 'Europe/Amsterdam'], 'customTitle' => 'Klok']]],
				]]],
			],
		]);
		$this->widgets->expects($this->exactly(2))->method('find')->with(12)->willReturn($well);
		$service = $this->service();

		$first = $service->render(subjectType: 'widget', subjectId: '12.12-0', token: self::token(subject: ['type' => 'widget', 'id' => '12.12-0']));
		$this->assertSame('widget', $first['subjectType'] ?? null);
		$this->assertEquals([[
			'id' => 12,
			'dashboardId' => 7,
			'widgetId' => 'text',
			'customTitle' => null,
			'styleConfig' => (object)[],
			'gridX' => 0,
			'content' => ['text' => 'Eerste'],
			'childKey' => '12-0',
		]], $first['placements'] ?? null);

		$nested = $service->render(subjectType: 'widget', subjectId: '12.cd34', token: self::token());
		$this->assertSame('clock', $nested['placements'][0]['widgetId'] ?? null);
		$this->assertSame('Klok', $nested['placements'][0]['customTitle'] ?? null);
		$this->assertSame(['zone' => 'Europe/Amsterdam'], $nested['placements'][0]['content'] ?? null);
	}//end testAWidgetInsideAWellIsDrawnAsAWidgetOfItsOwn()

	/**
	 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
	 */
	public function testAMissingChildOrWellIsNullAndAHiddenWellHidesItsChild(): void {
		$well = $this->createMock(WidgetPlacement::class);
		$well->method('jsonSerialize')->willReturn(['id' => 12]);
		$well->method('getContentArray')->willReturn(['placements' => [['type' => 'text', 'uuid' => 'aa'], ['uuid' => 'notype']]]);
		$this->widgets->method('find')->willReturnCallback(static function (int $id) use ($well) {
			if ($id === 12) {
				return $well;
			}

			throw new DoesNotExistException('gone');
		});
		$token = self::token();

		$this->assertNull($this->service()->renderWidget(widgetId: '12.bb', token: $token));
		$this->assertNull($this->service()->renderWidget(widgetId: '12.notype', token: $token));
		$this->assertNull($this->service()->renderWidget(widgetId: '13.aa', token: $token));
		$this->assertNull($this->service()->renderWidget(widgetId: '12.a/b', token: $token));

		$visibility = $this->createMock(originalClassName: ConditionalServiceInterface::class);
		$visibility->method('visiblePlacements')->willReturn([]);
		$hidden = new EmbedRenderService($this->dashboards, $this->widgets, $this->themes, new ScopeEnforcementService(), $visibility);
		$this->assertSame([], $hidden->renderWidget(widgetId: '12.aa', token: $token)['placements'] ?? null);
	}//end testAMissingChildOrWellIsNullAndAHiddenWellHidesItsChild()

	public function testThePageIsFramedOnlyByTheHostOriginsAndCarriesTheTheme(): void {
		$response = $this->service()->page(subjectType: 'widget', subjectId: '42', token: self::token());

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame('embed', $response->getTemplateName());
		$this->assertSame(TemplateResponse::RENDER_AS_BASE, $response->getRenderAs());
		$this->assertSame(
			['subjectType' => 'widget', 'subjectId' => '42', 'themeCss' => ':root{--c-primary:#004f9f;}'],
			$response->getParams()
		);
		$this->assertStringContainsString(
			"frame-ancestors 'self' https://intranet.zeist.nl https://www.zeist.nl",
			$response->getContentSecurityPolicy()->buildPolicy()
		);

		$headers = (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
		$this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
		$this->assertSame('nosniff', $headers['X-Content-Type-Options']);
	}//end testThePageIsFramedOnlyByTheHostOriginsAndCarriesTheTheme()

	/**
	 * The page URL can hold the token, so its requests send only its origin as Referer.
	 *
	 * @return void
	 */
	public function testThePageNeverSendsItsTokenUrlAsReferer(): void {
		$response = $this->service()->page(subjectType: 'widget', subjectId: '42', token: self::token());

		$headers = (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
		$this->assertSame(expected: 'strict-origin', actual: $headers['Referrer-Policy']);
	}//end testThePageNeverSendsItsTokenUrlAsReferer()

	private function service(): EmbedRenderService {
		return new EmbedRenderService($this->dashboards, $this->widgets, $this->themes, new ScopeEnforcementService());
	}//end service()

	/**
	 * @param array<string, mixed> $subject
	 * @param array<string, mixed> $scope
	 */
	private static function token(
		array $subject=['type' => 'widget', 'id' => '42'],
		array $scope=['mode' => 'read-with-interactions', 'allowedActions' => ['drillDown'], 'allowedFilters' => ['status']],
		?string $theme='theme-zeist',
	): EmbedToken {
		return EmbedToken::fromObject([
			'id' => 'token-1',
			'subject' => $subject,
			'scope' => $scope,
			'hostOrigins' => ['https://intranet.zeist.nl', 'https://www.zeist.nl'],
			'tenantThemeId' => $theme,
		]);
	}//end token()

	/**
	 * An embed has no viewer who can edit: its placements go through the
	 * visibility rules for a visitor without an account (REQ-VIS-013).
	 *
	 * @return void
	 */
	public function testAnEmbedAppliesTheVisibilityRulesForAVisitor(): void {
		$dashboard = $this->getMockBuilder(className: Dashboard::class)->onlyMethods(['jsonSerialize'])->getMock();
		$dashboard->setId(7);
		$dashboard->method('jsonSerialize')->willReturn(['uuid' => 'dash-uuid']);
		$this->dashboards->method('findByUuid')->willReturn($dashboard);
		$placement = $this->createMock(originalClassName: WidgetPlacement::class);
		$placement->method('jsonSerialize')->willReturn(['id' => 1]);
		$this->widgets->method('findByDashboardId')->willReturn([$placement]);
		$visibility = $this->createMock(originalClassName: ConditionalServiceInterface::class);
		$visibility->expects($this->once())->method('visiblePlacements')->with([['id' => 1]], '', false)->willReturn([]);
		$service = new EmbedRenderService($this->dashboards, $this->widgets, $this->themes, new ScopeEnforcementService(), $visibility);

		$payload = $service->renderDashboard(
			dashboardId: 'dash-uuid',
			token: self::token(subject: ['type' => 'dashboard', 'id' => 'dash-uuid'], scope: ['mode' => 'read'], theme: null)
		);

		$this->assertSame(expected: [], actual: $payload['placements'] ?? null);
	}//end testAnEmbedAppliesTheVisibilityRulesForAVisitor()
}//end class
