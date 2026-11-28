<?php

/**
 * FirstPaintNotices
 *
 * The notice banners the mounted page shows above the dashboard
 * (engagement-announcements REQ-ANN-005), for the first-paint shell
 * (first-paint-skeleton REQ-FPS-003): without them in the shell the page drops
 * by their height at the hand-over. They come from the reads the page's
 * initial state already carries for NoticeBannerRegion (`/api/announcements`
 * and the reader's `dismissed-notices` preference, WorkspaceApiReads), with
 * the region's own rule: a notice, not ended, not dismissed by this reader.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use Exception;

/**
 * Picks the notices the shell prints.
 *
 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-8
 */
class FirstPaintNotices {

	/**
	 * The notices as NoticeBannerRegion shows them on a first load.
	 *
	 * @param list<array<string, mixed>> $reads The page's API reads (path, params, data), or none.
	 * @param int                        $now   The moment of the request, as a Unix time.
	 *
	 * @return list<array{uuid: string, title: string, body: string, warning: bool}>
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-8
	 */
	public function shown(array $reads, int $now): array {
		$announcements = $this->dataOf(reads: $reads, path: '/api/announcements');
		$dismissed     = $this->dismissed(data: $this->dataOf(reads: $reads, path: '/api/preferences/dismissed-notices'));
		$shown         = [];
		foreach ((array) ($announcements['announcements'] ?? []) as $item) {
			if (is_array($item) === false || ($item['kind'] ?? '') !== 'notice') {
				continue;
			}

			$uuid = (string) ($item['uuid'] ?? '');
			if (in_array($uuid, $dismissed, true) === true || $this->ended(expiresAt: $item['expiresAt'] ?? null, now: $now) === true) {
				continue;
			}

			$shown[] = [
				'uuid'    => $uuid,
				'title'   => (string) ($item['title'] ?? ''),
				'body'    => (string) ($item['body'] ?? ''),
				'warning' => ($item['level'] ?? '') === 'warning',
			];
		}

		return $shown;
	}//end shown()

	/**
	 * The data of the read with this path, [] when the page carries none.
	 *
	 * @param list<array<string, mixed>> $reads The reads.
	 * @param string                     $path  The path.
	 *
	 * @return array<string, mixed>
	 */
	private function dataOf(array $reads, string $path): array {
		foreach ($reads as $read) {
			if (is_array($read) === true && ($read['path'] ?? '') === $path) {
				return (array) ($read['data'] ?? []);
			}
		}

		return [];
	}//end dataOf()

	/**
	 * The uuids this reader dismissed: the preference's value is a JSON list.
	 *
	 * @param array<string, mixed> $data The preference read.
	 *
	 * @return list<string>
	 */
	private function dismissed(array $data): array {
		$value = json_decode((string) ($data['value'] ?? '[]'), true);
		if (is_array($value) === false) {
			return [];
		}

		return array_values(array_filter($value, 'is_string'));
	}//end dismissed()

	/**
	 * Whether a notice has ended: an end time that is not later than now.
	 *
	 * @param mixed $expiresAt The end time as the API gives it, or none.
	 * @param int   $now       The moment of the request.
	 *
	 * @return bool
	 */
	private function ended(mixed $expiresAt, int $now): bool {
		if (is_string($expiresAt) === false || $expiresAt === '') {
			return false;
		}

		try {
			return (new DateTimeImmutable($expiresAt))->getTimestamp() <= $now;
		} catch (Exception) {
			return false;
		}
	}//end ended()
}//end class
