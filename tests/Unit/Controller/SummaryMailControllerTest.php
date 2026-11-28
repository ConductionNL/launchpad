<?php

/**
 * SummaryMailController and SummaryMailPreference Test
 *
 * Personal-settings REQ-PSET-002: the summary email is off until the person
 * changes it, only off, daily and weekly are stored, and the answer says
 * whether the person has an email address.
 *
 * @category Test
 * @package  Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-25-personal-settings-dialog/specs/personal-settings/spec.md
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\SummaryMailController;
use OCA\LaunchPad\Service\SummaryMailPreference;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the summary email choice.
 */
class SummaryMailControllerTest extends TestCase {

	/**
	 * Stored user values, keyed "user|app|key".
	 *
	 * @var array<string, string>
	 */
	private array $values = [];

	/**
	 * Start every test with nothing stored.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->values = [];
	}//end setUp()

	/**
	 * Sanne never chose: off.
	 *
	 * @return void
	 */
	public function testItIsOffUntilThePersonChangesIt(): void {
		$data = $this->controller(uid: 'sanne')->show()->getData();

		$this->assertSame(expected: ['frequency' => 'off', 'hasEmail' => true], actual: $data);
	}//end testItIsOffUntilThePersonChangesIt()

	/**
	 * Pieter's Daily is stored for him and nobody else.
	 *
	 * @return void
	 */
	public function testDailyIsStoredForTheCallerOnly(): void {
		$response = $this->controller(uid: 'pieter')->update(frequency: 'daily');

		$this->assertSame(expected: 'daily', actual: $response->getData()['frequency']);
		$this->assertSame(expected: ['pieter|launchpad|summary_mail_frequency' => 'daily'], actual: $this->values);
		$this->assertSame(expected: 'daily', actual: $this->controller(uid: 'pieter')->show()->getData()['frequency']);
		$this->assertSame(expected: 'off', actual: $this->controller(uid: 'sanne')->show()->getData()['frequency']);
	}//end testDailyIsStoredForTheCallerOnly()

	/**
	 * Only the three frequencies are accepted.
	 *
	 * @return void
	 */
	public function testAnUnknownFrequencyIsRefusedAndNothingStored(): void {
		$this->assertSame(expected: 400, actual: $this->controller(uid: 'pieter')->update(frequency: 'hourly')->getStatus());
		$this->assertSame(expected: [], actual: $this->values);
	}//end testAnUnknownFrequencyIsRefusedAndNothingStored()

	/**
	 * A stored value that is not one of the three reads as off.
	 *
	 * @return void
	 */
	public function testAStoredValueThatIsNotOneOfTheThreeReadsAsOff(): void {
		$this->values['pieter|launchpad|summary_mail_frequency'] = 'monthly';

		$this->assertSame(expected: 'off', actual: $this->controller(uid: 'pieter')->show()->getData()['frequency']);
	}//end testAStoredValueThatIsNotOneOfTheThreeReadsAsOff()

	/**
	 * The preference refuses an unknown frequency itself too.
	 *
	 * @return void
	 */
	public function testThePreferenceRefusesAnUnknownFrequency(): void {
		$this->expectException(exception: \InvalidArgumentException::class);
		$this->preference()->setFrequency(userId: 'pieter', frequency: 'hourly');
	}//end testThePreferenceRefusesAnUnknownFrequency()

	/**
	 * Without an email address the answer says so.
	 *
	 * @return void
	 */
	public function testAPersonWithoutEmailIsToldSo(): void {
		$this->assertFalse(condition: $this->controller(uid: 'pieter', email: '')->show()->getData()['hasEmail']);
	}//end testAPersonWithoutEmailIsToldSo()

	/**
	 * Nobody signed in: 401, nothing stored.
	 *
	 * @return void
	 */
	public function testSignedOutGetsNothing(): void {
		$this->assertSame(expected: 401, actual: $this->controller(uid: null)->show()->getStatus());
		$this->assertSame(expected: 401, actual: $this->controller(uid: null)->update(frequency: 'daily')->getStatus());
		$this->assertSame(expected: [], actual: $this->values);
	}//end testSignedOutGetsNothing()

	/**
	 * The preference over an in-memory user config.
	 *
	 * @return SummaryMailPreference
	 */
	private function preference(): SummaryMailPreference {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $user, string $app, string $key, $default='') => ($this->values["$user|$app|$key"] ?? $default)
		);
		$config->method('setUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, $value): void {
				$this->values["$user|$app|$key"] = (string)$value;
			}
		);

		return new SummaryMailPreference(config: $config);
	}//end preference()

	/**
	 * The controller for a caller.
	 *
	 * @param string|null $uid   The caller, or null when signed out.
	 * @param string      $email The caller's email address.
	 *
	 * @return SummaryMailController
	 */
	private function controller(?string $uid, string $email='pieter@zuiddrecht.example'): SummaryMailController {
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
			$user->method('getEMailAddress')->willReturn($email);
		}

		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new SummaryMailController(
			request: $this->createMock(originalClassName: IRequest::class),
			userSession: $session,
			preference: $this->preference()
		);
	}//end controller()
}//end class
