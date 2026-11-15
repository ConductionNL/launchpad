<?php

/**
 * ConfluenceImportRequest
 *
 * What a Confluence import run asks for: the archive, the parent path, the importing user and whether it is a dry run.
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
 * @spec openspec/specs/confluence-html-import/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

use Symfony\Component\Console\Input\InputInterface;

/**
 * The options of one Confluence import run.
 *
 * @spec openspec/specs/confluence-html-import/spec.md
 */
final class ConfluenceImportRequest {
	/**
	 * Path to the export ZIP, empty when `--file` is missing.
	 *
	 * @var string
	 */
	public readonly string $file;

	/**
	 * Slug-chain path of the parent dashboard, empty for the root.
	 *
	 * @var string
	 */
	public readonly string $parentPath;

	/**
	 * The user the imported dashboards are attributed to.
	 *
	 * @var string
	 */
	public readonly string $userId;

	/**
	 * Whether to inspect the archive without creating dashboards.
	 *
	 * @var boolean
	 */
	public readonly bool $isDryRun;

	/**
	 * Read the request from the command options.
	 *
	 * @param InputInterface $input CLI input.
	 *
	 * @spec openspec/specs/confluence-html-import/spec.md
	 */
	public function __construct(InputInterface $input) {
		$this->file = (string)($input->getOption(name: 'file') ?? '');
		$this->parentPath = (string)($input->getOption(name: 'parent-path') ?? '');
		$this->userId = (string)($input->getOption(name: 'user') ?? 'cli');
		$this->isDryRun = (bool)$input->getOption(name: 'dry-run');
	}//end __construct()
}//end class
