<?php

/**
 * LaunchPadAdminTest
 *
 * Pins Launchpad's admin settings page in Nextcloud's settings framework
 * (REQ-ADM-032): both settings classes render the page through one form,
 * under the Launchpad section; the announcements class renders nothing for
 * a viewer who holds the whole page, so the page mounts once (REQ-ADM-034).
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

use OCA\LaunchPad\Settings\AdminFormState;
use OCA\LaunchPad\Settings\AdminPageAccess;
use OCA\LaunchPad\Settings\AdminSettingsForm;
use OCA\LaunchPad\Settings\AdminTabs;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCA\LaunchPad\Settings\LaunchPadAdminSection;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for LaunchPadAdmin, LaunchPadAnnouncementsAdmin and AdminSettingsForm.
 *
 * @covers \OCA\LaunchPad\Settings\LaunchPadAdmin
 * @covers \OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin
 * @covers \OCA\LaunchPad\Settings\AdminSettingsForm
 * @covers \OCA\LaunchPad\Settings\AdminTabs
 * @uses   \OCA\LaunchPad\Settings\LaunchPadAdminSection
 */
class LaunchPadAdminTest extends TestCase {

	/** @var AdminFormState&MockObject */
	private $formState;
	/** @var AdminPageAccess&MockObject */
	private $access;
	/** @var int How often the settings bundle was added. */
	private int $assets = 0;

	/**
	 * The form, with the viewer's tabs and a counter in place of the assets.
	 *
	 * @param list<string> $tabs     The tabs the viewer holds.
	 * @param bool         $wholePage Whether the viewer holds the whole page.
	 *
	 * @return AdminSettingsForm
	 */
	private function form(array $tabs, bool $wholePage): AdminSettingsForm {
		$this->formState = $this->createMock(originalClassName: AdminFormState::class);
		$this->access = $this->createMock(originalClassName: AdminPageAccess::class);
		$this->access->method('tabs')->willReturn($tabs);
		$this->access->method('allows')->willReturn($wholePage);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getAppVersion')->with('launchpad')->willReturn('1.2.3');
		$test = $this;

		return new class($this->formState, $this->access, $apps, $test) extends AdminSettingsForm {
			/**
			 * @param AdminFormState    $formState The state.
			 * @param AdminPageAccess   $access    The access.
			 * @param IAppManager       $apps      The app manager.
			 * @param LaunchPadAdminTest $test     The test, counting assets.
			 */
			public function __construct(AdminFormState $formState, AdminPageAccess $access, IAppManager $apps, private readonly LaunchPadAdminTest $test) {
				parent::__construct(formState: $formState, access: $access, appManager: $apps);
			}

			/**
			 * Count instead of loading the bundle.
			 *
			 * @return void
			 */
			protected function addAssets(): void {
				$this->test->countAssets();
			}
		};
	}//end form()

	/**
	 * Count one asset load.
	 *
	 * @return void
	 */
	public function countAssets(): void {
		$this->assets++;
	}//end countAssets()

	/**
	 * An administrator gets the page: the mount template, the bundle, and
	 * the state with every tab and the version.
	 *
	 * @return void
	 */
	public function testTheWholePageRendersTheSettingsTemplate(): void {
		$form = $this->form(tabs: AdminTabs::TABS, wholePage: true);
		$this->formState->expects($this->once())->method('provide')->with(AdminTabs::TABS, '1.2.3');

		$response = (new LaunchPadAdmin(form: $form))->getForm();

		$this->assertSame(expected: 'launchpad', actual: $response->getApp());
		$this->assertSame(expected: 'settings/admin', actual: $response->getTemplateName());
		$this->assertSame(expected: 1, actual: $this->assets);
	}//end testTheWholePageRendersTheSettingsTemplate()

	/**
	 * For a holder of the whole page the announcements class renders an
	 * empty form, so the page mounts once.
	 *
	 * @return void
	 */
	public function testTheAnnouncementsClassStaysEmptyBesideTheWholePage(): void {
		$form = $this->form(tabs: AdminTabs::TABS, wholePage: true);
		$this->formState->expects($this->never())->method('provide');

		$response = $this->announcements(form: $form)->getForm();

		$this->assertSame(expected: 'settings/empty', actual: $response->getTemplateName());
		$this->assertSame(expected: 0, actual: $this->assets);
	}//end testTheAnnouncementsClassStaysEmptyBesideTheWholePage()

	/**
	 * An announcements manager gets the page with the Announcements tab.
	 *
	 * @return void
	 */
	public function testAnAnnouncementsManagerGetsTheAnnouncementsTab(): void {
		$form = $this->form(tabs: ['announcements'], wholePage: false);
		$this->formState->expects($this->once())->method('provide')->with(['announcements'], '1.2.3');

		$response = $this->announcements(form: $form)->getForm();

		$this->assertSame(expected: 'settings/admin', actual: $response->getTemplateName());
		$this->assertSame(expected: 1, actual: $this->assets);
	}//end testAnAnnouncementsManagerGetsTheAnnouncementsTab()

	/**
	 * A viewer who holds no tab gets nothing of the page.
	 *
	 * @return void
	 */
	public function testAViewerWithoutATabGetsAnEmptyForm(): void {
		$form = $this->form(tabs: [], wholePage: false);
		$this->formState->expects($this->never())->method('provide');

		$this->assertInstanceOf(expected: TemplateResponse::class, actual: $form->render());
		$this->assertSame(expected: 'settings/empty', actual: $form->render()->getTemplateName());
		$this->assertSame(expected: 0, actual: $this->assets);
	}//end testAViewerWithoutATabGetsAnEmptyForm()

	/**
	 * The template is the mount point and nothing else; the empty template
	 * renders nothing.
	 *
	 * @return void
	 */
	public function testTheTemplatesAreAMountPointAndNothing(): void {
		$root = dirname(__DIR__, 3).'/templates/settings/';
		$template = (string) file_get_contents($root.'admin.php');
		$empty = (string) file_get_contents($root.'empty.php');

		$this->assertStringContainsString(needle: '<div id="launchpad-admin-settings"', haystack: $template);
		$this->assertStringNotContainsString(needle: 'href=', haystack: $template);
		$this->assertStringNotContainsString(needle: '<div', haystack: $empty);
	}//end testTheTemplatesAreAMountPointAndNothing()

	/**
	 * Both classes register under the LaunchPad section, the announcements
	 * class after the whole page and with a name of its own for the
	 * Administration privileges.
	 *
	 * @return void
	 */
	public function testBothClassesRegisterUnderTheLaunchPadSection(): void {
		$form = $this->form(tabs: [], wholePage: false);
		$admin = new LaunchPadAdmin(form: $form);
		$announcements = $this->announcements(form: $form);
		$section = new LaunchPadAdminSection(
			l: $this->createMock(originalClassName: IL10N::class),
			urlGenerator: $this->createMock(originalClassName: IURLGenerator::class)
		);

		$this->assertSame(expected: $section->getID(), actual: $admin->getSection());
		$this->assertSame(expected: $section->getID(), actual: $announcements->getSection());
		$this->assertSame(expected: 10, actual: $admin->getPriority());
		$this->assertGreaterThan(expected: $admin->getPriority(), actual: $announcements->getPriority());
		$this->assertNull(actual: $admin->getName());
		$this->assertSame(expected: 'Launchpad announcements', actual: $announcements->getName());
		$this->assertSame(expected: [], actual: $admin->getAuthorizedAppConfig());
		$this->assertSame(expected: [], actual: $announcements->getAuthorizedAppConfig());
	}//end testBothClassesRegisterUnderTheLaunchPadSection()

	/**
	 * Both classes are registered in info.xml.
	 *
	 * @return void
	 */
	public function testInfoXmlRegistersBothClasses(): void {
		$info = (string) file_get_contents(dirname(__DIR__, 3).'/appinfo/info.xml');

		$this->assertStringContainsString(needle: '<admin>OCA\LaunchPad\Settings\LaunchPadAdmin</admin>', haystack: $info);
		$this->assertStringContainsString(needle: '<admin>OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin</admin>', haystack: $info);
	}//end testInfoXmlRegistersBothClasses()

	/**
	 * The fragment a former address opens: a tab, a former tab as it is,
	 * anything else General.
	 *
	 * @return void
	 */
	public function testTheFragmentOfAFormerAddress(): void {
		$this->assertSame(expected: 'sharing', actual: AdminTabs::fragment(tab: 'sharing'));
		$this->assertSame(expected: 'embeds', actual: AdminTabs::fragment(tab: 'embeds'));
		$this->assertSame(expected: 'general', actual: AdminTabs::fragment(tab: 'nonsense'));
		$this->assertSame(expected: 'general', actual: AdminTabs::fragment(tab: ''));
		$this->assertCount(expectedCount: 13, haystack: AdminTabs::TABS);
		$this->assertSame(expected: 'announcements', actual: AdminTabs::TABS[1]);
	}//end testTheFragmentOfAFormerAddress()

	/**
	 * The announcements class over a form.
	 *
	 * @param AdminSettingsForm $form The form.
	 *
	 * @return LaunchPadAnnouncementsAdmin
	 */
	private function announcements(AdminSettingsForm $form): LaunchPadAnnouncementsAdmin {
		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnArgument(0);

		return new LaunchPadAnnouncementsAdmin(form: $form, access: $this->access, l: $l);
	}//end announcements()
}//end class
