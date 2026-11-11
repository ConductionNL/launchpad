<?php

/**
 * CommandStreams
 *
 * The input and output of one command run, kept together for the helpers that print to both.
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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The input and output of one command run.
 *
 * @spec openspec/specs/cli-commands/spec.md
 */
final class CommandStreams {
	/**
	 * Constructor.
	 *
	 * @param InputInterface  $input  CLI input.
	 * @param OutputInterface $output CLI output.
	 */
	public function __construct(
		public readonly InputInterface $input,
		public readonly OutputInterface $output,
	) {
	}//end __construct()
}//end class
