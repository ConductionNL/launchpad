<?php

/**
 * DashboardListFilters
 *
 * The owner, group and publication-status filters of one dashboard list run.
 *
 * @category  Command
 * @package   OCA\LaunchPad\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/cli-commands/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

use Symfony\Component\Console\Input\InputInterface;

/**
 * The filters of one dashboard list run; null when not given.
 *
 * @spec openspec/specs/cli-commands/spec.md
 */
final class DashboardListFilters {
	/**
	 * The owning user id (`--user`).
	 *
	 * @var string|null
	 */
	public readonly string|null $user;

	/**
	 * The group id (`--group`).
	 *
	 * @var string|null
	 */
	public readonly string|null $group;

	/**
	 * The publication status (`--status`).
	 *
	 * @var string|null
	 */
	public readonly string|null $status;

	/**
	 * Read the filters from the command options.
	 *
	 * @param InputInterface $input CLI input.
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	public function __construct(InputInterface $input) {
		$this->user = self::optionToString(value: $input->getOption(name: 'user'));
		$this->group = self::optionToString(value: $input->getOption(name: 'group'));
		$this->status = self::optionToString(value: $input->getOption(name: 'status'));
	}//end __construct()

	/**
	 * An option value as a string, or null when not given.
	 *
	 * @param mixed $value The option value.
	 *
	 * @return string|null The value.
	 */
	private static function optionToString(mixed $value): string|null {
		if ($value === null) {
			return null;
		}

		return (string)$value;
	}//end optionToString()
}//end class
