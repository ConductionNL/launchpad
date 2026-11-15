<?php

/**
 * ProfileFieldsListenerTest
 *
 * Pins which profile-field operations each user event triggers, and that a
 * failing sync is logged as a warning without breaking the sign-in, edit or
 * deletion that raised it.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Listener;

use OCA\LaunchPad\Listener\ProfileFieldsListener;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\Accounts\UserUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use OCP\User\Events\UserLoggedInEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for ProfileFieldsListener.
 *
 * @covers \OCA\LaunchPad\Listener\ProfileFieldsListener
 */
class ProfileFieldsListenerTest extends TestCase {

	/** @var ProfileFieldService&MockObject */
	private $profileFields;

	/** @var LoggerInterface&MockObject */
	private $logger;

	/** @var IUser&MockObject */
	private $user;

	private ProfileFieldsListener $listener;

	/**
	 * Fresh mocks per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->profileFields = $this->createMock(ProfileFieldService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('pieter');
		$this->listener = new ProfileFieldsListener(
			profileFields: $this->profileFields,
			logger: $this->logger,
		);
	}//end setUp()

	/**
	 * Sign-in syncs LDAP fields first, then mirrors the standard fields.
	 *
	 * @return void
	 */
	public function testSignInSyncsLdapThenMirrorsStandardFields(): void {
		$calls = [];
		$this->profileFields->expects($this->once())
			->method('syncLdapFields')
			->with($this->identicalTo($this->user))
			->willReturnCallback(static function () use (&$calls): int {
				$calls[] = 'ldap';
				return 2;
			});
		$this->profileFields->expects($this->once())
			->method('mirrorStandardFields')
			->with($this->identicalTo($this->user))
			->willReturnCallback(static function () use (&$calls): void {
				$calls[] = 'mirror';
			});
		$this->profileFields->expects($this->never())->method('deleteUser');
		$this->logger->expects($this->never())->method('warning');

		$this->listener->handle(new UserLoggedInEvent($this->user, 'pieter', null, false));

		$this->assertSame(['ldap', 'mirror'], $calls);
	}//end testSignInSyncsLdapThenMirrorsStandardFields()

	/**
	 * A profile edit mirrors the standard fields only.
	 *
	 * @return void
	 */
	public function testProfileEditOnlyMirrorsStandardFields(): void {
		$this->profileFields->expects($this->never())->method('syncLdapFields');
		$this->profileFields->expects($this->once())
			->method('mirrorStandardFields')
			->with($this->identicalTo($this->user));
		$this->profileFields->expects($this->never())->method('deleteUser');

		$this->listener->handle(new UserUpdatedEvent($this->user, ['displayname' => 'Pieter']));
	}//end testProfileEditOnlyMirrorsStandardFields()

	/**
	 * Deleting a user removes that user's stored field values by uid.
	 *
	 * @return void
	 */
	public function testUserDeletionDeletesTheUsersValues(): void {
		$this->profileFields->expects($this->never())->method('syncLdapFields');
		$this->profileFields->expects($this->never())->method('mirrorStandardFields');
		$this->profileFields->expects($this->once())
			->method('deleteUser')
			->with('pieter');

		$this->listener->handle(new UserDeletedEvent($this->user));
	}//end testUserDeletionDeletesTheUsersValues()

	/**
	 * A failure stops the remaining steps and is logged as a warning, never thrown.
	 *
	 * @return void
	 */
	public function testAFailingSyncIsLoggedAndSwallowed(): void {
		$this->profileFields->method('syncLdapFields')
			->willThrowException(new RuntimeException('ldap unreachable'));
		$this->profileFields->expects($this->never())->method('mirrorStandardFields');
		$this->logger->expects($this->once())
			->method('warning')
			->with('Profile field sync failed: ldap unreachable');

		$this->listener->handle(new UserLoggedInEvent($this->user, 'pieter', null, false));
	}//end testAFailingSyncIsLoggedAndSwallowed()

	/**
	 * Unrelated events touch nothing.
	 *
	 * @return void
	 */
	public function testOtherEventsAreIgnored(): void {
		$this->profileFields->expects($this->never())->method('syncLdapFields');
		$this->profileFields->expects($this->never())->method('mirrorStandardFields');
		$this->profileFields->expects($this->never())->method('deleteUser');
		$this->logger->expects($this->never())->method('warning');

		$this->listener->handle(new Event());
	}//end testOtherEventsAreIgnored()
}//end class
