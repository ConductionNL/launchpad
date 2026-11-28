<?php

/**
 * PreferenceKey
 *
 * The user value a LaunchPad preference key is stored under.
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
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Maps a preference key as the browser names it to the user value it is
 * stored under, so every reader reads what `GET /api/preferences/{key}` reads.
 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */
class PreferenceKey {
	/**
	 * Lower-case the key and keep only `[a-z0-9-]`, at most 64 characters,
	 * so callers cannot reach user values outside the `pref_` namespace.
	 *
	 * @param string $key The raw key.
	 *
	 * @return string The sanitised key, or '' when nothing safe remains.
	 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
	 */
	public function safe(string $key): string {
		$safe = preg_replace(pattern: '/[^a-z0-9-]/', replacement: '', subject: strtolower($key));
		return substr((string)$safe, offset: 0, length: 64);
	}//end safe()

	/**
	 * The user value the key is stored under: `pref_` plus the sanitised key.
	 *
	 * @param string $key The preference key as the browser names it.
	 *
	 * @return string The stored key, or '' when nothing safe remains.
	 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
	 */
	public function stored(string $key): string {
		$safe = $this->safe(key: $key);
		if ($safe === '') {
			return '';
		}

		return 'pref_'.$safe;
	}//end stored()
}//end class
