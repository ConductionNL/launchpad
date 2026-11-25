<?php

/**
 * QuicklinksContentValidator
 *
 * Server-side validator for the `quicklinks` widget content blob.
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
 * Validates `quicklinks` widget content before a placement is saved.
 *
 * Every link URL is required, at most 2048 characters, and starts with
 * `http://`, `https://` or a single `/` (a relative Nextcloud address).
 * Anything else, such as `javascript:` or `data:`, is refused as "Invalid URL".
 *
 * @spec openspec/specs/quicklinks-widget/spec.md#requirement-req-qlnk-008-url-sanitisation-on-save
 */
class QuicklinksContentValidator {

	/**
	 * The longest URL a link may carry.
	 */
	private const MAX_URL_LENGTH = 2048;

	/**
	 * Validate a quicklinks placement's content.
	 *
	 * @param array $content The placement content.
	 *
	 * @return void
	 * @throws InvalidArgumentException When a link URL is refused.
	 *
	 * @spec openspec/specs/quicklinks-widget/spec.md#requirement-req-qlnk-008-url-sanitisation-on-save
	 */
	public function validate(array $content): void {
		$links = ($content['links'] ?? []);
		if (is_array($links) === false) {
			throw new InvalidArgumentException(message: 'Links must be a list');
		}

		foreach ($links as $link) {
			$url = null;
			if (is_array($link) === true) {
				$url = ($link['url'] ?? null);
			}

			if ($this->isAllowedUrl(url: $url) === false) {
				throw new InvalidArgumentException(message: 'Invalid URL');
			}
		}
	}//end validate()

	/**
	 * Whether a link URL is a non-empty http(s) address or relative Nextcloud path.
	 *
	 * @param mixed $url The link's `url` value.
	 *
	 * @return bool True when the URL may be saved.
	 *
	 * @spec openspec/specs/quicklinks-widget/spec.md#requirement-req-qlnk-008-url-sanitisation-on-save
	 */
	private function isAllowedUrl(mixed $url): bool {
		if (is_string($url) === false) {
			return false;
		}

		$url = trim($url);
		if ($url === '' || strlen($url) > self::MAX_URL_LENGTH) {
			return false;
		}

		return preg_match('#^https?://[^\s/]#i', $url) === 1
			|| preg_match('#^/(?![/\\\\])#', $url) === 1;
	}//end isAllowedUrl()
}//end class
