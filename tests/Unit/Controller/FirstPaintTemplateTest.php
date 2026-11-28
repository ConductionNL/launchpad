<?php

/**
 * FirstPaintTemplateTest
 *
 * The workspace template with the first-paint shell (first-paint-skeleton
 * task 3): the global custom CSS in the `launchpad-custom-css` element outside
 * the shell, the dashboard's own look on the shell's region, and a dashboard
 * that fills the height drawn as a grid of the library's row tracks with
 * each frame at its own cell.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\FirstPaintPage;
use OCA\LaunchPad\Service\FirstPaintLook;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/fixtures/template-functions.php';

/**
 * Tests for the shell's look, custom CSS and fill height.
 *
 * @covers \OCA\LaunchPad\Controller\FirstPaintPage
 * @covers \OCA\LaunchPad\Service\FirstPaintLook
 * @uses   \OCA\LaunchPad\Service\FirstPaintShell
 * @uses   \OCA\LaunchPad\Service\FirstPaintLayout
 * @uses   \OCA\LaunchPad\Service\FirstPaintGridEngine
 * @uses   \OCA\LaunchPad\Service\FirstPaintPlaceholder
 * @uses   \OCA\LaunchPad\Service\FirstPaintFill
 * @uses   \OCA\LaunchPad\Service\FirstPaintNotices
 * @uses   \OCA\LaunchPad\Service\DashboardLook
 */
class FirstPaintTemplateTest extends TestCase {

	/**
	 * The page values of an opened dashboard.
	 *
	 * @param array<string, mixed> $dashboard  More dashboard fields.
	 * @param list<array>          $placements The placements.
	 *
	 * @return array<string, mixed>
	 */
	private static function page(array $dashboard, array $placements): array {
		return [
			'firstLoad'         => [
				'visible' => [],
				'active'  => ['dashboard' => $dashboard + ['uuid' => 'u-1', 'name' => 'Team', 'gridColumns' => 12], 'placements' => $placements],
			],
			'activeDashboardId' => 'u-1',
			'dashboardSource'   => 'group',
			'widgets'           => [['id' => 'calendar', 'title' => 'Agenda']],
		];
	}//end page()

	/**
	 * Render the template with these parameters.
	 *
	 * @param array<string, mixed> $params The template parameters.
	 *
	 * @return string The HTML.
	 */
	private function render(array $params): string {
		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnArgument(0);
		$l->method('getLocaleCode')->willReturn('en');
		// The template's own names: Nextcloud hands the parameters over as $_ and the translator as $l.
		$_ = $params; // phpcs:ignore Squiz.NamingConventions.ValidVariableName.NotCamelCaps
		ob_start();
		include __DIR__.'/../../../templates/index.php';

		return (string) ob_get_clean();
	}//end render()

	/**
	 * The look draws on the region as dashboardLook.js does; a dashboard that
	 * follows the theme gets no style; nothing but checked values goes in.
	 *
	 * @return void
	 */
	public function testTheLook(): void {
		$look = new FirstPaintLook();
		$this->assertSame(
			expected: '--color-primary-element:#1f5c3a;--color-primary-element-text:#ffffff;background-color:#ffeedd;color:#000000;--color-main-text:#000000;'
				.'background-image:url("/apps/launchpad/resource/hero-1.png");background-size:cover;background-position:center;',
			actual: $look->style(
				dashboard: ['themeAccent' => '#1F5C3A', 'themeBackgroundColor' => '#fed', 'themeBackgroundResource' => 'hero-1.png'],
				resourceUrl: '/apps/launchpad/resource/'
			)
		);
		$this->assertSame(expected: '', actual: $look->style(dashboard: [], resourceUrl: '/r/'));
		$this->assertSame(
			expected: '',
			actual: $look->style(
				dashboard: ['themeAccent' => 'red;x:y', 'themeBackgroundColor' => null, 'themeBackgroundResource' => '../etc/passwd.png'],
				resourceUrl: '/r/'
			)
		);
	}//end testTheLook()

	/**
	 * The custom CSS is printed outside the shell with the id applyCustomCss
	 * reuses, and cannot close its element; the look is on the region.
	 *
	 * @return void
	 */
	public function testCustomCssAndLookArePrinted(): void {
		$calendar = ['id' => 7, 'widgetId' => 'calendar', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 4];
		$params   = FirstPaintPage::params(
			page: self::page(dashboard: ['themeBackgroundColor' => '#000000'], placements: [$calendar]),
			context: ['customCss' => '.launchpad-workspace a{color:red}</style><b>', 'resourceUrl' => '/r/', 'reloadUrl' => '/apps/launchpad/team']
		);
		$html   = $this->render(params: $params);

		$this->assertSame(expected: 1, actual: substr_count($html, '<style id="launchpad-custom-css">'));
		$this->assertStringContainsString(needle: '.launchpad-workspace a{color:red}\3c /style>\3c b></style>', haystack: $html);
		$this->assertLessThan(expected: strpos($html, 'id="launchpad-first-paint"'), actual: strpos($html, 'id="launchpad-custom-css"'));
		$region = '<div class="lp-fp__c" style="background-color:#000000;color:#ffffff;--color-main-text:#ffffff;">';
		$this->assertStringContainsString(needle: $region, haystack: $html);

		// REQ-FPS-008: the could-not-load line with its Reload link, an alert shown by CSS after 15 s.
		$this->assertMatchesRegularExpression(
			pattern: '#<p class="lp-fp__x" role="alert">\s*The page could not load\s*<a href="/apps/launchpad/team">Reload</a>#',
			string: $html
		);

		$plain = $this->render(params: FirstPaintPage::params(page: self::page(dashboard: [], placements: []), context: []));
		$this->assertStringNotContainsString(needle: 'launchpad-custom-css', haystack: $plain);
		$this->assertStringContainsString(needle: '<div class="lp-fp__c">', haystack: $plain);
	}//end testCustomCssAndLookArePrinted()

	/**
	 * A filling dashboard: the grid carries the tracks, each frame its cell,
	 * and fit-row frames keep their rows' height; a plain one has none of it.
	 *
	 * @return void
	 */
	public function testAFillingDashboardIsAGridOfTracks(): void {
		$placements = [
			[
				'id'      => 1, 'widgetId' => 'text', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2,
				'content' => ['fillHeight' => true, 'fitRows' => true],
			],
			['id' => 2, 'widgetId' => 'calendar', 'gridX' => 4, 'gridY' => 2, 'gridWidth' => 8, 'gridHeight' => 3],
		];
		$html       = $this->render(params: FirstPaintPage::params(page: self::page(dashboard: [], placements: $placements), context: []));

		$this->assertStringContainsString(needle: 'class="lp-fp__g lp-fp__g--fill"', haystack: $html);
		$this->assertStringContainsString(needle: '--fr:auto auto minmax(0,1fr) minmax(0,1fr) minmax(0,1fr);', haystack: $html);
		$this->assertStringContainsString(needle: '--fc:5/span 8;--fw:3/span 3;--fh:3;', haystack: $html);
		$this->assertStringContainsString(needle: 'lp-fp__f lp-fp__f--lines lp-fp__f--fit', haystack: $html);

		unset($placements[0]['content']);
		$plain = $this->render(params: FirstPaintPage::params(page: self::page(dashboard: [], placements: $placements), context: []));
		$this->assertStringNotContainsString(needle: 'lp-fp__g--fill"', haystack: $plain);
		$this->assertStringNotContainsString(needle: '--fc:', haystack: $plain);
	}//end testAFillingDashboardIsAGridOfTracks()

	/**
	 * The notice banners from the page's reads stand above the dashboard in
	 * the shell, escaped, a warning in its own colours.
	 *
	 * @return void
	 */
	public function testNoticeBannersArePrinted(): void {
		$page = self::page(dashboard: [], placements: []) + [
			'apiReads' => [
				[
					'path' => '/api/announcements',
					'data' => ['announcements' => [['uuid' => 'n', 'kind' => 'notice', 'title' => '<i>Down</i>', 'body' => 'Sat', 'level' => 'warning']]],
				],
			],
		];
		$html = $this->render(params: FirstPaintPage::params(page: $page, context: []));

		$this->assertStringContainsString(needle: '<div class="lp-fp__n lp-fp__n--w">', haystack: $html);
		$this->assertStringContainsString(needle: '<b>&lt;i&gt;Down&lt;/i&gt;</b>', haystack: $html);
		$this->assertLessThan(expected: strpos($html, 'class="lp-fp__c"'), actual: strpos($html, 'class="lp-fp__n'));

		$none = $this->render(params: FirstPaintPage::params(page: self::page(dashboard: [], placements: []), context: []));
		$this->assertStringNotContainsString(needle: 'class="lp-fp__n', haystack: $none);
	}//end testNoticeBannersArePrinted()
}//end class
