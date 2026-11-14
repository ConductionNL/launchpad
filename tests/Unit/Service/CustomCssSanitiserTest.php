<?php

/**
 * CustomCssSanitiser Test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\CustomCssSanitiser;
use OCA\LaunchPad\Service\DashboardLook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomCssSanitiser::class)]
#[UsesClass(DashboardLook::class)]
class CustomCssSanitiserTest extends TestCase {

	private const SCOPE = CustomCssSanitiser::GLOBAL_SCOPE;

	private CustomCssSanitiser $sanitiser;

	protected function setUp(): void {
		parent::setUp();
		$this->sanitiser = new CustomCssSanitiser();
	}

	/**
	 * The spec's scenario "Import dropped".
	 */
	public function testTheImportIsDroppedAndTheRuleKeptScoped(): void {
		$result = $this->sanitiser->sanitise(
			css: '@import url(https://evil.example/x.css); .tile-widget__title { font-weight: 700; }',
			scope: self::SCOPE
		);

		$this->assertSame(".launchpad-workspace .tile-widget__title {\n  font-weight: 700;\n}", $result['css']);
		$this->assertSame(['@import'], $result['removed']);
	}

	/**
	 * The spec's scenario "CSS cannot reach outside LaunchPad".
	 */
	public function testADashboardRuleIsScopedToThatDashboardsRegion(): void {
		$scope  = CustomCssSanitiser::dashboardScope(uuid: 'a1b2c3d4-0000-4000-8000-000000000001');
		$result = $this->sanitiser->sanitise(css: 'body { display: none; }', scope: $scope);

		$this->assertSame(
			".launchpad-dashboard[data-dashboard-uuid=\"a1b2c3d4-0000-4000-8000-000000000001\"] body {\n  display: none;\n}",
			$result['css']
		);
		$this->assertSame([], $result['removed']);
	}

	public function testEverySelectorOfAListIsScopedAndTheScopeItselfIsKept(): void {
		$result = $this->sanitiser->sanitise(
			css: 'h2, .launchpad-workspace, a[title="x, y"] > span { color: red }',
			scope: self::SCOPE
		);

		$this->assertSame(
			".launchpad-workspace h2, .launchpad-workspace, .launchpad-workspace a[title=\"x, y\"] > span {\n  color: red;\n}",
			$result['css']
		);
	}

	public function testAClassThatOnlyStartsLikeTheScopeIsStillScoped(): void {
		$result = $this->sanitiser->sanitise(css: '.launchpad-workspace-x { color: red; }', scope: self::SCOPE);

		$this->assertSame(".launchpad-workspace .launchpad-workspace-x {\n  color: red;\n}", $result['css']);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function droppedDeclarations(): array {
		return [
			'expression'            => ['width: expression(alert(1))', 'expression()'],
			'javascript address'    => ['background: url(javascript:alert(1))', 'javascript:'],
			'behavior'              => ['behavior: url(x.htc)', 'behavior'],
			'moz binding'           => ['-moz-binding: url(x.xml#y)', '-moz-binding'],
			'remote url'            => ['background: url(https://evil.example/a.png)', 'url() to another site'],
			'quoted remote url'     => ["background: url('//evil.example/a.png')", 'url() to another site'],
			'image-set'             => ['background: image-set("https://evil.example/a.png" 1x)', 'url() to another site'],
			'escape'                => ['background: u\\72l(https://evil.example/a.png)', 'escapes'],
			'markup'                => ['content: "</style><script>"', 'markup'],
			'path traversal'        => ['background: url(/apps/launchpad/resource/../../x.png)', 'url() to another site'],
			'no colon'              => ['colour red', 'unreadable CSS'],
		];
	}

	#[DataProvider('droppedDeclarations')]
	public function testAnUnsafeDeclarationIsDroppedAndNamed(string $declaration, string $label): void {
		$result = $this->sanitiser->sanitise(css: '.tile { color: red; ' . $declaration . '; }', scope: self::SCOPE);

		$this->assertSame(".launchpad-workspace .tile {\n  color: red;\n}", $result['css']);
		$this->assertSame([$label], $result['removed']);
	}

	public function testACommentCannotHideAnExpression(): void {
		$result = $this->sanitiser->sanitise(css: '.tile { width: expr/**/ession(alert(1)); }', scope: self::SCOPE);

		$this->assertSame('', $result['css']);
		$this->assertSame(['expression()'], $result['removed']);
	}

	public function testALaunchPadResourceMayBeUsed(): void {
		$result = $this->sanitiser->sanitise(
			css: '.tile { background: url("/index.php/apps/launchpad/resource/logo.png") no-repeat; }',
			scope: self::SCOPE
		);

		$this->assertSame(
			".launchpad-workspace .tile {\n  background: url(\"/index.php/apps/launchpad/resource/logo.png\") no-repeat;\n}",
			$result['css']
		);
		$this->assertSame([], $result['removed']);
	}

	public function testAFontFaceIsDropped(): void {
		$result = $this->sanitiser->sanitise(
			css: '@font-face { font-family: x; src: url(https://evil.example/f.woff); } p { margin: 0 }',
			scope: self::SCOPE
		);

		$this->assertSame(".launchpad-workspace p {\n  margin: 0;\n}", $result['css']);
		$this->assertSame(['@font-face'], $result['removed']);
	}

	public function testAMediaGroupIsKeptWithItsRulesScoped(): void {
		$result = $this->sanitiser->sanitise(
			css: '@media (max-width: 600px) { .tile { padding: 4px } @import url(x.css); }',
			scope: self::SCOPE
		);

		$this->assertSame(
			"@media (max-width: 600px) {\n.launchpad-workspace .tile {\n  padding: 4px;\n}\n}",
			$result['css']
		);
		$this->assertSame(['@import'], $result['removed']);
	}

	public function testANestedRuleAndAnUnsafeSelectorAreDropped(): void {
		$result = $this->sanitiser->sanitise(
			css: '.a { .b { color: red } } .c\\,body { color: red } .d { color: blue }',
			scope: self::SCOPE
		);

		$this->assertSame(".launchpad-workspace .d {\n  color: blue;\n}", $result['css']);
		$this->assertSame(['nested rules', 'unsafe selector'], $result['removed']);
	}

	public function testAnUnclosedBlockIsDroppedNotPassedThrough(): void {
		$result = $this->sanitiser->sanitise(css: '.a { color: red } .b { color: "blue }', scope: self::SCOPE);

		$this->assertSame(".launchpad-workspace .a {\n  color: red;\n}", $result['css']);
		$this->assertSame(['unreadable CSS'], $result['removed']);
	}

	public function testMoreThanTwentyKilobytesIsRefused(): void {
		$this->sanitiser->sanitise(css: str_repeat('a', CustomCssSanitiser::MAX_BYTES), scope: self::SCOPE);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Custom CSS may be at most 20 KB.');
		$this->sanitiser->sanitise(css: str_repeat('a', (CustomCssSanitiser::MAX_BYTES + 1)), scope: self::SCOPE);
	}

	public function testCssThatOutgrowsTheCapWhenScopedIsRefused(): void {
		$css = str_repeat('a{b:c}', 3000);
		$this->assertLessThanOrEqual(CustomCssSanitiser::MAX_BYTES, strlen($css));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('also after scoping it');
		$this->sanitiser->sanitise(css: $css, scope: self::SCOPE);
	}

	public function testEmptyCssStaysEmpty(): void {
		$this->assertSame(['css' => '', 'removed' => []], $this->sanitiser->sanitise(css: "  /* only a comment */ \n", scope: self::SCOPE));
	}

	public function testADashboardScopeRefusesAnIdentifierThatCouldBreakOut(): void {
		$this->expectException(InvalidArgumentException::class);
		CustomCssSanitiser::dashboardScope(uuid: 'x"] , body');
	}
}
