<?php

/**
 * Translation variant create golden test
 *
 * Pins the variant row that the create endpoint writes through a real
 * DashboardTranslationService, for explicit fields, seeds and refusals,
 * against a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\TranslationSources;
use OCA\LaunchPad\Controller\ActionCaller;
use OCA\LaunchPad\Controller\DashboardTranslationApiController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardTranslation;
use OCA\LaunchPad\Db\DashboardTranslationMapper;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DashboardTranslationService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * The create endpoint's variant rows, case by case.
 *
 * @covers \OCA\LaunchPad\Controller\DashboardTranslationApiController
 * @covers \OCA\LaunchPad\Service\DashboardTranslationService
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Controller\RespondsWithErrors
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\DashboardTranslation
 * @uses   \OCA\LaunchPad\Db\DashboardTranslationMapper
 * @uses   \OCA\LaunchPad\Controller\ActionCaller
 * @uses   \OCA\LaunchPad\Controller\TranslationSources
 * @uses   \OCA\LaunchPad\Controller\TranslationVariantDraft
 */
class DashboardTranslationCreateGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/dashboard-translation-create.json';

	/**
	 * A stored variant.
	 *
	 * @param string $language The language code.
	 * @param string $suffix   A marker for its texts.
	 *
	 * @return DashboardTranslation
	 */
	private static function variant(string $language, string $suffix): DashboardTranslation {
		$variant = new DashboardTranslation();
		$variant->setLanguageCode($language);
		$variant->setName('Name '.$suffix);
		$variant->setDescription('Desc '.$suffix);
		$variant->setWidgetTreeJson('{"t":"'.$suffix.'"}');
		return $variant;
	}//end variant()

	/**
	 * Every case writes the recorded row or answers the recorded refusal.
	 *
	 * @return void
	 */
	public function testRowsMatchTheRecordedFixture(): void {
		$cases = [
			'explicit'           => ['languageCode' => 'nl_NL', 'name' => 'N', 'description' => 'D', 'widgetTreeJson' => '{}'],
			'seed primary'       => ['languageCode' => 'de'],
			'seed named primary' => ['languageCode' => 'de', 'copyFrom' => 'PRIMARY'],
			'seed empty'         => ['languageCode' => 'de', 'copyFrom' => ''],
			'seed language'      => ['languageCode' => 'fr', 'copyFrom' => 'en'],
			'seed missing'       => ['languageCode' => 'fr', 'copyFrom' => 'xx'],
			'partly explicit'    => ['languageCode' => 'fr', 'name' => 'Own', 'copyFrom' => 'en'],
			'empty name'         => ['languageCode' => 'fr', 'name' => ''],
			'exists'             => ['languageCode' => 'en'],
			'bad language'       => ['languageCode' => '__'],
			'no language'        => [],
		];

		$actual = [];
		foreach ($cases as $case => $args) {
			$rows   = [];
			$mapper = $this->createMock(DashboardTranslationMapper::class);
			$mapper->method('findPrimaryByDashboardUuid')->willReturn(self::variant(language: 'en', suffix: 'primary'));
			$mapper->method('findByDashboardUuidAndLanguage')->willReturnCallback(
				static fn (string $dashboardUuid, string $languageCode): ?DashboardTranslation => $languageCode === 'en' ? self::variant(language: 'en', suffix: 'en') : null
			);
			$mapper->method('insert')->willReturnCallback(
				static function (DashboardTranslation $t) use (&$rows): DashboardTranslation {
					$rows[] = [$t->getDashboardUuid(), $t->getLanguageCode(), $t->getName(), $t->getDescription(), $t->getWidgetTreeJson(), $t->getIsPrimary(), $t->getCreatedAt() === $t->getUpdatedAt()];
					return $t;
				}
			);

			$dashboard = new Dashboard();
			$dashboard->setUserId('alice');
			$dashboards = $this->createMock(DashboardMapper::class);
			$dashboards->method('findByUuid')->willReturn($dashboard);
			$user = $this->createMock(IUser::class);
			$session = $this->createMock(IUserSession::class);
			$session->method('getUser')->willReturn($user);

			$controller = new DashboardTranslationApiController(
				request: $this->createMock(IRequest::class),
				translations: new TranslationSources(
					dashboardMapper: $dashboards,
					translationService: new DashboardTranslationService(
						translationMapper: $mapper,
						db: $this->createMock(IDBConnection::class),
						config: $this->createMock(IConfig::class),
					)
				),
				caller: new ActionCaller(
					actionAuth: $this->createMock(ActionAuthService::class),
					userSession: $session
				),
				userId: 'alice'
			);

			$response      = $controller->create('uuid-1', ...$args);
			$actual[$case] = ['status' => $response->getStatus(), 'rows' => $rows, 'error' => $response->getData()['message'] ?? $response->getData()['error'] ?? null];
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			$lines = [];
			foreach ($actual as $case => $row) {
				$lines[] = json_encode($case).': '.json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			}

			file_put_contents(self::FIXTURE, "{\n".implode(",\n", $lines)."\n}\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_encode(json_decode((string) file_get_contents(self::FIXTURE))), json_encode($actual));
	}//end testRowsMatchTheRecordedFixture()
}//end class
