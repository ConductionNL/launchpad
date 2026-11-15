<?php

/**
 * TenantThemeService unit tests (REQ-EMB-005, REQ-EMB-010 10.2).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
 */

declare(strict_types=1);

namespace Unit\Service\Embed;

use OCA\LaunchPad\Entity\TenantTheme;
use OCA\LaunchPad\Service\Embed\EmbedObjectStore;
use OCA\LaunchPad\Service\Embed\TenantThemeService;
use OCA\LaunchPad\Service\Embed\TenantThemeValidator;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Unit\Service\Embed\Support\InMemoryObjectService;

#[CoversClass(TenantThemeService::class)]
#[CoversClass(TenantTheme::class)]
#[UsesClass(EmbedObjectStore::class)]
#[UsesClass(TenantThemeValidator::class)]
class TenantThemeServiceTest extends TestCase {

	private const ZEIST = [
		'name'         => 'Gemeente Zeist',
		'cssVariables' => ['--c-primary' => '#21468B', '--c-surface' => '#FFFFFF', '--c-text' => '#1a1a1a'],
		'logoUrl'      => 'https://www.zeist.nl/logo.svg',
		'customCss'    => ':root { --spacing: 8px; }',
	];

	private InMemoryObjectService $objects;

	private function service(): TenantThemeService {
		$this->objects ??= new InMemoryObjectService();
		$container       = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($this->objects);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn((int)strtotime('2026-05-22T14:30:00Z'));

		return new TenantThemeService(new EmbedObjectStore($container), new TenantThemeValidator(), $time);
	}//end service()

	/**
	 * A valid theme is stored with its creator and renders as one :root block.
	 *
	 * @return void
	 */
	public function testAValidThemeIsStoredAndRendered(): void {
		$service = $this->service();
		$theme   = $service->createTheme(input: self::ZEIST, userId: 'admin');

		$this->assertSame(['obj-1', 'Gemeente Zeist', 'admin', '2026-05-22T14:30:00Z'], [$theme['id'], $theme['name'], $theme['createdBy'], $theme['createdAt']]);
		$this->assertSame(
			":root {\n  --c-primary: #21468B;\n  --c-surface: #FFFFFF;\n  --c-text: #1a1a1a;\n}\n:root { --spacing: 8px; }",
			$service->rootCss(themeId: 'obj-1')
		);
		$this->assertSame('', $service->rootCss(themeId: null));
		$this->assertSame('', $service->rootCss(themeId: 'gone'));
	}//end testAValidThemeIsStoredAndRendered()

	/**
	 * A non-:root selector is refused with the 5.3 body, and nothing is stored.
	 *
	 * @return void
	 */
	public function testANonRootSelectorIsRefused(): void {
		$refusal = $this->service()->createTheme(input: ['customCss' => '.widget-title { color: red; }'] + self::ZEIST, userId: 'admin');

		$this->assertSame(['error', 'invalid_customCss', ['customCss' => 'non_root_selector']], [$refusal['status'], $refusal['error'], $refusal['fieldErrors']]);
		$this->assertStringStartsWith("CSS rules must be scoped to :root only. Found selector '.widget-title'", $refusal['message']);
		$this->assertSame([], $this->objects->objects);
	}//end testANonRootSelectorIsRefused()

	/**
	 * Primary or text colours under 4.5:1 on the surface are refused, the
	 * ratio rounded down.
	 *
	 * @return void
	 */
	public function testInsufficientContrastIsRefused(): void {
		$service = $this->service();
		$primary = $service->createTheme(input: ['cssVariables' => ['--c-primary' => '#0099CC', '--c-surface' => '#FFFFFF']] + self::ZEIST, userId: 'admin');
		$this->assertSame(
			[
				'status'      => 'error',
				'error'       => 'insufficient_contrast',
				'message'     => 'Primary colour #0099CC against surface #FFFFFF has contrast ratio 3.2:1. WCAG AA requires 4.5:1 for text.',
				'fieldErrors' => ['cssVariables.--c-primary' => 'insufficient_contrast'],
			],
			$primary
		);

		$text = $service->createTheme(input: ['cssVariables' => ['--c-primary' => '#21468B', '--c-surface' => '#FFFFFF', '--c-text' => '#999999']] + self::ZEIST, userId: 'admin');
		$this->assertSame(['cssVariables.--c-text' => 'insufficient_contrast'], $text['fieldErrors']);
		$this->assertSame([], $this->objects->objects);
	}//end testInsufficientContrastIsRefused()

	/**
	 * Bad names, values, logos and missing names are refused.
	 *
	 * @return void
	 */
	public function testEveryBadFieldIsRefused(): void {
		$cases = [
			'invalid_name'         => ['name' => ''],
			'invalid_cssVariables' => ['cssVariables' => ['--c-primary' => 'red; } body { display:none']],
			'invalid_logoUrl'      => ['logoUrl' => 'javascript:alert(1)'],
		];
		foreach ($cases as $error => $change) {
			$this->assertSame($error, $this->service()->createTheme(input: $change + self::ZEIST, userId: 'admin')['error'], $error);
		}

		$this->assertSame(['cssVariables.color' => 'invalid_cssVariables'], $this->service()->createTheme(input: ['cssVariables' => ['color' => 'red']] + self::ZEIST, userId: 'a')['fieldErrors']);
	}//end testEveryBadFieldIsRefused()

	/**
	 * An update keeps the id and creator; the list counts the tokens using a theme.
	 *
	 * @return void
	 */
	public function testUpdateAndListCountTokens(): void {
		$service = $this->service();
		$service->createTheme(input: self::ZEIST, userId: 'admin');
		$this->objects->objects['launchpad/embed_token'] = ['t1' => ['tenantThemeId' => 'obj-1'], 't2' => ['tenantThemeId' => 'obj-1'], 't3' => []];

		$updated = $service->updateTheme(id: 'obj-1', input: ['name' => 'Zeist (nieuw)'] + self::ZEIST);
		$this->assertSame(['obj-1', 'Zeist (nieuw)', 'admin'], [$updated['id'], $updated['name'], $updated['createdBy']]);
		$this->assertNull($service->updateTheme(id: 'gone', input: self::ZEIST));
		$this->assertSame('invalid_name', $service->updateTheme(id: 'obj-1', input: ['name' => ' '] + self::ZEIST)['error']);

		$list = $service->listThemes();
		$this->assertSame([['obj-1', 2]], array_map(static fn (array $t): array => [$t['id'], $t['tokenCount']], $list));
	}//end testUpdateAndListCountTokens()
}//end class
