<?php

/**
 * DisplayNamePageCollector
 *
 * Collects one people-widget page from users streamed in display-name
 * order: disabled users skipped when asked, the window's offset skipped,
 * then users taken until the page is full.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IUser;

/**
 * Collects one page of users in display-name order.
 *
 * Built by {@see PeopleWidgetService} for one page; never shared.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
final class DisplayNamePageCollector {
	/**
	 * Matching users still to skip before the page starts.
	 *
	 * @var integer
	 */
	private int $toSkip;

	/**
	 * The page collected so far.
	 *
	 * @var IUser[]
	 */
	private array $page = [];

	/**
	 * Constructor.
	 *
	 * @param PeoplePage $window Whether disabled users are skipped, the page
	 *                           size (1..MAX_LIMIT) and the offset (>= 0).
	 */
	public function __construct(
		private readonly PeoplePage $window,
	) {
		$this->toSkip = $window->offset;
	}//end __construct()

	/**
	 * Take users from a backend batch until the page is full.
	 *
	 * @param IUser[] $users One batch, in display-name order.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function takeFrom(array $users): void {
		foreach ($users as $user) {
			if ($this->take(user: $user) === true) {
				return;
			}
		}
	}//end takeFrom()

	/**
	 * Whether the page holds `limit` users.
	 *
	 * @return bool True when full.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function isFull(): bool {
		return count(value: $this->page) >= $this->window->limit;
	}//end isFull()

	/**
	 * The collected page.
	 *
	 * @return IUser[] The users, in display-name order.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function page(): array {
		return $this->page;
	}//end page()

	/**
	 * Skip a disabled user (when asked) or one inside the offset, else add
	 * it to the page.
	 *
	 * @param IUser $user The next user.
	 *
	 * @return bool True when the page is now full.
	 */
	private function take(IUser $user): bool {
		if ($this->window->excludeDisabled === true && $user->isEnabled() === false) {
			return false;
		}

		if ($this->toSkip > 0) {
			$this->toSkip--;
			return false;
		}

		$this->page[] = $user;

		return $this->isFull();
	}//end take()
}//end class
