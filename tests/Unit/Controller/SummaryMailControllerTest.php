<?php

/**
 * SummaryMailController and SummaryMailPreference Test
 *
 * personal-settings REQ-PSET-002: the summary email is off until the person
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
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
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

class SummaryMailControllerTest extends TestCase {
	/**
	 * Stored user values, keyed "user|app|key".
	 *
	 * @var array<string, string>
	 */
	private array $values = [];

	protected function setUp(): void {
		parent::setUp();
		$this->values = [];
	}

	private function preference(): SummaryMailPreference {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $user, string $app, string $key, $default = '') => $this->values["$user|$app|$key"] ?? $default
		);
		$config->method('setUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, $value): void {
				$this->values["$user|$app|$key"] = (string)$value;
			}
		);
		return new SummaryMailPreference($config);
	}

	private function controller(?string $uid, string $email = 'pieter@zuiddrecht.example'): SummaryMailController {
		$session = $this->createMock(IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			$user->method('getEMailAddress')->willReturn($email === '' ? null : $email);
		}
		$session->method('getUser')->willReturn($user);

		return new SummaryMailController($this->createMock(IRequest::class), $session, $this->preference());
	}

	public function testItIsOffUntilThePersonChangesIt(): void {
		$data = $this->controller('sanne')->show()->getData();

		$this->assertSame(['frequency' => 'off', 'hasEmail' => true], $data);
	}

	public function testDailyIsStoredForTheCallerOnly(): void {
		$response = $this->controller('pieter')->update('daily');

		$this->assertSame('daily', $response->getData()['frequency']);
		$this->assertSame(['pieter|launchpad|summary_mail_frequency' => 'daily'], $this->values);
		$this->assertSame('daily', $this->controller('pieter')->show()->getData()['frequency']);
		$this->assertSame('off', $this->controller('sanne')->show()->getData()['frequency']);
	}

	public function testAnUnknownFrequencyIsRefusedAndNothingStored(): void {
		$this->assertSame(400, $this->controller('pieter')->update('hourly')->getStatus());
		$this->assertSame([], $this->values);
	}

	public function testAStoredValueThatIsNotOneOfTheThreeReadsAsOff(): void {
		$this->values['pieter|launchpad|summary_mail_frequency'] = 'monthly';

		$this->assertSame('off', $this->controller('pieter')->show()->getData()['frequency']);
	}

	public function testAPersonWithoutEmailIsToldSo(): void {
		$this->assertFalse($this->controller('pieter', '')->show()->getData()['hasEmail']);
	}

	public function testSignedOutGetsNothing(): void {
		$this->assertSame(401, $this->controller(null)->show()->getStatus());
		$this->assertSame(401, $this->controller(null)->update('daily')->getStatus());
		$this->assertSame([], $this->values);
	}
}
