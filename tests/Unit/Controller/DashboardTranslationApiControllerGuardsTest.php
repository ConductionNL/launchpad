<?php

/**
 * DashboardTranslationApiControllerGuardsTest
 *
 * Golden matrix of the caller, action and ownership guards in front of
 * every translation endpoint: who is refused with which status, which
 * action is asked for, and whether the service is reached at all.
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

use Exception;
use OCA\LaunchPad\Controller\ActionCaller;
use OCA\LaunchPad\Controller\DashboardTranslationApiController;
use OCA\LaunchPad\Controller\TranslationSources;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardTranslation;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DashboardTranslationService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Allowed and refused outcomes of every translation endpoint.
 */
class DashboardTranslationApiControllerGuardsTest extends TestCase {

	private const ENDPOINTS = ['list', 'create', 'createBlankLanguage', 'update', 'destroy', 'setPrimary', 'resolved'];

	private const SCENARIOS = ['anonymous', 'no session user', 'action denied', 'unknown dashboard', 'not the owner', 'owner'];

	/**
	 * Every endpoint × scenario gives the recorded status, action and service reach.
	 *
	 * @return void
	 */
	public function testGuardMatrix(): void {
		$actual = [];
		foreach (self::ENDPOINTS as $endpoint) {
			foreach (self::SCENARIOS as $scenario) {
				$actual[$endpoint.' / '.$scenario] = $this->callEndpoint($endpoint, $scenario);
			}
		}

		$expected = json_decode((string) file_get_contents(__DIR__.'/fixtures/translation-guards.json'), true);
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(__DIR__.'/fixtures/translation-guards.json', json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame($expected, $actual);
	}//end testGuardMatrix()

	/**
	 * A failed delete maps the service message to an error code.
	 *
	 * @return void
	 */
	public function testDestroyFailureCodes(): void {
		$codes = [];
		foreach ([DashboardTranslationService::ERR_LAST_VARIANT, DashboardTranslationService::ERR_DELETE_PRIMARY, 'something else'] as $message) {
			$response = $this->controller(userId: 'alice', sessionUser: true, failWith: new Exception($message))
				->destroy('uuid-a', 'nl');
			$codes[] = [$response->getStatus(), $response->getData()];
		}

		$this->assertSame(
			[
				[400, ['status' => 'error', 'error' => 'last_variant', 'message' => DashboardTranslationService::ERR_LAST_VARIANT]],
				[400, ['status' => 'error', 'error' => 'primary_variant', 'message' => DashboardTranslationService::ERR_DELETE_PRIMARY]],
				[400, ['status' => 'error', 'error' => 'invalid_state', 'message' => 'something else']],
			],
			$codes
		);
	}//end testDestroyFailureCodes()

	/**
	 * One endpoint call in one scenario.
	 *
	 * @param string $endpoint The controller method.
	 * @param string $scenario The caller situation.
	 *
	 * @return array<string, mixed> Status, body, asked action and service calls.
	 */
	private function callEndpoint(string $endpoint, string $scenario): array {
		$this->asked   = [];
		$this->reached = [];
		$controller    = $this->controller(
			userId: ($scenario === 'anonymous') ? null : (($scenario === 'not the owner') ? 'mallory' : 'alice'),
			sessionUser: ($scenario !== 'no session user'),
			denied: ($scenario === 'action denied'),
			missing: ($scenario === 'unknown dashboard')
		);

		$response = match ($endpoint) {
			'list' => $controller->list('uuid-a'),
			'create' => $controller->create('uuid-a', 'de', 'Name', null, null, null),
			'createBlankLanguage' => $controller->create('uuid-a', ' ', 'Name', null, null, null),
			'update' => $controller->update('uuid-a', 'nl', 'Name', null, null),
			'destroy' => $controller->destroy('uuid-a', 'nl'),
			'setPrimary' => $controller->setPrimary('uuid-a', 'nl'),
			'resolved' => $controller->resolved('uuid-a'),
		};

		return ['status' => $response->getStatus(), 'body' => $response->getData(), 'asked' => $this->asked, 'reached' => $this->reached];
	}//end callEndpoint()

	/**
	 * Actions asked of the action guard.
	 *
	 * @var string[]
	 */
	private array $asked = [];

	/**
	 * Service methods reached.
	 *
	 * @var string[]
	 */
	private array $reached = [];

	/**
	 * The controller wired for one scenario.
	 *
	 * @param string|null    $userId      The controller's user id.
	 * @param bool           $sessionUser Whether the session has a user.
	 * @param bool           $denied      Whether the action guard refuses.
	 * @param bool           $missing     Whether the dashboard is unknown.
	 * @param Exception|null $failWith    What every service write throws.
	 *
	 * @return DashboardTranslationApiController
	 */
	private function controller(?string $userId, bool $sessionUser, bool $denied = false, bool $missing = false, ?Exception $failWith = null): DashboardTranslationApiController {
		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($sessionUser === true) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn((string) $userId);
		}
		$session->method('getUser')->willReturn($user);

		$auth = $this->createMock(ActionAuthService::class);
		$auth->method('requireAction')->willReturnCallback(
			function (IUser $user, string $action) use ($denied): void {
				$this->asked[] = $action;
				if ($denied === true) {
					throw new OCSForbiddenException('denied');
				}
			}
		);

		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findByUuid')->willReturnCallback(
			static function (string $uuid) use ($missing): Dashboard {
				if ($missing === true) {
					throw new DoesNotExistException('nope');
				}
				$dashboard = new Dashboard();
				$dashboard->setId(7);
				$dashboard->setUuid($uuid);
				$dashboard->setUserId('alice');
				$dashboard->setName('Board');
				return $dashboard;
			}
		);

		$variant = new DashboardTranslation();
		$variant->setLanguageCode('nl');
		$service = $this->createMock(DashboardTranslationService::class);
		foreach (['listVariants', 'createVariant', 'updateVariant', 'deleteVariant', 'promoteVariantToPrimary'] as $method) {
			$service->method($method)->willReturnCallback(
				function () use ($method, $variant, $failWith) {
					$this->reached[] = $method;
					if ($failWith !== null) {
						throw $failWith;
					}
					return ($method === 'listVariants') ? [$variant] : $variant;
				}
			);
		}

		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturn('nl');
		$service->method('resolveForLocale')->willReturn(['translation' => $variant, 'isFallback' => false]);
		$service->method('listAvailableLanguages')->willReturn(['nl']);

		return new DashboardTranslationApiController(
			request: $request,
			translations: new TranslationSources(dashboardMapper: $mapper, translationService: $service),
			caller: new ActionCaller(actionAuth: $auth, userSession: $session),
			userId: $userId
		);
	}//end controller()
}//end class
