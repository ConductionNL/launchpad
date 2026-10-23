<?php

/**
 * ProfileFieldsControllerTest
 *
 * The profile field endpoints (REQ-PEX-001, REQ-PEX-002): a person reads and
 * saves only their own values; only an administrator reads or writes the
 * field definitions.
 *
 * @category  Test
 * @package   Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\ProfileFieldsController;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\LDAP\ILDAPProviderFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Unit\Support\InMemoryProfileValues;

class ProfileFieldsControllerTest extends TestCase {
	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	private InMemoryProfileValues $values;

	private function controller(string $uid, bool $admin): ProfileFieldsController {
		$settingMapper = $this->createMock(AdminSettingMapper::class);
		$settingMapper->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default
		);
		$settingMapper->method('setSetting')->willReturnCallback(
			function (string $key, mixed $value): AdminSetting {
				$this->settings[$key] = $value;
				return new AdminSetting();
			}
		);
		$this->values ??= new InMemoryProfileValues();

		$service = new ProfileFieldService(
			settingMapper: $settingMapper,
			valueMapper: $this->values,
			accountManager: $this->createMock(IAccountManager::class),
			adminTemplateService: $this->createMock(AdminTemplateService::class),
			ldapProviderFactory: $this->createMock(ILDAPProviderFactory::class),
			logger: $this->createMock(LoggerInterface::class),
		);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn($admin);

		return new ProfileFieldsController(
			request: $this->createMock(IRequest::class),
			profileFields: $service,
			actionAuth: $this->createMock(ActionAuthService::class),
			userSession: $session,
			groupManager: $groups,
		);
	}//end controller()

	public function testANonAdminCannotReadOrWriteDefinitions(): void {
		$controller = $this->controller(uid: 'sanne', admin: false);

		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->getDefinitions()->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->saveDefinitions(fields: [['key' => 'x', 'label' => 'X']])->getStatus());
		$this->assertSame([], $this->settings, 'a refused write must not store anything');
	}//end testANonAdminCannotReadOrWriteDefinitions()

	public function testAnAdminDefinesAndAPersonSavesOnlyTheirOwnValues(): void {
		$saved = $this->controller(uid: 'noor', admin: true)->saveDefinitions(
			fields: [['key' => 'expertise', 'label' => 'Expertise', 'type' => 'tags', 'searchable' => true]]
		);
		$this->assertSame(Http::STATUS_OK, $saved->getStatus());

		$pieter = $this->controller(uid: 'pieter', admin: false);
		$response = $pieter->saveOwn(values: ['expertise' => ['subsidies']]);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['subsidies'], $response->getData()['fields'][0]['values']);
		$this->assertSame('pieter', $this->values->rows[0]->getUserId(), 'the value must land on the caller');

		$sanne = $this->controller(uid: 'sanne', admin: false);
		$this->assertSame([], $sanne->getOwn()->getData()['fields'][0]['values'], 'Sanne must not read Pieter\'s values as her own');
	}//end testAnAdminDefinesAndAPersonSavesOnlyTheirOwnValues()

	public function testAnInvalidValueIsABadRequest(): void {
		$this->controller(uid: 'noor', admin: true)->saveDefinitions(fields: [['key' => 'expertise', 'label' => 'Expertise', 'type' => 'tags']]);

		$response = $this->controller(uid: 'pieter', admin: false)->saveOwn(values: ['expertise' => 'not a list']);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}//end testAnInvalidValueIsABadRequest()
}//end class
