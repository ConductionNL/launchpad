<?php

/**
 * LaunchPadAdminTest
 *
 * Pins Launchpad's entry under Nextcloud's administration settings: one line
 * and a link to the administration page (REQ-ADM-031), under the Launchpad
 * section.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Settings;

use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCA\LaunchPad\Settings\LaunchPadAdminSection;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for LaunchPadAdmin.
 *
 * @covers \OCA\LaunchPad\Settings\LaunchPadAdmin
 * @uses   \OCA\LaunchPad\Settings\LaunchPadAdminSection
 */
class LaunchPadAdminTest extends TestCase {

	/**
	 * The form with a URL generator that answers for the administration page.
	 *
	 * @return LaunchPadAdmin
	 */
	private function form(): LaunchPadAdmin {
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('linkToRoute')->willReturnCallback(
			static fn (string $route, array $args = []): string => '/index.php/' . $route . '/' . ($args['tab'] ?? '')
		);

		return new LaunchPadAdmin(urlGenerator: $urls);
	}//end form()

	/**
	 * The entry renders the short template with the link to the
	 * administration page on General, and provides no initial state.
	 *
	 * @return void
	 */
	public function testTheFormLinksToTheAdministrationPage(): void {
		$response = $this->form()->getForm();

		$this->assertSame(expected: 'launchpad', actual: $response->getApp());
		$this->assertSame(expected: 'settings/admin', actual: $response->getTemplateName());
		$this->assertSame(
			expected: ['adminPageUrl' => '/index.php/launchpad.adminPage.show/general'],
			actual: $response->getParams()
		);
	}//end testTheFormLinksToTheAdministrationPage()

	/**
	 * The template says where the administration is in one translated line
	 * and links to it.
	 *
	 * @return void
	 */
	public function testTheTemplateIsOneLineAndALink(): void {
		$template = (string) file_get_contents(dirname(__DIR__, 3) . '/templates/settings/admin.php');

		$this->assertStringContainsString(needle: "p(\$l->t('The Launchpad administration is on a page of its own.'))", haystack: $template);
		$this->assertStringContainsString(needle: 'href="<?php p($adminPageUrl); ?>"', haystack: $template);
		$this->assertStringContainsString(needle: "p(\$l->t('Open Launchpad administration'))", haystack: $template);
		$this->assertStringNotContainsString(needle: '<script', haystack: $template);
	}//end testTheTemplateIsOneLineAndALink()

	/**
	 * The form registers under the LaunchPad section, with no name of its own
	 * and no delegated app config.
	 *
	 * @return void
	 */
	public function testTheFormRegistersUnderTheLaunchPadSection(): void {
		$admin = $this->form();
		$section = new LaunchPadAdminSection(
			l: $this->createMock(originalClassName: IL10N::class),
			urlGenerator: $this->createMock(originalClassName: IURLGenerator::class)
		);

		$this->assertSame(expected: $section->getID(), actual: $admin->getSection());
		$this->assertSame(expected: 10, actual: $admin->getPriority());
		$this->assertNull(actual: $admin->getName());
		$this->assertSame(expected: [], actual: $admin->getAuthorizedAppConfig());
	}//end testTheFormRegistersUnderTheLaunchPadSection()
}//end class
