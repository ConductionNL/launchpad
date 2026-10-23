<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\AdminShippedTemplateController;
use OCA\LaunchPad\Exception\TemplateNotInstalledException;
use OCA\LaunchPad\Service\ShippedTemplateService;
use OCA\LaunchPad\Service\ShippedTemplateUpdateService;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * The shipped template endpoints (REQ-TMPL-018): who may call them and what
 * each outcome answers.
 */
class AdminShippedTemplateControllerTest extends TestCase {
	/** @var ShippedTemplateService&MockObject */
	private $service;

	/** @var ShippedTemplateUpdateService&MockObject */
	private $updates;

	/** @var IGroupManager&MockObject */
	private $groupManager;

	/** @var IUserSession&MockObject */
	private $userSession;

	private AdminShippedTemplateController $controller;

	protected function setUp(): void {
		$this->service = $this->createMock(ShippedTemplateService::class);
		$this->updates = $this->createMock(ShippedTemplateUpdateService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->controller = new AdminShippedTemplateController(
			request: $this->createMock(IRequest::class),
			templates: $this->service,
			updates: $this->updates,
			userSession: $this->userSession,
			groupManager: $this->groupManager,
			logger: new NullLogger(),
		);
	}

	private function loginAs(string $uid, bool $admin): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
		$this->groupManager->method('isAdmin')->with($uid)->willReturn($admin);
	}

	public function testNobodySignedInGets401AndNothingIsInstalled(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->service->expects(self::never())->method('install');
		$this->service->expects(self::never())->method('listTemplates');

		self::assertSame(Http::STATUS_UNAUTHORIZED, $this->controller->index()->getStatus());
		self::assertSame(Http::STATUS_UNAUTHORIZED, $this->controller->install(id: 'mijn-werkdag')->getStatus());
	}

	public function testANonAdminGets403AndNothingIsInstalled(): void {
		$this->loginAs('bob', false);
		$this->service->expects(self::never())->method('install');
		$this->service->expects(self::never())->method('listTemplates');

		self::assertSame(Http::STATUS_FORBIDDEN, $this->controller->index()->getStatus());
		self::assertSame(Http::STATUS_FORBIDDEN, $this->controller->install(id: 'mijn-werkdag')->getStatus());
	}

	public function testAnAdminGetsTheListing(): void {
		$this->loginAs('alice', true);
		$this->service->method('listTemplates')->willReturn([['id' => 'mijn-werkdag']]);

		$response = $this->controller->index();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame([['id' => 'mijn-werkdag']], $response->getData());
	}

	public function testInstallAnswers201ThenThe200OfASecondRun(): void {
		$this->loginAs('alice', true);
		$this->service->expects(self::exactly(2))->method('install')
			->with('mijn-werkdag', [], false, false, 'alice')
			->willReturnOnConsecutiveCalls(
				['uuid' => 'u1', 'alreadyInstalled' => false],
				['uuid' => 'u1', 'alreadyInstalled' => true],
			);

		$first = $this->controller->install(id: 'mijn-werkdag');
		$second = $this->controller->install(id: 'mijn-werkdag');

		self::assertSame(Http::STATUS_CREATED, $first->getStatus());
		self::assertSame('u1', $first->getData()['uuid']);
		self::assertSame(Http::STATUS_OK, $second->getStatus());
	}

	public function testAnUnknownTemplateIs404AndAFailureIs500WithoutTheReason(): void {
		$this->loginAs('alice', true);
		$this->service->method('install')->willReturnCallback(
			static function (string $templateId): array {
				if ($templateId === 'nope') {
					throw new InvalidArgumentException('Unknown template: nope');
				}
				throw new RuntimeException('SQLSTATE secret detail');
			}
		);

		self::assertSame(Http::STATUS_NOT_FOUND, $this->controller->install(id: 'nope')->getStatus());
		$failed = $this->controller->install(id: 'mijn-werkdag');
		self::assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $failed->getStatus());
		self::assertSame(['error' => 'Template installation failed'], $failed->getData());
	}

	/**
	 * REQ-TMPL-020: only an administrator updates, and a dry run is passed on.
	 */
	public function testOnlyAnAdminUpdatesAndADryRunIsPassedOn(): void {
		$this->loginAs('alice', true);
		$this->updates->expects(self::exactly(2))->method('update')
			->willReturnCallback(
				static fn (string $templateId, bool $dryRun, string $userId): array => [
					'templateId' => $templateId,
					'dryRun' => $dryRun,
					'by' => $userId,
				]
			);

		$dry = $this->controller->update(id: 'mijn-werkdag', dryRun: true);
		$real = $this->controller->update(id: 'mijn-werkdag');

		self::assertSame(Http::STATUS_OK, $dry->getStatus());
		self::assertSame(['templateId' => 'mijn-werkdag', 'dryRun' => true, 'by' => 'alice'], $dry->getData());
		self::assertFalse($real->getData()['dryRun']);
	}

	public function testANonAdminCannotUpdate(): void {
		$this->loginAs('bob', false);
		$this->updates->expects(self::never())->method('update');

		self::assertSame(Http::STATUS_FORBIDDEN, $this->controller->update(id: 'mijn-werkdag')->getStatus());
	}

	public function testUpdateAnswers404409And500(): void {
		$this->loginAs('alice', true);
		$this->updates->method('update')->willReturnCallback(
			static function (string $templateId): array {
				if ($templateId === 'nope') {
					throw new InvalidArgumentException('Unknown template: nope');
				}
				if ($templateId === 'not-installed') {
					throw new TemplateNotInstalledException('not installed');
				}
				throw new RuntimeException('SQLSTATE secret detail');
			}
		);

		self::assertSame(Http::STATUS_NOT_FOUND, $this->controller->update(id: 'nope')->getStatus());
		self::assertSame(Http::STATUS_CONFLICT, $this->controller->update(id: 'not-installed')->getStatus());
		$failed = $this->controller->update(id: 'mijn-werkdag');
		self::assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $failed->getStatus());
		self::assertSame(['error' => 'Template update failed'], $failed->getData());
	}
}
