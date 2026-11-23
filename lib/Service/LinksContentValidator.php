<?php

/**
 * LinksContentValidator
 *
 * Server-side validator for the `links` widget content blob.
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * Validates `links` widget content before a placement is saved.
 *
 * Every link URL is required and must be an http(s) address, a root-relative
 * path without `..`, or a single `../` step; anything else (`javascript:`,
 * `data:`, `file:`, a `../` chain) is refused. The column count, when given,
 * is a whole number from 1 to 6.
 *
 * @spec openspec/specs/links-widget/spec.md#requirement-req-lnks-007-url-sanitisation-and-validation
 */
class LinksContentValidator {

	/**
	 * The fewest columns the grid takes.
	 */
	private const MIN_COLUMNS = 1;

	/**
	 * The most columns the grid takes.
	 */
	private const MAX_COLUMNS = 6;

	/**
	 * Validate a links placement's content.
	 *
	 * @param array $content The placement content.
	 *
	 * @return void
	 * @throws InvalidArgumentException When a URL or the column count is refused.
	 *
	 * @spec openspec/specs/links-widget/spec.md#requirement-req-lnks-007-url-sanitisation-and-validation
	 */
	public function validate(array $content): void {
		if (array_key_exists('columns', $content) === true) {
			$this->checkColumns(columns: $content['columns']);
		}

		$sections = ($content['sections'] ?? []);
		if (is_array($sections) === false) {
			throw new InvalidArgumentException(message: 'Sections must be a list');
		}

		foreach ($sections as $section) {
			$links = [];
			if (is_array($section) === true && is_array($section['links'] ?? null) === true) {
				$links = $section['links'];
			}

			foreach ($links as $link) {
				$url = null;
				if (is_array($link) === true) {
					$url = ($link['url'] ?? null);
				}

				$this->checkUrl(url: $url);
			}
		}
	}//end validate()

	/**
	 * The column count is a whole number from 1 to 6.
	 *
	 * @param mixed $columns The `columns` value.
	 *
	 * @return void
	 * @throws InvalidArgumentException When it is out of range or not a whole number.
	 *
	 * @spec openspec/specs/links-widget/spec.md#requirement-req-lnks-006-multi-column-grid-layout
	 */
	private function checkColumns(mixed $columns): void {
		$valid = filter_var(
			$columns,
			FILTER_VALIDATE_INT,
			['options' => ['min_range' => self::MIN_COLUMNS, 'max_range' => self::MAX_COLUMNS]]
		);
		if ($valid === false) {
			throw new InvalidArgumentException(message: 'Columns must be a whole number from 1 to 6');
		}
	}//end checkColumns()

	/**
	 * A link URL is required and must be http(s) or a safe relative path.
	 *
	 * @param mixed $url The link's `url` value.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the URL is missing or unsafe.
	 *
	 * @spec openspec/specs/links-widget/spec.md#requirement-req-lnks-007-url-sanitisation-and-validation
	 */
	private function checkUrl(mixed $url): void {
		if (is_string($url) === false || trim($url) === '') {
			throw new InvalidArgumentException(message: 'Link URL is required');
		}

		$url = trim($url);
		$safe = preg_match('#^https?://[^\s/]#i', $url) === 1
			|| (preg_match('#^/(?![/\\\\])#', $url) === 1 && str_contains($url, '..') === false)
			|| (str_starts_with($url, '../') === true && str_contains(substr($url, 3), '..') === false);
		if ($safe === false) {
			throw new InvalidArgumentException(message: 'Invalid URL — use HTTP(S) or relative paths.');
		}
	}//end checkUrl()
}//end class
