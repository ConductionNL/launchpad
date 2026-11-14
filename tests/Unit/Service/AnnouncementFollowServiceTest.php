<?php

/**
 * AnnouncementFollowServiceTest
 *
 * Following and unfollowing categories (engagement-announcements task 3,
 * REQ-ANN-003).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\AnnouncementFollow;
use OCA\LaunchPad\Db\AnnouncementFollowMapper;
use OCA\LaunchPad\Service\AnnouncementFollowService;
use OCA\LaunchPad\Service\AnnouncementInput;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementFollowService.
 *
 * @covers \OCA\LaunchPad\Service\AnnouncementFollowService
 * @uses   \OCA\LaunchPad\Service\AnnouncementInput
 * @uses   \OCA\LaunchPad\Db\AnnouncementFollow
 */
class AnnouncementFollowServiceTest extends TestCase {

	/**
	 * The stored follows.
	 *
	 * @var list<AnnouncementFollow>
	 */
	private array $rows = [];

	/**
	 * The service over an in-memory mapper.
	 *
	 * @return AnnouncementFollowService
	 */
	private function service(): AnnouncementFollowService {
		$mapper = $this->createMock(AnnouncementFollowMapper::class);
		$mapper->method('insert')->willReturnCallback(function (AnnouncementFollow $entity): AnnouncementFollow {
			$this->rows[] = $entity;
			return $entity;
		});
		$mapper->method('findByUser')->willReturnCallback(fn (string $userId): array => array_values(array_filter(
			$this->rows,
			static fn (AnnouncementFollow $row): bool => $row->getUserId() === $userId
		)));
		$mapper->method('unfollow')->willReturnCallback(function (string $userId, string $category): void {
			$this->rows = array_values(array_filter(
				$this->rows,
				static fn (AnnouncementFollow $row): bool => ($row->getUserId() === $userId && $row->getCategory() === $category) === false
			));
		});

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturnCallback(static fn (): DateTime => new DateTime('2026-11-13T18:00:00+00:00'));

		return new AnnouncementFollowService($mapper, new AnnouncementInput(), $time);
	}//end service()

	/**
	 * Following twice keeps one follow, trimmed; unfollowing removes it; other readers are untouched.
	 *
	 * @return void
	 */
	public function testFollowAndUnfollowACategory(): void {
		$service = $this->service();

		$this->assertSame(['Privacy'], $service->follow('pieter', ' Privacy '));
		$this->assertSame(['Privacy'], $service->follow('pieter', 'Privacy'));
		$this->assertSame(['Privacy', 'Facilitair'], $service->follow('pieter', 'Facilitair'));
		$this->assertCount(2, $this->rows);
		$this->assertSame('2026-11-13T18:00:00+00:00', $this->rows[0]->jsonSerialize()['createdAt']);
		$this->assertSame([], $service->followedCategories('sanne'));
		$this->assertSame(['Facilitair'], $service->unfollow('pieter', 'Privacy'));
	}//end testFollowAndUnfollowACategory()

	/**
	 * An empty or too long category is refused.
	 *
	 * @return void
	 */
	public function testABadCategoryIsRefused(): void {
		$service = $this->service();
		foreach (['', '   ', str_repeat('c', 65)] as $category) {
			try {
				$service->follow('pieter', $category);
				$this->fail('Followed ' . json_encode($category));
			} catch (InvalidArgumentException $e) {
				$this->assertSame('A category of 1 to 64 characters is required', $e->getMessage());
			}
		}

		$this->assertSame([], $this->rows);
	}//end testABadCategoryIsRefused()
}//end class
