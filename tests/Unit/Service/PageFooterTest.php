<?php

/**
 * PageFooterTest
 *
 * The footer the workspace page shows and the viewer's footer language
 * (footer-customization REQ-FTR-004, REQ-FTR-006, REQ-FTR-007).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
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

namespace OCA\LaunchPad\Tests\Unit\Service;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardTranslation;
use OCA\LaunchPad\Db\DashboardTranslationMapper;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\PageFooter;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

/**
 * Tests for {@see PageFooter}.
 */
class PageFooterTest extends TestCase {
	/**
	 * A page footer over a global footer that is on with the given HTML, and
	 * a dashboard whose primary language is the given one.
	 *
	 * @param string|null $primary The dashboard's primary language, or null.
	 *
	 * @return PageFooter
	 */
	private function pageFooter(?string $primary): PageFooter {
		$settings = $this->createMock(originalClassName: AdminSettingMapper::class);
		$settings->method('getValue')->willReturnCallback(
			static fn (string $key, mixed $default): mixed => match ($key) {
				AdminSetting::KEY_FOOTER_ENABLED => true,
				AdminSetting::KEY_FOOTER_HTML => '<p>Global</p>',
				default => $default,
			}
		);

		$translations = $this->createMock(originalClassName: DashboardTranslationMapper::class);
		$variant      = null;
		if ($primary !== null) {
			$variant = new DashboardTranslation();
			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$variant->setLanguageCode($primary);
		}

		$translations->method('findPrimaryByDashboardUuid')->willReturn($variant);

		$user  = $this->createMock(originalClassName: IUser::class);
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturnMap([['jan', $user], ['', null]]);
		$l10n = $this->createMock(originalClassName: IFactory::class);
		$l10n->method('getUserLanguage')->willReturn('nl');

		return new PageFooter(
			footerService: new FooterService(settingMapper: $settings),
			translationMapper: $translations,
			l10nFactory: $l10n,
			userManager: $users,
		);
	}//end pageFooter()

	/**
	 * An inherit dashboard shows the global footer with its primary language.
	 *
	 * @return void
	 */
	public function testTheActiveDashboardsFooterCarriesItsPrimaryLanguage(): void {
		$dashboard = new Dashboard();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setUuid('d-1');

		$footer = $this->pageFooter(primary: 'fr')->footerFor(dashboard: $dashboard);

		$this->assertSame(expected: '<p>Global</p>', actual: $footer['html']);
		$this->assertSame(expected: 'fr', actual: $footer['primaryLanguage']);
		$this->assertArrayNotHasKey(key: 'primaryLanguage', array: $this->pageFooter(primary: null)->footerFor(dashboard: $dashboard));
	}//end testTheActiveDashboardsFooterCarriesItsPrimaryLanguage()

	/**
	 * A hidden dashboard shows no footer; no dashboard shows the global one.
	 *
	 * @return void
	 */
	public function testAHiddenDashboardShowsNoneAndNoDashboardShowsTheGlobalOne(): void {
		$hidden = new Dashboard();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$hidden->setDashboardFooterMode(Dashboard::FOOTER_MODE_HIDDEN);

		$this->assertNull(actual: $this->pageFooter(primary: 'en')->footerFor(dashboard: $hidden));
		$this->assertSame(expected: '<p>Global</p>', actual: $this->pageFooter(primary: null)->footerFor(dashboard: null)['html']);
	}//end testAHiddenDashboardShowsNoneAndNoDashboardShowsTheGlobalOne()

	/**
	 * The viewer's language is their Nextcloud language; nobody gets `en`.
	 *
	 * @return void
	 */
	public function testTheViewerLocaleIsTheirNextcloudLanguage(): void {
		$this->assertSame(expected: 'nl', actual: $this->pageFooter(primary: null)->viewerLocale(userId: 'jan'));
		$this->assertSame(expected: 'en', actual: $this->pageFooter(primary: null)->viewerLocale(userId: ''));
	}//end testTheViewerLocaleIsTheirNextcloudLanguage()
}//end class
