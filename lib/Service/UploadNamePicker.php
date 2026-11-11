<?php

/**
 * UploadNamePicker
 *
 * Picks the name a files-widget upload is stored under: separators and
 * NUL replaced, and ` (1)`, ` (2)` … inserted before the extension while
 * the name is taken (REQ-FLS-007).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/files-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\Files\Folder;

/**
 * Picks a free, safe name for an upload inside a folder.
 *
 * Built by {@see FilesWidgetService} for each upload; holds no state.
 *
 * @spec openspec/specs/files-widget/spec.md
 */
final class UploadNamePicker {
	/**
	 * Find a free filename inside the target folder by appending
	 * `(1)`, `(2)` etc. when the desired name is taken (REQ-FLS-007).
	 *
	 * @param Folder $folder Target folder.
	 * @param string $desired Desired leaf name.
	 *
	 * @return string The free name.
	 *
	 * @spec openspec/specs/files-widget/spec.md
	 */
	public function availableName(Folder $folder, string $desired): string {
		$sanitised = self::sanitiseUploadName(desired: $desired);
		if ($folder->nodeExists(path: $sanitised) === false) {
			return $sanitised;
		}

		return $this->firstFreeNumberedName(folder: $folder, name: $sanitised);
	}//end availableName()

	/**
	 * Replace path separators and NUL with `_`; an empty, `.` or `..`
	 * result becomes `upload`.
	 *
	 * @param string $desired Desired leaf name.
	 *
	 * @return string The safe leaf name.
	 */
	private static function sanitiseUploadName(string $desired): string {
		$sanitised = str_replace(search: ['/', '\\', "\0"], replace: '_', subject: $desired);
		if ($sanitised === '' || $sanitised === '.' || $sanitised === '..') {
			return 'upload';
		}

		return $sanitised;
	}//end sanitiseUploadName()

	/**
	 * The first of `name (1)` … `name (999)` that is free in the folder,
	 * else a `name (<unique id>)` variant.
	 *
	 * @param Folder $folder Target folder.
	 * @param string $name   The taken, sanitised name.
	 *
	 * @return string The free name.
	 */
	private function firstFreeNumberedName(Folder $folder, string $name): string {
		for ($i = 1; $i <= 999; $i++) {
			$candidate = self::numberedName(name: $name, number: (string)$i);
			if ($folder->nodeExists(path: $candidate) === false) {
				return $candidate;
			}
		}

		return self::numberedName(name: $name, number: uniqid());
	}//end firstFreeNumberedName()

	/**
	 * Insert ` (<number>)` before a name's extension; a leading-dot name
	 * or one without a dot gets it at the end.
	 *
	 * @param string $name   The sanitised name.
	 * @param string $number The number to insert.
	 *
	 * @return string The numbered name.
	 */
	private static function numberedName(string $name, string $number): string {
		$extPos = strrpos(haystack: $name, needle: '.');
		if ($extPos !== false && $extPos !== 0) {
			return substr(string: $name, offset: 0, length: $extPos) . ' (' . $number . ')' . substr(string: $name, offset: $extPos);
		}

		return $name . ' (' . $number . ')';
	}//end numberedName()
}//end class
