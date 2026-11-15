<?php

/**
 * DisplayNamePageCollectorTest
 *
 * Unit tests for DisplayNamePageCollector: skipping disabled users when
 * asked, skipping the offset, and stopping when the page is full.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\DisplayNamePageCollector;
use OCA\LaunchPad\Service\PeoplePage;
use OCP\IUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DisplayNamePageCollector.
 */
#[CoversClass(DisplayNamePageCollector::class)]
#[UsesClass(PeoplePage::class)]
class DisplayNamePageCollectorTest extends TestCase {
	/**
	 * A user double with a uid and enabled flag.
	 *
	 * @param string $uid     The user id.
	 * @param bool   $enabled Whether the account is enabled.
	 *
	 * @return IUser
	 */
	private function user(string $uid, bool $enabled=true): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('isEnabled')->willReturn($enabled);

		return $user;
	}//end user()

	/**
	 * The uids of a page.
	 *
	 * @param IUser[] $page The page.
	 *
	 * @return string[]
	 */
	private static function uids(array $page): array {
		return array_map(static fn (IUser $user): string => $user->getUID(), $page);
	}//end uids()

	/**
	 * The offset is skipped and the page stops at the limit.
	 *
	 * @return void
	 */
	public function testOffsetIsSkippedAndPageStopsAtLimit(): void {
		$collector = new DisplayNamePageCollector(window: new PeoplePage(excludeDisabled: false, showBirthdays: false, limit: 2, offset: 1));

		$collector->takeFrom(users: [$this->user('a'), $this->user('b'), $this->user('c'), $this->user('d')]);

		$this->assertTrue($collector->isFull());
		$this->assertSame(['b', 'c'], self::uids($collector->page()));
	}//end testOffsetIsSkippedAndPageStopsAtLimit()

	/**
	 * The offset carries over between batches.
	 *
	 * @return void
	 */
	public function testOffsetAndPageSpanBatches(): void {
		$collector = new DisplayNamePageCollector(window: new PeoplePage(excludeDisabled: false, showBirthdays: false, limit: 3, offset: 3));

		$collector->takeFrom(users: [$this->user('a'), $this->user('b')]);
		$this->assertFalse($collector->isFull());
		$this->assertSame([], $collector->page());

		$collector->takeFrom(users: [$this->user('c'), $this->user('d'), $this->user('e')]);
		$this->assertFalse($collector->isFull());
		$this->assertSame(['d', 'e'], self::uids($collector->page()));
	}//end testOffsetAndPageSpanBatches()

	/**
	 * Disabled users are skipped, and do not count towards the offset,
	 * when the window excludes them.
	 *
	 * @return void
	 */
	public function testDisabledUsersAreSkippedWhenExcluded(): void {
		$collector = new DisplayNamePageCollector(window: new PeoplePage(excludeDisabled: true, showBirthdays: false, limit: 5, offset: 1));

		$collector->takeFrom(users: [$this->user('x', false), $this->user('a'), $this->user('y', false), $this->user('b'), $this->user('c')]);

		$this->assertSame(['b', 'c'], self::uids($collector->page()));
	}//end testDisabledUsersAreSkippedWhenExcluded()

	/**
	 * Disabled users are listed when the window keeps them.
	 *
	 * @return void
	 */
	public function testDisabledUsersAreKeptWhenNotExcluded(): void {
		$collector = new DisplayNamePageCollector(window: new PeoplePage(excludeDisabled: false, showBirthdays: false, limit: 5, offset: 0));

		$collector->takeFrom(users: [$this->user('x', false), $this->user('a')]);

		$this->assertSame(['x', 'a'], self::uids($collector->page()));
	}//end testDisabledUsersAreKeptWhenNotExcluded()

	/**
	 * Once full, a further batch adds nothing past the limit.
	 *
	 * @return void
	 */
	public function testFullPageTakesNoFurtherUsersFromTheBatch(): void {
		$collector = new DisplayNamePageCollector(window: new PeoplePage(excludeDisabled: false, showBirthdays: false, limit: 1, offset: 0));

		$collector->takeFrom(users: [$this->user('a'), $this->user('b')]);

		$this->assertSame(['a'], self::uids($collector->page()));
	}//end testFullPageTakesNoFurtherUsersFromTheBatch()
}//end class
